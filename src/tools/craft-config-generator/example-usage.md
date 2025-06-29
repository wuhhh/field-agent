# Example: Using Natural Language to Generate Craft Config

## User Prompt
"Set up my project with basic fields including text, rich text, image, url and number"

## How Claude Would Process This

### Step 1: Parse the Requirements
From the prompt, I identify:
- Text field (plain text)
- Rich text field (WYSIWYG editor)
- Image field (asset field for images)
- URL field (for links)
- Number field (numeric input)

### Step 2: Generate JSON Config
```json
{
  "fields": [
    {
      "name": "Text",
      "handle": "text",
      "field_type": "plain_text",
      "instructions": "Single line text field",
      "required": false,
      "searchable": true
    },
    {
      "name": "Rich Text",
      "handle": "richText",
      "field_type": "rich_text",
      "instructions": "Rich text editor with formatting options",
      "required": false,
      "searchable": true
    },
    {
      "name": "Image",
      "handle": "image",
      "field_type": "image",
      "instructions": "Upload an image",
      "required": false,
      "searchable": false
    },
    {
      "name": "URL",
      "handle": "url",
      "field_type": "url",
      "instructions": "External link URL",
      "required": false,
      "searchable": false
    },
    {
      "name": "Number",
      "handle": "number",
      "field_type": "number",
      "instructions": "Numeric value",
      "required": false,
      "searchable": false
    }
  ],
  "entry_types": [
    {
      "name": "Basic Page",
      "handle": "basicPage",
      "fields": [
        {"handle": "text", "required": false},
        {"handle": "richText", "required": false},
        {"handle": "image", "required": false},
        {"handle": "url", "required": false},
        {"handle": "number", "required": false}
      ],
      "has_title_field": true
    }
  ]
}
```

### Step 3: Run the Tool
```bash
./target/release/craft-config-gen generate --config basic-fields.json --output-dir config/project
```

### Step 4: Generated Files
The tool creates:
- `config/project/fields/text--[uuid].yaml`
- `config/project/fields/richText--[uuid].yaml`
- `config/project/fields/image--[uuid].yaml`
- `config/project/fields/url--[uuid].yaml`
- `config/project/fields/number--[uuid].yaml`
- `config/project/entryTypes/basicPage--[uuid].yaml`
- `config/project/project.yaml`

## More Complex Example

### User Prompt
"Create a recipe website with ingredients list, cooking instructions, prep time, cook time, difficulty level, and multiple recipe photos"

### Generated Config
```json
{
  "fields": [
    {
      "name": "Ingredients",
      "handle": "ingredients",
      "field_type": "rich_text",
      "instructions": "List all ingredients with quantities",
      "required": true,
      "searchable": true
    },
    {
      "name": "Cooking Instructions",
      "handle": "cookingInstructions",
      "field_type": "rich_text",
      "instructions": "Step-by-step cooking instructions",
      "required": true,
      "searchable": true
    },
    {
      "name": "Prep Time",
      "handle": "prepTime",
      "field_type": "number",
      "instructions": "Preparation time in minutes",
      "required": true,
      "searchable": false
    },
    {
      "name": "Cook Time",
      "handle": "cookTime",
      "field_type": "number",
      "instructions": "Cooking time in minutes",
      "required": true,
      "searchable": false
    },
    {
      "name": "Difficulty Level",
      "handle": "difficultyLevel",
      "field_type": "plain_text",
      "instructions": "Easy, Medium, or Hard",
      "required": true,
      "searchable": true
    },
    {
      "name": "Recipe Photos",
      "handle": "recipePhotos",
      "field_type": "asset",
      "instructions": "Upload multiple photos of the recipe",
      "required": false,
      "searchable": false
    }
  ],
  "entry_types": [
    {
      "name": "Recipe",
      "handle": "recipe",
      "fields": [
        {"handle": "ingredients", "required": true},
        {"handle": "cookingInstructions", "required": true},
        {"handle": "prepTime", "required": true},
        {"handle": "cookTime", "required": true},
        {"handle": "difficultyLevel", "required": true},
        {"handle": "recipePhotos", "required": false}
      ],
      "has_title_field": true
    }
  ]
}
```

This demonstrates how natural language requirements are translated into structured Craft CMS configurations.

## Applying the Generated Configs

After generating the config files, copy them to your Craft project and apply:

```bash
# Copy files to your Craft project
cp -r config/project/* /path/to/your/craft/config/project/

# Apply changes using DDEV
cd /path/to/your/craft
ddev craft up
```

**Important:** Always use `ddev craft up` to apply project config changes, as it runs inside the DDEV container where your Craft installation lives.