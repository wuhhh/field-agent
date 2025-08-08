# Claude Context - Craft CMS Field Agent Plugin

## Project Overview
This is a Craft CMS 5.7.10 project with an advanced Field Agent plugin that uses AI to create and modify Craft fields, entry types, and sections from natural language prompts.

## Key Components

### 1. Craft CMS Installation
- Located at: `/Users/huwroberts/Sites/ddev/craft`
- Running in DDEV environment
- Project config files in: `config/project/`

### 2. Field Agent Plugin - CONTEXT-AWARE SYSTEM ✨
- Location: `plugins/field-agent/`
- Installed as local Composer package
- **Context-aware operations** that understand existing project structures
- **AI-powered modifications** of existing fields, entry types, and sections
- Stores configurations in `/storage/field-agent/configs/`

#### Plugin Architecture:
```
plugins/field-agent/
├── composer.json
├── src/
│   ├── Plugin.php
│   ├── console/controllers/
│   │   ├── GeneratorController.php          # Main command interface
│   │   └── DiscoveryController.php          # Project analysis commands
│   ├── models/
│   │   ├── Settings.php
│   │   └── Operation.php
│   ├── services/
│   │   ├── FieldGeneratorService.php        # Core field creation
│   │   ├── DiscoveryService.php             # Project context analysis ⭐
│   │   ├── LLMIntegrationService.php        # LLM service requests / integration
│   │   ├── LLMOperationsService.php         # AI operations generation ⭐
│   │   ├── OperationsExecutorService.php    # Operation execution ⭐
│   │   ├── RollbackService.php              # Operation rollback
│   │   ├── SectionGeneratorService.php      # Section creation
│   │   ├── SchemaValidationService.php
│   │   └── tools/                           # Discovery tools ⭐
│   │       ├── BaseTool.php
│   │       ├── GetFields.php
│   │       ├── GetSections.php
│   │       ├── GetEntryTypeFields.php
│   │       └── CheckHandleAvailability.php
│   └── schemas/
│       ├── llm-output-schema.json          # Legacy schema
│       └── llm-operations-schema.json      # New operations schema ⭐
└── tests/                                  # Comprehensive test suite ⭐
    ├── basic-operations/                   # Basic field/entry/section tests
    ├── advanced-operations/               # Matrix fields, complex structures
    ├── integration-tests/                 # Complete site scenarios
    └── edge-cases/                        # Conflicts, rollbacks, errors
```

## Important Commands

### 🎯 Context-Aware Field Generation (PRIMARY APPROACH)
```bash
# Generate AND modify from natural language - ONE intelligent command
ddev craft field-agent/generator/prompt "Add a featured image field to blog posts" [provider] [--debug]
ddev craft field-agent/generator/prompt "Create a portfolio section with project fields" [provider] [--debug]
ddev craft field-agent/generator/prompt "Modify the blog entry type to include author and tags" [provider] [--debug]
ddev craft field-agent/generator/prompt "Create a content block field with title, description, and image" [provider] [--debug]

# The system automatically determines whether to:
# - Create new fields/entry types/sections
# - Modify existing structures  
# - Reuse existing fields
# - Avoid conflicts with reserved names
```

### 🔍 Project Discovery & Analysis
```bash
# Analyze current project state
ddev craft field-agent/generator/test-discovery

# Get detailed field information
ddev craft field-agent/generator/discovery/fields

# Analyze sections and entry types
ddev craft field-agent/generator/discovery/sections

# Check handle availability
ddev craft field-agent/generator/discovery/check-handle <handle>
```

### 🤖 AI/LLM Integration (Enhanced)
```bash
# Test LLM API connection
ddev craft field-agent/generator/test-llm [provider] [--debug]

# Export prompt/schema for manual testing
ddev craft field-agent/generator/export-prompt

# Providers: anthropic (default), openai
# Set API keys: ANTHROPIC_API_KEY or OPENAI_API_KEY environment variables

# Debug mode shows full request/response and operation details
ddev craft field-agent/generator/prompt "Create a news section" anthropic --debug
```

### 🧪 Test Suite Commands
```bash
# Test framework commands
ddev craft field-agent/generator/test-list                              # List all available tests
ddev craft field-agent/generator/test-run <test-name> [--cleanup]       # Run individual test
ddev craft field-agent/generator/test-suite <category> [--cleanup]      # Run test category
ddev craft field-agent/generator/test-all [--cleanup]                   # Run complete test suite

# Test categories: basic-operations, advanced-operations, integration-tests, edge-cases

# Examples
ddev craft field-agent/generator/test-run ai-test-all-field-types       # Test all 22 field types
ddev craft field-agent/generator/test-run ai-test-all-field-types --cleanup  # Auto-cleanup after test
ddev craft field-agent/generator/test-suite basic-operations            # Test core operations
ddev craft field-agent/generator/test-all --cleanup                     # Full validation with cleanup
```

