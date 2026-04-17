<?php
$pageTitle = 'Staff Attendance';
require_once '../../includes/header.php';
requireRole(['admin','manager']);
$pdo = getDB();

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark') {
        $date = sanitize($_POST['date']);
        foreach ($_POST['attendance'] ?? [] as $uid => $status) {
            $uid = (int)$uid;
            $pdo->prepare("INSERT INTO attendance (user_id,work_date,status) VALUES (?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status)")
                ->execute([$uid, $date, sanitize($status)]);
        }
        foreach ($_POST['clock_in'] ?? [] as $uid => $t) {
            if ($t) $pdo->prepare("UPDATE attendance SET clock_in=? WHERE user_id=? AND work_date=?")->execute([$t,(int)$uid,$date]);
        }
        foreach ($_POST['clock_out'] ?? [] as $uid => $t) {
            if ($t) $pdo->prepare("UPDATE attendance SET clock_out=? WHERE user_id=? AND work_date=?")->execute([$t,(int)$uid,$date]);
        }
        auditLog('Save Attendance', 'attendance', null, null, $date);
        flashMessage('success', 'Attendance saved for ' . date('d M Y', strtotime($date)));
    }
    redirect(BASE_URL . 'modules/attendance/attendance.php?date=' . ($_POST['date'] ?? date('Y-m-d')) . '&tab=' . ($_POST['active_tab'] ?? 'daily'));
}

// ── Data ──────────────────────────────────────────────────────────────────────
$tab        = sanitize($_GET['tab']  ?? 'daily');
$dateFilter = sanitize($_GET['date'] ?? date('Y-m-d'));
$monthStr   = sanitize($_GET['month'] ?? date('Y-m'));      // e.g. 2026-03
$monthLabel = date('F Y', strtotime($monthStr . '-01'));

$staff = $pdo->query("SELECT * FROM users WHERE status='active' ORDER BY role,name")->fetchAll();

// Daily attendance map
$existing = $pdo->prepare("SELECT * FROM attendance WHERE work_date=?");
$existing->execute([$dateFilter]);
$attMap = [];
foreach ($existing->fetchAll() as $a) $attMap[$a['user_id']] = $a;

// Monthly summary
$monthlySummary = $pdo->prepare(
    "SELECT u.id, u.name, u.role, COALESCE(u.daily_wage, 0) as daily_wage,
        SUM(CASE WHEN a.status='present'  THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN a.status='absent'   THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN a.status='half_day' THEN 1 ELSE 0 END) as half,
        SUM(CASE WHEN a.status='leave'    THEN 1 ELSE 0 END) as leave_days,
        COUNT(a.id) as recorded
     FROM users u
     LEFT JOIN attendance a ON a.user_id=u.id AND DATE_FORMAT(a.work_date,'%Y-%m')=?
     WHERE u.status='active'
     GROUP BY u.id ORDER BY u.role,u.name"
);
$monthlySummary->execute([$monthStr]);
$monthlySummary = $monthlySummary->fetchAll();

// Count working days in selected month (Mon–Sat, exclude Sun = simplified)
$daysInMonth    = cal_days_in_month(CAL_GREGORIAN, (int)substr($monthStr,5,2), (int)substr($monthStr,0,4));
$workingDays    = 0;
for ($d = 1; $d <= $daysInMonth; $d++) {
    $dow = date('N', strtotime("$monthStr-" . str_pad($d,2,'0',STR_PAD_LEFT)));
    if ($dow < 7) $workingDays++; // Mon–Sat
}

// Salary settings (₹ per day)
$dailyRates = [
    'manager'  => 800,
    'waiter'   => 500,
    'delivery' => 450,
    'cashier'  => 500,
    'chef'     => 700,
    'admin'    => 1000,
];
?>

