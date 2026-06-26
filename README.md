# WiFi Hotspot Billing System

A PHP + MySQL billing system for MikroTik hotspots, with M-Pesa STK Push payments
and an admin dashboard for managing packages, vouchers, sessions, and routers.

## How it works

1. Customer connects to your WiFi → MikroTik's Hotspot redirects them to `index.php`
2. Customer picks a package and enters their M-Pesa number
3. System sends an STK Push (the "Enter PIN" prompt) via Safaricom's Daraja API
4. When Safaricom confirms payment (via callback), the system creates a hotspot user
   account directly on the MikroTik router via the RouterOS API
5. Customer is now online; their account auto-expires after the package duration
6. You manage everything (prices, packages, vouchers, sessions) from `/admin`

## Folder structure

```
/                    customer-facing pages (what MikroTik redirects to)
/admin               admin dashboard (login required)
/api                 AJAX/webhook endpoints (payment, callback, voucher redemption)
/mikrotik            RouterOS API client + hotspot management logic
/mpesa               Safaricom Daraja STK Push client
/includes            shared helpers, auth, db session
/config              database.php — YOUR DB CREDENTIALS GO HERE
/sql                 schema.sql — import this first
```

## Setup steps

### 1. Requirements
- PHP 8.0+ with `curl`, `pdo_mysql` extensions enabled
- MySQL/MariaDB
- A MikroTik router (RouterOS 6.43+) with the Hotspot feature configured
- A Safaricom Daraja account (sandbox is free, for testing)

### 2. Database
```bash
mysql -u root -p < sql/schema.sql
cp config/database.php.example config/database.php
```
Then edit `config/database.php` with your real DB host/user/password.
This file is gitignored on purpose — never commit real credentials.

### 3. Create your admin login
Visit `http://yourserver/install.php` in a browser once. It creates your first
admin account and deletes itself. Then log in at `/admin/login.php`.

### 4. Configure MikroTik
On the router:
- `IP > Services` → make sure `api` service is enabled (default port **8728**)
- `System > Users` → create a user (or use existing) with `api` and `read/write` permissions
- `IP > Hotspot > Hotspot Setup` → run the wizard if you haven't already set up
  the hotspot network, DHCP, and the default login page

Then in the admin dashboard, go to **Routers** → add your router's IP, API port,
and the username/password you just created. Click **Test Connection** to confirm.

> **Important:** this PHP app needs network access to the router's API port.
> If your server is a VPS on the internet and the router is behind NAT at the
> physical location, you'll need either a VPN between them, port forwarding,
> or — simpler for one site — run this PHP app on a small local machine
> (Raspberry Pi / mini PC) on the same LAN as the router.

### 5. Point MikroTik's hotspot login page at this app
In `IP > Hotspot > Server Profiles` → your profile → **Login** tab, or by
editing the hotspot's `login.html` template, redirect/link to:
```
http://<this-server-ip>/index.php?mac=$(mac)&ip=$(ip)
```
The simplest approach: replace the default hotspot login page's form action
with a link to your billing app's URL, since payment happens off-router.

### 6. Configure M-Pesa (Daraja)
1. Create an app at https://developer.safaricom.co.ke
2. Get your **Consumer Key**, **Consumer Secret**, sandbox **shortcode** (174379)
   and **passkey** (Safaricom publishes a test passkey for sandbox)
3. In admin **Settings**, enter these and set Environment = `sandbox`
4. The callback URL Safaricom calls is `https://yourserver/api/mpesa_callback.php`
   — **this must be a public HTTPS URL**. For local testing, use `ngrok http 80`
   and use the ngrok URL temporarily (Daraja sandbox doesn't strictly require
   registering it in advance for STK push, but production paybills do — register
   it under your app's confirmation/validation URLs in the Daraja portal)
5. Once tested, apply for a production shortcode (Paybill or Till Number) from
   Safaricom, switch Environment to `production`, and update credentials

### 7. Go live checklist
- [ ] Change the default install flow — delete `install.php` if it didn't auto-delete
- [ ] Enable HTTPS (required for M-Pesa callbacks in production)
- [ ] Set `session.cookie_secure` flag in `includes/database_session.php` once on HTTPS
- [ ] Set up a cron job to run session cleanup (mark expired sessions) every few minutes,
      e.g.: `php /path/to/cron_expire_sessions.php` — see note below
- [ ] Restrict `/admin` further if needed (e.g. IP allowlist, fail2ban)
- [ ] Back up your MySQL database regularly (it's your transaction record)

## Known gaps / things to build next
This is a strong working foundation, not a finished product. Things you'll
likely want to add as you grow:

- **Cron-based session expiry + auto-removal from MikroTik** — right now expired
  sessions are only marked in the DB when an admin loads the Sessions page or
  when the MikroTik hotspot's own `limit-uptime` kicks the user off (it does,
  natively — but for clean accounting you want a periodic sync job)
- **SMS notifications** to customers (e.g. via Africa's Talking) when their time is running low
- **Multi-router support refinement** — currently picks "the first active router"
  for new payments; once you have multiple sites you'll want to associate each
  transaction with the router whose hotspot page the customer actually loaded
- **RADIUS** instead of local hotspot users, if you scale to many routers under one
  central billing system (more setup, more flexible long-term)
- **Reporting/exports** (CSV downloads of transactions for accounting)
- **Refund/manual override flows** in admin

## Security notes
- Router API passwords are stored in plaintext in the DB in this version — consider
  encrypting them at rest if multiple people have DB access
- Always run this over HTTPS in production — M-Pesa requires it for callbacks anyway
- The default admin password rules are minimal (6+ chars) — tighten this for production
