/**
 * Address Management JavaScript
 * Handles CRUD operations for customer addresses with PSGC support
 */

const __API_BASE = (typeof API_BASE !== 'undefined') ? API_BASE : '/backend/api';
const __CSRF_TOKEN = (typeof CSRF_TOKEN !== 'undefined')
    ? CSRF_TOKEN
    : (document.querySelector('input[name="csrf_token"]')?.value || (typeof CSRF !== 'undefined' ? CSRF : ''));

// PSGC Data Cache
let psgcProvinces = [];
let psgcCities = {};
let psgcBarangays = {};

/**
 * Fetch with timeout to prevent 504 errors
 */
async function fetchWithTimeout(url, options = {}, timeout = 15000) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), timeout);

    try {
        const response = await fetch(url, {
            ...options,
            signal: controller.signal
        });
        clearTimeout(timeoutId);
        return response;
    } catch (error) {
        clearTimeout(timeoutId);
        if (error.name === 'AbortError') {
            throw new Error('Request timeout - server took too long to respond');
        }
        throw error;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const addressTabLink = document.querySelector('.menu-item[data-tab="address"]');
    if (addressTabLink) {
        addressTabLink.addEventListener('click', loadAddresses);
    }

    setupAddressModal();
    initializePSGC();

    if (window.location.hash === '#address') {
        loadAddresses();
    }
});

async function initializePSGC() {
    try {
        const response = await fetchWithTimeout('/backend/api/psgc.php?endpoint=provinces');

        if (!response.ok) {
            const errorText = await response.text();
            console.error('Province API failed:', response.status, errorText.substring(0, 300));
            throw new Error(`Failed to load provinces: ${response.status}`);
        }

        const provinces = await response.json();

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
        const provinceSelect = document.getElementById('addressProvince');
        if (provinceSelect) {
            provinceSelect.innerHTML = '<option value="">Failed to load provinces - please refresh</option>';
        }
    }
}

