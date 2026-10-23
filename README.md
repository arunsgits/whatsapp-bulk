# ZapReach — WhatsApp Cloud API & CRM Automation Platform

[![Laravel](https://img.shields.io/badge/Laravel-10.x-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php)](https://php.net)
[![Passport](https://img.shields.io/badge/Auth-Passport%20OAuth2-4F5B93?style=for-the-badge)](https://laravel.com/docs/passport)
[![WhatsApp Cloud API](https://img.shields.io/badge/Meta-WhatsApp%20Cloud%20API-25D366?style=for-the-badge&logo=whatsapp)](https://developers.facebook.com/docs/whatsapp/cloud-api)

**ZapReach** is an enterprise-grade WhatsApp Business Cloud API broadcast engine, multi-agent CRM inbox, Auto-AI automated reply assistant, product catalog showcase, contact segmentation, and subscription/wallet billing system built on Laravel 10.

---

## 🌟 Key Features

- **Official WhatsApp Cloud API Integration**: Direct Meta Cloud API integration for text, media, documents, templates, interactive button/list messages, and webhooks.
- **Smart Auto-AI Engine**: Autonomous response engine with customizable brand tone, knowledge base FAQs, and smart fallback routing.
- **Campaigns & Bulk Broadcast**: High-volume scheduled broadcasts with batch processing and delivery status tracking (`sent`, `delivered`, `read`, `failed`).
- **Multi-Agent Live Chat Inbox**: Two-way customer conversations with lead management, tags, notes, and agent assignments.
- **Product & Commerce Catalog**: Native product collections, interactive cart messaging, and order inquiry flows.
- **Subscription & Wallet Billing**: Flexible tiered packages (Starter, Growth, Enterprise), monthly/yearly renewal cycles, Razorpay payment gateway integration, GST compliance, and transaction ledgers.
- **Robust Security & Auth**: Laravel Passport OAuth2 token-based authentication with granular role-based access control (`super_admin`, `admin`, `user`).
- **Interactive API Documentation**: Comprehensive dark-mode API portal accessible at `/api-docs` with real-time test queries and copy-paste cURL snippets.

---

## 🚀 Quick Start / Local Installation

### Prerequisites

- **PHP**: `^8.2` (with `pdo`, `mbstring`, `openssl`, `curl`, `json` extensions)
- **Database**: MySQL `8.0+` or MariaDB `10.4+`
- **Composer**: `2.x+`
- **Web Server**: Apache / Nginx or built-in PHP development server

### Step 1: Clone & Enter Directory

```bash
git clone https://github.com/zapreach/zapreach-api.git
cd ZapReach
```

### Step 2: Install PHP Dependencies

```bash
composer install
```

### Step 3: Environment Setup

Create your `.env` configuration file from the template:

```bash
cp .env.example .env
php artisan key:generate
```

Configure your database connection in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=zapreach
DB_USERNAME=root
DB_PASSWORD=
```

### Step 4: Run Database Migrations & Turnkey Seeders

Run migrations and seed the initial Super Admin account, demo user, default packages, settings, and navigation modules:

```bash
php artisan migrate --seed
```

### Step 5: Install Laravel Passport Keys

Generate the OAuth2 encryption keys and personal access clients:

```bash
php artisan passport:install
```

### Step 6: Start Development Server

```bash
php artisan serve
```

The application will be live at `http://127.0.0.1:8000`.

- **API Portal**: Visit `http://127.0.0.1:8000/api-docs`
- **Health Check**: Visit `http://127.0.0.1:8000/api/health`

---

## 🔑 Default Seeded Accounts

| Role | Email | Password | Pre-loaded Wallet |
|------|-------|----------|-------------------|
| **Super Admin** | `admin@zapreach.com` | `ZapReach@2026!` | ₹1,000.00 |
| **Demo User** | `demo@zapreach.com` | `ZapReach@2026!` | ₹250.00 |

---

## 📦 Default Subscription Packages

| Package | Messages / mo | Templates | Contacts | Price | Popular |
|---------|---------------|-----------|----------|-------|---------|
| **Starter** | 1,000 | 10 | 500 | ₹999 / mo | No |
| **Growth** | 5,000 | 30 | 3,000 | ₹2,499 / mo | ⭐ Yes |
| **Enterprise** | 25,000 | 100 | 15,000 | ₹6,999 / mo | No |

---

## 📡 Core API Reference

All protected API endpoints require an `Authorization` header containing the Bearer token acquired from `/api/auth/login`:

```http
Authorization: Bearer <your_access_token>
Accept: application/json
```

### System & Health

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| `GET` | `/api/health` | System health check (DB, cache, server metrics) | No |
| `GET` | `/api` | API heartbeat & service name | No |
| `GET` | `/api/is-app-live` | Platform maintenance & live state status | No |

### Authentication & Account

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| `POST` | `/api/auth/register` | Register a new business account | No |
| `POST` | `/api/auth/login` | Authenticate and obtain OAuth2 access token | No |
| `GET` | `/api/auth/profile` | Retrieve authenticated user profile | Yes |
| `PUT` | `/api/auth/profile` | Update profile information & company details | Yes |
| `PUT` | `/api/auth/change-password` | Update account password | Yes |
| `POST` | `/api/auth/logout` | Revoke current access token | Yes |

### WhatsApp Cloud & Messaging

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| `POST` | `/whatsapp/webhook` | Meta WhatsApp Cloud API incoming webhook | No (HMAC signed) |
| `POST` | `/whatsapp/webhook/{userId}` | User-specific Meta WhatsApp webhook endpoint | No (HMAC signed) |
| `GET` | `/api/inbox/conversations` | List active WhatsApp conversation threads | Yes |
| `POST` | `/api/bulk-messages/send` | Dispatch bulk broadcast campaign | Yes |
| `GET` | `/api/reports` | Campaign analytics, delivery & read statistics | Yes |

### Contacts & Segmentation

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| `GET` | `/api/contacts` | Paginated list of contacts with filter tags | Yes |
| `POST` | `/api/contacts` | Create a new contact | Yes |
| `PUT` | `/api/contacts/{id}` | Update existing contact details | Yes |
| `DELETE` | `/api/contacts/{id}` | Delete contact | Yes |
| `GET` | `/api/groups` | List contact audience groups | Yes |

### Packages & Wallet Billing

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| `GET` | `/api/package/get` | Browse available subscription packages | Yes |
| `POST` | `/api/plan/create` | Activate subscription package | Yes |
| `GET` | `/api/wallet/balance` | Fetch current wallet balance & ledger summary | Yes |
| `POST` | `/api/wallet/recharge` | Initiate Razorpay wallet recharge order | Yes |
| `POST` | `/payment/callback` | Razorpay payment webhook callback | No |

---

## 🛠️ Configuration & Webhook Forwarding

ZapReach supports optional webhook forwarding for distributed microservices or custom notification listeners. Configure in `.env`:

```env
# Optional external webhook forwarding (leave disabled if not needed)
FORWARD_WEBHOOK_ENABLED=false
FORWARD_WEBHOOK_URL=
```

---

## 🔒 Security Best Practices

1. **Keep Secrets Safe**: Never commit `.env` or production credentials to version control.
2. **Passport Keys**: Ensure `oauth-private.key` and `oauth-public.key` in `storage/` have restrictive permissions (`chmod 600`).
3. **Webhook Verification**: Configure a strong `WHATSAPP_WEBHOOK_VERIFY_TOKEN` in `.env` and match it in Meta's Developer App Dashboard.

---

## 📄 License & Copyright

Copyright © 2026 **ZapReach Technologies**. All rights reserved.  
This software is proprietary and confidential. Unauthorized copying, distribution, or modification is strictly prohibited.
