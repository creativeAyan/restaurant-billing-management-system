<?php
$pageTitle = 'Settings';
require_once 'includes/header.php';
requireRole(['admin']);
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = ['restaurant_name','restaurant_address','restaurant_phone','restaurant_email','gst_number','currency_symbol','service_charge_percent','delivery_charge','tax_percent','receipt_footer'];
    foreach ($keys as $key) {
        $val = sanitize($_POST[$key] ?? '');
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$key,$val,$val]);
    }
    flashMessage('success', 'Settings saved!');
    redirect(BASE_URL . 'settings.php');
}

$settings = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$s = fn($k) => htmlspecialchars($settings[$k] ?? '');
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h6><i class="fa-solid fa-gear me-2 text-primary"></i>System Settings</h6></div>
            <div class="card-body">
                <form method="POST">
                    <h6 class="text-primary mb-3">Restaurant Information</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6"><label class="form-label">Restaurant Name</label><input type="text" name="restaurant_name" class="form-control" value="<?= $s('restaurant_name') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="restaurant_phone" class="form-control" value="<?= $s('restaurant_phone') ?>"></div>
                        <div class="col-12"><label class="form-label">Address</label><textarea name="restaurant_address" class="form-control" rows="2"><?= $s('restaurant_address') ?></textarea></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="restaurant_email" class="form-control" value="<?= $s('restaurant_email') ?>"></div>
                        <div class="col-md-6"><label class="form-label">GST Number</label><input type="text" name="gst_number" class="form-control" value="<?= $s('gst_number') ?>"></div>
                    </div>

                    <h6 class="text-primary mb-3">Billing Settings</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4"><label class="form-label">Currency Symbol</label><input type="text" name="currency_symbol" class="form-control" value="<?= $s('currency_symbol') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Tax % (GST/VAT)</label><input type="number" name="tax_percent" class="form-control" value="<?= $s('tax_percent') ?>" step="0.01"></div>
                        <div class="col-md-4"><label class="form-label">Service Charge %</label><input type="number" name="service_charge_percent" class="form-control" value="<?= $s('service_charge_percent') ?>" step="0.01"></div>
                        <div class="col-md-4"><label class="form-label">Delivery Charge (₹)</label><input type="number" name="delivery_charge" class="form-control" value="<?= $s('delivery_charge') ?>" step="0.01"></div>
                        <div class="col-12"><label class="form-label">Receipt Footer Message</label><input type="text" name="receipt_footer" class="form-control" value="<?= $s('receipt_footer') ?>"></div>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
