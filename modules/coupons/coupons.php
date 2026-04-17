<?php
$pageTitle = 'Coupons & Offers';
require_once '../../includes/header.php';
requireRole(['admin','manager']);
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        try {
            $pdo->prepare("INSERT INTO coupons (code,description,discount_type,discount_value,min_order_amount,max_uses,valid_from,valid_to) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([
                    strtoupper(sanitize($_POST['code'])),
                    sanitize($_POST['description']),
                    sanitize($_POST['discount_type']),
                    (float)$_POST['discount_value'],
                    (float)($_POST['min_order_amount'] ?? 0),
                    (int)($_POST['max_uses'] ?? 0),
                    sanitize($_POST['valid_from']),
                    sanitize($_POST['valid_to'])
                ]);
            flashMessage('success', 'Coupon created.');
        } catch(Exception $e) {
            flashMessage('error', 'Coupon code already exists.');
        }
    } elseif ($action === 'toggle') {
        $pdo->prepare("UPDATE coupons SET status=IF(status='active','inactive','active') WHERE id=?")->execute([(int)$_POST['id']]);
        flashMessage('success', 'Coupon status toggled.');
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM coupons WHERE id=?")->execute([(int)$_POST['id']]);
        flashMessage('success', 'Coupon deleted.');
    }
    redirect(BASE_URL . 'modules/coupons/coupons.php');
}

$coupons = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 style="font-family:'Playfair Display',serif;font-size:18px;"><i class="fa-solid fa-tag me-2 text-primary"></i>Coupons & Promo Codes</h6>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCouponModal">
        <i class="fa-solid fa-plus me-1"></i> New Coupon
    </button>
</div>

<div class="row g-3">
<?php foreach ($coupons as $c):
    $isExpired = $c['valid_to'] < date('Y-m-d');
    $isActive  = $c['status'] === 'active' && !$isExpired;
    $usedPct   = $c['max_uses'] > 0 ? min(100, round($c['used_count']/$c['max_uses']*100)) : 0;
?>
<div class="col-md-6 col-lg-4">
    <div class="card" style="border-left:4px solid <?= $isActive ? 'var(--success)' : 'var(--danger)' ?>;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div style="font-family:'DM Mono',monospace;font-size:20px;font-weight:700;color:var(--primary);letter-spacing:2px;"><?= htmlspecialchars($c['code']) ?></div>
                    <div style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($c['description']) ?></div>
                </div>
                <span class="badge-status badge-<?= $isActive ? 'ready' : 'cancelled' ?>">
                    <?= $isExpired ? 'Expired' : ucfirst($c['status']) ?>
                </span>
            </div>

            <div class="d-flex gap-3 mb-3">
                <div style="background:#fde8e0;border-radius:8px;padding:10px 14px;text-align:center;">
                    <div style="font-size:22px;font-weight:700;color:var(--primary);">
                        <?= $c['discount_type']==='percent' ? $c['discount_value'].'%' : '₹'.$c['discount_value'] ?>
                    </div>
                    <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;">Discount</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--text-muted);">Min order: <strong>₹<?= number_format($c['min_order_amount'],0) ?></strong></div>
                    <div style="font-size:12px;color:var(--text-muted);">Valid: <?= date('d M',strtotime($c['valid_from'])) ?> – <?= date('d M Y',strtotime($c['valid_to'])) ?></div>
                    <div style="font-size:12px;color:var(--text-muted);">Used: <strong><?= $c['used_count'] ?></strong><?= $c['max_uses']>0 ? ' / '.$c['max_uses'] : ' (unlimited)' ?></div>
                </div>
            </div>

            <?php if ($c['max_uses'] > 0): ?>
            <div class="progress mb-3" style="height:4px;">
                <div class="progress-bar" style="width:<?= $usedPct ?>%;background:var(--primary);"></div>
            </div>
            <?php endif; ?>

            <div class="d-flex gap-2">
                <form method="POST" style="flex:1">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <button class="btn btn-sm <?= $c['status']==='active' ? 'btn-outline-warning' : 'btn-outline-success' ?> w-100">
                        <?= $c['status']==='active' ? 'Disable' : 'Enable' ?>
                    </button>
                </form>
                <form method="POST" onsubmit="return confirm('Delete coupon?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($coupons)): ?>
<div class="col-12">
    <div class="text-center text-muted py-5">
        <i class="fa-solid fa-tag fa-3x mb-3 d-block" style="color:var(--border)"></i>
        No coupons yet. Create your first promo code!
    </div>
</div>
<?php endif; ?>
</div>

<!-- Add Coupon Modal -->
<div class="modal fade" id="addCouponModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Create Coupon</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label">Coupon Code</label>
                            <input type="text" name="code" class="form-control" required placeholder="e.g. SUMMER25" style="text-transform:uppercase;font-family:monospace;font-size:16px;font-weight:700;letter-spacing:2px;">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" class="form-control" placeholder="e.g. 25% off for summer">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Discount Type</label>
                            <select name="discount_type" class="form-select" id="discTypeSelect">
                                <option value="percent">Percent (%)</option>
                                <option value="fixed">Fixed (₹)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Discount Value</label>
                            <div class="input-group">
                                <span class="input-group-text" id="discSymbol">%</span>
                                <input type="number" name="discount_value" class="form-control" step="0.01" required min="0">
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Min Order (₹)</label>
                            <input type="number" name="min_order_amount" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Max Uses (0=unlimited)</label>
                            <input type="number" name="max_uses" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Valid From</label>
                            <input type="date" name="valid_from" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Valid To</label>
                            <input type="date" name="valid_to" class="form-control" value="<?= date('Y-m-d', strtotime('+1 month')) ?>" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Coupon</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('discTypeSelect').addEventListener('change', function() {
    document.getElementById('discSymbol').textContent = this.value === 'percent' ? '%' : '₹';
});
</script>
<?php require_once '../../includes/footer.php'; ?>
