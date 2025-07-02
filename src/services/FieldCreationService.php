<?php

namespace craftcms\fieldagent\services;

use Craft;
use craft\base\Component;
use craft\fields\PlainText;
use craft\fields\Assets;
use craft\fields\Number;
use craft\fields\Link;
use craft\fields\Dropdown;
use craft\fields\RadioButtons;
use craft\fields\Checkboxes;
use craft\fields\MultiSelect;
use craft\fields\Country;
use craft\fields\Date;
use craft\fields\Time;
use craft\fields\Email;
use craft\fields\Color;
use craft\fields\Lightswitch;
use craft\fields\Money;
use craft\fields\Range;
use craft\fields\ButtonGroup;
use craft\fields\Icon;
use craft\fields\Table;
use craft\fields\Categories;
use craft\fields\Entries;
use craft\fields\Tags;
use craft\fields\Users;
use craft\fields\Matrix;
use craft\ckeditor\Field as CKEditorField;

/**
 * Field Creation Service
 * 
 * Handles field creation logic for the Field Agent plugin
 */
class FieldCreationService extends Component
{
    /**
     * Create a field from configuration
     * 
     * @param array $config Field configuration
     * @return \craft\base\FieldInterface|null
     */
    public function createFieldFromConfig(array $config)
    {
        $fieldType = $config['field_type'] ?? '';
        $field = null;

        switch ($fieldType) {
            case 'plain_text':
            case 'text':
                $field = new PlainText();
                $field->multiline = $config['multiline'] ?? false;
                $field->initialRows = $field->multiline ? 4 : 1;
                break;

            case 'rich_text':
            case 'richtext':
                if (class_exists(CKEditorField::class)) {
                    $field = new CKEditorField();
                    $field->purifyHtml = true;
                } else {
                    Craft::warning("CKEditor plugin not installed, skipping rich text field", __METHOD__);
                    return null;
                }
                break;

            case 'image':
            case 'asset':
                $field = new Assets();
                $field->allowedKinds = $fieldType === 'image' ? ['image'] : null;
                $field->maxRelations = 1;
                $field->viewMode = 'list';
                break;

            case 'number':
                $field = new Number();
                $field->decimals = $config['decimals'] ?? 0;
                break;

            case 'url':
                $field = new Link();
                $field->types = ['url'];
                $field->showLabelField = false;
                $field->maxLength = 255;

                // Set URL-specific type settings with supported properties only
                $field->typeSettings = [
                    'url' => [
                        'allowRootRelativeUrls' => $config['allow_root_relative_urls'] ?? true,
                        'allowAnchors' => $config['allow_anchors'] ?? true,
                        'allowCustomSchemes' => $config['allow_custom_schemes'] ?? false,
                    ],
                ];
                break;

            case 'dropdown':
                $field = new Dropdown();
                $field->options = $this->prepareOptions($config['options'] ?? []);
                break;

            case 'radio_buttons':
            case 'radio':
                $field = new RadioButtons();
                $field->options = $this->prepareOptions($config['options'] ?? []);
                break;

            case 'checkboxes':
                $field = new Checkboxes();
                $field->options = $this->prepareOptions($config['options'] ?? []);
                break;

            case 'multi_select':
            case 'multiselect':
                $field = new MultiSelect();
                $field->options = $this->prepareOptions($config['options'] ?? []);
                break;

            case 'country':
                $field = new Country();
                break;

            case 'date':
                $field = new Date();
                $field->showTimeZone = $config['show_timezone'] ?? false;
                $field->showDate = $config['show_date'] ?? true;
                $field->showTime = $config['show_time'] ?? false;
                break;

            case 'time':
                $field = new Time();
                break;

            case 'email':
                $field = new Email();
                $field->placeholder = $config['placeholder'] ?? 'Enter email address';
                break;

            case 'color':
                $field = new Color();
                $field->palette = $config['palette'] ?? [
                    ['color' => '#ff0000', 'label' => 'Red'],
                    ['color' => '#00ff00', 'label' => 'Green'],
                    ['color' => '#0000ff', 'label' => 'Blue'],
                    ['color' => '#ffff00', 'label' => 'Yellow'],
                    ['color' => '#ff00ff', 'label' => 'Magenta'],
                    ['color' => '#00ffff', 'label' => 'Cyan'],
                ];
                $field->allowCustomColors = $config['allow_custom_colors'] ?? true;
                break;

            case 'lightswitch':
            case 'toggle':
                $field = new Lightswitch();
                $field->default = $config['default'] ?? false;
                $field->onLabel = $config['on_label'] ?? 'On';
                $field->offLabel = $config['off_label'] ?? 'Off';
                break;

            case 'money':
                $field = new Money();
                $field->currency = $config['currency'] ?? 'USD';
                $field->showCurrency = $config['show_currency'] ?? true;
                $field->min = $config['min'] ?? null;
                $field->max = $config['max'] ?? null;
                break;

            case 'range':
                $field = new Range();
                $field->min = $config['min'] ?? 0;
                $field->max = $config['max'] ?? 100;
                $field->step = $config['step'] ?? 1;
                $field->suffix = $config['suffix'] ?? '';
                break;

            case 'button_group':
            case 'buttongroup':
                $field = new ButtonGroup();
                $field->options = $this->prepareButtonGroupOptions($config['options'] ?? []);
                break;

            case 'icon':
                $field = new Icon();
                // Icon field configuration is handled through the field settings
                break;

            case 'table':
                $field = new Table();
                $field->columns = $this->prepareTableColumns($config['columns'] ?? []);
                $field->defaults = $config['defaults'] ?? [];
                $field->addRowLabel = $config['add_row_label'] ?? 'Add a row';
                $field->maxRows = $config['max_rows'] ?? null;
                $field->minRows = $config['min_rows'] ?? null;
                break;

            case 'categories':
                $field = new Categories();
                $field->allowLimit = $config['allow_limit'] ?? true;
                $field->selectionLabel = $config['selection_label'] ?? 'Choose categories';
                // Note: Category group sources and limits would need to be configured after creation
                break;

            case 'entries':
                $field = new Entries();
                $field->allowLimit = $config['allow_limit'] ?? true;
                $field->selectionLabel = $config['selection_label'] ?? 'Choose entries';
                // Note: Entry sources and limits would need to be configured after creation
                break;

            case 'tags':
                $field = new Tags();
                $field->allowLimit = $config['allow_limit'] ?? true;
                $field->selectionLabel = $config['selection_label'] ?? 'Choose tags';
                // Note: Tag group sources and limits would need to be configured after creation
                break;

            case 'users':
                $field = new Users();
                $field->allowLimit = $config['allow_limit'] ?? true;
                $field->selectionLabel = $config['selection_label'] ?? 'Choose users';
                // Note: User limits would need to be configured after creation
                break;

            case 'matrix':
                // Matrix fields require block types which are complex to create programmatically
                // For now, we'll skip creating Matrix fields and guide users to the admin panel
                Craft::warning("Matrix fields require block type configuration and cannot be created via this tool.", __METHOD__);
                return null;

            default:
                Craft::error("Unsupported field type: $fieldType", __METHOD__);
                return null;
        }

        if ($field) {
            $field->name = $config['name'];
            $field->handle = $config['handle'];
            $field->instructions = $config['instructions'] ?? '';
            $field->searchable = $config['searchable'] ?? false;
            $field->translationMethod = 'none';

            // Apply type-specific configurations
            $this->applyTypeSpecificConfig($field, $config);
        }

        return $field;
    }

