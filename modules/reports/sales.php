<?php
$pageTitle = 'Sales Report';
require_once '../../includes/header.php';
requireRole(['admin','manager']);
$pdo = getDB();

$from = sanitize($_GET['from'] ?? date('Y-m-01'));
$to   = sanitize($_GET['to']   ?? date('Y-m-d'));

// Summary
$summary = $pdo->prepare("SELECT 
    COUNT(*) as total_bills,
    SUM(total_amount) as total_revenue,
    SUM(discount_amount) as total_discount,
    SUM(tax_amount) as total_tax,
    AVG(total_amount) as avg_bill
    FROM bills WHERE DATE(created_at) BETWEEN ? AND ? AND payment_status='paid'");
$summary->execute([$from, $to]);
$summary = $summary->fetch();

// By payment method
$payMethods = $pdo->prepare("SELECT payment_method, COUNT(*) as count, SUM(total_amount) as total FROM bills WHERE DATE(created_at) BETWEEN ? AND ? AND payment_status='paid' GROUP BY payment_method");
$payMethods->execute([$from, $to]);
$payMethods = $payMethods->fetchAll();

// By order type
$orderTypes = $pdo->prepare("SELECT o.order_type, COUNT(*) as count, SUM(b.total_amount) as total FROM bills b JOIN orders o ON b.order_id=o.id WHERE DATE(b.created_at) BETWEEN ? AND ? AND b.payment_status='paid' GROUP BY o.order_type");
$orderTypes->execute([$from, $to]);
$orderTypes = $orderTypes->fetchAll();

// Top items
$topItems = $pdo->prepare("SELECT m.name, SUM(oi.quantity) as qty_sold, SUM(oi.quantity*oi.unit_price) as revenue FROM order_items oi JOIN menu_items m ON oi.menu_item_id=m.id JOIN orders o ON oi.order_id=o.id WHERE DATE(o.created_at) BETWEEN ? AND ? GROUP BY m.id ORDER BY qty_sold DESC LIMIT 10");
$topItems->execute([$from, $to]);
$topItems = $topItems->fetchAll();

// Daily sales
$dailySales = $pdo->prepare("SELECT DATE(created_at) as day, COUNT(*) as orders, SUM(total_amount) as revenue FROM bills WHERE DATE(created_at) BETWEEN ? AND ? AND payment_status='paid' GROUP BY DATE(created_at) ORDER BY day");
$dailySales->execute([$from, $to]);
$dailySales = $dailySales->fetchAll();

$chartLabels = json_encode(array_map(fn($r) => date('d M', strtotime($r['day'])), $dailySales));
$chartData   = json_encode(array_map(fn($r) => round($r['revenue'],2), $dailySales));
?>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
            <label class="form-label mb-0">From:</label>
            <input type="date" name="from" class="form-control form-control-sm" style="width:auto" value="<?= $from ?>">
            <label class="form-label mb-0">To:</label>
            <input type="date" name="to"   class="form-control form-control-sm" style="width:auto" value="<?= $to ?>">
            <button class="btn btn-sm btn-primary" type="submit">Generate Report</button>
            <?php foreach ([['This Month','from='.date('Y-m-01').'&to='.date('Y-m-d')],['Last 7 Days','from='.date('Y-m-d',strtotime('-6 days')).'&to='.date('Y-m-d')],['Today','from='.date('Y-m-d').'&to='.date('Y-m-d')]] as [$label,$params]): ?>
            <a href="?<?= $params ?>" class="btn btn-sm btn-outline-secondary"><?= $label ?></a>
            <?php endforeach; ?>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="stat-card primary">
            <div class="stat-icon primary"><i class="fa-solid fa-indian-rupee-sign"></i></div>
            <div class="stat-value">₹<?= number_format($summary['total_revenue']??0,0) ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card success">
            <div class="stat-icon success"><i class="fa-solid fa-receipt"></i></div>
            <div class="stat-value"><?= $summary['total_bills']??0 ?></div>
            <div class="stat-label">Total Bills</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card warning">
            <div class="stat-icon warning"><i class="fa-solid fa-calculator"></i></div>
            <div class="stat-value">₹<?= number_format($summary['avg_bill']??0,0) ?></div>
            <div class="stat-label">Avg Bill Value</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card info">
            <div class="stat-icon info"><i class="fa-solid fa-percent"></i></div>
            <div class="stat-value">₹<?= number_format($summary['total_tax']??0,0) ?></div>
            <div class="stat-label">Total Tax</div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h6><i class="fa-solid fa-chart-line me-2 text-primary"></i>Daily Revenue</h6></div>
            <div class="card-body"><canvas id="revenueChart" height="120"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h6><i class="fa-solid fa-chart-pie me-2 text-primary"></i>By Order Type</h6></div>
            <div class="card-body"><canvas id="typeChart" height="180"></canvas></div>
        </div>
    </div>
</div>

<!-- Tables Row -->
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6><i class="fa-solid fa-trophy me-2 text-primary"></i>Top Selling Items</h6></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>#</th><th>Item</th><th>Qty Sold</th><th>Revenue</th></tr></thead>
                    <tbody>
                        <?php foreach ($topItems as $i => $item): ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><?= $item['qty_sold'] ?></td>
                            <td>₹<?= number_format($item['revenue'],2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6><i class="fa-solid fa-credit-card me-2 text-primary"></i>Payment Methods</h6></div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead><tr><th>Method</th><th>Transactions</th><th>Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($payMethods as $p): ?>
                        <tr>
                            <td><?= ucfirst($p['payment_method']) ?></td>
                            <td><?= $p['count'] ?></td>
                            <td>₹<?= number_format($p['total'],2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
// Revenue chart
new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
        labels: <?= $chartLabels ?>,
        datasets: [{
            label: 'Revenue (₹)',
            data: <?= $chartData ?>,
            borderColor: '#b5451b',
            backgroundColor: 'rgba(181,69,27,0.1)',
            fill: true, tension: 0.3, pointBackgroundColor: '#b5451b'
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});

// Order type pie
const typeData = <?= json_encode($orderTypes) ?>;
new Chart(document.getElementById('typeChart'), {
    type: 'doughnut',
    data: {
        labels: typeData.map(d => d.order_type.replace('_',' ')),
        datasets: [{ data: typeData.map(d => d.total), backgroundColor: ['#b5451b','#d4a853','#2980b9'] }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
