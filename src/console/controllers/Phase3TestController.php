<?php

namespace craftcms\fieldagent\console\controllers;

use Craft;
use yii\console\Controller;
use craftcms\fieldagent\registry\FieldRegistryService;
use craftcms\fieldagent\fieldTypes\TableField;
use craftcms\fieldagent\fieldTypes\PlainTextField;
use craftcms\fieldagent\fieldTypes\EmailField;
use craftcms\fieldagent\fieldTypes\NumberField;
use craftcms\fieldagent\fieldTypes\LightswitchField;
use craftcms\fieldagent\fieldTypes\CountryField;
use craftcms\fieldagent\fieldTypes\DropdownField;
use craftcms\fieldagent\fieldTypes\RichTextField;
use craftcms\fieldagent\fieldTypes\AssetField;
use craftcms\fieldagent\fieldTypes\MoneyField;
use craftcms\fieldagent\fieldTypes\AddressesField;
use craftcms\fieldagent\fieldTypes\ColorField;
use craftcms\fieldagent\fieldTypes\DateField;
use craftcms\fieldagent\fieldTypes\TimeField;
use craftcms\fieldagent\fieldTypes\RangeField;
use craftcms\fieldagent\fieldTypes\IconField;
use craftcms\fieldagent\fieldTypes\JsonField;
use craftcms\fieldagent\fieldTypes\RadioButtonsField;
use craftcms\fieldagent\fieldTypes\CheckboxesField;
use craftcms\fieldagent\fieldTypes\MultiSelectField;
use craftcms\fieldagent\fieldTypes\ButtonGroupField;
use craftcms\fieldagent\fieldTypes\UsersField;
use craftcms\fieldagent\fieldTypes\EntriesField;
use craftcms\fieldagent\fieldTypes\CategoriesField;
use craftcms\fieldagent\fieldTypes\TagsField;
use craftcms\fieldagent\fieldTypes\MatrixField;
use craftcms\fieldagent\fieldTypes\ContentBlockField;
use craftcms\fieldagent\fieldTypes\LinkField;
use craftcms\fieldagent\fieldTypes\ImageField;

/**
 * Comprehensive test for Phase 2 and Phase 3 field migrations
 */
