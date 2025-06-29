# Craft CMS Config Generator

A deterministic tool for generating Craft CMS project configuration files from structured JSON input.

## Installation

```bash
cargo build --release
```

## Usage

### Direct CLI Usage

Generate individual fields:
```bash
./target/release/craft-config-gen field \
  --name "Article Body" \
  --handle "articleBody" \
  --field-type "rich_text" \
  --instructions "Main content of the article" \
  --output "config/project/fields/articleBody.yaml"
```

Generate entry types:
```bash
./target/release/craft-config-gen entry-type \
  --name "Blog Post" \
  --handle "blogPost" \
  --fields articleBody featuredImage summary \
  --output "config/project/entryTypes/blogPost.yaml"
```

Generate from JSON config:
```bash
./target/release/craft-config-gen generate \
  --config project-config.json \
  --output-dir config/project
```

Generate example config:
```bash
./target/release/craft-config-gen example --output example-config.json
```

### Using the Helper Script

The helper script provides common configurations:

```bash
# Generate basic field set
./craft-config-helper.sh basic-fields

# Generate blog structure
./craft-config-helper.sh blog

# Generate portfolio structure  
./craft-config-helper.sh portfolio

# Generate page builder fields
./craft-config-helper.sh page-builder

# Generate from custom JSON
./craft-config-helper.sh custom my-config.json
```

## JSON Config Format

```json
{
  "fields": [
    {
      "name": "Field Name",
      "handle": "fieldHandle",
      "field_type": "plain_text|rich_text|image|number|url",
      "instructions": "Optional instructions",
      "required": false,
      "searchable": true
    }
  ],
  "entry_types": [
    {
      "name": "Entry Type Name",
      "handle": "entryTypeHandle",
      "fields": [
        {
          "handle": "fieldHandle",
          "required": false
        }
      ],
      "has_title_field": true,
      "title_format": null
    }
  ]
}
```

## Supported Field Types

- `plain_text` - Single or multi-line text fields
- `rich_text` - CKEditor rich text fields
- `image` - Asset fields restricted to images
- `asset` - General asset fields
- `number` - Numeric fields
- `url` - URL fields

## How Claude Can Use This Tool

When you ask me to create Craft CMS configurations using natural language, I can:

1. Parse your requirements
2. Generate a JSON configuration matching your needs
3. Run the tool to create the actual Craft config files

### Example Prompts

- "Set up my project with basic fields including text, rich text, image, url and number"
- "Create a blog structure with fields for article body, featured image, summary, and author"
- "Build a portfolio section with project description, images gallery, client name, and technologies used"
- "Create a landing page builder with hero section, content blocks, and CTA buttons"

### Integration Process

1. I analyze your natural language prompt
2. I create a JSON config that maps your requirements to Craft field types
3. I save the JSON to a temporary file
4. I run: `craft-config-gen generate --config temp.json --output-dir config/project`
5. The tool generates all necessary YAML files with proper UUIDs and structure

### Advanced Usage

For complex requirements, I can:
- Create multiple entry types with shared fields
- Set up proper field relationships
- Configure field validation and settings
- Generate complete content architectures

The tool ensures all generated configs are valid and ready for Craft CMS to process.

## Applying Changes to Your Craft Project

After generating config files and copying them to your Craft project:

```bash
# IMPORTANT: Apply changes inside the DDEV container
ddev craft up
```

This runs `craft project-config/apply` inside the DDEV container, which is required for the changes to take effect properly.