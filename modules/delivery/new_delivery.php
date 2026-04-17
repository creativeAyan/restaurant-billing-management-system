<?php
$pageTitle = 'New Delivery Order';
require_once '../../includes/header.php';

$pdo = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $custName  = sanitize($_POST['cust_name'] ?? '');
    $custPhone = sanitize($_POST['cust_phone'] ?? '');
    $custAddr  = sanitize($_POST['cust_address'] ?? '');
    $instructions = sanitize($_POST['special_instructions'] ?? '');
    $itemsJson = $_POST['items'] ?? '[]';
    $items     = json_decode($itemsJson, true);

    if (empty($items)) {
        flashMessage('error', 'Please add at least one item to the order.');
        redirect(BASE_URL . 'modules/delivery/new_delivery.php');
    }
    if (!$custName || !$custPhone) {
        flashMessage('error', 'Customer name and phone are required.');
        redirect(BASE_URL . 'modules/delivery/new_delivery.php');
    }

    // Create or find customer
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ?");
    $stmt->execute([$custPhone]);
    $existing = $stmt->fetch();
    if ($existing) {
        $customerId = $existing['id'];
        $pdo->prepare("UPDATE customers SET name=?, address=? WHERE id=?")->execute([$custName, $custAddr, $customerId]);
    } else {
        $pdo->prepare("INSERT INTO customers (name, phone, address) VALUES (?,?,?)")->execute([$custName, $custPhone, $custAddr]);
        $customerId = $pdo->lastInsertId();
    }

    // Create order
    $orderNumber = generateOrderNumber();
    $pdo->prepare("INSERT INTO orders (order_number,order_type,customer_id,waiter_id,special_instructions,status) VALUES (?,'delivery',?,?,?,'confirmed')")
        ->execute([$orderNumber, $customerId, $_SESSION['user_id'], $instructions]);
    $orderId = $pdo->lastInsertId();

    // Insert items
    foreach ($items as $item) {
        $menuId = (int)$item['id'];
        $qty    = max(1, (int)$item['qty']);
        $priceStmt = $pdo->prepare("SELECT price FROM menu_items WHERE id=?");
        $priceStmt->execute([$menuId]);
        $priceRow = $priceStmt->fetch();
        if ($priceRow) {
            $pdo->prepare("INSERT INTO order_items (order_id,menu_item_id,quantity,unit_price) VALUES (?,?,?,?)")
                ->execute([$orderId, $menuId, $qty, $priceRow['price']]);
        }
    }

    // Create delivery record
    $pdo->prepare("INSERT INTO deliveries (order_id, delivery_address, status) VALUES (?,?,'pending')")
        ->execute([$orderId, $custAddr]);

    flashMessage('success', "Delivery order #$orderNumber created!");
    redirect(BASE_URL . 'modules/billing/create_bill.php?order_id=' . $orderId);
}

$categories = $pdo->query("SELECT * FROM categories WHERE status='active'")->fetchAll();
$menuItems  = $pdo->query("SELECT m.*, c.name as cat_name FROM menu_items m JOIN categories c ON m.category_id=c.id WHERE m.available=1 ORDER BY c.id, m.name")->fetchAll();
$grouped    = [];
foreach ($menuItems as $item) $grouped[$item['cat_name']][] = $item;
$deliveryCharge = getSetting('delivery_charge') ?: 50;
$taxPercent     = getSetting('tax_percent') ?: 5;
?>

