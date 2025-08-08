<?php

namespace craftcms\fieldagent\models;

use craft\base\Model;

/**
 * Field Generator plugin settings
 */
class Settings extends Model
{
    /**
     * @var bool Whether to store configuration files persistently
     */
    public bool $persistConfigs = true;

    /**
     * @var string Directory to store configuration files (relative to storage)
     */
    public string $configDir = 'configs';

    /**
     * @var int Maximum number of stored configurations to keep
     */
    public int $maxStoredConfigs = 50;

    /**
     * @var bool Whether to enable debug logging
     */
    public bool $enableDebugLogging = false;

    /**
     * @var string Default AI provider to use when none specified
     */
    public string $defaultProvider = 'anthropic';

    public function rules(): array
    {
        return [
            [['persistConfigs', 'enableDebugLogging'], 'boolean'],
            [['configDir', 'defaultProvider'], 'string'],
            [['maxStoredConfigs'], 'integer', 'min' => 1, 'max' => 1000],
            [['defaultProvider'], 'in', 'range' => ['anthropic', 'openai']],
        ];
    }
}