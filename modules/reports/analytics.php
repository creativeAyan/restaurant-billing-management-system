<?php
$pageTitle = 'Reports & Analytics';
require_once '../../includes/header.php';
requireRole(['admin','manager']);
$pdo = getDB();

$from = sanitize($_GET['from'] ?? date('Y-m-01'));
$to   = sanitize($_GET['to']   ?? date('Y-m-d'));
// Ensure $to always covers the full day including today
$to = $to ?: date('Y-m-d');

// Revenue
$revenue = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM bills WHERE DATE(created_at) BETWEEN ? AND ? AND payment_status='paid'");
$revenue->execute([$from,$to]); $revenue = (float)$revenue->fetchColumn();

$taxCollected = $pdo->prepare("SELECT COALESCE(SUM(tax_amount),0) FROM bills WHERE DATE(created_at) BETWEEN ? AND ? AND payment_status='paid'");
$taxCollected->execute([$from,$to]); $taxCollected = (float)$taxCollected->fetchColumn();

$discounts = $pdo->prepare("SELECT COALESCE(SUM(discount_amount),0) FROM bills WHERE DATE(created_at) BETWEEN ? AND ? AND payment_status='paid'");
$discounts->execute([$from,$to]); $discounts = (float)$discounts->fetchColumn();

$billCount = $pdo->prepare("SELECT COUNT(*) FROM bills WHERE DATE(created_at) BETWEEN ? AND ? AND payment_status='paid'");
$billCount->execute([$from,$to]); $billCount = (int)$billCount->fetchColumn();

// Expenses
$expenses = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date BETWEEN ? AND ?");
$expenses->execute([$from,$to]); $expenses = (float)$expenses->fetchColumn();

$expByCategory = $pdo->prepare("SELECT category, COALESCE(SUM(amount),0) as total FROM expenses WHERE expense_date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC");
$expByCategory->execute([$from,$to]); $expByCategory = $expByCategory->fetchAll();

$profit = $revenue - $expenses;
$margin = $revenue > 0 ? round($profit / $revenue * 100, 1) : 0;

// Daily breakdown
$daily = $pdo->prepare("SELECT DATE(b.created_at) as day, COUNT(b.id) as bills, SUM(b.total_amount) as rev, COALESCE((SELECT SUM(e.amount) FROM expenses e WHERE e.expense_date=DATE(b.created_at)),0) as exp FROM bills b WHERE DATE(b.created_at) BETWEEN ? AND ? AND b.payment_status='paid' GROUP BY DATE(b.created_at) ORDER BY day");
$daily->execute([$from,$to]); $daily = $daily->fetchAll();

// Top items
$topItems = $pdo->prepare("SELECT m.name, m.is_veg, SUM(oi.quantity) as qty, SUM(oi.quantity*oi.unit_price) as rev FROM order_items oi JOIN menu_items m ON oi.menu_item_id=m.id JOIN orders o ON oi.order_id=o.id WHERE DATE(o.created_at) BETWEEN ? AND ? GROUP BY m.id ORDER BY qty DESC LIMIT 10");
$topItems->execute([$from,$to]); $topItems = $topItems->fetchAll();

// Waiter performance
$waiters = $pdo->prepare("SELECT u.name, COUNT(DISTINCT o.id) as orders, COALESCE(SUM(b.total_amount),0) as revenue, COALESCE(AVG(b.total_amount),0) as avg_bill FROM users u LEFT JOIN orders o ON o.waiter_id=u.id LEFT JOIN bills b ON b.order_id=o.id AND b.payment_status='paid' WHERE u.role='waiter' AND (DATE(o.created_at) BETWEEN ? AND ? OR o.id IS NULL) GROUP BY u.id ORDER BY revenue DESC");
$waiters->execute([$from,$to]); $waiters = $waiters->fetchAll();

// Payment method split
$payMethods = $pdo->prepare("SELECT payment_method, COUNT(*) as cnt, SUM(total_amount) as total FROM bills WHERE DATE(created_at) BETWEEN ? AND ? AND payment_status='paid' GROUP BY payment_method");
$payMethods->execute([$from,$to]); $payMethods = $payMethods->fetchAll();

// Order type split
$orderTypes = $pdo->prepare("SELECT o.order_type, COUNT(b.id) as cnt, SUM(b.total_amount) as total FROM bills b JOIN orders o ON b.order_id=o.id WHERE DATE(b.created_at) BETWEEN ? AND ? AND b.payment_status='paid' GROUP BY o.order_type");
$orderTypes->execute([$from,$to]); $orderTypes = $orderTypes->fetchAll();

// Hourly heatmap
$hourly = $pdo->prepare("SELECT HOUR(created_at) as hr, COUNT(*) as cnt, SUM(total_amount) as rev FROM bills WHERE DATE(created_at) BETWEEN ? AND ? AND payment_status='paid' GROUP BY HOUR(created_at) ORDER BY hr");
$hourly->execute([$from,$to]);
$hourlyData = array_fill(0,24,['cnt'=>0,'rev'=>0]);
foreach($hourly->fetchAll() as $h) $hourlyData[$h['hr']] = $h;
?>

