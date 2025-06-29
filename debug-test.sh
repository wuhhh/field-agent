#!/bin/bash

# Field Generator Debug Mode Test Script

echo "🧪 Field Generator Debug Mode Test"
echo "=================================="
echo ""
echo "This script demonstrates the debug mode functionality."
echo ""

# Test commands
echo "1. Testing LLM connection with debug mode:"
echo "   ddev craft field-generator/generator/test-llm anthropic --debug"
echo ""
echo "   Or use the short alias:"
echo "   ddev craft field-generator/generator/test-llm anthropic -d"
echo ""

echo "2. Generate fields with debug mode:"
echo "   ddev craft field-generator/generator/prompt \"Create a portfolio with project showcases\" anthropic --debug"
echo ""

echo "3. Export prompts for manual testing:"
echo "   ddev craft field-generator/generator/export-prompt"
echo ""

echo "Debug logs will appear in:"
echo "- Console output (real-time)"
echo "- storage/logs/web.log (tagged as 'field-generator-llm')"
echo ""

echo "📝 Note: Ensure you have set your API keys:"
echo "   export ANTHROPIC_API_KEY=\"sk-ant-...\""
echo "   export OPENAI_API_KEY=\"sk-...\""
echo ""

# Run a test if requested
if [ "$1" == "run" ]; then
    echo "Running test with debug mode..."
    ddev craft field-generator/generator/test-llm anthropic --debug
fi