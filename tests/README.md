# Comprehensive Test Preset Suite

This directory contains a complete test suite for validating all functionality across the Field Agent plugin's operations system.

## Directory Structure

```
tests/
├── basic-operations/       # Core CRUD operations testing
├── advanced-operations/    # Complex features and matrix fields  
├── integration-tests/      # Complete workflows and scenarios
├── edge-cases/            # Error handling and conflict resolution
└── README.md              # This documentation
```

## Test Categories

### 📁 Basic Operations (`basic-operations/`)

Tests core functionality for fields, entry types, and sections.

- **`test-create-fields.json`** - All 21 field types with comprehensive settings
- **`test-modify-fields.json`** - Field updates, requirements, instructions, width
- **`test-entry-types.json`** - Entry type creation, modification, title field management  
- **`test-sections.json`** - Section creation and management

### 📁 Advanced Operations (`advanced-operations/`)

Tests complex features including matrix fields and nested structures.

- **`test-matrix-fields.json`** - Matrix field creation with inline and referenced block types
- **`test-matrix-modifications.json`** - Adding/removing/modifying matrix block types
- **`test-nested-structures.json`** - Complex nested matrix scenarios (4+ levels deep)
- **`test-field-reuse.json`** - Intelligent field reuse across entry types

### 📁 Integration Tests (`integration-tests/`)

Tests complete workflows and realistic usage scenarios.

- **`test-complete-site.json`** - Full site structure (blog, portfolio, pages)
- **`test-multi-site-scenario.json`** - Multi-site content structures with shared and unique fields
- **`test-page-builder.json`** - Advanced page builder with 12+ block types

### 📁 Edge Cases (`edge-cases/`)

Tests error handling, conflict resolution, and system boundaries.

- **`test-conflict-resolution.json`** - Handle conflicts, reserved words, alternatives
- **`test-rollback-scenarios.json`** - Complex operations designed for rollback testing
- **`test-error-conditions.json`** - Operations that should fail gracefully

## Execution Guide

### Prerequisites

Ensure the Field Agent plugin is installed and configured:

```bash
# Verify plugin is active
ddev craft plugin/list

# Test LLM connection (optional - tests can run without LLM)
ddev craft field-agent/generator/test-llm
```

### Running Individual Tests

Execute any test preset using the generator command:

```bash
# Basic operations
ddev craft field-agent/generator/generate test-create-fields
ddev craft field-agent/generator/generate test-modify-fields  
ddev craft field-agent/generator/generate test-entry-types
ddev craft field-agent/generator/generate test-sections

# Advanced operations  
ddev craft field-agent/generator/generate test-matrix-fields
ddev craft field-agent/generator/generate test-matrix-modifications
ddev craft field-agent/generator/generate test-nested-structures
ddev craft field-agent/generator/generate test-field-reuse

# Integration tests
ddev craft field-agent/generator/generate test-complete-site
ddev craft field-agent/generator/generate test-multi-site-scenario
ddev craft field-agent/generator/generate test-page-builder

# Edge cases
ddev craft field-agent/generator/generate test-conflict-resolution
ddev craft field-agent/generator/generate test-rollback-scenarios
ddev craft field-agent/generator/generate test-error-conditions
```

### Running Test Sequences

Some tests have dependencies and should be run in sequence:

#### Matrix Field Testing Sequence
```bash
# 1. Create base matrix fields
ddev craft field-agent/generator/generate test-matrix-fields

# 2. Test modifications to existing matrix fields  
ddev craft field-agent/generator/generate test-matrix-modifications

# 3. Clean up
ddev craft field-agent/generator/operations
ddev craft field-agent/generator/rollback <operation-id>
```

#### Field Modification Testing Sequence
```bash
# 1. Create base fields
ddev craft field-agent/generator/generate test-create-fields

# 2. Test field modifications
ddev craft field-agent/generator/generate test-modify-fields

# 3. Clean up
ddev craft field-agent/generator/rollback-all
```

### Post-Execution Steps

After running tests, apply Craft configuration changes:

```bash
# Apply any project config changes
ddev craft up

# Verify field creation in Craft admin
# Check Settings > Fields, Sections, Entry Types
```

## Test Coverage Matrix

### Field Types Coverage (21 types)

| Field Type | Create | Modify | Matrix Inline | Matrix Referenced | Reuse |
|------------|---------|---------|---------------|-------------------|-------|
| plain_text | ✅ | ✅ | ✅ | ✅ | ✅ |
| rich_text | ✅ | ✅ | ✅ | ✅ | ✅ |
| email | ✅ | ✅ | ❌ | ❌ | ✅ |
| image | ✅ | ✅ | ✅ | ✅ | ✅ |
| asset | ✅ | ✅ | ❌ | ✅ | ✅ |
| number | ✅ | ✅ | ✅ | ✅ | ✅ |
| money | ✅ | ✅ | ❌ | ✅ | ✅ |
| range | ✅ | ✅ | ✅ | ❌ | ✅ |
| link | ✅ | ✅ | ✅ | ✅ | ✅ |
| dropdown | ✅ | ✅ | ✅ | ❌ | ✅ |
| radio_buttons | ✅ | ✅ | ❌ | ✅ | ✅ |
| checkboxes | ✅ | ✅ | ❌ | ✅ | ✅ |
| multi_select | ✅ | ✅ | ❌ | ✅ | ✅ |
| country | ✅ | ❌ | ❌ | ✅ | ✅ |
| button_group | ✅ | ✅ | ✅ | ❌ | ✅ |
| date | ✅ | ✅ | ✅ | ✅ | ✅ |
| time | ✅ | ❌ | ❌ | ✅ | ✅ |
| color | ✅ | ❌ | ✅ | ❌ | ✅ |
| lightswitch | ✅ | ✅ | ✅ | ❌ | ✅ |
| icon | ✅ | ❌ | ✅ | ❌ | ✅ |
| matrix | ✅ | ✅ | ✅ | ✅ | ✅ |

