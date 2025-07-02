<?php

namespace craftcms\fieldagent\services;

use Craft;
use craft\base\Component;
use craftcms\fieldagent\Plugin;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Testing Service
 * 
 * Handles all test-related functionality for the Field Agent plugin
 */
class TestingService extends Component
{
    /**
     * Discover all available test suites organized by category
     * 
     * @return array
     */
    public function discoverTestSuites(): array
    {
        $testSuites = [];
        $testsDir = dirname(__DIR__, 2) . '/tests';

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
     * 
     * @param string $testName
     * @return string|null
     */
    public function findTestFile(string $testName): ?string
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
     * 
     * @param string $testFile
     * @param bool $cleanup
     * @return array ['success' => bool, 'message' => string, 'operationId' => string|null]
     */
    public function executeTestFile(string $testFile, bool $cleanup = false): array
    {
        if (!file_exists($testFile)) {
            return [
                'success' => false,
                'message' => "Test file not found: $testFile",
                'operationId' => null
            ];
        }

        $testData = json_decode(file_get_contents($testFile), true);
        if (!$testData) {
            return [
                'success' => false,
                'message' => "Invalid JSON in test file: $testFile",
                'operationId' => null
            ];
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
     * 
     * @param array $testData
     * @param string $testName
     * @param bool $cleanup
     * @return array ['success' => bool, 'message' => string, 'operationId' => string|null]
     */
    public function executeOperationsTest(array $testData, string $testName, bool $cleanup = false): array
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
                return [
                    'success' => true,
                    'message' => 'Test executed successfully',
                    'operationId' => $operationId
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Test failed with errors: " . implode(', ', $errors),
                    'operationId' => null
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Test failed with exception: " . $e->getMessage(),
                'operationId' => null
            ];
        }
    }

    /**
     * Execute a legacy field-based test (converts to operations first)
     * 
     * @param array $testData
     * @param string $testName
     * @param bool $cleanup
     * @return array ['success' => bool, 'message' => string, 'operationId' => string|null]
     */
    public function executeLegacyTest(array $testData, string $testName, bool $cleanup = false): array
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
     * 
     * @param string $operationId
     * @return array ['success' => bool, 'deletedCount' => int]
     */
    public function performTestCleanup(string $operationId): array
    {
        try {
            $plugin = Plugin::getInstance();
            $rollbackService = $plugin->rollbackService;

            $result = $rollbackService->rollbackOperation($operationId);

            // Check if anything was deleted (success is indicated by having deletion results)
            $totalDeleted = count($result['deleted']['fields'] ?? []) +
                           count($result['deleted']['entryTypes'] ?? []) +
                           count($result['deleted']['sections'] ?? []) +
                           count($result['deleted']['categoryGroups'] ?? []) +
                           count($result['deleted']['tagGroups'] ?? []);

            return [
                'success' => true,
                'deletedCount' => $totalDeleted
            ];
        } catch (\Exception $e) {
            Craft::error('Test cleanup failed: ' . $e->getMessage(), __METHOD__);
            return [
                'success' => false,
                'deletedCount' => 0
            ];
        }
    }

    /**
     * Get category display name
     * 
     * @param string $category
     * @return string
     */
    public function getCategoryDisplayName(string $category): string
    {
        $categoryNames = [
            'basic-operations' => 'Basic Operations',
            'advanced-operations' => 'Advanced Operations',
            'integration-tests' => 'Integration Tests',
            'edge-cases' => 'Edge Cases'
        ];

        return $categoryNames[$category] ?? ucfirst(str_replace('-', ' ', $category));
    }

    /**
     * Run all tests in a category
     * 
     * @param string $category
     * @param bool $cleanup
     * @return array ['totalTests' => int, 'passed' => int, 'failed' => int, 'results' => array]
     */
    public function runTestSuite(string $category, bool $cleanup = false): array
    {
        $testSuites = $this->discoverTestSuites();
        $results = [];
        $passed = 0;
        $failed = 0;

        if (!isset($testSuites[$category])) {
            return [
                'totalTests' => 0,
                'passed' => 0,
                'failed' => 0,
                'results' => []
            ];
        }

        foreach ($testSuites[$category] as $test) {
            $result = $this->executeTestFile($test['path'], $cleanup);
            
            if ($result['success']) {
                $passed++;
            } else {
                $failed++;
            }

            $results[] = [
                'testName' => $test['filename'],
                'success' => $result['success'],
                'message' => $result['message']
            ];
        }

        return [
            'totalTests' => count($testSuites[$category]),
            'passed' => $passed,
            'failed' => $failed,
            'results' => $results
        ];
    }

    /**
     * Run all tests across all categories
     * 
     * @param bool $cleanup
     * @return array ['totalTests' => int, 'passed' => int, 'failed' => int, 'categoryResults' => array]
     */
    public function runAllTests(bool $cleanup = false): array
    {
        $testSuites = $this->discoverTestSuites();
        $categoryResults = [];
        $totalTests = 0;
        $totalPassed = 0;
        $totalFailed = 0;

        foreach ($testSuites as $category => $tests) {
            $categoryResult = $this->runTestSuite($category, $cleanup);
            
            $totalTests += $categoryResult['totalTests'];
            $totalPassed += $categoryResult['passed'];
            $totalFailed += $categoryResult['failed'];
            
            $categoryResults[$category] = $categoryResult;
        }

        return [
            'totalTests' => $totalTests,
            'passed' => $totalPassed,
            'failed' => $totalFailed,
            'categoryResults' => $categoryResults
        ];
    }
}