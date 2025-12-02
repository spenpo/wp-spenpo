# WordPress Build Project

A modern WordPress development setup using Composer for dependency management and a clean build process.

## 🚀 Quick Start

### 1. Clone and Build
```bash
git clone <your-repo>
cd wp-build
./build.sh
```

### 2. Set up WordPress Configuration
The build script does not generate `wp-config.php`. You need to:

**For Development:**
- Copy `wordpress/wp-config-sample.php` to `wordpress/wp-config.php`
- Update database credentials and security keys

**For Production/Deployment:**
- Your deployment environment should handle `wp-config.php`
- DeployHQ or similar services can manage environment-specific configuration

### 3. Start Development Server
```bash
php -S localhost:8000 -t wordpress/
```

Visit http://localhost:8000 to complete WordPress installation.

## 📁 Project Structure

```
wp-build/
├── composer.json          # Dependencies and configuration
├── composer.lock          # Locked versions (committed)
├── .gitignore            # Ignores generated files
├── build.php             # Build script
├── build.sh              # Build shell script
├── src/                  # Your custom code (committed)
│   ├── bootstrap.php     # WordPress initialization
│   ├── CustomPlugin.php  # Main custom plugin
│   ├── Utilities/        # Helper functions
│   └── Features/         # Custom features
├── vendor/               # Composer packages (ignored)
└── wordpress/            # WordPress installation (ignored)
    ├── wp-admin/         # WordPress admin
    ├── wp-includes/      # WordPress core
    ├── wp-content/       # Plugins and themes
    └── wp-config.php     # WordPress configuration (environment-specific)
```

## 🔧 Development Workflow

### Adding Plugins
```bash
composer require wpackagist-plugin/plugin-name
./build.sh
```

### Adding Custom Code
1. Add your classes to `src/`
2. Initialize them in `src/bootstrap.php`
3. The code is automatically loaded by WordPress

### Rebuilding WordPress
```bash
./build.sh
```

This completely rebuilds the WordPress installation from Composer dependencies.

## 🎯 Key Features

- **Composer-managed**: All dependencies managed through Composer
- **Clean builds**: Entire WordPress installation rebuilt from scratch
- **Custom code**: Your code in `src/` automatically loaded
- **Version controlled**: Only source code and configuration committed
- **Deployment ready**: Environment handles WordPress configuration
- **Development ready**: Debug mode enabled, file editing disabled
- **R2 image storage**: Images stored in Cloudflare R2, not in git

## 📦 Installed Plugins

- **WooCommerce**: E-commerce functionality
- **Contact Form 7**: Contact forms
- **Yoast SEO**: Search engine optimization

## 🖼️ Image Management

This project uses Cloudflare R2 for image storage instead of committing images to git. This keeps your repository lightweight while ensuring images are available during builds.

### Setup
1. **Environment Configuration**: Set your R2 credentials in one of these ways:

   **For Local Development:**
   ```bash
   cp env.example .env
   # Edit .env with your R2 credentials
   ```

   **For Production/CI/CD:**
   Set these environment variables in your build environment:
   ```bash
   R2_ACCESS_KEY_ID=your_access_key
   R2_SECRET_ACCESS_KEY=your_secret_key
   R2_ENDPOINT=your_r2_endpoint
   R2_BUCKET_NAME=your_bucket_name
   ```

2. **Install Dependencies**: The AWS SDK is included via Composer:
   ```bash
   composer install
   ```

### Managing Images

#### Upload Local Images to R2
```bash
./image-manager.sh upload
# or
php r2-sync.php upload
```

#### Download Images from R2
```bash
./image-manager.sh download
# or
php r2-sync.php download
```

#### Check Image Status
```bash
./image-manager.sh status
```

#### Clean Local Uploads
```bash
./image-manager.sh clean
```

### Build Process Integration
Images are automatically downloaded from R2 during the build process:
```bash
./build.sh
```

The build script will:
1. Rebuild WordPress from Composer
2. Fetch images from R2 to `src/uploads/`
3. Copy images to the WordPress installation

### Workflow
1. **Development**: Add images to `src/uploads/` locally
2. **Upload**: Run `./image-manager.sh upload` to sync to R2
3. **Build**: Run `./build.sh` to fetch images and rebuild
4. **Deployment**: Images are automatically available in production builds

### Environment Variable Precedence
The script follows this order for configuration:
1. **System environment variables** (highest priority - for production/CI/CD)
2. **`.env` file** (for local development)
3. **Default values** (lowest priority)

This means production builds can override local `.env` settings by setting environment variables.

### File Structure
```
src/uploads/           # Local image development (gitignored)
├── 2025/
│   └── 08/
│       └── image.png
wordpress/wp-content/uploads/  # WordPress uploads (gitignored)
```

## 🛠️ Custom Code

Your custom code goes in the `src/` directory and is automatically loaded by WordPress.

### Example Custom Feature
```php
// src/Features/SocialSharing.php
namespace Spenpo\WpBuild\Features;

class SocialSharing {
    public function __construct() {
        add_filter('the_content', [$this, 'add_social_buttons']);
    }
}
```

### Initializing Custom Code
```php
// src/bootstrap.php
new Features\SocialSharing();
```

## 🧪 Testing

### Run Build Tests
```bash
php test-build.php
```

### Run Source Code Tests
```bash
php test-src.php
```

### Test Without Database
```bash
php test-no-db.php
```

## 🔄 Build Process

