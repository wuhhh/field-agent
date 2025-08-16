<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * ButtonGroup field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class ButtonGroupField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the ButtonGroup field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\ButtonGroup::class);
        
        return new FieldDefinition([
            'type' => 'button_group',
            'craftClass' => \craft\fields\ButtonGroup::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['button_group', 'buttongroup'], // Manual
            'llmDocumentation' => 'button_group: options (array of strings or {label, value, icon, default} objects)', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'updateFactory' => [$this, 'updateField'], // Manual update method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a ButtonGroup field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\ButtonGroup();
        $field->options = $this->prepareButtonGroupOptions($config['options'] ?? []);
        return $field;
    }

    /**
     * Update a ButtonGroup field with new settings
     * Exact copy of legacy logic from FieldService::legacyUpdateField
     */
    public function updateField(FieldInterface $field, array $updates): array
    {
        $modifications = [];
        
        if (isset($updates['options'])) {
            $field->options = $this->prepareButtonGroupOptions($updates['options']);
            $modifications[] = "Updated button group options";
        }
        
        return $modifications;
    }

    /**
     * Get test cases for ButtonGroup field
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic ButtonGroup field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Button Group',
                            'handle' => 'testButtonGroup',
                            'field_type' => 'button_group',
                            'settings' => [
                                'options' => ['Option 1', 'Option 2', 'Option 3']
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate ButtonGroup field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        if (!isset($config['options'])) {
            $errors[] = 'ButtonGroup field requires options array';
        } elseif (!is_array($config['options'])) {
            $errors[] = 'options must be an array';
        } elseif (empty($config['options'])) {
            $errors[] = 'ButtonGroup field must have at least one option';
        }

        return $errors;
    }

    /**
     * Prepare options array for ButtonGroup field
     * Matches exact logic from FieldService
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
}