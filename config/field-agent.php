<?php

use craft\helpers\App;

return [
    // API Configuration - Add your AI provider API keys to .env
    'anthropicApiKey' => App::env('ANTHROPIC_API_KEY'),
    'openaiApiKey' => App::env('OPENAI_API_KEY'),
    
    // Default AI Provider - Which provider to use when none specified
    // Options: 'anthropic' or 'openai'
    'defaultProvider' => 'anthropic',
    
    // Storage Configuration - How field configurations are stored
    'persistConfigs' => true,              // Whether to save generated configs
    'configDir' => 'configs',              // Directory name in storage/field-agent/
    'maxStoredConfigs' => 50,              // Max number of configs to keep
    
    // Debug Settings - For troubleshooting
    'enableDebugLogging' => false,         // Enable detailed logging
];