<?php
// KDS must handle AJAX/print BEFORE any HTML output
require_once '../../includes/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$pdo = getDB();

// ── AJAX: POST status update ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $oid    = (int)($_POST['order_id'] ?? 0);
    if ($action === 'mark_preparing') {
        $pdo->prepare("UPDATE orders SET status='preparing' WHERE id=? AND status='confirmed'")->execute([$oid]);
        auditLog('Mark Preparing', 'kitchen', $oid, 'confirmed', 'preparing');
    } elseif ($action === 'mark_ready') {
        $pdo->prepare("UPDATE orders SET status='ready' WHERE id=? AND status='preparing'")->execute([$oid]);
        auditLog('Mark Ready', 'kitchen', $oid, 'preparing', 'ready');
    }
    echo json_encode(['ok' => true]);
    exit;
}

// ── AJAX: GET cards HTML ──────────────────────────────────────────────────────
if (isset($_GET['ajax'])) {
    $filter   = sanitize($_GET['filter'] ?? 'all');
    $statusIn = match($filter) {
        'confirmed' => "('confirmed')",
        'preparing' => "('preparing')",
        'ready'     => "('ready')",
        default     => "('confirmed','preparing','ready')"
    };
    $orders = $pdo->query(
        "SELECT o.*, t.table_number, u.name as waiter_name
         FROM orders o
         LEFT JOIN tables t ON o.table_id = t.id
         LEFT JOIN users u  ON o.waiter_id = u.id
         WHERE o.status IN $statusIn
         AND DATE(o.created_at) = CURDATE()
         ORDER BY FIELD(o.status,'confirmed','preparing','ready'), o.created_at ASC"
    )->fetchAll();

    if (empty($orders)) {
        echo '<div class="kds-empty"><i class="fa-solid fa-check-circle fa-3x mb-3 d-block" style="color:#27ae60"></i><strong>All clear!</strong><br>No active kitchen orders right now.</div>';
        exit;
    }
    foreach ($orders as $ord) {
        $items = $pdo->prepare("SELECT oi.quantity, m.name, m.is_veg FROM order_items oi JOIN menu_items m ON oi.menu_item_id=m.id WHERE oi.order_id=?");
        $items->execute([$ord['id']]);
        $items = $items->fetchAll();
        $mins  = max(0, round((time() - strtotime($ord['created_at'])) / 60));
        $late  = $mins > 20 ? 'color:#c0392b;font-weight:700;' : '';
        echo '<div class="kds-card ' . $ord['status'] . '">';
        echo '<div class="kds-header">';
        echo '<div><div class="kds-order-num">' . htmlspecialchars($ord['order_number']) . '</div>';
        echo '<div style="font-size:12px;opacity:.85;">' . ucfirst(str_replace('_', ' ', $ord['order_type'])) . ' · ' . htmlspecialchars($ord['waiter_name'] ?? '') . '</div></div>';
        echo '<div class="kds-table">' . htmlspecialchars($ord['table_number'] ?? ($ord['order_type'] === 'takeaway' ? 'TKW' : 'DLV')) . '</div>';
        echo '</div><div class="kds-body">';
        foreach ($items as $it) {
            echo '<div class="kds-item"><span>' . ($it['is_veg'] ? '🟢' : '🔴') . ' ' . htmlspecialchars($it['name']) . '</span><span class="qty">×' . $it['quantity'] . '</span></div>';
        }
        if ($ord['special_instructions']) {
            echo '<div class="kds-note"><i class="fa-solid fa-triangle-exclamation me-1"></i>' . htmlspecialchars($ord['special_instructions']) . '</div>';
        }
        echo '</div><div class="kds-footer">';
        echo '<span style="font-size:11px;' . $late . '"><i class="fa-solid fa-clock me-1"></i>' . $mins . ' min ago</span>';
        echo '<div class="ms-auto d-flex gap-1">';
        echo '<button class="btn btn-sm btn-outline-secondary" onclick="printKOT(' . $ord['id'] . ')" title="Print KOT"><i class="fa-solid fa-print"></i></button>';
        if ($ord['status'] === 'confirmed')  echo '<button class="btn btn-sm btn-warning" onclick="updateStatus(' . $ord['id'] . ',\'preparing\')">Start Cooking</button>';
        if ($ord['status'] === 'preparing')  echo '<button class="btn btn-sm btn-success" onclick="updateStatus(' . $ord['id'] . ',\'ready\')">Mark Ready ✓</button>';
        if ($ord['status'] === 'ready')      echo '<span class="badge bg-success align-self-center">Ready to Serve</span>';
        echo '</div></div></div>';
    }
    exit;
}

