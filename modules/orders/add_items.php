<?php
$pageTitle = 'Add More Items';
require_once '../../includes/header.php';

$pdo     = getDB();
$orderId = (int)($_GET['order_id'] ?? 0);
if (!$orderId) redirect(BASE_URL . 'modules/orders/orders.php');

// Load order – must be open (not served / cancelled / billed)
$stmt = $pdo->prepare(
    "SELECT o.*, t.table_number
     FROM orders o
     LEFT JOIN tables t ON o.table_id = t.id
     WHERE o.id = ?"
);
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order || !in_array($order['status'], ['confirmed','preparing','ready'])) {
    flashMessage('error', 'This order is already closed or does not exist.');
    redirect(BASE_URL . 'modules/orders/orders.php');
}

// Check no bill has been finalized yet
$billCheck = $pdo->prepare("SELECT id FROM bills WHERE order_id = ? AND payment_status = 'paid' LIMIT 1");
$billCheck->execute([$orderId]);
if ($billCheck->fetch()) {
    flashMessage('error', 'This order has already been billed and cannot be modified.');
    redirect(BASE_URL . 'modules/orders/view_order.php?id=' . $orderId);
}

// ── Handle POST: append items ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemsJson = $_POST['items'] ?? '[]';
    $items     = json_decode($itemsJson, true);

    if (empty($items)) {
        flashMessage('error', 'Please select at least one item to add.');
        redirect(BASE_URL . 'modules/orders/add_items.php?order_id=' . $orderId);
    }

    foreach ($items as $item) {
        $menuId = (int)$item['id'];
        $qty    = max(1, (int)$item['qty']);

        $priceStmt = $pdo->prepare("SELECT price FROM menu_items WHERE id = ? AND available = 1");
        $priceStmt->execute([$menuId]);
        $priceRow = $priceStmt->fetch();

        if ($priceRow) {
            // Merge with existing order_item if same item ordered again
            $existStmt = $pdo->prepare(
                "SELECT id, quantity FROM order_items WHERE order_id = ? AND menu_item_id = ?"
            );
            $existStmt->execute([$orderId, $menuId]);
            $existing = $existStmt->fetch();

            if ($existing) {
                $pdo->prepare("UPDATE order_items SET quantity = quantity + ? WHERE id = ?")
                    ->execute([$qty, $existing['id']]);
            } else {
                $pdo->prepare(
                    "INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price) VALUES (?,?,?,?)"
                )->execute([$orderId, $menuId, $qty, $priceRow['price']]);
            }
        }
    }

    // Bump order back to 'confirmed' so kitchen sees the new items
    $pdo->prepare("UPDATE orders SET status = 'confirmed', updated_at = NOW() WHERE id = ?")
        ->execute([$orderId]);

    $addedCount = array_sum(array_column($items, 'qty'));
    flashMessage('success', "$addedCount item(s) added to Order #{$order['order_number']}.");
    redirect(BASE_URL . 'modules/orders/view_order.php?id=' . $orderId);
}

// ── Load menu ─────────────────────────────────────────────────────────────────
$categories = $pdo->query("SELECT * FROM categories WHERE status='active'")->fetchAll();
$menuItems  = $pdo->query(
    "SELECT m.*, c.name as cat_name
     FROM menu_items m
     JOIN categories c ON m.category_id = c.id
     WHERE m.available = 1
     ORDER BY c.id, m.name"
)->fetchAll();

// Existing items on this order (for reference display)
$existingItems = $pdo->prepare(
    "SELECT oi.*, m.name, m.is_veg
     FROM order_items oi
     JOIN menu_items m ON oi.menu_item_id = m.id
     WHERE oi.order_id = ?"
);
$existingItems->execute([$orderId]);
$existingItems = $existingItems->fetchAll();
$existingSubtotal = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $existingItems));

$taxPercent     = getSetting('tax_percent') ?: 5;
$serviceCharge  = getSetting('service_charge_percent') ?: 5;
?>

