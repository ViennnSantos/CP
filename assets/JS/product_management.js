// admin/assets/JS/product_management.js
// Full rewrite (minimal behavior changes) - fixes: image paths, placeholders, fetch credentials, safe fallbacks
// Also: ensures product list uses the product_images primary image when available (keeps edit modal behavior)

// ===== Global state =====
let allProducts = [];
let allTextures = [];
let allColors = [];
let allHandles = [];
let currentProduct = null;             // used by customization / edit
const MAX_GLB_MB = 80;                 // front-end cap for .glb

// ===== Image / Model upload state =====
let uploadedProductImages = [];

// ===== Size config persistence =====
let savedSizeConfig = null;

// cache for primary images to avoid repeated calls
const productPrimaryImageCache = new Map(); // productId -> filename (string) or null

// ===== Boot =====
document.addEventListener('DOMContentLoaded', () => {
  injectPMStyles();
  initPMModalNotifier();
  initConfirmationModal();
  hideAddProductFields();

  loadProducts();
  loadTextures();
  loadColors();
  loadHandles();
  initializeEventListeners();

  updateModelRequirement();

  const addModal = document.getElementById('addProductModal');
  if (addModal) {
    addModal.addEventListener('click', (e) => {
      if (e.target === addModal) e.stopPropagation();
    }, true);
  }

  const customModal = document.getElementById('manageCustomizationModal');
  if (customModal) {
    customModal.addEventListener('click', (e) => {
      if (e.target === customModal) e.stopPropagation();
    }, true);
  }

  const saveCustomBtn = document.querySelector('#manageCustomizationModal .btn-primary');
  if (saveCustomBtn && !saveCustomBtn.hasAttribute('data-listener-added')) {
    saveCustomBtn.setAttribute('data-listener-added', 'true');
    saveCustomBtn.addEventListener('click', async (e) => {
      e.preventDefault();
      e.stopPropagation();
      await saveCustomizationOptions();
    });
  }
});

// ===== Helpers =====
function normalizeSrc(s) {
  if (!s) return s;
  return String(s).replace(/\/+$/, ''); // remove trailing slashes
}

async function fetchJSON(url, opts = {}) {
  // ensure cookies/session are sent for same-origin admin API calls
  const fetchOpts = Object.assign({}, opts, { credentials: opts?.credentials ?? 'same-origin' });
  fetchOpts.headers = Object.assign({ 'Accept': 'application/json' }, fetchOpts.headers || {});
  const res = await fetch(url, fetchOpts);
  const text = await res.text().catch(() => '');
  try {
    const data = text ? JSON.parse(text) : {};
    return data;
  } catch (err) {
    console.error('Invalid JSON from', url, text);
    return { success: false, message: 'Invalid response' };
  }
}

function hideAddProductFields() {
  ['stockGroup', 'stockField', 'stockRow'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
  });
  const stockInput = document.getElementById('productStock');
  if (stockInput) { stockInput.value = '0'; stockInput.disabled = true; }

  ['sizeConfigSection', 'sizeSliderSection', 'sizeConfigCard'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
  });
}

// ===== Events =====
function initializeEventListeners() {
  document.getElementById('product-search')?.addEventListener('input', filterProducts);
  document.getElementById('product-filter')?.addEventListener('change', filterProducts);

  document.getElementById('addProductForm')?.addEventListener('submit', handleAddProduct);

  document.getElementById('productImage')?.addEventListener('change', handleImagePreview);
  document.getElementById('productModel')?.addEventListener('change', handleModelPreview);

  document.getElementById('isCustomizable')?.addEventListener('change', updateModelRequirement);

  const cancelBtn =
    document.getElementById('addProductCancelBtn') ||
    document.querySelector('#addProductModal .btn-cancel') ||
    document.querySelector('#addProductModal [data-role="cancelAddProduct"]');

  if (cancelBtn) {
    cancelBtn.addEventListener('click', (e) => {
      e.preventDefault();
      resetAddProductForm(true);
      showNotification('info', 'New order has been canceled');
    });
  }

  document.querySelector('.btn-add-product')?.addEventListener('click', (e) => {
    e.preventDefault();
    openAddProductFresh();
  });

  document.getElementById('btnToggleAvailability')?.addEventListener('click', async (e) => {
    e.preventDefault();
    if (!currentProduct) return;
    const makeAvailable = currentProduct.is_available == 0 ? 1 : 0;
    await toggleAvailability(currentProduct.id, makeAvailable);
  });

  // Event delegation for customize buttons so re-rendering isn't a problem
  const productTableBody = document.getElementById('productTableBody');
  if (productTableBody) {
    if (!productTableBody._pm_custom_delegate_added) {
      productTableBody.addEventListener('click', function (e) {
        const btn = e.target.closest?.('.btn-customize');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        const productId = btn.getAttribute('data-product-id');
        if (productId) {
          console.log('🎨 (delegated) Opening customization modal for product:', productId);
          openCustomizationModal(parseInt(productId));
        }
      });
      productTableBody._pm_custom_delegate_added = true;
    }
  }
}

// ===== Fetch data =====
async function loadProducts() {
  try {
    const data = await fetchJSON('/backend/api/admin_products.php?action=list');
    allProducts = data.data || [];
    displayProducts(allProducts);
  } catch (err) {
    console.error('Load products error:', err);
    showNotification('error', 'Failed to load products');
  }
}
async function loadTextures() {
  try {
    const d = await fetchJSON('/backend/api/admin_customization.php?action=list_textures');
    allTextures = d.data || [];
    if (allTextures.length > 0) console.log('📦 Texture data structure:', allTextures[0]);
  } catch (e) { console.warn('Failed to load textures:', e); }
}
async function loadColors() {
  try {
    const d = await fetchJSON('/backend/api/admin_customization.php?action=list_colors');
    allColors = d.data || [];
  } catch (e) { console.warn(e); }
}
async function loadHandles() {
  try {
    const d = await fetchJSON('/backend/api/admin_customization.php?action=list_handles');
    allHandles = d.data || [];
    if (allHandles.length > 0) console.log('🔧 Handle data structure:', allHandles[0]);
  } catch (e) { console.warn('Failed to load handles:', e); }
}

// ===== Primary image helper =====
/**
 * Fetch product_images list for product and determine primary image filename (no path).
 * Caches the result in productPrimaryImageCache.
 */
async function fetchPrimaryImageForProduct(productId) {
  if (!productId) return null;
  if (productPrimaryImageCache.has(productId)) return productPrimaryImageCache.get(productId);

  try {
    const resp = await fetch(`/backend/api/product_images.php?action=list&product_id=${productId}`, {
      credentials: 'same-origin'
    });
    const js = await resp.json().catch(() => ({ success: false }));
    if (!js.success) {
      productPrimaryImageCache.set(productId, null);
      return null;
    }
    const imgs = js.data?.images || js.data || [];
    if (!Array.isArray(imgs) || imgs.length === 0) {
      productPrimaryImageCache.set(productId, null);
      return null;
    }
    // prefer is_primary === 1
    let primary = imgs.find(i => Number(i.is_primary) === 1);
    if (!primary) {
      // fallback to smallest display_order or first
      primary = imgs.slice().sort((a, b) => (Number(a.display_order || 0) - Number(b.display_order || 0)))[0];
    }
    const filename = primary ? String(primary.image_path || primary.path || primary.file || primary.filename || '').split('/').pop() : null;
    productPrimaryImageCache.set(productId, filename);
    return filename;
  } catch (err) {
    console.error('Error fetching primary image for', productId, err);
    productPrimaryImageCache.set(productId, null);
    return null;
  }
}

// ===== Render =====
function displayProducts(products) {
  const tbody = document.getElementById('productTableBody');
  if (!tbody) return;

  if (!products.length) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">No products found</td></tr>';
    return;
  }

  // Build each row but attach data-product-id on the img for later patching (if needed).
  tbody.innerHTML = products.map((product) => {
    // Build image src safely from whatever product.image holds
    let imgSrc = '/uploads/products/placeholder.jpg';
    if (product.image) {
      const raw = String(product.image || '');
      if (raw.startsWith('uploads/')) {
        imgSrc = `/${normalizeSrc(raw)}`;
      } else {
        imgSrc = `/uploads/products/${normalizeSrc(raw)}`;
      }
    }

    const alt = (product.name || '').replace(/"/g, '&quot;');

    return `
      <tr data-product-id="${product.id}">
        <td>
          <img 
            src="${imgSrc}"
            alt="${alt}"
            class="product-img"
            data-product-id="${product.id}"
            onerror="this.onerror=null; this.src='/uploads/products/placeholder.jpg'">
        </td>
        <td><strong>${product.name}</strong></td>
        <td><span class="badge badge-info">${product.type}</span></td>
        <td>${product.description || 'N/A'}</td>
        <td>₱${parseFloat(product.price || 0).toFixed(2)}</td>
        <td>
          <span class="badge ${Number(product.is_customizable) === 1 ? 'badge-success' : 'badge-secondary'}">
            ${Number(product.is_customizable) === 1 ? 'Yes' : 'No'}
          </span>
        </td>
        <td>
          <span class="badge ${product.status === 'released' ? 'badge-active' : 'badge-inactive'}">
            ${product.status === 'released' ? 'released' : 'draft'}
          </span>
        </td>
        <td>
          <button class="btn-edit" title="Edit Product" onclick="handleEditProduct(${product.id})">
            <span class="material-symbols-rounded">edit</span>
          </button>
          ${Number(product.is_customizable) === 1 ? `
            <button class="btn-edit btn-customize" title="Manage Customization" data-product-id="${product.id}">
              <span class="material-symbols-rounded">tune</span>
            </button>` : ''}
          <button 
            class="btn-edit" 
            title="${product.status === 'released' ? 'Set to Draft' : 'Release Product'}" 
            onclick="toggleProductRelease(${product.id}, '${product.status === 'released' ? 'draft' : 'released'}')">
            <span class="material-symbols-rounded">
              ${product.status === 'released' ? 'visibility_off' : 'visibility'}
            </span>
          </button>
        </td>
      </tr>
    `;
  }).join('');

  // After initial render: ensure table image matches DB primary if product.image is absent or placeholder
  // We'll update only rows where product.image was empty/placeholder OR where cache shows a different primary
  const imgs = Array.from(document.querySelectorAll('#productTableBody img.product-img'));
  imgs.forEach(async (imgEl) => {
    try {
      const pid = Number(imgEl.dataset.productId);
      if (!pid) return;

      // quick check: if src already points to a concrete upload and doesn't equal placeholder, still check cache
      const src = imgEl.getAttribute('src') || '';
      const isPlaceholder = src.endsWith('placeholder.jpg') || src.includes('/placeholder');
      const cached = productPrimaryImageCache.get(pid);

      // If we already have cache and src matches, skip fetch
      if (cached && src.endsWith(cached)) return;

      // If src is placeholder or product.image absent, fetch primary
      // Also fetch when cache undefined (first time)
      const primaryFn = await fetchPrimaryImageForProduct(pid);
      if (primaryFn) {
        const newSrc = `/uploads/products/${primaryFn}`;
        // only update if different
        if ((imgEl.getAttribute('src') || '') !== newSrc) {
          imgEl.src = newSrc;
        }
      }
    } catch (err) {
      console.error('Error patching table image:', err);
    }
  });
}

