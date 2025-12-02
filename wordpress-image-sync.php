<?php
/**
 * WordPress Image Sync Script
 * 
 * This script registers downloaded images in the WordPress database.
 * Run with: wp eval-file wordpress-image-sync.php
 */

// Ensure we're in WordPress context
if (!function_exists('wp_insert_attachment')) {
    echo "❌ This script must be run within WordPress context\n";
    echo "Usage: wp eval-file wordpress-image-sync.php\n";
    exit(1);
}

echo "🔄 Registering images in WordPress database...\n";

$uploadsDir = wp_upload_dir()['basedir'];
$registered = 0;
$skipped = 0;

// Scan uploads directory for images
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    
    $filePath = $file->getPathname();
    $relativePath = str_replace($uploadsDir . '/', '', $filePath);
    
    // Check if it's an image
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'pdf'];
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    
    if (!in_array($extension, $imageExtensions)) {
        continue;
    }
    
    // Skip WordPress-generated thumbnails (they contain size info like -150x150)
    $filename = basename($filePath, '.' . $extension);
    if (preg_match('/-\d+x\d+$/', $filename)) {
        echo "   ⏭️  Skipped (WordPress thumbnail): $relativePath\n";
        continue;
    }
    
    // Check if already registered
    if (isImageRegistered($relativePath)) {
        $skipped++;
        echo "   ⏭️  Skipped (already registered): $relativePath\n";
        continue;
    }
    
    // Register the image
    if (registerImage($relativePath, $filePath)) {
        $registered++;
        echo "   ✅ Registered: $relativePath\n";
    }
}

echo "�� Complete: $registered registered, $skipped skipped\n";

function isImageRegistered($relativePath) {
    global $wpdb;
    
    // Check by the _wp_attached_file meta field (most reliable method)
    $attachment = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'attachment' 
            AND pm.meta_key = '_wp_attached_file'
            AND pm.meta_value = %s",
            $relativePath
        )
    );
    
    // Debug output
    if ($attachment) {
        echo "   🔍 Found existing attachment ID: {$attachment->ID} for: $relativePath\n";
    }
    
    return $attachment !== null;
}

function registerImage($relativePath, $filePath) {
    // Get file info
    $fileType = wp_check_filetype(basename($filePath), null);
    
    if (!$fileType['type']) {
        return false;
    }
    
    // Create attachment post
    $attachment = array(
        'post_mime_type' => $fileType['type'],
        'post_title' => preg_replace('/\.[^.]+$/', '', basename($filePath)),
        'post_content' => '',
        'post_status' => 'inherit',
        'post_author' => get_current_user_id() ?: 1,
    );
    
    // Insert the attachment
    $attachId = wp_insert_attachment($attachment, $filePath);
    
    if (is_wp_error($attachId)) {
        return false;
    }
    
    // Generate attachment metadata
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attachData = wp_generate_attachment_metadata($attachId, $filePath);
    
    if (!is_wp_error($attachData)) {
        wp_update_attachment_metadata($attachId, $attachData);
    }
    
    return true;
}
