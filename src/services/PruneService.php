<?php

namespace craftcms\fieldagent\services;

use Craft;
use craft\base\Component;
use craftcms\fieldagent\Plugin;
use craftcms\fieldagent\models\Operation;
use yii\base\Exception;

/**
 * Prune service for cleaning up storage files
 */
class PruneService extends Component
{
    private const CONFIGS_DIR = 'configs';
    private const OPERATIONS_DIR = 'operations';

    /**
     * Prune operations that have been rolled back
     */
    public function pruneRolledBackOperations(): array
    {
        $rollbackService = Plugin::getInstance()->rollbackService;
        $operations = $rollbackService->getOperations();
        
        $results = [
            'deleted' => [],
            'skipped' => [],
            'errors' => []
        ];

        foreach ($operations as $operation) {
            // Check if operation is marked as rolled back
            if ($this->isOperationRolledBack($operation)) {
                try {
                    if ($rollbackService->deleteOperation($operation->id)) {
                        $results['deleted'][] = [
                            'id' => $operation->id,
                            'type' => $operation->type,
                            'timestamp' => date('Y-m-d H:i:s', $operation->timestamp)
                        ];
                    } else {
                        $results['errors'][] = [
                            'id' => $operation->id,
                            'reason' => 'Failed to delete operation file'
                        ];
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'id' => $operation->id,
                        'reason' => $e->getMessage()
                    ];
                }
            } else {
                $results['skipped'][] = [
                    'id' => $operation->id,
                    'type' => $operation->type,
                    'reason' => 'Not rolled back'
                ];
            }
        }

        return $results;
    }

    /**
     * Prune old config files
     */
    public function pruneOldConfigs(int $keepDays = 7): array
    {
        $configsDir = $this->getConfigsDirectoryPath();
        
        if (!is_dir($configsDir)) {
            return ['deleted' => [], 'errors' => []];
        }

        $cutoffTime = time() - ($keepDays * 24 * 60 * 60);
        $results = [
            'deleted' => [],
            'skipped' => [],
            'errors' => []
        ];

        $files = glob($configsDir . DIRECTORY_SEPARATOR . '*.json');

        foreach ($files as $file) {
            $filename = basename($file);
            $fileTime = filemtime($file);

            if ($fileTime === false) {
                $results['errors'][] = [
                    'file' => $filename,
                    'reason' => 'Could not get file modification time'
                ];
                continue;
            }

            if ($fileTime < $cutoffTime) {
                try {
                    if (unlink($file)) {
                        $results['deleted'][] = [
                            'file' => $filename,
                            'age_days' => round((time() - $fileTime) / (24 * 60 * 60), 1)
                        ];
                    } else {
                        $results['errors'][] = [
                            'file' => $filename,
                            'reason' => 'Failed to delete file'
                        ];
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'file' => $filename,
                        'reason' => $e->getMessage()
                    ];
                }
            } else {
                $results['skipped'][] = [
                    'file' => $filename,
                    'age_days' => round((time() - $fileTime) / (24 * 60 * 60), 1),
                    'reason' => 'File is newer than cutoff'
                ];
            }
        }

        return $results;
    }

