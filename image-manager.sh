#!/bin/bash

# WordPress Image Manager
# This script helps manage images between local development and R2 storage

echo "🖼️  WordPress Image Manager"
echo "=========================="

case "$1" in
    "upload")
        echo "📤 Uploading local images to R2..."
        php r2-sync.php upload
        ;;
    "download")
        echo "📥 Downloading images from R2 to local..."
        php r2-sync.php download
        ;;
    "sync")
        echo "🔄 Syncing local changes to R2..."
        php r2-sync.php sync
        ;;
    "status")
        echo "📊 Checking image status..."
        echo "Local uploads directory: src/uploads/"
        
        # Try to get bucket name from environment or .env file
        if [ -n "$R2_BUCKET_NAME" ]; then
            echo "R2 bucket: $R2_BUCKET_NAME"
        elif [ -f ".env" ]; then
            bucket=$(grep R2_BUCKET_NAME .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")
            echo "R2 bucket: $bucket"
        else
            echo "R2 bucket: Not configured"
        fi
        
        echo ""
        if [ -d "src/uploads" ]; then
            local_count=$(find src/uploads -type f -name "*.jpg" -o -name "*.jpeg" -o -name "*.png" -o -name "*.gif" -o -name "*.webp" -o -name "*.pdf" | wc -l)
            echo "Local images: $local_count"
        else
            echo "Local uploads directory not found"
        fi
        
        # Check environment variables
        echo ""
        echo "Environment check:"
        if [ -n "$R2_ACCESS_KEY_ID" ]; then
            echo "  ✅ R2_ACCESS_KEY_ID: Set"
        else
            echo "  ❌ R2_ACCESS_KEY_ID: Not set"
        fi
        
        if [ -n "$R2_SECRET_ACCESS_KEY" ]; then
            echo "  ✅ R2_SECRET_ACCESS_KEY: Set"
        else
            echo "  ❌ R2_SECRET_ACCESS_KEY: Not set"
        fi
        
        if [ -n "$R2_ENDPOINT" ]; then
            echo "  ✅ R2_ENDPOINT: Set"
        else
            echo "  ❌ R2_ENDPOINT: Not set"
        fi
        ;;
    "clean")
        echo "🧹 Cleaning local uploads directory..."
        if [ -d "src/uploads" ]; then
            rm -rf src/uploads/*
            echo "✅ Local uploads cleaned"
        else
            echo "❌ Local uploads directory not found"
        fi
        ;;
    "help"|*)
        echo "Usage: $0 <command>"
        echo ""
        echo "Commands:"
        echo "  upload   - Upload local images to R2"
        echo "  download - Download images from R2 to local"
        echo "  sync     - Sync local changes to R2"
        echo "  status   - Show image status"
        echo "  clean    - Clean local uploads directory"
        echo "  help     - Show this help message"
        echo ""
        echo "Examples:"
        echo "  $0 upload     # Upload local images to R2"
        echo "  $0 download   # Download images from R2"
        echo "  $0 status     # Check current status"
        ;;
esac