function setupAddressModal() {
    const modal = document.getElementById('addressFormModal');
    const form = document.getElementById('addressManageForm');
    const cancelBtn = document.getElementById('addressFormCancel');

    cancelBtn?.addEventListener('click', () => closeModal(modal));

    const closeBtn = document.getElementById('addressModalCloseBtn');
    closeBtn?.addEventListener('click', () => closeModal(modal));

    const backBtn = document.getElementById('addressModalBackBtn');
    backBtn?.addEventListener('click', () => closeModal(modal));

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
            closeModal(modal);
        }
    });

    form?.addEventListener('submit', saveAddress);

    const phoneLocalInput = document.getElementById('addressPhoneLocal');
    if (phoneLocalInput) {
        phoneLocalInput.addEventListener('input', (e) => {
            e.target.value = e.target.value.replace(/\D/g, '').slice(0, 10);
        });
    }

    const provinceSelect = document.getElementById('addressProvince');
    provinceSelect?.addEventListener('change', async (e) => {
        const selectedOption = e.target.options[e.target.selectedIndex];
        const provinceCode = selectedOption.dataset.code;
        const provinceName = selectedOption.value;

        document.getElementById('addressProvinceCode').value = provinceCode || '';

        const citySelect = document.getElementById('addressCity');
        const barangaySelect = document.getElementById('addressBarangay');
        citySelect.innerHTML = '<option value="">Loading cities...</option>';
        barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        document.getElementById('addressCityCode').value = '';
        document.getElementById('addressBarangayCode').value = '';

        if (!provinceCode) {
            citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
            return;
        }

        if (provinceName.includes('Metropolitan Manila') || provinceName.includes('NCR')) {
            loadNCRCities(citySelect);
            return;
        }

        try {
            if (psgcCities[provinceCode]) {
                populateCitySelect(citySelect, psgcCities[provinceCode]);
                return;
            }

            const response = await fetchWithTimeout(`/backend/api/psgc.php?endpoint=provinces/${provinceCode}/cities-municipalities`);

            if (!response.ok) {
                const errorText = await response.text();
                console.error('Cities API failed:', response.status, errorText.substring(0, 300));
                citySelect.innerHTML = '<option value="">Failed to load cities - please try again</option>';
                return;
            }

            const cities = await response.json();

            if (!Array.isArray(cities) || cities.length === 0) {
                console.warn('No cities found for province:', provinceCode);
                citySelect.innerHTML = '<option value="">No cities found</option>';
                return;
            }

            psgcCities[provinceCode] = cities;
            populateCitySelect(citySelect, cities);
        } catch (error) {
            console.error('Exception loading cities:', error);
            citySelect.innerHTML = '<option value="">Error loading cities - please try again</option>';
        }
    });

    const citySelect = document.getElementById('addressCity');
    citySelect?.addEventListener('change', async (e) => {
        const selectedOption = e.target.options[e.target.selectedIndex];
        const cityCode = selectedOption.dataset.code;

        document.getElementById('addressCityCode').value = cityCode || '';

        const barangaySelect = document.getElementById('addressBarangay');
        barangaySelect.innerHTML = '<option value="">Loading barangays...</option>';
        document.getElementById('addressBarangayCode').value = '';

        if (!cityCode) {
            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            return;
        }

        try {
            if (psgcBarangays[cityCode]) {
                populateBarangaySelect(barangaySelect, psgcBarangays[cityCode]);
                return;
            }

            let response = await fetchWithTimeout(`/backend/api/psgc.php?endpoint=cities/${cityCode}/barangays`);
            let barangays = [];

            if (response.ok) {
                barangays = await response.json();
            }

            if (!Array.isArray(barangays) || barangays.length === 0) {
                response = await fetchWithTimeout(`/backend/api/psgc.php?endpoint=municipalities/${cityCode}/barangays`);
                if (response.ok) {
                    barangays = await response.json();
                }
            }

            if (!Array.isArray(barangays) || barangays.length === 0) {
                console.warn('No barangays found for city:', cityCode);
                barangaySelect.innerHTML = '<option value="">No barangays found</option>';
                return;
            }

            psgcBarangays[cityCode] = barangays;
            populateBarangaySelect(barangaySelect, barangays);
        } catch (error) {
            console.error('Failed to load barangays:', error);
            barangaySelect.innerHTML = '<option value="">Error loading barangays</option>';
        }
    });

    const barangaySelect = document.getElementById('addressBarangay');
    barangaySelect?.addEventListener('change', (e) => {
        const selectedOption = e.target.options[e.target.selectedIndex];
        const barangayCode = selectedOption.dataset.code;
        document.getElementById('addressBarangayCode').value = barangayCode || '';
    });
}

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

function populateCitySelect(citySelect, cities) {
    citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
    cities.forEach(city => {
        const option = document.createElement('option');
        option.value = city.name;
        option.dataset.code = city.code;
        option.textContent = city.name;
        citySelect.appendChild(option);
    });
}

function populateBarangaySelect(barangaySelect, barangays) {
    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
    barangays.forEach(brgy => {
        const option = document.createElement('option');
        option.value = brgy.name;
        option.dataset.code = brgy.code;
        option.textContent = brgy.name;
        barangaySelect.appendChild(option);
    });
}

