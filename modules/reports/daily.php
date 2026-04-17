<?php
$pageTitle = 'Daily Report';
require_once '../../includes/header.php';
requireRole(['admin','manager']);
$pdo = getDB();

$date = sanitize($_GET['date'] ?? date('Y-m-d'));

$bills = $pdo->prepare("SELECT b.*, o.order_number, o.order_type, t.table_number, c.name as cust_name FROM bills b JOIN orders o ON b.order_id=o.id LEFT JOIN tables t ON o.table_id=t.id LEFT JOIN customers c ON o.customer_id=c.id WHERE DATE(b.created_at)=? AND b.payment_status='paid' ORDER BY b.created_at");
$bills->execute([$date]);
$bills = $bills->fetchAll();

$totals = ['revenue'=>0,'tax'=>0,'discount'=>0,'bills'=>count($bills)];
foreach ($bills as $b) { $totals['revenue'] += $b['total_amount']; $totals['tax'] += $b['tax_amount']; $totals['discount'] += $b['discount_amount']; }

$restName = getSetting('restaurant_name');
?>

<div class="no-print mb-3 d-flex gap-2 align-items-center">
    <form method="GET" class="d-flex gap-2">
        <input type="date" name="date" class="form-control form-control-sm" value="<?= $date ?>">
        <button class="btn btn-sm btn-primary">View</button>
    </form>
    <button class="btn btn-sm btn-outline-primary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i> Print Report</button>
</div>

<div class="card" id="printArea">
    <div class="card-body">
        <div class="text-center mb-4" style="border-bottom:2px solid #e8e0d8;padding-bottom:16px;">
            <h4 style="font-family:'Playfair Display',serif;"><?= htmlspecialchars($restName) ?></h4>
            <div class="text-muted">Daily Sales Report - <?= date('d F Y', strtotime($date)) ?></div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3 text-center">
                <div style="font-size:24px;font-weight:700;color:#b5451b;">₹<?= number_format($totals['revenue'],2) ?></div>
                <div class="text-muted" style="font-size:12px;">Total Revenue</div>
            </div>
            <div class="col-6 col-md-3 text-center">
                <div style="font-size:24px;font-weight:700;"><?= $totals['bills'] ?></div>
                <div class="text-muted" style="font-size:12px;">Bills Generated</div>
            </div>
            <div class="col-6 col-md-3 text-center">
                <div style="font-size:24px;font-weight:700;">₹<?= number_format($totals['tax'],2) ?></div>
                <div class="text-muted" style="font-size:12px;">Tax Collected</div>
            </div>
            <div class="col-6 col-md-3 text-center">
                <div style="font-size:24px;font-weight:700;">₹<?= number_format($totals['discount'],2) ?></div>
                <div class="text-muted" style="font-size:12px;">Total Discount</div>
            </div>
        </div>

        <table class="table table-sm" style="font-size:13px;">
            <thead>
                <tr><th>#</th><th>Bill No.</th><th>Order</th><th>Type</th><th>Table/Customer</th><th>Payment</th><th>Time</th><th class="text-end">Amount</th></tr>
            </thead>
            <tbody>
                <?php if (empty($bills)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No bills on this date</td></tr>
                <?php else: foreach ($bills as $i => $b): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($b['bill_number']) ?></td>
                    <td><?= htmlspecialchars($b['order_number']) ?></td>
                    <td><?= ucfirst(str_replace('_',' ',$b['order_type'])) ?></td>
                    <td><?= $b['order_type']==='dine_in' ? htmlspecialchars($b['table_number']??'-') : htmlspecialchars($b['cust_name']??'-') ?></td>
                    <td><?= ucfirst($b['payment_method']) ?></td>
                    <td><?= date('h:i A', strtotime($b['created_at'])) ?></td>
                    <td class="text-end fw-600">₹<?= number_format($b['total_amount'],2) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:#faf7f4;font-weight:700;">
                    <td colspan="7" class="text-end">TOTAL:</td>
                    <td class="text-end" style="color:#b5451b;">₹<?= number_format($totals['revenue'],2) ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="text-center text-muted mt-4" style="font-size:11px;">
            Report generated on <?= date('d/m/Y h:i A') ?> &nbsp;|&nbsp; <?= htmlspecialchars($restName) ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
