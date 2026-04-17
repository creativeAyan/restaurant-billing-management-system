<?php
$pageTitle = 'Create Bill';
require_once '../../includes/header.php';
$pdo = getDB();

$orderId = (int)($_GET['order_id'] ?? 0);
if (!$orderId) redirect(BASE_URL . 'modules/orders/orders.php');

// Handle bill creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subtotal       = (float)$_POST['subtotal'];
    $taxAmount      = (float)$_POST['tax_amount'];
    $serviceCharge  = (float)$_POST['service_charge'];
    $deliveryCharge = (float)$_POST['delivery_charge'];
    $discountVal    = (float)$_POST['discount_value'];
    $discountType   = sanitize($_POST['discount_type']);
    $discountAmount = $discountType === 'percent' ? ($subtotal * $discountVal / 100) : $discountVal;
    $total          = $subtotal + $taxAmount + $serviceCharge + $deliveryCharge - $discountAmount;
    $payMethod      = sanitize($_POST['payment_method']);
    $paidAmount     = (float)$_POST['paid_amount'];
    $changeAmount   = max(0, $paidAmount - $total);
    $notes          = sanitize($_POST['notes'] ?? '');
    $billNumber     = generateBillNumber();

    $stmt = $pdo->prepare("INSERT INTO bills 
        (bill_number,order_id,subtotal,tax_amount,discount_amount,discount_type,discount_value,
         service_charge,delivery_charge,total_amount,payment_method,payment_status,paid_amount,change_amount,notes,created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$billNumber, $orderId, $subtotal, $taxAmount, $discountAmount, $discountType, $discountVal,
                    $serviceCharge, $deliveryCharge, $total, $payMethod, 'paid', $paidAmount, $changeAmount, $notes, $_SESSION['user_id']]);
    $billId = $pdo->lastInsertId();

    // Update order status to served
    $pdo->prepare("UPDATE orders SET status='served' WHERE id=?")->execute([$orderId]);
    // Free up table
    $pdo->prepare("UPDATE tables t JOIN orders o ON o.table_id=t.id SET t.status='available' WHERE o.id=?")->execute([$orderId]);

    auditLog('Generate Bill', 'billing', $billId, null, $billNumber . ' — ₹' . number_format($total, 2));
    redirect(BASE_URL . 'modules/billing/receipt.php?bill_id=' . $billId);
}

