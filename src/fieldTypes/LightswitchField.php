<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * Lightswitch field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class LightswitchField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Lightswitch field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Lightswitch::class);
        
        return new FieldDefinition([
            'type' => 'lightswitch',
            'craftClass' => \craft\fields\Lightswitch::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['lightswitch', 'toggle'], // Manual
            'llmDocumentation' => 'lightswitch: default (boolean), onLabel (string), offLabel (string)', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a Lightswitch field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\Lightswitch();
        
        // Apply Lightswitch-specific settings exactly as in original implementation
        if (isset($config['default'])) {
            $field->default = $config['default'];
        }
        if (isset($config['onLabel'])) {
            $field->onLabel = $config['onLabel'];
        }
        if (isset($config['offLabel'])) {
            $field->offLabel = $config['offLabel'];
        }

        return $field;
    }

    /**
     * Get test cases for Lightswitch field
     * Enhanced from auto-generated base with Lightswitch-specific scenarios
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Lightswitch field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Lightswitch',
                            'handle' => 'testLightswitch',
                            'field_type' => 'lightswitch'
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Lightswitch field with custom labels and default',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Published',
                            'handle' => 'published',
                            'field_type' => 'lightswitch',
                            'settings' => [
                                'default' => true,
                                'onLabel' => 'Published',
                                'offLabel' => 'Draft'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Lightswitch field using toggle alias',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Active Toggle',
                            'handle' => 'activeToggle',
                            'field_type' => 'toggle', // Using alias
                            'settings' => [
                                'default' => false
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Lightswitch field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        // Validate default value
        if (isset($config['default']) && !is_bool($config['default'])) {
            $errors[] = 'default must be a boolean value';
        }

        // Validate labels
        if (isset($config['onLabel']) && !is_string($config['onLabel'])) {
            $errors[] = 'onLabel must be a string';
        }

        if (isset($config['offLabel']) && !is_string($config['offLabel'])) {
            $errors[] = 'offLabel must be a string';
        }

        return $errors;
    }
}