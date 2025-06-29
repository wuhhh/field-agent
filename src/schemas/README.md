# Field Agent JSON Schemas

This directory contains JSON schemas for validating AI/LLM generated field configurations.

## Files

### `llm-output-schema-v2.json`
The improved JSON schema with field-type-specific settings validation. This schema:
- Enforces proper field types and naming conventions
- Validates required properties and data types
- Limits field types to a proven subset for proof of concept
- Ensures handle naming follows camelCase conventions
- Validates field-specific settings (e.g., dropdown options, number ranges)

### `example-llm-output.json`
A complete example showing the expected JSON structure that matches the schema. This example demonstrates:
- All supported field types
- Proper handle naming conventions
- Field-specific settings configuration
- Entry type and section definitions
- Best practices for field configuration

## Supported Field Types (Limited Set)

For the initial proof of concept, we support these field types:

- **plain_text**: Single or multi-line text fields
- **rich_text**: WYSIWYG editor with formatting
- **image**: Image upload fields
- **number**: Numeric fields with decimal support
- **url**: URL fields for links
- **dropdown**: Selection fields with predefined options
- **lightswitch**: Boolean on/off fields

## Usage in LLM Integration

The schema is used by:

1. **LLMIntegrationService**: Validates responses from AI APIs
2. **SchemaValidationService**: Performs validation and normalization
3. **System Prompts**: Instructs the AI on expected output format

## Schema Validation

The schema enforces:
- Required properties (name, handle, field_type)
- Handle format validation (camelCase starting with lowercase)
- Field type restrictions
- Settings validation per field type
- Array size limits for practical use
- String length limits for database compatibility

## Future Expansion

To add new field types:
1. Add the type to the `field_type` enum in the schema
2. Add validation logic in `SchemaValidationService`
3. Add creation logic in `GeneratorController`
4. Update the system prompt in `LLMIntegrationService`
5. Test with example configurations

## Testing

Use the test command to validate the integration:
```bash
ddev craft field-agent/generator/test-llm anthropic
ddev craft field-agent/generator/test-llm openai
```

Example usage:
```bash
ddev craft field-agent/generator/prompt "Create a blog with title, content, and featured image" anthropic
```