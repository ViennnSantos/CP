<?php
// public/testimonials.php - Fixed version
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
        // Get stats - check if deleted column exists first
        $colCheck = $pdo->query("SHOW COLUMNS FROM feedback LIKE 'deleted'")->fetch();
        $deletedFilter = $colCheck ? " AND deleted = 0" : "";
        
        $statsQuery = $pdo->query("
            SELECT 
                COUNT(*) AS cnt, 
                COALESCE(ROUND(AVG(rating), 1), 0) AS avg_rating
            FROM feedback
            WHERE is_released = 1 {$deletedFilter}
        ");
        $statsRow = $statsQuery->fetch(PDO::FETCH_ASSOC);
        $stats['count'] = (int)($statsRow['cnt'] ?? 0);
        $stats['avg'] = (float)($statsRow['avg_rating'] ?? 0);

        // ✅ FIXED: Simpler query without product details for public page
        $stmt = $pdo->prepare("
            SELECT 
                f.id as feedback_id,
                f.rating,
                f.comment,
                f.created_at,
                f.customer_id,
                COALESCE(c.full_name, 'Customer') AS customer_name
            FROM feedback f
            INNER JOIN customers c ON c.id = f.customer_id
            WHERE f.is_released = 1 {$deletedFilter}
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

$user = $_SESSION['user'] ?? null;
$isCustomer = $user && (($user['aud'] ?? '') === 'customer');

// If customer is logged in, redirect to customer view
if ($isCustomer) {
    header('Location: /customer/testimonials.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>RADS TOOLING - Customer Testimonials</title>
    <link rel="stylesheet" href="/assets/CSS/Homepage.css" />
    <link rel="stylesheet" href="/assets/CSS/about.css">
    <link rel="stylesheet" href="/assets/CSS/chat-widget.css">
    <link rel="stylesheet" href="/assets/CSS/responsive.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap">
     <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(to bottom, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            color: #2c3e50;
        }

        /* Header matching homepage */
        .top-header {
            background: linear-gradient(135deg, #4a6fa5 0%, #6b8cce 100%);
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            text-decoration: none;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            color: #fff;
            text-decoration: none;
            padding: .6rem 1.2rem;
            background: rgba(255,255,255,.15);
            border-radius: 8px;
            transition: all .2s ease;
            font-weight: 500;
            border: 1px solid rgba(255,255,255,.3);
        }

        .back-link:hover {
            background: rgba(255,255,255,.25);
            transform: translateX(-3px);
        }

        /* Main content */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-header h1 {
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: .5rem;
            font-weight: 700;
        }

        .page-header p {
            font-size: 1.1rem;
            color: #6c757d;
        }

        .summary-bar {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            align-items: center;
            margin-bottom: 3rem;
            flex-wrap: wrap;
        }

        .summary-chip {
            display: inline-flex;
            align-items: center;
            gap: .6rem;
            background: #fff;
            padding: .8rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            color: #4a6fa5;
            box-shadow: 0 4px 15px rgba(0,0,0,.08);
            border: 2px solid #e9ecef;
        }

        .summary-chip .material-symbols-rounded {
            font-size: 22px;
            color: #fbbf24;
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 2rem;
        }

        .testimonial-card {
            background: #fff;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
            transition: all .3s ease;
            position: relative;
            border: 1px solid #e9ecef;
        }

        .testimonial-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 30px rgba(74,111,165,.2);
            border-color: #4a6fa5;
        }

        .testimonial-card::before {
            content: '"';
            position: absolute;
            top: -5px;
            left: 15px;
            font-size: 80px;
            color: rgba(74,111,165,.08);
            font-family: Georgia, serif;
            line-height: 1;
        }

        .rating {
            display: flex;
            gap: .3rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .star {
            color: #fbbf24;
            font-size: 1.5rem;
            line-height: 1;
        }

        .star.empty {
            color: #dee2e6;
        }

        .comment {
            color: #495057;
            line-height: 1.7;
            margin: 1rem 0 1.5rem;
            font-style: italic;
            white-space: pre-wrap;
            position: relative;
            z-index: 1;
            font-size: .95rem;
        }

        .customer-info {
            display: flex;
            align-items: center;
            gap: .8rem;
            padding-top: 1rem;
            border-top: 2px solid #f1f3f5;
        }

        .avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #4a6fa5, #6b8cce);
            color: #fff;
            font-weight: 700;
            font-size: 1.05rem;
            box-shadow: 0 2px 8px rgba(74,111,165,.3);
        }

        .customer-details {
            flex: 1;
        }

        .customer-name {
            font-weight: 600;
            color: #4a6fa5;
            display: block;
            margin-bottom: 2px;
            font-size: .95rem;
        }

        .customer-date {
            font-size: .85rem;
            color: #868e96;
        }

        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
        }

        .empty-state .icon {
            font-size: 5rem;
            color: #4a6fa5;
            margin-bottom: 1.5rem;
            opacity: .5;
        }

        .empty-state h2 {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: .5rem;
        }

        .empty-state p {
            font-size: 1.1rem;
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .container {
                padding: 2rem 1rem;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .testimonials-grid {
                grid-template-columns: 1fr;
            }
            
            .summary-bar {
                flex-direction: column;
                gap: 1rem;
            }

            .header-content {
                padding: 0 1rem;
            }
        }
    </style>
<body>
    <!-- HEADER -->
    <header class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
                <a href="/index.php" class="logo-link">
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
                <a href="/customer/cust_login.php" class="nav-link">
                    <span class="material-symbols-rounded">login</span>
                    <span>Login</span>
                </a>
                <a href="/customer/register.php" class="nav-link">
                    <span class="material-symbols-rounded">person_add</span>
                    <span>Sign Up</span>
                </a>
                <a href="/admin/login.php" class="nav-link-icon" title="Staff Login">
                    <span class="material-symbols-rounded">admin_panel_settings</span>
                </a>
            </div>
        </div>

        <nav class="navbar-menu">
            <a href="/index.php" class="nav-menu-item">Home</a>
            <a href="/public/about.php" class="nav-menu-item">About Us</a>
            <a href="/public/products.php" class="nav-menu-item">Products</a>
            <a href="/public/testimonials.php" class="nav-menu-item active">Testimonials</a>
        </nav>
    </header>

    <div class="container">
        <div class="page-header">
            <h1>Customer Testimonials</h1>
            <p>See what our satisfied customers are saying</p>
        </div>

        <div class="summary-bar">
            <span class="summary-chip">
                <span class="material-symbols-rounded">star</span>
                Average: <?php echo number_format($stats['avg'], 1); ?> / 5
            </span>
            <span class="summary-chip">
                <span class="material-symbols-rounded">reviews</span>
                <?php echo number_format($stats['count']); ?> verified <?php echo $stats['count'] == 1 ? 'review' : 'reviews'; ?>
            </span>
        </div>

        <?php if (empty($testimonials)): ?>
            <div class="empty-state">
                <div class="icon">
                    <span class="material-symbols-rounded">sentiment_satisfied</span>
                </div>
                <h2>No testimonials yet</h2>
                <p>Be the first to share your experience with us!</p>
            </div>
        <?php else: ?>
            <div class="testimonials-grid">
                <?php foreach ($testimonials as $t): 
                    $name = trim($t['customer_name'] ?? 'Customer');
                    $initials = '';
                    if ($name !== '' && $name !== 'Customer') {
                        $parts = preg_split('/\s+/', $name);
                        $initials = strtoupper(
                            mb_substr($parts[0], 0, 1) . 
                            (isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '')
                        );
                    } else {
                        $initials = 'C';
                    }
                    $rating = (int)($t['rating'] ?? 0);
                    $comment = trim((string)($t['comment'] ?? ''));
                    ?>
                    <div class="testimonial-card">
                        <div class="rating" aria-label="<?php echo $rating; ?> out of 5 stars">
                            <?php for ($i=1; $i<=5; $i++): ?>
                                <span class="star <?php echo $i <= $rating ? '' : 'empty'; ?>">★</span>
                            <?php endfor; ?>
                        </div>

                        <?php if ($comment !== ''): ?>
                            <div class="comment">
                                "<?php echo nl2br(e($comment)); ?>"
                            </div>
                        <?php endif; ?>

                        <div class="customer-info">
                            <span class="avatar" aria-hidden="true">
                                <?php echo e($initials); ?>
                            </span>
                            <div class="customer-details">
                                <span class="customer-name"><?php echo e($name); ?></span>
                                <span class="customer-date"><?php echo safeDate($t['created_at'] ?? null); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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

        <div class="rt-chat-messages" style="padding: 40px 20px; text-align: center;">
            <div style="margin-bottom: 20px;">
                <i class="fas fa-lock" style="font-size: 48px; color: #1f4e74; opacity: 0.6;"></i>
            </div>
            <h3 style="color: #333; margin-bottom: 10px;">Chat Support Unavailable</h3>
            <p style="color: #666; margin-bottom: 20px;">
                Please login to chat with our support team and get instant answers to your questions.
            </p>
            <a href="/customer/cust_login.php"
                style="display: inline-block; padding: 12px 24px; background: #1f4e74; color: white; text-decoration: none; border-radius: 6px; font-weight: 600;">
                <i class="fas fa-sign-in-alt"></i> Login Now
            </a>
        </div>
    </div>

    <script>
        // Simple toggle for public page
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

            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="/index.php">Home</a></li>
                    <li><a href="/public/about.php">About Us</a></li>
                    <li><a href="/public/products.php">Products</a></li>
                    <li><a href="/public/testimonials.php">Testimonials</a></li>
                    <li><a href="/customer/register.php">Sign Up</a></li>
                    <li><a href="/customer/cust_login.php">Login</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h3>Categories</h3>
                <ul class="footer-links">
                    <li><a href="/public/products.php?type=Kitchen">Kitchen Cabinet</a></li>
                    <li><a href="/public/products.php?type=Wardrobe">Wardrobe</a></li>
                    <li><a href="/public/products.php?type=Office Cabinet">Office Cabinet</a></li>
                    <li><a href="/public/products.php?type=Bathroom Cabinet">Bathroom Cabinet</a></li>
                    <li><a href="/public/products.php?type=Storage Cabinet">Storage Cabinet</a></li>
                </ul>
            </div>

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
                <a href="/public/privacy.php">Privacy Policy</a>
                <a href="/public/terms.php">Terms & Conditions</a>
            </div>
        </div>
    </footer>
    <script src="/assets/JS/chat_widget.js"></script>
</body>
</html>