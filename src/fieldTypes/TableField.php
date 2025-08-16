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
     * TODO: Implement table field update logic in Phase 4
     */
    public function updateField(FieldInterface $field, array $updates): array
    {
        // Placeholder implementation - will be implemented in Phase 4
        return [];
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