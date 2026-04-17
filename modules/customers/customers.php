<?php
$pageTitle = 'Customers & Loyalty';
require_once '../../includes/header.php';
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone=?");
        $stmt->execute([sanitize($_POST['phone'])]);
        if ($stmt->fetch()) {
            flashMessage('error', 'A customer with this phone number already exists.');
        } else {
            $pdo->prepare("INSERT INTO customers (name,phone,email,address,birthday,notes) VALUES (?,?,?,?,?,?)")
                ->execute([
                    sanitize($_POST['name']), sanitize($_POST['phone']),
                    sanitize($_POST['email'] ?? ''), sanitize($_POST['address'] ?? ''),
                    !empty($_POST['birthday']) ? $_POST['birthday'] : null,
                    sanitize($_POST['notes'] ?? '')
                ]);
            flashMessage('success', 'Customer added.');
        }
    } elseif ($action === 'adjust_points') {
        $cid    = (int)$_POST['customer_id'];
        $points = (int)$_POST['points'];
        $desc   = sanitize($_POST['description']);
        $pdo->prepare("UPDATE customers SET loyalty_points = loyalty_points + ? WHERE id=?")->execute([$points, $cid]);
        $pdo->prepare("INSERT INTO loyalty_transactions (customer_id,type,points,description) VALUES (?,'adjust',?,?)")
            ->execute([$cid, $points, $desc]);
        flashMessage('success', 'Points adjusted.');
    }
    redirect(BASE_URL . 'modules/customers/customers.php');
}

$search = sanitize($_GET['q'] ?? '');
$viewId = (int)($_GET['view'] ?? 0);

