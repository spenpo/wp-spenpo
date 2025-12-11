#!/bin/bash

# Usage: ./init-migration.sh <migration-description>

# Script to create a new migration file with today's date and proper sequence number

MIGRATIONS_DIR="./migrations"

# Check if migrations directory exists
if [ ! -d "$MIGRATIONS_DIR" ]; then
    echo "Error: Migrations directory '$MIGRATIONS_DIR' does not exist" >&2
    exit 1
fi

# Get today's date in YYYYMMDD format
TODAY=$(date +%Y%m%d)

# Determine the next sequence number
sequence_num=1
max_sequence=0
has_unsequenced=false

# Check all migration files to find ones for today
shopt -s nullglob
for migration in "$MIGRATIONS_DIR"/*.sql; do
    if [ -f "$migration" ]; then
        filename=$(basename "$migration")
        
        # Skip seed.sql
        if [[ "$filename" == "seed.sql" ]]; then
            continue
        fi
        
        # Check if this migration is for today
        if [[ "$filename" =~ ^${TODAY}- ]]; then
            # Extract sequence number if present (format: YYYYMMDD-001-description.sql)
            if [[ "$filename" =~ ^${TODAY}-([0-9]{1,3})-.+\.sql$ ]]; then
                seq="${BASH_REMATCH[1]}"
                # Remove leading zeros for comparison
                seq_int=$((10#$seq))
                if [ $seq_int -gt $max_sequence ]; then
                    max_sequence=$seq_int
                fi
            else
                # Migration without sequence number exists (doesn't match the sequenced pattern)
                has_unsequenced=true
            fi
        fi
    fi
done
shopt -u nullglob

# Determine sequence number
if [ $has_unsequenced = true ]; then
    # There's already a migration today without sequence, so next one should be 001
    sequence_num=1
elif [ $max_sequence -gt 0 ]; then
    # There are migrations with sequences, increment
    sequence_num=$((max_sequence + 1))
else
    # First migration of the day, no sequence needed
    sequence_num=0
fi

# Prompt for migration description
if [ -z "$1" ]; then
    echo "Enter migration description (e.g., 'insert-wastewater-image-post'):"
    read -r description
else
    description="$1"
fi

# Validate description
if [ -z "$description" ]; then
    echo "Error: Migration description cannot be empty" >&2
    exit 1
fi

# Remove any file extension if user included it
description="${description%.sql}"

# Construct filename
if [ $sequence_num -eq 0 ]; then
    # First migration of the day, no sequence number needed
    filename="${TODAY}-${description}.sql"
    sequence_str="000"
else
    # Multiple migrations today, use sequence number
    sequence_str=$(printf "%03d" $sequence_num)
    filename="${TODAY}-${sequence_str}-${description}.sql"
fi

filepath="${MIGRATIONS_DIR}/${filename}"

# Check if file already exists
if [ -f "$filepath" ]; then
    echo "Error: Migration file '$filepath' already exists" >&2
    exit 1
fi

# Create the migration file with a basic template
if [ $sequence_num -eq 0 ]; then
    sequence_display="none (first migration of the day)"
else
    sequence_display="${sequence_str}"
fi

cat > "$filepath" << EOF
-- Migration: $description
-- Date: $(date +%Y-%m-%d)
-- Sequence: ${sequence_display}

-- Add your SQL migration code here

EOF

echo "✅ Created migration file: $filepath"
echo ""
echo "You can now edit the file to add your SQL migration code."
