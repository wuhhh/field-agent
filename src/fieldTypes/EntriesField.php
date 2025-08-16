<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * Entries field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class EntriesField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Entries field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Entries::class);
        
        return new FieldDefinition([
            'type' => 'entries',
            'craftClass' => \craft\fields\Entries::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['entries'], // Manual
            'llmDocumentation' => 'entries: maxRelations (number), sources (array of section handles or "*" for all)', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'updateFactory' => [$this, 'updateField'], // Update factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create an Entries field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\Entries();
        $field->maxRelations = $config['maxRelations'] ?? 1;
        $field->viewMode = 'list';
        
        // Configure sources (sections)
        if (isset($config['sources']) && is_array($config['sources'])) {
            $entriesService = \Craft::$app->getEntries();
            $sources = [];
            foreach ($config['sources'] as $sectionHandle) {
                if ($sectionHandle === '*') {
                    // Allow all sections
                    $sources = '*';
                    break;
                }
                $section = $entriesService->getSectionByHandle($sectionHandle);
                if ($section) {
                    $sources[] = 'section:' . $section->uid;
                }
            }
            $field->sources = $sources ?: '*';
        } else {
            // Default to all sections
            $field->sources = '*';
        }
        
        return $field;
    }

    /**
     * Update an Entries field with new settings
     * Based on Entries field properties: maxRelations, minRelations, sources, viewMode, selectionLabel
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
        if (isset($updates['sources'])) {
            $field->sources = $updates['sources'];
            $modifications[] = "Updated sources";
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
     * Get test cases for Entries field
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Entries field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Entries',
                            'handle' => 'testEntries',
                            'field_type' => 'entries'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Entries field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        if (isset($config['maxRelations']) && !is_numeric($config['maxRelations'])) {
            $errors[] = 'maxRelations must be a number';
        }

        if (isset($config['sources']) && !is_array($config['sources'])) {
            $errors[] = 'sources must be an array';
        }

        return $errors;
    }
}