// ===== Filter =====
function filterProducts() {
  const searchTerm = document.getElementById('product-search')?.value?.toLowerCase() || '';
  const filterType = document.getElementById('product-filter')?.value || '';

  const filtered = allProducts.filter(p => {
    const matchesSearch = !searchTerm ||
      (p.name?.toLowerCase().includes(searchTerm)) ||
      (p.description?.toLowerCase().includes(searchTerm));

    const matchesType = !filterType || p.type === filterType;

    return matchesSearch && matchesType;
  });

  displayProducts(filtered);
}

// ===== Utilities (safe fallbacks if not present) =====
function showNotification(type, message) {
  const c = document.getElementById('toastContainer');
  if (!c) return alert(message);
  const t = document.createElement('div');
  t.className = 'toast show toast-' + (type || 'info');
  t.textContent = message;
  if (type === 'success') t.style.background = '#28a745';
  else if (type === 'error') t.style.background = '#dc3545';
  else t.style.background = '#0dcaf0';
  c.appendChild(t);
  setTimeout(() => { t.classList.remove('show'); t.remove(); }, 3500);
}

// ===== Add/Edit Product (refactored, copy/paste) =====

/**
 * Open Add Product modal — fresh (ADD mode).
 * Ensures button text + form dataset indicate ADD explicitly.
 * FIX: Force button text to always show "Add Product" when adding new product
 */
function openAddProductFresh() {
  // Reset form fields but do NOT close modal (we're opening it)
  resetAddProductForm(false);

  // Title
  const titleEl = document.getElementById('addProductModalTitle');
  if (titleEl) titleEl.textContent = 'Add New Product';

  // FIX: Multiple fallback selectors to find the submit button
  const submitBtn = document.getElementById('addProductSubmitBtn') ||
    document.querySelector('#addProductModal button[type="submit"]') ||
    document.querySelector('#addProductModal .btn-primary') ||
    document.querySelector('button[onclick*="handleAddProduct"]');

  if (submitBtn) {
    submitBtn.textContent = 'Add Product';
    submitBtn.innerText = 'Add Product'; // backup property
    submitBtn.dataset.mode = 'add'; // explicit mode marker
    // Remove any update-related classes/attributes
    submitBtn.classList.remove('btn-update');
    submitBtn.classList.add('btn-add');
  }

  // Clear edit state on form dataset (so form behaves as ADD not EDIT)
  const form = document.getElementById('addProductForm');
  if (form) {
    delete form.dataset.editingId;
    form.removeAttribute('data-editing-id');
    form.dataset.mode = 'add'; // explicit mode marker for form
  }

  // Clear global edit state and uploaded images (fresh start)
  window.currentProduct = null;
  uploadedProductImages = [];

  // Clear preview container if any
  const previewContainer = document.getElementById('imagePreviewContainer');
  if (previewContainer) previewContainer.innerHTML = '';

  // Finally open modal
  openModal('addProductModal');

  // FIX: Force re-check button text after modal opens (in case of CSS/JS interference)
  setTimeout(() => {
    const btn = document.getElementById('addProductSubmitBtn') ||
      document.querySelector('#addProductModal button[type="submit"]');
    if (btn && btn.textContent !== 'Add Product') {
      btn.textContent = 'Add Product';
      btn.innerText = 'Add Product';
    }
  }, 100);
}

/**
 * Load product data into the form and open Edit modal (EDIT mode).
 * id: product id to fetch
 */
async function handleEditProduct(id) {
  try {
    if (!id) throw new Error('Invalid product id');

    const data = await fetchJSON(`/backend/api/admin_products.php?action=view&id=${id}`);

    // DEBUG: useful for troubleshooting — remove when stable
    console.log('DEBUG: view product response', data);

    if (!data) throw new Error('No response from server');
    if (data && data.success === false) {
      throw new Error(data.message || 'Server returned an error while fetching product');
    }
    if (!data.data) throw new Error('No product data returned from server');

    const product = data.data;
    window.currentProduct = product;

    // Title
    const titleEl = document.getElementById('addProductModalTitle');
    if (titleEl) titleEl.textContent = 'Edit Product';

    // Submit button -> Update Product
    // Submit button -> Update Product
    // FIX: Multiple fallback selectors para sure na makuha natin yung button
    const submitBtn = document.getElementById('addProductSubmitBtn') ||
      document.querySelector('#addProductModal button[type="submit"]') ||
      document.querySelector('#addProductModal .btn-primary') ||
      document.querySelector('#addProductForm button[type="submit"]');

    if (submitBtn) {
      submitBtn.textContent = 'Update Product';
      submitBtn.innerText = 'Update Product'; // backup property
      submitBtn.dataset.mode = 'edit';
      submitBtn.classList.remove('btn-add');
      submitBtn.classList.add('btn-update');
    }

    // Set form dataset to edit mode and attach editing id
    const form = document.getElementById('addProductForm');
    if (form) {
      form.dataset.editingId = String(id);
      form.dataset.mode = 'edit';
    }

    // Populate fields
    setField('productName', product.name);
    setField('productType', product.type);
    setField('productDescription', product.description);
    setField('productPrice', product.price);
    setField('isCustomizable', Number(product.is_customizable) === 1, true);

    // Reset uploaded images array and load existing images into preview
    uploadedProductImages = [];
    await loadExistingProductImages(id);

    // If there's a 3D model, show it
    if (product.model_3d) {
      const modelPrev = document.getElementById('productModelPreview');
      if (modelPrev) {
        modelPrev.textContent = `📦 Current: ${product.model_3d}`;
        modelPrev.style.display = 'block';
        modelPrev.dataset.filename = product.model_3d;
      }
    } else {
      const modelPrev = document.getElementById('productModelPreview');
      if (modelPrev) {
        modelPrev.textContent = '';
        modelPrev.style.display = 'none';
        delete modelPrev.dataset.filename;
      }
    }

    // Open modal AFTER all dataset flags are set
    openModal('addProductModal');

    // 🔥 FIX: Force button text update after modal opens (in case of CSS/JS interference)
    setTimeout(() => {
      const btn = document.getElementById('addProductSubmitBtn') ||
        document.querySelector('#addProductModal button[type="submit"]') ||
        document.querySelector('#addProductModal .btn-primary');
      if (btn && btn.textContent !== 'Update Product') {
        btn.textContent = 'Update Product';
        btn.innerText = 'Update Product';
      }
    }, 100);

    // Optional: small sanity log
    // console.log('OPEN EDIT: form.dataset =', form?.dataset);
  } catch (err) {
    console.error('Edit product error:', err);
    showNotification('error', `Failed to load product details: ${err.message || 'Unknown error'}`);
  }
}

/**
 * Submit handler for add/edit product form. Mode-driven: uses form.dataset.mode.
 */
