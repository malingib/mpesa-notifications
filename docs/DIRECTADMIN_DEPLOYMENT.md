# DirectAdmin Deployment Guide

## 🚀 Overview

This guide covers deploying the M-Pesa Notifications system on a DirectAdmin hosting environment. DirectAdmin is a shared hosting control panel that requires specific configuration for Laravel applications.

## ⚠️ Common Issue: "Forbidden" Error

If you're seeing "Forbidden - You don't have permission to access this resource", it's usually because:
1. Document root is pointing to the wrong directory
2. Laravel's `public` directory is not being used as document root
3. Permissions are incorrect
4. `.htaccess` file is missing or misconfigured

## 📋 Pre-Deployment Checklist

- [ ] DirectAdmin account created
- [ ] Domain/subdomain configured
- [ ] PHP version set to 8.2 or 8.3
- [ ] MySQL database created
- [ ] SSH access enabled (for migrations and commands)
- [ ] Composer installed or available

## 🔧 Step-by-Step Deployment

### Step 1: Access Your Domain Directory

```bash
# SSH into your DirectAdmin server
ssh talksasa@your-server.com

# Navigate to your domain directory
cd ~/domains/your-domain.com/public_html
```

### Step 2: Upload Application Files

**Option A: Via Git (Recommended)**
```bash
cd ~/domains/your-domain.com
git clone <your-repository-url> app
cd app
```

**Option B: Via FTP/SFTP**
- Upload all files to `~/domains/your-domain.com/app/`
- Exclude: `vendor/`, `node_modules/`, `.env`, `storage/logs/*.log`

### Step 3: Fix Directory Structure

**CRITICAL:** DirectAdmin uses `public_html` as document root, but Laravel needs `public` as document root.

**Solution: Move Laravel files and configure document root**

```bash
cd ~/domains/your-domain.com

# If you cloned/uploaded to 'app' directory:
# Move Laravel files one level up
mv app/* .
mv app/.* . 2>/dev/null || true
rmdir app

# OR if files are already in public_html:
# Move everything except public_html contents to parent
cd public_html
mkdir ../app_backup
mv app bootstrap config database resources routes storage tests vendor artisan composer.json composer.lock phpunit.xml .env.example ../app_backup/ 2>/dev/null || true

# Now move public directory contents to public_html
mv public/* .
mv public/.* . 2>/dev/null || true
rmdir public

# Move Laravel files back
mv ../app_backup/* .
mv ../app_backup/.* . 2>/dev/null || true
rmdir ../app_backup
```

**Better Solution: Configure DirectAdmin to use subdirectory**

1. In DirectAdmin, go to **Domain Setup** → **your-domain.com**
2. Set **Document Root** to: `/home/talksasa/domains/your-domain.com/public_html/public`
3. Or use **Subdomain** feature to point to `public_html/public`

### Step 4: Update Paths in index.php

Since Laravel files are now one level up from `public_html`, update `public_html/index.php`:

```bash
cd ~/domains/your-domain.com/public_html
nano index.php
```

Update the paths:
```php
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
```

### Step 5: Create/Update .htaccess

Create or update `public_html/.htaccess`:

```bash
cd ~/domains/your-domain.com/public_html
nano .htaccess
```

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# Security Headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>

# Prevent access to sensitive files
<FilesMatch "^\.">
    Order allow,deny
    Deny from all
</FilesMatch>

# Increase upload size for webhooks
php_value upload_max_filesize 10M
php_value post_max_size 10M
```

### Step 6: Set Permissions (Without Sudo)

In DirectAdmin, you typically don't have sudo access. Set permissions as your user:

```bash
cd ~/domains/your-domain.com/public_html

# Set directory permissions
find . -type d -exec chmod 755 {} \;

# Set file permissions
find . -type f -exec chmod 644 {} \;

# Make artisan executable
chmod +x artisan

# CRITICAL: Storage and cache need write permissions
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Create storage subdirectories if they don't exist
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs

# Set permissions on storage subdirectories
chmod -R 775 storage/framework
chmod -R 775 storage/logs

