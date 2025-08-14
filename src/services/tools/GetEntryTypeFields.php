<?php
/**
 * Field Generator plugin for Craft CMS
 *
 * GetEntryTypeFields Discovery Tool
 */

namespace craftcms\fieldagent\services\tools;

use Craft;

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
        
        // Get section info using the Sections service
        $section = null;
        $sectionId = null;
        
        // Find the section that contains this entry type
        if (Craft::$app instanceof \craft\console\Application) {
            // In console mode, search through project config
            $sectionsConfig = Craft::$app->getProjectConfig()->get('sections') ?? [];
            foreach ($sectionsConfig as $sectionData) {
                if (isset($sectionData['entryTypes'])) {
                    foreach ($sectionData['entryTypes'] as $etData) {
                        if (isset($etData['handle']) && $etData['handle'] === $entryType->handle) {
                            $sectionId = $sectionData['id'] ?? null;
                            $section = [
                                'id' => $sectionId,
                                'name' => $sectionData['name'] ?? '',
                                'handle' => $sectionData['handle'] ?? '',
                                'type' => $sectionData['type'] ?? '',
                            ];
                            break 2;
                        }
                    }
                }
            }
        } else {
            // In web mode, we can search through sections
            $sections = Craft::$app->getSections()->getAllSections();
            foreach ($sections as $s) {
                $entryTypes = $s->getEntryTypes();
                foreach ($entryTypes as $et) {
                    if ($et->id === $entryType->id) {
                        $section = [
                            'id' => $s->id,
                            'name' => $s->name,
                            'handle' => $s->handle,
                            'type' => $s->type,
                        ];
                        $sectionId = $s->id;
                        break 2;
                    }
                }
            }
        }
        
        $result = [
            'entryType' => [
                'id' => $entryType->id,
                'name' => $entryType->name,
                'handle' => $entryType->handle,
                'sectionId' => $sectionId,
                'hasTitleField' => $entryType->hasTitleField,
                'titleFormat' => $entryType->titleFormat,
            ],
            'fields' => [],
        ];
        
        if ($section) {
            $result['section'] = $section;
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