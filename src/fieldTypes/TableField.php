<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;
use yii\base\Exception;

/**
 * Table field type implementation
 * Reference implementation for the hook-based field registration system
 */
class TableField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Table field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Table::class);
        
        return new FieldDefinition([
            'type' => 'table',
            'craftClass' => \craft\fields\Table::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['table'], // Manual
            'llmDocumentation' => 'table: ONLY columns (array), minRows (integer), maxRows (integer), addRowLabel (string), defaults (array)', // Manual
            'manualSettings' => [
                'columnTypes' => ['singleline', 'multiline', 'number', 'checkbox', 'color', 'url', 'email', 'date', 'time'], // Manual
            ],
            'factory' => [$this, 'createField'], // Manual factory method
            'updateFactory' => [$this, 'updateField'], // Update factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a Table field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\Table();
        
        // Apply Table-specific settings exactly as in original implementation
        $field->columns = $this->prepareTableColumns($config['columns'] ?? []);
        $field->defaults = $config['defaults'] ?? [];
        $field->addRowLabel = $config['addRowLabel'] ?? 'Add a row';
        $field->maxRows = $config['maxRows'] ?? null;
        $field->minRows = $config['minRows'] ?? null;

        return $field;
    }

    /**
     * Update a Table field with new settings
     * Supports complex column operations and basic property updates
     */
    public function updateField(FieldInterface $field, array $updates): array
    {
        $modifications = [];
        
        // Handle column updates (most complex part)
        if (isset($updates['columns'])) {
            $newColumns = $this->prepareTableColumns($updates['columns']);
            $field->columns = $newColumns;
            $modifications[] = "Updated table columns (" . count($newColumns) . " columns)";
        }
        
        // Handle adding columns to existing ones
        if (isset($updates['addColumns'])) {
            $existingColumns = $field->columns ?? [];
            $newColumns = $this->prepareTableColumns($updates['addColumns']);
            $field->columns = array_merge($existingColumns, $newColumns);
            $modifications[] = "Added " . count($newColumns) . " new columns to table";
        }
        
        // Handle removing columns by handle
        if (isset($updates['removeColumns']) && is_array($updates['removeColumns'])) {
            $existingColumns = $field->columns ?? [];
            $remainingColumns = array_filter($existingColumns, function($column) use ($updates) {
                return !in_array($column['handle'] ?? '', $updates['removeColumns']);
            });
            $field->columns = array_values($remainingColumns); // Re-index array
            $removedCount = count($existingColumns) - count($remainingColumns);
            $modifications[] = "Removed {$removedCount} columns from table";
        }
        
        // Handle modifying existing columns
        if (isset($updates['modifyColumns']) && is_array($updates['modifyColumns'])) {
            $existingColumns = $field->columns ?? [];
            foreach ($updates['modifyColumns'] as $columnUpdate) {
                $handle = $columnUpdate['handle'] ?? '';
                for ($i = 0; $i < count($existingColumns); $i++) {
                    if (($existingColumns[$i]['handle'] ?? '') === $handle) {
                        // Update specific properties of this column
                        if (isset($columnUpdate['heading'])) {
                            $existingColumns[$i]['heading'] = $columnUpdate['heading'];
                        }
                        if (isset($columnUpdate['type'])) {
                            $existingColumns[$i]['type'] = $columnUpdate['type'];
                        }
                        if (isset($columnUpdate['width'])) {
                            $existingColumns[$i]['width'] = $columnUpdate['width'];
                        }
                        $modifications[] = "Modified column '{$handle}'";
                        break;
                    }
                }
            }
            $field->columns = $existingColumns;
        }
        
        // Handle simple property updates
        if (isset($updates['minRows'])) {
            $field->minRows = $updates['minRows'];
            $modifications[] = "Updated minRows to {$updates['minRows']}";
        }
        
        if (isset($updates['maxRows'])) {
            $field->maxRows = $updates['maxRows'];
            $modifications[] = "Updated maxRows to {$updates['maxRows']}";
        }
        
        if (isset($updates['addRowLabel'])) {
            $field->addRowLabel = $updates['addRowLabel'];
            $modifications[] = "Updated addRowLabel to '{$updates['addRowLabel']}'";
        }
        
        if (isset($updates['defaults'])) {
            $field->defaults = (array)$updates['defaults'];
            $modifications[] = "Updated default values";
        }
        
        // Handle any other generic properties
        $handledProperties = ['columns', 'addColumns', 'removeColumns', 'modifyColumns', 'minRows', 'maxRows', 'addRowLabel', 'defaults'];
        foreach ($updates as $settingName => $settingValue) {
            if (!in_array($settingName, $handledProperties) && property_exists($field, $settingName)) {
                $field->$settingName = $settingValue;
                $modifications[] = "Updated {$settingName} to " . (is_bool($settingValue) ? ($settingValue ? 'true' : 'false') : $settingValue);
            }
        }
        
        return $modifications;
    }

    /**
     * Get test cases for Table field
     * Enhanced from auto-generated base with Table-specific scenarios
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Table field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Table',
                            'handle' => 'testTable',
                            'field_type' => 'table',
                            'settings' => [
                                'columns' => [
                                    ['heading' => 'Name', 'handle' => 'name', 'type' => 'singleline'],
                                    ['heading' => 'Age', 'handle' => 'age', 'type' => 'number']
                                ],
                                'minRows' => 1,
                                'maxRows' => 10,
                                'addRowLabel' => 'Add Row'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Table field with string columns (simplified format)',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Simple Table',
                            'handle' => 'simpleTable',
                            'field_type' => 'table',
                            'settings' => [
                                'columns' => ['Name', 'Email', 'Phone'],
                                'maxRows' => 5
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Table field with complex column types',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Complex Table',
                            'handle' => 'complexTable',
                            'field_type' => 'table',
                            'settings' => [
                                'columns' => [
                                    ['heading' => 'URL', 'handle' => 'url', 'type' => 'url'],
                                    ['heading' => 'Email', 'handle' => 'email', 'type' => 'email'],
                                    ['heading' => 'Date', 'handle' => 'date', 'type' => 'date'],
                                    ['heading' => 'Active', 'handle' => 'active', 'type' => 'checkbox'],
                                    ['heading' => 'Notes', 'handle' => 'notes', 'type' => 'multiline']
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Table field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        // Validate columns
        if (isset($config['columns'])) {
            if (!is_array($config['columns'])) {
                $errors[] = 'Table columns must be an array';
            } else {
                foreach ($config['columns'] as $index => $column) {
                    if (is_string($column)) {
                        // String columns are valid (will be converted to array format)
                        continue;
                    }
                    
                    if (is_array($column)) {
                        // Validate array column format
                        if (empty($column['heading']) && empty($column['handle'])) {
                            $errors[] = "Column at index {$index} must have either 'heading' or 'handle'";
                        }
                        
                        if (isset($column['type'])) {
                            $validTypes = ['singleline', 'multiline', 'number', 'checkbox', 'color', 'url', 'email', 'date', 'time'];
                            if (!in_array($column['type'], $validTypes)) {
                                $errors[] = "Column at index {$index} has invalid type '{$column['type']}'. Valid types: " . implode(', ', $validTypes);
                            }
                        }
                    } else {
                        $errors[] = "Column at index {$index} must be either a string or array";
                    }
                }
            }
        }

        // Validate numeric limits
        if (isset($config['minRows']) && (!is_numeric($config['minRows']) || $config['minRows'] < 0)) {
            $errors[] = 'minRows must be a non-negative number';
        }

        if (isset($config['maxRows']) && (!is_numeric($config['maxRows']) || $config['maxRows'] < 1)) {
            $errors[] = 'maxRows must be a positive number';
        }

        if (isset($config['minRows'], $config['maxRows']) && $config['minRows'] > $config['maxRows']) {
            $errors[] = 'minRows cannot be greater than maxRows';
        }

        return $errors;
    }

    /**
     * Prepare table columns from configuration
     * EXACT copy from original FieldService implementation - no modifications
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
     * EXACT copy from original FieldService implementation - no modifications
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