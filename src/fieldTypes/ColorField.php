<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * Color field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class ColorField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Color field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Color::class);
        
        return new FieldDefinition([
            'type' => 'color',
            'craftClass' => \craft\fields\Color::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['color'], // Manual
            'llmDocumentation' => 'color: allowCustomColors (boolean), palette (array of {color, label} objects)', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'updateFactory' => [$this, 'updateField'], // Update factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a Color field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\Color();
        $field->allowCustomColors = $config['allowCustomColors'] ?? true;
        if (isset($config['palette'])) {
            $field->palette = $config['palette'];
        } else {
            // Default palette
            $field->palette = [
                ['color' => '#ff0000', 'label' => 'Red'],
                ['color' => '#00ff00', 'label' => 'Green'],
                ['color' => '#0000ff', 'label' => 'Blue'],
            ];
        }
        return $field;
    }

    /**
     * Update field instance with new configuration
     * FEATURE PARITY: Legacy update method had no color-specific logic
     * Adding all settings from createField to maintain feature parity
     */
    public function updateField(FieldInterface $field, array $updates): array
    {
        $modifications = [];
        
        // FEATURE PARITY: Add all settings from createField
        if (isset($updates['allowCustomColors'])) {
            $field->allowCustomColors = (bool)$updates['allowCustomColors'];
            $modifications[] = "Updated allowCustomColors to " . ($updates['allowCustomColors'] ? 'true' : 'false');
        }
        
        if (isset($updates['palette'])) {
            $field->palette = $updates['palette'];
            $modifications[] = "Updated color palette";
        }
        
        return $modifications;
    }

    /**
     * Get test cases for Color field
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Color field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Color',
                            'handle' => 'testColor',
                            'field_type' => 'color'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Color field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        if (isset($config['allowCustomColors']) && !is_bool($config['allowCustomColors'])) {
            $errors[] = 'allowCustomColors must be a boolean';
        }

        if (isset($config['palette']) && !is_array($config['palette'])) {
            $errors[] = 'palette must be an array';
        }

        return $errors;
    }
}