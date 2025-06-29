<?php
/**
 * Field Generator plugin for Craft CMS
 *
 * GetEntryTypeFields Discovery Tool
 */

namespace craftcms\fieldagent\services\tools;

use Craft;
use craft\models\EntryType;

/**
 * GetEntryTypeFields Tool
 * 
 * Returns fields for a specific entry type
 */
class GetEntryTypeFields extends BaseTool
{
    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return 'Get all fields assigned to a specific entry type by handle or ID';
    }

    /**
     * @inheritdoc
     */
    public function getParameters(): array
    {
        return [
            'handle' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Entry type handle (use either handle or id)',
            ],
            'id' => [
                'type' => 'integer',
                'required' => false,
                'description' => 'Entry type ID (use either handle or id)',
            ],
            'includeNative' => [
                'type' => 'boolean',
                'required' => false,
                'description' => 'Include native fields like title and slug',
                'default' => false,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function execute(array $params = []): array
    {
        $this->validateParameters($params);
        
        // Ensure we have either handle or id
        if (!isset($params['handle']) && !isset($params['id'])) {
            throw new \Exception('Either handle or id parameter is required');
        }
        
        // Get the entry type
        $entryType = null;
        if (isset($params['handle'])) {
            $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($params['handle']);
        } elseif (isset($params['id'])) {
            $entryType = Craft::$app->getEntries()->getEntryTypeById($params['id']);
        }
        
        if (!$entryType) {
            return [
                'error' => 'Entry type not found',
                'params' => $params,
            ];
        }
        
        $includeNative = $params['includeNative'] ?? false;
        $result = [
            'entryType' => [
                'id' => $entryType->id,
                'name' => $entryType->name,
                'handle' => $entryType->handle,
                'sectionId' => $entryType->sectionId,
                'hasTitleField' => $entryType->hasTitleField,
                'titleFormat' => $entryType->titleFormat,
            ],
            'fields' => [],
        ];
        
        // Get section info
        $section = $entryType->getSection();
        if ($section) {
            $result['section'] = [
                'id' => $section->id,
                'name' => $section->name,
                'handle' => $section->handle,
                'type' => $section->type,
            ];
        }
        
        // Get field layout
        $fieldLayout = $entryType->getFieldLayout();
        
        if ($fieldLayout) {
            // Get tabs if they exist
            $tabs = $fieldLayout->getTabs();
            
            if ($tabs) {
                $result['tabs'] = [];
                
                foreach ($tabs as $tab) {
                    $tabData = [
                        'name' => $tab->name,
                        'fields' => [],
                    ];
                    
                    foreach ($tab->getElements() as $element) {
                        if ($element instanceof \craft\fieldlayoutelements\CustomField) {
                            $field = $element->getField();
                            if ($field) {
                                $fieldData = [
                                    'id' => $field->id,
                                    'name' => $field->name,
                                    'handle' => $field->handle,
                                    'type' => $field::displayName(),
                                    'typeClass' => get_class($field),
                                    'required' => $element->required,
                                    'width' => $element->width,
                                ];
                                
                                $tabData['fields'][] = $fieldData;
                                $result['fields'][] = $fieldData;
                            }
                        } elseif ($includeNative) {
                            // Include native fields like title
                            if ($element instanceof \craft\fieldlayoutelements\TitleField) {
                                $tabData['fields'][] = [
                                    'name' => 'Title',
                                    'handle' => 'title',
                                    'type' => 'Title',
                                    'required' => $element->required,
                                    'width' => $element->width,
                                    'native' => true,
                                ];
                            }
                        }
                    }
                    
                    $result['tabs'][] = $tabData;
                }
            } else {
                // No tabs, just get fields directly
                foreach ($fieldLayout->getCustomFields() as $field) {
                    $result['fields'][] = [
                        'id' => $field->id,
                        'name' => $field->name,
                        'handle' => $field->handle,
                        'type' => $field::displayName(),
                        'typeClass' => get_class($field),
                        'required' => $field->required,
                    ];
                }
            }
        }
        
        $result['fieldCount'] = count($result['fields']);
        
        return $result;
    }
}