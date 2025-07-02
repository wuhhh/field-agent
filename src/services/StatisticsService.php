<?php

namespace craftcms\fieldagent\services;

use Craft;
use craft\base\Component;
use craftcms\fieldagent\Plugin;

/**
 * Statistics Service
 * 
 * Handles statistics and reporting for the Field Agent plugin
 */
class StatisticsService extends Component
{
    /**
     * Get storage statistics for configs and operations
     * 
     * @return array
     */
    public function getStorageStats(): array
    {
        $plugin = Plugin::getInstance();
        return $plugin->pruneService->getStorageStats();
    }

    /**
     * Format bytes to human readable format
     * 
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Get operation statistics
     * 
     * @return array
     */
    public function getOperationStats(): array
    {
        $plugin = Plugin::getInstance();
        $rollbackService = $plugin->rollbackService;
        
        // Get all operations
        $operations = $rollbackService->listOperations();
        
        $stats = [
            'total' => count($operations),
            'rolled_back' => 0,
            'by_type' => [],
            'by_source' => [],
            'by_date' => [],
            'success_rate' => 0
        ];
        
        foreach ($operations as $operation) {
            // Count rolled back
            if ($operation['rolled_back']) {
                $stats['rolled_back']++;
            }
            
            // Group by type
            $type = $operation['type'] ?? 'unknown';
            if (!isset($stats['by_type'][$type])) {
                $stats['by_type'][$type] = 0;
            }
            $stats['by_type'][$type]++;
            
            // Group by source
            $source = $operation['source'] ?? 'manual';
            if (!isset($stats['by_source'][$source])) {
                $stats['by_source'][$source] = 0;
            }
            $stats['by_source'][$source]++;
            
            // Group by date
            $date = date('Y-m-d', $operation['timestamp']);
            if (!isset($stats['by_date'][$date])) {
                $stats['by_date'][$date] = 0;
            }
            $stats['by_date'][$date]++;
        }
        
        // Calculate success rate
        if ($stats['total'] > 0) {
            $stats['success_rate'] = round((($stats['total'] - $stats['rolled_back']) / $stats['total']) * 100, 2);
        }
        
        return $stats;
    }

    /**
     * Get field statistics
     * 
     * @return array
     */
    public function getFieldStats(): array
    {
        $fieldsService = Craft::$app->getFields();
        $fields = $fieldsService->getAllFields();
        
        $stats = [
            'total' => count($fields),
            'by_type' => [],
            'by_group' => [],
            'searchable' => 0,
            'translatable' => 0,
            'recently_created' => []
        ];
        
        foreach ($fields as $field) {
            // Group by type
            $type = get_class($field);
            $typeName = basename(str_replace('\\', '/', $type));
            if (!isset($stats['by_type'][$typeName])) {
                $stats['by_type'][$typeName] = 0;
            }
            $stats['by_type'][$typeName]++;
            
            // Group by field group
            if ($field->groupId) {
                $group = $fieldsService->getGroupById($field->groupId);
                if ($group) {
                    $groupName = $group->name;
                    if (!isset($stats['by_group'][$groupName])) {
                        $stats['by_group'][$groupName] = 0;
                    }
                    $stats['by_group'][$groupName]++;
                }
            }
            
            // Count searchable fields
            if ($field->searchable) {
                $stats['searchable']++;
            }
            
            // Count translatable fields
            if ($field->translationMethod !== 'none') {
                $stats['translatable']++;
            }
            
            // Track recently created fields (last 7 days)
            if ($field->dateCreated && $field->dateCreated->getTimestamp() > strtotime('-7 days')) {
                $stats['recently_created'][] = [
                    'handle' => $field->handle,
                    'name' => $field->name,
                    'type' => $typeName,
                    'date' => $field->dateCreated->format('Y-m-d H:i:s')
                ];
            }
        }
        
        return $stats;
    }

    /**
     * Get section and entry type statistics
     * 
     * @return array
     */
    public function getSectionStats(): array
    {
        $entriesService = Craft::$app->getEntries();
        $sections = $entriesService->getAllSections();
        
        $stats = [
            'total_sections' => count($sections),
            'total_entry_types' => 0,
            'by_type' => [
                'single' => 0,
                'channel' => 0,
                'structure' => 0
            ],
            'entry_types_per_section' => [],
            'fields_per_entry_type' => []
        ];
        
        foreach ($sections as $section) {
            // Count by section type
            $stats['by_type'][$section->type]++;
            
            // Get entry types for this section
            $entryTypes = $section->getEntryTypes();
            $stats['total_entry_types'] += count($entryTypes);
            $stats['entry_types_per_section'][$section->handle] = count($entryTypes);
            
            // Count fields per entry type
            foreach ($entryTypes as $entryType) {
                $fieldLayout = $entryType->getFieldLayout();
                if ($fieldLayout) {
                    $customFields = $fieldLayout->getCustomFields();
                    $stats['fields_per_entry_type'][$entryType->handle] = count($customFields);
                }
            }
        }
        
        return $stats;
    }

    /**
     * Generate a summary report of all statistics
     * 
     * @return array
     */
    public function generateSummaryReport(): array
    {
        return [
            'storage' => $this->getStorageStats(),
            'operations' => $this->getOperationStats(),
            'fields' => $this->getFieldStats(),
            'sections' => $this->getSectionStats(),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get test statistics
     * 
     * @return array
     */
    public function getTestStats(): array
    {
        $plugin = Plugin::getInstance();
        $testingService = $plugin->testingService;
        
        $testSuites = $testingService->discoverTestSuites();
        
        $stats = [
            'total_suites' => count($testSuites),
            'total_tests' => 0,
            'by_category' => []
        ];
        
        foreach ($testSuites as $category => $tests) {
            $stats['by_category'][$category] = count($tests);
            $stats['total_tests'] += count($tests);
        }
        
        return $stats;
    }
}