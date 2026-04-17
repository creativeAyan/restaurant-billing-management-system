<?php
$pageTitle = 'Dashboard';
require_once 'includes/header.php';

$pdo = getDB();
$today = date('Y-m-d');

// Stats
$todaySales = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM bills WHERE DATE(created_at)='$today' AND payment_status='paid'")->fetchColumn();
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)='$today'")->fetchColumn();
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending','confirmed','preparing') AND DATE(created_at)='$today'")->fetchColumn();
$activeDeliveries = $pdo->query("SELECT COUNT(*) FROM deliveries WHERE status IN ('pending','assigned','picked')")->fetchColumn();
$occupiedTables = $pdo->query("SELECT COUNT(*) FROM tables WHERE status='occupied'")->fetchColumn();
$totalTables = $pdo->query("SELECT COUNT(*) FROM tables")->fetchColumn();
$todayExpenses = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date='$today'")->fetchColumn();
$todayReservations = $pdo->query("SELECT COUNT(*) FROM reservations WHERE reserved_date='$today' AND status IN ('confirmed','seated') LIMIT 1")->fetchColumn();
$lowStockCount = 0;
try { $lowStockCount = $pdo->query("SELECT COUNT(*) FROM inventory_items WHERE current_qty<=min_qty AND status='active'")->fetchColumn(); } catch(Exception $e) {}
$birthdaysToday = $pdo->query("SELECT COUNT(*) FROM customers WHERE DATE_FORMAT(birthday,'%m-%d')=DATE_FORMAT(CURDATE(),'%m-%d')")->fetchColumn();