### 📋 Operation Management & Rollback
```bash
# List all field generation operations
ddev craft field-agent/generator/operations

# Rollback a specific operation by ID
ddev craft field-agent/generator/rollback <operation-id>

# Rollback ALL operations (with confirmation)
ddev craft field-agent/generator/rollback-all

# Prune old configurations and operations
ddev craft field-agent/generator/prune-all --confirm=1
```

### 🔧 Legacy Commands (Still Available)
```bash
# Generate from JSON config file or stored config name
ddev craft field-agent/generator/generate <config.json|stored-name>

# List stored configurations
ddev craft field-agent/generator/list
```

### 🧪 Test Suite Commands (NEW)
```bash
# List all available test suites organized by category
ddev craft field-agent/generator/test-list

# Run individual test by name (default: keep test data for inspection)
ddev craft field-agent/generator/test-run <test-name>
ddev craft field-agent/generator/test-run ai-test-all-field-types

# Run test with automatic cleanup (for quick validation)
ddev craft field-agent/generator/test-run ai-test-all-field-types --cleanup

# Run entire test category
ddev craft field-agent/generator/test-suite <category>
ddev craft field-agent/generator/test-suite basic-operations --cleanup

# Run all tests (comprehensive validation)
ddev craft field-agent/generator/test-all
ddev craft field-agent/generator/test-all --cleanup

# Test categories:
# - basic-operations: Field creation, entry types, sections
# - advanced-operations: Matrix fields, complex structures  
# - integration-tests: Complete site scenarios, relationships
# - edge-cases: Conflicts, rollbacks, error handling

# Test modes:
# - Default: Keep test data for manual inspection and debugging
# - --cleanup: Auto-remove test data after successful completion
```

### Apply Craft Config Changes
```bash
# MUST use this command to apply config changes
ddev craft up
```

## 🚀 Context-Aware Operations System

### How It Works
1. **Discovery Phase**: System analyzes existing fields, sections, and entry types
2. **AI Planning**: LLM generates appropriate operations based on context
3. **Intelligent Execution**: Operations executed in proper dependency order
4. **Conflict Prevention**: Automatic detection of handle conflicts and reserved names

### Operation Types
- **create** - Create new fields, entry types, or sections
- **modify** - Add/remove fields from entry types, update settings  
- **delete** - Remove fields, entry types, or sections (use sparingly)

### Example Workflows

#### ✨ Intelligent Modification
```bash
# User has existing blog section with title and content
ddev craft field-agent/generator/prompt "Add author and featured image to blog posts"

# System intelligently:
# 1. Creates 'author' and 'featuredImage' fields
# 2. Adds them to existing 'blogPost' entry type
# 3. Preserves all existing fields and settings
```

#### 🎯 Smart Field Reuse
```bash
# System detects existing 'featuredImage' field
ddev craft field-agent/generator/prompt "Create news section with title, content, and featured image"

# System intelligently:
# 1. Creates 'newsTitle' and 'newsContent' fields (avoiding reserved 'title'/'content')
# 2. Reuses existing 'featuredImage' field
# 3. Creates news section and entry type
```

#### 🛡️ Conflict Prevention
```bash
# System prevents reserved field names
ddev craft field-agent/generator/prompt "Create fields for title, content, and author"

# System intelligently:
# - Uses 'pageTitle' instead of reserved 'title'
# - Uses 'bodyContent' instead of reserved 'content'  
# - Uses 'writer' instead of reserved 'author'
```

## Field Types Supported (Complete Set - 21 Types)

### Text & Content (3 types)
- `plain_text` - Single/multi-line text with character limits
- `rich_text` - CKEditor WYSIWYG content
- `email` - Email validation

### Assets & Media (2 types) 
- `image` - Asset field for images with relation limits
- `asset` - General asset uploads

### Numbers & Measurements (3 types)
- `number` - Numeric fields with decimals, min/max
- `money` - Currency fields with currency settings
- `range` - Slider/range inputs

### Links (1 type)
- `link` - Link fields supporting URLs and entry links

