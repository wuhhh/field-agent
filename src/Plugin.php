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

        Craft::info(
            Craft::t('field-agent', '{name} plugin loaded', ['name' => $this->name]),
            __METHOD__
        );
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
