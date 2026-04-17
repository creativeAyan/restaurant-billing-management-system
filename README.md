# 🍽️ Hotel / Restaurant Billing System
**Full-featured PHP + MySQL billing system** with Dine-In, Delivery, Takeaway, Print Receipts, and Reports.

---

## 📋 FEATURES

| Module | Features |
|---|---|
| 🔐 **Authentication** | Login, roles: Admin / Manager / Waiter / Delivery |
| 📊 **Dashboard** | Live table map, recent orders, quick stats |
| 🍽️ **Dine-In Orders** | Table selection, menu picker, order management |
| 🛵 **Delivery Orders** | Customer details, rider assignment, status tracking |
| 🥡 **Takeaway Orders** | Customer details, quick billing |
| 💳 **Billing** | Tax, discount, service charge, multi-payment methods |
| 🖨️ **Receipts** | Thermal-style print receipt (80mm) |
| 🪑 **Table Management** | Visual floor map, status control |
| 📋 **Menu Management** | Add/edit items, toggle availability, categories |
| 📈 **Sales Reports** | Charts, date range, top items, payment methods |
| 🗓️ **Daily Report** | Printable daily summary |
| ⚙️ **Settings** | Restaurant name, GST, tax rates, delivery charges |

---

## 🚀 INSTALLATION

### Requirements
- PHP 7.4+ (PHP 8.x recommended)
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx with mod_rewrite
- XAMPP / WAMP / LAMP recommended for local setup

### Step 1: Copy Files
```bash
# Copy project to your web server root
# XAMPP: C:/xampp/htdocs/restaurant_billing/
# Linux:  /var/www/html/restaurant_billing/
```

### Step 2: Create Database
1. Open **phpMyAdmin** (http://localhost/phpmyadmin)
2. Create new database: `restaurant_billing`
3. Import `database.sql` — this creates all tables and seed data

### Step 3: Configure Database
Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // your MySQL username
define('DB_PASS', '');           // your MySQL password
define('DB_NAME', 'restaurant_billing');
define('BASE_URL', 'http://localhost/restaurant_billing/');
```

### Step 4: Login
Open: http://localhost/restaurant_billing/

| Username | Password | Role |
|---|---|---|
| admin | password | Administrator |
| manager | password | Manager |
| waiter1 | password | Waiter |
| rider1 | password | Delivery |

---

## 📁 FILE STRUCTURE

```
restaurant_billing/
├── index.php               ← Redirect to login/dashboard
├── login.php               ← Login page
├── logout.php              ← Logout
├── dashboard.php           ← Main dashboard
├── settings.php            ← System settings
├── database.sql            ← Database schema + seed data
│
├── includes/
│   ├── config.php          ← DB config + helper functions
│   ├── header.php          ← Sidebar + topbar
│   └── footer.php          ← Closing tags + JS
│
├── assets/
│   ├── css/style.css       ← Main stylesheet
│   └── js/app.js           ← JavaScript
│
└── modules/
    ├── orders/
    │   ├── new_order.php   ← Create dine-in/delivery/takeaway order
    │   ├── orders.php      ← All orders list
    │   └── view_order.php  ← Order detail view
    ├── billing/
    │   ├── create_bill.php ← Payment & billing screen
    │   ├── bills.php       ← All bills list
    │   └── receipt.php     ← Printable receipt (thermal style)
    ├── tables/
    │   ├── tables.php      ← Visual table map with status control
    │   └── manage_tables.php ← Add/delete tables
    ├── menu/
    │   ├── menu.php        ← Menu items management
    │   └── categories.php  ← Menu categories
    ├── delivery/
    │   └── delivery.php    ← Delivery tracking + rider assignment
    └── reports/
        ├── sales.php       ← Sales report with charts
        └── daily.php       ← Daily printable report
```

---

## 🖨️ PRINTING RECEIPTS
- Receipts are styled for **80mm thermal printers**
- Click **Print** on the receipt page
- In your browser print dialog: set margins to None, disable headers/footers
- For best results, set paper size to 80mm width

## 💡 USAGE FLOW

### Dine-In Order:
1. New Order → Select "Dine-In" → Choose table
2. Click menu items to add to order
3. Place Order → Billing screen appears
4. Select payment method → Confirm Payment
5. Receipt prints automatically

### Delivery Order:
1. New Order → Select "Delivery" → Enter customer details
2. Add items → Place Order
3. Go to Delivery page → Assign rider
4. Update: Assigned → Picked Up → Delivered
5. Generate Bill → Print Receipt

---

## 🔐 DEFAULT PASSWORDS
All test accounts use password: **`password`**
> ⚠️ Change passwords in production via MySQL:
> ```sql
> UPDATE users SET password = '$2y$10$...' WHERE username = 'admin';
> ```
> Generate hash: `echo password_hash('newpassword', PASSWORD_DEFAULT);`

---

## 🛠️ CUSTOMIZATION
- Change restaurant name, address, GST in **Settings** page
- Add your menu items in **Menu Management**
- Adjust tax rates in Settings
- Add more tables in **Manage Tables**

---

*Built with PHP, MySQL, Bootstrap 5, Chart.js, Font Awesome*
