<?php
$pageTitle = 'Inventory';
require_once '../../includes/header.php';
requireRole(['admin','manager']);
$pdo = getDB();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_item') {
        $pdo->prepare("INSERT INTO inventory_items (name,unit,current_qty,min_qty,cost_per_unit,category) VALUES (?,?,?,?,?,?)")
            ->execute([
                sanitize($_POST['name']),
                sanitize($_POST['unit']),
                (float)$_POST['current_qty'],
                (float)$_POST['min_qty'],
                (float)$_POST['cost_per_unit'],
                sanitize($_POST['category'])
            ]);
        flashMessage('success','Item added to inventory.');

    } elseif ($action === 'update_stock') {
        $id  = (int)$_POST['item_id'];
        $qty = (float)$_POST['new_qty'];
        $pdo->prepare("UPDATE inventory_items SET current_qty=? WHERE id=?")->execute([$qty, $id]);
        flashMessage('success','Stock updated.');

    } elseif ($action === 'delete_item') {
        $pdo->prepare("UPDATE inventory_items SET status='inactive' WHERE id=?")->execute([(int)$_POST['item_id']]);
        flashMessage('success','Item removed.');

    } elseif ($action === 'add_expense') {
        $invId = !empty($_POST['inventory_item_id']) ? (int)$_POST['inventory_item_id'] : null;
        $qty   = !empty($_POST['qty_purchased']) ? (float)$_POST['qty_purchased'] : null;
        $pdo->prepare("INSERT INTO expenses (expense_date,category,description,amount,paid_to,payment_mode,inventory_item_id,qty_purchased,recorded_by) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([
                sanitize($_POST['expense_date']),
                sanitize($_POST['category']),
                sanitize($_POST['description']),
                (float)$_POST['amount'],
                sanitize($_POST['paid_to'] ?? ''),
                sanitize($_POST['payment_mode']),
                $invId, $qty,
                $_SESSION['user_id']
            ]);
        // Also update stock if linked
        if ($invId && $qty) {
            $pdo->prepare("UPDATE inventory_items SET current_qty=current_qty+? WHERE id=?")->execute([$qty, $invId]);
        }
        flashMessage('success','Expense recorded.');
    }
    redirect(BASE_URL . 'modules/inventory/inventory.php');
}

$items    = $pdo->query("SELECT * FROM inventory_items WHERE status='active' ORDER BY category,name")->fetchAll();
$lowStock = array_filter($items, fn($i) => $i['current_qty'] <= $i['min_qty']);

// Expense summary this month
$monthExp = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE MONTH(expense_date)=MONTH(CURDATE()) AND YEAR(expense_date)=YEAR(CURDATE())")->fetchColumn();
$todayRev = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM bills WHERE DATE(created_at)=CURDATE() AND payment_status='paid'")->fetchColumn();

$recentExpenses = $pdo->query("SELECT e.*, i.name as item_name FROM expenses e LEFT JOIN inventory_items i ON e.inventory_item_id=i.id ORDER BY e.created_at DESC LIMIT 20")->fetchAll();
$expCategories  = ['Raw Materials','Meat & Seafood','Dairy','Vegetables','Grains','Oils','Beverages','Utilities','Maintenance','Salary','Marketing','Other'];
?>

<!-- Stats row -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card warning">
            <div class="stat-icon warning"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="stat-value"><?= count($lowStock) ?></div>
            <div class="stat-label">Low Stock Alerts</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card info">
            <div class="stat-icon info"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div class="stat-value"><?= count($items) ?></div>
            <div class="stat-label">Inventory Items</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card primary">
            <div class="stat-icon primary"><i class="fa-solid fa-money-bill-wave"></i></div>
            <div class="stat-value">₹<?= number_format($monthExp, 0) ?></div>
            <div class="stat-label">Expenses This Month</div>
        </div>
    </div>
</div>

<?php if (!empty($lowStock)): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
    <i class="fa-solid fa-triangle-exclamation fa-lg"></i>
    <div><strong>Low Stock:</strong>
        <?= implode(', ', array_map(fn($i) => htmlspecialchars($i['name']) . ' (' . $i['current_qty'] . ' ' . $i['unit'] . ')', $lowStock)) ?>
    </div>
</div>
<?php endif; ?>

