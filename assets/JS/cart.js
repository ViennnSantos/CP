// /assets/JS/cart.js

(function() {
  'use strict';

  // Cart state
  let cart = [];
  let selectedMode = null;

  // Initialize
  document.addEventListener('DOMContentLoaded', async function() {
    await loadCart();
    renderCart();
    setupEventListeners();
    updateAllCartCounts();
  });

  // Load cart from localStorage and database (merged)
  async function loadCart() {
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
        if (result.success && result.cart) {
          // Merge database cart with local cart
          const dbCart = result.cart;
          cart = mergeCart(localCart, dbCart);
          // Save merged cart back to localStorage and database
          localStorage.setItem('cart', JSON.stringify(cart));
          await syncCartToDatabase();
          return;
        }
      }
    } catch (err) {
      // User not logged in or API error - use local cart only
      console.log('Using localStorage cart only:', err.message);

    }
    // Fallback to localStorage cart
    cart = localCart;
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
  async function syncCartToDatabase() {
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
      // Silently fail - cart is still saved to localStorage
      console.log('Cart database sync failed:', err.message);
    }
  }

  // Save cart to localStorage and database
  function saveCart() {

    localStorage.setItem('cart', JSON.stringify(cart));

    updateAllCartCounts();

    // Async sync to database (don't wait for it)

    syncCartToDatabase();
  }

  // Add item to cart (called from product pages)
  window.addToCart = function(product) {
    // Check if product already exists in cart
    const existingIndex = cart.findIndex(item => item.id === product.id);
    
    if (existingIndex !== -1) {
      // Increment quantity
      cart[existingIndex].quantity += 1;
      showNotification(`${product.name} quantity updated!`, 'success');
    } else {
      // Add new item
      cart.push({
        id: product.id,
        name: product.name,
        type: product.type,
        price: parseFloat(product.price),
        image: product.image,
        quantity: 1
      });
      showNotification(`${product.name} added to cart!`, 'success');
    }
    
    saveCart();
    
    // Trigger animation
    animateAddToCart(event.target);
    
    // If on cart page, re-render
    if (window.location.pathname.includes('cart.php')) {
      renderCart();
    }
  };

  // Remove item from cart
  function removeFromCart(productId) {
    const item = cart.find(i => i.id === productId);
    if (!item) return;

    if (confirm(`Remove ${item.name} from cart?`)) {
      cart = cart.filter(i => i.id !== productId);
      saveCart();
      renderCart();
      showNotification('Item removed from cart', 'info');
    }
  }

  // Update quantity
  function updateQuantity(productId, change) {
    const item = cart.find(i => i.id === productId);
    if (!item) return;

    const newQty = item.quantity + change;
    
    if (newQty < 1) {
      removeFromCart(productId);
      return;
    }

    if (newQty > 10) {
      showNotification('Maximum quantity is 10', 'error');
      return;
    }

    item.quantity = newQty;
    saveCart();
    renderCart();
  }

  // Render cart page
  function renderCart() {
    const container = document.getElementById('cartContent');
    if (!container) return;

    const searchTerm = document.getElementById('cartSearch')?.value.toLowerCase() || '';
    
    // Filter cart based on search
    const filteredCart = cart.filter(item => 
      item.name.toLowerCase().includes(searchTerm) ||
      item.type.toLowerCase().includes(searchTerm)
    );

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

    if (filteredCart.length === 0) {
      container.innerHTML = `
        <div class="no-results">
          <p>No items match your search "${searchTerm}"</p>
        </div>
      `;
      return;
    }

    const subtotal = filteredCart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const vat = subtotal * 0.12;
    const total = subtotal + vat;

    container.innerHTML = `
      <div class="cart-container">
        <div class="cart-items">
          ${filteredCart.map(item => `
            <div class="cart-item" data-product-id="${item.id}">
              <img src="${item.image}" alt="${escapeHtml(item.name)}" class="cart-item-image" onerror="this.src='/assets/images/placeholder.jpg'">
              
              <div class="cart-item-details">
                <h3 class="cart-item-name">${escapeHtml(item.name)}</h3>
                <p class="cart-item-type">${escapeHtml(item.type)}</p>
                <p class="cart-item-price">₱${item.price.toLocaleString('en-PH', {minimumFractionDigits: 2})}</p>
              </div>

              <div class="cart-item-actions">
                <div class="cart-item-quantity">
                  <button class="qty-btn" onclick="updateCartQuantity(${item.id}, -1)" ${item.quantity <= 1 ? 'disabled' : ''}>
                    <span class="material-symbols-rounded">remove</span>
                  </button>
                  <span class="qty-value">${item.quantity}</span>
                  <button class="qty-btn" onclick="updateCartQuantity(${item.id}, 1)" ${item.quantity >= 10 ? 'disabled' : ''}>
                    <span class="material-symbols-rounded">add</span>
                  </button>
                </div>
                
                <button class="remove-btn" onclick="removeCartItem(${item.id})">
                  <span class="material-symbols-rounded">delete</span>
                  Remove
                </button>
              </div>
            </div>
          `).join('')}
        </div>

        <div class="cart-summary">
          <h3>Order Summary</h3>
          
          <div class="summary-row">
            <span>Subtotal (${filteredCart.reduce((sum, item) => sum + item.quantity, 0)} items)</span>
            <span>₱${subtotal.toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
          </div>
          
          <div class="summary-row">
            <span>VAT (12%)</span>
            <span>₱${vat.toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
          </div>
          
          <div class="summary-row">
            <strong>Total</strong>
            <strong>₱${total.toLocaleString('en-PH', {minimumFractionDigits: 2})}</strong>
          </div>

          <button class="checkout-btn" onclick="proceedToCheckout()">
            <span class="material-symbols-rounded">shopping_bag</span>
            Proceed to Checkout
          </button>

          <button class="continue-shopping" onclick="location.href='/customer/products.php'">
            Continue Shopping
          </button>
        </div>
      </div>
    `;
  }

  // Setup event listeners
  function setupEventListeners() {
    // Search functionality
    const searchInput = document.getElementById('cartSearch');
    if (searchInput) {
      searchInput.addEventListener('input', renderCart);
    }

    // Profile dropdown
    const profileToggle = document.getElementById('profileToggle');
    const profileDropdown = document.getElementById('profileDropdown');
    
    if (profileToggle && profileDropdown) {
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
    }

    // Buy choice modal
    const modal = document.getElementById('buyChoiceModal');
    const closeBtn = document.getElementById('closeChoiceModal');
    const okBtn = document.getElementById('choiceOk');
    const deliveryBtn = document.getElementById('choiceDelivery');
    const pickupBtn = document.getElementById('choicePickup');

    if (closeBtn) {
      closeBtn.addEventListener('click', () => {
        modal.hidden = true;
        selectedMode = null;
        deliveryBtn?.classList.remove('is-active');
        pickupBtn?.classList.remove('is-active');
        okBtn.disabled = true;
      });
    }

    if (deliveryBtn) {
      deliveryBtn.addEventListener('click', () => {
        selectedMode = 'delivery';
        deliveryBtn.classList.add('is-active');
        pickupBtn?.classList.remove('is-active');
        okBtn.disabled = false;
      });
    }

    if (pickupBtn) {
      pickupBtn.addEventListener('click', () => {
        selectedMode = 'pickup';
        pickupBtn.classList.add('is-active');
        deliveryBtn?.classList.remove('is-active');
        okBtn.disabled = false;
      });
    }

    if (okBtn) {
      okBtn.addEventListener('click', async () => {
        if (!selectedMode) return;
        if (cart.length === 0) {
          showNotification('Cart is empty', 'error');
          return;
        }

        // Save cart to session storage for checkout
        sessionStorage.setItem('checkoutCart', JSON.stringify(cart));
        sessionStorage.setItem('checkoutMode', selectedMode);
        // Also save to database if logged in
        await syncCartToDatabase();
        // Redirect to cart checkout page
        const redirectUrl = selectedMode === 'delivery' 
          ? '/customer/checkout_delivery.php'
          : '/customer/checkout_pickup.php';
        window.location.href = redirectUrl;
      });
    }
  }

  // Update all cart count displays
  function updateAllCartCounts() {
    const count = cart.reduce((sum, item) => sum + item.quantity, 0);
    
    // Update navbar cart count
    const navCount = document.getElementById('navCartCount');
    if (navCount) {
      navCount.textContent = count;
    }

    // Update cart page badge
    const badge = document.getElementById('cartCountBadge');
    if (badge) {
      badge.textContent = `${count} item${count !== 1 ? 's' : ''}`;
    }

    // Update any other cart counts on page
    document.querySelectorAll('.cart-badge, #cartCount').forEach(el => {
      el.textContent = count;
    });
  }

  // Animation for adding to cart
  function animateAddToCart(button) {
    if (!button) return;

    const cart = document.querySelector('.cart-button');
    if (!cart) return;

    const buttonRect = button.getBoundingClientRect();
    const cartRect = cart.getBoundingClientRect();

    const flyingItem = document.createElement('div');
    flyingItem.innerHTML = '<span class="material-symbols-rounded">shopping_cart</span>';
    flyingItem.style.cssText = `
      position: fixed;
      left: ${buttonRect.left}px;
      top: ${buttonRect.top}px;
      z-index: 10000;
      pointer-events: none;
      font-size: 24px;
      color: #2f5b88;
      transition: all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    `;
    
    document.body.appendChild(flyingItem);

    requestAnimationFrame(() => {
      flyingItem.style.left = `${cartRect.left}px`;
      flyingItem.style.top = `${cartRect.top}px`;
      flyingItem.style.opacity = '0';
      flyingItem.style.transform = 'scale(0.3)';
    });

    setTimeout(() => {
      flyingItem.remove();
      
      // Bounce animation on cart icon
      cart.style.animation = 'none';
      setTimeout(() => {
        cart.style.animation = 'cartBounce 0.5s ease';
      }, 10);
    }, 600);
  }

  // Add bounce animation CSS
  const style = document.createElement('style');
  style.textContent = `
    @keyframes cartBounce {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.2); }
    }
  `;
  document.head.appendChild(style);

  // Proceed to checkout
  window.proceedToCheckout = function() {
    if (cart.length === 0) {
      showNotification('Your cart is empty', 'error');
      return;
    }

    // Show mode selection modal
    const modal = document.getElementById('buyChoiceModal');
    if (modal) {
      modal.hidden = false;
    }
  };

  // Global functions for inline onclick handlers
  window.updateCartQuantity = function(productId, change) {
    updateQuantity(productId, change);
  };

  window.removeCartItem = function(productId) {
    removeFromCart(productId);
  };

  // Notification system
  function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    notification.style.cssText = `
      position: fixed;
      top: 100px;
      right: 20px;
      background: ${type === 'success' ? '#3db36b' : type === 'error' ? '#e14d4d' : '#2f5b88'};
      color: white;
      padding: 1rem 1.5rem;
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      z-index: 10000;
      animation: slideInRight 0.3s ease-out;
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
      notification.style.animation = 'slideOutRight 0.3s ease-out';
      setTimeout(() => notification.remove(), 300);
    }, 3000);
  }

  // Add notification animations
  const notifStyle = document.createElement('style');
  notifStyle.textContent = `
    @keyframes slideInRight {
      from {
        transform: translateX(400px);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }
    @keyframes slideOutRight {
      from {
        transform: translateX(0);
        opacity: 1;
      }
      to {
        transform: translateX(400px);
        opacity: 0;
      }
    }
  `;
  document.head.appendChild(notifStyle);

  // Utility function
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // Get cart (for external use)
  window.getCart = function() {
    return [...cart];
  };

  // Clear cart
  window.clearCart = function() {
    if (confirm('Are you sure you want to clear your cart?')) {
      cart = [];
      saveCart();
      renderCart();
      showNotification('Cart cleared', 'info');
    }
  };

})();