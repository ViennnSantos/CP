/**
 * RADS TOOLING - Product Image Modal
 * Enhanced image viewer with keyboard navigation, thumbnails, and accessibility
 *
 * Features:
 * - Click main image to open modal
 * - Keyboard navigation (Arrow keys, Escape)
 * - Thumbnail selection
 * - Close button (top-right X)
 * - Click outside to close
 * - Image counter overlay
 * - Accessibility (aria-labels, focus trap)
 */

(function() {
  'use strict';

  // ==================== STATE ====================
  let currentImageIndex = 0;
  let productImages = [];

  // ==================== DOM ELEMENTS ====================
  const imageModal = document.getElementById('imageModal');
  const modalImage = document.getElementById('modalImage');
  const mainImageWrapper = document.getElementById('mainImageWrapper');
  const mainImage = document.getElementById('mainImage');
  const thumbnailGallery = document.getElementById('thumbnailGallery');

  if (!imageModal || !modalImage) {
    console.warn('Product modal elements not found');
    return;
  }

  // ==================== HELPER FUNCTIONS ====================

  /**
   * Get all thumbnail elements
   */
  function getThumbnails() {
    return Array.from(document.querySelectorAll('.thumbnail'));
  }

  /**
   * Set active state on thumbnail
   */
  function setActiveThumbnail(index) {
    const thumbnails = getThumbnails();
    thumbnails.forEach((thumb, i) => {
      if (i === index) {
        thumb.classList.add('active');
        thumb.setAttribute('aria-selected', 'true');
      } else {
        thumb.classList.remove('active');
        thumb.setAttribute('aria-selected', 'false');
      }
    });
  }

  /**
   * Show image by index
   */
  function showImageByIndex(index) {
    const thumbnails = getThumbnails();
    if (!thumbnails.length) return;

    // Clamp index
    if (index < 0) index = 0;
    if (index >= thumbnails.length) index = thumbnails.length - 1;

    const thumb = thumbnails[index];
    const src = thumb.dataset.src || thumb.src;
    if (!src) return;

    // Update main image
    if (mainImage) {
      mainImage.src = src;
      mainImage.alt = thumb.alt || `Product image ${index + 1}`;
    }

    // Update modal image
    if (modalImage) {
      modalImage.src = src;
      modalImage.alt = thumb.alt || `Product image ${index + 1}`;
    }

    currentImageIndex = index;

    // Update counter
    const counter = document.getElementById('currentImageNum');
    if (counter) {
      counter.textContent = (index + 1);
    }

    // Update thumbnails
    setActiveThumbnail(index);

    // Scroll thumbnail into view
    try {
      thumb.scrollIntoView({
        behavior: 'smooth',
        block: 'nearest',
        inline: 'center'
      });
    } catch (e) {
      // Fallback for older browsers
      thumb.scrollIntoView(false);
    }

    // Announce to screen readers
    announceImageChange(index + 1, thumbnails.length);
  }

  /**
   * Announce image change to screen readers
   */
  function announceImageChange(current, total) {
    const announcement = document.createElement('div');
    announcement.setAttribute('role', 'status');
    announcement.setAttribute('aria-live', 'polite');
    announcement.className = 'sr-only';
    announcement.textContent = `Image ${current} of ${total}`;
    document.body.appendChild(announcement);
    setTimeout(() => announcement.remove(), 1000);
  }

  /**
   * Open image modal
   */
  function openImageModal(productId, imageIndex = 0) {
    if (!imageModal || !modalImage) return;

    currentImageIndex = imageIndex;

    // Get current main image source
    const currentSrc = mainImage ? mainImage.src : '';
    if (currentSrc) {
      modalImage.src = currentSrc;
      modalImage.alt = mainImage ? mainImage.alt : 'Product image';
    }

    // Show modal
    imageModal.classList.add('active');
    imageModal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';

    // Focus close button
    const closeBtn = imageModal.querySelector('.image-modal-close');
    if (closeBtn) {
      setTimeout(() => closeBtn.focus(), 100);
    }

    // Dispatch custom event
    document.dispatchEvent(new CustomEvent('imageModalOpened', {
      detail: { productId, imageIndex }
    }));
  }
  // Expose globally
  window.openImageModal = openImageModal;

  /**
   * Close image modal
   */
  function closeImageModal() {
    if (!imageModal) return;

    imageModal.classList.remove('active');
    imageModal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';

    // Clear modal image (save memory)
    if (modalImage) {
      setTimeout(() => {
        modalImage.src = '';
      }, 300); // Wait for fade animation
    }

    // Return focus to main image
    if (mainImageWrapper) {
      mainImageWrapper.focus();
    }

    // Dispatch custom event
    document.dispatchEvent(new CustomEvent('imageModalClosed'));
  }
  // Expose globally
  window.closeImageModal = closeImageModal;

  /**
   * Navigate to previous image
   */
  function previousImage() {
    showImageByIndex(currentImageIndex - 1);
  }

  /**
   * Navigate to next image
   */
  function nextImage() {
    showImageByIndex(currentImageIndex + 1);
  }

  // ==================== EVENT HANDLERS ====================

  /**
   * Main image click - open modal
   */
  if (mainImageWrapper) {
    mainImageWrapper.addEventListener('click', function(e) {
      e.preventDefault();
      openImageModal(null, currentImageIndex);
    });

    // Make keyboard accessible
    mainImageWrapper.setAttribute('tabindex', '0');
    mainImageWrapper.setAttribute('role', 'button');
    mainImageWrapper.setAttribute('aria-label', 'Open image gallery');

    mainImageWrapper.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openImageModal(null, currentImageIndex);
      }
    });
  }

  /**
   * Thumbnail clicks
   */
  if (thumbnailGallery) {
    thumbnailGallery.addEventListener('click', function(e) {
      const thumb = e.target.closest('.thumbnail');
      if (!thumb) return;

      const index = parseInt(thumb.dataset.index || '0', 10);
      showImageByIndex(isNaN(index) ? 0 : index);
    });

    // Keyboard navigation for thumbnails
    thumbnailGallery.addEventListener('keydown', function(e) {
      const thumb = e.target.closest('.thumbnail');
      if (!thumb) return;

      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        const index = parseInt(thumb.dataset.index || '0', 10);
        showImageByIndex(isNaN(index) ? 0 : index);
      }
    });

    // Make thumbnails keyboard accessible
    getThumbnails().forEach((thumb, index) => {
      thumb.setAttribute('tabindex', '0');
      thumb.setAttribute('role', 'button');
      thumb.setAttribute('aria-label', `View image ${index + 1}`);
    });
  }

  /**
   * Keyboard navigation
   */
  document.addEventListener('keydown', function(e) {
    // Don't interfere with form inputs
    const activeTag = document.activeElement?.tagName?.toLowerCase();
    if (activeTag === 'input' || activeTag === 'textarea' || activeTag === 'select') {
      return;
    }

    // Arrow key navigation (works both in and out of modal)
    if (e.key === 'ArrowLeft') {
      e.preventDefault();
      previousImage();
    } else if (e.key === 'ArrowRight') {
      e.preventDefault();
      nextImage();
    }

    // Escape to close modal
    if (e.key === 'Escape') {
      const modalIsOpen = imageModal && imageModal.classList.contains('active');
      if (modalIsOpen) {
        e.preventDefault();
        closeImageModal();
      }
    }
  });

  /**
   * Click outside modal to close
   */
  if (imageModal) {
    imageModal.addEventListener('click', function(e) {
      if (e.target === imageModal) {
        closeImageModal();
      }
    });
  }

  /**
   * Close button click
   */
  const closeButtons = document.querySelectorAll('.image-modal-close, [data-modal-close]');
  closeButtons.forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      closeImageModal();
    });

    // Add aria-label
    if (!btn.getAttribute('aria-label')) {
      btn.setAttribute('aria-label', 'Close image gallery (Esc)');
    }
  });

  /**
   * Modal navigation arrows
   */
  const prevBtn = document.querySelector('.modal-nav-prev');
  const nextBtn = document.querySelector('.modal-nav-next');

  if (prevBtn) {
    prevBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      previousImage();
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      nextImage();
    });
  }

  /**
   * Modal thumbnail clicks
   */
  document.addEventListener('click', function(e) {
    const modalThumb = e.target.closest('.modal-thumb');
    if (!modalThumb) return;

    const index = parseInt(modalThumb.dataset.index || '0', 10);
    showImageByIndex(isNaN(index) ? 0 : index);
  });

  // ==================== INITIALIZATION ====================

  /**
   * Initialize on page load
   */
  document.addEventListener('DOMContentLoaded', function() {
    // Ensure modal is hidden on initial load
    if (imageModal) {
      imageModal.classList.remove('active');
      imageModal.setAttribute('aria-hidden', 'true');
    }

    // Ensure body overflow is reset
    document.body.style.overflow = '';

    // Set initial thumbnail as active
    setActiveThumbnail(0);

    console.log('Product modal initialized');
  });

  // ==================== FOCUS TRAP ====================

  /**
   * Trap focus within modal when open
   */
  function trapFocus(element) {
    const focusableElements = element.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );

    const firstFocusable = focusableElements[0];
    const lastFocusable = focusableElements[focusableElements.length - 1];

    element.addEventListener('keydown', function(e) {
      const isTabPressed = e.key === 'Tab';
      if (!isTabPressed) return;

      if (e.shiftKey) {
        // Shift + Tab
        if (document.activeElement === firstFocusable) {
          e.preventDefault();
          lastFocusable.focus();
        }
      } else {
        // Tab
        if (document.activeElement === lastFocusable) {
          e.preventDefault();
          firstFocusable.focus();
        }
      }
    });
  }

  if (imageModal) {
    trapFocus(imageModal);
  }

  // ==================== EXPOSE PUBLIC API ====================

  window.ProductModal = {
    open: openImageModal,
    close: closeImageModal,
    showImage: showImageByIndex,
    next: nextImage,
    previous: previousImage,
    getCurrentIndex: () => currentImageIndex
  };

})();
