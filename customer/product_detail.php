<?php
// /RADS-TOOLING/customer/product_detail.php - Product Detail Page (simplified info: name, description, price, quantity, add/buy)
declare(strict_types=1);

require_once __DIR__ . '/../backend/config/app.php';

// Auth guard
require_once __DIR__ . '/../includes/guard.php';
guard_require_customer();

$user = $_SESSION['user'] ?? null;
$isCustomer = $user && (($user['aud'] ?? '') === 'customer');
if (!$isCustomer) {
    header('Location: /RADS-TOOLING/customer/cust_login.php');
    exit;
}

$customerName = htmlspecialchars($user['name'] ?? $user['username']);
$customerId = (int)($user['id'] ?? 0);

// Get product ID
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($productId <= 0) {
    header('Location: /RADS-TOOLING/customer/products.php');
    exit;
}

// Image URL normalizer
function rt_img_url(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '/RADS-TOOLING/uploads/products/placeholder.jpg';
    }
    if (preg_match('~^https?://~i', $raw)) return $raw;
    if ($raw[0] === '/') return $raw;
    $knownRoots = [
        'uploads/products/',
        'uploads/textures/',
        'uploads/handles/',
        'assets/uploads/',
        'assets/images/',
        'images/',
        'backend/uploads/',
    ];
    foreach ($knownRoots as $root) {
        if (strpos($raw, $root) === 0) {
            return '/RADS-TOOLING/' . ltrim($raw, '/');
        }
    }
    return '/RADS-TOOLING/uploads/products/placeholder.jpg';
}

// Fetch product details
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'released' LIMIT 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log("Product fetch error: " . $e->getMessage());
    header('Location: /RADS-TOOLING/customer/products.php');
    exit;
}

if (!$product) {
    header('Location: /RADS-TOOLING/customer/products.php');
    exit;
}

