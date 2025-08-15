<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * Date field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class DateField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Date field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Date::class);
        
        return new FieldDefinition([
            'type' => 'date',
            'craftClass' => \craft\fields\Date::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['date', 'datetime'], // Manual
            'llmDocumentation' => 'date: showDate (boolean), showTime (boolean), showTimeZone (boolean)', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a Date field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\Date();
        $field->showTimeZone = $config['showTimeZone'] ?? false;
        $field->showDate = $config['showDate'] ?? true;
        $field->showTime = $config['showTime'] ?? false;
        return $field;
    }

    /**
     * Get test cases for Date field
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Date field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Date',
                            'handle' => 'testDate',
                            'field_type' => 'date'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Date field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        if (isset($config['showDate']) && !is_bool($config['showDate'])) {
            $errors[] = 'showDate must be a boolean';
        }

        if (isset($config['showTime']) && !is_bool($config['showTime'])) {
            $errors[] = 'showTime must be a boolean';
        }

        if (isset($config['showTimeZone']) && !is_bool($config['showTimeZone'])) {
            $errors[] = 'showTimeZone must be a boolean';
        }

        return $errors;
    }
}