async function handleAddProduct(e) {
  e.preventDefault();

  const form = e.target;
  if (!form) {
    showNotification('error', 'Form not found');
    return;
  }

  // Use explicit mode attribute on form as source-of-truth
  const mode = (form.dataset && form.dataset.mode) ? String(form.dataset.mode) : (form.dataset.editingId ? 'edit' : 'add');
  const isEdit = mode === 'edit';
  const editingId = form.dataset.editingId ? String(form.dataset.editingId) : null;

  // Required inputs
  const nameEl = document.getElementById('productName');
  const typeEl = document.getElementById('productType');
  const priceEl = document.getElementById('productPrice');

  const name = nameEl?.value?.trim() || '';
  const type = typeEl?.value || '';
  const price = priceEl?.value || 0;

  if (!name || !type || !price) {
    showNotification('error', 'Please fill in all required fields');
    return;
  }

  // Choose primary image filename (first uploaded or existing product image if editing)
  let imageFilename = '';
  if (Array.isArray(window.uploadedProductImages) && window.uploadedProductImages.length > 0) {
    imageFilename = window.uploadedProductImages[0];
  } else if (isEdit && window.currentProduct?.image) {
    imageFilename = String(window.currentProduct.image).split('/').pop();
  }

  const modelPrev = document.getElementById('productModelPreview');
  const modelFilename = modelPrev?.dataset?.filename || (isEdit ? (window.currentProduct?.model_3d || '') : '');

  const payload = {
    name,
    type,
    description: document.getElementById('productDescription')?.value?.trim() || '',
    price: parseFloat(price) || 0,
    is_customizable: document.getElementById('isCustomizable')?.checked ? 1 : 0,
    image: imageFilename ? `uploads/products/${imageFilename}` : '',
    model_3d: modelFilename || null
  };

  try {
    let productId;

    if (isEdit) {
      // Update endpoint (edit)
      const endpoint = `/backend/api/admin_products.php?action=update&id=${encodeURIComponent(editingId)}`;
      const resp = await fetchJSON(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      if (!resp || resp.success !== true) {
        const msg = resp?.message || 'Failed to update product';
        throw new Error(msg);
      }

      productId = editingId; // keep as string or number; used later

      // ===== Sync images: insert new ones only =====
      if (Array.isArray(window.uploadedProductImages) && window.uploadedProductImages.length > 0) {
        try {
          // 1) fetch existing images list for this product
          const existingResponse = await fetch(`/backend/api/product_images.php?action=list&product_id=${productId}`, {
            credentials: 'same-origin'
          });
          const existingResult = await existingResponse.json().catch(() => ({ success: false, data: [] }));
          const existingImages = existingResult.data?.images || existingResult.data || [];

          // 2) normalize existing filenames
          const existingFilenames = existingImages.map(img => {
            const path = img.image_path || img.path || img.filename || '';
            return String(path).split('/').pop();
          }).filter(Boolean);

          console.log('Existing images in DB:', existingFilenames);
          console.log('Uploaded images:', window.uploadedProductImages);

          // 3) detect which uploaded images are NEW
          const newImages = window.uploadedProductImages.filter(filename => !existingFilenames.includes(filename));
          console.log('New images to insert:', newImages);

          // 4) insert only new ones (start order after existing ones)
          if (newImages.length > 0) {
            const startOrder = existingFilenames.length || 0;
            await uploadImagesToProductImagesTable(productId, newImages, startOrder);
          } else {
            console.log('No new images detected — skipping insert.');
          }
        } catch (err) {
          console.error('❌ Error syncing product images:', err);
          showNotification('error', 'Error while saving product images.');
        }
      }
    } else {
      // Add endpoint (create)
      const endpoint = '/backend/api/admin_products.php?action=add';
      const result = await fetchJSON(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      // explicit server-side success check
      if (!result || result.success !== true) {
        const msg = result?.message || 'Server refused to add product';
        throw new Error(msg);
      }

      productId = Number(result.data?.id || 0);
      if (!productId) throw new Error('Failed to obtain new product id (no id returned)');

      // If there are uploaded images, insert them
      if (Array.isArray(window.uploadedProductImages) && window.uploadedProductImages.length > 0) {
        await uploadImagesToProductImagesTable(productId, window.uploadedProductImages, 0);
      }
    }

    // clear cache for this product so next listing refresh will fetch current primary
    if (productId) productPrimaryImageCache.delete(Number(productId));

    showNotification('success', isEdit ? 'Product updated successfully' : 'Product added successfully');
    closeModal('addProductModal');
    resetAddProductForm(true);
    await loadProducts();
  } catch (err) {
    console.error('Save product error:', err);
    const msg = err.message || 'Failed to save product';
    showNotification('error', msg);
  }
}


/**
 * Upload / insert images into product_images table using insert_direct endpoint.
 * Expects filenames (strings) from uploads/products/ (no leading slash).
 */
/**
 * Upload / insert images into product_images table using insert_direct endpoint.
 * Expects filenames (strings) from uploads/products/ (no leading slash).
 * FIX: Check for duplicates before inserting to prevent same image appearing multiple times
 */
async function uploadImagesToProductImagesTable(productId, imageFilenames = [], startDisplayOrder = 0) {
  if (!productId) throw new Error('Invalid product id for image insert');
  if (!Array.isArray(imageFilenames) || imageFilenames.length === 0) return { successCount: 0, errorCount: 0 };

  console.log(`📤 Inserting ${imageFilenames.length} image(s) for product ${productId} starting at order ${startDisplayOrder}`);

  let successCount = 0;
  let errorCount = 0;

  // FIX: Get existing images first to avoid duplicates
  let existingImages = [];
  try {
    const existingResp = await fetch(`/backend/api/product_images.php?action=list&product_id=${productId}`, {
      credentials: 'same-origin'
    });
    const existingResult = await existingResp.json().catch(() => ({ success: false, data: [] }));
    existingImages = (existingResult.data?.images || existingResult.data || []).map(img => {
      const path = img.image_path || img.path || img.filename || '';
      return String(path).split('/').pop();
    }).filter(Boolean);
  } catch (err) {
    console.warn('Could not fetch existing images:', err);
  }

  // Ensure filenames are strings, unique, and NOT already existing
  const uniqueFilenames = [...new Set(imageFilenames.map(f => String(f).split('/').pop()).filter(Boolean))]
    .filter(filename => !existingImages.includes(filename));

  if (uniqueFilenames.length === 0) {
    console.log('No new unique images to insert - all already exist for this product');
    return { successCount: 0, errorCount: 0 };
  }

  for (let i = 0; i < uniqueFilenames.length; i++) {
    const filename = uniqueFilenames[i];
    const imagePath = `uploads/products/${filename}`;

    const fd = new FormData();
    fd.append('product_id', productId);
    fd.append('image_path', imagePath);
    fd.append('display_order', startDisplayOrder + i);
    fd.append('is_primary', (startDisplayOrder === 0 && i === 0 && existingImages.length === 0) ? '1' : '0');

    try {
      const res = await fetch(`/backend/api/product_images.php?action=insert_direct`, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      });
      // server should return JSON: { success: true/false, message: ..., data: ...}
      const json = await res.json().catch(() => null);
      if (res.ok && json && json.success) {
        successCount++;
        console.log(`✅ Inserted ${filename} (order ${startDisplayOrder + i})`);
      } else {
        errorCount++;
        console.warn(`❌ Failed to insert ${filename}:`, json?.message || 'Unknown error from insert API');
      }
    } catch (err) {
      errorCount++;
      console.error(`❌ Error inserting ${filename}:`, err);
    }
  }

  console.log(`📊 Insert complete: ${successCount} success, ${errorCount} errors`);
  if (errorCount > 0) showNotification('warning', `${successCount} image(s) saved, ${errorCount} failed`);
  return { successCount, errorCount };
}


// ===== Replaced handleImagePreview =====
async function handleImagePreview(e) {
  const files = e.target.files;
  if (!files || files.length === 0) return;

  const previewContainer = document.getElementById('imagePreviewContainer');
  if (!previewContainer) {
    console.error('imagePreviewContainer not found');
    return;
  }

  // Show loading indicator (don't remove existing previews)
  const loadingId = 'upload-loading-indicator';
  let loadingIndicator = document.getElementById(loadingId);
  if (!loadingIndicator) {
    loadingIndicator = document.createElement('p');
    loadingIndicator.id = loadingId;
    loadingIndicator.style.cssText = 'color:#666;padding:10px;grid-column:1/-1;';
    loadingIndicator.textContent = 'Uploading images...';
    previewContainer.appendChild(loadingIndicator);
  } else {
    loadingIndicator.textContent = 'Uploading images...';
  }

  try {
    const formData = new FormData();
    for (let i = 0; i < files.length; i++) {
      formData.append('images[]', files[i]);
    }

    const resp = await fetch('/backend/api/admin_products.php?action=upload_image', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    });

    const result = await resp.json().catch(() => ({ success: false, message: 'Invalid JSON' }));
    if (!result.success) {
      throw new Error(result.message || 'Upload failed');
    }

    // server may return different shapes: { data: { files: [...] } } or { data: [...] } or { files: [...] } or { uploaded: [...] }
    let uploadedFiles = [];
    if (result.data && Array.isArray(result.data.files)) uploadedFiles = result.data.files;
    else if (Array.isArray(result.data)) uploadedFiles = result.data;
    else if (Array.isArray(result.files)) uploadedFiles = result.files;
    else if (Array.isArray(result.uploaded)) uploadedFiles = result.uploaded;
    else if (typeof result.data === 'string') uploadedFiles = [result.data];
    else if (typeof result.filename === 'string') uploadedFiles = [result.filename];

    // Normalize to filenames (strip any path)
    const normalized = uploadedFiles.map(item => {
      if (!item) return null;
      if (typeof item === 'object') {
        // object may contain: filename, path, image_path
        return (item.filename || item.path || item.image_path || item.file || '').toString().split('/').pop();
      }
      return item.toString().split('/').pop();
    }).filter(Boolean);

    if (normalized.length === 0) {
      throw new Error('No uploaded files returned from server');
    }

    // Append preview for each, only if not already present in uploadedProductImages
    for (let i = 0; i < normalized.length; i++) {
      const shortName = normalized[i];
      if (!window.uploadedProductImages) window.uploadedProductImages = [];
      if (window.uploadedProductImages.includes(shortName)) {
        console.log('Image already present, skipping preview:', shortName);
        continue;
      }
      window.uploadedProductImages.push(shortName);

      const imagePath = `/uploads/products/${shortName}`;
      const imgWrapper = document.createElement('div');
      imgWrapper.className = 'image-preview-item';
      imgWrapper.style.cssText = 'position:relative;width:100px;height:100px;display:inline-block;margin:5px;';
      imgWrapper.dataset.filename = shortName;

      const img = document.createElement('img');
      img.src = imagePath;
      img.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:8px;border:2px solid #ddd;';
      img.onerror = function () { console.error('Failed to load preview', imagePath); this.src = '/uploads/products/placeholder.jpg'; };

      // primary badge if first (or none existing)
      if (window.uploadedProductImages.length === 1 && !previewContainer.querySelector('.primary-badge')) {
        const badge = document.createElement('span');
        badge.className = 'primary-badge';
        badge.textContent = 'Primary';
        badge.style.cssText = 'position:absolute;top:5px;left:5px;background:#4CAF50;color:#fff;padding:2px 6px;font-size:10px;border-radius:4px;z-index:1;';
        imgWrapper.appendChild(badge);
      }

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.innerHTML = '&times;';
      removeBtn.className = 'remove-image-btn';
      removeBtn.style.cssText = 'position:absolute;top:2px;right:2px;background:#f44336;color:#fff;border:none;border-radius:50%;width:20px;height:20px;cursor:pointer;font-size:16px;line-height:1;padding:0;z-index:1;';
      removeBtn.onclick = (ev) => { ev.preventDefault(); ev.stopPropagation(); removeImagePreview(shortName); };

      imgWrapper.appendChild(img);
      imgWrapper.appendChild(removeBtn);
      previewContainer.appendChild(imgWrapper);
    }

    // Update main preview element for backward compatibility
    if (window.uploadedProductImages && window.uploadedProductImages.length > 0) {
      const imgPrev = document.getElementById('productImagePreview');
      if (imgPrev) {
        imgPrev.dataset.filename = window.uploadedProductImages[0];
        imgPrev.src = `/uploads/products/${window.uploadedProductImages[0]}`;
        imgPrev.style.display = 'none';
      }
    }

    // remove loading indicator and clear input
    loadingIndicator.remove();
    e.target.value = '';

    console.log('uploadedProductImages (after upload):', window.uploadedProductImages);
    showNotification('success', `${normalized.length} image(s) uploaded successfully`);
    return normalized;
  } catch (err) {
    console.error('Image upload error:', err);
    if (document.getElementById('upload-loading-indicator')) document.getElementById('upload-loading-indicator').remove();
    showNotification('error', err.message || 'Failed to upload images');
  }
}


