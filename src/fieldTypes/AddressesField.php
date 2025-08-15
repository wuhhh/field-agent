<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * Addresses field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class AddressesField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Addresses field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Addresses::class);
        
        return new FieldDefinition([
            'type' => 'addresses',
            'craftClass' => \craft\fields\Addresses::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['addresses'], // Manual
            'llmDocumentation' => 'addresses: Field for storing addresses', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create an Addresses field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\Addresses();
        // No additional settings required for addresses field
        return $field;
    }

    /**
     * Get test cases for Addresses field
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Addresses field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Addresses',
                            'handle' => 'testAddresses',
                            'field_type' => 'addresses'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Addresses field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];
        // No specific validation needed for addresses field
        return $errors;
    }
}