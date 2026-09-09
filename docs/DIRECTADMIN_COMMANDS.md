# DirectAdmin: Correct Command Usage

## ❌ What NOT to Do

**DON'T copy PHP code into bash shell!** The PHP code is already in the file `app/Console/Commands/CreateUser.php`. You just need to run the artisan command.

## ✅ Correct Way to Create Users

### Step 1: Navigate to Your Project Directory

```bash
cd ~/domains/business.talksasa.com/public_html
```

### Step 2: Run the Artisan Command

**Create Admin:**
```bash
php artisan user:create --role=admin --name="Admin User" --email="admin@business.talksasa.com" --password="YourPassword123!" --active
```

**Create Customer:**
```bash
php artisan user:create --role=client --name="Customer Name" --email="customer@example.com" --password="CustomerPass123!" --active
```

### Step 3: Interactive Mode (Easier)

If you want to be prompted for details:

```bash
php artisan user:create --role=admin
```

Then answer the prompts:
- Enter user full name: `Admin User`
- Enter user email address: `admin@business.talksasa.com`
- Enter password: `********` (type password, it won't show)
- Confirm password: `********` (type again)
- Make user active? (yes/no): `yes`

## 🔍 Verify the Command Works

First, check if the command is available:

```bash
cd ~/domains/business.talksasa.com/public_html
php artisan list | grep user:create
```

You should see:
```
  user:create               Create a new admin or client user account
```

## 📝 Complete Example Session

```bash
# SSH into server
ssh talksasa@your-server.com

# Navigate to project
cd ~/domains/business.talksasa.com/public_html

# Create admin account
php artisan user:create --role=admin --name="System Admin" --email="admin@business.talksasa.com" --password="SecurePass123!" --active

# Create customer account  
php artisan user:create --role=client --name="John Doe" --email="john@example.com" --password="CustomerPass123!" --active

# Verify users were created
php artisan tinker
```

Then in tinker:
```php
\App\Models\User::all(['id', 'name', 'email', 'role']);
exit
```

## 🚨 Common Mistakes

### ❌ Mistake 1: Copying PHP code to bash
```bash
# DON'T DO THIS!
$password = $this->option('password');
```

### ✅ Correct: Use artisan command
```bash
php artisan user:create --password="YourPass123!"
```

### ❌ Mistake 2: Running PHP code directly
```bash
# DON'T DO THIS!
php -r "$user = User::create([...]);"
```

### ✅ Correct: Use artisan command
```bash
php artisan user:create --role=admin
```

## 🛠️ Alternative: Using Tinker (If Command Doesn't Work)

If for some reason the artisan command doesn't work, you can use tinker:

```bash
cd ~/domains/business.talksasa.com/public_html
php artisan tinker
```

Then paste this code (this is the ONLY time you paste PHP code):

```php
// Create Admin
$admin = \App\Models\User::create([
    'name' => 'Admin User',
    'email' => 'admin@business.talksasa.com',
    'password' => \Illuminate\Support\Facades\Hash::make('YourPassword123!'),
    'code' => 'ADMIN001',
    'role' => 'admin',
    'is_active' => true,
    'rate_limit_per_minute' => 1000,
    'rate_limit_per_hour' => 100000,
    'sms_enabled' => true,
]);
echo "Admin created: {$admin->email}\n";

// Create Customer
$client = \App\Models\User::create([
    'name' => 'Customer Name',
    'email' => 'customer@example.com',
    'password' => \Illuminate\Support\Facades\Hash::make('CustomerPass123!'),
    'code' => 'CLIENT001',
    'role' => 'client',
    'is_active' => true,
    'rate_limit_per_minute' => 60,
    'rate_limit_per_hour' => 1000,
    'sms_enabled' => true,
]);
echo "Client created: {$client->email}\n";

exit
```

## ✅ Quick Reference

**Always use:**
```bash
php artisan user:create [options]
```

**Never paste PHP code directly into bash shell!**
