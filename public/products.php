<?php
session_start();

/* Dev-only: show errors habang nagwa-wire */
ini_set('display_errors', '1');
error_reporting(E_ALL);

/* ---------- DB loader (tries common repo paths) ---------- */
$pdo = null;
$tried = [];
$try = function ($rel) use (&$pdo, &$tried) {
    $full = __DIR__ . $rel;
    $tried[] = $full;
    if (file_exists($full)) {
        require_once $full;
        if (class_exists('Database')) {
            $pdo = (new Database())->getConnection();
        } elseif (isset($pdo) && $pdo instanceof PDO) { /* ok */
        } elseif (isset($conn) && $conn instanceof PDO) {
            $pdo = $conn;
        }
    }
};
$try('/../backend/config/database.php');
if (!$pdo) $try('/../backend/lib/db.php');
if (!$pdo) {
    http_response_code(500);
    echo "<pre style='font:14px monospace;padding:16px'>Products page cannot connect to DB.\n\nTried:\n- "
        . implode("\n- ", array_map('htmlspecialchars', $tried))
        . "\n\nFix include path at top of products.php.</pre>";
    exit;
}

/* ---------- Search + Categories ---------- */
$q = trim($_GET['q'] ?? '');

$allowedTypes = [
    'All'              => 'All',
    'Kitchen Cabinet'  => 'Kitchen Cabinet',
    'Wardrobe'         => 'Wardrobe',
    'Office Cabinet'   => 'Office Cabinet',
    'Bathroom Cabinet' => 'Bathroom Cabinet',
    'Storage Cabinet'  => 'Storage Cabinet',
];
$alias = [
    'Kitchen'      => 'Kitchen Cabinet',
    'Bedroom'      => 'Wardrobe',
    'Living Room'  => 'Storage Cabinet',
    'Bathroom'     => 'Bathroom Cabinet',
    'Commercial'   => 'Storage Cabinet',
];
$activeType = $_GET['type'] ?? 'All';
if (isset($alias[$activeType])) $activeType = $alias[$activeType];
if (!isset($allowedTypes[$activeType])) $activeType = 'All';

/* ---------- Fetch released-only from Product Management (use actual columns) ----------
   NOTE: do NOT concatenate placeholder with image in SQL (that was a bug).
   Fetch raw image column and normalize in PHP below.
   FIX: Also fetch primary image from product_images table if available
*/
$sql = "
SELECT
  p.id,
  p.name,
  p.description AS short_desc,
  p.price       AS base_price,
  p.image       AS image_raw,
  p.type,
  p.is_customizable,
  (SELECT pi.image_path 
   FROM product_images pi 
   WHERE pi.product_id = p.id AND pi.is_primary = 1 
   LIMIT 1) AS primary_image_path
FROM products p
WHERE p.status='released' AND p.is_archived=0
";
$params = [];
if ($activeType !== 'All') {
    $sql .= " AND type = ?";
    $params[] = $activeType;
}
if ($q !== '') {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $needle = "%$q%";
    array_push($params, $needle, $needle);
}
$sql .= " ORDER BY released_at DESC, id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* helpers */
function peso($n)
{
    return '₱ ' . number_format((float)$n, 2);
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

/**
 * Normalize image URL coming from DB.
 * FIX: Check if file exists and use primary_image_path from product_images table
 * Accepts:
 *  - absolute urls (http/https) -> return as-is
 *  - absolute paths (/...) -> return as-is
 *  - known relative roots -> prefix /RADS-TOOLING/
 *  - filename only -> prefix /RADS-TOOLING/uploads/products/
 *  - empty -> placeholder
 */
function public_img_url($raw, $checkExists = true)
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return '/RADS-TOOLING/uploads/products/placeholder.jpg';
    }

    if (preg_match('~^https?://~i', $raw)) return $raw;
    if ($raw[0] === '/') return $raw;

    $knownRoots = [
        'uploads/products/',
        'assets/uploads/',
        'assets/images/',
        'images/',
        'backend/uploads/',
    ];
    
    $finalPath = '';
    foreach ($knownRoots as $root) {
        if (strpos($raw, $root) === 0) {
            $finalPath = '/RADS-TOOLING/' . ltrim($raw, '/');
            break;
        }
    }
    
    if (!$finalPath) {
        // If it includes uploads/products somewhere, prefix cleanly
        if (stripos($raw, 'uploads/products/') !== false) {
            $finalPath = '/RADS-TOOLING/' . ltrim($raw, '/');
        } else {
            // Otherwise assume it's a filename
            $finalPath = '/RADS-TOOLING/uploads/products/' . ltrim($raw, '/');
        }
    }
    
    // FIX: Check if file actually exists (optional)
    if ($checkExists) {
        $serverPath = $_SERVER['DOCUMENT_ROOT'] . $finalPath;
        if (!file_exists($serverPath)) {
            return '/RADS-TOOLING/uploads/products/placeholder.jpg';
        }
    }
    
    return $finalPath;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>RADS TOOLING - Products</title>

    <!-- Fonts/Icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="/RADS-TOOLING/assets/CSS/Homepage.css" />
    <link rel="stylesheet" href="/RADS-TOOLING/assets/CSS/chat-widget.css" />
    <link rel="stylesheet" href="/RADS-TOOLING/assets/CSS/product.css" />
    <link rel="stylesheet" href="/RADS-TOOLING/assets/CSS/product-ux-enhanced.css">
    <link rel="stylesheet" href="/RADS-TOOLING/assets/CSS/responsive.css">
    <style>
        /* small helper for consistent product img sizing and selector */
        .product-public-img { width:100%; height:180px; object-fit:cover; display:block; border-radius:6px; }
    </style>
