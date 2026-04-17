<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'restaurant_billing');

define('APP_NAME', 'Restaurant Billing System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/restaurant_billing/');

date_default_timezone_set('Asia/Kolkata');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die("<div style='font-family:monospace;background:#fee;padding:20px;border-left:4px solid red;'>
                <strong>Database Connection Error:</strong><br>" . $e->getMessage() . "
                <br><br>Please check your database configuration in <code>includes/config.php</code>
            </div>");
        }
    }
    return $pdo;
}

function getSetting($key) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : '';
}

function generateOrderNumber() {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

function generateBillNumber() {
    return 'BILL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

function formatCurrency($amount) {
    $symbol = getSetting('currency_symbol') ?: '₹';
    return $symbol . number_format($amount, 2);
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function redirect($url) {
    // Clear any buffered output so the Location header can be sent
    while (ob_get_level()) {
        ob_end_clean();
    }
    header("Location: $url");
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect(BASE_URL . 'login.php');
    }
}

function hasRole($roles) {
    if (!isset($_SESSION['user_role'])) return false;
    if (is_string($roles)) $roles = [$roles];
    return in_array($_SESSION['user_role'], $roles);
}

function requireRole($roles) {
    if (!hasRole($roles)) {
        redirect(BASE_URL . 'dashboard.php?error=unauthorized');
    }
}

function flashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function auditLog($action, $module = null, $recordId = null, $oldVal = null, $newVal = null) {
    try {
        $pdo = getDB();
        $pdo->prepare(
            "INSERT INTO audit_log (user_id,user_name,action,module,record_id,old_value,new_value,ip_address)
             VALUES (?,?,?,?,?,?,?,?)"
        )->execute([
            $_SESSION['user_id']   ?? null,
            $_SESSION['user_name'] ?? 'System',
            $action, $module, $recordId,
            is_array($oldVal) ? json_encode($oldVal) : $oldVal,
            is_array($newVal) ? json_encode($newVal) : $newVal,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) { /* silent fail if table not yet created */ }
}

function awardLoyaltyPoints($customerId, $billId, $orderTotal) {
    try {
        $pdo = getDB();
        $rate   = (float)(getSetting('loyalty_points_per_rupee') ?: 1);
        $points = (int)floor($orderTotal * $rate);
        if ($points <= 0) return;
        $pdo->prepare("UPDATE customers SET loyalty_points=loyalty_points+?, total_spent=total_spent+? WHERE id=?")->execute([$points, $orderTotal, $customerId]);
        $pdo->prepare("INSERT INTO loyalty_transactions (customer_id,bill_id,type,points,description) VALUES (?,'earn',?,?)")->execute([$customerId, $billId, $points, "Earned on bill #".$billId]);
    } catch (Exception $e) {}
}