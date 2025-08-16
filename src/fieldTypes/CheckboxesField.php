<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * Checkboxes field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class CheckboxesField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Checkboxes field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Checkboxes::class);
        
        return new FieldDefinition([
            'type' => 'checkboxes',
            'craftClass' => \craft\fields\Checkboxes::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['checkboxes'], // Manual
            'llmDocumentation' => 'checkboxes: options (array of strings or {label, value, default} objects)', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'updateFactory' => [$this, 'updateField'], // Manual update method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a Checkboxes field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\Checkboxes();
        $field->options = $this->prepareOptions($config['options'] ?? []);
        return $field;
    }

    /**
     * Update a Checkboxes field with new settings
     * Exact copy of legacy logic from FieldService::legacyUpdateField
     */
    public function updateField(FieldInterface $field, array $updates): array
    {
        $modifications = [];
        
        if (isset($updates['options'])) {
            $field->options = $this->prepareOptions($updates['options']);
            $modifications[] = "Updated checkbox options";
        }
        
        return $modifications;
    }

    /**
     * Get test cases for Checkboxes field
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Checkboxes field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Checkboxes',
                            'handle' => 'testCheckboxes',
                            'field_type' => 'checkboxes',
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
     * Validate Checkboxes field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        if (!isset($config['options'])) {
            $errors[] = 'Checkboxes field requires options array';
        } elseif (!is_array($config['options'])) {
            $errors[] = 'options must be an array';
        } elseif (empty($config['options'])) {
            $errors[] = 'Checkboxes field must have at least one option';
        }

        return $errors;
    }

    /**
     * Prepare options array for Checkboxes field
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