async function removeImagePreview(filename) {
  if (!filename) return;

  const previewContainer = document.getElementById('imagePreviewContainer');
  if (!previewContainer) return;

  const itemToRemove = Array.from(previewContainer.querySelectorAll('.image-preview-item'))
    .find(it => it.dataset.filename === filename);
  if (!itemToRemove) return;

  const imageId = itemToRemove.dataset.imageId ? Number(itemToRemove.dataset.imageId) : null;
  if (imageId) {
    // If image exists in DB, call delete endpoint
    const productId = (document.getElementById('addProductForm')?.dataset?.editingId) || null;
    const ok = await deleteProductImageById(imageId, productId);
    if (!ok) return; // don't remove UI if delete failed
  }

  // remove UI and update uploadedProductImages (covers both new uploads and DB images after successful delete)
  uploadedProductImages = uploadedProductImages.filter(f => f !== filename);
  itemToRemove.remove();

  // reassign primary badge if needed
  const remainingItems = previewContainer.querySelectorAll('.image-preview-item');
  if (remainingItems.length > 0) {
    previewContainer.querySelectorAll('.primary-badge').forEach(b => b.remove());
    const firstItem = remainingItems[0];
    if (!firstItem.querySelector('.primary-badge')) {
      const badge = document.createElement('span');
      badge.textContent = 'Primary';
      badge.className = 'primary-badge';
      badge.style.cssText = 'position: absolute; top: 5px; left: 5px; background: #4CAF50; color: white; padding: 2px 6px; font-size: 10px; border-radius: 4px; font-weight: bold; z-index: 1;';
      firstItem.insertBefore(badge, firstItem.firstChild);
    }
    const imgPrev = document.getElementById('productImagePreview');
    if (imgPrev) {
      imgPrev.dataset.filename = uploadedProductImages[0] || '';
    }
  } else {
    const imgPrev = document.getElementById('productImagePreview');
    if (imgPrev) {
      imgPrev.src = '';
      imgPrev.style.display = 'none';
      delete imgPrev.dataset.filename;
    }
  }

  showNotification('info', 'Image removed');
}

async function loadExistingProductImages(productId) {
  try {
    // 🔥 FIX: Clear global array FIRST to prevent mixing with other products
    uploadedProductImages = [];
    window.uploadedProductImages = [];

    const response = await fetch(`/backend/api/product_images.php?action=list&product_id=${productId}`, {
      credentials: 'same-origin'
    });
    const result = await response.json();

    if (!result.success) {
      console.warn('No existing images or error:', result.message);
      return;
    }

    // 🔥 FIX: Handle different response structures from API
    let images = [];
    if (result.data && result.data.images && Array.isArray(result.data.images)) {
      images = result.data.images; // Structure: { data: { images: [...] } }
    } else if (Array.isArray(result.data)) {
      images = result.data; // Structure: { data: [...] }
    } else if (result.images && Array.isArray(result.images)) {
      images = result.images; // Structure: { images: [...] }
    }

    console.log('🖼️ Loaded existing images for product', productId, ':', images.length, 'images');

    if (images.length === 0) {
      console.log('No existing images found for this product');
      return;
    }
    const previewContainer = document.getElementById('imagePreviewContainer');
    if (!previewContainer) return;

    previewContainer.innerHTML = '';
    uploadedProductImages = [];

    if (!Array.isArray(images) || images.length === 0) return;

    images.forEach((img, index) => {
      // robust keys - support multiple shapes
      const imgPath = img.image_path || img.path || img.file || img.filename || '';
      const imageId = img.image_id || img.id || img.imageId || null;
      const filename = String(imgPath).split('/').pop();
      if (!filename) return;

      if (!uploadedProductImages.includes(filename)) uploadedProductImages.push(filename);

      const imgWrapper = document.createElement('div');
      imgWrapper.className = 'image-preview-item';
      imgWrapper.style.cssText = 'position: relative; width: 100px; height: 100px; display: inline-block; margin: 5px;';
      imgWrapper.dataset.filename = filename;
      if (imageId) imgWrapper.dataset.imageId = String(imageId);
      imgWrapper.dataset.index = index;

      const imgEl = document.createElement('img');
      // ensure path is normalized and points to uploads (fallback to placeholder)
      imgEl.src = imgPath ? `/${normalizeSrc(imgPath)}` : '/uploads/products/placeholder.jpg';
      imgEl.style.cssText = 'width: 100%; height: 100%; object-fit: cover; border-radius: 8px; border: 2px solid #ddd;';
      imgEl.onerror = function () {
        this.onerror = null;
        this.src = '/uploads/products/placeholder.jpg';
      };

      if (Number(img.is_primary) === 1 || index === 0) {
        const badge = document.createElement('span');
        badge.textContent = 'Primary';
        badge.className = 'primary-badge';
        badge.style.cssText = 'position: absolute; top: 5px; left: 5px; background: #4CAF50; color: white; padding: 2px 6px; font-size: 10px; border-radius: 4px; font-weight: bold; z-index: 1;';
        imgWrapper.appendChild(badge);
      }

      const removeBtn = document.createElement('button');
      removeBtn.innerHTML = '&times;';
      removeBtn.className = 'remove-image-btn';
      removeBtn.type = 'button';
      removeBtn.style.cssText = 'position: absolute; top: 2px; right: 2px; background: #f44336; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; cursor: pointer; font-size: 16px; line-height: 1; padding: 0; z-index: 1;';
      removeBtn.onclick = async function (event) {
        event.preventDefault();
        event.stopPropagation();
        // if we have an image id, call the backend delete endpoint
        const iid = imgWrapper.dataset.imageId ? Number(imgWrapper.dataset.imageId) : null;
        if (iid) {
          // optimistic UI: disable button to avoid double click
          removeBtn.disabled = true;
          const ok = await deleteProductImageById(iid, productId);
          if (ok) {
            // remove from DOM and uploadedProductImages
            imgWrapper.remove();
            uploadedProductImages = uploadedProductImages.filter(f => f !== filename);
            // if we removed primary, server already handled setting new primary and updating products table (product_images.php)
            productPrimaryImageCache.delete(Number(productId));
          } else {
            removeBtn.disabled = false;
          }
        } else {
          // fallback: if no image id (shouldn't happen for images loaded from DB), just remove UI
          imgWrapper.remove();
          uploadedProductImages = uploadedProductImages.filter(f => f !== filename);
        }
      };

      imgWrapper.appendChild(imgEl);
      imgWrapper.appendChild(removeBtn);
      previewContainer.appendChild(imgWrapper);
    });

    // keep existing behavior for productImagePreview element
    if (uploadedProductImages.length > 0) {
      const imgPrev = document.getElementById('productImagePreview');
      if (imgPrev) {
        imgPrev.dataset.filename = uploadedProductImages[0];
        imgPrev.style.display = 'none';
      }
    }

    // cache primary for this product so list will align with edit modal
    const primary = images.find(i => Number(i.is_primary) === 1) || images[0];
    if (primary) {
      const fn = String(primary.image_path || primary.path || primary.filename || '').split('/').pop();
      if (fn) productPrimaryImageCache.set(Number(productId), fn);
    }

    console.log('loaded existing images:', uploadedProductImages);
  } catch (error) {
    console.error('Error loading existing images:', error);
  }
}