<div class="row g-3">
    <!-- Inventory List -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h6><i class="fa-solid fa-boxes-stacked me-2 text-primary"></i>Stock Levels</h6>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="fa-solid fa-plus me-1"></i> Add Item
                </button>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Item</th><th>Category</th><th>Stock</th><th>Min</th><th>Cost/Unit</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $it):
                        $isLow = $it['current_qty'] <= $it['min_qty'];
                    ?>
                    <tr class="<?= $isLow ? 'table-warning' : '' ?>">
                        <td><strong><?= htmlspecialchars($it['name']) ?></strong></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($it['category']) ?></span></td>
                        <td>
                            <span class="fw-600 <?= $isLow ? 'text-danger' : 'text-success' ?>">
                                <?= $it['current_qty'] ?> <?= $it['unit'] ?>
                            </span>
                        </td>
                        <td class="text-muted"><?= $it['min_qty'] ?> <?= $it['unit'] ?></td>
                        <td>₹<?= number_format($it['cost_per_unit'], 2) ?></td>
                        <td><?= $isLow ? '<span class="badge-status badge-cancelled">Low</span>' : '<span class="badge-status badge-ready">OK</span>' ?></td>
                        <td>
                            <button class="btn-icon" title="Update Stock"
                                onclick="quickStock(<?= $it['id'] ?>,'<?= addslashes($it['name']) ?>',<?= $it['current_qty'] ?>,'<?= $it['unit'] ?>')">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Remove this item?')">
                                <input type="hidden" name="action" value="delete_item">
                                <input type="hidden" name="item_id" value="<?= $it['id'] ?>">
                                <button class="btn-icon" style="color:var(--danger)" title="Remove"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Expenses -->
        <div class="card mt-3">
            <div class="card-header">
                <h6><i class="fa-solid fa-receipt me-2 text-primary"></i>Recent Expenses</h6>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                    <i class="fa-solid fa-plus me-1"></i> Log Expense
                </button>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th><th>Mode</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentExpenses as $ex): ?>
                    <tr>
                        <td><?= date('d M', strtotime($ex['expense_date'])) ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($ex['category']) ?></span></td>
                        <td><?= htmlspecialchars($ex['description'] ?: ($ex['item_name'] ?? '-')) ?></td>
                        <td><strong>₹<?= number_format($ex['amount'], 2) ?></strong></td>
                        <td><?= ucfirst($ex['payment_mode']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentExpenses)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">No expenses logged yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right: Add forms -->
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><h6>Quick Expense Log</h6></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_expense">
                    <div class="mb-2">
                        <label class="form-label">Date</label>
                        <input type="date" name="expense_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select form-select-sm" required>
                            <?php foreach ($expCategories as $c): ?>
                            <option><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control form-control-sm" placeholder="e.g. Vegetables from market">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Amount (₹)</label>
                        <input type="number" name="amount" class="form-control form-control-sm" step="0.01" required placeholder="0.00">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Paid To</label>
                        <input type="text" name="paid_to" class="form-control form-control-sm" placeholder="Supplier / vendor name">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Payment Mode</label>
                        <select name="payment_mode" class="form-select form-select-sm">
                            <option value="cash">Cash</option>
                            <option value="upi">UPI</option>
                            <option value="card">Card</option>
                            <option value="bank">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Link to Inventory Item (optional)</label>
                        <select name="inventory_item_id" class="form-select form-select-sm">
                            <option value="">-- None --</option>
                            <?php foreach ($items as $it): ?>
                            <option value="<?= $it['id'] ?>"><?= htmlspecialchars($it['name']) ?> (<?= $it['unit'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Qty Purchased (if linked)</label>
                        <input type="number" name="qty_purchased" class="form-control form-control-sm" step="0.001" placeholder="0">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa-solid fa-save me-1"></i> Log Expense
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Inventory Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Add Inventory Item</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="add_item">
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-8">
                            <label class="form-label">Item Name</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Chicken">
                        </div>
                        <div class="col-4">
                            <label class="form-label">Unit</label>
                            <select name="unit" class="form-select">
                                <option>kg</option><option>litre</option><option>piece</option><option>packet</option><option>unit</option><option>gram</option><option>ml</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Current Stock</label>
                            <input type="number" name="current_qty" class="form-control" step="0.001" value="0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Minimum Stock</label>
                            <input type="number" name="min_qty" class="form-control" step="0.001" value="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Cost per Unit (₹)</label>
                            <input type="number" name="cost_per_unit" class="form-control" step="0.01" value="0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" class="form-control" placeholder="Meat, Dairy…">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Quick Stock Update Modal -->
<div class="modal fade" id="stockModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="stockModalTitle">Update Stock</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="update_stock">
                <input type="hidden" name="item_id" id="stockItemId">
                <div class="modal-body">
                    <label class="form-label">New Quantity (<span id="stockUnit"></span>)</label>
                    <input type="number" name="new_qty" id="stockNewQty" class="form-control" step="0.001" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function quickStock(id, name, qty, unit) {
    document.getElementById('stockItemId').value = id;
    document.getElementById('stockNewQty').value  = qty;
    document.getElementById('stockUnit').textContent = unit;
    document.getElementById('stockModalTitle').textContent = 'Update: ' + name;
    new bootstrap.Modal(document.getElementById('stockModal')).show();
}
</script>

<?php require_once '../../includes/footer.php'; ?>
