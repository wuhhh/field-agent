<?php

namespace craftcms\fieldagent\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craftcms\fieldagent\Plugin;
use craftcms\fieldagent\models\Operation;
use yii\base\Exception;

/**
 * Rollback service for managing field operation history and rollbacks
 */
class RollbackService extends Component
{
    private const OPERATIONS_DIR = 'operations';
    private const LEGACY_OPERATIONS_FILE = 'operations.json';

    /**
     * Record a field generation operation
     */
    public function recordOperation(string $type, string $source, array $createdFields, array $failedFields = [], array $createdEntryTypes = [], array $failedEntryTypes = [], array $createdSections = [], array $failedSections = [], array $createdCategoryGroups = [], array $failedCategoryGroups = [], array $createdTagGroups = [], array $failedTagGroups = [], ?string $description = null): string
    {
        $operation = new Operation();
        $operation->id = $this->generateOperationId();
        $operation->type = $type;
        $operation->source = $source;
        $operation->timestamp = time();
        $operation->createdFields = $createdFields;
        $operation->failedFields = $failedFields;
        $operation->createdEntryTypes = $createdEntryTypes;
        $operation->failedEntryTypes = $failedEntryTypes;
        $operation->createdSections = $createdSections;
        $operation->failedSections = $failedSections;
        $operation->createdCategoryGroups = $createdCategoryGroups;
        $operation->failedCategoryGroups = $failedCategoryGroups;
        $operation->createdTagGroups = $createdTagGroups;
        $operation->failedTagGroups = $failedTagGroups;
        $operation->description = $description;

        $this->saveOperation($operation);

        return $operation->id;
    }

