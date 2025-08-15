<?php
/**
 * Field Generator plugin for Craft CMS
 *
 * GetEntryTypes Discovery Tool
 */

namespace craftcms\fieldagent\services\tools;

use Craft;

/**
 * GetEntryTypes Tool
 * 
 * Returns ALL entry types in the project, regardless of section association
 */
class GetEntryTypes extends BaseTool
{
    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return 'Get all entry types in the project with their basic configuration';
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
        
        // In console mode, use project config to get all entry types
        if (Craft::$app instanceof \craft\console\Application) {
            $entryTypesConfig = Craft::$app->getProjectConfig()->get('entryTypes') ?? [];
            $entryTypes = [];
            
            foreach ($entryTypesConfig as $entryTypeUid => $entryTypeData) {
                $entryType = [
                    'uid' => $entryTypeUid,
                    'id' => null, // We don't have ID in console mode
                    'name' => $entryTypeData['name'] ?? 'Unknown Entry Type',
                    'handle' => $entryTypeData['handle'] ?? 'unknown',
                    'hasTitleField' => $entryTypeData['hasTitleField'] ?? true,
                    'titleFormat' => $entryTypeData['titleFormat'] ?? null,
                    'icon' => $entryTypeData['icon'] ?? null,
                    'color' => $entryTypeData['color'] ?? null,
                    'description' => $entryTypeData['description'] ?? null,
                ];
                
                $entryTypes[] = $entryType;
            }
        } else {
            // In web mode, use the entries service
            $allEntryTypes = Craft::$app->getEntries()->getAllEntryTypes();
            $entryTypes = [];
            
            foreach ($allEntryTypes as $entryType) {
                $entryTypes[] = [
                    'uid' => $entryType->uid,
                    'id' => $entryType->id,
                    'name' => $entryType->name,
                    'handle' => $entryType->handle,
                    'hasTitleField' => $entryType->hasTitleField,
                    'titleFormat' => $entryType->titleFormat,
                    'icon' => $entryType->icon,
                    'color' => $entryType->color,
                    'description' => $entryType->description,
                ];
            }
        }
        
        return [
            'entryTypes' => $entryTypes,
            'count' => count($entryTypes),
        ];
    }
}