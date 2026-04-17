-- ============================================================
-- HOTEL/RESTAURANT BILLING SYSTEM - DATABASE SCHEMA
-- ============================================================

CREATE DATABASE IF NOT EXISTS restaurant_billing;
USE restaurant_billing;

-- Users / Staff
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','manager','waiter','delivery') DEFAULT 'waiter',
    phone VARCHAR(20),
    email VARCHAR(100),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Restaurant Tables
CREATE TABLE tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_number VARCHAR(20) NOT NULL,
    capacity INT DEFAULT 4,
    floor VARCHAR(50) DEFAULT 'Ground',
    status ENUM('available','occupied','reserved','cleaning') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Menu Categories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    status ENUM('active','inactive') DEFAULT 'active'
);

-- Menu Items
CREATE TABLE menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    tax_percent DECIMAL(5,2) DEFAULT 5.00,
    image VARCHAR(255),
    is_veg TINYINT(1) DEFAULT 1,
    available TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Customers (for delivery)
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    address TEXT,
    city VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Orders
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(30) UNIQUE NOT NULL,
    order_type ENUM('dine_in','delivery','takeaway') DEFAULT 'dine_in',
    table_id INT NULL,
    customer_id INT NULL,
    waiter_id INT NULL,
    status ENUM('pending','confirmed','preparing','ready','served','cancelled') DEFAULT 'pending',
    special_instructions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (table_id) REFERENCES tables(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (waiter_id) REFERENCES users(id)
);

-- Order Items
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    notes TEXT,
    status ENUM('pending','preparing','ready','served') DEFAULT 'pending',
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
);

-- Bills
CREATE TABLE bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_number VARCHAR(30) UNIQUE NOT NULL,
    order_id INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    discount_type ENUM('percent','fixed') DEFAULT 'fixed',
    discount_value DECIMAL(10,2) DEFAULT 0,
    service_charge DECIMAL(10,2) DEFAULT 0,
    delivery_charge DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash','card','upi','wallet','credit') DEFAULT 'cash',
    payment_status ENUM('pending','paid','partial','refunded') DEFAULT 'pending',
    paid_amount DECIMAL(10,2) DEFAULT 0,
    change_amount DECIMAL(10,2) DEFAULT 0,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Delivery Assignments
CREATE TABLE deliveries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    delivery_person_id INT,
    delivery_address TEXT,
    estimated_time INT DEFAULT 30,
    status ENUM('pending','assigned','picked','delivered','failed') DEFAULT 'pending',
    assigned_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (delivery_person_id) REFERENCES users(id)
);

-- Settings
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default Admin
INSERT INTO users (name, username, password, role) VALUES
('Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Manager John', 'manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager'),
('Waiter Ali', 'waiter1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'waiter'),
('Rider Ravi', 'rider1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'delivery');
-- Default password for all: "password"

-- Tables
INSERT INTO tables (table_number, capacity, floor) VALUES
('T1', 2, 'Ground'), ('T2', 4, 'Ground'), ('T3', 4, 'Ground'),
('T4', 6, 'Ground'), ('T5', 6, 'Ground'), ('T6', 8, 'Ground'),
('T7', 2, 'First'), ('T8', 4, 'First'), ('T9', 4, 'First'),
('T10', 6, 'First'), ('T11', 8, 'First'), ('T12', 10, 'First');

-- Categories
INSERT INTO categories (name, description, icon) VALUES
('Starters', 'Appetizers and snacks', 'fa-utensils'),
('Main Course', 'Full meals and entrees', 'fa-bowl-food'),
('Breads', 'Naan, roti, paratha etc.', 'fa-bread-slice'),
('Rice & Biryani', 'Rice dishes', 'fa-bowl-rice'),
('Desserts', 'Sweet dishes', 'fa-ice-cream'),
('Beverages', 'Drinks and juices', 'fa-mug-hot'),
('Soups', 'Hot and cold soups', 'fa-mug-saucer');

-- Menu Items
INSERT INTO menu_items (category_id, name, price, tax_percent, is_veg) VALUES
(1, 'Paneer Tikka', 220.00, 5, 1),
(1, 'Chicken 65', 260.00, 5, 0),
(1, 'Veg Spring Roll', 160.00, 5, 1),
(1, 'Fish Fry', 300.00, 5, 0),
(2, 'Paneer Butter Masala', 280.00, 5, 1),
(2, 'Butter Chicken', 320.00, 5, 0),
(2, 'Dal Makhani', 220.00, 5, 1),
(2, 'Mutton Rogan Josh', 380.00, 5, 0),
(3, 'Butter Naan', 40.00, 5, 1),
(3, 'Garlic Naan', 50.00, 5, 1),
(3, 'Tandoori Roti', 30.00, 5, 1),
(4, 'Veg Biryani', 220.00, 5, 1),
(4, 'Chicken Biryani', 280.00, 5, 0),
(4, 'Mutton Biryani', 340.00, 5, 0),
(5, 'Gulab Jamun', 80.00, 5, 1),
(5, 'Ice Cream (2 scoops)', 100.00, 5, 1),
(6, 'Mango Lassi', 80.00, 5, 1),
(6, 'Masala Chai', 40.00, 5, 1),
(6, 'Cold Coffee', 90.00, 5, 1),
(7, 'Tomato Soup', 120.00, 5, 1),
(7, 'Sweet Corn Soup', 130.00, 5, 1);

-- Settings
INSERT INTO settings (setting_key, setting_value) VALUES
('restaurant_name', 'Grand Spice Restaurant'),
('restaurant_address', '123 Main Street, City - 400001'),
('restaurant_phone', '+91 98765 43210'),
('restaurant_email', 'info@grandspice.com'),
('gst_number', '27AAPFU0939F1ZV'),
('currency_symbol', '₹'),
('service_charge_percent', '5'),
('delivery_charge', '50'),
('tax_percent', '5'),
('receipt_footer', 'Thank you for dining with us! Visit again.');
