# Product Page UX Redesign - Developer Specification

**Project**: RADS TOOLING E-Commerce
**Scope**: Product listing (public + customer) and product detail page
**Date**: 2025-11-07

---

## 1. BEHAVIOR & UX REQUIREMENTS

### 1.1 Product Cards (Grid View)

**Layout**:
- Desktop (≥1024px): 3 cards per row
- Tablet (768–1023px): 2 cards per row
- Mobile (≤767px): 1 card per row

**Card Structure**:
- Image: Fixed aspect ratio, `object-fit: cover`, 220px height
- Title: Product name, 1 line, truncate with ellipsis if needed
- Short description: 2-3 lines max with `text-overflow: ellipsis`
- Price: Large, bold, high contrast (AA compliant)
- CTAs: "Add to Cart" (secondary), "Buy Now" (primary)

**CSS Classes**:
```css
.product-grid        /* Grid container */
.product-card        /* Individual card */
.product-image       /* Image wrapper */
.product-title       /* Product name */
.product-short-desc  /* Short description (truncated on card) */
.product-price       /* Price display */
.cta-add             /* Add to Cart button */
.cta-buy             /* Buy Now button */
```

---

### 1.2 Product Detail Page

**Layout**:
- **Left Column** (520px): Large image gallery
  - Main image viewer (580px height)
  - Thumbnail carousel below (horizontally scrollable on mobile)
  - Image counter overlay: "1/3" bottom-right
- **Right Column**: Product information
  - Title (large, 34px)
  - Price (36px, bold, blue)
  - **Full description** (no cutoff, white box with padding)
  - Quantity control (increment/decrement)
  - CTAs: "Add to Cart", "Buy Now"

**Back Link**:
- Text: "← Back to Products"
- Style: **No underline**, plain link with chevron icon
- CSS class: `.back-link`

**Image Viewer Modal**:
- Opens when clicking main image
- Features:
  - Large image display (centered, max 90vw/90vh)
  - Close button (X) in **top-right corner**
  - Thumbnails below (clickable)
  - Image counter: "2/5"
  - Dismissible: Esc key, click outside, click X
- Accessibility: `aria-label="Open image gallery"`, `aria-hidden` when closed

**CSS Classes**:
```css
.product-detail      /* Main container */
.gallery-main        /* Main image viewer */
.gallery-thumbs      /* Thumbnail carousel */
.back-link           /* Back to Products link (no underline) */
.image-modal         /* Modal overlay */
.image-modal-close   /* Close button (top-right X) */
```

---

### 1.3 Image Drag & Drop (Product Gallery Management)

**Feature**: Allow dragging an image from one product card to another product

**UI Elements**:
- Draggable images: Add `draggable="true"` attribute
- Drop target: Highlight with border + background when hovering
- Visual feedback: Show "+ icon" or "Drop here" text on valid targets
- Confirmation toast: "Image moved to [Product Name]"

**JavaScript Hooks**:

```javascript
// Event 1: Drag Start
function onImageDragStart({ imageId, fromProductId }) {
  // Called when user starts dragging an image
  // Store imageId and fromProductId in dataTransfer
}

// Event 2: Drop
function onImageDrop({ imageId, toProductId }) {
  // Called when image is dropped on a product card
  // Update UI immediately
  // Call backend API to persist change
}

// Custom DOM Event (for developer integration)
document.dispatchEvent(new CustomEvent('imageMoved', {
  detail: {
    imageId: "123",
    fromProductId: "45",
    toProductId: "67"
  }
}));
```

**Backend API (Optional)**:

**Endpoint**: `POST /api/products/{toProductId}/images/move`

**Request Body**:
```json
{
  "imageId": "img_123",
  "fromProductId": "45"
}
```

**Response** (Success):
```json
{
  "success": true,
  "message": "Image moved successfully",
  "data": {
    "imageId": "img_123",
    "newProductId": "67",
    "newDisplayOrder": 2,
    "images": [
      {
        "imageId": "img_123",
        "productId": "67",
        "url": "/uploads/products/img_123.jpg",
        "displayOrder": 2,
        "isPrimary": false
      }
    ]
  }
}
```

**Response** (Error):
```json
{
  "success": false,
  "error": "Unauthorized",
  "message": "You don't have permission to move this image"
}
```

**Fallback** (No Backend):
- Update UI only
- Store in localStorage for session persistence
- Emit `imageMoved` event for developer to handle saving

---

## 2. VISUAL DESIGN (CSS Mockup)

### 2.1 Color Palette
```css
:root {
  --primary-blue: #2f5b88;
  --text-dark: #17233b;
  --text-muted: #6b7280;
  --bg-light: #f7fafc;
  --card-bg: #ffffff;
  --border-light: #e6eef7;
  --success-green: #3db36b;
  --shadow-sm: 0 6px 18px rgba(15,23,42,0.03);
  --shadow-md: 0 8px 20px rgba(8,15,35,0.06);
}
```

