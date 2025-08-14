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
        $entryTypeFieldMappings = [];
        
        // Build entry type -> field mappings
        foreach ($sectionsData['sections'] as $section) {
            foreach ($section['entryTypes'] as $entryType) {
                $entryTypeFields = $this->executeTool('getEntryTypeFields', [
                    'handle' => $entryType['handle'],
                    'includeNative' => false
                ]);
                
                if (!isset($entryTypeFields['error'])) {
                    $entryTypeFieldMappings[] = [
                        'entryType' => [
                            'handle' => $entryType['handle'],
                            'name' => $entryType['name'],
                        ],
                        'section' => [
                            'handle' => $section['handle'],
                            'name' => $section['name'],
                            'type' => $section['type'],
                        ],
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
        }
        
        $context = [
            'fields' => $this->executeTool('getFields'),
            'sections' => $sectionsData,
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
        // Handle console mode
        if (Craft::$app instanceof \craft\console\Application) {
            $sectionsConfig = Craft::$app->getProjectConfig()->get('sections') ?? [];
            $sections = array_values($sectionsConfig);
        } else {
            $sections = Craft::$app->getSections()->getAllSections();
        }
        $allEntryTypes = [];
        if (Craft::$app instanceof \craft\console\Application) {
            // Count entry types from project config
            foreach ($sections as $sectionData) {
                if (isset($sectionData['entryTypes'])) {
                    $allEntryTypes = array_merge($allEntryTypes, array_values($sectionData['entryTypes']));
                }
            }
        } else {
            foreach ($sections as $section) {
                $allEntryTypes = array_merge($allEntryTypes, $section->getEntryTypes());
            }
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