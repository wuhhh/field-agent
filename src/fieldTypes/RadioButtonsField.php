<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * RadioButtons field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class RadioButtonsField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the RadioButtons field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\RadioButtons::class);
        
        return new FieldDefinition([
            'type' => 'radio_buttons',
            'craftClass' => \craft\fields\RadioButtons::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['radio_buttons', 'radio'], // Manual
            'llmDocumentation' => 'radio_buttons: options (array of strings or {label, value, default} objects)', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'updateFactory' => [$this, 'updateField'], // Manual update method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a RadioButtons field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\RadioButtons();
        $field->options = $this->prepareOptions($config['options'] ?? []);
        return $field;
    }

    /**
     * Update a RadioButtons field with new settings
     * Exact copy of legacy logic from FieldService::legacyUpdateField
     */
    public function updateField(FieldInterface $field, array $updates): array
    {
        $modifications = [];
        
        if (isset($updates['options'])) {
            $field->options = $this->prepareOptions($updates['options']);
            $modifications[] = "Updated radio button options";
        }
        
        return $modifications;
    }

    /**
     * Get test cases for RadioButtons field
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic RadioButtons field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Radio Buttons',
                            'handle' => 'testRadioButtons',
                            'field_type' => 'radio_buttons',
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
     * Validate RadioButtons field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        if (!isset($config['options'])) {
            $errors[] = 'RadioButtons field requires options array';
        } elseif (!is_array($config['options'])) {
            $errors[] = 'options must be an array';
        } elseif (empty($config['options'])) {
            $errors[] = 'RadioButtons field must have at least one option';
        }

        return $errors;
    }

    /**
     * Prepare options array for RadioButtons field
     * Matches exact logic from FieldService
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