### 2.2 Typography
```css
body {
  font-family: 'Poppins', sans-serif;
  font-size: 14px;
  line-height: 1.6;
  color: var(--text-dark);
}

.product-title {
  font-size: 15px;
  font-weight: 700;
  line-height: 1.3;
}

.product-price {
  font-size: 16px;
  font-weight: 800;
  color: var(--primary-blue);
}

.product-short-desc {
  font-size: 13px;
  color: var(--text-muted);
  line-height: 1.5;
}
```

### 2.3 Spacing & Shadows
```css
.product-card {
  padding: 0;
  border-radius: 12px;
  box-shadow: var(--shadow-md);
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.product-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 28px rgba(8,15,35,0.10);
}

.product-content {
  padding: 14px 18px;
}

.cta-buttons {
  display: flex;
  gap: 10px;
  padding-top: 6px;
}
```

### 2.4 Button Styles
```css
/* Primary CTA: Buy Now */
.cta-buy {
  background: var(--primary-blue);
  color: #fff;
  padding: 8px 18px;
  border-radius: 26px;
  font-weight: 700;
  font-size: 13px;
  border: none;
  cursor: pointer;
  transition: all 0.15s ease;
}

.cta-buy:hover {
  background: #234568;
  transform: translateY(-2px);
}

/* Secondary CTA: Add to Cart */
.cta-add {
  background: #ffffff;
  color: var(--primary-blue);
  border: 1px solid var(--border-light);
  padding: 8px 14px;
  border-radius: 10px;
  font-weight: 600;
  font-size: 13px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  box-shadow: var(--shadow-sm);
  transition: all 0.15s ease;
}

.cta-add:hover {
  background: #f4f7ff;
  border-color: var(--primary-blue);
}
```

---

## 3. IMPLEMENTATION PLAN (Prioritized)

### Phase 1: Quick Wins (CSS Fixes) - 30 mins
1. ✅ Remove underline from `.back-link`
2. ✅ Increase description line-height on product detail
3. ✅ Add subtle shadows to product cards
4. ✅ Improve button spacing and sizing
5. ✅ Responsive breakpoints for grid (3→2→1 columns)

### Phase 2: Image Modal Enhancements - 1 hour
1. ✅ Add close button (X) to top-right of modal
2. ✅ Implement keyboard navigation (←/→ arrows, Esc)
3. ✅ Add image counter overlay
4. ✅ Ensure thumbnails are selectable/highlighted
5. ✅ Add aria-labels for accessibility

### Phase 3: Drag & Drop UI - 2 hours
1. 🔄 Make product images draggable
2. 🔄 Add drop zone indicators
3. 🔄 Implement drag start/end handlers
4. 🔄 Add visual feedback (highlight, cursor)
5. 🔄 Show confirmation toast on drop

### Phase 4: Backend Integration (Optional) - 1 hour
1. 🔄 Create API endpoint `/api/products/{id}/images/move`
2. 🔄 Test with real product data
3. 🔄 Add error handling and rollback

---

## 4. EXAMPLE CODE SNIPPETS

### 4.1 CSS: Remove Underline from Back Link

```css
.back-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--primary-blue);
  font-weight: 600;
  text-decoration: none; /* ✅ No underline */
  transition: color 0.15s ease;
}

.back-link:hover {
  color: #234568;
}

.back-link .material-symbols-rounded {
  font-size: 18px;
}
```

### 4.2 JS: Modal Open/Close with Keyboard Navigation

```javascript
// Modal elements
const imageModal = document.getElementById('imageModal');
const modalImage = document.getElementById('modalImage');
const closeBtn = document.querySelector('.image-modal-close');
let currentImageIndex = 0;
let productImages = [];

// Open modal
function openImageModal(productId, imageIndex = 0) {
  currentImageIndex = imageIndex;

  // Load product images
  fetch(`/api/products/${productId}/images`)
    .then(res => res.json())
    .then(data => {
      productImages = data.images;
      showImage(currentImageIndex);
      imageModal.classList.add('active');
      imageModal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    });
}

// Close modal
function closeImageModal() {
  imageModal.classList.remove('active');
  imageModal.setAttribute('aria-hidden', 'true');
  document.body.style.overflow = '';
  modalImage.src = '';
}

// Show image by index
function showImage(index) {
  if (index < 0) index = 0;
  if (index >= productImages.length) index = productImages.length - 1;

  currentImageIndex = index;
  modalImage.src = productImages[index].url;

  // Update counter
  document.getElementById('imageCounter').textContent =
    `${index + 1} / ${productImages.length}`;

  // Update thumbnail selection
  document.querySelectorAll('.gallery-thumbs img').forEach((thumb, i) => {
    thumb.classList.toggle('active', i === index);
  });
}

// Keyboard navigation
document.addEventListener('keydown', (e) => {
  if (!imageModal.classList.contains('active')) return;

  if (e.key === 'ArrowLeft') {
    showImage(currentImageIndex - 1);
  } else if (e.key === 'ArrowRight') {
    showImage(currentImageIndex + 1);
  } else if (e.key === 'Escape') {
    closeImageModal();
  }
});

// Close button
closeBtn.addEventListener('click', closeImageModal);

// Click outside to close
imageModal.addEventListener('click', (e) => {
  if (e.target === imageModal) {
    closeImageModal();
  }
});

// Thumbnail clicks
document.querySelectorAll('.gallery-thumbs img').forEach((thumb, index) => {
  thumb.addEventListener('click', () => showImage(index));
});
```

