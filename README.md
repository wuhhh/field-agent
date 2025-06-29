# Field Agent Plugin for Craft CMS

**AI-Powered Context-Aware Field Generation**  
Generate and intelligently modify Craft CMS fields, entry types, and sections from natural language prompts using advanced AI integration.

## 🌟 Key Features

### 🎯 Context-Aware Intelligence
- **Project Analysis**: Understands existing field layouts and structures
- **Smart Modification**: Add fields to existing entry types without breaking current content
- **Intelligent Field Reuse**: Automatically reuses appropriate existing fields across entry types
- **Conflict Prevention**: Avoids handle conflicts and reserved word usage automatically

### 🤖 Advanced AI Integration
- **Natural Language Processing**: Understands complex modification and creation requests
- **Multi-Provider Support**: Anthropic Claude & OpenAI GPT-4 integration
- **Structured Output**: JSON schema validation ensures reliable, consistent results
- **Debug Mode**: Complete request/response logging and operation tracking

### 📊 Operation Management System
- **Complete Audit Trail**: Track all field generation activities with timestamps
- **Granular Rollback**: Undo individual or bulk operations safely
- **Safety Checks**: Prevent deletion of fields/types with existing content
- **Operation History**: Full history with source prompts and success/failure tracking

### 🔧 Comprehensive Field Support
- **21 Field Types**: Complete support for all major Craft field types
- **Reserved Word Protection**: Automatic alternatives for Craft reserved handles
- **Dependency Management**: Ensures proper creation order (fields → entry types → sections)
- **Matrix Fields**: AI-generated complex content block structures

## 🚀 Quick Start

### 1. Installation & Setup

The plugin is already installed as a local Composer package. Set up AI integration:

**Add API keys to `.ddev/config.yaml`** (Recommended):
```yaml
webimage_extra_environment:
  - ANTHROPIC_API_KEY=sk-ant-your-key-here
  - OPENAI_API_KEY=sk-your-key-here
```
Then run: `ddev restart`

**Verify setup:**
```bash
ddev craft field-agent/generator/test-llm
```

### 2. Context-Aware Field Generation

The system automatically determines whether to create new structures or modify existing ones:

```bash
# Create new structures from scratch
ddev craft field-agent/generator/prompt "Create a portfolio section with project fields"

# Intelligently modify existing structures
ddev craft field-agent/generator/prompt "Add author and featured image to blog posts"

# Smart field reuse across sections
ddev craft field-agent/generator/prompt "Create news section with title, content, and featured image"

# Apply changes to Craft
ddev craft up
```

## 📋 Core Commands

### 🎯 Context-Aware Generation (Primary Interface)

```bash
# One intelligent command handles everything
ddev craft field-agent/generator/prompt "<description>" [provider] [--debug]

# Examples:
ddev craft field-agent/generator/prompt "Add testimonials to the landing page"
ddev craft field-agent/generator/prompt "Create a team section with member profiles"
ddev craft field-agent/generator/prompt "Modify blog posts to include tags and categories"
```

**The system automatically:**
- Analyzes existing project structure
- Determines appropriate operations (create/modify)
- Reuses existing fields when beneficial
- Prevents handle conflicts
- Executes operations in proper dependency order

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

### 📊 Operation Management

```bash
# List all operations with detailed history
ddev craft field-agent/generator/operations

# Rollback specific operation
ddev craft field-agent/generator/rollback <operation-id>

# Rollback ALL operations (with confirmation)
ddev craft field-agent/generator/rollback-all

# Clean up old operations and configs
ddev craft field-agent/generator/prune-all --confirm=1
```

### 🤖 AI Provider Options

```bash
# Use Anthropic Claude (default)
ddev craft field-agent/generator/prompt "Create blog fields" anthropic

# Use OpenAI GPT-4
ddev craft field-agent/generator/prompt "Create blog fields" openai

# Test API connections
ddev craft field-agent/generator/test-llm anthropic --debug
ddev craft field-agent/generator/test-llm openai --debug
```

### 🔧 Debug & Development

```bash
# Enable debug mode for full operation visibility
ddev craft field-agent/generator/prompt "Create portfolio" --debug

# Export API templates for manual testing
ddev craft field-agent/generator/export-prompt
```

## 🎨 Example Workflows

### ✨ Intelligent Modification
Starting with an existing blog section:
```bash
ddev craft field-agent/generator/prompt "Add author and featured image to blog posts"
```
**System intelligently:**
1. Creates `author` and `featuredImage` fields
2. Adds them to existing `blogPost` entry type
3. Preserves all existing fields and settings

### 🎯 Smart Field Reuse
With existing `featuredImage` field:
```bash
ddev craft field-agent/generator/prompt "Create news section with title, content, and featured image"
```
**System intelligently:**
1. Creates `newsTitle` and `newsContent` fields (avoiding reserved names)
2. Reuses existing `featuredImage` field
3. Creates news section and entry type with proper relationships

### 🛡️ Automatic Conflict Prevention
```bash
ddev craft field-agent/generator/prompt "Create fields for title, content, and author"
```
**System automatically uses safe alternatives:**
- `title` → `pageTitle`, `blogTitle`, `articleTitle`
- `content` → `bodyContent`, `mainContent`, `description`
- `author` → `writer`, `creator`, `byline`