<div class="row g-3">
    <!-- Left: Menu -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h6><i class="fa-solid fa-book-open me-2 text-primary"></i>Select Items</h6>
                <input type="text" class="form-control form-control-sm" style="width:200px;" placeholder="Search menu..." oninput="searchMenu(this.value)">
            </div>
            <div class="card-body" style="max-height:calc(100vh - 220px);overflow-y:auto;">
                <div class="d-flex gap-2 flex-wrap mb-3">
                    <button class="btn btn-sm btn-primary cat-filter-btn" data-cat="all" onclick="filterCategory('all')">All</button>
                    <?php foreach ($categories as $cat): ?>
                    <button class="btn btn-sm btn-outline-secondary cat-filter-btn" data-cat="<?= $cat['id'] ?>" onclick="filterCategory(<?= $cat['id'] ?>)">
                        <?= htmlspecialchars($cat['name']) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($grouped as $catName => $items): $catId = $items[0]['category_id']; ?>
                <div class="menu-category-section mb-3" data-cat="<?= $catId ?>">
                    <div class="fw-600 mb-2" style="color:#b5451b;font-size:13px;text-transform:uppercase;letter-spacing:1px;"><?= htmlspecialchars($catName) ?></div>
                    <div class="row g-2">
                        <?php foreach ($items as $item): ?>
                        <div class="col-6 col-md-4">
                            <div class="menu-item-card" id="menu_card_<?= $item['id'] ?>" data-name="<?= strtolower(htmlspecialchars($item['name'])) ?>"
                                 onclick="toggleMenuItem(<?= $item['id'] ?>, '<?= addslashes($item['name']) ?>', <?= $item['price'] ?>)">
                                <div class="item-badge" id="badge_<?= $item['id'] ?>">1</div>
                                <div style="display:flex;align-items:center;gap:4px;margin-bottom:4px;">
                                    <span class="veg-dot <?= $item['is_veg'] ? 'veg' : 'non-veg' ?>"></span>
                                    <span style="font-size:13px;font-weight:500;"><?= htmlspecialchars($item['name']) ?></span>
                                </div>
                                <div style="font-size:14px;font-weight:700;color:#b5451b;">₹<?= number_format($item['price'],2) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Right: Order Form -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h6><i class="fa-solid fa-motorcycle me-2 text-primary"></i>Delivery Details</h6>
            </div>
            <div class="card-body" style="max-height:calc(100vh - 220px);overflow-y:auto;">
                <form method="POST" id="deliveryForm">
                    <div class="mb-2">
                        <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                        <input type="text" name="cust_name" class="form-control" required placeholder="Full name">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" name="cust_phone" class="form-control" required placeholder="+91 XXXXX XXXXX">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Delivery Address <span class="text-danger">*</span></label>
                        <textarea name="cust_address" class="form-control" rows="3" required placeholder="House no, Street, Area, City..."></textarea>
                    </div>
                    <hr>
                    <div class="fw-600 mb-2" style="font-size:13px;">Selected Items</div>
                    <div id="orderItemsContainer">
                        <div class="text-muted text-center py-3" id="emptyItemsMsg" style="font-size:13px;">
                            <i class="fa-solid fa-motorcycle fa-2x mb-2 d-block" style="color:#e8e0d8;"></i>
                            Click items from the menu to add
                        </div>
                    </div>
                    <hr>
                    <div class="bill-summary">
                        <div class="bill-row"><span>Subtotal</span><span id="billSubtotal">₹0.00</span></div>
                        <div class="bill-row"><span>Tax (<?= $taxPercent ?>%)</span><span id="billTax">₹0.00</span></div>
                        <div class="bill-row"><span>Delivery Charge</span><span id="billDelivery">₹<?= number_format($deliveryCharge,2) ?></span></div>
                        <div class="bill-row"><span>Discount</span><span id="billDiscount">₹0.00</span></div>
                        <div class="bill-row total"><span>TOTAL</span><span id="billTotal">₹<?= number_format($deliveryCharge,2) ?></span></div>
                    </div>

                    <input type="hidden" name="items" id="hiddenItems">
                    <input type="hidden" id="hiddenTotal" name="total_amount">
                    <input type="hidden" id="taxRate" value="<?= $taxPercent ?>">
                    <input type="hidden" id="serviceRate" value="0">
                    <input type="hidden" id="deliveryCharge" value="<?= $deliveryCharge ?>">
                    <input type="hidden" id="discountAmount" value="0">

                    <div class="mb-3 mt-3">
                        <label class="form-label">Special Instructions</label>
                        <textarea name="special_instructions" class="form-control" rows="2" placeholder="Allergies, spice level..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa-solid fa-motorcycle me-2"></i> Place Delivery Order
                    </button>
                    <a href="<?= BASE_URL ?>modules/delivery/delivery.php" class="btn btn-outline-secondary w-100 mt-2">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const origToggle = window.toggleMenuItem;
window.toggleMenuItem = function(id, name, price) {
    document.getElementById('emptyItemsMsg')?.remove();
    origToggle(id, name, price);
};
</script>

<?php require_once '../../includes/footer.php'; ?>
