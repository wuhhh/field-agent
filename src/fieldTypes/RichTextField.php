<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;
use yii\base\Exception;

/**
 * Rich Text field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class RichTextField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Rich Text field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Check if CKEditor is available
        if (!class_exists('craft\ckeditor\Field')) {
            throw new Exception('CKEditor plugin is not installed');
        }
        
        // Get auto-discovered base data from Craft APIs
        $craftClass = 'craft\ckeditor\Field';
        $autoData = $this->introspector->analyzeFieldType($craftClass);
        
        return new FieldDefinition([
            'type' => 'ckeditor',
            'craftClass' => $craftClass,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['rich_text', 'richtext', 'ckeditor'], // Manual
            'llmDocumentation' => 'ckeditor: Rich text editor using CKEditor plugin', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a Rich Text field instance from configuration
     * Preserves exact logic from original FieldService implementation
     * 
     * @param array $config
     * @return FieldInterface
     * @throws Exception if CKEditor plugin is not installed
     * @phpstan-ignore-next-line
     */
    public function createField(array $config): FieldInterface
    {
        if (!class_exists('craft\ckeditor\Field')) {
            throw new Exception('CKEditor plugin is not installed');
        }
        
        // Apply Rich Text-specific logic exactly as in original implementation
        /** @var FieldInterface $field */
        $field = new \craft\ckeditor\Field();
        
        // Set purifyHtml property if it exists (it should on CKEditor fields)
        if (property_exists($field, 'purifyHtml')) {
            $field->purifyHtml = true;
        }

        return $field;
    }

    /**
     * Update field with new settings
     * RichTextField supports generic property updates
     */
    public function updateField(FieldInterface $field, array $updates): array
    {
        $modifications = [];
        
        // For Rich Text field types, try generic property setting
        foreach ($updates as $settingName => $settingValue) {
            if (property_exists($field, $settingName)) {
                $field->$settingName = $settingValue;
                $modifications[] = "Updated {$settingName} to " . (is_bool($settingValue) ? ($settingValue ? 'true' : 'false') : $settingValue);
            }
        }
        
        return $modifications;
    }

    /**
     * Get test cases for CKEditor field
     * Enhanced from auto-generated base with CKEditor-specific scenarios
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic CKEditor field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test CKEditor',
                            'handle' => 'testCkeditor',
                            'field_type' => 'ckeditor'
                        ]
                    ]
                ]
            ],
            [
                'name' => 'CKEditor field using rich_text alias',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Content',
                            'handle' => 'content',
                            'field_type' => 'rich_text' // Using alias
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Rich Text field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        // No specific validation needed - CKEditor is assumed to be installed

        return $errors;
    }
}