<?php

declare(strict_types=1);
$orderId = $_GET['order'] ?? '';
if (!$orderId) {
  http_response_code(400);
  exit('No order');
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <title>Payment Status</title>
  <link rel="stylesheet" href="/assets/css/checkout.css">
  <link rel="stylesheet" href="/assets/CSS/responsive.css">
</head>

<body>
  <div style="max-width:720px;margin:24px auto;padding:0 16px" class="rt-card">
    <h3>Verification status</h3>
    <p class="rt-muted">Order: <strong><?= htmlspecialchars($orderId) ?></strong></p>
    <p id="state" class="rt-tag">Checking…</p>
    <div id="extra" style="margin-top:12px"></div>
  </div>
  <script>
    const state = document.getElementById('state');
    const extra = document.getElementById('extra');
    async function tick() {
      const r = await fetch('/backend/api/payment_status.php?order=<?= urlencode($orderId) ?>');
      const j = await r.json();
      if (!j.success) {
        state.textContent = 'Error';
        state.className = 'rt-tag bad';
        return;
      }
      state.textContent = j.status_text;
      state.className = 'rt-tag ' + (j.status === 'approved' ? 'ok' : (j.status === 'rejected' ? 'bad' : ''));
      extra.innerHTML = j.status === 'approved' ? `<a class="rt-btn rt-btn-primary" href="/customer/download_receipt.php?order=<?= urlencode($orderId) ?>">Download receipt</a>` : '';
      if (j.status === 'approved' || j.status === 'rejected') return; // stop polling
      setTimeout(tick, 3000);
    }
    tick();
  </script>
</body>

</html>