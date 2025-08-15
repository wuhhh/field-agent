<?php

namespace craftcms\fieldagent\registry;

use Craft;
use craft\base\FieldInterface;
use yii\base\InvalidArgumentException;

/**
 * Automated field metadata discovery service using Craft's APIs
 */
class FieldIntrospector
{
    /**
     * Analyze all available field types in the Craft installation
     *
     * @return array Array of field type data keyed by class name
     */
    public function analyzeAllFieldTypes(): array
    {
        $allFieldTypes = [];
        $fieldTypes = Craft::$app->fields->getAllFieldTypes();

        foreach ($fieldTypes as $fieldType) {
            $classData = $this->analyzeFieldType($fieldType);
            $allFieldTypes[$fieldType] = $classData;
        }

        return $allFieldTypes;
    }

    /**
     * Analyze a specific field type class
     *
     * @param string $craftClass Craft field class name
     * @return array Field type metadata
     */
    public function analyzeFieldType(string $craftClass): array
    {
        if (!class_exists($craftClass)) {
            throw new InvalidArgumentException("Field class {$craftClass} does not exist");
        }

        try {
            // Create a temporary instance to introspect
            $field = new $craftClass();

            if (!$field instanceof FieldInterface) {
                throw new InvalidArgumentException("Class {$craftClass} is not a valid field type");
            }

            return [
                'craftClass' => $craftClass,
                'displayName' => $field->displayName(),
                'icon' => $field->icon(),
                'settingsAttributes' => $this->getSettingsAttributes($field),
                'validationRules' => $this->getValidationRules($field),
                'phpType' => $this->getPhpType($field),
                'dbType' => $this->getDbType($field),
                'supportsTranslation' => method_exists($field, 'getTranslationDescription'),
                'isSearchable' => $field::isSelectable(),
            ];
        } catch (\Exception $e) {
            // Log the error and return basic data
            Craft::error("Failed to introspect field type {$craftClass}: {$e->getMessage()}", __METHOD__);
            
            return [
                'craftClass' => $craftClass,
                'displayName' => $this->extractClassNameFromFqn($craftClass),
                'icon' => '',
                'settingsAttributes' => [],
                'validationRules' => [],
                'phpType' => 'mixed',
                'dbType' => 'text',
                'supportsTranslation' => false,
                'isSearchable' => true,
                'introspectionError' => $e->getMessage()
            ];
        }
    }

    /**
     * Discover field settings from a field instance
     *
     * @param FieldInterface $field Field instance
     * @return array Settings configuration
     */
    public function discoverFieldSettings(FieldInterface $field): array
    {
        $settings = [];
        
        // Get all defined settings attributes
        $settingsAttributes = $this->getSettingsAttributes($field);
        
        foreach ($settingsAttributes as $attribute) {
            if (property_exists($field, $attribute)) {
                $settings[$attribute] = $field->$attribute ?? null;
            }
        }

        return $settings;
    }

    /**
     * Extract validation rules from a field instance
     *
     * @param FieldInterface $field Field instance
     * @return array Validation rules
     */
    public function extractValidationRules(FieldInterface $field): array
    {
        return $this->getValidationRules($field);
    }

    /**
     * Generate base test cases from field metadata
     *
     * @param array $metadata Field metadata from analyzeFieldType
     * @return array Base test cases
     */
    public function generateBaseTestCases(array $metadata): array
    {
        $testCases = [];
        
        // Generate basic creation test
        $testCases[] = [
            'name' => 'Basic ' . $metadata['displayName'] . ' field creation',
            'operation' => [
                'type' => 'create',
                'target' => 'field',
                'create' => [
                    'field' => [
                        'name' => 'Test ' . $metadata['displayName'],
                        'handle' => 'test' . str_replace(' ', '', $metadata['displayName']),
                        'field_type' => $this->deriveFieldType($metadata['craftClass']),
                        'settings' => $this->generateBasicSettings($metadata)
                    ]
                ]
            ]
        ];

        return $testCases;
    }

