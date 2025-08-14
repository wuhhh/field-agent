<?php

namespace craftcms\fieldagent\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craftcms\fieldagent\Plugin;
use yii\base\Exception;

/**
 * Operations Executor Service
 *
 * Executes operation arrays from the enhanced LLM system
 */
class OperationsExecutorService extends Component
{
    /**
     * Execute an array of operations
     */
    public function executeOperations(array $operationsData): array
    {
        $results = [];
        $createdFields = []; // Track fields created in this session
        $createdEntryTypes = []; // Track entry types created in this session

        if (!isset($operationsData['operations'])) {
            throw new Exception('No operations found in data');
        }

        foreach ($operationsData['operations'] as $i => $operation) {
            $result = [
                'index' => $i,
                'operation' => $operation,
                'success' => false,
                'message' => '',
            ];

            try {
                switch ($operation['type']) {
                    case 'create':
                        $result = $this->executeCreateOperation($operation, $result, $createdFields, $createdEntryTypes);
                        // Track created items for future operations
                        if ($result['success'] && isset($result['created'])) {
                            switch ($result['created']['type']) {
                                case 'field':
                                    $createdFields[$result['created']['handle']] = $result['created'];
                                    // Force refresh field cache so field is immediately available
                                    Craft::$app->getFields()->refreshFields();
                                    break;
                                case 'entryType':
                                    $createdEntryTypes[$result['created']['handle']] = $result['created'];
                                    break;
                                case 'categoryGroup':
                                    // Category groups don't need to be tracked for dependencies in this system
                                    break;
                                case 'tagGroup':
                                    // Tag groups don't need to be tracked for dependencies in this system
                                    break;
                            }
                        }
                        break;
                    case 'modify':
                        $result = $this->executeModifyOperation($operation, $result, $createdFields);
                        break;
                    case 'delete':
                        $result = $this->executeDeleteOperation($operation, $result);
                        break;
                    default:
                        throw new Exception("Unknown operation type: {$operation['type']}");
                }
            } catch (\Exception $e) {
                $result['success'] = false;
                $result['message'] = $e->getMessage();
                $result['error_details'] = [
                    'exception_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ];
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Execute create operation
     */
    private function executeCreateOperation(array $operation, array $result, array $createdFields = [], array $createdEntryTypes = []): array
    {
        $plugin = Plugin::getInstance();

        switch ($operation['target']) {
            case 'field':
                if (!isset($operation['create']['field'])) {
                    throw new Exception('Field data missing for create operation');
                }

                $fieldData = $operation['create']['field'];

                // Clear any previous matrix block tracking
                $plugin->fieldService->clearBlockTracking();

                $field = $plugin->fieldService->createFieldFromConfig($fieldData);

                if ($field) {
                    // Save the field to the database
                    if (!Craft::$app->getFields()->saveField($field)) {
                        $errors = $field->getErrors();
                        $errorMessages = [];
                        foreach ($errors as $attribute => $messages) {
                            foreach ($messages as $message) {
                                $errorMessages[] = "$attribute: $message";
                            }
                        }
                        throw new Exception("Field validation failed: " . implode(', ', $errorMessages));
                    }

                    $result['success'] = true;
                    $result['message'] = "Created field: {$field->name} ({$field->handle})";
                    $result['created'] = ['type' => 'field', 'handle' => $field->handle, 'id' => $field->id];


                    // Check if this was a matrix field and capture block information
                    if ($field instanceof \craft\fields\Matrix) {
                        $blockFields = $plugin->fieldService->getCreatedBlockFields();
                        $blockEntryTypes = $plugin->fieldService->getCreatedBlockEntryTypes();

                        if (!empty($blockFields) || !empty($blockEntryTypes)) {
                            $result['matrix_blocks'] = [
                                'fields' => $blockFields,
                                'entry_types' => $blockEntryTypes
                            ];

                            $blockCount = count($blockEntryTypes);
                            $fieldCount = count($blockFields);
                            $result['message'] .= " (with $blockCount block types and $fieldCount block fields)";
                        }
                    }
                } else {
                    throw new Exception('Failed to create field');
                }
                break;

            case 'entryType':
                if (!isset($operation['create']['entryType'])) {
                    throw new Exception('Entry type data missing for create operation');
                }

                $entryTypeData = $operation['create']['entryType'];
                $entryType = $plugin->entryTypeService->createEntryTypeFromConfig($entryTypeData, $createdFields);

                if ($entryType) {
                    $result['success'] = true;
                    $result['message'] = "Created entry type: {$entryType->name} ({$entryType->handle})";
                    $result['created'] = ['type' => 'entryType', 'handle' => $entryType->handle, 'id' => $entryType->id];
                } else {
                    throw new Exception('Failed to create entry type');
                }
                break;

            case 'section':
                if (!isset($operation['create']['section'])) {
                    throw new Exception('Section data missing for create operation');
                }

                $sectionData = $operation['create']['section'];
                $section = $plugin->sectionService->createSectionFromConfig($sectionData, $createdEntryTypes);

                if ($section) {
                    $result['success'] = true;
                    $result['message'] = "Created section: {$section->name} ({$section->handle})";
                    $result['created'] = ['type' => 'section', 'handle' => $section->handle, 'id' => $section->id];
                } else {
                    throw new Exception('Failed to create section');
                }
                break;

            case 'categoryGroup':
                if (!isset($operation['create']['categoryGroup'])) {
                    throw new Exception('Category group data missing for create operation');
                }

                $categoryGroupData = $operation['create']['categoryGroup'];
                $categoryGroup = $this->createCategoryGroup($categoryGroupData);

                if ($categoryGroup) {
                    $result['success'] = true;
                    $result['message'] = "Created category group: {$categoryGroup->name} ({$categoryGroup->handle})";
                    $result['created'] = ['type' => 'categoryGroup', 'handle' => $categoryGroup->handle, 'id' => $categoryGroup->id];

                    // Category group created successfully and should be immediately available
                } else {
                    throw new Exception('Failed to create category group - check logs for details');
                }
                break;

            case 'tagGroup':
                if (!isset($operation['create']['tagGroup'])) {
                    throw new Exception('Tag group data missing for create operation');
                }

                $tagGroupData = $operation['create']['tagGroup'];
                $tagGroup = $this->createTagGroup($tagGroupData);

                if ($tagGroup) {
                    $result['success'] = true;
                    $result['message'] = "Created tag group: {$tagGroup->name} ({$tagGroup->handle})";
                    $result['created'] = ['type' => 'tagGroup', 'handle' => $tagGroup->handle, 'id' => $tagGroup->id];

                    // Tag group created successfully and should be immediately available
                } else {
                    throw new Exception('Failed to create tag group');
                }
                break;

            default:
                throw new Exception("Unknown create target: {$operation['target']}");
        }

        return $result;
    }

    /**
     * Execute modify operation
     */
    private function executeModifyOperation(array $operation, array $result, array $createdFields = []): array
    {
        switch ($operation['target']) {
            case 'entryType':
                $result = $this->modifyEntryType($operation, $result, $createdFields);
                break;
            case 'section':
                $result = $this->modifySection($operation, $result);
                break;
            case 'field':
                $result = $this->modifyField($operation, $result);
                break;
            default:
                throw new Exception("Unknown modify target: {$operation['target']}");
        }

        return $result;
    }

    /**
     * Modify an entry type
     */
    private function modifyEntryType(array $operation, array $result, array $createdFields = []): array
    {
        $targetId = $operation['targetId'];
        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($targetId);

        if (!$entryType) {
            throw new Exception("Entry type '{$targetId}' not found");
        }

        $actions = $operation['modify']['actions'] ?? [];
        $modifications = [];

        foreach ($actions as $action) {
            switch ($action['action']) {
                case 'addField':
                    $fieldHandle = $action['field']['handle'] ?? $action['fieldHandle'];

                    // Check if field was created in this session first
                    if (isset($createdFields[$fieldHandle])) {
                        // Field was just created, try to get it with retries
                        $field = null;
                        $maxRetries = 3;

                        for ($retry = 0; $retry < $maxRetries; $retry++) {
                            Craft::$app->getFields()->refreshFields();
                            $field = Craft::$app->getFields()->getFieldByHandle($fieldHandle);

                            if ($field) {
                                break;
                            }

                            // Wait a bit before retry
                            if ($retry < $maxRetries - 1) {
                                usleep(500000); // 0.5 seconds
                            }
                        }
                    } else {
                        $field = Craft::$app->getFields()->getFieldByHandle($fieldHandle);
                    }

                    if (!$field) {
                        // If field still not found but was created in this session, try using the field object directly
                        if (isset($createdFields[$fieldHandle])) {
                            throw new Exception("Field '{$fieldHandle}' was created but not yet available in database. This is a timing issue - please retry the operation.");
                        } else {
                            throw new Exception("Field '{$fieldHandle}' not found");
                        }
                    }

                    // Add field to entry type's field layout
                    $fieldLayout = $entryType->getFieldLayout();
                    if ($fieldLayout) {
                        $tabs = $fieldLayout->getTabs();
                        $contentTab = $tabs[0] ?? null; // Use first tab

                        if ($contentTab) {
                            $elements = $contentTab->getElements();
                            $customField = new \craft\fieldlayoutelements\CustomField();
                            $customField->fieldUid = $field->uid;
                            $customField->required = $action['field']['required'] ?? false;
                            $elements[] = $customField;
                            $contentTab->setElements($elements);

                            if (Craft::$app->getEntries()->saveEntryType($entryType)) {
                                $modifications[] = "Added field '{$fieldHandle}' to entry type";
                            } else {
                                throw new Exception("Failed to save entry type after adding field '{$fieldHandle}'");
                            }
                        }
                    }
                    break;

                case 'removeField':
                    // Support both patterns: fieldHandle (legacy) and field.handle (consistent)
                    $fieldHandle = $action['fieldHandle'] ?? $action['field']['handle'] ?? null;

                    if (!$fieldHandle) {
                        throw new Exception("Field handle is required for removeField action (use 'field': {'handle': 'fieldName'} or 'fieldHandle': 'fieldName')");
                    }
                    $field = Craft::$app->getFields()->getFieldByHandle($fieldHandle);
                    if ($field) {
                        // Get current field layout
                        $fieldLayout = $entryType->getFieldLayout();
                        $elements = [];

                        // Rebuild layout excluding the target field
                        foreach ($fieldLayout->getTabs() as $tab) {
                            foreach ($tab->getElements() as $element) {
                                if ($element instanceof \craft\fieldlayoutelements\CustomField) {
                                    $elementField = Craft::$app->getFields()->getFieldByUid($element->fieldUid);
                                    if ($elementField && $elementField->handle !== $fieldHandle) {
                                        $elements[] = $element;
                                    }
                                } else {
                                    $elements[] = $element;
                                }
                            }
                        }

                        // Update field layout
                        $newFieldLayout = new \craft\models\FieldLayout();
                        $newFieldLayout->type = \craft\models\EntryType::class;
                        $newFieldLayout->setTabs([
                            [
                                'name' => 'Content',
                                'elements' => $elements,
                            ]
                        ]);
                        $entryType->setFieldLayout($newFieldLayout);

                        $modifications[] = "Removed field '{$fieldHandle}' from entry type";
                    } else {
                        $modifications[] = "Field '{$fieldHandle}' not found (may already be removed)";
                    }
                    break;

                case 'updateEntryType':
                    $updates = $action['updates'] ?? [];

                    if (empty($updates)) {
                        throw new Exception("Updates are required for updateEntryType action");
                    }

                    $updateCount = 0;

                    // Update entry type name
                    if (isset($updates['name'])) {
                        $entryType->name = $updates['name'];
                        $updateCount++;
                        $modifications[] = "Updated entry type name to '{$updates['name']}'";
                    }

                    // Update hasTitleField setting
                    if (isset($updates['hasTitleField'])) {
                        $entryType->hasTitleField = (bool)$updates['hasTitleField'];
                        $updateCount++;
                        $titleFieldStatus = $updates['hasTitleField'] ? 'enabled' : 'disabled';

                        // Also update the field layout to show/hide the title field
                        $fieldLayout = $entryType->getFieldLayout();
                        if ($fieldLayout) {
                            $tabs = $fieldLayout->getTabs();
                            $contentTab = $tabs[0] ?? null; // Use first tab

                            if ($contentTab) {
                                $elements = $contentTab->getElements();

                                if ($updates['hasTitleField']) {
                                    // Add title field to layout if not present
                                    $hasTitleElement = false;
                                    foreach ($elements as $element) {
                                        if ($element instanceof EntryTitleField) {
                                            $hasTitleElement = true;
                                            break;
                                        }
                                    }

                                    if (!$hasTitleElement) {
                                        $titleField = new EntryTitleField();
                                        array_unshift($elements, $titleField); // Add at beginning
                                        $contentTab->setElements($elements);
                                        $modifications[] = "Added title field to entry type layout";
                                    }
                                } else {
                                    // Remove title field from layout
                                    $newElements = [];
                                    $titleFieldRemoved = false;

                                    foreach ($elements as $element) {
                                        if (!($element instanceof EntryTitleField)) {
                                            $newElements[] = $element;
                                        } else {
                                            $titleFieldRemoved = true;
                                        }
                                    }

                                    if ($titleFieldRemoved) {
                                        $contentTab->setElements($newElements);
                                        $modifications[] = "Removed title field from entry type layout";
                                    }
                                }
                            }
                        }

                        $modifications[] = "Updated title field setting to {$titleFieldStatus}";
                    }

                    // Update titleTranslationMethod if provided
                    if (isset($updates['titleTranslationMethod'])) {
                        $entryType->titleTranslationMethod = $updates['titleTranslationMethod'];
                        $updateCount++;
                        $modifications[] = "Updated title translation method to '{$updates['titleTranslationMethod']}'";
                    }

                    // Update titleTranslationKeyFormat if provided
                    if (isset($updates['titleTranslationKeyFormat'])) {
                        $entryType->titleTranslationKeyFormat = $updates['titleTranslationKeyFormat'];
                        $updateCount++;
                        $modifications[] = "Updated title translation key format";
                    }

                    if ($updateCount === 0) {
                        $modifications[] = "No valid updates provided for entry type";
                    }
                    break;

                case 'updateField':
                    $fieldHandle = $action['fieldHandle'] ?? $action['field']['handle'] ?? null;
                    $updates = $action['updates'] ?? [];

                    if (!$fieldHandle) {
                        throw new Exception("Field handle is required for updateField action");
                    }

                    if (empty($updates)) {
                        throw new Exception("Updates are required for updateField action");
                    }

                    $field = Craft::$app->getFields()->getFieldByHandle($fieldHandle);
                    if (!$field) {
                        throw new Exception("Field '{$fieldHandle}' not found");
                    }

                    // Update field layout element properties (like required status)
                    $fieldLayout = $entryType->getFieldLayout();
                    $updated = false;

                    if ($fieldLayout) {
                        $tabs = $fieldLayout->getTabs();
                        foreach ($tabs as $tab) {
                            $elements = $tab->getElements();
                            foreach ($elements as $element) {
                                if ($element instanceof \craft\fieldlayoutelements\CustomField) {
                                    $elementField = Craft::$app->getFields()->getFieldByUid($element->fieldUid);
                                    if ($elementField && $elementField->handle === $fieldHandle) {
                                        // Update required status
                                        if (isset($updates['required'])) {
                                            $element->required = (bool)$updates['required'];
                                            $updated = true;
                                            $requiredStatus = $updates['required'] ? 'required' : 'optional';
                                            $modifications[] = "Updated field '{$fieldHandle}' to {$requiredStatus}";
                                        }

                                        // Update instructions if provided
                                        if (isset($updates['instructions'])) {
                                            $element->instructions = $updates['instructions'];
                                            $updated = true;
                                            $modifications[] = "Updated instructions for field '{$fieldHandle}'";
                                        }

                                        // Update width if provided
                                        if (isset($updates['width'])) {
                                            $element->width = (int)$updates['width'];
                                            $updated = true;
                                            $modifications[] = "Updated width for field '{$fieldHandle}' to {$updates['width']}%";
                                        }

                                        break 2; // Exit both loops
                                    }
                                }
                            }
                        }
                    }

                    if (!$updated) {
                        $modifications[] = "No valid updates applied to field '{$fieldHandle}'";
                    }
                    break;

                default:
                    throw new Exception("Unknown modify action: {$action['action']}");
            }
        }

        // Save the entry type to persist all modifications
        if (!empty($modifications)) {
            if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
                $errors = $entryType->getFirstErrors();
                $result['success'] = false;
                $result['message'] = "Failed to save entry type '{$targetId}': " . implode(', ', $errors);
                return $result;
            }
        }

        $result['success'] = true;
        $result['message'] = "Modified entry type '{$targetId}': " . implode(', ', $modifications);
        $result['modified'] = ['type' => 'entryType', 'handle' => $targetId, 'actions' => $modifications];

        return $result;
    }

    /**
     * Modify a section
     */
    private function modifySection(array $operation, array $result): array
    {
        $targetId = $operation['targetId'];
        $section = Craft::$app->getEntries()->getSectionByHandle($targetId);

        if (!$section) {
            $result['success'] = false;
            $result['message'] = "Section with handle '{$targetId}' not found";
            return $result;
        }

        // Handle both direct actions and nested modify structure
        $actions = null;
        if (isset($operation['actions']) && is_array($operation['actions'])) {
            $actions = $operation['actions'];
        } elseif (isset($operation['modify']['actions']) && is_array($operation['modify']['actions'])) {
            $actions = $operation['modify']['actions'];
        }

        if (!$actions) {
            $result['success'] = false;
            $result['message'] = "Modify operation for section requires 'actions' array";
            return $result;
        }

        $modificationResults = [];
        $hasError = false;

        foreach ($actions as $action) {
            $actionType = $action['action'] ?? null;

            switch ($actionType) {
                case 'addEntryType':
                    $actionResult = $this->addEntryTypeToSection($section, $action);
                    break;

                case 'removeEntryType':
                    $actionResult = $this->removeEntryTypeFromSection($section, $action);
                    break;

                case 'updateSettings':
                    $actionResult = $this->updateSectionSettings($section, $action);
                    break;

                default:
                    $actionResult = [
                        'success' => false,
                        'message' => "Unknown section action: {$actionType}"
                    ];
            }

            $modificationResults[] = $actionResult;
            if (!$actionResult['success']) {
                $hasError = true;
            }
        }

        if ($hasError) {
            $result['success'] = false;
            $failedActions = array_filter($modificationResults, fn($r) => !$r['success']);
            $result['message'] = "Section modification failed: " .
                implode('; ', array_column($failedActions, 'message'));
        } else {
            $result['success'] = true;
            $result['message'] = "Section '{$targetId}' modified successfully";
        }

        $result['actionResults'] = $modificationResults;

        return $result;
    }

    /**
     * Modify a field
     */
    private function modifyField(array $operation, array $result): array
    {
        $targetId = $operation['targetId'];
        $field = Craft::$app->getFields()->getFieldByHandle($targetId);

        if (!$field) {
            throw new Exception("Field '{$targetId}' not found");
        }

        $modifications = [];
        $actions = $operation['modify']['actions'] ?? [];

        // Process each action
        foreach ($actions as $action) {
            if ($action['action'] === 'updateField' && isset($action['updates'])) {
                foreach ($action['updates'] as $setting => $value) {
                    // Handle special cases and property mapping
                    if ($setting === 'required') {
                        // 'required' is a property on the field object
                        $field->required = $value;
                        $modifications[] = "Updated required to " . ($value ? 'true' : 'false');
                    } elseif (property_exists($field, $setting)) {
                        $field->$setting = $value;
                        $modifications[] = "Updated {$setting} to " . (is_bool($value) ? ($value ? 'true' : 'false') : $value);
                    } elseif (method_exists($field, 'setSettings')) {
                        // Try updating as a field setting
                        $settings = $field->getSettings();
                        $settings[$setting] = $value;
                        $field->setSettings($settings);
                        $modifications[] = "Updated setting {$setting} to " . (is_bool($value) ? ($value ? 'true' : 'false') : $value);
                    }
                }
            } elseif ($action['action'] === 'addMatrixEntryType' && $field instanceof \craft\fields\Matrix) {
                $entryTypeConfig = $action['matrixEntryType'] ?? null;
                if ($entryTypeConfig) {
                    $plugin = Plugin::getInstance();
                    if ($plugin->fieldService->addMatrixEntryType($field, $entryTypeConfig)) {
                        $modifications[] = "Added matrix entry type '{$entryTypeConfig['name']}'";
                    }
                }
            } elseif ($action['action'] === 'removeMatrixEntryType' && $field instanceof \craft\fields\Matrix) {
                $entryTypeHandle = $action['matrixEntryTypeHandle'] ?? null;
                if ($entryTypeHandle) {
                    $plugin = Plugin::getInstance();
                    if ($plugin->fieldService->removeMatrixEntryType($field, $entryTypeHandle)) {
                        $modifications[] = "Removed matrix entry type '{$entryTypeHandle}'";
                    }
                }
            } elseif ($action['action'] === 'modifyMatrixEntryType' && $field instanceof \craft\fields\Matrix) {
                $entryTypeHandle = $action['matrixEntryTypeHandle'] ?? null;
                $entryTypeUpdates = $action['matrixEntryTypeUpdates'] ?? null;
                if ($entryTypeHandle && $entryTypeUpdates) {
                    $plugin = Plugin::getInstance();
                    if ($plugin->fieldService->modifyMatrixEntryType($field, $entryTypeHandle, $entryTypeUpdates)) {
                        $changeDesc = [];
                        if (isset($entryTypeUpdates['addFields'])) {
                            $changeDesc[] = "added " . count($entryTypeUpdates['addFields']) . " fields";
                        }
                        if (isset($entryTypeUpdates['removeFields'])) {
                            $changeDesc[] = "removed " . count($entryTypeUpdates['removeFields']) . " fields";
                        }
                        if (isset($entryTypeUpdates['name'])) {
                            $changeDesc[] = "updated name to '{$entryTypeUpdates['name']}'";
                        }
                        $modifications[] = "Modified matrix entry type '{$entryTypeHandle}': " . implode(', ', $changeDesc);
                    }
                }
            }
        }

        // Save the field if modifications were made
        if (!empty($modifications)) {
            if (Craft::$app->getFields()->saveField($field)) {
                $result['success'] = true;
                $result['message'] = "Modified field '{$targetId}': " . implode(', ', $modifications);
                $result['modified'] = ['type' => 'field', 'handle' => $targetId, 'changes' => $modifications];
            } else {
                $errors = $field->getErrors();
                $errorMessages = [];
                foreach ($errors as $attribute => $messages) {
                    foreach ($messages as $message) {
                        $errorMessages[] = "$attribute: $message";
                    }
                }
                throw new Exception("Failed to save field '{$targetId}' after modifications: " . implode(', ', $errorMessages));
            }
        } else {
            $result['success'] = true;
            $result['message'] = "No modifications needed for field '{$targetId}'";
        }

        return $result;
    }

    /**
     * Execute delete operation with safety checks
     */
    private function executeDeleteOperation(array $operation, array $result): array
    {
        $targetId = $operation['targetId'];
        $target = $operation['target'];
        $deleteOptions = $operation['delete'] ?? [];
        $force = $deleteOptions['force'] ?? false;
        $cascade = $deleteOptions['cascade'] ?? false;

        switch ($target) {
            case 'field':
                return $this->deleteField($targetId, $force, $cascade, $result);

            case 'entryType':
                return $this->deleteEntryType($targetId, $force, $cascade, $result);

            case 'section':
                return $this->deleteSection($targetId, $force, $cascade, $result);

            default:
                $result['success'] = false;
                $result['message'] = "Unknown delete target: {$target}";
                return $result;
        }
    }

    /**
     * Add an entry type to a section
     */
    private function addEntryTypeToSection($section, array $action): array
    {
        // Handle both direct entryTypeHandle and nested entryType structure
        $entryTypeHandle = $action['entryTypeHandle'] ?? $action['entryType']['handle'] ?? null;

        if (!$entryTypeHandle) {
            return [
                'success' => false,
                'message' => "Entry type handle is required for addEntryType action"
            ];
        }

        // Get the entry type by handle
        $entryType = null;
        $allEntryTypes = Craft::$app->getEntries()->getAllEntryTypes();
        foreach ($allEntryTypes as $et) {
            if ($et->handle === $entryTypeHandle) {
                $entryType = $et;
                break;
            }
        }

        if (!$entryType) {
            return [
                'success' => false,
                'message' => "Entry type with handle '{$entryTypeHandle}' not found"
            ];
        }

        // Get current entry types for the section
        $currentEntryTypes = $section->getEntryTypes();

        // Check if entry type is already in this section
        foreach ($currentEntryTypes as $existing) {
            if ($existing->id === $entryType->id) {
                return [
                    'success' => true,
                    'message' => "Entry type '{$entryTypeHandle}' is already in section '{$section->handle}'"
                ];
            }
        }

        // Add the new entry type
        $currentEntryTypes[] = $entryType;
        $section->setEntryTypes($currentEntryTypes);

        // Save the section
        if (!Craft::$app->getEntries()->saveSection($section)) {
            $errors = $section->getFirstErrors();
            return [
                'success' => false,
                'message' => "Failed to add entry type to section: " . implode(', ', $errors)
            ];
        }

        return [
            'success' => true,
            'message' => "Entry type '{$entryTypeHandle}' added to section '{$section->handle}'"
        ];
    }

    /**
     * Remove an entry type from a section
     */
    private function removeEntryTypeFromSection($section, array $action): array
    {
        // Handle both direct entryTypeHandle and nested entryType structure
        $entryTypeHandle = $action['entryTypeHandle'] ?? $action['entryType']['handle'] ?? null;

        if (!$entryTypeHandle) {
            return [
                'success' => false,
                'message' => "Entry type handle is required for removeEntryType action"
            ];
        }

        // Get current entry types for the section
        $currentEntryTypes = $section->getEntryTypes();
        $filteredEntryTypes = [];
        $found = false;

        foreach ($currentEntryTypes as $entryType) {
            if ($entryType->handle === $entryTypeHandle) {
                $found = true;

                // Check if there are entries using this entry type
                $entryQuery = Entry::find()
                    ->sectionId($section->id)
                    ->typeId($entryType->id)
                    ->limit(1);

                if ($entryQuery->exists()) {
                    return [
                        'success' => false,
                        'message' => "Cannot remove entry type '{$entryTypeHandle}' - entries exist using this type"
                    ];
                }
            } else {
                $filteredEntryTypes[] = $entryType;
            }
        }

        if (!$found) {
            return [
                'success' => false,
                'message' => "Entry type '{$entryTypeHandle}' not found in section '{$section->handle}'"
            ];
        }

        // Ensure we're not removing the last entry type
        if (empty($filteredEntryTypes)) {
            return [
                'success' => false,
                'message' => "Cannot remove the last entry type from section '{$section->handle}'"
            ];
        }

        // Update the section with filtered entry types
        $section->setEntryTypes($filteredEntryTypes);

        // Save the section
        if (!Craft::$app->getEntries()->saveSection($section)) {
            $errors = $section->getFirstErrors();
            return [
                'success' => false,
                'message' => "Failed to remove entry type from section: " . implode(', ', $errors)
            ];
        }

        return [
            'success' => true,
            'message' => "Entry type '{$entryTypeHandle}' removed from section '{$section->handle}'"
        ];
    }

    /**
     * Update section settings
     */
    private function updateSectionSettings($section, array $action): array
    {
        $updates = $action['updates'] ?? [];

        if (empty($updates)) {
            return [
                'success' => false,
                'message' => "No updates provided for updateSettings action"
            ];
        }

        // Apply updates to section
        $updatedFields = [];

        // Basic section properties
        if (isset($updates['name'])) {
            $section->name = $updates['name'];
            $updatedFields[] = 'name';
        }

        if (isset($updates['type'])) {
            if (!in_array($updates['type'], ['single', 'channel', 'structure'])) {
                return [
                    'success' => false,
                    'message' => "Invalid section type: {$updates['type']}"
                ];
            }
            $section->type = $updates['type'];
            $updatedFields[] = 'type';
        }

        if (isset($updates['enableVersioning'])) {
            $section->enableVersioning = (bool)$updates['enableVersioning'];
            $updatedFields[] = 'enableVersioning';
        }

        if (isset($updates['maxAuthors'])) {
            $section->maxAuthors = (int)$updates['maxAuthors'];
            $updatedFields[] = 'maxAuthors';
        }

        if (isset($updates['defaultPlacement'])) {
            $section->defaultPlacement = $updates['defaultPlacement'];
            $updatedFields[] = 'defaultPlacement';
        }

        if (isset($updates['propagationMethod'])) {
            $section->propagationMethod = $updates['propagationMethod'];
            $updatedFields[] = 'propagationMethod';
        }

        // Simple URI update (applies to default site)
        if (isset($updates['uri'])) {
            $siteSettings = $section->getSiteSettings();
            $defaultSite = Craft::$app->getSites()->getPrimarySite();
            if (isset($siteSettings[$defaultSite->id])) {
                $siteSettings[$defaultSite->id]->uriFormat = $updates['uri'];
                $section->setSiteSettings($siteSettings);
                $updatedFields[] = 'uri';
            }
        }

        // Simple template update (applies to default site)
        if (isset($updates['template'])) {
            $siteSettings = $section->getSiteSettings();
            $defaultSite = Craft::$app->getSites()->getPrimarySite();
            if (isset($siteSettings[$defaultSite->id])) {
                $siteSettings[$defaultSite->id]->template = $updates['template'];
                $section->setSiteSettings($siteSettings);
                $updatedFields[] = 'template';
            }
        }

        // Site-specific settings
        if (isset($updates['siteSettings'])) {
            $siteSettings = $section->getSiteSettings();
            foreach ($updates['siteSettings'] as $siteId => $settings) {
                if (isset($siteSettings[$siteId])) {
                    if (isset($settings['enabledByDefault'])) {
                        $siteSettings[$siteId]->enabledByDefault = (bool)$settings['enabledByDefault'];
                    }
                    if (isset($settings['uriFormat'])) {
                        $siteSettings[$siteId]->uriFormat = $settings['uriFormat'];
                    }
                    if (isset($settings['template'])) {
                        $siteSettings[$siteId]->template = $settings['template'];
                    }
                }
            }
            $section->setSiteSettings($siteSettings);
            $updatedFields[] = 'siteSettings';
        }

        if (empty($updatedFields)) {
            return [
                'success' => true,
                'message' => "No valid updates to apply to section '{$section->handle}'"
            ];
        }

        // Save the section
        if (!Craft::$app->getEntries()->saveSection($section)) {
            $errors = $section->getFirstErrors();
            return [
                'success' => false,
                'message' => "Failed to update section settings: " . implode(', ', $errors)
            ];
        }

        return [
            'success' => true,
            'message' => "Section '{$section->handle}' settings updated: " . implode(', ', $updatedFields)
        ];
    }

    /**
     * Delete a field with safety checks
     */
    private function deleteField(string $fieldHandle, bool $force, bool $cascade, array $result): array
    {
        $field = Craft::$app->getFields()->getFieldByHandle($fieldHandle);

        if (!$field) {
            $result['success'] = false;
            $result['message'] = "Field with handle '{$fieldHandle}' not found";
            return $result;
        }

        // Check if field is used in any entry types (unless force is true)
        if (!$force) {
            $entryTypes = Craft::$app->getEntries()->getAllEntryTypes();
            $usedInEntryTypes = [];

            foreach ($entryTypes as $entryType) {
                $fieldLayout = $entryType->getFieldLayout();
                if ($fieldLayout) {
                    foreach ($fieldLayout->getCustomFields() as $layoutField) {
                        if ($layoutField->id === $field->id) {
                            $usedInEntryTypes[] = $entryType->handle;
                            break;
                        }
                    }
                }
            }

            if (!empty($usedInEntryTypes)) {
                $result['success'] = false;
                $result['message'] = "Cannot delete field '{$fieldHandle}' - it is used in entry types: " .
                    implode(', ', $usedInEntryTypes) . ". Use force=true to override.";
                return $result;
            }

            // Check if field has content (entries with data in this field)
            $hasContent = $this->fieldHasContent($field);
            if ($hasContent) {
                $result['success'] = false;
                $result['message'] = "Cannot delete field '{$fieldHandle}' - it contains content. Use force=true to override.";
                return $result;
            }
        }

        // Perform the deletion
        if (!Craft::$app->getFields()->deleteField($field)) {
            $errors = $field->getFirstErrors();
            $result['success'] = false;
            $result['message'] = "Failed to delete field '{$fieldHandle}': " . implode(', ', $errors);
            return $result;
        }

        $result['success'] = true;
        $result['message'] = "Field '{$fieldHandle}' deleted successfully" . ($force ? " (forced)" : "");
        $result['deleted'] = ['type' => 'field', 'handle' => $fieldHandle];

        return $result;
    }

    /**
     * Delete an entry type with safety checks
     */
    private function deleteEntryType(string $entryTypeHandle, bool $force, bool $cascade, array $result): array
    {
        // Find the entry type
        $entryType = null;
        $allEntryTypes = Craft::$app->getEntries()->getAllEntryTypes();
        foreach ($allEntryTypes as $et) {
            if ($et->handle === $entryTypeHandle) {
                $entryType = $et;
                break;
            }
        }

        if (!$entryType) {
            $result['success'] = false;
            $result['message'] = "Entry type with handle '{$entryTypeHandle}' not found";
            return $result;
        }

        // Check if entry type has entries (unless force is true)
        if (!$force) {
            $entryQuery = Entry::find()
                ->typeId($entryType->id)
                ->limit(1);

            if ($entryQuery->exists()) {
                $result['success'] = false;
                $result['message'] = "Cannot delete entry type '{$entryTypeHandle}' - entries exist using this type. Use force=true to override.";
                return $result;
            }
        }

        // Check if this is the last entry type in any section
        if (!$force) {
            $sections = Craft::$app->getEntries()->getAllSections();
            foreach ($sections as $section) {
                $sectionEntryTypes = $section->getEntryTypes();
                if (count($sectionEntryTypes) === 1 && $sectionEntryTypes[0]->id === $entryType->id) {
                    $result['success'] = false;
                    $result['message'] = "Cannot delete entry type '{$entryTypeHandle}' - it is the last entry type in section '{$section->handle}'. Use force=true to override.";
                    return $result;
                }
            }
        }

        // If cascade is true, delete associated entries first
        if ($cascade) {
            $entries = Entry::find()->typeId($entryType->id)->all();
            foreach ($entries as $entry) {
                Craft::$app->getEntries()->deleteEntry($entry);
            }
        }

        // Perform the deletion
        if (!Craft::$app->getEntries()->deleteEntryType($entryType)) {
            $errors = $entryType->getFirstErrors();
            $result['success'] = false;
            $result['message'] = "Failed to delete entry type '{$entryTypeHandle}': " . implode(', ', $errors);
            return $result;
        }

        $result['success'] = true;
        $result['message'] = "Entry type '{$entryTypeHandle}' deleted successfully" .
            ($force ? " (forced)" : "") . ($cascade ? " (with entries)" : "");
        $result['deleted'] = ['type' => 'entryType', 'handle' => $entryTypeHandle];

        return $result;
    }

    /**
     * Delete a section with safety checks
     */
    private function deleteSection(string $sectionHandle, bool $force, bool $cascade, array $result): array
    {
        $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);

        if (!$section) {
            $result['success'] = false;
            $result['message'] = "Section with handle '{$sectionHandle}' not found";
            return $result;
        }

        // Check if section has entries (unless force is true)
        if (!$force) {
            $entryQuery = Entry::find()
                ->sectionId($section->id)
                ->limit(1);

            if ($entryQuery->exists()) {
                $result['success'] = false;
                $result['message'] = "Cannot delete section '{$sectionHandle}' - entries exist in this section. Use force=true to override.";
                return $result;
            }
        }

        // If cascade is true, delete all entries and entry types first
        if ($cascade) {
            // Delete all entries in the section
            $entries = Entry::find()->sectionId($section->id)->all();
            foreach ($entries as $entry) {
                Craft::$app->getEntries()->deleteEntry($entry);
            }

            // Delete all entry types in the section
            $entryTypes = $section->getEntryTypes();
            foreach ($entryTypes as $entryType) {
                Craft::$app->getEntries()->deleteEntryType($entryType);
            }
        }

        // Perform the deletion
        if (!Craft::$app->getEntries()->deleteSection($section)) {
            $errors = $section->getFirstErrors();
            $result['success'] = false;
            $result['message'] = "Failed to delete section '{$sectionHandle}': " . implode(', ', $errors);
            return $result;
        }

        $result['success'] = true;
        $result['message'] = "Section '{$sectionHandle}' deleted successfully" .
            ($force ? " (forced)" : "") . ($cascade ? " (with all content)" : "");
        $result['deleted'] = ['type' => 'section', 'handle' => $sectionHandle];

        return $result;
    }

    /**
     * Check if a field has content in any entries
     */
    private function fieldHasContent($field): bool
    {
        try {
            // Get the field's column name
            $columnName = $field->columnSuffix ? "field_{$field->handle}_{$field->columnSuffix}" : "field_{$field->handle}";

            // Check content table for any non-empty values
            $query = (new \yii\db\Query())
                ->select($columnName)
                ->from('{{%content}}')
                ->where(['not', [$columnName => null]])
                ->andWhere(['not', [$columnName => '']])
                ->limit(1);

            return $query->exists();
        } catch (\Exception $e) {
            // If we can't check content, err on the side of caution
            return true;
        }
    }

    /**
     * Create a category group from configuration
     */
    private function createCategoryGroup(array $config): ?\craft\models\CategoryGroup
    {
        $categoryGroup = new \craft\models\CategoryGroup();
        $categoryGroup->name = $config['name'];
        $categoryGroup->handle = $config['handle'];

        // Set optional properties with defaults
        $hasUrls = $config['hasUrls'] ?? false;
        $uriFormat = $config['uri'] ?? null;
        $template = $config['template'] ?? null;
        $categoryGroup->maxLevels = $config['maxLevels'] ?? null;

        // Create site settings for all sites (required for category groups)
        $siteSettings = [];
        foreach (\Craft::$app->getSites()->getAllSites() as $site) {
            $siteSettings[] = new \craft\models\CategoryGroup_SiteSettings([
                'siteId' => $site->id,
                'hasUrls' => $hasUrls,
                'uriFormat' => $uriFormat,
                'template' => $template,
            ]);
        }
        $categoryGroup->setSiteSettings($siteSettings);

        // Create default field layout for categories
        $fieldLayout = new \craft\models\FieldLayout();
        $fieldLayout->type = \craft\elements\Category::class;
        $categoryGroup->setFieldLayout($fieldLayout);

        // Save the category group
        try {
            if (\Craft::$app->getCategories()->saveGroup($categoryGroup)) {
                return $categoryGroup;
            } else {
                // Log validation errors with more detail
                $errors = $categoryGroup->getErrors();
                $errorMessage = "Category group validation failed for '{$config['name']}': " . json_encode($errors);
                \Craft::error($errorMessage, __METHOD__);
                echo "❌ $errorMessage\n";  // Also output to console for debugging
                return null;
            }
        } catch (\Exception $e) {
            $errorMessage = "Exception creating category group '{$config['name']}': " . $e->getMessage();
            \Craft::error($errorMessage, __METHOD__);
            echo "❌ $errorMessage\n";  // Also output to console for debugging
            throw $e;
        }
    }

    /**
     * Create a tag group from configuration
     */
    private function createTagGroup(array $config): ?\craft\models\TagGroup
    {
        $tagGroup = new \craft\models\TagGroup();
        $tagGroup->name = $config['name'];
        $tagGroup->handle = $config['handle'];

        // Tag groups don't require site settings like category groups do

        // Create default field layout for tags
        $fieldLayout = new \craft\models\FieldLayout();
        $fieldLayout->type = \craft\elements\Tag::class;
        $tagGroup->setFieldLayout($fieldLayout);

        // Save the tag group
        try {
            if (\Craft::$app->getTags()->saveTagGroup($tagGroup)) {
                return $tagGroup;
            } else {
                // Log validation errors with more detail
                $errors = $tagGroup->getErrors();
                $errorMessage = "Tag group validation failed for '{$config['name']}': " . json_encode($errors);
                \Craft::error($errorMessage, __METHOD__);
                echo "❌ $errorMessage\n";  // Also output to console for debugging
                return null;
            }
        } catch (\Exception $e) {
            $errorMessage = "Exception creating tag group '{$config['name']}': " . $e->getMessage();
            \Craft::error($errorMessage, __METHOD__);
            echo "❌ $errorMessage\n";  // Also output to console for debugging
            throw $e;
        }
    }
}
