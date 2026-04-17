<?php
$pageTitle = 'My Deliveries';
require_once '../../includes/header.php';
requireRole(['delivery','admin','manager']);
$pdo    = getDB();
$myId   = (int)$_SESSION['user_id'];
$isRider= ($_SESSION['user_role'] === 'delivery');

// Handle status update
if (isset($_GET['action']) && $_GET['action'] === 'update' && isset($_GET['did'], $_GET['status'])) {
    $did    = (int)$_GET['did'];
    $status = sanitize($_GET['status']);
    // Delivery boy can only update their own
    $whereOwn = $isRider ? "AND delivery_person_id = $myId" : "";
    $sql = $status === 'delivered'
        ? "UPDATE deliveries SET status=?, delivered_at=NOW() WHERE id=? $whereOwn"
        : "UPDATE deliveries SET status=? WHERE id=? $whereOwn";
    $pdo->prepare($sql)->execute([$status, $did]);
    auditLog('Update Delivery', 'delivery', $did, null, $status);
    flashMessage('success', 'Status updated to ' . ucfirst($status));
    redirect(BASE_URL . 'modules/delivery/my_deliveries.php');
}

$filter  = sanitize($_GET['filter'] ?? 'all');
$whereStatus = match($filter) {
    'active'    => "AND d.status IN ('assigned','picked')",
    'delivered' => "AND d.status='delivered' AND DATE(d.delivered_at)=CURDATE()",
    'pending'   => "AND d.status='pending'",
    default     => ""
};
$whereRider = $isRider ? "AND d.delivery_person_id = $myId" : "";

