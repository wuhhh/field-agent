<?php

namespace craftcms\fieldagent\registry;

/**
 * Standardized field metadata container supporting both auto-discovered and manual data
 */
class FieldDefinition
{
    /**
     * Field type identifier (e.g., 'table', 'plain_text')
     *
     * @var string
     */
    public string $type;

    /**
     * Craft CMS field class (e.g., '\craft\fields\Table')
     *
     * @var string
     */
    public string $craftClass;

    /**
     * Alternative names for this field type
     *
     * @var array
     */
    public array $aliases = [];

    /**
     * Data automatically discovered from Craft APIs
     *
     * @var array
     */
    public array $autoDiscoveredData = [];

    /**
     * Human-defined settings and overrides
     *
     * @var array
     */
    public array $manualSettings = [];

    /**
     * LLM-specific documentation for this field
     *
     * @var string
     */
    public string $llmDocumentation = '';

    /**
     * Optional factory callable for custom field creation
     *
     * @var callable|null
     */
    public $factory = null;

    /**
     * Test cases for this field type
     *
     * @var array
     */
    public array $testCases = [];

    /**
     * Constructor
     *
     * @param array $data Initial data to populate the definition
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $property => $value) {
            if (property_exists($this, $property)) {
                $this->$property = $value;
            }
        }
    }

    /**
     * Get merged settings combining auto-discovered and manual data
     *
     * Manual settings take precedence over auto-discovered data
     *
     * @return array
     */
    public function getMergedSettings(): array
    {
        return array_merge($this->autoDiscoveredData, $this->manualSettings);
    }

    /**
     * Get all available settings attributes
     *
     * @return array
     */
    public function getSettingsAttributes(): array
    {
        $merged = $this->getMergedSettings();
        return $merged['settingsAttributes'] ?? [];
    }

    /**
     * Get validation rules for this field type
     *
     * @return array
     */
    public function getValidationRules(): array
    {
        $merged = $this->getMergedSettings();
        return $merged['validationRules'] ?? [];
    }

    /**
     * Get display name for this field type
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        $merged = $this->getMergedSettings();
        return $merged['displayName'] ?? $this->type;
    }

    /**
     * Get icon identifier for this field type
     *
     * @return string
     */
    public function getIcon(): string
    {
        $merged = $this->getMergedSettings();
        return $merged['icon'] ?? '';
    }

    /**
     * Check if this field type matches a given type or alias
     *
     * @param string $type Type or alias to check
     * @return bool
     */
    public function matches(string $type): bool
    {
        return $this->type === $type || in_array($type, $this->aliases);
    }
}