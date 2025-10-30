# WebDaddy Empire Template Marketplace

A PHP/PostgreSQL web application for selling website templates with bundled domains and affiliate tracking.

## ⚡ SUPER EASY SETUP (3 Steps!)

### Step 1: Install Docker Desktop
Download and install from: https://www.docker.com/products/docker-desktop

### Step 2: Build the Application (One-Time, Requires Internet)
Double-click `build.bat` - this downloads and builds the Docker images:
```bash
build.bat
```
**Note:** You only need to do this once, or when you update the Dockerfile.

### Step 3: Start the Application (Works Offline!)
Double-click `start.bat` to start the containers:
```bash
start.bat
```

### Step 4: Open Your Browser
Go to: **http://localhost:8080**

**That's it! You're done!** 🎉

### 🔄 Daily Usage
- **Start**: Run `start.bat` (works offline after initial build)
- **Stop**: Run `stop.bat`
- **Restart**: Run `restart.bat`
- **Rebuild** (only if Dockerfile changes): Run `build.bat` (requires internet)

---

## 📝 Configuration (All Hardcoded - No Environment Variables!)

All settings are in **`includes/config.php`** - just open and edit!

```php
// Database (already configured for Docker)
define('DB_HOST', 'db');             // Use 'localhost' for local install
define('DB_PASS', 'postgres');       // Change if needed

// Your WhatsApp number
define('WHATSAPP_NUMBER', '+2348012345678');

// Your site URL
define('SITE_URL', 'http://localhost:8080');
```

**No `.env` files, no environment variables - just edit the values directly!**

---

## 🎯 Features

- **Template Catalog**: Browse professionally designed website templates with prices, categories, and live demos
- **Domain Assignment**: Each template includes premium domain names; users select during order
- **Order Flow**: Collect customer details and redirect to WhatsApp with a pre-filled message for payment
- **Affiliate System**: Unique referral codes, click tracking, 30% commission per sale, withdrawal requests
- **Admin Panel**: View orders, mark paid, manage templates, domains, users, and withdrawals
- **Public Frontend**: Runs from the project root (no `/public` subdirectory) with Bootstrap 5 UI

---

## 🛠️ Tech Stack

- **Backend**: PHP 8.2 + PostgreSQL 15
- **Database**: PostgreSQL with foreign keys, enums, and indexes
- **Frontend**: Bootstrap 5, Bootstrap Icons, custom CSS
- **Security**: Prepared statements (PDO), input sanitization, HTTPS-ready `.htaccess`
- **Deployment**: Docker Compose (super easy!)

---

## 📂 Quick Commands

**Start the app:**
```bash
docker-compose up -d
```

**Stop the app:**
```bash
docker-compose down
```

**View logs:**
```bash
docker-compose logs -f
```

**Reset database:**
```bash
docker-compose down -v
docker-compose up -d
```

---

## 🔐 Default Admin Login

After first run, create an admin user via SQL or use the default:
- Email: `admin@example.com`
- Password: `admin123`

**Important**: Change the admin password in production!

## Directory Structure (Root Deployed)

```
/
├── index.php          # Landing page
├── template.php       # Template detail page
├── order.php          # Order form (redirects to WhatsApp)
├── admin/             # Admin panel
│   ├── login.php
│   ├── dashboard.php
│   └── includes/
├── affiliate/         # Affiliate dashboard
├── includes/          # App core (config, DB, sessions, functions)
├── database/
│   └── schema.sql     # PostgreSQL schema (canonical)
├── assets/            # CSS, images, JS
└── .htaccess          # Security + rewrites
```

## Configuration Constants

In `includes/config.php` you can also set:

- `WHATSAPP_NUMBER`: Business WhatsApp for orders
- `SITE_NAME`: Used in headers and titles
- `AFFILIATE_COMMISSION_RATE`: Default `0.30` (30%)
- `AFFILIATE_COOKIE_DAYS`: Referral cookie lifespan

## Deploying on cPanel

- Upload all files into `public_html/`.
- Use **cPanel > PostgreSQL Databases** to create DB/user.
- Import `database/schema.sql` via phpPgAdmin.
- Edit `includes/config.php` with your PostgreSQL credentials.
- Optionally enable HTTPS and canonical redirects in `.htaccess`.

## Security Notes

- `includes/.htaccess` denies direct access to PHP includes.
- `.htaccess` blocks common sensitive file types (`.env`, `.sql`, `.md`, etc.).
- Database uses prepared statements throughout.
- Sessions use secure settings and optional custom save path.

## Admin / Affiliate Management

- **Admin**: Manage templates, domains, orders, mark payments, approve withdrawals.
- **Affiliate**: Track clicks, sales, earnings; request withdrawals.

## Templates & Domains

- Add templates and domains via the admin panel.
- Each template can have many available domains.
- When an order is marked paid, the domain is assigned to that customer.

## Troubleshooting

- **Database connection failed**: Check DB credentials in `includes/config.php` and ensure PostgreSQL is running.
- **Page not found**: Ensure your document root is the project root and `.htaccess` is present (Apache).
- **Forbidden / Access Denied**: Check permissions; `includes/.htaccess` must block direct access.
- **Sessions not persisting**: Verify `/tmp/php_sessions` is writable or adjust path in `includes/session.php`.
- **White page or fatal error**: Check that all constants in `config.php` are set; ensure `pdo_pgsql` is installed.

## License

Use, modify, and redistribute as needed. No external warranties.

---

**WebDaddy Empire** – Professional website templates with domains included.