// Fetch product images
try {
    $stmt = $pdo->prepare("
        SELECT image_id, image_path, is_primary, display_order 
        FROM product_images 
        WHERE product_id = ? 
        ORDER BY is_primary DESC, display_order ASC, image_id ASC
    ");
    $stmt->execute([$productId]);
    $productImagesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log("Product images fetch error: " . $e->getMessage());
    $productImagesRaw = [];
}

$productImages = [];
foreach ($productImagesRaw as $row) {
    $path = $row['image_path'] ?? '';
    if ($path === '') continue;
    $productImages[] = [
        'image_id' => $row['image_id'] ?? null,
        'url' => rt_img_url($path),
        'is_primary' => (int)($row['is_primary'] ?? 0),
        'display_order' => (int)($row['display_order'] ?? 0),
        'original_path' => $path
    ];
}
if (empty($productImages) && !empty($product['image'])) {
    $productImages[] = [
        'image_id' => null,
        'url' => rt_img_url($product['image']),
        'is_primary' => 1,
        'display_order' => 0,
        'original_path' => $product['image']
    ];
}
if (empty($productImages)) {
    $productImages[] = [
        'image_id' => null,
        'url' => rt_img_url(''),
        'is_primary' => 1,
        'display_order' => 0,
        'original_path' => ''
    ];
}
$primaryImageUrl = $productImages[0]['url'] ?? rt_img_url($product['image'] ?? '');

function e($val)
{
    return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title><?= e($product['name']) ?> — RADS TOOLING</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="/RADS-TOOLING/assets/CSS/Homepage.css">
    <link rel="stylesheet" href="/RADS-TOOLING/assets/CSS/chat-widget.css">
    <link rel="stylesheet" href="/RADS-TOOLING/assets/CSS/product.css">
    <link rel="stylesheet" href="/RADS-TOOLING/assets/CSS/responsive.css">
    <link rel="stylesheet" href="/RADS-TOOLING/assets/CSS/checkout_modal.css">

    <style>
        :root {
            --blue: #2f5b88;
            --muted: #f1f5f9;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f7fafc;
        }

        .product-detail-container {
            max-width: 1200px;
            margin: 48px auto;
            padding: 24px;
            display: grid;
            grid-template-columns: 520px 1fr;
            gap: 48px;
            align-items: start;
        }

        .back-link {
            grid-column: 1/-1;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--blue);
            font-weight: 600;
            margin-bottom: 8px;
        }

        .main-image-container {
            position: relative;
            height: 580px;
            border-radius: 14px;
            overflow: hidden;
            background: var(--muted);
            padding: 28px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .main-image-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            display: block;
        }

        .image-counter {
            position: absolute;
            right: 18px;
            bottom: 18px;
            background: rgba(0, 0, 0, .6);
            color: #fff;
            padding: 8px 14px;
            border-radius: 22px;
            font-weight: 700;
        }

        .image-thumbnails {
            display: flex;
            gap: 10px;
            margin-top: 12px;
            overflow-x: auto;
            padding: 6px;
        }

        .thumbnail {
            width: 76px;
            height: 76px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            border: 3px solid transparent;
            flex-shrink: 0;
            background: #fff;
        }

        .thumbnail.active {
            border-color: var(--blue);
            transform: translateY(-3px);
        }

        .product-info-section {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .product-title {
            font-size: 34px;
            font-weight: 800;
            margin: 0;
            color: #17233b;
        }

        .product-price {
            font-size: 36px;
            color: var(--blue);
            font-weight: 800;
            margin-top: 8px;
        }

        .product-description-box {
            background: #fff;
            padding: 18px;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, .03);
            color: #495266;
            line-height: 1.6;
            margin-top: 4px;
        }

        /* quantity control */
        .quantity-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }

        .qty-control {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 10px;
            background: #fff;
            padding: 6px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, .03);
        }

        .qty-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            background: #fff;
            cursor: pointer;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .qty-input {
            width: 72px;
            text-align: center;
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid #e6e9ef;
            font-weight: 700;
        }

        .product-actions {
            display: flex;
            gap: 12px;
            margin-top: 28px;
            align-items: center;
        }

        .btn-detail {
            padding: 12px 18px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            border: 0;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-detail.primary {
            background: var(--blue);
            color: #fff;
        }

        .btn-detail.secondary {
            background: #fff;
            color: var(--blue);
            border: 2px solid var(--blue);
        }

        /* modal backdrop effect (keeps similar visual as products.php) */
        .rt-modal {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 12000;
        }

        .rt-modal__dialog {
            width: 520px;
            max-width: 92%;
            border-radius: 10px;
            overflow: hidden;
        }

        .rt-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(8, 15, 35, .25);
        }

        /* IMPORTANT: ensure modal respects hidden attribute */
        #buyChoiceModal[hidden] {
            display: none !important;
        }

        #buyChoiceModal:not([hidden]) {
            display: flex !important;
            align-items: center;
            justify-content: center;
        }

  /* Image Modal Styles - FIX: hide by default, show with .active class */

        .image-modal {

            position: fixed;

            inset: 0;

            background: rgba(0, 0, 0, 0.9);

            z-index: 15000;

            display: none;

            align-items: center;

            justify-content: center;

            opacity: 0;

            transition: opacity 0.3s ease;

        }

 

        .image-modal.active {

            display: flex;

            opacity: 1;

        }
        @media (max-width:820px) {
            .product-detail-container {
                grid-template-columns: 1fr;
                gap: 18px;
                padding: 18px;
            }

            .main-image-container {
                height: 420px;
            }

            .product-title {
                font-size: 26px;
            }

            .product-price {
                font-size: 26px;
            }
        }
    </style>
</head>

