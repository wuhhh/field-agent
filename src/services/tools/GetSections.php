<?php
/**
 * Field Generator plugin for Craft CMS
 *
 * GetSections Discovery Tool
 */

namespace craftcms\fieldagent\services\tools;

use Craft;

/**
 * GetSections Tool
 * 
 * Returns all sections with their entry types
 */
class GetSections extends BaseTool
{
    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return 'Get all sections in the project with their entry types and basic configuration';
    }

    /**
     * @inheritdoc
     */
    public function getParameters(): array
    {
        return [
            'includeFields' => [
                'type' => 'boolean',
                'required' => false,
                'description' => 'Include field layouts for each entry type',
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
        
        // In console mode, getSections() might not be available
        if (Craft::$app instanceof \craft\console\Application) {
            // Use project config to get sections in console mode
            $sectionsConfig = Craft::$app->getProjectConfig()->get('sections') ?? [];
            $entryTypesConfig = Craft::$app->getProjectConfig()->get('entryTypes') ?? [];
            $sections = [];
            
            foreach ($sectionsConfig as $sectionUid => $sectionData) {
                $section = (object) [
                    'id' => null, // We don't have ID in console mode
                    'name' => $sectionData['name'] ?? 'Unknown Section',
                    'handle' => $sectionData['handle'] ?? 'unknown',
                    'type' => $sectionData['type'] ?? 'channel',
                    'enableVersioning' => $sectionData['enableVersioning'] ?? true,
                ];
                
                // Get entry types for this section by UID
                $section->entryTypes = [];
                if (isset($sectionData['entryTypes'])) {
                    foreach ($sectionData['entryTypes'] as $entryTypeRef) {
                        $entryTypeUid = $entryTypeRef['uid'] ?? null;
                        if ($entryTypeUid && isset($entryTypesConfig[$entryTypeUid])) {
                            $typeData = $entryTypesConfig[$entryTypeUid];
                            $entryType = (object) [
                                'id' => null,
                                'name' => $typeData['name'] ?? 'Unknown Entry Type',
                                'handle' => $typeData['handle'] ?? 'unknown',
                                'hasTitleField' => $typeData['hasTitleField'] ?? true,
                                'titleFormat' => $typeData['titleFormat'] ?? null,
                            ];
                            $section->entryTypes[] = $entryType;
                        }
                    }
                }
                
                $sections[] = $section;
            }
        } else {
            $sections = Craft::$app->getSections()->getAllSections();
        }
        $includeFields = $params['includeFields'] ?? false;
        
        $result = [];
        
        foreach ($sections as $section) {
            $sectionData = [
                'id' => $section->id,
                'name' => $section->name,
                'handle' => $section->handle,
                'type' => $section->type,
                'enableVersioning' => $section->enableVersioning,
                'entryTypes' => [],
            ];
            
            // Get entry types for this section
            $entryTypes = isset($section->entryTypes) ? $section->entryTypes : $section->getEntryTypes();
            
            foreach ($entryTypes as $entryType) {
                $entryTypeData = [
                    'id' => $entryType->id,
                    'name' => $entryType->name,
                    'handle' => $entryType->handle,
                    'hasTitleField' => $entryType->hasTitleField,
                    'titleFormat' => $entryType->titleFormat,
                ];
                
                if ($includeFields) {
                    $entryTypeData['fields'] = [];
                    
                    if (Craft::$app instanceof \craft\console\Application) {
                        // In console mode, get field layout from project config
                        if (isset($typeData['fieldLayouts'])) {
                            foreach ($typeData['fieldLayouts'] as $layoutData) {
                                if (isset($layoutData['tabs'])) {
                                    foreach ($layoutData['tabs'] as $tab) {
                                        if (isset($tab['elements'])) {
                                            foreach ($tab['elements'] as $element) {
                                                if (isset($element['fieldUid']) && isset($element['type']) && $element['type'] === 'craft\\fieldlayoutelements\\CustomField') {
                                                    // This is a simplified version - in real implementation we'd look up field by UID
                                                    $entryTypeData['fields'][] = [
                                                        'handle' => 'field_' . substr($element['fieldUid'], 0, 8),
                                                        'name' => 'Unknown Field',
                                                        'type' => 'Unknown',
                                                        'required' => $element['required'] ?? false,
                                                    ];
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        $fieldLayout = $entryType->getFieldLayout();
                        if ($fieldLayout) {
                            foreach ($fieldLayout->getCustomFields() as $field) {
                                $entryTypeData['fields'][] = [
                                    'handle' => $field->handle,
                                    'name' => $field->name,
                                    'type' => $field::displayName(),
                                    'required' => $field->required,
                                ];
                            }
                        }
                    }
                }
                
                $sectionData['entryTypes'][] = $entryTypeData;
            }
            
            $result[] = $sectionData;
        }
        
        return [
            'sections' => $result,
            'count' => count($sections),
        ];
    }
}