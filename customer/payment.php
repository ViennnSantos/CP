<?php

declare(strict_types=1);
session_start();
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
  <title>Payment</title>
  <link rel="stylesheet" href="/assets/css/checkout.css">
  <link rel="stylesheet" href="/RADS-TOOLING/assets/CSS/responsive.css">
</head>

<body>
  <div style="max-width:900px;margin:24px auto;padding:0 16px" class="rt-grid">
    <div class="rt-card">
      <h3>Payment</h3>
      <div class="rt-field">
        <label>Choose method</label>
        <select id="payMethod" class="rt-input">
          <option value="cash">Cash (COD/COP)</option>
          <option value="gcash">Online — GCash</option>
          <option value="instapay">Online — InstaPay</option>
        </select>
      </div>

      <div id="qrWrap" class="rt-card" style="display:none">
        <div class="rt-row"><strong id="qrTitle">GCash</strong><span class="rt-tag">Scan to pay</span></div>
        <img id="qrImg" alt="QR" style="display:block;width:220px;height:220px;object-fit:contain;margin:12px 0">
        <form id="verifyForm">
          <input type="hidden" name="order_id" value="<?= htmlspecialchars($orderId) ?>">
          <input type="hidden" name="channel" id="channel" value="gcash">
          <div class="rt-field"><label>Reference No.</label><input class="rt-input" name="ref_no" required></div>
          <div class="rt-field"><label>Sender Name</label><input class="rt-input" name="sender_name" required></div>
          <div class="rt-field"><label>Amount Paid (₱)</label><input class="rt-input" name="amount" inputmode="decimal" required></div>
          <div class="rt-row" style="margin-top:8px">
            <button class="rt-btn rt-btn-dark" type="submit">Submit for verification</button>
          </div>
        </form>
      </div>

      <div id="cashWrap" class="rt-card">
        <p class="rt-muted">Cash will be paid upon delivery/pick-up.</p>
        <a class="rt-btn rt-btn-primary" href="/customer/payment_status.php?order=<?= urlencode($orderId) ?>">Continue</a>
      </div>
    </div>
  </div>

  <script>
    const pay = document.getElementById('payMethod');
    const qr = document.getElementById('qrWrap');
    const cash = document.getElementById('cashWrap');
    const chEl = document.getElementById('channel');
    const title = document.getElementById('qrTitle');
    const img = document.getElementById('qrImg');

    function setQR(which) {
      const map = {
        gcash: {
          title: 'GCash',
          img: '/assets/img/qr/gcash.png'
        },
        instapay: {
          title: 'InstaPay',
          img: '/assets/img/qr/instapay.png'
        }
      };
      title.textContent = map[which].title;
      img.src = map[which].img;
      chEl.value = which;
    }

    pay.addEventListener('change', () => {
      if (pay.value === 'cash') {
        cash.style.display = 'block';
        qr.style.display = 'none';
      } else {
        setQR(pay.value);
        cash.style.display = 'none';
        qr.style.display = 'block';
      }
    });

    document.getElementById('verifyForm')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const r = await fetch('/backend/api/payment_submit.php', {
        method: 'POST',
        body: fd
      });
      const j = await r.json();
      if (!j.success) {
        alert(j.message || 'Submit failed');
        return;
      }
      alert('Payment submitted. Under review pa, boss.');
      window.location.href = `/customer/payment_status.php?order=${encodeURIComponent(fd.get('order_id'))}`;
    });
  </script>
</body>

</html>