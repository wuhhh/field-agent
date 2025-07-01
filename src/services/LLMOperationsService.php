<?php

namespace craftcms\fieldagent\services;

use Craft;
use craft\base\Component;
use craftcms\fieldagent\Plugin;
use craftcms\fieldagent\services\DiscoveryService;
use yii\base\Exception;

/**
 * Enhanced LLM Integration service that supports operations and context awareness
 */
class LLMOperationsService extends Component
{
    public const PROVIDER_ANTHROPIC = 'anthropic';
    public const PROVIDER_OPENAI = 'openai';

    /**
     * Generate operations from natural language prompt with context
     */
    public function generateOperationsFromPrompt(string $prompt, string $provider = self::PROVIDER_ANTHROPIC, bool $debug = false): array
    {
        // Get project context from discovery service
        $plugin = Plugin::getInstance();
        $context = $plugin->discoveryService->getProjectContext();

        // Load the operations schema
        $schemaPath = Plugin::getInstance()->getBasePath() . '/schemas/llm-operations-schema.json';
        if (!file_exists($schemaPath)) {
            throw new Exception("Operations schema file not found: $schemaPath");
        }

        $schema = json_decode(file_get_contents($schemaPath), true);
        if (!$schema) {
            throw new Exception("Invalid JSON operations schema file");
        }

        // Generate the system prompt with context and schema
        $systemPrompt = $this->buildOperationsSystemPrompt($schema, $context);

        if ($debug) {
            $this->logDebug("=== LLM OPERATIONS REQUEST DEBUG ===");
            $this->logDebug("Provider: $provider");
            $this->logDebug("User Prompt: $prompt");
            $this->logDebug("Context Summary: " . $context['summary']);
            $this->logDebug("Existing Fields: " . $context['fields']['count']);
            $this->logDebug("Existing Sections: " . $context['sections']['count']);
        }

        // Call the appropriate LLM provider
        $response = match ($provider) {
            self::PROVIDER_ANTHROPIC => $this->callAnthropic($systemPrompt, $prompt, $schema, $debug),
            self::PROVIDER_OPENAI => $this->callOpenAI($systemPrompt, $prompt, $schema, $debug),
            default => throw new Exception("Unsupported LLM provider: $provider")
        };

        if ($debug) {
            $this->logDebug("=== LLM OPERATIONS RESPONSE DEBUG ===");
            $this->logDebug("Operations Count: " . count($response['operations'] ?? []));
            foreach ($response['operations'] ?? [] as $i => $op) {
                $this->logDebug("  [$i] {$op['type']} {$op['target']}" . (isset($op['targetId']) ? " ({$op['targetId']})" : ''));
            }
        }

        // Validate operations
        $validation = $this->validateOperations($response);
        if (!$validation['valid']) {
            throw new Exception("Operations validation failed: " . implode(', ', $validation['errors']));
        }

        return $response;
    }

