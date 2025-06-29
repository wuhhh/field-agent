<?php
/**
 * Field Generator plugin for Craft CMS
 *
 * CheckHandleAvailability Discovery Tool
 */

namespace craftcms\fieldagent\services\tools;

use Craft;
use craft\helpers\StringHelper;

/**
 * CheckHandleAvailability Tool
 * 
 * Checks if a handle is available for fields, sections, or entry types
 */
class CheckHandleAvailability extends BaseTool
{
    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return 'Check if a handle is available for use with fields, sections, or entry types';
    }

    /**
     * @inheritdoc
     */
    public function getParameters(): array
    {
        return [
            'handle' => [
                'type' => 'string',
                'required' => true,
                'description' => 'The handle to check',
            ],
            'type' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Type of element to check: field, section, or entryType',
                'enum' => ['field', 'section', 'entryType'],
            ],
            'suggest' => [
                'type' => 'boolean',
                'required' => false,
                'description' => 'Suggest alternative handles if unavailable',
                'default' => true,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function execute(array $params = []): array
    {
        $this->validateParameters($params);
        
        $handle = $params['handle'];
        $type = $params['type'];
        $suggest = $params['suggest'] ?? true;
        
        $result = [
            'handle' => $handle,
            'type' => $type,
            'available' => false,
            'conflicts' => [],
        ];
        
        // Validate handle format
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $handle)) {
            $result['error'] = 'Invalid handle format. Handles must start with a letter and contain only letters, numbers, and underscores.';
            if ($suggest) {
                $result['suggestions'] = $this->generateSuggestions($handle, $type);
            }
            return $result;
        }
        
        // Check availability based on type
        switch ($type) {
            case 'field':
                $result = $this->checkFieldHandle($handle, $result);
                break;
            case 'section':
                $result = $this->checkSectionHandle($handle, $result);
                break;
            case 'entryType':
                $result = $this->checkEntryTypeHandle($handle, $result);
                break;
        }
        
        // Generate suggestions if not available and requested
        if (!$result['available'] && $suggest && !isset($result['suggestions'])) {
            $result['suggestions'] = $this->generateSuggestions($handle, $type);
        }
        
        return $result;
    }

    /**
     * Check if a field handle is available
     */
    private function checkFieldHandle(string $handle, array $result): array
    {
        $existingField = Craft::$app->getFields()->getFieldByHandle($handle);
        
        if ($existingField) {
            $result['available'] = false;
            $result['conflicts'][] = [
                'type' => 'field',
                'name' => $existingField->name,
                'id' => $existingField->id,
                'class' => get_class($existingField),
            ];
        } else {
            $result['available'] = true;
        }
        
        // Check reserved words
        if ($this->isReservedWord($handle)) {
            $result['available'] = false;
            $result['reserved'] = true;
            $result['message'] = "'{$handle}' is a reserved word and cannot be used as a field handle";
        }
        
        return $result;
    }

    /**
     * Check if a section handle is available
     */
    private function checkSectionHandle(string $handle, array $result): array
    {
        $existingSection = Craft::$app->getSections()->getSectionByHandle($handle);
        
        if ($existingSection) {
            $result['available'] = false;
            $result['conflicts'][] = [
                'type' => 'section',
                'name' => $existingSection->name,
                'id' => $existingSection->id,
            ];
        } else {
            $result['available'] = true;
        }
        
        return $result;
    }

    /**
     * Check if an entry type handle is available
     */
    private function checkEntryTypeHandle(string $handle, array $result): array
    {
        $existingEntryType = Craft::$app->getEntries()->getEntryTypeByHandle($handle);
        
        if ($existingEntryType) {
            $result['available'] = false;
            $result['conflicts'][] = [
                'type' => 'entryType',
                'name' => $existingEntryType->name,
                'id' => $existingEntryType->id,
                'sectionId' => $existingEntryType->sectionId,
            ];
        } else {
            $result['available'] = true;
        }
        
        return $result;
    }

    /**
     * Generate handle suggestions
     */
    private function generateSuggestions(string $baseHandle, string $type): array
    {
        $suggestions = [];
        
        // Clean the handle first
        $cleanHandle = StringHelper::toCamelCase($baseHandle);
        
        // Try common variations
        $variations = [
            $cleanHandle,
            $cleanHandle . '2',
            $cleanHandle . 'Field',
            $cleanHandle . 'New',
            'new' . ucfirst($cleanHandle),
            $cleanHandle . date('Y'),
        ];
        
        foreach ($variations as $variation) {
            // Check if this variation is available
            $checkResult = $this->execute([
                'handle' => $variation,
                'type' => $type,
                'suggest' => false,
            ]);
            
            if ($checkResult['available']) {
                $suggestions[] = $variation;
                if (count($suggestions) >= 3) {
                    break;
                }
            }
        }
        
        // If still no suggestions, add numbered suffixes
        if (empty($suggestions)) {
            for ($i = 1; $i <= 5; $i++) {
                $numberedHandle = $cleanHandle . $i;
                $checkResult = $this->execute([
                    'handle' => $numberedHandle,
                    'type' => $type,
                    'suggest' => false,
                ]);
                
                if ($checkResult['available']) {
                    $suggestions[] = $numberedHandle;
                    if (count($suggestions) >= 3) {
                        break;
                    }
                }
            }
        }
        
        return $suggestions;
    }

    /**
     * Check if a handle is a reserved word
     */
    private function isReservedWord(string $handle): bool
    {
        $reserved = [
            'id', 'uid', 'title', 'slug', 'uri', 'url',
            'enabled', 'archived', 'siteId', 'sectionId',
            'typeId', 'authorId', 'postDate', 'expiryDate',
            'dateCreated', 'dateUpdated', 'root', 'lft',
            'rgt', 'level', 'searchScore', 'trashed',
            'awaitingFieldValues', 'propagating', 'propagateAll',
            'newSiteIds', 'resaving', 'duplicateOf',
            'previewing', 'hardDelete', 'ref', 'status',
            'structureId', 'fieldLayoutId',
        ];
        
        return in_array(strtolower($handle), $reserved);
    }
}