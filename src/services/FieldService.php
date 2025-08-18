<?php

namespace craftcms\fieldagent\services;

use Craft;
use craft\base\Component;
use craft\base\Field;
use yii\base\Exception;
use craftcms\fieldagent\Plugin;

/**
 * Field Generator service
 */
class FieldService extends Component
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
     * @var bool Feature flag to use registry system for field creation
     */
    private bool $useRegistry = true;

    /**
     * @var array Map of our field type identifiers to Craft field classes
     */
    public const FIELD_TYPE_MAP = [
        'addresses' => \craft\fields\Addresses::class,
        'assets' => \craft\fields\Assets::class,
        'button_group' => \craft\fields\ButtonGroup::class,
        'categories' => \craft\fields\Categories::class,
        'checkboxes' => \craft\fields\Checkboxes::class,
        'ckeditor' => \craft\ckeditor\Field::class,
        'color' => \craft\fields\Color::class,
        'content_block' => \craft\fields\ContentBlock::class,
        'country' => \craft\fields\Country::class,
        'date' => \craft\fields\Date::class,
        'dropdown' => \craft\fields\Dropdown::class,
        'email' => \craft\fields\Email::class,
        'entries' => \craft\fields\Entries::class,
        'icon' => \craft\fields\Icon::class,
        'image' => \craft\fields\Assets::class, // Special case - Assets field configured for images only
        'json' => \craft\fields\Json::class,
        'lightswitch' => \craft\fields\Lightswitch::class,
        'link' => \craft\fields\Link::class,
        'matrix' => \craft\fields\Matrix::class,
        'money' => \craft\fields\Money::class,
        'multi_select' => \craft\fields\MultiSelect::class,
        'number' => \craft\fields\Number::class,
        'plain_text' => \craft\fields\PlainText::class,
        'radio_buttons' => \craft\fields\RadioButtons::class,
        'range' => \craft\fields\Range::class,
        'table' => \craft\fields\Table::class,
        'tags' => \craft\fields\Tags::class,
        'time' => \craft\fields\Time::class,
        'users' => \craft\fields\Users::class,
    ];

    /**
     * Get our field type identifier from a Craft field instance
     */
    private function getFieldTypeFromInstance(Field $field): ?string
    {
        $className = get_class($field);

        // Search for the class in our map
        foreach (self::FIELD_TYPE_MAP as $type => $class) {
            if ($className === $class) {
                // Special case for Assets fields configured as image
                if ($type === 'assets' && $field instanceof \craft\fields\Assets) {
                    // Check if it's configured as an image field
                    if ($field->restrictFiles && $field->allowedKinds === ['image']) {
                        return 'image';
                    }
                    // Check if it's configured as single asset
                    if ($field->maxRelations === 1) {
                        return 'asset';
                    }
                }
                return $type;
            }
        }

        return null;
    }

    /**
     * Get all available field type identifiers
     * Used to generate consistent lists for prompts and schemas
     */
    public static function getAvailableFieldTypes(): array
    {
        return array_keys(self::FIELD_TYPE_MAP);
    }

    /**
     * Get field types as a comma-separated string for prompts
     */
    public static function getFieldTypesString(): string
    {
        return implode(',', self::getAvailableFieldTypes());
    }

    /**
     * Set whether to use the registry system for field creation
     */
    public function setUseRegistry(bool $useRegistry): void
    {
        $this->useRegistry = $useRegistry;
    }

    /**
     * Check if registry system is enabled
     */
    public function isUsingRegistry(): bool
    {
        return $this->useRegistry;
    }

    /**
     * Update an existing field from config array
     * This method applies settings to an existing field using the same patterns as creation
     */
    public function updateFieldFromConfig(Field $field, array $updates): array
    {
        // Get field type using our clean mapping
        $fieldType = $this->getFieldTypeFromInstance($field);
        
        // Try registry system first if enabled
        if ($this->useRegistry && $fieldType) {
            try {
                $registry = Plugin::getInstance()->fieldRegistryService;
                $fieldDefinition = $registry->getField($fieldType);
                
                if ($fieldDefinition && $fieldDefinition->hasUpdateMethod()) {
                    // Update field using registry
                    $modifications = $fieldDefinition->updateField($field, $updates);
                    
                    if (!empty($modifications)) {
                        Craft::info("Updated field '{$fieldType}' using registry system", __METHOD__);
                    }
                    
                    return $modifications;
                }
            } catch (\Exception $e) {
                Craft::warning("Registry field update failed for '{$fieldType}': {$e->getMessage()}", __METHOD__);
                // Fall through to legacy system
            }
        }
        
        // Fall back to legacy update method
        return $this->legacyUpdateField($field, $updates, $fieldType);
    }

    /**
     * Legacy field update method - contains original switch statement logic
     * This will be removed once all field types are migrated to registry
     */
    private function legacyUpdateField(Field $field, array $updates, ?string $fieldType): array
    {
        $modifications = [];

        // Apply field-type-specific updates using creation patterns
        switch ($fieldType) {
            case 'plain_text':
                if (isset($updates['multiline'])) {
                    $field->multiline = (bool)$updates['multiline'];
                    $field->initialRows = $field->multiline ? 4 : 1;
                    $modifications[] = "Updated multiline to " . ($updates['multiline'] ? 'true' : 'false');
                }
                if (isset($updates['charLimit'])) {
                    $field->charLimit = $updates['charLimit'];
                    $modifications[] = "Updated charLimit to {$updates['charLimit']}";
                }
                break;

            case 'number':
                if (isset($updates['decimals'])) {
                    $field->decimals = $updates['decimals'];
                    $modifications[] = "Updated decimals to {$updates['decimals']}";
                }
                if (isset($updates['min'])) {
                    $field->min = $updates['min'];
                    $modifications[] = "Updated min to {$updates['min']}";
                }
                if (isset($updates['max'])) {
                    $field->max = $updates['max'];
                    $modifications[] = "Updated max to {$updates['max']}";
                }
                if (isset($updates['prefix'])) {
                    $field->prefix = $updates['prefix'];
                    $modifications[] = "Updated prefix to '{$updates['prefix']}'";
                }
                if (isset($updates['suffix'])) {
                    $field->suffix = $updates['suffix'];
                    $modifications[] = "Updated suffix to '{$updates['suffix']}'";
                }
                break;

            case 'money':
                if (isset($updates['currency'])) {
                    $field->currency = $updates['currency'];
                    $modifications[] = "Updated currency to {$updates['currency']}";
                }
                if (isset($updates['showCurrency'])) {
                    $field->showCurrency = (bool)$updates['showCurrency'];
                    $modifications[] = "Updated showCurrency to " . ($updates['showCurrency'] ? 'true' : 'false');
                }
                if (isset($updates['min'])) {
                    $field->min = $updates['min'];
                    $modifications[] = "Updated min to {$updates['min']}";
                }
                if (isset($updates['max'])) {
                    $field->max = $updates['max'];
                    $modifications[] = "Updated max to {$updates['max']}";
                }
                break;

            case 'asset':
                if (isset($updates['maxRelations'])) {
                    $field->maxRelations = $updates['maxRelations'];
                    $modifications[] = "Updated maxRelations to {$updates['maxRelations']}";
                }
                if (isset($updates['minRelations'])) {
                    $field->minRelations = $updates['minRelations'];
                    $modifications[] = "Updated minRelations to {$updates['minRelations']}";
                }
                if (isset($updates['viewMode'])) {
                    $field->viewMode = $updates['viewMode'];
                    $modifications[] = "Updated viewMode to {$updates['viewMode']}";
                }
                if (isset($updates['allowedKinds'])) {
                    $field->allowedKinds = $updates['allowedKinds'];
                    // When setting allowedKinds, we MUST also enable restrictFiles
                    // Otherwise the allowedKinds setting won't take effect!
                    $field->restrictFiles = true;
                    $modifications[] = "Updated allowedKinds to " . implode(', ', $updates['allowedKinds']) . " (enabled file type restrictions)";
                }
                // Handle explicit restrictFiles setting
                if (isset($updates['restrictFiles'])) {
                    $field->restrictFiles = (bool)$updates['restrictFiles'];
                    $modifications[] = "Updated restrictFiles to " . ($updates['restrictFiles'] ? 'true' : 'false');
                }
                // Handle restrictLocation setting
                if (isset($updates['restrictLocation'])) {
                    $field->restrictLocation = (bool)$updates['restrictLocation'];
                    $modifications[] = "Updated restrictLocation to " . ($updates['restrictLocation'] ? 'true' : 'false');
                }
                // Handle sources setting
                if (isset($updates['sources'])) {
                    $field->sources = $updates['sources'];
                    $modifications[] = "Updated sources";
                }
                break;

            case 'dropdown':
                if (isset($updates['options'])) {
                    $field->options = $this->prepareOptions($updates['options']);
                    $modifications[] = "Updated dropdown options";
                }
                break;

            case 'date':
                if (isset($updates['showDate'])) {
                    $field->showDate = (bool)$updates['showDate'];
                    $modifications[] = "Updated showDate to " . ($updates['showDate'] ? 'true' : 'false');
                }
                if (isset($updates['showTime'])) {
                    $field->showTime = (bool)$updates['showTime'];
                    $modifications[] = "Updated showTime to " . ($updates['showTime'] ? 'true' : 'false');
                }
                if (isset($updates['showTimeZone'])) {
                    $field->showTimeZone = (bool)$updates['showTimeZone'];
                    $modifications[] = "Updated showTimeZone to " . ($updates['showTimeZone'] ? 'true' : 'false');
                }
                break;

            case 'lightswitch':
                if (isset($updates['default'])) {
                    $field->default = (bool)$updates['default'];
                    $modifications[] = "Updated default to " . ($updates['default'] ? 'true' : 'false');
                }
                break;

            case 'radio_buttons':
            case 'checkboxes':
            case 'multi_select':
                if (isset($updates['options'])) {
                    $field->options = $this->prepareOptions($updates['options']);
                    $modifications[] = "Updated options";
                }
                break;

            case 'button_group':
                if (isset($updates['options'])) {
                    $field->options = $this->prepareButtonGroupOptions($updates['options']);
                    $modifications[] = "Updated button group options";
                }
                break;

            case 'range':
                if (isset($updates['min'])) {
                    $field->min = $updates['min'];
                    $modifications[] = "Updated min to {$updates['min']}";
                }
                if (isset($updates['max'])) {
                    $field->max = $updates['max'];
                    $modifications[] = "Updated max to {$updates['max']}";
                }
                if (isset($updates['step'])) {
                    $field->step = $updates['step'];
                    $modifications[] = "Updated step to {$updates['step']}";
                }
                if (isset($updates['suffix'])) {
                    $field->suffix = $updates['suffix'];
                    $modifications[] = "Updated suffix to '{$updates['suffix']}'";
                }
                break;

            case 'email':
                if (isset($updates['placeholder'])) {
                    $field->placeholder = $updates['placeholder'];
                    $modifications[] = "Updated placeholder to '{$updates['placeholder']}'";
                }
                break;

            default:
                // For unknown field types, try generic property setting
                foreach ($updates as $settingName => $settingValue) {
                    if (property_exists($field, $settingName)) {
                        $field->$settingName = $settingValue;
                        $modifications[] = "Updated {$settingName} to " . (is_bool($settingValue) ? ($settingValue ? 'true' : 'false') : $settingValue);
                    } elseif (method_exists($field, 'setSettings') && method_exists($field, 'getSettings')) {
                        // Try as a setting
                        $settings = $field->getSettings();
                        $settings[$settingName] = $settingValue;
                        $field->setSettings($settings);
                        $modifications[] = "Updated setting {$settingName} to " . (is_bool($settingValue) ? ($settingValue ? 'true' : 'false') : $settingValue);
                    }
                }
                break;
        }

        return $modifications;
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

        // Try registry system first if enabled
        if ($this->useRegistry) {
            try {
                $registry = Plugin::getInstance()->fieldRegistryService;
                $fieldDefinition = $registry->getField($fieldType);
                
                if ($fieldDefinition) {
                    // Create field using registry
                    $field = $fieldDefinition->createField($normalizedConfig);
                    
                    if ($field) {
                        Craft::info("Created field '{$fieldType}' using registry system", __METHOD__);
                    }
                }
            } catch (\Exception $e) {
                Craft::warning("Registry field creation failed for '{$fieldType}': {$e->getMessage()}", __METHOD__);
                // Fall through to legacy system
            }
        }

        // Fall back to legacy switch statement if registry didn't work
        if (!$field) {
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
            case 'ckeditor':
                if (!class_exists('craft\ckeditor\Field')) {
                    throw new Exception('CKEditor plugin is not installed. Please install the CKEditor plugin to use rich text fields.');
                }
                /** @var \craft\base\Field $field */
                $field = new \craft\ckeditor\Field();
                if (property_exists($field, 'purifyHtml')) {
                    $field->purifyHtml = true;
                }
                break;

            case 'image':
                $field = new \craft\fields\Assets();
                $field->allowedKinds = ['image'];
                $field->restrictFiles = true; // Must enable this for allowedKinds to work
                $field->maxRelations = $normalizedConfig['maxRelations'] ?? 1;
                if (isset($normalizedConfig['minRelations'])) {
                    $field->minRelations = $normalizedConfig['minRelations'];
                }
                $field->viewMode = 'list';
                break;

            case 'assets':
            case 'asset':
                $field = new \craft\fields\Assets();
                if ($fieldType === 'asset') {
                    // Single asset field - default to maxRelations=1
                    $field->maxRelations = $normalizedConfig['maxRelations'] ?? 1;
                } else {
                    // Multiple assets field - no default limit
                    $field->maxRelations = $normalizedConfig['maxRelations'] ?? null;
                }
                if (isset($normalizedConfig['minRelations'])) {
                    $field->minRelations = $normalizedConfig['minRelations'];
                }
                $field->viewMode = 'list';
                // If allowedKinds is specified, enable restrictFiles
                if (isset($normalizedConfig['allowedKinds'])) {
                    $field->allowedKinds = $normalizedConfig['allowedKinds'];
                    $field->restrictFiles = true;
                }
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
                if (isset($normalizedConfig['prefix'])) {
                    $field->prefix = $normalizedConfig['prefix'];
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
                
                // Configure advanced fields if specified
                if (isset($normalizedConfig['advancedFields']) && is_array($normalizedConfig['advancedFields'])) {
                    $field->advancedFields = $normalizedConfig['advancedFields'];
                }
                
                // If target is specified, ensure it's included in advanced fields
                if (isset($normalizedConfig['target'])) {
                    $advancedFields = $field->advancedFields ?? [];
                    if (!in_array('target', $advancedFields)) {
                        $advancedFields[] = 'target';
                        $field->advancedFields = $advancedFields;
                    }
                }

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
                
                // Category type settings
                if (in_array('category', $field->types)) {
                    $categorySources = '*'; // Default to all categories
                    if (isset($normalizedConfig['categorySources']) && is_array($normalizedConfig['categorySources'])) {
                        $categoriesService = \Craft::$app->getCategories();
                        $sources = [];
                        foreach ($normalizedConfig['categorySources'] as $groupHandle) {
                            $group = $categoriesService->getGroupByHandle($groupHandle);
                            if ($group) {
                                $sources[] = 'group:' . $group->uid;
                            }
                        }
                        if (!empty($sources)) {
                            $categorySources = $sources;
                        }
                    }
                    $typeSettings['category'] = [
                        'sources' => $categorySources,
                    ];
                }
                
                // Asset type settings
                if (in_array('asset', $field->types)) {
                    $assetSources = '*'; // Default to all assets
                    if (isset($normalizedConfig['assetSources']) && is_array($normalizedConfig['assetSources'])) {
                        $volumesService = \Craft::$app->getVolumes();
                        $sources = [];
                        foreach ($normalizedConfig['assetSources'] as $volumeHandle) {
                            $volume = $volumesService->getVolumeByHandle($volumeHandle);
                            if ($volume) {
                                $sources[] = 'volume:' . $volume->uid;
                            }
                        }
                        if (!empty($sources)) {
                            $assetSources = $sources;
                        }
                    }
                    $typeSettings['asset'] = [
                        'sources' => $assetSources,
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
                    // Categories fields use 'source' (singular), not 'sources' (plural)
                    $field->source = !empty($sources) && $sources !== '*' ? $sources[0] : null;
                } else {
                    // Default to all category groups
                    $field->source = null;
                }

                // Configure branch limit (how deep in the category tree to show)
                $field->branchLimit = $normalizedConfig['branchLimit'] ?? null;
                break;

            case 'tags':
                $field = new \craft\fields\Tags();

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
                    // Tags fields use 'source' (singular), not 'sources' (plural)
                    $field->source = !empty($sources) && $sources !== '*' ? $sources[0] : null;
                } else {
                    // Default to all tag groups
                    $field->source = null;
                }
                break;

            case 'table':
                $field = new \craft\fields\Table();
                $field->columns = $this->prepareTableColumns($normalizedConfig['columns'] ?? []);
                $field->defaults = $normalizedConfig['defaults'] ?? [];
                $field->addRowLabel = $normalizedConfig['addRowLabel'] ?? 'Add a row';
                $field->maxRows = $normalizedConfig['maxRows'] ?? null;
                $field->minRows = $normalizedConfig['minRows'] ?? null;
                break;

            case 'json':
                $field = new \craft\fields\Json();
                break;

            case 'addresses':
                $field = new \craft\fields\Addresses();
                break;

            case 'content_block':
            case 'contentblock':
                $field = new \craft\fields\ContentBlock();
                $field->viewMode = $normalizedConfig['viewMode'] ?? 'grouped'; // grouped, pane, or inline

                // Create field layout with nested fields (similar to entry types)
                if (isset($normalizedConfig['fields']) && is_array($normalizedConfig['fields'])) {
                    $fieldLayout = $this->createContentBlockFieldLayout($normalizedConfig['fields']);
                    $field->setFieldLayout($fieldLayout);
                }
                break;

            default:
                throw new Exception("Unsupported field type: $fieldType");
            }
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
            // if (isset($normalizedConfig['required']) && property_exists($field, 'required')) {
            //     $field->required = $normalizedConfig['required'];
            // }
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
     * Prepare columns array for table fields
     */
    private function prepareTableColumns(array $columns): array
    {
        $preparedColumns = [];

        foreach ($columns as $column) {
            if (is_string($column)) {
                // Simple string column: "Column Name"
                $preparedColumns[] = [
                    'heading' => $column,
                    'handle' => $this->createHandle($column),
                    'type' => 'singleline',
                    'width' => '',
                ];
            } elseif (is_array($column)) {
                // Array column: {"heading": "Name", "handle": "name", "type": "singleline", "width": "50%"}
                $preparedColumns[] = [
                    'heading' => $column['heading'] ?? $column['handle'] ?? '',
                    'handle' => $column['handle'] ?? $this->createHandle($column['heading'] ?? ''),
                    'type' => $column['type'] ?? 'singleline', // singleline, multiline, number, checkbox, color, url, email, date, time
                    'width' => $column['width'] ?? '',
                ];
            }
        }

        return $preparedColumns;
    }

    /**
     * Create a handle from a string
     */
    private function createHandle(string $name): string
    {
        // Convert to camelCase handle
        $handle = trim(preg_replace('/[^a-zA-Z0-9]/', ' ', $name));
        $handle = str_replace(' ', '', ucwords(strtolower($handle)));
        return lcfirst($handle);
    }

    /**
     * Create field layout for ContentBlock fields
     */
    private function createContentBlockFieldLayout(array $fieldsConfig): \craft\models\FieldLayout
    {
        $fieldLayout = new \craft\models\FieldLayout();
        $fieldLayout->type = \craft\fields\ContentBlock::class;

        $fieldLayoutElements = [];
        $fieldsService = \Craft::$app->getFields();

        foreach ($fieldsConfig as $fieldConfig) {
            // Check if this is a field reference (existing field)
            if (!isset($fieldConfig['field_type']) && isset($fieldConfig['handle'])) {
                // Look up existing field
                $existingField = $fieldsService->getFieldByHandle($fieldConfig['handle']);
                if ($existingField) {
                    // Use existing field
                    $fieldLayoutElement = new \craft\fieldlayoutelements\CustomField();
                    $fieldLayoutElement->fieldUid = $existingField->uid;
                    $fieldLayoutElement->required = $fieldConfig['required'] ?? false;
                    $fieldLayoutElements[] = $fieldLayoutElement;
                    continue;
                } else {
                    // If field doesn't exist and no field_type provided, skip
                    Craft::warning("Field '{$fieldConfig['handle']}' not found for ContentBlock field layout", __METHOD__);
                    continue;
                }
            }

            // This is a full field definition for inline creation
            if (isset($fieldConfig['field_type'])) {
                // Create the field
                $blockField = $this->createFieldFromConfig($fieldConfig);

                if ($blockField) {
                    // Save the field
                    if (!$fieldsService->saveField($blockField)) {
                        $errors = $blockField->getErrors();
                        $errorMessage = "Failed to save field '{$blockField->name}' for ContentBlock";
                        if (!empty($errors)) {
                            $errorMessage .= ": " . implode(', ', array_map(function($err) {
                                return is_array($err) ? implode(', ', $err) : $err;
                            }, $errors));
                        }
                        throw new Exception($errorMessage);
                    }

                    // Create field layout element
                    $fieldLayoutElement = new \craft\fieldlayoutelements\CustomField();
                    $fieldLayoutElement->fieldUid = $blockField->uid;
                    $fieldLayoutElement->required = $fieldConfig['required'] ?? false;
                    $fieldLayoutElements[] = $fieldLayoutElement;
                }
            }
        }

        // Set up the field layout tabs
        $fieldLayout->setTabs([
            [
                'name' => 'Content',
                'elements' => $fieldLayoutElements,
            ]
        ]);

        return $fieldLayout;
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
     * Check if a field handle is reserved
     */
    public function isReservedFieldHandle(string $handle): bool
    {
        $reservedWords = Field::RESERVED_HANDLES;

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
                        'blockType' => $entryTypeHandle
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