    /**
     * Build system prompt for operations with context
     */
    private function buildOperationsSystemPrompt(array $schema, array $context): string
    {
        // Format existing fields for the prompt
        $fieldsContext = $this->formatFieldsContext($context['fields']);
        $sectionsContext = $this->formatSectionsContext($context['sections']);

        return <<<PROMPT
You are an expert Craft CMS field configuration generator with awareness of existing project structures. Your task is to create JSON operation configurations that intelligently modify or extend the current project.

CURRENT PROJECT STATE:
{$context['summary']}

EXISTING FIELDS:
{$fieldsContext}

EXISTING SECTIONS AND ENTRY TYPES:
{$sectionsContext}

IMPORTANT: You MUST respond with valid JSON that exactly matches the operations schema. Do not include any explanation, markdown formatting, or additional text - only the JSON response.

OPERATION TYPES:
1. "create" - Create new fields, entry types, or sections
2. "modify" - Add/remove fields from entry types, update settings
3. "delete" - Remove fields, entry types, or sections (use sparingly)

CRITICAL: OPERATION ORDERING
Operations MUST be ordered correctly for dependencies:
1. Create category groups and tag groups FIRST (if needed)
2. Create fields second (can now reference the groups)
3. Create entry types third (referencing the fields)
4. Create sections last (referencing the entry types)
Wrong order will cause failures!

CRITICAL: FIELD TYPES (use these EXACT values only):
- plain_text (NOT "text") - For text inputs with optional multiline and charLimit settings
- rich_text - For CKEditor WYSIWYG content  
- link (NOT "url") - For website links with types and sources settings
- image - For image uploads with maxRelations setting
- email - For email addresses with validation
- date - For date/time selection with showDate, showTime settings
- lightswitch - For boolean toggles
- dropdown - For selection with options setting
- number - For numeric inputs with decimals, min, max settings
- money - For currency amounts with currency setting
- categories - For Craft CMS native category groups (NOT multi_select for categories!)
- tags - For Craft CMS native tag groups (NOT multi_select for tags!)
- All other supported: time, color, range, radio_buttons, checkboxes, multi_select, country, button_group, icon, asset, matrix, users, entries

WHEN TO USE CATEGORIES vs TAGS vs MULTI_SELECT:
- Use "categories" when content should be organized into hierarchical category groups (e.g., blog categories, product categories)
- Use "tags" when content needs flexible tagging for SEO, filtering, or loose categorization (e.g., keywords, skills, topics)  
- Use "multi_select" only for predefined static options that are NOT categories or tags (e.g., sizes, colors, features)

CRITICAL: CATEGORY AND TAG GROUP CREATION
Before creating categories or tags fields, you MUST create the groups they reference:

1. CREATE GROUPS FIRST: Always create categoryGroup and tagGroup targets before fields that use them
2. FIELD REFERENCES: Category fields reference category groups, tag fields reference tag groups
3. NAMING CONVENTION: Use descriptive group names like "Blog Categories", "Product Tags", etc.

Example order for blog with categories:
1. Create categoryGroup: "blogCategories" 
2. Create categories field: references "blogCategories" group via sources: ["blogCategories"]
3. Create entry type with the categories field
4. Create section with the entry type

CRITICAL: RESERVED FIELD HANDLES (NEVER USE THESE):
author, authorId, dateCreated, dateUpdated, id, slug, title, uid, uri, url, content, level, lft, rgt, root, parent, parentId, children, descendants, ancestors, next, prev, siblings, status, enabled, archived, trashed, postDate, expiryDate, revisionCreator, revisionNotes, section, sectionId, type, typeId, field, fieldId

FIELD HANDLE ALTERNATIVES:
- Instead of "title" → use "pageTitle", "blogTitle", "articleTitle" 
- Instead of "content" → use "bodyContent", "mainContent", "description"
- Instead of "author" → use "writer", "creator", "byline"
- Always prefix handles with the content type when possible (e.g., "blogContent", "newsTitle")

FIELD-TYPE-SPECIFIC SETTINGS (each field type has ONLY its allowed settings):
Settings MUST be inside "settings" object. Do NOT use settings from one field type on another!

- plain_text: ONLY multiline (boolean), charLimit (integer 1-10000)
- rich_text: No settings needed
- link: ONLY types (array: ["url"] or ["entry"] or both), sources (array of section handles), showLabelField (boolean)
- image: ONLY maxRelations (integer 1-10)
- asset: ONLY maxRelations (integer 1-10)
- dropdown/radio_buttons/checkboxes/multi_select/button_group: ONLY options (array of strings) - REQUIRED
- number: ONLY decimals (0-4), min (number), max (number), suffix (string)
- date: ONLY showDate (boolean), showTime (boolean), showTimeZone (boolean)
- time: No settings needed
- email: ONLY placeholder (string)
- lightswitch: ONLY default (boolean), onLabel (string), offLabel (string)
- color: ONLY allowCustomColors (boolean)
- money: ONLY currency (string like "USD"), showCurrency (boolean), min (number), max (number)
- range: ONLY min (number), max (number), step (number), suffix (string)
- categories: ONLY maxRelations (integer), sources (array of category group handles or ["*"] for all), branchLimit (integer)
- tags: ONLY maxRelations (integer), sources (array of tag group handles or ["*"] for all)
- users: ONLY maxRelations (integer), sources (array of user group handles or ["*"] for all)
- entries: ONLY maxRelations (integer), sources (array of section handles or ["*"] for all)
- country/icon: No settings needed
- matrix: ONLY minEntries (integer), maxEntries (integer), viewMode (string), entryTypes (array) - entryTypes REQUIRED

CRITICAL: MATRIX FIELDS AND ENTRY TYPE ASSOCIATIONS

Matrix fields in Craft CMS contain ENTRY TYPES, not "blocks" or "block types". The term "block" is just a UI abstraction - technically, matrix fields contain entry types.

UNDERSTANDING MATRIX FIELD ARCHITECTURE:
- Matrix Field (e.g., "pageBuilder") contains multiple Entry Types
- Entry Types can be created inline for the matrix field OR can reference existing Entry Types  
- Existing Entry Types can be added to matrix fields
- This allows complex content structures and reusable components

TWO APPROACHES FOR MATRIX ENTRY TYPES:

1. INLINE ENTRY TYPE DEFINITION (ONLY for matrix field entryTypes - NOT for regular entry type creation):
This format is ONLY used inside matrix field settings.entryTypes array:
{
  "name": "Text Content",
  "handle": "textContent", 
  "fields": [
    {
      "name": "Text Content", 
      "handle": "textContent",
      "field_type": "rich_text",  // ← Use "field_type", NOT "type"
      "required": true
    }
  ]
}

2. REFERENCE EXISTING ENTRY TYPE (add existing entry type to matrix):
When you want to add an existing entry type to a matrix field:
{
  "type": "modify",
  "target": "field",
  "targetId": "pageBuilder",  // ← Matrix field to modify
  "modify": {
    "actions": [
      {
        "action": "addMatrixEntryType",
        "matrixEntryType": {
          "name": "Feature Cards Block",
          "handle": "featureCardsBlock",
          "entryTypeHandle": "featureCardsBlock"  // ← References existing entry type
        }
      }
    ]
  }
}

COMMON MATRIX FIELD PATTERNS:

IMPORTANT: Many component requests require NESTED MATRIX STRUCTURES with multiple levels:

PATTERN 0: "NESTED COMPONENT CREATION" (Most Complex)
Example: "Create a Feature Cards component" actually means:
1. Feature Card entry type (individual card: title, image, description)
2. Feature Cards matrix field (allows multiple Feature Card entries)  
3. Feature Cards Block entry type (container: title, description, + Feature Cards matrix)
4. Add Feature Cards Block to page builder matrix

This creates: Page Builder > Feature Cards Block > Feature Cards > Feature Card
(4 levels deep!)

{
  "name": "Feature Cards Component (Nested Structure)",
  "description": "Creates complete nested Feature Cards: individual cards, cards matrix, block container, and page builder integration",
  "operations": [
    {
      "type": "create",
      "target": "entryType", 
      "create": {
        "entryType": {
          "name": "Feature Card",
          "handle": "featureCard",
          "hasTitleField": false,
          "fields": [
            {"handle": "cardTitle", "required": true},
            {"handle": "cardImage", "required": false}, 
            {"handle": "cardDescription", "required": false}
          ]
        }
      }
    },
    {
      "type": "create",
      "target": "field",
      "create": {
        "field": {
          "name": "Feature Cards",
          "handle": "featureCards", 
          "field_type": "matrix",
          "settings": {
            "entryTypes": [
              {
                "name": "Feature Card",
                "handle": "featureCard",
                "entryTypeHandle": "featureCard"
              }
            ]
          }
        }
      }
    },
    {
      "type": "create",
      "target": "entryType",
      "create": {
        "entryType": {
          "name": "Feature Cards Block", 
          "handle": "featureCardsBlock",
          "hasTitleField": false,
          "fields": [
            {"handle": "blockTitle", "required": true},
            {"handle": "blockDescription", "required": false},
            {"handle": "featureCards", "required": true}
          ]
        }
      }
    },
    {
      "type": "modify",
      "target": "field", 
      "targetId": "pageBuilder",
      "modify": {
        "actions": [
          {
            "action": "addMatrixEntryType",
            "matrixEntryType": {
              "name": "Feature Cards Block",
              "handle": "featureCardsBlock",
              "entryTypeHandle": "featureCardsBlock"
            }
          }
        ]
      }
    }
  ]
}

PATTERN RECOGNITION KEYWORDS:
- "component" = usually means nested structure
- "cards", "items", "blocks" = individual entry type + matrix field + container
- "repeatable X" = matrix field containing X entry types
- "section with Y" = container entry type with Y fields/matrices
- "testimonials", "team members", "portfolio items" = nested card pattern
- "gallery", "slider", "carousel" = media collection pattern
- "FAQ", "accordion" = question/answer pair pattern

COMPONENT CREATION GUIDANCE:
When users request components like testimonials, cards, galleries, etc., create the full nested structure:
1. Individual item entry type (e.g., testimonial with quote/author/image)
2. Collection matrix field (e.g., testimonials matrix using testimonial entry type)  
3. Container entry type (e.g., testimonials block with title + testimonials matrix)
4. Add container to page builder matrix

COMMON MATRIX FIELD PATTERNS:

PATTERN 1: "Create entry type, add matrix field to it, then add entry type to another matrix field"
This is a complex 3-step pattern:

Example: "Create a new entry type called Feature Cards Block, add the existing feature card matrix field to it, then add the new entry type to the existing page builder field"

{
  "name": "Feature Cards Block with Matrix Integration",
  "description": "Creates entry type, adds matrix field to it, then adds entry type to page builder matrix field",
  "operations": [
    {
      "type": "create",
      "target": "entryType",
      "create": {
        "entryType": {
          "name": "Feature Cards Block",
          "handle": "featureCardsBlock",
          "hasTitleField": true,
          "fields": [
            {
              "handle": "featureCard",  // ← Add existing matrix field
              "required": false
            }
          ]
        }
      }
    },
    {
      "type": "modify", 
      "target": "field",
      "targetId": "pageBuilder",  // ← Existing page builder matrix field
      "modify": {
        "actions": [
          {
            "action": "addMatrixEntryType",
            "matrixEntryType": {
              "name": "Feature Cards Block",
              "handle": "featureCardsBlock",
              "entryTypeHandle": "featureCardsBlock"  // ← Reference the entry type we created
            }
          }
        ]
      }
    }
  ]
}

PATTERN 2: "Add existing field to existing entry type"
{
  "type": "modify",
  "target": "entryType", 
  "targetId": "existingEntryType",
  "modify": {
    "actions": [
      {
        "action": "addField",
        "field": {
          "handle": "existingFieldHandle",  // ← Reference existing field
          "required": false
        }
      }
    ]
  }
}

INTELLIGENT BEHAVIOR:
- Reuse existing fields when appropriate (e.g., if "title" field exists, use it instead of creating "blogTitle")
- Check field handles to avoid conflicts (existing handles are shown above)
- When asked to "add X to Y", use modify operations to add fields to existing entry types
- When creating similar structures, reuse common fields (e.g., use same "featuredImage" field across different entry types)
- Suggest field handle alternatives if conflicts detected
- CRITICAL: When asked to "add entry type to matrix field", use addMatrixEntryType action

CRITICAL: FIELD-TO-ENTRY-TYPE ASSOCIATIONS
When creating complete content structures (like "page builder section and matrix field"), you MUST:
1. Create the fields first
2. Create the entry types AND associate the fields with them
3. Create the sections last

NEVER create fields in isolation - they must be associated with entry types to be useful!

Example: If creating "page builder with matrix field":
- Create the matrix field (pageBuilder)
- Create the entry type (page) AND add the matrix field to it in the same operation
- Create the section (pages) with the entry type

ASSOCIATION PATTERNS:
- "Create X section with Y field" = Create field + Create entry type WITH field + Create section
- "Create X with matrix field" = Create matrix field + Create entry type WITH matrix field + Create section
- "Page builder" typically means: matrix field + entry type that uses the matrix field + section for pages

OPERATION STRUCTURE:
{
  "name": "Human-readable name for this operation set",
  "description": "What these operations accomplish",
  "operations": [
    {
      "type": "create|modify|delete",
      "target": "field|entryType|section",
      "targetId": "existingHandle", // For modify/delete operations
      "create": { /* Data for create operations */ },
      "modify": { /* Modifications for modify operations */ },
      "delete": { /* Options for delete operations */ }
    }
  ]
}

CREATE OPERATIONS:
- For fields: Include full field definition (name, handle, field_type, settings)
- For entryTypes: Include name, handle, hasTitleField, and field references (handle + required ONLY)
- For sections: Include name, handle, type, and entry type references

CRITICAL: Regular entry type creation MUST reference existing fields by handle only:
When creating entry types, fields MUST already exist. You cannot create fields inline.
Correct format for entry type fields:
{
  "fields": [
    {"handle": "existingFieldHandle", "required": true},  // ✓ CORRECT
    {"handle": "anotherField", "required": false}         // ✓ CORRECT
  ]
}
NOT this:
{
  "fields": [
    {"name": "Field Name", "handle": "...", "field_type": "..."}  // ✗ WRONG for regular entry types
  ]
}

MODIFY OPERATIONS:
- addField: Add existing or new field to an entry type
- removeField: Remove field from an entry type
- updateField: Update field settings
- updateSettings: Update section or entry type settings

Example for "Add a featured image to the blog post entry type":
{
  "name": "Add Featured Image to Blog",
  "description": "Adds a featured image field to the existing blog post entry type",
  "operations": [
    {
      "type": "create",
      "target": "field",
      "create": {
        "field": {
          "name": "Featured Image",
          "handle": "featuredImage",
          "field_type": "image",
          "instructions": "Main image for the blog post",
          "required": false,
          "settings": {
            "maxRelations": 1
          }
        }
      }
    },
    {
      "type": "modify",
      "target": "entryType",
      "targetId": "blogPost",
      "modify": {
        "actions": [
          {
            "action": "addField",
            "field": {
              "handle": "featuredImage",
              "required": false
            }
          }
        ]
      }
    }
  ]
}

CORRECT ENTRY TYPE CREATION PATTERN - Always create fields first:
Example: "Create a testimonial entry type with quote, author, and image fields"
{
  "name": "Testimonial Entry Type",
  "description": "Creates testimonial fields and entry type",
  "operations": [
    {
      "type": "create",
      "target": "field",
      "create": {
        "field": {
          "name": "Quote",
          "handle": "testimonialQuote",
          "field_type": "rich_text"
        }
      }
    },
    {
      "type": "create",
      "target": "field", 
      "create": {
        "field": {
          "name": "Author Name",
          "handle": "testimonialAuthor",
          "field_type": "plain_text",
          "settings": {"charLimit": 100}
        }
      }
    },
    {
      "type": "create",
      "target": "field",
      "create": {
        "field": {
          "name": "Author Photo",
          "handle": "testimonialPhoto",
          "field_type": "image",
          "settings": {"maxRelations": 1}
        }
      }
    },
    {
      "type": "create",
      "target": "entryType",
      "create": {
        "entryType": {
          "name": "Testimonial",
          "handle": "testimonial",
          "hasTitleField": false,
          "fields": [
            {"handle": "testimonialQuote", "required": true},     // ← References field created above
            {"handle": "testimonialAuthor", "required": true},    // ← References field created above
            {"handle": "testimonialPhoto", "required": false}     // ← References field created above
          ]
        }
      }
    }
  ]
}

FIELD REUSE EXAMPLE - If asked to "Create a news section similar to blog":
- Check which fields from blog can be reused (title, content, featuredImage)
- Only create new fields that are unique to news (e.g., publicationDate)
- Use modify operations to assign reused fields to the new entry type

COMPLETE PAGE BUILDER EXAMPLE - "Create a page builder section with matrix field":
{
  "name": "Page Builder System",
  "description": "Creates a complete page builder with matrix field for flexible content",
  "operations": [
    {
      "type": "create",
      "target": "field",
      "create": {
        "field": {
          "name": "Page Builder",
          "handle": "pageBuilder",
          "field_type": "matrix",
          "settings": {
            "entryTypes": [/* entry type definitions */]
          }
        }
      }
    },
    {
      "type": "create", 
      "target": "entryType",
      "create": {
        "entryType": {
          "name": "Page",
          "handle": "page",
          "hasTitleField": true,
          "fields": [
            {
              "handle": "pageBuilder",  // ← CRITICAL: Associate the field!
              "required": true
            }
          ]
        }
      }
    },
    {
      "type": "create",
      "target": "section", 
      "create": {
        "section": {
          "name": "Pages",
          "handle": "pages",
          "type": "channel",
          "entryTypes": [{"handle": "page"}]
        }
      }
    }
  ]
}

Remember: Be smart about field reuse, avoid duplication, maintain consistency across the project structure, and ALWAYS associate fields with entry types!
PROMPT;
    }