### 4.3 JS: Drag & Drop Handlers (Vanilla JS)

```javascript
let draggedImageId = null;
let draggedFromProductId = null;

// Make images draggable
document.querySelectorAll('.product-image img').forEach(img => {
  img.setAttribute('draggable', 'true');

  img.addEventListener('dragstart', (e) => {
    draggedImageId = e.target.dataset.imageId;
    draggedFromProductId = e.target.closest('.product-card').dataset.productId;

    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', e.target.outerHTML);

    // Custom event
    onImageDragStart({ imageId: draggedImageId, fromProductId: draggedFromProductId });

    // Visual feedback
    e.target.style.opacity = '0.5';
  });

  img.addEventListener('dragend', (e) => {
    e.target.style.opacity = '1';
  });
});

// Make product cards drop targets
document.querySelectorAll('.product-card').forEach(card => {
  card.addEventListener('dragover', (e) => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';

    // Visual feedback: highlight drop zone
    card.classList.add('drag-over');
  });

  card.addEventListener('dragleave', (e) => {
    card.classList.remove('drag-over');
  });

  card.addEventListener('drop', (e) => {
    e.preventDefault();
    card.classList.remove('drag-over');

    const toProductId = card.dataset.productId;

    if (toProductId === draggedFromProductId) {
      // Same product, ignore
      return;
    }

    // Custom event
    onImageDrop({ imageId: draggedImageId, toProductId });

    // Call backend API (if exists)
    moveImageToProduct(draggedImageId, draggedFromProductId, toProductId);
  });
});

// Drag start handler
function onImageDragStart({ imageId, fromProductId }) {
  console.log(`Drag started: Image ${imageId} from Product ${fromProductId}`);

  // Dispatch custom event
  document.dispatchEvent(new CustomEvent('imageDragStart', {
    detail: { imageId, fromProductId }
  }));
}

// Drop handler
function onImageDrop({ imageId, toProductId }) {
  console.log(`Dropped: Image ${imageId} to Product ${toProductId}`);

  // Dispatch custom event
  document.dispatchEvent(new CustomEvent('imageDrop', {
    detail: { imageId, toProductId }
  }));
}

// Backend API call
async function moveImageToProduct(imageId, fromProductId, toProductId) {
  try {
    const response = await fetch(`/api/products/${toProductId}/images/move`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        imageId: imageId,
        fromProductId: fromProductId
      })
    });

    const result = await response.json();

    if (result.success) {
      // Success toast
      showToast(`Image moved — saved.`, 'success');

      // Dispatch custom event
      document.dispatchEvent(new CustomEvent('imageMoved', {
        detail: {
          imageId,
          fromProductId,
          toProductId
        }
      }));

      // Refresh UI (re-fetch images for both products)
      refreshProductImages(fromProductId);
      refreshProductImages(toProductId);
    } else {
      showToast(`Failed to move image: ${result.message}`, 'error');
    }
  } catch (error) {
    console.error('API error:', error);

    // Fallback: UI-only update
    showToast(`Image moved (UI only — not saved to server)`, 'warning');

    // Dispatch event anyway
    document.dispatchEvent(new CustomEvent('imageMoved', {
      detail: { imageId, fromProductId, toProductId }
    }));
  }
}

// Toast notification helper
function showToast(message, type = 'info') {
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  toast.style.cssText = `
    position: fixed;
    top: 100px;
    right: 20px;
    background: ${type === 'success' ? '#3db36b' : type === 'error' ? '#e74c3c' : '#2f5b88'};
    color: #fff;
    padding: 1rem 1.5rem;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    z-index: 10000;
    font-weight: 600;
    animation: slideIn 0.3s ease;
  `;

  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 3000);
}
```

### 4.4 CSS: Drag & Drop Visual Feedback

