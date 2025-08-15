<?php

namespace craftcms\fieldagent\fieldTypes;

use Craft;
use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;
use craftcms\fieldagent\registry\FieldIntrospector;

/**
 * Users field type implementation
 * Following Table field pattern for the hook-based field registration system
 */
class UsersField implements FieldTypeInterface
{
    private FieldIntrospector $introspector;

    public function __construct()
    {
        $this->introspector = new FieldIntrospector();
    }

    /**
     * Register the Users field type with complete definition
     */
    public function register(): FieldDefinition
    {
        // Get auto-discovered base data from Craft APIs
        $autoData = $this->introspector->analyzeFieldType(\craft\fields\Users::class);
        
        return new FieldDefinition([
            'type' => 'users',
            'craftClass' => \craft\fields\Users::class,
            'autoDiscoveredData' => $autoData,  // 80% automated
            'aliases' => ['users'], // Manual
            'llmDocumentation' => 'users: maxRelations (number), sources (array of user group handles or "*" for all)', // Manual
            'factory' => [$this, 'createField'], // Manual factory method
            'testCases' => $this->getTestCases() // Enhanced from auto-generated base
        ]);
    }

    /**
     * Create a Users field instance from configuration
     * Preserves exact logic from original FieldService implementation
     */
    public function createField(array $config): FieldInterface
    {
        $field = new \craft\fields\Users();
        $field->maxRelations = $config['maxRelations'] ?? 1;
        $field->viewMode = 'list';
        
        // Configure sources (user groups)
        if (isset($config['sources']) && is_array($config['sources'])) {
            $userService = \Craft::$app->getUsers();
            $sources = [];
            foreach ($config['sources'] as $groupHandle) {
                if ($groupHandle === '*') {
                    // Allow all users
                    $sources = '*';
                    break;
                }
                $group = $userService->getGroupByHandle($groupHandle);
                if ($group) {
                    $sources[] = 'group:' . $group->uid;
                }
            }
            $field->sources = $sources ?: '*';
        } else {
            // Default to all users
            $field->sources = '*';
        }
        
        return $field;
    }

    /**
     * Get test cases for Users field
     */
    public function getTestCases(): array
    {
        return [
            [
                'name' => 'Basic Users field creation',
                'operation' => [
                    'type' => 'create',
                    'target' => 'field',
                    'create' => [
                        'field' => [
                            'name' => 'Test Users',
                            'handle' => 'testUsers',
                            'field_type' => 'users'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate Users field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        if (isset($config['maxRelations']) && !is_numeric($config['maxRelations'])) {
            $errors[] = 'maxRelations must be a number';
        }

        if (isset($config['sources']) && !is_array($config['sources'])) {
            $errors[] = 'sources must be an array';
        }

        return $errors;
    }
}