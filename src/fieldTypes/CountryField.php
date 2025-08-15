<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * Country field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class CountryField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Country field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Country::class);
        
        return new FieldDefinition([
            'type' => 'country',
            'craftClass' => \craft\fields\Country::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['country'], // Manual
            'llmDocumentation' => 'country: No specific settings - provides country selection dropdown', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a Country field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        // Country field is simple - no specific settings needed
        $field = new \craft\fields\Country();

        return $field;
    }

    /**
     * Get test cases for Country field
     * Enhanced from auto-generated base with Country-specific scenarios
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Country field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Country',
                            'handle' => 'testCountry',
                            'field_type' => 'country'
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Country field for address form',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Country',
                            'handle' => 'country',
                            'field_type' => 'country'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Country field configuration
     */
    public function validate(array $config): array
    {
        // Country field has no specific configuration options to validate
        return [];
    }
}