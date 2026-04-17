<?php
session_start();
require_once '../../includes/config.php';
requireLogin();

$pdo = getDB();
$billId = (int)($_GET['bill_id'] ?? 0);
if (!$billId) redirect(BASE_URL . 'modules/billing/bills.php');

$bill = $pdo->prepare("SELECT b.*, o.order_number, o.order_type, o.special_instructions,
    t.table_number, c.name as cust_name, c.phone as cust_phone, c.address as cust_address,
    u.name as staff_name
    FROM bills b
    JOIN orders o ON b.order_id = o.id
    LEFT JOIN tables t ON o.table_id = t.id
    LEFT JOIN customers c ON o.customer_id = c.id
    LEFT JOIN users u ON b.created_by = u.id
    WHERE b.id = ?");
$bill->execute([$billId]);
$bill = $bill->fetch();
if (!$bill) redirect(BASE_URL . 'modules/billing/bills.php');

$items = $pdo->prepare("SELECT oi.*, m.name, m.is_veg FROM order_items oi JOIN menu_items m ON oi.menu_item_id=m.id WHERE oi.order_id=?");
$items->execute([$bill['order_id']]);
$items = $items->fetchAll();

$restName    = getSetting('restaurant_name');
$restAddress = getSetting('restaurant_address');
$restPhone   = getSetting('restaurant_phone');
$gstNo       = getSetting('gst_number');
$footer      = getSetting('receipt_footer');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt - <?= htmlspecialchars($bill['bill_number']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', 'Courier New', monospace; background: #f0f0f0; display: flex;
            align-items: flex-start; justify-content: center; padding: 20px; min-height: 100vh; }
        .receipt-wrap { background: #fff; width: 320px; padding: 0; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
        .receipt { padding: 20px 16px; }
        .r-header { text-align: center; border-bottom: 2px dashed #333; padding-bottom: 12px; margin-bottom: 12px; }
        .r-name { font-size: 18px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .r-addr { font-size: 11px; color: #555; line-height: 1.5; margin-top: 4px; }
        .r-gst { font-size: 10px; color: #777; margin-top: 4px; }
        .r-info { font-size: 11px; margin-bottom: 12px; border-bottom: 1px dashed #ccc; padding-bottom: 10px; }
        .r-info table { width: 100%; }
        .r-info td { padding: 2px 0; }
        .r-info td:last-child { text-align: right; }
        .r-items { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 10px; }
        .r-items th { border-bottom: 1px solid #333; padding: 4px 2px; text-align: left; font-size: 11px; text-transform: uppercase; }
        .r-items th:last-child, .r-items td:last-child { text-align: right; }
        .r-items th:nth-child(2), .r-items td:nth-child(2) { text-align: center; }
        .r-items td { padding: 4px 2px; border-bottom: 1px dotted #ddd; }
        .r-totals { border-top: 1px dashed #333; padding-top: 8px; font-size: 12px; }
        .r-totals table { width: 100%; }
        .r-totals td { padding: 3px 0; }
        .r-totals td:last-child { text-align: right; }
        .r-grand { font-size: 15px; font-weight: 700; border-top: 2px solid #333; padding-top: 6px; margin-top: 4px; }
        .r-grand td { padding: 4px 0; }
        .r-payment { font-size: 11px; margin-top: 10px; border-top: 1px dashed #ccc; padding-top: 8px; }
        .r-footer { text-align: center; font-size: 11px; color: #555; margin-top: 12px;
            border-top: 2px dashed #333; padding-top: 10px; line-height: 1.6; }
        .r-barcode { text-align: center; font-size: 9px; color: #999; margin-top: 6px; letter-spacing: 2px; }
        .action-bar { background: #1e1510; padding: 12px 16px; display: flex; gap: 8px; justify-content: center; }
        .action-bar button, .action-bar a { padding: 8px 18px; border-radius: 6px; font-size: 13px;
            font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-print { background: #b5451b; color: #fff; border: none; }
        .btn-back { background: transparent; color: #e8ddd4; border: 1px solid #9a8880; }
        .btn-new { background: #d4a853; color: #1e1510; border: none; }
        @media print {
            body { background: none; padding: 0; }
            .action-bar { display: none; }
            .receipt-wrap { box-shadow: none; width: 100%; }
        }
    </style>
</head>
<body>
<div class="receipt-wrap">
    <div class="action-bar no-print">
        <button class="btn-print" onclick="window.print()">🖨️ Print</button>
        <a href="<?= BASE_URL ?>modules/orders/new_order.php" class="btn-new">➕ New Order</a>
        <a href="<?= BASE_URL ?>dashboard.php" class="btn-back">← Dashboard</a>
    </div>
    <div class="receipt">
        <!-- Header -->
        <div class="r-header">
            <div class="r-name"><?= htmlspecialchars($restName) ?></div>
            <div class="r-addr"><?= nl2br(htmlspecialchars($restAddress)) ?><br><?= htmlspecialchars($restPhone) ?></div>
            <?php if ($gstNo): ?><div class="r-gst">GST: <?= htmlspecialchars($gstNo) ?></div><?php endif; ?>
        </div>

        <!-- Order Info -->
        <div class="r-info">
            <table>
                <tr><td>Bill No:</td><td><strong><?= htmlspecialchars($bill['bill_number']) ?></strong></td></tr>
                <tr><td>Order No:</td><td><?= htmlspecialchars($bill['order_number']) ?></td></tr>
                <tr><td>Type:</td><td><?= ucfirst(str_replace('_',' ',$bill['order_type'])) ?></td></tr>
                <?php if ($bill['order_type']==='dine_in' && $bill['table_number']): ?>
                <tr><td>Table:</td><td><?= htmlspecialchars($bill['table_number']) ?></td></tr>
                <?php elseif ($bill['cust_name']): ?>
                <tr><td>Customer:</td><td><?= htmlspecialchars($bill['cust_name']) ?></td></tr>
                <tr><td>Phone:</td><td><?= htmlspecialchars($bill['cust_phone']??'') ?></td></tr>
                <?php endif; ?>
                <tr><td>Date:</td><td><?= date('d/m/Y h:i A', strtotime($bill['created_at'])) ?></td></tr>
                <tr><td>Served by:</td><td><?= htmlspecialchars($bill['staff_name']??'') ?></td></tr>
            </table>
        </div>

        <!-- Items -->
        <table class="r-items">
            <thead>
                <tr><th>Item</th><th>Qty</th><th>Rate</th><th>Amt</th></tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td>₹<?= number_format($item['unit_price'],2) ?></td>
                    <td>₹<?= number_format($item['quantity']*$item['unit_price'],2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="r-totals">
            <table>
                <tr><td>Subtotal</td><td>₹<?= number_format($bill['subtotal'],2) ?></td></tr>
                <tr><td>Tax</td><td>₹<?= number_format($bill['tax_amount'],2) ?></td></tr>
                <?php if ($bill['service_charge'] > 0): ?>
                <tr><td>Service Charge</td><td>₹<?= number_format($bill['service_charge'],2) ?></td></tr>
                <?php endif; ?>
                <?php if ($bill['delivery_charge'] > 0): ?>
                <tr><td>Delivery Charge</td><td>₹<?= number_format($bill['delivery_charge'],2) ?></td></tr>
                <?php endif; ?>
                <?php if ($bill['discount_amount'] > 0): ?>
                <tr><td>Discount</td><td>-₹<?= number_format($bill['discount_amount'],2) ?></td></tr>
                <?php endif; ?>
            </table>
            <table class="r-grand">
                <tr><td><strong>TOTAL</strong></td><td><strong>₹<?= number_format($bill['total_amount'],2) ?></strong></td></tr>
            </table>
        </div>

        <!-- Payment -->
        <div class="r-payment">
            <table style="width:100%">
                <tr><td>Payment Method:</td><td style="text-align:right"><?= ucfirst($bill['payment_method']) ?></td></tr>
                <tr><td>Amount Paid:</td><td style="text-align:right">₹<?= number_format($bill['paid_amount'],2) ?></td></tr>
                <?php if ($bill['change_amount'] > 0): ?>
                <tr><td>Change:</td><td style="text-align:right">₹<?= number_format($bill['change_amount'],2) ?></td></tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- Footer -->
        <div class="r-footer">
            <strong><?= htmlspecialchars($footer) ?></strong>
            <?php if ($bill['special_instructions']): ?>
            <br><small>Note: <?= htmlspecialchars($bill['special_instructions']) ?></small>
            <?php endif; ?>
        </div>
        <div class="r-barcode"><?= str_repeat('|', 24) ?> <?= htmlspecialchars($bill['bill_number']) ?></div>
    </div>
</div>
</body>
</html>
