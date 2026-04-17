<?php
$pageTitle = 'Staff Management';
require_once '../../includes/header.php';
requireRole(['admin']);
$pdo = getDB();

$error = '';
// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name      = sanitize($_POST['name']);
        $username  = sanitize($_POST['username']);
        $password  = $_POST['password'];
        $role      = sanitize($_POST['role']);
        $phone     = sanitize($_POST['phone'] ?? '');
        $email     = sanitize($_POST['email'] ?? '');
        $dailyWage = (float)($_POST['daily_wage'] ?? 0);

        if ($name && $username && $password && $role) {
            $check = $pdo->prepare("SELECT id FROM users WHERE username=?");
            $check->execute([$username]);
            if ($check->fetch()) {
                flashMessage('error', 'Username already exists.');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (name,username,password,role,phone,email,daily_wage) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$name, $username, $hash, $role, $phone, $email, $dailyWage]);
                auditLog('Add Staff', 'staff', null, null, "$name ($role)");
                flashMessage('success', "Staff member '$name' added!");
            }
        } else {
            flashMessage('error', 'All required fields must be filled.');
        }

    } elseif ($action === 'update_wage') {
        $id   = (int)$_POST['user_id'];
        $wage = (float)$_POST['daily_wage'];
        $pdo->prepare("UPDATE users SET daily_wage=? WHERE id=?")->execute([$wage, $id]);
        auditLog('Update Wage', 'staff', $id, null, "₹$wage/day");
        flashMessage('success', 'Daily wage updated.');
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['user_id'];
        if ($id !== $_SESSION['user_id']) {
            $pdo->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id=?")->execute([$id]);
            flashMessage('success', 'Status updated.');
        }

    } elseif ($action === 'reset_password') {
        $id       = (int)$_POST['user_id'];
        $password = $_POST['new_password'] ?? '';
        if ($password && strlen($password) >= 4) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $id]);
            flashMessage('success', 'Password reset successfully.');
        } else {
            flashMessage('error', 'Password must be at least 4 characters.');
        }

    } elseif ($action === 'delete') {
        $id = (int)$_POST['user_id'];
        if ($id !== $_SESSION['user_id']) {
            $delUser = $pdo->prepare("SELECT name FROM users WHERE id=?"); $delUser->execute([$id]); $delUser = $delUser->fetch();
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            auditLog('Delete Staff', 'staff', $id, $delUser['name'] ?? '', null);
            flashMessage('success', 'Staff removed.');
        } else {
            flashMessage('error', 'You cannot delete your own account.');
        }
    }

    redirect(BASE_URL . 'modules/admin/staff.php');
}

$users = $pdo->query("SELECT * FROM users ORDER BY role, name")->fetchAll();
$roles = ['admin','manager','waiter','delivery','cashier','chef'];

// Role permissions explanation
$rolePerms = [
    'admin'    => ['Full system access','Staff management','All reports','Settings','Audit log','Delete records'],
    'manager'  => ['All orders & billing','Inventory & expenses','Reports & analytics','Reservations','Attendance','Cannot manage staff/settings'],
    'waiter'   => ['Take new orders','View own orders','Kitchen display','Table management','Cannot access reports or billing'],
    'delivery' => ['View delivery orders','Update delivery status','Cannot access POS or billing'],
    'cashier'  => ['Billing only','View orders','Print receipts','Cannot take orders or view reports'],
    'chef'     => ['Kitchen display only','Mark orders ready','Cannot access POS or billing'],
];
?>

<div class="row g-3">
    <!-- Add Staff Form -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h6><i class="fa-solid fa-user-plus me-2 text-primary"></i>Add Staff Member</h6></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-2">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. John Doe">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" required placeholder="Login username">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required placeholder="Min 4 characters">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <?php foreach ($roles as $r): ?>
                            <option value="<?= $r ?>"><?= ucfirst($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="Optional">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="Optional">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Daily Wage (₹) <span class="text-muted" style="font-size:11px;">— used for salary calculation</span></label>
                        <input type="number" name="daily_wage" class="form-control" placeholder="e.g. 500" min="0" step="0.01">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa-solid fa-plus me-1"></i> Add Staff
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Staff List -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h6><i class="fa-solid fa-users me-2 text-primary"></i>All Staff (<?= count($users) ?>)</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>Name</th><th>Username</th><th>Role</th><th>Phone</th><th>Daily Wage</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width:32px;height:32px;border-radius:50%;background:#b5451b;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:13px;flex-shrink:0;">
                                        <?= strtoupper(substr($u['name'],0,1)) ?>
                                    </div>
                                    <?= htmlspecialchars($u['name']) ?>
                                    <?php if ($u['id'] == $_SESSION['user_id']): ?><small class="text-muted">(you)</small><?php endif; ?>
                                </div>
                            </td>
                            <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                            <td>
                                <?php $roleColors = ['admin'=>'danger','manager'=>'primary','waiter'=>'success','delivery'=>'warning']; ?>
                                <span class="badge bg-<?= $roleColors[$u['role']] ?? 'secondary' ?>"><?= ucfirst($u['role']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
                            <td>
                                <form method="POST" class="d-flex align-items-center gap-1" style="min-width:120px;">
                                    <input type="hidden" name="action" value="update_wage">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="number" name="daily_wage" value="<?= number_format($u['daily_wage'] ?? 0, 0) ?>"
                                           class="form-control form-control-sm" style="width:75px;" min="0" step="1">
                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Save wage"><i class="fa-solid fa-check"></i></button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="badge-status <?= $u['status']==='active'?'badge-paid':'badge-cancelled' ?>"
                                        style="border:none;cursor:pointer;" <?= $u['id']==$_SESSION['user_id']?'disabled':'' ?>>
                                        <?= ucfirst($u['status']) ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <!-- Reset Password -->
                                    <button class="btn-icon" title="Reset Password" onclick="showResetPassword(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')">
                                        <i class="fa-solid fa-key"></i>
                                    </button>
                                    <!-- Delete -->
                                    <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($u['name'])) ?>?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn-icon text-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Reset Password</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="resetUserId">
                <p class="text-muted mb-3" style="font-size:13px;">Resetting password for: <strong id="resetUserName"></strong></p>
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" required minlength="4" placeholder="Min 4 characters">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Reset</button>
            </div>
        </form>
    </div>
</div>

<script>
function showResetPassword(userId, userName) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetUserName').textContent = userName;
    new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
}
</script>

<!-- Role Permissions Guide -->
<div class="card mt-3">
    <div class="card-header">
        <h6><i class="fa-solid fa-key me-2 text-primary"></i>Role Permissions Guide</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php
            $roleColors2 = ['admin'=>'danger','manager'=>'primary','waiter'=>'success','delivery'=>'warning','cashier'=>'info','chef'=>'secondary'];
            foreach ($rolePerms as $role => $perms):
            ?>
            <div class="col-md-4 col-lg-2">
                <div style="border:1px solid var(--border);border-radius:10px;padding:12px;height:100%;">
                    <div class="text-center mb-2">
                        <span class="badge bg-<?= $roleColors2[$role] ?? 'secondary' ?> mb-1"><?= ucfirst($role) ?></span>
                    </div>
                    <ul style="font-size:12px;color:var(--text-muted);padding-left:16px;margin:0;">
                        <?php foreach ($perms as $p): ?>
                        <li style="margin-bottom:3px;"><?= htmlspecialchars($p) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
