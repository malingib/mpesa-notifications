# Fix STK Push Syntax Error

## Error
```
ParseError: syntax error, unexpected token "*", expecting end of file
app/Services/MpesaStkPushService.php :250
```

## Cause
The file on the live server has duplicate content - the class definition appears twice. This happens when a file is incorrectly uploaded or merged.

## Solution

### Step 1: Delete the corrupted file on live server
```bash
cd ~/domains/business.talksasa.com/public_html
rm app/Services/MpesaStkPushService.php
```

### Step 2: Upload the clean file
Upload the clean `app/Services/MpesaStkPushService.php` file from your local machine to:
```
/home/talksasa/domains/business.talksasa.com/public_html/app/Services/MpesaStkPushService.php
```

### Step 3: Verify file integrity
```bash
cd ~/domains/business.talksasa.com/public_html

# Check file exists
ls -la app/Services/MpesaStkPushService.php

# Check syntax
php -l app/Services/MpesaStkPushService.php

# Should output: "No syntax errors detected"
```

### Step 4: Regenerate autoloader
```bash
composer dump-autoload
```

### Step 5: Clear caches
```bash
php artisan config:clear
php artisan config:cache
php artisan view:clear
```

## File Verification

The correct file should:
- Have exactly **239 lines** (or 240 with empty last line)
- End with: `}`
- Start with: `<?php`
- Contain only ONE class definition: `class MpesaStkPushService`

## Quick Fix Commands

```bash
cd ~/domains/business.talksasa.com/public_html

# 1. Remove corrupted file
rm app/Services/MpesaStkPushService.php

# 2. Upload clean file via FTP/SFTP/SCP

# 3. Verify syntax
php -l app/Services/MpesaStkPushService.php

# 4. Regenerate autoloader
composer dump-autoload

# 5. Clear caches
php artisan config:clear && php artisan config:cache
```

## Expected File Structure

The file should end like this:
```php
        } catch (\Exception $e) {
            Log::error('M-Pesa OAuth error in STK Push', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Authentication failed: ' . $e->getMessage(),
            ];
        }
    }
}
```

**NOT** like this (which causes the error):
```php
    }
}
 
 * M-Pesa STK Push Service
 * 
 * Initiates STK Push (Lipa na M-Pesa Online) payments
 */
class MpesaStkPushService
{
    // ... duplicate content
```
