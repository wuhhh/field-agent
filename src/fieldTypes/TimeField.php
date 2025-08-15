<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * Time field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class TimeField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Time field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Time::class);
        
        return new FieldDefinition([
            'type' => 'time',
            'craftClass' => \craft\fields\Time::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['time'], // Manual
            'llmDocumentation' => 'time: Field for storing time values', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a Time field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\Time();
        // No additional settings required for time field
        return $field;
    }

    /**
     * Get test cases for Time field
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Time field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Time',
                            'handle' => 'testTime',
                            'field_type' => 'time'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Time field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];
        // No specific validation needed for time field
        return $errors;
    }
}