async function deleteProductImageById(imageId, productId) {
  try {
    const fd = new FormData();
    fd.append('image_id', imageId);
    const resp = await fetch('/backend/api/product_images.php?action=delete', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    });
    const j = await resp.json().catch(() => ({ success: false }));
    if (j.success) {
      showNotification('success', 'Image deleted');
      // invalidate cache for product so front listing will re-check on next load
      if (productId) productPrimaryImageCache.delete(Number(productId));
      return true;
    } else {
      showNotification('error', j.message || 'Failed to delete image');
      return false;
    }
  } catch (err) {
    console.error('delete image error', err);
    showNotification('error', 'Failed to delete image (network)');
    return false;
  }
}

// ===== Image / Model upload =====
async function handleModelPreview(e) {
  const file = e.target.files?.[0];
  if (!file) return;

  const sizeMB = file.size / (1024 * 1024);
  if (sizeMB > MAX_GLB_MB) {
    showNotification('error', `Model file too large. Max ${MAX_GLB_MB}MB.`);
    e.target.value = '';
    return;
  }

  const formData = new FormData();
  formData.append('model', file);

  try {
    const data = await fetch('/backend/api/admin_products.php?action=upload_model', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    }).then(r => r.json());
    if (!data.success) throw new Error(data.message);

    const prev = document.getElementById('productModelPreview');
    if (prev) {
      prev.textContent = `📦 ${file.name}`;
      prev.style.display = 'block';
      prev.dataset.filename = data.data.filename;
    }

    showNotification('success', '3D model uploaded successfully');
  } catch (err) {
    console.error('Model upload error:', err);
    showNotification('error', 'Failed to upload model');
  }
}

// ===== Release toggle =====
async function toggleProductRelease(productId, newStatus) {
  const actionText = newStatus === 'released' ? 'release' : 'set to draft';
  const message = `Are you sure you want to ${actionText} this product?`;

  const confirmed = await showConfirmation(message);
  if (!confirmed) return;

  try {
    await fetchJSON('/backend/api/admin_products.php?action=toggle_release', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ product_id: productId, status: newStatus })
    });

    showNotification('success', `Product ${newStatus === 'released' ? 'released' : 'set to draft'} successfully`);
    loadProducts();
  } catch (err) {
    console.error('Toggle release error:', err);
    showNotification('error', 'Failed to update product status');
  }
}

// ===== Customization Modal =====
async function openCustomizationModal(productId) {
  try {
    const data = await fetchJSON(`/backend/api/admin_products.php?action=view&id=${productId}`);
    const product = data.data;
    currentProduct = product;

    console.log('🎨 Product customization data:', {
      textures: product.textures,
      colors: product.colors,
      handles: product.handles
    });

    const productIdEl = document.getElementById('customProductId');
    if (productIdEl) productIdEl.value = productId;

    const productNameEl = document.getElementById('customProductName');
    if (productNameEl) productNameEl.textContent = product.name;

    populateSizeConfig(product.size_config || []);
    populateTexturesList(product.textures || []);
    populateColorsList(product.colors || []);
    populateHandlesList(product.handles || []);

    openModal('manageCustomizationModal');
  } catch (error) {
    console.error('Open customization modal error:', error);
    showNotification('error', 'Failed to load customization options');
  }
}

function populateSizeConfig(sizes) {
  const sizeMap = {};
  sizes.forEach(s => { sizeMap[s.dimension_type] = s; });

  // Save current size config for potential revert (deep copy)
  savedSizeConfig = JSON.parse(JSON.stringify(sizeMap));

  ['width', 'height', 'depth'].forEach(dim => {
    const s = sizeMap[dim];
    if (!s) return;

    setField(`${dim}MinCustom`, s.min_value);
    setField(`${dim}MaxCustom`, s.max_value);
    setField(`${dim}DefaultCustom`, s.default_value);
    setField(`${dim}StepCustom`, s.step_value);
    setField(`${dim}Unit`, s.measurement_unit);

    const mode = s.pricing_mode || 'percm';
    const radios = document.getElementsByName(`${dim}PricingMode`);
    radios.forEach(r => { r.checked = (r.value === mode); });

    setField(`${dim}PPU`, s.price_per_unit);
    setField(`${dim}BlockCM`, s.price_block_cm);
    setField(`${dim}PerBlock`, s.price_per_block);
  });
}

// Revert size config to last saved state (called when modal is closed without saving)
function revertSizeConfig() {
  if (!savedSizeConfig) return;

  ['width', 'height', 'depth'].forEach(dim => {
    const s = savedSizeConfig[dim];
    if (!s) return;

    setField(`${dim}MinCustom`, s.min_value);
    setField(`${dim}MaxCustom`, s.max_value);
    setField(`${dim}DefaultCustom`, s.default_value);
    setField(`${dim}StepCustom`, s.step_value);
    setField(`${dim}Unit`, s.measurement_unit);

    const mode = s.pricing_mode || 'percm';
    const radios = document.getElementsByName(`${dim}PricingMode`);
    radios.forEach(r => { r.checked = (r.value === mode); });

    setField(`${dim}PPU`, s.price_per_unit);
    setField(`${dim}BlockCM`, s.price_block_cm);
    setField(`${dim}PerBlock`, s.price_per_block);
  });
}

// Custom close function for customization modal (reverts unsaved changes)
window.closeCustomizationModal = function () {
  revertSizeConfig();
  closeModal('manageCustomizationModal');
};

// ===== Textures, Colors, Handles lists =====
async function populateTexturesList(productTextures) {
  const container = document.getElementById('texturesListContainer');
  if (!container) return;
  container.innerHTML = '';

  // Show empty state if no textures exist
  if (!allTextures || allTextures.length === 0) {
    container.innerHTML = '<div style="padding:20px;text-align:center;color:#666;">No customization options added yet.</div>';
    return;
  }

  let existingParts = {};
  try {
    if (currentProduct?.id) {
      const response = await fetchJSON(`/backend/api/admin_customization.php?action=list_product_textures_parts&product_id=${currentProduct.id}`);
      if (response.success && response.data) {
        response.data.forEach(texture => {
          existingParts[texture.id] = texture.allowed_parts || [];
        });
      }
    }
  } catch (error) {
    console.warn('Failed to load existing texture parts:', error);
  }

  const placeholderSVG = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 40 40"%3E%3Crect fill="%23e5e7eb" width="40" height="40"/%3E%3Ctext x="50%25" y="50%25" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="12" fill="%239ca3af"%3ENo Img%3C/text%3E%3C/svg%3E';

  const selectedIds = productTextures.map(t => +t.texture_id);
  allTextures.forEach(texture => {
    const checked = selectedIds.includes(+texture.id);

    const textureName = texture.name || texture.texture_name || texture.title || 'Unnamed Texture';
    const textureImage = texture.image || texture.texture_image || texture.file_path || texture.filename;
    const texturePrice = texture.price || texture.texture_price || 0;

    const imageUrl = textureImage
      ? `/uploads/textures/${textureImage}`
      : placeholderSVG;

    const textureParts = existingParts[texture.id] || [];
    const bodyChecked = textureParts.includes('body') ? 'checked' : '';
    const doorChecked = textureParts.includes('door') ? 'checked' : '';
    const interiorChecked = textureParts.includes('interior') ? 'checked' : '';

    container.innerHTML += `
      <div style="border:1px solid #e5e7eb; border-radius:8px; margin:8px 0; padding:12px; background: ${checked ? '#f0f9ff' : 'white'}; transition: all 0.2s;">
       <div style="display:flex; align-items:center; gap:12px;">
          <label style="display:flex; align-items:center; gap:12px; cursor:pointer; flex:1;" onmouseover="this.parentElement.parentElement.style.backgroundColor='#f9fafb'" onmouseout="this.parentElement.parentElement.style.backgroundColor='${checked ? '#f0f9ff' : 'white'}'">
            <input type="checkbox" value="${texture.id}" ${checked ? 'checked' : ''} style="width:18px; height:18px; cursor:pointer; flex-shrink:0;">
            <img src="${imageUrl}"
                 alt="${textureName}"
                 style="width:50px; height:50px; object-fit:cover; border-radius:6px; border:1px solid #d1d5db; flex-shrink:0;"
                 onerror="this.src='${placeholderSVG}'">
            <div style="flex:1; min-width:0;">
              <div style="font-weight:600; color:#111827; font-size:14px;">${textureName}</div>
              ${texturePrice > 0 ? `<div style="color:#6b7280; font-size:13px; margin-top:2px;">₱${parseFloat(texturePrice).toFixed(2)}</div>` : ''}
            </div>
          </label>
          <button type="button" onclick="deleteTexture(${texture.id}, '${textureName.replace(/'/g, "\\'")}')"
                  style="background:#ef4444; color:white; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:500; white-space:nowrap;"
                  onmouseover="this.style.backgroundColor='#dc2626'"
                  onmouseout="this.style.backgroundColor='#ef4444'">
            Delete
          </button>
        </div>
      </div>`;
  });
}

function toggleTexturePartOptions(mainCheckbox) {
  const textureId = mainCheckbox.value;
  const partsDiv = document.querySelector(`.texture-parts[data-texture-id="${textureId}"]`);
  if (partsDiv) {
    partsDiv.style.display = mainCheckbox.checked ? 'block' : 'none';
    if (!mainCheckbox.checked) {
      const partCheckboxes = partsDiv.querySelectorAll('.texture-part-checkbox');
      partCheckboxes.forEach(cb => cb.checked = false);
    }
  }
}

