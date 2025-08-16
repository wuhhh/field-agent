<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * Categories field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class CategoriesField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Categories field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Categories::class);
        
        return new FieldDefinition([
            'type' => 'categories',
            'craftClass' => \craft\fields\Categories::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['categories'], // Manual
            'llmDocumentation' => 'categories: maxRelations (number), sources (array of category group handles or "*" for all)', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'updateFactory' => [$this, 'updateField'], // Update factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a Categories field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\Categories();
        $field->maxRelations = $config['maxRelations'] ?? null;
        $field->viewMode = 'list';
        
        // Configure sources (category groups)
        if (isset($config['sources']) && is_array($config['sources'])) {
            $categoriesService = \Craft::$app->getCategories();
            $sources = [];
            foreach ($config['sources'] as $groupHandle) {
                if ($groupHandle === '*') {
                    // Allow all category groups
                    $sources = '*';
                    break;
                }
                $group = $categoriesService->getGroupByHandle($groupHandle);
                if ($group) {
                    $sources[] = 'group:' . $group->uid;
                }
            }
            $field->sources = $sources ?: '*';
        } else {
            // Default to all category groups
            $field->sources = '*';
        }
        
        return $field;
    }

    /**
     * Update a Categories field with new settings
     * Based on Categories field properties: maxRelations, minRelations, source (singular), viewMode, selectionLabel
     */
    public function updateField(FieldInterface $field, array $updates): array
    {
        $modifications = [];
        
        if (isset($updates['maxRelations'])) {
            $field->maxRelations = $updates['maxRelations'];
            $modifications[] = "Updated maxRelations to {$updates['maxRelations']}";
        }
        if (isset($updates['minRelations'])) {
            $field->minRelations = $updates['minRelations'];
            $modifications[] = "Updated minRelations to {$updates['minRelations']}";
        }
        if (isset($updates['source'])) {
            $field->source = $updates['source'];
            $modifications[] = "Updated source";
        }
        if (isset($updates['viewMode'])) {
            $field->viewMode = $updates['viewMode'];
            $modifications[] = "Updated viewMode to {$updates['viewMode']}";
        }
        if (isset($updates['selectionLabel'])) {
            $field->selectionLabel = $updates['selectionLabel'];
            $modifications[] = "Updated selectionLabel to '{$updates['selectionLabel']}'";
        }
        
        return $modifications;
    }

    /**
     * Get test cases for Categories field
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Categories field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Categories',
                            'handle' => 'testCategories',
                            'field_type' => 'categories'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Categories field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        if (isset($config['maxRelations']) && !is_null($config['maxRelations']) && !is_numeric($config['maxRelations'])) {
            $errors[] = 'maxRelations must be a number or null';
        }

        if (isset($config['sources']) && !is_array($config['sources'])) {
            $errors[] = 'sources must be an array';
        }

        return $errors;
    }
}