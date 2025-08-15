<?php
/**
 * Field Generator plugin for Craft CMS
 *
 * Discovery Service for field context operations
 */

namespace craftcms\fieldagent\services;

use Craft;
use craft\base\Component;
use craftcms\fieldagent\services\tools\GetFields;
use craftcms\fieldagent\services\tools\GetSections;
use craftcms\fieldagent\services\tools\GetEntryTypes;
use craftcms\fieldagent\services\tools\GetEntryTypeFields;
use craftcms\fieldagent\services\tools\CheckHandleAvailability;

/**
 * Discovery Service
 * 
 * Provides context about existing Craft structures
 * to enable intelligent field generation and modification
 */
class DiscoveryService extends Component
{
    /**
     * Available discovery tools
     */
    private array $tools = [];

    /**
     * Initialize the discovery service with available tools
     */
    public function init(): void
    {
        parent::init();
        
        $this->tools = [
            'getFields' => new GetFields(),
            'getSections' => new GetSections(),
            'getEntryTypes' => new GetEntryTypes(),
            'getEntryTypeFields' => new GetEntryTypeFields(),
            'checkHandleAvailability' => new CheckHandleAvailability(),
        ];
    }

    /**
     * Execute a tool by name with given parameters
     * 
     * @param string $toolName
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function executeTool(string $toolName, array $params = []): array
    {
        if (!isset($this->tools[$toolName])) {
            throw new \Exception("Unknown tool: {$toolName}");
        }

        return $this->tools[$toolName]->execute($params);
    }

    /**
     * Get all available tools and their descriptions
     * 
     * @return array
     */
    public function getAvailableTools(): array
    {
        $toolList = [];
        
        foreach ($this->tools as $name => $tool) {
            $toolList[$name] = [
                'name' => $name,
                'description' => $tool->getDescription(),
                'parameters' => $tool->getParameters(),
            ];
        }

        return $toolList;
    }

    /**
     * Get current project context for LLM
     * 
     * @return array
     */
    public function getProjectContext(): array
    {
        $sectionsData = $this->executeTool('getSections', ['includeFields' => false]);
        $entryTypesData = $this->executeTool('getEntryTypes', ['includeFields' => false]);
        $entryTypeFieldMappings = [];
        
        // Build mappings for ALL entry types, not just those in sections
        foreach ($entryTypesData['entryTypes'] as $entryType) {
            $entryTypeFields = $this->executeTool('getEntryTypeFields', [
                'handle' => $entryType['handle'],
                'includeNative' => false
            ]);
            
            if (!isset($entryTypeFields['error'])) {
                // Find which section this entry type belongs to (if any)
                $associatedSection = null;
                if (!empty($sectionsData['sections'])) {
                    foreach ($sectionsData['sections'] as $section) {
                        if (!empty($section['entryTypes'])) {
                            foreach ($section['entryTypes'] as $sectionEntryType) {
                                if ($sectionEntryType['handle'] === $entryType['handle']) {
                                    $associatedSection = [
                                        'handle' => $section['handle'] ?? 'unknown',
                                        'name' => $section['name'] ?? 'Unknown Section',
                                        'type' => $section['type'] ?? 'channel',
                                    ];
                                    break 2;
                                }
                            }
                        }
                    }
                }
                
                $entryTypeFieldMappings[] = [
                    'entryType' => [
                        'handle' => $entryType['handle'],
                        'name' => $entryType['name'],
                        'icon' => $entryType['icon'],
                        'color' => $entryType['color'],
                        'description' => $entryType['description'],
                    ],
                    'section' => $associatedSection, // null if not associated with a section
                    'fields' => array_map(function($field) {
                        return [
                            'handle' => $field['handle'],
                            'name' => $field['name'],
                            'type' => $field['type'],
                            'required' => $field['required'] ?? false,
                        ];
                    }, $entryTypeFields['fields']),
                    'fieldCount' => $entryTypeFields['fieldCount'],
                ];
            }
        }
        
        $context = [
            'fields' => $this->executeTool('getFields'),
            'sections' => $sectionsData,
            'entryTypes' => $entryTypesData,
            'entryTypeFieldMappings' => $entryTypeFieldMappings,
            'summary' => $this->generateContextSummary(),
        ];

        return $context;
    }

    /**
     * Generate a human-readable summary of the current project state
     * 
     * @return string
     */
    private function generateContextSummary(): string
    {
        $fields = Craft::$app->getFields()->getAllFields();
        
        // Handle console mode for sections
        if (Craft::$app instanceof \craft\console\Application) {
            $sectionsConfig = Craft::$app->getProjectConfig()->get('sections') ?? [];
            $sections = array_values($sectionsConfig);
        } else {
            $sections = Craft::$app->getSections()->getAllSections();
        }
        
        // Count ALL entry types from project config (not just those in sections)
        if (Craft::$app instanceof \craft\console\Application) {
            $entryTypesConfig = Craft::$app->getProjectConfig()->get('entryTypes') ?? [];
            $allEntryTypes = array_values($entryTypesConfig);
        } else {
            $allEntryTypes = Craft::$app->getEntries()->getAllEntryTypes();
        }

        $summary = sprintf(
            "Current project has %d fields, %d sections, and %d entry types.",
            count($fields),
            count($sections),
            count($allEntryTypes)
        );

        return $summary;
    }
}