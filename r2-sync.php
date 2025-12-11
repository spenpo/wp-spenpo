<?php
/**
 * R2 Image Sync Script
 * 
 * This script syncs images between local uploads directory and Cloudflare R2 storage.
 * It can upload local images to R2 or download images from R2 to local.
 * 
 * Upload Feature:
 * - By default, only uploads new files or files that have changed
 * - Uses ETag/MD5 comparison to detect changes (skips identical files)
 * - Significantly faster for large directories with mostly unchanged files
 * - Use --force to upload all files regardless of their status
 */

require_once 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class R2ImageSync {
    private $s3Client;
    private $bucket;
    private $localUploadsDir;
    private $buildUploadsDir;
    
    public function __construct() {
        // Load environment variables from .env file for local development
        if (file_exists('.env')) {
            $this->loadEnvFile('.env');
        }
        
        // Validate required environment variables
        $requiredVars = ['R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY', 'R2_ENDPOINT'];
        $missingVars = [];
        
        foreach ($requiredVars as $var) {
            if (!getenv($var)) {
                $missingVars[] = $var;
            }
        }
        
        if (!empty($missingVars)) {
            echo "❌ Missing required environment variables: " . implode(', ', $missingVars) . "\n";
            echo "   These should be set in your environment or in a .env file for local development.\n";
            echo "   For production builds, ensure these are set as environment variables.\n";
            exit(1);
        }
        
        $this->bucket = getenv('R2_BUCKET_NAME') ?: 'dealvault';
        $this->localUploadsDir = 'src/uploads';
        $this->buildUploadsDir = 'wordpress/wp-content/uploads';
                
        // Initialize S3 client for R2
        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region' => 'auto',
            'endpoint' => getenv('R2_ENDPOINT'),
            'credentials' => [
                'key' => getenv('R2_ACCESS_KEY_ID'),
                'secret' => getenv('R2_SECRET_ACCESS_KEY'),
            ],
            'use_path_style_endpoint' => true,
        ]);
    }
    
    private function loadEnvFile($file) {
        if (!file_exists($file)) {
            return;
        }
        
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments and empty lines
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, '"\'');
                
                // Only set if not already set in environment
                if (!getenv($key)) {
                    putenv("$key=$value");
                }
            }
        }
    }
    
    public function uploadToR2($dryRun = false, $skipExisting = true) {
        echo "📤 Uploading local images to R2...\n";
        
        if (!is_dir($this->localUploadsDir)) {
            echo "❌ Local uploads directory not found: {$this->localUploadsDir}\n";
            return false;
        }
        
        $files = $this->scanDirectory($this->localUploadsDir);
        $uploaded = 0;
        $skipped = 0;
        $changed = 0;
        $wouldUpload = 0;
        
        foreach ($files as $file) {
            $relativePath = str_replace($this->localUploadsDir . '/', '', $file);
            $r2Key = 'uploads/' . $relativePath;
            
            // Check if file needs to be uploaded by comparing ETags
            if ($skipExisting) {
                try {
                    $checkResult = $this->checkFileNeedsUpload($file, $r2Key);
                    
                    if (!$checkResult['needsUpload']) {
                        if ($dryRun) {
                            echo "   Would skip (unchanged): $relativePath\n";
                            $skipped++;
                        } else {
                            echo "   ⏭️  Skipped (unchanged): $relativePath\n";
                            $skipped++;
                        }
                        continue;
                    } else {
                        // File needs upload - track reason
                        if ($dryRun) {
                            $reason = $checkResult['reason'];
                            if ($reason === 'file content changed') {
                                echo "   Would upload (changed): $relativePath\n";
                                $changed++;
                            } else {
                                echo "   Would upload ($reason): $relativePath\n";
                            }
                            $wouldUpload++;
                        } else if ($checkResult['reason'] === 'file content changed') {
                            $changed++;
                        }
                    }
                } catch (AwsException $e) {
                    // If check fails, we'll try to upload anyway
                    if ($dryRun) {
                        echo "   Would upload (check failed): $relativePath\n";
                        $wouldUpload++;
                    } else {
                        echo "   ⚠️  Could not check file status for $relativePath, attempting upload: " . $e->getMessage() . "\n";
                    }
                }
            } else {
                // Not skipping existing, always upload
                if ($dryRun) {
                    echo "   Would upload: $relativePath\n";
                    $wouldUpload++;
                }
            }
            
            if ($dryRun) {
                continue;
            }
            
            try {
                $result = $this->s3Client->putObject([
                    'Bucket' => $this->bucket,
                    'Key' => $r2Key,
                    'SourceFile' => $file,
                    'ContentType' => $this->getMimeType($file),
                    'ACL' => 'public-read',
                ]);
                
                echo "   ✅ Uploaded: $relativePath\n";
                $uploaded++;
                
            } catch (AwsException $e) {
                echo "   ❌ Failed to upload $relativePath: " . $e->getMessage() . "\n";
            }
        }
        
        if ($dryRun) {
            $summary = "   🔍 Dry run complete: $wouldUpload would upload";
            if ($skipExisting && $skipped > 0) {
                $summary .= ", $skipped would skip (unchanged)";
                if ($changed > 0) {
                    $summary .= " ($changed changed files)";
                }
            }
            $summary .= "\n";
            echo $summary;
        } else {
            $summary = "   📊 Upload complete: $uploaded uploaded";
            if ($skipExisting) {
                $summary .= ", $skipped skipped (unchanged)";
                if ($changed > 0) {
                    $summary .= ", $changed updated";
                }
            }
            $summary .= "\n";
            echo $summary;
        }
        
        return true;
    }
    
    public function downloadFromR2($dryRun = false) {
        echo "📥 Downloading images from R2 to WordPress...\n";
        
        try {
            $objects = $this->s3Client->listObjects([
                'Bucket' => $this->bucket,
                'Prefix' => 'uploads/',
            ]);
            
            $downloaded = 0;
            $registered = 0;
            
            foreach ($objects['Contents'] as $object) {
                $r2Key = $object['Key'];
                
                if (!str_starts_with($r2Key, 'uploads/')) {
                    continue;
                }
                
                $relativePath = str_replace('uploads/', '', $r2Key);
                $localPath = $this->buildUploadsDir . '/' . $relativePath;
                
                $localDir = dirname($localPath);
                if (!is_dir($localDir)) {
                    if (!$dryRun) {
                        mkdir($localDir, 0755, true);
                    }
                }
                
                if ($dryRun) {
                    echo "   Would download: $r2Key -> $localPath\n";
                    continue;
                }
                
                try {
                    $result = $this->s3Client->getObject([
                        'Bucket' => $this->bucket,
                        'Key' => $r2Key,
                        'SaveAs' => $localPath,
                    ]);
                    
                    echo "   ✅ Downloaded: $relativePath\n";
                    $downloaded++;
                    
                    // Register the image in WordPress database
                    if ($this->registerImageInWordPress($relativePath, $localPath)) {
                        $registered++;
                        echo "   📝 Registered in WordPress: $relativePath\n";
                    }
                    
                } catch (AwsException $e) {
                    echo "   ❌ Failed to download $relativePath: " . $e->getMessage() . "\n";
                }
            }
            
            if ($dryRun) {
                echo "   🔍 Dry run complete. Would download " . count($objects['Contents']) . " files.\n";
            } else {
                echo "   📊 Download complete: $downloaded downloaded, $registered registered in WordPress\n";
            }
            
        } catch (AwsException $e) {
            echo "   ❌ Failed to list objects: " . $e->getMessage() . "\n";
            return false;
        }
        
        return true;
    }
    
    private function scanDirectory($dir) {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $this->isImageFile($file->getPathname())) {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    private function isImageFile($file) {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'pdf'];
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        return in_array($extension, $imageExtensions);
    }
    
    private function getMimeType($file) {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'pdf' => 'application/pdf',
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
    
    /**
     * Check if a file needs to be uploaded by comparing ETags
     * Returns array with 'needsUpload' (bool) and 'reason' (string)
     */
    private function checkFileNeedsUpload($localFile, $r2Key) {
        // Calculate local file MD5
        if (!file_exists($localFile)) {
            return ['needsUpload' => false, 'reason' => 'Local file does not exist'];
        }
        
        $localMd5 = md5_file($localFile);
        
        // Check if object exists in R2 and get its ETag
        try {
            $result = $this->s3Client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $r2Key,
            ]);
            
            // ETag is returned with quotes, remove them for comparison
            $r2Etag = trim($result['ETag'], '"');
            
            // If ETag contains a hyphen, it's a multipart upload and ETag != MD5
            // In that case, we'll re-upload to be safe
            if (strpos($r2Etag, '-') !== false) {
                return ['needsUpload' => true, 'reason' => 'ETag indicates multipart upload, cannot verify'];
            }
            
            // Compare MD5 hashes
            if ($localMd5 === $r2Etag) {
                return ['needsUpload' => false, 'reason' => 'unchanged'];
            } else {
                return ['needsUpload' => true, 'reason' => 'file content changed'];
            }
            
        } catch (AwsException $e) {
            // If object doesn't exist (404) or other error, we need to upload
            if ($e->getAwsErrorCode() === 'NotFound' || $e->getStatusCode() === 404) {
                return ['needsUpload' => true, 'reason' => 'file does not exist in R2'];
            }
            // For other errors, throw to be handled by caller
            throw $e;
        }
    }
    
    /**
     * Register an image in WordPress database as a media attachment
     */
    private function registerImageInWordPress($relativePath, $localPath) {
        // Check if WordPress is loaded
        if (!function_exists('wp_insert_attachment')) {
            echo "   ⚠️  WordPress not loaded, skipping database registration\n";
            return false;
        }
        
        // Check if file already exists in database
        $existingAttachment = $this->getAttachmentByPath($relativePath);
        if ($existingAttachment) {
            echo "   ℹ️  Image already registered: $relativePath\n";
            return true;
        }
        
        // Get file info
        $fileType = wp_check_filetype(basename($localPath), null);
        $attachment = array(
            'post_mime_type' => $fileType['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($localPath)),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        // Insert the attachment
        $attachId = wp_insert_attachment($attachment, $localPath);
        
        if (is_wp_error($attachId)) {
            echo "   ❌ Failed to register image: " . $attachId->get_error_message() . "\n";
            return false;
        }
        
        // Generate attachment metadata (thumbnails, etc.)
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachData = wp_generate_attachment_metadata($attachId, $localPath);
        wp_update_attachment_metadata($attachId, $attachData);
        
        return true;
    }
    
    /**
     * Check if an attachment already exists in the database
     */
    private function getAttachmentByPath($relativePath) {
        global $wpdb;
        
        $filename = basename($relativePath);
        $attachment = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'attachment' 
                AND guid LIKE %s",
                '%' . $wpdb->esc_like($filename) . '%'
            )
        );
        
        return $attachment;
    }
}

