<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';
requireLogin();

$currentPage    = basename($_SERVER['PHP_SELF'], '.php');
$restaurantName = getSetting('restaurant_name') ?: 'Restaurant';
$flash          = getFlash();
$role           = $_SESSION['user_role'] ?? 'waiter';
$userId         = (int)($_SESSION['user_id'] ?? 0);

// Build notification alerts
$notifItems = [];
$notifCount = 0;
try {
    $pdo = getDB();
    // Slow orders (confirmed > 15 min)
    $slowOrders = $pdo->query("SELECT order_number, TIMESTAMPDIFF(MINUTE,created_at,NOW()) as mins FROM orders WHERE status='confirmed' AND TIMESTAMPDIFF(MINUTE,created_at,NOW())>15 ORDER BY created_at ASC LIMIT 5")->fetchAll();
    foreach ($slowOrders as $s) {
        $notifItems[] = ['icon'=>'fa-clock','color'=>'#e67e22','text'=>"Order #{$s['order_number']} waiting {$s['mins']} mins — needs attention!"];
        $notifCount++;
    }
    // Low stock
    try {
        $ls = $pdo->query("SELECT name,current_qty,unit FROM inventory_items WHERE current_qty<=min_qty AND status='active' LIMIT 3")->fetchAll();
        foreach ($ls as $s) { $notifItems[] = ['icon'=>'fa-box-open','color'=>'#c0392b','text'=>"Low stock: {$s['name']} only {$s['current_qty']} {$s['unit']} left"]; $notifCount++; }
    } catch(Exception $e) {}
    // Upcoming reservations in next 60 min
    $rs = $pdo->query("SELECT customer_name,reserved_time FROM reservations WHERE reserved_date=CURDATE() AND reserved_time BETWEEN TIME(NOW()) AND ADDTIME(TIME(NOW()),'01:00:00') AND status='confirmed' LIMIT 3")->fetchAll();
    foreach ($rs as $r) { $notifItems[] = ['icon'=>'fa-calendar-check','color'=>'#2980b9','text'=>"Reservation: {$r['customer_name']} arriving at ".date('h:i A',strtotime($r['reserved_time']))]; $notifCount++; }
} catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle ?? 'Dashboard') ?> — <?= sanitize($restaurantName) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
<style>
.notif-wrap{position:relative;}
.notif-btn{background:none;border:none;cursor:pointer;padding:7px 10px;border-radius:8px;color:var(--text-muted);font-size:19px;line-height:1;transition:background .15s;}
.notif-btn:hover{background:var(--bg-alt);}
.notif-badge{position:absolute;top:1px;right:2px;background:#c0392b;color:#fff;font-size:10px;font-weight:700;border-radius:10px;min-width:17px;height:17px;display:flex;align-items:center;justify-content:center;padding:0 3px;animation:pulse-badge 2s infinite;}
@keyframes pulse-badge{0%,100%{transform:scale(1)}50%{transform:scale(1.2)}}
.notif-drop{position:absolute;top:calc(100% + 10px);right:0;width:320px;background:#fff;border-radius:12px;box-shadow:0 8px 40px rgba(0,0,0,.2);border:1px solid var(--border);z-index:9999;display:none;}
.notif-drop.show{display:block;animation:slideDown .15s ease;}
@keyframes slideDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.notif-head{padding:12px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;}
.notif-head strong{font-size:14px;}
.notif-item{padding:10px 14px;border-bottom:1px solid #f5f1ed;display:flex;gap:10px;align-items:flex-start;font-size:13px;color:var(--text);}
.notif-item:last-child{border:none;}
.notif-dot{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;}
.notif-empty{padding:24px 16px;text-align:center;color:var(--text-muted);font-size:13px;}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="fa-solid fa-utensils brand-icon"></i>
        <div>
            <div class="brand-name"><?= sanitize($restaurantName) ?></div>
            <div class="brand-sub">Billing System</div>
        </div>
    </div>
    <nav class="sidebar-nav">

    <?php if ($role === 'delivery'): ?>
        <div class="nav-section">MY DELIVERIES</div>
        <a href="<?= BASE_URL ?>modules/delivery/my_deliveries.php" class="nav-item <?= in_array($currentPage,['my_deliveries'])?'active':'' ?>">
            <i class="fa-solid fa-motorcycle"></i> My Orders
        </a>
        <a href="<?= BASE_URL ?>modules/delivery/my_deliveries.php?filter=active" class="nav-item">
            <i class="fa-solid fa-person-biking"></i> Active
        </a>
        <a href="<?= BASE_URL ?>modules/delivery/my_deliveries.php?filter=delivered" class="nav-item">
            <i class="fa-solid fa-check-circle"></i> Completed Today
        </a>

    <?php elseif ($role === 'cook'): ?>
        <div class="nav-section">KITCHEN</div>
        <a href="<?= BASE_URL ?>modules/kitchen/kds.php" class="nav-item <?= $currentPage==='kds'?'active':'' ?>">
            <i class="fa-solid fa-fire-burner"></i> Kitchen Display
        </a>

    <?php elseif ($role === 'waiter'): ?>
        <div class="nav-section">MY WORK</div>
        <a href="<?= BASE_URL ?>modules/orders/waiter_dashboard.php" class="nav-item <?= $currentPage==='waiter_dashboard'?'active':'' ?>">
            <i class="fa-solid fa-gauge-high"></i> My Dashboard
        </a>
        <a href="<?= BASE_URL ?>modules/orders/new_order.php" class="nav-item <?= $currentPage==='new_order'?'active':'' ?>">
            <i class="fa-solid fa-plus-circle"></i> New Order
        </a>
        <a href="<?= BASE_URL ?>modules/orders/orders.php" class="nav-item <?= $currentPage==='orders'?'active':'' ?>">
            <i class="fa-solid fa-receipt"></i> My Orders
        </a>
        <div class="nav-section">TOOLS</div>
        <a href="<?= BASE_URL ?>modules/tables/tables.php" class="nav-item <?= $currentPage==='tables'?'active':'' ?>">
            <i class="fa-solid fa-table-cells"></i> Tables
        </a>
        <a href="<?= BASE_URL ?>modules/kitchen/kds.php" class="nav-item <?= $currentPage==='kds'?'active':'' ?>">
            <i class="fa-solid fa-fire-burner"></i> Kitchen Status
        </a>
        <a href="<?= BASE_URL ?>modules/menu/menu.php" class="nav-item <?= $currentPage==='menu'?'active':'' ?>">
            <i class="fa-solid fa-book-open"></i> Menu
        </a>

    <?php else: // admin, manager, cashier ?>
        <div class="nav-section">MAIN</div>
        <a href="<?= BASE_URL ?>dashboard.php" class="nav-item <?= $currentPage==='dashboard'?'active':'' ?>">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>
        <a href="<?= BASE_URL ?>modules/orders/new_order.php" class="nav-item <?= $currentPage==='new_order'?'active':'' ?>">
            <i class="fa-solid fa-plus-circle"></i> New Order
        </a>
        <a href="<?= BASE_URL ?>modules/orders/orders.php" class="nav-item <?= $currentPage==='orders'?'active':'' ?>">
            <i class="fa-solid fa-receipt"></i> All Orders
        </a>
        <a href="<?= BASE_URL ?>modules/billing/bills.php" class="nav-item <?= $currentPage==='bills'?'active':'' ?>">
            <i class="fa-solid fa-file-invoice-dollar"></i> Billing
        </a>
        <div class="nav-section">OPERATIONS</div>
        <a href="<?= BASE_URL ?>modules/tables/tables.php" class="nav-item <?= $currentPage==='tables'?'active':'' ?>">
            <i class="fa-solid fa-table-cells"></i> Tables
        </a>
        <a href="<?= BASE_URL ?>modules/reservations/reservations.php" class="nav-item <?= $currentPage==='reservations'?'active':'' ?>">
            <i class="fa-solid fa-calendar-check"></i> Reservations
        </a>
        <a href="<?= BASE_URL ?>modules/kitchen/kds.php" class="nav-item <?= $currentPage==='kds'?'active':'' ?>">
            <i class="fa-solid fa-fire-burner"></i> Kitchen Display
        </a>
        <a href="<?= BASE_URL ?>modules/delivery/delivery.php" class="nav-item <?= $currentPage==='delivery'?'active':'' ?>">
            <i class="fa-solid fa-motorcycle"></i> Delivery
        </a>
        <a href="<?= BASE_URL ?>modules/menu/menu.php" class="nav-item <?= $currentPage==='menu'?'active':'' ?>">
            <i class="fa-solid fa-book-open"></i> Menu
        </a>
        <div class="nav-section">CUSTOMERS</div>
        <a href="<?= BASE_URL ?>modules/customers/customers.php" class="nav-item <?= $currentPage==='customers'?'active':'' ?>">
            <i class="fa-solid fa-users"></i> Customers & Loyalty
        </a>
        <a href="<?= BASE_URL ?>modules/coupons/coupons.php" class="nav-item <?= $currentPage==='coupons'?'active':'' ?>">
            <i class="fa-solid fa-tag"></i> Coupons & Offers
        </a>
        <?php if (hasRole(['admin','manager'])): ?>
        <div class="nav-section">INVENTORY</div>
        <a href="<?= BASE_URL ?>modules/inventory/inventory.php" class="nav-item <?= $currentPage==='inventory'?'active':'' ?>">
            <i class="fa-solid fa-boxes-stacked"></i> Stock & Expenses
        </a>
        <div class="nav-section">REPORTS</div>
        <a href="<?= BASE_URL ?>modules/reports/analytics.php" class="nav-item <?= $currentPage==='analytics'?'active':'' ?>">
            <i class="fa-solid fa-chart-pie"></i> Analytics & P&L
        </a>
        <a href="<?= BASE_URL ?>modules/reports/sales.php" class="nav-item <?= $currentPage==='sales'?'active':'' ?>">
            <i class="fa-solid fa-chart-line"></i> Sales Report
        </a>
        <a href="<?= BASE_URL ?>modules/reports/daily.php" class="nav-item <?= $currentPage==='daily'?'active':'' ?>">
            <i class="fa-solid fa-calendar-day"></i> Daily Report
        </a>
        <div class="nav-section">ADMIN</div>
        <a href="<?= BASE_URL ?>modules/attendance/attendance.php" class="nav-item <?= $currentPage==='attendance'?'active':'' ?>">
            <i class="fa-solid fa-user-clock"></i> Attendance & Salary
        </a>
        <a href="<?= BASE_URL ?>modules/admin/staff.php" class="nav-item <?= $currentPage==='staff'?'active':'' ?>">
            <i class="fa-solid fa-user-tie"></i> Staff Management
        </a>
        <a href="<?= BASE_URL ?>modules/tables/manage_tables.php" class="nav-item <?= $currentPage==='manage_tables'?'active':'' ?>">
            <i class="fa-solid fa-chair"></i> Manage Tables
        </a>
        <a href="<?= BASE_URL ?>modules/menu/categories.php" class="nav-item <?= $currentPage==='categories'?'active':'' ?>">
            <i class="fa-solid fa-tags"></i> Categories
        </a>
        <a href="<?= BASE_URL ?>modules/admin/audit_log.php" class="nav-item <?= $currentPage==='audit_log'?'active':'' ?>">
            <i class="fa-solid fa-shield-halved"></i> Audit Log
        </a>
        <a href="<?= BASE_URL ?>settings.php" class="nav-item <?= $currentPage==='settings'?'active':'' ?>">
            <i class="fa-solid fa-gear"></i> Settings
        </a>
        <a href="<?= BASE_URL ?>guide.php" class="nav-item <?= $currentPage==='guide'?'active':'' ?>" style="color:#d4a853;">
            <i class="fa-solid fa-book-open-reader"></i> How-To Guide
        </a>
        <?php endif; ?>
    <?php endif; // end role nav ?>

    </nav>
    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?></div>
        <div class="user-info">
            <div class="user-name"><?= sanitize($_SESSION['user_name'] ?? '') ?></div>
            <div class="user-role"><?= ucfirst($role) ?></div>
        </div>
        <a href="<?= BASE_URL ?>logout.php" class="btn-logout" title="Logout"><i class="fa-solid fa-right-from-bracket"></i></a>
    </div>
</div>

<!-- Main -->
<div class="main-content" id="mainContent">
    <div class="topbar">
        <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title"><?= sanitize($pageTitle ?? 'Dashboard') ?></div>
        <div style="display:flex;align-items:center;gap:12px;margin-left:auto;">

            <!-- 🔔 Notification Bell -->
            <div class="notif-wrap">
                <button class="notif-btn" id="notifBtn" onclick="toggleNotif()">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($notifCount > 0): ?>
                    <span class="notif-badge"><?= min($notifCount, 9) ?><?= $notifCount > 9 ? '+' : '' ?></span>
                    <?php endif; ?>
                </button>
                <div class="notif-drop" id="notifDrop">
                    <div class="notif-head">
                        <strong>🔔 Alerts</strong>
                        <small style="color:var(--text-muted);"><?= $notifCount ?> active</small>
                    </div>
                    <?php if (empty($notifItems)): ?>
                    <div class="notif-empty">
                        <i class="fa-solid fa-check-circle fa-2x mb-2 d-block" style="color:#27ae60;"></i>
                        All clear — no alerts right now!
                    </div>
                    <?php else: foreach ($notifItems as $n): ?>
                    <div class="notif-item">
                        <div class="notif-dot" style="background:<?= $n['color'] ?>22;color:<?= $n['color'] ?>;"><i class="fa-solid <?= $n['icon'] ?>"></i></div>
                        <div style="line-height:1.4;"><?= htmlspecialchars($n['text']) ?></div>
                    </div>
                    <?php endforeach; ?>
                    <div style="padding:8px 14px;border-top:1px solid var(--border);">
                        <a href="<?= BASE_URL ?>modules/orders/orders.php" style="font-size:12px;color:var(--primary);text-decoration:none;">View all orders →</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Clock -->
            <div style="display:flex;flex-direction:column;align-items:flex-end;line-height:1.3;">
                <span id="topbarTime" style="font-size:14px;font-weight:700;color:var(--primary);white-space:nowrap;"></span>
                <span id="topbarDate" style="font-size:11px;color:var(--text-muted);white-space:nowrap;"></span>
            </div>
        </div>
    </div>

    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type']==='success'?'success':($flash['type']==='error'?'danger':'info') ?> alert-dismissible fade show mx-3 mt-3" role="alert">
        <?= $flash['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="page-content">
<script>
function toggleNotif() {
    document.getElementById('notifDrop').classList.toggle('show');
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.notif-wrap')) {
        document.getElementById('notifDrop')?.classList.remove('show');
    }
});
</script>