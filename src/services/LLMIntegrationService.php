<?php

namespace craftcms\fieldagent\services;

use Craft;
use craft\base\Component;
use craftcms\fieldagent\Plugin;
use yii\base\Exception;

/**
 * LLM Integration service for generating field configurations from natural language
 */
class LLMIntegrationService extends Component
{
    public const PROVIDER_ANTHROPIC = 'anthropic';
    public const PROVIDER_OPENAI = 'openai';

    /**
     * Generate field configuration from natural language prompt
     */
    public function generateFromPrompt(string $prompt, string $provider = self::PROVIDER_ANTHROPIC, bool $debug = false): array
    {
        // Load the JSON schema for structured output
        $schemaPath = Plugin::getInstance()->getBasePath() . '/schemas/llm-output-schema-v2.json';
        if (!file_exists($schemaPath)) {
            throw new Exception("Schema file not found: $schemaPath");
        }

        $schema = json_decode(file_get_contents($schemaPath), true);
        if (!$schema) {
            throw new Exception("Invalid JSON schema file");
        }

        // Generate the system prompt with schema
        $systemPrompt = $this->buildSystemPrompt($schema);

        if ($debug) {
            $this->logDebug("=== LLM REQUEST DEBUG ===");
            $this->logDebug("Provider: $provider");
            $this->logDebug("User Prompt: $prompt");
            $this->logDebug("System Prompt Length: " . strlen($systemPrompt) . " characters");
            $this->logDebug("Schema Properties: " . implode(', ', array_keys($schema['properties'] ?? [])));
        }

        // Call the appropriate LLM provider
        $response = match ($provider) {
            self::PROVIDER_ANTHROPIC => $this->callAnthropic($systemPrompt, $prompt, $schema, $debug),
            self::PROVIDER_OPENAI => $this->callOpenAI($systemPrompt, $prompt, $schema, $debug),
            default => throw new Exception("Unsupported LLM provider: $provider")
        };

        if ($debug) {
            $this->logDebug("=== LLM RESPONSE DEBUG ===");
            $this->logDebug("Response Structure: " . json_encode(array_keys($response), JSON_PRETTY_PRINT));
            if (isset($response['fields'])) {
                $this->logDebug("Generated Fields: " . count($response['fields']));
                foreach ($response['fields'] as $i => $field) {
                    $this->logDebug("  [$i] {$field['name']} ({$field['handle']}) - {$field['field_type']}");
                }
            }
        }

        // Validate the response against our schema
        $plugin = Plugin::getInstance();
        $validation = $plugin->schemaValidationService->validateLLMOutput($response);

        if (!$validation['valid']) {
            throw new Exception("LLM response validation failed: " . implode(', ', $validation['errors']));
        }

        // Return the validated response as-is (settings handled in FieldGeneratorService)
        return $response;
    }

