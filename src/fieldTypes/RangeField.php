<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * Range field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class RangeField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Range field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Range::class);

        return new FieldDefinition([
            'type' => 'range',
            'craftClass' => \craft\fields\Range::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['range', 'slider'], // Manual
            'llmDocumentation' => 'range: min (number), max (number), step (number), suffix (string)', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'updateFactory' => [$this, 'updateField'], // Update factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a Range field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\Range();
        $field->min = $config['min'] ?? 0;
        $field->max = $config['max'] ?? 100;
        $field->step = $config['step'] ?? 1;
        if (isset($config['suffix'])) {
            $field->suffix = $config['suffix'];
        }
        return $field;
    }

    /**
     * Update a Range field with new settings
     * Exact copy of legacy logic from FieldService::legacyUpdateField
     */
    public function updateField(FieldInterface $field, array $updates): array
    {
        $modifications = [];
        
        if (isset($updates['min'])) {
            $field->min = $updates['min'];
            $modifications[] = "Updated min to {$updates['min']}";
        }
        if (isset($updates['max'])) {
            $field->max = $updates['max'];
            $modifications[] = "Updated max to {$updates['max']}";
        }
        if (isset($updates['step'])) {
            $field->step = $updates['step'];
            $modifications[] = "Updated step to {$updates['step']}";
        }
        if (isset($updates['suffix'])) {
            $field->suffix = $updates['suffix'];
            $modifications[] = "Updated suffix to '{$updates['suffix']}'";
        }
        
        return $modifications;
    }

    /**
     * Get test cases for Range field
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Range field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Range',
                            'handle' => 'testRange',
                            'field_type' => 'range'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Range field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        if (isset($config['min']) && !is_numeric($config['min'])) {
            $errors[] = 'min must be a number';
        }

        if (isset($config['max']) && !is_numeric($config['max'])) {
            $errors[] = 'max must be a number';
        }

        if (isset($config['step']) && !is_numeric($config['step'])) {
            $errors[] = 'step must be a number';
        }

        if (isset($config['suffix']) && !is_string($config['suffix'])) {
            $errors[] = 'suffix must be a string';
        }

        return $errors;
    }
}
