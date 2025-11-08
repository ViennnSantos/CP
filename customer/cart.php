<?php
// /customer/cart.php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/guard.php';
guard_require_customer();

$user = $_SESSION['user'] ?? null;
$customerName = htmlspecialchars($user['name'] ?? $user['username'] ?? 'Customer');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - RADS Tooling</title>

    <!-- Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --brand: #2f5b88;
            --brand-dark: #1e3a8a;
            --success: #3db36b;
            --danger: #e14d4d;
            --text: #111827;
            --text-muted: #6b7280;
            --border: #e5e7eb;
            --bg: #f5f7fa;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        /* ========== MINIMAL HEADER ========== */
        .minimal-header {
            background: linear-gradient(to left, #355683, #6e7ca1, #a0a5bf, #d1d0de, #ffffff);
            padding: 1rem 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .back-button {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #2f5b88;
            /* Solid blue */
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }

        .back-button:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(-2px);
        }

        .logo {
            color: #2c3e50;
            /* Dark blue for ADS and OOLING */
            font-size: 1.5rem;
            font-weight: 600;
            text-decoration: none;
        }

        .logo-text {
            color: #2f5b88;
            /* Brand blue for R and T */
            font-weight: 700;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .profile-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            transition: background 0.2s;
        }

        .profile-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .profile-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: white;
            color: var(--brand);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        /* ========== MAIN CONTENT ========== */
        .cart-wrapper {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
            min-height: calc(100vh - 250px);
        }

        .cart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .cart-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .cart-title h1 {
            font-size: 2rem;
            font-weight: 700;
        }

        .cart-count-badge {
            background: var(--brand);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* ========== FILTERS ========== */
        .cart-filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .cart-search {
            position: relative;
            flex: 1;
            min-width: 250px;
        }

        .cart-search input {
            width: 100%;
            padding: 0.75rem 3rem 0.75rem 1rem;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 1rem;
            font-family: inherit;
        }

        .cart-search input:focus {
            outline: none;
            border-color: var(--brand);
        }

        .cart-search .material-symbols-rounded {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .price-filter {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .price-filter input {
            width: 120px;
            padding: 0.75rem;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-family: inherit;
        }

        .price-filter input:focus {
            outline: none;
            border-color: var(--brand);
        }

        /* ========== CART LAYOUT ========== */
        .cart-container {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 2rem;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .cart-container {
                grid-template-columns: 1fr;
            }
        }

        .cart-items {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .cart-items-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 2px solid var(--border);
            margin-bottom: 1rem;
        }

        .cart-items-header label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            user-select: none;
        }

        .item-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--brand);
        }

        .cart-item {
            display: grid;
            grid-template-columns: auto 120px 1fr auto;
            gap: 1.5rem;
            padding: 1.5rem;
            border: 2px solid var(--border);
            border-radius: 10px;
            margin-bottom: 1rem;
            transition: all 0.2s;
            background: white;
            align-items: center;
        }

        .cart-item.selected {
            border-color: var(--brand);
            background: #f8faff;
        }

        .cart-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .cart-item-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            background: var(--bg);
        }

        .cart-item-details {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .cart-item-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
        }

        .cart-item-type {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin: 0;
        }

        .cart-item-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--brand);
            margin-top: auto;
        }

        .cart-item-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 1rem;
        }

        .cart-item-quantity {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.25rem;
        }

        .qty-btn {
            width: 32px;
            height: 32px;
            border: none;
            background: var(--bg);
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .qty-btn:hover:not(:disabled) {
            background: var(--border);
        }

        .qty-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .qty-value {
            min-width: 40px;
            text-align: center;
            font-weight: 600;
        }

        .remove-btn {
            background: transparent;
            border: 1px solid var(--danger);
            color: var(--danger);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .remove-btn:hover {
            background: var(--danger);
            color: white;
        }

        /* ========== ORDER SUMMARY ========== */
        .cart-summary {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            position: sticky;
            top: 100px;
        }

        .cart-summary h3 {
            font-size: 1.25rem;
            margin: 0 0 1.5rem 0;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }

        .summary-row.total {
            border-bottom: 2px solid var(--brand);
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--brand);
            margin-top: 1rem;
            padding-top: 1rem;
        }

        .checkout-btn {
            width: 100%;
            background: var(--brand);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 1.5rem;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-family: inherit;
        }

        .checkout-btn:hover:not(:disabled) {
            background: var(--brand-dark);
        }

        .checkout-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background: #94a3b8;
        }

        .continue-shopping {
            width: 100%;
            background: transparent;
            color: var(--brand);
            border: 2px solid var(--brand);
            padding: 0.75rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 1rem;
            transition: all 0.2s;
            font-family: inherit;
        }

        .continue-shopping:hover {
            background: rgba(47, 91, 136, 0.05);
        }

        /* ========== EMPTY STATE ========== */
        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 12px;
        }

        .empty-cart .material-symbols-rounded {
            font-size: 80px;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        .empty-cart h2 {
            margin-bottom: 0.5rem;
        }

        .empty-cart p {
            color: var(--text-muted);
            margin-bottom: 2rem;
        }

        /* ========== MODALS ========== */
        .modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .5);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            z-index: 9999;
            backdrop-filter: blur(2px);
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: min(480px, 95vw);
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .3);
            animation: modalSlideUp .3s ease;
        }

        @keyframes modalSlideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            background: var(--brand);
            color: white;
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.125rem;
        }

        .modal-close {
            background: transparent;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            line-height: 1;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-body p {
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }

        .modal-choices {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .modal-choice {
            flex: 1;
            background: white;
            color: var(--brand);
            border: 2px solid var(--brand);
            border-radius: 10px;
            padding: 1.25rem;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            font-weight: 600;
            font-family: inherit;
            font-size: 1rem;
        }

        .modal-choice:hover:not(.is-active) {
            background: rgba(47, 91, 136, 0.05);
        }

        .modal-choice.is-active {
            background: var(--brand);
            color: white;
        }

        .modal-choice .material-symbols-rounded {
            font-size: 36px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .modal-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            font-size: 0.95rem;
        }

        .modal-btn-primary {
            background: var(--brand);
            color: white;
            border: none;
        }

        .modal-btn-primary:hover:not(:disabled) {
            background: var(--brand-dark);
        }

        .modal-btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .modal-btn-secondary {
            background: transparent;
            color: var(--text);
            border: 1px solid var(--border);
        }

        .modal-btn-secondary:hover {
            background: var(--bg);
        }

        /* ========== MINIMAL FOOTER ========== */
        .minimal-footer {
            background: #111827;
            padding: 2rem;
            margin-top: 4rem;
            text-align: center;
        }

        .minimal-footer p {
            color: #9ca3af;
            margin: 0;
            font-size: 0.875rem;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .minimal-header {
                padding: 1rem;
            }

            .header-left {
                gap: 1rem;
            }

            .logo {
                font-size: 1.25rem;
            }

            .cart-wrapper {
                padding: 0 1rem;
            }

            .cart-item {
                grid-template-columns: auto 1fr;
                gap: 1rem;
            }

            .cart-item-image {
                grid-column: 1 / -1;
                width: 100%;
                height: 200px;
            }

            .cart-item-actions {
                grid-column: 1 / -1;
                flex-direction: row;
                justify-content: space-between;
            }

            .modal-choices {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <!-- MINIMAL HEADER -->
    <header class="minimal-header">
        <div class="header-content">
            <div class="header-left">
                <a href="/customer/products.php" class="back-button">
                    <span class="material-symbols-rounded">arrow_back</span>
                    Back
                </a>
                <a href="/customer/homepage.php" class="logo">
                    <span class="logo-text">R</span>ADS <span class="logo-text">T</span>OOLING
                </a>
            </div>
            <div class="header-right">
                <div class="profile-btn">
                    <div class="profile-avatar">
                        <?= strtoupper(substr($customerName, 0, 1)) ?>
                    </div>
                    <span><?= $customerName ?></span>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="cart-wrapper">
        <div class="cart-header">
            <div class="cart-title">
                <h1>Shopping Cart</h1>
                <span id="cartCountBadge" class="cart-count-badge">0 items</span>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="cart-filters">
            <div class="cart-search">
                <input type="text" id="cartSearch" placeholder="Search items in cart...">
                <span class="material-symbols-rounded">search</span>
            </div>

            <div class="price-filter">
                <span>Price:</span>
                <input type="number" id="minPrice" placeholder="Min" step="100">
                <span>-</span>
                <input type="number" id="maxPrice" placeholder="Max" step="100">
            </div>
        </div>

        <!-- CART CONTENT -->
        <div id="cartContent"></div>
    </main>

    <!-- MINIMAL FOOTER -->
    <footer class="minimal-footer">
        <p>© 2025 RADS TOOLING INC. All rights reserved.</p>
    </footer>

    <!-- BUY CHOICE MODAL -->
    <div id="buyChoiceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>How do you want to get your order?</h3>
                <button class="modal-close" id="closeChoiceModal">×</button>
            </div>
            <div class="modal-body">
                <p>Choose your preferred fulfillment method.</p>
                <div class="modal-choices">
                    <button class="modal-choice" id="choiceDelivery">
                        <span class="material-symbols-rounded">local_shipping</span>
                        Delivery
                    </button>
                    <button class="modal-choice" id="choicePickup">
                        <span class="material-symbols-rounded">store</span>
                        Pick-up
                    </button>
                </div>
                <div class="modal-footer">
                    <button class="modal-btn modal-btn-primary" id="choiceOk" disabled>OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- SELECT ITEMS MODAL -->
    <div id="selectItemsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>No Items Selected</h3>
                <button class="modal-close" onclick="closeModal('selectItemsModal')">×</button>
            </div>
            <div class="modal-body">
                <p>Please select items to checkout</p>
                <div class="modal-footer">
                    <button class="modal-btn modal-btn-primary" onclick="closeModal('selectItemsModal')">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- REMOVE ITEM MODAL -->
    <div id="removeItemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Remove Item</h3>
                <button class="modal-close" onclick="closeModal('removeItemModal')">×</button>
            </div>
            <div class="modal-body">
                <p id="removeItemText">Remove this item from cart?</p>
                <div class="modal-footer">
                    <button class="modal-btn modal-btn-secondary" onclick="closeModal('removeItemModal')">Cancel</button>
                    <button class="modal-btn modal-btn-primary" id="confirmRemoveBtn">Remove</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Helper functions
        function showModal(id) {
            document.getElementById(id).classList.add('show');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Cart System
        (function() {
            'use strict';

            let selectedItems = new Set();
            let itemToRemove = null;

// Load cart from localStorage and database (merged)
            async function loadCartFromDatabase() {
                // First load from localStorage
                const stored = localStorage.getItem('cart');
                let localCart = stored ? JSON.parse(stored) : [];

                // Try to load from database if user is logged in
                try {
                    const response = await fetch('/backend/api/cart.php', {
                        method: 'GET',
                        credentials: 'same-origin'
                    });

                    if (response.ok) {
                        const result = await response.json();
                        if (result.success && result.cart && result.cart.length > 0) {
                            // Merge database cart with local cart
                            const dbCart = result.cart;
                            const mergedCart = mergeCart(localCart, dbCart);
                            // Save merged cart back to localStorage and database
                            localStorage.setItem('cart', JSON.stringify(mergedCart));
                            await syncCartToDatabase(mergedCart);
                            console.log('✅ Cart loaded and merged from database');
                            return;
                        }
                    }
                } catch (err) {
                    console.log('Using localStorage cart only:', err.message);
                }
            }

            // Merge two carts (prioritize higher quantity)
            function mergeCart(cart1, cart2) {
                const merged = [...cart1];

                cart2.forEach(item2 => {
                    const existingIndex = merged.findIndex(item => item.id === item2.id);
                    if (existingIndex !== -1) {
                        // Keep the higher quantity
                        if (item2.quantity > merged[existingIndex].quantity) {
                            merged[existingIndex] = item2;
                        }
                    } else {
                        // Add new item
                        merged.push(item2);
                    }
                });

                return merged;
            }

            // Sync cart to database (for logged-in users)
            async function syncCartToDatabase(cart) {
                try {
                    await fetch('/backend/api/cart.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ cart })
                    });
                } catch (err) {
                    console.log('Cart database sync failed:', err.message);
                }
            }

            // Render Cart
            window.renderCart = function() {
                const container = document.getElementById('cartContent');
                if (!container) return;

                const cart = JSON.parse(localStorage.getItem('cart') || '[]');
                const searchTerm = document.getElementById('cartSearch')?.value.toLowerCase() || '';
                const minPrice = parseFloat(document.getElementById('minPrice')?.value) || 0;
                const maxPrice = parseFloat(document.getElementById('maxPrice')?.value) || Infinity;

                // Filter cart
                let filteredCart = cart.filter(item => {
                    const matchesSearch = item.name.toLowerCase().includes(searchTerm) ||
                        item.type.toLowerCase().includes(searchTerm);
                    const matchesPrice = item.price >= minPrice && item.price <= maxPrice;
                    return matchesSearch && matchesPrice;
                });

                // Update badge
                const badge = document.getElementById('cartCountBadge');
                if (badge) {
                    const total = cart.reduce((sum, item) => sum + item.quantity, 0);
                    badge.textContent = `${total} item${total !== 1 ? 's' : ''}`;
                }

                // Empty state
                if (cart.length === 0) {
                    container.innerHTML = `
          <div class="empty-cart">
            <span class="material-symbols-rounded">shopping_cart</span>
            <h2>Your cart is empty</h2>
            <p>Add some amazing cabinets to get started!</p>
            <button class="checkout-btn" onclick="location.href='/customer/products.php'">
              Browse Products
            </button>
          </div>
        `;
                    return;
                }

                // No results
                if (filteredCart.length === 0) {
                    container.innerHTML = `
          <div class="empty-cart">
            <p style="color: var(--text-muted)">No items match your filters</p>
          </div>
        `;
                    return;
                }

                // Render with checkboxes
                container.innerHTML = `
        <div class="cart-container">
          <div class="cart-items">
            <div class="cart-items-header">
              <label>
                <input type="checkbox" id="selectAll" class="item-checkbox">
                <span>Select All (${filteredCart.length})</span>
              </label>
            </div>
            
            ${filteredCart.map(item => `
              <div class="cart-item ${selectedItems.has(item.id) ? 'selected' : ''}" data-product-id="${item.id}">
                <div class="cart-item-checkbox">
                  <input type="checkbox" class="item-checkbox item-select" data-id="${item.id}" ${selectedItems.has(item.id) ? 'checked' : ''}>
                </div>
                
                <img 
                  src="${escapeHtml(item.image)}" 
                  alt="${escapeHtml(item.name)}" 
                  class="cart-item-image" 
                  onerror="this.src='/assets/images/placeholder.jpg'">
                
                <div class="cart-item-details">
                  <h3 class="cart-item-name">${escapeHtml(item.name)}</h3>
                  <p class="cart-item-type">${escapeHtml(item.type)}</p>
                  <p class="cart-item-price">₱${item.price.toLocaleString('en-PH', {minimumFractionDigits: 2})}</p>
                </div>

                <div class="cart-item-actions">
                  <div class="cart-item-quantity">
                    <button 
                      class="qty-btn" 
                      onclick="updateCartQuantity(${item.id}, -1)" 
                      ${item.quantity <= 1 ? 'disabled' : ''}>
                      <span class="material-symbols-rounded">remove</span>
                    </button>
                    <span class="qty-value">${item.quantity}</span>
                    <button 
                      class="qty-btn" 
                      onclick="updateCartQuantity(${item.id}, 1)" 
                      ${item.quantity >= 10 ? 'disabled' : ''}>
                      <span class="material-symbols-rounded">add</span>
                    </button>
                  </div>
                  
                  <button class="remove-btn" onclick="showRemoveModal(${item.id}, '${escapeHtml(item.name).replace(/'/g, "\\'")}')">
                    <span class="material-symbols-rounded">delete</span>
                    Remove
                  </button>
                </div>
              </div>
            `).join('')}
          </div>
          
          <div class="cart-summary">
            <h3>Order Summary</h3>
            <div id="summaryContent"></div>
          </div>
        </div>
      `;

                setupCheckboxListeners();
                updateSummary();
            };

            // Setup checkbox listeners
            function setupCheckboxListeners() {
                const selectAll = document.getElementById('selectAll');
                const itemCheckboxes = document.querySelectorAll('.item-select');

                if (selectAll) {
                    selectAll.addEventListener('change', function() {
                        itemCheckboxes.forEach(cb => {
                            cb.checked = this.checked;
                            const id = parseInt(cb.dataset.id);
                            if (this.checked) {
                                selectedItems.add(id);
                            } else {
                                selectedItems.delete(id);
                            }
                        });
                        updateSelectionUI();
                        updateSummary();
                    });
                }

                itemCheckboxes.forEach(cb => {
                    cb.addEventListener('change', function() {
                        const id = parseInt(this.dataset.id);
                        if (this.checked) {
                            selectedItems.add(id);
                        } else {
                            selectedItems.delete(id);
                        }

                        if (selectAll) {
                            selectAll.checked = itemCheckboxes.length === selectedItems.size;
                        }

                        updateSelectionUI();
                        updateSummary();
                    });
                });
            }

            // Update selection UI
            function updateSelectionUI() {
                document.querySelectorAll('.cart-item').forEach(item => {
                    const id = parseInt(item.dataset.productId);
                    if (selectedItems.has(id)) {
                        item.classList.add('selected');
                    } else {
                        item.classList.remove('selected');
                    }
                });
            }

            // Update summary
            function updateSummary() {
                const summaryContent = document.getElementById('summaryContent');
                if (!summaryContent) return;

                const cart = JSON.parse(localStorage.getItem('cart') || '[]');
                const selected = cart.filter(item => selectedItems.has(item.id));

                const subtotal = selected.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                const vat = subtotal * 0.12;
                const total = subtotal + vat;
                const itemCount = selected.reduce((sum, item) => sum + item.quantity, 0);

                summaryContent.innerHTML = `
        <div class="summary-row">
          <span>Subtotal (${itemCount} items)</span>
          <span>₱${subtotal.toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
        </div>
        
        <div class="summary-row">
          <span>VAT (12%)</span>
          <span>₱${vat.toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
        </div>
        
        <div class="summary-row total">
          <strong>Total</strong>
          <strong>₱${total.toLocaleString('en-PH', {minimumFractionDigits: 2})}</strong>
        </div>

        <button class="checkout-btn" onclick="proceedToCheckout()" ${selectedItems.size === 0 ? 'disabled' : ''}>
          <span class="material-symbols-rounded">shopping_bag</span>
          Proceed to Checkout (${selectedItems.size})
        </button>

        <button class="continue-shopping" onclick="location.href='/customer/products.php'">
          Continue Shopping
        </button>
      `;
            }

            // Filter listeners
            document.getElementById('cartSearch')?.addEventListener('input', window.renderCart);
            document.getElementById('minPrice')?.addEventListener('input', window.renderCart);
            document.getElementById('maxPrice')?.addEventListener('input', window.renderCart);

            // Checkout
            window.proceedToCheckout = function() {
                if (selectedItems.size === 0) {
                    showModal('selectItemsModal');
                    return;
                }

                showModal('buyChoiceModal');
            };

            // Quantity update
            window.updateCartQuantity = function(id, change) {
                let cart = JSON.parse(localStorage.getItem('cart') || '[]');
                const item = cart.find(i => i.id === id);
                if (!item) return;

                const newQty = item.quantity + change;
                if (newQty < 1 || newQty > 10) return;

                item.quantity = newQty;
                localStorage.setItem('cart', JSON.stringify(cart));
                syncCartToDatabase(cart);
                window.renderCart();
            };

            // Remove item
            window.showRemoveModal = function(id, name) {
                itemToRemove = id;
                document.getElementById('removeItemText').textContent = `Remove "${name}" from cart?`;
                showModal('removeItemModal');
            };

            document.getElementById('confirmRemoveBtn').addEventListener('click', function() {
                if (itemToRemove !== null) {
                    let cart = JSON.parse(localStorage.getItem('cart') || '[]');
                    cart = cart.filter(i => i.id !== itemToRemove);
                    localStorage.setItem('cart', JSON.stringify(cart));
                    syncCartToDatabase(cart);
                    selectedItems.delete(itemToRemove);
                    closeModal('removeItemModal');
                    window.renderCart();
                }
            });

            // Buy choice modal
            const deliveryBtn = document.getElementById('choiceDelivery');
            const pickupBtn = document.getElementById('choicePickup');
            const okBtn = document.getElementById('choiceOk');
            const closeBtn = document.getElementById('closeChoiceModal');

            let selectedMode = null;

            deliveryBtn.addEventListener('click', function() {
                selectedMode = 'delivery';
                deliveryBtn.classList.add('is-active');
                pickupBtn.classList.remove('is-active');
                okBtn.disabled = false;
            });

            pickupBtn.addEventListener('click', function() {
                selectedMode = 'pickup';
                pickupBtn.classList.add('is-active');
                deliveryBtn.classList.remove('is-active');
                okBtn.disabled = false;
            });

            closeBtn.addEventListener('click', function() {
                closeModal('buyChoiceModal');
                selectedMode = null;
                deliveryBtn.classList.remove('is-active');
                pickupBtn.classList.remove('is-active');
                okBtn.disabled = true;
            });

            okBtn.addEventListener('click', function() {
                if (!selectedMode || selectedItems.size === 0) return;

                const cart = JSON.parse(localStorage.getItem('cart') || '[]');
                const checkoutItems = cart.filter(item => selectedItems.has(item.id));
                localStorage.setItem('checkoutItems', JSON.stringify(checkoutItems));

                const url = selectedMode === 'delivery' ?
                    '/customer/checkout_delivery.php' :
                    '/customer/checkout_pickup.php';

                window.location.href = url;
            });

            (async function() {
                await loadCartFromDatabase();
                window.renderCart();
            })();
        })();
    </script>
</body>

</html>