<?php
$pageTitle = 'All Bills';
require_once '../../includes/header.php';
$pdo = getDB();

$dateFilter = sanitize($_GET['date'] ?? date('Y-m-d'));
$bills = $pdo->prepare("
    SELECT b.*, o.order_number, o.order_type, t.table_number, c.name as cust_name, u.name as staff_name
    FROM bills b
    JOIN orders o ON b.order_id = o.id
    LEFT JOIN tables t ON o.table_id = t.id
    LEFT JOIN customers c ON o.customer_id = c.id
    LEFT JOIN users u ON b.created_by = u.id
    WHERE DATE(b.created_at) = ?
    ORDER BY b.created_at DESC
");
$bills->execute([$dateFilter]);
$bills = $bills->fetchAll();
$dayTotal = array_sum(array_column($bills, 'total_amount'));
?>

<div class="card">
    <div class="card-header">
        <h6><i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i>Bills</h6>
        <div class="d-flex gap-2 align-items-center">
            <span class="fw-600 text-primary">₹<?= number_format($dayTotal,2) ?> today</span>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" class="mb-3 d-flex gap-2">
            <input type="date" name="date" class="form-control form-control-sm" style="width:auto" value="<?= $dateFilter ?>">
            <button class="btn btn-sm btn-primary" type="submit">Filter</button>
            <a href="bills.php" class="btn btn-sm btn-outline-secondary">Today</a>
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr><th>Bill #</th><th>Order #</th><th>Type</th><th>Table/Customer</th><th>Total</th><th>Payment</th><th>Staff</th><th>Time</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($bills)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No bills found for this date</td></tr>
                    <?php else: foreach ($bills as $b): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($b['bill_number']) ?></strong></td>
                        <td><?= htmlspecialchars($b['order_number']) ?></td>
                        <td>
                            <?php if ($b['order_type']==='dine_in'): ?><span class="badge bg-primary">Dine-In</span>
                            <?php elseif ($b['order_type']==='delivery'): ?><span class="badge bg-warning text-dark">Delivery</span>
                            <?php else: ?><span class="badge bg-info text-dark">Takeaway</span><?php endif; ?>
                        </td>
                        <td><?= $b['order_type']==='dine_in' ? htmlspecialchars($b['table_number']??'-') : htmlspecialchars($b['cust_name']??'-') ?></td>
                        <td><strong>₹<?= number_format($b['total_amount'],2) ?></strong></td>
                        <td><span class="badge-status badge-paid"><?= ucfirst($b['payment_method']) ?></span></td>
                        <td><?= htmlspecialchars($b['staff_name']??'-') ?></td>
                        <td><?= date('h:i A', strtotime($b['created_at'])) ?></td>
                        <td>
                            <a href="receipt.php?bill_id=<?= $b['id'] ?>" class="btn-icon" title="Print Receipt"><i class="fa-solid fa-print"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($bills)): ?>
                <tfoot>
                    <tr style="background:#faf7f4;">
                        <td colspan="4" class="text-end fw-600">Day Total:</td>
                        <td colspan="5" class="fw-700 text-primary">₹<?= number_format($dayTotal,2) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
