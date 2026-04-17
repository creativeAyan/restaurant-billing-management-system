<?php
$pageTitle = 'Table Management';
require_once '../../includes/header.php';
$pdo = getDB();

// Handle AJAX status update
if (isset($_GET['action']) && $_GET['action'] === 'update_status') {
    $tableId = (int)$_GET['table_id'];
    $status  = sanitize($_GET['status']);
    $allowed = ['available','occupied','reserved','cleaning'];
    if (in_array($status, $allowed)) {
        $pdo->prepare("UPDATE tables SET status=? WHERE id=?")->execute([$status, $tableId]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

$tables = $pdo->query("
    SELECT t.*, 
        (SELECT o.id FROM orders o WHERE o.table_id=t.id AND o.status NOT IN ('served','cancelled') ORDER BY o.created_at DESC LIMIT 1) as active_order_id,
        (SELECT o.order_number FROM orders o WHERE o.table_id=t.id AND o.status NOT IN ('served','cancelled') ORDER BY o.created_at DESC LIMIT 1) as active_order_number
    FROM tables t ORDER BY floor, table_number
")->fetchAll();

$floors = array_unique(array_column($tables, 'floor'));
$stats  = ['available'=>0,'occupied'=>0,'reserved'=>0,'cleaning'=>0];
foreach ($tables as $t) $stats[$t['status']]++;
?>

<div class="row g-2 mb-3">
    <?php foreach ([['available','success','fa-check-circle'],['occupied','danger','fa-person'],['reserved','warning','fa-clock'],['cleaning','info','fa-broom']] as [$s,$c,$ic]): ?>
    <div class="col-6 col-md-3">
        <div class="card text-center p-3">
            <i class="fa-solid <?= $ic ?> fa-2x mb-2 text-<?= $c ?>"></i>
            <div class="fw-700 fs-4"><?= $stats[$s] ?></div>
            <div class="text-muted" style="font-size:12px;text-transform:capitalize;"><?= $s ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php foreach ($floors as $floor): ?>
<div class="card mb-3">
    <div class="card-header">
        <h6><i class="fa-solid fa-layer-group me-2 text-primary"></i><?= htmlspecialchars($floor) ?> Floor</h6>
    </div>
    <div class="card-body">
        <div class="table-grid">
            <?php foreach ($tables as $t): if ($t['floor'] !== $floor) continue; ?>
            <div class="table-card <?= $t['status'] ?>">
                <div class="table-num"><?= htmlspecialchars($t['table_number']) ?></div>
                <div class="table-cap"><i class="fa-solid fa-person"></i> <?= $t['capacity'] ?></div>
                <div class="table-status-badge"><?= $t['status'] ?></div>
                <?php if ($t['active_order_id']): ?>
                <div style="font-size:10px;margin-top:4px;color:#b5451b;">
                    <a href="<?= BASE_URL ?>modules/orders/view_order.php?id=<?= $t['active_order_id'] ?>" style="color:inherit;text-decoration:none;">
                        <?= htmlspecialchars($t['active_order_number']) ?>
                    </a>
                </div>
                <?php endif; ?>
                <div class="dropdown mt-2">
                    <button class="btn btn-sm btn-outline-secondary w-100 dropdown-toggle" style="font-size:10px;" data-bs-toggle="dropdown">
                        Change
                    </button>
                    <ul class="dropdown-menu">
                        <?php foreach (['available','occupied','reserved','cleaning'] as $s): ?>
                        <li><a class="dropdown-item <?= $s===$t['status']?'active':'' ?>" href="javascript:void(0)" onclick="changeTableStatus(<?= $t['id'] ?>,'<?= $s ?>')">
                            <?= ucfirst($s) ?>
                        </a></li>
                        <?php endforeach; ?>
                        <?php if ($t['active_order_id']): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/billing/create_bill.php?order_id=<?= $t['active_order_id'] ?>">
                            <i class="fa-solid fa-file-invoice-dollar me-1"></i> Generate Bill
                        </a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
function changeTableStatus(id, status) {
    fetch(`?action=update_status&table_id=${id}&status=${status}`)
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); });
}
</script>

<?php require_once '../../includes/footer.php'; ?>