// CLI usage
if (php_sapi_name() === 'cli') {
    $sync = new R2ImageSync();
    
    $command = $argv[1] ?? 'help';
    $dryRun = in_array('--dry-run', $argv);
    $force = in_array('--force', $argv);
    
    switch ($command) {
        case 'upload':
            $sync->uploadToR2($dryRun, !$force);
            break;
        case 'download':
            $sync->downloadFromR2($dryRun);
            break;
        case 'help':
        default:
            echo "R2 Image Sync Tool\n\n";
            echo "Usage: php r2-sync.php <command> [options]\n\n";
            echo "Commands:\n";
            echo "  upload     - Upload local images to R2 (skips unchanged files by default)\n";
            echo "  download   - Download all images from R2 to local\n";
            echo "  help       - Show this help message\n\n";
            echo "Options:\n";
            echo "  --dry-run  - Show what would be done without making changes\n";
            echo "  --force    - Force upload even if file already exists and is unchanged\n\n";
            echo "Upload Behavior:\n";
            echo "  By default, uploads are optimized using ETag/MD5 comparison:\n";
            echo "  - New files (not in R2): Always uploaded\n";
            echo "  - Changed files (different content): Uploaded\n";
            echo "  - Unchanged files (same MD5 hash): Skipped\n";
            echo "  Use --force to upload all files regardless of status.\n\n";
            echo "Examples:\n";
            echo "  php r2-sync.php upload              # Upload only new/changed files\n";
            echo "  php r2-sync.php upload --dry-run    # Preview what would be uploaded\n";
            echo "  php r2-sync.php upload --force      # Upload all files (no optimization)\n";
            echo "  php r2-sync.php download --dry-run  # Preview what would be downloaded\n";
            break;
    }
}
