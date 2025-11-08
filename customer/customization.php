<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Accept ?pid= or ?id=
$pid = (int)($_GET['pid'] ?? ($_GET['id'] ?? 0));
if ($pid <= 0) {
    header('Location:  /customer/products.php');
    exit;
}

// App + helpers (correct paths)
require_once __DIR__ . '/../backend/config/app.php';
require_once __DIR__ . '/../backend/lib/cms_helper.php';

// Preview vs Auth
$isPreview = isset($GLOBALS['cms_preview_content']) && !empty($GLOBALS['cms_preview_content']);
if (!$isPreview) {
    require_once __DIR__ . '/../includes/guard.php';
    guard_require_customer();
    $user = $_SESSION['user'] ?? null;
    if (!($user && (($user['aud'] ?? '') === 'customer'))) {
        header('Location: /customer/cust_login.php');
        exit;
    }
} else {
    $user = $_SESSION['user'] ?? null;
}

$content = $isPreview ? $GLOBALS['cms_preview_content'] : getCMSContent('about');

// Safe vars
$customerName = htmlspecialchars($user['name'] ?? $user['username'] ?? 'Customer', ENT_QUOTES, 'UTF-8');
$customerId   = (int)($user['id'] ?? 0);
$img          = $_SESSION['user']['profile_image'] ?? '';
$avatarHtml   = $img
    ? '<img src="/' . htmlspecialchars($img, ENT_QUOTES, 'UTF-8') . '?v=' . time() . '" alt="Avatar" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">'
    : strtoupper(substr($customerName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Rads Tooling - <?= $customerName ?></title>

    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- App CSS -->
    <link rel="stylesheet" href="/assets/CSS/Homepage.css" />
    <link rel="stylesheet" href="/assets/CSS/chat-widget.css">
    <link rel="stylesheet" href="/assets/CSS/customize.css">
    <link rel="stylesheet" href="/assets/CSS/checkout_modal.css">

    <!-- === Add-to-Cart / Buy-Now minimal CSS from product_detail.php === -->
    <style>
        /* Modal shell */
        .rt-modal {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 12000;
            background: rgba(15, 23, 42, .35);
            backdrop-filter: blur(2px);
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

        /* Keep hidden behavior consistent */
        #buyChoiceModal[hidden] {
            display: none !important;
        }

        #buyChoiceModal:not([hidden]) {
            display: flex !important;
            align-items: center;
            justify-content: center;
        }

        /* Inner modal bits (same naming used in product_detail.php) */
        .rt-header {
            padding: 16px 18px;
            border-bottom: 1px solid #eaeaea;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .rt-header h3 {
            margin: 0;
            font-weight: 800;
            color: #17233b;
        }

        .rt-close {
            background: transparent;
            border: 0;
            font-size: 22px;
            cursor: pointer;
            line-height: 1;
        }

        .rt-body {
            padding: 18px;
        }

        .rt-body .muted {
            color: #6b7280;
            margin-bottom: 10px;
        }

        .rt-choices {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 8px;
        }

        .rt-choice {
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1.5px solid #e5e7eb;
            background: #fff;
            border-radius: 12px;
            padding: 14px;
            cursor: pointer;
            font-weight: 700;
            justify-content: center;
        }

        .rt-choice.is-active {
            border-color: #2f5b88;
            box-shadow: 0 0 0 3px rgba(47, 91, 136, .1);
        }

        .rt-footer {
            display: flex;
            justify-content: flex-end;
            padding-top: 12px;
        }

        .rt-btn {
            border: 0;
            padding: 10px 14px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
        }

        .rt-btn-primary {
            background: #2f5b88;
            color: #fff;
            opacity: 1;
        }

        .rt-btn-primary:disabled {
            opacity: .5;
            cursor: not-allowed;
        }
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
                                <div class="profile-avatar" id="nav-avatar"><?= $avatarHtml ?></div>
                            </div>
                            <div class="profile-info">
                                <span class="profile-name"><?= $customerName ?></span>
                                <span class="material-symbols-rounded dropdown-icon">expand_more</span>
                            </div>
                        </button>

                        <div class="profile-dropdown" id="profileDropdown">
                            <div class="profile-dropdown-header">
                                <div class="dropdown-avatar" id="dd-avatar"><?= $avatarHtml ?></div>
                                <div class="dropdown-user-info">
                                    <div class="dropdown-name" id="dd-name"><?= $customerName ?></div>
                                    <div class="dropdown-email" id="userEmailDisplay">Loading...</div>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="/customer/profile.php" class="dropdown-item"><span class="material-symbols-rounded">person</span><span>My Profile</span></a>
                            <a href="/customer/orders.php" class="dropdown-item"><span class="material-symbols-rounded">receipt_long</span><span>My Orders</span></a>

                            <div class="dropdown-divider"></div>
                            <button onclick="showLogoutModal()" class="dropdown-item dropdown-logout" type="button">
                                <span class="material-symbols-rounded">logout</span><span>Logout</span>
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
                <a href="/customer/products.php" class="nav-menu-item">Products</a>
            </nav>
        </header>

        <!-- === CUSTOMIZER UI START === -->
        <div class="cz-topbar">
            <a href="/customer/products.php" class="cz-back">←</a>
            <div class="cz-breadcrumb">CUSTOMIZING</div>
        </div>

        <div class="cz-shell">
            <header class="cz-header">
                <h2>Customize your Cabinet</h2>
                <div class="cz-product-meta">
                    <div id="prodTitle">—</div>
                    <div id="priceBox" class="cz-price">₱ 0.00</div>
                </div>
            </header>

            <div class="cz-main">
                <!-- LEFT -->
                <section class="cz-left">
                    <div class="cz-stage">
                        <!-- Step arrows beside the viewer -->
                        <button id="stepPrev" class="cz-step-btn" aria-label="Previous">‹</button>
                        <div id="mediaBox" class="cz-media"><!-- model-viewer injected --></div>
                        <button id="stepNext" class="cz-step-btn" aria-label="Next">›</button>
                    </div>

                    <div class="cz-sizebar">
                        <span class="cz-size-label">SIZE:</span>
                        <div class="cz-size-legend">Use the controls to adjust (<span class="cz-unit-label">cm</span>).</div>
                    </div>

                    <div id="breakdown" class="cz-breakdown"></div>
                </section>

                <!-- RIGHT -->
                <aside class="cz-right">
                    <div class="cz-step-title">
                        <span id="stepLabel">Step 1 · Size</span>
                        <div class="cz-step-nav"><!-- kept for future top-nav dots, if needed --></div>
                    </div>

                    <!-- SIZE -->
                    <section id="sec-size" class="cz-sec">
                        <h3>
                            Size
                            <select id="unitSel" class="cz-unit">
                                <option value="cm">cm</option>
                                <option value="mm">mm</option>
                                <option value="inch">inch</option>
                                <option value="ft">ft</option>
                                <option value="meter">meter</option>
                            </select>
                            <button id="btnSizeGuide" type="button" class="btn ghost" style="margin-left:8px;padding:6px 10px">Size guide</button>
                        </h3>

                        <div class="cz-grid">
                            <label class="cz-field">
                                <div class="cz-label">Width (<span class="cz-unit-label">cm</span>)</div>
                                <input type="range" id="wSlider" min="0" max="0" step="1" value="0">
                                <input type="number" id="wInput" inputmode="decimal" style="margin-top:6px">
                            </label>

                            <label class="cz-field">
                                <div class="cz-label">Height (<span class="cz-unit-label">cm</span>)</div>
                                <input type="range" id="hSlider" min="0" max="0" step="1" value="0">
                                <input type="number" id="hInput" inputmode="decimal" style="margin-top:6px">
                            </label>

                            <label class="cz-field">
                                <div class="cz-label">Depth (<span class="cz-unit-label">cm</span>)</div>
                                <input type="range" id="dSlider" min="0" max="0" step="1" value="0">
                                <input type="number" id="dInput" inputmode="decimal" style="margin-top:6px">
                            </label>
                        </div>
                    </section>

                    <!-- TEXTURES -->
                    <section id="sec-textures" class="cz-sec" hidden>
                        <h3>Textures</h3>
                        <div class="cz-partbar">
                            <div class="part-tabs">
                                <button data-part="door" class="active">Door</button>
                                <button data-part="body">Body</button>
                                <button data-part="interior">Inside</button>
                            </div>
                        </div>
                        <div id="textures" class="cz-list"></div>
                    </section>

                    <!-- COLORS -->
                    <section id="sec-colors" class="cz-sec" hidden>
                        <h3>Colors</h3>
                        <div class="cz-partbar">
                            <div class="part-tabs">
                                <button data-part="door" class="active">Door</button>
                                <button data-part="body">Body</button>
                                <button data-part="interior">Inside</button>
                            </div>
                        </div>
                        <div id="colors" class="cz-list"></div>
                    </section>

                    <!-- HANDLES -->
                    <section id="sec-handles" class="cz-sec" hidden>
                        <h3>Handles</h3>
                        <div id="handles" class="cz-list"></div>
                    </section>

                    <div class="cz-actions">
                        <button id="toCart" class="btn main">ADD TO CART</button>
                        <a class="btn ghost js-buynow" data-pid="<?= (int)$pid ?>" href="#">BUY NOW</a>
                    </div>
                </aside>
            </div>
        </div>
        <!-- === CUSTOMIZER UI END === -->


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

    </div><!-- /.page-wrapper -->

    <!-- Scripts -->
    <script>
        window.PID = <?= (int)$pid ?>;
    </script>
    <script type="module" src="https://unpkg.com/@google/model-viewer/dist/model-viewer.min.js"></script>
    <script src="/assets/JS/customize.js" defer></script>
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
                    // Cart persists through logout - will reload from database on next login
                    sessionStorage.clear();
                    window.location.href = '/index.php';
                });
            };

        })();
    </script>

    <script>
        /* === Buy Now: open modal & redirect (ported) === */
        (function() {
            const modal = document.getElementById('buyChoiceModal');
            const closeBtn = document.getElementById('closeChoiceModal');
            const deliveryBtn = document.getElementById('choiceDelivery');
            const pickupBtn = document.getElementById('choicePickup');
            const okBtn = document.getElementById('choiceOk');

            if (!modal) return;

            // Open modal on any .js-buynow (anchor or button)
            document.addEventListener('click', function(e) {
                const b = e.target.closest && e.target.closest('.js-buynow');
                if (!b) return;
                e.preventDefault();
                e.stopPropagation();

                const pid = b.dataset.pid || window.PID || '';
                const qty = '1'; // customization page default = 1

                modal.dataset.pid = String(pid);
                modal.dataset.qty = String(qty);
                delete modal.dataset.mode;

                // reset UI
                okBtn.disabled = true;
                [deliveryBtn, pickupBtn].forEach(el => el && el.classList.remove('is-active'));
                modal.hidden = false;
                deliveryBtn && deliveryBtn.focus();
            }, {
                passive: false
            });

            // Close
            closeBtn && closeBtn.addEventListener('click', function() {
                modal.hidden = true;
                delete modal.dataset.pid;
                delete modal.dataset.qty;
                delete modal.dataset.mode;
                okBtn && (okBtn.disabled = true);
            });

            // Choose mode
            deliveryBtn && deliveryBtn.addEventListener('click', function() {
                modal.dataset.mode = 'delivery';
                this.classList.add('is-active');
                pickupBtn && pickupBtn.classList.remove('is-active');
                okBtn && (okBtn.disabled = false);
            });

            pickupBtn && pickupBtn.addEventListener('click', function() {
                modal.dataset.mode = 'pickup';
                this.classList.add('is-active');
                deliveryBtn && deliveryBtn.classList.remove('is-active');
                okBtn && (okBtn.disabled = false);
            });

            // Confirm
            okBtn && okBtn.addEventListener('click', function() {
                const pid = modal.dataset.pid || '';
                const qty = modal.dataset.qty || '1';
                const mode = modal.dataset.mode || '';
                if (!pid || !mode) return;

                const url = (mode === 'delivery') ?
                    `/customer/checkout_delivery.php?pid=${encodeURIComponent(pid)}&qty=${encodeURIComponent(qty)}` :
                    `/customer/checkout_pickup.php?pid=${encodeURIComponent(pid)}&qty=${encodeURIComponent(qty)}`;

                modal.hidden = true;
                window.location.href = url;
            });

            // Escape to close
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !modal.hidden) modal.hidden = true;
            });

            // Defensive: hidden on load
            document.addEventListener('DOMContentLoaded', function() {
                modal.hidden = true;
            });
        })();
    </script>

    <script>
        /* === Add to Cart: portable version (ported) === */
        (function() {
            // Helper (same as product_detail’s showToast)
            function showToast(message, type) {
                const toast = document.createElement('div');
                toast.textContent = message;
                toast.style.cssText =
                    'position:fixed;top:100px;right:20px;background:' +
                    (type === 'success' ? '#3db36b' : '#2f5b88') +
                    ';color:#fff;padding:1rem 1.5rem;border-radius:10px;' +
                    'box-shadow:0 4px 12px rgba(0,0,0,0.2);z-index:10000;font-weight:600;';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            }

            function updateCartCount() {
                const cart = JSON.parse(localStorage.getItem('cart') || '[]');
                const count = cart.reduce((s, i) => s + (i.quantity || 0), 0);
                document.querySelectorAll('#cartCount, #navCartCount, .cart-badge').forEach(el => el.textContent = count);
            }

            // Portable handler: expects a button/link with data-action="add-to-cart"
            // and data attributes: data-pid, data-name, data-type, data-price, data-image
            document.addEventListener('click', function(e) {
                const addBtn = e.target.closest && e.target.closest('[data-action="add-to-cart"]');
                if (!addBtn) return;

                e.preventDefault();
                e.stopPropagation();

                const product = {
                    id: parseInt(addBtn.dataset.pid || (window.PID || '0')),
                    name: addBtn.dataset.name || 'Custom Cabinet',
                    type: addBtn.dataset.type || '',
                    price: parseFloat(addBtn.dataset.price || '0'),
                    image: addBtn.dataset.image || '',
                    quantity: parseInt(addBtn.dataset.qty || '1')
                };

                let cart = JSON.parse(localStorage.getItem('cart') || '[]');
                const idx = cart.findIndex(i => i.id === product.id);
                if (idx !== -1) {
                    cart[idx].quantity = (cart[idx].quantity || 0) + product.quantity;
                    showToast(product.name + ' quantity updated!', 'success');
                } else {
                    cart.push(product);
                    showToast(product.name + ' added to cart!', 'success');
                }
                localStorage.setItem('cart', JSON.stringify(cart));
                updateCartCount();
            });
        })();
    </script>


    <!-- External Scripts -->
    <script src="/assets/JS/nav_user.js"></script>
    <script src="/assets/JS/chat_widget.js"></script>

    <!-- === BUY CHOICE MODAL (ported from product_detail.php) === -->
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

</body>

</html>