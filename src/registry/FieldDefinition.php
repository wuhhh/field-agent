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
     * Optional factory callable for updating field instances
     *
     * @var callable|null
     */
    public $updateFactory = null;

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

    /**
     * Create a field instance using the factory if available
     *
     * @param array $config Field configuration
     * @return \craft\base\FieldInterface|null
     */
    public function createField(array $config): ?\craft\base\FieldInterface
    {
        if ($this->factory && is_callable($this->factory)) {
            return call_user_func($this->factory, $config);
        }
        
        return null;
    }

    /**
     * Update a field instance using the update factory if available
     *
     * @param \craft\base\FieldInterface $field The field to update
     * @param array $updates Updates to apply
     * @return array Array of modifications made
     */
    public function updateField(\craft\base\FieldInterface $field, array $updates): array
    {
        if ($this->updateFactory && is_callable($this->updateFactory)) {
            return call_user_func($this->updateFactory, $field, $updates);
        }
        
        return [];
    }

    /**
     * Check if this field type has an update method registered
     *
     * @return bool
     */
    public function hasUpdateMethod(): bool
    {
        return $this->updateFactory !== null && is_callable($this->updateFactory);
    }
}