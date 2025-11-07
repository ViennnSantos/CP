/**
 * Address Management JavaScript
 * Handles CRUD operations for customer addresses with PSGC support
 */

// Use existing API_BASE and CSRF_TOKEN from profile.php if available

// Otherwise, declare them here for standalone use
if (typeof API_BASE === 'undefined') {
    var API_BASE = '/RADS-TOOLING/backend/api';
}
if (typeof CSRF_TOKEN === 'undefined') {
    var CSRF_TOKEN = document.querySelector('input[name="csrf_token"]')?.value ||
        (typeof CSRF !== 'undefined' ? CSRF : '');
}

// PSGC Data Cache
let psgcProvinces = [];
let psgcCities = {};
let psgcBarangays = {};

/**
 * Safe helper: call showAddressForm if available, otherwise fallback
 */
function safeShowAddressForm() {
    if (typeof window.showAddressForm === 'function') {
        try {
            window.showAddressForm();
        } catch (err) {
            console.warn('showAddressForm exists but threw an error:', err);
            // best-effort fallback: open modal directly if present
            const modal = document.getElementById('addressFormModal');
            if (modal) openModal(modal);
        }
    } else {
        console.warn('showAddressForm not loaded yet — opening fallback modal (if present). Check console/network for address_management.js load errors.');
        const modal = document.getElementById('addressFormModal');
        if (modal) openModal(modal);
    }
}

/**
 * Load addresses when address tab is viewed
 * Initialize PSGC and modal handlers
 * Attach click handlers for Add buttons (safe binding)
 */
document.addEventListener('DOMContentLoaded', () => {
    // Load addresses when address tab is clicked
    const addressTabLink = document.querySelector('.menu-item[data-tab="address"]');
    if (addressTabLink) {
        addressTabLink.addEventListener('click', loadAddresses);
    }

    // Initialize PSGC data
    initializePSGC();

    // Setup modal handlers
    setupAddressModal();

    // Attach safe bindings for "Add New Address" buttons (if present)
    // Buttons in profile.php should have IDs: addAddressBtn, addAddressBtnFirst
    const addBtn = document.getElementById('addAddressBtn');
    if (addBtn) {
        // remove inline onclick to avoid duplicate calls (optional)
        addBtn.removeAttribute && addBtn.removeAttribute('onclick');
        addBtn.addEventListener('click', safeShowAddressForm);
    }

    const addBtnFirst = document.getElementById('addAddressBtnFirst');
    if (addBtnFirst) {
        addBtnFirst.removeAttribute && addBtnFirst.removeAttribute('onclick');
        addBtnFirst.addEventListener('click', safeShowAddressForm);
    }

    // Load addresses on initial page load if on address tab (hash)
    if (window.location.hash === '#address' || document.getElementById('address-tab')?.classList.contains('active')) {
        loadAddresses();
    }

    // Expose debug flag so fallback script in profile.php can detect this script ran
    window.__addr_dbg_loaded = true;
});

/**
 * Initialize PSGC dropdowns
 */
async function initializePSGC() {
    try {
        // Load provinces
        const response = await fetch('/RADS-TOOLING/backend/api/psgc.php?endpoint=provinces');
        const provinces = await response.json();

        // Filter to NCR and Calabarzon only
        const allowedProvinces = [
            'Metropolitan Manila',
            'NCR, City of Manila, First District',
            'Cavite',
            'Laguna',
            'Batangas',
            'Rizal',
            'Quezon'
        ];

        psgcProvinces = provinces.filter(p =>
            allowedProvinces.some(ap => p.name.includes(ap) || ap.includes(p.name))
        );

        // Populate province dropdown
        const provinceSelect = document.getElementById('addressProvince');
        if (provinceSelect) {
            provinceSelect.innerHTML = '<option value="">Select Province</option>';
            psgcProvinces.forEach(prov => {
                const option = document.createElement('option');
                option.value = prov.name;
                option.dataset.code = prov.code;
                option.textContent = prov.name;
                provinceSelect.appendChild(option);
            });
        }

    } catch (error) {
        console.error('Failed to load PSGC data:', error);
    }
}

/**
 * Setup address modal handlers
 */
