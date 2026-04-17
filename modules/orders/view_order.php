<?php
$pageTitle = 'Order Details';
require_once '../../includes/header.php';
$pdo = getDB();

$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) redirect(BASE_URL . 'modules/orders/orders.php');

// ── Handle inline item actions (POST) ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';
    $orderItemId = (int)($_POST['order_item_id'] ?? 0);

    // Verify the order_item belongs to this order and order is still open & unbilled
    $checkStmt = $pdo->prepare(
        "SELECT oi.id, oi.order_id, o.status
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         WHERE oi.id = ? AND oi.order_id = ?"
    );
    $checkStmt->execute([$orderItemId, $orderId]);
    $checkRow = $checkStmt->fetch();

    $billCheck = $pdo->prepare("SELECT id FROM bills WHERE order_id = ? AND payment_status = 'paid' LIMIT 1");
    $billCheck->execute([$orderId]);
    $alreadyBilled = $billCheck->fetch();

    if ($checkRow && !$alreadyBilled && in_array($checkRow['status'], ['confirmed','preparing','ready'])) {
        if ($action === 'delete') {
            $pdo->prepare("DELETE FROM order_items WHERE id = ?")->execute([$orderItemId]);
            flashMessage('success', 'Item removed from order.');

        } elseif ($action === 'update_qty') {
            $newQty = max(1, (int)($_POST['new_qty'] ?? 1));
            $pdo->prepare("UPDATE order_items SET quantity = ? WHERE id = ?")->execute([$newQty, $orderItemId]);
            flashMessage('success', 'Quantity updated.');
        }
    } else {
        flashMessage('error', 'Cannot modify a billed or closed order.');
    }

    redirect(BASE_URL . 'modules/orders/view_order.php?id=' . $orderId);
}

// ── Load order ────────────────────────────────────────────────────────────────
$order = $pdo->prepare(
    "SELECT o.*, t.table_number, c.name as cust_name, c.phone as cust_phone,
            c.address as cust_address, u.name as waiter_name
     FROM orders o
     LEFT JOIN tables t  ON o.table_id    = t.id
     LEFT JOIN customers c ON o.customer_id = c.id
     LEFT JOIN users u     ON o.waiter_id   = u.id
     WHERE o.id = ?"
);
$order->execute([$orderId]);
$order = $order->fetch();
if (!$order) redirect(BASE_URL . 'modules/orders/orders.php');

$items = $pdo->prepare(
    "SELECT oi.*, m.name, m.is_veg
     FROM order_items oi
     JOIN menu_items m ON oi.menu_item_id = m.id
     WHERE oi.order_id = ?
     ORDER BY oi.id"
);
$items->execute([$orderId]);
$items    = $items->fetchAll();
$subtotal = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $items));

$bill = $pdo->prepare("SELECT * FROM bills WHERE order_id = ? LIMIT 1");
$bill->execute([$orderId]);
$bill = $bill->fetch();

$taxRate   = (float)getSetting('tax_percent') / 100;
$svcRate   = $order['order_type'] !== 'delivery' ? (float)getSetting('service_charge_percent') / 100 : 0;
$delCharge = $order['order_type'] === 'delivery'  ? (float)getSetting('delivery_charge') : 0;
$taxAmount = $subtotal * $taxRate;
$svcAmount = $subtotal * $svcRate;
$estTotal  = $subtotal + $taxAmount + $svcAmount + $delCharge;

$isOpen   = in_array($order['status'], ['confirmed', 'preparing', 'ready']);
$isDineIn = $order['order_type'] === 'dine_in';
$isBilled = (bool)$bill;
$canEdit  = $isOpen && !$isBilled;   // editable only when open & unpaid
?>

