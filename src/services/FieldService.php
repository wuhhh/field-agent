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
		'url' => \craft\fields\Link::class, // Alias for link - common in natural language
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
	 * Update an existing field from config array
	 * This method applies settings to an existing field using the same patterns as creation
	 */
	public function updateFieldFromConfig(Field $field, array $updates): array
	{
		// Get field type using our clean mapping
		$fieldType = $this->getFieldTypeFromInstance($field);

		// Only update fields we recognize and have registered
		if (!$fieldType) {
			Craft::warning("Skipping update for unrecognized field type: " . get_class($field), __METHOD__);
			return [];
		}

		// Use registry system for field updates
		try {
			$registry = Plugin::getInstance()->fieldRegistryService;
			$fieldDefinition = $registry->getField($fieldType);

			if (!$fieldDefinition) {
				Craft::warning("Field type '{$fieldType}' is not registered in the field registry - skipping", __METHOD__);
				return [];
			}

			if (!$fieldDefinition->hasUpdateMethod()) {
				Craft::info("Field type '{$fieldType}' does not support updates - skipping", __METHOD__);
				return [];
			}

			// Update field using registry
			$modifications = $fieldDefinition->updateField($field, $updates);

			if (!empty($modifications)) {
				Craft::info("Updated field '{$fieldType}' using registry system", __METHOD__);
			}

			return $modifications;
		} catch (\Exception $e) {
			Craft::error("Failed to update field '{$fieldType}': {$e->getMessage()}", __METHOD__);
			// Return empty array instead of throwing - fail silently
			return [];
		}
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

		if (!$fieldType) {
			throw new Exception("Field type is required");
		}

		// Use registry system for field creation
		try {
			$registry = Plugin::getInstance()->fieldRegistryService;
			$fieldDefinition = $registry->getField($fieldType);

			if (!$fieldDefinition) {
				throw new Exception("Field type '{$fieldType}' is not registered in the field registry");
			}

			// Create field using registry
			$field = $fieldDefinition->createField($normalizedConfig);

			if (!$field) {
				throw new Exception("Failed to create field instance for type '{$fieldType}'");
			}

			Craft::info("Created field '{$fieldType}' using registry system", __METHOD__);
		} catch (\Exception $e) {
			throw new Exception("Failed to create field '{$fieldType}': {$e->getMessage()}");
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
	 * Check if a field handle is reserved
	 */
	public function isReservedFieldHandle(string $handle): bool
	{
		$reservedWords = Field::RESERVED_HANDLES;

		return in_array($handle, $reservedWords, true);
	}

}