function setupAddressModal() {
    const modal = document.getElementById('addressFormModal');
    const form = document.getElementById('addressManageForm');
    const cancelBtn = document.getElementById('addressFormCancel');

    // Cancel button
    cancelBtn?.addEventListener('click', () => closeModal(modal));

    // Close on backdrop click
    modal?.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal(modal);
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
            closeModal(modal);
        }
    });

    // Form submit
    form?.addEventListener('submit', saveAddress);

    // Province change - load cities
    const provinceSelect = document.getElementById('addressProvince');
    provinceSelect?.addEventListener('change', async (e) => {
        const selectedOption = e.target.options[e.target.selectedIndex] || {};
        const provinceCode = selectedOption.dataset?.code;
        const provinceName = selectedOption.value || '';

        // Update hidden field
        const provCodeEl = document.getElementById('addressProvinceCode');
        if (provCodeEl) provCodeEl.value = provinceCode || '';

        // Clear city and barangay
        const citySelect = document.getElementById('addressCity');
        const barangaySelect = document.getElementById('addressBarangay');
        if (citySelect) citySelect.innerHTML = '<option value="">Loading cities...</option>';
        if (barangaySelect) barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        const cityCodeEl = document.getElementById('addressCityCode');
        const brgyCodeEl = document.getElementById('addressBarangayCode');
        if (cityCodeEl) cityCodeEl.value = '';
        if (brgyCodeEl) brgyCodeEl.value = '';

        if (!provinceCode) {
            if (citySelect) citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
            return;
        }

        // Special handling for NCR
        if (provinceName.includes('Metropolitan Manila') || provinceName.includes('NCR')) {
            loadNCRCities(citySelect);
            return;
        }

        // Load cities for selected province
        try {
            // Check cache first
            if (psgcCities[provinceCode]) {
                populateCitySelect(citySelect, psgcCities[provinceCode]);
                return;
            }

            const response = await fetch(`/RADS-TOOLING/backend/api/psgc.php?endpoint=provinces/${provinceCode}/cities`);
            const cities = await response.json();

            // Cache the cities
            psgcCities[provinceCode] = cities;

            populateCitySelect(citySelect, cities);
        } catch (error) {
            console.error('Failed to load cities:', error);
            if (citySelect) citySelect.innerHTML = '<option value="">Failed to load cities</option>';
        }
    });

    // City change - load barangays
    const citySelect = document.getElementById('addressCity');
    citySelect?.addEventListener('change', async (e) => {
        const selectedOption = e.target.options[e.target.selectedIndex] || {};
        const cityCode = selectedOption.dataset?.code;

        // Update hidden field
        const cityCodeEl = document.getElementById('addressCityCode');
        if (cityCodeEl) cityCodeEl.value = cityCode || '';

        // Clear barangay
        const barangaySelect = document.getElementById('addressBarangay');
        if (barangaySelect) barangaySelect.innerHTML = '<option value="">Loading barangays...</option>';
        const brgyCodeEl = document.getElementById('addressBarangayCode');
        if (brgyCodeEl) brgyCodeEl.value = '';

        if (!cityCode) {
            if (barangaySelect) barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            return;
        }

        // Load barangays for selected city
        try {
            // Check cache first
            if (psgcBarangays[cityCode]) {
                populateBarangaySelect(barangaySelect, psgcBarangays[cityCode]);
                return;
            }

            const response = await fetch(`/RADS-TOOLING/backend/api/psgc.php?endpoint=cities/${cityCode}/barangays`);
            const barangays = await response.json();

            // Cache the barangays
            psgcBarangays[cityCode] = barangays;

            populateBarangaySelect(barangaySelect, barangays);
        } catch (error) {
            console.error('Failed to load barangays:', error);
            if (barangaySelect) barangaySelect.innerHTML = '<option value="">Failed to load barangays</option>';
        }
    });

    // Barangay change - update hidden field
    const barangaySelect = document.getElementById('addressBarangay');
    barangaySelect?.addEventListener('change', (e) => {
        const selectedOption = e.target.options[e.target.selectedIndex] || {};
        const barangayCode = selectedOption.dataset?.code;
        const brgyCodeEl = document.getElementById('addressBarangayCode');
        if (brgyCodeEl) brgyCodeEl.value = barangayCode || '';
    });
}

/**
 * Load NCR cities (hardcoded list)
 */
function loadNCRCities(citySelect) {
    const ncrCities = [
        { name: 'Caloocan', code: '137404000' },
        { name: 'Las Piñas', code: '137601000' },
        { name: 'Makati', code: '137602000' },
        { name: 'Malabon', code: '137603000' },
        { name: 'Mandaluyong', code: '137604000' },
        { name: 'Manila', code: '133900000' },
        { name: 'Marikina', code: '137605000' },
        { name: 'Muntinlupa', code: '137606000' },
        { name: 'Navotas', code: '137607000' },
        { name: 'Parañaque', code: '137608000' },
        { name: 'Pasay', code: '137609000' },
        { name: 'Pasig', code: '137610000' },
        { name: 'Pateros', code: '137611000' },
        { name: 'Quezon City', code: '137404000' },
        { name: 'San Juan', code: '137612000' },
        { name: 'Taguig', code: '137613000' },
        { name: 'Valenzuela', code: '137614000' }
    ];

    populateCitySelect(citySelect, ncrCities);
}

