<?php
$pageTitle = 'Audit Log';
require_once '../../includes/header.php';
requireRole(['admin']);
$pdo = getDB();

$dateFilter = sanitize($_GET['date']   ?? date('Y-m-d'));
$modFilter  = sanitize($_GET['module'] ?? '');
$userFilter = sanitize($_GET['user']   ?? '');

$where  = ["DATE(a.created_at) = ?"];
$params = [$dateFilter];
if ($modFilter) { $where[] = "a.module = ?";    $params[] = $modFilter; }
if ($userFilter){ $where[] = "a.user_id = ?";   $params[] = (int)$userFilter; }

$logs = $pdo->prepare(
    "SELECT a.*, u.name as staff_name FROM audit_log a
     LEFT JOIN users u ON a.user_id = u.id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY a.created_at DESC LIMIT 500"
);
$logs->execute($params);
$logs = $logs->fetchAll();

$modules  = $pdo->query("SELECT DISTINCT module FROM audit_log WHERE module IS NOT NULL ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);
$staffAll = $pdo->query("SELECT id,name FROM users ORDER BY name")->fetchAll();

// Stats for today
$todayCount = $pdo->query("SELECT COUNT(*) FROM audit_log WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$totalCount = $pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();

// Action color map
function actionColor($action) {
    $a = strtolower($action);
    if (str_contains($a,'delete') || str_contains($a,'cancel')) return ['#f8d7da','#721c24','🗑'];
    if (str_contains($a,'create') || str_contains($a,'place'))  return ['#d4edda','#155724','➕'];
    if (str_contains($a,'update') || str_contains($a,'save') || str_contains($a,'mark')) return ['#fff3cd','#856404','✏️'];
    if (str_contains($a,'print'))  return ['#d1ecf1','#0c5460','🖨'];
    if (str_contains($a,'login') || str_contains($a,'logout'))  return ['#e2e3e5','#383d41','🔐'];
    return ['#f9f6f3','#555','📋'];
}
?>

<div class="row g-3 mb-3">
    <div class="col-sm-3">
        <div class="stat-card info">
            <div class="stat-icon info"><i class="fa-solid fa-shield-halved"></i></div>
            <div class="stat-value"><?= $todayCount ?></div>
            <div class="stat-label">Actions Today</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card primary">
            <div class="stat-icon primary"><i class="fa-solid fa-database"></i></div>
            <div class="stat-value"><?= number_format($totalCount) ?></div>
            <div class="stat-label">Total Log Entries</div>
        </div>
    </div>
</div>

<div class="card mb-3" style="background:#fef3cd;border-color:#ffd875;">
    <div class="card-body py-2 d-flex align-items-center gap-2" style="font-size:13px;">
        <i class="fa-solid fa-circle-info" style="color:#856404;"></i>
        <span><strong>What is the Audit Log?</strong> Every important action taken by staff — creating orders, cancellations, bill generation, printing, status changes — is automatically recorded here with the staff name, time, and details. This helps you track activity and prevent fraud.</span>
    </div>
</div>

<?php if ($totalCount == 0): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fa-solid fa-shield-halved fa-3x mb-3 d-block" style="color:var(--border);"></i>
        <h5>No Audit Entries Yet</h5>
        <p class="text-muted">The audit log will start filling up automatically as staff use the system.<br>
        Actions like placing orders, generating bills, cancelling orders, and printing KOTs will all be logged here.</p>
        <div class="d-flex justify-content-center gap-3 mt-3" style="font-size:13px;color:var(--text-muted);">
            <span>➕ Create Order → logged</span>
            <span>🗑 Cancel Order → logged</span>
            <span>🖨 Print KOT → logged</span>
            <span>✏️ Update Status → logged</span>
        </div>
    </div>
</div>
<?php else: ?>

<div class="card">
    <div class="card-header">
        <h6><i class="fa-solid fa-shield-halved me-2 text-primary"></i>Activity Log — <?= date('d M Y', strtotime($dateFilter)) ?></h6>
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <input type="date" name="date" class="form-control form-control-sm" value="<?= $dateFilter ?>">
            <select name="module" class="form-select form-select-sm" style="width:130px;">
                <option value="">All Modules</option>
                <?php foreach ($modules as $m): ?><option value="<?= $m ?>" <?= $modFilter===$m?'selected':'' ?>><?= ucfirst($m) ?></option><?php endforeach; ?>
            </select>
            <select name="user" class="form-select form-select-sm" style="width:150px;">
                <option value="">All Staff</option>
                <?php foreach ($staffAll as $u): ?><option value="<?= $u['id'] ?>" <?= $userFilter==(string)$u['id']?'selected':'' ?>><?= htmlspecialchars($u['name']) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-primary">Filter</button>
            <a href="?" class="btn btn-sm btn-outline-secondary">Clear</a>
        </form>
    </div>
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
        <div class="text-center text-muted py-4">No activity found for these filters.</div>
        <?php else: ?>
        <table class="table table-hover mb-0">
            <thead><tr><th style="width:120px;">Time</th><th style="width:130px;">Staff</th><th style="width:100px;">Module</th><th>Action</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log):
                [$bg,$col,$icon] = actionColor($log['action']);
            ?>
            <tr>
                <td style="font-size:12px;white-space:nowrap;color:var(--text-muted);">
                    <?= date('h:i:s A', strtotime($log['created_at'])) ?>
                </td>
                <td>
                    <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($log['user_name'] ?? $log['staff_name'] ?? 'System') ?></div>
                    <?php if ($log['ip_address']): ?><div style="font-size:10px;color:var(--text-muted);"><?= htmlspecialchars($log['ip_address']) ?></div><?php endif; ?>
                </td>
                <td><span class="badge bg-secondary" style="font-size:11px;"><?= ucfirst($log['module'] ?? '—') ?></span></td>
                <td>
                    <span style="background:<?= $bg ?>;color:<?= $col ?>;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">
                        <?= $icon ?> <?= htmlspecialchars($log['action']) ?>
                    </span>
                </td>
                <td style="font-size:12px;color:var(--text-muted);max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <?php
                    $detail = $log['new_value'] ?: $log['old_value'] ?: '';
                    if ($log['record_id']) echo '#' . $log['record_id'] . ' ';
                    echo htmlspecialchars(substr($detail, 0, 100));
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
