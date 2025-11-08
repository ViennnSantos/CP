//assets/JS/cart-counter.js
// Lightweight cart count updater for ALL pages

(function() {
  'use strict';

  // Update cart count from localStorage
  function updateCartCount() {
    try {
      const cart = JSON.parse(localStorage.getItem('cart') || '[]');
      
      // Validate cart is an array
      if (!Array.isArray(cart)) {
        console.warn('Invalid cart data, resetting...');
        localStorage.setItem('cart', '[]');
        return;
      }
      
      // Calculate total with safety check
      const totalItems = cart.reduce((sum, item) => {
        return sum + (parseInt(item.quantity) || 0);
      }, 0);
      
      console.log('🛒 Cart count:', totalItems);
      
      // Update all cart count elements
      const selectors = '#navCartCount, #cartCount, .cart-badge';
      document.querySelectorAll(selectors).forEach(element => {
        element.textContent = totalItems;
        
        // Add updated animation
        element.classList.add('updated');
        setTimeout(() => element.classList.remove('updated'), 500);
      });
      
    } catch (error) {
      console.error('❌ Cart count error:', error);
      // Reset cart if corrupted
      localStorage.setItem('cart', '[]');
    }
  }

  // Wait for DOM to be fully loaded
  function init() {
    updateCartCount();
    
    // Listen for custom events from cart.js
    window.addEventListener('cartUpdated', function() {
      console.log('🔔 Cart update event received');
      updateCartCount();
    });
    
    // Listen for changes from OTHER tabs only
    window.addEventListener('storage', function(e) {
      if (e.key === 'cart') {
        console.log('📦 Cart updated from another tab');
        updateCartCount();
      }
    });
  }

  // Smart initialization
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    // DOM already loaded
    init();
  }

  // Expose globally for manual updates
  window.updateCartCount = updateCartCount;

  console.log('✅ Cart Counter Ready');
})();
  