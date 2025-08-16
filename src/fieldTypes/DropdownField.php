<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * Dropdown field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class DropdownField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Dropdown field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Dropdown::class);
        
        return new FieldDefinition([
            'type' => 'dropdown',
            'craftClass' => \craft\fields\Dropdown::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['dropdown'], // Manual
            'llmDocumentation' => 'dropdown: options (array) - Use format: ["value1","value2"] NOT objects', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'updateFactory' => [$this, 'updateField'], // Update factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a Dropdown field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\Dropdown();
        
        // Apply Dropdown-specific settings exactly as in original implementation
        $field->options = $this->prepareOptions($config['options'] ?? []);

        return $field;
    }

    /**
     * Update field instance with new configuration
     * EXACT COPY from FieldService::legacyUpdateField switch case
     */
    public function updateField(FieldInterface $field, array $updates): array
    {
        $modifications = [];
        
        if (isset($updates['options'])) {
            $field->options = $this->prepareOptions($updates['options']);
            $modifications[] = "Updated dropdown options";
        }
        
        return $modifications;
    }

    /**
     * Get test cases for Dropdown field
     * Enhanced from auto-generated base with Dropdown-specific scenarios
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Dropdown field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Dropdown',
                            'handle' => 'testDropdown',
                            'field_type' => 'dropdown',
                            'settings' => [
                                'options' => ['Option 1', 'Option 2', 'Option 3']
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Dropdown field with complex options',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Status Dropdown',
                            'handle' => 'statusDropdown',
                            'field_type' => 'dropdown',
                            'settings' => [
                                'options' => [
                                    ['label' => 'Draft', 'value' => 'draft', 'default' => true],
                                    ['label' => 'Published', 'value' => 'published', 'default' => false],
                                    ['label' => 'Archived', 'value' => 'archived', 'default' => false]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Dropdown field with mixed option formats',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Priority',
                            'handle' => 'priority',
                            'field_type' => 'dropdown',
                            'settings' => [
                                'options' => [
                                    'Low',
                                    ['label' => 'Medium', 'value' => 'medium'],
                                    'High'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Dropdown field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        // Validate options array
        if (!isset($config['options'])) {
            $errors[] = 'Dropdown field requires options array';
        } elseif (!is_array($config['options'])) {
            $errors[] = 'options must be an array';
        } else {
            if (empty($config['options'])) {
                $errors[] = 'Dropdown field must have at least one option';
            }

            foreach ($config['options'] as $index => $option) {
                if (is_string($option)) {
                    // String options are valid
                    continue;
                } elseif (is_array($option)) {
                    // Validate array option format
                    if (empty($option['label']) && empty($option['value'])) {
                        $errors[] = "Option at index {$index} must have either 'label' or 'value'";
                    }
                    
                    if (isset($option['default']) && !is_bool($option['default'])) {
                        $errors[] = "Option at index {$index} 'default' must be boolean";
                    }
                } else {
                    $errors[] = "Option at index {$index} must be either a string or array";
                }
            }
        }

        return $errors;
    }

    /**
     * Prepare options from configuration
     * EXACT copy from original FieldService implementation - no modifications
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
}