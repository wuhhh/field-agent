<?php

namespace craftcms\fieldagent\services;

use Craft;
use craft\base\Component;
use craft\helpers\StringHelper;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\records\Section as SectionRecord;
use craft\enums\PropagationMethod;
use craftcms\fieldagent\Plugin;

class SectionService extends Component
{
    /**
     * Create a section from config
     *
     * @param array $config Section configuration
     * @param array $createdEntryTypes Array of created entry types
     * @return Section|null The created section or null on failure
     */
    public function createSectionFromConfig(array $config, array $createdEntryTypes = []): ?Section
    {
        $section = new Section();

        // Basic settings
        $section->name = $config['name'] ?? 'Untitled Section';
        $section->handle = $config['handle'] ?? $this->generateHandle($section->name);
        $section->type = $config['type'] ?? Section::TYPE_CHANNEL; // single, channel, or structure

        // Additional settings
        $section->enableVersioning = $config['enableVersioning'] ?? true;
        $section->maxAuthors = $config['maxAuthors'] ?? 1;
        $section->defaultPlacement = $config['defaultPlacement'] ?? Section::DEFAULT_PLACEMENT_END;

        // Handle propagation method
        if (isset($config['propagationMethod'])) {
            // Convert string to PropagationMethod enum
            switch ($config['propagationMethod']) {
                case 'all':
                    $section->propagationMethod = PropagationMethod::All;
                    break;
                case 'siteGroup':
                    $section->propagationMethod = PropagationMethod::SiteGroup;
                    break;
                case 'language':
                    $section->propagationMethod = PropagationMethod::Language;
                    break;
                case 'custom':
                    $section->propagationMethod = PropagationMethod::Custom;
                    break;
                case 'none':
                default:
                    $section->propagationMethod = PropagationMethod::None;
                    break;
            }
        }

        // Structure-specific settings
        if ($section->type === Section::TYPE_STRUCTURE) {
            $section->maxLevels = $config['maxLevels'] ?? null;
        }

        // Site settings
        $allSites = Craft::$app->getSites()->getAllSites();
        $siteSettings = [];

        foreach ($allSites as $site) {
            $siteConfig = $config['siteSettings'][$site->handle] ?? $config;

            $siteSetting = new Section_SiteSettings();
            $siteSetting->siteId = $site->id;
            $siteSetting->enabledByDefault = $siteConfig['enabledByDefault'] ?? true;
            $siteSetting->hasUrls = $siteConfig['hasUrls'] ?? true;

            if ($siteSetting->hasUrls) {
                // Convert handle to kebab-case for URI (e.g., cakeRecipes -> cake-recipes)
                $kebabHandle = StringHelper::toKebabCase($section->handle);
                
                // Provide a default URI format if none specified
                $defaultUriFormat = $section->type === Section::TYPE_SINGLE ?
                    $kebabHandle :
                    $kebabHandle . '/{slug}';

                // Provide a default template path if none specified
                $defaultTemplate = $section->handle . '/_entry';

                $siteSetting->uriFormat = $siteConfig['uri'] ?? $siteConfig['uriFormat'] ?? $defaultUriFormat;
                $siteSetting->template = $siteConfig['template'] ?? $defaultTemplate;
            }

            $siteSettings[$site->id] = $siteSetting;
        }

        $section->setSiteSettings($siteSettings);

        // Find entry types to associate with this section
        $entryTypesToAssociate = [];
        $missingEntryTypes = [];

        if (isset($config['entryTypes']) && is_array($config['entryTypes'])) {
            foreach ($config['entryTypes'] as $entryTypeConfig) {
                // Handle both string handles and objects with handle property
                if (is_array($entryTypeConfig) && isset($entryTypeConfig['handle'])) {
                    $entryTypeHandle = $entryTypeConfig['handle'];
                } elseif (is_string($entryTypeConfig)) {
                    $entryTypeHandle = $entryTypeConfig;
                } else {
                    Craft::warning("Invalid entry type configuration in section '{$section->name}'", __METHOD__);
                    continue;
                }

                $found = false;
                // Find the entry type in our created entry types
                foreach ($createdEntryTypes as $createdEntryType) {
                    if ($createdEntryType['handle'] === $entryTypeHandle) {
                        $entryType = Craft::$app->getEntries()->getEntryTypeById($createdEntryType['id']);
                        if ($entryType) {
                            $entryTypesToAssociate[] = $entryType;
                            $found = true;
                        }
                        break;
                    }
                }

                if (!$found) {
                    $missingEntryTypes[] = $entryTypeHandle;
                }
            }
        }

        // If there were missing entry types, log error and fail
        if (!empty($missingEntryTypes)) {
            $missingList = implode(', ', $missingEntryTypes);
            Craft::error("Section '{$section->name}' requires entry types that were not found or failed to create: $missingList", __METHOD__);
            return null;
        }

        // If no entry types specified, create a default one
        if (empty($entryTypesToAssociate) && empty($config['entryTypes'])) {
            $defaultEntryType = new EntryType();
            $defaultEntryType->name = $config['defaultEntryTypeName'] ?? $section->name;
            $defaultEntryType->handle = $config['defaultEntryTypeHandle'] ?? $section->handle;
            $defaultEntryType->hasTitleField = true;

            $fieldLayout = new FieldLayout();
            $fieldLayout->type = EntryType::class;
            $defaultEntryType->setFieldLayout($fieldLayout);

            $entryTypesToAssociate[] = $defaultEntryType;
        }

        // If entry types were specified but none found, fail gracefully
        if (empty($entryTypesToAssociate)) {
            Craft::error("Section '{$section->name}' cannot be created without at least one entry type. Check that the specified entry types exist and were created successfully.", __METHOD__);
            return null;
        }

        // Set the entry types
        $section->setEntryTypes($entryTypesToAssociate);

        // Save the section
        if (!Craft::$app->getEntries()->saveSection($section)) {
            $errors = $section->getErrors();
            if (empty($errors)) {
                $errorMsg = "Section save failed with no specific errors";
                Craft::error($errorMsg, __METHOD__);
                throw new \Exception($errorMsg);
            } else {
                $errorMessages = [];
                foreach ($errors as $attribute => $messages) {
                    foreach ($messages as $message) {
                        $errorMsg = "Section save error on $attribute: $message";
                        Craft::error($errorMsg, __METHOD__);
                        $errorMessages[] = $errorMsg;
                    }
                }
                throw new \Exception("Section validation failed: " . implode(', ', $errorMessages));
            }
        }

        Craft::info("Created section: {$section->name} (handle: {$section->handle}, type: {$section->type})", __METHOD__);

        return $section;
    }