<style>
.tab-pills { display:flex; gap:8px; margin-bottom:20px; }
.tab-pill  { padding:8px 20px; border-radius:20px; border:2px solid var(--border); background:#fff; cursor:pointer; font-size:13px; font-weight:500; text-decoration:none; color:var(--text); }
.tab-pill.active { background:var(--primary); border-color:var(--primary); color:#fff; }
.att-badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600; }
.att-present  { background:#d4edda; color:#155724; }
.att-absent   { background:#f8d7da; color:#721c24; }
.att-half     { background:#fff3cd; color:#856404; }
.att-leave    { background:#d1ecf1; color:#0c5460; }
.salary-card  { background:linear-gradient(135deg,#1e1510,#3a2a20); color:#fff; border-radius:12px; padding:16px; }
</style>

<!-- Tabs -->
<div class="tab-pills">
    <a href="?tab=daily&date=<?= $dateFilter ?>" class="tab-pill <?= $tab==='daily'?'active':'' ?>">
        <i class="fa-solid fa-calendar-day me-1"></i> Daily Marking
    </a>
    <a href="?tab=monthly&month=<?= $monthStr ?>" class="tab-pill <?= $tab==='monthly'?'active':'' ?>">
        <i class="fa-solid fa-calendar me-1"></i> Monthly Summary
    </a>
    <a href="?tab=salary&month=<?= $monthStr ?>" class="tab-pill <?= $tab==='salary'?'active':'' ?>">
        <i class="fa-solid fa-sack-dollar me-1"></i> Salary Calculator
    </a>
</div>

<?php if ($tab === 'daily'): ?>
<!-- ── DAILY MARKING ── -->
<div class="card">
    <div class="card-header">
        <h6><i class="fa-solid fa-user-clock me-2 text-primary"></i>Mark Attendance</h6>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="tab" value="daily">
            <input type="date" name="date" class="form-control form-control-sm" value="<?= $dateFilter ?>">
            <button class="btn btn-sm btn-primary">View</button>
            <?php
            $prev = date('Y-m-d', strtotime($dateFilter . ' -1 day'));
            $next = date('Y-m-d', strtotime($dateFilter . ' +1 day'));
            ?>
            <a href="?tab=daily&date=<?= $prev ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-chevron-left"></i></a>
            <a href="?tab=daily&date=<?= $next ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-chevron-right"></i></a>
            <a href="?tab=daily&date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-primary">Today</a>
        </form>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="mark">
            <input type="hidden" name="date" value="<?= $dateFilter ?>">
            <input type="hidden" name="active_tab" value="daily">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Hours</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($staff as $s):
                    $att    = $attMap[$s['id']] ?? null;
                    $status = $att['status'] ?? 'present';
                    $hours  = '';
                    if ($att && $att['clock_in'] && $att['clock_out']) {
                        $diff = strtotime($att['clock_out']) - strtotime($att['clock_in']);
                        $hours = round($diff / 3600, 1) . 'h';
                    }
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($s['name']) ?></strong>
                    </td>
                    <td><span class="badge bg-secondary"><?= ucfirst($s['role']) ?></span></td>
                    <td>
                        <select name="attendance[<?= $s['id'] ?>]" class="form-select form-select-sm" style="width:130px;">
                            <option value="present"  <?= $status==='present' ?'selected':'' ?>>✅ Present</option>
                            <option value="absent"   <?= $status==='absent'  ?'selected':'' ?>>❌ Absent</option>
                            <option value="half_day" <?= $status==='half_day'?'selected':'' ?>>🌓 Half Day</option>
                            <option value="leave"    <?= $status==='leave'   ?'selected':'' ?>>📅 Leave</option>
                        </select>
                    </td>
                    <td><input type="time" name="clock_in[<?= $s['id'] ?>]"  class="form-control form-control-sm" style="width:110px;" value="<?= $att['clock_in']  ?? '' ?>"></td>
                    <td><input type="time" name="clock_out[<?= $s['id'] ?>]" class="form-control form-control-sm" style="width:110px;" value="<?= $att['clock_out'] ?? '' ?>"></td>
                    <td><span class="text-muted" style="font-size:13px;"><?= $hours ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-save me-1"></i> Save Attendance for <?= date('d M Y', strtotime($dateFilter)) ?>
            </button>
        </form>
    </div>
</div>

<?php elseif ($tab === 'monthly'): ?>
<!-- ── MONTHLY SUMMARY ── -->
<div class="card">
    <div class="card-header">
        <h6><i class="fa-solid fa-calendar me-2 text-primary"></i>Monthly Attendance — <?= $monthLabel ?></h6>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="tab" value="monthly">
            <input type="month" name="month" class="form-control form-control-sm" value="<?= $monthStr ?>">
            <button class="btn btn-sm btn-primary">View</button>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="p-3 border-bottom" style="background:#f9f6f3;font-size:13px;">
            <strong>Working days in <?= $monthLabel ?>:</strong> <?= $workingDays ?> days
            (Mon – Sat, excluding Sundays)
        </div>
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Staff</th>
                    <th>Role</th>
                    <th class="text-center" style="color:var(--success);">Present</th>
                    <th class="text-center" style="color:var(--danger);">Absent</th>
                    <th class="text-center" style="color:var(--warning);">Half Day</th>
                    <th class="text-center">Leave</th>
                    <th class="text-center">Not Marked</th>
                    <th class="text-center">Attendance %</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($monthlySummary as $s):
                $effectiveDays = $s['present'] + $s['half']*0.5;
                $pct = $workingDays > 0 ? round($effectiveDays / $workingDays * 100) : 0;
                $notMarked = $workingDays - $s['recorded'];
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                <td><span class="badge bg-secondary"><?= ucfirst($s['role']) ?></span></td>
                <td class="text-center"><span class="att-badge att-present"><?= $s['present'] ?></span></td>
                <td class="text-center"><span class="att-badge att-absent"><?= $s['absent'] ?></span></td>
                <td class="text-center"><span class="att-badge att-half"><?= $s['half'] ?></span></td>
                <td class="text-center"><span class="att-badge att-leave"><?= $s['leave_days'] ?></span></td>
                <td class="text-center"><span class="text-muted"><?= max(0,$notMarked) ?></span></td>
                <td class="text-center">
                    <div class="progress" style="height:6px;margin-bottom:2px;">
                        <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $pct>=80?'var(--success)':($pct>=60?'var(--warning)':'var(--danger)') ?>;"></div>
                    </div>
                    <span style="font-size:12px;font-weight:600;color:<?= $pct>=80?'var(--success)':($pct>=60?'var(--warning)':'var(--danger)') ?>;"><?= $pct ?>%</span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'salary'): ?>
<!-- ── SALARY CALCULATOR ── -->
<div class="card mb-3">
    <div class="card-header">
        <h6><i class="fa-solid fa-sack-dollar me-2 text-primary"></i>Salary Calculator — <?= $monthLabel ?></h6>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="tab" value="salary">
            <input type="month" name="month" class="form-control form-control-sm" value="<?= $monthStr ?>">
            <button class="btn btn-sm btn-primary">Calculate</button>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="p-3 border-bottom" style="background:#f9f6f3;font-size:13px;">
            <i class="fa-solid fa-info-circle me-1 text-primary"></i>
            Working days: <strong><?= $workingDays ?></strong> &nbsp;|&nbsp;
            Salary = (Present × Rate) + (Half Day × Rate × 0.5) &nbsp;|&nbsp;
            <span class="text-muted">Daily rates are default estimates. Adjust below.</span>
        </div>
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Staff</th>
                    <th>Role</th>
                    <th class="text-center">Present</th>
                    <th class="text-center">Half Day</th>
                    <th class="text-center">Absent</th>
                    <th class="text-end">Daily Rate (₹)</th>
                    <th class="text-end">Payable Days</th>
                    <th class="text-end">Salary (₹)</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $totalPayroll = 0;
            foreach ($monthlySummary as $s):
            $rate     = $s['daily_wage'] > 0 ? (float)$s['daily_wage'] : ($dailyRates[$s['role']] ?? 500);
                $payDays  = $s['present'] + ($s['half'] * 0.5);
                $salary   = $payDays * $rate;
                $totalPayroll += $salary;
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                <td><span class="badge bg-secondary"><?= ucfirst($s['role']) ?></span></td>
                <td class="text-center"><span class="att-badge att-present"><?= $s['present'] ?></span></td>
                <td class="text-center"><span class="att-badge att-half"><?= $s['half'] ?></span></td>
                <td class="text-center"><span class="att-badge att-absent"><?= $s['absent'] ?></span></td>
                <td class="text-end">
                    <input type="number" class="form-control form-control-sm text-end salary-rate"
                           style="width:90px;display:inline-block;"
                           value="<?= $rate ?>"
                           data-present="<?= $s['present'] ?>"
                           data-half="<?= $s['half'] ?>"
                           oninput="recalcRow(this)">
                </td>
                <td class="text-end"><?= number_format($payDays, 1) ?></td>
                <td class="text-end">
                    <strong style="color:var(--primary);" class="salary-amt">₹<?= number_format($salary, 0) ?></strong>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f9f6f3;">
                    <td colspan="7" class="text-end"><strong>Total Payroll</strong></td>
                    <td class="text-end"><strong style="font-size:16px;color:var(--primary);" id="totalPayroll">₹<?= number_format($totalPayroll, 0) ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="alert alert-info d-flex align-items-center gap-2">
    <i class="fa-solid fa-lightbulb"></i>
    <div>You can edit the <strong>Daily Rate</strong> for each staff member above — the salary will recalculate instantly. To set permanent rates, go to Staff Management and add a salary field.</div>
</div>

<script>
function recalcRow(input) {
    const row     = input.closest('tr');
    const present = parseFloat(input.dataset.present)||0;
    const half    = parseFloat(input.dataset.half)||0;
    const rate    = parseFloat(input.value)||0;
    const salary  = (present + half*0.5) * rate;
    row.querySelector('.salary-amt').textContent = '₹' + salary.toLocaleString('en-IN',{maximumFractionDigits:0});

    // Recalc total
    let total = 0;
    document.querySelectorAll('.salary-amt').forEach(el => {
        total += parseFloat(el.textContent.replace(/[₹,]/g,''))||0;
    });
    document.getElementById('totalPayroll').textContent = '₹' + total.toLocaleString('en-IN',{maximumFractionDigits:0});
}
</script>

<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
