<?php

namespace craftcms\fieldagent\services;

use Craft;
use craft\base\Component;
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
        $plugin = Plugin::getInstance();
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
                $plugin->fieldGeneratorService->clearBlockTracking();
                
                $field = $plugin->fieldGeneratorService->createFieldFromConfig($fieldData);
                
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
                        $blockFields = $plugin->fieldGeneratorService->getCreatedBlockFields();
                        $blockEntryTypes = $plugin->fieldGeneratorService->getCreatedBlockEntryTypes();
                        
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
                $entryType = $plugin->fieldGeneratorService->createEntryTypeFromConfig($entryTypeData, $createdFields);
                
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
                $section = $plugin->sectionGeneratorService->createSectionFromConfig($sectionData, $createdEntryTypes);
                
                if ($section) {
                    $result['success'] = true;
                    $result['message'] = "Created section: {$section->name} ({$section->handle})";
                    $result['created'] = ['type' => 'section', 'handle' => $section->handle, 'id' => $section->id];
                } else {
                    throw new Exception('Failed to create section');
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
                    $fieldHandle = $action['fieldHandle'];
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

                default:
                    throw new Exception("Unknown modify action: {$action['action']}");
            }
        }

        $result['success'] = true;
        $result['message'] = "Modified entry type '{$targetId}': " . implode(', ', $modifications);
        $result['modified'] = ['type' => 'entryType', 'handle' => $targetId, 'actions' => $modifications];

        return $result;
    }

    /**
     * Modify a section (placeholder)
     */
    private function modifySection(array $operation, array $result): array
    {
        $targetId = $operation['targetId'];
        $result['success'] = true;
        $result['message'] = "Section modification for '{$targetId}' not implemented yet";
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
            } elseif ($action['action'] === 'addMatrixBlockType' && $field instanceof \craft\fields\Matrix) {
                $blockTypeConfig = $action['blockType'] ?? null;
                if ($blockTypeConfig) {
                    $plugin = Plugin::getInstance();
                    if ($plugin->fieldGeneratorService->addMatrixBlockType($field, $blockTypeConfig)) {
                        $modifications[] = "Added matrix block type '{$blockTypeConfig['name']}'";
                    }
                }
            } elseif ($action['action'] === 'removeMatrixBlockType' && $field instanceof \craft\fields\Matrix) {
                $blockTypeHandle = $action['blockTypeHandle'] ?? null;
                if ($blockTypeHandle) {
                    $plugin = Plugin::getInstance();
                    if ($plugin->fieldGeneratorService->removeMatrixBlockType($field, $blockTypeHandle)) {
                        $modifications[] = "Removed matrix block type '{$blockTypeHandle}'";
                    }
                }
            } elseif ($action['action'] === 'modifyMatrixBlockType' && $field instanceof \craft\fields\Matrix) {
                $blockTypeHandle = $action['blockTypeHandle'] ?? null;
                $blockTypeUpdates = $action['blockTypeUpdates'] ?? null;
                if ($blockTypeHandle && $blockTypeUpdates) {
                    $plugin = Plugin::getInstance();
                    if ($plugin->fieldGeneratorService->modifyMatrixBlockType($field, $blockTypeHandle, $blockTypeUpdates)) {
                        $changeDesc = [];
                        if (isset($blockTypeUpdates['addFields'])) {
                            $changeDesc[] = "added " . count($blockTypeUpdates['addFields']) . " fields";
                        }
                        if (isset($blockTypeUpdates['removeFields'])) {
                            $changeDesc[] = "removed " . count($blockTypeUpdates['removeFields']) . " fields";
                        }
                        if (isset($blockTypeUpdates['name'])) {
                            $changeDesc[] = "updated name to '{$blockTypeUpdates['name']}'";
                        }
                        $modifications[] = "Modified matrix block type '{$blockTypeHandle}': " . implode(', ', $changeDesc);
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
     * Execute delete operation (placeholder)
     */
    private function executeDeleteOperation(array $operation, array $result): array
    {
        $targetId = $operation['targetId'];
        $result['success'] = true;
        $result['message'] = "Delete operation for '{$targetId}' not implemented yet (safety first!)";
        return $result;
    }
}