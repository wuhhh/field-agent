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
            'llmDocumentation' => 'link: types (array), sources (array), showLabelField (boolean), allowRootRelativeUrls (boolean), allowAnchors (boolean), allowCustomSchemes (boolean), advancedFields (array), target (string)', // Manual
            'manualSettings' => [
                'linkTypes' => ['url', 'entry', 'asset', 'category', 'email', 'tel', 'sms'], // Manual - Complete list
                'advancedFields' => ['urlSuffix', 'target', 'title', 'class', 'id', 'rel', 'ariaLabel', 'download'], // Manual
            ],
            'factory' => [$this, 'createField'], // Manual factory method
            'updateFactory' => [$this, 'updateField'], // Update factory method
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
        
        // Configure advanced fields if specified
        if (isset($config['advancedFields']) && is_array($config['advancedFields'])) {
            $field->advancedFields = $config['advancedFields'];
        }
        
        // If target is specified, ensure it's included in advanced fields
        if (isset($config['target'])) {
            $advancedFields = $field->advancedFields ?? [];
            if (!in_array('target', $advancedFields)) {
                $advancedFields[] = 'target';
                $field->advancedFields = $advancedFields;
            }
        }

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
        
        // Category type settings
        if (in_array('category', $field->types)) {
            // Configure category sources similarly to entries
            $categorySources = '*'; // Default to all categories
            if (isset($config['categorySources']) && is_array($config['categorySources'])) {
                $categoriesService = \Craft::$app->getCategories();
                $sources = [];
                foreach ($config['categorySources'] as $groupHandle) {
                    $group = $categoriesService->getGroupByHandle($groupHandle);
                    if ($group) {
                        $sources[] = 'group:' . $group->uid;
                    }
                }
                if (!empty($sources)) {
                    $categorySources = $sources;
                }
            }
            $typeSettings['category'] = [
                'sources' => $categorySources,
            ];
        }
        
        // Asset type settings
        if (in_array('asset', $field->types)) {
            $assetSources = '*'; // Default to all assets
            if (isset($config['assetSources']) && is_array($config['assetSources'])) {
                $volumesService = \Craft::$app->getVolumes();
                $sources = [];
                foreach ($config['assetSources'] as $volumeHandle) {
                    $volume = $volumesService->getVolumeByHandle($volumeHandle);
                    if ($volume) {
                        $sources[] = 'volume:' . $volume->uid;
                    }
                }
                if (!empty($sources)) {
                    $assetSources = $sources;
                }
            }
            $typeSettings['asset'] = [
                'sources' => $assetSources,
            ];
        }

        if (!empty($typeSettings)) {
            $field->typeSettings = $typeSettings;
        }

        return $field;
    }

    /**
     * Update field with new settings
     * LinkField supports specific property updates and generic updates
     */
    public function updateField(FieldInterface $field, array $updates): array
    {
        $modifications = [];
        
        // Handle specific Link field properties
        if (isset($updates['types'])) {
            $field->types = (array)$updates['types'];
            $modifications[] = "Updated types to " . implode(', ', $field->types);
        }
        
        if (isset($updates['showLabelField'])) {
            $field->showLabelField = (bool)$updates['showLabelField'];
            $modifications[] = "Updated showLabelField to " . ($updates['showLabelField'] ? 'true' : 'false');
        }
        
        // Handle sources update (complex - requires section UID conversion)
        if (isset($updates['sources']) && is_array($updates['sources'])) {
            $entriesService = \Craft::$app->getEntries();
            $sources = [];
            foreach ($updates['sources'] as $sectionHandle) {
                $section = $entriesService->getSectionByHandle($sectionHandle);
                if ($section) {
                    $sources[] = 'section:' . $section->uid;
                }
            }
            
            // Update typeSettings for entry type
            $typeSettings = $field->typeSettings ?? [];
            if (!empty($sources)) {
                $typeSettings['entry'] = ['sources' => $sources];
                $field->typeSettings = $typeSettings;
                $modifications[] = "Updated sources to " . implode(', ', $updates['sources']);
            }
        }
        
        // Handle advanced fields updates
        if (isset($updates['advancedFields']) && is_array($updates['advancedFields'])) {
            $field->advancedFields = $updates['advancedFields'];
            $modifications[] = "Updated advancedFields to " . implode(', ', $updates['advancedFields']);
        }
        
        // Handle target updates (ensure target is in advanced fields)
        if (isset($updates['target'])) {
            $advancedFields = $field->advancedFields ?? [];
            if (!in_array('target', $advancedFields)) {
                $advancedFields[] = 'target';
                $field->advancedFields = $advancedFields;
                $modifications[] = "Added target to advanced fields";
            }
        }
        
        // Handle category sources update
        if (isset($updates['categorySources']) && is_array($updates['categorySources'])) {
            $categoriesService = \Craft::$app->getCategories();
            $sources = [];
            foreach ($updates['categorySources'] as $groupHandle) {
                $group = $categoriesService->getGroupByHandle($groupHandle);
                if ($group) {
                    $sources[] = 'group:' . $group->uid;
                }
            }
            
            $typeSettings = $field->typeSettings ?? [];
            if (!empty($sources)) {
                $typeSettings['category'] = ['sources' => $sources];
                $field->typeSettings = $typeSettings;
                $modifications[] = "Updated category sources to " . implode(', ', $updates['categorySources']);
            }
        }
        
        // Handle asset sources update
        if (isset($updates['assetSources']) && is_array($updates['assetSources'])) {
            $volumesService = \Craft::$app->getVolumes();
            $sources = [];
            foreach ($updates['assetSources'] as $volumeHandle) {
                $volume = $volumesService->getVolumeByHandle($volumeHandle);
                if ($volume) {
                    $sources[] = 'volume:' . $volume->uid;
                }
            }
            
            $typeSettings = $field->typeSettings ?? [];
            if (!empty($sources)) {
                $typeSettings['asset'] = ['sources' => $sources];
                $field->typeSettings = $typeSettings;
                $modifications[] = "Updated asset sources to " . implode(', ', $updates['assetSources']);
            }
        }

        // Handle URL-specific type settings
        $urlSettings = ['allowRootRelativeUrls', 'allowAnchors', 'allowCustomSchemes'];
        $urlTypeSettingsUpdated = [];
        foreach ($urlSettings as $setting) {
            if (isset($updates[$setting])) {
                $urlTypeSettingsUpdated[$setting] = (bool)$updates[$setting];
                $modifications[] = "Updated {$setting} to " . ($updates[$setting] ? 'true' : 'false');
            }
        }
        
        if (!empty($urlTypeSettingsUpdated)) {
            $typeSettings = $field->typeSettings ?? [];
            $typeSettings['url'] = array_merge($typeSettings['url'] ?? [], $urlTypeSettingsUpdated);
            $field->typeSettings = $typeSettings;
        }
        
        // Handle any other generic properties
        $handledProperties = ['types', 'showLabelField', 'sources', 'categorySources', 'assetSources', 'advancedFields', 'target', 'allowRootRelativeUrls', 'allowAnchors', 'allowCustomSchemes'];
        foreach ($updates as $settingName => $settingValue) {
            if (!in_array($settingName, $handledProperties) && property_exists($field, $settingName)) {
                $field->$settingName = $settingValue;
                $modifications[] = "Updated {$settingName} to " . (is_bool($settingValue) ? ($settingValue ? 'true' : 'false') : $settingValue);
            }
        }
        
        return $modifications;
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