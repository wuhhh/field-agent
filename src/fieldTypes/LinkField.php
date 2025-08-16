<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;
use yii\base\Exception;

/**
 * Link field type implementation
 * Reference implementation for the hook-based field registration system
 */
class LinkField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Link field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Link::class);
        
        return new FieldDefinition([
            'type' => 'link',
            'craftClass' => \craft\fields\Link::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['link'], // Manual
            'llmDocumentation' => 'link: ONLY types (array), sources (array), showLabelField (boolean), allowRootRelativeUrls (boolean), allowAnchors (boolean), allowCustomSchemes (boolean)', // Manual
            'manualSettings' => [
                'linkTypes' => ['url', 'entry', 'site', 'email', 'tel', 'asset'], // Manual
            ],
            'factory' => [$this, 'createField'], // Manual factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a Link field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\Link();

        // Set enabled link types (default to url only for backward compatibility)
        $field->types = $config['types'] ?? ['url'];

        // Configure display options
        $field->showLabelField = $config['showLabelField'] ?? true;
        $field->maxLength = 255;

        // Configure sources for entry links
        $entrySources = '*'; // Default to all sources
        if (isset($config['sources']) && is_array($config['sources'])) {
            // Convert section handles to section UIDs for sources configuration
            $entriesService = \Craft::$app->getEntries();
            $sources = [];
            foreach ($config['sources'] as $sectionHandle) {
                $section = $entriesService->getSectionByHandle($sectionHandle);
                if ($section) {
                    $sources[] = 'section:' . $section->uid;
                }
            }
            if (!empty($sources)) {
                $entrySources = $sources;
            }
        }

        // Configure type-specific settings
        $typeSettings = [];

        // URL type settings
        if (in_array('url', $field->types)) {
            $typeSettings['url'] = [
                'allowRootRelativeUrls' => $config['allowRootRelativeUrls'] ?? true,
                'allowAnchors' => $config['allowAnchors'] ?? true,
                'allowCustomSchemes' => $config['allowCustomSchemes'] ?? false,
            ];
        }

        // Entry type settings
        if (in_array('entry', $field->types)) {
            $typeSettings['entry'] = [
                'sources' => $entrySources,
            ];
        }

        if (!empty($typeSettings)) {
            $field->typeSettings = $typeSettings;
        }

        return $field;
    }

    /**
     * Update field with new settings
     * TODO: Implement update logic in Phase 4
     */
    public function updateField(FieldInterface $field, array $updates): array
    {
        // Placeholder implementation - will be implemented in Phase 4
        return [];
    }

    /**
     * Get test cases for Link field
     * Enhanced from auto-generated base with Link-specific scenarios
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Link field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Link',
                            'handle' => 'testLink',
                            'field_type' => 'link'
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Link field with URL type only',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'External Link',
                            'handle' => 'externalLink',
                            'field_type' => 'link',
                            'settings' => [
                                'types' => ['url'],
                                'showLabelField' => true,
                                'allowRootRelativeUrls' => false,
                                'allowAnchors' => false,
                                'allowCustomSchemes' => true
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Link field with multiple types',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Call to Action',
                            'handle' => 'callToAction',
                            'field_type' => 'link',
                            'settings' => [
                                'types' => ['url', 'entry', 'email'],
                                'sources' => ['blog', 'pages'],
                                'showLabelField' => true
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Link field with entry links to specific sections',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Related Page',
                            'handle' => 'relatedPage',
                            'field_type' => 'link',
                            'settings' => [
                                'types' => ['entry'],
                                'sources' => ['pages', 'articles'],
                                'showLabelField' => false
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Link field with all link types',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Universal Link',
                            'handle' => 'universalLink',
                            'field_type' => 'link',
                            'settings' => [
                                'types' => ['url', 'entry', 'site', 'email', 'tel', 'asset'],
                                'sources' => ['*'],
                                'showLabelField' => true,
                                'allowRootRelativeUrls' => true,
                                'allowAnchors' => true,
                                'allowCustomSchemes' => false
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Link field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        // Validate types
        if (isset($config['types'])) {
            if (!is_array($config['types'])) {
                $errors[] = 'types must be an array';
            } else {
                $validTypes = ['url', 'entry', 'site', 'email', 'tel', 'asset'];
                foreach ($config['types'] as $type) {
                    if (!in_array($type, $validTypes)) {
                        $errors[] = "Invalid link type: {$type}. Valid types: " . implode(', ', $validTypes);
                    }
                }
                
                if (empty($config['types'])) {
                    $errors[] = 'At least one link type must be specified';
                }
            }
        }

        // Validate sources (if provided)
        if (isset($config['sources'])) {
            if (!is_array($config['sources'])) {
                $errors[] = 'sources must be an array';
            }
        }

        // Validate boolean settings
        $booleanSettings = ['showLabelField', 'allowRootRelativeUrls', 'allowAnchors', 'allowCustomSchemes'];
        foreach ($booleanSettings as $setting) {
            if (isset($config[$setting]) && !is_bool($config[$setting])) {
                $errors[] = "{$setting} must be a boolean value";
            }
        }

        return $errors;
    }
}