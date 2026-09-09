# Demo Login Credentials

## Admin Login

**Email:** `admin@talksasa.com`  
**Password:** `password`

**Access:** Full admin dashboard with system-wide statistics, user management, and all payments.

---

## Client Login

**Email:** `client@example.com`  
**Password:** `password`

**Access:** Client dashboard with tenant-scoped data (only their own payments and merchants).

---

## Creating Demo Users

If the demo users don't exist, you can create them using the seeder:

```bash
php artisan db:seed --class=DemoUserSeeder
```

Or manually via tinker:

```bash
php artisan tinker
```

```php
// Create Admin
$admin = \App\Models\User::create([
    'name' => 'Admin User',
    'email' => 'admin@talksasa.com',
    'password' => \Hash::make('password'),
    'code' => 'ADMIN001',
    'role' => 'admin',
    'is_active' => true,
    'rate_limit_per_minute' => 1000,
    'rate_limit_per_hour' => 100000,
]);

// Create Client
$client = \App\Models\User::create([
    'name' => 'Demo Client',
    'email' => 'client@example.com',
    'password' => \Hash::make('password'),
    'code' => 'CLIENT001',
    'role' => 'client',
    'is_active' => true,
    'rate_limit_per_minute' => 60,
    'rate_limit_per_hour' => 1000,
]);
```

---

## Security Note

⚠️ **Important:** These are demo credentials for development/testing only.  
**Never use these credentials in production!**

In production:
1. Create unique admin accounts
2. Use strong passwords
3. Enable two-factor authentication (if implemented)
4. Regularly rotate passwords

---

## Login URL

Navigate to: `http://localhost:8000/login`

After login:
- **Admin** → Redirected to `/admin/dashboard`
- **Client** → Redirected to `/dashboard`
