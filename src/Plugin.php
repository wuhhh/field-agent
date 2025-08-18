<?php

namespace craftcms\fieldagent;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\base\Model;
use craftcms\fieldagent\services\FieldService;
use craftcms\fieldagent\services\RollbackService;
use craftcms\fieldagent\services\SectionService;
use craftcms\fieldagent\services\LLMIntegrationService;
use craftcms\fieldagent\services\LLMOperationsService;
use craftcms\fieldagent\services\OperationsExecutorService;
use craftcms\fieldagent\services\PruneService;
use craftcms\fieldagent\services\TestingService;
use craftcms\fieldagent\services\ConfigurationService;
use craftcms\fieldagent\services\StatisticsService;
use craftcms\fieldagent\services\EntryTypeService;
use craftcms\fieldagent\models\Settings;
use craftcms\fieldagent\services\DiscoveryService;
use craftcms\fieldagent\registry\FieldRegistryService;

/**
 * Field Generator plugin
 *
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 * @property-read FieldService $fieldService
 * @property-read RollbackService $rollbackService
 * @property-read SectionService $sectionService
 * @property-read LLMIntegrationService $llmIntegrationService
 * @property-read LLMOperationsService $llmOperationsService
 * @property-read OperationsExecutorService $operationsExecutorService
 * @property-read PruneService $pruneService
 * @property-read DiscoveryService $discoveryService
 * @property-read TestingService $testingService
 * @property-read ConfigurationService $configurationService
 * @property-read StatisticsService $statisticsService
 * @property-read EntryTypeService $entryTypeService
 * @property-read FieldRegistryService $fieldRegistryService
 * @author Craft CMS
 * @since 1.0.0
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = false;

    public static function config(): array
    {
        return [
            'components' => [
                'fieldService' => FieldService::class,
                'rollbackService' => RollbackService::class,
                'sectionService' => SectionService::class,
                'llmIntegrationService' => LLMIntegrationService::class,
                'llmOperationsService' => LLMOperationsService::class,
                'operationsExecutorService' => OperationsExecutorService::class,
                'pruneService' => PruneService::class,
                'discoveryService' => DiscoveryService::class,
                'testingService' => TestingService::class,
                'configurationService' => ConfigurationService::class,
                'statisticsService' => StatisticsService::class,
                'entryTypeService' => EntryTypeService::class,
                'fieldRegistryService' => FieldRegistryService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        // Register console commands
        if (Craft::$app instanceof \craft\console\Application) {
            $this->controllerNamespace = 'craftcms\\fieldagent\\console\\controllers';
        }

        // Initialize field registry
        $this->initializeFieldRegistry();

        Craft::info(
            Craft::t('field-agent', '{name} plugin loaded', ['name' => $this->name]),
            __METHOD__
        );
    }

    /**
     * Initialize the field registry with all field types
     */
    private function initializeFieldRegistry(): void
    {
        try {
            $registry = $this->fieldRegistryService;

            // Auto-register native Craft field types
            $autoCount = $registry->autoRegisterNativeFields();

            // Register manually enhanced field types
            $this->registerEnhancedFieldTypes($registry);

            $totalFields = count($registry->getAllFields());
            Craft::info("Field registry initialized with {$totalFields} field types ({$autoCount} auto-discovered)", __METHOD__);

        } catch (\Exception $e) {
            Craft::error("Failed to initialize field registry: {$e->getMessage()}", __METHOD__);
        }
    }

    /**
     * Register manually enhanced field types
     */
    private function registerEnhancedFieldTypes(FieldRegistryService $registry): void
    {
        // List all manually enhanced field type classes
        $fieldTypeClasses = [
            \craftcms\fieldagent\fieldTypes\TableField::class,
            \craftcms\fieldagent\fieldTypes\PlainTextField::class,
            \craftcms\fieldagent\fieldTypes\EmailField::class,
            \craftcms\fieldagent\fieldTypes\NumberField::class,
            \craftcms\fieldagent\fieldTypes\LightswitchField::class,
            \craftcms\fieldagent\fieldTypes\CountryField::class,
            \craftcms\fieldagent\fieldTypes\DropdownField::class,
            \craftcms\fieldagent\fieldTypes\RichTextField::class,
            \craftcms\fieldagent\fieldTypes\AssetField::class,
            \craftcms\fieldagent\fieldTypes\MoneyField::class,
            \craftcms\fieldagent\fieldTypes\AddressesField::class,
            \craftcms\fieldagent\fieldTypes\ColorField::class,
            \craftcms\fieldagent\fieldTypes\DateField::class,
            \craftcms\fieldagent\fieldTypes\TimeField::class,
            \craftcms\fieldagent\fieldTypes\RangeField::class,
            \craftcms\fieldagent\fieldTypes\IconField::class,
            \craftcms\fieldagent\fieldTypes\JsonField::class,
            \craftcms\fieldagent\fieldTypes\RadioButtonsField::class,
            \craftcms\fieldagent\fieldTypes\CheckboxesField::class,
            \craftcms\fieldagent\fieldTypes\MultiSelectField::class,
            \craftcms\fieldagent\fieldTypes\ButtonGroupField::class,
            \craftcms\fieldagent\fieldTypes\UsersField::class,
            \craftcms\fieldagent\fieldTypes\EntriesField::class,
            \craftcms\fieldagent\fieldTypes\CategoriesField::class,
            \craftcms\fieldagent\fieldTypes\TagsField::class,
            \craftcms\fieldagent\fieldTypes\MatrixField::class,
            \craftcms\fieldagent\fieldTypes\ContentBlockField::class,
            \craftcms\fieldagent\fieldTypes\LinkField::class,
            \craftcms\fieldagent\fieldTypes\UrlField::class, // Alias for Link field
            \craftcms\fieldagent\fieldTypes\ImageField::class, // Alias for Assets
        ];

        foreach ($fieldTypeClasses as $className) {
            try {
                if (class_exists($className)) {
                    $fieldType = new $className();
                    $registry->registerFieldType($fieldType);
                }
            } catch (\Exception $e) {
                Craft::error("Failed to register field type {$className}: {$e->getMessage()}", __METHOD__);
            }
        }
    }

    public function getControllerNamespace(): string
    {
        if (Craft::$app instanceof \craft\console\Application) {
            return 'craftcms\\fieldagent\\console\\controllers';
        }

        return parent::getControllerNamespace();
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    /**
     * Get the plugin's storage path for configuration files
     */
    public function getStoragePath(): string
    {
        return Craft::$app->getPath()->getStoragePath() . DIRECTORY_SEPARATOR . 'field-agent';
    }

    /**
     * Ensure storage directory exists
     */
    public function ensureStorageDirectory(): void
    {
        $path = $this->getStoragePath();
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
