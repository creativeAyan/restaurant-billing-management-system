<?php
$pageTitle = 'All Orders';
require_once '../../includes/header.php';
$pdo = getDB();

// Handle status update
if (isset($_GET['action']) && $_GET['action'] === 'update' && isset($_GET['id'], $_GET['status'])) {
    $newStatus = sanitize($_GET['status']);
    $oid = (int)$_GET['id'];
    $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$newStatus, $oid]);
    if ($newStatus === 'cancelled') {
        $pdo->prepare("UPDATE tables t JOIN orders o ON o.table_id = t.id SET t.status='available' WHERE o.id = ?")->execute([$oid]);
    }
    auditLog('Update Order Status', 'orders', $oid, null, $newStatus);
    flashMessage('success', 'Order status updated.');
    // Return to view_order if caller was there
    if (!empty($_GET['ret']) && $_GET['ret'] === 'view') {
        redirect(BASE_URL . 'modules/orders/view_order.php?id=' . (int)$_GET['id']);
    }
    redirect(BASE_URL . 'modules/orders/orders.php');
}

// Filters
$statusFilter = sanitize($_GET['status'] ?? '');
$typeFilter   = sanitize($_GET['type'] ?? '');
$dateFilter   = sanitize($_GET['date'] ?? date('Y-m-d'));

$where = ["DATE(o.created_at) = :date"];
$params = [':date' => $dateFilter];
if ($statusFilter) { $where[] = "o.status = :status"; $params[':status'] = $statusFilter; }
if ($typeFilter)   { $where[] = "o.order_type = :type"; $params[':type'] = $typeFilter; }

$sql = "SELECT o.*, t.table_number, u.name as waiter_name, c.name as customer_name,
        (SELECT SUM(oi.quantity*oi.unit_price) FROM order_items oi WHERE oi.order_id=o.id) as subtotal
        FROM orders o
        LEFT JOIN tables t ON o.table_id=t.id
        LEFT JOIN users u ON o.waiter_id=u.id
        LEFT JOIN customers c ON o.customer_id=c.id
        WHERE " . implode(' AND ', $where) . " ORDER BY o.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>

<div class="card">
    <div class="card-header">
        <h6><i class="fa-solid fa-receipt me-2 text-primary"></i>Orders</h6>
        <a href="<?= BASE_URL ?>modules/orders/new_order.php" class="btn btn-sm btn-primary">
            <i class="fa-solid fa-plus me-1"></i> New Order
        </a>
    </div>
    <div class="card-body">
        <!-- Filters -->
        <form method="GET" class="row g-2 mb-3 no-print">
            <div class="col-auto">
                <input type="date" name="date" class="form-control form-control-sm" value="<?= $dateFilter ?>">
            </div>
            <div class="col-auto">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <?php foreach (['pending','confirmed','preparing','ready','served','cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <option value="dine_in" <?= $typeFilter==='dine_in'?'selected':'' ?>>Dine-In</option>
                    <option value="delivery" <?= $typeFilter==='delivery'?'selected':'' ?>>Delivery</option>
                    <option value="takeaway" <?= $typeFilter==='takeaway'?'selected':'' ?>>Takeaway</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
                <a href="orders.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Type</th>
                        <th>Table / Customer</th>
                        <th>Items Amount</th>
                        <th>Waiter</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No orders found</td></tr>
                    <?php else: foreach ($orders as $o): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($o['order_number']) ?></strong></td>
                        <td>
                            <?php if ($o['order_type']==='dine_in'): ?><span class="badge bg-primary">Dine-In</span>
                            <?php elseif ($o['order_type']==='delivery'): ?><span class="badge bg-warning text-dark">Delivery</span>
                            <?php else: ?><span class="badge bg-info text-dark">Takeaway</span><?php endif; ?>
                        </td>
                        <td><?= $o['order_type']==='dine_in' ? htmlspecialchars($o['table_number']??'-') : htmlspecialchars($o['customer_name']??'-') ?></td>
                        <td>₹<?= number_format($o['subtotal']??0, 2) ?></td>
                        <td><?= htmlspecialchars($o['waiter_name']??'-') ?></td>
                        <td><?= date('h:i A', strtotime($o['created_at'])) ?></td>
                        <td><span class="badge-status badge-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="view_order.php?id=<?= $o['id'] ?>" class="btn-icon" title="View"><i class="fa-solid fa-eye"></i></a>
                                <?php
                                $orderIsOpen  = in_array($o['status'], ['confirmed','preparing','ready']);
                                $orderDineIn  = $o['order_type'] === 'dine_in';
                                if ($orderIsOpen && $orderDineIn): ?>
                                    <a href="add_items.php?order_id=<?= $o['id'] ?>" class="btn-icon" title="Add Items" style="color:#2d7d4f;">
                                        <i class="fa-solid fa-plus"></i>
                                    </a>
                                    <a href="<?= BASE_URL ?>modules/billing/create_bill.php?order_id=<?= $o['id'] ?>"
                                       class="btn-icon" title="Generate Bill" style="color:#b5451b;"
                                       onclick="return confirm('Generate the final bill for this table?')">
                                        <i class="fa-solid fa-file-invoice-dollar"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="<?= BASE_URL ?>modules/billing/create_bill.php?order_id=<?= $o['id'] ?>" class="btn-icon" title="Bill"><i class="fa-solid fa-file-invoice-dollar"></i></a>
                                <?php endif; ?>
                                <?php if ($o['status'] !== 'served' && $o['status'] !== 'cancelled'): ?>
                                <div class="dropdown">
                                    <button class="btn-icon dropdown-toggle" data-bs-toggle="dropdown" style="border:none;background:none;color:#7a6e68;cursor:pointer;">
                                        <i class="fa-solid fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php foreach (['confirmed','preparing','ready','served'] as $s): ?>
                                        <li><a class="dropdown-item" href="?action=update&id=<?= $o['id'] ?>&status=<?= $s ?>">Mark <?= ucfirst($s) ?></a></li>
                                        <?php endforeach; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="?action=update&id=<?= $o['id'] ?>&status=cancelled" onclick="return confirm('Cancel this order?')">Cancel Order</a></li>
                                    </ul>
                                </div>
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
