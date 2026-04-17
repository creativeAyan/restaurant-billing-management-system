# 🍽️ Restaurant Billing & Management System

A web-based restaurant billing system built using PHP and MySQL to manage orders, generate bills, and handle admin operations efficiently.

---

## 🚀 Features

- Secure login system  
- Admin dashboard  
- Billing & invoice generation  
- Order management  
- Staff/admin control panel  
- Organized modular structure  

---

## 🛠️ Tech Stack

- PHP (Core PHP)
- MySQL
- HTML5, CSS3, JavaScript

---

## 📁 Project Structure

- `/modules` → Billing and admin functionalities  
- `/includes` → Configuration files (DB, header, footer)  
- `/assets` → CSS, JS, images  
- `database.sql` → Database file  

---

## ⚙️ Setup Instructions

1. Install XAMPP or WAMP  
2. Copy project folder to `htdocs`  
3. Open phpMyAdmin  
4. Create a database named:

   restaurant_db

5. Import the `database.sql` file  
6. Open this file and update DB details if needed:

   `/includes/config.php`

7. Run the project in browser:

   http://localhost/restaurant-billing-management-system  

---

## 🗄️ Database Setup

- Database name: `restaurant_db`  
- Import the provided `database.sql` file  
- Default config:

```php
$host = "localhost";
$user = "root";
$password = "";
$database = "restaurant_db";