function populateColorsList(productColors) {
  const container = document.getElementById('colorsListContainer');
  if (!container) return;
  container.innerHTML = '';

  // Show empty state if no colors exist
  if (!allColors || allColors.length === 0) {
    container.innerHTML = '<div style="padding:20px;text-align:center;color:#666;">No customization options added yet.</div>';
    return;
  }

  const selectedIds = productColors.map(c => +c.color_id);
  allColors.forEach(color => {
    const checked = selectedIds.includes(+color.id);
    const colorName = color.name || color.color_name || 'Unnamed Color';
    const colorPrice = color.price || color.color_price || 0;
    const hexCode = color.hex_code || color.hex || color.color || '#cccccc';

    container.innerHTML += `
       <div style="border:1px solid #e5e7eb; border-radius:8px; margin:8px 0; padding:12px; background: ${checked ? '#f0f9ff' : 'white'}; transition: all 0.2s;">
        <div style="display:flex; align-items:center; gap:12px;">
          <label style="display:flex; align-items:center; gap:12px; cursor:pointer; flex:1;" onmouseover="this.parentElement.parentElement.style.backgroundColor='#f9fafb'" onmouseout="this.parentElement.parentElement.style.backgroundColor='${checked ? '#f0f9ff' : 'white'}'">
            <input type="checkbox" value="${color.id}" ${checked ? 'checked' : ''} style="width:18px; height:18px; cursor:pointer; flex-shrink:0;">
            <div style="width:50px; height:50px; background:${hexCode}; border:2px solid #d1d5db; border-radius:6px; flex-shrink:0;"></div>
            <div style="flex:1; min-width:0;">
              <div style="font-weight:600; color:#111827; font-size:14px;">${colorName}</div>
              ${colorPrice > 0 ? `<div style="color:#6b7280; font-size:13px; margin-top:2px;">₱${parseFloat(colorPrice).toFixed(2)}</div>` : ''}
            </div>
          </label>
          <button type="button" onclick="deleteColor(${color.id}, '${colorName.replace(/'/g, "\\'")}')"
                  style="background:#ef4444; color:white; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:500; white-space:nowrap;"
                  onmouseover="this.style.backgroundColor='#dc2626'"
                  onmouseout="this.style.backgroundColor='#ef4444'">
            Delete
          </button>
        </div>
      </div>`;
  });
}

function populateHandlesList(productHandles) {
  const container = document.getElementById('handlesListContainer');
  if (!container) return;
  container.innerHTML = '';

  // Show empty state if no handles exist
  if (!allHandles || allHandles.length === 0) {
    container.innerHTML = '<div style="padding:20px;text-align:center;color:#666;">No customization options added yet.</div>';
    return;
  }

  const placeholderSVG = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 40 40"%3E%3Crect fill="%23e5e7eb" width="40" height="40"/%3E%3Ctext x="50%25" y="50%25" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="12" fill="%239ca3af"%3ENo Img%3C/text%3E%3C/svg%3E';

  const selectedIds = productHandles.map(h => +h.handle_id);
  allHandles.forEach(handle => {
    const checked = selectedIds.includes(+handle.id);

    const handleName = handle.name || handle.handle_name || handle.title || 'Unnamed Handle';
    const handleImage = handle.image || handle.handle_image || handle.file_path || handle.filename;
    const handlePrice = handle.price || handle.handle_price || 0;

    const imageUrl = handleImage
      ? `/uploads/handles/${handleImage}`
      : placeholderSVG;

    container.innerHTML += `
    <div style="border:1px solid #e5e7eb; border-radius:8px; margin:8px 0; padding:12px; background: ${checked ? '#f0f9ff' : 'white'}; transition: all 0.2s;">
        <div style="display:flex; align-items:center; gap:12px;">
          <label style="display:flex; align-items:center; gap:12px; cursor:pointer; flex:1;" onmouseover="this.parentElement.parentElement.style.backgroundColor='#f9fafb'" onmouseout="this.parentElement.parentElement.style.backgroundColor='${checked ? '#f0f9ff' : 'white'}'">
            <input type="checkbox" value="${handle.id}" ${checked ? 'checked' : ''} style="width:18px; height:18px; cursor:pointer; flex-shrink:0;">
            <img src="${imageUrl}"
                 alt="${handleName}"
                 style="width:50px; height:50px; object-fit:cover; border-radius:6px; border:1px solid #d1d5db; flex-shrink:0;"
                 onerror="this.src='${placeholderSVG}'">
            <div style="flex:1; min-width:0;">
              <div style="font-weight:600; color:#111827; font-size:14px;">${handleName}</div>
              ${handlePrice > 0 ? `<div style="color:#6b7280; font-size:13px; margin-top:2px;">₱${parseFloat(handlePrice).toFixed(2)}</div>` : ''}
            </div>
          </label>
          <button type="button" onclick="deleteHandle(${handle.id}, '${handleName.replace(/'/g, "\\'")}')"
                  style="background:#ef4444; color:white; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:500; white-space:nowrap;"
                  onmouseover="this.style.backgroundColor='#dc2626'"
                  onmouseout="this.style.backgroundColor='#ef4444'">
            Delete
          </button>
        </div>
      </div>`;
  });
}

// ===== Delete functions for texture/color/handle =====
async function deleteTexture(textureId, textureName) {
  // Use universal confirmation modal
  const confirmed = await showConfirmation(`Are you sure you want to delete the texture "${textureName}"?\n\nThis will permanently remove it from the system and unassign it from all products.`);
  if (!confirmed) return;

  // Find and disable the delete button to prevent multiple clicks
  const deleteBtn = event?.target;
  if (deleteBtn) {
    deleteBtn.disabled = true;
    deleteBtn.style.opacity = '0.5';
    deleteBtn.style.cursor = 'not-allowed';
  }

  try {
    const response = await fetchJSON('/backend/api/admin_customization.php?action=delete_texture', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ texture_id: textureId })
    });

    if (response.success) {
      // Update in-memory array
      allTextures = allTextures.filter(t => +t.id !== +textureId);

      // Remove from DOM immediately without page refresh
      const container = document.getElementById('texturesListContainer');
      if (container) {
        const itemToRemove = Array.from(container.children).find(child => {
          const checkbox = child.querySelector('input[type="checkbox"]');
          return checkbox && +checkbox.value === +textureId;
        });
        if (itemToRemove) itemToRemove.remove();
      }

      // Check if container is now empty and add empty state
      if (container && container.children.length === 0) {
        container.innerHTML = '<div style="padding:20px;text-align:center;color:#666;">No customization options added yet.</div>';
      }
      // Show success notification using universal modal
      showNotification('success', 'Texture deleted successfully!');
    } else {
      // Re-enable button on failure
      if (deleteBtn) {
        deleteBtn.disabled = false;
        deleteBtn.style.opacity = '1';
        deleteBtn.style.cursor = 'pointer';
      }
      showNotification('error', response.message || 'Failed to delete. Please try again.');
    }
  } catch (error) {
    console.error('Delete texture error:', error);
    // Re-enable button on error
    if (deleteBtn) {
      deleteBtn.disabled = false;
      deleteBtn.style.opacity = '1';
      deleteBtn.style.cursor = 'pointer';
    }
    showNotification('error', 'Failed to delete. Please try again.');
  }
}

async function deleteColor(colorId, colorName) {
  // Use universal confirmation modal
  const confirmed = await showConfirmation(`Are you sure you want to delete the color "${colorName}"?\n\nThis will permanently remove it from the system and unassign it from all products.`);
  if (!confirmed) return;

  // Find and disable the delete button to prevent multiple clicks
  const deleteBtn = event?.target;
  if (deleteBtn) {
    deleteBtn.disabled = true;
    deleteBtn.style.opacity = '0.5';
    deleteBtn.style.cursor = 'not-allowed';
  }

  try {
    const response = await fetchJSON('/backend/api/admin_customization.php?action=delete_color', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ color_id: colorId })
    });

    if (response.success) {
      // Update in-memory array
      allColors = allColors.filter(c => +c.id !== +colorId);

      // Remove from DOM immediately without page refresh
      const container = document.getElementById('colorsListContainer');
      if (container) {
        const itemToRemove = Array.from(container.children).find(child => {
          const checkbox = child.querySelector('input[type="checkbox"]');
          return checkbox && +checkbox.value === +colorId;
        });
        if (itemToRemove) itemToRemove.remove();
      }
      // Check if container is now empty and add empty state
      if (container && container.children.length === 0) {
        container.innerHTML = '<div style="padding:20px;text-align:center;color:#666;">No customization options added yet.</div>';
      }

      // Show success notification using universal modal
      showNotification('success', 'Color deleted successfully!');
    } else {
      // Re-enable button on failure
      if (deleteBtn) {
        deleteBtn.disabled = false;
        deleteBtn.style.opacity = '1';
        deleteBtn.style.cursor = 'pointer';
      }
      showNotification('error', response.message || 'Failed to delete. Please try again.');
    }
  } catch (error) {
    console.error('Delete color error:', error);
    // Re-enable button on error
    if (deleteBtn) {
      deleteBtn.disabled = false;
      deleteBtn.style.opacity = '1';
      deleteBtn.style.cursor = 'pointer';
    }
    showNotification('error', 'Failed to delete. Please try again.');
  }
}