### Selection & Choice (5 types)
- `dropdown` - Selection field with options
- `radio_buttons` - Radio button groups
- `checkboxes` - Multiple checkboxes
- `multi_select` - Multiple selection dropdown
- `country` - Country selection
- `button_group` - Button group selection

### Date & Time (2 types)
- `date` - Date picker with time options
- `time` - Time picker

### User Interface (3 types)
- `color` - Color picker
- `lightswitch` - Boolean on/off field
- `icon` - Icon picker

### Complex Structure (1 type)
- `matrix` - Flexible content blocks with configurable block types

### Reserved Field Protection
The system automatically prevents use of Craft CMS reserved field handles:
`author`, `authorId`, `content`, `dateCreated`, `dateUpdated`, `id`, `slug`, `title`, `uid`, `uri`, `url`, `level`, `lft`, `rgt`, `root`, `parent`, `parentId`, `children`, `descendants`, `ancestors`, `next`, `prev`, `siblings`, `status`, `enabled`, `archived`, `trashed`, `postDate`, `expiryDate`, `revisionCreator`, `revisionNotes`, `section`, `sectionId`, `type`, `typeId`, `field`, `fieldId`

**Automatic Alternatives:**
- `title` → `pageTitle`, `blogTitle`, `articleTitle`
- `content` → `bodyContent`, `mainContent`, `description`
- `author` → `writer`, `creator`, `byline`

## Plugin Features

### 🎯 Context-Aware Intelligence
- **Project Analysis**: Understands existing field layouts and structures
- **Smart Field Reuse**: Automatically reuses appropriate existing fields
- **Conflict Avoidance**: Prevents handle conflicts and reserved word usage
- **Dependency Management**: Ensures proper creation order (fields → entry types → sections)

### 🤖 Advanced AI Integration
- **Natural Language Processing**: Understands complex modification requests
- **Structured Output**: JSON schema validation ensures reliable results
- **Multi-Provider Support**: Anthropic Claude & OpenAI GPT-4 support
- **Debug Mode**: Complete request/response logging and operation tracking

### 📊 Operation Management
- **Complete Audit Trail**: Track all field generation activities
- **Granular Rollback**: Undo individual or bulk operations safely
- **Safety Checks**: Prevent deletion of fields/types with existing content
- **Operation History**: Full history with timestamps and source prompts

### 🔧 Developer Tools
- **Discovery API**: Programmatic access to project structure analysis
- **Export Tools**: Generate API request templates for external testing
- **Configuration Management**: Store, list, and reuse field configurations
- **Debug Tools**: Comprehensive logging and error reporting

### 🧪 Comprehensive Test Suite
- **AI-Generated Tests**: 4 critical test suites created using advanced LLM system
- **Complete Coverage**: All 22 field types, matrix structures, site relationships, edge cases
- **Context-Aware Validation**: Tests understand existing project state and prevent conflicts
- **Production-Ready Scenarios**: Real-world field generation and modification workflows
- **Test Categories**: 20 test files across basic operations, advanced operations, integration tests, and edge cases
- **Auto-Cleanup**: Optional `--cleanup` flag for quick validation workflows

## Current State - CONTEXT-AWARE SYSTEM COMPLETE ✅

### 🎯 Phase 1: Core System - ✅ COMPLETE
- ✅ **Context-aware operations system** with create/modify/delete operations
- ✅ **Discovery service** for project structure analysis
- ✅ **AI/LLM integration** with structured JSON schema validation
- ✅ **22 field types** fully supported with comprehensive settings
- ✅ **Reserved field protection** with intelligent alternatives
- ✅ **Operation dependency management** with proper ordering
- ✅ **Complete rollback system** with safety checks and audit trails

### 🚀 Phase 2: Advanced Intelligence - ✅ COMPLETE  
- ✅ **Smart field reuse** across different entry types
- ✅ **Conflict detection** and automatic resolution
- ✅ **Matrix field support** with AI-generated block types
- ✅ **End-to-end workflow** from natural language to functional CMS structures
- ✅ **Production-ready** field generation with comprehensive error handling

### 🧪 Phase 3: AI-Powered Test Suite - ✅ COMPLETE
- ✅ **Comprehensive test coverage** with 4 AI-generated test suites
- ✅ **Context-aware test generation** using advanced LLM prompt engineering
- ✅ **Complete field type validation** covering all 22 supported field types
- ✅ **Complex scenario testing** including matrix fields, site structures, edge cases
- ✅ **Automated conflict resolution validation** with reserved handle testing
- ✅ **Production-ready test framework** with rollback tracking and execution metrics