    /**
     * Build comprehensive system prompt with JSON schema
     */
    public function buildSystemPrompt(array $schema): string
    {
        return <<<PROMPT
You are an expert Craft CMS field configuration generator. Your task is to create JSON field configurations from natural language descriptions.

IMPORTANT: You MUST respond with valid JSON that exactly matches this schema. Do not include any explanation, markdown formatting, or additional text - only the JSON response.

JSON Schema Requirements:
- You must respond with a JSON object containing:
  - "fields" array (REQUIRED) - Field definitions
  - "entryTypes" array (OPTIONAL but recommended) - Entry type definitions that use the fields
  - "sections" array (OPTIONAL but recommended) - Section definitions that use the entry types
- Each field must have: name, handle, field_type, and optionally instructions, required, searchable, settings
- Handles must be camelCase starting with lowercase (e.g. "blogTitle", "featuredImage")
- Available field types: plain_text, rich_text, image, number, link, dropdown, lightswitch, email, date, time, color, money, range, radio_buttons, checkboxes, multi_select, country, button_group, icon, asset, matrix

IMPORTANT: When creating a complete content structure, include all three components (fields, entryTypes, sections) to create a fully functional setup in Craft CMS.

CRITICAL: Field-specific configurations MUST go inside a "settings" object, NOT at the root level of the field!
IMPORTANT: Each field type has SPECIFIC settings that apply ONLY to that type. Do not use settings from one field type on another.

Field Type Guidelines with TYPE-SPECIFIC Settings:

1. plain_text: Use for short text, titles, names
   ONLY these settings allowed (inside "settings" object):
   - multiline: true/false (for textarea vs text input)
   - charLimit: integer 1-10000 (max characters)

2. rich_text: Use for formatted content like blog posts
   No settings object needed (can omit "settings" entirely)

3. image: Use for photos, logos, featured images
   ONLY this setting allowed (inside "settings" object):
   - maxRelations: integer 1-10 (MAXIMUM 10 - never exceed this limit)

4. number: Use for quantities, prices, counts
   ONLY these settings allowed (inside "settings" object):
   - decimals: integer 0-4 (decimal places)
   - min: number (minimum value)
   - max: number (maximum value)
   - suffix: string (e.g., "USD", "items")

5. link: Use for website links, external URLs, and internal entry links
   ONLY these settings allowed (inside "settings" object):
   - types: array of strings (link types: ["url"] for external links, ["entry"] for internal links, ["url", "entry"] for both)
   - sources: array of strings (section handles for entry links, e.g., ["blog", "pages"])
   - showLabelField: true/false (show custom link text field)

6. dropdown: Use for predefined choices
   ONLY this setting allowed (inside "settings" object):
   - options: array of strings (the choices) - REQUIRED

7. lightswitch: Use for yes/no, on/off toggles
   ONLY these settings allowed (inside "settings" object):
   - default: true/false (default state)
   - onLabel: string (custom "on" label)
   - offLabel: string (custom "off" label)

8. email: Use for email addresses with validation
   ONLY this setting allowed (inside "settings" object):
   - placeholder: string (placeholder text)

9. date: Use for date/datetime selection
   ONLY these settings allowed (inside "settings" object):
   - showDate: true/false (show date picker)
   - showTime: true/false (show time picker)
   - showTimeZone: true/false (show timezone selector)

10. time: Use for time-only selection
    No settings object needed (can omit "settings" entirely)

11. color: Use for color selection with picker
    ONLY this setting allowed (inside "settings" object):
    - allowCustomColors: true/false (allow custom color input)

12. money: Use for currency amounts
    ONLY these settings allowed (inside "settings" object):
    - currency: string (currency code like "USD", "EUR", "GBP")
    - showCurrency: true/false (show currency symbol)
    - min: number (minimum value)
    - max: number (maximum value)

13. range: Use for slider/range inputs
    ONLY these settings allowed (inside "settings" object):
    - min: number (minimum value)
    - max: number (maximum value)
    - step: number (step increment)
    - suffix: string (text after value)

14. radio_buttons: Use for single choice from options (radio buttons)
    ONLY this setting allowed (inside "settings" object):
    - options: array of strings (the choices) - REQUIRED

15. checkboxes: Use for multiple selections (checkboxes)
    ONLY this setting allowed (inside "settings" object):
    - options: array of strings (the choices) - REQUIRED

16. multi_select: Use for multiple selections (dropdown with multi-select)
    ONLY this setting allowed (inside "settings" object):
    - options: array of strings (the choices) - REQUIRED

17. country: Use for country selection (built-in country list)
    No settings object needed (can omit "settings" entirely)

18. button_group: Use for button group selection
    ONLY this setting allowed (inside "settings" object):
    - options: array of strings (the choices) - REQUIRED

19. icon: Use for icon selection
    No settings object needed (can omit "settings" entirely)

20. asset: Use for general file uploads (documents, videos, etc.)
    ONLY this setting allowed (inside "settings" object):
    - maxRelations: integer 1-10 (maximum number of assets)

21. matrix: Use for flexible content blocks (complex layouts, repeatable sections)
    ONLY these settings allowed (inside "settings" object):
    - minEntries: integer 0-100 (minimum number of entries/blocks required)
    - maxEntries: integer 1-100 (maximum number of entries/blocks allowed, null for unlimited)
    - viewMode: string "cards", "blocks", or "index" (how blocks are displayed in admin)
    - blockTypes: array of block type objects (each represents a type of content block) - REQUIRED
    
    Block Type Structure (inside blockTypes array):
    - name: Display name for the block type (e.g., "Text Block", "Image Block")
    - handle: Unique camelCase identifier (e.g., "textBlock", "imageBlock")
    - hasTitleField: true/false (usually false for block types)
    - fields: Array of field objects for this block type (simplified field types only)
    
    Block Type Field Structure (inside blockTypes[].fields):
    - name: Display name for the field
    - handle: Unique camelCase identifier
    - field_type: Simplified set (plain_text, rich_text, image, number, email, date, lightswitch, dropdown, asset)
    - instructions: Help text for content editors
    - required: true/false
    - settings: Field-specific settings (simplified, same structure as main fields but limited options)
    
    Matrix Field Usage Examples:
    - Page builder with text blocks, image blocks, gallery blocks
    - FAQ sections with question/answer pairs
    - Team member listings with name, photo, bio
    - Product features with icon, title, description
    - Testimonials with quote, author, image
    
    IMPORTANT: Keep block types simple - use only basic field types within matrix blocks
    LIMITATION: Matrix fields cannot contain other matrix fields (no nesting)

Entry Type Structure:
- name: Display name for the entry type
- handle: Unique camelCase identifier
- hasTitleField: Whether to include a title field (usually true)
- fields: Array of field references with handle and required status

Section Structure:
- name: Display name for the section
- handle: Unique camelCase identifier
- type: "single", "channel", or "structure"
- hasUrls: Whether entries have URLs (usually true)
- uri: URL format (e.g., "blog/{slug}")
- template: Template path (e.g., "blog/_entry")
- entryTypes: Array of entry type handles to use

Best Practices:
- Create logical, user-friendly field names
- Add helpful instructions for content editors
- Mark essential fields as required:true
- Make content fields searchable:true
- Use consistent handle naming (prefix related fields)
- ALWAYS create sections and entry types for a complete setup
- Link fields → entry types → sections for proper associations

Example Response Structure:
{
  "name": "Blog System",
  "description": "Complete blog setup with various field types",
  "fields": [
    {
      "name": "Blog Title",
      "handle": "blogTitle",
      "field_type": "plain_text",
      "instructions": "The main title of the blog post",
      "required": true,
      "searchable": true,
      "settings": {
        "multiline": false,
        "charLimit": 255
      }
    },
    {
      "name": "Blog Content",
      "handle": "blogContent",
      "field_type": "rich_text",
      "instructions": "The main content of the blog post",
      "required": true,
      "searchable": true
    },
    {
      "name": "Featured Image",
      "handle": "blogFeaturedImage",
      "field_type": "image",
      "instructions": "Main image for the blog post",
      "required": false,
      "settings": {
        "maxRelations": 1
      }
    },
    {
      "name": "Gallery Images",
      "handle": "galleryImages",
      "field_type": "image",
      "instructions": "Additional images for the gallery",
      "required": false,
      "settings": {
        "maxRelations": 10
      }
    },
    {
      "name": "Price",
      "handle": "price",
      "field_type": "number",
      "instructions": "Product price",
      "settings": {
        "decimals": 2,
        "min": 0,
        "suffix": "USD"
      }
    },
    {
      "name": "Category",
      "handle": "category",
      "field_type": "dropdown",
      "instructions": "Select a category",
      "settings": {
        "options": ["News", "Tutorial", "Review"]
      }
    },
    {
      "name": "Published",
      "handle": "published",
      "field_type": "lightswitch",
      "instructions": "Is this post published?",
      "settings": {
        "onLabel": "Published",
        "offLabel": "Draft"
      }
    },
    {
      "name": "Author Email",
      "handle": "authorEmail",
      "field_type": "email",
      "instructions": "Contact email for the author",
      "settings": {
        "placeholder": "author@example.com"
      }
    },
    {
      "name": "Publish Date",
      "handle": "publishDate",
      "field_type": "date",
      "instructions": "When to publish this post",
      "settings": {
        "showDate": true,
        "showTime": true
      }
    },
    {
      "name": "Priority Level",
      "handle": "priorityLevel",
      "field_type": "radio_buttons",
      "instructions": "Select the priority level",
      "settings": {
        "options": ["Low", "Medium", "High", "Urgent"]
      }
    },
    {
      "name": "Budget",
      "handle": "budget",
      "field_type": "money",
      "instructions": "Project budget",
      "settings": {
        "currency": "USD",
        "min": 0,
        "max": 100000
      }
    }
  ],
  "entryTypes": [
    {
      "name": "Blog Post",
      "handle": "blogPost",
      "hasTitleField": true,
      "fields": [
        {"handle": "blogTitle", "required": true},
        {"handle": "blogContent", "required": true},
        {"handle": "blogFeaturedImage", "required": false}
      ]
    }
  ],
  "sections": [
    {
      "name": "Blog",
      "handle": "blog",
      "type": "channel",
      "hasUrls": true,
      "uri": "blog/{slug}",
      "template": "blog/_entry",
      "entryTypes": ["blogPost"]
    }
  ]
}

CRITICAL REMINDERS:
1. ALWAYS include all three components for a complete setup: fields, entryTypes, and sections
2. ALL field-specific configurations (maxRelations, multiline, options, etc.) MUST be inside a "settings" object
3. NEVER put maxRelations, multiline, options, or other field-specific properties at the root level of a field
4. The "settings" object is REQUIRED for image fields with maxRelations
5. The "settings" object is REQUIRED for dropdown fields with options
6. DO NOT include default values in your response - let the system handle defaults
7. Only include properties that are explicitly needed for the requested configuration
8. Check each field to ensure any configuration is properly nested in "settings"
9. IMPORTANT: maxRelations for image fields MUST be between 1 and 10 (inclusive). NEVER use values like 20, 50, 100, etc.
10. If user asks for "many" images, use maxRelations: 10 (the maximum allowed)
11. Create meaningful associations: fields must be referenced in entryTypes, and entryTypes must be referenced in sections

Remember: Respond ONLY with valid JSON matching this exact structure. No explanations or additional text.
PROMPT;
    }