The build script (`build.php`) does the following:

1. **Cleans up**: Removes existing WordPress installation
2. **Installs dependencies**: Runs `composer install`
3. **Sets up structure**: Organizes files in `wordpress/` directory
4. **Creates directories**: Sets up uploads, cache, etc.
5. **Sets permissions**: Ensures proper file permissions
6. **Verifies installation**: Checks all required files exist

**Note**: `wp-config.php` is not generated by the build script. The environment should handle WordPress configuration.

## 🚫 What's Ignored

The `.gitignore` file excludes:
- `wordpress/` - Entire WordPress installation
- `vendor/` - Composer packages
- `composer.lock` - Optional (some teams commit this)
- Uploads, cache, and logs
- Environment-specific files

## 🔒 Security

- WordPress configuration is handled by the environment
- File editing is disabled in admin
- Automatic updates are disabled
- Debug logging is enabled for development

## 🚀 Deployment

### DeployHQ Setup
1. Connect your repository to DeployHQ
2. Set build command: `./build.sh`
3. Configure environment variables for database credentials
4. DeployHQ will handle `wp-config.php` generation

### Environment Variables
Your deployment environment should set:
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`
- `DB_HOST`
- WordPress security keys

## 📚 Documentation

- [TESTING.md](TESTING.md) - Comprehensive testing guide
- [WordPress Codex](https://codex.wordpress.org/) - WordPress documentation
- [Composer Documentation](https://getcomposer.org/doc/) - Composer documentation

## 🔄 WordPress Migrations System

The project includes a robust migration system for managing WordPress database changes. Migrations are automatically run during deployment to ensure consistent database state across environments.

### Migration Types

#### 1. SQL-Only Migrations
Simple migrations that only require SQL statements. Just create a `.sql` file and it will run directly.

**Example**: `migrations/create-users-table.sql`

#### 2. Enhanced Migrations (PHP + SQL)
Complex migrations that need serialized data or dynamic content. Create both a `.sql` file and a matching `.php` file.

**Files needed**:
- `migrations/migration-name.sql` - Base SQL statements
- `migrations/migration-name.php` - PHP script that generates additional SQL

#### 3. Plugin Activation Migrations
Simple plugin activation without needing SQL or content. Just create a file starting with `activate-`.

**Files needed**:
- `migrations/activate-plugin-name` - No extension needed, no content required

### How Enhanced Migrations Work

1. **Detection**: The migration system automatically detects when both `.sql` and `.php` files exist
2. **PHP Execution**: The PHP script runs first, generating additional SQL
3. **SQL Enhancement**: The generated SQL is appended to the original SQL file
4. **Execution**: The enhanced SQL file is executed
5. **Cleanup**: Temporary files are automatically removed

### Plugin Activation Migrations

Plugin activation files are automatically detected and handled by WP-CLI:

- **Naming Convention**: `activate-plugin-name` (no extension required)
- **Content**: No content needed - the filename is sufficient
- **Processing**: Automatically extracts plugin name and runs `wp plugin activate`
- **Examples**: 
  - `activate-hello-dolly` → activates "hello-dolly" plugin
  - `activate-woocommerce` → activates "woocommerce" plugin

### PHP Script Requirements

Your PHP script must:
- Accept command line arguments: `$argv[1]` = WordPress path
- Output valid SQL statements to stdout
- Exit with code 0 on success, non-zero on failure

### Example Enhanced Migration

#### `migrations/example-serialized-data.sql`
```sql
-- Basic table structure
CREATE TABLE IF NOT EXISTS wp_example_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- PHP script will append serialized options here
```

#### `migrations/example-serialized-data.php`
```php
<?php
// Generate serialized WordPress options
$options = [
    'my_setting' => ['enabled' => true, 'features' => ['a', 'b', 'c']]
];

foreach ($options as $name => $value) {
    $serialized = serialize($value);
    echo "INSERT INTO wp_options (option_name, option_value) VALUES ('$name', '$serialized');\n";
}
```

### Best Practices

1. **Keep SQL files simple** - Use them for basic structure
2. **Use PHP for complex data** - Serialized data, dynamic content, complex logic
3. **Plugin activation files** - No extension needed, no content required
4. **Test locally** - Run migrations in development before production
5. **Version control** - All migration files should be committed
6. **Documentation** - Comment your PHP scripts clearly

### Migration Execution Order

Migrations are executed in alphabetical order by filename. Use numeric prefixes if you need specific ordering:
- `001-create-tables.sql`
- `002-add-data.sql`
- `003-activate-plugins` (no extension needed)

### File Naming Examples

```
migrations/
├── seed.sql                           # Basic SQL migration (creates migrations table)
├── create-tables.sql                  # SQL-only migration
├── complex-data.sql                   # SQL migration
├── complex-data.php                   # PHP enhancement script
├── activate-hello-dolly              # Plugin activation (no extension)
├── activate-woocommerce              # Plugin activation (no extension)
└── activate-contact-form-7           # Plugin activation (no extension)
```

### Running Migrations

Migrations are automatically run during deployment by your CI/CD pipeline:

```bash
./migrations.sh /path/to/wordpress
```

The script will:
1. Check if the migrations table exists and create it if needed
2. Run all pending migrations in alphabetical order
3. Track which migrations have been applied
4. Handle plugin activations automatically


## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Add your custom code to `src/`
4. Test with `./build.sh`
5. Submit a pull request

## 📄 License

MIT License - see LICENSE file for details. 