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
│       ├── FieldCreationService.php         # Core field creation (currently unused)
│       ├── FieldGeneratorService.php        # Core field creation
│       ├── LLMIntegrationService.php        # LLM service requests / integration
│       ├── LLMOperationsService.php         # AI operations generation
│       ├── OperationsExecutorService.php    # Operation execution
│       ├── PruneService.php                 # Storage clean up
│       ├── RollbackService.php              # Operation rollback
│       ├── SchemaValidationService.php      # JSON config validation
│       ├── SectionGeneratorService.php      # Section creation
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

### Reserved Field Protection
The system automatically prevents use of Craft CMS reserved field handles:
`author`, `authorId`, `content`, `dateCreated`, `dateUpdated`, `id`, `slug`, `title`, `uid`, `uri`, `url`, `level`, `lft`, `rgt`, `root`, `parent`, `parentId`, `children`, `descendants`, `ancestors`, `next`, `prev`, `siblings`, `status`, `enabled`, `archived`, `trashed`, `postDate`, `expiryDate`, `revisionCreator`, `revisionNotes`, `section`, `sectionId`, `type`, `typeId`, `field`, `fieldId`
