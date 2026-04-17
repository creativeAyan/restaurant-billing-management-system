<?php
session_start();
require_once 'includes/config.php';

if (isLoggedIn()) redirect(BASE_URL . 'dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            try { auditLog('Login', 'auth', $user['id'], null, $user['username']); } catch(Exception $e) {}
            // Role-based redirect
            $dest = match($user['role']) {
                'delivery' => BASE_URL . 'modules/delivery/my_deliveries.php',
                'cook'     => BASE_URL . 'modules/kitchen/kds.php',
                'waiter'   => BASE_URL . 'modules/orders/waiter_dashboard.php',
                default    => BASE_URL . 'dashboard.php',
            };
            redirect($dest);
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please enter username and password.';
    }
}
$restaurantName = 'Grand Spice Restaurant';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= $restaurantName ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #b5451b; --accent: #d4a853; }
        body { background: #1e1510; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: 'DM Sans', sans-serif; }
        .login-wrap { width: 100%; max-width: 420px; padding: 16px; }
        .login-card { background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.4); }
        .login-header { background: #1e1510; padding: 36px 32px 28px; text-align: center; }
        .login-icon { font-size: 40px; color: var(--accent); margin-bottom: 12px; }
        .login-title { font-family: 'Playfair Display', serif; color: #fff; font-size: 22px; margin: 0; }
        .login-sub { color: #9a8880; font-size: 13px; margin-top: 4px; }
        .login-body { padding: 32px; }
        .form-label { font-size: 13px; font-weight: 500; color: #7a6e68; }
        .form-control { border-color: #e8e0d8; padding: 10px 14px; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(181,69,27,0.1); }
        .input-group-text { background: #faf7f4; border-color: #e8e0d8; color: #7a6e68; }
        .btn-login { background: var(--primary); border: none; color: #fff; width: 100%; padding: 12px;
            font-size: 15px; font-weight: 600; border-radius: 8px; transition: all 0.2s; }
        .btn-login:hover { background: #8c3214; }
        .login-hint { background: #faf7f4; border-radius: 8px; padding: 12px; margin-top: 20px;
            font-size: 12px; color: #7a6e68; text-align: center; }
        .login-hint strong { color: #1e1510; }
        .alert-danger { font-size: 13px; }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="login-header">
            <div class="login-icon"><i class="fa-solid fa-utensils"></i></div>
            <h1 class="login-title"><?= htmlspecialchars($restaurantName) ?></h1>
            <p class="login-sub">Billing & Management System</p>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= $error ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="Enter username" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                    </div>
                </div>
                <button type="submit" class="btn-login">
                    <i class="fa-solid fa-right-to-bracket me-2"></i> Sign In
                </button>
            </form>
            <div class="login-hint">
                Default credentials: <strong>admin</strong> / <strong>password</strong>
            </div>
        </div>
    </div>
</div>
</body>
</html>
