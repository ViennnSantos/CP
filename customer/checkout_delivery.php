<?php
// /RADS-TOOLING/customer/checkout_delivery.php
// 🔥 COMPLETE FIXED VERSION with NCR support

declare(strict_types=1);
session_start();

require_once __DIR__ . '/../backend/config/app.php';

$pid = (int)($_GET['pid'] ?? $_POST['pid'] ?? 0);

if ($pid <= 0) {
    header('Location: /RADS-TOOLING/customer/products.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'released'");
$stmt->execute([$pid]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: /RADS-TOOLING/customer/products.php');
    exit;
}

$user = $_SESSION['user'] ?? null;
$customerName = htmlspecialchars($user['name'] ?? $user['username'] ?? 'Customer');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Delivery Details - Rads Tooling</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
  
  <link rel="stylesheet" href="../assets/CSS/Homepage.css">
  <link rel="stylesheet" href="../assets/CSS/chat-widget.css">
  <link rel="stylesheet" href="../assets/CSS/about.css">
  <link rel="stylesheet" href="../assets/CSS/checkout.css">
  <link rel="stylesheet" href="../assets/CSS/responsive.css">
  <link rel="stylesheet" href="../assets/CSS/checkout_modal.css">
  
  <style>
    * {
      font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
    }

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

    .checkout-wrapper {
      max-width: 800px;
      margin: 40px auto;
      padding: 0 24px;
    }

    .checkout-header {
      text-align: center;
      margin-bottom: 32px;
    }

    .checkout-header h1 {
      font-size: 32px;
      font-weight: 700;
      color: #111827;
      margin: 0 0 8px 0;
    }

    .checkout-header p {
      font-size: 16px;
      color: #6b7280;
      margin: 0;
    }

    .checkout-card {
      background: white;
      border: 2px solid #e5e7eb;
      border-radius: 16px;
      padding: 32px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }

    .form-group {
      margin-bottom: 24px;
    }

    .form-group label {
      display: block;
      font-size: 15px;
      font-weight: 600;
      color: #374151;
      margin-bottom: 8px;
    }

    .form-group label .required {
      color: #ef4444;
      margin-left: 4px;
    }

    .form-group input,
    .form-group select {
      width: 100%;
      padding: 12px 16px;
      font-size: 15px;
      border: 2px solid #e5e7eb;
      border-radius: 10px;
      transition: all 0.2s ease;
      font-family: 'Poppins', sans-serif;
    }

    .form-group input:focus,
    .form-group select:focus {
      border-color: #2f5b88;
      outline: none;
      box-shadow: 0 0 0 4px rgba(47, 91, 136, 0.1);
    }

    .form-group input[type="text"]::placeholder {
      color: #9ca3af;
    }

    .form-group small {
      display: block;
      font-size: 13px;
      color: #6b7280;
      margin-top: 6px;
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }

    @media (max-width: 640px) {
      .form-row {
        grid-template-columns: 1fr;
      }
    }

    .phone-group {
      display: flex;
      gap: 8px;
    }

    .phone-group .country-code {
      flex: 0 0 80px;
    }

    .phone-group .phone-input {
      flex: 1;
    }

    .btn-group {
      display: flex;
      gap: 12px;
      margin-top: 32px;
    }

    .btn {
      flex: 1;
      padding: 16px 24px;
      font-size: 16px;
      font-weight: 600;
      border-radius: 12px;
      border: none;
      cursor: pointer;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .btn-primary {
      background: linear-gradient(135deg, #2f5b88 0%, #1e3a5f 100%);
      color: white;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(47, 91, 136, 0.4);
    }

    .btn-secondary {
      background: white;
      color: #4b5563;
      border: 2px solid #e5e7eb;
    }

    .btn-secondary:hover {
      border-color: #2f5b88;
      color: #2f5b88;
    }

    .notice-box {
      background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
      border: 2px solid #fbbf24;
      border-radius: 12px;
      padding: 16px;
      margin-bottom: 24px;
      display: flex;
      align-items: start;
      gap: 12px;
    }

    .notice-box .material-symbols-rounded {
      font-size: 24px;
      color: #b45309;
      flex-shrink: 0;
    }

    .notice-box p {
      margin: 0;
      font-size: 14px;
      color: #92400e;
      line-height: 1.6;
    }

    .rt-modal {
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
      border-radius: 16px;
      max-width: 450px;
      width: 100%;
      padding: 28px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }

    .rt-card h3 {
      font-size: 22px;
      font-weight: 700;
      margin: 0 0 12px 0;
      color: #111827;
    }

    .rt-card p {
      font-size: 15px;
      color: #6b7280;
      margin: 0 0 24px 0;
      line-height: 1.6;
    }

    .rt-actions {
      display: flex;
      justify-content: flex-end;
    }

    .rt-btn {
      padding: 12px 24px;
      font-size: 16px;
      font-weight: 600;
      border-radius: 10px;
      border: none;
      cursor: pointer;
      background: linear-gradient(135deg, #2f5b88 0%, #1e3a5f 100%);
      color: white;
      transition: all 0.2s ease;
    }

    .rt-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(47, 91, 136, 0.4);
    }
  </style>
</head>
<body>
<div class="page-wrapper">
  <header class="navbar">
    <div class="navbar-container">
      <div class="navbar-brand">
        <a class="logo-link" href="/RADS-TOOLING/customer/homepage.php">
          <span class="logo-text">R</span>ADS <span class="logo-text">T</span>OOLING
        </a>
      </div>
      <div class="navbar-actions">
        <a class="cart-button" href="/RADS-TOOLING/cart.php">
          <span class="material-symbols-rounded">shopping_cart</span>
          <span id="cartCount" class="cart-badge">0</span>
        </a>
      </div>
    </div>
    <nav class="navbar-menu">
      <a href="/RADS-TOOLING/customer/homepage.php" class="nav-menu-item">Home</a>
      <a href="/RADS-TOOLING/customer/about.php" class="nav-menu-item">About</a>
      <a href="/RADS-TOOLING/customer/products.php" class="nav-menu-item">Products</a>
      <a href="/RADS-TOOLING/customer/testimonials.php" class="nav-menu-item">Testimonials</a>
    </nav>
  </header>

  <main class="checkout-main checkout-wrapper">
    <div class="checkout-header">
      <h1>Delivery Details</h1>
      <p>Please provide your delivery information</p>
    </div>

    <div class="checkout-card">
      <div class="notice-box">
        <span class="material-symbols-rounded">info</span>
        <p><strong>Limited to NCR and Calabarzon.</strong> Delivery fee is ₱500. Orders are shipped within 3-5 business days.</p>
      </div>
      
 <!-- Saved Addresses Dropdown -->
      <div id="savedAddressesContainer" style="margin-bottom: 24px; display: none;">
        <div class="form-group">
          <label style="display: flex; justify-content: space-between; align-items: center;">
            <span>My Saved Addresses</span>
            <button type="button" class="btn-link" onclick="showNewAddressForm()" style="font-size: 14px; color: #2f5b88; background: none; border: none; cursor: pointer; text-decoration: underline;">
              + Add New Address
            </button>
          </label>
          <select id="savedAddressSelect" style="width: 100%; padding: 12px 16px; font-size: 15px; border: 2px solid #e5e7eb; border-radius: 10px;">
            <option value="">Loading addresses...</option>
          </select>
        </div>
      </div>

      <form id="deliveryForm" method="POST" action="checkout_delivery_review.php">
        <input type="hidden" name="mode" value="delivery">
        <input type="hidden" name="pid" value="<?= $pid ?>">

        <!-- Personal Information -->
        <div class="form-row">
          <div class="form-group">
            <label>First Name <span class="required">*</span></label>
            <input type="text" name="first_name" placeholder="Enter your first name" required>
          </div>

          <div class="form-group">
            <label>Last Name <span class="required">*</span></label>
            <input type="text" name="last_name" placeholder="Enter your last name" required>
          </div>
        </div>

        <!-- Contact Information -->
        <div class="form-group">
          <label>Mobile Number <span class="required">*</span></label>
          <div class="phone-group">
            <input type="text" value="+63" disabled class="country-code">
            <input type="tel" id="phoneLocal" name="phoneLocal" class="phone-input" 
                   placeholder="9123456789" pattern="[0-9]{10}" maxlength="10" 
                   inputmode="numeric" required>
          </div>
          <input type="hidden" id="phoneFull" name="phone">
          <small>Enter 10 digits only (example: 9123456789)</small>
        </div>

        <!-- Address Information -->
        <div class="form-row">
          <div class="form-group">
            <label>Province <span class="required">*</span></label>
            <select id="province" name="province" required disabled>
              <option value="">Loading provinces...</option>
            </select>
            <input type="hidden" id="provinceVal" name="province">
            <input type="text" id="provinceInput" name="province" placeholder="Enter province" hidden disabled>
          </div>

          <div class="form-group">
            <label>City/Municipality <span class="required">*</span></label>
            <select id="city" name="city" required disabled>
              <option value="">Select province first</option>
            </select>
            <input type="hidden" id="cityVal" name="city">
            <input type="text" id="cityInput" name="city" placeholder="Enter city" hidden disabled>
          </div>
        </div>

        <div class="form-group">
          <label>Barangay <span class="required">*</span></label>
          <select id="barangaySelect" name="barangay" required disabled>
            <option value="">Select city first</option>
          </select>
          <input type="hidden" id="barangayVal" name="barangay">
          <input type="text" id="barangayInput" name="barangay" placeholder="Enter barangay" hidden disabled>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Street / Block / Lot <span class="required">*</span></label>
            <input type="text" name="street" placeholder="123 Main Street, Block 4 Lot 5" required>
          </div>

          <div class="form-group">
            <label>Postal Code</label>
            <input type="text" name="postal" placeholder="4114" pattern="[0-9]{4}" maxlength="4" inputmode="numeric">
          </div>
        </div>

        <!-- Email (Optional) -->
        <div class="form-group">
          <label>Email Address (Optional)</label>
          <input type="email" name="email" placeholder="your.email@example.com">
          <small>We'll send order updates to this email</small>
        </div>

        <!-- Action Buttons -->
        <div class="btn-group">
          <button type="button" class="btn btn-secondary" id="btnClear">
            <span class="material-symbols-rounded">restart_alt</span>
            Clear Form
          </button>
          <button type="button" class="btn btn-primary" id="btnContinue">
            <span>Continue to Review</span>
            <span class="material-symbols-rounded">arrow_forward</span>
          </button>
        </div>
      </form>
    </div>
  </main>

  <footer class="footer">
    <div class="footer-bottom">
      <p>© 2025 RADS TOOLING INC. All rights reserved.</p>
    </div>
  </footer>
</div>

<!-- Validation Modal -->
<div class="rt-modal" id="invalidModal" hidden>
  <div class="rt-card">
    <h3>Incomplete Form</h3>
    <p>Please fill in all the highlighted fields before continuing.</p>
    <div class="rt-actions">
      <button class="rt-btn" onclick="document.getElementById('invalidModal').hidden = true">OK</button>
    </div>
  </div>
</div>

<script src="/RADS-TOOLING/assets/JS/checkout.js" defer></script>
</body>
</html> 