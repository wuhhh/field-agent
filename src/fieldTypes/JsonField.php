<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * Json field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class JsonField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Json field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Json::class);
        
        return new FieldDefinition([
            'type' => 'json',
            'craftClass' => \craft\fields\Json::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['json'], // Manual
            'llmDocumentation' => 'json: Field for storing JSON data', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a Json field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\Json();
        // No additional settings required for json field
        return $field;
    }

    /**
     * Get test cases for Json field
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Json field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Json',
                            'handle' => 'testJson',
                            'field_type' => 'json'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Json field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];
        // No specific validation needed for json field
        return $errors;
    }
}