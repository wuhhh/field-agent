<?php

namespace craftcms\fieldagent\console\controllers;

use Craft;
use yii\console\Controller;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;
use craftcms\fieldagent\registry\FieldRegistryService;
use craftcms\fieldagent\fieldTypes\TableField;
use craftcms\fieldagent\fieldTypes\PlainTextField;
use craftcms\fieldagent\fieldTypes\EmailField;
use craftcms\fieldagent\fieldTypes\NumberField;
use craftcms\fieldagent\fieldTypes\LightswitchField;
use craftcms\fieldagent\fieldTypes\CountryField;

/**
 * Test the Field Registry infrastructure
 */
class RegistryTestController extends Controller
{
    /**
     * Test the field registry infrastructure
     */
    public function actionTest(): int
    {
        $this->stdout("=== Field Registry Infrastructure Test ===\n\n");

        try {
            // Test 1: FieldDefinition instantiation
            $this->stdout("Test 1: FieldDefinition instantiation... ");
            $definition = new FieldDefinition([
                'type' => 'test_field',
                'craftClass' => 'craft\fields\PlainText',
                'autoDiscoveredData' => ['displayName' => 'Test Field'],
                'manualSettings' => ['required' => true]
            ]);
            
            if ($definition->type === 'test_field' && $definition->craftClass === 'craft\fields\PlainText') {
                $this->stdout("✓ PASSED\n", \yii\helpers\Console::FG_GREEN);
            } else {
                $this->stdout("✗ FAILED - Properties not set correctly\n", \yii\helpers\Console::FG_RED);
                return 1;
            }

            // Test 2: getMergedSettings functionality
            $this->stdout("Test 2: FieldDefinition getMergedSettings... ");
            $merged = $definition->getMergedSettings();
            if (isset($merged['displayName']) && $merged['displayName'] === 'Test Field' && 
                isset($merged['required']) && $merged['required'] === true) {
                $this->stdout("✓ PASSED\n", \yii\helpers\Console::FG_GREEN);
            } else {
                $this->stdout("✗ FAILED - Settings merge incorrect\n", \yii\helpers\Console::FG_RED);
                $this->stdout("Merged settings: " . print_r($merged, true) . "\n");
                return 1;
            }

            // Test 3: FieldIntrospector instantiation
            $this->stdout("Test 3: FieldIntrospector instantiation... ");
            $introspector = new FieldIntrospector();
            if ($introspector instanceof FieldIntrospector) {
                $this->stdout("✓ PASSED\n", \yii\helpers\Console::FG_GREEN);
            } else {
                $this->stdout("✗ FAILED - Could not create FieldIntrospector\n", \yii\helpers\Console::FG_RED);
                return 1;
            }

            // Test 4: FieldRegistryService instantiation
            $this->stdout("Test 4: FieldRegistryService instantiation... ");
            $registry = new FieldRegistryService();
            $registry->init();
            if ($registry instanceof FieldRegistryService) {
                $this->stdout("✓ PASSED\n", \yii\helpers\Console::FG_GREEN);
            } else {
                $this->stdout("✗ FAILED - Could not create FieldRegistryService\n", \yii\helpers\Console::FG_RED);
                return 1;
            }

            // Test 5: Field registration
            $this->stdout("Test 5: Field registration... ");
            $registry->registerField('test_field', $definition);
            $retrieved = $registry->getField('test_field');
            if ($retrieved !== null && $retrieved->type === 'test_field') {
                $this->stdout("✓ PASSED\n", \yii\helpers\Console::FG_GREEN);
            } else {
                $this->stdout("✗ FAILED - Field registration/retrieval failed\n", \yii\helpers\Console::FG_RED);
                return 1;
            }

            // Test 6: Field introspection (test with Table field)
            $this->stdout("Test 6: Field introspection with Table field... ");
            try {
                $metadata = $introspector->analyzeFieldType('craft\fields\Table');
                if (isset($metadata['craftClass']) && $metadata['craftClass'] === 'craft\fields\Table' &&
                    isset($metadata['displayName']) && !empty($metadata['displayName'])) {
                    $this->stdout("✓ PASSED\n", \yii\helpers\Console::FG_GREEN);
                    $this->stdout("  Display name: " . $metadata['displayName'] . "\n");
                    $this->stdout("  Icon: " . ($metadata['icon'] ?: 'none') . "\n");
                    $this->stdout("  Settings attributes: " . count($metadata['settingsAttributes'] ?? []) . "\n");
                } else {
                    $this->stdout("✗ FAILED - Introspection data incomplete\n", \yii\helpers\Console::FG_RED);
                    $this->stdout("Metadata: " . print_r($metadata, true) . "\n");
                    return 1;
                }
            } catch (\Exception $e) {
                $this->stdout("✗ FAILED - Exception during introspection: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
                return 1;
            }

            // Test 7: Schema generation
            $this->stdout("Test 7: Schema generation... ");
            $schema = $registry->generateSchema();
            if (isset($schema['fieldTypes']) && is_array($schema['fieldTypes']) &&
                in_array('test_field', $schema['fieldTypes'])) {
                $this->stdout("✓ PASSED\n", \yii\helpers\Console::FG_GREEN);
            } else {
                $this->stdout("✗ FAILED - Schema generation incorrect\n", \yii\helpers\Console::FG_RED);
                $this->stdout("Schema: " . print_r($schema, true) . "\n");
                return 1;
            }

            // Test 8: LLM documentation generation
            $this->stdout("Test 8: LLM documentation generation... ");
            $docs = $registry->generateLLMDocumentation();
            if (!empty($docs) && strpos($docs, 'test_field') !== false) {
                $this->stdout("✓ PASSED\n", \yii\helpers\Console::FG_GREEN);
            } else {
                $this->stdout("✗ FAILED - LLM documentation generation failed\n", \yii\helpers\Console::FG_RED);
                $this->stdout("Generated docs: " . $docs . "\n");
                return 1;
            }

            // Test 9: Registry statistics
            $this->stdout("Test 9: Registry statistics... ");
            $stats = $registry->getStatistics();
            if (isset($stats['totalFields']) && $stats['totalFields'] >= 1) {
                $this->stdout("✓ PASSED\n", \yii\helpers\Console::FG_GREEN);
            } else {
                $this->stdout("✗ FAILED - Statistics generation failed\n", \yii\helpers\Console::FG_RED);
                $this->stdout("Stats: " . print_r($stats, true) . "\n");
                return 1;
            }

            // Test 10: Auto-registration attempt (limited test)
            $this->stdout("Test 10: Auto-registration functionality... ");
            try {
                // Clear existing registrations first
                $registry->clearCache();
                $initialCount = count($registry->getAllFields());
                
                // This should discover and register native Craft fields
                $registered = $registry->autoRegisterNativeFields();
                
                if ($registered > 0) {
                    $this->stdout("✓ PASSED (registered {$registered} field types)\n", \yii\helpers\Console::FG_GREEN);
                } else {
                    $this->stdout("✗ FAILED - No fields were auto-registered\n", \yii\helpers\Console::FG_RED);
                    return 1;
                }
            } catch (\Exception $e) {
                $this->stdout("✗ FAILED - Exception during auto-registration: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
                return 1;
            }

            $this->stdout("\n=== ALL TESTS PASSED ===\n", \yii\helpers\Console::FG_GREEN);
            $this->stdout("Phase 1 infrastructure is working correctly!\n");
            
            // Output some useful information
            $this->stdout("\nRegistry Statistics:\n", \yii\helpers\Console::FG_YELLOW);
            $finalStats = $registry->getStatistics();
            foreach ($finalStats as $key => $value) {
                if ($key === 'fieldTypes') {
                    $fieldTypeList = implode(', ', array_slice($value, 0, 5)) . (count($value) > 5 ? "... (+" . (count($value) - 5) . " more)" : "");
                    $this->stdout("  {$key}: {$fieldTypeList}\n");
                } else {
                    $this->stdout("  {$key}: {$value}\n");
                }
            }

            // Test 11: Table field registration and creation
            $this->stdout("\nTest 11: Table field registration and creation... ");
            try {
                $tableField = new TableField();
                $registry->registerFieldType($tableField);
                
                $tableDefinition = $registry->getField('table');
                if ($tableDefinition && $tableDefinition->craftClass === \craft\fields\Table::class) {
                    $this->stdout("✓ PASSED\n", \yii\helpers\Console::FG_GREEN);
                    $this->stdout("  Manual documentation: " . substr($tableDefinition->llmDocumentation, 0, 50) . "...\n");
                } else {
                    $this->stdout("✗ FAILED - Table field not properly registered\n", \yii\helpers\Console::FG_RED);
                    return 1;
                }
            } catch (\Exception $e) {
                $this->stdout("✗ FAILED - Exception during Table field registration: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
                return 1;
            }

            // Test 12: Table field creation comparison
            $this->stdout("Test 12: Table field creation behavior comparison... ");
            try {
                // Test configuration
                $testConfig = [
                    'columns' => [
                        ['heading' => 'Name', 'handle' => 'name', 'type' => 'singleline'],
                        'Email' // Test string format
                    ],
                    'minRows' => 1,
                    'maxRows' => 10,
                    'addRowLabel' => 'Add Row'
                ];

                // Create field using new system
                $newField = $tableField->createField($testConfig);
                
                // Validate field properties
                if ($newField instanceof \craft\fields\Table &&
                    count($newField->columns) === 2 &&
                    $newField->minRows === 1 &&
                    $newField->maxRows === 10 &&
                    $newField->addRowLabel === 'Add Row') {
                    $this->stdout("✓ PASSED\n", \yii\helpers\Console::FG_GREEN);
                    $this->stdout("  Columns created: " . count($newField->columns) . "\n");
                    $this->stdout("  First column: {$newField->columns[0]['heading']} ({$newField->columns[0]['type']})\n");
                    $this->stdout("  Second column: {$newField->columns[1]['heading']} ({$newField->columns[1]['type']})\n");
                } else {
                    $this->stdout("✗ FAILED - Field creation behavior mismatch\n", \yii\helpers\Console::FG_RED);
                    $this->stdout("  Field type: " . get_class($newField) . "\n");
                    $this->stdout("  Columns count: " . count($newField->columns) . "\n");
                    return 1;
                }
            } catch (\Exception $e) {
                $this->stdout("✗ FAILED - Exception during field creation: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
                return 1;
            }

            // Test 13: Table field validation
            $this->stdout("Test 13: Table field validation... ");
            $validConfig = ['columns' => [['heading' => 'Test', 'type' => 'singleline']], 'minRows' => 1];
            $invalidConfig = ['columns' => 'invalid', 'minRows' => -1, 'maxRows' => 0];
            
            $validErrors = $tableField->validate($validConfig);
            $invalidErrors = $tableField->validate($invalidConfig);
            
            if (empty($validErrors) && !empty($invalidErrors)) {
                $this->stdout("✓ PASSED\n", \yii\helpers\Console::FG_GREEN);
                $this->stdout("  Validation errors for invalid config: " . count($invalidErrors) . "\n");
            } else {
                $this->stdout("✗ FAILED - Validation behavior incorrect\n", \yii\helpers\Console::FG_RED);
                $this->stdout("  Valid config errors: " . count($validErrors) . "\n");
                $this->stdout("  Invalid config errors: " . count($invalidErrors) . "\n");
                return 1;
            }

            // Test 14: Register all Phase 2 field types
            $this->stdout("\nTest 14: Register all Phase 2 field types... ");
            try {
                $fieldTypes = [
                    new PlainTextField(),
                    new EmailField(),
                    new NumberField(),
                    new LightswitchField(),
                    new CountryField(),
                ];

                foreach ($fieldTypes as $fieldType) {
                    $registry->registerFieldType($fieldType);
                }

                // Verify all are registered
                $registeredTypes = ['table', 'plain_text', 'email', 'number', 'lightswitch', 'country'];
                $allRegistered = true;
                foreach ($registeredTypes as $type) {
                    if (!$registry->hasField($type)) {
                        $allRegistered = false;
                        break;
                    }
                }

                if ($allRegistered) {
                    $this->stdout("✓ PASSED\n", \yii\helpers\Console::FG_GREEN);
                    $this->stdout("  All Phase 2 field types registered successfully\n");
                } else {
                    $this->stdout("✗ FAILED - Not all field types registered\n", \yii\helpers\Console::FG_RED);
                    return 1;
                }
            } catch (\Exception $e) {
                $this->stdout("✗ FAILED - Exception during bulk registration: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
                return 1;
            }

            // Test 15: Schema generation with manual field types
            $this->stdout("Test 15: Schema generation with manual field types... ");
            $schema = $registry->generateSchema();
            if (isset($schema['fieldTypes']) && 
                in_array('table', $schema['fieldTypes']) &&
                in_array('plain_text', $schema['fieldTypes']) &&
                in_array('email', $schema['fieldTypes']) &&
                in_array('number', $schema['fieldTypes']) &&
                in_array('lightswitch', $schema['fieldTypes']) &&
                in_array('country', $schema['fieldTypes'])) {
                $this->stdout("✓ PASSED\n", \yii\helpers\Console::FG_GREEN);
                $this->stdout("  All migrated field types in schema\n");
            } else {
                $this->stdout("✗ FAILED - Schema missing migrated field types\n", \yii\helpers\Console::FG_RED);
                return 1;
            }

            return 0;

        } catch (\Exception $e) {
            $this->stdout("\n✗ CRITICAL FAILURE: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            $this->stdout("Stack trace:\n" . $e->getTraceAsString() . "\n");
            return 1;
        }
    }
}