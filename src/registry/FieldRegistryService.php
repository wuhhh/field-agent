<?php

namespace craftcms\fieldagent\registry;

use Craft;
use craft\base\Component;
use craftcms\fieldagent\fieldTypes\FieldTypeInterface;
use yii\base\InvalidArgumentException;

/**
 * Central registry managing all field type metadata with hybrid auto/manual registration
 */
class FieldRegistryService extends Component
{
    /**
     * Registered field definitions
     *
     * @var FieldDefinition[]
     */
    private array $registeredFields = [];

    /**
     * Field introspector for auto-discovery
     *
     * @var FieldIntrospector
     */
    private FieldIntrospector $introspector;

    /**
     * Cache for generated schemas and documentation
     *
     * @var array
     */
    private array $cache = [];

    /**
     * Initialize the service
     */
    public function init(): void
    {
        parent::init();
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register a field type with definition
     *
     * @param string $type Field type identifier
     * @param FieldDefinition $definition Field definition
     * @return void
     * @throws InvalidArgumentException
     */
    public function registerField(string $type, FieldDefinition $definition): void
    {
        if (empty($type)) {
            throw new InvalidArgumentException('Field type cannot be empty');
        }

        if (isset($this->registeredFields[$type])) {
            Craft::warning("Field type '{$type}' is already registered, overriding", __METHOD__);
        }

        $definition->type = $type;
        $this->registeredFields[$type] = $definition;
        
        // Clear cache when registry changes
        $this->clearCache();
        
        Craft::info("Registered field type: {$type}", __METHOD__);
    }

    /**
     * Register a field type using a FieldTypeInterface implementation
     *
     * @param FieldTypeInterface $fieldType
     * @return void
     */
    public function registerFieldType(FieldTypeInterface $fieldType): void
    {
        $definition = $fieldType->register();
        $this->registerField($definition->type, $definition);
    }

    /**
     * Auto-register all native Craft field types
     *
     * @return int Number of fields registered
     */
    public function autoRegisterNativeFields(): int
    {
        $count = 0;
        $allFieldTypes = $this->introspector->analyzeAllFieldTypes();

        foreach ($allFieldTypes as $craftClass => $metadata) {
            try {
                $fieldType = $this->deriveFieldTypeFromClass($craftClass);
                
                if ($this->hasField($fieldType)) {
                    continue; // Skip if already manually registered
                }

                $definition = new FieldDefinition([
                    'type' => $fieldType,
                    'craftClass' => $craftClass,
                    'autoDiscoveredData' => $metadata,
                    'testCases' => $this->introspector->generateBaseTestCases($metadata)
                ]);

                $this->registerField($fieldType, $definition);
                $count++;
            } catch (\Exception $e) {
                Craft::error("Failed to auto-register field type {$craftClass}: {$e->getMessage()}", __METHOD__);
            }
        }

        Craft::info("Auto-registered {$count} native field types", __METHOD__);
        return $count;
    }

    /**
     * Get a field definition by type
     *
     * @param string $type Field type identifier
     * @return FieldDefinition|null
     */
    public function getField(string $type): ?FieldDefinition
    {
        // Direct match
        if (isset($this->registeredFields[$type])) {
            return $this->registeredFields[$type];
        }

        // Check aliases
        foreach ($this->registeredFields as $definition) {
            if ($definition->matches($type)) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * Get all registered field definitions
     *
     * @return FieldDefinition[]
     */
    public function getAllFields(): array
    {
        return $this->registeredFields;
    }

    /**
     * Check if a field type is registered
     *
     * @param string $type Field type identifier
     * @return bool
     */
    public function hasField(string $type): bool
    {
        return $this->getField($type) !== null;
    }

    /**
     * Get all registered field type identifiers
     *
     * @return array
     */
    public function getFieldTypes(): array
    {
        return array_keys($this->registeredFields);
    }

    /**
     * Generate JSON schema for all registered fields
     *
     * @return array Schema definition
     */
    public function generateSchema(): array
    {
        $cacheKey = 'schema';
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $fieldTypes = [];
        $fieldDefinitions = [];

        foreach ($this->registeredFields as $type => $definition) {
            $fieldTypes[] = $type;
            
            // Add aliases to the enum as well
            foreach ($definition->aliases as $alias) {
                if (!in_array($alias, $fieldTypes)) {
                    $fieldTypes[] = $alias;
                }
            }

            // Build field-specific schema information
            $fieldDefinitions[$type] = [
                'displayName' => $definition->getDisplayName(),
                'icon' => $definition->getIcon(),
                'settingsAttributes' => $definition->getSettingsAttributes(),
                'craftClass' => $definition->craftClass
            ];
        }

        sort($fieldTypes);

        $schema = [
            'fieldTypes' => $fieldTypes,
            'fieldDefinitions' => $fieldDefinitions,
            'generatedAt' => date('c'),
            'totalFields' => count($this->registeredFields)
        ];

        $this->cache[$cacheKey] = $schema;
        return $schema;
    }

    /**
     * Generate LLM documentation for all registered fields
     *
     * @return string Formatted documentation
     */
    public function generateLLMDocumentation(): string
    {
        $cacheKey = 'llm_documentation';
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $documentation = [];
        $fieldTypes = $this->getFieldTypes();
        sort($fieldTypes);

        $documentation[] = "Available field types (alphabetized):";
        $documentation[] = implode(', ', $fieldTypes);
        $documentation[] = "";
        $documentation[] = "Field type settings and documentation:";

        foreach ($this->registeredFields as $type => $definition) {
            $doc = $definition->llmDocumentation;
            
            if (empty($doc)) {
                // Generate basic documentation from auto-discovered data
                $merged = $definition->getMergedSettings();
                $displayName = $merged['displayName'] ?? $type;
                $attributes = $definition->getSettingsAttributes();
                
                if (!empty($attributes)) {
                    $doc = "{$type}: Available settings - " . implode(', ', $attributes);
                } else {
                    $doc = "{$type}: {$displayName} field type";
                }
            }

            $documentation[] = $doc;
        }

        $result = implode("\n", $documentation);
        $this->cache[$cacheKey] = $result;
        return $result;
    }

    /**
     * Clear the internal cache
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Get registry statistics
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $autoDiscovered = 0;
        $manuallyEnhanced = 0;
        
        foreach ($this->registeredFields as $definition) {
            if (!empty($definition->autoDiscoveredData) && empty($definition->manualSettings)) {
                $autoDiscovered++;
            } else {
                $manuallyEnhanced++;
            }
        }

        return [
            'totalFields' => count($this->registeredFields),
            'autoDiscovered' => $autoDiscovered,
            'manuallyEnhanced' => $manuallyEnhanced,
            'fieldTypes' => $this->getFieldTypes()
        ];
    }

    /**
     * Derive field type identifier from Craft class name
     *
     * @param string $craftClass
     * @return string
     */
    private function deriveFieldTypeFromClass(string $craftClass): string
    {
        // Extract class name from namespace
        $parts = explode('\\', $craftClass);
        $className = end($parts);
        
        // Convert CamelCase to snake_case
        $snakeCase = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $className));
        
        // Remove 'Field' suffix if present
        return str_replace('_field', '', $snakeCase);
    }

    /**
     * Validate registry state
     *
     * @return array Validation errors
     */
    public function validateRegistry(): array
    {
        $errors = [];

        foreach ($this->registeredFields as $type => $definition) {
            if (empty($definition->craftClass)) {
                $errors[] = "Field type '{$type}' has no Craft class defined";
            }

            if (!class_exists($definition->craftClass)) {
                $errors[] = "Field type '{$type}' references non-existent class: {$definition->craftClass}";
            }
        }

        return $errors;
    }
}