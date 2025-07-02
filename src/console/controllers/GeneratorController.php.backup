<?php

namespace craftcms\fieldagent\console\controllers;

use Craft;
use craft\console\Controller;
use craft\fields\PlainText;
use craft\fields\Assets;
use craft\fields\Number;
use craft\fields\Link;
use craft\fields\Dropdown;
use craft\fields\RadioButtons;
use craft\fields\Checkboxes;
use craft\fields\MultiSelect;
use craft\fields\Country;
use craft\fields\Date;
use craft\fields\Time;
use craft\fields\Email;
use craft\fields\Color;
use craft\fields\Lightswitch;
use craft\fields\Money;
use craft\fields\Range;
use craft\fields\ButtonGroup;
use craft\fields\Icon;
use craft\fields\Table;
use craft\fields\Categories;
use craft\fields\Entries;
use craft\fields\Tags;
use craft\fields\Users;
use craft\fields\Matrix;
use craft\ckeditor\Field as CKEditorField;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craftcms\fieldagent\Plugin;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Generator controller
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
        $configData = $this->loadConfig($config);
        if (!$configData) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Store this config for future reference if it was a file
        if (file_exists($config)) {
            $plugin = Plugin::getInstance();
            $configName = pathinfo($config, PATHINFO_FILENAME);
            $plugin->fieldGeneratorService->storeConfig($configName, $configData);
            $this->stdout("Config stored for future use.\n", Console::FG_CYAN);
        }

        return $this->executeFieldGeneration('generate', $config, $configData);
    }

    /**
     * Execute field generation and record operation for rollback
     */
    private function executeFieldGeneration(string $type, string $source, array $configData): int
    {
        $fieldsService = Craft::$app->getFields();
        $plugin = Plugin::getInstance();
        $createdFields = [];
        $failedFields = [];
        $createdEntryTypes = [];
        $failedEntryTypes = [];
        $createdSections = [];
        $failedSections = [];

        // Record operation ID early so we can update it if errors occur
        $operationId = null;

        try {
            // Step 1: Create fields first (they're needed for entry types)
            if (isset($configData['fields'])) {
                $this->stdout("Creating fields...\n", Console::FG_YELLOW);

                foreach ($configData['fields'] as $fieldConfig) {
                    try {
                        $field = $plugin->fieldGeneratorService->createFieldFromConfig($fieldConfig);
                        if ($field) {
                            if ($fieldsService->saveField($field)) {
                                $this->stdout("✓ Created field: {$field->name} ({$field->handle})\n", Console::FG_GREEN);
                                $createdFields[] = [
                                    'handle' => $field->handle,
                                    'name' => $field->name,
                                    'type' => $field::class,
                                    'id' => $field->id
                                ];

                                // If this is a matrix field, also track its block fields and entry types
                                if ($field instanceof \craft\fields\Matrix) {
                                    $blockFields = $plugin->fieldGeneratorService->getCreatedBlockFields();
                                    $blockEntryTypes = $plugin->fieldGeneratorService->getCreatedBlockEntryTypes();

                                    // Add block fields to created fields
                                    foreach ($blockFields as $blockField) {
                                        $this->stdout("  → Block field: {$blockField['name']} ({$blockField['handle']})\n", Console::FG_BLUE);
                                        $createdFields[] = $blockField;
                                    }

                                    // Add block entry types to created entry types
                                    foreach ($blockEntryTypes as $blockEntryType) {
                                        $this->stdout("  → Block type: {$blockEntryType['name']} ({$blockEntryType['handle']})\n", Console::FG_CYAN);
                                        $createdEntryTypes[] = $blockEntryType;
                                    }

                                    // Clear the tracking arrays for next matrix field
                                    $plugin->fieldGeneratorService->clearBlockTracking();
                                }
                            } else {
                                $this->stderr("✗ Failed to create field: {$fieldConfig['name']}\n", Console::FG_RED);
                                $this->stderr("  Errors: " . implode(', ', $field->getFirstErrors()) . "\n");
                                $failedFields[] = [
                                    'handle' => $fieldConfig['handle'],
                                    'name' => $fieldConfig['name'],
                                    'errors' => $field->getFirstErrors()
                                ];
                            }
                        }
                    } catch (\Exception $e) {
                        $this->stderr("✗ Failed to create field: {$fieldConfig['name']}\n", Console::FG_RED);
                        $this->stderr("  Exception: " . $e->getMessage() . "\n");
                        $failedFields[] = [
                            'handle' => $fieldConfig['handle'],
                            'name' => $fieldConfig['name'],
                            'errors' => ['exception' => $e->getMessage()]
                        ];

                        // Record operation immediately with partial results to prevent orphaned fields
                        if (!$operationId && (!empty($createdFields) || !empty($failedFields))) {
                            $operationId = $plugin->rollbackService->recordOperation(
                                $type,
                                $source,
                                $createdFields,
                                $failedFields,
                                $createdEntryTypes,
                                $failedEntryTypes,
                                $createdSections,
                                $failedSections,
                                [], // createdCategoryGroups
                                [], // failedCategoryGroups
                                [], // createdTagGroups
                                [], // failedTagGroups
                                "PARTIAL: Exception during field creation"
                            );
                            $this->stdout("\n⚠️  Partial operation recorded with ID: $operationId (due to exception)\n", Console::FG_YELLOW);
                            $this->stdout("   Use 'field-agent/generator/rollback $operationId' to clean up created fields.\n", Console::FG_YELLOW);
                        }

                        // Continue processing other fields
                        continue;
                    }
                }
            }

            // Step 2: Create entry types (they need fields but not sections)
            if (isset($configData['entryTypes'])) {
                $this->stdout("\nCreating entry types...\n", Console::FG_YELLOW);

                foreach ($configData['entryTypes'] as $entryTypeConfig) {
                    try {
                        $entryType = $this->createEntryTypeFromConfig($entryTypeConfig, $createdFields);
                        if ($entryType) {
                            $this->stdout("✓ Created entry type: {$entryType->name} ({$entryType->handle})\n", Console::FG_GREEN);
                            $createdEntryTypes[] = [
                                'handle' => $entryType->handle,
                                'name' => $entryType->name,
                                'id' => $entryType->id
                            ];
                        } else {
                            $this->stderr("✗ Failed to create entry type: {$entryTypeConfig['name']}\n", Console::FG_RED);
                            $failedEntryTypes[] = [
                                'handle' => $entryTypeConfig['handle'] ?? '',
                                'name' => $entryTypeConfig['name'] ?? 'Unknown'
                            ];
                        }
                    } catch (\Exception $e) {
                        $this->stderr("✗ Failed to create entry type: {$entryTypeConfig['name']}\n", Console::FG_RED);
                        $this->stderr("  Exception: " . $e->getMessage() . "\n");
                        $failedEntryTypes[] = [
                            'handle' => $entryTypeConfig['handle'] ?? '',
                            'name' => $entryTypeConfig['name'] ?? 'Unknown',
                            'errors' => ['exception' => $e->getMessage()]
                        ];
                    }
                }
            }

            // Step 3: Create sections (they need entry types)
            if (isset($configData['sections'])) {
                $this->stdout("\nCreating sections...\n", Console::FG_YELLOW);

                foreach ($configData['sections'] as $sectionConfig) {
                    try {
                        $section = $plugin->sectionGeneratorService->createSectionFromConfig($sectionConfig, $createdEntryTypes);
                        if ($section) {
                            $this->stdout("✓ Created section: {$section->name} ({$section->handle})\n", Console::FG_GREEN);
                            $createdSections[] = [
                                'handle' => $section->handle,
                                'name' => $section->name,
                                'type' => $section->type,
                                'id' => $section->id
                            ];
                        } else {
                            $this->stderr("✗ Failed to create section: {$sectionConfig['name']}\n", Console::FG_RED);
                            $failedSections[] = [
                                'handle' => $sectionConfig['handle'] ?? '',
                                'name' => $sectionConfig['name'] ?? 'Unknown'
                            ];
                        }
                    } catch (\Exception $e) {
                        $this->stderr("✗ Failed to create section: {$sectionConfig['name']}\n", Console::FG_RED);
                        $this->stderr("  Exception: " . $e->getMessage() . "\n");
                        $failedSections[] = [
                            'handle' => $sectionConfig['handle'] ?? '',
                            'name' => $sectionConfig['name'] ?? 'Unknown',
                            'errors' => ['exception' => $e->getMessage()]
                        ];
                    }
                }
            }

        } catch (\Exception $e) {
            // Catch any unexpected exceptions and record partial operation
            $this->stderr("\n✗ Unexpected exception during generation: " . $e->getMessage() . "\n", Console::FG_RED);

            if (!$operationId && (!empty($createdFields) || !empty($failedFields) || !empty($createdEntryTypes) || !empty($failedEntryTypes) || !empty($createdSections) || !empty($failedSections))) {
                $operationId = $plugin->rollbackService->recordOperation(
                    $type,
                    $source,
                    $createdFields,
                    $failedFields,
                    $createdEntryTypes,
                    $failedEntryTypes,
                    $createdSections,
                    $failedSections,
                    [], // createdCategoryGroups
                    [], // failedCategoryGroups
                    [], // createdTagGroups
                    [], // failedTagGroups
                    "PARTIAL: Unexpected exception - " . $e->getMessage()
                );
                $this->stdout("\n⚠️  Partial operation recorded with ID: $operationId (due to unexpected exception)\n", Console::FG_YELLOW);
                $this->stdout("   Use 'field-agent/generator/rollback $operationId' to clean up created items.\n", Console::FG_YELLOW);
            }
        }

        // Record the operation for potential rollback (if not already recorded due to exception)
        if (!$operationId && (!empty($createdFields) || !empty($failedFields) || !empty($createdEntryTypes) || !empty($failedEntryTypes) || !empty($createdSections) || !empty($failedSections))) {
            $operationId = $plugin->rollbackService->recordOperation(
                $type,
                $source,
                $createdFields,
                $failedFields,
                $createdEntryTypes,
                $failedEntryTypes,
                $createdSections,
                $failedSections,
                [], // createdCategoryGroups
                [], // failedCategoryGroups
                [], // createdTagGroups
                [] // failedTagGroups
            );

            if (!empty($createdFields) || !empty($createdEntryTypes) || !empty($createdSections)) {
                $this->stdout("\n📋 Operation recorded with ID: $operationId\n", Console::FG_CYAN);
                $this->stdout("   Use 'field-agent/generator/rollback $operationId' to undo this operation.\n", Console::FG_CYAN);
            }
        } elseif ($operationId && (!empty($createdEntryTypes) || !empty($createdSections))) {
            // Update the existing operation with any additional items created after the initial recording
            // Note: This is a limitation - we'd need to implement operation updating for full coverage
            $this->stdout("\n📋 Additional items created under operation ID: $operationId\n", Console::FG_CYAN);
        }

        $this->stdout("\nDone! Run 'ddev craft up' to apply changes.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Define command options
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        // Add debug option for prompt and test-llm actions
        if (in_array($actionID, ['prompt', 'test-llm'])) {
            $options[] = 'debug';
        }

        // Add dry-run and output options for prompt action
        if ($actionID === 'prompt') {
            $options[] = 'dryRun';
            $options[] = 'output';
        }

        // Add confirm option for prune-all action
        if ($actionID === 'prune-all') {
            $options[] = 'confirm';
        }

        // Add force option for rollback-all action
        if ($actionID === 'rollback-all') {
            $options[] = 'force';
        }

        // Add cleanup option for test actions
        if (in_array($actionID, ['test-run', 'test-suite', 'test-all'])) {
            $options[] = 'cleanup';
        }

        return $options;
    }

    /**
     * Define option aliases
     */
    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), [
            'd' => 'debug',
        ]);
    }

    /**
     * Generate fields from natural language prompt
     *
     * @param string $prompt The natural language prompt
     * @param string $provider The LLM provider to use (anthropic or openai)
     * @return int Exit code
     */
    public function actionPrompt(string $prompt, string $provider = 'anthropic'): int
    {
        $this->stdout("Generating config from prompt: $prompt\n", Console::FG_YELLOW);
        $this->stdout("Using LLM provider: $provider\n", Console::FG_CYAN);

        if ($this->dryRun) {
            $this->stdout("🔍 DRY RUN MODE - Only generating configuration, no fields will be created\n", Console::FG_YELLOW);
        }

        if ($this->debug) {
            $this->stdout("🐛 DEBUG MODE ENABLED - Full request/response details will be shown\n", Console::FG_CYAN);
        }

        try {
            $plugin = Plugin::getInstance();

            // First try the new context-aware operations system
            try {
                $this->stdout("Using context-aware operations system...\n", Console::FG_CYAN);
                $operationsData = $plugin->llmOperationsService->generateOperationsFromPrompt($prompt, $provider, $this->debug);

                if ($this->debug) {
                    $this->stdout("\n=== GENERATED OPERATIONS ===\n", Console::FG_YELLOW);
                    $this->stdout(json_encode($operationsData, JSON_PRETTY_PRINT) . "\n", Console::FG_GREY);
                }

                // Store the operations for reference
                $configPath = $plugin->fieldGeneratorService->storeConfig('operations_' . date('Y-m-d_H-i-s'), $operationsData);
                $this->stdout("✓ Operations config stored at: $configPath\n", Console::FG_CYAN);

                if ($this->dryRun) {
                    $this->stdout("\n🔍 DRY RUN MODE - Operations generated but not executed\n", Console::FG_YELLOW);
                    $configName = pathinfo($configPath, PATHINFO_FILENAME);
                    $this->stdout("Execute with: ddev craft field-agent/generator/execute-operations $configName\n");
                    return ExitCode::OK;
                }

                // Execute the operations
                $this->stdout("Executing operations...\n", Console::FG_CYAN);
                $results = $plugin->operationsExecutorService->executeOperations($operationsData);

                // Display results
                $this->displayOperationResults($results);

                // Extract successful and failed operations for recording
                $allSucceeded = true;
                $createdFields = [];
                $createdEntryTypes = [];
                $createdSections = [];
                $createdCategoryGroups = [];
                $createdTagGroups = [];
                $failedOperations = [];

                foreach ($results as $result) {
                    if (!$result['success']) {
                        $allSucceeded = false;
                        $failedOperations[] = $result;
                    } elseif (isset($result['created'])) {
                        switch ($result['created']['type']) {
                            case 'field':
                                $createdFields[] = $result['created'];
                                // Check for matrix blocks
                                if (isset($result['matrix_blocks'])) {
                                    // Add block fields to the created fields array
                                    if (isset($result['matrix_blocks']['fields'])) {
                                        foreach ($result['matrix_blocks']['fields'] as $blockField) {
                                            $createdFields[] = $blockField;
                                        }
                                    }
                                    // Add block entry types to the created entry types array
                                    if (isset($result['matrix_blocks']['entry_types'])) {
                                        foreach ($result['matrix_blocks']['entry_types'] as $blockEntryType) {
                                            $createdEntryTypes[] = $blockEntryType;
                                        }
                                    }
                                }
                                break;
                            case 'entryType':
                                $createdEntryTypes[] = $result['created'];
                                break;
                            case 'section':
                                $createdSections[] = $result['created'];
                                break;
                            case 'categoryGroup':
                                $createdCategoryGroups[] = $result['created'];
                                break;
                            case 'tagGroup':
                                $createdTagGroups[] = $result['created'];
                                break;
                        }
                    }
                }

                // ALWAYS record operation if anything was created (successful or failed)
                if (!empty($createdFields) || !empty($createdEntryTypes) || !empty($createdSections) || !empty($createdCategoryGroups) || !empty($createdTagGroups) || !empty($failedOperations)) {
                    $description = $allSucceeded ? "Smart prompt: $prompt" : "PARTIAL: Smart prompt with failures: $prompt";

                    $operationId = $plugin->rollbackService->recordOperation(
                        'smart-prompt',
                        $prompt,
                        $createdFields,
                        [], // deletedFields
                        $createdEntryTypes,
                        [], // deletedEntryTypes
                        $createdSections,
                        [], // deletedSections
                        $createdCategoryGroups,
                        [], // deletedCategoryGroups
                        $createdTagGroups,
                        [], // deletedTagGroups
                        $description
                    );

                    $this->stdout("\n📋 Operation recorded with ID: $operationId\n", Console::FG_CYAN);
                    $this->stdout("   Use 'field-agent/generator/rollback $operationId' to undo this operation.\n");

                    if (!$allSucceeded) {
                        $this->stdout("   ⚠ Partial operation recorded - successful items can be rolled back\n", Console::FG_YELLOW);
                    }
                }

                if ($allSucceeded) {
                    $this->stdout("\nDone! Run 'ddev craft up' to apply changes.\n", Console::FG_GREEN);
                    return ExitCode::OK;
                } else {
                    $this->stdout("\n⚠ Some operations failed. Please check the errors above and retry.\n", Console::FG_YELLOW);
                    $this->stdout("Hint: Operations must be executed in dependency order (fields → entry types → sections).\n", Console::FG_CYAN);
                    $this->stdout("Note: Successful operations have been recorded and can be rolled back if needed.\n", Console::FG_CYAN);
                    return ExitCode::UNSPECIFIED_ERROR;
                }

            } catch (\Exception $e) {
                $this->stdout("⚠ Operations system failed: {$e->getMessage()}\n", Console::FG_YELLOW);
                $this->stdout("Falling back to legacy create-only system...\n", Console::FG_YELLOW);
            }

            // Fallback to old system if operations failed
            $this->stdout("Using legacy create-only system...\n", Console::FG_CYAN);
            $configData = $plugin->llmIntegrationService->generateFromPrompt($prompt, $provider, $this->debug);

            // Determine where to save the config
            if ($this->output) {
                // Custom output path
                $outputPath = $this->output;
                if (!str_ends_with($outputPath, '.json')) {
                    $outputPath .= '.json';
                }

                // Make path absolute if relative
                if (!str_starts_with($outputPath, '/')) {
                    $outputPath = getcwd() . '/' . $outputPath;
                }

                // Ensure directory exists
                $dir = dirname($outputPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                file_put_contents($outputPath, json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $this->stdout("✓ Generated config saved to: $outputPath\n", Console::FG_GREEN);
                $configName = basename($outputPath, '.json');
            } else {
                // Store in default location
                $configName = 'llm_' . date('Y-m-d_H-i-s');
                $configPath = $plugin->fieldGeneratorService->storeConfig($configName, $configData);
                $this->stdout("✓ Generated config stored at: $configPath\n", Console::FG_GREEN);
            }

            // Show what was generated
            $this->stdout("\nGenerated configuration:\n", Console::FG_YELLOW);
            $this->stdout("  Name: " . ($configData['name'] ?? 'Unnamed') . "\n");
            $this->stdout("  Description: " . ($configData['description'] ?? 'No description') . "\n");
            $this->stdout("  Fields: " . count($configData['fields'] ?? []) . "\n");
            if (isset($configData['entryTypes'])) {
                $this->stdout("  Entry Types: " . count($configData['entryTypes']) . "\n");
            }
            if (isset($configData['sections'])) {
                $this->stdout("  Sections: " . count($configData['sections']) . "\n");
            }

            // If dry run, stop here
            if ($this->dryRun) {
                $this->stdout("\n✅ Configuration generated successfully!\n", Console::FG_GREEN);
                $this->stdout("\nTo create the fields, run:\n", Console::FG_CYAN);
                if ($this->output) {
                    $this->stdout("  ddev craft field-agent/generator/generate $outputPath\n");
                } else {
                    $this->stdout("  ddev craft field-agent/generator/generate $configName\n");
                }
                return ExitCode::OK;
            }

            // Otherwise, generate the fields
            return $this->executeFieldGeneration('llm-prompt', $prompt, $configData);

        } catch (\Exception $e) {
            $this->stderr("✗ Failed to generate config from prompt: {$e->getMessage()}\n", Console::FG_RED);

            // Only fallback if not in dry-run mode
            if (!$this->dryRun) {
                $this->stdout("Falling back to basic config generation...\n", Console::FG_YELLOW);
                $configData = $this->generateConfigFromPrompt($prompt);

                $plugin = Plugin::getInstance();
                $configPath = $plugin->fieldGeneratorService->storeConfig('fallback_' . date('Y-m-d_H-i-s'), $configData);
                $this->stdout("Fallback config stored at: $configPath\n", Console::FG_CYAN);

                return $this->executeFieldGeneration('fallback-prompt', $prompt, $configData);
            }

            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * List stored configurations and built-in presets
     */
    public function actionList(): int
    {
        $plugin = Plugin::getInstance();
        $configs = $plugin->fieldGeneratorService->listStoredConfigs();
        $presets = $this->listBuiltInPresets();

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
     * Generate basic fields (text, rich text, image, url, number)
     */
    public function actionBasicFields(): int
    {
        $this->stdout("Creating basic field set...\n", Console::FG_YELLOW);

        $fields = [
            [
                'name' => 'Text',
                'handle' => 'text',
                'field_type' => 'plain_text',
                'instructions' => 'Single line text field'
            ],
            [
                'name' => 'Rich Text',
                'handle' => 'richText',
                'field_type' => 'rich_text',
                'instructions' => 'Rich text editor with formatting options'
            ],
            [
                'name' => 'Image',
                'handle' => 'image',
                'field_type' => 'image',
                'instructions' => 'Upload an image'
            ],
            [
                'name' => 'URL',
                'handle' => 'url',
                'field_type' => 'url',
                'instructions' => 'External link URL'
            ],
            [
                'name' => 'Number',
                'handle' => 'number',
                'field_type' => 'number',
                'instructions' => 'Numeric value'
            ]
        ];

        return $this->executeFieldGeneration('basic-fields', 'built-in', ['fields' => $fields]);
    }

    /**
     * Test LLM API connection
     */
    public function actionTestLlm(string $provider = 'anthropic'): int
    {
        $this->stdout("Testing LLM API connection...\n", Console::FG_YELLOW);
        $this->stdout("Provider: $provider\n", Console::FG_CYAN);

        if ($this->debug) {
            $this->stdout("🐛 DEBUG MODE ENABLED - Full request/response details will be shown\n", Console::FG_CYAN);
        }

        $plugin = Plugin::getInstance();
        $result = $plugin->llmIntegrationService->testConnection($provider, $this->debug);

        if ($result['success']) {
            $this->stdout("✓ Connection successful!\n", Console::FG_GREEN);
            $this->stdout("  Provider: {$result['provider']}\n");
            $this->stdout("  Message: {$result['message']}\n");
            if (isset($result['fieldCount'])) {
                $this->stdout("  Test generated {$result['fieldCount']} fields\n");
            }
            return ExitCode::OK;
        } else {
            $this->stderr("✗ Connection failed!\n", Console::FG_RED);
            $this->stderr("  Provider: {$result['provider']}\n");
            $this->stderr("  Error: {$result['error']}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Check API key configuration
     */
    public function actionCheckKeys(): int
    {
        $this->stdout("Checking API key configuration...\n", Console::FG_YELLOW);

        $providers = [
            'ANTHROPIC_API_KEY' => 'Anthropic Claude',
            'OPENAI_API_KEY' => 'OpenAI GPT-4'
        ];

        foreach ($providers as $keyName => $providerName) {
            $this->stdout("\n🔑 $providerName ($keyName):\n", Console::FG_CYAN);

            // Check getenv()
            $getenvKey = getenv($keyName);
            $this->stdout("  getenv(): " . ($getenvKey ? "✓ Found (length: " . strlen($getenvKey) . ")" : "✗ Not found") . "\n");

            // Check $_ENV
            $envKey = $_ENV[$keyName] ?? null;
            $this->stdout("  \$_ENV: " . ($envKey ? "✓ Found (length: " . strlen($envKey) . ")" : "✗ Not found") . "\n");

            // Check Craft parseEnv
            $craftKey = Craft::parseEnv('$' . $keyName);
            $craftFound = $craftKey && $craftKey !== '$' . $keyName;
            $this->stdout("  Craft::parseEnv(): " . ($craftFound ? "✓ Found (length: " . strlen($craftKey) . ")" : "✗ Not found") . "\n");

            // Show prefix to verify format and check for issues
            if ($getenvKey) {
                $prefix = substr($getenvKey, 0, 8);
                $suffix = substr($getenvKey, -8);
                $expectedPrefix = $keyName === 'ANTHROPIC_API_KEY' ? 'sk-ant-' : 'sk-';
                $this->stdout("  Key prefix: '$prefix...' " . (strpos($getenvKey, $expectedPrefix) === 0 ? "✓" : "⚠ Expected '$expectedPrefix'") . "\n");
                $this->stdout("  Key suffix: '...$suffix'\n");

                // Check for common issues
                $issues = [];
                if (strlen(trim($getenvKey)) !== strlen($getenvKey)) {
                    $issues[] = "Leading/trailing whitespace detected";
                }
                if (strpos($getenvKey, '"') !== false || strpos($getenvKey, "'") !== false) {
                    $issues[] = "Quotes found in key";
                }
                if (!ctype_print($getenvKey)) {
                    $issues[] = "Non-printable characters detected";
                }

                if (!empty($issues)) {
                    $this->stdout("  ⚠ Issues: " . implode(', ', $issues) . "\n");
                } else {
                    $this->stdout("  ✓ No obvious formatting issues\n");
                }
            }
        }

        $this->stdout("\n🧪 Test API Key Manually:\n", Console::FG_CYAN);
        $this->stdout("  # Test Anthropic key with curl:\n");
        $this->stdout("  ddev exec curl -X POST https://api.anthropic.com/v1/messages \\\n");
        $this->stdout("    -H \"Content-Type: application/json\" \\\n");
        $this->stdout("    -H \"anthropic-version: 2023-06-01\" \\\n");
        $this->stdout("    -H \"x-api-key: \$ANTHROPIC_API_KEY\" \\\n");
        $this->stdout("    -d '{\"model\":\"claude-3-5-sonnet-20241022\",\"max_tokens\":10,\"messages\":[{\"role\":\"user\",\"content\":\"Hi\"}]}'\n");

        $this->stdout("\n💡 Setup Tips:\n", Console::FG_GREEN);
        $this->stdout("  - In DDEV: Set in .ddev/config.yaml under webimage_extra_environment\n");
        $this->stdout("  - Or use: ddev exec export ANTHROPIC_API_KEY=\"sk-ant-...\"\n");
        $this->stdout("  - Or add to .env file in project root\n");
        $this->stdout("  - Anthropic keys start with 'sk-ant-'\n");
        $this->stdout("  - OpenAI keys start with 'sk-'\n");
        $this->stdout("  - Check your Anthropic Console for key validity\n");

        return ExitCode::OK;
    }

    /**
     * Export system prompt and schema for manual testing
     */
    public function actionExportPrompt(): int
    {
        $this->stdout("Exporting LLM system prompt and schema for manual testing...\n", Console::FG_YELLOW);

        $plugin = Plugin::getInstance();

        // Load schema
        $schemaPath = $plugin->getBasePath() . '/schemas/llm-output-schema-v2.json';
        if (!file_exists($schemaPath)) {
            $this->stderr("Schema file not found: $schemaPath\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $schema = json_decode(file_get_contents($schemaPath), true);
        if (!$schema) {
            $this->stderr("Invalid JSON schema file\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Get system prompt
        $systemPrompt = $plugin->llmIntegrationService->buildSystemPrompt($schema);

        // Create export directory
        $exportDir = $plugin->getStoragePath() . DIRECTORY_SEPARATOR . 'llm-exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        // Export files
        $timestamp = date('Y-m-d_H-i-s');

        // 1. System prompt
        $promptFile = $exportDir . DIRECTORY_SEPARATOR . "system-prompt_$timestamp.txt";
        file_put_contents($promptFile, $systemPrompt);

        // 2. JSON Schema
        $schemaFile = $exportDir . DIRECTORY_SEPARATOR . "schema_$timestamp.json";
        file_put_contents($schemaFile, json_encode($schema, JSON_PRETTY_PRINT));

        // 3. Anthropic request template
        $anthropicTemplate = [
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 4000,
            'system' => $systemPrompt,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'CREATE_YOUR_PROMPT_HERE'
                ]
            ]
            // Note: Anthropic doesn't support response_format yet
            // JSON output is enforced through system prompt instructions
        ];
        $anthropicFile = $exportDir . DIRECTORY_SEPARATOR . "anthropic-request_$timestamp.json";
        file_put_contents($anthropicFile, json_encode($anthropicTemplate, JSON_PRETTY_PRINT));

        // 4. OpenAI request template
        $openaiTemplate = [
            'model' => 'gpt-4o-2024-08-06',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => 'CREATE_YOUR_PROMPT_HERE'
                ]
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'field_configuration',
                    'schema' => $schema
                ]
            ],
            'max_tokens' => 4000,
            'temperature' => 0.1
        ];
        $openaiFile = $exportDir . DIRECTORY_SEPARATOR . "openai-request_$timestamp.json";
        file_put_contents($openaiFile, json_encode($openaiTemplate, JSON_PRETTY_PRINT));

        // 5. Instructions file
        $instructions = <<<INSTRUCTIONS
# LLM Testing Export - $timestamp

This export contains everything needed to manually test the LLM integration using tools like Insomnia, Postman, or curl.

## Files Exported:

1. **system-prompt_$timestamp.txt**: The complete system prompt sent to LLMs
2. **schema_$timestamp.json**: JSON schema for structured output validation
3. **anthropic-request_$timestamp.json**: Complete Anthropic API request template
4. **openai-request_$timestamp.json**: Complete OpenAI API request template

## Usage Instructions:

### Anthropic API Testing:
- URL: https://api.anthropic.com/v1/messages
- Method: POST
- Headers:
  - x-api-key: YOUR_ANTHROPIC_API_KEY
  - Content-Type: application/json
  - anthropic-version: 2023-06-01
- Body: Use anthropic-request_$timestamp.json, replace 'CREATE_YOUR_PROMPT_HERE' with your test prompt

### OpenAI API Testing:
- URL: https://api.openai.com/v1/chat/completions
- Method: POST
- Headers:
  - Authorization: Bearer YOUR_OPENAI_API_KEY
  - Content-Type: application/json
- Body: Use openai-request_$timestamp.json, replace 'CREATE_YOUR_PROMPT_HERE' with your test prompt

### Example Test Prompts:
- "Create a blog with title, content, and featured image"
- "Create a product catalog with name, price, description, and images"
- "Create a portfolio with project title, description, images, and client name"
- "Create a real estate listing with property information"
- "Create a team member profile with name, bio, photo, and role"
- "Create a landing page hero section with headline, subheading, and CTA"

### Validation:
The response should match the schema in schema_$timestamp.json. You can use online JSON schema validators to verify responses.

## Debug Tips:
- Check that handles are camelCase starting with lowercase
- Verify all required fields are present
- Ensure field_type values match allowed enum values
- Check that dropdown fields include options array
- Validate that settings match field type requirements

INSTRUCTIONS;

        $instructionsFile = $exportDir . DIRECTORY_SEPARATOR . "README_$timestamp.txt";
        file_put_contents($instructionsFile, $instructions);

        $this->stdout("✓ Export completed successfully!\n", Console::FG_GREEN);
        $this->stdout("\nExported files:\n");
        $this->stdout("  📄 System Prompt: $promptFile\n");
        $this->stdout("  🗂 JSON Schema: $schemaFile\n");
        $this->stdout("  🟣 Anthropic Template: $anthropicFile\n");
        $this->stdout("  🟢 OpenAI Template: $openaiFile\n");
        $this->stdout("  📖 Instructions: $instructionsFile\n");

        $this->stdout("\n📂 All files saved to: $exportDir\n", Console::FG_CYAN);
        $this->stdout("\nUse these files with Insomnia, Postman, or curl to test LLM responses manually.\n");
        $this->stdout("See the README file for detailed usage instructions.\n");

        return ExitCode::OK;
    }

    /**
     * Rollback a field generation operation
     */
    public function actionRollback(string $operationId): int
    {
        $plugin = Plugin::getInstance();

        try {
            $results = $plugin->rollbackService->rollbackOperation($operationId);

            // Display deleted items
            if (!empty($results['deleted']['sections'])) {
                $this->stdout("✓ Successfully deleted sections:\n", Console::FG_GREEN);
                foreach ($results['deleted']['sections'] as $section) {
                    $this->stdout("  - {$section['name']} ({$section['handle']})\n");
                }
            }

            if (!empty($results['deleted']['entryTypes'])) {
                $this->stdout("✓ Successfully deleted entry types:\n", Console::FG_GREEN);
                foreach ($results['deleted']['entryTypes'] as $entryType) {
                    $this->stdout("  - {$entryType['name']} ({$entryType['handle']})\n");
                }
            }

            if (!empty($results['deleted']['fields'])) {
                $this->stdout("✓ Successfully deleted fields:\n", Console::FG_GREEN);
                foreach ($results['deleted']['fields'] as $field) {
                    $this->stdout("  - {$field['name']} ({$field['handle']})\n");
                }
            }

            if (!empty($results['deleted']['categoryGroups'])) {
                $this->stdout("✓ Successfully deleted category groups:\n", Console::FG_GREEN);
                foreach ($results['deleted']['categoryGroups'] as $categoryGroup) {
                    $this->stdout("  - {$categoryGroup['name']} ({$categoryGroup['handle']})\n");
                }
            }

            if (!empty($results['deleted']['tagGroups'])) {
                $this->stdout("✓ Successfully deleted tag groups:\n", Console::FG_GREEN);
                foreach ($results['deleted']['tagGroups'] as $tagGroup) {
                    $this->stdout("  - {$tagGroup['name']} ({$tagGroup['handle']})\n");
                }
            }

            // Display protected items
            if (!empty($results['protected']['sections'])) {
                $this->stdout("\n⚠ Protected sections (contain entries):\n", Console::FG_YELLOW);
                foreach ($results['protected']['sections'] as $section) {
                    $this->stdout("  - {$section['name']} ({$section['handle']}): {$section['reason']}\n");
                }
            }

            if (!empty($results['protected']['entryTypes'])) {
                $this->stdout("\n⚠ Protected entry types (have entries):\n", Console::FG_YELLOW);
                foreach ($results['protected']['entryTypes'] as $entryType) {
                    $this->stdout("  - {$entryType['name']} ({$entryType['handle']}): {$entryType['reason']}\n");
                }
            }

            if (!empty($results['protected']['fields'])) {
                $this->stdout("\n⚠ Protected fields (in use by entries):\n", Console::FG_YELLOW);
                foreach ($results['protected']['fields'] as $field) {
                    $this->stdout("  - {$field['name']} ({$field['handle']}): {$field['reason']}\n");
                }
            }

            if (!empty($results['protected']['categoryGroups'])) {
                $this->stdout("\n⚠ Protected category groups (contain categories):\n", Console::FG_YELLOW);
                foreach ($results['protected']['categoryGroups'] as $categoryGroup) {
                    $this->stdout("  - {$categoryGroup['name']} ({$categoryGroup['handle']}): {$categoryGroup['reason']}\n");
                }
            }

            if (!empty($results['protected']['tagGroups'])) {
                $this->stdout("\n⚠ Protected tag groups (contain tags):\n", Console::FG_YELLOW);
                foreach ($results['protected']['tagGroups'] as $tagGroup) {
                    $this->stdout("  - {$tagGroup['name']} ({$tagGroup['handle']}): {$tagGroup['reason']}\n");
                }
            }

            // Display failed items
            if (!empty($results['failed']['sections'])) {
                $this->stdout("\n✗ Failed to delete sections:\n", Console::FG_RED);
                foreach ($results['failed']['sections'] as $section) {
                    $this->stdout("  - {$section['handle']}: {$section['reason']}\n");
                }
            }

            if (!empty($results['failed']['entryTypes'])) {
                $this->stdout("\n✗ Failed to delete entry types:\n", Console::FG_RED);
                foreach ($results['failed']['entryTypes'] as $entryType) {
                    $this->stdout("  - {$entryType['handle']}: {$entryType['reason']}\n");
                }
            }

            if (!empty($results['failed']['fields'])) {
                $this->stdout("\n✗ Failed to delete fields:\n", Console::FG_RED);
                foreach ($results['failed']['fields'] as $field) {
                    $this->stdout("  - {$field['handle']}: {$field['reason']}\n");
                }
            }

            if (!empty($results['failed']['categoryGroups'])) {
                $this->stdout("\n✗ Failed to delete category groups:\n", Console::FG_RED);
                foreach ($results['failed']['categoryGroups'] as $categoryGroup) {
                    $this->stdout("  - {$categoryGroup['handle']}: {$categoryGroup['reason']}\n");
                }
            }

            if (!empty($results['failed']['tagGroups'])) {
                $this->stdout("\n✗ Failed to delete tag groups:\n", Console::FG_RED);
                foreach ($results['failed']['tagGroups'] as $tagGroup) {
                    $this->stdout("  - {$tagGroup['handle']}: {$tagGroup['reason']}\n");
                }
            }

            // Check if there was nothing to rollback
            $hasAnyItems = !empty($results['deleted']['sections']) || !empty($results['deleted']['entryTypes']) || !empty($results['deleted']['fields']) ||
                          !empty($results['deleted']['categoryGroups']) || !empty($results['deleted']['tagGroups']) ||
                          !empty($results['protected']['sections']) || !empty($results['protected']['entryTypes']) || !empty($results['protected']['fields']) ||
                          !empty($results['protected']['categoryGroups']) || !empty($results['protected']['tagGroups']) ||
                          !empty($results['failed']['sections']) || !empty($results['failed']['entryTypes']) || !empty($results['failed']['fields']) ||
                          !empty($results['failed']['categoryGroups']) || !empty($results['failed']['tagGroups']);

            if (!$hasAnyItems) {
                $this->stdout("No sections, entry types, fields, category groups, or tag groups to rollback.\n", Console::FG_YELLOW);
            }

            $this->stdout("\nRun 'ddev craft up' to apply changes.\n", Console::FG_CYAN);

        } catch (\Exception $e) {
            $this->stderr("Rollback failed: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    /**
     * Rollback the most recent operation
     */
    public function actionRollbackLast(): int
    {
        $plugin = Plugin::getInstance();
        $operations = $plugin->rollbackService->getOperations();

        if (empty($operations)) {
            $this->stdout("No operations found to rollback.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        // Get the most recent operation (operations are sorted newest first)
        $lastOperation = $operations[0];

        $this->stdout("Rolling back the most recent operation:\n", Console::FG_YELLOW);
        $this->stdout("  ID: {$lastOperation->id}\n");
        $this->stdout("  Type: {$lastOperation->type}\n");
        $this->stdout("  Date: " . date('Y-m-d H:i:s', $lastOperation->timestamp) . "\n");
        $this->stdout("  Source: " . substr($lastOperation->source, 0, 80) . (strlen($lastOperation->source) > 80 ? '...' : '') . "\n\n");

        try {
            $results = $plugin->rollbackService->rollbackOperation($lastOperation->id);

            // Display results
            if (!empty($results['deleted']['sections'])) {
                $this->stdout("✓ Successfully deleted sections:\n", Console::FG_GREEN);
                foreach ($results['deleted']['sections'] as $section) {
                    $this->stdout("  - {$section['name']} ({$section['handle']})\n");
                }
            }

            if (!empty($results['deleted']['entryTypes'])) {
                $this->stdout("✓ Successfully deleted entry types:\n", Console::FG_GREEN);
                foreach ($results['deleted']['entryTypes'] as $entryType) {
                    $this->stdout("  - {$entryType['name']} ({$entryType['handle']})\n");
                }
            }

            if (!empty($results['deleted']['fields'])) {
                $this->stdout("✓ Successfully deleted fields:\n", Console::FG_GREEN);
                foreach ($results['deleted']['fields'] as $field) {
                    $this->stdout("  - {$field['name']} ({$field['handle']})\n");
                }
            }

            if (!empty($results['deleted']['categoryGroups'])) {
                $this->stdout("✓ Successfully deleted category groups:\n", Console::FG_GREEN);
                foreach ($results['deleted']['categoryGroups'] as $categoryGroup) {
                    $this->stdout("  - {$categoryGroup['name']} ({$categoryGroup['handle']})\n");
                }
            }

            if (!empty($results['deleted']['tagGroups'])) {
                $this->stdout("✓ Successfully deleted tag groups:\n", Console::FG_GREEN);
                foreach ($results['deleted']['tagGroups'] as $tagGroup) {
                    $this->stdout("  - {$tagGroup['name']} ({$tagGroup['handle']})\n");
                }
            }

            if (!empty($results['protected']['sections']) || !empty($results['protected']['entryTypes']) || !empty($results['protected']['fields']) ||
                !empty($results['protected']['categoryGroups']) || !empty($results['protected']['tagGroups'])) {
                $this->stdout("⚠ Protected items (cannot delete, in use):\n", Console::FG_YELLOW);
                foreach ($results['protected']['sections'] as $section) {
                    $this->stdout("  - Section: {$section['name']} - {$section['reason']}\n");
                }
                foreach ($results['protected']['entryTypes'] as $entryType) {
                    $this->stdout("  - Entry Type: {$entryType['name']} - {$entryType['reason']}\n");
                }
                foreach ($results['protected']['fields'] as $field) {
                    $this->stdout("  - Field: {$field['name']} - {$field['reason']}\n");
                }
                foreach ($results['protected']['categoryGroups'] as $categoryGroup) {
                    $this->stdout("  - Category Group: {$categoryGroup['name']} - {$categoryGroup['reason']}\n");
                }
                foreach ($results['protected']['tagGroups'] as $tagGroup) {
                    $this->stdout("  - Tag Group: {$tagGroup['name']} - {$tagGroup['reason']}\n");
                }
            }

            if (!empty($results['failed']['sections']) || !empty($results['failed']['entryTypes']) || !empty($results['failed']['fields']) ||
                !empty($results['failed']['categoryGroups']) || !empty($results['failed']['tagGroups'])) {
                $this->stdout("✗ Failed to delete:\n", Console::FG_RED);
                foreach ($results['failed']['sections'] as $section) {
                    $this->stdout("  - Section: {$section['name']} - {$section['reason']}\n");
                }
                foreach ($results['failed']['entryTypes'] as $entryType) {
                    $this->stdout("  - Entry Type: {$entryType['name']} - {$entryType['reason']}\n");
                }
                foreach ($results['failed']['fields'] as $field) {
                    $this->stdout("  - Field: {$field['name']} - {$field['reason']}\n");
                }
                foreach ($results['failed']['categoryGroups'] as $categoryGroup) {
                    $this->stdout("  - Category Group: {$categoryGroup['name']} - {$categoryGroup['reason']}\n");
                }
                foreach ($results['failed']['tagGroups'] as $tagGroup) {
                    $this->stdout("  - Tag Group: {$tagGroup['name']} - {$tagGroup['reason']}\n");
                }
            }

            if (empty($results['deleted']['sections']) && empty($results['deleted']['entryTypes']) && empty($results['deleted']['fields']) && 
                empty($results['deleted']['categoryGroups']) && empty($results['deleted']['tagGroups'])) {
                $this->stdout("No sections, entry types, fields, category groups, or tag groups to rollback.\n", Console::FG_YELLOW);
            }

            $this->stdout("\nRun 'ddev craft up' to apply changes.\n", Console::FG_CYAN);

        } catch (\Exception $e) {
            $this->stderr("Rollback failed: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    /**
     * Rollback all operations (excluding already rolled back ones)
     */
    public function actionRollbackAll(): int
    {
        $plugin = Plugin::getInstance();
        $operations = $plugin->rollbackService->getOperations();

        if (empty($operations)) {
            $this->stdout("No operations found to rollback.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        // Filter out already rolled back operations
        $activeOperations = array_filter($operations, function($operation) {
            return !($operation->description && strpos($operation->description, '[ROLLED BACK]') !== false);
        });

        if (empty($activeOperations)) {
            $this->stdout("No active operations found to rollback (all operations already rolled back).\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $this->stdout("Found " . count($activeOperations) . " active operations to rollback:\n\n", Console::FG_YELLOW);

        // Show what will be rolled back
        foreach (array_slice($activeOperations, 0, 5) as $operation) {
            $this->stdout("  - {$operation->id} ({$operation->type}) - " . date('Y-m-d H:i:s', $operation->timestamp) . "\n");
        }

        if (count($activeOperations) > 5) {
            $remaining = count($activeOperations) - 5;
            $this->stdout("  ... and {$remaining} more operations\n");
        }

        // Confirmation prompt
        if (!$this->force) {
            $this->stdout("\n⚠ WARNING: This will rollback ALL active operations!\n", Console::FG_RED);
            $this->stdout("This action cannot be undone. Are you sure? [y/N]: ");

            $handle = fopen("php://stdin", "r");
            $response = trim(fgets($handle));
            fclose($handle);

            if (!in_array(strtolower($response), ['y', 'yes'])) {
                $this->stdout("Rollback cancelled.\n", Console::FG_YELLOW);
                return ExitCode::OK;
            }
        }

        $this->stdout("\nRolling back " . count($activeOperations) . " operations...\n", Console::FG_CYAN);

        $totalResults = [
            'deleted' => ['sections' => [], 'entryTypes' => [], 'fields' => [], 'categoryGroups' => [], 'tagGroups' => []],
            'protected' => ['sections' => [], 'entryTypes' => [], 'fields' => [], 'categoryGroups' => [], 'tagGroups' => []],
            'failed' => ['sections' => [], 'entryTypes' => [], 'fields' => [], 'categoryGroups' => [], 'tagGroups' => []],
            'errors' => []
        ];

        $successCount = 0;
        $errorCount = 0;

        // Rollback each operation (newest first for proper dependency handling)
        foreach ($activeOperations as $operation) {
            try {
                $this->stdout("  Rolling back {$operation->id}... ");
                $results = $plugin->rollbackService->rollbackOperation($operation->id);

                // Merge results
                foreach (['deleted', 'protected', 'failed'] as $category) {
                    foreach (['sections', 'entryTypes', 'fields', 'categoryGroups', 'tagGroups'] as $type) {
                        $totalResults[$category][$type] = array_merge(
                            $totalResults[$category][$type],
                            $results[$category][$type] ?? []
                        );
                    }
                }

                $deletedCount = count($results['deleted']['sections'] ?? []) +
                               count($results['deleted']['entryTypes'] ?? []) +
                               count($results['deleted']['fields'] ?? []) +
                               count($results['deleted']['categoryGroups'] ?? []) +
                               count($results['deleted']['tagGroups'] ?? []);

                if ($deletedCount > 0) {
                    $this->stdout("✓ ({$deletedCount} items)\n", Console::FG_GREEN);
                } else {
                    $this->stdout("- (no items to delete)\n", Console::FG_YELLOW);
                }

                $successCount++;

            } catch (\Exception $e) {
                $this->stdout("✗ ({$e->getMessage()})\n", Console::FG_RED);
                $totalResults['errors'][] = [
                    'operation_id' => $operation->id,
                    'reason' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        // Display summary
        $this->stdout("\n=== Rollback Summary ===\n", Console::FG_CYAN);
        $this->stdout("Operations processed: " . count($activeOperations) . "\n");
        $this->stdout("Successful: {$successCount}\n", Console::FG_GREEN);
        if ($errorCount > 0) {
            $this->stdout("Failed: {$errorCount}\n", Console::FG_RED);
        }

        // Display item counts
        $totalDeleted = count($totalResults['deleted']['sections']) +
                       count($totalResults['deleted']['entryTypes']) +
                       count($totalResults['deleted']['fields']) +
                       count($totalResults['deleted']['categoryGroups']) +
                       count($totalResults['deleted']['tagGroups']);

        if ($totalDeleted > 0) {
            $this->stdout("\nItems successfully deleted:\n", Console::FG_GREEN);
            $this->stdout("  - Sections: " . count($totalResults['deleted']['sections']) . "\n");
            $this->stdout("  - Entry Types: " . count($totalResults['deleted']['entryTypes']) . "\n");
            $this->stdout("  - Fields: " . count($totalResults['deleted']['fields']) . "\n");
            $this->stdout("  - Category Groups: " . count($totalResults['deleted']['categoryGroups']) . "\n");
            $this->stdout("  - Tag Groups: " . count($totalResults['deleted']['tagGroups']) . "\n");
        }

        $totalProtected = count($totalResults['protected']['sections']) +
                         count($totalResults['protected']['entryTypes']) +
                         count($totalResults['protected']['fields']) +
                         count($totalResults['protected']['categoryGroups']) +
                         count($totalResults['protected']['tagGroups']);

        if ($totalProtected > 0) {
            $this->stdout("\nItems protected (in use):\n", Console::FG_YELLOW);
            $this->stdout("  - Sections: " . count($totalResults['protected']['sections']) . "\n");
            $this->stdout("  - Entry Types: " . count($totalResults['protected']['entryTypes']) . "\n");
            $this->stdout("  - Fields: " . count($totalResults['protected']['fields']) . "\n");
            $this->stdout("  - Category Groups: " . count($totalResults['protected']['categoryGroups']) . "\n");
            $this->stdout("  - Tag Groups: " . count($totalResults['protected']['tagGroups']) . "\n");
        }

        if (!empty($totalResults['errors'])) {
            $this->stdout("\nErrors encountered:\n", Console::FG_RED);
            foreach ($totalResults['errors'] as $error) {
                $this->stdout("  - {$error['operation_id']}: {$error['reason']}\n");
            }
        }

        $this->stdout("\nRun 'ddev craft up' to apply changes.\n", Console::FG_CYAN);

        return ExitCode::OK;
    }

    /**
     * List all recorded operations
     */
    public function actionOperations(): int
    {
        $plugin = Plugin::getInstance();
        $operations = $plugin->rollbackService->getOperations();

        if (empty($operations)) {
            $this->stdout("No operations recorded.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $this->stdout("Field Generation Operations:\n\n", Console::FG_YELLOW);

        foreach ($operations as $operation) {
            $status = strpos($operation->description ?? '', '[ROLLED BACK]') !== false ?
                Console::FG_RED : Console::FG_GREEN;

            $this->stdout("ID: {$operation->id}\n", $status);
            $this->stdout("  Type: {$operation->type}\n");
            $this->stdout("  Source: {$operation->source}\n");
            $this->stdout("  Date: {$operation->getFormattedTimestamp()}\n");
            $this->stdout("  Fields Created: {$operation->getFieldCount()}\n");
            $this->stdout("  Entry Types Created: {$operation->getEntryTypeCount()}\n");
            $this->stdout("  Sections Created: {$operation->getSectionCount()}\n");

            if ($operation->description) {
                $this->stdout("  Status: {$operation->description}\n");
            }

            if (!empty($operation->failedFields)) {
                $this->stdout("  Failed Fields: " . count($operation->failedFields) . "\n");
            }
            if (!empty($operation->failedEntryTypes)) {
                $this->stdout("  Failed Entry Types: " . count($operation->failedEntryTypes) . "\n");
            }
            if (!empty($operation->failedSections)) {
                $this->stdout("  Failed Sections: " . count($operation->failedSections) . "\n");
            }

            $this->stdout("\n");
        }

        $this->stdout("Use 'field-agent/generator/rollback <operation-id>' to undo an operation.\n", Console::FG_CYAN);

        return ExitCode::OK;
    }

    /**
     * List available test suites
     */
    public function actionTestList(): int
    {
        $testSuites = $this->discoverTestSuites();

        if (empty($testSuites)) {
            $this->stdout("No test suites found.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $this->stdout("Available Test Suites:\n\n", Console::FG_GREEN);

        foreach ($testSuites as $category => $tests) {
            $this->stdout("📁 {$category}:\n", Console::FG_CYAN);
            foreach ($tests as $test) {
                $this->stdout("  🧪 {$test['filename']} - {$test['description']}\n");
                if (isset($test['prerequisite'])) {
                    $this->stdout("     ⚠️  Prerequisite: {$test['prerequisite']}\n", Console::FG_YELLOW);
                }
                if (isset($test['expectedOutcome']['fieldsCreated'])) {
                    $fieldsCount = $test['expectedOutcome']['fieldsCreated'];
                    $this->stdout("     📊 Creates: {$fieldsCount} fields\n", Console::FG_GREY);
                }
            }
            $this->stdout("\n");
        }

        $this->stdout("Usage:\n", Console::FG_CYAN);
        $this->stdout("  field-agent/generator/test-run <test-name>     Run individual test\n");
        $this->stdout("  field-agent/generator/test-suite <category>    Run entire test suite\n");
        $this->stdout("  field-agent/generator/test-all                 Run all tests\n");

        return ExitCode::OK;
    }

    /**
     * Run a specific test
     */
    public function actionTestRun($testName = null): int
    {
        if (!$testName) {
            $this->stderr("Test name is required.\n", Console::FG_RED);
            $this->stdout("Use 'field-agent/generator/test-list' to see available tests.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $testFile = $this->findTestFile($testName);
        if (!$testFile) {
            $this->stderr("Test '{$testName}' not found.\n", Console::FG_RED);
            $this->stdout("Use 'field-agent/generator/test-list' to see available tests.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("🧪 Running test: {$testName}\n", Console::FG_YELLOW);
        $this->stdout("📄 Test file: {$testFile}\n");
        if ($this->cleanup) {
            $this->stdout("🧹 Cleanup enabled - will remove test data after completion\n", Console::FG_CYAN);
        }

        $startTime = microtime(true);
        $result = $this->executeTestFile($testFile, $this->cleanup);
        $duration = round(microtime(true) - $startTime, 2);

        if ($result === ExitCode::OK) {
            $this->stdout("✅ Test completed successfully in {$duration}s\n", Console::FG_GREEN);
        } else {
            $this->stdout("❌ Test failed after {$duration}s\n", Console::FG_RED);
        }

        return $result;
    }

    /**
     * Run an entire test suite category
     */
    public function actionTestSuite($category = null): int
    {
        if (!$category) {
            $this->stderr("Test suite category is required.\n", Console::FG_RED);
            $this->stdout("Available categories: basic-operations, advanced-operations, integration-tests, edge-cases\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $testSuites = $this->discoverTestSuites();
        if (!isset($testSuites[$category])) {
            $this->stderr("Test suite '{$category}' not found.\n", Console::FG_RED);
            $this->stdout("Available categories: " . implode(', ', array_keys($testSuites)) . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $tests = $testSuites[$category];
        $this->stdout("🧪 Running test suite: {$category} ({" . count($tests) . "} tests)\n", Console::FG_YELLOW);
        if ($this->cleanup) {
            $this->stdout("🧹 Cleanup enabled - will remove test data after each test\n", Console::FG_CYAN);
        }
        $this->stdout("\n");

        $passed = 0;
        $failed = 0;
        $startTime = microtime(true);

        foreach ($tests as $test) {
            $this->stdout("Running {$test['filename']}... ", Console::FG_CYAN);
            $testStartTime = microtime(true);

            $result = $this->executeTestFile($test['path'], $this->cleanup);
            $testDuration = round(microtime(true) - $testStartTime, 2);

            if ($result === ExitCode::OK) {
                $this->stdout("✅ PASS ({$testDuration}s)\n", Console::FG_GREEN);
                $passed++;
            } else {
                $this->stdout("❌ FAIL ({$testDuration}s)\n", Console::FG_RED);
                $failed++;
            }
        }

        $totalDuration = round(microtime(true) - $startTime, 2);
        $this->stdout("\n📊 Test Suite Results:\n", Console::FG_YELLOW);
        $this->stdout("  ✅ Passed: {$passed}\n", Console::FG_GREEN);
        $this->stdout("  ❌ Failed: {$failed}\n", $failed > 0 ? Console::FG_RED : Console::FG_GREY);
        $this->stdout("  ⏱️  Duration: {$totalDuration}s\n");

        return $failed > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * Run all test suites
     */
    public function actionTestAll(): int
    {
        $testSuites = $this->discoverTestSuites();

        if (empty($testSuites)) {
            $this->stdout("No test suites found.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $this->stdout("🧪 Running all test suites...\n", Console::FG_YELLOW);
        if ($this->cleanup) {
            $this->stdout("🧹 Cleanup enabled - will remove test data after each test\n", Console::FG_CYAN);
        }
        $this->stdout("\n");

        $totalPassed = 0;
        $totalFailed = 0;
        $startTime = microtime(true);

        foreach ($testSuites as $category => $tests) {
            $this->stdout("📁 {$category}:\n", Console::FG_CYAN);

            foreach ($tests as $test) {
                $this->stdout("  Running {$test['filename']}... ");
                $testStartTime = microtime(true);

                $result = $this->executeTestFile($test['path'], $this->cleanup);
                $testDuration = round(microtime(true) - $testStartTime, 2);

                if ($result === ExitCode::OK) {
                    $this->stdout("✅ PASS ({$testDuration}s)\n", Console::FG_GREEN);
                    $totalPassed++;
                } else {
                    $this->stdout("❌ FAIL ({$testDuration}s)\n", Console::FG_RED);
                    $totalFailed++;
                }
            }
            $this->stdout("\n");
        }

        $totalDuration = round(microtime(true) - $startTime, 2);
        $this->stdout("📊 Overall Test Results:\n", Console::FG_YELLOW);
        $this->stdout("  ✅ Total Passed: {$totalPassed}\n", Console::FG_GREEN);
        $this->stdout("  ❌ Total Failed: {$totalFailed}\n", $totalFailed > 0 ? Console::FG_RED : Console::FG_GREY);
        $this->stdout("  ⏱️  Total Duration: {$totalDuration}s\n");

        return $totalFailed > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * Show help information
     */
    public function actionHelp(): int
    {
        $this->stdout("Field Generator Commands\n\n", Console::FG_YELLOW);
        $this->stdout("Available commands:\n");
        $this->stdout("  field-agent/generator/generate <preset|config.json|stored-name>	Generate fields from preset, JSON config, or stored config\n");
        $this->stdout("  field-agent/generator/prompt \"description\" [provider] [options]	Generate fields from natural language (AI/LLM)\n");
        $this->stdout("  field-agent/generator/test-llm [provider] [--debug]             	Test AI/LLM API connection\n");
        $this->stdout("  field-agent/generator/check-keys                               	Check API key configuration\n");
        $this->stdout("  field-agent/generator/export-prompt                            	Export system prompt for manual testing\n");
        $this->stdout("  field-agent/generator/list                                       	List built-in presets and stored configurations\n");
        $this->stdout("  field-agent/generator/basic-fields                               	Generate basic field set\n");
        $this->stdout("  field-agent/generator/operations                                 	List all operations (for rollback)\n");
        $this->stdout("  field-agent/generator/rollback <operation-id>                    	Rollback a field generation operation\n");
        $this->stdout("  field-agent/generator/rollback-last                              	Rollback the most recent operation\n");
        $this->stdout("  field-agent/generator/rollback-all [--force]                     	Rollback all active operations\n");
        $this->stdout("  field-agent/generator/sync-config                                	Fix orphaned fields by rebuilding project config\n");
        $this->stdout("  field-agent/generator/stats                                      	Show storage statistics\n");
        $this->stdout("  field-agent/generator/prune-rolled-back                          	Delete rolled back operations\n");
        $this->stdout("  field-agent/generator/prune-configs [days]                       	Delete old config files (default: 7 days)\n");
        $this->stdout("  field-agent/generator/prune-all --confirm=1                      	Delete ALL configs and operations\n");
        $this->stdout("  field-agent/generator/help                                       	Show this help\n");

        $this->stdout("\nTest Framework:\n", Console::FG_PURPLE);
        $this->stdout("  field-agent/generator/test-list                                  	List available test suites\n");
        $this->stdout("  field-agent/generator/test-run <test-name> [--cleanup]           	Run specific test (with optional cleanup)\n");
        $this->stdout("  field-agent/generator/test-suite <category> [--cleanup]          	Run test suite category (with optional cleanup)\n");
        $this->stdout("  field-agent/generator/test-all [--cleanup]                       	Run all tests (with optional cleanup)\n");

        $this->stdout("\nAI/LLM Integration:\n", Console::FG_GREEN);
        $this->stdout("  Providers: anthropic (default), openai\n");
        $this->stdout("  Set API keys: ANTHROPIC_API_KEY or OPENAI_API_KEY environment variables\n");

        $this->stdout("\nStorage Management:\n", Console::FG_CYAN);
        $this->stdout("  stats              Show storage usage and file counts\n");
        $this->stdout("  prune-rolled-back  Remove operations that have been rolled back (safe)\n");
        $this->stdout("  prune-configs      Remove old config files (default: 7+ days old)\n");
        $this->stdout("  prune-all          Nuclear option - remove everything (requires --confirm=1)\n");

        $this->stdout("\nPrompt Options:\n", Console::FG_CYAN);
        $this->stdout("  --dry-run          Generate config only, don't create fields\n");
        $this->stdout("  --output <path>    Save config to custom path (implies dry-run)\n");
        $this->stdout("  --debug, -d        Show full request/response details\n");

        $this->stdout("\nExamples:\n", Console::FG_YELLOW);
        $this->stdout("  # Standard workflow (config + create fields):\n");
        $this->stdout("  field-agent/generator/prompt \"Create a product catalog\" anthropic\n");

        $this->stdout("\n  # Decoupled workflow (config only):\n");
        $this->stdout("  field-agent/generator/prompt \"Create a blog\" --dry-run\n");
        $this->stdout("  field-agent/generator/generate llm_2025-01-01_12-30-45\n");

        $this->stdout("\n  # Save to custom file:\n");
        $this->stdout("  field-agent/generator/prompt \"Create a portfolio\" --output my-config.json\n");
        $this->stdout("  field-agent/generator/generate my-config.json\n");

        $this->stdout("\n  # Provider options (anthropic is default):\n");
        $this->stdout("  field-agent/generator/prompt \"Create a blog\" openai --dry-run\n");
        $this->stdout("  field-agent/generator/prompt \"Create a team page\" anthropic --debug --dry-run\n");
        $this->stdout("  field-agent/generator/test-llm openai --debug\n");

        $this->stdout("\nBuilt-in presets:\n", Console::FG_GREEN);
        $presets = $this->listBuiltInPresets();
        if (!empty($presets)) {
            foreach ($presets as $preset) {
                $this->stdout("  📦 {$preset['filename']} - {$preset['description']}\n");
            }
        } else {
            $this->stdout("  No built-in presets available\n");
        }

        $this->stdout("\nSupported field types:\n", Console::FG_YELLOW);
        $this->stdout("  Text: plain_text, rich_text, email\n");
        $this->stdout("  Assets: image, asset\n");
        $this->stdout("  Numbers: number, money, range\n");
        $this->stdout("  Links: url (Link field with URL type)\n");
        $this->stdout("  Selection: dropdown, radio_buttons, checkboxes, multi_select, country\n");
        $this->stdout("  Date/Time: date, time\n");
        $this->stdout("  UI: color, lightswitch, button_group, icon\n");
        $this->stdout("  Relational: entries, users\n");
        $this->stdout("  Complex: matrix\n\n");
        // $this->stdout("  Relational: categories, entries, tags, users\n"); // Not implemented yet
        // $this->stdout("  Complex: table, matrix\n"); // Not implemented yet

        return ExitCode::OK;
    }

    /**
     * Load configuration from file, stored config, or built-in preset
     */
    private function loadConfig(string $config): ?array
    {
        // Check if it's a file path
        if (file_exists($config)) {
            $configData = json_decode(file_get_contents($config), true);
            if (!$configData) {
                $this->stderr("Invalid JSON in config file: $config\n", Console::FG_RED);
                return null;
            }
            return $configData;
        }

        // Check if it's a built-in preset
        $presetData = $this->loadBuiltInPreset($config);
        if ($presetData) {
            $this->stdout("Using built-in preset: $config\n", Console::FG_GREEN);
            return $presetData;
        }

        // Check if it's a stored config
        $plugin = Plugin::getInstance();
        $configData = $plugin->fieldGeneratorService->getStoredConfig($config);
        if ($configData) {
            $this->stdout("Using stored configuration: $config\n", Console::FG_CYAN);
            return $configData;
        }

        $this->stderr("Config not found: $config\n", Console::FG_RED);
        $this->stderr("Use 'field-agent/generator/list' to see available presets and configs.\n");
        return null;
    }

    /**
     * List built-in presets (excluding tests)
     */
    private function listBuiltInPresets(): array
    {
        $presets = [];
        $storageDir = Craft::$app->path->getStoragePath() . '/field-agent/presets';

        if (!is_dir($storageDir)) {
            return $presets;
        }

        // Get all JSON files in the presets directory, but exclude the tests subdirectory
        $files = glob($storageDir . '/*.json');
        foreach ($files as $file) {
            $filename = basename($file, '.json');
            $data = json_decode(file_get_contents($file), true);

            if ($data) {
                $presets[] = [
                    'filename' => $filename,
                    'name' => $data['name'] ?? $filename,
                    'description' => $data['description'] ?? 'User preset',
                    'version' => $data['version'] ?? '1.0.0'
                ];
            }
        }

        // Also check for built-in presets in the plugin directory
        $pluginPresetsDir = Plugin::getInstance()->getBasePath() . '/presets';
        if (is_dir($pluginPresetsDir)) {
            $builtInFiles = glob($pluginPresetsDir . '/*.json');
            foreach ($builtInFiles as $file) {
                $filename = basename($file, '.json');
                $data = json_decode(file_get_contents($file), true);

                if ($data) {
                    $presets[] = [
                        'filename' => $filename,
                        'name' => $data['name'] ?? $filename,
                        'description' => $data['description'] ?? 'Built-in preset',
                        'version' => $data['version'] ?? '1.0.0'
                    ];
                }
            }
        }

        return $presets;
    }

    /**
     * Load a built-in preset
     */
    private function loadBuiltInPreset(string $presetName): ?array
    {
        $presetsDir = Plugin::getInstance()->getBasePath() . '/presets';
        $presetFile = $presetsDir . '/' . $presetName . '.json';

        if (!file_exists($presetFile)) {
            return null;
        }

        $data = json_decode(file_get_contents($presetFile), true);
        if (!$data) {
            $this->stderr("Invalid JSON in preset file: $presetName\n", Console::FG_RED);
            return null;
        }

        return $data;
    }

    /**
     * Generate configuration from natural language prompt
     */
    private function generateConfigFromPrompt(string $prompt): array
    {
        // This is a placeholder - in a real implementation, you'd use AI/LLM here
        // For now, return a basic blog config as an example

        if (stripos($prompt, 'blog') !== false) {
            return [
                'fields' => [
                    [
                        'name' => 'Blog Title',
                        'handle' => 'blogTitle',
                        'field_type' => 'plain_text',
                        'instructions' => 'The main title of the blog post',
                        'searchable' => true
                    ],
                    [
                        'name' => 'Blog Content',
                        'handle' => 'blogContent',
                        'field_type' => 'rich_text',
                        'instructions' => 'The main content of the blog post',
                        'searchable' => true
                    ],
                    [
                        'name' => 'Featured Image',
                        'handle' => 'featuredImage2',
                        'field_type' => 'image',
                        'instructions' => 'Main image for the blog post'
                    ]
                ]
            ];
        }

        // Default fallback
        return [
            'fields' => [
                [
                    'name' => 'Title',
                    'handle' => 'promptTitle',
                    'field_type' => 'plain_text',
                    'instructions' => 'Generated from prompt: ' . $prompt
                ]
            ]
        ];
    }

    /**
     * Create a field from config array
     */
    protected function createFieldFromConfig(array $config)
    {
        $fieldType = $config['field_type'] ?? '';
        $field = null;

        switch ($fieldType) {
            case 'plain_text':
            case 'text':
                $field = new PlainText();
                $field->multiline = $config['multiline'] ?? false;
                $field->initialRows = $field->multiline ? 4 : 1;
                break;

            case 'rich_text':
            case 'richtext':
                if (class_exists(CKEditorField::class)) {
                    $field = new CKEditorField();
                    $field->purifyHtml = true;
                } else {
                    $this->stderr("CKEditor plugin not installed, skipping rich text field\n", Console::FG_YELLOW);
                    return null;
                }
                break;

            case 'image':
            case 'asset':
                $field = new Assets();
                $field->allowedKinds = $fieldType === 'image' ? ['image'] : null;
                $field->maxRelations = 1;
                $field->viewMode = 'list';
                break;

            case 'number':
                $field = new Number();
                $field->decimals = $config['decimals'] ?? 0;
                break;

            case 'url':
                $field = new Link();
                $field->types = ['url'];
                $field->showLabelField = false;
                $field->maxLength = 255;

                // Set URL-specific type settings with supported properties only
                $field->typeSettings = [
                    'url' => [
                        'allowRootRelativeUrls' => $config['allow_root_relative_urls'] ?? true,
                        'allowAnchors' => $config['allow_anchors'] ?? true,
                        'allowCustomSchemes' => $config['allow_custom_schemes'] ?? false,
                    ],
                ];
                break;

            case 'dropdown':
                $field = new Dropdown();
                $field->options = $this->prepareOptions($config['options'] ?? []);
                break;

            case 'radio_buttons':
            case 'radio':
                $field = new RadioButtons();
                $field->options = $this->prepareOptions($config['options'] ?? []);
                break;

            case 'checkboxes':
                $field = new Checkboxes();
                $field->options = $this->prepareOptions($config['options'] ?? []);
                break;

            case 'multi_select':
            case 'multiselect':
                $field = new MultiSelect();
                $field->options = $this->prepareOptions($config['options'] ?? []);
                break;

            case 'country':
                $field = new Country();
                break;

            case 'date':
                $field = new Date();
                $field->showTimeZone = $config['show_timezone'] ?? false;
                $field->showDate = $config['show_date'] ?? true;
                $field->showTime = $config['show_time'] ?? false;
                break;

            case 'time':
                $field = new Time();
                break;

            case 'email':
                $field = new Email();
                $field->placeholder = $config['placeholder'] ?? 'Enter email address';
                break;

            case 'color':
                $field = new Color();
                $field->palette = $config['palette'] ?? [
                    ['color' => '#ff0000', 'label' => 'Red'],
                    ['color' => '#00ff00', 'label' => 'Green'],
                    ['color' => '#0000ff', 'label' => 'Blue'],
                    ['color' => '#ffff00', 'label' => 'Yellow'],
                    ['color' => '#ff00ff', 'label' => 'Magenta'],
                    ['color' => '#00ffff', 'label' => 'Cyan'],
                ];
                $field->allowCustomColors = $config['allow_custom_colors'] ?? true;
                break;

            case 'lightswitch':
            case 'toggle':
                $field = new Lightswitch();
                $field->default = $config['default'] ?? false;
                $field->onLabel = $config['on_label'] ?? 'On';
                $field->offLabel = $config['off_label'] ?? 'Off';
                break;

            case 'money':
                $field = new Money();
                $field->currency = $config['currency'] ?? 'USD';
                $field->showCurrency = $config['show_currency'] ?? true;
                $field->min = $config['min'] ?? null;
                $field->max = $config['max'] ?? null;
                break;

            case 'range':
                $field = new Range();
                $field->min = $config['min'] ?? 0;
                $field->max = $config['max'] ?? 100;
                $field->step = $config['step'] ?? 1;
                $field->suffix = $config['suffix'] ?? '';
                break;

            case 'button_group':
            case 'buttongroup':
                $field = new ButtonGroup();
                $field->options = $this->prepareButtonGroupOptions($config['options'] ?? []);
                break;

            case 'icon':
                $field = new Icon();
                // Icon field configuration is handled through the field settings
                break;

            case 'table':
                $field = new Table();
                $field->columns = $this->prepareTableColumns($config['columns'] ?? []);
                $field->defaults = $config['defaults'] ?? [];
                $field->addRowLabel = $config['add_row_label'] ?? 'Add a row';
                $field->maxRows = $config['max_rows'] ?? null;
                $field->minRows = $config['min_rows'] ?? null;
                break;

            case 'categories':
                $field = new Categories();
                $field->allowLimit = $config['allow_limit'] ?? true;
                $field->selectionLabel = $config['selection_label'] ?? 'Choose categories';
                // Note: Category group sources and limits would need to be configured after creation
                break;

            case 'entries':
                $field = new Entries();
                $field->allowLimit = $config['allow_limit'] ?? true;
                $field->selectionLabel = $config['selection_label'] ?? 'Choose entries';
                // Note: Entry sources and limits would need to be configured after creation
                break;

            case 'tags':
                $field = new Tags();
                $field->allowLimit = $config['allow_limit'] ?? true;
                $field->selectionLabel = $config['selection_label'] ?? 'Choose tags';
                // Note: Tag group sources and limits would need to be configured after creation
                break;

            case 'users':
                $field = new Users();
                $field->allowLimit = $config['allow_limit'] ?? true;
                $field->selectionLabel = $config['selection_label'] ?? 'Choose users';
                // Note: User limits would need to be configured after creation
                break;

            case 'matrix':
                // Matrix fields require block types which are complex to create programmatically
                // For now, we'll skip creating Matrix fields and guide users to the admin panel
                $this->stderr("Matrix fields require block type configuration and cannot be created via this tool.\n", Console::FG_YELLOW);
                $this->stderr("Please create Matrix fields through Settings > Fields in the admin panel.\n", Console::FG_YELLOW);
                $this->stderr("You can then configure block types with your desired field layouts.\n", Console::FG_YELLOW);
                return null;

            default:
                $this->stderr("Unsupported field type: $fieldType\n", Console::FG_RED);
                return null;
        }

        if ($field) {
            $field->name = $config['name'];
            $field->handle = $config['handle'];
            $field->instructions = $config['instructions'] ?? '';
            $field->searchable = $config['searchable'] ?? false;
            $field->translationMethod = 'none';

            // Set required property if specified and field supports it
            if (isset($config['required']) && property_exists($field, 'required')) {
                $field->required = $config['required'];
            }
        }

        return $field;
    }

    /**
     * Prepare options array for selection fields
     */
    private function prepareOptions(array $options): array
    {
        $preparedOptions = [];

        foreach ($options as $option) {
            if (is_string($option)) {
                // Simple string option: "Option 1"
                $preparedOptions[] = [
                    'label' => $option,
                    'value' => $option,
                    'default' => false,
                ];
            } elseif (is_array($option)) {
                // Array option: {"label": "Option 1", "value": "opt1", "default": true}
                $preparedOptions[] = [
                    'label' => $option['label'] ?? $option['value'] ?? '',
                    'value' => $option['value'] ?? $option['label'] ?? '',
                    'default' => $option['default'] ?? false,
                ];
            }
        }

        return $preparedOptions;
    }

    /**
     * Prepare options array for button group fields
     */
    private function prepareButtonGroupOptions(array $options): array
    {
        $preparedOptions = [];

        foreach ($options as $option) {
            if (is_string($option)) {
                // Simple string option: "Option 1"
                $preparedOptions[] = [
                    'label' => $option,
                    'value' => $option,
                    'icon' => '',
                    'default' => false,
                ];
            } elseif (is_array($option)) {
                // Array option: {"label": "Option 1", "value": "opt1", "icon": "icon-name", "default": true}
                $preparedOptions[] = [
                    'label' => $option['label'] ?? $option['value'] ?? '',
                    'value' => $option['value'] ?? $option['label'] ?? '',
                    'icon' => $option['icon'] ?? '',
                    'default' => $option['default'] ?? false,
                ];
            }
        }

        return $preparedOptions;
    }

    /**
     * Prepare columns array for table fields
     */
    private function prepareTableColumns(array $columns): array
    {
        $preparedColumns = [];

        foreach ($columns as $column) {
            if (is_string($column)) {
                // Simple string column: "Column Name"
                $preparedColumns[] = [
                    'heading' => $column,
                    'handle' => $this->createHandle($column),
                    'type' => 'singleline',
                    'width' => '',
                ];
            } elseif (is_array($column)) {
                // Array column: {"heading": "Name", "handle": "name", "type": "singleline", "width": "50%"}
                $preparedColumns[] = [
                    'heading' => $column['heading'] ?? $column['handle'] ?? '',
                    'handle' => $column['handle'] ?? $this->createHandle($column['heading'] ?? ''),
                    'type' => $column['type'] ?? 'singleline', // singleline, multiline, number, checkbox, color, url, email, date, time
                    'width' => $column['width'] ?? '',
                ];
            }
        }

        return $preparedColumns;
    }

    /**
     * Create a handle from a string
     */
    private function createHandle(string $name): string
    {
        // Convert to camelCase handle
        $handle = trim(preg_replace('/[^a-zA-Z0-9]/', ' ', $name));
        $handle = str_replace(' ', '', ucwords(strtolower($handle)));
        return lcfirst($handle);
    }


    /**
     * Create an entry type from config
     */
    protected function createEntryTypeFromConfig(array $config, array $createdFields): ?EntryType
    {
        // Create the entry type without section association
        $entryType = new EntryType();
        $entryType->name = $config['name'];
        $entryType->handle = $config['handle'];
        $entryType->hasTitleField = $config['hasTitleField'] ?? true;
        $entryType->titleFormat = $config['titleFormat'] ?? null;

        // Create field layout
        $fieldLayout = new FieldLayout();
        $fieldLayout->type = EntryType::class;

        $elements = [];

        // Add title field if enabled
        if ($entryType->hasTitleField) {
            $titleField = new EntryTitleField();
            $titleField->required = true;
            $elements[] = $titleField;
        }

        // Add custom fields
        if (isset($config['fields'])) {
            $fieldsService = Craft::$app->getFields();

            foreach ($config['fields'] as $fieldRef) {
                $handle = $fieldRef['handle'];

                // Try to find field in recently created fields first
                $field = null;
                foreach ($createdFields as $createdField) {
                    if ($createdField['handle'] === $handle) {
                        $field = $fieldsService->getFieldById($createdField['id']);
                        break;
                    }
                }

                // If not found in created fields, try to find existing field
                if (!$field) {
                    $field = $fieldsService->getFieldByHandle($handle);
                }

                if ($field) {
                    $element = new CustomField($field);
                    $element->required = $fieldRef['required'] ?? false;
                    $elements[] = $element;
                } else {
                    $this->stdout("  Warning: Field '{$handle}' not found for entry type\n", Console::FG_YELLOW);
                }
            }
        }

        // Set up the field layout
        $fieldLayout->setTabs([
            [
                'name' => 'Content',
                'elements' => $elements,
            ]
        ]);

        $entryType->setFieldLayout($fieldLayout);

        // Save the entry type
        try {
            if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
                $errors = $entryType->getErrors();
                foreach ($errors as $attribute => $messages) {
                    foreach ($messages as $message) {
                        $this->stderr("  Error on $attribute: $message\n", Console::FG_RED);
                    }
                }
                return null;
            }

            return $entryType;
        } catch (\Exception $e) {
            $this->stderr("  Exception: {$e->getMessage()}\n", Console::FG_RED);
            return null;
        }
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

        } catch (\Exception $e) {
            $this->stderr("✗ Failed to sync project config: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    /**
     * Show storage statistics
     */
    public function actionStats(): int
    {
        $pruneService = Plugin::getInstance()->pruneService;
        $stats = $pruneService->getStorageStats();

        $this->stdout("Storage Statistics\n", Console::FG_CYAN);
        $this->stdout("==================\n\n");

        // Config files
        $this->stdout("Config Files:\n", Console::FG_YELLOW);
        $this->stdout("  Count: " . $stats['configs']['count'] . "\n");
        $this->stdout("  Total size: " . $this->formatBytes($stats['configs']['total_size']) . "\n");

        if ($stats['configs']['oldest']) {
            $this->stdout("  Oldest: " . date('Y-m-d H:i:s', $stats['configs']['oldest']) . "\n");
        }
        if ($stats['configs']['newest']) {
            $this->stdout("  Newest: " . date('Y-m-d H:i:s', $stats['configs']['newest']) . "\n");
        }

        $this->stdout("\nOperations:\n", Console::FG_YELLOW);
        $this->stdout("  Count: " . $stats['operations']['count'] . "\n");
        $this->stdout("  Rolled back: " . $stats['operations']['rolled_back_count'] . "\n");
        $this->stdout("  Total size: " . $this->formatBytes($stats['operations']['total_size']) . "\n");

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
        $pruneService = Plugin::getInstance()->pruneService;
        $results = $pruneService->pruneRolledBackOperations();

        $this->stdout("Pruning Rolled Back Operations\n", Console::FG_CYAN);
        $this->stdout("===============================\n\n");

        if (!empty($results['deleted'])) {
            $this->stdout("Deleted " . count($results['deleted']) . " rolled back operations:\n", Console::FG_GREEN);
            foreach ($results['deleted'] as $op) {
                $this->stdout("  - {$op['id']} ({$op['type']}) - {$op['timestamp']}\n");
            }
            $this->stdout("\n");
        } else {
            $this->stdout("No rolled back operations found to delete.\n", Console::FG_YELLOW);
        }

        if (!empty($results['skipped'])) {
            $this->stdout("Skipped " . count($results['skipped']) . " operations (not rolled back):\n", Console::FG_YELLOW);
            foreach (array_slice($results['skipped'], 0, 5) as $op) {
                $this->stdout("  - {$op['id']} ({$op['type']})\n");
            }
            if (count($results['skipped']) > 5) {
                $remaining = count($results['skipped']) - 5;
                $this->stdout("  ... and {$remaining} more\n");
            }
            $this->stdout("\n");
        }

        if (!empty($results['errors'])) {
            $this->stdout("Errors:\n", Console::FG_RED);
            foreach ($results['errors'] as $error) {
                $this->stdout("  - {$error['id']}: {$error['reason']}\n");
            }
        }

        return ExitCode::OK;
    }

    /**
     * Prune old config files
     *
     * @param int $days Number of days to keep (default: 7)
     */
    public function actionPruneConfigs(int $days = 7): int
    {
        $pruneService = Plugin::getInstance()->pruneService;
        $results = $pruneService->pruneOldConfigs($days);

        $this->stdout("Pruning Config Files Older Than {$days} Days\n", Console::FG_CYAN);
        $this->stdout("==========================================\n\n");

        if (!empty($results['deleted'])) {
            $this->stdout("Deleted " . count($results['deleted']) . " old config files:\n", Console::FG_GREEN);
            foreach ($results['deleted'] as $file) {
                $this->stdout("  - {$file['file']} ({$file['age_days']} days old)\n");
            }
            $this->stdout("\n");
        } else {
            $this->stdout("No old config files found to delete.\n", Console::FG_YELLOW);
        }

        if (!empty($results['skipped'])) {
            $this->stdout("Skipped " . count($results['skipped']) . " newer files:\n", Console::FG_YELLOW);
            foreach (array_slice($results['skipped'], 0, 5) as $file) {
                $this->stdout("  - {$file['file']} ({$file['age_days']} days old)\n");
            }
            if (count($results['skipped']) > 5) {
                $remaining = count($results['skipped']) - 5;
                $this->stdout("  ... and {$remaining} more\n");
            }
            $this->stdout("\n");
        }

        if (!empty($results['errors'])) {
            $this->stdout("Errors:\n", Console::FG_RED);
            foreach ($results['errors'] as $error) {
                $this->stdout("  - {$error['file']}: {$error['reason']}\n");
            }
        }

        return ExitCode::OK;
    }

    /**
     * Nuclear option: delete all configs and operations
     */
    public function actionPruneAll(): int
    {
        if (!$this->confirm) {
            $this->stdout("WARNING: This will delete ALL configs and operations!\n", Console::FG_RED);
            $this->stdout("Run with --confirm=1 to proceed.\n\n");
            $this->stdout("Example: ddev craft field-agent/generator/prune-all --confirm=1\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $pruneService = Plugin::getInstance()->pruneService;
        $results = $pruneService->pruneAll();

        $this->stdout("Nuclear Prune - Deleting All Storage\n", Console::FG_RED);
        $this->stdout("====================================\n\n");

        $totalDeleted = count($results['deleted']['configs']) + count($results['deleted']['operations']);

        if ($totalDeleted > 0) {
            $this->stdout("Successfully deleted:\n", Console::FG_GREEN);
            $this->stdout("  - " . count($results['deleted']['configs']) . " config files\n");
            $this->stdout("  - " . count($results['deleted']['operations']) . " operation files\n\n");
        } else {
            $this->stdout("No files to delete.\n", Console::FG_YELLOW);
        }

        if (!empty($results['errors'])) {
            $this->stdout("Errors:\n", Console::FG_RED);
            foreach ($results['errors'] as $error) {
                $this->stdout("  - {$error['file']} ({$error['type']}): {$error['reason']}\n");
            }
        }

        return ExitCode::OK;
    }

    /**
     * Smart modify - Context-aware field generation using operations
     *
     * @param string $prompt Natural language description of modifications needed
     * @param string $provider LLM provider to use (anthropic or openai)
     * @return int
     */
    public function actionModify(string $prompt, string $provider = 'anthropic'): int
    {
        $this->stdout("Smart field modification with context awareness...\n", Console::FG_CYAN);
        $this->stdout("Prompt: $prompt\n", Console::FG_GREY);
        $this->stdout("Provider: $provider\n", Console::FG_GREY);

        if ($this->debug) {
            $this->stdout("🐛 DEBUG MODE ENABLED - Full request/response details will be shown\n", Console::FG_YELLOW);
        }

        try {
            $plugin = Plugin::getInstance();

            // Generate operations using context-aware LLM service
            $operationsData = $plugin->llmOperationsService->generateOperationsFromPrompt($prompt, $provider, $this->debug);

            if ($this->debug) {
                $this->stdout("\n=== GENERATED OPERATIONS ===\n", Console::FG_YELLOW);
                $this->stdout(json_encode($operationsData, JSON_PRETTY_PRINT) . "\n", Console::FG_GREY);
            }

            // Store the operations for reference
            $configPath = $plugin->fieldGeneratorService->storeConfig('operations_' . date('Y-m-d_H-i-s'), $operationsData);
            $this->stdout("✓ Operations config stored at: $configPath\n", Console::FG_CYAN);

            if ($this->dryRun) {
                $this->stdout("\n🔍 DRY RUN MODE - Operations generated but not executed\n", Console::FG_YELLOW);
                $this->stdout("Execute with: ddev craft field-agent/generator/execute-operations " . basename($configPath) . "\n");
                return ExitCode::OK;
            }

            // Execute the operations
            $this->stdout("\nExecuting operations...\n", Console::FG_CYAN);
            $results = $plugin->operationsExecutorService->executeOperations($operationsData);

            // Display results
            $this->displayOperationResults($results);

            // Extract successful and failed operations for recording
            $allSucceeded = true;
            $createdFields = [];
            $createdEntryTypes = [];
            $createdSections = [];
            $failedOperations = [];

            foreach ($results as $result) {
                if (!$result['success']) {
                    $allSucceeded = false;
                    $failedOperations[] = $result;
                } elseif (isset($result['created'])) {
                    switch ($result['created']['type']) {
                        case 'field':
                            $createdFields[] = $result['created'];
                            // Check for matrix blocks
                            if (isset($result['matrix_blocks'])) {
                                // Add block fields to the created fields array
                                if (isset($result['matrix_blocks']['fields'])) {
                                    foreach ($result['matrix_blocks']['fields'] as $blockField) {
                                        $createdFields[] = $blockField;
                                    }
                                }
                                // Add block entry types to the created entry types array
                                if (isset($result['matrix_blocks']['entry_types'])) {
                                    foreach ($result['matrix_blocks']['entry_types'] as $blockEntryType) {
                                        $createdEntryTypes[] = $blockEntryType;
                                    }
                                }
                            }
                            break;
                        case 'entryType':
                            $createdEntryTypes[] = $result['created'];
                            break;
                        case 'section':
                            $createdSections[] = $result['created'];
                            break;
                    }
                }
            }

            // ALWAYS record operation if anything was created (successful or failed)
            if (!empty($createdFields) || !empty($createdEntryTypes) || !empty($createdSections) || !empty($failedOperations)) {
                $description = $allSucceeded ? "Smart modification: $prompt" : "PARTIAL: Smart modification with failures: $prompt";

                $operationId = $plugin->rollbackService->recordOperation(
                    'smart-modify',
                    $prompt,
                    $createdFields,
                    [], // failedFields
                    $createdEntryTypes,
                    [], // failedEntryTypes
                    $createdSections,
                    [], // failedSections
                    [], // createdCategoryGroups
                    [], // failedCategoryGroups
                    [], // createdTagGroups
                    [], // failedTagGroups
                    $description
                );

                $this->stdout("\n📋 Operation recorded with ID: $operationId\n", Console::FG_CYAN);
                $this->stdout("   Use 'field-agent/generator/rollback $operationId' to undo this operation.\n");

                if (!$allSucceeded) {
                    $this->stdout("   ⚠ Partial operation recorded - successful items can be rolled back\n", Console::FG_YELLOW);
                }
            }

            if ($allSucceeded) {
                $this->stdout("\nDone! Run 'ddev craft up' to apply changes.\n", Console::FG_GREEN);
            } else {
                $this->stdout("\n⚠ Some operations failed. Please check the errors above and retry.\n", Console::FG_YELLOW);
                $this->stdout("Note: Successful operations have been recorded and can be rolled back if needed.\n", Console::FG_CYAN);
            }

        } catch (\Exception $e) {
            $this->stderr("✗ Failed to execute smart modification: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    /**
     * Execute stored operations from a config file
     */
    public function actionExecuteOperations(string $config): int
    {
        $configData = $this->loadConfig($config);
        if (!$configData) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $plugin = Plugin::getInstance();

        $this->stdout("Executing operations from config...\n", Console::FG_CYAN);
        $results = $plugin->operationsExecutorService->executeOperations($configData);

        $this->displayOperationResults($results);

        // Extract successful and failed operations for recording
        $allSucceeded = true;
        $createdFields = [];
        $createdEntryTypes = [];
        $createdSections = [];
        $failedOperations = [];

        foreach ($results as $result) {
            if (!$result['success']) {
                $allSucceeded = false;
                $failedOperations[] = $result;
            } elseif (isset($result['created'])) {
                switch ($result['created']['type']) {
                    case 'field':
                        $createdFields[] = $result['created'];
                        break;
                    case 'entryType':
                        $createdEntryTypes[] = $result['created'];
                        break;
                    case 'section':
                        $createdSections[] = $result['created'];
                        break;
                }
            }
        }

        // ALWAYS record operation if anything was created (successful or failed)
        if (!empty($createdFields) || !empty($createdEntryTypes) || !empty($createdSections) || !empty($failedOperations)) {
            $configName = $configData['name'] ?? 'Operations';
            $description = $allSucceeded ? "Execute operations: $configName" : "PARTIAL: Execute operations with failures: $configName";

            $operationId = $plugin->rollbackService->recordOperation(
                'execute-operations',
                $config,
                $createdFields,
                [], // failedFields
                $createdEntryTypes,
                [], // failedEntryTypes
                $createdSections,
                [], // failedSections
                [], // createdCategoryGroups
                [], // failedCategoryGroups
                [], // createdTagGroups
                [], // failedTagGroups
                $description
            );

            $this->stdout("\n📋 Operation recorded with ID: $operationId\n", Console::FG_CYAN);
            $this->stdout("   Use 'field-agent/generator/rollback $operationId' to undo this operation.\n");

            if (!$allSucceeded) {
                $this->stdout("   ⚠ Partial operation recorded - successful items can be rolled back\n", Console::FG_YELLOW);
            }
        }

        if ($allSucceeded) {
            $this->stdout("\nDone! Run 'ddev craft up' to apply changes.\n", Console::FG_GREEN);
            return ExitCode::OK;
        } else {
            $this->stdout("\n⚠ Some operations failed. Please check the errors above and retry.\n", Console::FG_YELLOW);
            $this->stdout("Note: Successful operations have been recorded and can be rolled back if needed.\n", Console::FG_CYAN);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Display operation execution results
     */
    private function displayOperationResults(array $results): void
    {
        $successful = 0;
        $failed = 0;
        $totalBlockFields = 0;
        $totalBlockTypes = 0;

        foreach ($results as $result) {
            if ($result['success']) {
                $this->stdout("✓ {$result['message']}\n", Console::FG_GREEN);

                // Display matrix block details if present
                if (isset($result['matrix_blocks'])) {
                    $blockFields = $result['matrix_blocks']['fields'] ?? [];
                    $blockEntryTypes = $result['matrix_blocks']['entry_types'] ?? [];

                    foreach ($blockEntryTypes as $blockType) {
                        $this->stdout("  → Block type: {$blockType['name']} ({$blockType['handle']})\n", Console::FG_CYAN);
                        $totalBlockTypes++;
                    }

                    foreach ($blockFields as $blockField) {
                        $this->stdout("    • Block field: {$blockField['name']} ({$blockField['handle']})\n", Console::FG_BLUE);
                        $totalBlockFields++;
                    }
                }

                $successful++;
            } else {
                $this->stdout("✗ Operation {$result['index']}: {$result['message']}\n", Console::FG_RED);
                if (isset($result['error_details']) && $this->debug) {
                    $this->stdout("  Exception: {$result['error_details']['exception_type']}\n", Console::FG_YELLOW);
                    $this->stdout("  File: {$result['error_details']['file']}:{$result['error_details']['line']}\n", Console::FG_YELLOW);
                }
                $failed++;
            }
        }

        $this->stdout("\nSummary: $successful successful, $failed failed\n", Console::FG_CYAN);

        if ($totalBlockTypes > 0 || $totalBlockFields > 0) {
            $this->stdout("Matrix blocks: $totalBlockTypes block types, $totalBlockFields block fields\n", Console::FG_CYAN);
        }
    }

    /**
     * Test the discovery service - show current fields and sections
     */
    public function actionTestDiscovery(): int
    {
        $this->stdout("Testing Discovery Service...\n", Console::FG_CYAN);
        $this->stdout(str_repeat("=", 60) . "\n");

        $plugin = Plugin::getInstance();
        $discoveryService = $plugin->discoveryService;

        try {
            // Get project context
            $this->stdout("\n📋 Getting Project Context...\n", Console::FG_YELLOW);
            $context = $discoveryService->getProjectContext();

            // Display summary
            $this->stdout("\n📊 Project Summary:\n", Console::FG_GREEN);
            $this->stdout($context['summary'] . "\n\n");

            // Display fields
            $this->stdout("🏷 Fields:\n", Console::FG_GREEN);
            if (empty($context['fields'])) {
                $this->stdout("  No fields found.\n", Console::FG_GREY);
            } else {
                foreach ($context['fields'] as $field) {
                    $this->stdout(sprintf(
                        "  • %s (%s) - %s\n",
                        $field['name'] ?? 'Unknown',
                        $field['handle'] ?? 'unknown',
                        $field['type'] ?? 'unknown'
                    ));
                }
            }

            // Display sections
            $this->stdout("\n📁 Sections:\n", Console::FG_GREEN);
            if (empty($context['sections'])) {
                $this->stdout("  No sections found.\n", Console::FG_GREY);
            } else {
                foreach ($context['sections'] as $section) {
                    $this->stdout(sprintf(
                        "  • %s (%s) - %s\n",
                        $section['name'] ?? 'Unknown',
                        $section['handle'] ?? 'unknown',
                        $section['type'] ?? 'unknown'
                    ));

                    // Show entry types for this section
                    if (!empty($section['entryTypes'])) {
                        foreach ($section['entryTypes'] as $entryType) {
                            $this->stdout(sprintf(
                                "    └─ Entry Type: %s (%s)\n",
                                $entryType['name'] ?? 'Unknown',
                                $entryType['handle'] ?? 'unknown'
                            ));

                            // Show fields for this entry type
                            if (!empty($entryType['fieldLayoutFields'])) {
                                $this->stdout("       Fields: ");
                                $fieldNames = array_map(function($field) {
                                    return $field['name'] ?? $field['handle'] ?? 'unknown';
                                }, $entryType['fieldLayoutFields']);
                                $this->stdout(implode(", ", $fieldNames) . "\n");
                            }
                        }
                    }
                }
            }

            // Test individual tools
            $this->stdout("\n🔧 Testing Individual Tools:\n", Console::FG_YELLOW);

            $tools = $discoveryService->getAvailableTools();
            foreach ($tools as $toolName => $toolInfo) {
                $this->stdout(sprintf(
                    "  • %s: %s\n",
                    $toolName,
                    $toolInfo['description']
                ));
            }

            $this->stdout("\n✓ Discovery service test completed successfully!\n", Console::FG_GREEN);

        } catch (\Exception $e) {
            $this->stdout("\n✗ Discovery service test failed:\n", Console::FG_RED);
            $this->stdout($e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    /**
     * Format bytes into human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Clean up all sections, entry types, and fields - useful for testing
     *
     * @param bool $force Skip confirmation prompt
     * @return int Exit code
     */
    public function actionCleanup(): int
    {
        $this->stdout("⚠️  WARNING: This will DELETE ALL sections, entry types, fields, category groups, and tag groups!\n", Console::FG_RED);
        $this->stdout("This action cannot be undone through normal rollback.\n\n", Console::FG_YELLOW);

        // Get project config
        $projectConfig = Craft::$app->getProjectConfig();

        // Count items
        $sectionsConfig = $projectConfig->get('sections') ?? [];
        $entryTypesConfig = $projectConfig->get('entryTypes') ?? [];
        $fieldsConfig = $projectConfig->get('fields') ?? [];
        $categoryGroupsConfig = $projectConfig->get('categoryGroups') ?? [];
        $tagGroupsConfig = $projectConfig->get('tagGroups') ?? [];

        $sectionCount = count($sectionsConfig);
        $entryTypeCount = count($entryTypesConfig);
        $fieldCount = count($fieldsConfig);
        $categoryGroupCount = count($categoryGroupsConfig);
        $tagGroupCount = count($tagGroupsConfig);

        if ($sectionCount === 0 && $fieldCount === 0 && $categoryGroupCount === 0 && $tagGroupCount === 0) {
            $this->stdout("No sections, fields, category groups, or tag groups found. System is already clean.\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        $this->stdout("Current state:\n", Console::FG_CYAN);
        $this->stdout("  • {$sectionCount} sections\n");
        $this->stdout("  • {$entryTypeCount} entry types\n");
        $this->stdout("  • {$fieldCount} fields\n");
        $this->stdout("  • {$categoryGroupCount} category groups\n");
        $this->stdout("  • {$tagGroupCount} tag groups\n\n");

        // Confirm unless force flag is set
        if (!$this->force) {
            $confirm = $this->confirm('Are you sure you want to delete everything? Type YES to confirm:');
            if (!$confirm) {
                $this->stdout("Cleanup cancelled.\n", Console::FG_YELLOW);
                return ExitCode::OK;
            }
        }

        $this->stdout("\nStarting cleanup...\n", Console::FG_YELLOW);

        // Track what we delete for reporting
        $deletedSections = [];
        $deletedEntryTypes = [];
        $deletedFields = [];
        $deletedCategoryGroups = [];
        $deletedTagGroups = [];
        $errors = [];

        // Step 1: Delete all sections through project config
        if ($sectionCount > 0) {
            $this->stdout("\n🗑️  Deleting sections...\n", Console::FG_YELLOW);
            foreach ($sectionsConfig as $uid => $sectionConfig) {
                try {
                    $projectConfig->remove("sections.{$uid}");
                    $this->stdout("  ✓ Deleted section: {$sectionConfig['name']} ({$sectionConfig['handle']})\n", Console::FG_GREEN);
                    $deletedSections[] = [
                        'handle' => $sectionConfig['handle'],
                        'name' => $sectionConfig['name'],
                        'type' => $sectionConfig['type'] ?? 'channel',
                        'id' => $uid
                    ];
                } catch (\Exception $e) {
                    $this->stderr("  ✗ Error deleting section {$sectionConfig['name']}: {$e->getMessage()}\n", Console::FG_RED);
                    $errors[] = "Error deleting section {$sectionConfig['name']}: {$e->getMessage()}";
                }
            }
        }

        // Step 2: Delete orphaned entry types (if any remain)
        $remainingEntryTypes = $projectConfig->get('entryTypes') ?? [];
        if (!empty($remainingEntryTypes)) {
            $this->stdout("\n🗑️  Deleting orphaned entry types...\n", Console::FG_YELLOW);
            foreach ($remainingEntryTypes as $uid => $entryTypeConfig) {
                try {
                    $projectConfig->remove("entryTypes.{$uid}");
                    $this->stdout("  ✓ Deleted entry type: {$entryTypeConfig['name']} ({$entryTypeConfig['handle']})\n", Console::FG_GREEN);
                    $deletedEntryTypes[] = [
                        'handle' => $entryTypeConfig['handle'],
                        'name' => $entryTypeConfig['name'],
                        'id' => $uid
                    ];
                } catch (\Exception $e) {
                    $this->stderr("  ✗ Error deleting entry type {$entryTypeConfig['name']}: {$e->getMessage()}\n", Console::FG_RED);
                    $errors[] = "Error deleting entry type {$entryTypeConfig['name']}: {$e->getMessage()}";
                }
            }
        }

        // Step 3: Delete all fields through project config
        if ($fieldCount > 0) {
            $this->stdout("\n🗑️  Deleting fields...\n", Console::FG_YELLOW);
            foreach ($fieldsConfig as $uid => $fieldConfig) {
                try {
                    $projectConfig->remove("fields.{$uid}");
                    $this->stdout("  ✓ Deleted field: {$fieldConfig['name']} ({$fieldConfig['handle']})\n", Console::FG_GREEN);
                    $deletedFields[] = [
                        'handle' => $fieldConfig['handle'],
                        'name' => $fieldConfig['name'],
                        'type' => $fieldConfig['type'] ?? 'unknown',
                        'id' => $uid
                    ];
                } catch (\Exception $e) {
                    $this->stderr("  ✗ Error deleting field {$fieldConfig['name']}: {$e->getMessage()}\n", Console::FG_RED);
                    $errors[] = "Error deleting field {$fieldConfig['name']}: {$e->getMessage()}";
                }
            }
        }

        // Step 4: Delete all category groups through project config
        if ($categoryGroupCount > 0) {
            $this->stdout("\n🗑️  Deleting category groups...\n", Console::FG_YELLOW);
            foreach ($categoryGroupsConfig as $uid => $categoryGroupConfig) {
                try {
                    $projectConfig->remove("categoryGroups.{$uid}");
                    $this->stdout("  ✓ Deleted category group: {$categoryGroupConfig['name']} ({$categoryGroupConfig['handle']})\n", Console::FG_GREEN);
                    $deletedCategoryGroups[] = [
                        'handle' => $categoryGroupConfig['handle'],
                        'name' => $categoryGroupConfig['name'],
                        'id' => $uid
                    ];
                } catch (\Exception $e) {
                    $this->stderr("  ✗ Error deleting category group {$categoryGroupConfig['name']}: {$e->getMessage()}\n", Console::FG_RED);
                    $errors[] = "Error deleting category group {$categoryGroupConfig['name']}: {$e->getMessage()}";
                }
            }
        }

        // Step 5: Delete all tag groups through project config
        if ($tagGroupCount > 0) {
            $this->stdout("\n🗑️  Deleting tag groups...\n", Console::FG_YELLOW);
            foreach ($tagGroupsConfig as $uid => $tagGroupConfig) {
                try {
                    $projectConfig->remove("tagGroups.{$uid}");
                    $this->stdout("  ✓ Deleted tag group: {$tagGroupConfig['name']} ({$tagGroupConfig['handle']})\n", Console::FG_GREEN);
                    $deletedTagGroups[] = [
                        'handle' => $tagGroupConfig['handle'],
                        'name' => $tagGroupConfig['name'],
                        'id' => $uid
                    ];
                } catch (\Exception $e) {
                    $this->stderr("  ✗ Error deleting tag group {$tagGroupConfig['name']}: {$e->getMessage()}\n", Console::FG_RED);
                    $errors[] = "Error deleting tag group {$tagGroupConfig['name']}: {$e->getMessage()}";
                }
            }
        }

        // Summary
        $this->stdout("\n" . str_repeat("=", 60) . "\n", Console::FG_CYAN);
        $this->stdout("Cleanup Summary:\n", Console::FG_CYAN);
        $this->stdout("  ✓ Deleted {$this->count($deletedSections)} sections\n", Console::FG_GREEN);
        $this->stdout("  ✓ Deleted {$this->count($deletedEntryTypes)} entry types\n", Console::FG_GREEN);
        $this->stdout("  ✓ Deleted {$this->count($deletedFields)} fields\n", Console::FG_GREEN);
        $this->stdout("  ✓ Deleted {$this->count($deletedCategoryGroups)} category groups\n", Console::FG_GREEN);
        $this->stdout("  ✓ Deleted {$this->count($deletedTagGroups)} tag groups\n", Console::FG_GREEN);

        if (!empty($errors)) {
            $this->stdout("\n  ⚠️  {$this->count($errors)} errors occurred:\n", Console::FG_RED);
            foreach ($errors as $error) {
                $this->stdout("    • {$error}\n", Console::FG_YELLOW);
            }
        }

        // Record this as a special cleanup operation
        if (!empty($deletedSections) || !empty($deletedFields) || !empty($deletedEntryTypes) || !empty($deletedCategoryGroups) || !empty($deletedTagGroups)) {
            $plugin = Plugin::getInstance();
            $operationId = $plugin->rollbackService->recordOperation(
                'cleanup',
                'manual cleanup command',
                [], // createdFields
                $deletedFields, // failedFields (using for deleted items in cleanup)
                [], // createdEntryTypes
                $deletedEntryTypes, // failedEntryTypes (using for deleted items in cleanup)
                [], // createdSections
                $deletedSections, // failedSections (using for deleted items in cleanup)
                [], // createdCategoryGroups
                $deletedCategoryGroups, // failedCategoryGroups (using for deleted items in cleanup)
                [], // createdTagGroups
                $deletedTagGroups, // failedTagGroups (using for deleted items in cleanup)
                'Manual cleanup - deleted all sections, entry types, fields, category groups, and tag groups'
            );

            $this->stdout("\n📋 Cleanup operation recorded with ID: $operationId\n", Console::FG_CYAN);
            $this->stdout("   (Note: This operation cannot be rolled back as items were deleted)\n", Console::FG_YELLOW);
        }

        $this->stdout("\nDone! Run 'ddev craft up' to apply changes.\n", Console::FG_GREEN);

        return empty($errors) ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Helper to count arrays safely
     */
    private function count($array): int
    {
        return is_array($array) ? count($array) : 0;
    }

    /**
     * Discover available test suites organized by category
     */
    private function discoverTestSuites(): array
    {
        $testSuites = [];
        $testsDir = dirname(__DIR__, 3) . '/tests';

        if (!is_dir($testsDir)) {
            return $testSuites;
        }

        $categories = ['basic-operations', 'advanced-operations', 'integration-tests', 'edge-cases'];

        foreach ($categories as $category) {
            $categoryDir = $testsDir . '/' . $category;
            if (!is_dir($categoryDir)) {
                continue;
            }

            $tests = [];
            $files = glob($categoryDir . '/*.json');

            foreach ($files as $file) {
                $filename = basename($file, '.json');
                $data = json_decode(file_get_contents($file), true);

                if ($data) {
                    $tests[] = [
                        'filename' => $filename,
                        'path' => $file,
                        'description' => $data['description'] ?? 'Test suite',
                        'prerequisite' => $data['prerequisite'] ?? null,
                        'expectedOutcome' => $data['expectedOutcome'] ?? []
                    ];
                }
            }

            if (!empty($tests)) {
                // Sort tests by filename for consistent ordering
                usort($tests, function($a, $b) {
                    return strcmp($a['filename'], $b['filename']);
                });
                $testSuites[$category] = $tests;
            }
        }

        return $testSuites;
    }

    /**
     * Find a specific test file by name across all categories
     */
    private function findTestFile(string $testName): ?string
    {
        $testSuites = $this->discoverTestSuites();

        foreach ($testSuites as $category => $tests) {
            foreach ($tests as $test) {
                if ($test['filename'] === $testName) {
                    return $test['path'];
                }
            }
        }

        return null;
    }

    /**
     * Execute a test file and return the result
     */
    private function executeTestFile(string $testFile, bool $cleanup = false): int
    {
        if (!file_exists($testFile)) {
            $this->stderr("Test file not found: $testFile\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $testData = json_decode(file_get_contents($testFile), true);
        if (!$testData) {
            $this->stderr("Invalid JSON in test file: $testFile\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Check if this is an operations-based test or legacy field-based test
        if (isset($testData['operations'])) {
            // New operations-based test format
            return $this->executeOperationsTest($testData, basename($testFile, '.json'), $cleanup);
        } else {
            // Legacy field-based test format - convert to operations
            return $this->executeLegacyTest($testData, basename($testFile, '.json'), $cleanup);
        }
    }

    /**
     * Execute an operations-based test
     */
    private function executeOperationsTest(array $testData, string $testName, bool $cleanup = false): int
    {
        $plugin = Plugin::getInstance();
        $operationsService = $plugin->operationsExecutorService;
        $rollbackService = $plugin->rollbackService;
        $operationId = null;

        try {
            // Execute the operations - pass the entire testData which contains the 'operations' key
            $results = $operationsService->executeOperations($testData);

            // Check if all operations succeeded
            $allSucceeded = true;
            $errors = [];

            $resultsArray = is_array($results) ? $results : [$results];
            foreach ($resultsArray as $result) {
                if (is_array($result) && isset($result['success']) && !$result['success']) {
                    $allSucceeded = false;
                    $errors[] = $result['message'] ?? 'Unknown error';
                }
            }

            if ($allSucceeded) {
                // If cleanup is enabled and test passed, record and rollback the operation
                if ($cleanup) {
                    // Record the operation so it can be rolled back
                    $operationId = $rollbackService->recordTestOperation($testName, $testData, $results);
                    if ($operationId) {
                        $this->performTestCleanup($operationId);
                    }
                }
                return ExitCode::OK;
            } else {
                $this->stderr("\nTest failed with errors:\n", Console::FG_RED);
                foreach ($errors as $error) {
                    $this->stderr("  - $error\n");
                }
                return ExitCode::UNSPECIFIED_ERROR;
            }
        } catch (\Exception $e) {
            $this->stderr("\nTest failed with exception: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Execute a legacy field-based test (converts to operations first)
     */
    private function executeLegacyTest(array $testData, string $testName, bool $cleanup = false): int
    {
        // Convert legacy field format to operations format
        $operations = [];

        if (isset($testData['fields'])) {
            foreach ($testData['fields'] as $field) {
                $operations[] = [
                    'type' => 'create',
                    'target' => 'field',
                    'data' => $field
                ];
            }
        }

        if (isset($testData['sections'])) {
            foreach ($testData['sections'] as $section) {
                $operations[] = [
                    'type' => 'create',
                    'target' => 'section',
                    'data' => $section
                ];
            }
        }

        if (isset($testData['entryTypes'])) {
            foreach ($testData['entryTypes'] as $entryType) {
                $operations[] = [
                    'type' => 'create',
                    'target' => 'entryType',
                    'data' => $entryType
                ];
            }
        }

        $operationsData = ['operations' => $operations];
        return $this->executeOperationsTest($operationsData, $testName, $cleanup);
    }

    /**
     * Perform cleanup for a test operation
     */
    private function performTestCleanup(string $operationId): void
    {
        try {
            $plugin = Plugin::getInstance();
            $rollbackService = $plugin->rollbackService;

            $this->stdout(" 🧹 Cleaning up test data...", Console::FG_CYAN);
            $result = $rollbackService->rollbackOperation($operationId);

            // Check if anything was deleted (success is indicated by having deletion results)
            $totalDeleted = count($result['deleted']['fields'] ?? []) +
                           count($result['deleted']['entryTypes'] ?? []) +
                           count($result['deleted']['sections'] ?? []) +
                           count($result['deleted']['categoryGroups'] ?? []) +
                           count($result['deleted']['tagGroups'] ?? []);

            if ($totalDeleted > 0) {
                $this->stdout(" ✅ Cleanup complete ({$totalDeleted} items removed)\n", Console::FG_GREEN);
            } else {
                $this->stdout(" ⚠️ Cleanup completed but no items were removed\n", Console::FG_YELLOW);
            }
        } catch (\Exception $e) {
            $this->stdout(" ❌ Cleanup failed: " . $e->getMessage() . "\n", Console::FG_YELLOW);
        }
    }
}