$deliveries = $pdo->query("
    SELECT d.*,
        o.order_number, o.created_at AS order_time,
        c.name AS cust_name, c.phone AS cust_phone,
        u.name AS rider_name,
        (SELECT SUM(oi.quantity*oi.unit_price) FROM order_items oi WHERE oi.order_id=d.order_id) AS order_total
    FROM deliveries d
    JOIN orders o ON o.id = d.order_id
    LEFT JOIN customers c ON c.id = o.customer_id
    LEFT JOIN users u ON u.id = d.delivery_person_id
    WHERE 1=1 $whereRider $whereStatus
    ORDER BY FIELD(d.status,'picked','assigned','pending','delivered','failed'), d.id DESC
    LIMIT 100
")->fetchAll();

// Stats for this rider
$stats = $pdo->prepare("
    SELECT
        SUM(status IN ('assigned','picked')) AS active,
        SUM(status='delivered' AND DATE(delivered_at)=CURDATE()) AS today_done,
        SUM(status='delivered') AS total_done,
        COUNT(*) AS total
    FROM deliveries WHERE delivery_person_id=?
");
$stats->execute([$myId]);
$stats = $stats->fetch();
?>

<style>
.rider-stat { background:#fff; border-radius:12px; padding:16px; text-align:center; border:1px solid var(--border); }
.rider-stat .rs-num { font-size:32px; font-weight:800; font-family:'Playfair Display',serif; color:var(--primary); line-height:1; }
.rider-stat .rs-label { font-size:12px; color:var(--text-muted); margin-top:4px; }
.delivery-card { background:#fff; border-radius:12px; border:1px solid var(--border); margin-bottom:12px; overflow:hidden; }
.delivery-card .dc-header { padding:12px 16px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); }
.delivery-card .dc-body { padding:14px 16px; }
.delivery-card .dc-footer { padding:10px 16px; background:#faf7f4; display:flex; gap:8px; flex-wrap:wrap; }
.status-picked { background:#fff3cd; border-left:4px solid #f0ad4e !important; }
.status-assigned { background:#eaf4fb; border-left:4px solid #2980b9 !important; }
.status-delivered { background:#eafaf1; border-left:4px solid #27ae60 !important; }
.filter-tab { padding:7px 16px; border-radius:20px; border:1.5px solid var(--border); background:#fff; font-size:13px; font-weight:500; cursor:pointer; text-decoration:none; color:var(--text); transition:all .15s; }
.filter-tab.active, .filter-tab:hover { background:var(--primary); border-color:var(--primary); color:#fff; text-decoration:none; }
</style>

<!-- Stats row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="rider-stat">
            <div class="rs-num" style="color:#e67e22;"><?= $stats['active'] ?? 0 ?></div>
            <div class="rs-label">Active Now</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rider-stat">
            <div class="rs-num" style="color:#27ae60;"><?= $stats['today_done'] ?? 0 ?></div>
            <div class="rs-label">Delivered Today</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rider-stat">
            <div class="rs-num"><?= $stats['total_done'] ?? 0 ?></div>
            <div class="rs-label">Total Delivered</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rider-stat">
            <div class="rs-num" style="color:#2980b9;"><?= $stats['total'] ?? 0 ?></div>
            <div class="rs-label">All Orders</div>
        </div>
    </div>
</div>

<!-- Filter tabs -->
<div class="d-flex gap-2 mb-4 flex-wrap">
    <a href="?filter=all"       class="filter-tab <?= $filter==='all'?'active':'' ?>">🚀 All</a>
    <a href="?filter=active"    class="filter-tab <?= $filter==='active'?'active':'' ?>">⚡ Active</a>
    <a href="?filter=delivered" class="filter-tab <?= $filter==='delivered'?'active':'' ?>">✅ Today's Completed</a>
</div>

<!-- Delivery cards -->
<?php if (empty($deliveries)): ?>
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="fa-solid fa-motorcycle fa-3x mb-3 d-block" style="color:var(--border);"></i>
        <h5>No deliveries<?= $filter !== 'all' ? ' for this filter' : ' assigned yet' ?></h5>
        <p>When an order is assigned to you, it will appear here.</p>
    </div>
</div>
<?php else: ?>
<?php foreach ($deliveries as $d):
    $statusClass = 'status-' . $d['status'];
    $addr = htmlspecialchars($d['delivery_address'] ?: 'No address on record');
?>
<div class="delivery-card <?= $statusClass ?>">
    <div class="dc-header">
        <div>
            <strong style="font-size:16px;"><?= htmlspecialchars($d['order_number']) ?></strong>
            <span class="ms-2 badge-status badge-<?= $d['status']==='delivered'?'paid':($d['status']==='picked'?'preparing':'confirmed') ?>"><?= ucfirst($d['status']) ?></span>
        </div>
        <div style="font-size:13px;color:var(--text-muted);">
            ₹<?= number_format($d['order_total'] ?? 0, 0) ?>
        </div>
    </div>
    <div class="dc-body">
        <div class="row g-2">
            <div class="col-sm-6">
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:2px;">CUSTOMER</div>
                <div style="font-weight:600;"><?= htmlspecialchars($d['cust_name'] ?? 'Unknown') ?></div>
                <div style="font-size:13px;">
                    <?php if ($d['cust_phone']): ?>
                    <a href="tel:<?= htmlspecialchars($d['cust_phone']) ?>" style="color:var(--primary);text-decoration:none;">
                        <i class="fa-solid fa-phone me-1"></i><?= htmlspecialchars($d['cust_phone']) ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-sm-6">
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:2px;">ADDRESS</div>
                <div style="font-size:13px;"><?= $addr ?></div>
                <?php if ($d['delivery_address']): ?>
                <a href="https://maps.google.com?q=<?= urlencode($d['delivery_address']) ?>" target="_blank" style="font-size:12px;color:var(--primary);">
                    <i class="fa-solid fa-map-location-dot me-1"></i>Open in Maps
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="mt-2" style="font-size:12px;color:var(--text-muted);">
            Ordered: <?= date('h:i A — d M', strtotime($d['order_time'])) ?>
            <?php if ($d['assigned_at']): ?>
            &nbsp;·&nbsp; Assigned: <?= date('h:i A', strtotime($d['assigned_at'])) ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="dc-footer">
        <?php if ($d['status'] === 'assigned'): ?>
            <a href="?action=update&did=<?= $d['id'] ?>&status=picked" class="btn btn-warning btn-sm">
                <i class="fa-solid fa-motorcycle me-1"></i> I've Picked Up
            </a>
        <?php elseif ($d['status'] === 'picked'): ?>
            <a href="?action=update&did=<?= $d['id'] ?>&status=delivered"
               class="btn btn-success btn-sm"
               onclick="return confirm('Confirm delivery to <?= addslashes($d['cust_name'] ?? 'customer') ?>?')">
                <i class="fa-solid fa-check me-1"></i> Mark Delivered
            </a>
        <?php elseif ($d['status'] === 'delivered'): ?>
            <span style="color:#27ae60;font-size:13px;font-weight:600;">
                <i class="fa-solid fa-circle-check me-1"></i>
                Delivered at <?= $d['delivered_at'] ? date('h:i A', strtotime($d['delivered_at'])) : '—' ?>
            </span>
        <?php endif; ?>
        <?php if (!in_array($d['status'], ['delivered','failed'])): ?>
        <a href="?action=update&did=<?= $d['id'] ?>&status=failed"
           class="btn btn-outline-danger btn-sm ms-auto"
           onclick="return confirm('Mark as delivery failed?')">
            <i class="fa-solid fa-xmark me-1"></i> Failed
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
