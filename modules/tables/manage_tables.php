<?php
$pageTitle = 'Manage Tables';
require_once '../../includes/header.php';
requireRole(['admin','manager']);
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pdo->prepare("INSERT INTO tables (table_number,capacity,floor) VALUES (?,?,?)")
            ->execute([sanitize($_POST['table_number']), (int)$_POST['capacity'], sanitize($_POST['floor'])]);
        flashMessage('success','Table added!');
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM tables WHERE id=?")->execute([(int)$_POST['table_id']]);
        flashMessage('success','Table deleted.');
    }
    redirect(BASE_URL . 'modules/tables/manage_tables.php');
}

$tables = $pdo->query("SELECT * FROM tables ORDER BY floor, table_number")->fetchAll();
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h6>Add New Table</h6></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3"><label class="form-label">Table Number/Name</label>
                        <input type="text" name="table_number" class="form-control" placeholder="e.g. T13, VIP-1" required></div>
                    <div class="mb-3"><label class="form-label">Capacity (seats)</label>
                        <input type="number" name="capacity" class="form-control" min="1" max="50" value="4" required></div>
                    <div class="mb-3"><label class="form-label">Floor</label>
                        <input type="text" name="floor" class="form-control" value="Ground" placeholder="Ground, First, Terrace..."></div>
                    <button type="submit" class="btn btn-primary w-100">Add Table</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h6>All Tables (<?= count($tables) ?>)</h6></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Table</th><th>Capacity</th><th>Floor</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($tables as $t): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($t['table_number']) ?></strong></td>
                            <td><?= $t['capacity'] ?> seats</td>
                            <td><?= htmlspecialchars($t['floor']) ?></td>
                            <td><span class="badge-status badge-<?= $t['status'] === 'available' ? 'available' : 'occupied' ?>"><?= ucfirst($t['status']) ?></span></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this table?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="table_id" value="<?= $t['id'] ?>">
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