    /**
     * Get all recorded operations
     */
    public function getOperations(): array
    {
        $operationsDir = $this->getOperationsDirectoryPath();

        if (!is_dir($operationsDir)) {
            return [];
        }

        $operations = [];
        $files = glob($operationsDir . DIRECTORY_SEPARATOR . '*.json');

        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $operations[] = Operation::fromArray($data);
            }
        }

        // Sort by timestamp, newest first
        usort($operations, fn($a, $b) => $b->timestamp <=> $a->timestamp);

        return $operations;
    }

    /**
     * Get a specific operation by ID
     */
    public function getOperation(string $id): ?Operation
    {
        $operationFile = $this->getOperationFilePath($id);

        if (!file_exists($operationFile)) {
            return null;
        }

        $data = json_decode(file_get_contents($operationFile), true);
        if (!$data) {
            return null;
        }

        return Operation::fromArray($data);
    }

    /**
     * Rollback a specific operation
     * Deletes in order: sections → entry types → fields
     */
    public function rollbackOperation(string $operationId): array
    {
        $operation = $this->getOperation($operationId);
        if (!$operation) {
            throw new Exception("Operation not found: $operationId");
        }

        $results = [
            'deleted' => [
                'sections' => [],
                'entryTypes' => [],
                'fields' => [],
                'categoryGroups' => [],
                'tagGroups' => []
            ],
            'failed' => [
                'sections' => [],
                'entryTypes' => [],
                'fields' => [],
                'categoryGroups' => [],
                'tagGroups' => []
            ],
            'protected' => [
                'sections' => [],
                'entryTypes' => [],
                'fields' => [],
                'categoryGroups' => [],
                'tagGroups' => []
            ]
        ];

        // Step 1: Delete sections first (they depend on entry types)
        $this->rollbackSections($operation, $results);

        // Step 2: Delete entry types (they depend on fields)
        $this->rollbackEntryTypes($operation, $results);

        // Step 3: Delete fields (they may depend on category/tag groups)
        $this->rollbackFields($operation, $results);

        // Step 4: Delete category groups last (nothing should depend on them after fields are gone)
        $this->rollbackCategoryGroups($operation, $results);

        // Step 5: Delete tag groups last (nothing should depend on them after fields are gone)
        $this->rollbackTagGroups($operation, $results);

        // Mark operation as rolled back if we had some success and no critical failures
        $hasDeleted = !empty($results['deleted']['sections']) || !empty($results['deleted']['entryTypes']) || !empty($results['deleted']['fields']) || !empty($results['deleted']['categoryGroups']) || !empty($results['deleted']['tagGroups']);
        $hasCriticalFailures = !empty($results['failed']['sections']) || !empty($results['failed']['entryTypes']) || !empty($results['failed']['fields']) || !empty($results['failed']['categoryGroups']) || !empty($results['failed']['tagGroups']);

        if ($hasDeleted && !$hasCriticalFailures) {
            $this->markOperationRolledBack($operationId);
            
            // Force project config sync to prevent orphaned fields in CP
            $this->syncProjectConfig();
        }

        return $results;
    }

    /**
     * Rollback sections from an operation
     */
    private function rollbackSections(Operation $operation, array &$results): void
    {
        $entriesService = Craft::$app->getEntries();

        foreach ($operation->createdSections as $sectionData) {
            $section = $entriesService->getSectionByHandle($sectionData['handle']);

            if (!$section) {
                $results['failed']['sections'][] = [
                    'handle' => $sectionData['handle'],
                    'name' => $sectionData['name'] ?? 'Unknown',
                    'reason' => 'Section not found'
                ];
                continue;
            }

            // Check if section has entries
            if ($this->isSectionInUse($section)) {
                $results['protected']['sections'][] = [
                    'handle' => $sectionData['handle'],
                    'name' => $section->name,
                    'reason' => 'Section contains entries'
                ];
                continue;
            }

            // Attempt to delete the section
            try {
                if ($entriesService->deleteSection($section)) {
                    $results['deleted']['sections'][] = [
                        'handle' => $sectionData['handle'],
                        'name' => $section->name
                    ];
                } else {
                    $results['failed']['sections'][] = [
                        'handle' => $sectionData['handle'],
                        'name' => $section->name,
                        'reason' => 'Failed to delete section'
                    ];
                }
            } catch (\Exception $e) {
                $results['failed']['sections'][] = [
                    'handle' => $sectionData['handle'],
                    'name' => $section->name,
                    'reason' => 'Exception: ' . $e->getMessage()
                ];
            }
        }
    }

    /**
     * Rollback entry types from an operation
     */
    private function rollbackEntryTypes(Operation $operation, array &$results): void
    {
        $entriesService = Craft::$app->getEntries();

        foreach ($operation->createdEntryTypes as $entryTypeData) {
            $entryType = $entriesService->getEntryTypeByHandle($entryTypeData['handle']);

            if (!$entryType) {
                $results['failed']['entryTypes'][] = [
                    'handle' => $entryTypeData['handle'],
                    'name' => $entryTypeData['name'] ?? 'Unknown',
                    'reason' => 'Entry type not found'
                ];
                continue;
            }

            // Check if entry type has entries
            if ($this->isEntryTypeInUse($entryType)) {
                $results['protected']['entryTypes'][] = [
                    'handle' => $entryTypeData['handle'],
                    'name' => $entryType->name,
                    'reason' => 'Entry type has entries'
                ];
                continue;
            }

            // Attempt to delete the entry type
            try {
                if ($entriesService->deleteEntryType($entryType)) {
                    $results['deleted']['entryTypes'][] = [
                        'handle' => $entryTypeData['handle'],
                        'name' => $entryType->name
                    ];
                } else {
                    $results['failed']['entryTypes'][] = [
                        'handle' => $entryTypeData['handle'],
                        'name' => $entryType->name,
                        'reason' => 'Failed to delete entry type'
                    ];
                }
            } catch (\Exception $e) {
                $results['failed']['entryTypes'][] = [
                    'handle' => $entryTypeData['handle'],
                    'name' => $entryType->name,
                    'reason' => 'Exception: ' . $e->getMessage()
                ];
            }
        }
    }

    /**
     * Rollback fields from an operation
     */
    private function rollbackFields(Operation $operation, array &$results): void
    {
        $fieldsService = Craft::$app->getFields();

        foreach ($operation->createdFields as $fieldData) {
            $field = $fieldsService->getFieldByHandle($fieldData['handle']);

            if (!$field) {
                $results['failed']['fields'][] = [
                    'handle' => $fieldData['handle'],
                    'name' => $fieldData['name'] ?? 'Unknown',
                    'reason' => 'Field not found'
                ];
                continue;
            }

            // Check if field is in use by any entries
            if ($this->isFieldInUse($field)) {
                $results['protected']['fields'][] = [
                    'handle' => $fieldData['handle'],
                    'name' => $field->name,
                    'reason' => 'Field is in use by entries'
                ];
                continue;
            }

            // Attempt to delete the field
            if ($fieldsService->deleteField($field)) {
                $results['deleted']['fields'][] = [
                    'handle' => $fieldData['handle'],
                    'name' => $field->name
                ];
            } else {
                $results['failed']['fields'][] = [
                    'handle' => $fieldData['handle'],
                    'name' => $field->name,
                    'reason' => 'Failed to delete field'
                ];
            }
        }
    }

    /**
     * Check if a section contains any entries
     */
    private function isSectionInUse($section): bool
    {
        try {
            $entryCount = Entry::find()
                ->sectionId($section->id)
                ->status(null)
                ->count();

            return $entryCount > 0;
        } catch (\Exception $e) {
            // If we can't check safely, assume it's not in use
            Craft::warning("Could not check section usage for {$section->handle}: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Check if an entry type has any entries
     */
    private function isEntryTypeInUse($entryType): bool
    {
        try {
            $entryCount = Entry::find()
                ->typeId($entryType->id)
                ->status(null)
                ->count();

            return $entryCount > 0;
        } catch (\Exception $e) {
            // If we can't check safely, assume it's not in use
            Craft::warning("Could not check entry type usage for {$entryType->handle}: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Check if a field is currently in use by any entries
     */
    private function isFieldInUse($field): bool
    {
        try {
            // Check if the field has any content in the content table
            $contentService = Craft::$app->getContent();
            $contentTable = $contentService->contentTable;

            // Build column name as Craft does (field_<handle>)
            $columnName = 'field_' . $field->handle;

            // Check if the column exists in content table
            $schema = Craft::$app->getDb()->getTableSchema($contentTable);
            if ($schema && isset($schema->columns[$columnName])) {
                // Check if any entries have non-empty content for this field
                $count = Craft::$app->getDb()->createCommand()
                    ->select('COUNT(*)')
                    ->from($contentTable)
                    ->where(['not', [$columnName => null]])
                    ->andWhere(['not', [$columnName => '']])
                    ->queryScalar();

                return $count > 0;
            }

            // If column doesn't exist, field is not in use
            return false;

        } catch (\Exception $e) {
            // If we can't check safely, assume it's not in use for new fields
            // This is safer for testing - in production you might want to be more conservative
            Craft::warning("Could not check field usage for {$field->handle}: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Clean up old operations
     */
    public function cleanupOperations(int $maxOperations = 100): void
    {
        $operations = $this->getOperations();

        if (count($operations) <= $maxOperations) {
            return;
        }

        // Keep only the most recent operations, delete the rest
        $operationsToDelete = array_slice($operations, $maxOperations);

        foreach ($operationsToDelete as $operation) {
            $operationFile = $this->getOperationFilePath($operation->id);
            if (file_exists($operationFile)) {
                unlink($operationFile);
            }
        }
    }

    /**
     * Delete an operation file (useful after successful rollback)
     */
    public function deleteOperation(string $operationId): bool
    {
        $operationFile = $this->getOperationFilePath($operationId);

        if (file_exists($operationFile)) {
            return unlink($operationFile);
        }

        return false;
    }

    /**
     * Generate a unique operation ID
     */
    private function generateOperationId(): string
    {
        return 'op_' . date('Ymd_His') . '_' . substr(uniqid(), -6);
    }

    /**
     * Save an operation to its own file
     */
    private function saveOperation(Operation $operation): void
    {
        $plugin = Plugin::getInstance();
        $plugin->ensureStorageDirectory();

        $operationsDir = $this->getOperationsDirectoryPath();
        if (!is_dir($operationsDir)) {
            if (!mkdir($operationsDir, 0755, true)) {
                throw new Exception("Failed to create operations directory: $operationsDir");
            }
        }

        $operationFile = $this->getOperationFilePath($operation->id);
        $data = json_encode($operation->toArray(), JSON_PRETTY_PRINT);

        if (file_put_contents($operationFile, $data) === false) {
            throw new Exception("Failed to write operation file: $operationFile");
        }
    }


    /**
     * Record a test operation for rollback purposes
     */
    public function recordTestOperation(string $testName, array $testData, array $results): ?string
    {
        try {
            $operation = new Operation();
            $operation->id = $this->generateOperationId();
            $operation->type = 'test';
            $operation->source = "Test: {$testName}";
            $operation->timestamp = time();
            $operation->description = "Test operation for: {$testName}";
            
            // Extract created items from results
            if (is_array($results)) {
                foreach ($results as $result) {
                    if (is_array($result) && isset($result['success']) && $result['success'] && isset($result['created'])) {
                        $created = $result['created'];
                        switch ($created['type'] ?? '') {
                            case 'field':
                                $operation->createdFields[] = [
                                    'id' => $created['id'] ?? null,
                                    'handle' => $created['handle'] ?? '',
                                    'name' => $created['name'] ?? '',
                                    'type' => $created['fieldType'] ?? ''
                                ];
                                
                                // If this is a Matrix field, also capture its block fields and entry types
                                if (isset($result['matrix_blocks'])) {
                                    $matrixBlocks = $result['matrix_blocks'];
                                    
                                    // Add Matrix block fields
                                    if (isset($matrixBlocks['fields']) && is_array($matrixBlocks['fields'])) {
                                        foreach ($matrixBlocks['fields'] as $blockField) {
                                            $operation->createdFields[] = [
                                                'id' => $blockField['id'] ?? null,
                                                'handle' => $blockField['handle'] ?? '',
                                                'name' => $blockField['name'] ?? '',
                                                'type' => $blockField['type'] ?? ''
                                            ];
                                        }
                                    }
                                    
                                    // Add Matrix block entry types
                                    if (isset($matrixBlocks['entry_types']) && is_array($matrixBlocks['entry_types'])) {
                                        foreach ($matrixBlocks['entry_types'] as $blockEntryType) {
                                            $operation->createdEntryTypes[] = [
                                                'id' => $blockEntryType['id'] ?? null,
                                                'handle' => $blockEntryType['handle'] ?? '',
                                                'name' => $blockEntryType['name'] ?? ''
                                            ];
                                        }
                                    }
                                }
                                break;
                            case 'entryType':
                                $operation->createdEntryTypes[] = [
                                    'id' => $created['id'] ?? null,
                                    'handle' => $created['handle'] ?? '',
                                    'name' => $created['name'] ?? ''
                                ];
                                break;
                            case 'section':
                                $operation->createdSections[] = [
                                    'id' => $created['id'] ?? null,
                                    'handle' => $created['handle'] ?? '',
                                    'name' => $created['name'] ?? ''
                                ];
                                break;
                        }
                    }
                }
            }
            
            $this->saveOperation($operation);
            return $operation->id;
        } catch (\Exception $e) {
            // Log error but don't fail the test
            Craft::error("Failed to record test operation: " . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    /**
     * Mark an operation as rolled back
     */
    private function markOperationRolledBack(string $operationId): void
    {
        $operation = $this->getOperation($operationId);
        if (!$operation) {
            return;
        }

        $operation->description = ($operation->description ? $operation->description . ' ' : '') . '[ROLLED BACK]';
        $this->saveOperation($operation);
    }

    /**
     * Get the path to the operations directory
     */
    private function getOperationsDirectoryPath(): string
    {
        $plugin = Plugin::getInstance();
        return $plugin->getStoragePath() . DIRECTORY_SEPARATOR . self::OPERATIONS_DIR;
    }

    /**
     * Get the path to a specific operation file
     */
    private function getOperationFilePath(string $operationId): string
    {
        return $this->getOperationsDirectoryPath() . DIRECTORY_SEPARATOR . $operationId . '.json';
    }

    /**
     * Get the path to the legacy operations file
     */
    private function getLegacyOperationsFilePath(): string
    {
        $plugin = Plugin::getInstance();
        return $plugin->getStoragePath() . DIRECTORY_SEPARATOR . self::LEGACY_OPERATIONS_FILE;
    }

    /**
     * Sync project config to prevent orphaned fields in CP
     */
    private function syncProjectConfig(): void
    {
        try {
            // Force project config to sync with database state
            $projectConfig = Craft::$app->getProjectConfig();
            
            // Rebuild project config from database state
            $projectConfig->rebuild();
            
            Craft::info('Project config synced after rollback to prevent orphaned fields', __METHOD__);
            
        } catch (\Exception $e) {
            // Log but don't fail the rollback operation
            Craft::warning("Failed to sync project config after rollback: " . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * Rollback category groups from an operation
     */
    private function rollbackCategoryGroups(Operation $operation, array &$results): void
    {
        if (empty($operation->createdCategoryGroups)) {
            return;
        }

        $categoriesService = \Craft::$app->getCategories();

        foreach ($operation->createdCategoryGroups as $categoryGroupData) {
            $categoryGroup = $categoriesService->getGroupByHandle($categoryGroupData['handle']);

            if (!$categoryGroup) {
                $results['failed']['categoryGroups'][] = [
                    'handle' => $categoryGroupData['handle'],
                    'name' => $categoryGroupData['name'] ?? 'Unknown',
                    'reason' => 'Category group not found'
                ];
                continue;
            }

            // Check if category group has categories
            if ($this->isCategoryGroupInUse($categoryGroup)) {
                $results['protected']['categoryGroups'][] = [
                    'handle' => $categoryGroupData['handle'],
                    'name' => $categoryGroup->name,
                    'reason' => 'Category group has categories'
                ];
                continue;
            }

            // Attempt to delete the category group
            try {
                if ($categoriesService->deleteGroup($categoryGroup)) {
                    $results['deleted']['categoryGroups'][] = [
                        'handle' => $categoryGroupData['handle'],
                        'name' => $categoryGroup->name
                    ];
                } else {
                    $results['failed']['categoryGroups'][] = [
                        'handle' => $categoryGroupData['handle'],
                        'name' => $categoryGroup->name,
                        'reason' => 'Failed to delete category group'
                    ];
                }
            } catch (\Exception $e) {
                $results['failed']['categoryGroups'][] = [
                    'handle' => $categoryGroupData['handle'],
                    'name' => $categoryGroup->name,
                    'reason' => 'Exception: ' . $e->getMessage()
                ];
            }
        }
    }

    /**
     * Rollback tag groups from an operation
     */
    private function rollbackTagGroups(Operation $operation, array &$results): void
    {
        if (empty($operation->createdTagGroups)) {
            return;
        }

        $tagsService = \Craft::$app->getTags();

        foreach ($operation->createdTagGroups as $tagGroupData) {
            $tagGroup = $tagsService->getTagGroupByHandle($tagGroupData['handle']);

            if (!$tagGroup) {
                $results['failed']['tagGroups'][] = [
                    'handle' => $tagGroupData['handle'],
                    'name' => $tagGroupData['name'] ?? 'Unknown',
                    'reason' => 'Tag group not found'
                ];
                continue;
            }

            // Check if tag group has tags
            if ($this->isTagGroupInUse($tagGroup)) {
                $results['protected']['tagGroups'][] = [
                    'handle' => $tagGroupData['handle'],
                    'name' => $tagGroup->name,
                    'reason' => 'Tag group has tags'
                ];
                continue;
            }

            // Attempt to delete the tag group
            try {
                if ($tagsService->deleteTagGroup($tagGroup)) {
                    $results['deleted']['tagGroups'][] = [
                        'handle' => $tagGroupData['handle'],
                        'name' => $tagGroup->name
                    ];
                } else {
                    $results['failed']['tagGroups'][] = [
                        'handle' => $tagGroupData['handle'],
                        'name' => $tagGroup->name,
                        'reason' => 'Failed to delete tag group'
                    ];
                }
            } catch (\Exception $e) {
                $results['failed']['tagGroups'][] = [
                    'handle' => $tagGroupData['handle'],
                    'name' => $tagGroup->name,
                    'reason' => 'Exception: ' . $e->getMessage()
                ];
            }
        }
    }

    /**
     * Check if a category group is in use
     */
    private function isCategoryGroupInUse(\craft\models\CategoryGroup $categoryGroup): bool
    {
        try {
            $count = \craft\elements\Category::find()
                ->groupId($categoryGroup->id)
                ->count();
            return $count > 0;
        } catch (\Exception $e) {
            // If we can't check, err on the side of caution
            return true;
        }
    }

    /**
     * Check if a tag group is in use
     */
    private function isTagGroupInUse(\craft\models\TagGroup $tagGroup): bool
    {
        try {
            $count = \craft\elements\Tag::find()
                ->groupId($tagGroup->id)
                ->count();
            return $count > 0;
        } catch (\Exception $e) {
            // If we can't check, err on the side of caution
            return true;
        }
    }
}