async function deleteHandle(handleId, handleName) {
  // Use universal confirmation modal
  const confirmed = await showConfirmation(`Are you sure you want to delete the handle "${handleName}"?\n\nThis will permanently remove it from the system and unassign it from all products.`);
  if (!confirmed) return;

  // Find and disable the delete button to prevent multiple clicks
  const deleteBtn = event?.target;
  if (deleteBtn) {
    deleteBtn.disabled = true;
    deleteBtn.style.opacity = '0.5';
    deleteBtn.style.cursor = 'not-allowed';
  }

  try {
    const response = await fetchJSON('/backend/api/admin_customization.php?action=delete_handle', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ handle_id: handleId })
    });

    if (response.success) {
      // Update in-memory array
      allHandles = allHandles.filter(h => +h.id !== +handleId);

      // Remove from DOM immediately without page refresh
      const container = document.getElementById('handlesListContainer');
      if (container) {
        const itemToRemove = Array.from(container.children).find(child => {
          const checkbox = child.querySelector('input[type="checkbox"]');
          return checkbox && +checkbox.value === +handleId;
        });
        if (itemToRemove) itemToRemove.remove();
      }

      // Check if container is now empty and add empty state
      if (container && container.children.length === 0) {
        container.innerHTML = '<div style="padding:20px;text-align:center;color:#666;">No customization options added yet.</div>';
      }
      // Show success notification using universal modal
      showNotification('success', 'Handle deleted successfully!');
    } else {
      // Re-enable button on failure
      if (deleteBtn) {
        deleteBtn.disabled = false;
        deleteBtn.style.opacity = '1';
        deleteBtn.style.cursor = 'pointer';
      }
      showNotification('error', response.message || 'Failed to delete. Please try again.');
    }
  } catch (error) {
    console.error('Delete handle error:', error);
    // Re-enable button on error
    if (deleteBtn) {
      deleteBtn.disabled = false;
      deleteBtn.style.opacity = '1';
      deleteBtn.style.cursor = 'pointer';
    }
    showNotification('error', 'Failed to delete. Please try again.');
  }
}

// ===== Size config collector & save customization =====
function collectSizeConfig() {
  const getDim = (dim) => {
    const unit = (document.getElementById(`${dim}Unit`)?.value) || 'cm';
    const radios = document.getElementsByName(`${dim}PricingMode`);
    const pricing_mode = [...radios].find(r => r.checked)?.value || 'percm';

    const price_per_unit = Number(document.getElementById(`${dim}PPU`)?.value || 0);
    const price_block_cm = Number(document.getElementById(`${dim}BlockCM`)?.value || 0);
    const price_per_block = Number(document.getElementById(`${dim}PerBlock`)?.value || 0);

    return {
      min_value: Number(document.getElementById(`${dim}MinCustom`)?.value || 0),
      max_value: Number(document.getElementById(`${dim}MaxCustom`)?.value || 300),
      default_value: Number(document.getElementById(`${dim}DefaultCustom`)?.value || 100),
      step_value: Number(document.getElementById(`${dim}StepCustom`)?.value || 1),
      measurement_unit: unit,
      pricing_mode,
      price_per_unit,
      price_block_cm,
      price_per_block
    };
  };

  return { width: getDim('width'), height: getDim('height'), depth: getDim('depth') };
}

async function saveCustomizationOptions() {
  const el = id => document.getElementById(id);
  const valNum = id => Number(el(id)?.value || 0);
  const valStr = id => (el(id)?.value || '');

  const getDim = (dim) => {
    const min = valNum(`${dim}MinCustom`);
    const max = valNum(`${dim}MaxCustom`);
    const def = (el(`${dim}DefaultCustom`) || el(`${dim}DefCustom`)) ? Number((el(`${dim}DefaultCustom`) || el(`${dim}DefCustom`)).value || 0) : undefined;
    const step = el(`${dim}StepCustom`) ? Number(el(`${dim}StepCustom`).value || 1) : 1;
    const unit = (el(`${dim}Unit`)?.value) || (currentProduct?.measurement_unit || 'cm');

    const ppu = el(`${dim}PPU`) ? Number(el(`${dim}PPU`).value || 0)
      : el(`${dim}PriceCustom`) ? Number(el(`${dim}PriceCustom`).value || 0) : 0;
    const bSize = el(`${dim}BlockCM`) ? Number(el(`${dim}BlockCM`).value || 0)
      : el(`${dim}BlockSize`) ? Number(el(`${dim}BlockSize`).value || 0) : 0;
    const bCost = el(`${dim}PerBlock`) ? Number(el(`${dim}PerBlock`).value || 0)
      : el(`${dim}BlockPrice`) ? Number(el(`${dim}BlockPrice`).value || 0) : 0;

    const radios = document.getElementsByName(`${dim}PricingMode`);
    const mode = [...radios].find(r => r.checked)?.value || 'percm';

    const pricing_mode = (mode === 'block') ? 'block' : 'percm';
    const price_block_cm = (pricing_mode === 'percm') ? 0 : bSize;
    const price_per_block = (pricing_mode === 'percm') ? 0 : bCost;
    const price_per_unit = ppu;

    return {
      min_value: min,
      max_value: max,
      ...(def !== undefined ? { default_value: def } : {}),
      step_value: step,
      measurement_unit: unit,
      pricing_mode: pricing_mode,
      price_per_unit: price_per_unit,
      price_block_cm: price_block_cm,
      price_per_block: price_per_block
    };
  };

  const product_id = el('customProductId')?.value;
  if (!product_id) {
    showNotification('error', 'Product ID not found');
    return;
  }

  const size_config = {
    width: getDim('width'),
    height: getDim('height'),
    depth: getDim('depth')
  };

  const texture_ids = Array.from(document.querySelectorAll('#texturesListContainer input:checked')).map(cb => +cb.value);
  const color_ids = Array.from(document.querySelectorAll('#colorsListContainer   input:checked')).map(cb => +cb.value);
  const handle_ids = Array.from(document.querySelectorAll('#handlesListContainer  input:checked')).map(cb => +cb.value);

  try {
    await fetchJSON('/backend/api/admin_customization.php?action=update_size_config', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ product_id, size_config })
    });
    await fetchJSON('/backend/api/admin_customization.php?action=assign_textures', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ product_id, texture_ids })
    });
    await fetchJSON('/backend/api/admin_customization.php?action=assign_colors', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ product_id, color_ids })
    });
    await fetchJSON('/backend/api/admin_customization.php?action=assign_handles', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ product_id, handle_ids })
    });

    // Update saved size config after successful save
    savedSizeConfig = JSON.parse(JSON.stringify(size_config));
    
    showNotification('success', 'Customization options saved successfully');
    closeModal('manageCustomizationModal');
    loadProducts();
  } catch (error) {
    console.error('Save customization error:', error);
    showNotification('error', 'Failed to save customization options');
  }
}

// ===== Small utilities / UI glue =====
function initPMModalNotifier() {
  if (document.getElementById('pm-toast-modal')) return;

  const wrap = document.createElement('div');
  wrap.innerHTML = `
    <div id="pm-toast-modal" style="position:fixed; inset:0; display:none; align-items:center; justify-content:center; z-index:9999; backdrop-filter: blur(2px); background: rgba(0,0,0,.35);">
      <div id="pm-toast-box" style="min-width:320px; max-width:520px; background:#111827; color:#e5e7eb; border-radius:12px; box-shadow:0 12px 24px rgba(0,0,0,.25); overflow:hidden;">
        <div id="pm-toast-head" style="display:flex; align-items:center; gap:10px; padding:14px 16px; border-bottom:1px solid rgba(255,255,255,.08); background:#1e3a5f;">
          <span id="pm-toast-title" style="font-weight:700;">Notice</span>
        </div>
        <div id="pm-toast-body" style="padding:14px 16px; line-height:1.5;"></div>
        <div style="padding:12px 16px; display:flex; justify-content:flex-end; gap:8px;">
          <button id="pm-toast-ok" style="background:#67e8f9; color:#0f172a; border:0; padding:8px 14px; border-radius:8px; font-weight:600; cursor:pointer;">OK</button>
        </div>
      </div>
    </div>`;
  document.body.appendChild(wrap);

  const modal = document.getElementById('pm-toast-modal');
  const title = document.getElementById('pm-toast-title');
  const body = document.getElementById('pm-toast-body');

  function open(type, message) {
    title.textContent = type === 'error' ? 'Error' : type === 'success' ? 'Success' : 'Notice';
    body.textContent = message;
    modal.style.display = 'flex';
  }
  function close() { modal.style.display = 'none'; }

  document.getElementById('pm-toast-ok').addEventListener('click', close);
  modal.addEventListener('click', (e) => { if (e.target === modal) close(); });

  window.showNotification = (type, message) => open(type, message);
}

// ✅ NEW: Custom confirmation modal
function initConfirmationModal() {
  if (document.getElementById('pm-confirm-modal')) return;

  const wrap = document.createElement('div');
  wrap.innerHTML = `
    <div id="pm-confirm-modal" style="position:fixed; inset:0; display:none; align-items:center; justify-content:center; z-index:9999; backdrop-filter: blur(2px); background: rgba(0,0,0,.35);">
      <div id="pm-confirm-box" style="min-width:320px; max-width:520px; background:#111827; color:#e5e7eb; border-radius:12px; box-shadow:0 12px 24px rgba(0,0,0,.25); overflow:hidden;">
        <div id="pm-confirm-head" style="display:flex; align-items:center; gap:10px; padding:14px 16px; border-bottom:1px solid rgba(255,255,255,.08); background:#1e3a5f;">
          <span id="pm-confirm-title" style="font-weight:700;">Confirm Action</span>
        </div>
        <div id="pm-confirm-body" style="padding:14px 16px; line-height:1.5;"></div>
        <div style="padding:12px 16px; display:flex; justify-content:flex-end; gap:8px;">
          <button id="pm-confirm-cancel" style="background:#374151; color:#e5e7eb; border:0; padding:8px 14px; border-radius:8px; font-weight:600; cursor:pointer;">Cancel</button>
          <button id="pm-confirm-ok" style="background:#67e8f9; color:#0f172a; border:0; padding:8px 14px; border-radius:8px; font-weight:600; cursor:pointer;">OK</button>
        </div>
      </div>
    </div>`;
  document.body.appendChild(wrap);

  const modal = document.getElementById('pm-confirm-modal');
  const body = document.getElementById('pm-confirm-body');
  let resolvePromise = null;

  function open(message) {
    return new Promise((resolve) => {
      resolvePromise = resolve;
      body.textContent = message;
      modal.style.display = 'flex';
    });
  }

  function close(result) {
    modal.style.display = 'none';
    if (resolvePromise) {
      resolvePromise(result);
      resolvePromise = null;
    }
  }

  document.getElementById('pm-confirm-ok').addEventListener('click', () => close(true));
  document.getElementById('pm-confirm-cancel').addEventListener('click', () => close(false));
  modal.addEventListener('click', (e) => {
    if (e.target === modal) close(false);
  });

  window.showConfirmation = (message) => open(message);
}

