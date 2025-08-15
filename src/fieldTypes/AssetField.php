<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * Asset field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class AssetField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Asset field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Assets::class);
        
        return new FieldDefinition([
            'type' => 'assets',
            'craftClass' => \craft\fields\Assets::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['asset'], // Manual - single asset with maxRelations=1
            'llmDocumentation' => 'assets: maxRelations (integer), minRelations (integer), viewMode (string), allowedKinds (array)', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create an Asset field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\Assets();
        
        // Check if this was requested as "asset" (singular) vs "assets" (plural)
        $fieldType = $config['field_type'] ?? 'assets';
        
        // Apply Asset-specific settings exactly as in original implementation
        if ($fieldType === 'asset') {
            // Single asset field - default to maxRelations=1
            $field->maxRelations = $config['maxRelations'] ?? 1;
        } else {
            // Multiple assets field - no default limit
            $field->maxRelations = $config['maxRelations'] ?? null;
        }
        
        if (isset($config['minRelations'])) {
            $field->minRelations = $config['minRelations'];
        }
        $field->viewMode = 'list';
        // If allowedKinds is specified, enable restrictFiles
        if (isset($config['allowedKinds'])) {
            $field->allowedKinds = $config['allowedKinds'];
            $field->restrictFiles = true;
        }

        return $field;
    }

    /**
     * Get test cases for Asset field
     * Enhanced from auto-generated base with Asset-specific scenarios
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Asset field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Asset',
                            'handle' => 'testAsset',
                            'field_type' => 'asset'
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Asset field with relation limits',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Gallery',
                            'handle' => 'gallery',
                            'field_type' => 'asset',
                            'settings' => [
                                'maxRelations' => 10,
                                'minRelations' => 1
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Asset field restricted to images',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Featured Image',
                            'handle' => 'featuredImage',
                            'field_type' => 'asset',
                            'settings' => [
                                'maxRelations' => 1,
                                'allowedKinds' => ['image']
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Asset field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        // Validate relation limits
        if (isset($config['maxRelations']) && (!is_numeric($config['maxRelations']) || $config['maxRelations'] < 1)) {
            $errors[] = 'maxRelations must be a positive number';
        }

        if (isset($config['minRelations']) && (!is_numeric($config['minRelations']) || $config['minRelations'] < 0)) {
            $errors[] = 'minRelations must be a non-negative number';
        }

        if (isset($config['minRelations'], $config['maxRelations']) && $config['minRelations'] > $config['maxRelations']) {
            $errors[] = 'minRelations cannot be greater than maxRelations';
        }

        // Validate allowedKinds
        if (isset($config['allowedKinds'])) {
            if (!is_array($config['allowedKinds'])) {
                $errors[] = 'allowedKinds must be an array';
            } else {
                $validKinds = ['image', 'audio', 'video', 'pdf', 'compress', 'excel', 'html', 'javascript', 'json', 'pdf', 'powerpoint', 'text', 'video', 'word', 'xml'];
                foreach ($config['allowedKinds'] as $kind) {
                    if (!in_array($kind, $validKinds)) {
                        $errors[] = "Invalid allowedKind: {$kind}. Valid kinds: " . implode(', ', $validKinds);
                    }
                }
            }
        }

        // Validate viewMode
        if (isset($config['viewMode'])) {
            $validModes = ['list', 'large'];
            if (!in_array($config['viewMode'], $validModes)) {
                $errors[] = "Invalid viewMode: {$config['viewMode']}. Valid modes: " . implode(', ', $validModes);
            }
        }

        return $errors;
    }
}