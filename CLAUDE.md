# Claude Context - Craft CMS Field Agent Plugin

## Project Overview
You're helping develop a Craft CMS 5.8+ plugin that uses AI to generate fields, sections, and entry types from natural language. The plugin is written using PHP.

## Key Components

### 1. Craft CMS Installation
- Located at: `/Users/huwroberts/Sites/ddev/craft`
- Running in DDEV environment
- Project config files in: `config/project/`

### 2. Field Agent Plugin - CONTEXT-AWARE SYSTEM
- Location: `plugins/field-agent/`
- Installed as local Composer package
- Stores configurations in `/storage/field-agent/configs/`

#### Plugin Architecture:
```
plugins/field-agent/
├── composer.json
├── src/
│   ├── Plugin.php
│   ├── console/controllers/
│   │   └── DiscoveryController.php          # Project analysis commands
│   │   ├── GeneratorController.php          # Main command interface
│   ├── models/
│   │   └── Operation.php
│   │   ├── Settings.php
│   ├── presets/
│   │   └── blog-operations.json
│   │   ├── portfolio-operations.json
│   ├── schemas/
│   │   └── llm-operations-schema.json       # Operations schema
│   └── services/
│       ├── ConfigurationService.php         # Plugin configuration
│       ├── DiscoveryService.php             # Project context analysis
│       ├── EntryTypeService.php             # Entry type creation and management
│       ├── FieldService.php                 # Core field creation
│       ├── LLMIntegrationService.php        # LLM service requests / integration
│       ├── LLMOperationsService.php         # AI operations generation
│       ├── OperationsExecutorService.php    # Operation execution
│       ├── PruneService.php                 # Storage clean up
│       ├── RollbackService.php              # Operation rollback
│       ├── SectionService.php               # Section creation
│       ├── StatisticsService.php            # Stats and reports
│       ├── TestingService.php               # Test related functionality
│       └── tools/                           # Discovery tools
│           ├── BaseTool.php                 # Base discovery tool interface
│           ├── CheckHandleAvailability.php  # Check handle availability
│           ├── GetEntryTypeFields.php       # Discover field <-> entry type relations
│           ├── GetFields.php                # Fields and their configuration
│           └── GetSections.php              # Sections and their entry types
└── tests/                                   # Comprehensive test suite
    ├── basic-operations/                    # Basic field/entry/section tests
    ├── advanced-operations/                 # Matrix fields, complex structures
    ├── integration-tests/                   # Complete site scenarios
    └── edge-cases/                          # Conflicts, rollbacks, errors
```

## Generator Commands - `field-agent/generator/<command>`

### Context-aware:
  `prompt <prompt>` - Generate fields/sections from natural language  

#### Examples:

`field-agent/generator/prompt "Create a blog section with title, content, and featured image"`  
`field-agent/generator/prompt "Add author and tags fields to the blog entry type"`

### Basic:

  `generate <config>` - Generate from JSON config file or stored config  
  `list` - List available configurations and presets

### Rollback:

`rollback <id>` - Rollback a specific operation by ID  
`rollback-last` - Rollback the most recent operation  
`rollback-all` - Rollback all operations (requires confirmation)  
`operations` - List all field generation operations

### Test:

`test-list` - List all available test suites  
`test-run <name`> - Run a specific test  
`test-suite <category>` - Run all tests in a category  
`test-all` - Run all tests

### Utility:

`test-llm [provider]` - Test LLM API connection  
`check-keys` - Check API key configuration  
`export-prompt` - Export LLM prompt and schema for manual testing  
`stats` - Show storage statistics  
`sync-config` - Force project config sync  
`test-discovery` - Test the discovery service

### Maintenance:

`prune-rolled-back` - Remove rolled back operation records  
`prune-configs` [days] - Remove old config files  
`prune` - Remove stale data (rolled back ops + old configs)  
`delete-operations` - Delete ALL operation records (keeps content)  
`reset` - Delete ALL content AND operation records (nuclear!)

### Options:

`--debug` - Enable debug mode for verbose output  
`--cleanup` - Auto-cleanup test data after completion  
`--dry-run` - Generate config without creating fields  
`--force` - Skip confirmation prompts  
`--output=<path>` - Save generated config to file

## Discovery Commands - `field-agent/discovery/<command>`

`field-agent/discovery/test`  
`field-agent/discovery/tools`

## Adding Support for a New Field Type

There are numerous places in the codebase that need to be updated in order to support a new field type. Here are the steps you will need to take - it is VERY IMPORTANT that you are methodical in processing each of these tasks when adding support for a new field:

### 1. **Implement Field Creation Logic**
**File:** `plugins/field-agent/src/services/FieldService.php`

In the `createFieldFromConfig()` method, add a new `case` statement for your field type:
- Add the case statement matching your field type identifier
- Instantiate the appropriate Craft field class (e.g., `\craft\fields\YourFieldType()`)
- Map configuration settings from the normalized config to the field object properties
- Handle any field-specific settings or transformations

Example pattern:
```php
case 'your_field_type':
    $field = new \craft\fields\YourFieldType();
    $field->someProperty = $normalizedConfig['someProperty'] ?? 'default';
    // Add more field-specific configurations
    break;
```

### 2. **Field Types are Generated Dynamically**
**No manual schema updates needed!**

Field types are now automatically generated from the single source of truth in `FieldService::FIELD_TYPE_MAP`. The JSON schema and LLM prompts are updated dynamically at runtime.

Current field types (alphabetized):
```
addresses, asset, button_group, categories, checkboxes, color, content_block, 
country, date, dropdown, email, entries, icon, image, json, lightswitch, 
link, matrix, money, multi_select, number, plain_text, radio_buttons, 
range, rich_text, table, tags, time, users
```

**Important:** Do NOT manually edit the JSON schema enum - it's injected dynamically!

### 3. **Create Test Coverage**
**File:** `plugins/field-agent/tests/basic-operations/test-all-field-types.json`

Add a test case for your new field type:
- Create an operation that creates a field with your new type
- Include various settings configurations to test
- Update the `expectedOutcome.fieldTypes` array to include your field type

Example test operation:
```json
{
    "type": "create",
    "target": "field",
    "create": {
        "field": {
            "name": "Your Field Type Test",
            "handle": "fieldTestYourType",
            "field_type": "your_field_type",
            "settings": {
                // Add field-specific settings to test
            }
        }
    }
}
```

### 4. **Update LLM Prompts and Documentation**
**File:** `plugins/field-agent/src/services/LLMOperationsService.php`

In the system prompt generation methods:
- Update any documentation that lists available field types
- Add examples showing how to use your new field type
- Include information about field-specific settings

### 5. **Optional: Add Preset Examples**
**Files:** `plugins/field-agent/src/presets/*.json`

Consider adding examples of your field type in preset configurations to demonstrate proper usage.

### Field Type Implementation Checklist

When adding a new field type, ensure you complete all these steps:

- Add case statement in `FieldService.php` method `createFieldFromConfig()`
- Add field type to schema enum in `llm-operations-schema.json`
- Creat test case in `test-all-field-types.json`
- Updat LLM prompt documentation if needed
- Add preset examples (optional but recommended)
- Test field creation via console command
- Verify field appears correctly in Craft CP