### 🎨 Key Achievements
- **Hybrid Architecture**: Discovery service + existing field generation APIs
- **Database Persistence**: Critical bug fix ensuring fields are actually saved
- **Intelligent Operations**: System decides between create/modify based on context
- **Complete Workflow**: Single command handles complex multi-step operations
- **Error Recovery**: Detailed error messages with suggested fixes

### 🌟 Milestone: AI-Powered CMS Management
The system successfully transforms natural language like:
> "Generate blog and landing page sections plus all necessary fields"

Into complete, functional Craft CMS structures with:
- 6 contextually appropriate fields created
- 2 entry types modified with proper field assignments  
- Existing structures preserved and enhanced
- Complete operation tracking and rollback capability

## Debug Features

### Context-Aware Debug Mode (`--debug` flag)
Shows complete operation flow:
- Project context analysis and existing structure detection
- AI prompt generation with schema validation
- Operation planning and dependency resolution
- Field creation and database persistence verification
- Entry type modification with field assignment tracking
- Complete request/response details with timing information

### Discovery Service Analysis
Real-time project state analysis:
- Field enumeration with type and setting details
- Section and entry type relationship mapping
- Handle availability checking
- Conflict detection and resolution suggestions

### Operation Tracking
Complete audit trail:
- Operation-by-operation execution logging
- Success/failure tracking with detailed error messages
- Rollback preparation with dependency analysis
- Performance metrics and timing information

## Architectural Insights

### Discovery Service Pattern
The discovery service acts as a "project intelligence layer" that:
- Queries Craft's APIs to understand current state
- Provides contextual information to AI for intelligent decisions
- Enables modification of existing structures rather than create-only
- Prevents conflicts through proactive analysis

### Operations-Based Approach
Instead of monolithic "create everything" configs, the system uses:
- Granular operations that can be combined intelligently
- Dependency-aware execution ensuring proper creation order
- Individual operation rollback for precise undo capabilities
- Context-sensitive operation generation based on existing structures

### Hybrid AI Architecture
Combines the best of both approaches:
- Discovery service for real-time project analysis
- Existing field generation APIs for reliable creation
- AI-powered operation planning for intelligent modifications
- Structured JSON schemas for consistent, validatable output

## Next Steps

### Phase 3: Advanced Features (Future)
- **Template Generation**: Use Rust tool to generate Twig templates based on field configurations
- **Performance Optimization**: Add caching for repeated prompts and responses
- **Batch Operations**: Generate multiple related content types in one operation
- **Custom Prompting**: Allow users to customize the AI system prompts

### Phase 4: Production Enhancements (Future)
- **Integration Testing**: Automated tests for AI responses and field creation
- **Version Control Integration**: Git-aware field generation
- **Move to Separate Repository**: Package for official distribution when ready
- **Advanced Validation**: Schema versioning and migration support

### Phase 5: Ecosystem Integration (Future)
- **Form Builder Integration**: Connect with Formie/other form plugins
- **Content Migration**: AI-assisted content structure migrations
- **Multi-site Support**: Cross-site field and structure management
- **Webhook Support**: Async LLM processing for complex operations

## Notes
- The system maintains compatibility with existing project configurations
- All generated structures use proper Craft UUID format
- Git is initialized in the main project directory
- Context-aware system works with both greenfield and existing projects

## Important Limitations

### ⚠️ Context-Aware System Capabilities
The current implementation excels at both **initial creation** and **intelligent modification**:

**What Works Exceptionally Well:**
- ✅ Creating new fields, entry types, and sections from scratch
- ✅ Adding fields to existing entry types intelligently
- ✅ Reusing existing fields across different entry types
- ✅ Modifying existing structures while preserving content
- ✅ Conflict detection and automatic resolution
- ✅ Complete site structure generation and enhancement

**Current Considerations:**
- Field creation timing: Newly created fields are immediately available in same operation
- Reserved word protection: System automatically uses safe alternatives
- Dependency management: Operations execute in proper order automatically
- Safety first: System prevents destructive changes without explicit confirmation

**Performance Notes:**
- Discovery analysis adds minimal overhead to operations
- Context awareness significantly improves success rates
- AI-powered planning reduces trial-and-error iterations
- Operation-based approach enables precise rollbacks

# important-instruction-reminders
Do what has been asked; nothing more, nothing less.
NEVER create files unless they're absolutely necessary for achieving your goal.
ALWAYS prefer editing an existing file to creating a new one.
NEVER proactively create documentation files (*.md) or README files. Only create documentation files if explicitly requested by the User.
