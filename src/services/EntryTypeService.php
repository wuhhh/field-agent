<?php

namespace craftcms\fieldagent\services;

use Craft;
use craft\base\Component;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use yii\base\Exception;

/**
 * Entry Type Service
 *
 * Handles entry type creation and management for the Field Agent plugin
 */
class EntryTypeService extends Component
{
    /**
     * Create an entry type from configuration
     *
     * @param array $config Entry type configuration
     * @param array $createdFields Array of recently created fields
     * @return EntryType|null
     */
    public function createEntryTypeFromConfig(array $config, array $createdFields = []): ?EntryType
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
     * Create a field layout for an entry type
     *
     * @param array $config Entry type configuration
     * @param array $createdFields Array of recently created fields
     * @param bool $hasTitleField Whether the entry type has a title field
     * @return FieldLayout|null
     */
    public function createFieldLayout(array $config, array $createdFields = [], bool $hasTitleField = true): ?FieldLayout
    {
        $fieldLayout = new FieldLayout();
        $fieldLayout->type = EntryType::class;

        $elements = [];

        // Add title field if enabled
        if ($hasTitleField) {
            $titleField = new EntryTitleField();
            $titleField->required = true;
            $elements[] = $titleField;
        }

        // Add custom fields
        if (isset($config['fields'])) {
            $fieldsService = Craft::$app->getFields();

            foreach ($config['fields'] as $fieldRef) {
                $handle = $fieldRef['handle'];

                // Try to find field in recently created fields first
                $field = null;
                foreach ($createdFields as $createdField) {
                    if ($createdField['handle'] === $handle) {
                        $field = $fieldsService->getFieldById($createdField['id']);
                        break;
                    }
                }

                // If not found in created fields, try to find existing field
                if (!$field) {
                    $field = $fieldsService->getFieldByHandle($handle);
                }

                if ($field) {
                    $element = new CustomField($field);
                    $element->required = $fieldRef['required'] ?? false;
                    $elements[] = $element;
                } else {
                    Craft::warning("Field '{$handle}' not found for entry type", __METHOD__);
                }
            }
        }

        // Set up the field layout
        $fieldLayout->setTabs([
            [
                'name' => $config['tabName'] ?? 'Content',
                'elements' => $elements,
            ]
        ]);

        return $fieldLayout;
    }

    /**
     * Update an existing entry type
     *
     * @param EntryType $entryType
     * @param array $config
     * @param array $createdFields
     * @return bool
     */
    public function updateEntryType(EntryType $entryType, array $config, array $createdFields = []): bool
    {
        // Update basic properties if provided
        if (isset($config['name'])) {
            $entryType->name = $config['name'];
        }

        if (isset($config['hasTitleField'])) {
            $entryType->hasTitleField = $config['hasTitleField'];
        }

        if (isset($config['titleFormat'])) {
            $entryType->titleFormat = $config['titleFormat'];
        }

        // Update field layout if fields are provided
        if (isset($config['fields'])) {
            $fieldLayout = $this->createFieldLayout($config, $createdFields, $entryType->hasTitleField);
            if ($fieldLayout) {
                $entryType->setFieldLayout($fieldLayout);
            }
        }

        try {
            return Craft::$app->getEntries()->saveEntryType($entryType);
        } catch (\Exception $e) {
            Craft::error('Exception updating entry type: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Add fields to an existing entry type
     *
     * @param EntryType $entryType
     * @param array $fieldHandles Array of field handles to add
     * @param bool $required Whether the fields should be required
     * @return bool
     */
    public function addFieldsToEntryType(EntryType $entryType, array $fieldHandles, bool $required = false): bool
    {
        $fieldLayout = $entryType->getFieldLayout();
        if (!$fieldLayout) {
            $fieldLayout = new FieldLayout();
            $fieldLayout->type = EntryType::class;
        }

        $fieldsService = Craft::$app->getFields();
        $tabs = $fieldLayout->getTabs();
        $contentTab = null;

        // Find or create the Content tab
        if (!empty($tabs)) {
            $contentTab = $tabs[0];
        } else {
            $contentTab = [
                'name' => 'Content',
                'elements' => []
            ];
        }

        // Get existing elements
        $elements = $contentTab['elements'] ?? [];

        // Add new fields
        foreach ($fieldHandles as $handle) {
            $field = $fieldsService->getFieldByHandle($handle);
            if ($field) {
                // Check if field is already in the layout
                $alreadyExists = false;
                foreach ($elements as $element) {
                    if ($element instanceof CustomField && $element->getField()->handle === $handle) {
                        $alreadyExists = true;
                        break;
                    }
                }

                if (!$alreadyExists) {
                    $element = new CustomField($field);
                    $element->required = $required;
                    $elements[] = $element;
                }
            }
        }

        // Update the tab
        $contentTab['elements'] = $elements;
        $fieldLayout->setTabs([$contentTab]);
        $entryType->setFieldLayout($fieldLayout);

        try {
            return Craft::$app->getEntries()->saveEntryType($entryType);
        } catch (\Exception $e) {
            Craft::error('Exception adding fields to entry type: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Remove fields from an entry type
     *
     * @param EntryType $entryType
     * @param array $fieldHandles Array of field handles to remove
     * @return bool
     */
    public function removeFieldsFromEntryType(EntryType $entryType, array $fieldHandles): bool
    {
        $fieldLayout = $entryType->getFieldLayout();
        if (!$fieldLayout) {
            return true; // Nothing to remove
        }

        $tabs = $fieldLayout->getTabs();
        if (empty($tabs)) {
            return true; // Nothing to remove
        }

        $contentTab = $tabs[0];
        $elements = $contentTab['elements'] ?? [];
        $newElements = [];

        // Filter out the fields to remove
        foreach ($elements as $element) {
            if ($element instanceof CustomField) {
                $field = $element->getField();
                if (!in_array($field->handle, $fieldHandles)) {
                    $newElements[] = $element;
                }
            } else {
                // Keep non-field elements (like title field)
                $newElements[] = $element;
            }
        }

        // Update the tab
        $contentTab['elements'] = $newElements;
        $fieldLayout->setTabs([$contentTab]);
        $entryType->setFieldLayout($fieldLayout);

        try {
            return Craft::$app->getEntries()->saveEntryType($entryType);
        } catch (\Exception $e) {
            Craft::error('Exception removing fields from entry type: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Get all entry types
     *
     * @return array
     */
    public function getAllEntryTypes(): array
    {
        $sections = Craft::$app->getSections()->getAllSections();
        $entryTypes = [];

        foreach ($sections as $section) {
            $sectionEntryTypes = $section->getEntryTypes();
            foreach ($sectionEntryTypes as $entryType) {
                $entryTypes[] = [
                    'id' => $entryType->id,
                    'name' => $entryType->name,
                    'handle' => $entryType->handle,
                    'sectionId' => $section->id,
                    'sectionHandle' => $section->handle,
                    'fieldCount' => count($entryType->getFieldLayout()->getCustomFields())
                ];
            }
        }

        return $entryTypes;
    }

    /**
     * Get entry type by handle within a section
     *
     * @param string $sectionHandle
     * @param string $entryTypeHandle
     * @return EntryType|null
     */
    public function getEntryTypeByHandle(string $sectionHandle, string $entryTypeHandle): ?EntryType
    {
        $section = Craft::$app->getSections()->getSectionByHandle($sectionHandle);
        if (!$section) {
            return null;
        }

        $entryTypes = $section->getEntryTypes();
        foreach ($entryTypes as $entryType) {
            if ($entryType->handle === $entryTypeHandle) {
                return $entryType;
            }
        }

        return null;
    }
}
