<?php
$pageTitle = 'Menu Management';
require_once '../../includes/header.php';
$pdo = getDB();

// Add/Edit/Delete handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $name    = sanitize($_POST['name']);
        $catId   = (int)$_POST['category_id'];
        $price   = (float)$_POST['price'];
        $desc    = sanitize($_POST['description'] ?? '');
        $isVeg   = isset($_POST['is_veg']) ? 1 : 0;
        $avail   = isset($_POST['available']) ? 1 : 0;
        $tax     = (float)$_POST['tax_percent'];
        if ($action === 'add') {
            $pdo->prepare("INSERT INTO menu_items (category_id,name,description,price,tax_percent,is_veg,available) VALUES (?,?,?,?,?,?,?)")
                ->execute([$catId,$name,$desc,$price,$tax,$isVeg,$avail]);
            flashMessage('success','Item added!');
        } else {
            $pdo->prepare("UPDATE menu_items SET category_id=?,name=?,description=?,price=?,tax_percent=?,is_veg=?,available=? WHERE id=?")
                ->execute([$catId,$name,$desc,$price,$tax,$isVeg,$avail,(int)$_POST['item_id']]);
            flashMessage('success','Item updated!');
        }
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['item_id'];
        $pdo->prepare("UPDATE menu_items SET available = NOT available WHERE id=?")->execute([$id]);
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM menu_items WHERE id=?")->execute([(int)$_POST['item_id']]);
        flashMessage('success','Item deleted.');
    }
    redirect(BASE_URL . 'modules/menu/menu.php');
}

$categories = $pdo->query("SELECT * FROM categories WHERE status='active' ORDER BY name")->fetchAll();
$menuItems  = $pdo->query("SELECT m.*, c.name as cat_name FROM menu_items m JOIN categories c ON m.category_id=c.id ORDER BY c.name, m.name")->fetchAll();
$grouped    = [];
foreach ($menuItems as $item) $grouped[$item['cat_name']][] = $item;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
        <i class="fa-solid fa-plus me-1"></i> Add Menu Item
    </button>
</div>

<?php foreach ($grouped as $catName => $items): ?>
<div class="card mb-3">
    <div class="card-header">
        <h6><i class="fa-solid fa-layer-group me-2 text-primary"></i><?= htmlspecialchars($catName) ?> (<?= count($items) ?> items)</h6>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Item</th><th>Price</th><th>Tax</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr class="<?= !$item['available'] ? 'text-muted' : '' ?>">
                    <td>
                        <span class="veg-dot <?= $item['is_veg'] ? 'veg' : 'non-veg' ?>"></span>
                        <?= htmlspecialchars($item['name']) ?>
                        <?php if ($item['description']): ?><br><small class="text-muted"><?= htmlspecialchars(substr($item['description'],0,60)) ?></small><?php endif; ?>
                    </td>
                    <td>₹<?= number_format($item['price'],2) ?></td>
                    <td><?= $item['tax_percent'] ?>%</td>
                    <td><?= $item['is_veg'] ? '<span class="badge" style="background:#2d7d4f;">Veg</span>' : '<span class="badge bg-danger">Non-Veg</span>' ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                            <button type="submit" class="badge-status <?= $item['available'] ? 'badge-paid' : 'badge-cancelled' ?>" style="border:none;cursor:pointer;">
                                <?= $item['available'] ? 'Available' : 'Unavailable' ?>
                            </button>
                        </form>
                    </td>
                    <td>
                        <button class="btn-icon" onclick='editItem(<?= json_encode($item) ?>)' title="Edit"><i class="fa-solid fa-pen"></i></button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this item?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                            <button type="submit" class="btn-icon text-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<!-- Add/Edit Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Menu Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="item_id" id="itemId">
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select" required>
                            <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Item Name</label>
                        <input type="text" name="name" id="itemName" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Price (₹)</label>
                        <input type="number" name="price" id="itemPrice" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Tax %</label>
                        <input type="number" name="tax_percent" id="itemTax" class="form-control" value="5" step="0.01" min="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description (optional)</label>
                        <textarea name="description" id="itemDesc" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_veg" id="itemVeg" checked>
                            <label class="form-check-label" for="itemVeg">Vegetarian</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="available" id="itemAvail" checked>
                            <label class="form-check-label" for="itemAvail">Available</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Item</button>
            </div>
        </form>
    </div>
</div>

<script>
function editItem(item) {
    document.getElementById('modalTitle').textContent = 'Edit Menu Item';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('itemId').value = item.id;
    document.getElementById('itemName').value = item.name;
    document.getElementById('itemPrice').value = item.price;
    document.getElementById('itemTax').value = item.tax_percent;
    document.getElementById('itemDesc').value = item.description || '';
    document.getElementById('itemVeg').checked = item.is_veg == 1;
    document.getElementById('itemAvail').checked = item.available == 1;
    document.querySelector('[name="category_id"]').value = item.category_id;
    new bootstrap.Modal(document.getElementById('addItemModal')).show();
}
</script>

<?php require_once '../../includes/footer.php'; ?>
