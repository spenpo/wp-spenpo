#!/bin/bash

# WordPress Build Script
# This script rebuilds the entire WordPress installation from Composer

echo "🚀 Starting WordPress build..."

# Run the PHP build script
php build.php

if [ $? -eq 0 ]; then
    echo "📁 Copying migrations to WordPress build..."
    
    # Create migrations directory in WordPress if it doesn't exist
    mkdir -p wordpress/migrations
    
    # Copy migrations directory contents
    if [ -d "migrations" ]; then
        cp -r migrations/* wordpress/migrations/
        echo "✅ Migrations copied successfully"
    else
        echo "⚠️  Migrations directory not found"
    fi
    
    # Copy migrations script
    if [ -f "migrations.sh" ]; then
        cp migrations.sh wordpress/migrations.sh
        chmod +x wordpress/migrations.sh
        echo "✅ Migrations script copied successfully"
    else
        echo "⚠️  Migrations script not found"
    fi

    # Image integration
    echo "🖼️  Fetching images from R2..."
    if [ -f "r2-sync.php" ]; then
        php r2-sync.php download
        cp wordpress-image-sync.php wordpress/wordpress-image-sync.php
        if [ $? -eq 0 ]; then
            echo "✅ Images fetched successfully from R2"
            
            # Note about WordPress integration
            echo "📝 Note: Images are now in wordpress/wp-content/uploads/"
            echo "   To register them in WordPress database, run:"
            echo "   wp eval-file wordpress-image-sync.php"
        else
            echo "⚠️  Image fetch failed, continuing with build..."
        fi
    else
        echo "⚠️  R2 sync script not found, skipping image fetch..."
    fi

    echo "✅ Build completed successfully!"
    echo ""
    echo "Next steps:"
    echo "1. Set up your database (MySQL/MariaDB)"
    echo "2. Create wordpress/wp-config.php from wordpress/wp-config-sample.php"
    echo "3. Update wordpress/wp-config.php with your database credentials"
    echo "4. Start the development server: php -S localhost:8000 -t wordpress/"
    echo "5. Visit http://localhost:8000 to complete WordPress installation"
    echo ""
    echo "Note: For deployment, your environment should handle wp-config.php"
else
    echo "❌ Build failed! Please check the errors above."
    exit 1
fi 