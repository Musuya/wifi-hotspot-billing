-- ============================================
-- WiFi Hotspot Billing System - Database Schema
-- ============================================

CREATE DATABASE IF NOT EXISTS wifi_billing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wifi_billing;

-- ---------- Admin users (people who log into the dashboard) ----------
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('superadmin','staff') DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- ---------- MikroTik routers (you can support more than one site) ----------
CREATE TABLE routers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,           -- e.g. "Main Branch"
    ip_address VARCHAR(45) NOT NULL,      -- router's IP reachable by this server
    api_port INT DEFAULT 8728,
    api_username VARCHAR(50) NOT NULL,
    api_password VARCHAR(255) NOT NULL,   -- store encrypted, see includes/crypto.php
    hotspot_server VARCHAR(50) DEFAULT 'all', -- mikrotik hotspot server name
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------- Packages (the products customers buy) ----------
CREATE TABLE packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,           -- e.g. "1 Hour", "Daily Unlimited"
    description VARCHAR(255),
    price DECIMAL(10,2) NOT NULL,
    duration_minutes INT NOT NULL,        -- validity period, e.g. 60, 1440 (24h)
    data_limit_mb INT DEFAULT NULL,       -- NULL = unlimited data
    rate_limit VARCHAR(50) DEFAULT NULL,  -- mikrotik format e.g. "2M/2M" (down/up)
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------- Transactions (every payment attempt, M-Pesa or manual) ----------
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_id INT NOT NULL,
    phone_number VARCHAR(15) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    mpesa_checkout_request_id VARCHAR(100) DEFAULT NULL,
    mpesa_receipt_number VARCHAR(50) DEFAULT NULL,
    status ENUM('pending','completed','failed','cancelled') DEFAULT 'pending',
    voucher_code VARCHAR(20) DEFAULT NULL,  -- generated once payment succeeds
    router_id INT DEFAULT NULL,
    mac_address VARCHAR(17) DEFAULT NULL,   -- device that requested access
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (package_id) REFERENCES packages(id),
    FOREIGN KEY (router_id) REFERENCES routers(id)
);

-- ---------- Vouchers (pre-generated codes, e.g. for cash sales / cards) ----------
CREATE TABLE vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    package_id INT NOT NULL,
    status ENUM('unused','used','expired') DEFAULT 'unused',
    batch_label VARCHAR(50) DEFAULT NULL,  -- e.g. "Printed batch - June 2026"
    transaction_id INT DEFAULT NULL,       -- linked if bought via M-Pesa
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (package_id) REFERENCES packages(id),
    FOREIGN KEY (transaction_id) REFERENCES transactions(id)
);

-- ---------- Active hotspot sessions (mirrors what's on the router) ----------
CREATE TABLE sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voucher_id INT DEFAULT NULL,
    transaction_id INT DEFAULT NULL,
    hotspot_username VARCHAR(50) NOT NULL, -- username created on MikroTik
    hotspot_password VARCHAR(50) NOT NULL,
    router_id INT NOT NULL,
    mac_address VARCHAR(17) DEFAULT NULL,
    expires_at TIMESTAMP NOT NULL,
    status ENUM('active','expired','disconnected') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (voucher_id) REFERENCES vouchers(id),
    FOREIGN KEY (transaction_id) REFERENCES transactions(id),
    FOREIGN KEY (router_id) REFERENCES routers(id)
);

-- ---------- Settings (key-value store for system config) ----------
CREATE TABLE settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT
);

-- Seed some default packages
INSERT INTO packages (name, description, price, duration_minutes, rate_limit, sort_order) VALUES
('1 Hour', 'Quick browsing access', 10.00, 60, '2M/2M', 1),
('3 Hours', 'Extended browsing', 25.00, 180, '3M/3M', 2),
('Daily Unlimited', 'Full day access', 50.00, 1440, '5M/5M', 3),
('Weekly Unlimited', '7 days unlimited access', 250.00, 10080, '5M/5M', 4);

-- Seed default settings
INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'My WiFi Hotspot'),
('mpesa_shortcode', ''),
('mpesa_passkey', ''),
('mpesa_consumer_key', ''),
('mpesa_consumer_secret', ''),
('mpesa_env', 'sandbox'),
('currency', 'KES');

-- NOTE: The default admin account is NOT created here.
-- Run install.php once after importing this schema - it creates the
-- first admin user with a properly generated password hash, then
-- deletes itself for safety.
