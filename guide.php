<?php
$pageTitle = 'System Guide';
require_once 'includes/header.php';
?>
<style>
.guide-section{background:#fff;border-radius:12px;border:1px solid var(--border);margin-bottom:20px;overflow:hidden;}
.guide-head{padding:16px 20px;background:var(--primary);color:#fff;display:flex;align-items:center;gap:12px;}
.guide-head .gh-icon{width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
.guide-head h5{margin:0;font-size:15px;}
.guide-body{padding:16px 20px;}
.step{display:flex;gap:14px;margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid #f5f1ed;}
.step:last-child{border:none;margin-bottom:0;padding-bottom:0;}
.step-num{width:28px;height:28px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;margin-top:2px;}
.step-body strong{display:block;font-size:14px;margin-bottom:3px;}
.step-body p{margin:0;font-size:13px;color:var(--text-muted);line-height:1.6;}
.step-body .path{background:#f5f1ed;padding:2px 8px;border-radius:4px;font-family:monospace;font-size:12px;color:#b5451b;}
.role-card{border-radius:10px;padding:14px;border:1px solid var(--border);height:100%;}
.tip-box{background:#fef3cd;border:1px solid #ffd875;border-radius:8px;padding:12px 16px;font-size:13px;margin-top:10px;}
.tip-box strong{color:#856404;}
</style>

<div class="row g-3 mb-4">
    <div class="col-12">
        <div style="background:linear-gradient(135deg,#1e1510,#3a2a20);color:#fff;border-radius:14px;padding:24px;display:flex;align-items:center;gap:20px;">
            <div style="font-size:48px;">📖</div>
            <div>
                <h4 style="font-family:'Playfair Display',serif;margin:0;">System Guide & How-To</h4>
                <p style="margin:6px 0 0;opacity:.8;font-size:14px;">Step-by-step instructions for every feature in the system</p>
            </div>
        </div>
    </div>
</div>

<!-- Role Overview -->
<div class="guide-section">
    <div class="guide-head">
        <div class="gh-icon"><i class="fa-solid fa-users"></i></div>
        <h5>Who Does What — Role Guide</h5>
    </div>
    <div class="guide-body">
        <div class="row g-3">
            <?php
            $roles = [
                ['🔑','Admin','Full access to everything — staff management, reports, settings, audit log'],
                ['📊','Manager','Orders, billing, inventory, reports, reservations, attendance. Cannot manage staff accounts'],
                ['🍽️','Waiter','Takes dine-in/takeaway orders, views kitchen status, manages their own tables. Sees their personal dashboard after login'],
                ['🛵','Delivery','Only sees orders assigned to them. Can update status: Assigned → Picked Up → Delivered. Cannot see anything else'],
                ['🍳','Cook','Only sees the Kitchen Display screen. Can mark orders as Preparing or Ready. Cannot access any other module'],
                ['💳','Cashier','Can generate bills and view orders. Cannot take new orders or see reports'],
            ];
            foreach ($roles as [$icon,$name,$desc]):
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="role-card">
                    <div style="font-size:22px;margin-bottom:8px;"><?= $icon ?></div>
                    <div style="font-weight:700;font-size:14px;margin-bottom:4px;"><?= $name ?></div>
                    <div style="font-size:13px;color:var(--text-muted);"><?= $desc ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Daily Wage / Salary -->
<div class="guide-section">
    <div class="guide-head" style="background:#2980b9;">
        <div class="gh-icon"><i class="fa-solid fa-sack-dollar"></i></div>
        <h5>How to Set Daily Wages &amp; Calculate Salary</h5>
    </div>
    <div class="guide-body">
        <div class="step">
            <div class="step-num">1</div>
            <div class="step-body">
                <strong>Go to Staff Management</strong>
                <p>Navigate to <span class="path">Admin → Staff Management</span>. You'll see a list of all staff with a <strong>Daily Wage (₹)</strong> column.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">2</div>
            <div class="step-body">
                <strong>Set/Edit Daily Wage for Each Staff</strong>
                <p>In the Daily Wage column, type the amount (e.g. <code>500</code> for ₹500/day) and click the ✓ button next to it. The wage is saved immediately. When adding a new staff member, fill in the "Daily Wage" field in the form.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">3</div>
            <div class="step-body">
                <strong>Mark Daily Attendance</strong>
                <p>Go to <span class="path">Admin → Attendance &amp; Salary</span>. Under the <strong>Daily Marking</strong> tab, select the date and mark each staff as Present / Absent / Half Day / Leave. Click "Save Attendance".</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">4</div>
            <div class="step-body">
                <strong>View Monthly Salary</strong>
                <p>Click the <strong>Salary Calculator</strong> tab. Select the month. It automatically calculates: <code>(Present days × Daily Wage) + (Half days × Daily Wage × 0.5)</code>. You can also temporarily override the rate on screen without saving.</p>
            </div>
        </div>
        <div class="tip-box">
            <strong>💡 Tip:</strong> If a staff member's salary changes mid-month, update their daily wage in Staff Management. The calculation uses the current saved wage.
        </div>
    </div>
</div>

<!-- Taking an Order -->
<div class="guide-section">
    <div class="guide-head" style="background:#27ae60;">
        <div class="gh-icon"><i class="fa-solid fa-cart-plus"></i></div>
        <h5>How to Take an Order (with Loyalty &amp; Coupons)</h5>
    </div>
    <div class="guide-body">
        <div class="step">
            <div class="step-num">1</div>
            <div class="step-body">
                <strong>Click "New Order"</strong>
                <p>Select order type: <strong>Dine-In</strong> (choose table), <strong>Delivery</strong> (needs address), or <strong>Takeaway</strong>.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">2</div>
            <div class="step-body">
                <strong>Search Customer (optional but recommended)</strong>
                <p>Type the customer's phone number in the Phone field. Matching customers appear as a dropdown — click to auto-fill. Their loyalty points will show automatically. For delivery/takeaway, phone is required.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">3</div>
            <div class="step-body">
                <strong>Apply Loyalty Points or Coupon</strong>
                <p>If the customer has loyalty points, enter how many to redeem. Or enter a coupon code and click Apply. The total updates live.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">4</div>
            <div class="step-body">
                <strong>Select Items &amp; Place Order</strong>
                <p>Click items from the menu grid to add them. Use +/- to adjust quantities. Click "Place Order" — you'll land on the order view page.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">5</div>
            <div class="step-body">
                <strong>Add More Items Anytime</strong>
                <p>On the Order View page, click <strong>"Add More Items"</strong>. This works for all order types — dine-in, delivery, and takeaway.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">6</div>
            <div class="step-body">
                <strong>Generate Bill</strong>
                <p>When ready, click "Generate Bill" on the order view page. Apply final discounts, select payment method (Cash / Card / UPI), enter amount received, and confirm payment.</p>
            </div>
        </div>
    </div>
</div>

<!-- Reservations -->
<div class="guide-section">
    <div class="guide-head" style="background:#8e44ad;">
        <div class="gh-icon"><i class="fa-solid fa-calendar-check"></i></div>
        <h5>How Reservations Work</h5>
    </div>
    <div class="guide-body">
        <div class="step">
            <div class="step-num">1</div>
            <div class="step-body">
                <strong>Create a Reservation</strong>
                <p>Go to <span class="path">Operations → Reservations</span> and click "+ New". Fill in guest name, phone, party size, table, date, time and duration.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">2</div>
            <div class="step-body">
                <strong>Conflict Prevention (automatic)</strong>
                <p>If you try to book the same table at an overlapping time on the same date, the system blocks it and shows an error like: <em>"Table already reserved from 11:00 AM to 12:30 PM for Ayna."</em> Choose a different table or time.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">3</div>
            <div class="step-body">
                <strong>When Guest Arrives</strong>
                <p>Click <strong>"Seat"</strong> on the reservation — this marks the table as occupied. Then take a new order linked to that table as usual.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">4</div>
            <div class="step-body">
                <strong>No-Show</strong>
                <p>If the guest doesn't arrive, click <strong>"No Show"</strong> to free up the table slot.</p>
            </div>
        </div>
    </div>
</div>

<!-- Delivery Flow -->
<div class="guide-section">
    <div class="guide-head" style="background:#e67e22;">
        <div class="gh-icon"><i class="fa-solid fa-motorcycle"></i></div>
        <h5>Delivery Flow</h5>
    </div>
    <div class="guide-body">
        <div class="step">
            <div class="step-num">1</div>
            <div class="step-body">
                <strong>Create Delivery Order</strong>
                <p>Manager/Admin creates a new order with type "Delivery". Customer phone + address is required.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">2</div>
            <div class="step-body">
                <strong>Assign Rider</strong>
                <p>Go to <span class="path">Operations → Delivery</span>. In the Rider column, select a delivery boy from the dropdown — they get assigned instantly.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">3</div>
            <div class="step-body">
                <strong>Delivery Boy Logs In</strong>
                <p>The delivery boy logs in with their own account (role: delivery). They only see <strong>their assigned orders</strong> — no other data. They see the customer name, phone (clickable to call), address (with Google Maps link), and order total.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">4</div>
            <div class="step-body">
                <strong>Update Status</strong>
                <p>Delivery boy clicks <strong>"I've Picked Up"</strong> when they collect the order, then <strong>"Mark Delivered"</strong> when done. Failed deliveries can be marked too.</p>
            </div>
        </div>
    </div>
</div>

<!-- Kitchen / Cook -->
<div class="guide-section">
    <div class="guide-head" style="background:#c0392b;">
        <div class="gh-icon"><i class="fa-solid fa-fire-burner"></i></div>
        <h5>Kitchen Display &amp; Cook Dashboard</h5>
    </div>
    <div class="guide-body">
        <div class="step">
            <div class="step-num">1</div>
            <div class="step-body">
                <strong>Create a "Cook" Account</strong>
                <p>Go to Staff Management, add a new staff with role <strong>Cook</strong>. Set a username and password for the kitchen screen.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">2</div>
            <div class="step-body">
                <strong>Cook Logs In</strong>
                <p>When the cook logs in, they land directly on the Kitchen Display. They see only new/preparing/ready orders. No billing, no reports, no customer data.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">3</div>
            <div class="step-body">
                <strong>Cook Updates Status</strong>
                <p>Click <strong>"Start Cooking"</strong> when an order is being prepared. Click <strong>"Mark Ready ✓"</strong> when food is ready to serve. The waiter sees this update immediately.</p>
            </div>
        </div>
        <div class="tip-box">
            <strong>💡 How to use on a kitchen screen:</strong> Set up a tablet or monitor in the kitchen. Log in as the cook account. The screen auto-refreshes every 20 seconds — no manual action needed.
        </div>
    </div>
</div>

<!-- Notifications -->
<div class="guide-section">
    <div class="guide-head" style="background:#16a085;">
        <div class="gh-icon"><i class="fa-solid fa-bell"></i></div>
        <h5>Topbar Notifications</h5>
    </div>
    <div class="guide-body">
        <p style="font-size:14px;">The 🔔 bell icon in the top-right corner shows live alerts. It shows a red badge when there are active alerts. Click it to see:</p>
        <div class="step">
            <div class="step-num">🟠</div>
            <div class="step-body">
                <strong>Slow Orders</strong>
                <p>Any order that's been in "confirmed" status for more than 15 minutes — meaning it may not have reached the kitchen yet.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">🔴</div>
            <div class="step-body">
                <strong>Low Stock Alerts</strong>
                <p>Inventory items where current quantity has dropped below the minimum level set in Stock &amp; Expenses.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">🔵</div>
            <div class="step-body">
                <strong>Upcoming Reservations</strong>
                <p>Guests with reservations in the next 60 minutes — so you can prepare the table.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