### Operation Types Coverage

| Operation | Basic | Advanced | Integration | Edge Cases |
|-----------|-------|----------|-------------|------------|
| create (field) | ✅ | ✅ | ✅ | ✅ |
| create (entryType) | ✅ | ✅ | ✅ | ✅ |
| create (section) | ✅ | ✅ | ✅ | ✅ |
| modify (updateField) | ✅ | ✅ | ✅ | ✅ |
| modify (updateEntryType) | ✅ | ✅ | ✅ | ✅ |
| modify (addEntryType) | ✅ | ❌ | ✅ | ❌ |
| modify (addBlockType) | ❌ | ✅ | ✅ | ❌ |
| modify (updateBlockType) | ❌ | ✅ | ❌ | ❌ |
| modify (removeBlockType) | ❌ | ✅ | ❌ | ❌ |
| modify (updateSettings) | ✅ | ❌ | ❌ | ❌ |
| delete | ❌ | ❌ | ❌ | ✅ |

### Context-Aware Features Coverage

| Feature | Test Coverage | Files |
|---------|---------------|-------|
| Field Reuse | ✅ | test-field-reuse.json, test-multi-site-scenario.json |
| Conflict Resolution | ✅ | test-conflict-resolution.json |
| Reserved Word Handling | ✅ | test-conflict-resolution.json |
| Dependency Management | ✅ | All integration tests |
| Error Handling | ✅ | test-error-conditions.json |
| Rollback Capability | ✅ | test-rollback-scenarios.json |

## Cleanup and Rollback

### Individual Test Cleanup

Each test execution creates a tracked operation that can be rolled back:

```bash
# List all operations
ddev craft field-agent/generator/operations

# Rollback specific operation
ddev craft field-agent/generator/rollback <operation-id>
```

### Bulk Cleanup

Remove all test data at once:

```bash
# WARNING: This removes ALL field-agent operations
ddev craft field-agent/generator/rollback-all
```

### Manual Cleanup

If needed, remove test artifacts manually:

```bash
# Remove test configurations
ddev craft field-agent/generator/prune-all --confirm=1

# Apply project config changes
ddev craft up
```

## Expected Results

### Success Metrics

- **All basic operations complete successfully** 
- **Matrix fields with 4+ nesting levels function properly**
- **Field reuse works across different entry types and sections**
- **Reserved word conflicts resolve automatically**
- **Error conditions fail gracefully with descriptive messages**
- **Rollback operations work for all complexity levels**

### Performance Expectations

| Test Category | Expected Duration | Operations Count |
|---------------|-------------------|------------------|
| Basic Operations | 2-5 minutes | 50-100 operations |
| Advanced Operations | 5-10 minutes | 100-200 operations |
| Integration Tests | 10-15 minutes | 200-300 operations |
| Edge Cases | 3-7 minutes | 50-150 operations |

### Validation Checklist

After running the complete test suite:

- [ ] All 21 field types created successfully
- [ ] Matrix fields with nested structures function properly  
- [ ] Field reuse works across multiple entry types
- [ ] Reserved word conflicts resolve automatically
- [ ] Error conditions provide clear, actionable messages
- [ ] Rollback functionality works for all operation types
- [ ] No orphaned fields, entry types, or sections remain
- [ ] Craft admin interface shows all created structures properly
- [ ] No PHP errors or warnings in logs

## Troubleshooting

### Common Issues

**Test fails with "Field already exists" error:**
```bash
# Check for existing fields
ddev craft field-agent/generator/discovery/fields

# Clean up previous test runs
ddev craft field-agent/generator/rollback-all
```

**Matrix field creation fails:**
```bash
# Verify field dependencies exist first
# Run test-create-fields.json before matrix tests
```

**Section URL conflicts:**
```bash
# Check existing Craft routes
# Modify uriFormat in test presets if needed
```

**Permission errors:**
```bash
# Ensure proper Craft user permissions
# Check file system permissions for storage/field-agent/
```

### Debug Mode

Run tests with debug information:

```bash
# Enable debug output
ddev craft field-agent/generator/generate test-create-fields --debug
```

### Support

For issues with the test suite:

1. Check the Field Agent plugin logs
2. Verify Craft CMS configuration
3. Review operation history: `ddev craft field-agent/generator/operations`
4. Test LLM connectivity: `ddev craft field-agent/generator/test-llm`

## Test Development

### Adding New Tests

To create additional test presets:

1. Follow the existing JSON structure
2. Include comprehensive `expectedOutcome` documentation
3. Add rollback instructions
4. Update this README with new test information
5. Consider dependencies on existing tests

### Test Preset Structure

```json
{
  "description": "Clear description of test purpose",
  "prerequisite": "Any required setup (optional)",
  "operations": [
    // Array of operations to execute
  ],
  "expectedOutcome": {
    // Detailed expected results
  },
  "rollbackInstructions": "How to clean up",
  "executionCommand": "ddev craft command"
}
```

This comprehensive test suite validates the entire Field Agent plugin operations system and serves as both quality assurance and demonstration of capabilities.