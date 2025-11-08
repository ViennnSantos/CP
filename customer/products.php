<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Try to load config
$configPaths = [
    __DIR__ . '/../backend/config/app.php',
    __DIR__ . '/../../backend/config/app.php',
    __DIR__ . '/../backend/config/database.php'
];

$pdo = null;
foreach ($configPaths as $configPath) {
    if (file_exists($configPath)) {
        require_once $configPath;
        break;
    }
}

if (!isset($pdo) || !$pdo) {
    if (class_exists('Database')) {
        try {
            $pdo = Database::getInstance()->getConnection();
        } catch (Throwable $e) {
            error_log('Testimonials DB error: ' . $e->getMessage());
        }
    }
}

if (!$pdo) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=rads_tooling;charset=utf8mb4", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Testimonials DB connection failed: ' . $e->getMessage());
    }
}

$testimonials = [];
$stats = ['count' => 0, 'avg' => 0];

if ($pdo instanceof PDO) {
    try {
        $statsQuery = $pdo->query("
            SELECT 
                COUNT(*) AS cnt, 
                COALESCE(ROUND(AVG(rating), 1), 0) AS avg_rating
            FROM feedback
            WHERE is_released = 1
        ");
        $statsRow = $statsQuery->fetch(PDO::FETCH_ASSOC);
        $stats['count'] = (int)($statsRow['cnt'] ?? 0);
        $stats['avg'] = (float)($statsRow['avg_rating'] ?? 0);

        $stmt = $pdo->prepare("
            SELECT 
                f.rating,
                f.comment,
                f.created_at,
                COALESCE(c.full_name, 'Customer') AS customer_name
            FROM feedback f
            INNER JOIN customers c ON c.id = f.customer_id
            WHERE f.is_released = 1
            ORDER BY COALESCE(f.released_at, f.created_at) DESC
            LIMIT 50
        ");
        $stmt->execute();
        $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    } catch (Throwable $e) {
        error_log('Testimonials query error: ' . $e->getMessage());
    }
}

function e(string $s): string { 
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); 
}

function safeDate(?string $s): string {
    if (!$s) return '—';
    $t = strtotime($s);
    return $t ? date('M d, Y', $t) : e($s);
}
// /customer/products.php - Customer Products Page
require_once __DIR__ . '/../backend/config/app.php';
require_once __DIR__ . '/../backend/lib/cms_helper.php';

// CRITICAL: Check if we're in preview mode
$isPreview = isset($GLOBALS['cms_preview_content']) && !empty($GLOBALS['cms_preview_content']);

// Auth guard only if NOT in preview
if (!$isPreview) {
    require_once __DIR__ . '/../includes/guard.php';
    guard_require_customer();

    $user = $_SESSION['user'] ?? null;
    $isCustomer = $user && (($user['aud'] ?? '') === 'customer');
    if (!$isCustomer) {
        header('Location: /customer/cust_login.php');
        exit;
    }
} else {
    // ensure $user exists to avoid notices
    $user = $_SESSION['user'] ?? ['name' => 'Guest', 'username' => 'guest', 'id' => 0];
}

// If in preview, use the content passed by cms_preview.php
// Otherwise, fetch published content for customer view
if ($isPreview) {
    $content = $GLOBALS['cms_preview_content'];
} else {
    $content = getCMSContent('about');
}

$customerName = htmlspecialchars($user['name'] ?? $user['username'] ?? 'Guest');
$customerId = (int)($user['id'] ?? 0);

// -------- Filters + Query for customer products page --------
$q = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'All';
$activeType = $type;

// Build query (released only)
$sql = "SELECT * FROM products WHERE status = 'released'";
$params = [];

if ($type !== 'All') {
    $sql .= " AND type = ?";
    $params[] = $type;
}
if ($q !== '') {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $like = "%{$q}%";
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Image URL normalizer (robust and safe)
function rt_img_url($raw)
{
    $raw = trim((string)$raw);

    // empty -> placeholder
    if ($raw === '') {
        return '/uploads/products/placeholder.jpg';
    }

    // absolute url -> return as-is
    if (preg_match('~^https?://~i', $raw)) return $raw;

    // already absolute path
    if ($raw[0] === '/') return $raw;

    // known relative roots -> prefix with RADS-TOOLING base
    $knownRoots = [
        'uploads/products/',
        'assets/uploads/',
        'assets/images/',
        'images/',
        'backend/uploads/',
    ];
    foreach ($knownRoots as $root) {
        if (strpos($raw, $root) === 0) {
            return '/' . ltrim($raw, '/');
        }
    }

    // sensible fallback: if it contains uploads or products, prefix anyway
    if (stripos($raw, 'uploads/') !== false || stripos($raw, 'products/') !== false) {
        return '/' . ltrim($raw, '/');
    }

    // final fallback -> placeholder only (don't concat raw)
    return '/uploads/products/placeholder.jpg';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Products - <?= $customerName ?></title>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="/assets/CSS/Homepage.css" />
    <link rel="stylesheet" href="/assets/CSS/chat-widget.css">
    <link rel="stylesheet" href="/assets/CSS/product.css">
    <link rel="stylesheet" href="/assets/CSS/product-ux-enhanced.css">
    <link rel="stylesheet" href="/assets/CSS/checkout_modal.css">
    <link rel="stylesheet" href="/assets/CSS/responsive.css">
    <style>
        /* small utility so grid imgs have a class we can target in JS patch */
        .product-grid-img { width:100%; height:180px; object-fit:cover; display:block; border-radius:6px; }
        .rt-card {
  border-radius: 12px;
  padding: 0; /* content uses inner wrappers */
  overflow: hidden;
  box-shadow: 0 8px 20px rgba(8,15,35,0.06);
  background: #fff;
}

/* Image wrapper: make image area a bit smaller (so buttons appear proportionate) */
.rt-imgwrap {
  height: 220px; /* was 180/varies - adjust as needed */
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fff;
  padding: 18px;
}

/* Grid images */
.product-grid-img {
  width: 100%;
  height: 100%;
  object-fit: contain; /* contain so not cropped too much */
  border-radius: 8px;
  transition: transform .18s ease;
}
.rt-imgwrap:hover .product-grid-img {
  transform: translateY(-4px);
}

/* Content section: smaller paddings */
.rt-content {
  padding: 14px 18px;
}

/* Title & description sizes */
.rt-name {
  font-weight: 700;
  font-size: 15px; /* smaller than before */
  color: #17233b;
  margin-bottom: 6px;
}
.rt-desc {
  color: #6b7280;
  font-size: 13px;
  margin-bottom: 10px;
}

/* Price */
.rt-price {
  font-weight: 800;
  color: var(--blue, #2f5b88);
  font-size: 16px;
  margin-bottom: 10px;
}

/* CTA row (buttons) - make them compact like first image */
.rt-cta {
  display:flex;
  gap:10px;
  align-items:center;
  justify-content:space-between;
  padding-top:6px;
}

/* Add to Cart small pill */
.btn-add-cart,
.rt-cta .btn-add-cart {
  background: #ffffff;
  border: 1px solid #e6eef7;
  color: #2f5b88;
  padding: 8px 14px;
  border-radius: 10px;
  min-width: 120px;
  height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-weight: 600;
  font-size: 13px;
  white-space: nowrap; /* ❗ keeps text in one line */
  box-shadow: 0 6px 18px rgba(15,23,42,0.03);
  line-height: 1; /* centers vertically */
}

.btn-add-cart .material-symbols-rounded {
  font-size: 17px;
  line-height: 1;
  transform: translateY(0.5px); /* slight adjust for perfect align */
}


/* Primary Buy Now button condensed */
.rt-btn.main,
.rt-cta .js-buynow,
.rt-cta .btn-detail.primary {
  background: var(--blue, #2f5b88);
  color: #fff;
  padding: 8px 18px;
  border-radius: 26px; /* pill */
  min-width: 120px;
  font-weight:700;
  font-size:13px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  box-shadow:none;
}

/* reduce icon size inside buttons */
.rt-cta .material-symbols-rounded,
.btn-add-cart .material-symbols-rounded {
  font-size:16px;
  vertical-align:middle;
}

/* make both buttons appear equal-height */
.rt-cta .btn-add-cart,
.rt-cta .js-buynow {
  height:40px;
  line-height:40px;
}

/* responsive: on small screens stack buttons but keep compact */
@media (max-width:720px) {
  .rt-cta {
    flex-direction: row;
    gap:8px;
  }
  .rt-imgwrap { height: 260px; }
  .product-grid-img { object-fit:cover; }
  .btn-add-cart, .rt-cta .js-buynow { min-width: auto; padding:8px 12px; }
}

/* Optional: make the tiny edit/customize icon less intrusive (if you have that tag) */
.rt-ico.rt-left-ico {
  width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center;
  box-shadow:0 4px 12px rgba(8,15,35,0.06);
  background:#fff;
}

/* Fix card bottom spacing so buttons don't look floating */
.products-wrap .rt-grid { gap:22px; }
    </style>
    
</head>

<body>
    <div class="page-wrapper">

        <!-- HEADER -->
        <header class="navbar">
            <div class="navbar-container">
                <div class="navbar-brand">
                    <a href="/customer/homepage.php" class="logo-link">
                        <span class="logo-text">R</span>ADS <span class="logo-text">T</span>OOLING
                    </a>
                </div>

                <form class="search-container" action="/customer/products.php" method="get">
                    <input type="text" name="q" class="search-input" placeholder="Search cabinets..."
                        value="<?= htmlspecialchars($q) ?>" />
                    <button type="submit" class="search-btn" aria-label="Search">
                        <span class="material-symbols-rounded">search</span>
                    </button>
                </form>

                <div class="navbar-actions">
                    <!-- Profile Dropdown -->
                    <div class="profile-menu">
                        <button class="profile-toggle" id="profileToggle" type="button">
                            <div class="profile-avatar-wrapper">
                                <div class="profile-avatar" id="nav-avatar">
                                    <?= strtoupper(substr($customerName, 0, 1)) ?>
                                </div>
                            </div>
                            <div class="profile-info">
                                <span class="profile-name"><?= $customerName ?></span>
                                <span class="material-symbols-rounded dropdown-icon">expand_more</span>
                            </div>
                        </button>

                        <div class="profile-dropdown" id="profileDropdown">
                            <div class="profile-dropdown-header">
                                <div class="dropdown-avatar" id="dd-avatar">
                                    <?= strtoupper(substr($customerName, 0, 1)) ?>
                                </div>
                                <div class="dropdown-user-info">
                                    <div class="dropdown-name" id="dd-name"><?= $customerName ?></div>
                                    <div class="dropdown-email" id="userEmailDisplay">Loading...</div>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="/customer/profile.php" class="dropdown-item">
                                <span class="material-symbols-rounded">person</span>
                                <span>My Profile</span>
                            </a>
                            <a href="/customer/orders.php" class="dropdown-item">
                                <span class="material-symbols-rounded">receipt_long</span>
                                <span>My Orders</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <button onclick="showLogoutModal()" class="dropdown-item dropdown-logout" type="button">
                                <span class="material-symbols-rounded">logout</span>
                                <span>Logout</span>
                            </button>
                        </div>
                    </div>


                    <!-- Cart -->
                    <a href="/customer/cart.php" class="cart-button">
                        <span class="material-symbols-rounded">shopping_cart</span>
                        <span id="cartCount" class="cart-badge">0</span>
                    </a>
                </div>
            </div>

            <nav class="navbar-menu">
                <a href="/customer/homepage.php" class="nav-menu-item">Home</a>
                <a href="/customer/about.php" class="nav-menu-item">About</a>
                <a href="/customer/products.php" class="nav-menu-item active">Products</a>
                <a href="/customer/testimonials.php" class="nav-menu-item">Testimonials</a>
            </nav>

            <!-- Category Tabs -->
            <div class="navbar-cats-bar">
                <div class="navbar-container navbar-container--cats">
                    <nav class="navbar-cats">
                        <?php
                        $cat = function ($label) use ($q, $activeType) {
                            $href = "/customer/products.php?type=" . urlencode($label) . ($q !== '' ? "&q=" . urlencode($q) : "");
                            $active = ($activeType === $label) ? 'active' : '';
                            echo '<a class="nav-menu-item ' . $active . '" href="' . htmlspecialchars($href) . '">' . htmlspecialchars($label) . '</a>';
                        };
                        $cat('All');
                        $cat('Kitchen Cabinet');
                        $cat('Wardrobe');
                        $cat('Office Cabinet');
                        $cat('Bathroom Cabinet');
                        $cat('Storage Cabinet');
                        ?>
                    </nav>
                </div>
            </div>
        </header>

        <!-- MAIN CONTENT -->
        <main class="products-wrap">
            <?php if (empty($products)) : ?>
                <div class="rt-empty">
                    No released products found<?= $q !== '' ? ' for "' . htmlspecialchars($q) . '"' : '' ?>.
                </div>
            <?php else : ?>
                <div class="rt-grid" id="productsGrid">
                    <?php foreach ($products as $p):
                        $id = (int)($p['id'] ?? 0);
                        $name = $p['name'] ?? 'Untitled';
                        $desc = $p['short_desc'] ?? ($p['description'] ?? '');
                        $price = (float)($p['base_price'] ?? ($p['price'] ?? 0));
                        $img = rt_img_url($p['image'] ?? ($p['image_url'] ?? ''));
                        $type = $p['type'] ?? 'Cabinet';
                    ?>
                        <article class="rt-card" data-pid="<?= $id ?>">
                            <div class="rt-imgwrap">
                                <img class="product-grid-img" src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($name) ?>"
                                     onerror="this.onerror=null;this.src='/uploads/products/placeholder.jpg'">

                                <?php if (!empty($p['is_customizable']) && (int)$p['is_customizable'] === 1): ?>
                                    <a class="rt-ico rt-left-ico" href="/customer/customization.php?pid=<?= $id ?>" title="Customize">
                                        <span class="material-symbols-rounded">edit_square</span>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <div class="rt-content">
                                <div class="rt-name"><?= htmlspecialchars($name) ?></div>
                                <div class="rt-desc"><?= htmlspecialchars($desc) ?></div>
                                <div class="rt-price">₱ <?= number_format($price, 2) ?></div>

                                <div class="rt-cta">
                                    <button class="btn-add-cart" data-action="add-to-cart" data-pid="<?= $id ?>" data-name="<?= htmlspecialchars($name) ?>" data-type="<?= htmlspecialchars($type) ?>" data-price="<?= $price ?>" data-image="<?= htmlspecialchars($img) ?>">
                                        <span class="material-symbols-rounded">shopping_cart</span>
                                        Add to Cart
                                    </button>

                                    <button type="button" class="rt-btn main js-buynow" data-pid="<?= $id ?>">
                                        Buy Now
                                    </button>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>

      <!-- FOOTER -->
        <footer class="footer">
            <div class="footer-content">
                <!-- About Section -->
                <div class="footer-section">
                    <h3>About RADS TOOLING</h3>
                    <p class="footer-description">
                        Premium custom cabinet manufacturer serving clients since 2007.
                        Quality craftsmanship, affordable prices, and exceptional service.
                    </p>
                    <div class="footer-social">
                        <a href="#" class="social-icon" aria-label="Facebook">
                            <span class="material-symbols-rounded">facebook</span>
                        </a>
                        <a href="mailto:RadsTooling@gmail.com" class="social-icon" aria-label="Email">
                            <span class="material-symbols-rounded">mail</span>
                        </a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="/customer/homepage.php">Home</a></li>
                        <li><a href="/customer/about.php">About Us</a></li>
                        <li><a href="/customer/products.php">Products</a></li>
                        <li><a href="/customer/testimonials.php">Testimonials</a></li>
                    </ul>
                </div>

                <!-- Categories -->
                <div class="footer-section">
                    <h3>Categories</h3>
                    <ul class="footer-links">
                        <li><a href="/customer/products.php?type=Kitchen Cabinet">Kitchen Cabinet</a></li>
                        <li><a href="/customer/products.php?type=Wardrobe">Wardrobe</a></li>
                        <li><a href="/customer/products.php?type=Office Cabinet">Office Cabinet</a></li>
                        <li><a href="/customer/products.php?type=Bathroom Cabinet">Bathroom Cabinet</a></li>
                        <li><a href="/customer/products.php?type=Commercial">Storage Cabinet</a></li>
                    </ul>
                </div>

                <!-- Contact Info -->
                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <div class="contact-info-item">
                        <span class="material-symbols-rounded">location_on</span>
                        <span>Green Breeze, Piela, Dasmariñas, Cavite</span>
                    </div>
                    <div class="contact-info-item">
                        <span class="material-symbols-rounded">mail</span>
                        <a href="mailto:RadsTooling@gmail.com">RadsTooling@gmail.com</a>
                    </div>
                    <div class="contact-info-item">
                        <span class="material-symbols-rounded">schedule</span>
                        <span>Mon-Sat: 8:00 AM - 5:00 PM</span>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p class="footer-copyright">
                    © 2025 RADS TOOLING INC. All rights reserved.
                </p>
                <div class="footer-legal">
                    <a href="/customer/privacy.php">Privacy Policy</a>
                    <a href="/customer/terms.php">Terms & Conditions</a>
                </div>
            </div>
        </footer>
    </div>
    </div>

    <!-- BUY CHOICE MODAL -->
    <div id="buyChoiceModal" class="rt-modal" hidden>
        <div class="rt-modal__dialog rt-card">
            <div class="rt-header">
                <h3>How do you want to get your order?</h3>
                <button type="button" class="rt-close" id="closeChoiceModal">×</button>
            </div>

            <div class="rt-body">
                <p class="muted">Choose your preferred fulfillment method.</p>

                <div class="rt-choices">
                    <button id="choiceDelivery" type="button" class="rt-choice">
                        <span class="material-symbols-rounded">local_shipping</span>
                        Delivery
                    </button>
                    <button id="choicePickup" type="button" class="rt-choice">
                        <span class="material-symbols-rounded">store</span>
                        Pick-up
                    </button>
                </div>

                <div class="rt-footer">
                    <button id="choiceOk" type="button" class="rt-btn rt-btn-primary" disabled>OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- LOGOUT MODAL -->
    <div class="modal" id="logoutModal" style="display:none;">
        <div class="modal-content modal-small">
            <button class="modal-close" onclick="closeLogoutModal()" type="button">
                <span class="material-symbols-rounded">close</span>
            </button>
            <div class="modal-icon-wrapper">
                <div class="modal-icon warning">
                    <span class="material-symbols-rounded">logout</span>
                </div>
            </div>
            <h2 class="modal-title">Confirm Logout</h2>
            <p class="modal-message">Are you sure you want to logout?</p>
            <div class="modal-actions">
                <button onclick="closeLogoutModal()" class="btn-modal-secondary" type="button">Cancel</button>
                <button onclick="confirmLogout()" class="btn-modal-primary" type="button">Logout</button>
            </div>
        </div>
    </div>

    <!-- CHAT WIDGET -->
    <button id="rtChatBtn" class="rt-chat-btn">
        <span class="material-symbols-rounded">chat</span>
        Need Help?
    </button>

    <div id="rtChatPopup" class="rt-chat-popup">
        <div class="rt-chat-header">
            <span>Rads Tooling - Chat Support</span>
            <button id="rtClearChat" class="rt-clear-btn" type="button">
                <span class="material-symbols-rounded">delete</span>
            </button>
        </div>
        <div class="rt-faq-container" id="rtFaqContainer">
            <div class="rt-faq-toggle" id="rtFaqToggle">
                <span>Quick FAQs</span>
                <span class="rt-faq-icon">▼</span>
            </div>
            <div class="rt-faq-dropdown" id="rtFaqDropdown">
                <div style="padding: 12px; color: #999; font-size: 13px;">Loading FAQs...</div>
            </div>
        </div>
        <div id="rtChatMessages" class="rt-chat-messages"></div>
        <div class="rt-chat-input">
            <input id="rtChatInput" type="text" placeholder="Type your message…" />
            <button id="rtChatSend" class="rt-chat-send">
                <span class="material-symbols-rounded">send</span>
            </button>
        </div>
    </div>

    <!-- ========== ONE SINGLE CLEAN SCRIPT ========== -->
    <script>
        (function() {
            'use strict';

            let selectedPID = null;
            let selectedMode = null;

            // Initialize everything on page load
            document.addEventListener('DOMContentLoaded', function() {
                console.log('Page loaded');
                initBuyNowModal();
                initAddToCart();
                initProfileDropdown();
                updateCartCount();

                // small delay to allow images rendered by PHP to be present
                setTimeout(patchProductCardsWithPrimaryImages, 150);
            });

            // ===== BUY NOW MODAL =====
            function initBuyNowModal() {
                const modal = document.getElementById('buyChoiceModal');
                const closeBtn = document.getElementById('closeChoiceModal');
                const deliveryBtn = document.getElementById('choiceDelivery');
                const pickupBtn = document.getElementById('choicePickup');
                const okBtn = document.getElementById('choiceOk');

                if (!modal) {
                    console.error('Modal not found');
                    return;
                }

                console.log('Modal initialized');

                // Buy Now button clicks
                document.addEventListener('click', function(e) {
                    const buyBtn = e.target.closest('.js-buynow');
                    if (!buyBtn) return;

                    e.preventDefault();
                    e.stopPropagation();

                    selectedPID = buyBtn.getAttribute('data-pid');
                    console.log('Buy Now clicked, PID:', selectedPID);

                    // Reset modal
                    selectedMode = null;
                    deliveryBtn.classList.remove('is-active');
                    pickupBtn.classList.remove('is-active');
                    okBtn.disabled = true;

                    // Show modal
                    modal.hidden = false;
                });

                // Close button
                closeBtn.addEventListener('click', function() {
                    modal.hidden = true;
                    selectedPID = null;
                    selectedMode = null;
                });

                // Delivery
                deliveryBtn.addEventListener('click', function() {
                    selectedMode = 'delivery';
                    deliveryBtn.classList.add('is-active');
                    pickupBtn.classList.remove('is-active');
                    okBtn.disabled = false;
                });

                // Pickup
                pickupBtn.addEventListener('click', function() {
                    selectedMode = 'pickup';
                    pickupBtn.classList.add('is-active');
                    deliveryBtn.classList.remove('is-active');
                    okBtn.disabled = false;
                });

                // OK button
                okBtn.addEventListener('click', function() {
                    if (!selectedPID || !selectedMode) return;

                    const url = selectedMode === 'delivery' ?
                        '/customer/checkout_delivery.php?pid=' + selectedPID :
                        '/customer/checkout_pickup.php?pid=' + selectedPID;

                    window.location.href = url;
                });
            }

            // ===== ADD TO CART =====
            function initAddToCart() {
                document.addEventListener('click', function(e) {
                    const addBtn = e.target.closest('[data-action="add-to-cart"]');
                    if (!addBtn) return;

                    e.preventDefault();
                    e.stopPropagation();

                    const product = {
                        id: parseInt(addBtn.dataset.pid),
                        name: addBtn.dataset.name,
                        type: addBtn.dataset.type,
                        price: parseFloat(addBtn.dataset.price),
                        image: addBtn.dataset.image,
                        quantity: 1
                    };

                    addToCart(product);
                });
            }

            function addToCart(product) {
                let cart = JSON.parse(localStorage.getItem('cart') || '[]');
                const existingIndex = cart.findIndex(item => item.id === product.id);

                if (existingIndex !== -1) {
                    cart[existingIndex].quantity += 1;
                    showToast(product.name + ' quantity updated!', 'success');
                } else {
                    cart.push(product);
                    showToast(product.name + ' added to cart!', 'success');
                }

                localStorage.setItem('cart', JSON.stringify(cart));
                updateCartCount();
            }

            function updateCartCount() {
                const cart = JSON.parse(localStorage.getItem('cart') || '[]');
                const count = cart.reduce((sum, item) => sum + item.quantity, 0);

                document.querySelectorAll('#cartCount, #navCartCount, .cart-badge').forEach(badge => {
                    badge.textContent = count;
                });
            }

            function showToast(message, type) {
                const toast = document.createElement('div');
                toast.textContent = message;
                toast.style.cssText = 'position:fixed;top:100px;right:20px;background:' +
                    (type === 'success' ? '#3db36b' : '#2f5b88') +
                    ';color:#fff;padding:1rem 1.5rem;border-radius:10px;' +
                    'box-shadow:0 4px 12px rgba(0,0,0,0.2);z-index:10000;font-weight:600;';

                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            }

            // ===== PROFILE DROPDOWN =====
            function initProfileDropdown() {
                const toggle = document.getElementById('profileToggle');
                const dropdown = document.getElementById('profileDropdown');

                if (!toggle || !dropdown) return;

                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropdown.classList.toggle('show');
                });

                document.addEventListener('click', function(e) {
                    if (!toggle.contains(e.target) && !dropdown.contains(e.target)) {
                        dropdown.classList.remove('show');
                    }
                });
            }

            // ===== LOGOUT =====
            window.showLogoutModal = function() {
                const modal = document.getElementById('logoutModal');
                if (modal) modal.style.display = 'flex';
            };

            window.closeLogoutModal = function() {
                const modal = document.getElementById('logoutModal');
                if (modal) modal.style.display = 'none';
            };

            window.confirmLogout = function() {
                fetch('/backend/api/auth.php?action=logout', {
                    method: 'POST',
                    credentials: 'same-origin'
                }).finally(function() {
                    sessionStorage.clear();
                    window.location.href = '/index.php';
                });
            };

            // ===== Patch product cards to use product_images primary if available =====
            async function patchProductCardsWithPrimaryImages() {
                try {
                    const imgs = Array.from(document.querySelectorAll('.product-grid-img'));
                    if (!imgs.length) return;

                    // Collect product ids
                    const productIds = [...new Set(imgs.map(i => i.closest('.rt-card')?.dataset?.pid).filter(Boolean))];

                    // For each product id, fetch primary image once
                    await Promise.all(productIds.map(async pid => {
                        try {
                            const resp = await fetch(`/backend/api/product_images.php?action=list&product_id=${pid}`, { credentials: 'same-origin' });
                            const json = await resp.json().catch(() => ({ success: false }));
                            if (!json.success) return;

                            const imgsData = json.data?.images || json.data || [];
                            if (!Array.isArray(imgsData) || imgsData.length === 0) return;

                            const primary = imgsData.find(i => Number(i.is_primary) === 1) || imgsData[0];
                            if (!primary) return;

                            const filename = String(primary.image_path || primary.path || primary.filename || '').split('/').pop();
                            if (!filename) return;

                            const desiredSrc = `/uploads/products/${filename}`;

                            // apply to all matching card imgs for this pid if they are placeholder or different
                            imgs.forEach(imgEl => {
                                const card = imgEl.closest('.rt-card');
                                if (!card) return;
                                if (String(card.dataset.pid) !== String(pid)) return;
                                const currentSrc = imgEl.getAttribute('src') || '';
                                const isPlaceholder = currentSrc.endsWith('placeholder.jpg') || currentSrc.includes('/placeholder.jpg') || currentSrc === '';
                                // update if placeholder or different file
                                if (isPlaceholder || !currentSrc.endsWith(filename)) {
                                    imgEl.src = desiredSrc;
                                }
                            });

                        } catch (err) {
                            console.warn('patch images fetch error for product', pid, err);
                        }
                    }));
                } catch (err) {
                    console.error('patchProductCardsWithPrimaryImages error', err);
                }
            }

        })();
    </script>

<script>
// Make product cards clickable (redirect to detail page)
document.addEventListener('DOMContentLoaded', function() {
    // Add click handler to all product cards
    document.querySelectorAll('.rt-card').forEach(card => {
        // Get the product ID from the Buy Now button
        const buyNowBtn = card.querySelector('.js-buynow');
        if (buyNowBtn) {
            const productId = buyNowBtn.dataset.pid;
            
            // Make the entire card clickable except for buttons
            card.style.cursor = 'pointer';
            card.addEventListener('click', function(e) {
                // Don't redirect if clicking on buttons or links
                if (e.target.closest('button') || e.target.closest('a')) {
                    return;
                }
                // Redirect to product detail page
                window.location.href = `/customer/product_detail.php?id=${productId}`;
            });
        }
    });
    
    // 🔥 FIX: Load primary images from product_images table
    // This fixes the 404 errors and makes images show properly
    if (typeof patchProductCardsWithPrimaryImages === 'function') {
        patchProductCardsWithPrimaryImages();
    }
});
</script>
<!-- External Scripts -->
    <script src="/assets/JS/nav_user.js"></script>
    <script src="/assets/JS/chat_widget.js"></script>
    <script src="/assets/JS/product-drag.js"></script>
</body>

</html>
