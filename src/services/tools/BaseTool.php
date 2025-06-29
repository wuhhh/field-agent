<?php
/**
 * Field Generator plugin for Craft CMS
 *
 * Base Discovery Tool interface
 */

namespace craftcms\fieldagent\services\tools;

use craft\base\Component;

/**
 * Base Tool
 * 
 * Abstract base class for all discovery tools
 */
abstract class BaseTool extends Component
{
    /**
     * Execute the tool with given parameters
     * 
     * @param array $params
     * @return array
     */
    abstract public function execute(array $params = []): array;

    /**
     * Get tool description
     * 
     * @return string
     */
    abstract public function getDescription(): string;

    /**
     * Get tool parameters schema
     * 
     * @return array
     */
    abstract public function getParameters(): array;

    /**
     * Validate parameters against schema
     * 
     * @param array $params
     * @return bool
     * @throws \Exception
     */
    protected function validateParameters(array $params): bool
    {
        $schema = $this->getParameters();
        
        foreach ($schema as $paramName => $paramConfig) {
            if ($paramConfig['required'] && !isset($params[$paramName])) {
                throw new \Exception("Required parameter '{$paramName}' is missing");
            }
            
            if (isset($params[$paramName]) && isset($paramConfig['type'])) {
                $actualType = gettype($params[$paramName]);
                $expectedType = $paramConfig['type'];
                
                if ($actualType !== $expectedType) {
                    throw new \Exception("Parameter '{$paramName}' must be of type {$expectedType}, {$actualType} given");
                }
            }
        }

        return true;
    }
}