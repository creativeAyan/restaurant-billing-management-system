<?php
$pageTitle = 'New Order';
require_once '../../includes/header.php';
$pdo = getDB();

// ── AJAX: customer lookup ─────────────────────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'lookup') {
    header('Content-Type: application/json');
    $phone = sanitize($_GET['phone'] ?? '');
    $name  = sanitize($_GET['name']  ?? '');
    if ($phone) {
        $stmt = $pdo->prepare("SELECT id,name,phone,address,loyalty_points FROM customers WHERE phone LIKE ? LIMIT 6");
        $stmt->execute(["%$phone%"]);
    } else {
        $stmt = $pdo->prepare("SELECT id,name,phone,address,loyalty_points FROM customers WHERE name LIKE ? LIMIT 6");
        $stmt->execute(["%$name%"]);
    }
    echo json_encode($stmt->fetchAll());
    exit;
}

// ── AJAX: coupon check ────────────────────────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'coupon') {
    header('Content-Type: application/json');
    $code   = strtoupper(sanitize($_GET['code'] ?? ''));
    $amount = (float)($_GET['amount'] ?? 0);
    $stmt   = $pdo->prepare("SELECT * FROM coupons WHERE code=? AND status='active' AND valid_from<=CURDATE() AND valid_to>=CURDATE()");
    $stmt->execute([$code]);
    $c = $stmt->fetch();
    if (!$c)                                            { echo json_encode(['ok'=>false,'msg'=>'Invalid or expired coupon code.']); exit; }
    if ($c['max_uses']>0 && $c['used_count']>=$c['max_uses']) { echo json_encode(['ok'=>false,'msg'=>'This coupon has reached its usage limit.']); exit; }
    if ($amount < $c['min_order_amount'])               { echo json_encode(['ok'=>false,'msg'=>'Minimum order of ₹'.number_format($c['min_order_amount'],0).' required.']); exit; }
    $disc = $c['discount_type']==='percent' ? round($amount*$c['discount_value']/100, 2) : $c['discount_value'];
    echo json_encode(['ok'=>true,'discount'=>$disc,'msg'=>$c['description'],'coupon_id'=>$c['id']]);
    exit;
}

// ── POST: place order ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderType    = sanitize($_POST['order_type'] ?? 'dine_in');
    $tableId      = !empty($_POST['table_id']) ? (int)$_POST['table_id'] : null;
    $instructions = sanitize($_POST['special_instructions'] ?? '');
    $items        = json_decode($_POST['items'] ?? '[]', true);
    $customerId   = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;

    if (empty($items)) {
        flashMessage('error', 'Please add at least one item.');
        redirect(BASE_URL . 'modules/orders/new_order.php');
    }

    // Customer upsert
    $custPhone = sanitize($_POST['cust_phone'] ?? '');
    $custName  = sanitize($_POST['cust_name']  ?? '');
    $custAddr  = sanitize($_POST['cust_address'] ?? '');
    if ($custPhone && $custName && !$customerId) {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone=?");
        $stmt->execute([$custPhone]);
        $ex = $stmt->fetch();
        if ($ex) {
            $customerId = $ex['id'];
        } else {
            $pdo->prepare("INSERT INTO customers (name,phone,address) VALUES (?,?,?)")->execute([$custName,$custPhone,$custAddr]);
            $customerId = $pdo->lastInsertId();
        }
    }

    $orderNumber = generateOrderNumber();
    $pdo->prepare("INSERT INTO orders (order_number,order_type,table_id,customer_id,waiter_id,special_instructions,status) VALUES (?,?,?,?,?,?,'confirmed')")
        ->execute([$orderNumber, $orderType, $tableId, $customerId, $_SESSION['user_id'], $instructions]);
    $orderId = $pdo->lastInsertId();

    foreach ($items as $item) {
        $menuId = (int)$item['id'];
        $qty    = max(1,(int)$item['qty']);
        $pr = $pdo->prepare("SELECT price FROM menu_items WHERE id=?");
        $pr->execute([$menuId]);
        $pr = $pr->fetch();
        if ($pr) $pdo->prepare("INSERT INTO order_items (order_id,menu_item_id,quantity,unit_price) VALUES (?,?,?,?)")->execute([$orderId,$menuId,$qty,$pr['price']]);
    }

    if ($tableId) $pdo->prepare("UPDATE tables SET status='occupied' WHERE id=?")->execute([$tableId]);
    if ($orderType==='delivery') $pdo->prepare("INSERT INTO deliveries (order_id,delivery_address,status) VALUES (?,?,'pending')")->execute([$orderId,$custAddr]);

    // Handle coupon usage
    $couponId = (int)($_POST['coupon_id'] ?? 0);
    if ($couponId) $pdo->prepare("UPDATE coupons SET used_count=used_count+1 WHERE id=?")->execute([$couponId]);

    // Handle loyalty redemption
    $redeemPts = (int)($_POST['redeem_points'] ?? 0);
    if ($redeemPts > 0 && $customerId) {
        $pdo->prepare("UPDATE customers SET loyalty_points=loyalty_points-? WHERE id=? AND loyalty_points>=?")->execute([$redeemPts,$customerId,$redeemPts]);
        $pdo->prepare("INSERT INTO loyalty_transactions (customer_id,type,points,description) VALUES (?,'redeem',?,?)")->execute([$customerId,-$redeemPts,"Redeemed on order $orderNumber"]);
    }

    auditLog('Create Order', 'orders', $orderId, null, $orderNumber);
    flashMessage('success', "Order #$orderNumber placed! Add more items or generate bill.");
    redirect(BASE_URL . 'modules/orders/view_order.php?id=' . $orderId);
}

