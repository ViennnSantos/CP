/**
 * RADS TOOLING - Product Image Drag & Drop
 * Allows moving images between products via drag-and-drop
 *
 * Features:
 * - Drag images from one product card to another
 * - Visual feedback (highlight drop zones)
 * - Toast notifications on success/error
 * - Custom DOM events for integration
 * - Optional backend persistence via API
 * - Graceful fallback (UI-only update if API unavailable)
 */

(function() {
  'use strict';

  // ==================== CONFIGURATION ====================

  const CONFIG = {
    apiEndpoint: '/RADS-TOOLING/backend/api/product_images.php',
    enableBackend: true, // Set to false for UI-only mode
    showToasts: true,
    debugMode: false
  };

  // ==================== STATE ====================

  let draggedImageId = null;
  let draggedFromProductId = null;
  let draggedElement = null;

  // ==================== HELPER FUNCTIONS ====================

  /**
   * Log debug message
   */
  function debug(...args) {
    if (CONFIG.debugMode) {
      console.log('[ProductDrag]', ...args);
    }
  }

  /**
   * Show toast notification
   */
  function showToast(message, type = 'info') {
    if (!CONFIG.showToasts) return;

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;

    // Icon based on type
    const icon = document.createElement('span');
    icon.className = 'material-symbols-rounded';
    icon.textContent = type === 'success' ? 'check_circle' :
                       type === 'error' ? 'error' :
                       type === 'warning' ? 'warning' : 'info';
    icon.style.fontSize = '20px';

    toast.insertBefore(icon, toast.firstChild);

    document.body.appendChild(toast);

    // Auto-remove after 3 seconds
    setTimeout(() => {
      toast.style.opacity = '0';
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  /**
   * Get product name from card
   */
  function getProductName(productCard) {
    const nameEl = productCard.querySelector('.product-title, .rt-name');
    return nameEl ? nameEl.textContent.trim() : 'Unknown Product';
  }

  // ==================== EVENT HANDLERS ====================

  /**
   * Called when drag starts
   */
  function onImageDragStart({ imageId, fromProductId, element }) {
    debug('Drag started:', { imageId, fromProductId });

    // Dispatch custom event
    document.dispatchEvent(new CustomEvent('imageDragStart', {
      detail: { imageId, fromProductId }
    }));
  }

  /**
   * Called when image is dropped
   */
  function onImageDrop({ imageId, toProductId }) {
    debug('Drop detected:', { imageId, toProductId });

    // Dispatch custom event
    document.dispatchEvent(new CustomEvent('imageDrop', {
      detail: { imageId, toProductId }
    }));
  }

  /**
   * Move image via API (with fallback)
   */
  async function moveImageToProduct(imageId, fromProductId, toProductId) {
    const fromCard = document.querySelector(`.product-card[data-pid="${fromProductId}"], .rt-card[data-pid="${fromProductId}"]`);
    const toCard = document.querySelector(`.product-card[data-pid="${toProductId}"], .rt-card[data-pid="${toProductId}"]`);

    const toProductName = getProductName(toCard);

    if (!CONFIG.enableBackend) {
      // UI-only fallback
      debug('Backend disabled, UI-only update');
      showToast(`Image moved to ${toProductName} (not saved)`, 'warning');

      // Dispatch custom event
      document.dispatchEvent(new CustomEvent('imageMoved', {
        detail: { imageId, fromProductId, toProductId }
      }));

      return;
    }

    try {
      // Call backend API
      const response = await fetch(`${CONFIG.apiEndpoint}?action=move`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          imageId: imageId,
          fromProductId: fromProductId,
          toProductId: toProductId
        })
      });

      const result = await response.json();

      if (result.success) {
        debug('Image moved successfully:', result);
        showToast(`Image moved — saved.`, 'success');

        // Dispatch custom event
        document.dispatchEvent(new CustomEvent('imageMoved', {
          detail: {
            imageId,
            fromProductId,
            toProductId,
            data: result.data
          }
        }));

        // Refresh product images
        refreshProductImages(fromProductId);
        refreshProductImages(toProductId);

      } else {
        throw new Error(result.message || 'Failed to move image');
      }

    } catch (error) {
      debug('API error:', error);

      // Graceful fallback
      showToast(`Image moved (UI only — not saved to server)`, 'warning');

      // Dispatch event anyway
      document.dispatchEvent(new CustomEvent('imageMoved', {
        detail: {
          imageId,
          fromProductId,
          toProductId,
          error: error.message
        }
      }));
    }
  }

  /**
   * Refresh product images (re-fetch from server)
   */
  async function refreshProductImages(productId) {
    const card = document.querySelector(`.product-card[data-pid="${productId}"], .rt-card[data-pid="${productId}"]`);
    if (!card) return;

    const imgEl = card.querySelector('.product-image img, .rt-imgwrap img, .product-grid-img, .product-public-img');
    if (!imgEl) return;

    try {
      const response = await fetch(`${CONFIG.apiEndpoint}?action=list&product_id=${productId}`, {
        credentials: 'same-origin'
      });

      const result = await response.json();

      if (!result.success) return;

      const images = result.data?.images || result.data || [];
      if (!Array.isArray(images) || images.length === 0) return;

      // Get primary image
      const primary = images.find(img => Number(img.is_primary) === 1) || images[0];
      if (!primary) return;

      const filename = String(primary.image_path || primary.path || primary.filename || '').split('/').pop();
      if (!filename) return;

      const newSrc = `/RADS-TOOLING/uploads/products/${filename}`;

      // Update image
      imgEl.src = newSrc;

      debug('Refreshed image for product', productId);

    } catch (error) {
      debug('Error refreshing images:', error);
    }
  }

  // ==================== DRAG & DROP INITIALIZATION ====================

  /**
   * Make product images draggable
   */
  function initDraggableImages() {
    const images = document.querySelectorAll('.product-image img, .rt-imgwrap img, .product-grid-img, .product-public-img');

    images.forEach(img => {
      // Set draggable attribute
      img.setAttribute('draggable', 'true');

      // Drag start
      img.addEventListener('dragstart', function(e) {
        draggedElement = this;

        // Get image ID and product ID
        const card = this.closest('.product-card, .rt-card');
        if (!card) return;

        draggedFromProductId = card.dataset.pid || card.dataset.productId;
        draggedImageId = this.dataset.imageId || this.dataset.id || `img_${draggedFromProductId}`;

        // Set drag data
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', this.outerHTML);
        e.dataTransfer.setData('application/json', JSON.stringify({
          imageId: draggedImageId,
          fromProductId: draggedFromProductId
        }));

        // Visual feedback
        this.style.opacity = '0.5';

        // Custom event
        onImageDragStart({
          imageId: draggedImageId,
          fromProductId: draggedFromProductId,
          element: this
        });

        debug('Drag start:', { imageId: draggedImageId, fromProductId: draggedFromProductId });
      });

      // Drag end
      img.addEventListener('dragend', function(e) {
        this.style.opacity = '1';
        draggedElement = null;

        // Remove all drag-over classes
        document.querySelectorAll('.drag-over').forEach(el => {
          el.classList.remove('drag-over');
        });
      });
    });

    debug(`Initialized ${images.length} draggable images`);
  }

  /**
   * Make product cards drop targets
   */
  function initDropTargets() {
    const cards = document.querySelectorAll('.product-card, .rt-card');

    cards.forEach(card => {
      // Drag over
      card.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';

        const toProductId = this.dataset.pid || this.dataset.productId;

        // Don't highlight same product
        if (toProductId === draggedFromProductId) {
          return;
        }

        // Highlight drop zone
        this.classList.add('drag-over');
      });

      // Drag leave
      card.addEventListener('dragleave', function(e) {
        // Only remove if leaving the card itself (not child elements)
        if (e.target === this) {
          this.classList.remove('drag-over');
        }
      });

      // Drop
      card.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();

        this.classList.remove('drag-over');

        const toProductId = this.dataset.pid || this.dataset.productId;

        // Same product, ignore
        if (toProductId === draggedFromProductId) {
          debug('Dropped on same product, ignoring');
          return;
        }

        if (!draggedImageId || !draggedFromProductId) {
          debug('Missing drag data');
          return;
        }

        // Custom event
        onImageDrop({
          imageId: draggedImageId,
          toProductId: toProductId
        });

        // Move image
        moveImageToProduct(draggedImageId, draggedFromProductId, toProductId);

        // Reset state
        draggedImageId = null;
        draggedFromProductId = null;
      });
    });

    debug(`Initialized ${cards.length} drop targets`);
  }

  // ==================== INITIALIZATION ====================

  /**
   * Initialize drag & drop
   */
  function init() {
    debug('Initializing product drag & drop...');

    initDraggableImages();
    initDropTargets();

    debug('Product drag & drop initialized');
  }

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Re-initialize when new products are added dynamically
  document.addEventListener('productsUpdated', function() {
    debug('Products updated, re-initializing...');
    init();
  });

  // ==================== EXPOSE PUBLIC API ====================

  window.ProductDrag = {
    init: init,
    enableBackend: (enabled) => { CONFIG.enableBackend = enabled; },
    enableToasts: (enabled) => { CONFIG.showToasts = enabled; },
    enableDebug: (enabled) => { CONFIG.debugMode = enabled; },
    moveImage: moveImageToProduct,
    refreshImages: refreshProductImages
  };

  debug('ProductDrag module loaded');

})();

/**
 * DEVELOPER USAGE:
 *
 * 1. Listen for custom events:
 *
 *    document.addEventListener('imageDragStart', (e) => {
 *      console.log('Drag started:', e.detail);
 *    });
 *
 *    document.addEventListener('imageDrop', (e) => {
 *      console.log('Image dropped:', e.detail);
 *    });
 *
 *    document.addEventListener('imageMoved', (e) => {
 *      console.log('Image moved:', e.detail);
 *    });
 *
 * 2. Programmatically move images:
 *
 *    ProductDrag.moveImage('img_123', 'product_1', 'product_2');
 *
 * 3. Refresh product images:
 *
 *    ProductDrag.refreshImages('product_1');
 *
 * 4. Configure behavior:
 *
 *    ProductDrag.enableBackend(false);  // UI-only mode
 *    ProductDrag.enableToasts(true);    // Show notifications
 *    ProductDrag.enableDebug(true);     // Enable debug logging
 */
