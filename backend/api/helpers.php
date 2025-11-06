<?php
declare(strict_types=1);

function db(): PDO {
  static $pdo=null;
  if($pdo) return $pdo;
  // TODO: palitan path kung may existing ka nang db.php
  $dsn  = 'mysql:host=127.0.0.1;dbname=rads;charset=utf8mb4';
  $user = 'root'; $pass = '';
  $pdo = new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  return $pdo;
}

function json_out($ok, $data=[]){
  header('Content-Type: application/json'); echo json_encode(['success'=>$ok] + $data); exit;
}

function compute_totals(float $price, int $qty): array {
  $sub = $price*$qty; $vat=round($sub*0.12,2); $total=$sub+$vat;
  return compact('sub','vat','total');
}
