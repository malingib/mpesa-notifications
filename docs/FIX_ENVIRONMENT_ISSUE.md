# Fix Environment Still Showing Sandbox

## Problem
Even after clearing cache, the system still uses sandbox API:
```
"env_value":"sandbox"
"base_url":"https://sandbox.safaricom.co.ke"
```

## Root Cause
The `.env` file either:
1. Doesn't have `MPESA_ENVIRONMENT=production`
2. Has it commented out (`#MPESA_ENVIRONMENT=production`)
3. Has a typo or extra spaces
4. Has it set to `sandbox` explicitly

## Diagnostic Steps

### Step 1: Check .env File
```bash
cd ~/domains/business.talksasa.com/public_html

# Check if MPESA_ENVIRONMENT exists
cat .env | grep MPESA_ENVIRONMENT

# Should show: MPESA_ENVIRONMENT=production
# NOT: MPESA_ENVIRONMENT=sandbox
# NOT: #MPESA_ENVIRONMENT=production (commented out)
```

### Step 2: Verify Current Value
```bash
# Check what PHP sees
php artisan tinker
>>> env('MPESA_ENVIRONMENT')
# Should output: "production"
# If it outputs: "sandbox" or null, the .env file is wrong
```

### Step 3: Fix .env File

**Option A: If missing, add it:**
```bash
# Add to .env file
echo "MPESA_ENVIRONMENT=production" >> .env
```

**Option B: If it exists but is wrong:**
```bash
# Edit .env file
nano .env
# OR
vi .env

# Find the line:
MPESA_ENVIRONMENT=sandbox

# Change it to:
MPESA_ENVIRONMENT=production

# Save and exit (Ctrl+X, then Y, then Enter for nano)
```

**Option C: Use sed to replace:**
```bash
# Replace sandbox with production
sed -i 's/MPESA_ENVIRONMENT=sandbox/MPESA_ENVIRONMENT=production/g' .env

# Or if commented out, uncomment and set
sed -i 's/#MPESA_ENVIRONMENT=.*/MPESA_ENVIRONMENT=production/g' .env
```

### Step 4: Verify .env Change
```bash
# Check it's correct now
cat .env | grep MPESA_ENVIRONMENT
# Should show: MPESA_ENVIRONMENT=production
```

### Step 5: Clear All Caches
```bash
# Clear config cache
php artisan config:clear

# Clear all caches
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Rebuild config cache
php artisan config:cache
```

### Step 6: Verify It Works
```bash
# Check via tinker
php artisan tinker
>>> env('MPESA_ENVIRONMENT')
# Should output: "production"

>>> config('mpesa.environment')
# Should output: "production"
```

### Step 7: Test STK Push
After fixing, test STK Push again. You should see:
```
"using_environment":"production"
"base_url":"https://api.safaricom.co.ke"
```

## Common Mistakes

### ❌ Wrong:
```env
#MPESA_ENVIRONMENT=production  # Commented out
MPESA_ENVIRONMENT = production  # Spaces around =
MPESA_ENVIRONMENT=sandbox  # Wrong value
MPESA_ENV=production  # Wrong variable name
```

### ✅ Correct:
```env
MPESA_ENVIRONMENT=production
```

## Quick Fix Script

Run this on your live server:

```bash
cd ~/domains/business.talksasa.com/public_html

# 1. Check current value
echo "Current .env value:"
cat .env | grep MPESA_ENVIRONMENT

# 2. Fix if needed (replace sandbox with production)
sed -i 's/MPESA_ENVIRONMENT=sandbox/MPESA_ENVIRONMENT=production/g' .env
sed -i 's/#MPESA_ENVIRONMENT=.*/MPESA_ENVIRONMENT=production/g' .env

# 3. Verify
echo "After fix:"
cat .env | grep MPESA_ENVIRONMENT

# 4. Clear caches
php artisan config:clear
php artisan config:cache

# 5. Verify PHP sees it
php artisan tinker <<EOF
env('MPESA_ENVIRONMENT')
config('mpesa.environment')
EOF
```

## Still Not Working?

If after all this it still shows sandbox:

1. **Check file permissions:**
   ```bash
   ls -la .env
   # Should be readable by web server
   ```

2. **Check for multiple .env files:**
   ```bash
   find . -name ".env*" -type f
   # Make sure you're editing the right one
   ```

3. **Check .env file location:**
   ```bash
   pwd
   # Should be: /home/talksasa/domains/business.talksasa.com/public_html
   # .env should be in this directory
   ```

4. **Restart PHP-FPM (if using):**
   ```bash
   # On DirectAdmin, might need to restart PHP
   # Or just wait a few minutes for PHP to reload
   ```

## Expected Log Output After Fix

**Before:**
```
"env_value":"sandbox"
"base_url":"https://sandbox.safaricom.co.ke"
```

**After:**
```
"env_value":"production"
"config_value":"production"
"using_environment":"production"
"base_url":"https://api.safaricom.co.ke"
```