    /**
     * Call Anthropic Claude API with structured output
     */
    public function callAnthropic(string $systemPrompt, string $userPrompt, array $schema, bool $debug = false): array
    {
        $apiKey = $this->getApiKey('ANTHROPIC_API_KEY');
        if (!$apiKey) {
            throw new Exception("Anthropic API key not found in environment variables");
        }

        $payload = [
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 4000,
            'system' => $systemPrompt,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $userPrompt
				],
				// TODO: Try assistant prefill here
				// [
				//     'role' => 'assistant',
				//     'content' => '{ "name": ',
				// ],
            ]
            // Note: Anthropic doesn't support response_format yet
            // We rely on system prompt instructions for JSON output
        ];

        if ($debug) {
            $this->logDebug("=== ANTHROPIC REQUEST ===");
            $this->logDebug("URL: https://api.anthropic.com/v1/messages");
            $this->logDebug("Model: " . $payload['model']);
            $this->logDebug("Max Tokens: " . $payload['max_tokens']);
            $this->logDebug("System Prompt: " . substr($systemPrompt, 0, 200) . "...");
            $this->logDebug("User Prompt: $userPrompt");
            $this->logDebug("Full Payload: " . json_encode($payload, JSON_PRETTY_PRINT));
        }

        $response = $this->makeHttpRequest('https://api.anthropic.com/v1/messages', [
            'x-api-key: ' . $apiKey,
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01'
        ], $payload, $debug);

        if ($debug) {
            $this->logDebug("=== ANTHROPIC RESPONSE ===");
            $this->logDebug("Full Response: " . json_encode($response, JSON_PRETTY_PRINT));
        }

        if (!isset($response['content'][0]['text'])) {
            throw new Exception("Invalid response from Anthropic API");
        }

        $rawContent = $response['content'][0]['text'];

        if ($debug) {
            $this->logDebug("Raw Content: $rawContent");
        }

        $jsonResponse = json_decode($rawContent, true);
        if (!$jsonResponse) {
            throw new Exception("Failed to parse JSON from Anthropic response: $rawContent");
        }

        if ($debug) {
            $this->logDebug("Parsed JSON: " . json_encode($jsonResponse, JSON_PRETTY_PRINT));
        }

        return $jsonResponse;
    }

    /**
     * Call OpenAI API with structured output
     */
    public function callOpenAI(string $systemPrompt, string $userPrompt, array $schema, bool $debug = false): array
    {
        $apiKey = $this->getApiKey('OPENAI_API_KEY');
        if (!$apiKey) {
            throw new Exception("OpenAI API key not found in environment variables");
        }

        $payload = [
            'model' => 'gpt-4o-2024-08-06',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt
                ]
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'field_configuration',
                    'schema' => $schema
                ]
            ],
            'max_tokens' => 4000,
            'temperature' => 0.1
        ];

        if ($debug) {
            $this->logDebug("=== OPENAI REQUEST ===");
            $this->logDebug("URL: https://api.openai.com/v1/chat/completions");
            $this->logDebug("Model: " . $payload['model']);
            $this->logDebug("Max Tokens: " . $payload['max_tokens']);
            $this->logDebug("Temperature: " . $payload['temperature']);
            $this->logDebug("System Prompt: " . substr($systemPrompt, 0, 200) . "...");
            $this->logDebug("User Prompt: $userPrompt");
            $this->logDebug("Schema Keys: " . implode(', ', array_keys($schema)));
            $this->logDebug("Full Payload: " . json_encode($payload, JSON_PRETTY_PRINT));
        }

        $response = $this->makeHttpRequest('https://api.openai.com/v1/chat/completions', [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ], $payload, $debug);

        if ($debug) {
            $this->logDebug("=== OPENAI RESPONSE ===");
            $this->logDebug("Full Response: " . json_encode($response, JSON_PRETTY_PRINT));
        }

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new Exception("Invalid response from OpenAI API");
        }

        $rawContent = $response['choices'][0]['message']['content'];

        if ($debug) {
            $this->logDebug("Raw Content: $rawContent");
        }

        $jsonResponse = json_decode($rawContent, true);
        if (!$jsonResponse) {
            throw new Exception("Failed to parse JSON from OpenAI response: $rawContent");
        }

        if ($debug) {
            $this->logDebug("Parsed JSON: " . json_encode($jsonResponse, JSON_PRETTY_PRINT));
        }

        return $jsonResponse;
    }

    /**
     * Make HTTP request to LLM API
     */
    private function makeHttpRequest(string $url, array $headers, array $payload, bool $debug = false): array
    {
        $jsonPayload = json_encode($payload);

        if ($debug) {
            $this->logDebug("=== HTTP REQUEST ===");
            $this->logDebug("URL: $url");
            $this->logDebug("Headers: " . implode(', ', array_map(function($h) {
                return strpos($h, 'x-api-key') === 0 ? 'x-api-key: ***' :
                       (strpos($h, 'Authorization') === 0 ? 'Authorization: Bearer ***' : $h);
            }, $headers)));
            $this->logDebug("Payload Size: " . strlen($jsonPayload) . " bytes");
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($debug) {
            $this->logDebug("=== HTTP RESPONSE ===");
            $this->logDebug("Duration: {$duration}ms");
            $this->logDebug("HTTP Code: $httpCode");
            $this->logDebug("Response Size: " . strlen($response) . " bytes");
            if ($error) {
                $this->logDebug("cURL Error: $error");
            }
        }

        if ($error) {
            throw new Exception("HTTP request failed: $error");
        }

        if ($httpCode !== 200) {
            if ($debug) {
                $this->logDebug("HTTP Error Details:");
                $this->logDebug("- Status Code: $httpCode");
                $this->logDebug("- Response Body: $response");

                // Try to parse error response for more details
                $errorData = json_decode($response, true);
                if ($errorData && isset($errorData['error'])) {
                    $this->logDebug("- Error Type: " . ($errorData['error']['type'] ?? 'unknown'));
                    $this->logDebug("- Error Message: " . ($errorData['error']['message'] ?? 'no message'));
                }
            }
            throw new Exception("HTTP request failed with code $httpCode: $response");
        }

        $decodedResponse = json_decode($response, true);
        if (!$decodedResponse) {
            throw new Exception("Failed to decode JSON response: $response");
        }

        if (isset($decodedResponse['error'])) {
            throw new Exception("API error: " . $decodedResponse['error']['message'] ?? 'Unknown error');
        }

        return $decodedResponse;
    }

    /**
     * Get API key from Craft config system
     */
    private function getApiKey(string $keyName): ?string
    {
        // Map environment variable names to config keys
        $configKeyMap = [
            'ANTHROPIC_API_KEY' => 'anthropicApiKey',
            'OPENAI_API_KEY' => 'openaiApiKey',
        ];

        $configKey = $configKeyMap[$keyName] ?? null;
        if (!$configKey) {
            $this->logDebug("No config mapping found for: $keyName");
            return null;
        }

        // Get from Craft config system
        $config = Craft::$app->getConfig()->getConfigFromFile('field-agent');
        $key = $config[$configKey] ?? null;

        if ($key) {
            $this->logDebug("API key found via Craft config system");
            return $key;
        }

        $this->logDebug("API key not found for: $keyName (config key: $configKey)");
        return null;
    }

    /**
     * Log debug message to Craft logs and console
     */
    private function logDebug(string $message): void
    {
        // Log to Craft's log system (storage/logs/web.log)
        Craft::info($message, 'field-agent-llm');

        // Also echo to console if in CLI mode for immediate visibility
        if (Craft::$app instanceof \craft\console\Application) {
            echo "[DEBUG] $message\n";
        }
    }

    /**
     * Test API connection with a simple prompt
     */
    public function testConnection(string $provider = self::PROVIDER_ANTHROPIC, bool $debug = false): array
    {
        try {
            $testConfig = $this->generateFromPrompt(
                "Create a simple blog with title, summary, content and featured image",
                $provider,
                $debug
            );

            return [
                'success' => true,
                'provider' => $provider,
                'message' => 'API connection successful',
                'fieldCount' => count($testConfig['fields'] ?? [])
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'provider' => $provider,
                'error' => $e->getMessage()
            ];
        }
    }
}
