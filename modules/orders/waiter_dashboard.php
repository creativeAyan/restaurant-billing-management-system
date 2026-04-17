<?php
$pageTitle = 'My Dashboard';
require_once '../../includes/header.php';
requireRole(['waiter','admin','manager']);
$pdo   = getDB();
$myId  = (int)$_SESSION['user_id'];
$today = date('Y-m-d');

// Waiter sees ONLY their own orders
$myOrders = $pdo->prepare("
    SELECT o.*, t.table_number, c.name AS cust_name,
        (SELECT SUM(oi.quantity*oi.unit_price) FROM order_items oi WHERE oi.order_id=o.id) AS total
    FROM orders o
    LEFT JOIN tables t ON t.id = o.table_id
    LEFT JOIN customers c ON c.id = o.customer_id
    WHERE o.waiter_id = ? AND DATE(o.created_at) = ?
    AND o.status NOT IN ('served','cancelled')
    ORDER BY FIELD(o.status,'ready','confirmed','preparing'), o.created_at ASC
");
$myOrders->execute([$myId, $today]);
$myOrders = $myOrders->fetchAll();

// Stats for today
$stats = $pdo->prepare("
    SELECT
        COUNT(*) AS total_orders,
        SUM(CASE WHEN o.status NOT IN ('served','cancelled') THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN o.status='served' THEN 1 ELSE 0 END) AS served,
        COALESCE(SUM(b.total_amount),0) AS revenue
    FROM orders o
    LEFT JOIN bills b ON b.order_id = o.id AND b.payment_status='paid'
    WHERE o.waiter_id = ? AND DATE(o.created_at) = ?
");
$stats->execute([$myId, $today]);
$stats = $stats->fetch();

// My occupied tables
$myTables = $pdo->prepare("
    SELECT DISTINCT t.* FROM tables t
    JOIN orders o ON o.table_id = t.id
    WHERE o.waiter_id = ? AND o.status NOT IN ('served','cancelled')
");
$myTables->execute([$myId]);
$myTables = $myTables->fetchAll();

// Ready orders (need to be served)
$readyOrders = array_filter($myOrders, fn($o) => $o['status'] === 'ready');
?>

<style>
.waiter-stat{background:#fff;border-radius:12px;padding:16px;text-align:center;border:1px solid var(--border);}
.waiter-stat .wn{font-size:30px;font-weight:800;font-family:'Playfair Display',serif;line-height:1;}
.waiter-stat .wl{font-size:12px;color:var(--text-muted);margin-top:3px;}
.order-card{background:#fff;border-radius:12px;border:2px solid var(--border);margin-bottom:12px;overflow:hidden;transition:border-color .2s;}
.order-card.status-ready{border-color:#27ae60;background:#eafaf1;}
.order-card.status-confirmed{border-color:#2980b9;}
.order-card.status-preparing{border-color:#e67e22;background:#fff8f0;}
.oc-head{padding:12px 16px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid rgba(0,0,0,.07);}
.oc-body{padding:12px 16px;font-size:13px;}
.oc-foot{padding:10px 16px;background:#faf7f4;display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
.ready-alert{background:linear-gradient(135deg,#27ae60,#1e8449);color:#fff;border-radius:10px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:12px;}
</style>

<!-- Ready alert banner -->
<?php if (!empty($readyOrders)): ?>
<div class="ready-alert">
    <i class="fa-solid fa-bell fa-2x" style="animation:pulse 1.5s infinite;"></i>
    <div>
        <strong style="font-size:15px;"><?= count($readyOrders) ?> order<?= count($readyOrders)>1?'s are':' is' ?> READY to serve!</strong>
        <div style="font-size:13px;opacity:.9;">Check the orders below marked in green</div>
    </div>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="waiter-stat"><div class="wn" style="color:#e67e22;"><?= $stats['active'] ?></div><div class="wl">Active Orders</div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="waiter-stat"><div class="wn" style="color:#27ae60;"><?= $stats['served'] ?></div><div class="wl">Served Today</div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="waiter-stat"><div class="wn"><?= $stats['total_orders'] ?></div><div class="wl">Total Today</div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="waiter-stat"><div class="wn" style="color:var(--primary);">₹<?= number_format($stats['revenue'],0) ?></div><div class="wl">My Revenue</div></div>
    </div>
</div>

<div class="row g-3">
    <!-- Active orders -->
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 style="font-family:'Playfair Display',serif;margin:0;">My Active Orders</h5>
            <a href="<?= BASE_URL ?>modules/orders/new_order.php" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus me-1"></i> New Order
            </a>
        </div>

        <?php if (empty($myOrders)): ?>
        <div class="card">
            <div class="card-body text-center py-5 text-muted">
                <i class="fa-solid fa-mug-hot fa-3x mb-3 d-block" style="color:var(--border);"></i>
                <h5>No active orders</h5>
                <p>Take a new order to get started!</p>
                <a href="<?= BASE_URL ?>modules/orders/new_order.php" class="btn btn-primary">
                    <i class="fa-solid fa-plus me-1"></i> Take Order
                </a>
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($myOrders as $o):
            $statusClass = 'status-' . $o['status'];
        ?>
        <div class="order-card <?= $statusClass ?>">
            <div class="oc-head">
                <div class="d-flex align-items-center gap-2">
                    <strong style="font-size:15px;"><?= htmlspecialchars($o['order_number']) ?></strong>
                    <?php
                    $badges = ['confirmed'=>'badge-confirmed','preparing'=>'badge-preparing','ready'=>'badge-delivered'];
                    $bl = $badges[$o['status']] ?? 'badge-pending';
                    ?>
                    <span class="badge-status <?= $bl ?>"><?= ucfirst($o['status']) ?></span>
                    <?php if ($o['status']==='ready'): ?>
                    <span style="color:#27ae60;font-weight:700;font-size:13px;animation:pulse 1.5s infinite;">🔔 Serve Now!</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:13px;font-weight:600;color:var(--primary);">₹<?= number_format($o['total'] ?? 0, 0) ?></div>
            </div>
            <div class="oc-body">
                <div class="d-flex gap-4 flex-wrap">
                    <div>
                        <span style="color:var(--text-muted);font-size:11px;">TABLE</span><br>
                        <strong style="font-size:18px;"><?= htmlspecialchars($o['table_number'] ?? '—') ?></strong>
                    </div>
                    <?php if ($o['cust_name']): ?>
                    <div>
                        <span style="color:var(--text-muted);font-size:11px;">CUSTOMER</span><br>
                        <strong><?= htmlspecialchars($o['cust_name']) ?></strong>
                    </div>
                    <?php endif; ?>
                    <div>
                        <span style="color:var(--text-muted);font-size:11px;">TIME</span><br>
                        <span><?= date('h:i A', strtotime($o['created_at'])) ?></span>
                    </div>
                    <?php
                    $mins = round((time() - strtotime($o['created_at'])) / 60);
                    $mintColor = $mins > 30 ? '#c0392b' : ($mins > 15 ? '#e67e22' : '#27ae60');
                    ?>
                    <div>
                        <span style="color:var(--text-muted);font-size:11px;">WAITING</span><br>
                        <span style="color:<?= $mintColor ?>;font-weight:600;"><?= $mins ?> min</span>
                    </div>
                </div>
            </div>
            <div class="oc-foot">
                <a href="<?= BASE_URL ?>modules/orders/view_order.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fa-solid fa-eye me-1"></i> View / Add Items
                </a>
                <?php if ($o['status'] === 'ready'): ?>
                <a href="<?= BASE_URL ?>modules/billing/create_bill.php?order_id=<?= $o['id'] ?>" class="btn btn-sm btn-success">
                    <i class="fa-solid fa-file-invoice-dollar me-1"></i> Generate Bill
                </a>
                <?php endif; ?>
                <?php if ($o['status'] === 'confirmed'): ?>
                <a href="<?= BASE_URL ?>modules/kitchen/kds.php" class="btn btn-sm btn-outline-warning">
                    <i class="fa-solid fa-fire-burner me-1"></i> Check Kitchen
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- My Tables -->
    <div class="col-lg-4">
        <h5 style="font-family:'Playfair Display',serif;margin-bottom:12px;">My Tables</h5>
        <?php if (empty($myTables)): ?>
        <div class="card"><div class="card-body text-center py-3 text-muted" style="font-size:13px;">No occupied tables</div></div>
        <?php else: foreach ($myTables as $t): ?>
        <div class="card mb-2">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div style="width:48px;height:48px;border-radius:10px;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;flex-shrink:0;">
                    <?= htmlspecialchars($t['table_number']) ?>
                </div>
                <div>
                    <div style="font-weight:600;"><?= htmlspecialchars($t['table_number']) ?></div>
                    <div style="font-size:12px;color:var(--text-muted);"><?= $t['floor'] ?> · <?= $t['capacity'] ?> seats</div>
                </div>
                <span class="ms-auto badge-status badge-confirmed" style="font-size:11px;">Occupied</span>
            </div>
        </div>
        <?php endforeach; endif; ?>

        <!-- Quick actions box -->
        <div class="card mt-3">
            <div class="card-header" style="padding:12px 16px;">
                <h6 class="mb-0" style="font-size:14px;">Quick Actions</h6>
            </div>
            <div class="card-body d-grid gap-2">
                <a href="<?= BASE_URL ?>modules/orders/new_order.php" class="btn btn-primary">
                    <i class="fa-solid fa-plus me-2"></i>Take New Order
                </a>
                <a href="<?= BASE_URL ?>modules/tables/tables.php" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-table-cells me-2"></i>View All Tables
                </a>
                <a href="<?= BASE_URL ?>modules/kitchen/kds.php" class="btn btn-outline-warning">
                    <i class="fa-solid fa-fire-burner me-2"></i>Kitchen Display
                </a>
                <a href="<?= BASE_URL ?>modules/menu/menu.php" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-book-open me-2"></i>View Menu
                </a>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}
</style>

<?php require_once '../../includes/footer.php'; ?>