/**
 * Populate city select with options
 */
function populateCitySelect(citySelect, cities) {
    if (!citySelect) return;
    citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
    cities.forEach(city => {
        const option = document.createElement('option');
        option.value = city.name;
        option.dataset.code = city.code;
        option.textContent = city.name;
        citySelect.appendChild(option);
    });
}

/**
 * Populate barangay select with options
 */
function populateBarangaySelect(barangaySelect, barangays) {
    if (!barangaySelect) return;
    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
    barangays.forEach(brgy => {
        const option = document.createElement('option');
        option.value = brgy.name;
        option.dataset.code = brgy.code;
        option.textContent = brgy.name;
        barangaySelect.appendChild(option);
    });
}

/**
 * Load all addresses for customer
 */
async function loadAddresses() {
    const listContainer = document.getElementById('address-list');
    const loadingEl = document.getElementById('address-list-loading');
    const noAddressesEl = document.getElementById('no-addresses');

    // Defensive checks
    if (!listContainer) return;

    // Show loading
    if (loadingEl) loadingEl.style.display = 'block';
    listContainer.innerHTML = '';
    if (noAddressesEl) noAddressesEl.style.display = 'none';

    try {
        const response = await fetch(`${API_BASE}/customer_addresses.php?action=list`, {
            credentials: 'include'
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Failed to load addresses');
        }

        if (loadingEl) loadingEl.style.display = 'none';

        if (!data.addresses || data.addresses.length === 0) {
            if (noAddressesEl) noAddressesEl.style.display = 'block';
            return;
        }

        // Render addresses
        listContainer.innerHTML = data.addresses.map(addr => renderAddressCard(addr)).join('');

    } catch (error) {
        console.error('Failed to load addresses:', error);
        if (loadingEl) loadingEl.style.display = 'none';
        listContainer.innerHTML = `<p style="color: red; text-align: center;">Failed to load addresses: ${error.message}</p>`;
    }
}

/**
 * Render single address card
 */
function renderAddressCard(addr) {
    const isDefault = addr.is_default == 1;
    const defaultBadge = isDefault ? '<span class="badge badge-primary">Default</span>' : '';

    return `
        <div class="address-card" data-id="${addr.id}">
            <div class="address-card-header">
                <div class="address-header-left">
                    ${addr.address_nickname ? `<strong>${escapeHtml(addr.address_nickname)}</strong>` : ''}
                    ${defaultBadge}
                </div>
                <div class="address-actions">
                    ${!isDefault ? `<button class="btn-icon" onclick="setDefaultAddress(${addr.id})" title="Set as default">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                    </button>` : ''}
                    <button class="btn-icon" onclick="editAddress(${addr.id})" title="Edit">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                    </button>
                    <button class="btn-icon btn-delete" onclick="deleteAddress(${addr.id})" title="Delete">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="address-card-body">
                <p><strong>${escapeHtml(addr.full_name)}</strong></p>
                <p>${escapeHtml(addr.mobile_number)}</p>
                ${addr.email ? `<p>${escapeHtml(addr.email)}</p>` : ''}
                <p class="address-text">
                    ${escapeHtml(addr.street_block_lot)}<br>
                    ${escapeHtml(addr.barangay)}, ${escapeHtml(addr.city_municipality)}<br>
                    ${escapeHtml(addr.province)}${addr.postal_code ? ', ' + escapeHtml(addr.postal_code) : ''}
                </p>
            </div>
        </div>
    `;
}

/**
 * Show address form modal (add new)
 */
function showAddressForm() {
    const modal = document.getElementById('addressFormModal');
    const form = document.getElementById('addressManageForm');
    const title = document.getElementById('addressModalTitle');

    if (!form || !modal || !title) {
        console.warn('Address form elements not found in DOM.');
        return;
    }

    // Reset form
    form.reset();
    const editId = document.getElementById('addressEditId');
    if (editId) editId.value = '';
    title.textContent = 'Add New Address';

    // Clear hidden codes
    const provCode = document.getElementById('addressProvinceCode');
    const cityCode = document.getElementById('addressCityCode');
    const brgyCode = document.getElementById('addressBarangayCode');
    if (provCode) provCode.value = '';
    if (cityCode) cityCode.value = '';
    if (brgyCode) brgyCode.value = '';

    // Clear message
    const msgEl = document.getElementById('addressFormMsg');
    if (msgEl) {
        msgEl.textContent = '';
        msgEl.className = 'msg';
    }

    openModal(modal);
}

// Expose to global scope for inline onclick handlers
window.showAddressForm = showAddressForm;


/**
 * Edit existing address
 */
async function editAddress(addressId) {
    const modal = document.getElementById('addressFormModal');
    const form = document.getElementById('addressManageForm');
    const title = document.getElementById('addressModalTitle');

    if (!modal || !form || !title) {
        alert('Address form not available.');
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/customer_addresses.php?action=get&id=${addressId}`, {
            credentials: 'include'
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Failed to load address');
        }

        const addr = data.address;

        // Populate form
        document.getElementById('addressEditId').value = addr.id;
        document.getElementById('addressNickname').value = addr.address_nickname || '';
        document.getElementById('addressFullName').value = addr.full_name;
        document.getElementById('addressPhoneLocal').value = addr.mobile_number.replace('+63', '');
        document.getElementById('addressPhone').value = addr.mobile_number;
        document.getElementById('addressEmail').value = addr.email || '';
        document.getElementById('addressStreet').value = addr.street_block_lot;
        document.getElementById('addressPostal').value = addr.postal_code || '';
        document.getElementById('addressIsDefault').checked = addr.is_default == 1;

        // Set province
        const provinceSelect = document.getElementById('addressProvince');
        if (provinceSelect) provinceSelect.value = addr.province;
        const provCodeEl = document.getElementById('addressProvinceCode');
        if (provCodeEl) provCodeEl.value = addr.province_code || '';

        // Trigger province change to load cities
        const provinceOption = provinceSelect?.options[provinceSelect.selectedIndex];
        const provinceCode = provinceOption?.dataset?.code || addr.province_code;

        if (provinceCode) {
            // Load cities
            const citySelect = document.getElementById('addressCity');
            if (citySelect) citySelect.innerHTML = '<option value="">Loading...</option>';

            if (addr.province.includes('Metropolitan Manila') || addr.province.includes('NCR')) {
                loadNCRCities(citySelect);
                setTimeout(() => {
                    if (citySelect) citySelect.value = addr.city_municipality;
                    const cityCodeEl = document.getElementById('addressCityCode');
                    if (cityCodeEl) cityCodeEl.value = addr.city_code || '';
                    loadBarangaysForEdit(addr);
                }, 100);
            } else {
                try {
                    const citiesResponse = await fetch(`/RADS-TOOLING/backend/api/psgc.php?endpoint=provinces/${provinceCode}/cities`);
                    const cities = await citiesResponse.json();
                    psgcCities[provinceCode] = cities;
                    populateCitySelect(citySelect, cities);

                    setTimeout(() => {
                        if (citySelect) citySelect.value = addr.city_municipality;
                        const cityCodeEl = document.getElementById('addressCityCode');
                        if (cityCodeEl) cityCodeEl.value = addr.city_code || '';
                        loadBarangaysForEdit(addr);
                    }, 100);
                } catch (err) {
                    console.error('Failed to load cities for edit:', err);
                }
            }
        }

        title.textContent = 'Edit Address';
        openModal(modal);

    } catch (error) {
        console.error('Failed to load address:', error);
        alert('Failed to load address: ' + error.message);
    }
}

// Expose to global scope for inline onclick handlers
window.editAddress = editAddress;

/**
 * Load barangays when editing address
 */
async function loadBarangaysForEdit(addr) {
    const cityCode = addr.city_code;
    if (!cityCode) return;

    const barangaySelect = document.getElementById('addressBarangay');
    if (!barangaySelect) return;
    barangaySelect.innerHTML = '<option value="">Loading...</option>';

    try {
        const response = await fetch(`/RADS-TOOLING/backend/api/psgc.php?endpoint=cities/${cityCode}/barangays`);
        const barangays = await response.json();
        psgcBarangays[cityCode] = barangays;
        populateBarangaySelect(barangaySelect, barangays);

        setTimeout(() => {
            barangaySelect.value = addr.barangay;
            const brgyCodeEl = document.getElementById('addressBarangayCode');
            if (brgyCodeEl) brgyCodeEl.value = addr.barangay_code || '';
        }, 100);
    } catch (error) {
        console.error('Failed to load barangays:', error);
        barangaySelect.innerHTML = '<option value="">Failed to load</option>';
    }
}

/**
 * Save address (create or update)
 */
async function saveAddress(e) {
    e.preventDefault();

    const submitBtn = document.getElementById('addressFormSubmit');
    const btnText = submitBtn?.querySelector('.btn-text');
    const btnSpinner = submitBtn?.querySelector('.btn-spinner');
    const msgEl = document.getElementById('addressFormMsg');

    if (submitBtn) {
        submitBtn.disabled = true;
        if (btnText) btnText.style.display = 'none';
        if (btnSpinner) btnSpinner.style.display = 'inline-block';
    }
    if (msgEl) {
        msgEl.textContent = '';
        msgEl.className = 'msg';
    }

    const addressId = document.getElementById('addressEditId')?.value;
    const isEdit = !!addressId;

    const formData = new FormData();
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('action', isEdit ? 'update' : 'create');

    if (isEdit) {
        formData.append('id', addressId);
    }

    formData.append('address_nickname', document.getElementById('addressNickname')?.value.trim() || '');
    formData.append('full_name', document.getElementById('addressFullName')?.value.trim() || '');
    formData.append('mobile_number', document.getElementById('addressPhone')?.value || '');
    formData.append('email', document.getElementById('addressEmail')?.value.trim() || '');
    formData.append('province', document.getElementById('addressProvince')?.value || '');
    formData.append('province_code', document.getElementById('addressProvinceCode')?.value || '');
    formData.append('city_municipality', document.getElementById('addressCity')?.value || '');
    formData.append('city_code', document.getElementById('addressCityCode')?.value || '');
    formData.append('barangay', document.getElementById('addressBarangay')?.value || '');
    formData.append('barangay_code', document.getElementById('addressBarangayCode')?.value || '');
    formData.append('street_block_lot', document.getElementById('addressStreet')?.value.trim() || '');
    formData.append('postal_code', document.getElementById('addressPostal')?.value.trim() || '');
    formData.append('is_default', document.getElementById('addressIsDefault')?.checked ? '1' : '0');

    try {
        const response = await fetch(`${API_BASE}/customer_addresses.php`, {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Failed to save address');
        }

        if (msgEl) {
            msgEl.textContent = isEdit ? 'Address updated successfully!' : 'Address added successfully!';
            msgEl.className = 'msg success';
        }

        // Reload addresses
        setTimeout(() => {
            closeModal(document.getElementById('addressFormModal'));
            loadAddresses();
        }, 1000);

    } catch (error) {
        console.error('Failed to save address:', error);
        if (msgEl) {
            msgEl.textContent = error.message;
            msgEl.className = 'msg error';
        }

        // Re-enable button
        if (submitBtn) {
            submitBtn.disabled = false;
            if (btnText) btnText.style.display = 'inline';
            if (btnSpinner) btnSpinner.style.display = 'none';
        }
    }
}

/**
 * Delete address
 */
async function deleteAddress(addressId) {
    if (!confirm('Are you sure you want to delete this address?')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('action', 'delete');
        formData.append('id', addressId);

        const response = await fetch(`${API_BASE}/customer_addresses.php`, {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Failed to delete address');
        }

        // Reload addresses
        loadAddresses();

        // Show success message
        if (typeof showMessage === 'function') {
            showMessage('Address deleted successfully', 'success');
        }

    } catch (error) {
        console.error('Failed to delete address:', error);
        alert('Failed to delete address: ' + error.message);
    }
}

// Expose to global scope for inline onclick handlers
window.deleteAddress = deleteAddress;

/**
 * Set address as default
 */
async function setDefaultAddress(addressId) {
    try {
        const formData = new FormData();
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('action', 'set_default');
        formData.append('id', addressId);

        const response = await fetch(`${API_BASE}/customer_addresses.php`, {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Failed to set default address');
        }

        // Reload addresses
        loadAddresses();

        // Show success message
        if (typeof showMessage === 'function') {
            showMessage('Default address updated', 'success');
        }

    } catch (error) {
        console.error('Failed to set default address:', error);
        alert('Failed to set default address: ' + error.message);
    }
}

// Expose to global scope for inline onclick handlers
window.setDefaultAddress = setDefaultAddress;

/**
 * Modal helpers
 */
function openModal(modal) {
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modal) {
    if (modal) {    
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return (text || '').replace(/[&<>"']/g, m => map[m]);
}

// Debug: Confirm script loaded and functions exposed
console.log('✅ Address management script loaded');
console.log('✅ Global functions exposed:', {
    showAddressForm: typeof window.showAddressForm,
    editAddress: typeof window.editAddress,
    deleteAddress: typeof window.deleteAddress,
    setDefaultAddress: typeof window.setDefaultAddress
});