## 🏗️ Supported Field Types (21 Types)

### Text & Content (3 types)
- `plain_text` - Single/multi-line text with character limits
- `rich_text` - CKEditor WYSIWYG content
- `email` - Email validation

### Assets & Media (2 types)
- `image` - Image uploads with relation limits
- `asset` - General file uploads

### Numbers & Measurements (3 types)
- `number` - Numeric fields with decimals, min/max
- `money` - Currency fields with currency settings
- `range` - Slider/range inputs

### Links (1 type)
- `link` - Link fields supporting URLs and entry links

### Selection & Choice (5 types)
- `dropdown` - Single selection with options
- `radio_buttons` - Radio button groups
- `checkboxes` - Multiple selection checkboxes
- `multi_select` - Multiple selection dropdown
- `country` - Country selection
- `button_group` - Button group interface

### Date & Time (2 types)
- `date` - Date picker with time options
- `time` - Time picker

### User Interface (3 types)
- `color` - Color picker
- `lightswitch` - Boolean toggle
- `icon` - Icon picker

### Complex Structure (1 type)
- `matrix` - Flexible content blocks with AI-generated block types

### Reserved Field Protection
Automatically prevents use of Craft CMS reserved handles and provides intelligent alternatives.

## 🔍 Debug Features

### Context-Aware Debug Mode
```bash
ddev craft field-agent/generator/prompt "Create portfolio" --debug
```
Shows complete operation flow:
- Project context analysis and structure detection
- AI prompt generation with schema validation
- Operation planning and dependency resolution
- Field creation and database persistence verification
- Entry type modification with field assignment tracking

### Discovery Service Analysis
Real-time project state analysis:
- Field enumeration with type and setting details
- Section and entry type relationship mapping
- Handle availability checking
- Conflict detection and resolution suggestions

## 🏛️ Architecture Overview

### Context-Aware Operations System
- **Discovery Service**: Analyzes existing project structures
- **Operations Generator**: AI-powered operation planning from natural language
- **Operations Executor**: Dependency-aware execution of operation sequences
- **Rollback System**: Complete audit trail with granular undo capabilities

### Hybrid AI Architecture
- **Discovery Layer**: Real-time project analysis for contextual decisions
- **AI Planning**: LLM generates appropriate operations based on existing structures
- **Execution Layer**: Reliable field creation using Craft's native APIs
- **Validation**: Structured JSON schemas ensure consistent, reliable output

## 🚨 Important Notes

### Apply Changes
Always run after field generation:
```bash
ddev craft up
```

### System Capabilities
**Exceptional Performance:**
- ✅ Creating new fields, entry types, and sections from scratch
- ✅ Adding fields to existing entry types intelligently
- ✅ Reusing existing fields across different entry types
- ✅ Modifying existing structures while preserving content
- ✅ Conflict detection and automatic resolution
- ✅ Complete site structure generation and enhancement

**Performance Notes:**
- Discovery analysis adds minimal overhead
- Context awareness significantly improves success rates
- AI-powered planning reduces trial-and-error iterations
- Operation-based approach enables precise rollbacks

## 🔧 Legacy Commands (Still Available)

```bash
# Generate from JSON configuration files
ddev craft field-agent/generator/generate <config.json|stored-name>

# Generate basic field presets
ddev craft field-agent/generator/basic-fields

# List stored configurations
ddev craft field-agent/generator/list
```

## 🚨 Troubleshooting

### API Key Issues
```bash
# Verify configuration
ddev craft field-agent/generator/test-llm [provider] --debug

# Check environment setup
ddev exec env | grep API_KEY
```

### Common Solutions
1. **401 Authentication**: Verify API key format and validity
2. **Operation Failures**: Check debug output for detailed error information
3. **Handle Conflicts**: System automatically resolves, check operation history
4. **Field Not Found**: Use discovery tools to verify current project state

### Debug Logs
- Console output during operations (real-time)
- Craft logs: `storage/logs/web.log` (tagged as `field-agent`)
- Operation history: `field-agent/generator/operations`

## 📊 Schema & Validation

All AI responses are validated against structured JSON schemas ensuring:
- Required operation types and targets
- Proper field handle formatting (camelCase)
- Supported field types and settings
- Dependency order compliance
- Conflict prevention rules

## 🏆 Advanced Features

### Matrix Field Generation
AI can generate complex matrix field structures with multiple block types:
```bash
ddev craft field-agent/generator/prompt "Create a page builder with hero, content, and testimonial blocks"
```

### Batch Operations
Single prompts can generate multiple related structures:
```bash
ddev craft field-agent/generator/prompt "Create complete e-commerce product catalog with categories, variants, and reviews"
```

### Safety Systems
- Rollback protection for content-bearing fields/sections
- Automatic backup of operation sequences
- Conflict detection before execution
- Validation at every step

## 📄 License

Craft License - See LICENSE.txt file for details.

---

**This plugin represents the future of AI-assisted CMS development, providing intelligent, context-aware field management that understands and enhances your existing Craft CMS structures.**