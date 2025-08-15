<?php

namespace craftcms\fieldagent\console\controllers;

use Craft;
use yii\console\Controller;
use craftcms\fieldagent\fieldTypes\TableField;
use craftcms\fieldagent\fieldTypes\PlainTextField;
use craftcms\fieldagent\fieldTypes\EmailField;
use craftcms\fieldagent\fieldTypes\DropdownField;
use craftcms\fieldagent\services\FieldService as LegacyFieldService;

/**
 * Compare old vs new field creation systems
 */
class ComparisonTestController extends Controller
{
    /**
     * Compare Table field creation between old and new systems
     */
    public function actionTable(): int
    {
        $this->stdout("=== Table Field Creation Comparison ===\n\n");

        try {
            // Test configuration for both systems
            $testConfig = [
                'columns' => [
                    ['heading' => 'Name', 'handle' => 'name', 'type' => 'singleline'],
                    'Age' // Test string format that should become number
                ],
                'minRows' => 1,
                'maxRows' => 10,
                'addRowLabel' => 'Add Person'
            ];

            // Create field using new system
            $this->stdout("Creating field with NEW system... ");
            $newTableField = new TableField();
            $newField = $newTableField->createField($testConfig);
            $this->stdout("✓\n", \yii\helpers\Console::FG_GREEN);

            // Create field using old system (simulate)
            $this->stdout("Creating field with OLD system... ");
            $legacyService = new LegacyFieldService();
            $oldField = new \craft\fields\Table();
            
            // Apply old system logic (from FieldService.php)
            $oldField->columns = $this->prepareTableColumnsLegacy($testConfig['columns'] ?? []);
            $oldField->defaults = $testConfig['defaults'] ?? [];
            $oldField->addRowLabel = $testConfig['addRowLabel'] ?? 'Add a row';
            $oldField->maxRows = $testConfig['maxRows'] ?? null;
            $oldField->minRows = $testConfig['minRows'] ?? null;
            
            $this->stdout("✓\n", \yii\helpers\Console::FG_GREEN);

            // Compare field properties
            $this->stdout("\n=== Field Comparison ===\n");
            
            $this->stdout("Field Class:\n");
            $this->stdout("  New: " . get_class($newField) . "\n");
            $this->stdout("  Old: " . get_class($oldField) . "\n");
            $match1 = get_class($newField) === get_class($oldField);
            $this->stdout("  Match: " . ($match1 ? "✓" : "✗") . "\n", $match1 ? \yii\helpers\Console::FG_GREEN : \yii\helpers\Console::FG_RED);

            $this->stdout("\nColumns:\n");
            $this->stdout("  New: " . json_encode($newField->columns, JSON_PRETTY_PRINT) . "\n");
            $this->stdout("  Old: " . json_encode($oldField->columns, JSON_PRETTY_PRINT) . "\n");
            $match2 = json_encode($newField->columns) === json_encode($oldField->columns);
            $this->stdout("  Match: " . ($match2 ? "✓" : "✗") . "\n", $match2 ? \yii\helpers\Console::FG_GREEN : \yii\helpers\Console::FG_RED);

            $this->stdout("\nOther Properties:\n");
            $properties = ['minRows', 'maxRows', 'addRowLabel', 'defaults'];
            $allMatch = true;
            
            foreach ($properties as $prop) {
                $newVal = $newField->$prop ?? null;
                $oldVal = $oldField->$prop ?? null;
                $match = $newVal === $oldVal;
                $allMatch = $allMatch && $match;
                
                $this->stdout("  {$prop}: New=" . json_encode($newVal) . ", Old=" . json_encode($oldVal));
                $this->stdout(" " . ($match ? "✓" : "✗") . "\n", $match ? \yii\helpers\Console::FG_GREEN : \yii\helpers\Console::FG_RED);
            }

            $overallMatch = $match1 && $match2 && $allMatch;
            
            $this->stdout("\n=== OVERALL RESULT ===\n");
            if ($overallMatch) {
                $this->stdout("✅ NEW SYSTEM CREATES IDENTICAL FIELDS TO OLD SYSTEM\n", \yii\helpers\Console::FG_GREEN);
                return 0;
            } else {
                $this->stdout("❌ NEW SYSTEM CREATES DIFFERENT FIELDS THAN OLD SYSTEM\n", \yii\helpers\Console::FG_RED);
                return 1;
            }

        } catch (\Exception $e) {
            $this->stdout("❌ COMPARISON FAILED: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            $this->stdout("Stack trace:\n" . $e->getTraceAsString() . "\n");
            return 1;
        }
    }

    /**
     * Compare Plain Text field creation between old and new systems
     */
    public function actionPlainText(): int
    {
        $this->stdout("=== Plain Text Field Creation Comparison ===\n\n");

        try {
            // Test configuration for both systems
            $testConfig = [
                'multiline' => true,
                'charLimit' => 500
            ];

            // Create field using new system
            $this->stdout("Creating field with NEW system... ");
            $newPlainTextField = new PlainTextField();
            $newField = $newPlainTextField->createField($testConfig);
            $this->stdout("✓\n", \yii\helpers\Console::FG_GREEN);

            // Create field using old system (simulate)
            $this->stdout("Creating field with OLD system... ");
            $oldField = new \craft\fields\PlainText();
            
            // Apply old system logic (from FieldService.php)
            $oldField->multiline = $testConfig['multiline'] ?? false;
            $oldField->initialRows = $oldField->multiline ? 4 : 1;
            if (isset($testConfig['charLimit'])) {
                $oldField->charLimit = $testConfig['charLimit'];
            }
            
            $this->stdout("✓\n", \yii\helpers\Console::FG_GREEN);

            // Compare field properties
            $this->stdout("\n=== Field Comparison ===\n");
            
            $this->stdout("Field Class:\n");
            $this->stdout("  New: " . get_class($newField) . "\n");
            $this->stdout("  Old: " . get_class($oldField) . "\n");
            $match1 = get_class($newField) === get_class($oldField);
            $this->stdout("  Match: " . ($match1 ? "✓" : "✗") . "\n", $match1 ? \yii\helpers\Console::FG_GREEN : \yii\helpers\Console::FG_RED);

            $this->stdout("\nProperties:\n");
            $properties = ['multiline', 'initialRows', 'charLimit'];
            $allMatch = true;
            
            foreach ($properties as $prop) {
                $newVal = $newField->$prop ?? null;
                $oldVal = $oldField->$prop ?? null;
                $match = $newVal === $oldVal;
                $allMatch = $allMatch && $match;
                
                $this->stdout("  {$prop}: New=" . json_encode($newVal) . ", Old=" . json_encode($oldVal));
                $this->stdout(" " . ($match ? "✓" : "✗") . "\n", $match ? \yii\helpers\Console::FG_GREEN : \yii\helpers\Console::FG_RED);
            }

            $overallMatch = $match1 && $allMatch;
            
            $this->stdout("\n=== OVERALL RESULT ===\n");
            if ($overallMatch) {
                $this->stdout("✅ NEW SYSTEM CREATES IDENTICAL FIELDS TO OLD SYSTEM\n", \yii\helpers\Console::FG_GREEN);
                return 0;
            } else {
                $this->stdout("❌ NEW SYSTEM CREATES DIFFERENT FIELDS THAN OLD SYSTEM\n", \yii\helpers\Console::FG_RED);
                return 1;
            }

        } catch (\Exception $e) {
            $this->stdout("❌ COMPARISON FAILED: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            $this->stdout("Stack trace:\n" . $e->getTraceAsString() . "\n");
            return 1;
        }
    }

    /**
     * Compare Email field creation between old and new systems
     */
    public function actionEmail(): int
    {
        $this->stdout("=== Email Field Creation Comparison ===\n\n");

        try {
            // Test configuration for both systems
            $testConfig = [
                'placeholder' => 'you@example.com'
            ];

            // Create field using new system
            $this->stdout("Creating field with NEW system... ");
            $newEmailField = new EmailField();
            $newField = $newEmailField->createField($testConfig);
            $this->stdout("✓\n", \yii\helpers\Console::FG_GREEN);

            // Create field using old system (simulate)
            $this->stdout("Creating field with OLD system... ");
            $oldField = new \craft\fields\Email();
            
            // Apply old system logic (from FieldService.php)
            if (isset($testConfig['placeholder'])) {
                $oldField->placeholder = $testConfig['placeholder'];
            }
            
            $this->stdout("✓\n", \yii\helpers\Console::FG_GREEN);

            // Compare field properties
            $this->stdout("\n=== Field Comparison ===\n");
            
            $this->stdout("Field Class:\n");
            $this->stdout("  New: " . get_class($newField) . "\n");
            $this->stdout("  Old: " . get_class($oldField) . "\n");
            $match1 = get_class($newField) === get_class($oldField);
            $this->stdout("  Match: " . ($match1 ? "✓" : "✗") . "\n", $match1 ? \yii\helpers\Console::FG_GREEN : \yii\helpers\Console::FG_RED);

            $this->stdout("\nProperties:\n");
            $properties = ['placeholder'];
            $allMatch = true;
            
            foreach ($properties as $prop) {
                $newVal = $newField->$prop ?? null;
                $oldVal = $oldField->$prop ?? null;
                $match = $newVal === $oldVal;
                $allMatch = $allMatch && $match;
                
                $this->stdout("  {$prop}: New=" . json_encode($newVal) . ", Old=" . json_encode($oldVal));
                $this->stdout(" " . ($match ? "✓" : "✗") . "\n", $match ? \yii\helpers\Console::FG_GREEN : \yii\helpers\Console::FG_RED);
            }

            $overallMatch = $match1 && $allMatch;
            
            $this->stdout("\n=== OVERALL RESULT ===\n");
            if ($overallMatch) {
                $this->stdout("✅ NEW SYSTEM CREATES IDENTICAL FIELDS TO OLD SYSTEM\n", \yii\helpers\Console::FG_GREEN);
                return 0;
            } else {
                $this->stdout("❌ NEW SYSTEM CREATES DIFFERENT FIELDS THAN OLD SYSTEM\n", \yii\helpers\Console::FG_RED);
                return 1;
            }

        } catch (\Exception $e) {
            $this->stdout("❌ COMPARISON FAILED: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            $this->stdout("Stack trace:\n" . $e->getTraceAsString() . "\n");
            return 1;
        }
    }

    /**
     * Compare Dropdown field creation between old and new systems
     */
    public function actionDropdown(): int
    {
        $this->stdout("=== Dropdown Field Creation Comparison ===\n\n");

        try {
            // Test configuration for both systems
            $testConfig = [
                'options' => [
                    'Simple Option',
                    ['label' => 'Complex Option', 'value' => 'complex', 'default' => true],
                    'Another Simple'
                ]
            ];

            // Create field using new system
            $this->stdout("Creating field with NEW system... ");
            $newDropdownField = new DropdownField();
            $newField = $newDropdownField->createField($testConfig);
            $this->stdout("✓\n", \yii\helpers\Console::FG_GREEN);

            // Create field using old system (simulate)
            $this->stdout("Creating field with OLD system... ");
            $oldField = new \craft\fields\Dropdown();
            
            // Apply old system logic (from FieldService.php)
            $oldField->options = $this->prepareOptionsLegacy($testConfig['options'] ?? []);
            
            $this->stdout("✓\n", \yii\helpers\Console::FG_GREEN);

            // Compare field properties
            $this->stdout("\n=== Field Comparison ===\n");
            
            $this->stdout("Field Class:\n");
            $this->stdout("  New: " . get_class($newField) . "\n");
            $this->stdout("  Old: " . get_class($oldField) . "\n");
            $match1 = get_class($newField) === get_class($oldField);
            $this->stdout("  Match: " . ($match1 ? "✓" : "✗") . "\n", $match1 ? \yii\helpers\Console::FG_GREEN : \yii\helpers\Console::FG_RED);

            $this->stdout("\nOptions:\n");
            $this->stdout("  New: " . json_encode($newField->options, JSON_PRETTY_PRINT) . "\n");
            $this->stdout("  Old: " . json_encode($oldField->options, JSON_PRETTY_PRINT) . "\n");
            $match2 = json_encode($newField->options) === json_encode($oldField->options);
            $this->stdout("  Match: " . ($match2 ? "✓" : "✗") . "\n", $match2 ? \yii\helpers\Console::FG_GREEN : \yii\helpers\Console::FG_RED);

            $overallMatch = $match1 && $match2;
            
            $this->stdout("\n=== OVERALL RESULT ===\n");
            if ($overallMatch) {
                $this->stdout("✅ NEW SYSTEM CREATES IDENTICAL FIELDS TO OLD SYSTEM\n", \yii\helpers\Console::FG_GREEN);
                return 0;
            } else {
                $this->stdout("❌ NEW SYSTEM CREATES DIFFERENT FIELDS THAN OLD SYSTEM\n", \yii\helpers\Console::FG_RED);
                return 1;
            }

        } catch (\Exception $e) {
            $this->stdout("❌ COMPARISON FAILED: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            $this->stdout("Stack trace:\n" . $e->getTraceAsString() . "\n");
            return 1;
        }
    }

    /**
     * Prepare options from configuration (exact copy from FieldService.php)
     */
    private function prepareOptionsLegacy(array $options): array
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
     * Legacy table column preparation logic (exact copy from FieldService.php)
     */
    private function prepareTableColumnsLegacy(array $columns): array
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
     * Legacy handle creation logic (exact copy from FieldService.php)
     */
    private function createHandle(string $name): string
    {
        // Convert to camelCase and remove special characters
        $handle = preg_replace('/[^a-zA-Z0-9]/', ' ', $name);
        $handle = trim($handle);
        $handle = ucwords($handle);
        $handle = str_replace(' ', '', $handle);
        $handle = lcfirst($handle);
        
        return $handle ?: 'column';
    }
}