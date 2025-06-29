#!/bin/bash

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
TOOL="$SCRIPT_DIR/target/release/craft-config-gen"

function print_usage() {
    echo "Craft CMS Config Generator Helper"
    echo ""
    echo "Usage: $0 <command> [options]"
    echo ""
    echo "Commands:"
    echo "  basic-fields         Generate basic field set (text, rich text, image, url, number)"
    echo "  blog                 Generate blog structure with article entry type"
    echo "  portfolio            Generate portfolio structure with project entry type"
    echo "  page-builder         Generate page builder with multiple field types"
    echo "  custom <json-file>   Generate from custom JSON config"
    echo ""
}

function generate_basic_fields() {
    cat > /tmp/craft-basic-fields.json << 'EOF'
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
EOF
    
    echo "Generating basic field configuration..."
    $TOOL generate --config /tmp/craft-basic-fields.json --output-dir "${1:-config/project}"
    rm /tmp/craft-basic-fields.json
}

function generate_blog() {
    cat > /tmp/craft-blog.json << 'EOF'
{
  "fields": [
    {
      "name": "Article Body",
      "handle": "articleBody",
      "field_type": "rich_text",
      "instructions": "Main content of the article",
      "required": true,
      "searchable": true
    },
    {
      "name": "Featured Image",
      "handle": "featuredImage",
      "field_type": "image",
      "instructions": "Hero image for the article",
      "required": false,
      "searchable": false
    },
    {
      "name": "Summary",
      "handle": "summary",
      "field_type": "plain_text",
      "instructions": "Brief summary for listings",
      "required": false,
      "searchable": true
    },
    {
      "name": "Author",
      "handle": "author",
      "field_type": "plain_text",
      "instructions": "Article author name",
      "required": false,
      "searchable": true
    },
    {
      "name": "Read Time",
      "handle": "readTime",
      "field_type": "number",
      "instructions": "Estimated reading time in minutes",
      "required": false,
      "searchable": false
    }
  ],
  "entry_types": [
    {
      "name": "Blog Post",
      "handle": "blogPost",
      "fields": [
        {"handle": "summary", "required": true},
        {"handle": "articleBody", "required": true},
        {"handle": "featuredImage", "required": false},
        {"handle": "author", "required": false},
        {"handle": "readTime", "required": false}
      ],
      "has_title_field": true
    }
  ]
}
EOF
    
    echo "Generating blog configuration..."
    $TOOL generate --config /tmp/craft-blog.json --output-dir "${1:-config/project}"
    rm /tmp/craft-blog.json
}

function generate_portfolio() {
    cat > /tmp/craft-portfolio.json << 'EOF'
{
  "fields": [
    {
      "name": "Project Description",
      "handle": "projectDescription",
      "field_type": "rich_text",
      "instructions": "Detailed project description",
      "required": true,
      "searchable": true
    },
    {
      "name": "Project Images",
      "handle": "projectImages",
      "field_type": "asset",
      "instructions": "Gallery of project images",
      "required": false,
      "searchable": false
    },
    {
      "name": "Client Name",
      "handle": "clientName",
      "field_type": "plain_text",
      "instructions": "Name of the client",
      "required": false,
      "searchable": true
    },
    {
      "name": "Project URL",
      "handle": "projectUrl",
      "field_type": "url",
      "instructions": "Live project URL",
      "required": false,
      "searchable": false
    },
    {
      "name": "Technologies",
      "handle": "technologies",
      "field_type": "plain_text",
      "instructions": "Technologies used (comma-separated)",
      "required": false,
      "searchable": true
    },
    {
      "name": "Year",
      "handle": "year",
      "field_type": "number",
      "instructions": "Year of completion",
      "required": false,
      "searchable": false
    }
  ],
  "entry_types": [
    {
      "name": "Portfolio Project",
      "handle": "portfolioProject",
      "fields": [
        {"handle": "projectDescription", "required": true},
        {"handle": "projectImages", "required": false},
        {"handle": "clientName", "required": false},
        {"handle": "projectUrl", "required": false},
        {"handle": "technologies", "required": false},
        {"handle": "year", "required": false}
      ],
      "has_title_field": true
    }
  ]
}
EOF
    
    echo "Generating portfolio configuration..."
    $TOOL generate --config /tmp/craft-portfolio.json --output-dir "${1:-config/project}"
    rm /tmp/craft-portfolio.json
}

function generate_page_builder() {
    cat > /tmp/craft-page-builder.json << 'EOF'
{
  "fields": [
    {
      "name": "Hero Title",
      "handle": "heroTitle",
      "field_type": "plain_text",
      "instructions": "Main hero section title",
      "required": false,
      "searchable": true
    },
    {
      "name": "Hero Subtitle",
      "handle": "heroSubtitle",
      "field_type": "plain_text",
      "instructions": "Hero section subtitle",
      "required": false,
      "searchable": true
    },
    {
      "name": "Hero Image",
      "handle": "heroImage",
      "field_type": "image",
      "instructions": "Background image for hero section",
      "required": false,
      "searchable": false
    },
    {
      "name": "CTA Button Text",
      "handle": "ctaButtonText",
      "field_type": "plain_text",
      "instructions": "Call-to-action button label",
      "required": false,
      "searchable": false
    },
    {
      "name": "CTA Button URL",
      "handle": "ctaButtonUrl",
      "field_type": "url",
      "instructions": "Call-to-action button link",
      "required": false,
      "searchable": false
    },
    {
      "name": "Content Blocks",
      "handle": "contentBlocks",
      "field_type": "rich_text",
      "instructions": "Main content area",
      "required": false,
      "searchable": true
    },
    {
      "name": "Gallery Images",
      "handle": "galleryImages",
      "field_type": "asset",
      "instructions": "Image gallery",
      "required": false,
      "searchable": false
    },
    {
      "name": "Video URL",
      "handle": "videoUrl",
      "field_type": "url",
      "instructions": "Embedded video URL (YouTube/Vimeo)",
      "required": false,
      "searchable": false
    }
  ],
  "entry_types": [
    {
      "name": "Landing Page",
      "handle": "landingPage",
      "fields": [
        {"handle": "heroTitle", "required": false},
        {"handle": "heroSubtitle", "required": false},
        {"handle": "heroImage", "required": false},
        {"handle": "ctaButtonText", "required": false},
        {"handle": "ctaButtonUrl", "required": false},
        {"handle": "contentBlocks", "required": false},
        {"handle": "galleryImages", "required": false},
        {"handle": "videoUrl", "required": false}
      ],
      "has_title_field": true
    }
  ]
}
EOF
    
    echo "Generating page builder configuration..."
    $TOOL generate --config /tmp/craft-page-builder.json --output-dir "${1:-config/project}"
    rm /tmp/craft-page-builder.json
}

case "$1" in
    basic-fields)
        generate_basic_fields "$2"
        ;;
    blog)
        generate_blog "$2"
        ;;
    portfolio)
        generate_portfolio "$2"
        ;;
    page-builder)
        generate_page_builder "$2"
        ;;
    custom)
        if [ -z "$2" ]; then
            echo "Error: Please provide a JSON config file"
            exit 1
        fi
        echo "Generating from custom configuration..."
        $TOOL generate --config "$2" --output-dir "${3:-config/project}"
        ;;
    *)
        print_usage
        exit 1
        ;;
esac