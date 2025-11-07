<?php
// /RADS-TOOLING/customer/checkout.php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/guard.php';
guard_require_customer();

$pid  = isset($_GET['pid']) ? trim((string)$_GET['pid']) : '';
$mode = isset($_GET['mode']) ? trim((string)$_GET['mode']) : '';

if ($mode === 'delivery') {
  header('Location: /RADS-TOOLING/customer/checkout_delivery.php?pid=' . urlencode($pid));
  exit;
}
if ($mode === 'pickup') {
  header('Location: /RADS-TOOLING/customer/checkout_pickup.php?pid=' . urlencode($pid));
  exit;
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Checkout</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="/RADS-TOOLING/assets/CSS/checkout.css">
  <link rel="stylesheet" href="/RADS-TOOLING/assets/CSS/responsive.css">
</head>
<body>
  <div class="cz-shell">
    <div class="cz-header">
      <h2>Checkout</h2>
    </div>

    <div class="card">
      <h3 class="mb8">How do you want to get your order?</h3>
      <div class="row gap8">
        <a class="rt-btn rt-btn-dark"
           href="/RADS-TOOLING/customer/checkout_delivery.php?pid=<?php echo urlencode($pid) ?>">Delivery</a>
        <a class="rt-btn"
           href="/RADS-TOOLING/customer/checkout_pickup.php?pid=<?php echo urlencode($pid) ?>">Pick-up</a>
      </div>
    </div>
  </div>
  <script src="/RADS-TOOLING/assets/JS/checkout.js" defer></script>
</body>
</html>
