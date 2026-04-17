-- ============================================================
-- v3 PATCH — Run this once in phpMyAdmin or MySQL CLI
-- ============================================================
USE restaurant_billing;

ALTER TABLE users ADD COLUMN IF NOT EXISTS daily_wage DECIMAL(10,2) DEFAULT 0 AFTER email;
ALTER TABLE users MODIFY COLUMN role ENUM('admin','manager','waiter','delivery','cashier','chef','cook') DEFAULT 'waiter';

-- Set default wages for existing staff by role
UPDATE users SET daily_wage=1000 WHERE role='admin'    AND (daily_wage IS NULL OR daily_wage=0);
UPDATE users SET daily_wage=800  WHERE role='manager'  AND (daily_wage IS NULL OR daily_wage=0);
UPDATE users SET daily_wage=500  WHERE role='waiter'   AND (daily_wage IS NULL OR daily_wage=0);
UPDATE users SET daily_wage=450  WHERE role='delivery' AND (daily_wage IS NULL OR daily_wage=0);
UPDATE users SET daily_wage=500  WHERE role='cashier'  AND (daily_wage IS NULL OR daily_wage=0);
UPDATE users SET daily_wage=700  WHERE role='chef'     AND (daily_wage IS NULL OR daily_wage=0);
UPDATE users SET daily_wage=600  WHERE role='cook'     AND (daily_wage IS NULL OR daily_wage=0);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('order','kitchen','delivery','reservation','low_stock','general') DEFAULT 'general',
    title VARCHAR(150) NOT NULL,
    message TEXT,
    target_role VARCHAR(50) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

SELECT 'v3 patch applied successfully' AS result;