<!-- Page header breadcrumb -->
<div class="d-flex align-items-center gap-2 mb-3 no-print">
    <a href="<?= BASE_URL ?>modules/orders/view_order.php?id=<?= $orderId ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i> Back to Order
    </a>
    <span class="text-muted" style="font-size:13px;">
        Adding items to &nbsp;<strong>Order #<?= htmlspecialchars($order['order_number']) ?></strong>
        <?php if ($order['table_number']): ?>
            &nbsp;—&nbsp; Table <strong><?= htmlspecialchars($order['table_number']) ?></strong>
        <?php endif; ?>
    </span>
</div>

<div class="row g-3">
    <!-- Left: Menu -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h6><i class="fa-solid fa-book-open me-2 text-primary"></i>Select Items to Add</h6>
                <input type="text" class="form-control form-control-sm" style="width:200px;"
                       placeholder="Search menu…" oninput="searchMenu(this.value)">
            </div>
            <div class="card-body" style="max-height:calc(100vh - 240px);overflow-y:auto;">
                <!-- Category Filters -->
                <div class="d-flex gap-2 flex-wrap mb-3">
                    <button class="btn btn-sm btn-primary cat-filter-btn" data-cat="all"
                            onclick="filterCategory('all')">All</button>
                    <?php foreach ($categories as $cat): ?>
                    <button class="btn btn-sm btn-outline-secondary cat-filter-btn"
                            data-cat="<?= $cat['id'] ?>"
                            onclick="filterCategory(<?= $cat['id'] ?>)">
                        <?= htmlspecialchars($cat['name']) ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <?php
                $grouped = [];
                foreach ($menuItems as $item) $grouped[$item['cat_name']][] = $item;
                foreach ($grouped as $catName => $items):
                    $catId = $items[0]['category_id'];
                ?>
                <div class="menu-category-section mb-3" data-cat="<?= $catId ?>">
                    <div class="fw-600 mb-2"
                         style="color:#b5451b;font-size:13px;text-transform:uppercase;letter-spacing:1px;">
                        <?= htmlspecialchars($catName) ?>
                    </div>
                    <div class="row g-2">
                        <?php foreach ($items as $item): ?>
                        <div class="col-6 col-md-4">
                            <div class="menu-item-card" id="menu_card_<?= $item['id'] ?>"
                                 data-name="<?= strtolower(htmlspecialchars($item['name'])) ?>"
                                 onclick="toggleMenuItem(<?= $item['id'] ?>, '<?= addslashes($item['name']) ?>', <?= $item['price'] ?>)">
                                <div class="item-badge" id="badge_<?= $item['id'] ?>">1</div>
                                <div style="display:flex;align-items:center;gap:4px;margin-bottom:4px;">
                                    <span class="veg-dot <?= $item['is_veg'] ? 'veg' : 'non-veg' ?>"></span>
                                    <span style="font-size:13px;font-weight:500;"><?= htmlspecialchars($item['name']) ?></span>
                                </div>
                                <div style="font-size:14px;font-weight:700;color:#b5451b;">
                                    ₹<?= number_format($item['price'], 2) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Right: Order Panel -->
    <div class="col-lg-5">

        <!-- Already-ordered items (read-only reference) -->
        <div class="card mb-3">
            <div class="card-header" style="background:#fef3cd;">
                <h6 style="margin:0;font-size:13px;">
                    <i class="fa-solid fa-clock-rotate-left me-2" style="color:#b5451b;"></i>
                    Already on This Order
                </h6>
            </div>
            <div class="card-body p-2">
                <?php if (empty($existingItems)): ?>
                    <p class="text-muted mb-0" style="font-size:13px;">No items yet.</p>
                <?php else: ?>
                    <table class="table table-sm mb-1" style="font-size:13px;">
                        <tbody>
                        <?php foreach ($existingItems as $ei): ?>
                        <tr>
                            <td>
                                <span class="veg-dot <?= $ei['is_veg'] ? 'veg' : 'non-veg' ?>"></span>
                                <?= htmlspecialchars($ei['name']) ?>
                            </td>
                            <td class="text-center">×<?= $ei['quantity'] ?></td>
                            <td class="text-end">₹<?= number_format($ei['quantity'] * $ei['unit_price'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                        <tr>
                            <td colspan="2" class="text-end fw-600" style="font-size:12px;">Previous subtotal:</td>
                            <td class="text-end fw-700">₹<?= number_format($existingSubtotal, 2) ?></td>
                        </tr>
                        </tfoot>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- New items being added -->
        <div class="card">
            <div class="card-header">
                <h6><i class="fa-solid fa-plus-circle me-2 text-primary"></i>New Items (This Round)</h6>
            </div>
            <div class="card-body" style="max-height:calc(100vh - 420px);overflow-y:auto;">
                <form method="POST" id="addItemsForm">
                    <div id="orderItemsContainer">
                        <div class="text-muted text-center py-3" id="emptyItemsMsg" style="font-size:13px;">
                            <i class="fa-solid fa-bowl-food fa-2x mb-2 d-block" style="color:#e8e0d8;"></i>
                            Tap items from the menu to add this round
                        </div>
                    </div>

                    <hr>
                    <div class="bill-summary">
                        <div class="bill-row">
                            <span>Previous subtotal</span>
                            <span>₹<?= number_format($existingSubtotal, 2) ?></span>
                        </div>
                        <div class="bill-row"><span>This round subtotal</span><span id="billSubtotal">₹0.00</span></div>
                        <div class="bill-row"><span>Tax (<?= $taxPercent ?>%)</span><span id="billTax">₹0.00</span></div>
                        <div class="bill-row" id="serviceRow">
                            <span>Service (<?= $serviceCharge ?>%)</span><span id="billService">₹0.00</span>
                        </div>
                        <div class="bill-row total"><span>Running Total</span><span id="billTotal">₹<?= number_format($existingSubtotal, 2) ?></span></div>
                    </div>

                    <!-- Hidden fields -->
                    <input type="hidden" name="items" id="hiddenItems">
                    <input type="hidden" id="hiddenTotal" name="total_amount">
                    <input type="hidden" id="taxRate"      value="<?= $taxPercent ?>">
                    <input type="hidden" id="serviceRate"  value="<?= $serviceCharge ?>">
                    <input type="hidden" id="deliveryCharge" value="0">
                    <input type="hidden" id="discountAmount"  value="0">

                    <button type="submit" class="btn btn-primary w-100 mt-3" id="addItemsBtn" disabled>
                        <i class="fa-solid fa-plus me-2"></i> Add Items to Order
                    </button>
                    <a href="<?= BASE_URL ?>modules/orders/view_order.php?id=<?= $orderId ?>"
                       class="btn btn-outline-secondary w-100 mt-2">
                        <i class="fa-solid fa-xmark me-1"></i> Cancel
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Patch: remove empty placeholder & enable submit button on first item
const _prevExistingSubtotal = <?= $existingSubtotal ?>;

document.addEventListener('DOMContentLoaded', function () {
    const _orig = window.toggleMenuItem;
    window.toggleMenuItem = function (id, name, price) {
        const msg = document.getElementById('emptyItemsMsg');
        if (msg) msg.remove();
        document.getElementById('addItemsBtn').disabled = false;
        _orig(id, name, price);
    };

    // Override updateOrderSummary to include existing subtotal in Running Total
    const _origUpdate = window.updateOrderSummary;
    window.updateOrderSummary = function () {
        _origUpdate();
        // Recalculate running total including what was already ordered
        const thisRound = parseFloat(document.getElementById('billSubtotal')?.textContent?.replace('₹','') || 0);
        const taxRate   = parseFloat(document.getElementById('taxRate')?.value || 0) / 100;
        const svcRate   = parseFloat(document.getElementById('serviceRate')?.value || 0) / 100;
        const tax       = thisRound * taxRate;
        const svc       = thisRound * svcRate;
        const grandRunning = _prevExistingSubtotal + thisRound + tax + svc;
        const el = document.getElementById('billTotal');
        if (el) el.textContent = '₹' + grandRunning.toFixed(2);
    };

    document.getElementById('addItemsForm').addEventListener('submit', function (e) {
        let parsed = [];
        try { parsed = JSON.parse(document.getElementById('hiddenItems').value || '[]'); } catch (_) {}
        if (!parsed.length) {
            e.preventDefault();
            alert('Please select at least one item to add.');
        } else {
            syncHiddenItems();
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
