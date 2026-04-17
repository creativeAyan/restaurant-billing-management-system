<?php
$pageTitle = 'Categories';
require_once '../../includes/header.php';
requireRole(['admin','manager']);
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pdo->prepare("INSERT INTO categories (name,description) VALUES (?,?)")
            ->execute([sanitize($_POST['name']), sanitize($_POST['description'] ?? '')]);
        flashMessage('success','Category added!');
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([(int)$_POST['cat_id']]);
        flashMessage('success','Category deleted.');
    } elseif ($action === 'toggle') {
        $pdo->prepare("UPDATE categories SET status = IF(status='active','inactive','active') WHERE id=?")->execute([(int)$_POST['cat_id']]);
    }
    redirect(BASE_URL . 'modules/menu/categories.php');
}

$cats = $pdo->query("SELECT c.*, COUNT(m.id) as item_count FROM categories c LEFT JOIN menu_items m ON m.category_id=c.id GROUP BY c.id ORDER BY c.name")->fetchAll();
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h6>Add Category</h6></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3"><label class="form-label">Category Name</label>
                        <input type="text" name="name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea></div>
                    <button type="submit" class="btn btn-primary w-100">Add Category</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h6>Menu Categories</h6></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Category</th><th>Items</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($cats as $c): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['name']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($c['description']??'') ?></small></td>
                            <td><?= $c['item_count'] ?> items</td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="cat_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="badge-status <?= $c['status']==='active'?'badge-paid':'badge-cancelled' ?>" style="border:none;cursor:pointer;">
                                        <?= ucfirst($c['status']) ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete category? Items will also be deleted!')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="cat_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn-icon text-danger"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
