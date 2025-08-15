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
        // Get auto-discovered base data from Craft APIs
        $craftClass = class_exists(\craft\ckeditor\Field::class) ? \craft\ckeditor\Field::class : \craft\fields\PlainText::class;
        $autoData = $this->introspector->analyzeFieldType($craftClass);
        
        return new FieldDefinition([
            'type' => 'rich_text',
            'craftClass' => $craftClass,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['rich_text', 'richtext'], // Manual
            'llmDocumentation' => 'rich_text: No specific settings - uses CKEditor if available', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a Rich Text field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        // Apply Rich Text-specific logic exactly as in original implementation
        if (class_exists(\craft\ckeditor\Field::class)) {
            $field = new \craft\ckeditor\Field();
            $field->purifyHtml = true;
        } else {
            throw new Exception("CKEditor plugin not installed, cannot create rich text field");
        }

        return $field;
    }

    /**
     * Get test cases for Rich Text field
     * Enhanced from auto-generated base with Rich Text-specific scenarios
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Rich Text field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Rich Text',
                            'handle' => 'testRichText',
                            'field_type' => 'rich_text'
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Rich Text field using richtext alias',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Content',
                            'handle' => 'content',
                            'field_type' => 'richtext' // Using alias
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

        // Check if CKEditor is available
        if (!class_exists(\craft\ckeditor\Field::class)) {
            $errors[] = 'CKEditor plugin not installed, cannot create rich text field';
        }

        return $errors;
    }
}