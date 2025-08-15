<?php
/**
 * Field Generator plugin for Craft CMS
 *
 * GetSections Discovery Tool
 */

namespace craftcms\fieldagent\services\tools;

use Craft;
use craftcms\fieldagent\services\tools\GetEntryTypeFields;

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
                    // Reuse the GetEntryTypeFields tool to get accurate field information
                    $getEntryTypeFieldsTool = new GetEntryTypeFields();
                    $entryTypeFieldsData = $getEntryTypeFieldsTool->execute([
                        'handle' => $entryType->handle,
                        'includeNative' => false
                    ]);
                    
                    $entryTypeData['fields'] = [];
                    if (isset($entryTypeFieldsData['fields'])) {
                        foreach ($entryTypeFieldsData['fields'] as $field) {
                            $entryTypeData['fields'][] = [
                                'handle' => $field['handle'],
                                'name' => $field['name'] ?? $field['handle'],
                                'type' => $field['typeDisplay'] ?? $field['type'],
                                'required' => $field['required'] ?? false,
                            ];
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