// Load order
$order = $pdo->prepare("SELECT o.*, t.table_number, c.name as cust_name, c.phone as cust_phone, c.address as cust_address
    FROM orders o LEFT JOIN tables t ON o.table_id=t.id LEFT JOIN customers c ON o.customer_id=c.id WHERE o.id=?");
$order->execute([$orderId]);
$order = $order->fetch();
if (!$order) redirect(BASE_URL . 'modules/orders/orders.php');

// Prevent double-billing: if a paid bill already exists, go to receipt
$existingBill = $pdo->prepare("SELECT id FROM bills WHERE order_id = ? AND payment_status = 'paid' LIMIT 1");
$existingBill->execute([$orderId]);
$paidBill = $existingBill->fetch();
if ($paidBill) {
    flashMessage('error', 'This order has already been billed.');
    redirect(BASE_URL . 'modules/billing/receipt.php?bill_id=' . $paidBill['id']);
}

$orderItems = $pdo->prepare("SELECT oi.*, m.name, m.is_veg FROM order_items oi JOIN menu_items m ON oi.menu_item_id=m.id WHERE oi.order_id=?");
$orderItems->execute([$orderId]);
$orderItems = $orderItems->fetchAll();

$subtotal = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $orderItems));
$taxRate   = (float)getSetting('tax_percent') / 100;
$svcRate   = $order['order_type'] !== 'delivery' ? (float)getSetting('service_charge_percent') / 100 : 0;
$delCharge = $order['order_type'] === 'delivery' ? (float)getSetting('delivery_charge') : 0;
$taxAmount = $subtotal * $taxRate;
$svcAmount = $subtotal * $svcRate;
$defaultTotal = $subtotal + $taxAmount + $svcAmount + $delCharge;
?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h6><i class="fa-solid fa-receipt me-2 text-primary"></i>Order #<?= htmlspecialchars($order['order_number']) ?></h6>
                <span class="badge-status badge-<?= $order['order_type'] === 'dine_in' ? 'confirmed' : 'pending' ?>">
                    <?= ucfirst(str_replace('_',' ',$order['order_type'])) ?>
                </span>
            </div>
            <div class="card-body">
                <?php if ($order['order_type'] === 'dine_in'): ?>
                <div class="mb-2 text-muted" style="font-size:14px;"><i class="fa-solid fa-table-cells me-1"></i> Table: <strong><?= htmlspecialchars($order['table_number'] ?? '-') ?></strong></div>
                <?php else: ?>
                <div class="mb-2 text-muted" style="font-size:14px;"><i class="fa-solid fa-user me-1"></i> Customer: <strong><?= htmlspecialchars($order['cust_name'] ?? '-') ?></strong> &nbsp;|&nbsp; <?= htmlspecialchars($order['cust_phone'] ?? '') ?></div>
                <?php if ($order['order_type'] === 'delivery'): ?>
                <div class="mb-2 text-muted" style="font-size:13px;"><i class="fa-solid fa-location-dot me-1"></i> <?= htmlspecialchars($order['cust_address'] ?? '-') ?></div>
                <?php endif; ?>
                <?php endif; ?>

                <hr>
                <table class="table table-sm">
                    <thead><tr><th>Item</th><th>Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($orderItems as $item): ?>
                    <tr>
                        <td>
                            <span class="veg-dot <?= $item['is_veg'] ? 'veg' : 'non-veg' ?>"></span>
                            <?= htmlspecialchars($item['name']) ?>
                        </td>
                        <td><?= $item['quantity'] ?></td>
                        <td class="text-end">₹<?= number_format($item['unit_price'],2) ?></td>
                        <td class="text-end">₹<?= number_format($item['quantity']*$item['unit_price'],2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="bill-summary">
                    <div class="bill-row"><span>Subtotal</span><span>₹<?= number_format($subtotal,2) ?></span></div>
                    <div class="bill-row"><span>Tax (<?= getSetting('tax_percent') ?>%)</span><span>₹<?= number_format($taxAmount,2) ?></span></div>
                    <?php if ($svcAmount > 0): ?>
                    <div class="bill-row"><span>Service Charge (<?= getSetting('service_charge_percent') ?>%)</span><span>₹<?= number_format($svcAmount,2) ?></span></div>
                    <?php endif; ?>
                    <?php if ($delCharge > 0): ?>
                    <div class="bill-row"><span>Delivery Charge</span><span>₹<?= number_format($delCharge,2) ?></span></div>
                    <?php endif; ?>
                    <div class="bill-row total"><span>GRAND TOTAL</span><span>₹<?= number_format($defaultTotal,2) ?></span></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h6><i class="fa-solid fa-cash-register me-2 text-primary"></i>Payment</h6></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="subtotal" value="<?= $subtotal ?>">
                    <input type="hidden" name="tax_amount" value="<?= $taxAmount ?>">
                    <input type="hidden" name="service_charge" value="<?= $svcAmount ?>">
                    <input type="hidden" name="delivery_charge" value="<?= $delCharge ?>">

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Discount Type</label>
                            <select name="discount_type" class="form-select form-select-sm" id="discountTypeSelect" onchange="recalc()">
                                <option value="fixed">Fixed (₹)</option>
                                <option value="percent">Percent (%)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Discount Value</label>
                            <input type="number" name="discount_value" class="form-control form-control-sm" id="discountInput" value="0" min="0" oninput="recalc()">
                        </div>
                    </div>

                    <div class="bill-summary mb-3">
                        <div class="bill-row"><span>Subtotal</span><span>₹<?= number_format($subtotal,2) ?></span></div>
                        <div class="bill-row"><span>Tax + Service + Delivery</span><span>₹<?= number_format($taxAmount+$svcAmount+$delCharge,2) ?></span></div>
                        <div class="bill-row"><span>Discount</span><span id="discountDisplay">₹0.00</span></div>
                        <div class="bill-row total"><span>TOTAL</span><span id="grandTotalDisplay">₹<?= number_format($defaultTotal,2) ?></span></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <div class="row g-2">
                            <?php foreach (['cash'=>'💵 Cash','card'=>'💳 Card','upi'=>'📱 UPI','wallet'=>'👛 Wallet'] as $val => $label): ?>
                            <div class="col-6">
                                <div class="form-check" style="border:1px solid #e8e0d8;border-radius:8px;padding:10px 10px 10px 34px;">
                                    <input class="form-check-input" type="radio" name="payment_method" value="<?= $val ?>" id="pay_<?= $val ?>" <?= $val==='cash'?'checked':'' ?>>
                                    <label class="form-check-label" for="pay_<?= $val ?>"><?= $label ?></label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Amount Received (₹)</label>
                        <input type="number" name="paid_amount" class="form-control" id="paidAmountInput" placeholder="Enter amount received" step="0.01">
                        <div class="mt-1" style="font-size:13px;">Change: <strong id="changeDisplay">₹0.00</strong></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes (optional)</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Any payment notes">
                    </div>

                    <button type="submit" class="btn btn-primary w-100 btn-lg">
                        <i class="fa-solid fa-check-circle me-2"></i> Confirm Payment & Print Receipt
                    </button>
                    <input type="hidden" name="final_total" id="hiddenTotal" value="<?= $defaultTotal ?>">
                    <a href="<?= BASE_URL ?>modules/orders/orders.php" class="btn btn-outline-secondary w-100 mt-2">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back to Orders
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const baseTotal = <?= $defaultTotal ?>;
const subtotal  = <?= $subtotal ?>;

function recalc() {
    const type = document.getElementById('discountTypeSelect').value;
    const val  = parseFloat(document.getElementById('discountInput').value) || 0;
    const disc = type === 'percent' ? (subtotal * val / 100) : val;
    const total = Math.max(0, baseTotal - disc);
    document.getElementById('discountDisplay').textContent = '₹' + disc.toFixed(2);
    document.getElementById('grandTotalDisplay').textContent = '₹' + total.toFixed(2);
    document.getElementById('hiddenTotal').value = total.toFixed(2);
    updateChange();
}

function updateChange() {
    const paid  = parseFloat(document.getElementById('paidAmountInput').value) || 0;
    const total = parseFloat(document.getElementById('hiddenTotal').value) || baseTotal;
    const change = paid - total;
    const el = document.getElementById('changeDisplay');
    el.textContent = '₹' + Math.max(0, change).toFixed(2);
    el.style.color = change >= 0 ? '#2d7d4f' : '#c0392b';
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('paidAmountInput').addEventListener('input', updateChange);
});
</script>

<?php require_once '../../includes/footer.php'; ?>