</head>

<body>
    <div class="page-wrapper">

        <!-- ====================== HEADER (PUBLIC) ====================== -->
        <header class="navbar navbar--products">
            <div class="navbar-container">
                <div class="navbar-brand">
                    <a href="/RADS-TOOLING/public/index.php" class="logo-link">
                        <span class="logo-text">R</span>ADS <span class="logo-text">T</span>OOLING
                    </a>
                </div>

                <form class="search-container" action="/RADS-TOOLING/public/products.php" method="get">
                    <input type="text" name="q" class="search-input" placeholder="Search cabinets..." value="<?= htmlspecialchars($q) ?>" />
                    <button type="submit" class="search-btn" aria-label="Search">
                        <span class="material-symbols-rounded">search</span>
                    </button>
                </form>

                <div class="navbar-actions">
                    <a href="/RADS-TOOLING/customer/cust_login.php" class="nav-link">
                        <span class="material-symbols-rounded">login</span>
                        <span>Login</span>
                    </a>
                    <a href="/RADS-TOOLING/customer/register.php" class="nav-link">
                        <span class="material-symbols-rounded">person_add</span>
                        <span>Sign Up</span>
                    </a>
                    <a href="/RADS-TOOLING/admin/login.php" class="nav-link-icon" title="Staff Login">
                        <span class="material-symbols-rounded">admin_panel_settings</span>
                    </a>
                </div>
            </div>

            <!-- NAVBAR MENU + CATEGORIES -->
            <nav class="navbar-menu">
                <a href="/RADS-TOOLING/public/index.php" class="nav-menu-item">Home</a>
                <a href="/RADS-TOOLING/public/about.php" class="nav-menu-item">About Us</a>
                <a href="/RADS-TOOLING/public/products.php" class="nav-menu-item active">Products</a>
                <a href="/RADS-TOOLING/public/testimonials.php" class="nav-menu-item ">Testimonials</a>
            </nav>
            <!-- thin divider line between rows -->
            <div class="nav-container"></div>
            <div class="navbar-cats-bar">
                <div class="navbar-container navbar-container--cats">
                    <nav class="navbar-cats">
                        <?php
                        $cat = function ($label) use ($q, $activeType) {
                            $href   = "/RADS-TOOLING/public/products.php?type=" . urlencode($label) . ($q !== '' ? "&q=" . urlencode($q) : "");
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

        <!-- ====================== CONTENT ====================== -->
        <main class="products-wrap">
            <?php if (empty($products)) : ?>
                <div class="rt-empty">No released products found<?= $q !== '' ? " for “" . htmlspecialchars($q) . "”" : '' ?>.</div>
            <?php else : ?>
                <div class="rt-grid" id="publicProductsGrid">
                    <?php foreach ($products as $p):
                        $id    = (int)$p['id'];
                        $name  = $p['name'] ?? 'Untitled';
                        $desc  = $p['short_desc'] ?? ($p['description'] ?? '');
                        // FIX: Use primary_image_path from product_images if available, fallback to product.image
                        $imageToUse = !empty($p['primary_image_path']) ? $p['primary_image_path'] : ($p['image_raw'] ?? '');
                        $img   = public_img_url($imageToUse);
                        $price = (float)$p['base_price'];
                        $isCustom = (int)($p['is_customizable'] ?? 0);
                    ?>
                        <article class="rt-card" data-id="<?= $id ?>" data-name="<?= htmlspecialchars($name) ?>">
                            <div class="rt-imgwrap">
                                <img
                                    class="product-public-img"
                                    src="<?= htmlspecialchars($img) ?>"
                                    alt="<?= htmlspecialchars($name) ?>"
                                    data-product-id="<?= $id ?>"
                                    onerror="this.onerror=null;this.src='/RADS-TOOLING/uploads/products/placeholder.jpg'">

                            </div>


                            <div class="rt-content">
                                <div class="rt-name"><?= htmlspecialchars($name) ?></div>
                                <div class="rt-desc"><?= htmlspecialchars($desc) ?></div>
                                <div class="rt-price">₱ <?= number_format($price, 2) ?></div>

                                <!-- BOTTOM CTA: CART + BUY NOW ONLY -->
                                <div class="rt-cta">
                                    <button class="rt-btn ghost" data-act="cart">Add to Cart</button>
                                    <button class="rt-btn main" data-act="buynow">Buy Now</button>
                                </div>
                            </div>

                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>

        <!-- ===== Login Required (Public) ===== -->
        <div id="rtLoginModal" class="rt-modal" aria-hidden="true">
            <div class="rt-modal__box">
                <button id="rtLoginX" class="rt-modal__x" aria-label="Close">
                    <span class="material-symbols-rounded">close</span>
                </button>

                <div class="material-symbols-rounded rt-modal__icon">lock</div>
                <h3>Please log in</h3>
                <p>Login or create an account to customize, add to cart, or buy.</p>

                <div class="rt-modal__actions">
                    <a class="rt-btn main" href="/RADS-TOOLING/customer/cust_login.php">Login</a>
                    <a class="rt-btn ghost" href="/RADS-TOOLING/customer/register.php">Sign up</a>
                </div>
            </div>
        </div>
        <!-- ====================== FOOTER (PUBLIC) ====================== -->
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
                        <li><a href="/RADS-TOOLING/public/index.php">Home</a></li>
          <li><a href="/RADS-TOOLING/public/about.php">About Us</a></li>
          <li><a href="/RADS-TOOLING/public/products.php">Products</a></li>
          <li><a href="/RADS-TOOLING/public/testimonials.php">Testimonials</a></li>
                        <li><a href="/RADS-TOOLING/customer/register.php">Sign Up</a></li>
                        <li><a href="/RADS-TOOLING/customer/cust_login.php">Login</a></li>
                    </ul>
                </div>

                <!-- Categories -->
                <div class="footer-section">
                    <h3>Categories</h3>
                    <ul class="footer-links">
                        <li><a href="/RADS-TOOLING/public/products.php?type=Kitchen Cabinet">Kitchen Cabinet</a></li>
                        <li><a href="/RADS-TOOLING/public/products.php?type=Wardrobe">Wardrobe</a></li>
                        <li><a href="/RADS-TOOLING/public/products.php?type=Office Cabinet">Office Cabinet</a></li>
                        <li><a href="/RADS-TOOLING/public/products.php?type=Bathroom Cabinet">Bathroom Cabinet</a></li>
                        <li><a href="/RADS-TOOLING/public/products.php?type=Storage Cabinet">Storage Cabinet</a></li>
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
                    <a href="/RADS-TOOLING/public/privacy.php">Privacy Policy</a>
                    <a href="/RADS-TOOLING/public/terms.php">Terms & Conditions</a>
                </div>
            </div>
        </footer>

    </div>
    <!-- RADS-TOOLING Chat Support Widget (Guest Mode) -->
    <button id="rtChatBtn" class="rt-chat-btn">
        <span class="material-symbols-rounded">chat</span>
        Need Help?
    </button>

    <div id="rtChatPopup" class="rt-chat-popup">
        <div class="rt-chat-header">
            <span>Rads Tooling - Chat Support</span>
        </div>

        <!-- Message: Please Login -->
        <div class="rt-chat-messages" style="padding: 40px 20px; text-align: center;">
            <div style="margin-bottom: 20px;">
                <i class="fas fa-lock" style="font-size: 48px; color: #1f4e74; opacity: 0.6;"></i>
            </div>
            <h3 style="color: #333; margin-bottom: 10px;">Chat Support Unavailable</h3>
            <p style="color: #666; margin-bottom: 20px;">
                Please login or create an account to chat with our support team and get instant answers to your questions.
            </p>
            <a href="/RADS-TOOLING/customer/cust_login.php"
                style="display: inline-block; padding: 12px 24px; background: #1f4e74; color: white; text-decoration: none; border-radius: 6px; font-weight: 600;">
                <i class="fas fa-sign-in-alt"></i> Login Now
            </a>
        </div>
    </div>

    <script>
        // Simple toggle for public page (no chat functionality until login)
        document.addEventListener('DOMContentLoaded', function() {
            const chatBtn = document.getElementById('rtChatBtn');
            const chatPopup = document.getElementById('rtChatPopup');
            // Chat close element might not exist; keep safe
            const chatClose = document.getElementById('rtChatClose');

            if (chatBtn && chatPopup) {
                chatBtn.addEventListener('click', function() {
                    chatPopup.style.display = 'flex';
                    chatBtn.style.display = 'none';
                });
            }

            if (chatClose && chatPopup && chatBtn) {
                chatClose.addEventListener('click', function() {
                    chatPopup.style.display = 'none';
                    chatBtn.style.display = 'flex';
                });
            }
        });
    </script><!-- /.page-wrapper -->

    <script>
        (() => {
            // Elements
            const modal = document.getElementById('rtLoginModal');

            const openLogin = () => {
                modal.classList.add('open');
                document.body.style.overflow = 'hidden';
            };
            const closeLogin = () => {
                modal.classList.remove('open');
                document.body.style.overflow = '';
            };

            if (modal) {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) closeLogin(); // click outside box to close
                });
            }

            // PUBLIC PAGE GATE:
            // I-block lahat ng actions (customize/cart/buynow) at ipakita ang login modal.
            document.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-act]');
                if (!btn) return;

                e.preventDefault(); // huwag ituloy ang tunay na action
                e.stopPropagation();
                openLogin(); // show "Login required" modal
            });
        })();
    </script>

    <script>
        (() => {
            const CAN_ORDER = false; // or PHP check mo dito...
            const modal = document.getElementById('rtLoginModal');

            const openLogin = () => {
                modal.classList.add('open');
                document.body.style.overflow = 'hidden';
            };
            const closeLogin = () => {
                modal.classList.remove('open');
                document.body.style.overflow = '';
            };

            // close button
            document.getElementById('rtLoginX')?.addEventListener('click', closeLogin);
            modal?.addEventListener('click', (e) => {
                if (e.target === modal) closeLogin();
            });

            // Gating ng buttons
            document.addEventListener('click', async (e) => {
                const btn = e.target.closest('[data-act]');
                if (!btn) return;

                if (!CAN_ORDER) {
                    e.preventDefault();
                    openLogin();
                    return;
                }

                const card = btn.closest('.rt-card');
                const id = Number(card?.dataset?.id);
                const act = btn.dataset.act;
                if (!id) return;

                if (act === 'customize') {
                    location.href = `/RADS-TOOLING/customer/customize.php?id=${id}`;
                    return;
                }
                if (act === 'cart') {
                    try {
                        await fetch('/RADS-TOOLING/customer/api/cart_add.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                _csrf: '<?= $CSRF ?>',
                                product_id: id,
                                qty: 1
                            })
                        });
                    } catch (_) {}
                    return;
                }
                if (act === 'buynow') {
                    try {
                        await fetch('/RADS-TOOLING/customer/api/checkout_seed.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                _csrf: '<?= $CSRF ?>',
                                product_id: id
                            })
                        });
                    } finally {
                        location.href = '/RADS-TOOLING/customer/checkout.php';
                    }
                }
            });
        })();

       // after render: patch product cards to use product_images primary if available
