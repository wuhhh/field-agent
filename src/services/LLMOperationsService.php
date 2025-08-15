<?php

namespace craftcms\fieldagent\services;

use craft\base\Component;
use craftcms\fieldagent\Plugin;
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
        try {
            // Get project context from discovery service
            $plugin = Plugin::getInstance();
            $context = $plugin->discoveryService->getProjectContext();

            // Generate the operations schema dynamically
            $schema = $this->generateOperationsSchema();

            // Generate the system prompt with context
            $systemPrompt = $this->buildOperationsSystemPrompt($context);

			// Integration service
			$llmService = $plugin->llmIntegrationService;

            if ($debug) {
                \Craft::info("=== LLM OPERATIONS REQUEST DEBUG ===", __METHOD__);
                \Craft::info("Provider: $provider", __METHOD__);
                \Craft::info("User Prompt: $prompt", __METHOD__);
                \Craft::info("Context Summary: " . $context['summary'], __METHOD__);
                \Craft::info("Existing Fields: " . $context['fields']['count'], __METHOD__);
                \Craft::info("Existing Sections: " . $context['sections']['count'], __METHOD__);
            }

            // Call the appropriate LLM provider
            $response = match ($provider) {
                self::PROVIDER_ANTHROPIC => $llmService->callAnthropic($systemPrompt, $prompt, $schema, $debug),
                self::PROVIDER_OPENAI => $llmService->callOpenAI($systemPrompt, $prompt, $schema, $debug),
                default => throw new Exception("Unsupported LLM provider: $provider")
            };

            if ($debug) {
                \Craft::info("=== LLM OPERATIONS RESPONSE DEBUG ===", __METHOD__);
                \Craft::info("Operations Count: " . count($response['operations'] ?? []), __METHOD__);
                foreach ($response['operations'] ?? [] as $i => $op) {
                    \Craft::info("  [$i] {$op['type']} {$op['target']}" . (isset($op['targetId']) ? " ({$op['targetId']})" : ''), __METHOD__);
                }
            }

            // Validate operations
            $validation = $this->validateOperations($response);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => "Operations validation failed: " . implode(', ', $validation['errors']),
                    'operations' => null
                ];
            }

            return [
                'success' => true,
                'operations' => $response['operations'] ?? $response,
                'error' => null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'operations' => null
            ];
        }
    }

    /**
     * Build system prompt for operations with context
     */
    private function buildOperationsSystemPrompt(array $context): string
    {
        // Format existing fields for the prompt
        $fieldsContext = $this->formatFieldsContext($context['fields']);
        $sectionsContext = $this->formatSectionsContext($context['sections']);
        $entryTypesContext = $this->formatEntryTypesContext($context['entryTypes']);
        $entryTypeFieldMappings = $this->formatEntryTypeFieldMappingsContext($context['entryTypeFieldMappings']);

        // Get reserved handles from Craft Field class
        $reservedHandles = \craft\base\Field::RESERVED_HANDLES;
        $reservedHandlesList = implode(',', $reservedHandles);

        // Get available field types from the registry
        $fieldTypesString = $this->getFieldTypesFromRegistry();

        return <<<PROMPT
You are an expert Craft CMS field configuration generator with awareness of existing project structures. Your task is to create JSON operation configurations that intelligently modify or extend the current project.

CURRENT PROJECT STATE:
{$context['summary']}

EXISTING FIELDS:
{$fieldsContext}

ALL ENTRY TYPES:
{$entryTypesContext}

SECTION -> ENTRY TYPE RELATIONS:
{$sectionsContext}

ENTRY TYPE -> FIELD RELATIONS:
{$entryTypeFieldMappings}

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

FIELD TYPES: {$fieldTypesString}

FIELD SETTINGS:
{$this->getFieldSettingsFromRegistry()}

NUMERIC FIELD SETTINGS - CRITICAL:
For number, money, and range fields, min/max values must be provided as actual numeric values, NOT percentages or decimals of the requested value.
Examples for NUMBER fields:
- User says "set min to 10" → use {"min": 10} NOT {"min": 0.10}
- User says "set max to 999" → use {"max": 999} NOT {"max": 9.99}
- User says "set min to 0 and max to 100" → use {"min": 0, "max": 100}
- User says "allow 2 decimal places" → use {"decimals": 2}

Examples for MONEY fields:
- User says "set minimum price to 100" → use {"min": 100} NOT {"min": 1.00}
- User says "set maximum to 5000" → use {"max": 5000} NOT {"max": 50.00}

Examples for RANGE fields:
- User says "range from 1 to 10" → use {"min": 1, "max": 10}
- User says "step by 5" → use {"step": 5}

IMPORTANT: The min/max values are the actual minimum and maximum values allowed in the field, not percentages, fractions, or converted decimal representations.

ENTRY TYPE COLORS:
Entry types can only use these predefined colors (use the lowercase name, not hex values):
red, orange, amber, yellow, lime, green, emerald, teal, cyan, sky, blue, indigo, violet, purple, fuchsia, pink, rose
Example: {"color": "emerald"} NOT {"color": "#10B981"} or {"color": "Emerald"}

CRITICAL RULES:
- Create categoryGroup/tagGroup BEFORE fields that use them
- ABSOLUTELY FORBIDDEN: NEVER use these reserved handles: {$reservedHandlesList}
- MANDATORY: If user requests any reserved handle name (like "icon", "title", "content"), you MUST automatically choose a suitable alternative (iconField, pageTitle, bodyContent, etc.)
- REQUIRED alternatives: title→pageTitle, content→bodyContent, author→writer, icon→iconField, id→identifier
- VALIDATION: Before creating any field, check if the handle is in the reserved list above and use an alternative if it is
- categories/tags need groups, multi_select for static options only

MATRIX FIELDS:
Matrix fields contain entry types (not "blocks"). Two approaches:
1. Inline definition: {name,handle,fields[{handle,field_type,name,required}]} in matrix settings.entryTypes
2. Reference existing: Use modify action "addMatrixEntryType" with entryTypeHandle

MATRIX ENTRY TYPE MODIFICATIONS:
- To modify fields in matrix entry types, use "modifyMatrixEntryType" action
- Target the matrix field, specify matrixEntryTypeHandle, then addFields/removeFields
- Example: Change videoBlock entry type in pageBuilder matrix field

COMPONENT PATTERNS:
"Component" requests = nested structures: individual entry type → matrix field → container entry type → add to page builder
Keywords: "cards/items/blocks" = collection pattern, "testimonials/gallery" = nested card pattern

COMMON OPERATIONS:
- Add field to entry type: modify→addField with {handle,required}
- Add entry type to matrix: modify→addMatrixEntryType with {name,handle,entryTypeHandle}

ASSOCIATION RULES:
- ALWAYS associate fields with entry types - never create isolated fields
- Order: Create fields → Create entry types WITH fields → Create sections
- "Create X section with Y field" = field + entry type with field + section
- Reuse existing fields when appropriate, avoid handle conflicts

OPERATION STRUCTURE: {name,description,operations[{type,target,targetId?,create?,modify?,delete?}]}

CREATE OPERATIONS MUST have wrapper objects:
- field: {"create":{"field":{name,handle,field_type,settings}}}
- entryType: {"create":{"entryType":{name,handle,hasTitleField,fields[{handle,required}]}}}
- section: {"create":{"section":{name,handle,type,entryTypes[handles]}}}
- tagGroup: {"create":{"tagGroup":{name,handle}}}
- categoryGroup: {"create":{"categoryGroup":{name,handle}}}

FIELD SETTINGS FORMATS:
- dropdown/radio_buttons/button_group: {"options":["value1","value2"]} NOT objects
- table: {"columns":[{"heading":"Name","handle":"name","type":"singleline"}],"maxRows":10}
- tags: {"sources":["groupHandle"],"maxRelations":5} NOT "source"

IMPORTANT: When modifying option-based fields (dropdown/button_group), if adding options, include ALL existing options plus new ones. Current options are not provided in context.

MODIFY ACTIONS: addField,removeField,updateField,updateSettings,addEntryType,removeEntryType,addMatrixEntryType
updateSettings: sections(name,uri,template,type), entryTypes(name,icon,color,description,hasTitleField)

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

        $fields = [];
        foreach ($fieldsData['fields'] as $field) {
            // Get the schema enum value from FieldService mapping
            $schemaType = $this->getSchemaFieldType($field['type']);
            $fields[] = "{$field['handle']}({$schemaType})";
        }

        return "F:" . implode(",", $fields);
    }

    /**
     * Convert Craft field class to schema enum value using FieldService mapping
     */
    private function getSchemaFieldType(string $fieldClass): string
    {
        // Use the FieldService mapping to find the schema type
        $fieldTypeMap = \craftcms\fieldagent\services\FieldService::FIELD_TYPE_MAP;

        // Search through the mapping to find the schema enum value
        foreach ($fieldTypeMap as $schemaType => $className) {
            if ($className === $fieldClass) {
                return $schemaType;
            }
        }

        return 'unknown';
    }

    /**
     * Format sections context for the prompt
     */
    private function formatSectionsContext(array $sectionsData): string
    {
        if (empty($sectionsData['sections'])) {
            return "No sections exist yet.";
        }

        $sections = [];
        foreach ($sectionsData['sections'] as $section) {
            $entryTypes = array_column($section['entryTypes'], 'handle');
            $sections[] = "S:{$section['handle']}>" . implode(",", $entryTypes);
        }

        return implode(" | ", $sections);
    }

    /**
     * Format entry types context for the prompt
     */
    private function formatEntryTypesContext(array $entryTypesData): string
    {
        if (empty($entryTypesData['entryTypes'])) {
            return "No entry types exist yet.";
        }

        $entryTypes = [];
        foreach ($entryTypesData['entryTypes'] as $entryType) {
            $entryTypeStr = $entryType['handle'] . '(' . $entryType['name'] . ')';
            if (!empty($entryType['icon'])) {
                $entryTypeStr .= ' [icon:' . $entryType['icon'] . ']';
            }
            if (!empty($entryType['color'])) {
                $entryTypeStr .= ' [color:' . $entryType['color'] . ']';
            }
            $entryTypes[] = $entryTypeStr;
        }

        return 'ET:' . implode(',', $entryTypes);
    }

    /**
     * Format entry type field mappings context for the prompt
     */
    private function formatEntryTypeFieldMappingsContext(array $entryTypeFieldMappings): string
    {
        if (empty($entryTypeFieldMappings)) {
            return "No entry type field mappings exist yet.";
        }

        $mappings = [];
        foreach ($entryTypeFieldMappings as $mapping) {
            $entryType = $mapping['entryType'];
            $fields = $mapping['fields'];

            if (empty($fields)) {
                $fieldList = "none";
            } else {
                $fieldHandles = [];
                foreach ($fields as $field) {
                    $req = $field['required'] ? '!' : '';
                    $fieldHandles[] = $field['handle'] . $req;
                }
                $fieldList = implode(",", $fieldHandles);
            }

            $mappings[] = "ET:{$entryType['handle']}({$fieldList})";
        }

        return implode(" | ", $mappings);
    }

    /**
     * Validate a configuration array against the operations schema
     */
    public function validateOperations(array $config): array
    {
        // Quick validation that operations array exists
        if (!isset($config['operations']) || !is_array($config['operations'])) {
            return [
                'valid' => false,
                'errors' => ['Missing required "operations" array']
            ];
        }

        // Validate each operation
        $errors = [];
        foreach ($config['operations'] as $index => $operation) {
            $opErrors = $this->validateOperation($operation, $index);
            $errors = array_merge($errors, $opErrors);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

	/**
     * Validate a single operation
     */
    private function validateOperation(array $operation, int $index): array
    {
        $errors = [];
        $opPath = "operations[$index]";

        // Required fields
        if (!isset($operation['type'])) {
            $errors[] = "$opPath.type: Required field missing";
        } elseif (!in_array($operation['type'], ['create', 'modify', 'delete'])) {
            $errors[] = "$opPath.type: Must be 'create', 'modify', or 'delete'";
        }

        if (!isset($operation['target'])) {
            $errors[] = "$opPath.target: Required field missing";
        } elseif (!in_array($operation['target'], ['field', 'entryType', 'section', 'categoryGroup', 'tagGroup'])) {
            $errors[] = "$opPath.target: Must be 'field', 'entryType', 'section', 'categoryGroup', or 'tagGroup'";
        }

        // Type-specific validation
        if (isset($operation['type']) && isset($operation['target'])) {
            switch ($operation['type']) {
                case 'create':
                    if (!isset($operation['create'])) {
                        $errors[] = "$opPath.create: Required for create operations";
                    }
                    break;

                case 'modify':
                    if (!isset($operation['targetId'])) {
                        $errors[] = "$opPath.targetId: Required for modify operations";
                    }
                    if (!isset($operation['modify'])) {
                        $errors[] = "$opPath.modify: Required for modify operations";
                    }
                    break;

                case 'delete':
                    if (!isset($operation['targetId'])) {
                        $errors[] = "$opPath.targetId: Required for delete operations";
                    }
                    break;
            }
        }

        return $errors;
    }

    /**
     * Export prompt and schema for manual testing
     */
    public function exportPromptAndSchema(): array
    {
        try {
            // Get project context
            $plugin = Plugin::getInstance();
            $context = $plugin->discoveryService->getProjectContext();

            // Load schema
            $schemaPath = $plugin->getBasePath() . '/schemas/llm-operations-schema.json';
            if (!file_exists($schemaPath)) {
                throw new Exception("Operations schema file not found at: $schemaPath");
            }

            $schema = $this->generateOperationsSchema();

            // Generate system prompt
            $systemPrompt = $this->buildOperationsSystemPrompt($context);

            return [
                'success' => true,
                'systemPrompt' => $systemPrompt,
                'schema' => $schema,
                'context' => $context,
                'exampleUserPrompt' => "Create a blog section with title, content, author, and featured image fields"
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate the operations schema dynamically with current field types
     */
    private function generateOperationsSchema(): array
    {
        // Load the base schema template
        $schemaPath = Plugin::getInstance()->getBasePath() . '/schemas/llm-operations-schema.json';
        if (!file_exists($schemaPath)) {
            throw new Exception("Operations schema file not found: $schemaPath");
        }

        $schema = json_decode(file_get_contents($schemaPath), true);
        if (!$schema) {
            throw new Exception("Invalid JSON operations schema file");
        }

        // Inject dynamic field types from the registry
        $fieldTypes = $this->getFieldTypesArrayFromRegistry();
        $schema['properties']['operations']['items']['properties']['create']['properties']['field']['properties']['field_type']['enum'] = $fieldTypes;

        return $schema;
    }

    /**
     * Get field types as a comma-separated string from the registry
     */
    private function getFieldTypesFromRegistry(): string
    {
        try {
            $registry = Plugin::getInstance()->fieldRegistryService;
            $fieldTypes = $registry->getFieldTypes();
            sort($fieldTypes);
            return implode(',', $fieldTypes);
        } catch (\Exception $e) {
            // Fallback to old method if registry not available
            return \craftcms\fieldagent\services\FieldService::getFieldTypesString();
        }
    }

    /**
     * Get field types as an array from the registry
     */
    private function getFieldTypesArrayFromRegistry(): array
    {
        try {
            $registry = Plugin::getInstance()->fieldRegistryService;
            $fieldTypes = $registry->getFieldTypes();
            sort($fieldTypes);
            return $fieldTypes;
        } catch (\Exception $e) {
            // Fallback to old method if registry not available
            return \craftcms\fieldagent\services\FieldService::getAvailableFieldTypes();
        }
    }

    /**
     * Get field settings documentation from the registry
     */
    private function getFieldSettingsFromRegistry(): string
    {
        try {
            $registry = Plugin::getInstance()->fieldRegistryService;
            return $registry->generateLLMDocumentation();
        } catch (\Exception $e) {
            // Fallback to old hardcoded documentation
            return "plain_text:multiline,charLimit | rich_text:none | link:types,sources | image:maxRelations,minRelations | asset:maxRelations,minRelations | dropdown:options | number:decimals,min,max,prefix,suffix | money:currency | categories:maxRelations,sources | tags:sources | matrix:entryTypes | lightswitch:default | date:showDate,showTime";
        }
    }

}