<!-- Date Filter -->
<form method="GET" class="d-flex gap-2 mb-4 no-print align-items-end">
    <div>
        <label class="form-label">From</label>
        <input type="date" name="from" class="form-control form-control-sm" value="<?= $from ?>">
    </div>
    <div>
        <label class="form-label">To</label>
        <input type="date" name="to" class="form-control form-control-sm" value="<?= $to ?>">
    </div>
    <div class="d-flex gap-1">
        <button class="btn btn-primary btn-sm">Apply</button>
        <a href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-secondary">Today</a>
        <a href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-secondary">This Month</a>
        <button class="btn btn-sm btn-outline-primary no-print" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Print</button>
    </div>
</form>

<!-- P&L Summary -->
<div class="row g-3 mb-4">
    <div class="col-sm-3">
        <div class="stat-card success">
            <div class="stat-icon success"><i class="fa-solid fa-arrow-trend-up"></i></div>
            <div class="stat-value">₹<?= number_format($revenue,0) ?></div>
            <div class="stat-label">Gross Revenue</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card warning">
            <div class="stat-icon warning"><i class="fa-solid fa-cart-shopping"></i></div>
            <div class="stat-value">₹<?= number_format($expenses,0) ?></div>
            <div class="stat-label">Total Expenses</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card <?= $profit>=0?'success':'primary' ?>">
            <div class="stat-icon <?= $profit>=0?'success':'primary' ?>"><i class="fa-solid fa-sack-dollar"></i></div>
            <div class="stat-value" style="color:<?= $profit>=0?'var(--success)':'var(--danger)' ?>">₹<?= number_format(abs($profit),0) ?></div>
            <div class="stat-label"><?= $profit>=0?'Net Profit':'Net Loss' ?></div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card info">
            <div class="stat-icon info"><i class="fa-solid fa-percent"></i></div>
            <div class="stat-value"><?= $margin ?>%</div>
            <div class="stat-label">Profit Margin</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card p-3 text-center">
            <div style="font-size:24px;font-weight:700;color:var(--primary);"><?= $billCount ?></div>
            <div class="text-muted" style="font-size:12px;">Bills Paid</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card p-3 text-center">
            <div style="font-size:24px;font-weight:700;color:var(--primary);">₹<?= $billCount>0?number_format($revenue/$billCount,0):'0' ?></div>
            <div class="text-muted" style="font-size:12px;">Avg Bill Value</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card p-3 text-center">
            <div style="font-size:24px;font-weight:700;color:var(--danger);">₹<?= number_format($discounts,0) ?></div>
            <div class="text-muted" style="font-size:12px;">Discounts Given</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Hourly heatmap -->
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header"><h6><i class="fa-solid fa-clock me-2 text-primary"></i>Hourly Sales Heatmap</h6></div>
            <div class="card-body">
                <?php
                $maxHrRev = max(array_column($hourlyData,'rev')+[0]);
                $peakHrs  = [7,8,9,12,13,14,19,20,21,22];
                ?>
                <div style="display:flex;gap:4px;align-items:flex-end;height:80px;">
                <?php for($h=0;$h<24;$h++):
                    $d    = $hourlyData[$h];
                    $pct  = $maxHrRev>0 ? ($d['rev']/$maxHrRev)*100 : 0;
                    $isPeak = in_array($h,$peakHrs);
                ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;" title="<?= $h ?>:00 — ₹<?= number_format($d['rev'],0) ?> (<?= $d['cnt'] ?> bills)">
                    <div style="width:100%;height:<?= max(4,$pct)?>%;background:<?= $d['cnt']>0?'var(--primary)':'var(--border)' ?>;border-radius:3px 3px 0 0;transition:height .3s;"></div>
                    <div style="font-size:9px;color:var(--text-muted);transform:rotate(-45deg);transform-origin:top right;width:16px;"><?= $h ?>h</div>
                </div>
                <?php endfor; ?>
                </div>
                <div class="mt-2 text-muted" style="font-size:11px;">Hover bars for details · Darker = more revenue</div>
            </div>
        </div>

        <!-- Daily breakdown table -->
        <div class="card mb-3">
            <div class="card-header"><h6><i class="fa-solid fa-table me-2 text-primary"></i>Daily Breakdown</h6></div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Date</th><th>Bills</th><th>Revenue</th><th>Expenses</th><th>Profit</th><th>Margin</th></tr></thead>
                    <tbody>
                    <?php foreach($daily as $d):
                        $dp = $d['rev'] - $d['exp'];
                        $dm = $d['rev']>0 ? round($dp/$d['rev']*100,1) : 0;
                    ?>
                    <tr>
                        <td><?= date('D, d M', strtotime($d['day'])) ?></td>
                        <td><?= $d['bills'] ?></td>
                        <td>₹<?= number_format($d['rev'],0) ?></td>
                        <td>₹<?= number_format($d['exp'],0) ?></td>
                        <td class="<?= $dp>=0?'text-success':'text-danger' ?> fw-600">₹<?= number_format(abs($dp),0) ?></td>
                        <td><span style="color:<?= $dm>=30?'var(--success)':($dm>=15?'var(--warning)':'var(--danger)') ?>"><?= $dm ?>%</span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($daily)): ?><tr><td colspan="6" class="text-center text-muted">No data for this period.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right column -->
    <div class="col-lg-4">
        <!-- P&L box -->
        <div class="card mb-3" style="border:2px solid var(--border);">
            <div class="card-header"><h6>Profit & Loss Summary</h6></div>
            <div class="card-body">
                <div class="bill-summary">
                    <div class="bill-row"><span>Gross Revenue</span><span style="color:var(--success);">₹<?= number_format($revenue,2) ?></span></div>
                    <div class="bill-row"><span>Less: Discounts</span><span style="color:var(--danger);">–₹<?= number_format($discounts,2) ?></span></div>
                    <div class="bill-row"><span>Net Revenue</span><span>₹<?= number_format($revenue-$discounts,2) ?></span></div>
                    <div class="bill-row"><span>Less: Expenses</span><span style="color:var(--danger);">–₹<?= number_format($expenses,2) ?></span></div>
                    <div class="bill-row total"><span><?= $profit>=0?'NET PROFIT':'NET LOSS' ?></span><span style="color:<?= $profit>=0?'var(--success)':'var(--danger)' ?>">₹<?= number_format(abs($profit),2) ?></span></div>
                </div>
                <div class="mt-3">
                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Expenses by Category</div>
                    <?php foreach($expByCategory as $ec): $epct=$expenses>0?round($ec['total']/$expenses*100):0; ?>
                    <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:13px;">
                        <span><?= htmlspecialchars($ec['category']) ?></span>
                        <span>₹<?= number_format($ec['total'],0) ?></span>
                    </div>
                    <div class="progress mb-2" style="height:4px;">
                        <div class="progress-bar" style="width:<?= $epct ?>%;background:var(--warning);"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Payment methods -->
        <div class="card mb-3">
            <div class="card-header"><h6>Payment Methods</h6></div>
            <div class="card-body">
                <?php foreach($payMethods as $pm): $pmpct=$revenue>0?round($pm['total']/$revenue*100):0; ?>
                <div class="d-flex justify-content-between mb-1" style="font-size:13px;">
                    <span><?= ucfirst($pm['payment_method']) ?></span>
                    <span>₹<?= number_format($pm['total'],0) ?> (<?= $pmpct ?>%)</span>
                </div>
                <div class="progress mb-2" style="height:4px;">
                    <div class="progress-bar" style="width:<?= $pmpct ?>%;background:var(--primary);"></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Order types -->
        <div class="card mb-3">
            <div class="card-header"><h6>Order Types</h6></div>
            <div class="card-body">
                <?php foreach($orderTypes as $ot): $otpct=$revenue>0?round($ot['total']/$revenue*100):0; ?>
                <div class="d-flex justify-content-between mb-1" style="font-size:13px;">
                    <span><?= ucfirst(str_replace('_',' ',$ot['order_type'])) ?></span>
                    <span><?= $ot['cnt'] ?> orders · ₹<?= number_format($ot['total'],0) ?></span>
                </div>
                <div class="progress mb-2" style="height:4px;">
                    <div class="progress-bar" style="width:<?= $otpct ?>%;background:var(--info);"></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Top items -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6><i class="fa-solid fa-fire me-2 text-primary"></i>Top Selling Items</h6></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>#</th><th>Item</th><th>Qty Sold</th><th>Revenue</th></tr></thead>
                    <tbody>
                    <?php foreach($topItems as $i=>$it): ?>
                    <tr>
                        <td><strong style="color:<?= $i<3?'var(--primary)':'inherit' ?>"><?= $i+1 ?></strong></td>
                        <td><span class="veg-dot <?= $it['is_veg']?'veg':'non-veg' ?>"></span><?= htmlspecialchars($it['name']) ?></td>
                        <td><?= $it['qty'] ?></td>
                        <td>₹<?= number_format($it['rev'],0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Waiter performance -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6><i class="fa-solid fa-user-tie me-2 text-primary"></i>Waiter Performance</h6></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Waiter</th><th>Orders</th><th>Revenue</th><th>Avg Bill</th></tr></thead>
                    <tbody>
                    <?php foreach($waiters as $w): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($w['name']) ?></strong></td>
                        <td><?= $w['orders'] ?></td>
                        <td>₹<?= number_format($w['revenue'],0) ?></td>
                        <td>₹<?= number_format($w['avg_bill'],0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
