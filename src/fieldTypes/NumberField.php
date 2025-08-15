<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * Number field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class NumberField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Number field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Number::class);
        
        return new FieldDefinition([
            'type' => 'number',
            'craftClass' => \craft\fields\Number::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['number'], // Manual
            'llmDocumentation' => 'number: decimals (integer), min (number), max (number), prefix (string), suffix (string)', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a Number field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\Number();
        
        // Apply Number-specific settings exactly as in original implementation
        if (isset($config['decimals'])) {
            $field->decimals = $config['decimals'];
        }
        if (isset($config['min'])) {
            $field->min = $config['min'];
        }
        if (isset($config['max'])) {
            $field->max = $config['max'];
        }
        if (isset($config['prefix'])) {
            $field->prefix = $config['prefix'];
        }
        if (isset($config['suffix'])) {
            $field->suffix = $config['suffix'];
        }

        return $field;
    }

    /**
     * Get test cases for Number field
     * Enhanced from auto-generated base with Number-specific scenarios
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Number field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Number',
                            'handle' => 'testNumber',
                            'field_type' => 'number'
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Number field with decimal places',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Price',
                            'handle' => 'price',
                            'field_type' => 'number',
                            'settings' => [
                                'decimals' => 2,
                                'min' => 0,
                                'prefix' => '$'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Number field with range and suffix',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Weight',
                            'handle' => 'weight',
                            'field_type' => 'number',
                            'settings' => [
                                'decimals' => 1,
                                'min' => 0,
                                'max' => 1000,
                                'suffix' => 'kg'
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Number field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        // Validate decimals
        if (isset($config['decimals']) && (!is_numeric($config['decimals']) || $config['decimals'] < 0)) {
            $errors[] = 'decimals must be a non-negative number';
        }

        // Validate min/max
        if (isset($config['min']) && !is_numeric($config['min'])) {
            $errors[] = 'min must be a number';
        }

        if (isset($config['max']) && !is_numeric($config['max'])) {
            $errors[] = 'max must be a number';
        }

        if (isset($config['min'], $config['max']) && $config['min'] > $config['max']) {
            $errors[] = 'min cannot be greater than max';
        }

        // Validate prefix/suffix
        if (isset($config['prefix']) && !is_string($config['prefix'])) {
            $errors[] = 'prefix must be a string';
        }

        if (isset($config['suffix']) && !is_string($config['suffix'])) {
            $errors[] = 'suffix must be a string';
        }

        return $errors;
    }
}