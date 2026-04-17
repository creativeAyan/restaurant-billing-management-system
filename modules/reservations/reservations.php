<?php
$pageTitle = 'Reservations';
require_once '../../includes/header.php';
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $tableIdNew = !empty($_POST['table_id']) ? (int)$_POST['table_id'] : null;
        $resDate    = sanitize($_POST['reserved_date']);
        $resTime    = sanitize($_POST['reserved_time']);
        $durMins    = (int)($_POST['duration_mins'] ?? 90);

        if ($tableIdNew) {
            // Proper overlap check: new booking overlaps if existing starts before new ends AND existing ends after new starts
            $stmt = $pdo->prepare(
                "SELECT id, customer_name, reserved_time, duration_mins FROM reservations
                 WHERE table_id = ?
                   AND reserved_date = ?
                   AND status IN ('confirmed','seated')
                   AND TIME_TO_SEC(?) < TIME_TO_SEC(reserved_time) + (duration_mins * 60)
                   AND TIME_TO_SEC(?) + (? * 60) > TIME_TO_SEC(reserved_time)"
            );
            $stmt->execute([$tableIdNew, $resDate, $resTime, $resTime, $durMins]);
            $conflictRow = $stmt->fetch();
            if ($conflictRow) {
                $conflictEnd = date('h:i A', strtotime($conflictRow['reserved_time']) + ($conflictRow['duration_mins']*60));
                flashMessage('error',
                    '⚠️ Table already reserved from ' .
                    date('h:i A', strtotime($conflictRow['reserved_time'])) . ' to ' . $conflictEnd .
                    ' for <strong>' . htmlspecialchars($conflictRow['customer_name']) . '</strong>. Choose a different table or time slot.'
                );
                redirect(BASE_URL . 'modules/reservations/reservations.php');
            }
        }
        $num = 'RES-' . date('Ymd') . '-' . strtoupper(substr(uniqid(),-4));
        $pdo->prepare("INSERT INTO reservations (reservation_number,table_id,customer_name,customer_phone,party_size,reserved_date,reserved_time,duration_mins,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                $num,
                $tableIdNew,
                sanitize($_POST['customer_name']),
                sanitize($_POST['customer_phone']),
                (int)$_POST['party_size'],
                $resDate,
                $resTime,
                $durMins,
                sanitize($_POST['notes'] ?? ''),
                $_SESSION['user_id']
            ]);
        auditLog('Create Reservation', 'reservations', null, null, $_POST['customer_name'].' on '.$resDate);
        flashMessage('success', 'Reservation confirmed.');
    } elseif ($action === 'status') {
        $pdo->prepare("UPDATE reservations SET status=? WHERE id=?")
            ->execute([sanitize($_POST['status']), (int)$_POST['id']]);
        // If seated, mark table occupied
        if ($_POST['status'] === 'seated' && !empty($_POST['table_id'])) {
            $pdo->prepare("UPDATE tables SET status='occupied' WHERE id=?")->execute([(int)$_POST['table_id']]);
        }
        flashMessage('success', 'Status updated.');
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM reservations WHERE id=?")->execute([(int)$_POST['id']]);
        flashMessage('success', 'Reservation removed.');
    }
    redirect(BASE_URL . 'modules/reservations/reservations.php');
}

$dateFilter = sanitize($_GET['date'] ?? date('Y-m-d'));
$reservations = $pdo->prepare(
    "SELECT r.*, t.table_number FROM reservations r
     LEFT JOIN tables t ON r.table_id = t.id
     WHERE r.reserved_date = ?
     ORDER BY r.reserved_time"
);
$reservations->execute([$dateFilter]);
$reservations = $reservations->fetchAll();

$availTables  = $pdo->query("SELECT * FROM tables ORDER BY floor,table_number")->fetchAll();