<body>
    <div class="page-wrapper">
        <!-- HEADER (kept same as products.php for parity) -->
        <header class="navbar">
            <div class="navbar-container">
                <div class="navbar-brand">
                    <a href="/RADS-TOOLING/customer/homepage.php" class="logo-link"><span class="logo-text">R</span>ADS <span class="logo-text">T</span>OOLING</a>
                </div>

                <form class="search-container" action="/RADS-TOOLING/customer/products.php" method="get">
                    <input type="text" name="q" class="search-input" placeholder="Search cabinets..." value="">
                    <button type="submit" class="search-btn" aria-label="Search"><span class="material-symbols-rounded">search</span></button>
                </form>

                <div class="navbar-actions">
                    <div class="profile-menu">
                        <button class="profile-toggle" id="profileToggle" type="button">
                            <div class="profile-avatar-wrapper">
                                <div class="profile-avatar" id="nav-avatar"><?= strtoupper(substr($customerName, 0, 1)) ?></div>
                            </div>
                            <div class="profile-info"><span class="profile-name"><?= $customerName ?></span><span class="material-symbols-rounded dropdown-icon">expand_more</span></div>
                        </button>
                        <div class="profile-dropdown" id="profileDropdown">
                            <div class="profile-dropdown-header">
                                <div class="dropdown-avatar"><?= strtoupper(substr($customerName, 0, 1)) ?></div>
                                <div class="dropdown-user-info">
                                    <div class="dropdown-name"><?= $customerName ?></div>
                                    <div class="dropdown-email">Loading...</div>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="/RADS-TOOLING/customer/profile.php" class="dropdown-item"><span class="material-symbols-rounded">person</span><span>My Profile</span></a>
                            <a href="/RADS-TOOLING/customer/orders.php" class="dropdown-item"><span class="material-symbols-rounded">receipt_long</span><span>My Orders</span></a>
                            <div class="dropdown-divider"></div>
                            <button onclick="showLogoutModal()" class="dropdown-item dropdown-logout" type="button"><span class="material-symbols-rounded">logout</span><span>Logout</span></button>
                        </div>
                    </div>

                    <a href="/RADS-TOOLING/customer/cart.php" class="cart-button"><span class="material-symbols-rounded">shopping_cart</span><span id="cartCount" class="cart-badge">0</span></a>
                </div>
            </div>

            <nav class="navbar-menu">
                <a href="/RADS-TOOLING/customer/homepage.php" class="nav-menu-item">Home</a>
                <a href="/RADS-TOOLING/customer/about.php" class="nav-menu-item">About</a>
                <a href="/RADS-TOOLING/customer/products.php" class="nav-menu-item active">Products</a>
                <a href="/RADS-TOOLING/customer/testimonials.php" class="nav-menu-item">Testimonials</a>
            </nav>
        </header>

        <!-- MAIN -->
        <main class="product-detail-container" role="main">
            <a class="back-link" href="/RADS-TOOLING/customer/products.php"><span class="material-symbols-rounded">arrow_back</span> Back to Products</a>

            <!-- LEFT: Images -->
            <div class="product-images-section">
                <div id="mainImageWrapper" class="main-image-container" role="button" aria-label="Open large image">
                    <img id="mainImage" src="<?= e($productImages[0]['url']) ?>" alt="<?= e($product['name']) ?>">
                    <?php if (count($productImages) > 1): ?>
                        <div class="image-counter" id="imageCounter"><span id="currentImageNum">1</span> / <?= count($productImages) ?></div>
                    <?php endif; ?>
                </div>

                <?php if (count($productImages) > 1): ?>
                    <div class="image-thumbnails" id="thumbnailGallery" role="list">
                        <?php foreach ($productImages as $i => $img): ?>
                            <img src="<?= e($img['url']) ?>" class="thumbnail <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>" data-src="<?= e($img['url']) ?>" alt="Thumb <?= $i + 1 ?>">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- RIGHT: Info (only name, description, price, quantity, buttons) -->
            <div class="product-info-section">
                <h1 class="product-title"><?= e($product['name']) ?></h1>
                <div class="product-price">₱<?= number_format((float)$product['price'], 2) ?></div>

                <div class="product-description-box">
                    <?= nl2br(e($product['description'] ?? 'No description available.')) ?>
                </div>

                <!-- Quantity control -->
                <div class="quantity-row">
                    <label style="font-weight:700; color:#374151;">Quantity</label>
                    <div class="qty-control" role="group" aria-label="Quantity controls">
                        <button type="button" class="qty-btn" id="qtyMinus" aria-label="Decrease">−</button>
                        <input id="qtyInput" class="qty-input" type="number" min="1" value="1" aria-label="Quantity">
                        <button type="button" class="qty-btn" id="qtyPlus" aria-label="Increase">+</button>
                    </div>
                </div>

                <!-- Actions -->
                <div class="product-actions">
                    <!-- Add to cart: uses quantity -->
                    <button class="btn-detail secondary" id="btnAddToCart" type="button">
                        <span class="material-symbols-rounded">shopping_cart</span> Add to Cart
                    </button>

                    <!-- Buy Now: opens modal; dataset holds pid and we'll attach quantity at click -->
                    <button class="btn-detail primary js-buynow" id="btnBuyNow" data-pid="<?= (int)$productId ?>" type="button">
                        <span class="material-symbols-rounded">local_mall</span> Buy Now
                    </button>
                </div>
            </div>
        </main>

        <!-- FOOTER (same as products.php) -->
        <footer class="footer">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>About RADS TOOLING</h3>
                    <p class="footer-description">Premium custom cabinet manufacturer serving clients since 2007. Quality craftsmanship, affordable prices, and exceptional service.</p>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="/RADS-TOOLING/customer/homepage.php">Home</a></li>
                        <li><a href="/RADS-TOOLING/customer/about.php">About Us</a></li>
                        <li><a href="/RADS-TOOLING/customer/products.php">Products</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <div class="contact-info-item"><span class="material-symbols-rounded">mail</span><a href="mailto:RadsTooling@gmail.com">RadsTooling@gmail.com</a></div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© <?= date('Y') ?> RADS TOOLING INC. All rights reserved.</p>
            </div>
        </footer>
    </div>


    <!-- Image Modal -->
    <div id="imageModal" class="image-modal" aria-hidden="true" role="dialog"><img id="modalImage" class="modal-content-img" alt="Enlarged image"></div>

    <!-- BUY CHOICE MODAL (same structure as products.php) -->
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
            <button class="modal-close" onclick="closeLogoutModal()" type="button"><span class="material-symbols-rounded">close</span></button>
            <div class="modal-icon-wrapper">
                <div class="modal-icon warning"><span class="material-symbols-rounded">logout</span></div>
            </div>
            <h2 class="modal-title">Confirm Logout</h2>
            <p class="modal-message">Are you sure you want to logout?</p>
            <div class="modal-actions"><button onclick="closeLogoutModal()" class="btn-modal-secondary" type="button">Cancel</button><button onclick="confirmLogout()" class="btn-modal-primary" type="button">Logout</button></div>
        </div>
    </div>

    <!-- CHAT WIDGET (unchanged) -->
    <button id="rtChatBtn" class="rt-chat-btn"><span class="material-symbols-rounded">chat</span> Need Help?</button>
    <div id="rtChatPopup" class="rt-chat-popup">
        <div class="rt-chat-header"><span>Rads Tooling - Chat Support</span><button id="rtClearChat" class="rt-clear-btn" type="button"><span class="material-symbols-rounded">delete</span></button></div>
        <div id="rtChatMessages" class="rt-chat-messages"></div>
        <div class="rt-chat-input"><input id="rtChatInput" type="text" placeholder="Type your message…"><button id="rtChatSend" class="rt-chat-send"><span class="material-symbols-rounded">send</span></button></div>
    </div>

    <!-- scripts -->
    <script src="/RADS-TOOLING/assets/js/script.js"></script>
    <script src="/RADS-TOOLING/assets/js/cart.js"></script>

    <script>
        // ===== Quantity control and buy/add handlers =====
        (function() {
            const qtyInput = document.getElementById('qtyInput');
            const minus = document.getElementById('qtyMinus');
            const plus = document.getElementById('qtyPlus');
            const btnAdd = document.getElementById('btnAddToCart');
            const btnBuy = document.getElementById('btnBuyNow');

            // optional max stock (set if you have stock column)
            const maxStock = 9999; // high default; change if you want a real limit
            const minStock = 1;

            function sanitizeQty(v) {
                v = parseInt(String(v).replace(/[^\d-]/g, ''), 10);
                if (isNaN(v) || v < minStock) return minStock;
                if (v > maxStock) return maxStock;
                return v;
            }

            minus?.addEventListener('click', function() {
                let val = sanitizeQty(qtyInput.value);
                if (val > minStock) val = val - 1;
                qtyInput.value = val;
            });

            plus?.addEventListener('click', function() {
                let val = sanitizeQty(qtyInput.value);
                if (val < maxStock) val = val + 1;
                qtyInput.value = val;
            });

            qtyInput?.addEventListener('input', function() {
                qtyInput.value = sanitizeQty(qtyInput.value);
            });

            // Add to Cart
            btnAdd?.addEventListener('click', function() {
                const qty = sanitizeQty(qtyInput.value);
                const pid = <?= (int)$productId ?>;
                const product = {
                    id: pid,
                    name: <?= json_encode($product['name']) ?>,
                    type: <?= json_encode($product['type'] ?? '') ?>,
                    price: parseFloat(<?= (float)$product['price'] ?>),
                    image: <?= json_encode($primaryImageUrl) ?>,
                    quantity: qty
                };

                let cart = JSON.parse(localStorage.getItem('cart') || '[]');
                const existing = cart.findIndex(item => item.id === product.id);
                if (existing !== -1) cart[existing].quantity = (cart[existing].quantity || 0) + qty;
                else cart.push(product);

                localStorage.setItem('cart', JSON.stringify(cart));
                showToast(product.name + ' added to cart (' + qty + ')', 'success');
                try {
                    updateCartCount();
                } catch (e) {}
            });

            // Buy Now (direct button on detail page) - open modal with pid + qty
            btnBuy?.addEventListener('click', function() {
                const qty = sanitizeQty(qtyInput.value);
                const modal = document.getElementById('buyChoiceModal');
                if (!modal) return;
                modal.dataset.pid = String(this.dataset.pid || <?= (int)$productId ?>);
                modal.dataset.qty = String(qty);
                // clear previous mode and disable OK until selection
                delete modal.dataset.mode;
                document.getElementById('choiceOk').disabled = true;
                // remove is-active classes
                document.querySelectorAll('#choiceDelivery, #choicePickup').forEach(el => el.classList.remove('is-active'));
                modal.hidden = false;
                // focus for accessibility
                document.getElementById('choiceDelivery')?.focus();
            });

            // Delegated buy-now support (for other elements with .js-buynow too)
            document.addEventListener('click', function(e) {
                const b = e.target.closest && e.target.closest('.js-buynow');
                if (!b) return;
                e.preventDefault();
                e.stopPropagation();
                const pid = b.dataset.pid || b.getAttribute('data-pid') || '';
                // try to get qty from page (#qtyInput) if present
                let qty = 1;
                const qInput = document.getElementById('qtyInput');
                if (qInput) qty = sanitizeQty(qInput.value);
                else qty = parseInt(b.dataset.qty || b.getAttribute('data-qty') || '1', 10) || 1;

                const modal = document.getElementById('buyChoiceModal');
                if (!modal) return;
                modal.dataset.pid = String(pid || <?= (int)$productId ?>);
                modal.dataset.qty = String(qty);
                delete modal.dataset.mode;
                document.getElementById('choiceOk').disabled = true;
                document.querySelectorAll('#choiceDelivery, #choicePickup').forEach(el => el.classList.remove('is-active'));
                modal.hidden = false;
                document.getElementById('choiceDelivery')?.focus();
            }, {
                passive: false
            });

        })();

        // ===== Gallery and image modal logic =====
        (function() {
            let currentImageIndex = 0;

            function getThumbnails() {
                return Array.from(document.querySelectorAll('.thumbnail'));
            }

            function setActiveThumbnail(i) {
                getThumbnails().forEach(t => t.classList.remove('active'));
                const target = getThumbnails()[i];
                if (target) target.classList.add('active');
            }

            function showImageByIndex(index) {
                const tbs = getThumbnails();
                if (!tbs.length) return;
                if (index < 0) index = 0;
                if (index >= tbs.length) index = tbs.length - 1;
                const thumb = tbs[index];
                const src = thumb.dataset.src || thumb.src;
                if (!src) return;
                const main = document.getElementById('mainImage');
                if (main) {
                    main.src = src;
                    main.alt = thumb.alt || 'Product image';
                }
                currentImageIndex = index;
                const counter = document.getElementById('currentImageNum');
                if (counter) counter.textContent = (index + 1);
                setActiveThumbnail(index);
                try {
                    thumb.scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest',
                        inline: 'center'
                    });
                } catch (e) {}
            }
            document.getElementById('thumbnailGallery')?.addEventListener('click', function(e) {
                const t = e.target.closest && e.target.closest('.thumbnail');
                if (!t) return;
                const idx = parseInt(t.dataset.index || '0', 10);
                showImageByIndex(isNaN(idx) ? 0 : idx);
            });
            document.addEventListener('keydown', function(e) {
                const activeTag = document.activeElement?.tagName?.toLowerCase();
                if (activeTag === 'input' || activeTag === 'textarea') return;
                if (e.key === 'ArrowLeft') showImageByIndex(currentImageIndex - 1);
                if (e.key === 'ArrowRight') showImageByIndex(currentImageIndex + 1);
                if (e.key === 'Escape') {
                    const modal = document.getElementById('imageModal');
                    if (modal) {
                        modal.classList.remove('active');
                        modal.setAttribute('aria-hidden', 'true');
                        document.body.style.overflow = '';
                    }
                }
            });
            document.getElementById('mainImageWrapper')?.addEventListener('click', function() {
                const src = document.getElementById('mainImage')?.src;
                if (!src) return;
                const modal = document.getElementById('imageModal');
                const img = document.getElementById('modalImage');
                if (!modal || !img) return;
                img.src = src;
                modal.classList.add('active');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
                modal.addEventListener('click', function onModalClick(e) {
                    if (e.target === modal) {
                        modal.removeEventListener('click', onModalClick);
                        modal.classList.remove('active');
                        modal.setAttribute('aria-hidden', 'true');
                        document.body.style.overflow = '';
                        img.src = '';
                    }
                });
            });
        })();

        // ===== BuyChoiceModal handlers (uses dataset on modal for state) =====
        (function() {
            const modal = document.getElementById('buyChoiceModal');
            const closeBtn = document.getElementById('closeChoiceModal');
            const deliveryBtn = document.getElementById('choiceDelivery');
            const pickupBtn = document.getElementById('choicePickup');
            const okBtn = document.getElementById('choiceOk');

            closeBtn?.addEventListener('click', function() {
                if (!modal) return;
                modal.hidden = true;
                delete modal.dataset.pid;
                delete modal.dataset.qty;
                delete modal.dataset.mode;
                okBtn.disabled = true;
            });

            // set mode on modal.dataset.mode and toggle buttons' active state
            deliveryBtn?.addEventListener('click', function() {
                if (!modal) return;
                modal.dataset.mode = 'delivery';
                this.classList.add('is-active');
                pickupBtn?.classList.remove('is-active');
                okBtn.disabled = false;
            });
            pickupBtn?.addEventListener('click', function() {
                if (!modal) return;
                modal.dataset.mode = 'pickup';
                this.classList.add('is-active');
                deliveryBtn?.classList.remove('is-active');
                okBtn.disabled = false;
            });

            okBtn?.addEventListener('click', function() {
                if (!modal) return;
                const pid = modal.dataset.pid || '';
                const qty = modal.dataset.qty || '1';
                const mode = modal.dataset.mode || '';
                if (!pid || !mode) return;
                const url = mode === 'delivery' ? `/RADS-TOOLING/customer/checkout_delivery.php?pid=${encodeURIComponent(pid)}&qty=${encodeURIComponent(qty)}` : `/RADS-TOOLING/customer/checkout_pickup.php?pid=${encodeURIComponent(pid)}&qty=${encodeURIComponent(qty)}`;
                modal.hidden = true;
                // redirect to checkout
                window.location.href = url;
            });

            // close modal with Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal && !modal.hidden) {
                    modal.hidden = true;
                }
            });

            // Defensive: ensure modal hidden on initial load (prevent CSS race)
            document.addEventListener('DOMContentLoaded', function() {
                if (modal) modal.hidden = true;
                // FIX: Ensure body overflow is always reset on page load
                document.body.style.overflow = '';
                // FIX: Ensure image modal is hidden on page load

                const imgModal = document.getElementById('imageModal');

                if (imgModal) {

                    imgModal.classList.remove('active');

                    imgModal.setAttribute('aria-hidden', 'true');

                }
            });
        })();

        // Small toast helper (re-used)
        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.textContent = message;
            toast.style.cssText = 'position:fixed;top:100px;right:20px;background:' + (type === 'success' ? '#3db36b' : '#2f5b88') + ';color:#fff;padding:1rem 1.5rem;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,0.2);z-index:10000;font-weight:600;';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        // Profile dropdown, logout and cart count helpers (similar to products.php)
        (function() {
            const toggle = document.getElementById('profileToggle');
            const dropdown = document.getElementById('profileDropdown');
            toggle?.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropdown?.classList.toggle('show');
            });
            document.addEventListener('click', function(e) {
                if (toggle && dropdown && !toggle.contains(e.target) && !dropdown.contains(e.target)) dropdown.classList.remove('show');
            });

            window.showLogoutModal = function() {
                const m = document.getElementById('logoutModal');
                if (m) m.style.display = 'flex';
            };
            window.closeLogoutModal = function() {
                const m = document.getElementById('logoutModal');
                if (m) m.style.display = 'none';
            };
            window.confirmLogout = function() {
                fetch('/RADS-TOOLING/backend/api/auth.php?action=logout', {
                    method: 'POST',
                    credentials: 'same-origin'
                }).finally(() => {
                    localStorage.removeItem('cart');
                    window.location.href = '/RADS-TOOLING/public/index.php';
                });
            };

            // update cart count from localStorage
            function updateCartCountLocal() {
                const cart = JSON.parse(localStorage.getItem('cart') || '[]');
                const count = cart.reduce((s, i) => s + (i.quantity || 0), 0);
                document.querySelectorAll('#cartCount, #navCartCount, .cart-badge').forEach(b => b.textContent = count);
            }
            window.updateCartCount = updateCartCountLocal;
            document.addEventListener('DOMContentLoaded', updateCartCountLocal);
        })();
    </script>

    <!-- nav + chat widget scripts (existing) -->
    <script src="/RADS-TOOLING/assets/JS/nav_user.js"></script>
    <script src="/RADS-TOOLING/assets/JS/chat_widget.js"></script>
</body>

</html>