    /**
     * Format fields context for the prompt
     */
    private function formatFieldsContext(array $fieldsData): string
    {
        if (empty($fieldsData['fields'])) {
            return "No fields exist yet.";
        }

        $lines = [];
        foreach ($fieldsData['fields'] as $field) {
            $lines[] = "- {$field['handle']} ({$field['typeDisplay']}) - {$field['name']}";
        }

        return implode("\n", $lines);
    }

    /**
     * Format sections context for the prompt
     */
    private function formatSectionsContext(array $sectionsData): string
    {
        if (empty($sectionsData['sections'])) {
            return "No sections exist yet.";
        }

        $lines = [];
        foreach ($sectionsData['sections'] as $section) {
            $lines[] = "Section: {$section['name']} ({$section['handle']})";
            foreach ($section['entryTypes'] as $entryType) {
                $lines[] = "  - Entry Type: {$entryType['name']} ({$entryType['handle']})";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Validate operations response
     */
    private function validateOperations(array $response): array
    {
        $errors = [];

        if (!isset($response['operations']) || !is_array($response['operations'])) {
            $errors[] = 'Operations array is required';
            return ['valid' => false, 'errors' => $errors];
        }

        foreach ($response['operations'] as $i => $operation) {
            if (!isset($operation['type']) || !in_array($operation['type'], ['create', 'modify', 'delete'])) {
                $errors[] = "Operation $i: Invalid type";
            }

            if (!isset($operation['target']) || !in_array($operation['target'], ['field', 'entryType', 'section', 'categoryGroup', 'tagGroup'])) {
                $errors[] = "Operation $i: Invalid target";
            }

            // Validate based on operation type
            if ($operation['type'] === 'modify' || $operation['type'] === 'delete') {
                if (!isset($operation['targetId'])) {
                    $errors[] = "Operation $i: targetId required for {$operation['type']} operations";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Call Anthropic API (reuse from existing service)
     */
    private function callAnthropic(string $systemPrompt, string $userPrompt, array $schema, bool $debug): array
    {
        // Implementation would be similar to existing LLMIntegrationService
        // For now, we'll delegate to the existing service
        $plugin = Plugin::getInstance();
        return $plugin->llmIntegrationService->callAnthropic($systemPrompt, $userPrompt, $schema, $debug);
    }

    /**
     * Call OpenAI API with operations schema
     */
    private function callOpenAI(string $systemPrompt, string $userPrompt, array $schema, bool $debug): array
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
                    'name' => 'operations_configuration',
                    'schema' => $schema
                ]
            ],
            'max_tokens' => 4000,
            'temperature' => 0.1
        ];

        if ($debug) {
            $this->logDebug("=== OpenAI REQUEST ===");
            $this->logDebug("- Model: " . $payload['model']);
            $this->logDebug("- Schema Name: operations_configuration");
            $this->logDebug("- Schema Keys: " . implode(', ', array_keys($schema['properties'] ?? [])));
            $this->logDebug("- System Prompt Length: " . strlen($systemPrompt) . " chars");
            $this->logDebug("- User Prompt: " . $userPrompt);
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlError) {
            throw new Exception("cURL error: $curlError");
        }

        if ($debug) {
            $this->logDebug("=== OpenAI RESPONSE ===");
            $this->logDebug("- HTTP Code: $httpCode");
            $this->logDebug("- Response Body: $response");
        }

        if ($httpCode !== 200) {
            throw new Exception("HTTP request failed with code $httpCode: $response");
        }

        $data = json_decode($response, true);
        if (!$data) {
            throw new Exception("Invalid JSON response from OpenAI API");
        }

        if (isset($data['error'])) {
            if ($debug) {
                $this->logDebug("- Error Type: " . ($data['error']['type'] ?? 'unknown'));
                $this->logDebug("- Error Message: " . ($data['error']['message'] ?? 'no message'));
            }
            throw new Exception("OpenAI API error: " . ($data['error']['message'] ?? 'Unknown error'));
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new Exception("Unexpected OpenAI API response structure");
        }

        $content = $data['choices'][0]['message']['content'];
        $parsedContent = json_decode($content, true);

        if (!$parsedContent) {
            throw new Exception("Failed to parse OpenAI response as JSON: $content");
        }

        return $parsedContent;
    }

    /**
     * Get API key from environment or Craft config
     */
    private function getApiKey(string $keyName): ?string
    {
        // Try environment variable first
        $apiKey = $_ENV[$keyName] ?? getenv($keyName);
        if ($apiKey) {
            return $apiKey;
        }

        // Try Craft config system
        try {
            if ($keyName === 'OPENAI_API_KEY') {
                return \Craft::parseEnv('$OPENAI_API_KEY');
            } elseif ($keyName === 'ANTHROPIC_API_KEY') {
                return \Craft::parseEnv('$ANTHROPIC_API_KEY');
            }
        } catch (\Exception $e) {
            // Config parsing failed, return null
        }

        return null;
    }

    /**
     * Log debug information
     */
    private function logDebug(string $message): void
    {
        Craft::info("[field-agent-llm-operations] $message", __METHOD__);
        echo "[DEBUG] $message\n";
    }
}