function injectPMStyles() {
  if (document.getElementById('pm-style-patch')) return;
  const style = document.createElement('style');
  style.id = 'pm-style-patch';
  style.textContent = `
    .customizable-row { display:inline-flex; align-items:center; gap:8px; margin:10px 0; }
    #productImagePreview { display:block; max-width:240px; max-height:180px; object-fit:cover;
      margin-top:12px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,.08); }
    #addProductModal .modal-content { max-height:90vh; overflow:auto; }
    .image-preview-item { display:inline-block; margin:5px; vertical-align: top; }
    .image-preview-item img { display:block; border-radius:8px; }
    .primary-badge { font-weight:bold; font-size:10px; }
    .product-img { width:80px; height:80px; object-fit:cover; border-radius:8px; border:1px solid #e5e7eb; }
  `;
  document.head.appendChild(style);
}

function openModal(id) { const m = document.getElementById(id); if (m) { m.classList.add('show'); document.body.style.overflow = 'hidden'; } }
function closeModal(id) { const m = document.getElementById(id); if (m) { m.classList.remove('show'); document.body.style.overflow = ''; } }

function updateModelRequirement() {
  const cb = document.getElementById('isCustomizable');
  const model = document.getElementById('productModel');
  const prev = document.getElementById('productModelPreview');
  if (!cb || !model) return;

  model.disabled = !cb.checked;
  model.required = false;
  if (!cb.checked) {
    model.value = '';
    if (prev) { prev.textContent = ''; prev.style.display = 'none'; delete prev.dataset.filename; }
  }
}

function resetAddProductForm(closeAfter) {
  const form = document.getElementById('addProductForm');
  if (form) {
    form.reset();
    // 🔥 FIX: Clear all dataset attributes
    delete form.dataset.editingId;
    delete form.dataset.mode;
    form.removeAttribute('data-editing-id');
    form.removeAttribute('data-mode');
  }

  const previewContainer = document.getElementById('imagePreviewContainer');
  if (previewContainer) previewContainer.innerHTML = '';

  const imgInput = document.getElementById('productImage');
  if (imgInput) imgInput.value = '';
  const modelInput = document.getElementById('productModel');
  if (modelInput) modelInput.value = '';

  // 🔥 FIX: Clear BOTH global and window level arrays
  uploadedProductImages = [];
  window.uploadedProductImages = [];

  // 🔥 FIX: Clear current product reference
  window.currentProduct = null;

  if (closeAfter) closeModal('addProductModal');
}

function setField(id, value, isCheckbox = false) {
  const el = document.getElementById(id);
  if (!el) return;
  if (isCheckbox) {
    el.checked = !!value;
    el.dispatchEvent(new Event('change'));
  } else {
    el.value = value ?? '';
    el.dispatchEvent(new Event('input'));
  }
}

// ===== Image Manager (modal) functions =====
async function openImageManager(productId) {
  try {
    const response = await fetch(`/backend/api/product_images.php?action=list&product_id=${productId}`, {
      credentials: 'same-origin'
    });
    const result = await response.json();

    if (!result.success) {
      showNotification('error', result.message || 'Failed to load images');
      return;
    }

    const images = result.data || [];

    const product = allProducts.find(p => p.id === productId);
    const productName = product ? product.name : 'Product';

    const modalHTML = `
      <div class="image-manager-modal" id="imageManagerModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 10000; display: flex; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
        <div style="background: white; padding: 30px; border-radius: 12px; max-width: 900px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 15px;">
            <h2 style="margin: 0; color: #333; font-size: 24px;">Manage Images - ${productName}</h2>
            <button onclick="closeImageManager()" style="background: transparent; border: none; font-size: 28px; cursor: pointer; color: #666; line-height: 1;">&times;</button>
          </div>

          <div id="imageManagerGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; margin: 20px 0; min-height: 150px;">
            ${images.length === 0 ? '<p style="grid-column: 1/-1; text-align: center; color: #999; padding: 40px 0;">No images yet. Upload images below.</p>' : ''}
            ${images.map(img => `
              <div class="image-item" data-image-id="${img.image_id}" style="position: relative; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: transform 0.2s;">
                <img src="/${normalizeSrc(img.image_path)}" style="width: 100%; height: 180px; object-fit: cover; display: block;" onerror="this.onerror=null; this.src='/uploads/products/placeholder.jpg'">
                ${img.is_primary ? '<div style="position: absolute; top: 8px; left: 8px; background: #4CAF50; color: white; padding: 6px 10px; font-size: 11px; border-radius: 4px; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">PRIMARY</div>' : ''}
                <div style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.7)); padding: 10px 8px 8px; display: flex; gap: 4px; justify-content: center;">
                  ${!img.is_primary ? `<button onclick="setPrimaryImage(${img.image_id}, ${productId})" style="flex: 1; background: #2196F3; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold;">Set Primary</button>` : ''}
                  <button onclick="deleteProductImage(${img.image_id}, ${productId})" style="flex: 1; background: #f44336; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold;">Delete</button>
                </div>
              </div>
            `).join('')}
          </div>

          <div style="margin: 25px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <label style="display: block; margin-bottom: 10px; font-weight: 600; color: #333; font-size: 15px;">
              <span class="material-symbols-rounded" style="vertical-align: middle; margin-right: 5px;">add_photo_alternate</span>
              Add More Images
            </label>
            <input type="file" id="additionalImages" accept="image/*" multiple style="margin-bottom: 12px; padding: 8px; width: 100%; border: 2px dashed #ddd; border-radius: 6px; background: white;">
            <button onclick="uploadAdditionalImages(${productId})" style="background: #4CAF50; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%; font-size: 14px;">
              <span class="material-symbols-rounded" style="vertical-align: middle; margin-right: 5px; font-size: 18px;">cloud_upload</span>
              Upload Images
            </button>
          </div>

          <div style="text-align: right; margin-top: 20px; padding-top: 15px; border-top: 2px solid #eee;">
            <button onclick="closeImageManager()" style="background: #666; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 14px;">Close</button>
          </div>
        </div>
      </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
  } catch (error) {
    console.error('Error opening image manager:', error);
    showNotification('error', 'Failed to open image manager');
  }
}

function closeImageManager() {
  const modal = document.getElementById('imageManagerModal');
  if (modal) {
    modal.remove();
  }
  // refresh list to reflect changes (primary may have changed)
  productPrimaryImageCache.clear();
  loadProducts();
}

async function setPrimaryImage(imageId, productId) {
  try {
    const fd = new FormData();
    fd.append('image_id', imageId);

    const response = await fetch('/backend/api/product_images.php?action=set_primary', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    });

    const result = await response.json().catch(() => ({ success: false }));

    if (result.success) {
      showNotification('success', 'Primary image updated successfully');
      closeImageManager();
      // invalidate cache and reopen briefly to reflect change if needed
      productPrimaryImageCache.delete(Number(productId));
      setTimeout(() => openImageManager(productId), 500);
    } else {
      showNotification('error', result.message || 'Failed to set primary image');
    }
  } catch (error) {
    console.error('Error setting primary image:', error);
    showNotification('error', 'Failed to set primary image');
  }
}

async function deleteProductImage(imageId, productId) {
  if (!confirm('Are you sure you want to delete this image?')) return;

  try {
    const fd = new FormData();
    fd.append('image_id', imageId);

    const response = await fetch('/backend/api/product_images.php?action=delete', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    });

    const result = await response.json().catch(() => ({ success: false }));

    if (result.success) {
      showNotification('success', 'Image deleted successfully');
      closeImageManager();
      productPrimaryImageCache.delete(Number(productId));
      setTimeout(() => openImageManager(productId), 500);
    } else {
      showNotification('error', result.message || 'Failed to delete image');
    }
  } catch (error) {
    console.error('Error deleting image:', error);
    showNotification('error', 'Failed to delete image');
  }
}

async function uploadAdditionalImages(productId) {
  const input = document.getElementById('additionalImages');
  if (!input || input.files.length === 0) {
    showNotification('error', 'Please select images to upload');
    return;
  }

  try {
    const formData = new FormData();
    formData.append('product_id', productId);
    for (let i = 0; i < input.files.length; i++) {
      formData.append('images[]', input.files[i]);
    }

    showNotification('info', 'Uploading images...');

    const response = await fetch('/backend/api/product_images.php?action=upload', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    });

    const result = await response.json().catch(() => ({ success: false }));

    if (result.success) {
      showNotification('success', result.message || 'Images uploaded successfully');
      closeImageManager();
      productPrimaryImageCache.delete(Number(productId));
      setTimeout(() => openImageManager(productId), 500);
    } else {
      showNotification('error', result.message || 'Failed to upload images');
    }
  } catch (error) {
    console.error('Error uploading images:', error);
    showNotification('error', 'Failed to upload images');
  }
}

// ===== End of file =====