<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * Icon field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class IconField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Icon field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Icon::class);
        
        return new FieldDefinition([
            'type' => 'icon',
            'craftClass' => \craft\fields\Icon::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['icon'], // Manual
            'llmDocumentation' => 'icon: Field for selecting icons', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create an Icon field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\Icon();
        // No additional settings required for icon field
        return $field;
    }

    /**
     * Get test cases for Icon field
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Icon field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Icon',
                            'handle' => 'testIcon',
                            'field_type' => 'icon'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Icon field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];
        // No specific validation needed for icon field
        return $errors;
    }
}