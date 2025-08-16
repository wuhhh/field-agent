<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;
use yii\base\Exception;

/**
 * Content Block field type implementation
 * Reference implementation for the hook-based field registration system
 */
class ContentBlockField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Content Block field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\ContentBlock::class);
        
        return new FieldDefinition([
            'type' => 'content_block',
            'craftClass' => \craft\fields\ContentBlock::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['content_block', 'contentblock'], // Manual
            'llmDocumentation' => 'content_block: ONLY fields (array of existing field references by handle), viewMode (string). Fields must be created separately first.', // Manual
            'manualSettings' => [
                'viewModes' => ['grouped', 'pane', 'inline'], // Manual
            ],
            'factory' => [$this, 'createField'], // Manual factory method
            'updateFactory' => [$this, 'updateField'],
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a Content Block field instance from configuration
     * ARCHITECTURAL FIX: ContentBlock should only reference existing fields, not create them
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\ContentBlock();
        
        // Apply Content Block-specific settings
        $field->viewMode = $config['viewMode'] ?? 'grouped'; // grouped, pane, or inline

        // Create field layout with ONLY existing field references
        if (isset($config['fields']) && is_array($config['fields'])) {
            $fieldLayout = $this->createContentBlockFieldLayoutReferencesOnly($config['fields']);
            $field->setFieldLayout($fieldLayout);
        }

        return $field;
    }

    /**
     * Update a ContentBlock field with new settings
     * ContentBlock updates should only modify viewMode and field layout references
     */
    public function updateField(FieldInterface $field, array $updates): array
    {
        $modifications = [];
        
        // Handle viewMode updates
        if (isset($updates['viewMode'])) {
            $validModes = ['grouped', 'pane', 'inline'];
            if (in_array($updates['viewMode'], $validModes)) {
                $field->viewMode = $updates['viewMode'];
                $modifications[] = "Updated viewMode to {$updates['viewMode']}";
            } else {
                $modifications[] = "WARNING: Invalid viewMode '{$updates['viewMode']}'. Valid modes: " . implode(', ', $validModes);
            }
        }
        
        // Handle field layout updates (adding/removing existing field references)
        if (isset($updates['addFields']) && is_array($updates['addFields'])) {
            // Add references to existing fields
            $fieldLayout = $field->getFieldLayout();
            $existingElements = $fieldLayout ? $fieldLayout->getTabs()[0]->getElements() : [];
            $newElements = $this->createFieldLayoutElementsFromReferences($updates['addFields']);
            
            if (!empty($newElements)) {
                $allElements = array_merge($existingElements, $newElements);
                $fieldLayout = $this->createFieldLayoutFromElements($allElements);
                $field->setFieldLayout($fieldLayout);
                $modifications[] = "Added " . count($newElements) . " field references to ContentBlock";
            }
        }
        
        if (isset($updates['removeFields']) && is_array($updates['removeFields'])) {
            // Remove field references by handle
            $fieldLayout = $field->getFieldLayout();
            if ($fieldLayout) {
                $existingElements = $fieldLayout->getTabs()[0]->getElements();
                $filteredElements = [];
                $removedCount = 0;
                
                foreach ($existingElements as $element) {
                    if ($element instanceof \craft\fieldlayoutelements\CustomField) {
                        $fieldHandle = \Craft::$app->getFields()->getFieldByUid($element->fieldUid)?->handle;
                        if (!in_array($fieldHandle, $updates['removeFields'])) {
                            $filteredElements[] = $element;
                        } else {
                            $removedCount++;
                        }
                    } else {
                        $filteredElements[] = $element;
                    }
                }
                
                $fieldLayout = $this->createFieldLayoutFromElements($filteredElements);
                $field->setFieldLayout($fieldLayout);
                $modifications[] = "Removed {$removedCount} field references from ContentBlock";
            }
        }
        
        // Warn about field creation attempts
        if (isset($updates['fields'])) {
            $modifications[] = "WARNING: ContentBlock field creation via updates is not supported. Create fields separately first, then reference them.";
        }
        
        // Handle any other generic properties (but most shouldn't be updated)
        $handledProperties = ['viewMode', 'addFields', 'removeFields', 'fields'];
        foreach ($updates as $settingName => $settingValue) {
            if (!in_array($settingName, $handledProperties) && property_exists($field, $settingName)) {
                $field->$settingName = $settingValue;
                $modifications[] = "Updated {$settingName} to " . (is_bool($settingValue) ? ($settingValue ? 'true' : 'false') : $settingValue);
            }
        }
        
        return $modifications;
    }

    /**
     * Get test cases for Content Block field
     * CORRECTED: Shows proper architecture with existing field references only
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Content Block field with existing field references (CORRECT ARCHITECTURE)',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Article Block',
                            'handle' => 'articleBlock',
                            'field_type' => 'content_block',
                            'settings' => [
                                'fields' => [
                                    [
                                        'handle' => 'fieldTestSingleLine',  // References existing field
                                        'required' => true
                                    ],
                                    [
                                        'handle' => 'fieldTestRichText',    // References existing field
                                        'required' => false
                                    ],
                                    [
                                        'handle' => 'fieldTestImage',       // References existing field
                                        'required' => false
                                    ]
                                ],
                                'viewMode' => 'grouped'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Content Block field in pane view mode',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Hero Section',
                            'handle' => 'heroSection',
                            'field_type' => 'content_block',
                            'settings' => [
                                'fields' => [
                                    [
                                        'handle' => 'fieldTestSingleLine',  // References existing field
                                        'required' => true
                                    ],
                                    [
                                        'handle' => 'fieldTestMultiLine',   // References existing field
                                        'required' => false
                                    ],
                                    [
                                        'handle' => 'fieldTestLink',        // References existing field
                                        'required' => false
                                    ]
                                ],
                                'viewMode' => 'pane'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Content Block field with inline view mode',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Simple Card',
                            'handle' => 'simpleCard',
                            'field_type' => 'content_block',
                            'settings' => [
                                'fields' => [
                                    [
                                        'handle' => 'fieldTestSingleLine',  // References existing field
                                        'required' => true
                                    ],
                                    [
                                        'handle' => 'fieldTestImage',       // References existing field
                                        'required' => true
                                    ]
                                ],
                                'viewMode' => 'inline'
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Content Block field configuration
     * CORRECTED: Enforces proper architecture with existing field references only
     */
    public function validate(array $config): array
    {
        $errors = [];

        // Validate fields
        if (isset($config['fields'])) {
            if (!is_array($config['fields'])) {
                $errors[] = 'fields must be an array';
            } else {
                foreach ($config['fields'] as $index => $field) {
                    if (!is_array($field)) {
                        $errors[] = "Field at index {$index} must be an array";
                        continue;
                    }

                    if (empty($field['handle'])) {
                        $errors[] = "Field at index {$index} must have a 'handle' to reference an existing field";
                    }

                    // Warn about architectural violations (field creation within ContentBlock)
                    if (isset($field['field_type'])) {
                        $errors[] = "ARCHITECTURAL WARNING: Field at index {$index} has 'field_type' indicating inline field creation. ContentBlock should only reference existing fields. Create fields separately first.";
                    }
                    
                    if (isset($field['name']) && !isset($field['field_type'])) {
                        $errors[] = "Field at index {$index} has 'name' but no 'field_type'. ContentBlock should only reference existing fields by 'handle'.";
                    }

                    // Validate field reference exists
                    if (isset($field['handle']) && !isset($field['field_type'])) {
                        $fieldsService = \Craft::$app->getFields();
                        $existingField = $fieldsService->getFieldByHandle($field['handle']);
                        if (!$existingField) {
                            $errors[] = "Field at index {$index} references handle '{$field['handle']}' which does not exist. Create the field first.";
                        }
                    }
                }
            }
        }

        // Validate viewMode
        if (isset($config['viewMode'])) {
            $validModes = ['grouped', 'pane', 'inline'];
            if (!in_array($config['viewMode'], $validModes)) {
                $errors[] = "Invalid viewMode: {$config['viewMode']}. Valid modes: " . implode(', ', $validModes);
            }
        }

        return $errors;
    }

    /**
     * Create field layout for ContentBlock fields - REFERENCES ONLY
     * ARCHITECTURAL FIX: Only reference existing fields, do not create new ones
     */
    private function createContentBlockFieldLayoutReferencesOnly(array $fieldsConfig): \craft\models\FieldLayout
    {
        $fieldLayoutElements = $this->createFieldLayoutElementsFromReferences($fieldsConfig);
        return $this->createFieldLayoutFromElements($fieldLayoutElements);
    }
    
    /**
     * Create field layout elements from field references
     */
    private function createFieldLayoutElementsFromReferences(array $fieldsConfig): array
    {
        $fieldLayoutElements = [];
        $fieldsService = \Craft::$app->getFields();

        foreach ($fieldsConfig as $fieldConfig) {
            // Only handle existing field references
            if (isset($fieldConfig['handle'])) {
                $existingField = $fieldsService->getFieldByHandle($fieldConfig['handle']);
                if ($existingField) {
                    $fieldLayoutElement = new \craft\fieldlayoutelements\CustomField();
                    $fieldLayoutElement->fieldUid = $existingField->uid;
                    $fieldLayoutElement->required = $fieldConfig['required'] ?? false;
                    $fieldLayoutElements[] = $fieldLayoutElement;
                } else {
                    \Craft::warning("Field '{$fieldConfig['handle']}' not found for ContentBlock field layout", __METHOD__);
                }
            } elseif (isset($fieldConfig['field_type'])) {
                // Log warning about architectural issue but still support legacy behavior
                \Craft::warning("ContentBlock field creation detected - this should be done separately. Field type: {$fieldConfig['field_type']}", __METHOD__);
                
                // Delegate to main FieldService for backward compatibility
                $fieldService = \Craft::$app->plugins->getPlugin('field-agent')->fieldService;
                try {
                    $blockField = $fieldService->createFieldFromConfig($fieldConfig);
                    if ($blockField && $fieldsService->saveField($blockField)) {
                        $fieldLayoutElement = new \craft\fieldlayoutelements\CustomField();
                        $fieldLayoutElement->fieldUid = $blockField->uid;
                        $fieldLayoutElement->required = $fieldConfig['required'] ?? false;
                        $fieldLayoutElements[] = $fieldLayoutElement;
                    }
                } catch (\Exception $e) {
                    \Craft::error("Failed to create field for ContentBlock: {$e->getMessage()}", __METHOD__);
                }
            }
        }

        return $fieldLayoutElements;
    }
    
    /**
     * Create field layout from elements array
     */
    private function createFieldLayoutFromElements(array $elements): \craft\models\FieldLayout
    {
        $fieldLayout = new \craft\models\FieldLayout();
        $fieldLayout->type = \craft\fields\ContentBlock::class;
        
        $fieldLayout->setTabs([
            [
                'name' => 'Content',
                'elements' => $elements,
            ]
        ]);

        return $fieldLayout;
    }

}