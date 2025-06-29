<?php

namespace craftcms\fieldagent\services;

use Craft;
use craft\base\Component;
use craftcms\fieldagent\Plugin;
use yii\base\Exception;

/**
 * Schema Validation service for validating JSON configurations
 */
class SchemaValidationService extends Component
{
    /**
     * Validate a configuration array against the LLM output schema
     */
    public function validateLLMOutput(array $config): array
    {
        $schemaPath = Plugin::getInstance()->getBasePath() . '/schemas/llm-output-schema-v2.json';
        return $this->validateAgainstSchema($config, $schemaPath);
    }

    /**
     * Validate a configuration array against a JSON schema file
     */
    public function validateAgainstSchema(array $config, string $schemaPath): array
    {
        if (!file_exists($schemaPath)) {
            return [
                'valid' => false,
                'errors' => ["Schema file not found: $schemaPath"]
            ];
        }

        $schemaData = json_decode(file_get_contents($schemaPath), true);
        if (!$schemaData) {
            return [
                'valid' => false,
                'errors' => ["Invalid JSON in schema file: $schemaPath"]
            ];
        }

        // Basic validation without external JSON Schema library for now
        // In production, you'd want to use a proper JSON Schema validator like:
        // - justinrainbow/json-schema
        // - opis/json-schema
        
        return $this->performBasicValidation($config, $schemaData);
    }