$todayCount   = $pdo->query("SELECT COUNT(*) FROM reservations WHERE reserved_date=CURDATE()")->fetchColumn();
$upcomingCount= $pdo->query("SELECT COUNT(*) FROM reservations WHERE reserved_date>CURDATE() AND status='confirmed'")->fetchColumn();
?>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card success">
            <div class="stat-icon success"><i class="fa-solid fa-calendar-check"></i></div>
            <div class="stat-value"><?= $todayCount ?></div>
            <div class="stat-label">Today's Reservations</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card info">
            <div class="stat-icon info"><i class="fa-solid fa-calendar-days"></i></div>
            <div class="stat-value"><?= $upcomingCount ?></div>
            <div class="stat-label">Upcoming</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card primary">
            <div class="stat-icon primary"><i class="fa-solid fa-chair"></i></div>
            <div class="stat-value"><?= count($availTables) ?></div>
            <div class="stat-label">Total Tables</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h6><i class="fa-solid fa-calendar-days me-2 text-primary"></i>Reservations</h6>
                <div class="d-flex gap-2">
                    <form method="GET" class="d-flex gap-2">
                        <input type="date" name="date" class="form-control form-control-sm" value="<?= $dateFilter ?>">
                        <button class="btn btn-sm btn-primary">View</button>
                    </form>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addResModal">
                        <i class="fa-solid fa-plus me-1"></i> New
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($reservations)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-calendar-xmark fa-3x mb-3 d-block" style="color:var(--border)"></i>
                    No reservations for <?= date('d M Y', strtotime($dateFilter)) ?>
                </div>
                <?php else: ?>
                <table class="table table-hover mb-0">
                    <thead><tr><th>Time</th><th>Guest</th><th>Phone</th><th>Party</th><th>Table</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($reservations as $r): ?>
                    <tr>
                        <td><strong><?= date('h:i A', strtotime($r['reserved_time'])) ?></strong></td>
                        <td><?= htmlspecialchars($r['customer_name']) ?></td>
                        <td><?= htmlspecialchars($r['customer_phone']) ?></td>
                        <td><i class="fa-solid fa-users me-1"></i><?= $r['party_size'] ?></td>
                        <td><?= htmlspecialchars($r['table_number'] ?? 'Any') ?></td>
                        <td>
                            <?php $cls = match($r['status']) {
                                'confirmed'=>'confirmed','seated'=>'preparing','completed'=>'served',
                                'cancelled'=>'cancelled','no_show'=>'cancelled', default=>'pending' };
                            ?>
                            <span class="badge-status badge-<?= $cls ?>"><?= ucfirst($r['status']) ?></span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <?php if ($r['status'] === 'confirmed'): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="status" value="seated">
                                    <input type="hidden" name="table_id" value="<?= $r['table_id'] ?>">
                                    <button class="btn btn-sm btn-success">Seat</button>
                                </form>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="status" value="no_show">
                                    <button class="btn btn-sm btn-outline-danger">No Show</button>
                                </form>
                                <?php endif; ?>
                                <?php if ($r['status'] === 'seated'): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="status" value="completed">
                                    <button class="btn btn-sm btn-primary">Complete</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this reservation?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button class="btn-icon" style="color:var(--danger)"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php if ($r['notes']): ?>
                    <tr class="table-light">
                        <td colspan="7" class="text-muted" style="font-size:12px;padding:4px 16px;">
                            <i class="fa-solid fa-note-sticky me-1"></i><?= htmlspecialchars($r['notes']) ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Timeline view -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h6>Today's Timeline</h6></div>
            <div class="card-body" style="max-height:500px;overflow-y:auto;">
                <?php
                $todayRes = $pdo->query("SELECT r.*, t.table_number FROM reservations r LEFT JOIN tables t ON r.table_id=t.id WHERE r.reserved_date=CURDATE() ORDER BY r.reserved_time")->fetchAll();
                if (empty($todayRes)): ?>
                <p class="text-muted text-center">No bookings today.</p>
                <?php else: foreach ($todayRes as $tr):
                    $color = match($tr['status']) { 'confirmed'=>'#2980b9','seated'=>'#e67e22','completed'=>'#27ae60','cancelled'=>'#c0392b', default=>'#999' };
                ?>
                <div class="d-flex gap-3 mb-3" style="position:relative;">
                    <div style="width:60px;text-align:right;padding-top:4px;font-size:12px;font-weight:600;color:<?= $color ?>;flex-shrink:0;">
                        <?= date('h:i A', strtotime($tr['reserved_time'])) ?>
                    </div>
                    <div style="width:3px;background:<?= $color ?>;border-radius:2px;flex-shrink:0;"></div>
                    <div style="background:#f9f6f3;border-radius:8px;padding:10px 14px;flex:1;border-left:3px solid <?= $color ?>;">
                        <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($tr['customer_name']) ?></div>
                        <div style="font-size:12px;color:var(--text-muted);">
                            <?= $tr['party_size'] ?> guests · Table <?= htmlspecialchars($tr['table_number'] ?? 'TBD') ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- New Reservation Modal -->
<div class="modal fade" id="addResModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fa-solid fa-calendar-plus me-2"></i>New Reservation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-8">
                            <label class="form-label">Guest Name</label>
                            <input type="text" name="customer_name" class="form-control" required placeholder="Full name">
                        </div>
                        <div class="col-4">
                            <label class="form-label">Party Size</label>
                            <input type="number" name="party_size" class="form-control" value="2" min="1" max="50" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="customer_phone" class="form-control" required placeholder="+91 XXXXX XXXXX">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Date</label>
                            <input type="date" name="reserved_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Time</label>
                            <input type="time" name="reserved_time" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Table (optional)</label>
                            <select name="table_id" class="form-select">
                                <option value="">Any Available</option>
                                <?php foreach ($availTables as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['table_number']) ?> (<?= $t['capacity'] ?> seats, <?= $t['floor'] ?> Floor)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Duration (mins)</label>
                            <select name="duration_mins" class="form-select">
                                <option value="60">60 min</option>
                                <option value="90" selected>90 min</option>
                                <option value="120">120 min</option>
                                <option value="180">180 min</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Anniversary, birthday, dietary needs…"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i> Confirm Reservation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