// ── KOT print popup ───────────────────────────────────────────────────────────
if (isset($_GET['print_kot'])) {
    $oid = (int)$_GET['print_kot'];
    $ko  = $pdo->prepare("SELECT o.*, t.table_number FROM orders o LEFT JOIN tables t ON o.table_id=t.id WHERE o.id=?");
    $ko->execute([$oid]); $ko = $ko->fetch();
    $ki  = $pdo->prepare("SELECT oi.quantity, m.name FROM order_items oi JOIN menu_items m ON oi.menu_item_id=m.id WHERE oi.order_id=?");
    $ki->execute([$oid]); $ki = $ki->fetchAll();
    $kotNum = 'KOT-' . date('Ymd') . '-' . str_pad($oid, 4, '0', STR_PAD_LEFT);
    try { $pdo->prepare("INSERT IGNORE INTO kot_tickets (order_id,ticket_number,items_json,printed) VALUES (?,?,?,1)")->execute([$oid,$kotNum,json_encode($ki)]); } catch(Exception $e) {}
    auditLog('Print KOT', 'kitchen', $oid, null, $kotNum);
    echo '<!DOCTYPE html><html><head><style>body{font-family:monospace;font-size:13px;padding:10px;width:80mm}h3{text-align:center}.line{border-top:1px dashed #000;margin:6px 0}.row{display:flex;justify-content:space-between}.big{font-size:20px;font-weight:900}</style></head>';
    echo '<body onload="window.print();setTimeout(()=>window.close(),600)">';
    echo '<h3>🍳 KITCHEN ORDER TICKET</h3><div class="line"></div>';
    echo '<div class="row"><span>Ticket:</span><b>' . $kotNum . '</b></div>';
    echo '<div class="row"><span>Order:</span><span>' . htmlspecialchars($ko['order_number'] ?? '') . '</span></div>';
    echo '<div class="row"><span>Table:</span><span class="big">' . htmlspecialchars($ko['table_number'] ?? strtoupper($ko['order_type'])) . '</span></div>';
    echo '<div class="row"><span>Type:</span><span>' . ucfirst(str_replace('_', ' ', $ko['order_type'] ?? '')) . '</span></div>';
    echo '<div class="row"><span>Time:</span><span>' . date('h:i A') . '</span></div>';
    if (!empty($ko['special_instructions'])) echo '<div class="line"></div><b>⚠ ' . htmlspecialchars($ko['special_instructions']) . '</b>';
    echo '<div class="line"></div><b>ITEMS:</b><br>';
    foreach ($ki as $k) echo '<div class="row"><span>' . htmlspecialchars($k['name']) . '</span><b>×' . $k['quantity'] . '</b></div>';
    echo '<div class="line"></div><div style="text-align:center;font-size:11px;">*** END OF KOT ***</div></body></html>';
    exit;
}

