#!/bin/bash
# Zero-leak environment provisioning
# Scans for .env.example files and generates secrets automatically
# Run via: pnpm install (postinstall hook)

set -e

echo "🔐 Bootstrap Environment - Generating secrets..."

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Find all .env.example files in the monorepo
find_env_examples() {
    find . -name ".env.example" -not -path "*/node_modules/*" -not -path "*/.git/*"
}

# Generate a secure random value
generate_secret() {
    openssl rand -hex 32
}

# Generate a UUID
generate_uuid() {
    if command -v uuidgen &> /dev/null; then
        uuidgen | tr '[:upper:]' '[:lower:]'
    else
        # Fallback: generate UUID v4 using openssl
        printf '%08x-%04x-%04x-%04x-%012x\n' \
            $((RANDOM * RANDOM)) \
            $((RANDOM % 65536)) \
            $(((RANDOM % 4096) + 16384)) \
            $(((RANDOM % 16384) + 32768)) \
            $((RANDOM * RANDOM * RANDOM))
    fi
}

# Process a single .env.example file
process_env_file() {
    local example_file="$1"
    local dir=$(dirname "$example_file")
    local env_local="$dir/.env.local"

    # Skip if .env.local already exists
    if [ -f "$env_local" ]; then
        echo -e "${YELLOW}⏭️  Skipping $env_local (already exists)${NC}"
        return
    fi

    echo -e "${BLUE}📝 Processing $example_file${NC}"

    # Create .env.local from .env.example
    cp "$example_file" "$env_local"

    # Read each line and generate secrets for empty variables
    while IFS= read -r line; do
        # Skip comments and empty lines
        if [[ "$line" =~ ^#.*$ ]] || [[ -z "$line" ]]; then
            continue
        fi

        # Extract variable name and value
        if [[ "$line" =~ ^([A-Z_]+)=(.*)$ ]]; then
            var_name="${BASH_REMATCH[1]}"
            var_value="${BASH_REMATCH[2]}"

            # Only generate if value is empty
            if [[ -z "$var_value" ]]; then
                # Determine what type of secret to generate
                if [[ "$var_name" =~ (SECRET|KEY|TOKEN)$ ]]; then
                    new_value=$(generate_secret)
                    sed -i "s|^${var_name}=.*$|${var_name}=${new_value}|" "$env_local"
                    echo -e "${GREEN}  ✓ Generated ${var_name}${NC}"
                elif [[ "$var_name" =~ UUID$ ]]; then
                    new_value=$(generate_uuid)
                    sed -i "s|^${var_name}=.*$|${var_name}=${new_value}|" "$env_local"
                    echo -e "${GREEN}  ✓ Generated ${var_name}${NC}"
                fi
            fi
        fi
    done < "$example_file"

    echo -e "${GREEN}✅ Created $env_local${NC}"
}

# Ensure .gitignore includes .env.local
ensure_gitignore() {
    local gitignore=".gitignore"

    if [ ! -f "$gitignore" ]; then
        echo ".env.local" > "$gitignore"
        echo -e "${GREEN}✅ Created .gitignore with .env.local${NC}"
        return
    fi

    if ! grep -q "^\.env\.local$" "$gitignore"; then
        echo ".env.local" >> "$gitignore"
        echo -e "${GREEN}✅ Added .env.local to .gitignore${NC}"
    fi
}

# Main execution
main() {
    # Ensure we're in the project root
    if [ ! -f "package.json" ]; then
        echo "❌ Error: Must run from project root (package.json not found)"
        exit 1
    fi

    # Ensure .gitignore is configured
    ensure_gitignore

    # Find and process all .env.example files
    local env_files=$(find_env_examples)

    if [ -z "$env_files" ]; then
        echo "ℹ️  No .env.example files found"
        exit 0
    fi

    while IFS= read -r file; do
        process_env_file "$file"
    done <<< "$env_files"

    echo ""
    echo -e "${GREEN}🎉 Environment bootstrap complete!${NC}"
    echo -e "${YELLOW}⚠️  Remember: .env.local files contain secrets and are git-ignored${NC}"
}

main "$@"