    /**
     * Prepare options array for dropdown, radio, checkbox, and multi-select fields
     * 
     * @param array $options
     * @return array
     */
    public function prepareOptions(array $options): array
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
     * 
     * @param array $options
     * @return array
     */
    public function prepareButtonGroupOptions(array $options): array
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
     * Prepare columns array for table fields
     * 
     * @param array $columns
     * @return array
     */
    public function prepareTableColumns(array $columns): array
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
     * 
     * @param string $name
     * @return string
     */
    public function createHandle(string $name): string
    {
        // Convert to camelCase handle
        $handle = trim(preg_replace('/[^a-zA-Z0-9]/', ' ', $name));
        $handle = str_replace(' ', '', ucwords(strtolower($handle)));
        return lcfirst($handle);
    }

    /**
     * Apply type-specific configuration to a field
     * 
     * @param \craft\base\FieldInterface $field
     * @param array $config
     */
    private function applyTypeSpecificConfig($field, array $config): void
    {
        // Apply common configuration
        if (isset($config['required'])) {
            $field->required = (bool)$config['required'];
        }

        if (isset($config['placeholder']) && property_exists($field, 'placeholder')) {
            $field->placeholder = $config['placeholder'];
        }

        if (isset($config['default']) && property_exists($field, 'default')) {
            $field->default = $config['default'];
        }

        if (isset($config['min']) && property_exists($field, 'min')) {
            $field->min = $config['min'];
        }

        if (isset($config['max']) && property_exists($field, 'max')) {
            $field->max = $config['max'];
        }

        if (isset($config['columnType']) && property_exists($field, 'columnType')) {
            $field->columnType = $config['columnType'];
        }

        // Apply asset-specific configuration
        if ($field instanceof Assets) {
            if (isset($config['sources'])) {
                $field->sources = $config['sources'];
            }
            if (isset($config['restrict_files'])) {
                $field->restrictFiles = (bool)$config['restrict_files'];
            }
            if (isset($config['allowed_kinds'])) {
                $field->allowedKinds = $config['allowed_kinds'];
            }
            if (isset($config['max_relations'])) {
                $field->maxRelations = $config['max_relations'];
            }
            if (isset($config['view_mode'])) {
                $field->viewMode = $config['view_mode'];
            }
        }

        // Apply number-specific configuration
        if ($field instanceof Number) {
            if (isset($config['suffix'])) {
                $field->suffix = $config['suffix'];
            }
            if (isset($config['size'])) {
                $field->size = $config['size'];
            }
        }

        // Apply text-specific configuration
        if ($field instanceof PlainText) {
            if (isset($config['char_limit'])) {
                $field->charLimit = $config['char_limit'];
            }
            if (isset($config['code'])) {
                $field->code = (bool)$config['code'];
            }
        }
    }

    /**
     * Get all supported field types
     * 
     * @return array
     */
    public function getSupportedFieldTypes(): array
    {
        return [
            'plain_text' => 'Plain Text',
            'rich_text' => 'Rich Text (CKEditor)',
            'image' => 'Image',
            'asset' => 'Asset',
            'number' => 'Number',
            'url' => 'URL',
            'dropdown' => 'Dropdown',
            'radio_buttons' => 'Radio Buttons',
            'checkboxes' => 'Checkboxes',
            'multi_select' => 'Multi-select',
            'country' => 'Country',
            'date' => 'Date',
            'time' => 'Time',
            'email' => 'Email',
            'color' => 'Color',
            'lightswitch' => 'Lightswitch',
            'money' => 'Money',
            'range' => 'Range',
            'button_group' => 'Button Group',
            'icon' => 'Icon',
            'table' => 'Table',
            'categories' => 'Categories',
            'entries' => 'Entries',
            'tags' => 'Tags',
            'users' => 'Users',
            'matrix' => 'Matrix (not fully supported)'
        ];
    }
}