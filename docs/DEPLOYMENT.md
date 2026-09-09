# Production Deployment Guide

## 🚀 Quick Start

This guide covers deploying the M-Pesa Notifications system to a production server, including permission fixes and common issues.

## 📋 Pre-Deployment Checklist

- [ ] Server meets requirements (PHP 8.2+, MySQL 8.0+)
- [ ] Database created and credentials ready
- [ ] Domain name configured with DNS
- [ ] SSL certificate installed
- [ ] M-Pesa Developer Portal credentials ready
- [ ] Talksasa SMS API credentials ready
- [ ] Queue worker supervisor configured
- [ ] Backup strategy in place

## 🔧 Server Requirements

### Minimum Requirements
- **PHP**: 8.2 or 8.3
- **MySQL**: 8.0+
- **Composer**: Latest version
- **Web Server**: Nginx or Apache
- **Queue Manager**: Supervisor (required for SMS sending)

### PHP Extensions Required
```bash
php8.2-fpm (or php8.3-fpm)
php8.2-mysql (or php8.3-mysql)
php8.2-mbstring
php8.2-xml
php8.2-curl
php8.2-zip
php8.2-gd
php8.2-bcmath
```

### Install PHP Extensions
```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-bcmath

# Verify installation
php -v
php -m | grep -E "mysql|mbstring|xml|curl|zip|gd|bcmath"
```

## 📦 Deployment Steps

### Step 1: Upload Files

```bash
# Via Git (recommended)
cd /var/www
git clone <your-repository-url> mpesa-notifications
cd mpesa-notifications

# Or via FTP/SFTP - upload all files except:
# - vendor/ (will be installed via composer)
# - node_modules/ (if exists)
# - .env (create new)
# - storage/logs/*.log
```

### Step 2: Install Dependencies

```bash
cd /var/www/mpesa-notifications

# Install Composer dependencies (production mode)
composer install --no-dev --optimize-autoloader --no-interaction

# If composer is not installed globally:
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
php composer.phar install --no-dev --optimize-autoloader
```

### Step 3: Set Permissions (CRITICAL)

**This is the most common issue!** Laravel needs write permissions on storage and cache directories.

```bash
cd /var/www/mpesa-notifications

# Set ownership (replace www-data with your web server user if different)
# Common web server users: www-data (Ubuntu/Debian), apache (CentOS/RHEL), nginx (some setups)
sudo chown -R www-data:www-data .

# Set directory permissions
sudo find . -type d -exec chmod 755 {} \;

# Set file permissions
sudo find . -type f -exec chmod 644 {} \;

# Make artisan executable
sudo chmod +x artisan

# CRITICAL: Storage and cache directories need write permissions
sudo chmod -R 775 storage
sudo chmod -R 775 bootstrap/cache

# Ensure web server owns these directories
sudo chown -R www-data:www-data storage
sudo chown -R www-data:www-data bootstrap/cache

# Create storage subdirectories if they don't exist
sudo mkdir -p storage/framework/cache
sudo mkdir -p storage/framework/sessions
sudo mkdir -p storage/framework/views
sudo mkdir -p storage/logs

# Set permissions on storage subdirectories
sudo chmod -R 775 storage/framework
sudo chmod -R 775 storage/logs
sudo chown -R www-data:www-data storage/framework
sudo chown -R www-data:www-data storage/logs
```

**Verify Permissions:**
```bash
# Check storage permissions
ls -la storage/
ls -la storage/framework/
ls -la bootstrap/cache/

# Should show: drwxrwxr-x www-data www-data
```

### Step 4: Environment Configuration

```bash
# Copy environment file
cp .env.example .env

# Or create new .env file
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
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mpesa_notifications
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120

# M-Pesa Configuration (optional - users configure per account)
MPESA_ENVIRONMENT=sandbox
# For production, set to: production

# Talksasa SMS (optional - users configure per account)
# Users configure their own API tokens in Settings

# Mail Configuration (if needed)
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

**Generate Application Key:**
```bash
php artisan key:generate
```

### Step 5: Database Setup

```bash
# Run migrations
php artisan migrate --force