# Verify permissions
ls -ld storage bootstrap/cache
# Should show: drwxrwxr-x
```

### Step 7: Install Dependencies

```bash
cd ~/domains/your-domain.com/public_html

# Install Composer dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# If composer is not installed globally, download it:
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
php composer.phar install --no-dev --optimize-autoloader
```

### Step 8: Configure Environment

```bash
cd ~/domains/your-domain.com/public_html

# Copy environment file
cp .env.example .env

# Edit .env file
nano .env
```

**Required `.env` Configuration:**

```env
APP_NAME="M-Pesa Notifications"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://your-domain.com

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120

# M-Pesa Configuration (optional - users configure per account)
MPESA_ENVIRONMENT=sandbox

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=25
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@your-domain.com"
MAIL_FROM_NAME="${APP_NAME}"
```

**Generate Application Key:**
```bash
php artisan key:generate
```

### Step 9: Set PHP Version in DirectAdmin

1. Log into DirectAdmin
2. Go to **Account Manager** → **Select PHP Version**
3. Choose **PHP 8.2** or **PHP 8.3**
4. Enable required extensions:
   - `php-mysql`
   - `php-mbstring`
   - `php-xml`
   - `php-curl`
   - `php-zip`
   - `php-gd`
   - `php-bcmath`

### Step 10: Database Setup

```bash
cd ~/domains/your-domain.com/public_html

# Run migrations
php artisan migrate --force

# Create cache and session tables
php artisan cache:table
php artisan session:table
php artisan migrate --force
```

### Step 11: Create Storage Link

```bash
php artisan storage:link
```

### Step 12: Optimize for Production

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache
```

### Step 13: Configure Queue Worker (Cron Job)

Since DirectAdmin typically doesn't have Supervisor, use cron jobs:

1. In DirectAdmin, go to **Advanced Features** → **Cron Jobs**
2. Add a new cron job:

**Option A: Run queue worker continuously (recommended)**
```bash
* * * * * cd /home/talksasa/domains/your-domain.com/public_html && php artisan schedule:run >> /dev/null 2>&1
```

**Option B: Process queue jobs every minute**
```bash
* * * * * cd /home/talksasa/domains/your-domain.com/public_html && php artisan queue:work database --queue=sms,default --tries=3 --timeout=60 --once >> /dev/null 2>&1
```

**Option C: Use a dedicated queue processing script**

Create `~/domains/your-domain.com/public_html/queue-worker.sh`:
```bash
#!/bin/bash
cd /home/talksasa/domains/your-domain.com/public_html
php artisan queue:work database --queue=sms,default --sleep=3 --tries=3 --max-time=3600 --timeout=60
```

Make it executable:
```bash
chmod +x queue-worker.sh
```

Add to cron (runs every 5 minutes, checks if process is running):
```bash
*/5 * * * * cd /home/talksasa/domains/your-domain.com/public_html && [ -z "$(ps aux | grep 'queue:work' | grep -v grep)" ] && ./queue-worker.sh >> storage/logs/queue.log 2>&1 &
```

## 🔐 Fixing "Forbidden" Error

### Solution 1: Check Document Root

Verify DirectAdmin is pointing to the correct directory:

1. In DirectAdmin: **Domain Setup** → **your-domain.com**
2. Check **Document Root** - should be `/home/talksasa/domains/your-domain.com/public_html`
3. Ensure `public_html/index.php` exists and has correct paths

### Solution 2: Verify .htaccess

```bash
cd ~/domains/your-domain.com/public_html
ls -la .htaccess
cat .htaccess
```

Ensure `.htaccess` exists and contains the Laravel rewrite rules.

### Solution 3: Check File Permissions

```bash
cd ~/domains/your-domain.com/public_html

# Check permissions
ls -la index.php
ls -la .htaccess
ls -ld storage bootstrap/cache

# Fix if needed
chmod 644 index.php
chmod 644 .htaccess
chmod -R 775 storage bootstrap/cache
```

### Solution 4: Verify PHP Version

```bash
# Check PHP version
php -v

# Should show PHP 8.2 or 8.3
```

### Solution 5: Check Error Logs

