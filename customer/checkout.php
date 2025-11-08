<?php
// /customer/checkout.php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/guard.php';
guard_require_customer();

$pid  = isset($_GET['pid']) ? trim((string)$_GET['pid']) : '';
$mode = isset($_GET['mode']) ? trim((string)$_GET['mode']) : '';
$from = isset($_GET['from']) ? trim((string)$_GET['from']) : '';

// Handle cart-based checkout
if ($from === 'cart') {
  if ($mode === 'delivery') {
    header('Location: /customer/checkout_delivery.php?from=cart');
    exit;
  }
  if ($mode === 'pickup') {
    header('Location: /customer/checkout_pickup.php?from=cart');
    exit;
  }
}

// Handle single product checkout
if ($mode === 'delivery') {
  header('Location: /customer/checkout_delivery.php?pid=' . urlencode($pid));
  exit;
}
if ($mode === 'pickup') {
  header('Location: /customer/checkout_pickup.php?pid=' . urlencode($pid));
  exit;
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Checkout</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="/assets/CSS/checkout.css">
  <link rel="stylesheet" href="/assets/CSS/responsive.css">
</head>
<body>
  <div class="cz-shell">
    <div class="cz-header">
      <h2>Checkout</h2>
    </div>

    <div class="card">
      <h3 class="mb8">How do you want to get your order?</h3>
      <div class="row gap8">
        <?php if ($from === 'cart'): ?>
          <a class="rt-btn rt-btn-dark" href="/customer/checkout_delivery.php?from=cart">Delivery</a>
          <a class="rt-btn" href="/customer/checkout_pickup.php?from=cart">Pick-up</a>
        <?php else: ?>
          <a class="rt-btn rt-btn-dark" href="/customer/checkout_delivery.php?pid=<?php echo urlencode($pid) ?>">Delivery</a>
          <a class="rt-btn" href="/customer/checkout_pickup.php?pid=<?php echo urlencode($pid) ?>">Pick-up</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <script src="/assets/JS/checkout.js" defer></script>
</body>
</html>