# If you get permission errors, run as web server user:
sudo -u www-data php artisan migrate --force

# Create cache and session tables (if using database driver)
php artisan cache:table
php artisan session:table
php artisan migrate --force
```

### Step 6: Create Storage Link

```bash
# Create symbolic link for public storage
php artisan storage:link

# If permission error:
sudo -u www-data php artisan storage:link
```

### Step 7: Optimize for Production

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Clear all caches (if needed)
php artisan optimize:clear
```

### Step 8: Queue Worker Setup (CRITICAL)

SMS sending requires a queue worker running. Use Supervisor:

**Install Supervisor:**
```bash
sudo apt-get install supervisor
```

**Create Supervisor Config:**
```bash
sudo nano /etc/supervisor/conf.d/mpesa-notifications-worker.conf
```

**Supervisor Configuration:**
```ini
[program:mpesa-notifications-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/mpesa-notifications/artisan queue:work database --queue=sms,default --sleep=3 --tries=3 --max-time=3600 --timeout=60
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/mpesa-notifications/storage/logs/worker.log
stopwaitsecs=3600
```

**Start Supervisor:**
```bash
# Reload supervisor config
sudo supervisorctl reread
sudo supervisorctl update

# Start workers
sudo supervisorctl start mpesa-notifications-worker:*

# Check status
sudo supervisorctl status

# View logs
tail -f /var/www/mpesa-notifications/storage/logs/worker.log
```

### Step 9: Web Server Configuration

#### Nginx Configuration

```bash
sudo nano /etc/nginx/sites-available/mpesa-notifications
```

```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com www.your-domain.com;
    root /var/www/mpesa-notifications/public;

    index index.php index.html;

    charset utf-8;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Logging
    access_log /var/log/nginx/mpesa-notifications-access.log;
    error_log /var/log/nginx/mpesa-notifications-error.log;

    # Main location block
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM configuration
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;  # Adjust version if needed
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 300;
    }

    # Deny access to hidden files
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Deny access to sensitive files
    location ~ /\.(env|git) {
        deny all;
    }

    # Increase upload size for webhooks
    client_max_body_size 10M;
}
```

**Enable Site:**
```bash
sudo ln -s /etc/nginx/sites-available/mpesa-notifications /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

#### Apache Configuration

```bash
sudo nano /etc/apache2/sites-available/mpesa-notifications.conf
```

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    ServerAlias www.your-domain.com
    Redirect permanent / https://your-domain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName your-domain.com
    ServerAlias www.your-domain.com
    DocumentRoot /var/www/mpesa-notifications/public

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/your-domain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/your-domain.com/privkey.pem

    <Directory /var/www/mpesa-notifications/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/mpesa-notifications-error.log
    CustomLog ${APACHE_LOG_DIR}/mpesa-notifications-access.log combined
</VirtualHost>
```

**Enable Site:**
```bash
sudo a2ensite mpesa-notifications
sudo a2enmod rewrite ssl
sudo systemctl reload apache2
```

### Step 10: Scheduled Tasks (Cron)

```bash
sudo crontab -e -u www-data
```

Add this line:
```cron
* * * * * cd /var/www/mpesa-notifications && php artisan schedule:run >> /dev/null 2>&1
```

## 🔐 Common Permission Issues & Fixes

### Issue 1: "The stream or file could not be opened"
**Error:** `Unable to write in the "storage/logs" directory`

**Fix:**
```bash
sudo chmod -R 775 storage/logs
sudo chown -R www-data:www-data storage/logs
```

### Issue 2: "Cache path not found"
**Error:** `Please provide a valid cache path`

**Fix:**
```bash
sudo mkdir -p storage/framework/cache
sudo mkdir -p storage/framework/sessions
sudo mkdir -p storage/framework/views
sudo chmod -R 775 storage/framework
sudo chown -R www-data:www-data storage/framework
```

### Issue 3: "Session table not found"
**Error:** `Base table or view not found: sessions`

**Fix:**
```bash
php artisan session:table
php artisan migrate --force
```

### Issue 4: "Cache table not found"
**Error:** `Base table or view not found: cache`

