<?php
// public/testimonials.php
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
// STEP 1: Load config + helper
require_once __DIR__ . '/../backend/config/app.php';
require_once __DIR__ . '/../backend/lib/cms_helper.php';

// STEP 2: Detect CMS preview (cms_preview.php sets this global)
$isPreview = isset($GLOBALS['cms_preview_content']) && !empty($GLOBALS['cms_preview_content']);

// STEP 3: Auth only if NOT preview
if (!$isPreview) {
  require_once __DIR__ . '/../includes/guard.php';
  guard_require_customer();

  $user = $_SESSION['user'] ?? null;
  $isCustomer = $user && (($user['aud'] ?? '') === 'customer');
  if (!$isCustomer) {
    header('Location: /customer/cust_login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
  }
  $customerName = htmlspecialchars($user['name'] ?? $user['username']);
} else {
  $customerName = '{Customer Name}'; // placeholder in preview
}

// STEP 4: Fetch CMS JSON
// CRITICAL: If in preview, use the draft content passed by cms_preview.php
if ($isPreview) {
  $cms = $GLOBALS['cms_preview_content'];
} else {
  $cms = getCMSContent('home_customer'); // Gets published for normal customer view
}

// STEP 5: Map fields with defaults
$welcomeHtml = cmsTokens($cms['welcome_message'] ?? '<h1>Welcome back, {{customer_name}}!</h1>', [
  'customer_name' => $customerName
]);

$introHtml = cmsTokens($cms['intro_text'] ?? '<p>Explore our latest cabinet designs and continue your projects</p>', [
  'customer_name' => $customerName
]);

$heroImage = $cms['hero_image'] ?? '/assets/images/cabinet-hero.jpg';
$ctaPrimaryText = $cms['cta_primary_text'] ?? 'Start Designing';
$ctaSecondaryText = $cms['cta_secondary_text'] ?? 'Browse Products';

// Footer content
$footerDescription = $cms['footer_description'] ?? 'Premium custom cabinet manufacturer serving clients since 2007. Quality craftsmanship, affordable prices, and exceptional service.';
$footerEmail = $cms['footer_email'] ?? 'RadsTooling@gmail.com';
$footerPhone = $cms['footer_phone'] ?? '+63 976 228 4270';
$footerAddress = $cms['footer_address'] ?? 'Green Breeze, Piela, Dasmariñas, Cavite';
$footerHours = $cms['footer_hours'] ?? 'Mon-Sat: 8:00 AM - 5:00 PM';

$img = $_SESSION['user']['profile_image'] ?? '';
if ($img) {
  $avatarHtml = '<img src="/' . htmlspecialchars($img) . '?v=' . time() . '" alt="Avatar" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">';
} else {
  $avatarHtml = strtoupper(substr($customerName, 0, 1));
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Rads Tooling - <?= $customerName ?></title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap">
  <link rel="stylesheet" href="/assets/CSS/Homepage.css" />
  <link rel="stylesheet" href="/assets/CSS/chat-widget.css">
  <link rel="stylesheet" href="/assets/CSS/responsive.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        <form class="search-container" action="/public/products.php" method="get">
          <input type="text" name="q" class="search-input" placeholder="Search cabinets..." />
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
                <span class="profile-name" id="nav-username"><?= $customerName ?></span>
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
        <a href="/customer/homepage.php" class="nav-menu-item active">Home</a>
        <a href="/customer/about.php" class="nav-menu-item">About</a>
        <a href="/customer/products.php" class="nav-menu-item">Products</a>
        <a href="/customer/testimonials.php" class="nav-menu-item">Testimonials</a>
      </nav>
    </header>

    <main>
      <!-- WELCOME SECTION -->
      <section class="hero-section">
        <div class="hero-content-wrapper">
          <div class="hero-text-content">
            <h1>Welcome back, <span class="text-highlight"><?= $customerName ?></span>!</h1>
            <p class="hero-subtitle">Explore our latest cabinet designs and continue your projects</p>
            <div class="hero-actions">
              <a href="/customer/products.php" class="btn-hero-primary">
                <span class="material-symbols-rounded">view_in_ar</span>
                <span><?php echo htmlspecialchars($ctaPrimaryText); ?></span>
              </a>
            </div>
          </div>
          <div class="hero-image-content">
            <?php
            $heroMedia = $cmsContent['hero_image'] ?? '/assets/images/cabinet-hero.jpg';
            $heroPath  = parse_url($heroMedia, PHP_URL_PATH) ?: $heroMedia;
            $ext       = strtolower(pathinfo($heroPath, PATHINFO_EXTENSION));
            $isGLB     = ($ext === 'glb');
            ?>
            <div class="hero-image-content">
              <?php if ($isGLB): ?>
                <div id="hero3d" style="width:100%;max-width:580px;height:460px;border-radius:16px;overflow:hidden;background:#f8fafc"></div>
                <script type="importmap">
                  {
                    "imports": {
                    "three": "/assets/vendor_js/three/three.module.js",
                    "three/addons/": "/assets/vendor_js/three/"
                  }
                }
                </script>
                <script type="module">
                  import * as THREE from 'three';
                  import {
                    OrbitControls
                  } from 'three/addons/controls/OrbitControls.js';
                  import {
                    GLTFLoader
                  } from 'three/addons/loaders/GLTFLoader.js';

                  const MODEL_URL = <?= json_encode($heroMedia) ?>;
                  const wrap = document.getElementById('hero3d');

                  const scene = new THREE.Scene();
                  scene.background = new THREE.Color(0xffffff);

                  const renderer = new THREE.WebGLRenderer({
                    antialias: true
                  });
                  renderer.setPixelRatio(Math.min(2, window.devicePixelRatio || 1));
                  renderer.outputColorSpace = THREE.SRGBColorSpace;
                  wrap.appendChild(renderer.domElement);

                  const camera = new THREE.PerspectiveCamera(38, 1, 0.1, 100);
                  camera.position.set(3, 2, 3);

                  const controls = new OrbitControls(camera, renderer.domElement);
                  controls.enableZoom = false;
                  controls.enablePan = false;
                  controls.autoRotate = true;
                  controls.autoRotateSpeed = 0.6;

                  scene.add(new THREE.HemisphereLight(0xffffff, 0xb0b0b0, 0.9));
                  const dir = new THREE.DirectionalLight(0xffffff, 0.8);
                  dir.position.set(5, 5, 5);
                  scene.add(dir);

                  const loader = new GLTFLoader();
                  console.log('[HERO3D] Loading:', MODEL_URL);

                  loader.load(
                    MODEL_URL,
                    (gltf) => {
                      const model = gltf.scene;
                      const box = new THREE.Box3().setFromObject(model);
                      const size = box.getSize(new THREE.Vector3()).length();
                      const center = box.getCenter(new THREE.Vector3());
                      model.position.sub(center);
                      model.scale.multiplyScalar(2.2 / size);
                      scene.add(model);
                      console.log('[HERO3D] Loaded OK');
                    },
                    undefined,
                    (err) => {
                      console.error('[HERO3D] Load failed:', err);
                      wrap.innerHTML = '<div style="padding:16px;color:#ef4444;background:#fff1f2;border-radius:12px">Failed to load 3D model. Check path/MIME.</div>';
                    }
                  );

                  function resize() {
                    const w = wrap.clientWidth,
                      h = wrap.clientHeight || 460; // guard
                    renderer.setSize(w, h, false);
                    camera.aspect = w / h;
                    camera.updateProjectionMatrix();
                  }
                  resize();
                  window.addEventListener('resize', resize);

                  (function animate() {
                    requestAnimationFrame(animate);
                    controls.update();
                    renderer.render(scene, camera);
                  })();
                </script>
              <?php else: ?>
                <img src="<?= htmlspecialchars($heroMedia) ?>" alt="Custom Cabinets">
              <?php endif; ?>
            </div>
          </div>
        </div>
      </section>

      <!-- QUICK ACTIONS -->
      <section class="quick-actions-section">
        <h2 class="section-title">Quick Actions</h2>
        <div class="actions-grid">
          <a href="/customer/customization.php" class="action-card">
            <div class="action-icon">
              <span class="material-symbols-rounded">view_in_ar</span>
            </div>
            <h3>Design Cabinet</h3>
            <p>Create custom 3D designs</p>
          </a>

          <a href="/customer/testimonials.php" class="action-card">
            <div class="action-icon">
              <span class="material-symbols-rounded">storefront</span>
            </div>
            <h3>Browse Gallery</h3>
            <p>Explore our collection</p>
          </a>

          <a href="/customer/orders.php" class="action-card">
            <div class="action-icon">
              <span class="material-symbols-rounded">local_shipping</span>
            </div>
            <h3>Track Orders</h3>
            <p>View order status</p>
          </a>

          <a href="/customer/cart.php" class="action-card">
            <div class="action-icon">
              <span class="material-symbols-rounded">shopping_cart</span>
            </div>
            <h3>View Cart</h3>
            <p>Review checkout items</p>
          </a>
        </div>
      </section>



      <!-- RADS-TOOLING Chat Support Widget -->
      <button id="rtChatBtn" class="rt-chat-btn">
        <span class="material-symbols-rounded">chat</span>
        Need Help?
      </button>

      <div id="rtChatPopup" class="rt-chat-popup">
        <div class="rt-chat-header">
          <span>Rads Tooling - Chat Support</span>
          <button id="rtClearChat" class="rt-clear-btn" type="button" title="Clear chat">
            <span class="material-symbols-rounded">delete</span>
          </button>
        </div>

        <!-- FAQ Container (Sticky at top) -->
        <div class="rt-faq-container" id="rtFaqContainer">
          <div class="rt-faq-toggle" id="rtFaqToggle">
            <span>Quick FAQs</span>
            <span class="rt-faq-icon">▼</span>
          </div>
          <div class="rt-faq-dropdown" id="rtFaqDropdown">
            <!-- FAQ chips will be injected here by chat_widget.js -->
            <div style="padding: 12px; color: #999; font-size: 13px;">Loading FAQs...</div>
          </div>
        </div>

        <!-- Messages Area -->
        <div id="rtChatMessages" class="rt-chat-messages"></div>

        <!-- Input Area -->
        <div class="rt-chat-input">
          <input id="rtChatInput" type="text" placeholder="Type your message…" />
          <button id="rtChatSend" class="rt-chat-send">
            <span class="material-symbols-rounded">send</span>
          </button>
        </div>
      </div>

      <!-- LOGOUT MODAL (Admin Style) -->
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
    </main>
    <!-- ========== CABINETS GALLERY CAROUSEL ========== -->
    <section class="cabinets-section">
      <div class="section-header">
        <h2>Featured <span class="highlight">Cabinets</span></h2>
        <p>Explore our collection of premium custom cabinets</p>
      </div>

      <?php
      // Small helper so relative names work
      if (!function_exists('rt_url')) {
        function rt_url($raw)
        {
          $raw = trim((string)$raw);
          if ($raw === '') return '/uploads';
          if (preg_match('~^https?://~i', $raw)) return $raw;   // absolute
          if ($raw[0] === '/') return $raw;                     // site-absolute
          // otherwise treat as a file under /assets/images/
          return '/assets/images/' . $raw;
        }
      }

      // 1) from CMS (correct var = $cms)
      $imgs = $cms['carousel_images'] ?? [];

      // 2) fallback if CMS has nothing
      if (!$imgs) {
        $imgs = [
          ['image' => 'cab1.jpg', 'title' => 'Bathroom Vanity',  'description' => 'Water-resistant premium materials'],
          ['image' => 'cab2.jpg', 'title' => 'Living Room Display', 'description' => 'Custom shelving with clean lines'],
          ['image' => 'cab3.jpg', 'title' => 'Modern Kitchen',   'description' => 'Contemporary design with premium finishes'],
          ['image' => 'cab4.jpg', 'title' => 'Office Storage',   'description' => 'Professional workspace solutions'],
        ];
      }
      ?>

      <div class="carousel-container">
        <button class="carousel-btn prev" type="button" aria-label="Previous">
          <span class="material-symbols-rounded">chevron_left</span>
        </button>

        <div class="carousel-track">
          <?php foreach ($imgs as $img):
            $src  = rt_url($img['image'] ?? '');
            $ttl  = $img['title'] ?? '';
            $desc = $img['description'] ?? '';
          ?>
            <div class="carousel-item">
              <img src="<?= htmlspecialchars($src) ?>"
                alt="<?= htmlspecialchars($ttl) ?>"
                onerror="this.onerror=null;this.src='/uploads'">
              <div class="carousel-caption">
                <h4><?= htmlspecialchars($ttl) ?></h4>
                <p><?= htmlspecialchars($desc) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <button class="carousel-btn next" type="button" aria-label="Next">
          <span class="material-symbols-rounded">chevron_right</span>
        </button>
      </div>

      <div class="carousel-dots"></div>
    </section>




    <!-- FOOTER -->
    <footer class="footer">
      <div class="footer-content">
        <!-- About Section -->
        <div class="footer-section">
          <h3><?php echo $cmsContent['footer_company_name'] ?? 'About RADS TOOLING'; ?></h3>
          <p class="footer-description">
            <?php echo $cmsContent['footer_description'] ?? 'Premium custom cabinet manufacturer serving clients since 2007.
            Quality craftsmanship, affordable prices, and exceptional service.'; ?>
          </p>
          <div class="footer-social">
            <a href="#" class="social-icon" aria-label="Facebook">
              <span class="material-symbols-rounded">facebook</span>
            </a>
            <a href="#" class="social-icon" aria-label="Instagram">
              <span class="material-symbols-rounded">photo_camera</span>
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
            <li><a href="/customer/products.php?type=Bathroom">Bathroom</a></li>
            <li><a href="/customer/products.php?type=Storage Cabinet">Storage Cabinet</a></li>
          </ul>
        </div>

        <!-- Contact Info -->
        <div class="footer-section">
          <h3>Contact Info</h3>
          <div class="contact-info-item">
            <span class="material-symbols-rounded">location_on</span>
            <span><?php echo $cmsContent['footer_address'] ?? 'Green Breeze, Piela, Dasmariñas, Cavite'; ?></span>
          </div>
          <div class="contact-info-item">
            <span class="material-symbols-rounded">mail</span>
            <a href="mailto:<?php echo $cmsContent['footer_email'] ?? 'RadsTooling@gmail.com'; ?>">
              <?php echo $cmsContent['footer_email'] ?? 'RadsTooling@gmail.com'; ?>
            </a>
          </div>
          <div class="contact-info-item">
            <span class="material-symbols-rounded">phone</span>
            <span><?php echo $cmsContent['footer_phone'] ?? '+63 976 228 4270'; ?></span>
          </div>
          <div class="contact-info-item">
            <span class="material-symbols-rounded">schedule</span>
            <span><?php echo $cmsContent['footer_hours'] ?? 'Mon-Sat: 8:00 AM - 5:00 PM'; ?></span>
          </div>
        </div>
      </div>

      <div class="footer-bottom">
        <p class="footer-copyright">
          <?php echo $cmsContent['footer_copyright'] ?? '© 2025 RADS TOOLING INC. All rights reserved.'; ?>
        </p>
        <div class="footer-legal">
          <a href="/customer/privacy.php">Privacy Policy</a>
          <a href="/customer/terms.php">Terms & Conditions</a>
        </div>
      </div>
    </footer>

  </div><!-- /.page-wrapper -->

  <script>
    // ========== INITIALIZE ON PAGE LOAD ==========
    document.addEventListener('DOMContentLoaded', function() {
      initProfileDropdown();
      //loadUserStatistics();
      //loadRecentOrders();
      //loadRecommendedProducts();
      updateCartCount();
    });

    // ========== PROFILE DROPDOWN TOGGLE ==========
    function initProfileDropdown() {
      const profileToggle = document.getElementById('profileToggle');
      const profileDropdown = document.getElementById('profileDropdown');

      if (!profileToggle || !profileDropdown) {
        console.error('Profile elements not found');
        return;
      }

      profileToggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        profileDropdown.classList.toggle('show');
      });

      document.addEventListener('click', function(e) {
        if (!profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) {
          profileDropdown.classList.remove('show');
        }
      });

      profileDropdown.querySelectorAll('a, button').forEach(item => {
        item.addEventListener('click', function() {
          profileDropdown.classList.remove('show');
        });
      });
    }

    // ========== LOAD USER STATISTICS ==========
    /*async function loadUserStatistics() {
      try {
        const response = await fetch('/backend/api/customer_stats.php', {
          credentials: 'same-origin'
        });
        const data = await response.json();

        if (data.success) {
          document.getElementById('totalOrders').textContent = data.stats.total || 0;
          document.getElementById('pendingOrders').textContent = data.stats.pending || 0;
          document.getElementById('completedOrders').textContent = data.stats.completed || 0;
        } else {
          document.getElementById('totalOrders').textContent = '0';
          document.getElementById('pendingOrders').textContent = '0';
          document.getElementById('completedOrders').textContent = '0';
        }
      } catch (err) {
        console.error('Failed to load stats:', err);
        document.getElementById('totalOrders').textContent = '0';
        document.getElementById('pendingOrders').textContent = '0';
        document.getElementById('completedOrders').textContent = '0';
      }
    }*/

    // ========== LOAD RECENT ORDERS ==========
    /*async function loadRecentOrders() {
      const ordersContainer = document.getElementById('recentOrdersContainer');
      if (!ordersContainer) return;

      try {
        const response = await fetch('/backend/api/recent_orders.php?limit=3', {
          credentials: 'same-origin'
        });
        const data = await response.json();

        if (data.success && data.orders.length > 0) {
          ordersContainer.innerHTML = data.orders.map(order => `
        <div class="order-item">
          <div class="order-info">
            <h4>Order #${escapeHtml(order.order_code)}</h4>
            <p>${escapeHtml(order.product_name || 'Custom Cabinet')} - ₱${parseFloat(order.total_amount).toLocaleString()}</p>
            <p style="font-size:0.85rem;color:#999;">${formatDate(order.order_date)}</p>
          </div>
          <span class="order-status ${order.status.toLowerCase().replace(' ', '-')}">
            ${escapeHtml(order.status)}
          </span>
        </div>
      `).join('');
        } else {
          ordersContainer.innerHTML = '<p style="text-align:center;color:#666;padding:40px;">No orders yet. <a href="/customer/customize.php" style="color:#1f4e74;font-weight:600;">Start designing</a>!</p>';
        }
      } catch {
        ordersContainer.innerHTML = '<p style="text-align:center;color:#dc3545;padding:40px;">Failed to load orders</p>';
      }
    }*/

    // ========== LOGOUT MODAL ==========
    function showLogoutModal() {
      const modal = document.getElementById('logoutModal');
      if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
      }
      const dropdown = document.getElementById('profileDropdown');
      if (dropdown) dropdown.classList.remove('show');
    }

    function closeLogoutModal() {
      const modal = document.getElementById('logoutModal');
      if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
      }
    }

    async function confirmLogout() {
      try {
        // Stop chat polling and clear active chat UI only
        document.dispatchEvent(new Event('customer_logout'));

        // Sync cart to database before logout (safety measure)
        try {
          const cart = JSON.parse(localStorage.getItem('cart') || '[]');
          if (cart.length > 0) {
            await fetch('/backend/api/cart.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              credentials: 'same-origin',
              body: JSON.stringify({ cart })
            });
            console.log('✅ Cart synced to database before logout');
          }
        } catch (syncErr) {
          console.warn('⚠️ Cart sync before logout failed:', syncErr);
        }

        await fetch('/backend/api/auth.php?action=logout', {
          method: 'POST',
          credentials: 'same-origin'
        });

        // Clear ONLY session-related data, NOT cart
        sessionStorage.clear(); //clears temporary UI state only

        // IMPORTANT: Do NOT clear localStorage cart - it will reload from database on next login

        window.location.href = '/index.php';

      } catch (error) {
        console.error('Logout error:', error);
        sessionStorage.clear();
        window.location.href = '/index.php';
      }
    }

    // ========== LOAD PRODUCTS ==========
    async function loadRecommendedProducts() {
      const productsContainer = document.getElementById('recommendedProducts');
      if (!productsContainer) return;

      try {
        const response = await fetch('/backend/api/products.php?action=list&limit=4', {
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json'
          }
        });

        const result = await response.json();

        if (result.success && result.data && result.data.products && result.data.products.length > 0) {
          productsContainer.innerHTML = result.data.products.map(product => `
        <a href="/public/product_detail.php?id=${product.id}" class="product-card">
          <div class="product-image">
            <img src="/${product.image || 'uploads'}" 
                 alt="${escapeHtml(product.name)}"
                 onerror="this.src='/uploads/placeholder.jpg'">
          </div>
          <div class="product-info">
            <h3>${escapeHtml(product.name)}</h3>
            <p class="product-type">${escapeHtml(product.type || 'Cabinet')}</p>
            <p class="product-price">₱${parseFloat(product.price || 0).toLocaleString()}</p>
          </div>
        </a>
      `).join('');
        } else {
          productsContainer.innerHTML = '<div class="loading-state">No products available</div>';
        }
      } catch (error) {
        console.error('Failed to load products:', error);
        productsContainer.innerHTML = '<div class="loading-state">Failed to load products</div>';
      }
    }

    // ========== CART COUNT ==========
    function updateCartCount() {
      const cart = JSON.parse(localStorage.getItem('cart') || '[]');
      const cartCount = document.getElementById('cartCount');
      if (cartCount) {
        cartCount.textContent = cart.length;
      }
    }

    // ========== UTILITY FUNCTIONS ==========
    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    function formatDate(dateStr) {
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    }
  </script>

  <script>
    (() => {
      const track = document.querySelector('.carousel-track');
      const items0 = [...document.querySelectorAll('.carousel-item')];
      const nextBtn = document.querySelector('.carousel-btn.next');
      const prevBtn = document.querySelector('.carousel-btn.prev');
      const dotsBox = document.querySelector('.carousel-dots');

      if (!track || items0.length === 0) return;

      // --- sizing helpers ---
      const stepWidth = () => (items0[1] ? items0[1].offsetLeft - items0[0].offsetLeft :
        items0[0].offsetWidth + 20);
      const perView = Math.max(1, Math.round(track.parentElement.offsetWidth / stepWidth()));

      // --- clone edges for seamless loop ---
      for (let i = 0; i < perView; i++) {
        const head = items0[i].cloneNode(true);
        const tail = items0[items0.length - 1 - i].cloneNode(true);
        head.classList.add('is-clone');
        tail.classList.add('is-clone');
        track.appendChild(head); // first N -> end
        track.insertBefore(tail, track.firstChild); // last N  -> start
      }

      const origCount = items0.length;
      let index = perView; // start at first real slide
      let isAnimating = false,
        tEndTimer;

      // dots  👉 add 1 extra dot
      dotsBox.innerHTML = '';
      const EXTRA_DOTS = 1; // gawing 0 kung ayaw mo ng dagdag
      const dots = [];
      const totalDots = origCount + EXTRA_DOTS;

      for (let i = 0; i < totalDots; i++) {
        const d = document.createElement('span');
        d.className = 'dot' + (i === 0 ? ' active' : '');
        // kapag extra dot (lampas sa origCount), ituro sa last real slide
        const targetIndex = i >= origCount ? (perView + origCount - 1) : (perView + i);
        d.addEventListener('click', () => goTo(targetIndex, true, true));
        dotsBox.appendChild(d);
        dots.push(d);
      }

      const setTransform = (withTransition) => {
        track.style.transition = withTransition ? 'transform .45s ease' : 'none';
        track.style.transform = `translateX(-${index * stepWidth()}px)`;

        // real index (0..origCount-1)
        const real = ((index - perView) % origCount + origCount) % origCount;
        // kung nasa last slide at may extra dot, i-activate yung huling dot
        const active = (real === origCount - 1 && dots.length > origCount) ?
          (dots.length - 1) :
          real;

        dots.forEach((d, i) => d.classList.toggle('active', i === active));
      };


      const goTo = (i, withTransition = true, resetAuto = false) => {
        index = i;
        setTransform(withTransition);
        if (withTransition) watchTransition();
        if (resetAuto) restartAuto();
      };

      const watchTransition = () => {
        clearTimeout(tEndTimer);
        tEndTimer = setTimeout(onTransitionEnd, 600); // fallback if transitionend doesn’t fire
        isAnimating = true;
        toggleBtns(true);
      };

      const onTransitionEnd = () => {
        // snap back from clones (no transition so walang “jump”)
        if (index >= origCount + perView) index = perView;
        if (index < perView) index = origCount + perView - 1;
        setTransform(false);
        // re-enable clicks
        isAnimating = false;
        toggleBtns(false);
      };

      const step = (dir) => {
        if (isAnimating) return;
        goTo(index + dir, true, true);
      };

      const toggleBtns = (lock) => {
        [nextBtn, prevBtn].forEach(b => {
          if (!b) return;
          b.disabled = lock;
        });
      };

      track.addEventListener('transitionend', (e) => {
        if (e.propertyName === 'transform') onTransitionEnd();
      });

      nextBtn?.addEventListener('click', () => step(1));
      prevBtn?.addEventListener('click', () => step(-1));

      // autoplay
      let auto;
      const startAuto = () => auto = setInterval(() => step(1), 3500);
      const stopAuto = () => clearInterval(auto);
      const restartAuto = () => {
        stopAuto();
        startAuto();
      };

      track.parentElement.addEventListener('mouseenter', stopAuto);
      track.parentElement.addEventListener('mouseleave', startAuto);

      // init
      setTransform(false);
      startAuto();

      // keep position on resize
      window.addEventListener('resize', () => setTransform(false));
    })();
  </script>
  <script src="/assets/JS/nav_user.js"></script>
  <script src="/assets/JS/chat_widget.js"></script>
</body>

</html>