// ── Data ──────────────────────────────────────────────────────────────────────
$categories     = $pdo->query("SELECT * FROM categories WHERE status='active'")->fetchAll();
$menuItems      = $pdo->query("SELECT m.*, c.name as cat_name FROM menu_items m JOIN categories c ON m.category_id=c.id WHERE m.available=1 ORDER BY c.id,m.name")->fetchAll();
$tables         = $pdo->query("SELECT * FROM tables WHERE status='available' ORDER BY floor,table_number")->fetchAll();
$taxPct         = (float)(getSetting('tax_percent') ?: 5);
$svcPct         = (float)(getSetting('service_charge_percent') ?: 5);
$delChg         = (float)(getSetting('delivery_charge') ?: 50);
?>
<style>
.type-btn{border:2px solid var(--border);border-radius:10px;padding:10px 12px;cursor:pointer;text-align:center;transition:all .2s;background:#fff;flex:1;user-select:none;}
.type-btn.active{border-color:var(--primary);background:#fde8e0;}
.type-btn .t-icon{font-size:22px;display:block;margin-bottom:4px;}
.type-btn .t-label{font-size:11px;font-weight:600;color:var(--text-muted);}
.type-btn.active .t-label{color:var(--primary);}
.cust-lookup{position:relative;}
.cust-drop{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid var(--border);border-radius:8px;box-shadow:var(--shadow-lg);z-index:300;display:none;max-height:220px;overflow-y:auto;}
.cust-item{padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--border);font-size:13px;}
.cust-item:last-child{border:none;}
.cust-item:hover{background:#fde8e0;}
.cust-item b{display:block;}
.cust-item small{color:var(--text-muted);}
.loyalty-pill{background:linear-gradient(135deg,#b5451b,#d45f30);color:#fff;border-radius:10px;padding:12px 16px;margin-top:8px;display:none;}
.loyalty-pill .lp-pts{font-size:26px;font-weight:800;line-height:1;}
.coupon-ok{background:#d4edda;color:#155724;border-radius:6px;padding:8px 12px;font-size:13px;margin-top:6px;display:none;}
.coupon-err{background:#f8d7da;color:#721c24;border-radius:6px;padding:8px 12px;font-size:13px;margin-top:6px;display:none;}
</style>

<div class="row g-3">
    <!-- LEFT: Menu grid -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h6><i class="fa-solid fa-book-open me-2 text-primary"></i>Select Items</h6>
                <input type="text" class="form-control form-control-sm" style="width:200px;" placeholder="Search menu…" oninput="searchMenu(this.value)">
            </div>
            <div class="card-body" style="max-height:calc(100vh - 200px);overflow-y:auto;">
                <div class="d-flex gap-2 flex-wrap mb-3">
                    <button class="btn btn-sm btn-primary cat-filter-btn" data-cat="all" onclick="filterCategory('all')">All</button>
                    <?php foreach ($categories as $cat): ?>
                    <button class="btn btn-sm btn-outline-secondary cat-filter-btn" data-cat="<?= $cat['id'] ?>" onclick="filterCategory(<?= $cat['id'] ?>)"><?= htmlspecialchars($cat['name']) ?></button>
                    <?php endforeach; ?>
                </div>
                <?php
                $grouped = [];
                foreach ($menuItems as $m) $grouped[$m['cat_name']][] = $m;
                foreach ($grouped as $catName => $mItems):
                    $catId = $mItems[0]['category_id'];
                ?>
                <div class="menu-category-section mb-3" data-cat="<?= $catId ?>">
                    <div class="fw-600 mb-2" style="color:#b5451b;font-size:12px;text-transform:uppercase;letter-spacing:1px;"><?= htmlspecialchars($catName) ?></div>
                    <div class="row g-2">
                        <?php foreach ($mItems as $item): ?>
                        <div class="col-6 col-md-4">
                            <div class="menu-item-card" id="menu_card_<?= $item['id'] ?>" data-name="<?= strtolower(htmlspecialchars($item['name'])) ?>"
                                 onclick="pickItem(<?= $item['id'] ?>,'<?= addslashes($item['name']) ?>',<?= $item['price'] ?>)">
                                <div class="item-badge" id="badge_<?= $item['id'] ?>">1</div>
                                <div class="d-flex align-items-center gap-1 mb-1">
                                    <span class="veg-dot <?= $item['is_veg']?'veg':'non-veg' ?>"></span>
                                    <span style="font-size:13px;font-weight:500;"><?= htmlspecialchars($item['name']) ?></span>
                                </div>
                                <div style="font-size:14px;font-weight:700;color:#b5451b;">₹<?= number_format($item['price'],2) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT: Order form -->
    <div class="col-lg-5">
        <form method="POST" id="orderForm">
            <!-- Order Type -->
            <div class="card mb-2">
                <div class="card-body">
                    <div class="form-label mb-2" style="font-size:13px;">Order Type</div>
                    <div class="d-flex gap-2">
                        <div class="type-btn active" id="btn_dine_in" onclick="setType('dine_in')"><span class="t-icon">🍽️</span><span class="t-label">Dine In</span></div>
                        <div class="type-btn" id="btn_delivery" onclick="setType('delivery')"><span class="t-icon">🛵</span><span class="t-label">Delivery</span></div>
                        <div class="type-btn" id="btn_takeaway" onclick="setType('takeaway')"><span class="t-icon">🥡</span><span class="t-label">Takeaway</span></div>
                    </div>
                    <input type="hidden" name="order_type" id="orderTypeInput" value="dine_in">
                </div>
            </div>

            <!-- Table (dine-in only) -->
            <div class="card mb-2" id="tableCard">
                <div class="card-body">
                    <label class="form-label" style="font-size:13px;">Table</label>
                    <select name="table_id" class="form-select form-select-sm">
                        <option value="">-- Select Table --</option>
                        <?php foreach ($tables as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['table_number']) ?> — <?= $t['floor'] ?> Floor (<?= $t['capacity'] ?> seats)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Customer -->
            <div class="card mb-2">
                <div class="card-header" style="padding:10px 16px;">
                    <h6 class="mb-0" style="font-size:13px;"><i class="fa-solid fa-user me-2 text-primary"></i>Customer <span id="custStar" style="display:none;color:var(--danger);">*required</span></h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-12 cust-lookup">
                            <label class="form-label" style="font-size:12px;">Phone</label>
                            <div class="input-group input-group-sm">
                                <input type="text" name="cust_phone" id="custPhone" class="form-control" placeholder="Enter phone to search existing customer" oninput="debounce('phone')">
                                <button type="button" class="btn btn-outline-secondary" onclick="doLookup('phone')" title="Search"><i class="fa-solid fa-magnifying-glass"></i></button>
                            </div>
                            <div class="cust-drop" id="dropPhone"></div>
                        </div>
                        <div class="col-12 cust-lookup">
                            <label class="form-label" style="font-size:12px;">Name</label>
                            <div class="input-group input-group-sm">
                                <input type="text" name="cust_name" id="custName" class="form-control" placeholder="Or search by name" oninput="debounce('name')">
                                <button type="button" class="btn btn-outline-secondary" onclick="doLookup('name')" title="Search"><i class="fa-solid fa-magnifying-glass"></i></button>
                            </div>
                            <div class="cust-drop" id="dropName"></div>
                        </div>
                        <div class="col-12" id="addrField" style="display:none;">
                            <label class="form-label" style="font-size:12px;">Delivery Address</label>
                            <textarea name="cust_address" id="custAddr" class="form-control form-control-sm" rows="2" placeholder="Full delivery address"></textarea>
                        </div>
                    </div>
                    <input type="hidden" name="customer_id" id="custIdHidden">

                    <!-- Loyalty box -->
                    <div class="loyalty-pill" id="loyaltyPill">
                        <div style="font-size:11px;opacity:.8;text-transform:uppercase;letter-spacing:1px;">Loyalty Balance</div>
                        <div class="lp-pts" id="lpPts">0</div>
                        <div style="font-size:11px;opacity:.8;">points = ₹<span id="lpRupee">0</span> redeemable</div>
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <span style="font-size:12px;white-space:nowrap;">Redeem:</span>
                            <input type="number" name="redeem_points" id="redeemPts" class="form-control form-control-sm"
                                   style="max-width:80px;background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.3);color:#fff;"
                                   placeholder="0" min="0" oninput="applyLoyalty()">
                            <span style="font-size:12px;" id="redeemRupee"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coupon -->
            <div class="card mb-2">
                <div class="card-body">
                    <label class="form-label" style="font-size:13px;"><i class="fa-solid fa-tag me-1 text-primary"></i>Coupon / Promo Code</label>
                    <div class="input-group input-group-sm">
                        <input type="text" id="couponCode" name="coupon_code" class="form-control" placeholder="Enter code…" style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()">
                        <button type="button" class="btn btn-outline-primary" onclick="applyCoupon()">Apply</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="clearCoupon()">✕</button>
                    </div>
                    <div id="couponOk"  class="coupon-ok"></div>
                    <div id="couponErr" class="coupon-err"></div>
                    <input type="hidden" name="coupon_discount" id="couponDisc" value="0">
                    <input type="hidden" name="coupon_id"       id="couponId"   value="">
                </div>
            </div>

            <!-- Items list -->
            <div class="card mb-2">
                <div class="card-header" style="padding:10px 16px;">
                    <h6 class="mb-0" style="font-size:13px;"><i class="fa-solid fa-cart-shopping me-2 text-primary"></i>Selected Items</h6>
                </div>
                <div class="card-body" style="max-height:220px;overflow-y:auto;">
                    <div id="orderItemsContainer">
                        <div id="emptyMsg" class="text-muted text-center py-3" style="font-size:13px;">
                            <i class="fa-solid fa-bowl-food fa-2x mb-2 d-block" style="color:#e8e0d8;"></i>
                            Click items from the menu to add
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bill summary -->
            <div class="card mb-2">
                <div class="card-body py-2">
                    <div class="bill-summary">
                        <div class="bill-row"><span>Subtotal</span><span id="billSubtotal">₹0.00</span></div>
                        <div class="bill-row"><span>Tax (<?= $taxPct ?>%)</span><span id="billTax">₹0.00</span></div>
                        <div class="bill-row" id="svcRow"><span>Service (<?= $svcPct ?>%)</span><span id="billService">₹0.00</span></div>
                        <div class="bill-row" id="delRow" style="display:none;"><span>Delivery</span><span id="billDelivery">₹0.00</span></div>
                        <div class="bill-row" id="discRow" style="display:none;"><span>Discount</span><span id="billDiscount" style="color:var(--success);"></span></div>
                        <div class="bill-row total"><span>TOTAL</span><span id="billTotal">₹0.00</span></div>
                    </div>
                    <input type="hidden" id="hiddenItems" name="items">
                    <input type="hidden" id="hiddenTotal" name="total_amount">
                    <input type="hidden" id="taxRate"      value="<?= $taxPct ?>">
                    <input type="hidden" id="serviceRate"  value="<?= $svcPct ?>">
                    <input type="hidden" id="deliveryCharge" value="0">
                    <input type="hidden" id="discountAmount" value="0">
                </div>
            </div>

            <!-- Instructions -->
            <div class="mb-2">
                <textarea name="special_instructions" class="form-control form-control-sm" rows="2" placeholder="Special instructions (optional)"></textarea>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg">
                <i class="fa-solid fa-check me-2"></i> Place Order
            </button>
            <p class="text-center text-muted mt-1" style="font-size:11px;">You can add more items after placing the order from the order view page</p>
        </form>
    </div>
</div>

<script>
// Constants
const TAX_PCT   = <?= $taxPct ?>;
const SVC_PCT   = <?= $svcPct ?>;
const DEL_CHG   = <?= $delChg ?>;
const LY_RATE   = 0.25; // ₹ per point

let orderType      = 'dine_in';
let loyaltyDisc    = 0;
let couponDisc     = 0;
let maxPts         = 0;
let timer          = null;

// ── Type selector ─────────────────────────────────────────────
function setType(t) {
    orderType = t;
    document.getElementById('orderTypeInput').value = t;
    ['dine_in','delivery','takeaway'].forEach(x =>
        document.getElementById('btn_'+x).classList.toggle('active', x===t));
    document.getElementById('tableCard').style.display   = t==='dine_in'  ? '' : 'none';
    document.getElementById('addrField').style.display   = t==='delivery' ? '' : 'none';
    document.getElementById('delRow').style.display      = t==='delivery' ? '' : 'none';
    document.getElementById('svcRow').style.display      = t!=='delivery' ? '' : 'none';
    document.getElementById('custStar').style.display    = t!=='dine_in'  ? '' : 'none';
    document.getElementById('deliveryCharge').value = t==='delivery' ? DEL_CHG : 0;
    document.getElementById('serviceRate').value    = t!=='delivery' ? SVC_PCT : 0;
    recalc();
}

// ── Item picking (wraps app.js functions) ─────────────────────
function pickItem(id, name, price) {
    const em = document.getElementById('emptyMsg');
    if (em) em.remove();
    toggleMenuItem(id, name, price);
    recalc();
}
// Hook app.js functions to also recalc
['removeOrDecrement','incrementItem'].forEach(fn => {
    const orig = window[fn];
    window[fn] = function(...a){ orig(...a); recalc(); };
});

// ── Totals ────────────────────────────────────────────────────
function getSub() {
    let s=0;
    document.querySelectorAll('.order-item-row').forEach(r=>{
        s += parseInt(r.querySelector('.qty-num')?.textContent||0) * parseFloat(r.dataset.price||0);
    });
    return s;
}
function recalc() {
    const sub  = getSub();
    const tax  = sub * TAX_PCT/100;
    const svc  = sub * (parseFloat(document.getElementById('serviceRate').value)||0)/100;
    const del  = parseFloat(document.getElementById('deliveryCharge').value)||0;
    const disc = loyaltyDisc + couponDisc;
    const tot  = Math.max(0, sub+tax+svc+del-disc);

    const S = (id,v) => { const e=document.getElementById(id); if(e) e.textContent='₹'+v.toFixed(2); };
    S('billSubtotal',sub); S('billTax',tax); S('billService',svc); S('billDelivery',del);
    const dr = document.getElementById('discRow');
    if (disc>0) {
        dr.style.display='';
        document.getElementById('billDiscount').textContent = '-₹'+disc.toFixed(2);
    } else { dr.style.display='none'; }
    S('billTotal',tot);
    document.getElementById('hiddenTotal').value = tot.toFixed(2);
    document.getElementById('discountAmount').value = disc.toFixed(2);
    syncHiddenItems();
}

// ── Customer Lookup ───────────────────────────────────────────
function debounce(field) {
    clearTimeout(timer);
    timer = setTimeout(()=>doLookup(field), 380);
}
async function doLookup(field) {
    const val = document.getElementById(field==='phone'?'custPhone':'custName').value.trim();
    if (val.length < 3) { closeDrops(); return; }
    const url = `new_order.php?ajax=lookup&${field}=${encodeURIComponent(val)}`;
    const res = await fetch(url);
    const data= await res.json();
    renderDrop(data, field==='phone'?'dropPhone':'dropName');
}
function renderDrop(list, dropId) {
    const box = document.getElementById(dropId);
    if (!list.length) { box.style.display='none'; return; }
    box.innerHTML = list.map(c=>`
        <div class="cust-item" onclick="pick(${c.id},'${esc(c.name)}','${esc(c.phone)}','${esc(c.address||'')}',${c.loyalty_points||0})">
            <b>${h(c.name)}</b>
            <small>${h(c.phone)} &nbsp;·&nbsp; ⭐ ${c.loyalty_points||0} pts</small>
        </div>`).join('');
    box.style.display='block';
}
function pick(id,name,phone,addr,pts) {
    document.getElementById('custPhone').value   = phone;
    document.getElementById('custName').value    = name;
    document.getElementById('custIdHidden').value= id;
    const a=document.getElementById('custAddr');
    if(a&&addr) a.value=addr;
    closeDrops();
    // Show loyalty
    maxPts = pts;
    const pill = document.getElementById('loyaltyPill');
    document.getElementById('lpPts').textContent    = pts;
    document.getElementById('lpRupee').textContent  = (pts*LY_RATE).toFixed(2);
    pill.style.display = pts>0 ? 'block' : 'none';
}
function closeDrops() {
    ['dropPhone','dropName'].forEach(id=>{ const e=document.getElementById(id); if(e) e.style.display='none'; });
}
document.addEventListener('click', e=>{ if(!e.target.closest('.cust-lookup')) closeDrops(); });

// Loyalty
function applyLoyalty() {
    let pts = parseInt(document.getElementById('redeemPts').value)||0;
    if (pts>maxPts) { pts=maxPts; document.getElementById('redeemPts').value=pts; }
    loyaltyDisc = pts*LY_RATE;
    document.getElementById('redeemRupee').textContent = pts>0 ? `= ₹${loyaltyDisc.toFixed(2)} off` : '';
    recalc();
}

// ── Coupon ────────────────────────────────────────────────────
async function applyCoupon() {
    const code = document.getElementById('couponCode').value.trim().toUpperCase();
    if (!code) return;
    const sub = getSub();
    const res = await fetch(`new_order.php?ajax=coupon&code=${encodeURIComponent(code)}&amount=${sub}`);
    const d   = await res.json();
    document.getElementById('couponOk').style.display  = 'none';
    document.getElementById('couponErr').style.display = 'none';
    if (d.ok) {
        document.getElementById('couponOk').innerHTML  = `✅ <b>${h(d.msg)}</b> — ₹${d.discount.toFixed(2)} off`;
        document.getElementById('couponOk').style.display = 'block';
        couponDisc = d.discount;
        document.getElementById('couponDisc').value = d.discount;
        document.getElementById('couponId').value   = d.coupon_id;
    } else {
        document.getElementById('couponErr').textContent  = '❌ '+d.msg;
        document.getElementById('couponErr').style.display= 'block';
        couponDisc = 0;
    }
    recalc();
}
function clearCoupon() {
    document.getElementById('couponCode').value='';
    document.getElementById('couponOk').style.display='none';
    document.getElementById('couponErr').style.display='none';
    document.getElementById('couponDisc').value=0;
    document.getElementById('couponId').value='';
    couponDisc=0; recalc();
}

// ── Helpers ───────────────────────────────────────────────────
function h(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function esc(s){return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'");}

// ── Form validation ───────────────────────────────────────────
document.getElementById('orderForm').addEventListener('submit', function(e){
    let items=[];
    try{items=JSON.parse(document.getElementById('hiddenItems').value||'[]');}catch(_){}
    if(!items.length){ e.preventDefault(); alert('Please add at least one item.'); return; }
    if(orderType!=='dine_in' && !document.getElementById('custPhone').value.trim()){
        e.preventDefault(); alert('Phone number is required for Delivery / Takeaway.'); return;
    }
    syncHiddenItems();
});
</script>

<?php require_once '../../includes/footer.php'; ?>
