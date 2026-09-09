# STK Push Test Feature - Deployment Guide

## Issue
The `MpesaStkPushService` class is missing on the live server, causing:
```
Class "App\Services\MpesaStkPushService" does not exist
```

## Solution

### Step 1: Upload the New File
Upload the following file to your live server:
```
app/Services/MpesaStkPushService.php
```

**Path on live server:**
```
/home/talksasa/domains/business.talksasa.com/public_html/app/Services/MpesaStkPushService.php
```

### Step 2: Regenerate Autoloader
After uploading the file, regenerate Composer's autoloader:

```bash
cd ~/domains/business.talksasa.com/public_html
composer dump-autoload
```

### Step 3: Clear and Rebuild Config Cache
The logs show the system is using **sandbox** API with **production** credentials. Clear and rebuild the config cache:

```bash
cd ~/domains/business.talksasa.com/public_html
php artisan config:clear
php artisan config:cache
```

### Step 4: Verify
1. Check that the file exists:
   ```bash
   ls -la app/Services/MpesaStkPushService.php
   ```

2. Test the credentials test feature again

## Environment Issue

The logs show:
```
"base_url":"https://sandbox.safaricom.co.ke"
```

But your `.env` has:
```
MPESA_ENVIRONMENT=production
```

This indicates Laravel's config cache is stale. After running `php artisan config:clear && php artisan config:cache`, the system should use:
```
https://api.safaricom.co.ke
```

## Quick Fix Commands

Run these commands on your live server:

```bash
cd ~/domains/business.talksasa.com/public_html

# 1. Upload MpesaStkPushService.php first (via FTP/SFTP/SCP)

# 2. Regenerate autoloader
composer dump-autoload

# 3. Clear config cache
php artisan config:clear

# 4. Rebuild config cache
php artisan config:cache

# 5. Verify file exists
ls -la app/Services/MpesaStkPushService.php
```

## Files to Upload

Make sure these files are uploaded to the live server:

1. ✅ `app/Services/MpesaStkPushService.php` (NEW - required)
2. ✅ `app/Services/MpesaCredentialTestService.php` (updated)
3. ✅ `app/Http/Controllers/SettingsController.php` (updated)
4. ✅ `resources/views/settings/index.blade.php` (updated)

## After Deployment

After uploading and running the commands above, the STK Push test feature should work correctly with production credentials.
