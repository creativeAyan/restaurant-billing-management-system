<?php
$pageTitle = 'Delivery Management';
require_once '../../includes/header.php';
$pdo = getDB();

// Handle status update
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'assign' && isset($_GET['delivery_id'], $_GET['rider_id'])) {
        $pdo->prepare("UPDATE deliveries SET delivery_person_id=?, status='assigned', assigned_at=NOW() WHERE id=?")
            ->execute([(int)$_GET['rider_id'], (int)$_GET['delivery_id']]);
        flashMessage('success', 'Rider assigned!');
    } elseif ($_GET['action'] === 'update' && isset($_GET['delivery_id'], $_GET['status'])) {
        $status = sanitize($_GET['status']);
        $sql = $status === 'delivered'
            ? "UPDATE deliveries SET status=?, delivered_at=NOW() WHERE id=?"
            : "UPDATE deliveries SET status=? WHERE id=?";
        $pdo->prepare($sql)->execute([$status, (int)$_GET['delivery_id']]);
        flashMessage('success', 'Status updated!');
    }
    redirect(BASE_URL . 'modules/delivery/delivery.php');
}

// Fix: use d.order_id (on deliveries table), NOT o.order_id (doesn't exist on orders)
$deliveries = $pdo->query("
    SELECT d.*,
        o.order_number,
        o.created_at AS order_time,
        c.name    AS cust_name,
        c.phone   AS cust_phone,
        c.address AS cust_address,
        u.name    AS rider_name,
        (SELECT SUM(oi.quantity * oi.unit_price)
         FROM order_items oi
         WHERE oi.order_id = d.order_id) AS order_total
    FROM deliveries d
    JOIN   orders    o ON o.id = d.order_id
    LEFT JOIN customers c ON c.id = o.customer_id
    LEFT JOIN users     u ON u.id = d.delivery_person_id
    ORDER BY d.id DESC
    LIMIT 50
")->fetchAll();

$riders = $pdo->query("SELECT * FROM users WHERE role='delivery' AND status='active'")->fetchAll();

$stats = ['pending' => 0, 'assigned' => 0, 'picked' => 0, 'delivered' => 0, 'failed' => 0];
foreach ($deliveries as $d) {
    if (isset($stats[$d['status']])) $stats[$d['status']]++;
}
?>

<div class="row g-2 mb-3">
    <?php foreach ([
        ['pending',   'warning', 'fa-clock'],
        ['assigned',  'info',    'fa-person-biking'],
        ['picked',    'primary', 'fa-motorcycle'],
        ['delivered', 'success', 'fa-check-circle'],
    ] as [$s, $c, $ic]): ?>
    <div class="col-6 col-md-3">
        <div class="card text-center p-3">
            <i class="fa-solid <?= $ic ?> fa-2x mb-2 text-<?= $c ?>"></i>
            <div class="fw-700 fs-4"><?= $stats[$s] ?></div>
            <div class="text-muted" style="font-size:12px;text-transform:capitalize;"><?= $s ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <h6><i class="fa-solid fa-motorcycle me-2 text-primary"></i>Deliveries</h6>
        <a href="<?= BASE_URL ?>modules/orders/new_order.php" class="btn btn-sm btn-primary">
            <i class="fa-solid fa-plus me-1"></i> New Delivery Order
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Order #</th><th>Customer</th><th>Address</th>
                        <th>Amount</th><th>Rider</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($deliveries)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No deliveries yet</td></tr>
                <?php else: foreach ($deliveries as $d): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($d['order_number']) ?></strong><br>
                            <small class="text-muted"><?= date('h:i A', strtotime($d['order_time'])) ?></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($d['cust_name'] ?? '-') ?><br>
                            <small class="text-muted"><?= htmlspecialchars($d['cust_phone'] ?? '') ?></small>
                        </td>
                        <td style="max-width:180px;font-size:12px;">
                            <?= htmlspecialchars($d['delivery_address'] ?: ($d['cust_address'] ?? '-')) ?>
                        </td>
                        <td>&#8377;<?= number_format($d['order_total'] ?? 0, 2) ?></td>
                        <td>
                            <?php if ($d['status'] === 'pending'): ?>
                            <select class="form-select form-select-sm"
                                    onchange="if(this.value) window.location.href='?action=assign&delivery_id=<?= $d['id'] ?>&rider_id='+this.value">
                                <option value="">Assign Rider</option>
                                <?php foreach ($riders as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php else: ?>
                                <?= htmlspecialchars($d['rider_name'] ?? 'Unassigned') ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $badgeClass = match($d['status']) {
                                'delivered' => 'badge-delivered',
                                'pending'   => 'badge-pending',
                                'assigned'  => 'badge-confirmed',
                                'picked'    => 'badge-preparing',
                                'failed'    => 'badge-cancelled',
                                default     => 'badge-pending',
                            };
                            ?>
                            <span class="badge-status <?= $badgeClass ?>"><?= ucfirst($d['status']) ?></span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <?php if ($d['status'] === 'assigned'): ?>
                                    <a href="?action=update&delivery_id=<?= $d['id'] ?>&status=picked"
                                       class="btn btn-sm btn-warning">Picked Up</a>
                                <?php elseif ($d['status'] === 'picked'): ?>
                                    <a href="?action=update&delivery_id=<?= $d['id'] ?>&status=delivered"
                                       class="btn btn-sm btn-success">Delivered</a>
                                <?php elseif ($d['status'] === 'delivered'): ?>
                                    <a href="<?= BASE_URL ?>modules/billing/create_bill.php?order_id=<?= $d['order_id'] ?>"
                                       class="btn-icon" title="Generate Bill">
                                        <i class="fa-solid fa-file-invoice-dollar"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!in_array($d['status'], ['delivered', 'failed'])): ?>
                                    <a href="?action=update&delivery_id=<?= $d['id'] ?>&status=failed"
                                       class="btn-icon text-danger" title="Mark Failed"
                                       onclick="return confirm('Mark as failed?')">
                                        <i class="fa-solid fa-xmark"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>