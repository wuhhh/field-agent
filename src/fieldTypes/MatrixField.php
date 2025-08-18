<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;
use yii\base\Exception;

/**
 * Matrix field type implementation
 * Reference implementation for the hook-based field registration system
 */
class MatrixField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;
    
    /**
     * @var array Tracks block fields created during matrix field creation
     */
    private array $createdBlockFields = [];

    /**
     * @var array Tracks block entry types created during matrix field creation
     */
    private array $createdBlockEntryTypes = [];

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Matrix field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Matrix::class);
        
        return new FieldDefinition([
            'type' => 'matrix',
            'craftClass' => \craft\fields\Matrix::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['matrix'], // Manual
            'llmDocumentation' => 'matrix: ONLY entryTypes (array), minEntries (integer), maxEntries (integer), viewMode (string)', // Manual
            'manualSettings' => [
                'viewModes' => ['cards', 'blocks', 'index'], // Manual
            ],
            'factory' => [$this, 'createField'], // Manual factory method
            'updateFactory' => [$this, 'updateField'], // Update factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a Matrix field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\Matrix();
        
        // Apply Matrix-specific settings exactly as in original implementation
        $field->minEntries = $config['minEntries'] ?? 1;
        $field->maxEntries = $config['maxEntries'] ?? null;
        $field->viewMode = match($config['viewMode'] ?? 'cards') {
            'blocks' => \craft\fields\Matrix::VIEW_MODE_BLOCKS,
            'index' => \craft\fields\Matrix::VIEW_MODE_INDEX,
            default => \craft\fields\Matrix::VIEW_MODE_CARDS,
        };
        $field->propagationMethod = \craft\enums\PropagationMethod::All;

        // Create and associate entry types
        if (isset($config['entryTypes']) && is_array($config['entryTypes'])) {
            $entryTypes = $this->createMatrixBlockTypes($config['entryTypes']);
            $field->setEntryTypes($entryTypes);
        }

        return $field;
    }

    /**
     * Update field with new settings
     * MatrixField supports basic property updates but entry type modifications are complex
     */
    public function updateField(FieldInterface $field, array $updates): array
    {
        $modifications = [];
        
        // Handle simple Matrix field properties
        if (isset($updates['minEntries'])) {
            $field->minEntries = $updates['minEntries'];
            $modifications[] = "Updated minEntries to {$updates['minEntries']}";
        }
        
        if (isset($updates['maxEntries'])) {
            $field->maxEntries = $updates['maxEntries'];
            $modifications[] = "Updated maxEntries to {$updates['maxEntries']}";
        }
        
        if (isset($updates['viewMode'])) {
            $field->viewMode = match($updates['viewMode']) {
                'blocks' => \craft\fields\Matrix::VIEW_MODE_BLOCKS,
                'index' => \craft\fields\Matrix::VIEW_MODE_INDEX,
                default => \craft\fields\Matrix::VIEW_MODE_CARDS,
            };
            $modifications[] = "Updated viewMode to {$updates['viewMode']}";
        }
        
        // Entry type modifications are extremely complex and risky
        if (isset($updates['entryTypes'])) {
            $modifications[] = "WARNING: Entry type modifications for Matrix fields are not supported via updates - please recreate the field";
        }
        
        if (isset($updates['addEntryTypes'])) {
            $modifications[] = "WARNING: Adding entry types to existing Matrix fields is not supported via updates - please recreate the field";
        }
        
        if (isset($updates['removeEntryTypes'])) {
            $modifications[] = "WARNING: Removing entry types from Matrix fields is not supported via updates - please recreate the field";
        }
        
        if (isset($updates['modifyEntryTypes'])) {
            $modifications[] = "WARNING: Modifying entry types in Matrix fields is not supported via updates - please recreate the field";
        }
        
        // Handle any other generic properties
        $handledProperties = ['minEntries', 'maxEntries', 'viewMode', 'entryTypes', 'addEntryTypes', 'removeEntryTypes', 'modifyEntryTypes'];
        foreach ($updates as $settingName => $settingValue) {
            if (!in_array($settingName, $handledProperties) && property_exists($field, $settingName)) {
                $field->$settingName = $settingValue;
                $modifications[] = "Updated {$settingName} to " . (is_bool($settingValue) ? ($settingValue ? 'true' : 'false') : $settingValue);
            }
        }
        
        return $modifications;
    }

    /**
     * Get test cases for Matrix field
     * Enhanced from auto-generated base with Matrix-specific scenarios
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Matrix field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Matrix',
                            'handle' => 'testMatrix',
                            'field_type' => 'matrix',
                            'settings' => [
                                'entryTypes' => [
                                    [
                                        'name' => 'Text Block',
                                        'handle' => 'textBlock',
                                        'hasTitleField' => false,
                                        'fields' => [
                                            [
                                                'name' => 'Content',
                                                'handle' => 'content',
                                                'field_type' => 'plain_text',
                                                'multiline' => true
                                            ]
                                        ]
                                    ]
                                ],
                                'minEntries' => 1,
                                'maxEntries' => 10,
                                'viewMode' => 'cards'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Matrix field with multiple entry types',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Content Blocks',
                            'handle' => 'contentBlocks',
                            'field_type' => 'matrix',
                            'settings' => [
                                'entryTypes' => [
                                    [
                                        'name' => 'Text Block',
                                        'handle' => 'textBlock',
                                        'hasTitleField' => false,
                                        'fields' => [
                                            [
                                                'name' => 'Content',
                                                'handle' => 'content',
                                                'field_type' => 'rich_text'
                                            ]
                                        ]
                                    ],
                                    [
                                        'name' => 'Image Block',
                                        'handle' => 'imageBlock',
                                        'hasTitleField' => false,
                                        'fields' => [
                                            [
                                                'name' => 'Image',
                                                'handle' => 'image',
                                                'field_type' => 'image',
                                                'maxRelations' => 1
                                            ],
                                            [
                                                'name' => 'Caption',
                                                'handle' => 'caption',
                                                'field_type' => 'plain_text'
                                            ]
                                        ]
                                    ]
                                ],
                                'viewMode' => 'blocks'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Matrix field with existing entry type reference',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Page Content',
                            'handle' => 'pageContent',
                            'field_type' => 'matrix',
                            'settings' => [
                                'entryTypes' => [
                                    [
                                        'entryTypeHandle' => 'existingBlockType'
                                    ]
                                ],
                                'maxEntries' => 20
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Matrix field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        // Validate entry types
        if (isset($config['entryTypes'])) {
            if (!is_array($config['entryTypes'])) {
                $errors[] = 'entryTypes must be an array';
            } else {
                foreach ($config['entryTypes'] as $index => $entryType) {
                    if (!is_array($entryType)) {
                        $errors[] = "Entry type at index {$index} must be an array";
                        continue;
                    }

                    // Check for either existing entry type reference or new entry type definition
                    if (!isset($entryType['entryTypeHandle']) && !isset($entryType['name'])) {
                        $errors[] = "Entry type at index {$index} must have either 'entryTypeHandle' (for existing) or 'name' (for new)";
                    }

                    // If creating new entry type, validate required fields
                    if (!isset($entryType['entryTypeHandle'])) {
                        if (empty($entryType['handle'])) {
                            $errors[] = "Entry type at index {$index} must have a 'handle'";
                        }

                        // Validate fields if provided
                        if (isset($entryType['fields']) && is_array($entryType['fields'])) {
                            foreach ($entryType['fields'] as $fieldIndex => $field) {
                                if (!is_array($field)) {
                                    $errors[] = "Field at index {$fieldIndex} in entry type at index {$index} must be an array";
                                    continue;
                                }

                                if (empty($field['handle'])) {
                                    $errors[] = "Field at index {$fieldIndex} in entry type at index {$index} must have a 'handle'";
                                }

                                if (!isset($field['field_type']) && !isset($field['name'])) {
                                    $errors[] = "Field at index {$fieldIndex} in entry type at index {$index} must have either 'field_type' or 'name'";
                                }
                            }
                        }
                    }
                }
            }
        }

        // Validate numeric limits
        if (isset($config['minEntries']) && (!is_numeric($config['minEntries']) || $config['minEntries'] < 0)) {
            $errors[] = 'minEntries must be a non-negative number';
        }

        if (isset($config['maxEntries']) && (!is_numeric($config['maxEntries']) || $config['maxEntries'] < 1)) {
            $errors[] = 'maxEntries must be a positive number';
        }

        if (isset($config['minEntries'], $config['maxEntries']) && $config['minEntries'] > $config['maxEntries']) {
            $errors[] = 'minEntries cannot be greater than maxEntries';
        }

        // Validate viewMode
        if (isset($config['viewMode'])) {
            $validModes = ['cards', 'blocks', 'index'];
            if (!in_array($config['viewMode'], $validModes)) {
                $errors[] = "Invalid viewMode: {$config['viewMode']}. Valid modes: " . implode(', ', $validModes);
            }
        }

        return $errors;
    }

    /**
     * Create matrix block types (entry types) from configuration
     * EXACT copy from original FieldService implementation - no modifications
     */
    private function createMatrixBlockTypes(array $blockTypesConfig): array
    {
        $entryTypes = [];
        $fieldsService = \Craft::$app->getFields();
        $entriesService = \Craft::$app->getEntries();

        foreach ($blockTypesConfig as $blockTypeConfig) {
            // Check if this references an existing entry type
            if (isset($blockTypeConfig['entryTypeHandle'])) {
                // Reference existing entry type
                $existingEntryType = $entriesService->getEntryTypeByHandle($blockTypeConfig['entryTypeHandle']);
                if ($existingEntryType) {
                    $entryTypes[] = $existingEntryType;
                    continue;
                } else {
                    throw new Exception("Referenced entry type '{$blockTypeConfig['entryTypeHandle']}' not found");
                }
            }

            // Create new entry type for this block type
            $entryType = new \craft\models\EntryType();
            $entryType->name = $blockTypeConfig['name'];
            $entryType->handle = $blockTypeConfig['handle'];
            $entryType->hasTitleField = $blockTypeConfig['hasTitleField'] ?? false;
            $entryType->titleTranslationMethod = 'site';
            $entryType->titleTranslationKeyFormat = null;

            // Create fields for this block type (only if fields are provided)
            $blockFields = [];
            $fieldLayoutElements = [];

            if (isset($blockTypeConfig['fields']) && is_array($blockTypeConfig['fields'])) {
                foreach ($blockTypeConfig['fields'] as $fieldConfig) {
                    // Handle case where field is just a reference (handle + required) vs full field definition
                    if (!isset($fieldConfig['name']) && isset($fieldConfig['handle'])) {
                        // This is a field reference - look up existing field or generate name
                        $existingField = $fieldsService->getFieldByHandle($fieldConfig['handle']);
                        if ($existingField) {
                            // Use existing field instead of creating new one
                            $fieldLayoutElement = new \craft\fieldlayoutelements\CustomField();
                            $fieldLayoutElement->fieldUid = $existingField->uid;
                            $fieldLayoutElement->required = $fieldConfig['required'] ?? false;
                            $fieldLayoutElements[] = $fieldLayoutElement;
                            continue;
                        } else {
                            // Generate a name based on handle for new field creation
                            $fieldConfig['name'] = ucwords(str_replace(['-', '_'], ' ', $fieldConfig['handle']));
                        }
                    }

                    // For new field creation, make handle unique and adjust name
                    if (isset($fieldConfig['field_type'])) {
                        // This is a full field definition for inline creation
                        $fieldConfig['handle'] = $blockTypeConfig['handle'] . ucfirst($fieldConfig['handle']);
                        $fieldConfig['name'] = $blockTypeConfig['name'] . ' ' . $fieldConfig['name'];

                        // Create the field for this block type using FieldService
                        $fieldService = \Craft::$app->plugins->getPlugin('field-agent')->fieldService;
                        $blockField = $fieldService->createFieldFromConfig($fieldConfig);

                        if ($blockField) {
                            // Save the field
                            if (!$fieldsService->saveField($blockField)) {
                                $errors = $blockField->getErrors();
                                $errorMessage = "Failed to save field '{$blockField->name}' for block type '{$entryType->name}'";
                                if (!empty($errors)) {
                                    $errorMessage .= ": " . implode(', ', array_map(function($err) {
                                        return is_array($err) ? implode(', ', $err) : $err;
                                    }, $errors));
                                }
                                throw new Exception($errorMessage);
                            }

                            $blockFields[] = $blockField;

                            // Track created block field
                            $this->createdBlockFields[] = [
                                'type' => 'block-field',
                                'handle' => $blockField->handle,
                                'name' => $blockField->name,
                                'id' => $blockField->id,
                                'blockType' => $blockTypeConfig['handle']
                            ];

                            // Create field layout element
                            $fieldLayoutElement = new \craft\fieldlayoutelements\CustomField();
                            $fieldLayoutElement->fieldUid = $blockField->uid;
                            $fieldLayoutElement->required = $fieldConfig['required'] ?? false;
                            $fieldLayoutElements[] = $fieldLayoutElement;
                        }
                    }
                }
            }

            // Create field layout for the entry type (only needed for new entry types)
            $fieldLayout = new \craft\models\FieldLayout();
            $fieldLayout->type = \craft\models\EntryType::class;

            // Create field layout using setTabs method
            $fieldLayout->setTabs([
                [
                    'name' => 'Content',
                    'elements' => $fieldLayoutElements,
                ]
            ]);
            $entryType->setFieldLayout($fieldLayout);

            // Save the entry type
            if (!$entriesService->saveEntryType($entryType)) {
                $errors = $entryType->getErrors();
                $errorMessage = "Failed to save entry type '{$entryType->name}' for matrix field";
                if (!empty($errors)) {
                    $errorDetails = [];
                    foreach ($errors as $attribute => $messages) {
                        foreach ($messages as $message) {
                            $errorDetails[] = "$attribute: $message";
                        }
                    }
                    $errorMessage .= ". Errors: " . implode(', ', $errorDetails);
                }
                throw new Exception($errorMessage);
            }

            // Track created block entry type
            $this->createdBlockEntryTypes[] = [
                'type' => 'block-entry-type',
                'handle' => $entryType->handle,
                'name' => $entryType->name,
                'id' => $entryType->id
            ];

            $entryTypes[] = $entryType;
        }

        return $entryTypes;
    }

    /**
     * Get created block fields from last matrix field creation
     */
    public function getCreatedBlockFields(): array
    {
        return $this->createdBlockFields;
    }

    /**
     * Get created block entry types from last matrix field creation
     */
    public function getCreatedBlockEntryTypes(): array
    {
        return $this->createdBlockEntryTypes;
    }

    /**
     * Clear tracked matrix block items
     */
    public function clearBlockTracking(): void
    {
        $this->createdBlockFields = [];
        $this->createdBlockEntryTypes = [];
    }

    /**
     * Add a new entry type to an existing matrix field
     */
    public function addMatrixEntryType(\craft\fields\Matrix $matrixField, array $entryTypeConfig): bool
    {
        $fieldsService = \Craft::$app->getFields();
        $entriesService = \Craft::$app->getEntries();

        // Get existing entry types
        $existingEntryTypes = $matrixField->getEntryTypes();

        // Check if we should reference an existing entry type or create a new one
        if (isset($entryTypeConfig['entryTypeHandle'])) {
            // Reference existing entry type
            $entryType = $entriesService->getEntryTypeByHandle($entryTypeConfig['entryTypeHandle']);
            if (!$entryType) {
                throw new \Exception("Entry type '{$entryTypeConfig['entryTypeHandle']}' not found");
            }

            // Check if this entry type is already associated with the matrix field
            foreach ($existingEntryTypes as $existingType) {
                if ($existingType->handle === $entryType->handle) {
                    throw new \Exception("Entry type '{$entryType->handle}' is already associated with matrix field '{$matrixField->handle}'");
                }
            }

            $newEntryTypes = [$entryType];
        } else {
            // Create new entry type using our createMatrixBlockTypes method
            $newEntryTypes = $this->createMatrixBlockTypes([$entryTypeConfig]);
            if (empty($newEntryTypes)) {
                throw new \Exception("Failed to create entry type '{$entryTypeConfig['name']}'");
            }
        }

        // Combine existing and new entry types
        $allEntryTypes = array_merge($existingEntryTypes, $newEntryTypes);
        $matrixField->setEntryTypes($allEntryTypes);

        // Save the matrix field with updated block types
        return $fieldsService->saveField($matrixField);
    }

    /**
     * Remove an entry type from an existing matrix field
     */
    public function removeMatrixEntryType(\craft\fields\Matrix $matrixField, string $entryTypeHandle): bool
    {
        $fieldsService = \Craft::$app->getFields();
        $entriesService = \Craft::$app->getEntries();

        // Get existing entry types
        $existingEntryTypes = $matrixField->getEntryTypes();

        // Filter out the entry type to remove
        $remainingEntryTypes = array_filter($existingEntryTypes, function ($entryType) use ($entryTypeHandle) {
            return $entryType->handle !== $entryTypeHandle;
        });

        if (count($remainingEntryTypes) === count($existingEntryTypes)) {
            throw new \Exception("Entry type '{$entryTypeHandle}' not found in matrix field");
        }

        // Update matrix field with remaining entry types
        $matrixField->setEntryTypes(array_values($remainingEntryTypes));

        // Save the matrix field
        if (!$fieldsService->saveField($matrixField)) {
            return false;
        }

        // Find and delete the entry type that was removed
        $removedEntryType = null;
        foreach ($existingEntryTypes as $entryType) {
            if ($entryType->handle === $entryTypeHandle) {
                $removedEntryType = $entryType;
                break;
            }
        }

        if ($removedEntryType) {
            // Delete the entry type (this will also clean up its fields)
            $entriesService->deleteEntryType($removedEntryType);
        }

        return true;
    }

    /**
     * Modify an existing matrix entry type (add/remove fields)
     */
    public function modifyMatrixEntryType(\craft\fields\Matrix $matrixField, string $entryTypeHandle, array $modifications): bool
    {
        $fieldsService = \Craft::$app->getFields();
        $entriesService = \Craft::$app->getEntries();

        // Find the entry type for this entry type handle
        $targetEntryType = null;
        foreach ($matrixField->getEntryTypes() as $entryType) {
            if ($entryType->handle === $entryTypeHandle) {
                $targetEntryType = $entryType;
                break;
            }
        }

        if (!$targetEntryType) {
            throw new \Exception("Entry type '{$entryTypeHandle}' not found in matrix field");
        }

        $fieldLayout = $targetEntryType->getFieldLayout();
        $currentFields = $fieldLayout->getCustomFields();
        $layoutElements = [];

        // Add existing fields to layout
        foreach ($currentFields as $field) {
            $element = new \craft\fieldlayoutelements\CustomField();
            $element->fieldUid = $field->uid;
            $layoutElements[] = $element;
        }

        // Add new fields if specified
        if (isset($modifications['addFields'])) {
            foreach ($modifications['addFields'] as $fieldConfig) {
                // Ensure required field properties exist
                if (!isset($fieldConfig['handle'])) {
                    throw new \Exception("Field handle is required for addFields in modifyMatrixEntryType");
                }
                if (!isset($fieldConfig['name'])) {
                    throw new \Exception("Field name is required for addFields in modifyMatrixEntryType");
                }
                
                // Prefix field handle with entry type handle to ensure uniqueness
                $fieldConfig['handle'] = $entryTypeHandle . ucfirst($fieldConfig['handle']);
                $fieldConfig['name'] = $targetEntryType->name . ' ' . $fieldConfig['name'];

                // Create the field using FieldService
                $fieldService = \Craft::$app->plugins->getPlugin('field-agent')->fieldService;
                $newField = $fieldService->createFieldFromConfig($fieldConfig);
                if ($newField && $fieldsService->saveField($newField)) {
                    // Add to layout
                    $element = new \craft\fieldlayoutelements\CustomField();
                    $element->fieldUid = $newField->uid;
                    $element->required = $fieldConfig['required'] ?? false;
                    $layoutElements[] = $element;

                    // Track created field
                    $this->createdBlockFields[] = [
                        'type' => 'block-field',
                        'handle' => $newField->handle,
                        'name' => $newField->name,
                        'id' => $newField->id,
                        'blockType' => $entryTypeHandle
                    ];
                }
            }
        }

        // Remove fields if specified
        if (isset($modifications['removeFields'])) {
            foreach ($modifications['removeFields'] as $fieldHandle) {
                // Remove from layout elements
                $layoutElements = array_filter($layoutElements, function ($element) use ($fieldHandle) {
                    if ($element instanceof \craft\fieldlayoutelements\CustomField) {
                        $field = \Craft::$app->getFields()->getFieldByUid($element->fieldUid);
                        return $field ? $field->handle !== $fieldHandle : true;
                    }
                    return true;
                });
            }
        }

        // Update the field layout
        $newFieldLayout = new \craft\models\FieldLayout();
        $newFieldLayout->type = \craft\models\EntryType::class;
        $newFieldLayout->setTabs([
            [
                'name' => 'Content',
                'elements' => array_values($layoutElements),
            ]
        ]);

        $targetEntryType->setFieldLayout($newFieldLayout);

        // Update name if specified
        if (isset($modifications['name'])) {
            $targetEntryType->name = $modifications['name'];
        }

        // Save the entry type
        return $entriesService->saveEntryType($targetEntryType);
    }
}