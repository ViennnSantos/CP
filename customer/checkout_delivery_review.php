<?php
// /customer/checkout_delivery_review.php
// 🔥 ULTIMATE FIXED VERSION - All issues resolved!

declare(strict_types=1);
session_start();

require_once __DIR__ . '/../backend/config/app.php';

$mode = $_POST['mode'] ?? 'pickup';
$pid  = (int)($_POST['pid'] ?? 0);

$first    = $_POST['first_name'] ?? '';
$last     = $_POST['last_name'] ?? '';
$email    = $_POST['email'] ?? '';
$phone    = $_POST['phone'] ?? '';

$province = $_POST['province'] ?? '';
$city     = $_POST['city'] ?? '';
$barangay = $_POST['barangay'] ?? '';
$postal   = $_POST['postal'] ?? '';
$street   = $_POST['street'] ?? '';

$product = null;
if ($pid > 0) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'released'");
    $stmt->execute([$pid]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$product) {
    header('Location: /customer/products.php');
    exit;
}

$qty       = 1;
$basePrice = (float)($product['base_price'] ?? $product['price'] ?? 0);
$subtotal  = $basePrice * $qty;
$vat       = $subtotal * 0.12;
$shipping  = 0;

if ($mode === 'delivery') {
    $shipping = 500;
}

$total = $subtotal + $vat + $shipping;