if ($viewId) {
    // Single customer view
    $cust = $pdo->prepare("SELECT * FROM customers WHERE id=?");
    $cust->execute([$viewId]);
    $cust = $cust->fetch();
    if (!$cust) redirect(BASE_URL . 'modules/customers/customers.php');

    $orders = $pdo->prepare(
        "SELECT o.*, b.total_amount, b.payment_method, b.payment_status
         FROM orders o
         LEFT JOIN bills b ON b.order_id = o.id
         WHERE o.customer_id = ?
         ORDER BY o.created_at DESC LIMIT 30"
    );
    $orders->execute([$viewId]);
    $orders = $orders->fetchAll();

    $loyalty = $pdo->prepare("SELECT * FROM loyalty_transactions WHERE customer_id=? ORDER BY created_at DESC LIMIT 20");
    $loyalty->execute([$viewId]);
    $loyaltyTx = $loyalty->fetchAll();

    $totalOrders = count($orders);
    $totalSpent  = array_sum(array_column(array_filter($orders, fn($o) => $o['payment_status']==='paid'), 'total_amount'));
?>

<div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= BASE_URL ?>modules/customers/customers.php" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i> All Customers
    </a>
    <span class="text-muted">/</span>
    <span><?= htmlspecialchars($cust['name']) ?></span>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center py-4">
                <div style="width:72px;height:72px;background:var(--primary);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;font-family:'Playfair Display',serif;margin:0 auto 16px;">
                    <?= strtoupper(substr($cust['name'],0,1)) ?>
                </div>
                <h5 style="font-family:'Playfair Display',serif;"><?= htmlspecialchars($cust['name']) ?></h5>
                <div class="text-muted mb-1"><?= htmlspecialchars($cust['phone']) ?></div>
                <?php if ($cust['email']): ?><div class="text-muted mb-3" style="font-size:13px;"><?= htmlspecialchars($cust['email']) ?></div><?php endif; ?>

                <div style="background:linear-gradient(135deg,#b5451b,#d45f28);color:#fff;border-radius:12px;padding:16px;margin-bottom:16px;">
                    <div style="font-size:11px;opacity:.8;letter-spacing:2px;text-transform:uppercase;">Loyalty Points</div>
                    <div style="font-size:36px;font-weight:900;font-family:'Playfair Display',serif;"><?= number_format($cust['loyalty_points']) ?></div>
                    <div style="font-size:12px;opacity:.8;">≈ ₹<?= number_format($cust['loyalty_points'] * 0.25, 2) ?> redeemable</div>
                </div>

                <div class="row g-2 text-center mb-3">
                    <div class="col-6">
                        <div style="background:#f9f6f3;border-radius:8px;padding:12px;">
                            <div style="font-size:20px;font-weight:700;color:var(--primary);"><?= $totalOrders ?></div>
                            <div style="font-size:11px;color:var(--text-muted);">Total Orders</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div style="background:#f9f6f3;border-radius:8px;padding:12px;">
                            <div style="font-size:20px;font-weight:700;color:var(--success);">₹<?= number_format($totalSpent,0) ?></div>
                            <div style="font-size:11px;color:var(--text-muted);">Total Spent</div>
                        </div>
                    </div>
                </div>

                <?php if ($cust['address']): ?>
                <div class="text-muted text-start" style="font-size:13px;"><i class="fa-solid fa-location-dot me-1"></i><?= htmlspecialchars($cust['address']) ?></div>
                <?php endif; ?>
                <?php if ($cust['birthday']): ?>
                <div class="text-muted text-start mt-1" style="font-size:13px;"><i class="fa-solid fa-cake-candles me-1"></i><?= date('d M', strtotime($cust['birthday'])) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Adjust points -->
        <div class="card mt-3">
            <div class="card-header"><h6>Adjust Loyalty Points</h6></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="adjust_points">
                    <input type="hidden" name="customer_id" value="<?= $cust['id'] ?>">
                    <div class="mb-2">
                        <label class="form-label">Points (use – for deduction)</label>
                        <input type="number" name="points" class="form-control" placeholder="+100 or -50" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Reason</label>
                        <input type="text" name="description" class="form-control" placeholder="Manual adjustment reason">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">Adjust Points</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <!-- Order History -->
        <div class="card mb-3">
            <div class="card-header"><h6><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Order History</h6></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Order #</th><th>Date</th><th>Type</th><th>Amount</th><th>Payment</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><a href="<?= BASE_URL ?>modules/orders/view_order.php?id=<?= $o['id'] ?>" style="color:var(--primary);"><?= htmlspecialchars($o['order_number']) ?></a></td>
                        <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                        <td><?= ucfirst(str_replace('_',' ',$o['order_type'])) ?></td>
                        <td><?= $o['total_amount'] ? '₹'.number_format($o['total_amount'],2) : '—' ?></td>
                        <td><?= ucfirst($o['payment_method'] ?? '—') ?></td>
                        <td><span class="badge-status badge-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($orders)): ?><tr><td colspan="6" class="text-center text-muted py-3">No orders yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Loyalty Transactions -->
        <div class="card">
            <div class="card-header"><h6><i class="fa-solid fa-star me-2 text-primary"></i>Loyalty Activity</h6></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Date</th><th>Type</th><th>Points</th><th>Description</th></tr></thead>
                    <tbody>
                    <?php foreach ($loyaltyTx as $lt): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($lt['created_at'])) ?></td>
                        <td><span class="badge bg-<?= $lt['type']==='earn' ? 'success' : ($lt['type']==='redeem' ? 'warning' : 'info') ?>"><?= ucfirst($lt['type']) ?></span></td>
                        <td><strong class="<?= $lt['points']>0 ? 'text-success' : 'text-danger' ?>"><?= ($lt['points']>0?'+':'').$lt['points'] ?></strong></td>
                        <td><?= htmlspecialchars($lt['description'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($loyaltyTx)): ?><tr><td colspan="4" class="text-center text-muted py-3">No loyalty activity yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
} else {
    // Customer list
    $sql = "SELECT c.*, COUNT(o.id) as order_count FROM customers c LEFT JOIN orders o ON o.customer_id=c.id";
    $params = [];
    if ($search) { $sql .= " WHERE c.name LIKE ? OR c.phone LIKE ?"; $params = ["%$search%","%$search%"]; }
    $sql .= " GROUP BY c.id ORDER BY c.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll();

    $totalCustomers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    $totalLoyalty   = $pdo->query("SELECT COALESCE(SUM(loyalty_points),0) FROM customers")->fetchColumn();
?>
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card primary">
            <div class="stat-icon primary"><i class="fa-solid fa-users"></i></div>
            <div class="stat-value"><?= $totalCustomers ?></div>
            <div class="stat-label">Total Customers</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card warning">
            <div class="stat-icon warning"><i class="fa-solid fa-star"></i></div>
            <div class="stat-value"><?= number_format($totalLoyalty) ?></div>
            <div class="stat-label">Points in Circulation</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card success">
            <div class="stat-icon success"><i class="fa-solid fa-cake-candles"></i></div>
            <div class="stat-value"><?= $pdo->query("SELECT COUNT(*) FROM customers WHERE DATE_FORMAT(birthday,'%m-%d')=DATE_FORMAT(CURDATE(),'%m-%d')")->fetchColumn() ?></div>
            <div class="stat-label">Birthdays Today 🎂</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h6><i class="fa-solid fa-users me-2 text-primary"></i>Customers</h6>
        <div class="d-flex gap-2">
            <form method="GET" class="d-flex gap-2">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Search name or phone…" value="<?= htmlspecialchars($search) ?>">
                <button class="btn btn-sm btn-primary">Search</button>
                <?php if ($search): ?><a href="?" class="btn btn-sm btn-outline-secondary">Clear</a><?php endif; ?>
            </form>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCustModal">
                <i class="fa-solid fa-plus me-1"></i> Add
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Name</th><th>Phone</th><th>Orders</th><th>Points</th><th>Member Since</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
            <tr>
                <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                <td><?= htmlspecialchars($c['phone']) ?></td>
                <td><?= $c['order_count'] ?></td>
                <td>
                    <span style="background:#fde8e0;color:var(--primary);padding:2px 8px;border-radius:10px;font-weight:600;font-size:13px;">
                        ⭐ <?= number_format($c['loyalty_points']) ?>
                    </span>
                </td>
                <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                <td>
                    <a href="?view=<?= $c['id'] ?>" class="btn-icon" title="View Profile"><i class="fa-solid fa-eye"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($customers)): ?><tr><td colspan="6" class="text-center text-muted py-4">No customers found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Add Customer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-8"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" required></div>
                        <div class="col-4"><label class="form-label">Birthday</label><input type="date" name="birthday" class="form-control"></div>
                        <div class="col-12"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" required></div>
                        <div class="col-12"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                        <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"></textarea></div>
                        <div class="col-12"><label class="form-label">Notes</label><input type="text" name="notes" class="form-control" placeholder="VIP, allergies, preferences…"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php } require_once '../../includes/footer.php'; ?>
