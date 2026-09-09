# Creating Admin and Customer Accounts on DirectAdmin

This guide shows you how to create admin and customer accounts for your M-Pesa Notifications system deployed on DirectAdmin.

## 🚀 Quick Method: Using Artisan Command

The easiest way to create users is using the built-in artisan command.

### Create an Admin Account

```bash
cd ~/domains/your-domain.com/public_html

# Interactive mode (will prompt for details)
php artisan user:create --role=admin

# Or provide all details at once
php artisan user:create \
  --name="Admin User" \
  --email="admin@your-domain.com" \
  --password="YourSecurePassword123!" \
  --role=admin \
  --active
```

### Create a Customer/Client Account

```bash
cd ~/domains/your-domain.com/public_html

# Interactive mode
php artisan user:create --role=client

# Or provide all details
php artisan user:create \
  --name="John Doe" \
  --email="customer@example.com" \
  --password="CustomerPassword123!" \
  --role=client \
  --active
```

## 📝 Step-by-Step: Interactive Creation

### Example: Creating Admin Account

```bash
cd ~/domains/your-domain.com/public_html
php artisan user:create --role=admin
```

You'll be prompted:
```
Enter user full name: Admin User
Enter user email address: admin@your-domain.com
Enter password (min 8 characters): ********
Confirm password: ********
Make user active? (yes/no) [yes]: yes
```

Output:
```
✅ User created successfully!

┌─────────────────────────┬──────────────────────┐
│ Field                   │ Value                │
├─────────────────────────┼──────────────────────┤
│ ID                      │ 1                    │
│ Name                    │ Admin User           │
│ Email                   │ admin@your-domain.com│
│ Code                    │ A1B2C3D              │
│ Role                    │ ADMIN                │
│ Status                  │ Active               │
│ Rate Limit (per minute) │ 1000                 │
│ Rate Limit (per hour)   │ 100000               │
└─────────────────────────┴──────────────────────┘

⚠️  Password: YourSecurePassword123!
⚠️  Please save these credentials securely!
```

## 🔧 Alternative Method: Using Database Seeder

If you want to create demo accounts quickly:

```bash
cd ~/domains/your-domain.com/public_html
php artisan db:seed --class=DemoUserSeeder
```

This creates:
- **Admin**: `admin@talksasa.com` / `password`
- **Client**: `client@example.com` / `password`

⚠️ **Warning**: These are demo credentials. Change passwords immediately in production!

## 🛠️ Manual Creation: Using Tinker

If you prefer manual control:

```bash
cd ~/domains/your-domain.com/public_html
php artisan tinker
```

### Create Admin User

```php
$admin = \App\Models\User::create([
    'name' => 'Admin User',
    'email' => 'admin@your-domain.com',
    'password' => \Illuminate\Support\Facades\Hash::make('YourSecurePassword123!'),
    'code' => 'ADMIN001',
    'role' => 'admin',
    'is_active' => true,
    'rate_limit_per_minute' => 1000,
    'rate_limit_per_hour' => 100000,
    'sms_enabled' => true,
]);

echo "Admin created: ID {$admin->id}, Email: {$admin->email}\n";
```

### Create Client User

```php
$client = \App\Models\User::create([
    'name' => 'John Doe',
    'email' => 'customer@example.com',
    'password' => \Illuminate\Support\Facades\Hash::make('CustomerPassword123!'),
    'code' => 'CLIENT001',
    'role' => 'client',
    'is_active' => true,
    'rate_limit_per_minute' => 60,
    'rate_limit_per_hour' => 1000,
    'sms_enabled' => true,
]);

echo "Client created: ID {$client->id}, Email: {$client->email}\n";
```

Exit tinker:
```php
exit
```

## 📋 User Fields Explained

