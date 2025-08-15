<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * Email field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class EmailField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Email field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Email::class);
        
        return new FieldDefinition([
            'type' => 'email',
            'craftClass' => \craft\fields\Email::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['email'], // Manual
            'llmDocumentation' => 'email: placeholder (string)', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create an Email field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\Email();
        
        // Apply Email-specific settings exactly as in original implementation
        if (isset($config['placeholder'])) {
            $field->placeholder = $config['placeholder'];
        }

        return $field;
    }

    /**
     * Get test cases for Email field
     * Enhanced from auto-generated base with Email-specific scenarios
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Email field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Email',
                            'handle' => 'testEmail',
                            'field_type' => 'email'
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Email field with placeholder',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Email Address',
                            'handle' => 'emailAddress',
                            'field_type' => 'email',
                            'settings' => [
                                'placeholder' => 'you@example.com'
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Email field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        // Validate placeholder
        if (isset($config['placeholder']) && !is_string($config['placeholder'])) {
            $errors[] = 'placeholder must be a string';
        }

        return $errors;
    }
}