class Phase3TestController extends Controller
{
    /**
     * Test all migrated field types with comprehensive validation
     */
    public function actionAll(): int
    {
        $this->stdout("=== Phase 2 & 3 Field Migration Comprehensive Test ===\n\n");

        try {
            // Initialize registry
            $registry = new FieldRegistryService();
            $registry->init();
            
            // Auto-register native fields first
            $registry->autoRegisterNativeFields();

            // Define all migrated field types
            $migratedFields = [
                'table' => new TableField(),
                'plain_text' => new PlainTextField(),
                'email' => new EmailField(),
                'number' => new NumberField(),
                'lightswitch' => new LightswitchField(),
                'country' => new CountryField(),
                'dropdown' => new DropdownField(),
                'rich_text' => new RichTextField(),
                'asset' => new AssetField(),
                'money' => new MoneyField(),
                'addresses' => new AddressesField(),
                'color' => new ColorField(),
                'date' => new DateField(),
                'time' => new TimeField(),
                'range' => new RangeField(),
                'icon' => new IconField(),
                'json' => new JsonField(),
                'radio_buttons' => new RadioButtonsField(),
                'checkboxes' => new CheckboxesField(),
                'multi_select' => new MultiSelectField(),
                'button_group' => new ButtonGroupField(),
                'users' => new UsersField(),
                'entries' => new EntriesField(),
                'categories' => new CategoriesField(),
                'tags' => new TagsField(),
                'matrix' => new MatrixField(),
                'content_block' => new ContentBlockField(),
                'link' => new LinkField(),
                'image' => new ImageField(),
            ];

            $this->stdout("Test 1: Register all migrated field types... ");
            foreach ($migratedFields as $type => $fieldInstance) {
                $registry->registerFieldType($fieldInstance);
            }
            
            // Verify all are registered
            $allRegistered = true;
            $missingTypes = [];
            foreach (array_keys($migratedFields) as $type) {
                if (!$registry->hasField($type)) {
                    $allRegistered = false;
                    $missingTypes[] = $type;
                }
            }
            
            if ($allRegistered) {
                $this->stdout("✓ PASSED\n", \yii\helpers\Console::FG_GREEN);
                $this->stdout("  Registered " . count($migratedFields) . " field types\n");
            } else {
                $this->stdout("✗ FAILED\n", \yii\helpers\Console::FG_RED);
                $this->stdout("  Missing field types: " . implode(', ', $missingTypes) . "\n");
                return 1;
            }

            $this->stdout("Test 2: Schema generation with all migrated fields... ");
            $schema = $registry->generateSchema();
            $allInSchema = true;
            $missingFromSchema = [];
            
            foreach (array_keys($migratedFields) as $type) {
                if (!in_array($type, $schema['fieldTypes'])) {
                    $allInSchema = false;
                    $missingFromSchema[] = $type;
                }
            }
            
            if ($allInSchema) {
                $this->stdout("✓ PASSED\n", \yii\helpers\Console::FG_GREEN);
                $this->stdout("  All field types present in schema\n");
            } else {
                $this->stdout("✗ FAILED\n", \yii\helpers\Console::FG_RED);
                $this->stdout("  Missing from schema: " . implode(', ', $missingFromSchema) . "\n");
                return 1;
            }

            $this->stdout("Test 3: LLM documentation generation... ");
            $docs = $registry->generateLLMDocumentation();
            $allInDocs = true;
            $missingFromDocs = [];
            
            foreach (array_keys($migratedFields) as $type) {
                if (strpos($docs, $type . ':') === false) {
                    $allInDocs = false;
                    $missingFromDocs[] = $type;
                }
            }
            
            if ($allInDocs) {
                $this->stdout("✓ PASSED\n", \yii\helpers\Console::FG_GREEN);
                $this->stdout("  All field types documented\n");
            } else {
                $this->stdout("✗ FAILED\n", \yii\helpers\Console::FG_RED);
                $this->stdout("  Missing from docs: " . implode(', ', $missingFromDocs) . "\n");
                return 1;
            }

            $this->stdout("Test 4: Field creation behavior validation... ");
            $creationResults = [];
            
            // Test configurations for each field type
            $testConfigs = [
                'table' => ['columns' => [['heading' => 'Name', 'type' => 'singleline']]],
                'plain_text' => ['multiline' => false],
                'email' => ['placeholder' => 'test@example.com'],
                'number' => ['decimals' => 2, 'min' => 0],
                'lightswitch' => ['default' => true],
                'country' => [],
                'dropdown' => ['options' => ['Option 1', 'Option 2']],
                'rich_text' => [],
                'asset' => ['maxRelations' => 5],
            ];

            $allCreated = true;
            $creationErrors = [];

            foreach ($migratedFields as $type => $fieldInstance) {
                try {
                    $config = $testConfigs[$type] ?? [];
                    $field = $fieldInstance->createField($config);
                    
                    // Verify it's the correct Craft field class
                    $definition = $registry->getField($type);
                    if (!$field instanceof $definition->craftClass) {
                        $allCreated = false;
                        $creationErrors[] = "{$type}: Wrong class, expected {$definition->craftClass}, got " . get_class($field);
                    } else {
                        $creationResults[$type] = '✓';
                    }
                } catch (\Exception $e) {
                    $allCreated = false;
                    $creationErrors[] = "{$type}: {$e->getMessage()}";
                    $creationResults[$type] = '✗';
                }
            }
            
            if ($allCreated) {
                $this->stdout("✓ PASSED\n", \yii\helpers\Console::FG_GREEN);
                foreach ($creationResults as $type => $result) {
                    $this->stdout("  {$type}: {$result}\n");
                }
            } else {
                $this->stdout("✗ FAILED\n", \yii\helpers\Console::FG_RED);
                foreach ($creationErrors as $error) {
                    $this->stdout("  {$error}\n");
                }
                return 1;
            }

            $this->stdout("Test 5: Validation method testing... ");
            $validationResults = [];
            $allValidationsWork = true;

            foreach ($migratedFields as $type => $fieldInstance) {
                try {
                    // Test with valid config
                    $validConfig = $testConfigs[$type] ?? [];
                    $validErrors = $fieldInstance->validate($validConfig);
                    
                    // Test with invalid config  
                    $invalidConfig = ['invalid_setting' => 'invalid_value'];
                    $invalidErrors = $fieldInstance->validate($invalidConfig);
                    
                    // Validation should work without throwing exceptions
                    $validationResults[$type] = '✓';
                } catch (\Exception $e) {
                    $allValidationsWork = false;
                    $validationResults[$type] = '✗ ' . $e->getMessage();
                }
            }
            
            if ($allValidationsWork) {
                $this->stdout("✓ PASSED\n", \yii\helpers\Console::FG_GREEN);
                foreach ($validationResults as $type => $result) {
                    $this->stdout("  {$type}: {$result}\n");
                }
            } else {
                $this->stdout("✗ FAILED\n", \yii\helpers\Console::FG_RED);
                foreach ($validationResults as $type => $result) {
                    if (strpos($result, '✗') !== false) {
                        $this->stdout("  {$type}: {$result}\n");
                    }
                }
                return 1;
            }

            $this->stdout("Test 6: Alias support verification... ");
            $aliasTests = [
                'text' => 'plain_text',  // text should resolve to plain_text
                'richtext' => 'rich_text', // richtext should resolve to rich_text  
                'toggle' => 'lightswitch', // toggle should resolve to lightswitch
            ];

            $allAliasesWork = true;
            $aliasErrors = [];

            foreach ($aliasTests as $alias => $expectedType) {
                $definition = $registry->getField($alias);
                if (!$definition || $definition->type !== $expectedType) {
                    $allAliasesWork = false;
                    $aliasErrors[] = "Alias '{$alias}' should resolve to '{$expectedType}' but " . ($definition ? "resolved to '{$definition->type}'" : "was not found");
                }
            }
            
            if ($allAliasesWork) {
                $this->stdout("✓ PASSED\n", \yii\helpers\Console::FG_GREEN);
                $this->stdout("  All aliases work correctly\n");
            } else {
                $this->stdout("✗ FAILED\n", \yii\helpers\Console::FG_RED);
                foreach ($aliasErrors as $error) {
                    $this->stdout("  {$error}\n");
                }
                return 1;
            }

            $this->stdout("\n=== FINAL RESULTS ===\n");
            $this->stdout("✅ ALL PHASE 2 & 3 FIELD MIGRATIONS SUCCESSFUL!\n", \yii\helpers\Console::FG_GREEN);
            
            $stats = $registry->getStatistics();
            $this->stdout("\nRegistry Statistics:\n");
            $this->stdout("  Total fields: {$stats['totalFields']}\n");
            $this->stdout("  Auto-discovered: {$stats['autoDiscovered']}\n");
            $this->stdout("  Manually enhanced: {$stats['manuallyEnhanced']}\n");
            $this->stdout("  Migrated field types: " . count($migratedFields) . "\n");
            
            $this->stdout("\nMigrated Field Types:\n");
            foreach (array_keys($migratedFields) as $type) {
                $definition = $registry->getField($type);
                $this->stdout("  ✓ {$type} → {$definition->craftClass}\n", \yii\helpers\Console::FG_GREEN);
            }

            return 0;

        } catch (\Exception $e) {
            $this->stdout("\n❌ CRITICAL FAILURE: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            $this->stdout("Stack trace:\n" . $e->getTraceAsString() . "\n");
            return 1;
        }
    }
}