#!/bin/bash

# Check if path argument is provided
if [ $# -eq 0 ]; then
    echo "Usage: $0 <wordpress_path>"
    echo "Example: $0 /path/to/wordpress"
    exit 1
fi

WORDPRESS_PATH="$1"

echo "Running migrations"
cd "$WORDPRESS_PATH"

# Check if migrations table exists
echo "Checking if migrations table exists"
migrations_table_exists=$(wp db query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'dfs_migrations'" --path="$WORDPRESS_PATH" --skip-column-names)

if [ "$migrations_table_exists" -eq "0" ]; then
    echo "Migrations table does not exist, creating it"
    wp db import migrations/seed.sql --path="$WORDPRESS_PATH"
    echo "Migrations table created"
else
    echo "Migrations table exists"
fi

# Function to check if migration has been applied
check_migration_applied() {
    local migration_name="$1"
    wp db query "SELECT COUNT(*) FROM dfs_migrations WHERE name='$migration_name'" --path="$WORDPRESS_PATH" --skip-column-names
}

# Function to mark migration as applied
mark_migration_applied() {
    local migration_name="$1"
    wp db query "INSERT INTO dfs_migrations (name) VALUES ('$migration_name')" --path="$WORDPRESS_PATH"
}

# Function to process plugin or theme activation
process_plugin_activation() {
    local migration_name="$1"
    local plugin_name="${migration_name#activate-}"

    # Check if it's a theme
    if wp theme is-installed "$plugin_name" --path="$WORDPRESS_PATH" --quiet; then
        echo "Activating theme: $plugin_name"
        wp theme activate "$plugin_name" --path="$WORDPRESS_PATH"
        return 0
    fi
    
    # Check if plugin is installed before activating
    if wp plugin is-installed "$plugin_name" --path="$WORDPRESS_PATH" --quiet; then
        echo "Activating plugin: $plugin_name"
        wp plugin activate "$plugin_name" --path="$WORDPRESS_PATH"
        return 0
    else
        echo "Warning: $plugin_name is not installed, skipping activation"
        return 1
    fi
    
    if [ $? -eq 0 ]; then
        echo "$plugin_name activated successfully"
        return 0
    else
        echo "Warning: Failed to activate $plugin_name"
        return 1
    fi
}

# Function to process SQL migration
process_sql_migration() {
    local migration="$1"
    local migration_name="$2"
    
    # Check if there's a matching PHP script to enhance the SQL
    local php_script="./migrations/${migration_name%.sql}.php"
    if [ -f "$php_script" ]; then
        echo "Found PHP enhancement script: ${migration_name%.sql}.php"
        
        # Create a temporary enhanced SQL file
        local temp_sql="/tmp/enhanced_${migration_name}"
        
        # Run the PHP script to generate enhanced SQL
        php "$php_script" "$WORDPRESS_PATH" > "$temp_sql"
        
        if [ $? -eq 0 ]; then
            echo "PHP script executed successfully, running enhanced SQL"
            # Run the enhanced SQL migration
            wp db query < "$temp_sql" --path="$WORDPRESS_PATH"
            # Clean up temp file
            rm "$temp_sql"
            return 0
        else
            echo "Warning: PHP script failed, falling back to original SQL"
            wp db query < "$migration" --path="$WORDPRESS_PATH"
            return $?
        fi
    else
        # Run as regular SQL migration
        wp db query < "$migration" --path="$WORDPRESS_PATH"
        return $?
    fi
}

# Step 1: Process plugin activation migrations first
echo ""
echo "=== Step 1: Processing Plugin Activations ==="
shopt -s nullglob
plugin_migrations=(./migrations/activate-*)

if [ ${#plugin_migrations[@]} -gt 0 ]; then
    for migration in "${plugin_migrations[@]}"; do
        migration_name="${migration##*/}"
        
        # Check if migration has been applied
        applied=$(check_migration_applied "$migration_name")
        
        if [ "$applied" -eq "0" ]; then
            echo "Running plugin activation: $migration_name"
            
            if process_plugin_activation "$migration_name"; then
                mark_migration_applied "$migration_name"
                echo "Plugin activation completed: $migration_name"
            else
                echo "Plugin activation failed: $migration_name"
            fi
        else
            echo "Skipping already applied plugin activation: $migration_name"
        fi
    done
else
    echo "No plugin activation migrations found"
fi

# Step 2: Process SQL data migrations
echo ""
echo "=== Step 2: Processing Data Migrations ==="
sql_migrations=(./migrations/*.sql)

if [ ${#sql_migrations[@]} -gt 0 ]; then
    for migration in "${sql_migrations[@]}"; do
        migration_name="${migration##*/}"
        
        # Skip seed.sql as it's handled separately
        if [[ "$migration_name" == "seed.sql" ]]; then
            continue
        fi
        
        # Check if migration has been applied
        applied=$(check_migration_applied "$migration_name")
        
        if [ "$applied" -eq "0" ]; then
            echo "Running data migration: $migration_name"
            
            if process_sql_migration "$migration" "$migration_name"; then
                mark_migration_applied "$migration_name"
                echo "Data migration completed: $migration_name"
            else
                echo "Data migration failed: $migration_name"
            fi
        else
            echo "Skipping already applied data migration: $migration_name"
        fi
    done
else
    echo "No SQL data migrations found"
fi

echo ""
echo "=== Migration process completed ==="
