#!/bin/bash

# Install git hooks from the hooks/ directory to .git/hooks/
# This ensures all developers have the same hooks when they clone the repo

HOOKS_DIR="hooks"
GIT_HOOKS_DIR=".git/hooks"

# Check if we're in a git repository
if [ ! -d "$GIT_HOOKS_DIR" ]; then
    echo "❌ Error: Not in a git repository. Please run this from the repository root." >&2
    exit 1
fi

# Check if hooks directory exists
if [ ! -d "$HOOKS_DIR" ]; then
    echo "❌ Error: hooks/ directory not found." >&2
    exit 1
fi

echo "🔗 Installing git hooks..."

# Install each hook from the hooks/ directory
for hook in "$HOOKS_DIR"/*; do
    if [ -f "$hook" ]; then
        hook_name=$(basename "$hook")
        target="$GIT_HOOKS_DIR/$hook_name"
        
        # Copy the hook
        cp "$hook" "$target"
        
        # Make it executable
        chmod +x "$target"
        
        echo "   ✅ Installed: $hook_name"
    fi
done

echo "✅ Git hooks installed successfully!"
echo ""
echo "Installed hooks:"
ls -1 "$GIT_HOOKS_DIR"/* 2>/dev/null | xargs -n1 basename | sed 's/^/   - /'
