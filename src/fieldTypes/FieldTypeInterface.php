<?php

namespace craftcms\fieldagent\fieldTypes;

use craft\base\FieldInterface;
use craftcms\fieldagent\registry\FieldDefinition;

/**
 * Standard interface for field type registration in the hook-based system
 */
interface FieldTypeInterface
{
    /**
     * Register the field type with complete definition
     *
     * @return FieldDefinition
     */
    public function register(): FieldDefinition;

    /**
     * Create a field instance from configuration
     *
     * @param array $config Field configuration
     * @return FieldInterface
     */
    public function createField(array $config): FieldInterface;

    /**
     * Get test cases for this field type
     *
     * @return array
     */
    public function getTestCases(): array;

    /**
     * Validate field configuration
     *
     * @param array $config Configuration to validate
     * @return array Array of validation errors (empty if valid)
     */
    public function validate(array $config): array;
}