document.addEventListener('DOMContentLoaded', function() {
  // run after a small delay to ensure DOM/imgs present
  setTimeout(patchProductCardsWithPrimaryImages, 150);
});

async function patchProductCardsWithPrimaryImages() {
  // selector: use the images we added with data-product-id
  const cardImgs = Array.from(document.querySelectorAll('.rt-card img[data-product-id]'));
  if (!cardImgs.length) return;

  const ids = [...new Set(cardImgs.map(i => i.dataset.productId).filter(Boolean))];

  await Promise.all(ids.map(async pid => {
    try {
      const resp = await fetch(`/RADS-TOOLING/backend/api/product_images.php?action=list&product_id=${pid}`, { credentials: 'same-origin' });
      const j = await resp.json().catch(() => ({ success: false }));
      if (!j.success) return;
      const imgs = j.data?.images || j.data || [];
      if (!Array.isArray(imgs) || imgs.length === 0) return;
      const primary = imgs.find(i => Number(i.is_primary) === 1) || imgs[0];
      if (!primary) return;
      const filename = String(primary.image_path || primary.path || primary.filename || '').split('/').pop();
      if (!filename) return;
      const src = `/RADS-TOOLING/uploads/products/${filename}`;

      // update all images for this pid if currently placeholder or different
      cardImgs.forEach(imgEl => {
        if (String(imgEl.dataset.productId) !== String(pid)) return;
        const current = imgEl.getAttribute('src') || '';
        const isPlaceholder = current.endsWith('placeholder.jpg') || current === '';
        if (isPlaceholder || !current.includes(filename)) {
          imgEl.src = src;
        }
      });
    } catch (err) {
      console.error('patch card image error', err);
    }
  }));
}
    </script>
<script src="/RADS-TOOLING/assets/JS/chat_widget.js"></script>
<script src="/RADS-TOOLING/assets/JS/product-drag.js"></script>
</body>
</html>