    /**
     * Nuclear option: delete all configs and operations
     */
    public function pruneAll(): array
    {
        $results = [
            'deleted' => [
                'configs' => [],
                'operations' => []
            ],
            'errors' => []
        ];

        // Delete all configs
        $configsDir = $this->getConfigsDirectoryPath();
        if (is_dir($configsDir)) {
            $configFiles = glob($configsDir . DIRECTORY_SEPARATOR . '*.json');
            foreach ($configFiles as $file) {
                $filename = basename($file);
                try {
                    if (unlink($file)) {
                        $results['deleted']['configs'][] = $filename;
                    } else {
                        $results['errors'][] = [
                            'file' => $filename,
                            'type' => 'config',
                            'reason' => 'Failed to delete file'
                        ];
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'file' => $filename,
                        'type' => 'config',
                        'reason' => $e->getMessage()
                    ];
                }
            }
        }

        // Delete all operations
        $operationsDir = $this->getOperationsDirectoryPath();
        if (is_dir($operationsDir)) {
            $operationFiles = glob($operationsDir . DIRECTORY_SEPARATOR . '*.json');
            foreach ($operationFiles as $file) {
                $filename = basename($file);
                try {
                    if (unlink($file)) {
                        $results['deleted']['operations'][] = $filename;
                    } else {
                        $results['errors'][] = [
                            'file' => $filename,
                            'type' => 'operation',
                            'reason' => 'Failed to delete file'
                        ];
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'file' => $filename,
                        'type' => 'operation',
                        'reason' => $e->getMessage()
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Get storage statistics
     */
    public function getStorageStats(): array
    {
        $configsDir = $this->getConfigsDirectoryPath();
        $operationsDir = $this->getOperationsDirectoryPath();

        $stats = [
            'configs' => [
                'count' => 0,
                'total_size' => 0,
                'oldest' => null,
                'newest' => null
            ],
            'operations' => [
                'count' => 0,
                'total_size' => 0,
                'rolled_back_count' => 0,
                'oldest' => null,
                'newest' => null
            ]
        ];

        // Analyze configs
        if (is_dir($configsDir)) {
            $configFiles = glob($configsDir . DIRECTORY_SEPARATOR . '*.json');
            $stats['configs']['count'] = count($configFiles);

            foreach ($configFiles as $file) {
                $size = filesize($file);
                $time = filemtime($file);
                
                if ($size !== false) {
                    $stats['configs']['total_size'] += $size;
                }
                
                if ($time !== false) {
                    if ($stats['configs']['oldest'] === null || $time < $stats['configs']['oldest']) {
                        $stats['configs']['oldest'] = $time;
                    }
                    if ($stats['configs']['newest'] === null || $time > $stats['configs']['newest']) {
                        $stats['configs']['newest'] = $time;
                    }
                }
            }
        }

        // Analyze operations
        if (is_dir($operationsDir)) {
            $operationFiles = glob($operationsDir . DIRECTORY_SEPARATOR . '*.json');
            $stats['operations']['count'] = count($operationFiles);

            $rollbackService = Plugin::getInstance()->rollbackService;
            
            foreach ($operationFiles as $file) {
                $size = filesize($file);
                $time = filemtime($file);
                
                if ($size !== false) {
                    $stats['operations']['total_size'] += $size;
                }
                
                if ($time !== false) {
                    if ($stats['operations']['oldest'] === null || $time < $stats['operations']['oldest']) {
                        $stats['operations']['oldest'] = $time;
                    }
                    if ($stats['operations']['newest'] === null || $time > $stats['operations']['newest']) {
                        $stats['operations']['newest'] = $time;
                    }
                }

                // Check if rolled back
                $operationId = basename($file, '.json');
                $operation = $rollbackService->getOperation($operationId);
                if ($operation && $this->isOperationRolledBack($operation)) {
                    $stats['operations']['rolled_back_count']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Check if an operation has been rolled back
     */
    private function isOperationRolledBack(Operation $operation): bool
    {
        return $operation->description && strpos($operation->description, '[ROLLED BACK]') !== false;
    }

    /**
     * Get the path to the configs directory
     */
    private function getConfigsDirectoryPath(): string
    {
        $plugin = Plugin::getInstance();
        return $plugin->getStoragePath() . DIRECTORY_SEPARATOR . self::CONFIGS_DIR;
    }

    /**
     * Get the path to the operations directory
     */
    private function getOperationsDirectoryPath(): string
    {
        $plugin = Plugin::getInstance();
        return $plugin->getStoragePath() . DIRECTORY_SEPARATOR . self::OPERATIONS_DIR;
    }
}