    /**
     * Get section by handle
     *
     * @param string $handle
     * @return Section|null
     */
    public function getSectionByHandle(string $handle): ?Section
    {
        return Craft::$app->getEntries()->getSectionByHandle($handle);
    }

    /**
     * Check if section exists
     *
     * @param string $handle
     * @return bool
     */
    public function sectionExists(string $handle): bool
    {
        return $this->getSectionByHandle($handle) !== null;
    }

    /**
     * Delete section by handle
     *
     * @param string $handle
     * @return bool
     */
    public function deleteSectionByHandle(string $handle): bool
    {
        $section = $this->getSectionByHandle($handle);

        if (!$section) {
            return false;
        }

        return Craft::$app->getEntries()->deleteSection($section);
    }

    /**
     * Generate a handle from a name
     *
     * @param string $name
     * @return string
     */
    private function generateHandle(string $name): string
    {
        // Convert to lowercase, replace spaces with underscores, remove special chars
        $handle = strtolower($name);
        $handle = preg_replace('/[^a-z0-9]+/', '_', $handle);
        $handle = trim($handle, '_');

        return $handle;
    }

    /**
     * Get section types
     *
     * @return array
     */
    public function getSectionTypes(): array
    {
        return [
            Section::TYPE_SINGLE => 'Single',
            Section::TYPE_CHANNEL => 'Channel',
            Section::TYPE_STRUCTURE => 'Structure',
        ];
    }
}
