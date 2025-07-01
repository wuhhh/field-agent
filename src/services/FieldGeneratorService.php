<?php

namespace craftcms\fieldagent\services;

use Craft;
use craft\base\Component;
use craftcms\fieldagent\Plugin;
use yii\base\Exception;

/**
 * Field Generator service
 */
class FieldGeneratorService extends Component
{
    /**
     * @var array Tracks block fields created during matrix field creation
     */
    private array $createdBlockFields = [];

    /**
     * @var array Tracks block entry types created during matrix field creation
     */
    private array $createdBlockEntryTypes = [];

    /**
     * Store configuration data to plugin storage
     */
    public function storeConfig(string $name, array $data): string
    {
        $plugin = Plugin::getInstance();
        $plugin->ensureStorageDirectory();
        
        $configPath = $plugin->getStoragePath() . DIRECTORY_SEPARATOR . 'configs';
        if (!is_dir($configPath)) {
            mkdir($configPath, 0755, true);
        }

        $filename = $this->sanitizeFilename($name) . '_' . time() . '.json';
        $filepath = $configPath . DIRECTORY_SEPARATOR . $filename;

        if (file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT)) === false) {
            throw new Exception("Failed to write config file: $filepath");
        }

        $this->cleanupOldConfigs($configPath);

        return $filepath;
    }

    /**
     * Get stored configuration
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
     * List all stored configurations
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
     * Create a field from config array
     * This method handles the normalized config format from LLM responses
     */
    public function createFieldFromConfig(array $config)
    {
        // Normalize the config to flatten settings
        $normalizedConfig = $this->normalizeFieldConfig($config);

        $fieldType = $normalizedConfig['field_type'] ?? '';
        $field = null;

        switch ($fieldType) {
            case 'plain_text':
            case 'text':
                $field = new \craft\fields\PlainText();
                $field->multiline = $normalizedConfig['multiline'] ?? false;
                $field->initialRows = $field->multiline ? 4 : 1;
                if (isset($normalizedConfig['charLimit'])) {
                    $field->charLimit = $normalizedConfig['charLimit'];
                }
                break;

            case 'rich_text':
            case 'richtext':
                if (class_exists(\craft\ckeditor\Field::class)) {
                    $field = new \craft\ckeditor\Field();
                    $field->purifyHtml = true;
                } else {
                    throw new Exception("CKEditor plugin not installed, cannot create rich text field");
                }
                break;

            case 'image':
                $field = new \craft\fields\Assets();
                $field->allowedKinds = ['image'];
                $field->maxRelations = $normalizedConfig['maxRelations'] ?? 1;
                $field->viewMode = 'list';
                break;

            case 'asset':
                $field = new \craft\fields\Assets();
                $field->maxRelations = $normalizedConfig['maxRelations'] ?? 1;
                $field->viewMode = 'list';
                break;

            case 'number':
                $field = new \craft\fields\Number();
                if (isset($normalizedConfig['decimals'])) {
                    $field->decimals = $normalizedConfig['decimals'];
                }
                if (isset($normalizedConfig['min'])) {
                    $field->min = $normalizedConfig['min'];
                }
                if (isset($normalizedConfig['max'])) {
                    $field->max = $normalizedConfig['max'];
                }
                if (isset($normalizedConfig['suffix'])) {
                    $field->suffix = $normalizedConfig['suffix'];
                }
                break;

            case 'link':
                $field = new \craft\fields\Link();
                
                // Set enabled link types (default to url only for backward compatibility)
                $field->types = $normalizedConfig['types'] ?? ['url'];
                
                // Configure display options
                $field->showLabelField = $normalizedConfig['showLabelField'] ?? true;
                $field->maxLength = 255;
                
                // Configure sources for entry links
                $entrySources = '*'; // Default to all sources
                if (isset($normalizedConfig['sources']) && is_array($normalizedConfig['sources'])) {
                    // Convert section handles to section UIDs for sources configuration
                    $entriesService = \Craft::$app->getEntries();
                    $sources = [];
                    foreach ($normalizedConfig['sources'] as $sectionHandle) {
                        $section = $entriesService->getSectionByHandle($sectionHandle);
                        if ($section) {
                            $sources[] = 'section:' . $section->uid;
                        }
                    }
                    if (!empty($sources)) {
                        $entrySources = $sources;
                    }
                }
                
                // Configure type-specific settings
                $typeSettings = [];
                
                // URL type settings
                if (in_array('url', $field->types)) {
                    $typeSettings['url'] = [
                        'allowRootRelativeUrls' => $normalizedConfig['allowRootRelativeUrls'] ?? true,
                        'allowAnchors' => $normalizedConfig['allowAnchors'] ?? true,
                        'allowCustomSchemes' => $normalizedConfig['allowCustomSchemes'] ?? false,
                    ];
                }
                
                // Entry type settings
                if (in_array('entry', $field->types)) {
                    $typeSettings['entry'] = [
                        'sources' => $entrySources,
                    ];
                }
                
                if (!empty($typeSettings)) {
                    $field->typeSettings = $typeSettings;
                }
                break;

            case 'email':
                $field = new \craft\fields\Email();
                if (isset($normalizedConfig['placeholder'])) {
                    $field->placeholder = $normalizedConfig['placeholder'];
                }
                break;

            case 'date':
                $field = new \craft\fields\Date();
                $field->showTimeZone = $normalizedConfig['showTimeZone'] ?? false;
                $field->showDate = $normalizedConfig['showDate'] ?? true;
                $field->showTime = $normalizedConfig['showTime'] ?? false;
                break;

            case 'time':
                $field = new \craft\fields\Time();
                break;

            case 'color':
                $field = new \craft\fields\Color();
                $field->allowCustomColors = $normalizedConfig['allowCustomColors'] ?? true;
                if (isset($normalizedConfig['palette'])) {
                    $field->palette = $normalizedConfig['palette'];
                } else {
                    // Default palette
                    $field->palette = [
                        ['color' => '#ff0000', 'label' => 'Red'],
                        ['color' => '#00ff00', 'label' => 'Green'],
                        ['color' => '#0000ff', 'label' => 'Blue'],
                        ['color' => '#ffff00', 'label' => 'Yellow'],
                        ['color' => '#ff00ff', 'label' => 'Magenta'],
                        ['color' => '#00ffff', 'label' => 'Cyan'],
                    ];
                }
                break;

            case 'money':
                $field = new \craft\fields\Money();
                $field->currency = $normalizedConfig['currency'] ?? 'USD';
                $field->showCurrency = $normalizedConfig['showCurrency'] ?? true;
                if (isset($normalizedConfig['min'])) {
                    $field->min = $normalizedConfig['min'];
                }
                if (isset($normalizedConfig['max'])) {
                    $field->max = $normalizedConfig['max'];
                }
                break;

            case 'range':
                $field = new \craft\fields\Range();
                $field->min = $normalizedConfig['min'] ?? 0;
                $field->max = $normalizedConfig['max'] ?? 100;
                $field->step = $normalizedConfig['step'] ?? 1;
                if (isset($normalizedConfig['suffix'])) {
                    $field->suffix = $normalizedConfig['suffix'];
                }
                break;

            case 'dropdown':
                $field = new \craft\fields\Dropdown();
                $field->options = $this->prepareOptions($normalizedConfig['options'] ?? []);
                break;

            case 'radio_buttons':
            case 'radio':
                $field = new \craft\fields\RadioButtons();
                $field->options = $this->prepareOptions($normalizedConfig['options'] ?? []);
                break;

            case 'checkboxes':
                $field = new \craft\fields\Checkboxes();
                $field->options = $this->prepareOptions($normalizedConfig['options'] ?? []);
                break;

            case 'multi_select':
            case 'multiselect':
                $field = new \craft\fields\MultiSelect();
                $field->options = $this->prepareOptions($normalizedConfig['options'] ?? []);
                break;

            case 'country':
                $field = new \craft\fields\Country();
                break;

            case 'button_group':
            case 'buttongroup':
                $field = new \craft\fields\ButtonGroup();
                $field->options = $this->prepareButtonGroupOptions($normalizedConfig['options'] ?? []);
                break;

            case 'icon':
                $field = new \craft\fields\Icon();
                break;

            case 'lightswitch':
            case 'toggle':
                $field = new \craft\fields\Lightswitch();
                if (isset($normalizedConfig['default'])) {
                    $field->default = $normalizedConfig['default'];
                }
                if (isset($normalizedConfig['onLabel'])) {
                    $field->onLabel = $normalizedConfig['onLabel'];
                }
                if (isset($normalizedConfig['offLabel'])) {
                    $field->offLabel = $normalizedConfig['offLabel'];
                }
                break;

            case 'matrix':
                $field = new \craft\fields\Matrix();
                $field->minEntries = $normalizedConfig['minEntries'] ?? 1;
                $field->maxEntries = $normalizedConfig['maxEntries'] ?? null;
                $field->viewMode = match($normalizedConfig['viewMode'] ?? 'cards') {
                    'blocks' => \craft\fields\Matrix::VIEW_MODE_BLOCKS,  
                    'index' => \craft\fields\Matrix::VIEW_MODE_INDEX,
                    default => \craft\fields\Matrix::VIEW_MODE_CARDS,
                };
                $field->propagationMethod = \craft\enums\PropagationMethod::All;
                
                // Create and associate entry types
                if (isset($normalizedConfig['entryTypes']) && is_array($normalizedConfig['entryTypes'])) {
                    $entryTypes = $this->createMatrixBlockTypes($normalizedConfig['entryTypes']);
                    $field->setEntryTypes($entryTypes);
                }
                break;

            case 'users':
                $field = new \craft\fields\Users();
                $field->maxRelations = $normalizedConfig['maxRelations'] ?? 1;
                $field->viewMode = 'list';
                
                // Configure sources (user groups)
                if (isset($normalizedConfig['sources']) && is_array($normalizedConfig['sources'])) {
                    $userService = \Craft::$app->getUsers();
                    $sources = [];
                    foreach ($normalizedConfig['sources'] as $groupHandle) {
                        if ($groupHandle === '*') {
                            // Allow all users
                            $sources = '*';
                            break;
                        }
                        $group = $userService->getGroupByHandle($groupHandle);
                        if ($group) {
                            $sources[] = 'group:' . $group->uid;
                        }
                    }
                    $field->sources = $sources ?: '*';
                } else {
                    // Default to all users
                    $field->sources = '*';
                }
                break;

            case 'entries':
                $field = new \craft\fields\Entries();
                $field->maxRelations = $normalizedConfig['maxRelations'] ?? 1;
                $field->viewMode = 'list';
                
                // Configure sources (sections)
                if (isset($normalizedConfig['sources']) && is_array($normalizedConfig['sources'])) {
                    $entriesService = \Craft::$app->getEntries();
                    $sources = [];
                    foreach ($normalizedConfig['sources'] as $sectionHandle) {
                        if ($sectionHandle === '*') {
                            // Allow all sections
                            $sources = '*';
                            break;
                        }
                        $section = $entriesService->getSectionByHandle($sectionHandle);
                        if ($section) {
                            $sources[] = 'section:' . $section->uid;
                        }
                    }
                    $field->sources = $sources ?: '*';
                } else {
                    // Default to all sections
                    $field->sources = '*';
                }
                break;

            case 'categories':
                $field = new \craft\fields\Categories();
                $field->maxRelations = $normalizedConfig['maxRelations'] ?? null;
                $field->viewMode = 'list';
                
                // Configure sources (category groups)
                if (isset($normalizedConfig['sources']) && is_array($normalizedConfig['sources'])) {
                    $categoriesService = \Craft::$app->getCategories();
                    $sources = [];
                    foreach ($normalizedConfig['sources'] as $groupHandle) {
                        if ($groupHandle === '*') {
                            // Allow all category groups
                            $sources = '*';
                            break;
                        }
                        $group = $categoriesService->getGroupByHandle($groupHandle);
                        if ($group) {
                            $sources[] = 'group:' . $group->uid;
                        }
                    }
                    $field->sources = $sources ?: '*';
                } else {
                    // Default to all category groups
                    $field->sources = '*';
                }
                
                // Configure branch limit (how deep in the category tree to show)
                $field->branchLimit = $normalizedConfig['branchLimit'] ?? null;
                break;

            case 'tags':
                $field = new \craft\fields\Tags();
                $field->maxRelations = $normalizedConfig['maxRelations'] ?? null;
                
                // Configure sources (tag groups)
                if (isset($normalizedConfig['sources']) && is_array($normalizedConfig['sources'])) {
                    $tagsService = \Craft::$app->getTags();
                    $sources = [];
                    foreach ($normalizedConfig['sources'] as $groupHandle) {
                        if ($groupHandle === '*') {
                            // Allow all tag groups
                            $sources = '*';
                            break;
                        }
                        $group = $tagsService->getTagGroupByHandle($groupHandle);
                        if ($group) {
                            $sources[] = 'taggroup:' . $group->uid;
                        }
                    }
                    $field->sources = $sources ?: '*';
                } else {
                    // Default to all tag groups
                    $field->sources = '*';
                }
                break;

            default:
                throw new Exception("Unsupported field type: $fieldType");
        }

        if ($field) {
            $field->name = $normalizedConfig['name'];
            $field->handle = $normalizedConfig['handle'];
            $field->instructions = $normalizedConfig['instructions'] ?? '';
            $field->searchable = $normalizedConfig['searchable'] ?? false;
            $field->translationMethod = 'none';
            
            // Set default field group
            try {
                $field->groupId = 1; // Default field group
            } catch (\Exception $e) {
                // Some field types may not support groupId directly
                // This is acceptable as the fields service will handle it
            }

            // Set required property if specified and field supports it
            if (isset($normalizedConfig['required']) && property_exists($field, 'required')) {
                $field->required = $normalizedConfig['required'];
            }
        }

        return $field;
    }

    /**
     * Normalize field config by flattening settings object
     */
    private function normalizeFieldConfig(array $config): array
    {
        $normalized = $config;
        
        // If settings exist, merge them into the root level
        if (isset($config['settings']) && is_array($config['settings'])) {
            $normalized = array_merge($normalized, $config['settings']);
        }
        
        return $normalized;
    }

    /**
     * Prepare options array for selection fields
     */
    private function prepareOptions(array $options): array
    {
        $preparedOptions = [];

        foreach ($options as $option) {
            if (is_string($option)) {
                // Simple string option: "Option 1"
                $preparedOptions[] = [
                    'label' => $option,
                    'value' => $option,
                    'default' => false,
                ];
            } elseif (is_array($option)) {
                // Array option: {"label": "Option 1", "value": "opt1", "default": true}
                $preparedOptions[] = [
                    'label' => $option['label'] ?? $option['value'] ?? '',
                    'value' => $option['value'] ?? $option['label'] ?? '',
                    'default' => $option['default'] ?? false,
                ];
            }
        }

        return $preparedOptions;
    }

    /**
     * Prepare options array for button group fields
     */
    private function prepareButtonGroupOptions(array $options): array
    {
        $preparedOptions = [];

        foreach ($options as $option) {
            if (is_string($option)) {
                // Simple string option: "Option 1"
                $preparedOptions[] = [
                    'label' => $option,
                    'value' => $option,
                    'icon' => '',
                    'default' => false,
                ];
            } elseif (is_array($option)) {
                // Array option: {"label": "Option 1", "value": "opt1", "icon": "icon-name", "default": true}
                $preparedOptions[] = [
                    'label' => $option['label'] ?? $option['value'] ?? '',
                    'value' => $option['value'] ?? $option['label'] ?? '',
                    'icon' => $option['icon'] ?? '',
                    'default' => $option['default'] ?? false,
                ];
            }
        }

        return $preparedOptions;
    }

    /**
     * Create matrix block types (entry types) from configuration
     */
    private function createMatrixBlockTypes(array $blockTypesConfig): array
    {
        $entryTypes = [];
        $fieldsService = \Craft::$app->getFields();
        $entriesService = \Craft::$app->getEntries();

        foreach ($blockTypesConfig as $blockTypeConfig) {
            // Check if this references an existing entry type
            if (isset($blockTypeConfig['entryTypeHandle'])) {
                // Reference existing entry type
                $existingEntryType = $entriesService->getEntryTypeByHandle($blockTypeConfig['entryTypeHandle']);
                if ($existingEntryType) {
                    $entryTypes[] = $existingEntryType;
                    continue;
                } else {
                    throw new Exception("Referenced entry type '{$blockTypeConfig['entryTypeHandle']}' not found");
                }
            }
            
            // Create new entry type for this block type
            $entryType = new \craft\models\EntryType();
            $entryType->name = $blockTypeConfig['name'];
            $entryType->handle = $blockTypeConfig['handle'];
            $entryType->hasTitleField = $blockTypeConfig['hasTitleField'] ?? false;
            $entryType->titleTranslationMethod = 'site';
            $entryType->titleTranslationKeyFormat = null;
            
            // Create fields for this block type (only if fields are provided)
            $blockFields = [];
            $fieldLayoutElements = [];
            
            if (isset($blockTypeConfig['fields']) && is_array($blockTypeConfig['fields'])) {
                foreach ($blockTypeConfig['fields'] as $fieldConfig) {
                    // Handle case where field is just a reference (handle + required) vs full field definition
                    if (!isset($fieldConfig['name']) && isset($fieldConfig['handle'])) {
                        // This is a field reference - look up existing field or generate name
                        $existingField = $fieldsService->getFieldByHandle($fieldConfig['handle']);
                        if ($existingField) {
                            // Use existing field instead of creating new one
                            $fieldLayoutElement = new \craft\fieldlayoutelements\CustomField();
                            $fieldLayoutElement->fieldUid = $existingField->uid;
                            $fieldLayoutElement->required = $fieldConfig['required'] ?? false;
                            $fieldLayoutElements[] = $fieldLayoutElement;
                            continue;
                        } else {
                            // Generate a name based on handle for new field creation
                            $fieldConfig['name'] = ucwords(str_replace(['-', '_'], ' ', $fieldConfig['handle']));
                        }
                    }
                    
                    // For new field creation, make handle unique and adjust name
                    if (isset($fieldConfig['field_type'])) {
                        // This is a full field definition for inline creation
                        $fieldConfig['handle'] = $blockTypeConfig['handle'] . ucfirst($fieldConfig['handle']);
                        $fieldConfig['name'] = $blockTypeConfig['name'] . ' ' . $fieldConfig['name'];
                    
                        // Create the field for this block type
                        $blockField = $this->createFieldFromConfig($fieldConfig);
                        
                        if ($blockField) {
                            // Save the field
                            if (!$fieldsService->saveField($blockField)) {
                                $errors = $blockField->getErrors();
                                $errorMessage = "Failed to save field '{$blockField->name}' for block type '{$entryType->name}'";
                                if (!empty($errors)) {
                                    $errorMessage .= ": " . implode(', ', array_map(function($err) {
                                        return is_array($err) ? implode(', ', $err) : $err;
                                    }, $errors));
                                }
                                throw new Exception($errorMessage);
                            }
                            
                            $blockFields[] = $blockField;
                            
                            // Track created block field
                            $this->createdBlockFields[] = [
                                'type' => 'block-field',
                                'handle' => $blockField->handle,
                                'name' => $blockField->name,
                                'id' => $blockField->id,
                                'blockType' => $blockTypeConfig['handle']
                            ];
                            
                            // Create field layout element
                            $fieldLayoutElement = new \craft\fieldlayoutelements\CustomField();
                            $fieldLayoutElement->fieldUid = $blockField->uid;
                            $fieldLayoutElement->required = $fieldConfig['required'] ?? false;
                            $fieldLayoutElements[] = $fieldLayoutElement;
                        }
                    }
                }
            }
            
            // Create field layout for the entry type (only needed for new entry types)
            $fieldLayout = new \craft\models\FieldLayout();
            $fieldLayout->type = \craft\models\EntryType::class;
            
            // Create field layout using setTabs method  
            $fieldLayout->setTabs([
                [
                    'name' => 'Content',
                    'elements' => $fieldLayoutElements,
                ]
            ]);
            $entryType->setFieldLayout($fieldLayout);
            
            // Save the entry type
            if (!$entriesService->saveEntryType($entryType)) {
                $errors = $entryType->getErrors();
                $errorMessage = "Failed to save entry type '{$entryType->name}' for matrix field";
                if (!empty($errors)) {
                    $errorDetails = [];
                    foreach ($errors as $attribute => $messages) {
                        foreach ($messages as $message) {
                            $errorDetails[] = "$attribute: $message";
                        }
                    }
                    $errorMessage .= ". Errors: " . implode(', ', $errorDetails);
                }
                throw new Exception($errorMessage);
            }
            
            // Track created block entry type
            $this->createdBlockEntryTypes[] = [
                'type' => 'block-entry-type',
                'handle' => $entryType->handle,
                'name' => $entryType->name,
                'id' => $entryType->id
            ];
            
            $entryTypes[] = $entryType;
        }

        return $entryTypes;
    }

    /**
     * Get created block fields from last matrix field creation
     */
    public function getCreatedBlockFields(): array
    {
        return $this->createdBlockFields;
    }

    /**
     * Get created block entry types from last matrix field creation
     */
    public function getCreatedBlockEntryTypes(): array
    {
        return $this->createdBlockEntryTypes;
    }

    /**
     * Clear tracked matrix block items
     */
    public function clearBlockTracking(): void
    {
        $this->createdBlockFields = [];
        $this->createdBlockEntryTypes = [];
    }

    /**
     * Create entry type from configuration
     */
    public function createEntryTypeFromConfig(array $config, array $createdFields = []): ?\craft\models\EntryType
    {
        Craft::info("Creating entry type: " . json_encode($config), __METHOD__);
        Craft::info("Created fields available: " . json_encode(array_keys($createdFields)), __METHOD__);
        
        // Create the entry type without section association
        $entryType = new \craft\models\EntryType();
        $entryType->name = $config['name'];
        $entryType->handle = $config['handle'];
        $entryType->hasTitleField = $config['hasTitleField'] ?? true;
        $entryType->titleFormat = $config['titleFormat'] ?? null;

        // Create field layout
        $fieldLayout = new \craft\models\FieldLayout();
        $fieldLayout->type = \craft\models\EntryType::class;

        $elements = [];

        // Add title field if enabled
        if ($entryType->hasTitleField) {
            $titleField = new \craft\fieldlayoutelements\entries\EntryTitleField();
            $titleField->required = true;
            $elements[] = $titleField;
        }

        // Add custom fields
        if (isset($config['fields'])) {
            $fieldsService = Craft::$app->getFields();

            foreach ($config['fields'] as $fieldRef) {
                $handle = $fieldRef['handle'];

                // Check for reserved words and skip them
                if ($this->isReservedFieldHandle($handle)) {
                    Craft::warning("Skipping reserved field handle: $handle", __METHOD__);
                    continue;
                }

                // Try to find field in recently created fields first
                $field = null;
                foreach ($createdFields as $createdField) {
                    if ($createdField['handle'] === $handle) {
                        if (isset($createdField['id']) && $createdField['id']) {
                            $field = $fieldsService->getFieldById($createdField['id']);
                            break;
                        } else {
                            Craft::warning("Field handle '$handle' found in created fields but has no valid ID", __METHOD__);
                        }
                    }
                }

                // If not found in created fields, try to find existing field
                if (!$field) {
                    $field = $fieldsService->getFieldByHandle($handle);
                }

                if ($field) {
                    $element = new \craft\fieldlayoutelements\CustomField();
                    $element->fieldUid = $field->uid;
                    $element->required = $fieldRef['required'] ?? false;
                    $elements[] = $element;
                } else {
                    Craft::warning("Field '$handle' not found for entry type", __METHOD__);
                }
            }
        }

        // Set up the field layout
        $fieldLayout->setTabs([
            [
                'name' => 'Content',
                'elements' => $elements,
            ]
        ]);

        $entryType->setFieldLayout($fieldLayout);

        // Save the entry type
        try {
            if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
                $errors = $entryType->getErrors();
                $errorMessages = [];
                foreach ($errors as $attribute => $messages) {
                    foreach ($messages as $message) {
                        $errorMessages[] = "$attribute: $message";
                        Craft::error("Entry type error on $attribute: $message", __METHOD__);
                    }
                }
                throw new Exception("Entry type validation failed: " . implode(', ', $errorMessages));
            }

            return $entryType;
        } catch (\Exception $e) {
            Craft::error("Exception creating entry type: {$e->getMessage()}", __METHOD__);
            throw $e; // Re-throw to get the actual error message
        }
    }

    /**
     * Check if a field handle is reserved
     */
    private function isReservedFieldHandle(string $handle): bool
    {
        $reservedWords = [
            'author', 'authorId', 'dateCreated', 'dateUpdated', 'id', 'slug', 'title', 'uid', 'uri', 'url',
            'level', 'lft', 'rgt', 'root', 'parent', 'parentId', 'children', 'descendants', 'ancestors',
            'next', 'prev', 'siblings', 'status', 'enabled', 'archived', 'trashed', 'postDate', 'expiryDate',
            'revisionCreator', 'revisionNotes', 'section', 'sectionId', 'type', 'typeId', 'field', 'fieldId'
        ];

        return in_array($handle, $reservedWords, true);
    }

    /**
     * Add a new entry type to an existing matrix field
     */
    public function addMatrixEntryType(\craft\fields\Matrix $matrixField, array $entryTypeConfig): bool
    {
        $fieldsService = \Craft::$app->getFields();
        $entriesService = \Craft::$app->getEntries();
        
        // Get existing entry types
        $existingEntryTypes = $matrixField->getEntryTypes();
        
        // Check if we should reference an existing entry type or create a new one
        if (isset($entryTypeConfig['entryTypeHandle'])) {
            // Reference existing entry type
            $entryType = $entriesService->getEntryTypeByHandle($entryTypeConfig['entryTypeHandle']);
            if (!$entryType) {
                throw new \Exception("Entry type '{$entryTypeConfig['entryTypeHandle']}' not found");
            }
            
            // Check if this entry type is already associated with the matrix field
            foreach ($existingEntryTypes as $existingType) {
                if ($existingType->handle === $entryType->handle) {
                    throw new \Exception("Entry type '{$entryType->handle}' is already associated with matrix field '{$matrixField->handle}'");
                }
            }
            
            $newEntryTypes = [$entryType];
        } else {
            // Create new entry type using traditional method
            $newEntryTypes = $this->createMatrixBlockTypes([$entryTypeConfig]);
            if (empty($newEntryTypes)) {
                throw new \Exception("Failed to create entry type '{$entryTypeConfig['name']}'");
            }
        }
        
        // Combine existing and new entry types
        $allEntryTypes = array_merge($existingEntryTypes, $newEntryTypes);
        $matrixField->setEntryTypes($allEntryTypes);
        
        // Save the matrix field with updated block types
        return $fieldsService->saveField($matrixField);
    }

    /**
     * Remove an entry type from an existing matrix field
     */
    public function removeMatrixEntryType(\craft\fields\Matrix $matrixField, string $entryTypeHandle): bool
    {
        $fieldsService = \Craft::$app->getFields();
        $entriesService = \Craft::$app->getEntries();
        
        // Get existing entry types
        $existingEntryTypes = $matrixField->getEntryTypes();
        
        // Filter out the entry type to remove
        $remainingEntryTypes = array_filter($existingEntryTypes, function($entryType) use ($entryTypeHandle) {
            return $entryType->handle !== $entryTypeHandle;
        });
        
        if (count($remainingEntryTypes) === count($existingEntryTypes)) {
            throw new \Exception("Entry type '{$entryTypeHandle}' not found in matrix field");
        }
        
        // Update matrix field with remaining entry types
        $matrixField->setEntryTypes(array_values($remainingEntryTypes));
        
        // Save the matrix field
        if (!$fieldsService->saveField($matrixField)) {
            return false;
        }
        
        // Find and delete the entry type that was removed
        $removedEntryType = null;
        foreach ($existingEntryTypes as $entryType) {
            if ($entryType->handle === $entryTypeHandle) {
                $removedEntryType = $entryType;
                break;
            }
        }
        
        if ($removedEntryType) {
            // Delete the entry type (this will also clean up its fields)
            $entriesService->deleteEntryType($removedEntryType);
        }
        
        return true;
    }


    /**
     * Modify an existing matrix entry type (add/remove fields)
     */
    public function modifyMatrixEntryType(\craft\fields\Matrix $matrixField, string $entryTypeHandle, array $modifications): bool
    {
        $fieldsService = \Craft::$app->getFields();
        $entriesService = \Craft::$app->getEntries();
        
        // Find the entry type for this entry type handle
        $targetEntryType = null;
        foreach ($matrixField->getEntryTypes() as $entryType) {
            if ($entryType->handle === $entryTypeHandle) {
                $targetEntryType = $entryType;
                break;
            }
        }
        
        if (!$targetEntryType) {
            throw new \Exception("Entry type '{$entryTypeHandle}' not found in matrix field");
        }
        
        $fieldLayout = $targetEntryType->getFieldLayout();
        $currentFields = $fieldLayout->getCustomFields();
        $layoutElements = [];
        
        // Add existing fields to layout
        foreach ($currentFields as $field) {
            $element = new \craft\fieldlayoutelements\CustomField();
            $element->fieldUid = $field->uid;
            $layoutElements[] = $element;
        }
        
        // Add new fields if specified
        if (isset($modifications['addFields'])) {
            foreach ($modifications['addFields'] as $fieldConfig) {
                // Prefix field handle with entry type handle to ensure uniqueness
                $fieldConfig['handle'] = $entryTypeHandle . ucfirst($fieldConfig['handle']);
                $fieldConfig['name'] = $targetEntryType->name . ' ' . $fieldConfig['name'];
                
                // Create the field
                $newField = $this->createFieldFromConfig($fieldConfig);
                if ($newField && $fieldsService->saveField($newField)) {
                    // Add to layout
                    $element = new \craft\fieldlayoutelements\CustomField();
                    $element->fieldUid = $newField->uid;
                    $element->required = $fieldConfig['required'] ?? false;
                    $layoutElements[] = $element;
                    
                    // Track created field
                    $this->createdBlockFields[] = [
                        'type' => 'block-field',
                        'handle' => $newField->handle,
                        'name' => $newField->name,
                        'id' => $newField->id,
                        'blockType' => $blockTypeHandle
                    ];
                }
            }
        }
        
        // Remove fields if specified
        if (isset($modifications['removeFields'])) {
            foreach ($modifications['removeFields'] as $fieldHandle) {
                // Remove from layout elements
                $layoutElements = array_filter($layoutElements, function($element) use ($fieldHandle) {
                    if ($element instanceof \craft\fieldlayoutelements\CustomField) {
                        $field = \Craft::$app->getFields()->getFieldByUid($element->fieldUid);
                        return $field ? $field->handle !== $fieldHandle : true;
                    }
                    return true;
                });
            }
        }
        
        // Update the field layout
        $newFieldLayout = new \craft\models\FieldLayout();
        $newFieldLayout->type = \craft\models\EntryType::class;
        $newFieldLayout->setTabs([
            [
                'name' => 'Content',
                'elements' => array_values($layoutElements),
            ]
        ]);
        
        $targetEntryType->setFieldLayout($newFieldLayout);
        
        // Update name if specified
        if (isset($modifications['name'])) {
            $targetEntryType->name = $modifications['name'];
        }
        
        // Save the entry type
        return $entriesService->saveEntryType($targetEntryType);
    }
}