```css
/* Draggable image cursor */
.product-image img[draggable="true"] {
  cursor: grab;
}

.product-image img[draggable="true"]:active {
  cursor: grabbing;
}

/* Drop zone highlight */
.product-card.drag-over {
  border: 2px dashed var(--primary-blue);
  background: rgba(47, 91, 136, 0.05);
  transform: scale(1.02);
  transition: all 0.2s ease;
}

.product-card.drag-over::after {
  content: '+ Drop image here';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: var(--primary-blue);
  color: #fff;
  padding: 12px 24px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  pointer-events: none;
  z-index: 10;
}
```

---

## 5. ACCESSIBILITY NOTES

### 5.1 Image Modal
- **aria-label**: "Open image gallery" on main image
- **aria-hidden**: `true` when modal closed, `false` when open
- **Focus trap**: Ensure focus stays within modal when open
- **Escape key**: Always closes modal
- **Close button tooltip**: "Close (Esc)"

### 5.2 Keyboard Navigation
- **Arrow keys**: Navigate between images (←/→)
- **Tab**: Cycle through thumbnails and close button
- **Enter/Space**: Activate focused thumbnail or close button

### 5.3 Screen Reader Announcements
```html
<div role="dialog" aria-labelledby="modalTitle" aria-describedby="modalDesc">
  <h2 id="modalTitle" class="sr-only">Product Image Gallery</h2>
  <p id="modalDesc" class="sr-only">Use arrow keys to navigate images. Press Escape to close.</p>
  <img src="..." alt="Product image 1 of 5">
</div>
```

### 5.4 Color Contrast
- Primary blue (#2f5b88) on white: **7.5:1** (AAA)
- Price text (bold #2f5b88): **7.5:1** (AAA)
- Muted text (#6b7280): **4.6:1** (AA)
- Buttons have minimum 4.5:1 contrast ratio

---

## 6. RESPONSIVE BEHAVIOR

### 6.1 Breakpoints
```css
/* Mobile: ≤767px */
@media (max-width: 767px) {
  .product-grid { grid-template-columns: 1fr; }
  .product-detail-container { grid-template-columns: 1fr; }
  .gallery-thumbs { overflow-x: auto; }
  .cta-buttons { flex-direction: column; gap: 8px; }
}

/* Tablet: 768px–1023px */
@media (min-width: 768px) and (max-width: 1023px) {
  .product-grid { grid-template-columns: repeat(2, 1fr); }
}

/* Desktop: ≥1024px */
@media (min-width: 1024px) {
  .product-grid { grid-template-columns: repeat(3, 1fr); }
  .product-detail-container {
    grid-template-columns: 520px 1fr;
    max-width: 1200px;
  }
}
```

### 6.2 Mobile Optimizations
- Thumbnails: Horizontally scrollable carousel
- Main image: Full width on mobile, stack above details
- Buttons: Stack vertically with full width
- Modal: 95vw width, reduced padding

---

## 7. TESTING CHECKLIST

### UX
- [ ] Product cards display correctly at all breakpoints
- [ ] Description truncates properly (no overflow)
- [ ] Images load with correct aspect ratio
- [ ] Hover states work on all interactive elements

### Image Modal
- [ ] Modal opens when clicking main image
- [ ] Close button (X) is visible in top-right
- [ ] Arrow keys navigate between images
- [ ] Escape key closes modal
- [ ] Click outside closes modal
- [ ] Image counter updates correctly
- [ ] Thumbnails highlight current image

### Drag & Drop
- [ ] Images are draggable
- [ ] Drop zones highlight when hovering
- [ ] Toast appears on successful drop
- [ ] API call succeeds (or graceful fallback)
- [ ] UI updates immediately
- [ ] Custom events fire correctly

### Accessibility
- [ ] All images have alt text
- [ ] Buttons have aria-labels
- [ ] Modal is keyboard-navigable
- [ ] Focus trap works in modal
- [ ] Screen reader announces image changes

### Performance
- [ ] Images lazy-load
- [ ] Drag events don't cause lag
- [ ] Modal animates smoothly (60fps)
- [ ] No layout shift on image load

---

## 8. DEPLOYMENT NOTES

### Files Modified
1. `/customer/product_detail.php` - Add close button, keyboard nav
2. `/customer/products.php` - Add drag/drop handlers
3. `/public/products.php` - Add drag/drop handlers
4. `/assets/CSS/product.css` - Visual improvements
5. `/assets/JS/product-modal.js` - New file for modal logic
6. `/assets/JS/product-drag.js` - New file for drag/drop logic

### Backend (Optional)
- Create `/backend/api/product_images.php` with `move` action
- Ensure proper auth checks before moving images
- Return updated image list after move

### Browser Support
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile browsers (iOS 14+, Android Chrome)

---

**End of Specification**