```bash
# Check Laravel logs
tail -f ~/domains/your-domain.com/public_html/storage/logs/laravel.log

# Check DirectAdmin error logs
tail -f ~/logs/your-domain.com/error.log
```

### Solution 6: Test Direct Access

```bash
# Test if index.php is accessible
curl -I https://your-domain.com/index.php

# Should return 200 OK, not 403 Forbidden
```

## 🛠️ Alternative: Use Subdomain for Laravel Public

If you can't modify document root, use a subdomain:

1. In DirectAdmin: **Subdomain Management** → **Create Subdomain**
2. Create subdomain: `app.your-domain.com`
3. Point it to: `/home/talksasa/domains/your-domain.com/public_html/public`
4. Update `APP_URL` in `.env` to: `https://app.your-domain.com`

## 📝 Directory Structure (Recommended)

```
~/domains/your-domain.com/
├── public_html/          # Document root (DirectAdmin requirement)
│   ├── index.php        # Laravel entry point (updated paths)
│   ├── .htaccess        # Apache rewrite rules
│   └── [other public files]
├── app/                 # Laravel application (one level up)
├── bootstrap/
├── config/
├── database/
├── resources/
├── routes/
├── storage/             # Must be writable (775)
├── vendor/
├── artisan
├── composer.json
└── .env
```

**OR** (if you can set document root):

```
~/domains/your-domain.com/
├── public_html/         # Document root → points to public/
│   └── public/         # Laravel public directory
│       ├── index.php
│       └── .htaccess
├── app/
├── bootstrap/
├── config/
├── storage/            # Must be writable (775)
└── [other Laravel files]
```

## 🔍 Verification Steps

### 1. Test Application
```bash
cd ~/domains/your-domain.com/public_html

# Test artisan
php artisan --version

# Test routes
php artisan route:list

# Test database
php artisan migrate:status
```

### 2. Check Web Access
```bash
# Test homepage
curl -I https://your-domain.com

# Should return: HTTP/2 200 OK
```

### 3. Verify Permissions
```bash
ls -ld storage bootstrap/cache
# Should show: drwxrwxr-x

ls -l index.php .htaccess
# Should show: -rw-r--r--
```

## 🚨 Common Issues & Solutions

### Issue 1: "Class not found" errors
**Solution:**
```bash
composer dump-autoload
php artisan optimize:clear
php artisan config:cache
```

### Issue 2: "Storage directory not writable"
**Solution:**
```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

### Issue 3: "Session table not found"
**Solution:**
```bash
php artisan session:table
php artisan migrate --force
```

### Issue 4: Queue jobs not processing
**Solution:**
- Verify cron job is running: `crontab -l`
- Check queue logs: `tail -f storage/logs/laravel.log`
- Manually test: `php artisan queue:work --once`

### Issue 5: "500 Internal Server Error"
**Solution:**
```bash
# Check error logs
tail -f storage/logs/laravel.log
tail -f ~/logs/your-domain.com/error.log

# Clear caches
php artisan optimize:clear

# Regenerate config cache
php artisan config:cache
```

## 📚 Additional DirectAdmin Configuration

### Enable mod_rewrite
1. DirectAdmin → **Feature Manager** → **Apache Options**
2. Ensure **mod_rewrite** is enabled

### Set PHP Handler
1. DirectAdmin → **Select PHP Version**
2. Choose **php-fpm** or **suphp** (recommended: php-fpm)

### Configure MySQL
1. DirectAdmin → **MySQL Management**
2. Create database and user
3. Grant privileges
4. Use credentials in `.env`

## 🔄 Updating the Application

```bash
cd ~/domains/your-domain.com/public_html

# Pull latest changes (if using git)
git pull origin main

# Install new dependencies
composer install --no-dev --optimize-autoloader

# Run migrations
php artisan migrate --force

# Clear and rebuild caches
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 📞 Support

If issues persist:
1. Check `storage/logs/laravel.log`
2. Check DirectAdmin error logs: `~/logs/your-domain.com/error.log`
3. Verify PHP version: `php -v`
4. Test database connection: `php artisan tinker` → `DB::connection()->getPdo();`
5. Verify file permissions: `ls -la storage bootstrap/cache`
