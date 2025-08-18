<?php

namespace craftcms\fieldagent\fieldTypes;

use craftcms\fieldagent\registry\FieldDefinition;

/**
 * URL field type implementation - alias for Link field
 * Provides a more intuitive field type name for URL-specific link fields
 */
class UrlField extends LinkField
{
    /**
     * Register the URL field type as an alias for Link
     */
    public function register(): FieldDefinition
    {
        // Get the base Link field definition
        $definition = parent::register();
        
        // Override the type to be 'url' instead of 'link'
        $definition->type = 'url';
        
        // Update aliases
        $definition->aliases = ['url', 'link'];
        
        // Update documentation to clarify this is for URLs
        $definition->llmDocumentation = 'url: Alias for link field type. Use for URL/link fields. Supports types (array), sources (array), showLabelField (boolean), etc. Common usage: types:["url"] for external links only.';
        
        return $definition;
    }
}