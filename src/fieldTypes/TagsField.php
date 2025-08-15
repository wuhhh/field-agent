<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * Tags field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class TagsField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Tags field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Tags::class);
        
        return new FieldDefinition([
            'type' => 'tags',
            'craftClass' => \craft\fields\Tags::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['tags'], // Manual
            'llmDocumentation' => 'tags: sources (array of tag group handles or "*" for all)', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a Tags field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\Tags();
        
        // Configure sources (tag groups)
        if (isset($config['sources']) && is_array($config['sources'])) {
            $tagsService = \Craft::$app->getTags();
            $sources = [];
            foreach ($config['sources'] as $groupHandle) {
                if ($groupHandle === '*') {
                    // Allow all tag groups
                    $sources = '*';
                    break;
                }
                $group = $tagsService->getTagGroupByHandle($groupHandle);
                if ($group) {
                    $sources[] = 'taggroup:' . $group->uid;
                }
            }
            // Tags fields use 'source' (singular), not 'sources' (plural)
            $field->source = !empty($sources) && $sources !== '*' ? $sources[0] : null;
        } else {
            // Default to all tag groups
            $field->source = null;
        }
        
        return $field;
    }

    /**
     * Get test cases for Tags field
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Tags field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Tags',
                            'handle' => 'testTags',
                            'field_type' => 'tags'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Tags field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        if (isset($config['sources']) && !is_array($config['sources'])) {
            $errors[] = 'sources must be an array';
        }

        return $errors;
    }
}