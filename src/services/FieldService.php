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
	 * Create matrix block types (entry types) from configuration
	 * TODO: Move this to fieldTypes/MatrixField.php
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
								throw new Exception("Failed to save field '{$blockField->name}' for block type '{$entryType->name}'");
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
	 * Check if a field handle is reserved
	 */
	public function isReservedFieldHandle(string $handle): bool
	{
		$reservedWords = Field::RESERVED_HANDLES;

		return in_array($handle, $reservedWords, true);
	}

	/**
	 * Add a new entry type to an existing matrix field
	 * TODO: Move this to fieldTypes/MatrixField.php
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
	 * TODO: Move this to fieldTypes/MatrixField.php
	 */
	public function removeMatrixEntryType(\craft\fields\Matrix $matrixField, string $entryTypeHandle): bool
	{
		$fieldsService = \Craft::$app->getFields();
		$entriesService = \Craft::$app->getEntries();

		// Get existing entry types
		$existingEntryTypes = $matrixField->getEntryTypes();

		// Filter out the entry type to remove
		$remainingEntryTypes = array_filter($existingEntryTypes, function ($entryType) use ($entryTypeHandle) {
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
	 * TODO: Move this to fieldTypes/MatrixField.php
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
				$layoutElements = array_filter($layoutElements, function ($element) use ($fieldHandle) {
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
