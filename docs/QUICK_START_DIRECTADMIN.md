# Quick Start: DirectAdmin Setup

## 🚀 Create Admin and Customer Accounts

### Step 1: SSH into Your Server

```bash
ssh talksasa@your-server.com
cd ~/domains/business.talksasa.com/public_html
```

### Step 2: Create Admin Account

```bash
php artisan user:create --role=admin
```

**Example:**
```bash
php artisan user:create \
  --name="System Administrator" \
  --email="admin@business.talksasa.com" \
  --password="SecureAdminPass123!" \
  --role=admin \
  --active
```

### Step 3: Create Customer Account

```bash
php artisan user:create --role=client
```

**Example:**
```bash
php artisan user:create \
  --name="John Doe" \
  --email="customer@example.com" \
  --password="SecureCustomerPass123!" \
  --role=client \
  --active
```

## 📝 Interactive Mode

If you don't provide all options, you'll be prompted:

```bash
php artisan user:create --role=admin
```

You'll be asked:
- User full name
- Email address
- Password (hidden input)
- Confirm password
- Make user active? (yes/no)

## ✅ Verify Users Created

```bash
php artisan tinker
```

```php
\App\Models\User::all(['id', 'name', 'email', 'role', 'is_active']);
exit
```

## 🔐 Login URLs

- **Admin**: `https://business.talksasa.com/login` → `/admin/dashboard`
- **Client**: `https://business.talksasa.com/login` → `/dashboard`

## 📚 Full Documentation

- **User Creation Guide**: `docs/DIRECTADMIN_CREATE_USERS.md`
- **Deployment Guide**: `docs/DIRECTADMIN_DEPLOYMENT.md`
- **General Deployment**: `docs/DEPLOYMENT.md`