$user = $_SESSION['user'] ?? null;
$customerName = htmlspecialchars($user['name'] ?? $user['username'] ?? 'Customer');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Review & Payment - Rads Tooling</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <!-- ✅ CRITICAL: Fonts MUST load first -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
  
  <!-- ✅ FIXED: Proper Material Symbols loading with FILL enabled -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
  
  <link rel="stylesheet" href="../assets/CSS/Homepage.css">
  <link rel="stylesheet" href="../assets/CSS/chat-widget.css">
  <link rel="stylesheet" href="../assets/CSS/about.css">
  <link rel="stylesheet" href="../assets/CSS/checkout.css">
  <link rel="stylesheet" href="../assets/CSS/checkout_modal.css">
  <link rel="stylesheet" href="/assets/CSS/responsive.css">
  
  <style>
    /* ✅ Force Poppins everywhere */
    * {
      font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
    }

    /* ✅ CRITICAL: Material Symbols config */
    .material-symbols-rounded {
      font-family: 'Material Symbols Rounded' !important;
      font-weight: normal;
      font-style: normal;
      font-size: 24px;
      line-height: 1;
      letter-spacing: normal;
      text-transform: none;
      display: inline-block;
      white-space: nowrap;
      word-wrap: normal;
      direction: ltr;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      text-rendering: optimizeLegibility;
      font-feature-settings: 'liga';
      vertical-align: middle;
    }

    .review-wrap {
      max-width: 1400px !important;
      margin: 32px auto;
      padding: 0 24px;
    }

    .checkout-title {
      font-size: 32px !important;
      margin-bottom: 24px;
      font-weight: 700;
      color: #111827;
    }

    .checkout-grid {
      display: grid;
      grid-template-columns: 1.8fr 1fr;
      gap: 32px;
      align-items: start;
    }

    @media (max-width: 1024px) {
      .checkout-grid {
        grid-template-columns: 1fr;
      }
    }

    .checkout-card {
      background: #fff;
      border: 2px solid #e5e7eb;
      border-radius: 16px;
      padding: 32px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }

    .card-title {
      font-size: 20px !important;
      font-weight: 700;
      margin-bottom: 20px;
      color: #111827;
      padding-bottom: 12px;
      border-bottom: 2px solid #f3f4f6;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .card-title .material-symbols-rounded {
      font-size: 26px;
      color: #2f5b88;
    }

    .review-list {
      margin: 0;
      padding: 0;
      list-style: none;
    }

    .review-list li {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid #f3f4f6;
      padding: 14px 0;
      font-size: 15px;
    }

    .review-list li:last-child {
      border: 0;
    }

    .review-list li span:first-child {
      color: #6b7280;
      font-weight: 500;
    }

    .review-list li strong {
      color: #111827;
      font-weight: 600;
    }

    .sum-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 14px 0;
      font-size: 16px;
      border-bottom: 1px solid #f3f4f6;
    }

    .sum-row span:first-child {
      color: #6b7280;
      font-weight: 500;
    }

    .sum-row span:last-child,
    .sum-row strong {
      color: #111827;
      font-weight: 600;
    }

    .sum-row.total {
      font-size: 22px !important;
      font-weight: 700 !important;
      border-top: 3px solid #2f5b88 !important;
      border-bottom: none;
      margin-top: 12px;
      padding-top: 20px;
      color: #2f5b88 !important;
    }

    .sum-row.total strong {
      color: #2f5b88 !important;
    }

    .delivery-address-banner {
      background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
      border: 2px solid #bae6fd;
      border-radius: 12px;
      padding: 20px;
      margin-top: 24px;
    }

    .delivery-address-banner h4 {
      font-size: 16px;
      font-weight: 700;
      color: #0369a1;
      margin: 0 0 12px 0;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .delivery-address-banner h4 .material-symbols-rounded {
      font-size: 22px;
    }

    .delivery-address-banner p {
      font-size: 15px;
      color: #075985;
      margin: 0;
      line-height: 1.7;
    }

    .pickup-info-banner {
      background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
      border: 2px solid #fbbf24;
      border-radius: 12px;
      padding: 20px;
      margin-top: 24px;
      display: flex;
      align-items: start;
      gap: 12px;
    }

    .pickup-info-banner .material-symbols-rounded {
      font-size: 28px;
      color: #b45309;
    }

    .pickup-info-banner .content p {
      margin: 0;
      color: #92400e;
      font-size: 15px;
      line-height: 1.6;
    }

    .pickup-info-banner .content p:first-child {
      font-weight: 700;
      margin-bottom: 4px;
    }

    .rt-actions {
      margin-top: 24px;
    }

    .rt-btn {
      font-size: 17px !important;
      padding: 16px 32px !important;
      border-radius: 12px !important;
      font-weight: 600 !important;
      transition: all 0.2s ease;
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .rt-btn.main {
      background: linear-gradient(135deg, #2f5b88 0%, #1e3a5f 100%) !important;
      color: white !important;
      width: 100%;
    }

    .rt-btn.main:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(47, 91, 136, 0.4);
    }

    .rt-btn .material-symbols-rounded {
      font-size: 20px;
    }

    .rt-btn.ghost {
      background: white !important;
      border: 2px solid #e5e7eb !important;
      color: #4b5563 !important;
      flex: 1;
    }

    .rt-btn.ghost:hover {
      border-color: #2f5b88 !important;
      color: #2f5b88 !important;
    }

    /* ✅ IMPROVED: Better modal styling */
    .rt-modal {
      font-family: 'Poppins', sans-serif !important;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.7);
      z-index: 9999;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .rt-card {
      background: white;
      border-radius: 16px !important;
      max-width: 500px;
      width: 100%;
      max-height: 90vh;
      overflow-y: auto;
      padding: 28px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }

    .rt-card h3 {
      font-size: 24px !important;
      font-weight: 700 !important;
      margin: 0 0 20px 0;
      color: #111827;
    }

    /* ✅ IMPROVED: Payment method list */
    .rt-list {
      margin: 0;
      padding: 0;
      list-style: none;
    }

    .rt-list__item {
      position: relative;
      padding: 18px 24px;
      background: #f9fafb;
      border: 2px solid #e5e7eb;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 12px;
      font-size: 18px;
      font-weight: 600;
      color: #374151;
    }

    .rt-list__item:hover {
      border-color: #2f5b88;
      background: #f0f7ff;
      transform: translateX(4px);
    }

    .rt-list__item.is-active {
      background: #dbeafe !important;
      border-color: #2f5b88 !important;
      box-shadow: 0 0 0 4px rgba(47, 91, 136, 0.1);
      color: #2f5b88;
    }

    .rt-list__item.is-active::before {
      content: '✓';
      position: absolute;
      left: 24px;
      font-weight: 700;
      font-size: 20px;
      color: #2f5b88;
    }

    .rt-list__item.is-active span:first-child {
      margin-left: 32px;
    }

    .rt-arrow {
      font-size: 24px;
      color: #9ca3af;
    }

    .rt-list__item.is-active .rt-arrow {
      color: #2f5b88;
    }

    /* ✅ IMPROVED: Deposit chips */
    .rt-chips {
      display: flex;
      gap: 12px;
      margin: 20px 0;
    }

    .rt-chip {
      background: #f9fafb;
      border: 2px solid #e5e7eb;
      border-radius: 12px;
      padding: 24px 20px;
      cursor: pointer;
      transition: all 0.2s ease;
      text-align: center;
      flex: 1;
    }

    .rt-chip:hover {
      border-color: #2f5b88;
      background: #f0f7ff;
      transform: translateY(-2px);
    }

    .rt-chip.is-active {
      background: #dbeafe !important;
      border-color: #2f5b88 !important;
      box-shadow: 0 0 0 4px rgba(47, 91, 136, 0.1);
      transform: scale(1.05);
    }

    .rt-chip span {
      display: block;
      font-size: 22px;
      font-weight: 700;
      color: #111827;
      margin-bottom: 8px;
    }

    .rt-chip small {
      display: block;
      font-size: 16px;
      color: #6b7280;
      font-weight: 600;
    }

    .rt-chip.is-active span,
    .rt-chip.is-active small {
      color: #2f5b88;
    }

    .rt-sub {
      font-size: 16px;
      color: #6b7280;
      margin-bottom: 16px;
    }

    .rt-sub b {
      color: #2f5b88;
      font-size: 20px;
    }

    .rt-form label {
      font-size: 15px !important;
      font-weight: 600 !important;
      margin-bottom: 8px;
      display: block;
      color: #374151;
    }

    .rt-form input {
      font-size: 15px !important;
      padding: 12px 16px !important;
      border-radius: 10px !important;
      border: 2px solid #e5e7eb !important;
      font-family: 'Poppins', sans-serif !important;
      width: 100%;
      margin-bottom: 16px;
      transition: all 0.2s ease;
    }

    .rt-form input:focus {
      border-color: #2f5b88 !important;
      outline: none;
      box-shadow: 0 0 0 4px rgba(47, 91, 136, 0.1);
    }

    @media (max-width: 640px) {
      .review-wrap {
        padding: 0 16px;
      }

      .checkout-title {
        font-size: 24px !important;
      }

      .checkout-card {
        padding: 24px;
      }

      .card-title {
        font-size: 18px !important;
      }

      .rt-chips {
        flex-direction: column;
      }
    }
  </style>
</head>
<body>
<div class="page-wrapper">
  <header class="navbar">
    <div class="navbar-container">
      <div class="navbar-brand">
        <a class="logo-link" href="/customer/homepage.php">
          <span class="logo-text">R</span>ADS <span class="logo-text">T</span>OOLING
        </a>
      </div>
      <div class="navbar-actions">
        <a class="cart-button" href="/cart.php">
          <span class="material-symbols-rounded">shopping_cart</span>
          <span id="cartCount" class="cart-badge">0</span>
        </a>
      </div>
    </div>
    <nav class="navbar-menu">
      <a href="/customer/homepage.php" class="nav-menu-item">Home</a>
      <a href="/customer/about.php" class="nav-menu-item">About</a>
      <a href="/customer/products.php" class="nav-menu-item">Products</a>
      <a href="/customer/testimonials.php" class="nav-menu-item">Testimonials</a>
    </nav>
  </header>

  <main class="checkout-main review-wrap">
    <h1 class="checkout-title">Review & Payment</h1>
    
    <div class="checkout-grid">
      <section class="checkout-card">
        <h3 class="card-title">
          <span class="material-symbols-rounded">inventory_2</span>
          Order Summary
        </h3>
        <ul class="review-list">
          <li><span>Product</span><strong><?= htmlspecialchars($product['name']) ?></strong></li>
          <li><span>Unit Price</span><strong>₱ <?= number_format($basePrice,2) ?></strong></li>
          <li><span>Quantity</span><strong><?= $qty ?></strong></li>
        </ul>

        <h3 class="card-title" style="margin-top: 32px;">
          <span class="material-symbols-rounded">person</span>
          Contact Information
        </h3>
        <ul class="review-list">
          <li><span>Name</span><strong><?= htmlspecialchars("$first $last") ?></strong></li>
          <?php if ($email): ?>
          <li><span>Email</span><strong><?= htmlspecialchars($email) ?></strong></li>
          <?php endif; ?>
          <li><span>Phone</span><strong><?= htmlspecialchars($phone) ?></strong></li>
        </ul>

        <?php if ($mode === 'delivery'): ?>
        <h3 class="card-title" style="margin-top: 32px;">
          <span class="material-symbols-rounded">local_shipping</span>
          Delivery Address
        </h3>
        <div class="delivery-address-banner">
          <h4>
            <span class="material-symbols-rounded">location_on</span>
            Shipping To:
          </h4>
          <p>
            <?php 
            $addressParts = array_filter([
              $street ? htmlspecialchars($street) : '',
              $barangay ? 'Brgy. ' . htmlspecialchars($barangay) : '',
              $city ? htmlspecialchars($city) : '',
              $province ? htmlspecialchars($province) : '',
              $postal ? htmlspecialchars($postal) : ''
            ]);
            echo implode(', ', $addressParts);
            ?>
          </p>
        </div>
        <?php else: ?>
        <div class="pickup-info-banner">
          <span class="material-symbols-rounded">store</span>
          <div class="content">
            <p>Pick-up Location:</p>
            <p>Green Breeze, Piela, Dasmariñas, Cavite<br>
            <strong>Hours:</strong> Mon-Sat, 8:00 AM - 5:00 PM</p>
          </div>
        </div>
        <?php endif; ?>

        <div class="rt-actions">
          <button type="button" class="rt-btn main" id="inlineBuyBtn">
            <span class="material-symbols-rounded">payment</span>
            Proceed to Payment
          </button>
        </div>
      </section>

      <aside class="checkout-card" style="position: sticky; top: 100px;">
        <h3 class="card-title">
          <span class="material-symbols-rounded">payments</span>
          Price Summary
        </h3>
        
        <div class="sum-row">
          <span>Subtotal</span>
          <strong>₱ <?= number_format($subtotal,2) ?></strong>
        </div>
        
        <div class="sum-row">
          <span>VAT (12%)</span>
          <span>₱ <?= number_format($vat,2) ?></span>
        </div>
        
        <?php if ($mode === 'delivery'): ?>
        <div class="sum-row">
          <span>Shipping Fee</span>
          <span>₱ <?= number_format($shipping,2) ?></span>
        </div>
        <?php endif; ?>
        
        <div class="sum-row total">
          <strong>Total Amount</strong>
          <strong>₱ <?= number_format($total,2) ?></strong>
        </div>

        <div style="margin-top: 20px; padding: 16px; background: #f9fafb; border-radius: 10px; font-size: 13px; color: #6b7280; line-height: 1.6;">
          <strong style="color: #374151; display: block; margin-bottom: 6px;">Payment Options:</strong>
          • GCash or BPI online transfer<br>
          • Pay 30%, 50%, or 100% upfront<br>
          • Secure payment verification
        </div>
      </aside>
    </div>
  </main>

  <footer class="footer">
    <div class="footer-bottom">
      <p>© 2025 RADS TOOLING INC. All rights reserved.</p>
    </div>
  </footer>
</div>

<!-- PAYMENT WIZARD MODALS -->
<div class="rt-modal" id="rtModal" hidden>
  <div class="rt-card rt-step" id="methodModal" hidden>
    <h3>Select Payment Method</h3>
    <p class="rt-sub">Choose your preferred payment option</p>
    <div class="rt-list">
      <button class="rt-list__item pay-chip" data-pay="gcash">
        <span>GCash</span>
        <span class="rt-arrow">→</span>
      </button>
      <button class="rt-list__item pay-chip" data-pay="bpi">
        <span>BPI</span>
        <span class="rt-arrow">→</span>
      </button>
    </div>
    <div class="rt-actions" style="display: flex; gap: 12px; margin-top: 24px;">
      <button class="rt-btn ghost" data-close>Close</button>
      <button class="rt-btn main" id="btnChooseDeposit" disabled style="flex: 2;">Next</button>
    </div>
  </div>

  <div class="rt-card rt-step" id="depositModal" hidden>
    <h3>Select Payment Amount</h3>
    <div class="rt-sub">Total Amount: <b>₱<?= number_format($total,2) ?></b></div>

    <div class="rt-chips">
      <button class="rt-chip pay-chip" data-dep="30">
        <span>30%</span>
        <small>₱<?= number_format($total * 0.30,2) ?></small>
      </button>
      <button class="rt-chip pay-chip" data-dep="50">
        <span>50%</span>
        <small>₱<?= number_format($total * 0.50,2) ?></small>
      </button>
      <button class="rt-chip pay-chip" data-dep="100">
        <span>100%</span>
        <small>₱<?= number_format($total,2) ?></small>
      </button>
    </div>

    <div class="rt-actions" style="display: flex; gap: 12px;">
      <button class="rt-btn ghost" data-back="#methodModal">Back</button>
      <button class="rt-btn main" id="btnPayNow" disabled style="flex: 2;">Proceed to Pay</button>
    </div>
  </div>

  <div class="rt-card rt-step" id="qrModal" hidden>
    <h3>Scan QR Code to Pay</h3>
    <div class="rt-qrwrap">
      <div id="qrBox" class="rt-qr">Loading QR Code...</div>
    </div>
    <div class="rt-sub" style="font-size: 18px; margin: 16px 0;">
      Amount to pay: <b id="amountDueLabel">₱0.00</b>
    </div>
    <div style="font-size: 14px; color: #6b7280;">Scan with your selected payment app</div>

    <div class="rt-actions" style="display: flex; gap: 12px; margin-top: 24px;">
      <button class="rt-btn ghost" data-back="#depositModal">Back</button>
      <button class="rt-btn main" id="btnIpaid" style="flex: 2;">I've Completed Payment</button>
    </div>
  </div>

  <div class="rt-card rt-step" id="verifyModal" hidden>
    <h3>Verify Your Payment</h3>
    <p class="rt-sub">Please provide your payment details for verification</p>
    <div class="rt-form">
      <label>Account Name
        <input type="text" id="vpName" placeholder="Enter your account name" required>
      </label>
      <label>Account Number <small style="color: #6b7280;">(digits only)</small>
        <input type="text" id="vpNum" placeholder="Enter your account number" required inputmode="numeric">
      </label>
      <label>Reference Number <small style="color: #6b7280;">(digits only)</small>
        <input type="text" id="vpRef" placeholder="Enter transaction reference number" required inputmode="numeric">
      </label>
      <label>Amount Paid
        <input type="number" id="vpAmt" step="0.01" placeholder="0.00" required>
      </label>
      <label>Payment Screenshot
        <input type="file" id="vpShot" accept="image/*" required>
      </label>
    </div>
    <div class="rt-actions" style="display: flex; gap: 12px;">
      <button class="rt-btn ghost" data-back="#qrModal">Back</button>
      <button class="rt-btn main" id="btnVerify" style="flex: 2;">Submit Verification</button>
    </div>
  </div>

  <div class="rt-card rt-step" id="finalNotice" hidden>
    <div style="text-align: center; padding: 20px 0;">
      <span class="material-symbols-rounded" style="font-size: 64px; color: #10b981;">check_circle</span>
      <h3 style="margin: 16px 0 8px 0;">Payment Submitted Successfully!</h3>
      <p class="rt-sub" style="font-size: 15px; color: #6b7280; line-height: 1.6;">
        Your payment is <b style="color: #2f5b88;">under verification</b>. 
        Please check <b>Profile → Orders</b> to see the approval status.
      </p>
    </div>
    <div class="rt-actions" style="display: flex; gap: 12px;">
      <button class="rt-btn ghost" data-close>Close</button>
      <button class="rt-btn main" id="btnGoOrders" style="flex: 2;">View My Orders</button>
    </div>
  </div>
</div>

<div id="qrZoomModal">
  <div class="zoom-container">
    <button class="zoom-close" onclick="closeQrZoom()">×</button>
    <div class="zoom-qr">
      <img id="zoomQrImage" src="" alt="QR Code">
    </div>
  </div>
</div>

<input type="hidden" id="paymentMethod">
<input type="hidden" id="depositRate">
<input type="hidden" id="termsAgreed" value="0">

<!-- Terms & Conditions Modal -->
<div id="termsModal" class="rt-modal" hidden>
  <div class="rt-modal__backdrop"></div>
  <div class="rt-card rt-step" style="max-width: 600px; display: block; max-height: 80vh; overflow-y: auto;">
    <h3 style="color: #2f5b88; margin-bottom: 20px; text-align: center;">
      Terms & Conditions
    </h3>
    <div style="color: #555; line-height: 1.6; margin-bottom: 24px; text-align: left;">
      <p style="margin-bottom: 16px;">By confirming your purchase, you agree to the following terms and conditions:</p>
      <ul style="padding-left: 20px; margin-bottom: 16px;">
        <li style="margin-bottom: 8px;"><strong>Final Purchase:</strong> All purchases are final — no refunds once the payment has been processed and verified.</li>
        <li style="margin-bottom: 8px;"><strong>Data Privacy:</strong> Your personal and payment information is securely stored and protected under our data privacy policy. We will not share your information with third parties without your consent.</li>
        <li style="margin-bottom: 8px;"><strong>Accuracy of Information:</strong> Please ensure all details provided (delivery address, contact information, payment details) are correct before submitting your payment. RADS Tooling is not responsible for errors caused by incorrect information.</li>
        <li style="margin-bottom: 8px;"><strong>Order Verification:</strong> Your payment will be verified by our admin team. Upon successful verification, your order will be processed and prepared for delivery or pickup.</li>
        <li style="margin-bottom: 8px;"><strong>Delivery & Pickup:</strong> Delivery timeframes and pickup schedules will be communicated after payment verification. Additional shipping fees may apply based on your location.</li>
      </ul>
    </div>
    <div style="margin-bottom: 20px; display: flex; align-items: center; padding: 12px; background: #f8f9fa; border-radius: 8px;">
      <input type="checkbox" id="termsCheckbox" style="margin-right: 10px; width: 20px; height: 20px; cursor: pointer;">
      <label for="termsCheckbox" style="cursor: pointer; font-size: 14px; color: #333;">
        I have read and agree to the Terms & Conditions
      </label>
    </div>
    <div class="rt-actions" style="justify-content: space-between;">
      <button type="button" class="rt-btn" id="termsCancel" style="min-width: 120px;">Cancel</button>
      <button type="button" class="rt-btn main" id="termsConfirm" style="min-width: 120px; opacity: 0.5; cursor: not-allowed; background: #9ca3af;" disabled>I Agree and Continue</button>
    </div>
  </div>
</div>

<script>
window.RT_ORDER = <?= json_encode([
  'pid'      => (int)($product['id'] ?? 0),
  'qty'      => (int)($qty ?? 1),
  'subtotal' => (float)($subtotal ?? 0),
  'vat'      => (float)($vat ?? 0),
  'total'    => (float)($total ?? 0),
  'mode'     => $mode ?? 'delivery',
  'info'     => (($mode ?? 'delivery') === 'delivery')
    ? [
        'delivery' => [
          'first_name' => $first ?? '',
          'last_name'  => $last  ?? '',
          'email'      => $email      ?? '',
          'phone'      => $phone      ?? '',
          'province'   => $province   ?? '',
          'city'       => $city       ?? '',
          'barangay'   => $barangay   ?? '',
          'postal'     => $postal     ?? '',
          'street'     => $street     ?? '',
        ]
      ]
    : [
        'pickup' => [
          'first_name' => $first ?? '',
          'last_name'  => $last  ?? '',
          'email'      => $email  ?? '',
          'phone'      => $phone      ?? '',
        ]
      ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
console.log('✅ RT_ORDER:', window.RT_ORDER);
// ✅ FIX: Sync T&C checkbox with hidden field

document.addEventListener('DOMContentLoaded', function() {
  const termsCheckbox = document.getElementById('termsCheckbox');
  const termsAgreedField = document.getElementById('termsAgreed');
  const termsConfirmBtn = document.getElementById('termsConfirm');

  if (termsCheckbox && termsAgreedField) {
    termsCheckbox.addEventListener('change', function() {

      // Update hidden field value when checkbox changes
      termsAgreedField.value = this.checked ? '1' : '0';
      console.log('✅ T&C checkbox changed: terms_agreed =', termsAgreedField.value);

      // Enable/disable confirm button
      if (termsConfirmBtn) {
        if (this.checked) {
          termsConfirmBtn.disabled = false;
          termsConfirmBtn.style.opacity = '1';
          termsConfirmBtn.style.cursor = 'pointer';
          termsConfirmBtn.style.background = '#2c5f8d';
        } else {
          termsConfirmBtn.disabled = true;
          termsConfirmBtn.style.opacity = '0.5';
          termsConfirmBtn.style.cursor = 'not-allowed';
          termsConfirmBtn.style.background = '#9ca3af';
        }
      }
    });
  }
});
</script>
<script src="/assets/JS/checkout.js" defer></script>
</body>
</html>