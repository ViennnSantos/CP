<?php
declare(strict_types=1);
require __DIR__ . '/helpers.php';

$pid   = (int)($_POST['pid'] ?? 0);
$name  = $_POST['name'] ?? 'Item';
$price = (float)($_POST['price'] ?? 0);
$qty   = (int)($_POST['qty'] ?? 1);
$vat   = (float)($_POST['vat'] ?? 0);
$full  = $_POST['full_name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$png64 = $_POST['signature_png'] ?? '';

if(!preg_match('#^data:image/png;base64,#',$png64)) json_out(false,['message'=>'No signature']);

$png = base64_decode(preg_replace('#^data:image/png;base64,#','',$png64));
$dir = __DIR__ . '/../../uploads/signatures';
if(!is_dir($dir)) mkdir($dir,0775,true);
$fname = 'sig_'.time().'_'.mt_rand(1000,9999).'.png';
file_put_contents("$dir/$fname",$png);

$pdo = db();
$pdo->beginTransaction();
try{
  $totals = compute_totals($price,$qty);
  $pdo->prepare("INSERT INTO orders (order_no, type, product_id, product_name, price, qty, subtotal, vat, total, status, created_at) VALUES (UUID(),'pickup',?,?,?,?,?,?,?,'pending_payment',NOW())")
      ->execute([$pid,$name,$price,$qty,$totals['sub'],$totals['vat'],$totals['total']]);
  $orderId = $pdo->lastInsertId();

  $pdo->prepare("INSERT INTO order_pickup (order_id, full_name,email,phone,signature_path) VALUES (?,?,?,?,?)")
      ->execute([$orderId,$full,$email,$phone,"/uploads/signatures/$fname"]);

  $pdo->commit();
  json_out(true,['order_id'=>$orderId]);
}catch(Throwable $e){
  $pdo->rollBack();
  json_out(false,['message'=>$e->getMessage()]);
}
