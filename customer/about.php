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
// /customer/about.php - Customer About Page (uses same CMS content as public)
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
}

// If in preview, use the content passed by cms_preview.php
// Otherwise, fetch published content for customer view
if ($isPreview) {
    $content = $GLOBALS['cms_preview_content'];
} else {
    $content = getCMSContent('about'); // Gets published, same as public
}

$customerName = htmlspecialchars($user['name'] ?? $user['username']);
$customerId = $user['id'] ?? 0;

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
    <link rel="stylesheet" href="/assets/CSS/about.css">
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
                <a href="/customer/homepage.php" class="nav-menu-item ">Home</a>
                <a href="/customer/about.php" class="nav-menu-item active">About</a>
                <a href="/customer/products.php" class="nav-menu-item">Products</a>
                <a href="/customer/testimonials.php" class="nav-menu-item">Testimonials</a>
            </nav>
        </header>


        <!-- Hero Section -->
        <section class="sp-hero">
            <div class="sp-container">
                <h1><?php echo $content['about_headline'] ?? 'About RADS Tooling'; ?></h1>
                <?php echo $content['about_subheadline'] ?? '<p>Your trusted partner in precision tooling</p>'; ?>
                <div class="sp-hero-buttons">
                    <a href="products.php" class="sp-btn sp-btn-secondary">Browse Products</a>
                </div>
            </div>
        </section>

        <!-- Store Info and Hours Cards -->
        <section class="sp-section">
            <div class="sp-container">
                <div class="sp-cards-grid">
                    <!-- Store Information Card -->
                    <div class="sp-card">
                        <h2 class="sp-card-title">Store Information</h2>
                        <div class="sp-info-list">
                            <div class="sp-info-item">
                                <span class="sp-icon">📍</span>
                                <div>
                                    <p><strong>Address:</strong> <?php echo htmlspecialchars($content['about_address'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                            <div class="sp-info-item">
                                <span class="sp-icon">📞</span>
                                <div>
                                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($content['about_phone'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                            <div class="sp-info-item">
                                <span class="sp-icon">✉️</span>
                                <div>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($content['about_email'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Operating Hours Card -->
                    <div class="sp-card">
                        <h2 class="sp-card-title">Operating Hours</h2>
                        <div class="sp-hours-table">
                            <div class="sp-hours-row">
                                <p><strong>Hours:</strong> <?php echo htmlspecialchars($content['about_hours_weekday'] ?? 'N/A'); ?></p>
                            </div>
                            <div class="sp-hours-row">
                                <p><?php echo htmlspecialchars($content['about_hours_sunday'] ?? 'Sunday: Closed'); ?></p>
                            </div>
                        </div>
                        <div class="sp-notice">
                            <span class="sp-icon">📢</span>
                            <p>Special hours may apply during holidays. Please contact us for confirmation.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Mission & Vision Section -->
        <section class="sp-section sp-section-alt">
            <div class="sp-container">
                <div class="sp-mv-grid">
                    <div class="sp-mv-panel">
                        <div class="sp-mv-icon">🎯</div>
                        <h2>Our Mission</h2>
                        <?php echo $content['about_mission'] ?? '<p>To provide high-quality custom cabinets...</p>'; ?>
                    </div>
                    <div class="sp-mv-panel">
                        <div class="sp-mv-icon">🔮</div>
                        <h2>Our Vision</h2>
                        <?php echo $content['about_vision'] ?? '<p>To be the leading cabinet manufacturer...</p>'; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Company Story Section -->
        <section class="sp-section">
            <div class="sp-container">
                <div class="sp-story">
                    <h2>Our Story</h2>
                    <?php echo $content['about_story'] ?? '<p>Established in 2007...</p>'; ?>
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
                await fetch('/backend/api/auth.php?action=logout', {
                    method: 'POST',
                    credentials: 'same-origin'
                });

                localStorage.removeItem('cart');
                window.location.href = '/index.php';

            } catch (error) {
                console.error('Logout error:', error);
                localStorage.removeItem('cart');
                window.location.href = '/index.php';
            }
        }

        // ========== LOAD PRODUCTS ==========
        /*async function loadRecommendedProducts() {
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
            <img src="/${product.image || 'assets/images/placeholder.jpg'}" 
                 alt="${escapeHtml(product.name)}"
                 onerror="this.src='/assets/images/placeholder.jpg'">
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
        }*/

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
        // Reusable confirm modal (blue header, ghost/cancel + main/ok)
        function showConfirm(opts = {}) {
            const {
                title = 'Confirm',
                    message = 'Are you sure?',
                    okText = 'OK',
                    cancelText = 'Cancel',
                    onConfirm = null,
                    onCancel = null,
                    id = 'confirmModal'
            } = opts;

            // Create modal if not exists
            let modal = document.getElementById(id);
            if (!modal) {
                modal = document.createElement('div');
                modal.id = id;
                modal.className = 'rt-modal';
                modal.innerHTML = `
      <div class="rt-modal__dialog rt-card">
        <div class="rt-header">
          <h3 id="${id}-title"></h3>
          <button type="button" class="rt-close" aria-label="Close" data-close="#${id}">×</button>
        </div>
        <div class="rt-body">
          <p class="rt-sub" id="${id}-msg"></p>
          <div class="rt-actions">
            <button class="rt-btn ghost" data-cancel>Cancel</button>
            <button class="rt-btn main" data-ok>OK</button>
          </div>
        </div>
      </div>`;
                modal.setAttribute('hidden', '');
                document.body.appendChild(modal);
            }

            // Fill content
            modal.querySelector(`#${id}-title`).textContent = title;
            modal.querySelector(`#${id}-msg`).textContent = message;
            modal.querySelector('[data-ok]').textContent = okText;
            modal.querySelector('[data-cancel]').textContent = cancelText;

            // Helpers
            const open = () => {
                modal.removeAttribute('hidden');
                document.body.style.overflow = 'hidden';
            };
            const close = () => {
                modal.setAttribute('hidden', '');
                // unlock scroll if no other open modals
                if (!document.querySelector('.rt-modal:not([hidden])')) document.body.style.overflow = '';
                cleanup();
            };

            const onKey = (e) => {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    handleCancel();
                }
                if (e.key === 'Enter') {
                    e.preventDefault();
                    handleOK();
                }
            };

            const handleOK = async () => {
                try {
                    if (typeof onConfirm === 'function') await onConfirm();
                } finally {
                    close();
                }
            };
            const handleCancel = () => {
                try {
                    if (typeof onCancel === 'function') onCancel();
                } finally {
                    close();
                }
            };

            // Bind events
            const btnOk = modal.querySelector('[data-ok]');
            const btnCancel = modal.querySelector('[data-cancel]');
            const btnClose = modal.querySelector('[data-close]');
            const overlayClick = (e) => {
                if (e.target === modal) handleCancel();
            };

            btnOk.addEventListener('click', handleOK);
            btnCancel.addEventListener('click', handleCancel);
            btnClose.addEventListener('click', handleCancel);
            modal.addEventListener('click', overlayClick);
            document.addEventListener('keydown', onKey);

            function cleanup() {
                btnOk.removeEventListener('click', handleOK);
                btnCancel.removeEventListener('click', handleCancel);
                btnClose.removeEventListener('click', handleCancel);
                modal.removeEventListener('click', overlayClick);
                document.removeEventListener('keydown', onKey);
            }

            // Open it
            open();
        }

        // Your customer-side logout hook
        function setupLogout() {
            const logoutBtn = document.getElementById('logoutBtn');
            if (!logoutBtn) return;

            const newLogoutBtn = logoutBtn.cloneNode(true);
            logoutBtn.parentNode.replaceChild(newLogoutBtn, logoutBtn);

            newLogoutBtn.addEventListener('click', function(e) {
                e.preventDefault();
                showConfirm({
                    title: 'Logout',
                    message: 'Do you really want to log out?',
                    okText: 'Logout',
                    cancelText: 'Cancel',
                    onConfirm: async () => {
                        try {
                            // wipe any local/session flags you use for customer
                            sessionStorage.removeItem('rads_admin_session'); // ok lang iwan kung shared
                            await fetch('/backend/api/auth.php?action=logout', {
                                method: 'POST',
                                credentials: 'same-origin'
                            });
                        } catch (_) {
                            /* ignore network errors */ }
                        location.href = '/index.php';
                    }
                });
            });
        }
    </script>

    <script src="/assets/JS/nav_user.js"></script>

    <script src="/assets/JS/chat_widget.js"></script>
</body>

</html>