    /**
     * Get settings attributes from a field instance
     *
     * @param FieldInterface $field
     * @return array
     */
    private function getSettingsAttributes(FieldInterface $field): array
    {
        // Check if the field extends from craft\base\Field which has settingsAttributes() method
        if ($field instanceof \craft\base\Field && method_exists($field, 'settingsAttributes')) {
            try {
                return $field->settingsAttributes();
            } catch (\Exception $e) {
                Craft::warning("Failed to get settings attributes for " . get_class($field) . ": " . $e->getMessage(), __METHOD__);
            }
        }

        // Fallback: analyze class properties
        $reflection = new \ReflectionClass($field);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        
        $attributes = [];
        foreach ($properties as $property) {
            // Skip inherited properties from base classes
            if ($property->getDeclaringClass()->getName() !== get_class($field)) {
                continue;
            }
            
            $attributes[] = $property->getName();
        }

        return $attributes;
    }

    /**
     * Get validation rules from a field instance
     *
     * @param FieldInterface $field
     * @return array
     */
    private function getValidationRules(FieldInterface $field): array
    {
        // Check if the field extends from craft\base\Field which has rules() method
        if ($field instanceof \craft\base\Field && method_exists($field, 'rules')) {
            try {
                return $field->rules();
            } catch (\Exception $e) {
                Craft::warning("Failed to get validation rules for " . get_class($field) . ": " . $e->getMessage(), __METHOD__);
            }
        }

        return [];
    }

    /**
     * Get PHP type from a field instance
     *
     * @param FieldInterface $field
     * @return string
     */
    private function getPhpType(FieldInterface $field): string
    {
        // Check if the field extends from craft\base\Field which has phpType() method
        if ($field instanceof \craft\base\Field && method_exists($field, 'phpType')) {
            try {
                return $field->phpType();
            } catch (\Exception $e) {
                Craft::warning("Failed to get PHP type for " . get_class($field) . ": " . $e->getMessage(), __METHOD__);
            }
        }

        return 'mixed';
    }

    /**
     * Get database type from a field instance
     *
     * @param FieldInterface $field
     * @return string
     */
    private function getDbType(FieldInterface $field): string
    {
        // Check if the field extends from craft\base\Field which has getContentColumnType() method
        if ($field instanceof \craft\base\Field && method_exists($field, 'getContentColumnType')) {
            try {
                return $field->getContentColumnType();
            } catch (\Exception $e) {
                Craft::warning("Failed to get DB type for " . get_class($field) . ": " . $e->getMessage(), __METHOD__);
            }
        }

        return 'text';
    }

    /**
     * Extract simple class name from fully qualified name
     *
     * @param string $fqn Fully qualified class name
     * @return string Simple class name
     */
    private function extractClassNameFromFqn(string $fqn): string
    {
        $parts = explode('\\', $fqn);
        return end($parts);
    }

    /**
     * Derive field type identifier from Craft class name
     *
     * @param string $craftClass
     * @return string
     */
    private function deriveFieldType(string $craftClass): string
    {
        $className = $this->extractClassNameFromFqn($craftClass);
        
        // Convert CamelCase to snake_case
        $snakeCase = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $className));
        
        // Remove 'Field' suffix if present
        return str_replace('_field', '', $snakeCase);
    }

    /**
     * Generate basic settings for test cases
     *
     * @param array $metadata
     * @return array
     */
    private function generateBasicSettings(array $metadata): array
    {
        $settings = [];
        
        // Add some basic settings based on common attributes
        $settingsAttributes = $metadata['settingsAttributes'] ?? [];
        
        foreach ($settingsAttributes as $attribute) {
            switch ($attribute) {
                case 'required':
                    $settings[$attribute] = false;
                    break;
                case 'searchable':
                    $settings[$attribute] = true;
                    break;
                case 'translationMethod':
                    $settings[$attribute] = 'site';
                    break;
            }
        }

        return $settings;
    }
}