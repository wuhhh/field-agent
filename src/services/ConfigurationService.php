<?php

namespace craftcms\fieldagent\services;

use Craft;
use craft\base\Component;
use craftcms\fieldagent\Plugin;

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
        $plugin = Plugin::getInstance();
        $configData = $plugin->fieldGeneratorService->getStoredConfig($config);
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
    public function storeConfig(string $name, array $configData): bool
    {
        $plugin = Plugin::getInstance();
        return $plugin->fieldGeneratorService->storeConfig($name, $configData);
    }

    /**
     * Get all stored configurations
     * 
     * @return array
     */
    public function listStoredConfigs(): array
    {
        $plugin = Plugin::getInstance();
        return $plugin->fieldGeneratorService->listStoredConfigs();
    }

    /**
     * Get a stored configuration
     * 
     * @param string $name
     * @return array|null
     */
    public function getStoredConfig(string $name): ?array
    {
        $plugin = Plugin::getInstance();
        return $plugin->fieldGeneratorService->getStoredConfig($name);
    }

    /**
     * Generate basic fields configuration
     * 
     * @return array
     */
    public function generateBasicFieldsConfig(): array
    {
        return [
            'fields' => [
                [
                    'name' => 'Text Field',
                    'handle' => 'textField',
                    'field_type' => 'plain_text',
                    'instructions' => 'A simple text field',
                    'searchable' => true,
                    'columnType' => 'text'
                ],
                [
                    'name' => 'Rich Text Field',
                    'handle' => 'richTextField',
                    'field_type' => 'rich_text',
                    'instructions' => 'Rich text editor with formatting options',
                    'searchable' => true,
                    'columnType' => 'text'
                ],
                [
                    'name' => 'Image',
                    'handle' => 'imageField',
                    'field_type' => 'image',
                    'instructions' => 'Upload an image',
                    'restrictFiles' => true,
                    'allowedKinds' => ['image']
                ],
                [
                    'name' => 'URL',
                    'handle' => 'urlField',
                    'field_type' => 'link',
                    'instructions' => 'A URL field',
                    'placeholder' => 'https://example.com'
                ],
                [
                    'name' => 'Number',
                    'handle' => 'numberField',
                    'field_type' => 'number',
                    'instructions' => 'Enter a number',
                    'min' => 0,
                    'max' => 100,
                    'decimals' => 0
                ]
            ]
        ];
    }

    /**
     * Generate configuration from natural language prompt
     * This is a placeholder method that will be enhanced by PromptService
     * 
     * @param string $prompt
     * @return array
     */
    public function generateConfigFromPrompt(string $prompt): array
    {
        // This is a placeholder - in a real implementation, you'd use AI/LLM here
        // For now, return a basic blog config as an example

        if (stripos($prompt, 'blog') !== false) {
            return [
                'fields' => [
                    [
                        'name' => 'Blog Title',
                        'handle' => 'blogTitle',
                        'field_type' => 'plain_text',
                        'instructions' => 'The main title of the blog post',
                        'searchable' => true
                    ],
                    [
                        'name' => 'Blog Content',
                        'handle' => 'blogContent',
                        'field_type' => 'rich_text',
                        'instructions' => 'The main content of the blog post',
                        'searchable' => true
                    ],
                    [
                        'name' => 'Featured Image',
                        'handle' => 'featuredImage2',
                        'field_type' => 'image',
                        'instructions' => 'Main image for the blog post'
                    ]
                ]
            ];
        }

        // Default fallback
        return [
            'fields' => [
                [
                    'name' => 'Title',
                    'handle' => 'promptTitle',
                    'field_type' => 'plain_text',
                    'instructions' => 'Generated from prompt: ' . $prompt
                ]
            ]
        ];
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