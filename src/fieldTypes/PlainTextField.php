<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * Plain Text field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class PlainTextField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Plain Text field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\PlainText::class);
        
        return new FieldDefinition([
            'type' => 'plain_text',
            'craftClass' => \craft\fields\PlainText::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['plain_text', 'text'], // Manual
            'llmDocumentation' => 'plain_text: multiline (boolean), charLimit (integer)', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'updateFactory' => [$this, 'updateField'], // Update factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a Plain Text field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\PlainText();
        
        // Apply Plain Text-specific settings exactly as in original implementation
        $field->multiline = $config['multiline'] ?? false;
        $field->initialRows = $field->multiline ? 4 : 1;
        if (isset($config['charLimit'])) {
            $field->charLimit = $config['charLimit'];
        }

        return $field;
    }

    /**
     * Update field instance with new configuration
     * EXACT COPY from FieldService::legacyUpdateField switch case
     */
    public function updateField(FieldInterface $field, array $updates): array
    {
        $modifications = [];
        
        if (isset($updates['multiline'])) {
            $field->multiline = (bool)$updates['multiline'];
            $field->initialRows = $field->multiline ? 4 : 1;
            $modifications[] = "Updated multiline to " . ($updates['multiline'] ? 'true' : 'false');
        }
        if (isset($updates['charLimit'])) {
            $field->charLimit = $updates['charLimit'];
            $modifications[] = "Updated charLimit to {$updates['charLimit']}";
        }
        
        return $modifications;
    }

    /**
     * Get test cases for Plain Text field
     * Enhanced from auto-generated base with Plain Text-specific scenarios
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Plain Text field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Plain Text',
                            'handle' => 'testPlainText',
                            'field_type' => 'plain_text',
                            'settings' => [
                                'multiline' => false
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Multiline Plain Text field with character limit',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Multiline Text',
                            'handle' => 'multilineText',
                            'field_type' => 'plain_text',
                            'settings' => [
                                'multiline' => true,
                                'charLimit' => 500
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Plain Text field with text alias',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Simple Text',
                            'handle' => 'simpleText',
                            'field_type' => 'text', // Using alias
                            'settings' => [
                                'charLimit' => 255
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Plain Text field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        // Validate multiline setting
        if (isset($config['multiline']) && !is_bool($config['multiline'])) {
            $errors[] = 'multiline must be a boolean value';
        }

        // Validate character limit
        if (isset($config['charLimit'])) {
            if (!is_numeric($config['charLimit']) || $config['charLimit'] < 0) {
                $errors[] = 'charLimit must be a non-negative number';
            }
        }

        return $errors;
    }
}