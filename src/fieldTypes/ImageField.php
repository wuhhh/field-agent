<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * Image field type implementation
 * Special case of AssetField with allowedKinds=['image'] and restrictFiles=true
 * Reference implementation for the hook-based field registration system
 */
class ImageField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Image field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs (using Assets class as base)
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Assets::class);
        
        return new FieldDefinition([
            'type' => 'image',
            'craftClass' => \craft\fields\Assets::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['image'], // Manual
            'llmDocumentation' => 'image: ONLY maxRelations (integer), minRelations (integer), viewMode (string) - automatically restricted to image files', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'updateFactory' => [$this, 'updateField'], // Update factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create an Image field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\Assets();
        
        // Apply Image-specific settings exactly as in original implementation
        $field->allowedKinds = ['image'];
        $field->restrictFiles = true; // Must enable this for allowedKinds to work
        $field->maxRelations = $config['maxRelations'] ?? 1;
        if (isset($config['minRelations'])) {
            $field->minRelations = $config['minRelations'];
        }
        $field->viewMode = 'list';

        return $field;
    }

    /**
     * Update an Image field with new settings
     * Same as Asset field logic (maxRelations, minRelations, etc.) since Image extends Assets
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
        if (isset($updates['viewMode'])) {
            $field->viewMode = $updates['viewMode'];
            $modifications[] = "Updated viewMode to {$updates['viewMode']}";
        }
        if (isset($updates['sources'])) {
            $field->sources = $updates['sources'];
            $modifications[] = "Updated sources";
        }
        
        return $modifications;
    }

    /**
     * Get test cases for Image field
     * Enhanced from auto-generated base with Image-specific scenarios
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Image field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Image',
                            'handle' => 'testImage',
                            'field_type' => 'image'
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Single Image field',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Featured Image',
                            'handle' => 'featuredImage',
                            'field_type' => 'image',
                            'settings' => [
                                'maxRelations' => 1
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Multiple Images field (gallery)',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Image Gallery',
                            'handle' => 'imageGallery',
                            'field_type' => 'image',
                            'settings' => [
                                'maxRelations' => 20,
                                'minRelations' => 1
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Hero Images field with limits',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Hero Images',
                            'handle' => 'heroImages',
                            'field_type' => 'image',
                            'settings' => [
                                'maxRelations' => 3,
                                'minRelations' => 1
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Unlimited Images field',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'All Images',
                            'handle' => 'allImages',
                            'field_type' => 'image',
                            'settings' => [
                                'maxRelations' => null,
                                'minRelations' => 0
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Image field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        // Validate relation limits
        if (isset($config['maxRelations']) && $config['maxRelations'] !== null && (!is_numeric($config['maxRelations']) || $config['maxRelations'] < 1)) {
            $errors[] = 'maxRelations must be a positive number or null for unlimited';
        }

        if (isset($config['minRelations']) && (!is_numeric($config['minRelations']) || $config['minRelations'] < 0)) {
            $errors[] = 'minRelations must be a non-negative number';
        }

        if (isset($config['minRelations'], $config['maxRelations']) && $config['maxRelations'] !== null && $config['minRelations'] > $config['maxRelations']) {
            $errors[] = 'minRelations cannot be greater than maxRelations';
        }

        // Image fields don't support allowedKinds configuration (it's fixed to ['image'])
        if (isset($config['allowedKinds'])) {
            $errors[] = 'allowedKinds cannot be configured for image fields (automatically set to ["image"])';
        }

        // Image fields don't support restrictFiles configuration (it's fixed to true)
        if (isset($config['restrictFiles'])) {
            $errors[] = 'restrictFiles cannot be configured for image fields (automatically set to true)';
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