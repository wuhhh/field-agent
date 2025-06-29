<?php

use craft\helpers\App;

return [
    'anthropicApiKey' => App::env('ANTHROPIC_API_KEY'),
    'openaiApiKey' => App::env('OPENAI_API_KEY'),
];