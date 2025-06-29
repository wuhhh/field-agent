# Quick Start Guide

## Complete Example: Real Estate Website

### 1. Tell Claude what you need:
"I need a real estate website with property listings including price, square footage, bedrooms, bathrooms, and photos"

### 2. Claude generates the config files:
```bash
./craft-config-helper.sh custom real-estate-config.json
```

### 3. Copy to your Craft project:
```bash
# Assuming you're in the tools/craft-config-generator directory
cp -r config/project/* ../../config/project/
```

### 4. Apply changes in DDEV:
```bash
cd ../..  # Back to craft root
ddev craft up
```

### 5. Your fields are now available in Craft CMS!

## Pre-built Templates

### Blog
```bash
./craft-config-helper.sh blog
cp -r config/project/* ../../config/project/
cd ../.. && ddev craft up
```

### Portfolio
```bash
./craft-config-helper.sh portfolio
cp -r config/project/* ../../config/project/
cd ../.. && ddev craft up
```

### Basic Fields
```bash
./craft-config-helper.sh basic-fields
cp -r config/project/* ../../config/project/
cd ../.. && ddev craft up
```

## Custom Configurations

Just describe what you need and Claude will:
1. Create a JSON config file
2. Run the generator
3. Tell you exactly what commands to run

Remember: **Always use `ddev craft up`** to apply config changes!