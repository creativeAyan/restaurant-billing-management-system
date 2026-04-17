-- ============================================================
-- MIGRATION: Add all new feature tables
-- Run this ONCE on top of your existing database
-- ============================================================

USE restaurant_billing;

-- ── 1. KITCHEN ORDER TICKETS ─────────────────────────────────
CREATE TABLE IF NOT EXISTS kot_tickets (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    order_id     INT NOT NULL,
    ticket_number VARCHAR(30) UNIQUE NOT NULL,
    items_json   TEXT,
    printed      TINYINT(1) DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id)
);

-- ── 2. INVENTORY / STOCK ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS inventory_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(150) NOT NULL,
    unit         VARCHAR(30) DEFAULT 'kg',
    current_qty  DECIMAL(10,3) DEFAULT 0,
    min_qty      DECIMAL(10,3) DEFAULT 1,
    cost_per_unit DECIMAL(10,2) DEFAULT 0,
    category     VARCHAR(100),
    status       ENUM('active','inactive') DEFAULT 'active',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── 3. PURCHASE / EXPENSE ENTRIES ────────────────────────────
CREATE TABLE IF NOT EXISTS expenses (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    expense_date DATE NOT NULL,
    category     VARCHAR(100) NOT NULL,
    description  VARCHAR(255),
    amount       DECIMAL(10,2) NOT NULL,
    paid_to      VARCHAR(150),
    payment_mode ENUM('cash','card','upi','bank') DEFAULT 'cash',
    inventory_item_id INT NULL,
    qty_purchased DECIMAL(10,3) NULL,
    recorded_by  INT,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ── 4. TABLE RESERVATIONS ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS reservations (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    reservation_number VARCHAR(30) UNIQUE NOT NULL,
    table_id      INT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    party_size    INT DEFAULT 2,
    reserved_date DATE NOT NULL,
    reserved_time TIME NOT NULL,
    duration_mins INT DEFAULT 90,
    status        ENUM('confirmed','seated','completed','cancelled','no_show') DEFAULT 'confirmed',
    notes         TEXT,
    created_by    INT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ── 5. CUSTOMER LOYALTY ───────────────────────────────────────
ALTER TABLE customers
    ADD COLUMN IF NOT EXISTS loyalty_points INT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS total_spent DECIMAL(12,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS birthday DATE NULL,
    ADD COLUMN IF NOT EXISTS notes TEXT NULL;

CREATE TABLE IF NOT EXISTS loyalty_transactions (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    customer_id  INT NOT NULL,
    bill_id      INT NULL,
    type         ENUM('earn','redeem','adjust') DEFAULT 'earn',
    points       INT NOT NULL,
    description  VARCHAR(255),
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE SET NULL
);

-- ── 6. COUPONS / PROMO CODES ─────────────────────────────────
CREATE TABLE IF NOT EXISTS coupons (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(30) UNIQUE NOT NULL,
    description   VARCHAR(255),
    discount_type ENUM('percent','fixed') DEFAULT 'percent',
    discount_value DECIMAL(10,2) NOT NULL,
    min_order_amount DECIMAL(10,2) DEFAULT 0,
    max_uses      INT DEFAULT 0,
    used_count    INT DEFAULT 0,
    valid_from    DATE NOT NULL,
    valid_to      DATE NOT NULL,
    status        ENUM('active','inactive') DEFAULT 'active',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── 7. AUDIT LOG ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_log (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NULL,
    user_name    VARCHAR(100),
    action       VARCHAR(100) NOT NULL,
    module       VARCHAR(50),
    record_id    INT NULL,
    old_value    TEXT,
    new_value    TEXT,
    ip_address   VARCHAR(45),
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── 8. SPLIT PAYMENTS ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bill_payments (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    bill_id        INT NOT NULL,
    payment_method ENUM('cash','card','upi','wallet') NOT NULL,
    amount         DECIMAL(10,2) NOT NULL,
    reference      VARCHAR(100),
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(id)
);

-- ── 9. MENU ITEM EXTRAS ───────────────────────────────────────
ALTER TABLE menu_items
    ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS prep_time_mins INT DEFAULT 15,
    ADD COLUMN IF NOT EXISTS calories INT NULL;

-- ── 10. STAFF ATTENDANCE ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS attendance (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    work_date    DATE NOT NULL,
    clock_in     TIME NULL,
    clock_out    TIME NULL,
    status       ENUM('present','absent','half_day','leave') DEFAULT 'present',
    notes        VARCHAR(255),
    UNIQUE KEY unique_attendance (user_id, work_date),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ── 11. FEEDBACK ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS feedback (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    bill_id      INT NULL,
    order_id     INT NULL,
    customer_name VARCHAR(100),
    food_rating  INT DEFAULT 0,
    service_rating INT DEFAULT 0,
    ambiance_rating INT DEFAULT 0,
    comments     TEXT,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);

-- ── 12. EXPENSE CATEGORIES SEED ───────────────────────────────
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('loyalty_points_per_rupee', '1'),
('loyalty_rupee_per_point', '0.25'),
('kot_auto_print', '0'),
('low_stock_alert_enabled', '1'),
('upload_path', 'assets/uploads/');

-- Sample coupons
INSERT IGNORE INTO coupons (code, description, discount_type, discount_value, min_order_amount, valid_from, valid_to) VALUES
('WELCOME20', 'Welcome 20% off on first order', 'percent', 20, 200, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR)),
('FLAT50', 'Flat ₹50 off on orders above ₹500', 'fixed', 50, 500, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR));

-- Sample inventory items
INSERT IGNORE INTO inventory_items (name, unit, current_qty, min_qty, cost_per_unit, category) VALUES
('Chicken', 'kg', 15.0, 5.0, 220.00, 'Meat & Seafood'),
('Paneer', 'kg', 8.0, 2.0, 280.00, 'Dairy'),
('Basmati Rice', 'kg', 25.0, 5.0, 80.00, 'Grains'),
('Cooking Oil', 'litre', 10.0, 3.0, 120.00, 'Oils'),
('Onions', 'kg', 10.0, 2.0, 30.00, 'Vegetables'),
('Tomatoes', 'kg', 8.0, 2.0, 40.00, 'Vegetables'),
('Butter', 'kg', 3.0, 1.0, 450.00, 'Dairy'),
('Cream', 'litre', 2.0, 0.5, 180.00, 'Dairy'),
('LPG Cylinder', 'unit', 2.0, 1.0, 900.00, 'Utilities'),
('Naan Flour (Maida)', 'kg', 10.0, 3.0, 45.00, 'Grains');

-- Create upload directory entry in settings
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('menu_image_upload', '1');
