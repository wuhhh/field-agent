<?php

namespace craftcms\fieldagent\console\controllers;

use Craft;
use craft\console\Controller;
use craftcms\fieldagent\Plugin;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Discovery Controller - Test the discovery service
 */
class DiscoveryController extends Controller
{
    /**
     * Test discovery service by showing current project state
     */
    public function actionTest(): int
    {
        $this->stdout("Testing Discovery Service...\n\n", Console::FG_CYAN);
        
        // Test if we can access basic services
        $this->stdout("App type: " . get_class(Craft::$app) . "\n", Console::FG_GREY);
        
        // Try direct access to services that should work in console
        try {
            Craft::$app->getFields();
            $this->stdout("Fields service: OK\n", Console::FG_GREEN);
        } catch (\Exception $e) {
            $this->stderr("Fields service error: " . $e->getMessage() . "\n", Console::FG_RED);
        }
        
        $plugin = Plugin::getInstance();
        $discovery = $plugin->discoveryService;
        
        try {
            // Test getFields
            $this->stdout("=== FIELDS ===\n", Console::FG_YELLOW);
            $fieldsData = $discovery->executeTool('getFields');
            $this->stdout("Total fields: {$fieldsData['count']}\n", Console::FG_GREEN);
            
            foreach ($fieldsData['fields'] as $field) {
                $this->stdout("  - {$field['handle']} ({$field['typeDisplay']}) - {$field['name']}\n");
            }
            
            // Test getSections
            $this->stdout("\n=== SECTIONS ===\n", Console::FG_YELLOW);
            $sectionsData = $discovery->executeTool('getSections', ['includeFields' => true]);
            $this->stdout("Total sections: {$sectionsData['count']}\n", Console::FG_GREEN);
            
            foreach ($sectionsData['sections'] as $section) {
                $this->stdout("  Section: {$section['name']} ({$section['handle']}) - {$section['type']}\n");
                foreach ($section['entryTypes'] as $entryType) {
                    $this->stdout("    Entry Type: {$entryType['name']} ({$entryType['handle']})\n");
                    if (isset($entryType['fields'])) {
                        foreach ($entryType['fields'] as $field) {
                            $req = $field['required'] ? ' [required]' : '';
                            $this->stdout("      - {$field['handle']} ({$field['type']}){$req}\n");
                        }
                    }
                }
            }
            
            // Test getEntryTypeFields
            $this->stdout("\n=== ENTRY TYPE FIELDS TEST ===\n", Console::FG_YELLOW);
            if ($sectionsData['count'] > 0) {
                // Test with the first entry type we find
                foreach ($sectionsData['sections'] as $section) {
                    if (!empty($section['entryTypes'])) {
                        $firstEntryType = $section['entryTypes'][0];
                        $this->stdout("Testing getEntryTypeFields with entry type: {$firstEntryType['name']} ({$firstEntryType['handle']})\n", Console::FG_CYAN);
                        
                        $entryTypeFields = $discovery->executeTool('getEntryTypeFields', [
                            'handle' => $firstEntryType['handle'],
                            'includeNative' => true
                        ]);
                        
                        if (isset($entryTypeFields['error'])) {
                            $this->stdout("  Error: {$entryTypeFields['error']}\n", Console::FG_RED);
                        } else {
                            $this->stdout("  Entry Type: {$entryTypeFields['entryType']['name']} (handle: {$entryTypeFields['entryType']['handle']})\n");
                            if (isset($entryTypeFields['section'])) {
                                $this->stdout("  Section: {$entryTypeFields['section']['name']} ({$entryTypeFields['section']['type']})\n");
                            }
                            $this->stdout("  Field Count: {$entryTypeFields['fieldCount']}\n", Console::FG_GREEN);
                            
                            if (!empty($entryTypeFields['tabs'])) {
                                foreach ($entryTypeFields['tabs'] as $tab) {
                                    $this->stdout("    Tab: {$tab['name']}\n", Console::FG_YELLOW);
                                    foreach ($tab['fields'] as $field) {
                                        $native = isset($field['native']) ? ' [native]' : '';
                                        $required = isset($field['required']) && $field['required'] ? ' [required]' : '';
                                        $this->stdout("      - {$field['handle']} ({$field['type']}){$native}{$required}\n");
                                    }
                                }
                            } elseif (!empty($entryTypeFields['fields'])) {
                                $this->stdout("    Fields:\n");
                                foreach ($entryTypeFields['fields'] as $field) {
                                    $required = isset($field['required']) && $field['required'] ? ' [required]' : '';
                                    $this->stdout("      - {$field['handle']} ({$field['type']}){$required}\n");
                                }
                            }
                        }
                        break; // Only test the first entry type
                    }
                }
            } else {
                $this->stdout("  No sections/entry types available to test\n", Console::FG_GREY);
            }
            
            // Test checkHandleAvailability
            $this->stdout("\n=== HANDLE AVAILABILITY TEST ===\n", Console::FG_YELLOW);
            $testHandles = ['title', 'content', 'newField', 'blog'];
            
            foreach ($testHandles as $handle) {
                $result = $discovery->executeTool('checkHandleAvailability', [
                    'handle' => $handle,
                    'type' => 'field'
                ]);
                
                $status = $result['available'] ? 'AVAILABLE' : 'TAKEN';
                $color = $result['available'] ? Console::FG_GREEN : Console::FG_RED;
                $this->stdout("  Field handle '{$handle}': ", Console::FG_GREY);
                $this->stdout("{$status}\n", $color);
                
                if (!$result['available'] && isset($result['suggestions'])) {
                    $this->stdout("    Suggestions: " . implode(', ', $result['suggestions']) . "\n", Console::FG_CYAN);
                }
            }
            
            // Show project context summary
            $this->stdout("\n=== PROJECT CONTEXT ===\n", Console::FG_YELLOW);
            $context = $discovery->getProjectContext();
            $this->stdout($context['summary'] . "\n", Console::FG_GREEN);
            
            // Show entry type field mappings
            if (!empty($context['entryTypeFieldMappings'])) {
                $this->stdout("\n=== ENTRY TYPE -> FIELD MAPPINGS ===\n", Console::FG_YELLOW);
                foreach ($context['entryTypeFieldMappings'] as $mapping) {
                    $this->stdout("  {$mapping['section']['name']} > {$mapping['entryType']['name']} ({$mapping['entryType']['handle']})\n", Console::FG_CYAN);
                    $this->stdout("    Fields ({$mapping['fieldCount']}):\n");
                    foreach ($mapping['fields'] as $field) {
                        $required = $field['required'] ? ' [required]' : '';
                        $this->stdout("      - {$field['handle']} ({$field['type']}){$required}\n");
                    }
                }
            } else {
                $this->stdout("\nNo entry type field mappings found.\n", Console::FG_GREY);
            }
            
            $this->stdout("\nDiscovery Service test completed successfully!\n", Console::FG_GREEN);
            
        } catch (\Exception $e) {
            $this->stderr("Error: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        return ExitCode::OK;
    }
    
    /**
     * Show available discovery tools
     */
    public function actionTools(): int
    {
        $this->stdout("Available Discovery Tools:\n\n", Console::FG_CYAN);
        
        $plugin = Plugin::getInstance();
        $tools = $plugin->discoveryService->getAvailableTools();
        
        foreach ($tools as $name => $tool) {
            $this->stdout("  {$name}\n", Console::FG_YELLOW);
            $this->stdout("    {$tool['description']}\n", Console::FG_GREY);
            
            if (!empty($tool['parameters'])) {
                $this->stdout("    Parameters:\n", Console::FG_CYAN);
                foreach ($tool['parameters'] as $param => $config) {
                    $req = $config['required'] ? ' (required)' : ' (optional)';
                    $this->stdout("      - {$param}{$req}: {$config['description']}\n");
                }
            }
            $this->stdout("\n");
        }
        
        return ExitCode::OK;
    }
}