| Field | Required | Description | Example |
|-------|----------|-------------|---------|
| `name` | Yes | User's full name | "John Doe" |
| `email` | Yes | Unique email address | "user@example.com" |
| `password` | Yes | Hashed password (min 8 chars) | "SecurePass123!" |
| `code` | Yes | Unique tenant identifier | "CLIENT001" |
| `role` | Yes | Either "admin" or "client" | "admin" |
| `is_active` | No | Account active status (default: true) | true |
| `rate_limit_per_minute` | No | API rate limit per minute | 60 |
| `rate_limit_per_hour` | No | API rate limit per hour | 1000 |
| `sms_enabled` | No | Enable SMS features (default: true) | true |

## 🔐 Default Rate Limits

**Admin Users:**
- Per minute: 1000 requests
- Per hour: 100,000 requests

**Client Users:**
- Per minute: 60 requests
- Per hour: 1,000 requests

## ✅ Verification

After creating users, verify they exist:

```bash
php artisan tinker
```

```php
// List all users
\App\Models\User::all(['id', 'name', 'email', 'role', 'is_active']);

// Find specific user
$user = \App\Models\User::where('email', 'admin@your-domain.com')->first();
echo "User: {$user->name}, Role: {$user->role}, Active: " . ($user->is_active ? 'Yes' : 'No') . "\n";

exit
```

## 🚨 Troubleshooting

### Error: "User with email already exists"

```bash
# Check existing users
php artisan tinker
\App\Models\User::all(['email', 'role']);
exit

# Use a different email or delete existing user
```

### Error: "Invalid role"

Roles must be exactly:
- `admin` (lowercase)
- `client` (lowercase)

### Error: "Password must be at least 8 characters"

Ensure password is at least 8 characters long.

### Error: "User with code already exists"

The code must be unique. Either:
- Use a different code with `--code=NEWCODE`
- Or let the system auto-generate one (omit `--code` option)

## 🔄 Updating User Password

To change a user's password:

```bash
php artisan tinker
```

```php
$user = \App\Models\User::where('email', 'user@example.com')->first();
$user->password = \Illuminate\Support\Facades\Hash::make('NewPassword123!');
$user->save();
echo "Password updated for: {$user->email}\n";
exit
```

## 🔄 Activating/Deactivating Users

```bash
php artisan tinker
```

```php
// Activate user
$user = \App\Models\User::where('email', 'user@example.com')->first();
$user->is_active = true;
$user->save();

// Deactivate user
$user->is_active = false;
$user->save();

exit
```

## 📝 Example: Complete Setup Script

Create a file `create-users.sh`:

```bash
#!/bin/bash

cd ~/domains/your-domain.com/public_html

echo "Creating Admin Account..."
php artisan user:create \
  --name="System Administrator" \
  --email="admin@your-domain.com" \
  --password="AdminSecurePass123!" \
  --role=admin \
  --active

echo ""
echo "Creating Customer Account..."
php artisan user:create \
  --name="Demo Customer" \
  --email="customer@example.com" \
  --password="CustomerPass123!" \
  --role=client \
  --active

echo ""
echo "✅ Users created successfully!"
```

Make it executable and run:
```bash
chmod +x create-users.sh
./create-users.sh
```

## 🔐 Security Best Practices

1. **Use Strong Passwords**: Minimum 12 characters with mix of uppercase, lowercase, numbers, and symbols
2. **Change Default Passwords**: Never use "password" or "123456"
3. **Limit Admin Accounts**: Only create admin accounts for trusted personnel
4. **Regular Audits**: Review user accounts periodically
5. **Enable 2FA**: If two-factor authentication is implemented, enable it for admin accounts

## 📞 Login URLs

After creating accounts, users can login at:

- **Admin**: `https://your-domain.com/login` → Redirects to `/admin/dashboard`
- **Client**: `https://your-domain.com/login` → Redirects to `/dashboard`

## 🎯 Quick Reference

**Create Admin:**
```bash
php artisan user:create --role=admin --name="Admin Name" --email="admin@domain.com" --password="Pass123!" --active
```

**Create Client:**
```bash
php artisan user:create --role=client --name="Client Name" --email="client@domain.com" --password="Pass123!" --active
```

**List Users:**
```bash
php artisan tinker
\App\Models\User::all(['id', 'name', 'email', 'role']);
exit
```
