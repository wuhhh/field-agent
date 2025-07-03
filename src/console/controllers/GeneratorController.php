<?php

namespace craftcms\fieldagent\console\controllers;

use Craft;
use craft\console\Controller;
use craftcms\fieldagent\Plugin;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Generator controller
 *
 * Refactored to delegate functionality to specialized services
 */
class GeneratorController extends Controller
{
    public $defaultAction = 'help';

    /**
     * @var bool Whether to enable debug mode
     */
    public $debug = false;

    /**
     * @var bool Whether to cleanup test data after completion
     */
    public $cleanup = false;

    /**
     * @var bool Whether to only generate config without creating fields (dry run)
     */
    public $dryRun = false;

    /**
     * @var bool Whether to confirm destructive operations
     */
    public $confirm = false;

    /**
     * @var bool Whether to force operations without confirmation
     */
    public $force = false;

    /**
     * @var string|null Custom output path for generated config
     */
    public $output = null;

    /**
     * Generate fields from a JSON configuration file or stored config
     *
     * @param string $config The path to the JSON config file or stored config name
     * @return int
     */
    public function actionGenerate(string $config): int
    {
        $plugin = Plugin::getInstance();
        $configService = $plugin->configurationService;

        $configData = $configService->loadConfig($config);
        if (!$configData) {
            $this->stderr("Config not found: $config\n", Console::FG_RED);
            $this->stderr("Use 'field-agent/generator/list' to see available presets and configs.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Store this config for future reference if it was a file
        if (file_exists($config)) {
            $configName = pathinfo($config, PATHINFO_FILENAME);
            $configService->storeConfig($configName, $configData);
            $this->stdout("Config stored for future use.\n", Console::FG_CYAN);
        }

        return $this->executeFieldGeneration('generate', $config, $configData);
    }

    /**
     * Execute field generation from config data
     */
    private function executeFieldGeneration(string $type, string $source, array $configData): int
    {
        $plugin = Plugin::getInstance();
        $rollbackService = $plugin->rollbackService;
        $fieldGeneratorService = $plugin->fieldGeneratorService;

        // Validate the configuration first
        $validation = $plugin->schemaValidationService->validateLLMOutput($configData);
        if (!$validation['valid']) {
            $this->stderr("Invalid configuration:\n", Console::FG_RED);
            foreach ($validation['errors'] as $error) {
                $this->stderr("  - $error\n");
            }
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("\n=== Field Generation Starting ===\n", Console::FG_CYAN);
        $this->stdout("Type: $type\n");
        $this->stdout("Source: $source\n");
        $this->stdout("Fields to create: " . $this->count($configData['fields'] ?? []) . "\n");
        $this->stdout("Sections to create: " . $this->count($configData['sections'] ?? []) . "\n");
        $this->stdout("Entry types to create: " . $this->count($configData['entryTypes'] ?? []) . "\n\n");

        // Create fields
        $createdFields = [];
        if (isset($configData['fields'])) {
            $this->stdout("Creating fields...\n", Console::FG_YELLOW);
            foreach ($configData['fields'] as $fieldConfig) {
                $field = $fieldGeneratorService->createFieldFromConfig($fieldConfig);
                if ($field) {
                    $this->stdout("  ✓ Created field: {$field->name} ({$field->handle})\n", Console::FG_GREEN);
                    $createdFields[] = [
                        'id' => $field->id,
                        'name' => $field->name,
                        'handle' => $field->handle,
                        'type' => get_class($field)
                    ];
                } else {
                    $this->stderr("  ✗ Failed to create field: {$fieldConfig['name']}\n", Console::FG_RED);
                }
            }
        }

        // Create sections and entry types
        $createdSections = [];
        $createdEntryTypes = [];
        if (isset($configData['sections'])) {
            $this->stdout("\nCreating sections...\n", Console::FG_YELLOW);
            $sectionService = $plugin->sectionGeneratorService;

            foreach ($configData['sections'] as $sectionConfig) {
                $section = $sectionService->createSectionFromConfig($sectionConfig, $createdEntryTypes);
                if ($section) {
                    $this->stdout("  ✓ Created section: {$section->name} ({$section->handle})\n", Console::FG_GREEN);
                    $createdSections[] = [
                        'id' => $section->id,
                        'name' => $section->name,
                        'handle' => $section->handle,
                        'type' => $section->type
                    ];
                } else {
                    $this->stderr("  ✗ Failed to create section: {$sectionConfig['name']}\n", Console::FG_RED);
                }
            }
        }

        // Record the operation for rollback
        $operationId = $rollbackService->recordOperation($type, $source, [
            'fields' => $createdFields,
            'sections' => $createdSections,
            'entryTypes' => $createdEntryTypes,
        ]);

        $this->stdout("\n=== Generation Complete ===\n", Console::FG_GREEN);
        $this->stdout("Created {$this->count($createdFields)} fields\n");
        $this->stdout("Created {$this->count($createdSections)} sections\n");
        $this->stdout("Created {$this->count($createdEntryTypes)} entry types\n");
        $this->stdout("\nOperation ID: $operationId\n", Console::FG_CYAN);
        $this->stdout("Use 'field-agent/generator/rollback $operationId' to undo this operation.\n");

        return ExitCode::OK;
    }

    /**
     * Generate fields from a natural language prompt using AI
     */
    public function actionPrompt(string $prompt, string $provider = 'anthropic'): int
    {
        $plugin = Plugin::getInstance();

        // Use the LLM Operations Service for context-aware generation
        $llmOperationsService = $plugin->llmOperationsService;

        try {
            $this->stdout("\n🤖 Processing your request...\n", Console::FG_CYAN);

            if ($this->debug) {
                $this->stdout("\nDebug mode enabled - showing full request/response\n", Console::FG_YELLOW);
            }

            // Generate operations from prompt
            $result = $llmOperationsService->generateOperationsFromPrompt($prompt, $provider, $this->debug);

            if (!$result['success']) {
                $this->stderr("\n❌ Failed to generate operations: {$result['error']}\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            // If dry run, output the operations and exit
            if ($this->dryRun) {
                $this->stdout("\n📋 Generated Operations (Dry Run):\n", Console::FG_YELLOW);
                $this->stdout(json_encode($result['operations'], JSON_PRETTY_PRINT) . "\n");

                if ($this->output) {
                    file_put_contents($this->output, json_encode($result['operations'], JSON_PRETTY_PRINT));
                    $this->stdout("\nOperations saved to: {$this->output}\n", Console::FG_GREEN);
                }

                return ExitCode::OK;
            }

            // Execute the operations
            $this->stdout("\n⚡ Executing operations...\n\n", Console::FG_CYAN);

            $operationsExecutor = $plugin->operationsExecutorService;
            $executionResults = $operationsExecutor->executeOperations(['operations' => $result['operations']]);

            // Display results
            $this->displayOperationResults($executionResults);

            // Record the operation
            $operationId = $plugin->rollbackService->recordOperationFromResults('prompt', $prompt, $executionResults);

            $this->stdout("\n✅ Operations completed successfully!\n", Console::FG_GREEN);
            $this->stdout("\nOperation ID: $operationId\n", Console::FG_CYAN);
            $this->stdout("Use 'field-agent/generator/rollback $operationId' to undo this operation.\n");

            return ExitCode::OK;

        } catch (\Exception $e) {
            $this->stderr("\n❌ Error: " . $e->getMessage() . "\n", Console::FG_RED);

            if ($this->debug) {
                $this->stderr("\nStack trace:\n" . $e->getTraceAsString() . "\n");
            }

            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * List stored configurations and presets
     */
    public function actionList(): int
    {
        $plugin = Plugin::getInstance();
        $configService = $plugin->configurationService;

        $configs = $configService->listStoredConfigs();
        $presets = $configService->listBuiltInPresets();

        // Show built-in presets first
        if (!empty($presets)) {
            $this->stdout("Built-in presets:\n\n", Console::FG_GREEN);
            foreach ($presets as $preset) {
                $this->stdout("  📦 {$preset['filename']} - {$preset['name']}: {$preset['description']}\n");
            }
        }

        // Then show stored configurations
        if (!empty($configs)) {
            $this->stdout("\nStored configurations:\n\n", Console::FG_YELLOW);
            foreach ($configs as $config) {
                $date = date('Y-m-d H:i:s', $config['created']);
                $size = round($config['size'] / 1024, 2);
                $this->stdout("  📄 {$config['filename']} ({$size}KB) - $date\n");
            }
        }

        if (empty($presets) && empty($configs)) {
            $this->stdout("No configurations or presets found.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $this->stdout("\nUse 'field-agent/generator/generate <preset-key|filename>' to use a preset or config.\n", Console::FG_CYAN);
        return ExitCode::OK;
    }

    /**
     * Test LLM API connection
     */
    public function actionTestLlm(string $provider = 'anthropic'): int
    {
        $plugin = Plugin::getInstance();
        $llmService = $plugin->llmIntegrationService;

        $this->stdout("\nTesting $provider API connection...\n", Console::FG_YELLOW);

        $result = $llmService->testConnection($provider, $this->debug);

        if ($result['success']) {
            $this->stdout("\n✓ API connection successful!\n", Console::FG_GREEN);
            $this->stdout("Provider: {$result['provider']}\n");
            // $this->stdout("Model: {$result['model']}\n"); // Not implemented
            if (isset($result['response_time'])) {
                $this->stdout("Response time: {$result['response_time']}ms\n");
            }
            return ExitCode::OK;
        } else {
            $this->stderr("\n✗ API connection failed!\n", Console::FG_RED);
            $this->stderr("Error: {$result['error']}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Check API keys
     */
    public function actionCheckKeys(): int
    {
        $plugin = Plugin::getInstance();
        $llmService = $plugin->llmIntegrationService;

        $this->stdout("\nChecking API keys...\n", Console::FG_YELLOW);

        $keys = $llmService->checkApiKeys();

        foreach ($keys as $provider => $status) {
            if ($status['configured']) {
                $this->stdout("✓ $provider: Configured", Console::FG_GREEN);
                if ($status['masked']) {
                    $this->stdout(" ({$status['masked']})", Console::FG_GREY);
                }
                $this->stdout("\n");
            } else {
                $this->stdout("✗ $provider: Not configured\n", Console::FG_RED);
            }
        }

        return ExitCode::OK;
    }

    /**
     * Export prompt and schema for manual testing
     */
    public function actionExportPrompt(): int
    {
        $plugin = Plugin::getInstance();
        $llmOperationsService = $plugin->llmOperationsService;

        $this->stdout("\nExporting LLM prompt and schema...\n", Console::FG_YELLOW);

        $result = $llmOperationsService->exportPromptAndSchema();

        $outputDir = Craft::$app->path->getRuntimePath() . '/field-agent-exports';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $timestamp = date('Y-m-d-H-i-s');

        // Save system prompt
        $systemPromptFile = "$outputDir/system-prompt-$timestamp.txt";
        file_put_contents($systemPromptFile, $result['systemPrompt']);
        $this->stdout("System prompt saved to: $systemPromptFile\n", Console::FG_GREEN);

        // Save schema
        $schemaFile = "$outputDir/operations-schema-$timestamp.json";
        file_put_contents($schemaFile, json_encode($result['schema'], JSON_PRETTY_PRINT));
        $this->stdout("Operations schema saved to: $schemaFile\n", Console::FG_GREEN);

        // Save example prompt
        $exampleFile = "$outputDir/example-prompt-$timestamp.txt";
        file_put_contents($exampleFile, $result['examplePrompt']);
        $this->stdout("Example prompt saved to: $exampleFile\n", Console::FG_GREEN);

        $this->stdout("\nYou can use these files to test the LLM API manually.\n", Console::FG_CYAN);

        return ExitCode::OK;
    }

    /**
     * Rollback a specific operation
     */
    public function actionRollback(string $operationId): int
    {
        $plugin = Plugin::getInstance();
        $rollbackService = $plugin->rollbackService;

        $this->stdout("\nRolling back operation: $operationId\n", Console::FG_YELLOW);

        try {
            $result = $rollbackService->rollbackOperation($operationId);

            if (!$result) {
                $this->stderr("Operation not found or already rolled back.\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $this->stdout("\n✓ Rollback completed successfully!\n", Console::FG_GREEN);

            // Display what was deleted
            $deletedCount = 0;

            if (!empty($result['deleted']['fields'])) {
                $this->stdout("\nDeleted fields:\n", Console::FG_YELLOW);
                foreach ($result['deleted']['fields'] as $field) {
                    $this->stdout("  - {$field['name']} ({$field['handle']})\n");
                    $deletedCount++;
                }
            }

            if (!empty($result['deleted']['sections'])) {
                $this->stdout("\nDeleted sections:\n", Console::FG_YELLOW);
                foreach ($result['deleted']['sections'] as $section) {
                    $this->stdout("  - {$section['name']} ({$section['handle']})\n");
                    $deletedCount++;
                }
            }

            if (!empty($result['deleted']['entryTypes'])) {
                $this->stdout("\nDeleted entry types:\n", Console::FG_YELLOW);
                foreach ($result['deleted']['entryTypes'] as $entryType) {
                    $this->stdout("  - {$entryType['name']} ({$entryType['handle']})\n");
                    $deletedCount++;
                }
            }

            if (!empty($result['deleted']['categoryGroups'])) {
                $this->stdout("\nDeleted category groups:\n", Console::FG_YELLOW);
                foreach ($result['deleted']['categoryGroups'] as $group) {
                    $this->stdout("  - {$group['name']} ({$group['handle']})\n");
                    $deletedCount++;
                }
            }

            if (!empty($result['deleted']['tagGroups'])) {
                $this->stdout("\nDeleted tag groups:\n", Console::FG_YELLOW);
                foreach ($result['deleted']['tagGroups'] as $group) {
                    $this->stdout("  - {$group['name']} ({$group['handle']})\n");
                    $deletedCount++;
                }
            }

            $this->stdout("\nTotal items deleted: $deletedCount\n", Console::FG_CYAN);

            return ExitCode::OK;

        } catch (\Exception $e) {
            $this->stderr("\nRollback failed: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Rollback the last operation
     */
    public function actionRollbackLast(): int
    {
        $plugin = Plugin::getInstance();
        $rollbackService = $plugin->rollbackService;

        // Get the most recent operation
        $operations = $rollbackService->getOperations();
        if (empty($operations)) {
            $this->stdout("No operations to rollback.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        // Find the most recent non-rolled-back operation
        $lastOperation = null;
        foreach ($operations as $operation) {
            if (!$operation['rolled_back']) {
                $lastOperation = $operation;
                break;
            }
        }

        if (!$lastOperation) {
            $this->stdout("No active operations to rollback (all operations already rolled back).\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $this->stdout("\nLast operation:\n", Console::FG_CYAN);
        $this->stdout("  ID: {$lastOperation['id']}\n");
        $this->stdout("  Type: {$lastOperation['type']}\n");
        $this->stdout("  Source: {$lastOperation['source']}\n");
        $this->stdout("  Created: " . date('Y-m-d H:i:s', $lastOperation['timestamp']) . "\n");

        if (!$this->confirm("\nRollback this operation?")) {
            $this->stdout("Rollback cancelled.\n");
            return ExitCode::OK;
        }

        return $this->actionRollback($lastOperation['id']);
    }

    /**
     * Rollback all operations
     */
    public function actionRollbackAll(): int
    {
        $plugin = Plugin::getInstance();
        $rollbackService = $plugin->rollbackService;

        $operations = $rollbackService->getOperations();
        $activeOperations = array_filter($operations, function($op) {
            return !$op['rolled_back'];
        });

        if (empty($activeOperations)) {
            $this->stdout("No active operations to rollback.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $count = count($activeOperations);
        $this->stdout("\nFound $count active operations.\n", Console::FG_YELLOW);

        if (!$this->force && !$this->confirm("Rollback ALL operations? This cannot be undone.")) {
            $this->stdout("Rollback cancelled.\n");
            return ExitCode::OK;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($activeOperations as $operation) {
            $this->stdout("\nRolling back {$operation['id']}...", Console::FG_YELLOW);

            try {
                $result = $rollbackService->rollbackOperation($operation['id']);
                if ($result) {
                    $this->stdout(" ✓\n", Console::FG_GREEN);
                    $successCount++;
                } else {
                    $this->stdout(" ✗ (not found or already rolled back)\n", Console::FG_RED);
                    $failCount++;
                }
            } catch (\Exception $e) {
                $this->stdout(" ✗ (error: {$e->getMessage()})\n", Console::FG_RED);
                $failCount++;
            }
        }

        $this->stdout("\n=== Rollback Complete ===\n", Console::FG_CYAN);
        $this->stdout("Success: $successCount\n", Console::FG_GREEN);
        if ($failCount > 0) {
            $this->stdout("Failed: $failCount\n", Console::FG_RED);
        }

        return $failCount > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * List all field generation operations
     */
    public function actionOperations(): int
    {
        $plugin = Plugin::getInstance();
        $rollbackService = $plugin->rollbackService;

        $operations = $rollbackService->getOperations();

        if (empty($operations)) {
            $this->stdout("No operations found.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $this->stdout("\nField Generation Operations:\n\n", Console::FG_CYAN);

        foreach ($operations as $operation) {
            $timestamp = date('Y-m-d H:i:s', $operation['timestamp']);
            $status = $operation['rolled_back'] ? '↩️  Rolled back' : '✅ Active';

            $this->stdout("ID: {$operation['id']}\n");
            $this->stdout("  Type: {$operation['type']}\n");
            $this->stdout("  Source: {$operation['source']}\n");
            $this->stdout("  Created: $timestamp\n");
            $this->stdout("  Status: $status\n");

            if ($operation['rolled_back'] && isset($operation['rolled_back_at'])) {
                $rolledBackAt = date('Y-m-d H:i:s', $operation['rolled_back_at']);
                $this->stdout("  Rolled back: $rolledBackAt\n");
            }

            // Show counts
            $counts = [];
            if (isset($operation['data']['fields'])) {
                $counts[] = count($operation['data']['fields']) . ' fields';
            }
            if (isset($operation['data']['sections'])) {
                $counts[] = count($operation['data']['sections']) . ' sections';
            }
            if (isset($operation['data']['entryTypes'])) {
                $counts[] = count($operation['data']['entryTypes']) . ' entry types';
            }

            if (!empty($counts)) {
                $this->stdout("  Created: " . implode(', ', $counts) . "\n");
            }

            $this->stdout("\n");
        }

        return ExitCode::OK;
    }

    /**
     * List all available test suites
     */
    public function actionTestList(): int
    {
        $plugin = Plugin::getInstance();
        $testingService = $plugin->testingService;

        $testSuites = $testingService->discoverTestSuites();

        if (empty($testSuites)) {
            $this->stdout("No test suites found.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $this->stdout("\n📋 Available Test Suites\n", Console::FG_CYAN);
        $this->stdout("========================\n\n");

        foreach ($testSuites as $category => $tests) {
            $categoryName = $testingService->getCategoryDisplayName($category);
            $this->stdout("📁 $categoryName\n", Console::FG_YELLOW);

            foreach ($tests as $test) {
                $this->stdout("   - {$test['filename']}", Console::FG_GREEN);
                $this->stdout(" - {$test['description']}\n", Console::FG_GREY);

                if ($test['prerequisite']) {
                    $this->stdout("     ⚠️  Requires: {$test['prerequisite']}\n", Console::FG_YELLOW);
                }
            }

            $this->stdout("\n");
        }

        $this->stdout("To run a specific test:\n", Console::FG_CYAN);
        $this->stdout("  field-agent/generator/test-run <test-name>\n\n");

        $this->stdout("To run all tests in a category:\n", Console::FG_CYAN);
        $this->stdout("  field-agent/generator/test-suite <category>\n\n");

        return ExitCode::OK;
    }

    /**
     * Run a specific test or all tests
     */
    public function actionTestRun($testName = null): int
    {
        if (!$testName) {
            $this->stderr("Please specify a test name. Use 'field-agent/generator/test-list' to see available tests.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $plugin = Plugin::getInstance();
        $testingService = $plugin->testingService;

        $testFile = $testingService->findTestFile($testName);
        if (!$testFile) {
            $this->stderr("Test not found: $testName\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("\n🧪 Running test: $testName\n", Console::FG_CYAN);
        $this->stdout("=====================================\n\n");

        $result = $testingService->executeTestFile($testFile, $this->cleanup);

        if ($result['success']) {
            $this->stdout("\n✅ Test passed!\n", Console::FG_GREEN);
            return ExitCode::OK;
        } else {
            $this->stderr("\n❌ Test failed: {$result['message']}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Run all tests in a specific category
     */
    public function actionTestSuite($category = null): int
    {
        if (!$category) {
            $this->stderr("Please specify a test category. Use 'field-agent/generator/test-list' to see available categories.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $plugin = Plugin::getInstance();
        $testingService = $plugin->testingService;

        $this->stdout("\n🧪 Running test suite: $category\n", Console::FG_CYAN);
        $this->stdout("=====================================\n\n");

        $results = $testingService->runTestSuite($category, $this->cleanup);

        if ($results['totalTests'] === 0) {
            $this->stderr("No tests found in category: $category\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Display results
        foreach ($results['results'] as $result) {
            if ($result['success']) {
                $this->stdout("✅ {$result['testName']}\n", Console::FG_GREEN);
            } else {
                $this->stderr("❌ {$result['testName']}: {$result['message']}\n", Console::FG_RED);
            }
        }

        $this->stdout("\n=== Test Suite Summary ===\n", Console::FG_CYAN);
        $this->stdout("Total tests: {$results['totalTests']}\n");
        $this->stdout("Passed: {$results['passed']}\n", Console::FG_GREEN);
        $this->stdout("Failed: {$results['failed']}\n", Console::FG_RED);

        return $results['failed'] > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * Run all tests across all categories
     */
    public function actionTestAll(): int
    {
        $plugin = Plugin::getInstance();
        $testingService = $plugin->testingService;

        $this->stdout("\n🧪 Running all tests\n", Console::FG_CYAN);
        $this->stdout("=====================================\n\n");

        $results = $testingService->runAllTests($this->cleanup);

        if ($results['totalTests'] === 0) {
            $this->stderr("No tests found.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Display category results
        foreach ($results['categoryResults'] as $category => $categoryResult) {
            $categoryName = $testingService->getCategoryDisplayName($category);
            $this->stdout("\n📁 $categoryName\n", Console::FG_YELLOW);

            foreach ($categoryResult['results'] as $result) {
                if ($result['success']) {
                    $this->stdout("  ✅ {$result['testName']}\n", Console::FG_GREEN);
                } else {
                    $this->stderr("  ❌ {$result['testName']}: {$result['message']}\n", Console::FG_RED);
                }
            }
        }

        $this->stdout("\n=== Overall Test Summary ===\n", Console::FG_CYAN);
        $this->stdout("Total tests: {$results['totalTests']}\n");
        $this->stdout("Passed: {$results['passed']}\n", Console::FG_GREEN);
        $this->stdout("Failed: {$results['failed']}\n", Console::FG_RED);

        return $results['failed'] > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * Display help information
     */
    public function actionHelp(): int
    {
        $this->stdout("\n🚀 Craft CMS Field Agent - AI-Powered Field Generation\n", Console::FG_CYAN);
        $this->stdout("=====================================================\n\n");

        $this->stdout("CONTEXT-AWARE COMMANDS:\n", Console::FG_YELLOW);
        $this->stdout("  prompt <prompt>", Console::FG_GREEN);
        $this->stdout(" - Generate fields/sections from natural language\n");
        $this->stdout("    Examples:\n");
        $this->stdout("      field-agent/generator/prompt \"Create a blog section with title, content, and featured image\"\n");
        $this->stdout("      field-agent/generator/prompt \"Add author and tags fields to the blog entry type\"\n\n");

        $this->stdout("BASIC COMMANDS:\n", Console::FG_YELLOW);
        $this->stdout("  generate <config>", Console::FG_GREEN);
        $this->stdout(" - Generate from JSON config file or stored config\n");
        $this->stdout("  list", Console::FG_GREEN);
        $this->stdout(" - List available configurations and presets\n\n");

        $this->stdout("ROLLBACK COMMANDS:\n", Console::FG_YELLOW);
        $this->stdout("  rollback <id>", Console::FG_GREEN);
        $this->stdout(" - Rollback a specific operation by ID\n");
        $this->stdout("  rollback-last", Console::FG_GREEN);
        $this->stdout(" - Rollback the most recent operation\n");
        $this->stdout("  rollback-all", Console::FG_GREEN);
        $this->stdout(" - Rollback all operations (requires confirmation)\n");
        $this->stdout("  operations", Console::FG_GREEN);
        $this->stdout(" - List all field generation operations\n\n");

        $this->stdout("TEST COMMANDS:\n", Console::FG_YELLOW);
        $this->stdout("  test-list", Console::FG_GREEN);
        $this->stdout(" - List all available test suites\n");
        $this->stdout("  test-run <name>", Console::FG_GREEN);
        $this->stdout(" - Run a specific test\n");
        $this->stdout("  test-suite <category>", Console::FG_GREEN);
        $this->stdout(" - Run all tests in a category\n");
        $this->stdout("  test-all", Console::FG_GREEN);
        $this->stdout(" - Run all tests\n\n");

        $this->stdout("UTILITY COMMANDS:\n", Console::FG_YELLOW);
        $this->stdout("  test-llm [provider]", Console::FG_GREEN);
        $this->stdout(" - Test LLM API connection\n");
        $this->stdout("  check-keys", Console::FG_GREEN);
        $this->stdout(" - Check API key configuration\n");
        $this->stdout("  export-prompt", Console::FG_GREEN);
        $this->stdout(" - Export LLM prompt and schema for manual testing\n");
        $this->stdout("  stats", Console::FG_GREEN);
        $this->stdout(" - Show storage statistics\n");
        $this->stdout("  sync-config", Console::FG_GREEN);
        $this->stdout(" - Force project config sync\n");
        $this->stdout("  test-discovery", Console::FG_GREEN);
        $this->stdout(" - Test the discovery service\n\n");

        $this->stdout("MAINTENANCE COMMANDS:\n", Console::FG_YELLOW);
        $this->stdout("  prune-rolled-back", Console::FG_GREEN);
        $this->stdout(" - Remove rolled back operation records\n");
        $this->stdout("  prune-configs [days]", Console::FG_GREEN);
        $this->stdout(" - Remove old config files\n");
        $this->stdout("  prune", Console::FG_GREEN);
        $this->stdout(" - Remove stale data (rolled back ops + old configs)\n");
        $this->stdout("  delete-operations", Console::FG_GREEN);
        $this->stdout(" - Delete ALL operation records (keeps content)\n");
        $this->stdout("  reset", Console::FG_GREEN);
        $this->stdout(" - Delete ALL content AND operation records (nuclear!)\n\n");

        $this->stdout("OPTIONS:\n", Console::FG_YELLOW);
        $this->stdout("  --debug", Console::FG_GREEN);
        $this->stdout(" - Enable debug mode for verbose output\n");
        $this->stdout("  --cleanup", Console::FG_GREEN);
        $this->stdout(" - Auto-cleanup test data after completion\n");
        $this->stdout("  --dry-run", Console::FG_GREEN);
        $this->stdout(" - Generate config without creating fields\n");
        $this->stdout("  --force", Console::FG_GREEN);
        $this->stdout(" - Skip confirmation prompts\n");
        $this->stdout("  --output=<path>", Console::FG_GREEN);
        $this->stdout(" - Save generated config to file\n\n");

        $this->stdout("For more information, visit: https://github.com/craft-field-agent\n", Console::FG_CYAN);

        return ExitCode::OK;
    }

    /**
     * Force project config sync to fix orphaned fields
     */
    public function actionSyncConfig(): int
    {
        $this->stdout("Forcing project config sync...\n", Console::FG_YELLOW);
        $this->stdout("This will rebuild project config from database state to fix any orphaned fields.\n");

        try {
            $projectConfig = Craft::$app->getProjectConfig();

            // Rebuild project config from database state
            $projectConfig->rebuild();

            $this->stdout("✓ Project config rebuilt successfully!\n", Console::FG_GREEN);
            $this->stdout("\nThis should resolve any orphaned fields showing in the Control Panel.\n");
            $this->stdout("If fields still appear, they may have actual content and need manual deletion.\n");

            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Error rebuilding project config: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Show storage statistics
     */
    public function actionStats(): int
    {
        $plugin = Plugin::getInstance();
        $statisticsService = $plugin->statisticsService;

        $stats = $statisticsService->getStorageStats();

        $this->stdout("Storage Statistics\n", Console::FG_CYAN);
        $this->stdout("==================\n\n");

        // Config files
        $this->stdout("Config Files:\n", Console::FG_YELLOW);
        $this->stdout("  Count: " . $stats['configs']['count'] . "\n");
        $this->stdout("  Total size: " . $statisticsService->formatBytes($stats['configs']['total_size']) . "\n");

        if ($stats['configs']['oldest']) {
            $this->stdout("  Oldest: " . date('Y-m-d H:i:s', $stats['configs']['oldest']) . "\n");
        }
        if ($stats['configs']['newest']) {
            $this->stdout("  Newest: " . date('Y-m-d H:i:s', $stats['configs']['newest']) . "\n");
        }

        $this->stdout("\nOperations:\n", Console::FG_YELLOW);
        $this->stdout("  Count: " . $stats['operations']['count'] . "\n");
        $this->stdout("  Rolled back: " . $stats['operations']['rolled_back_count'] . "\n");
        $this->stdout("  Total size: " . $statisticsService->formatBytes($stats['operations']['total_size']) . "\n");

        if ($stats['operations']['oldest']) {
            $this->stdout("  Oldest: " . date('Y-m-d H:i:s', $stats['operations']['oldest']) . "\n");
        }
        if ($stats['operations']['newest']) {
            $this->stdout("  Newest: " . date('Y-m-d H:i:s', $stats['operations']['newest']) . "\n");
        }

        return ExitCode::OK;
    }

    /**
     * Prune rolled back operations
     */
    public function actionPruneRolledBack(): int
    {
        $plugin = Plugin::getInstance();
        $pruneService = $plugin->pruneService;

        $results = $pruneService->pruneRolledBackOperations();

        $this->stdout("Pruning Rolled Back Operations\n", Console::FG_CYAN);
        $this->stdout("===============================\n\n");

        if (!empty($results['deleted'])) {
            $this->stdout("Deleted " . count($results['deleted']) . " rolled back operations:\n", Console::FG_GREEN);
            foreach ($results['deleted'] as $op) {
                $this->stdout("  - {$op['id']} ({$op['type']}) - {$op['timestamp']}\n");
            }
        } else {
            $this->stdout("No rolled back operations to prune.\n", Console::FG_YELLOW);
        }

        if (isset($results['space_freed'])) {
            $this->stdout("\nSpace freed: " . $this->formatBytes($results['space_freed']) . "\n", Console::FG_CYAN);
        }

        return ExitCode::OK;
    }

    /**
     * Prune old configuration files
     */
    public function actionPruneConfigs(int $days = 7): int
    {
        $plugin = Plugin::getInstance();
        $pruneService = $plugin->pruneService;

        if (!$this->confirm("Delete config files older than $days days?")) {
            $this->stdout("Pruning cancelled.\n");
            return ExitCode::OK;
        }

        $results = $pruneService->pruneOldConfigs($days);

        $this->stdout("\nPruning Old Config Files\n", Console::FG_CYAN);
        $this->stdout("========================\n\n");

        if (!empty($results['deleted'])) {
            $this->stdout("Deleted " . count($results['deleted']) . " config files:\n", Console::FG_GREEN);
            foreach ($results['deleted'] as $file) {
                $this->stdout("  - {$file['name']} - {$file['date']}\n");
            }
        } else {
            $this->stdout("No config files to prune.\n", Console::FG_YELLOW);
        }

        if (isset($results['space_freed'])) {
            $this->stdout("\nSpace freed: " . $this->formatBytes($results['space_freed']) . "\n", Console::FG_CYAN);
        }

        return ExitCode::OK;
    }

    /**
     * Prune stale data (rolled back operations and old configs)
     */
    public function actionPrune(): int
    {
        if (!$this->confirm || !$this->confirm("Prune all rolled back operations and old configs?")) {
            $this->stdout("Pruning cancelled.\n");
            return ExitCode::OK;
        }

        $plugin = Plugin::getInstance();
        $pruneService = $plugin->pruneService;

        $this->stdout("\nPruning all old data...\n", Console::FG_YELLOW);

        // Prune rolled back operations
        $opsResults = $pruneService->pruneRolledBackOperations();
        $this->stdout("Deleted " . count($opsResults['deleted'] ?? []) . " rolled back operations\n", Console::FG_GREEN);

        // Prune old configs (30 days)
        $configResults = $pruneService->pruneOldConfigs(30);
        $this->stdout("Deleted " . count($configResults['deleted'] ?? []) . " old config files\n", Console::FG_GREEN);

        $totalSpaceFreed = ($opsResults['space_freed'] ?? 0) + ($configResults['space_freed'] ?? 0);
        if ($totalSpaceFreed > 0) {
            $this->stdout("\nTotal space freed: " . $this->formatBytes($totalSpaceFreed) . "\n", Console::FG_CYAN);
        }

        return ExitCode::OK;
    }

    /**
     * Test the discovery service
     */
    public function actionTestDiscovery(): int
    {
        $plugin = Plugin::getInstance();
        $discoveryService = $plugin->discoveryService;

        $this->stdout("\n🔍 Testing Discovery Service\n", Console::FG_CYAN);
        $this->stdout("============================\n\n");

        // Test field discovery
        $this->stdout("📋 Discovering Fields...\n", Console::FG_YELLOW);
        $fieldsResult = $discoveryService->executeTool('getFields');
        $fields = $fieldsResult['fields'] ?? [];

        if (empty($fields)) {
            $this->stdout("  No fields found.\n", Console::FG_YELLOW);
        } else {
            $this->stdout("  Found " . $fieldsResult['count'] . " fields:\n", Console::FG_GREEN);
            foreach (array_slice($fields, 0, 5) as $field) {
                $typeName = basename(str_replace('\\', '/', $field['type']));
                $this->stdout("    - {$field['name']} ({$field['handle']}) - Type: {$typeName}\n");
            }
            if (count($fields) > 5) {
                $this->stdout("    ... and " . (count($fields) - 5) . " more\n", Console::FG_YELLOW);
            }
        }

        // Test section discovery
        $this->stdout("\n📁 Discovering Sections...\n", Console::FG_YELLOW);
        $sectionsResult = $discoveryService->executeTool('getSections');
        $sections = $sectionsResult['sections'] ?? [];

        if (empty($sections)) {
            $this->stdout("  No sections found.\n", Console::FG_YELLOW);
        } else {
            $this->stdout("  Found " . $sectionsResult['count'] . " sections:\n", Console::FG_GREEN);
            foreach ($sections as $section) {
                $this->stdout("    - {$section['name']} ({$section['handle']}) - Type: {$section['type']}\n");
                foreach ($section['entryTypes'] as $entryType) {
                    $this->stdout("      → {$entryType['name']} ({$entryType['handle']}) - {$entryType['fieldCount']} fields\n");
                }
            }
        }

        // Test handle availability
        $this->stdout("\n✅ Testing Handle Availability...\n", Console::FG_YELLOW);
        $testHandles = ['title', 'blogPost', 'customField123'];

        foreach ($testHandles as $handle) {
            $result = $discoveryService->executeTool('checkHandleAvailability', [
                'handle' => $handle,
                'type' => 'field',
                'suggest' => true
            ]);

            if ($result['available']) {
                $this->stdout("  '$handle' - Available ✓\n", Console::FG_GREEN);
            } else {
                $this->stdout("  '$handle' - Not available", Console::FG_RED);
                if (!empty($result['conflicts'])) {
                    $this->stdout(" (conflicts: " . implode(', ', $result['conflicts']) . ")", Console::FG_YELLOW);
                }
                if (!empty($result['suggestions'])) {
                    $this->stdout(" → Try: " . implode(', ', array_slice($result['suggestions'], 0, 2)), Console::FG_CYAN);
                }
                $this->stdout("\n");
            }
        }

        return ExitCode::OK;
    }

    /**
     * Reset everything - delete all content and operation records
     */
    public function actionReset(): int
    {
        $this->stdout("⚠️  WARNING: This will DELETE ALL sections, entry types, fields, category groups, and tag groups!\n", Console::FG_RED);
        $this->stdout("This action cannot be undone!\n\n");

        if (!$this->force && !$this->confirm("Are you absolutely sure you want to delete everything?")) {
            $this->stdout("Cleanup cancelled.\n");
            return ExitCode::OK;
        }

        $this->stdout("\nStarting cleanup...\n", Console::FG_YELLOW);

        try {
            $plugin = Plugin::getInstance();

            // Delete all sections (this will also delete entry types)
            $entriesService = Craft::$app->getEntries();
            $sections = $entriesService->getAllSections();

            if (empty($sections)) {
                $this->stdout("No sections found to delete.\n", Console::FG_YELLOW);
            } else {
                $this->stdout("Found " . count($sections) . " sections to delete:\n", Console::FG_CYAN);
                foreach ($sections as $section) {
                    $this->stdout("Deleting section: {$section->name}...", Console::FG_YELLOW);
                    if ($entriesService->deleteSection($section)) {
                        $this->stdout(" ✓\n", Console::FG_GREEN);
                    } else {
                        $this->stdout(" ✗\n", Console::FG_RED);
                    }
                }
            }

            // Delete any remaining entry types (in case some weren't deleted with sections)
            $allEntryTypes = $entriesService->getAllEntryTypes();

            if (empty($allEntryTypes)) {
                $this->stdout("No entry types found to delete.\n", Console::FG_YELLOW);
            } else {
                $this->stdout("Found " . count($allEntryTypes) . " entry types to delete:\n", Console::FG_CYAN);
                foreach ($allEntryTypes as $entryType) {
                    $this->stdout("Deleting entry type: {$entryType->name}...", Console::FG_YELLOW);
                    if ($entriesService->deleteEntryType($entryType)) {
                        $this->stdout(" ✓\n", Console::FG_GREEN);
                    } else {
                        $this->stdout(" ✗\n", Console::FG_RED);
                    }
                }
            }

            // Delete all fields
            $fieldsService = Craft::$app->getFields();
            $fields = $fieldsService->getAllFields();

            if (empty($fields)) {
                $this->stdout("No fields found to delete.\n", Console::FG_YELLOW);
            } else {
                $this->stdout("Found " . count($fields) . " fields to delete:\n", Console::FG_CYAN);
                foreach ($fields as $field) {
                    $this->stdout("Deleting field: {$field->name}...", Console::FG_YELLOW);
                    if ($fieldsService->deleteField($field)) {
                        $this->stdout(" ✓\n", Console::FG_GREEN);
                    } else {
                        $this->stdout(" ✗\n", Console::FG_RED);
                    }
                }
            }

            // Delete all category groups
            $categoriesService = Craft::$app->getCategories();
            $categoryGroups = $categoriesService->getAllGroups();

            if (empty($categoryGroups)) {
                $this->stdout("No category groups found to delete.\n", Console::FG_YELLOW);
            } else {
                $this->stdout("Found " . count($categoryGroups) . " category groups to delete:\n", Console::FG_CYAN);
                foreach ($categoryGroups as $group) {
                    $this->stdout("Deleting category group: {$group->name}...", Console::FG_YELLOW);
                    if ($categoriesService->deleteGroup($group)) {
                        $this->stdout(" ✓\n", Console::FG_GREEN);
                    } else {
                        $this->stdout(" ✗\n", Console::FG_RED);
                    }
                }
            }

            // Delete all tag groups
            $tagsService = Craft::$app->getTags();
            $tagGroups = $tagsService->getAllTagGroups();

            if (empty($tagGroups)) {
                $this->stdout("No tag groups found to delete.\n", Console::FG_YELLOW);
            } else {
                $this->stdout("Found " . count($tagGroups) . " tag groups to delete:\n", Console::FG_CYAN);
                foreach ($tagGroups as $group) {
                    $this->stdout("Deleting tag group: {$group->name}...", Console::FG_YELLOW);
                    if ($tagsService->deleteTagGroup($group)) {
                        $this->stdout(" ✓\n", Console::FG_GREEN);
                    } else {
                        $this->stdout(" ✗\n", Console::FG_RED);
                    }
                }
            }

            // Delete all operation records
            $rollbackService = $plugin->rollbackService;
            $operations = $rollbackService->getOperations();

            if (empty($operations)) {
                $this->stdout("No operation records found to delete.\n", Console::FG_YELLOW);
            } else {
                $this->stdout("Found " . count($operations) . " operation records to delete:\n", Console::FG_CYAN);
                $deletedCount = 0;
                foreach ($operations as $operation) {
                    if ($rollbackService->deleteOperation($operation->id)) {
                        $this->stdout("Deleting operation: {$operation->id}... ✓\n", Console::FG_YELLOW);
                        $deletedCount++;
                    } else {
                        $this->stdout("Deleting operation: {$operation->id}... ✗\n", Console::FG_RED);
                    }
                }
                $this->stdout("Deleted $deletedCount operation records.\n", Console::FG_GREEN);
            }

            $this->stdout("\n✓ Cleanup completed!\n", Console::FG_GREEN);
            $this->stdout("All sections, entry types, fields, category groups, tag groups, and operation records have been deleted.\n");

            return ExitCode::OK;

        } catch (\Exception $e) {
            $this->stderr("\nError during cleanup: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Delete all operation records (but keep content intact)
     */
    public function actionDeleteOperations(): int
    {
        $this->stdout("⚠️  WARNING: This will DELETE ALL operation records!\n", Console::FG_RED);
        $this->stdout("This will remove the ability to rollback any operations.\n");
        $this->stdout("Content (fields, sections, etc.) will remain intact.\n\n");

        if (!$this->force && !$this->confirm("Are you sure you want to delete all operation records?")) {
            $this->stdout("Operation deletion cancelled.\n");
            return ExitCode::OK;
        }

        $plugin = Plugin::getInstance();
        $rollbackService = $plugin->rollbackService;
        $operations = $rollbackService->getOperations();

        if (empty($operations)) {
            $this->stdout("No operation records found to delete.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $this->stdout("\nDeleting all operation records...\n", Console::FG_YELLOW);
        $this->stdout("Found " . count($operations) . " operation records to delete:\n", Console::FG_CYAN);

        $deletedCount = 0;
        $failedCount = 0;

        foreach ($operations as $operation) {
            if ($rollbackService->deleteOperation($operation->id)) {
                $this->stdout("Deleting operation: {$operation->id} ({$operation->type})... ✓\n", Console::FG_GREEN);
                $deletedCount++;
            } else {
                $this->stdout("Deleting operation: {$operation->id} ({$operation->type})... ✗\n", Console::FG_RED);
                $failedCount++;
            }
        }

        $this->stdout("\n✓ Operation deletion completed!\n", Console::FG_GREEN);
        $this->stdout("Deleted: $deletedCount operation records\n", Console::FG_GREEN);
        if ($failedCount > 0) {
            $this->stdout("Failed: $failedCount operation records\n", Console::FG_RED);
        }

        return ExitCode::OK;
    }

    /**
     * Execute operations from a configuration file
     */
    public function actionExecuteOperations(string $config): int
    {
        $plugin = Plugin::getInstance();
        $configService = $plugin->configurationService;

        $configData = $configService->loadConfig($config);
        if (!$configData) {
            $this->stderr("Config not found: $config\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!isset($configData['operations'])) {
            $this->stderr("No operations found in config.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("\n⚡ Executing operations...\n", Console::FG_CYAN);

        $operationsExecutor = $plugin->operationsExecutorService;
        $results = $operationsExecutor->executeOperations($configData);

        $this->displayOperationResults($results);

        return ExitCode::OK;
    }

    /**
     * Display operation results
     */
    private function displayOperationResults(array $results): void
    {
        if (!is_array($results)) {
            $results = [$results];
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($results as $result) {
            if (!is_array($result)) {
                continue;
            }

            $success = $result['success'] ?? false;
            $type = $result['operation']['type'] ?? 'unknown';
            $target = $result['operation']['target'] ?? 'unknown';
            $message = $result['message'] ?? '';

            if ($success) {
                $this->stdout("✓ ", Console::FG_GREEN);
                $successCount++;
            } else {
                $this->stdout("✗ ", Console::FG_RED);
                $failCount++;
            }

            $this->stdout("$type $target: $message\n");

            if (!$success && isset($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    $this->stderr("  - $error\n", Console::FG_RED);
                }
            }
        }

        $this->stdout("\nSummary: $successCount succeeded, $failCount failed\n", Console::FG_CYAN);
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $plugin = Plugin::getInstance();
        return $plugin->statisticsService->formatBytes($bytes, $precision);
    }

    /**
     * Count array elements safely
     */
    private function count($array): int
    {
        return is_array($array) ? count($array) : 0;
    }

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        switch ($actionID) {
            case 'prompt':
            case 'test-llm':
                $options[] = 'debug';
                $options[] = 'dryRun';
                $options[] = 'output';
                break;
            case 'test-run':
            case 'test-suite':
            case 'test-all':
                $options[] = 'cleanup';
                break;
            case 'reset':
            case 'rollback-all':
            case 'delete-operations':
                $options[] = 'force';
                break;
            case 'prune':
                $options[] = 'confirm';
                break;
        }

        return $options;
    }
}