// Recent orders
$recentOrders = $pdo->query("
    SELECT o.*, t.table_number, u.name as waiter_name, c.name as customer_name
    FROM orders o
    LEFT JOIN tables t ON o.table_id = t.id
    LEFT JOIN users u ON o.waiter_id = u.id
    LEFT JOIN customers c ON o.customer_id = c.id
    ORDER BY o.created_at DESC LIMIT 10
")->fetchAll();

// Table status
$tables = $pdo->query("SELECT * FROM tables ORDER BY floor, table_number")->fetchAll();

// Weekly sales (last 7 days)
$weeklySales = $pdo->query("
    SELECT DATE(created_at) as day, SUM(total_amount) as total
    FROM bills WHERE payment_status='paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at) ORDER BY day
")->fetchAll();
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card primary">
            <div class="stat-icon primary"><i class="fa-solid fa-indian-rupee-sign"></i></div>
            <div class="stat-value"><?= getSetting('currency_symbol') ?><?= number_format($todaySales, 0) ?></div>
            <div class="stat-label">Today's Revenue</div>
            <?php if ($todayExpenses > 0): ?><div class="stat-change down">Expenses: ₹<?= number_format($todayExpenses,0) ?></div><?php endif; ?>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card success">
            <div class="stat-icon success"><i class="fa-solid fa-receipt"></i></div>
            <div class="stat-value"><?= $totalOrders ?></div>
            <div class="stat-label">Orders Today</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card warning">
            <div class="stat-icon warning"><i class="fa-solid fa-clock"></i></div>
            <div class="stat-value"><?= $pendingOrders ?></div>
            <div class="stat-label">Active Orders</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card info">
            <div class="stat-icon info"><i class="fa-solid fa-motorcycle"></i></div>
            <div class="stat-value"><?= $activeDeliveries ?></div>
            <div class="stat-label">Live Deliveries</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card success">
            <div class="stat-icon success"><i class="fa-solid fa-calendar-check"></i></div>
            <div class="stat-value"><?= $todayReservations ?></div>
            <div class="stat-label">Reservations Today</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card <?= $lowStockCount>0?'warning':'success' ?>">
            <div class="stat-icon <?= $lowStockCount>0?'warning':'success' ?>"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div class="stat-value"><?= $lowStockCount ?></div>
            <div class="stat-label">Low Stock Alerts</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card info">
            <div class="stat-icon info"><i class="fa-solid fa-table-cells"></i></div>
            <div class="stat-value"><?= $occupiedTables ?>/<?= $totalTables ?></div>
            <div class="stat-label">Tables Occupied</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card <?= $birthdaysToday>0?'warning':'primary' ?>">
            <div class="stat-icon <?= $birthdaysToday>0?'warning':'primary' ?>"><i class="fa-solid fa-cake-candles"></i></div>
            <div class="stat-value"><?= $birthdaysToday ?></div>
            <div class="stat-label">Birthdays Today 🎂</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Table Overview -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h6><i class="fa-solid fa-table-cells me-2 text-primary"></i>Table Status</h6>
                <span class="badge bg-secondary"><?= $occupiedTables ?>/<?= $totalTables ?> Occupied</span>
            </div>
            <div class="card-body">
                <div class="table-grid">
                    <?php foreach ($tables as $t):
                        $colors = ['available'=>'available','occupied'=>'occupied','reserved'=>'reserved','cleaning'=>'cleaning'];
                        $c = $colors[$t['status']] ?? 'available';
                    ?>
                    <div class="table-card <?= $c ?>" title="<?= htmlspecialchars($t['floor']) ?> Floor">
                        <div class="table-num"><?= htmlspecialchars($t['table_number']) ?></div>
                        <div class="table-cap"><i class="fa-solid fa-person"></i> <?= $t['capacity'] ?></div>
                        <div class="table-status-badge"><?= $t['status'] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="d-flex gap-2 flex-wrap mt-3" style="font-size:12px;">
                    <span><span style="background:#d4edda;padding:2px 8px;border-radius:10px;">Available</span></span>
                    <span><span style="background:#fde8e0;padding:2px 8px;border-radius:10px;">Occupied</span></span>
                    <span><span style="background:#fef3cd;padding:2px 8px;border-radius:10px;">Reserved</span></span>
                    <span><span style="background:#d1ecf1;padding:2px 8px;border-radius:10px;">Cleaning</span></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h6><i class="fa-solid fa-receipt me-2 text-primary"></i>Recent Orders</h6>
                <a href="modules/orders/orders.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Type</th>
                                <th>Table/Customer</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No orders today yet</td></tr>
                            <?php else: foreach ($recentOrders as $o): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($o['order_number']) ?></strong></td>
                                <td>
                                    <?php if ($o['order_type'] === 'dine_in'): ?>
                                        <span class="badge bg-primary">Dine-In</span>
                                    <?php elseif ($o['order_type'] === 'delivery'): ?>
                                        <span class="badge bg-warning text-dark">Delivery</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark">Takeaway</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $o['order_type'] === 'dine_in' ? htmlspecialchars($o['table_number'] ?? '-') : htmlspecialchars($o['customer_name'] ?? '-') ?></td>
                                <td><?= date('h:i A', strtotime($o['created_at'])) ?></td>
                                <td><span class="badge-status badge-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                                <td>
                                    <a href="modules/orders/view_order.php?id=<?= $o['id'] ?>" class="btn-icon" title="View"><i class="fa-solid fa-eye"></i></a>
                                    <a href="modules/billing/create_bill.php?order_id=<?= $o['id'] ?>" class="btn-icon" title="Bill"><i class="fa-solid fa-file-invoice-dollar"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row g-2 mt-2">
            <div class="col-6 col-md-3">
                <a href="modules/orders/new_order.php" class="card text-decoration-none text-center p-3" style="border-color:#b5451b;">
                    <i class="fa-solid fa-plus-circle fa-2x text-primary mb-2"></i><br>
                    <div style="font-size:13px;font-weight:600;">New Order</div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="modules/delivery/new_delivery.php" class="card text-decoration-none text-center p-3">
                    <i class="fa-solid fa-motorcycle fa-2x" style="color:#e67e22;" ></i><br>
                    <div style="font-size:13px;font-weight:600;margin-top:8px;">New Delivery</div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="modules/billing/bills.php" class="card text-decoration-none text-center p-3">
                    <i class="fa-solid fa-file-invoice fa-2x" style="color:#2980b9;"></i><br>
                    <div style="font-size:13px;font-weight:600;margin-top:8px;">All Bills</div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="modules/reports/daily.php" class="card text-decoration-none text-center p-3">
                    <i class="fa-solid fa-chart-bar fa-2x" style="color:#2d7d4f;"></i><br>
                    <div style="font-size:13px;font-weight:600;margin-top:8px;">Daily Report</div>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
