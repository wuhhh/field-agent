# Field Agent Plugin for Craft CMS
⚠️ **ALPHA SOFTWARE - USE WITH CAUTION** ⚠️

This plugin uses AI to generate Craft CMS fields, entry types, and sections from natural language prompts. The plugin supports all native Craft CMS field types (as of Craft 5.8 and including the new Content Block) as well as CKEditor fields.

**WARNING: This is an experimental plugin and you should not use it on production sites. Always backup your database before testing it.**

**Note: Rollback functionality currently only supports CREATE operations - field/entry type/section modifications and deletions cannot be rolled back.**

## Alpha Installation

This plugin is not yet available in the Craft Plugin Store. To install for testing:

```bash
composer require wuhhh/field-agent:v1.0.4-alpha
./craft plugin/install field-agent && ./craft plugin/enable field-agent
```

### Configuration Setup

1. **Create config file** - Copy the plugin's config file to your project:
```bash
cp vendor/wuhhh/field-agent/config/field-agent.php config/field-agent.php
```

2. **Add API key** to `.env` (you only need one):
```bash
# For Anthropic Claude (default)
ANTHROPIC_API_KEY=sk-ant-your-key-here

# OR for OpenAI (optional)
OPENAI_API_KEY=sk-your-key-here
```

3. **Set default provider** in `config/field-agent.php` if desired:
```php
'defaultProvider' => 'anthropic', // or 'openai'
```

Test your setup:
```bash
./craft field-agent/generator/test-llm
```

## Basic Usage

The plugin analyzes your existing Craft setup and creates or modifies fields/entry types/sections based on natural language prompts.

**Main command:**
```bash
./craft field-agent/generator/prompt "your description here"
```

**Examples:**
```bash
# Create new structures
./craft field-agent/generator/prompt "Create a portfolio section with project fields"

# Modify existing structures  
./craft field-agent/generator/prompt "Add author and featured image to blog posts"
```

The system will automatically:
- Analyse existing project structure
- Avoid naming conflicts with reserved Craft handles
- Reuse existing fields when appropriate
- Create fields, then entry types, then sections in proper order

## Commands

### Generation
```bash
# Main prompt command (uses your configured default provider)
./craft field-agent/generator/prompt "description" [--debug]

# Override provider for single command
./craft field-agent/generator/prompt "description" openai

# Debug mode (shows full AI request/response)  
./craft field-agent/generator/prompt "description" --debug
```

### Operation Management
```bash
# List all generation operations
./craft field-agent/generator/operations

# Rollback specific operation by ID
./craft field-agent/generator/rollback <operation-id>

# ⚠️ DESTRUCTIVE: Rollback ALL operations
./craft field-agent/generator/rollback-all

# Clean up old data
./craft field-agent/generator/prune-all --confirm=1
```

## Troubleshooting

### API Issues
```bash
# Test your API connection
./craft field-agent/generator/test-llm --debug

# Check environment variables are loaded
./craft field-agent/generator/test-llm
```

### Common Problems
- **401 errors:** Verify your API key is correct and has credits
- **Field conflicts:** System handles automatically, check operation history  
- **Operation failures:** Use `--debug` mode to see detailed error information

### Debug Mode
Add `--debug` to see full AI request/response and operation details:
```bash
./craft field-agent/generator/prompt "create blog fields" --debug
```

## What It Does

This plugin generates Craft CMS fields, entry types, and sections using AI from natural language descriptions. It analyzes your existing Craft setup to avoid conflicts and reuse appropriate fields.

**Key capabilities:**
- Understands all native Craft fields plus CKEditor
- Modifies existing entry types without breaking content
- Avoids reserved Craft handles and suggests alternatives  
- Creates related structures (category groups, tag groups) automatically
- Provides complete rollback of all operations

## Alpha Testing Notes

This is experimental software. It works well but may have edge cases. Please:

- Test on development sites only
- Backup your database before major operations
- Report issues at: https://github.com/wuhhh/field-agent/issues
- Use `--debug` mode to understand what's happening

## License

Proprietary - Commercial plugin for Craft CMS