// ── Normal page load ──────────────────────────────────────────────────────────
$pageTitle = 'Kitchen Display';
require_once '../../includes/header.php';
?>
<style>
.kds-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
.kds-card{border-radius:12px;overflow:hidden;border:2px solid;transition:all .3s}
.kds-card.confirmed{border-color:#2980b9;background:#eaf4fb}
.kds-card.preparing{border-color:#e67e22;background:#fff8f0}
.kds-card.ready{border-color:#27ae60;background:#eafaf1}
.kds-header{padding:12px 16px;display:flex;justify-content:space-between;align-items:center;color:#fff}
.kds-card.confirmed .kds-header{background:#2980b9}
.kds-card.preparing .kds-header{background:#e67e22}
.kds-card.ready .kds-header{background:#27ae60}
.kds-order-num{font-size:16px;font-weight:700;font-family:'Playfair Display',serif}
.kds-table{font-size:28px;font-weight:900;opacity:.9}
.kds-body{padding:14px 16px}
.kds-item{display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid rgba(0,0,0,.07);font-size:14px}
.kds-item:last-child{border:none}
.kds-item .qty{font-weight:700;color:#b5451b}
.kds-note{margin-top:8px;padding:8px;background:#fef3cd;border-radius:6px;font-size:12px}
.kds-footer{padding:12px 16px;border-top:1px solid rgba(0,0,0,.1);display:flex;gap:8px;align-items:center}
.kds-empty{text-align:center;padding:60px 20px;color:var(--text-muted);grid-column:1/-1}
.kds-tab{padding:8px 18px;border-radius:20px;border:2px solid var(--border);background:#fff;cursor:pointer;font-weight:500;font-size:13px;transition:all .2s}
.kds-tab.active{background:var(--primary);border-color:var(--primary);color:#fff}
.live-badge{background:#27ae60;color:#fff;font-size:10px;padding:2px 8px;border-radius:10px;font-weight:700;letter-spacing:1px;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.6}}
</style>

<div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center gap-3">
        <h5 class="mb-0" style="font-family:'Playfair Display',serif;">🍳 Kitchen Display</h5>
        <span class="live-badge">LIVE</span>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <span class="text-muted" style="font-size:12px;" id="lastRefresh">Auto-refreshes every 20s</span>
        <button class="btn btn-sm btn-outline-secondary" onclick="loadKDS()">
            <i class="fa-solid fa-rotate-right me-1"></i> Refresh Now
        </button>
    </div>
</div>

<div class="d-flex gap-2 mb-4 flex-wrap">
    <button class="kds-tab active" onclick="filterKDS('all',this)">All Active</button>
    <button class="kds-tab" onclick="filterKDS('confirmed',this)">🔵 New Orders</button>
    <button class="kds-tab" onclick="filterKDS('preparing',this)">🟠 Preparing</button>
    <button class="kds-tab" onclick="filterKDS('ready',this)">🟢 Ready to Serve</button>
</div>

<div class="kds-grid" id="kdsGrid">
    <div class="kds-empty"><i class="fa-solid fa-spinner fa-spin fa-2x mb-3 d-block"></i>Loading kitchen orders…</div>
</div>

<script>
let currentFilter = 'all';

async function loadKDS() {
    try {
        const r = await fetch('kds.php?ajax=1&filter=' + currentFilter);
        const html = await r.text();
        document.getElementById('kdsGrid').innerHTML = html;
        document.getElementById('lastRefresh').textContent = 'Updated ' + new Date().toLocaleTimeString('en-IN', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
    } catch(e) { console.error('KDS load failed', e); }
}

function filterKDS(f, btn) {
    currentFilter = f;
    document.querySelectorAll('.kds-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loadKDS();
}

async function updateStatus(orderId, status) {
    const fd = new FormData();
    fd.append('ajax','1');
    fd.append('action', status === 'preparing' ? 'mark_preparing' : 'mark_ready');
    fd.append('order_id', orderId);
    await fetch('kds.php', {method:'POST', body:fd});
    loadKDS();
}

function printKOT(orderId) {
    window.open('kds.php?print_kot=' + orderId, '_blank', 'width=420,height=620');
}

loadKDS();
setInterval(loadKDS, 20000);
</script>

<?php require_once '../../includes/footer.php'; ?>
