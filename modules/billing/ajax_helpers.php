<?php
// ajax_helpers.php — called by billing page JS
require_once '../../includes/header.php';
header('Content-Type: application/json');
$pdo = getDB();
$action = $_GET['action'] ?? '';

if ($action === 'validate_coupon') {
    $code   = strtoupper(sanitize($_GET['code'] ?? ''));
    $amount = (float)($_GET['amount'] ?? 0);
    $stmt   = $pdo->prepare("SELECT * FROM coupons WHERE code=? AND status='active' AND valid_from<=CURDATE() AND valid_to>=CURDATE()");
    $stmt->execute([$code]);
    $c = $stmt->fetch();
    if (!$c) { echo json_encode(['ok'=>false,'msg'=>'Invalid or expired coupon.']); exit; }
    if ($c['max_uses'] > 0 && $c['used_count'] >= $c['max_uses']) { echo json_encode(['ok'=>false,'msg'=>'Coupon usage limit reached.']); exit; }
    if ($amount < $c['min_order_amount']) { echo json_encode(['ok'=>false,'msg'=>'Min order ₹'.number_format($c['min_order_amount'],0).' required.']); exit; }
    $disc = $c['discount_type']==='percent' ? $amount*$c['discount_value']/100 : $c['discount_value'];
    echo json_encode(['ok'=>true,'type'=>$c['discount_type'],'value'=>$c['discount_value'],'discount'=>round($disc,2),'msg'=>'Coupon applied: '.$c['description'],'id'=>$c['id']]);
    exit;
}

if ($action === 'validate_loyalty') {
    $phone  = sanitize($_GET['phone'] ?? '');
    $points = (int)($_GET['redeem'] ?? 0);
    $stmt   = $pdo->prepare("SELECT * FROM customers WHERE phone=?");
    $stmt->execute([$phone]);
    $c = $stmt->fetch();
    if (!$c) { echo json_encode(['ok'=>false,'msg'=>'Customer not found.']); exit; }
    if ($points > $c['loyalty_points']) { echo json_encode(['ok'=>false,'msg'=>'Only '.$c['loyalty_points'].' points available.']); exit; }
    $rupee = $points * 0.25;
    echo json_encode(['ok'=>true,'customer_id'=>$c['id'],'name'=>$c['name'],'points'=>$c['loyalty_points'],'redeem_points'=>$points,'redeem_rupee'=>$rupee,'msg'=>'Redeeming '.$points.' pts = ₹'.number_format($rupee,2)]);
    exit;
}

echo json_encode(['ok'=>false,'msg'=>'Unknown action']);