**Fix:**
```bash
php artisan cache:table
php artisan migrate --force
```

### Issue 5: "Permission denied" when running artisan
**Error:** `Permission denied` or `Access denied`

**Fix:**
```bash
# Run as web server user
sudo -u www-data php artisan <command>

# Or fix ownership
sudo chown -R www-data:www-data .
```

### Issue 6: Queue worker not processing jobs
**Error:** Jobs stuck in queue

**Fix:**
```bash
# Check supervisor status
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart mpesa-notifications-worker:*

# Check logs
tail -f storage/logs/worker.log
tail -f storage/logs/laravel.log
```

## 🔍 Verification Steps

### 1. Check Permissions
```bash
# Storage directory
ls -la storage/
# Should show: drwxrwxr-x www-data www-data

# Bootstrap cache
ls -la bootstrap/cache/
# Should show: drwxrwxr-x www-data www-data

# Logs directory
ls -la storage/logs/
# Should show: drwxrwxr-x www-data www-data
```

### 2. Test Application
```bash
# Test artisan commands
php artisan --version
php artisan route:list

# Test database connection
php artisan migrate:status

# Test queue
php artisan queue:work --once
```

### 3. Check Web Server
```bash
# Test Nginx config
sudo nginx -t

# Test Apache config
sudo apache2ctl configtest

# Check web server status
sudo systemctl status nginx
# or
sudo systemctl status apache2
```

### 4. Test Webhook Endpoint
```bash
# Test webhook endpoint (should return JSON)
curl -X POST https://your-domain.com/api/webhooks/mpesa/payment \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"test": "data"}'
```

## 📝 Post-Deployment Tasks

### 1. Configure M-Pesa Webhooks
- Log into M-Pesa Developer Portal
- Set Confirmation URL: `https://your-domain.com/api/webhooks/mpesa/payment`
- Set Validation URL: `https://your-domain.com/api/webhooks/mpesa/payment`

### 2. Create Admin User
```bash
php artisan tinker
```
```php
$user = \App\Models\User::create([
    'name' => 'Admin User',
    'email' => 'admin@example.com',
    'password' => \Illuminate\Support\Facades\Hash::make('secure_password'),
    'role' => 'admin',
    'is_active' => true,
]);
```

### 3. Monitor Logs
```bash
# Application logs
tail -f storage/logs/laravel.log

# Queue worker logs
tail -f storage/logs/worker.log

# Web server logs
tail -f /var/log/nginx/mpesa-notifications-error.log
```

### 4. Set Up Monitoring
- Monitor queue worker status
- Monitor failed jobs: `php artisan queue:failed`
- Monitor disk space
- Monitor database connections

## 🛠️ Troubleshooting

### Debug Mode (Temporary)
If you need to debug issues, temporarily enable debug mode:

```bash
# Edit .env
APP_DEBUG=true
APP_ENV=local

# Clear config cache
php artisan config:clear
```

**⚠️ Remember to disable debug mode in production!**

### Check PHP Errors
```bash
# Check PHP-FPM error log
sudo tail -f /var/log/php8.2-fpm.log

# Check PHP errors in web server log
sudo tail -f /var/log/nginx/mpesa-notifications-error.log
```

### Database Connection Issues
```bash
# Test database connection
php artisan db:show

# Check database credentials
php artisan tinker
>>> DB::connection()->getPdo();
```

### Queue Issues
```bash
# Check queue status
php artisan queue:monitor

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

## 🔄 Update Deployment

When updating the application:

```bash
cd /var/www/mpesa-notifications

# Pull latest changes
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

# Restart queue workers
sudo supervisorctl restart mpesa-notifications-worker:*

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

## 📚 Additional Resources

- See `docs/` directory for detailed documentation
- Check Laravel documentation: https://laravel.com/docs
- M-Pesa API docs: https://developer.safaricom.co.ke

## 🆘 Support

If you encounter issues:
1. Check logs: `storage/logs/laravel.log`
2. Verify permissions (see Common Issues above)
3. Check queue worker status
4. Verify database connection
5. Check web server configuration
