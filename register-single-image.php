<?php
/**
 * WordPress Single Image Registration Script
 * 
 * This script registers a single downloaded image in the WordPress database.
 * Run with: wp eval-file register-single-image.php -- <file_path>
 * 
 * Example:
 * wp eval-file register-single-image.php -- "2024/11/example-image.jpg"
 * wp eval-file register-single-image.php -- "/full/path/to/image.png"
 */

// Ensure we're in WordPress context
if (!function_exists('wp_insert_attachment')) {
    echo "❌ This script must be run within WordPress context\n";
    echo "Usage: wp eval-file register-single-image.php -- <file_path>\n";
    echo "Example: wp eval-file register-single-image.php -- \"2024/11/example-image.jpg\"\n";
    echo "\nDebug info:\n";
    echo "  - WordPress loaded: " . (defined('ABSPATH') ? 'Yes' : 'No') . "\n";
    echo "  - wp_insert_attachment exists: " . (function_exists('wp_insert_attachment') ? 'Yes' : 'No') . "\n";
    echo "  - wp_upload_dir exists: " . (function_exists('wp_upload_dir') ? 'Yes' : 'No') . "\n";
    exit(1);
}

// Get the file path from command line arguments
// wp eval-file passes arguments after -- to the script
$filePath = null;

// Find the -- separator and get the next argument
if (isset($_SERVER['argv'])) {
    $args = $_SERVER['argv'];
    $dashIndex = array_search('--', $args);
    if ($dashIndex !== false && isset($args[$dashIndex + 1])) {
        $filePath = $args[$dashIndex + 1];
    }
} elseif (isset($GLOBALS['argv'])) {
    $args = $GLOBALS['argv'];
    $dashIndex = array_search('--', $args);
    if ($dashIndex !== false && isset($args[$dashIndex + 1])) {
        $filePath = $args[$dashIndex + 1];
    }
}

if (!$filePath) {
    echo "❌ No file path provided\n";
    echo "Usage: wp eval-file register-single-image.php -- <file_path>\n";
    echo "Example: wp eval-file register-single-image.php -- \"2024/11/example-image.jpg\"\n";
    echo "\nNote: Make sure to use the -- separator before the file path\n";
    exit(1);
}

echo "🔄 Registering single image: $filePath\n";

$uploadsDir = wp_upload_dir()['basedir'];

// Since we're running from WordPress root and using tab completion,
// the path will be like "wp-content/uploads/2019/10/image.jpg"
// We need to strip "wp-content/uploads/" to get the relative path
if (strpos($filePath, 'wp-content/uploads/') === 0) {
    $relativePath = str_replace('wp-content/uploads/', '', $filePath);
} else {
    $relativePath = $filePath;
}

$fullPath = $uploadsDir . '/' . $relativePath;

echo "   📁 Full path: $fullPath\n";
echo "   📁 Relative path: $relativePath\n";

// Check if file exists
if (!file_exists($fullPath)) {
    echo "❌ File not found: $fullPath\n";
    exit(1);
}

// Check if it's an image
$imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'pdf'];
$extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

if (!in_array($extension, $imageExtensions)) {
    echo "❌ File is not a supported image format: $extension\n";
    echo "Supported formats: " . implode(', ', $imageExtensions) . "\n";
    exit(1);
}

// Skip WordPress-generated thumbnails (they contain size info like -150x150)
$filename = basename($fullPath, '.' . $extension);
if (preg_match('/-\d+x\d+$/', $filename)) {
    echo "⚠️  Skipped (WordPress thumbnail): $relativePath\n";
    echo "WordPress thumbnails are automatically generated and should not be registered manually.\n";
    exit(0);
}

// Check if already registered
if (isImageRegistered($relativePath)) {
    echo "⏭️  Image already registered: $relativePath\n";
    exit(0);
}

// Register the image
if (registerImage($relativePath, $fullPath)) {
    echo "✅ Successfully registered: $relativePath\n";
} else {
    echo "❌ Failed to register: $relativePath\n";
    exit(1);
}

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
        echo "   ❌ Could not determine file type for: " . basename($filePath) . "\n";
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
        echo "   ❌ WordPress error: " . $attachId->get_error_message() . "\n";
        return false;
    }
    
    // Generate attachment metadata
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attachData = wp_generate_attachment_metadata($attachId, $filePath);
    
    if (!is_wp_error($attachData)) {
        wp_update_attachment_metadata($attachId, $attachData);
        echo "   📝 Generated metadata for attachment ID: $attachId\n";
    } else {
        echo "   ⚠️  Warning: Could not generate metadata: " . $attachData->get_error_message() . "\n";
    }
    
    return true;
}
