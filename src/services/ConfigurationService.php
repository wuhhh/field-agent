<?php

namespace craftcms\fieldagent\services;

use Craft;
use craft\base\Component;
use craftcms\fieldagent\Plugin;
use yii\base\Exception;

/**
 * Configuration Service
 *
 * Handles configuration management for the Field Agent plugin
 */
class ConfigurationService extends Component
{
    /**
     * Load configuration from file, built-in preset, or stored config
     *
     * @param string $config File path, preset name, or stored config name
     * @return array|null
     */
    public function loadConfig(string $config): ?array
    {
        // Check if it's a file path
        if (file_exists($config)) {
            $configData = json_decode(file_get_contents($config), true);
            if (!$configData) {
                Craft::error("Invalid JSON in config file: $config", __METHOD__);
                return null;
            }
            return $configData;
        }

        // Check if it's a built-in preset
        $presetData = $this->loadBuiltInPreset($config);
        if ($presetData) {
            return $presetData;
        }

        // Check if it's a stored config
        $configData = $this->getStoredConfig($config);
        if ($configData) {
            return $configData;
        }

        Craft::error("Config not found: $config", __METHOD__);
        return null;
    }

    /**
     * List built-in presets (excluding tests)
     *
     * @return array
     */
    public function listBuiltInPresets(): array
    {
        $presets = [];
        $storageDir = Craft::$app->path->getStoragePath() . '/field-agent/presets';

        if (!is_dir($storageDir)) {
            return $presets;
        }

        // Get all JSON files in the presets directory, but exclude the tests subdirectory
        $files = glob($storageDir . '/*.json');
        foreach ($files as $file) {
            $filename = basename($file, '.json');
            $data = json_decode(file_get_contents($file), true);

            if ($data) {
                $presets[] = [
                    'filename' => $filename,
                    'name' => $data['name'] ?? $filename,
                    'description' => $data['description'] ?? 'User preset',
                    'version' => $data['version'] ?? '1.0.0'
                ];
            }
        }

        // Also check for built-in presets in the plugin directory
        $pluginPresetsDir = Plugin::getInstance()->getBasePath() . '/presets';
        if (is_dir($pluginPresetsDir)) {
            $builtInFiles = glob($pluginPresetsDir . '/*.json');
            foreach ($builtInFiles as $file) {
                $filename = basename($file, '.json');
                $data = json_decode(file_get_contents($file), true);

                if ($data) {
                    $presets[] = [
                        'filename' => $filename,
                        'name' => $data['name'] ?? $filename,
                        'description' => $data['description'] ?? 'Built-in preset',
                        'version' => $data['version'] ?? '1.0.0'
                    ];
                }
            }
        }

        return $presets;
    }

    /**
     * Load a built-in preset
     *
     * @param string $presetName
     * @return array|null
     */
    public function loadBuiltInPreset(string $presetName): ?array
    {
        $presetsDir = Plugin::getInstance()->getBasePath() . '/presets';
        $presetFile = $presetsDir . '/' . $presetName . '.json';

        if (!file_exists($presetFile)) {
            return null;
        }

        $data = json_decode(file_get_contents($presetFile), true);
        if (!$data) {
            Craft::error("Invalid JSON in preset file: $presetName", __METHOD__);
            return null;
        }

        return $data;
    }

    /**
     * Store configuration for future use
     *
     * @param string $name Configuration name
     * @param array $configData Configuration data
     * @return bool
     */
    public function storeConfig(string $name, array $configData): string
    {
        $plugin = Plugin::getInstance();
        $plugin->ensureStorageDirectory();

        $configPath = $plugin->getStoragePath() . DIRECTORY_SEPARATOR . 'configs';
        if (!is_dir($configPath)) {
            mkdir($configPath, 0755, true);
        }

        $filename = $this->sanitizeFilename($name) . '_' . time() . '.json';
        $filepath = $configPath . DIRECTORY_SEPARATOR . $filename;

        if (file_put_contents($filepath, json_encode($configData, JSON_PRETTY_PRINT)) === false) {
            throw new Exception("Failed to write config file: $filepath");
        }

        $this->cleanupOldConfigs($configPath);

        return $filepath;
    }

	/**
     * Clean up old configuration files
     */
    private function cleanupOldConfigs(string $configPath): void
    {
        $settings = Plugin::getInstance()->getSettings();
        $maxConfigs = $settings->maxStoredConfigs;

        $files = glob($configPath . DIRECTORY_SEPARATOR . '*.json');
        if (count($files) <= $maxConfigs) {
            return;
        }

        // Sort by modification time
        usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b));

        // Delete oldest files
        $filesToDelete = array_slice($files, 0, count($files) - $maxConfigs);
        foreach ($filesToDelete as $file) {
            unlink($file);
        }
    }

    /**
     * Get all stored configurations
     *
     * @return array
     */
    public function listStoredConfigs(): array
    {
        $plugin = Plugin::getInstance();
        $configPath = $plugin->getStoragePath() . DIRECTORY_SEPARATOR . 'configs';

        if (!is_dir($configPath)) {
            return [];
        }

        $configs = [];
        $files = glob($configPath . DIRECTORY_SEPARATOR . '*.json');

        foreach ($files as $file) {
            $configs[] = [
                'filename' => basename($file),
                'path' => $file,
                'created' => filemtime($file),
                'size' => filesize($file),
            ];
        }

        // Sort by creation time, newest first
        usort($configs, fn($a, $b) => $b['created'] <=> $a['created']);

        return $configs;
    }

    /**
     * Get a stored configuration
     *
     * @param string $filename
     * @return array|null
     */
    public function getStoredConfig(string $filename): ?array
    {
        $plugin = Plugin::getInstance();
        $configPath = $plugin->getStoragePath() . DIRECTORY_SEPARATOR . 'configs' . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($configPath)) {
            return null;
        }

        $data = json_decode(file_get_contents($configPath), true);
        return $data ?: null;
    }

	/**
     * Sanitize filename
     */
    private function sanitizeFilename(string $name): string
    {
        // Remove or replace problematic characters
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        $name = trim($name, '_');
        return $name ?: 'config';
    }

    /**
     * Export configuration to a file
     *
     * @param array $config Configuration data
     * @param string $outputPath Output file path
     * @return bool
     */
    public function exportConfig(array $config, string $outputPath): bool
    {
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                Craft::error("Failed to create directory: $dir", __METHOD__);
                return false;
            }
        }

        if (file_put_contents($outputPath, $json) === false) {
            Craft::error("Failed to write config to file: $outputPath", __METHOD__);
            return false;
        }

        return true;
    }
}
