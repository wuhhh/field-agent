<?php
/**
 * Field Generator plugin for Craft CMS
 *
 * GetFields Discovery Tool
 */

namespace craftcms\fieldagent\services\tools;

use Craft;

/**
 * GetFields Tool
 * 
 * Returns all fields in the project with their configuration
 */
class GetFields extends BaseTool
{
    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return 'Get all fields in the project with their handles, types, and basic settings';
    }

    /**
     * @inheritdoc
     */
    public function getParameters(): array
    {
        return [
            'groupBy' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Group fields by: type, group, or none (default)',
                'enum' => ['type', 'group', 'none'],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function execute(array $params = []): array
    {
        $this->validateParameters($params);
        
        $fields = Craft::$app->getFields()->getAllFields();
        $groupBy = $params['groupBy'] ?? 'none';
        
        $result = [];
        
        foreach ($fields as $field) {
            $fieldData = [
                'id' => $field->id,
                'name' => $field->name,
                'handle' => $field->handle,
                'type' => get_class($field),
                'typeDisplay' => $field::displayName(),
                'required' => $field->required,
                'searchable' => $field->searchable,
                'translationMethod' => $field->translationMethod,
                'settings' => $this->extractFieldSettings($field),
            ];
            
            if ($groupBy === 'type') {
                $result[$field::displayName()][] = $fieldData;
            } elseif ($groupBy === 'group' && $field->groupId) {
                $group = Craft::$app->getFields()->getGroupById($field->groupId);
                $groupName = $group ? $group->name : 'Ungrouped';
                $result[$groupName][] = $fieldData;
            } else {
                $result[] = $fieldData;
            }
        }
        
        return [
            'fields' => $result,
            'count' => count($fields),
            'groupedBy' => $groupBy,
        ];
    }

    /**
     * Extract relevant settings based on field type
     * 
     * @param \craft\base\Field $field
     * @return array
     */
    private function extractFieldSettings($field): array
    {
        $settings = [];
        
        // Common settings for text fields
        if (method_exists($field, 'multiline') && property_exists($field, 'multiline')) {
            $settings['multiline'] = $field->multiline;
        }
        
        // Asset field settings
        if ($field instanceof \craft\fields\Assets) {
            $settings['allowedKinds'] = $field->allowedKinds;
            $settings['maxRelations'] = $field->maxRelations;
            $settings['viewMode'] = $field->viewMode;
        }
        
        // Dropdown/Select field settings
        if ($field instanceof \craft\fields\Dropdown || 
            $field instanceof \craft\fields\RadioButtons || 
            $field instanceof \craft\fields\Checkboxes) {
            $settings['options'] = array_map(function($option) {
                // Handle both object and array formats
                if (is_object($option)) {
                    return [
                        'label' => $option->label ?? $option->value ?? '',
                        'value' => $option->value ?? '',
                    ];
                } elseif (is_array($option)) {
                    return [
                        'label' => $option['label'] ?? $option['value'] ?? '',
                        'value' => $option['value'] ?? '',
                    ];
                } else {
                    // Handle string options
                    return [
                        'label' => (string)$option,
                        'value' => (string)$option,
                    ];
                }
            }, $field->options);
        }
        
        // Matrix field settings
        if ($field instanceof \craft\fields\Matrix) {
            $settings['entryTypes'] = [];
            foreach ($field->getEntryTypes() as $entryType) {
                $settings['entryTypes'][] = [
                    'name' => $entryType->name,
                    'handle' => $entryType->handle,
                ];
            }
        }
        
        // Number field settings
        if ($field instanceof \craft\fields\Number) {
            $settings['min'] = $field->min;
            $settings['max'] = $field->max;
            $settings['decimals'] = $field->decimals;
        }
        
        return $settings;
    }
}