<div class="row g-3">
    <!-- Left: order items -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h6>
                    <i class="fa-solid fa-receipt me-2 text-primary"></i>
                    Order #<?= htmlspecialchars($order['order_number']) ?>
                </h6>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge-status badge-<?= $order['status'] ?>">
                        <?= ucfirst($order['status']) ?>
                    </span>
                    <?php if ($isDineIn): ?>
                        <span class="badge bg-primary">Dine-In</span>
                    <?php elseif ($order['order_type'] === 'delivery'): ?>
                        <span class="badge bg-warning text-dark">Delivery</span>
                    <?php else: ?>
                        <span class="badge bg-info text-dark">Takeaway</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <!-- Order meta -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <?php if ($isDineIn): ?>
                            <div><strong>Table:</strong> <?= htmlspecialchars($order['table_number'] ?? '-') ?></div>
                        <?php else: ?>
                            <div><strong>Customer:</strong> <?= htmlspecialchars($order['cust_name'] ?? '-') ?></div>
                            <div><strong>Phone:</strong>    <?= htmlspecialchars($order['cust_phone'] ?? '-') ?></div>
                            <?php if ($order['order_type'] === 'delivery'): ?>
                            <div><strong>Address:</strong>  <?= htmlspecialchars($order['cust_address'] ?? '-') ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div><strong>Waiter:</strong> <?= htmlspecialchars($order['waiter_name'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div><strong>Order Time:</strong>
                            <?= date('d M Y h:i A', strtotime($order['created_at'])) ?></div>
                        <?php if ($order['updated_at'] !== $order['created_at']): ?>
                        <div class="text-muted" style="font-size:12px;">
                            Last updated: <?= date('h:i A', strtotime($order['updated_at'])) ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($order['special_instructions']): ?>
                        <div class="mt-2 p-2" style="background:#fef3cd;border-radius:6px;font-size:13px;">
                            <strong>Note:</strong> <?= htmlspecialchars($order['special_instructions']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Items table -->
                <?php if ($canEdit): ?>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span style="font-size:12px;color:#999;">
                        <i class="fa-solid fa-pencil fa-xs me-1"></i>
                        Click <strong>–</strong> / <strong>+</strong> to change quantity, or
                        <i class="fa-solid fa-trash fa-xs" style="color:#c0392b;"></i> to remove an item.
                    </span>
                </div>
                <?php endif; ?>

                <table class="table align-middle" id="orderItemsTable">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Total</th>
                            <?php if ($canEdit): ?><th class="text-center" style="width:80px;">Remove</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                        <tr id="emptyRow">
                            <td colspan="<?= $canEdit ? 5 : 4 ?>" class="text-center text-muted py-3">
                                No items on this order.
                            </td>
                        </tr>
                        <?php else: foreach ($items as $item):
                            $lineTotal = $item['quantity'] * $item['unit_price'];
                        ?>
                        <tr id="item-row-<?= $item['id'] ?>">
                            <td>
                                <span class="veg-dot <?= $item['is_veg'] ? 'veg' : 'non-veg' ?>"></span>
                                <?= htmlspecialchars($item['name']) ?>
                            </td>
                            <td class="text-center">
                                <?php if ($canEdit): ?>
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <button type="button"
                                            class="qty-btn"
                                            onclick="changeQtyInline(<?= $item['id'] ?>, -1, <?= $item['unit_price'] ?>)"
                                            style="width:26px;height:26px;font-size:14px;">–</button>
                                    <span id="qty-display-<?= $item['id'] ?>" style="min-width:24px;text-align:center;font-weight:600;">
                                        <?= $item['quantity'] ?>
                                    </span>
                                    <button type="button"
                                            class="qty-btn"
                                            onclick="changeQtyInline(<?= $item['id'] ?>, 1, <?= $item['unit_price'] ?>)"
                                            style="width:26px;height:26px;font-size:14px;">+</button>
                                </div>
                                <?php else: ?>
                                    <?= $item['quantity'] ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">₹<?= number_format($item['unit_price'], 2) ?></td>
                            <td class="text-end" id="line-total-<?= $item['id'] ?>">
                                ₹<?= number_format($lineTotal, 2) ?>
                            </td>
                            <?php if ($canEdit): ?>
                            <td class="text-center">
                                <button type="button"
                                        onclick="removeItem(<?= $item['id'] ?>, '<?= addslashes($item['name']) ?>')"
                                        class="btn-icon"
                                        style="color:#c0392b;"
                                        title="Remove item">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="<?= $canEdit ? 3 : 2 ?>" class="text-end fw-600">Subtotal:</td>
                            <td class="text-end fw-700" id="liveSubtotal">
                                ₹<?= number_format($subtotal, 2) ?>
                            </td>
                            <?php if ($canEdit): ?><td></td><?php endif; ?>
                        </tr>
                    </tfoot>
                </table>

                <!-- Estimate preview -->
                <?php if ($isOpen && !$isBilled && !empty($items)): ?>
                <div class="bill-summary mt-2"
                     style="background:#f8f4f0;border-radius:8px;padding:12px 16px;">
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#999;margin-bottom:8px;">
                        Running Estimate (not finalized)
                    </div>
                    <div class="bill-row">
                        <span>Subtotal</span>
                        <span id="estSubtotal">₹<?= number_format($subtotal, 2) ?></span>
                    </div>
                    <div class="bill-row">
                        <span>Tax (<?= getSetting('tax_percent') ?>%)</span>
                        <span id="estTax">₹<?= number_format($taxAmount, 2) ?></span>
                    </div>
                    <?php if ($svcAmount > 0): ?>
                    <div class="bill-row">
                        <span>Service Charge (<?= getSetting('service_charge_percent') ?>%)</span>
                        <span id="estService">₹<?= number_format($svcAmount, 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($delCharge > 0): ?>
                    <div class="bill-row">
                        <span>Delivery Charge</span>
                        <span>₹<?= number_format($delCharge, 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="bill-row total">
                        <span>Estimated Total</span>
                        <span id="estTotal">₹<?= number_format($estTotal, 2) ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Hidden save-qty form (submitted by JS) -->
                <?php if ($canEdit): ?>
                <form method="POST" id="qtyForm" style="display:none;">
                    <input type="hidden" name="action" value="update_qty">
                    <input type="hidden" name="order_item_id" id="qtyFormItemId">
                    <input type="hidden" name="new_qty" id="qtyFormNewQty">
                </form>
                <!-- Hidden delete form -->
                <form method="POST" id="deleteForm" style="display:none;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="order_item_id" id="deleteFormItemId">
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right: Actions -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h6>Actions</h6></div>
            <div class="card-body d-flex flex-column gap-2">

                <?php if ($isBilled): ?>
                    <div class="alert alert-success py-2 mb-0" style="font-size:13px;">
                        <i class="fa-solid fa-circle-check me-1"></i>
                        Bill <strong><?= htmlspecialchars($bill['bill_number']) ?></strong> settled.
                    </div>
                    <a href="<?= BASE_URL ?>modules/billing/receipt.php?bill_id=<?= $bill['id'] ?>"
                       class="btn btn-success w-100">
                        <i class="fa-solid fa-print me-2"></i> Print Receipt
                    </a>
                    <div class="bill-summary">
                        <div class="bill-row"><span>Total</span>
                            <span>₹<?= number_format($bill['total_amount'], 2) ?></span></div>
                        <div class="bill-row"><span>Payment</span>
                            <span><?= ucfirst($bill['payment_method']) ?></span></div>
                        <div class="bill-row total"><span>Status</span>
                            <span class="badge-status badge-paid">Paid</span></div>
                    </div>

                <?php elseif ($isOpen && $isDineIn): ?>
                    <a href="<?= BASE_URL ?>modules/orders/add_items.php?order_id=<?= $orderId ?>"
                       class="btn btn-primary w-100">
                        <i class="fa-solid fa-plus me-2"></i> Add More Items
                    </a>
                    <div class="text-muted text-center" style="font-size:11px;">
                        — add starters, mains, desserts separately —
                    </div>
                    <hr class="my-1">
                    <?php if (!empty($items)): ?>
                    <a href="<?= BASE_URL ?>modules/billing/create_bill.php?order_id=<?= $orderId ?>"
                       class="btn btn-warning w-100"
                       onclick="return confirm('Generate the final bill?\nNo more items can be added after this.')">
                        <i class="fa-solid fa-file-invoice-dollar me-2"></i> Generate Bill &amp; Collect Payment
                    </a>
                    <?php else: ?>
                    <button class="btn btn-warning w-100" disabled>
                        <i class="fa-solid fa-file-invoice-dollar me-2"></i> Generate Bill (add items first)
                    </button>
                    <?php endif; ?>
                    <hr class="my-1">
                    <div style="font-size:12px;color:#999;text-transform:uppercase;letter-spacing:.5px;">Kitchen Status</div>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach (['confirmed' => 'Confirmed', 'preparing' => 'Preparing', 'ready' => 'Ready'] as $s => $label): ?>
                        <a href="orders.php?action=update&id=<?= $orderId ?>&status=<?= $s ?>"
                           class="btn btn-sm <?= $order['status'] === $s ? 'btn-secondary' : 'btn-outline-secondary' ?>">
                           <?= $label ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <a href="orders.php?action=update&id=<?= $orderId ?>&status=cancelled"
                       class="btn btn-outline-danger w-100 btn-sm"
                       onclick="return confirm('Cancel this entire order?')">
                        <i class="fa-solid fa-ban me-1"></i> Cancel Order
                    </a>

                <?php elseif ($isOpen && !$isDineIn): ?>
                    <a href="<?= BASE_URL ?>modules/billing/create_bill.php?order_id=<?= $orderId ?>"
                       class="btn btn-primary w-100">
                        <i class="fa-solid fa-file-invoice-dollar me-2"></i> Generate Bill
                    </a>

                <?php else: ?>
                    <div class="alert alert-secondary py-2 mb-0" style="font-size:13px;">
                        Order is <strong><?= ucfirst($order['status']) ?></strong>.
                    </div>
                <?php endif; ?>

                <a href="<?= BASE_URL ?>modules/orders/orders.php"
                   class="btn btn-outline-secondary w-100">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Orders
                </a>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h6 style="font-size:13px;">Order Summary</h6></div>
            <div class="card-body py-2">
                <div style="font-size:13px;" class="d-flex flex-column gap-1">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Items (<?= count($items) ?> line(s)):</span>
                        <span id="summarySubtotal">₹<?= number_format($subtotal, 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Tax + Charges:</span>
                        <span id="summaryCharges">₹<?= number_format($taxAmount + $svcAmount + $delCharge, 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between fw-700 border-top pt-1 mt-1">
                        <span><?= $isBilled ? 'Billed Total' : 'Est. Total' ?>:</span>
                        <span id="summaryTotal">₹<?= number_format($isBilled ? $bill['total_amount'] : $estTotal, 2) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($canEdit): ?>
<script>
const TAX_RATE     = <?= $taxRate ?>;
const SVC_RATE     = <?= $svcRate ?>;
const DEL_CHARGE   = <?= $delCharge ?>;

// Per-row qty tracking (starts at DB values)
const qtys  = {};
const prices = {};
<?php foreach ($items as $item): ?>
qtys[<?= $item['id'] ?>]   = <?= $item['quantity'] ?>;
prices[<?= $item['id'] ?>] = <?= $item['unit_price'] ?>;
<?php endforeach; ?>

function fmt(n) { return '₹' + n.toFixed(2); }

function recalcTotals() {
    let sub = 0;
    for (const id in qtys) sub += qtys[id] * prices[id];
    const tax = sub * TAX_RATE;
    const svc = sub * SVC_RATE;
    const est = sub + tax + svc + DEL_CHARGE;

    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = fmt(v); };
    set('liveSubtotal', sub);
    set('estSubtotal',  sub);
    set('estTax',       tax);
    if (document.getElementById('estService')) set('estService', svc);
    set('estTotal',     est);
    set('summarySubtotal', sub);
    set('summaryCharges',  tax + svc + DEL_CHARGE);
    set('summaryTotal',    est);
}

function changeQtyInline(itemId, delta, unitPrice) {
    const current = qtys[itemId] || 1;
    const newQty  = current + delta;

    if (newQty < 1) {
        // Treat decrement below 1 as a remove
        removeItem(itemId, document.querySelector('#item-row-' + itemId + ' .veg-dot')?.nextSibling?.textContent?.trim() || 'this item');
        return;
    }

    qtys[itemId] = newQty;
    document.getElementById('qty-display-' + itemId).textContent = newQty;
    document.getElementById('line-total-' + itemId).textContent  = fmt(newQty * unitPrice);
    recalcTotals();

    // Debounce the actual save so rapid clicks don't flood the server
    clearTimeout(window['qtyTimer_' + itemId]);
    window['qtyTimer_' + itemId] = setTimeout(() => saveQty(itemId, newQty), 600);
}

function saveQty(itemId, qty) {
    document.getElementById('qtyFormItemId').value = itemId;
    document.getElementById('qtyFormNewQty').value  = qty;
    document.getElementById('qtyForm').submit();
}

function removeItem(itemId, itemName) {
    if (!confirm('Remove "' + itemName + '" from this order?')) return;

    // Optimistic UI update
    const row = document.getElementById('item-row-' + itemId);
    if (row) {
        row.style.transition = 'opacity 0.25s';
        row.style.opacity = '0';
        setTimeout(() => {
            row.remove();
            delete qtys[itemId];
            delete prices[itemId];
            recalcTotals();

            // Show empty message if no rows left
            const tbody = document.querySelector('#orderItemsTable tbody');
            if (!tbody.querySelector('tr[id^="item-row-"]')) {
                const emptyTr = document.createElement('tr');
                emptyTr.id = 'emptyRow';
                emptyTr.innerHTML = '<td colspan="5" class="text-center text-muted py-3">No items on this order.</td>';
                tbody.appendChild(emptyTr);
            }
        }, 250);
    }

    document.getElementById('deleteFormItemId').value = itemId;
    // Small delay so the fade-out is visible before page reload
    setTimeout(() => document.getElementById('deleteForm').submit(), 350);
}
</script>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