    /**
     * Perform basic validation against schema (simplified implementation)
     * This is a basic implementation - for production use a proper JSON Schema library
     */
    private function performBasicValidation(array $config, array $schema): array
    {
        $errors = [];

        // Check required properties
        if (isset($schema['required'])) {
            foreach ($schema['required'] as $required) {
                if (!isset($config[$required])) {
                    $errors[] = "Missing required property: $required";
                }
            }
        }

        // Validate fields array
        if (isset($config['fields'])) {
            $fieldsErrors = $this->validateFields($config['fields']);
            $errors = array_merge($errors, $fieldsErrors);
        }

        // Validate entry types if present
        if (isset($config['entryTypes'])) {
            $entryTypesErrors = $this->validateEntryTypes($config['entryTypes']);
            $errors = array_merge($errors, $entryTypesErrors);
        }

        // Validate sections if present
        if (isset($config['sections'])) {
            $sectionsErrors = $this->validateSections($config['sections']);
            $errors = array_merge($errors, $sectionsErrors);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate fields array
     */
    private function validateFields(array $fields): array
    {
        $errors = [];
        $allowedFieldTypes = [
            'plain_text', 'rich_text', 'image', 'number', 'link', 'dropdown', 'lightswitch',
            'email', 'date', 'time', 'color', 'money', 'range', 'radio_buttons', 'checkboxes', 
            'multi_select', 'country', 'button_group', 'icon', 'asset', 'matrix', 'users', 'entries'
        ];
        $handles = [];

        foreach ($fields as $index => $field) {
            $fieldPath = "fields[$index]";

            // Check required properties
            if (!isset($field['name']) || empty($field['name'])) {
                $errors[] = "$fieldPath: Missing required property 'name'";
            }

            if (!isset($field['handle']) || empty($field['handle'])) {
                $errors[] = "$fieldPath: Missing required property 'handle'";
            } else {
                // Check handle format
                if (!preg_match('/^[a-z][a-zA-Z0-9]*$/', $field['handle'])) {
                    $errors[] = "$fieldPath: Handle must be camelCase starting with lowercase letter";
                }

                // Check for duplicate handles
                if (in_array($field['handle'], $handles)) {
                    $errors[] = "$fieldPath: Duplicate handle '{$field['handle']}'";
                }
                $handles[] = $field['handle'];
            }

            if (!isset($field['field_type']) || empty($field['field_type'])) {
                $errors[] = "$fieldPath: Missing required property 'field_type'";
            } elseif (!in_array($field['field_type'], $allowedFieldTypes)) {
                $errors[] = "$fieldPath: Invalid field_type '{$field['field_type']}'. Allowed types: " . implode(', ', $allowedFieldTypes);
            }

            // Validate field-specific settings
            if (isset($field['settings'])) {
                $settingsErrors = $this->validateFieldSettings($field['field_type'], $field['settings'], $fieldPath);
                $errors = array_merge($errors, $settingsErrors);
            }
        }

        return $errors;
    }

    /**
     * Validate field type specific settings
     */
    private function validateFieldSettings(string $fieldType, array $settings, string $fieldPath): array
    {
        $errors = [];

        switch ($fieldType) {
            case 'plain_text':
                if (isset($settings['charLimit']) && (!is_int($settings['charLimit']) || $settings['charLimit'] < 1 || $settings['charLimit'] > 10000)) {
                    $errors[] = "$fieldPath.settings.charLimit: Must be an integer between 1 and 10000";
                }
                break;

            case 'image':
                if (isset($settings['maxRelations']) && (!is_int($settings['maxRelations']) || $settings['maxRelations'] < 1 || $settings['maxRelations'] > 10)) {
                    $errors[] = "$fieldPath.settings.maxRelations: Must be an integer between 1 and 10";
                }
                break;

            case 'number':
                if (isset($settings['decimals']) && (!is_int($settings['decimals']) || $settings['decimals'] < 0 || $settings['decimals'] > 4)) {
                    $errors[] = "$fieldPath.settings.decimals: Must be an integer between 0 and 4";
                }
                if (isset($settings['min']) && !is_numeric($settings['min'])) {
                    $errors[] = "$fieldPath.settings.min: Must be a number";
                }
                if (isset($settings['max']) && !is_numeric($settings['max'])) {
                    $errors[] = "$fieldPath.settings.max: Must be a number";
                }
                break;

            case 'dropdown':
            case 'radio_buttons':
            case 'checkboxes':
            case 'multi_select':
            case 'button_group':
                if (!isset($settings['options']) || !is_array($settings['options']) || empty($settings['options'])) {
                    $errors[] = "$fieldPath.settings.options: Required array of options for $fieldType field";
                }
                break;

            case 'asset':
                if (isset($settings['maxRelations']) && (!is_int($settings['maxRelations']) || $settings['maxRelations'] < 1 || $settings['maxRelations'] > 10)) {
                    $errors[] = "$fieldPath.settings.maxRelations: Must be an integer between 1 and 10";
                }
                break;

            case 'money':
                if (isset($settings['currency']) && (!is_string($settings['currency']) || strlen($settings['currency']) > 10)) {
                    $errors[] = "$fieldPath.settings.currency: Must be a string with max 10 characters";
                }
                if (isset($settings['min']) && !is_numeric($settings['min'])) {
                    $errors[] = "$fieldPath.settings.min: Must be a number";
                }
                if (isset($settings['max']) && !is_numeric($settings['max'])) {
                    $errors[] = "$fieldPath.settings.max: Must be a number";
                }
                break;

            case 'range':
                if (isset($settings['min']) && !is_numeric($settings['min'])) {
                    $errors[] = "$fieldPath.settings.min: Must be a number";
                }
                if (isset($settings['max']) && !is_numeric($settings['max'])) {
                    $errors[] = "$fieldPath.settings.max: Must be a number";
                }
                if (isset($settings['step']) && (!is_numeric($settings['step']) || $settings['step'] < 0.01)) {
                    $errors[] = "$fieldPath.settings.step: Must be a number >= 0.01";
                }
                break;

            case 'email':
                if (isset($settings['placeholder']) && (!is_string($settings['placeholder']) || strlen($settings['placeholder']) > 100)) {
                    $errors[] = "$fieldPath.settings.placeholder: Must be a string with max 100 characters";
                }
                break;

            case 'date':
                if (isset($settings['showDate']) && !is_bool($settings['showDate'])) {
                    $errors[] = "$fieldPath.settings.showDate: Must be a boolean";
                }
                if (isset($settings['showTime']) && !is_bool($settings['showTime'])) {
                    $errors[] = "$fieldPath.settings.showTime: Must be a boolean";
                }
                if (isset($settings['showTimeZone']) && !is_bool($settings['showTimeZone'])) {
                    $errors[] = "$fieldPath.settings.showTimeZone: Must be a boolean";
                }
                break;

            case 'color':
                if (isset($settings['allowCustomColors']) && !is_bool($settings['allowCustomColors'])) {
                    $errors[] = "$fieldPath.settings.allowCustomColors: Must be a boolean";
                }
                break;

            case 'lightswitch':
                if (isset($settings['default']) && !is_bool($settings['default'])) {
                    $errors[] = "$fieldPath.settings.default: Must be a boolean";
                }
                if (isset($settings['onLabel']) && (!is_string($settings['onLabel']) || strlen($settings['onLabel']) > 20)) {
                    $errors[] = "$fieldPath.settings.onLabel: Must be a string with max 20 characters";
                }
                if (isset($settings['offLabel']) && (!is_string($settings['offLabel']) || strlen($settings['offLabel']) > 20)) {
                    $errors[] = "$fieldPath.settings.offLabel: Must be a string with max 20 characters";
                }
                break;

            case 'link':
                if (isset($settings['types'])) {
                    if (!is_array($settings['types']) || empty($settings['types'])) {
                        $errors[] = "$fieldPath.settings.types: Must be a non-empty array";
                    } else {
                        $allowedLinkTypes = ['url', 'entry'];
                        foreach ($settings['types'] as $type) {
                            if (!in_array($type, $allowedLinkTypes)) {
                                $errors[] = "$fieldPath.settings.types: Invalid link type '$type'. Allowed: " . implode(', ', $allowedLinkTypes);
                            }
                        }
                    }
                }
                if (isset($settings['sources']) && !is_array($settings['sources'])) {
                    $errors[] = "$fieldPath.settings.sources: Must be an array of section handles";
                }
                if (isset($settings['showLabelField']) && !is_bool($settings['showLabelField'])) {
                    $errors[] = "$fieldPath.settings.showLabelField: Must be a boolean";
                }
                break;

            case 'matrix':
                if (isset($settings['minEntries']) && (!is_int($settings['minEntries']) || $settings['minEntries'] < 0 || $settings['minEntries'] > 100)) {
                    $errors[] = "$fieldPath.settings.minEntries: Must be an integer between 0 and 100";
                }
                if (isset($settings['maxEntries']) && $settings['maxEntries'] !== null && (!is_int($settings['maxEntries']) || $settings['maxEntries'] < 1 || $settings['maxEntries'] > 100)) {
                    $errors[] = "$fieldPath.settings.maxEntries: Must be an integer between 1 and 100 or null";
                }
                if (isset($settings['viewMode']) && !in_array($settings['viewMode'], ['cards', 'blocks', 'index'])) {
                    $errors[] = "$fieldPath.settings.viewMode: Must be 'cards', 'blocks', or 'index'";
                }
                if (!isset($settings['blockTypes']) || !is_array($settings['blockTypes']) || empty($settings['blockTypes'])) {
                    $errors[] = "$fieldPath.settings.blockTypes: Required array of block type definitions";
                } else {
                    $blockTypeErrors = $this->validateMatrixBlockTypes($settings['blockTypes'], $fieldPath);
                    $errors = array_merge($errors, $blockTypeErrors);
                }
                break;

            case 'users':
            case 'entries':
                if (isset($settings['maxRelations']) && (!is_int($settings['maxRelations']) || $settings['maxRelations'] < 1 || $settings['maxRelations'] > 10)) {
                    $errors[] = "$fieldPath.settings.maxRelations: Must be an integer between 1 and 10";
                }
                if (isset($settings['sources']) && !is_array($settings['sources'])) {
                    $errors[] = "$fieldPath.settings.sources: Must be an array of source handles";
                }
                break;

            // Field types that don't require specific settings validation
            case 'time':
            case 'country':
            case 'icon':
                break;
        }

        return $errors;
    }

    /**
     * Validate matrix block types
     */
    private function validateMatrixBlockTypes(array $blockTypes, string $fieldPath): array
    {
        $errors = [];
        $allowedBlockFieldTypes = [
            'plain_text', 'rich_text', 'image', 'number', 'email', 'date',
            'lightswitch', 'dropdown', 'asset'
        ];
        $blockHandles = [];

        foreach ($blockTypes as $index => $blockType) {
            $blockPath = "$fieldPath.settings.blockTypes[$index]";

            // Validate required properties
            if (!isset($blockType['name']) || empty($blockType['name'])) {
                $errors[] = "$blockPath: Missing required property 'name'";
            }

            if (!isset($blockType['handle']) || empty($blockType['handle'])) {
                $errors[] = "$blockPath: Missing required property 'handle'";
            } else {
                // Check handle format
                if (!preg_match('/^[a-z][a-zA-Z0-9]*$/', $blockType['handle'])) {
                    $errors[] = "$blockPath: Handle must be camelCase starting with lowercase letter";
                }

                // Check for duplicate handles
                if (in_array($blockType['handle'], $blockHandles)) {
                    $errors[] = "$blockPath: Duplicate block type handle '{$blockType['handle']}'";
                }
                $blockHandles[] = $blockType['handle'];
            }

            // Validate fields array
            if (!isset($blockType['fields']) || !is_array($blockType['fields']) || empty($blockType['fields'])) {
                $errors[] = "$blockPath: Required 'fields' array is missing or empty";
            } else {
                $fieldHandles = [];
                foreach ($blockType['fields'] as $fieldIndex => $field) {
                    $blockFieldPath = "$blockPath.fields[$fieldIndex]";

                    // Validate required properties
                    if (!isset($field['name']) || empty($field['name'])) {
                        $errors[] = "$blockFieldPath: Missing required property 'name'";
                    }

                    if (!isset($field['handle']) || empty($field['handle'])) {
                        $errors[] = "$blockFieldPath: Missing required property 'handle'";
                    } else {
                        // Check handle format
                        if (!preg_match('/^[a-z][a-zA-Z0-9]*$/', $field['handle'])) {
                            $errors[] = "$blockFieldPath: Handle must be camelCase starting with lowercase letter";
                        }

                        // Check for duplicate handles within block
                        if (in_array($field['handle'], $fieldHandles)) {
                            $errors[] = "$blockFieldPath: Duplicate field handle '{$field['handle']}' within block type";
                        }
                        $fieldHandles[] = $field['handle'];
                    }

                    if (!isset($field['field_type']) || empty($field['field_type'])) {
                        $errors[] = "$blockFieldPath: Missing required property 'field_type'";
                    } elseif (!in_array($field['field_type'], $allowedBlockFieldTypes)) {
                        $errors[] = "$blockFieldPath: Invalid field_type '{$field['field_type']}' for block fields. Allowed types: " . implode(', ', $allowedBlockFieldTypes);
                    }

                    // Validate field settings if present
                    if (isset($field['settings'])) {
                        $settingsErrors = $this->validateFieldSettings($field['field_type'], $field['settings'], $blockFieldPath);
                        $errors = array_merge($errors, $settingsErrors);
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Validate entry types array
     */
    private function validateEntryTypes(array $entryTypes): array
    {
        $errors = [];
        $handles = [];

        foreach ($entryTypes as $index => $entryType) {
            $entryTypePath = "entryTypes[$index]";

            // Check required properties
            if (!isset($entryType['name']) || empty($entryType['name'])) {
                $errors[] = "$entryTypePath: Missing required property 'name'";
            }

            if (!isset($entryType['handle']) || empty($entryType['handle'])) {
                $errors[] = "$entryTypePath: Missing required property 'handle'";
            } else {
                if (!preg_match('/^[a-z][a-zA-Z0-9]*$/', $entryType['handle'])) {
                    $errors[] = "$entryTypePath: Handle must be camelCase starting with lowercase letter";
                }

                if (in_array($entryType['handle'], $handles)) {
                    $errors[] = "$entryTypePath: Duplicate handle '{$entryType['handle']}'";
                }
                $handles[] = $entryType['handle'];
            }

            if (!isset($entryType['fields']) || !is_array($entryType['fields'])) {
                $errors[] = "$entryTypePath: Missing required property 'fields' (array)";
            }
        }

        return $errors;
    }

    /**
     * Validate sections array
     */
    private function validateSections(array $sections): array
    {
        $errors = [];
        $handles = [];
        $allowedSectionTypes = ['single', 'channel', 'structure'];

        foreach ($sections as $index => $section) {
            $sectionPath = "sections[$index]";

            // Check required properties
            if (!isset($section['name']) || empty($section['name'])) {
                $errors[] = "$sectionPath: Missing required property 'name'";
            }

            if (!isset($section['handle']) || empty($section['handle'])) {
                $errors[] = "$sectionPath: Missing required property 'handle'";
            } else {
                if (!preg_match('/^[a-z][a-zA-Z0-9]*$/', $section['handle'])) {
                    $errors[] = "$sectionPath: Handle must be camelCase starting with lowercase letter";
                }

                if (in_array($section['handle'], $handles)) {
                    $errors[] = "$sectionPath: Duplicate handle '{$section['handle']}'";
                }
                $handles[] = $section['handle'];
            }

            if (isset($section['type']) && !in_array($section['type'], $allowedSectionTypes)) {
                $errors[] = "$sectionPath: Invalid section type '{$section['type']}'. Allowed types: " . implode(', ', $allowedSectionTypes);
            }

            if (!isset($section['entryTypes']) || !is_array($section['entryTypes']) || empty($section['entryTypes'])) {
                $errors[] = "$sectionPath: Missing required property 'entryTypes' (non-empty array)";
            }
        }

        return $errors;
    }

    /**
     * Clean and normalize configuration for compatibility with existing system
     */
    public function normalizeConfig(array $config): array
    {
        // Ensure all fields have proper structure for existing system
        if (isset($config['fields'])) {
            foreach ($config['fields'] as &$field) {
                // Move settings to root level for compatibility with existing system
                if (isset($field['settings'])) {
                    foreach ($field['settings'] as $key => $value) {
                        $field[$key] = $value;
                    }
                    unset($field['settings']);
                }

                // Set defaults
                $field['required'] = $field['required'] ?? false;
                $field['searchable'] = $field['searchable'] ?? true;
                $field['instructions'] = $field['instructions'] ?? '';
            }
        }

        return $config;
    }
}