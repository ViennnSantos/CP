<?php
// /public/about.php - Public About Page
require_once __DIR__ . '/../backend/config/app.php';
require_once __DIR__ . '/../backend/lib/cms_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CRITICAL: Check if we're in preview mode
$isPreview = isset($GLOBALS['cms_preview_content']) && !empty($GLOBALS['cms_preview_content']);

// If in preview, use the content passed by cms_preview.php
// Otherwise, fetch published content for public view
if ($isPreview) {
    $content = $GLOBALS['cms_preview_content'];
} else {
    $content = getCMSContent('about'); // Gets published by default
}

$user = $_SESSION['user'] ?? null;
$isCustomer = $user && (($user['aud'] ?? '') === 'customer');

// If customer is logged in, redirect to customer view
if ($isCustomer) {
    header('Location: /RADS-TOOLING/customer/about.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>RADS TOOLING - Custom Cabinet Solutions</title>
    <link rel="stylesheet" href="/RADS-TOOLING/assets/CSS/Homepage.css" />
    <link rel="stylesheet" href="/RADS-TOOLING/assets/CSS/about.css">
    <link rel="stylesheet" href="/RADS-TOOLING/assets/CSS/chat-widget.css">
    <link rel="stylesheet" href="/RADS-TOOLING/assets/CSS/responsive.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap">
</head>

<body>
    <div class="page-wrapper">

        <!-- HEADER -->
        <header class="navbar">
            <div class="navbar-container">
                <div class="navbar-brand">
                    <a href="/RADS-TOOLING/public/index.php" class="logo-link">
                        <span class="logo-text">R</span>ADS <span class="logo-text">T</span>OOLING
                    </a>
                </div>

                <form class="search-container" action="/RADS-TOOLING/public/products.php" method="get">
                    <input type="text" name="q" class="search-input" placeholder="Search cabinets..." />
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

            <nav class="navbar-menu">
                <a href="/RADS-TOOLING/public/index.php" class="nav-menu-item ">Home</a>
                <a href="/RADS-TOOLING/public/about.php" class="nav-menu-item active ">About Us</a>
                <a href="/RADS-TOOLING/public/products.php" class="nav-menu-item">Products</a>
                <a href="/RADS-TOOLING/public/testimonials.php" class="nav-menu-item">Testimonials</a>
            </nav>
        </header>


        <!-- Hero Section -->
        <section class="sp-hero">
            <div class="sp-container">
                <h1><?php echo strip_tags($content['about_headline'] ?? 'About RADS Tooling'); ?></h1>
                <?php echo $content['about_subheadline'] ?? '<p>Your trusted partner</p>'; ?>
                <div class="sp-hero-buttons">
                    <a href="/RADS-TOOLING/customer/register.php" class="sp-btn sp-btn-primary">Get Started</a>
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
                                    <strong>Location</strong>
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
                        <?php echo $content['about_mission'] ?? '<p>Loading...</p>'; ?>
                    </div>
                    <div class="sp-mv-panel">
                        <div class="sp-mv-icon">🔮</div>
                        <h2>Our Vision</h2>
                        <?php echo $content['about_vision'] ?? '<p>Loading...</p>'; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Company Story Section -->
        <section class="sp-section">
            <div class="sp-container">
                <div class="sp-story">
                    <h2>Our Story</h2>
                    <?php echo $content['about_story'] ?? '<p>Loading...</p>'; ?>
                </div>
            </div>
        </section>

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
                    Please login to chat with our support team and get instant answers to your questions.
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
                const chatClose = document.getElementById('rtChatClose');

                if (chatBtn && chatPopup && chatClose) {
                    chatBtn.addEventListener('click', function() {
                        chatPopup.style.display = 'flex';
                        chatBtn.style.display = 'none';
                    });

                    chatClose.addEventListener('click', function() {
                        chatPopup.style.display = 'none';
                        chatBtn.style.display = 'flex';
                    });
                }
            });
        </script>

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
                        <li><a href="/RADS-TOOLING/public/products.php?type=Kitchen">Kitchen Cabinet</a></li>
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
        <script src="/RADS-TOOLING/assets/JS/chat_widget.js"></script>
</body>

</html>