<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * Money field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class MoneyField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Money field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Money::class);
        
        return new FieldDefinition([
            'type' => 'money',
            'craftClass' => \craft\fields\Money::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['money'], // Manual
            'llmDocumentation' => 'money: currency (string), showCurrency (boolean), min (number), max (number)', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'updateFactory' => [$this, 'updateField'], // Update factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a Money field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\Money();
        
        // Apply Money-specific settings exactly as in original implementation
        $field->currency = $config['currency'] ?? 'USD';
        $field->showCurrency = $config['showCurrency'] ?? true;
        if (isset($config['min'])) {
            $field->min = $config['min'];
        }
        if (isset($config['max'])) {
            $field->max = $config['max'];
        }

        return $field;
    }

    /**
     * Update a Money field with new settings
     * Exact copy of legacy logic from FieldService::legacyUpdateField
     */
    public function updateField(FieldInterface $field, array $updates): array
    {
        $modifications = [];
        
        if (isset($updates['currency'])) {
            $field->currency = $updates['currency'];
            $modifications[] = "Updated currency to {$updates['currency']}";
        }
        
        return $modifications;
    }

    /**
     * Get test cases for Money field
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Money field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Money',
                            'handle' => 'testMoney',
                            'field_type' => 'money'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Money field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        if (isset($config['currency']) && !is_string($config['currency'])) {
            $errors[] = 'currency must be a string';
        }

        if (isset($config['showCurrency']) && !is_bool($config['showCurrency'])) {
            $errors[] = 'showCurrency must be a boolean';
        }

        if (isset($config['min']) && !is_numeric($config['min'])) {
            $errors[] = 'min must be a number';
        }

        if (isset($config['max']) && !is_numeric($config['max'])) {
            $errors[] = 'max must be a number';
        }

        return $errors;
    }
}