async function loadAddresses() {
    const listContainer = document.getElementById('address-list');
    const loadingEl = document.getElementById('address-list-loading');
    const noAddressesEl = document.getElementById('no-addresses');

    loadingEl.style.display = 'block';
    listContainer.innerHTML = '';
    noAddressesEl.style.display = 'none';

    try {
        const response = await fetch(`${__API_BASE}/customer_addresses.php?action=list`, {
            credentials: 'include'
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Failed to load addresses');
        }

        loadingEl.style.display = 'none';

        if (data.addresses.length === 0) {
            noAddressesEl.style.display = 'block';
            return;
        }

        listContainer.innerHTML = data.addresses.map(addr => renderAddressCard(addr)).join('');

    } catch (error) {
        console.error('Failed to load addresses:', error);
        loadingEl.style.display = 'none';
        listContainer.innerHTML = `<p style="color: red; text-align: center;">Failed to load addresses: ${error.message}</p>`;
    }
}

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

function showAddressForm() {
    const modal = document.getElementById('addressFormModal');
    const form = document.getElementById('addressManageForm');
    const title = document.getElementById('addressModalTitle');

    form.reset();
    document.getElementById('addressEditId').value = '';
    title.textContent = 'Add New Address';

    document.getElementById('addressProvinceCode').value = '';
    document.getElementById('addressCityCode').value = '';
    document.getElementById('addressBarangayCode').value = '';

    document.getElementById('addressFormMsg').textContent = '';
    document.getElementById('addressFormMsg').className = 'msg';

    openModal(modal);
}

window.showAddressForm = showAddressForm;

async function editAddress(addressId) {
    const modal = document.getElementById('addressFormModal');
    const form = document.getElementById('addressManageForm');
    const title = document.getElementById('addressModalTitle');

    try {
        const response = await fetch(`${__API_BASE}/customer_addresses.php?action=get&id=${addressId}`, {
            credentials: 'include'
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Failed to load address');
        }

        const addr = data.address;

        document.getElementById('addressEditId').value = addr.id;
        document.getElementById('addressNickname').value = addr.address_nickname || '';
        document.getElementById('addressFullName').value = addr.full_name;
        document.getElementById('addressPhoneLocal').value = addr.mobile_number.replace('+63', '');
        document.getElementById('addressPhone').value = addr.mobile_number;
        document.getElementById('addressEmail').value = addr.email || '';
        document.getElementById('addressStreet').value = addr.street_block_lot;
        document.getElementById('addressPostal').value = addr.postal_code || '';
        document.getElementById('addressIsDefault').checked = addr.is_default == 1;

        const provinceSelect = document.getElementById('addressProvince');
        provinceSelect.value = addr.province;
        document.getElementById('addressProvinceCode').value = addr.province_code || '';

        const provinceOption = provinceSelect.options[provinceSelect.selectedIndex];
        const provinceCode = provinceOption.dataset.code || addr.province_code;

        if (provinceCode) {
            const citySelect = document.getElementById('addressCity');
            citySelect.innerHTML = '<option value="">Loading...</option>';

            if (addr.province.includes('Metropolitan Manila') || addr.province.includes('NCR')) {
                loadNCRCities(citySelect);
                setTimeout(() => {
                    citySelect.value = addr.city_municipality;
                    document.getElementById('addressCityCode').value = addr.city_code || '';
                    loadBarangaysForEdit(addr);
                }, 100);
            } else {
                const citiesResponse = await fetchWithTimeout(`/backend/api/psgc.php?endpoint=provinces/${provinceCode}/cities-municipalities`);

                if (!citiesResponse.ok) {
                    console.error('Failed to load cities for edit:', citiesResponse.status);
                    citySelect.innerHTML = '<option value="">Failed to load cities</option>';
                    return;
                }

                const cities = await citiesResponse.json();

                if (!Array.isArray(cities) || cities.length === 0) {
                    citySelect.innerHTML = '<option value="">No cities found</option>';
                    return;
                }

                psgcCities[provinceCode] = cities;
                populateCitySelect(citySelect, cities);

                setTimeout(() => {
                    citySelect.value = addr.city_municipality;
                    document.getElementById('addressCityCode').value = addr.city_code || '';
                    loadBarangaysForEdit(addr);
                }, 100);
            }
        }

        title.textContent = 'Edit Address';
        openModal(modal);

    } catch (error) {
        console.error('Failed to load address:', error);
        alert('Failed to load address: ' + error.message);
    }
}

window.editAddress = editAddress;

async function loadBarangaysForEdit(addr) {
    const cityCode = addr.city_code;
    if (!cityCode) return;

    const barangaySelect = document.getElementById('addressBarangay');
    barangaySelect.innerHTML = '<option value="">Loading...</option>';

    try {
        let response = await fetchWithTimeout(`/backend/api/psgc.php?endpoint=cities/${cityCode}/barangays`);
        let barangays = [];

        if (response.ok) {
            barangays = await response.json();
        }

        if (!Array.isArray(barangays) || barangays.length === 0) {
            response = await fetchWithTimeout(`/backend/api/psgc.php?endpoint=municipalities/${cityCode}/barangays`);
            if (response.ok) {
                barangays = await response.json();
            }
        }

        if (!Array.isArray(barangays) || barangays.length === 0) {
            console.warn('No barangays found for edit mode, city:', cityCode);
            barangaySelect.innerHTML = '<option value="">No barangays found</option>';
            return;
        }

        psgcBarangays[cityCode] = barangays;
        populateBarangaySelect(barangaySelect, barangays);

        setTimeout(() => {
            barangaySelect.value = addr.barangay;
            document.getElementById('addressBarangayCode').value = addr.barangay_code || '';
        }, 100);
    } catch (error) {
        console.error('Failed to load barangays:', error);
        barangaySelect.innerHTML = '<option value="">Failed to load</option>';
    }
}

async function saveAddress(e) {
    e.preventDefault();

    const submitBtn = document.getElementById('addressFormSubmit');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnSpinner = submitBtn.querySelector('.btn-spinner');
    const msgEl = document.getElementById('addressFormMsg');

    submitBtn.disabled = true;
    btnText.style.display = 'none';
    btnSpinner.style.display = 'inline-block';
    msgEl.textContent = '';
    msgEl.className = 'msg';

    const addressId = document.getElementById('addressEditId').value;
    const isEdit = !!addressId;

    const phoneLocal = document.getElementById('addressPhoneLocal').value.trim();
    if (!phoneLocal || phoneLocal.length !== 10) {
        msgEl.textContent = 'Please enter 10 digits after +63 (e.g., 9123456789)';
        msgEl.className = 'msg error';
        submitBtn.disabled = false;
        btnText.style.display = 'inline';
        btnSpinner.style.display = 'none';
        return;
    }
    const composedPhone = `+63${phoneLocal}`;
    document.getElementById('addressPhone').value = composedPhone;

    const formData = new FormData();
    formData.append('csrf_token', __CSRF_TOKEN);
    formData.append('action', isEdit ? 'update' : 'create');

    if (isEdit) {
        formData.append('id', addressId);
    }

    formData.append('address_nickname', document.getElementById('addressNickname').value.trim());
    formData.append('full_name', document.getElementById('addressFullName').value.trim());
    formData.append('mobile_number', composedPhone);
    formData.append('email', document.getElementById('addressEmail').value.trim());
    formData.append('province', document.getElementById('addressProvince').value);
    formData.append('province_code', document.getElementById('addressProvinceCode').value);
    formData.append('city_municipality', document.getElementById('addressCity').value);
    formData.append('city_code', document.getElementById('addressCityCode').value);
    formData.append('barangay', document.getElementById('addressBarangay').value);
    formData.append('barangay_code', document.getElementById('addressBarangayCode').value);
    formData.append('street_block_lot', document.getElementById('addressStreet').value.trim());
    formData.append('postal_code', document.getElementById('addressPostal').value.trim());
    formData.append('is_default', document.getElementById('addressIsDefault').checked ? '1' : '0');

    try {
        const response = await fetch(`${__API_BASE}/customer_addresses.php`, {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Failed to save address');
        }

        msgEl.textContent = isEdit ? 'Address updated successfully!' : 'Address added successfully!';
        msgEl.className = 'msg success';

        setTimeout(() => {
            closeModal(document.getElementById('addressFormModal'));
            loadAddresses();

            submitBtn.disabled = false;
            btnText.style.display = 'inline';
            btnSpinner.style.display = 'none';
        }, 1000);

    } catch (error) {
        console.error('Failed to save address:', error);
        msgEl.textContent = error.message;
        msgEl.className = 'msg error';

        submitBtn.disabled = false;
        btnText.style.display = 'inline';
        btnSpinner.style.display = 'none';
    }
}

function deleteAddress(addressId) {
    showDeleteConfirmModal(addressId);
}

function showDeleteConfirmModal(addressId) {
    const modal = document.createElement('div');
    modal.id = 'deleteConfirmModal';
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-card" style="max-width: 420px;">
            <div class="modal-card-header">
                <h3>Confirm Delete</h3>
                <button type="button" class="modal-close-btn" onclick="document.getElementById('deleteConfirmModal').remove()">&times;</button>
            </div>
            <p style="margin: 20px 0; color: #555;">Are you sure you want to delete this address? This action cannot be undone.</p>
            <div class="row" style="margin-top: 20px;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('deleteConfirmModal').remove()">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    modal.classList.remove('hidden');

    document.getElementById('confirmDeleteBtn').addEventListener('click', async () => {
        await performDelete(addressId);
        modal.remove();
    });
}

async function performDelete(addressId) {
    try {
        const formData = new FormData();
        formData.append('csrf_token', __CSRF_TOKEN);
        formData.append('action', 'delete');
        formData.append('id', addressId);

        const response = await fetch(`${__API_BASE}/customer_addresses.php`, {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const text = await response.text();
        if (!text || text.trim() === '') {
            throw new Error('Empty response from server');
        }

        let data;
        try {
            data = JSON.parse(text);
        } catch (jsonError) {
            console.error('Invalid JSON response:', text);
            throw new Error('Invalid response from server');
        }

        if (!data.success) {
            throw new Error(data.message || 'Failed to delete address');
        }

        showSuccessModal('Address deleted successfully!');
        loadAddresses();

    } catch (error) {
        console.error('Failed to delete address:', error);
        showErrorModal('Failed to delete address: ' + error.message);
    }
}

function showSuccessModal(message) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-card" style="max-width: 380px; text-align: center;">
            <div style="font-size: 48px; margin-bottom: 16px;">✅</div>
            <h3 style="color: #27ae60; margin-bottom: 12px;">Success</h3>
            <p style="margin-bottom: 24px; color: #555;">${message}</p>
            <button type="button" class="btn btn-primary" onclick="this.closest('.modal').remove()">OK</button>
        </div>
    `;
    document.body.appendChild(modal);
    modal.classList.remove('hidden');

    setTimeout(() => modal.remove(), 2000);
}

function showErrorModal(message) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-card" style="max-width: 380px; text-align: center;">
            <div style="font-size: 48px; margin-bottom: 16px;">❌</div>
            <h3 style="color: #e74c3c; margin-bottom: 12px;">Error</h3>
            <p style="margin-bottom: 24px; color: #555;">${message}</p>
            <button type="button" class="btn btn-primary" onclick="this.closest('.modal').remove()">OK</button>
        </div>
    `;
    document.body.appendChild(modal);
    modal.classList.remove('hidden');
}

window.deleteAddress = deleteAddress;

async function setDefaultAddress(addressId) {
    try {
        const formData = new FormData();
        formData.append('csrf_token', __CSRF_TOKEN);
        formData.append('action', 'set_default');
        formData.append('id', addressId);

        const response = await fetch(`${__API_BASE}/customer_addresses.php`, {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const text = await response.text();
        if (!text || text.trim() === '') {
            throw new Error('Empty response from server');
        }

        let data;
        try {
            data = JSON.parse(text);
        } catch (jsonError) {
            console.error('Invalid JSON response:', text);
            throw new Error('Invalid response from server');
        }

        if (!data.success) {
            throw new Error(data.message || 'Failed to set default address');
        }

        showSuccessModal('Default address updated successfully!');
        loadAddresses();

    } catch (error) {
        console.error('Failed to set default address:', error);
        showErrorModal('Failed to set default address: ' + error.message);
    }
}

window.setDefaultAddress = setDefaultAddress;

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

console.log('Address management script loaded');
