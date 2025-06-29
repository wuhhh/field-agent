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

    public function rules(): array
    {
        return [
            [['persistConfigs', 'enableDebugLogging'], 'boolean'],
            [['configDir'], 'string'],
            [['maxStoredConfigs'], 'integer', 'min' => 1, 'max' => 1000],
        ];
    }
}