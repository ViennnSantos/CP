// /RADS-TOOLING/assets/JS/checkout.js
// 🔥 COMPLETE ULTIMATE FIXED VERSION - All bugs squashed!

(function () {
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

  // ===== Modal Management =====
  function openModal(id) {
    const modal = $(id);
    if (!modal) return;
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
  }

  function closeModal(id) {
    const modal = $(id);
    if (!modal) return;
    modal.hidden = true;
    document.body.style.overflow = '';
  }

  function closeAllSteps() {
    $$('.rt-step').forEach(step => step.hidden = true);
  }

  function showStep(stepId) {
    closeAllSteps();
    const step = $(stepId);
    if (step) {
      step.hidden = false;
    }
  }

  // ===== ✅ IMPROVED: Better Modal Alert System =====
  function showModalAlert(title, message, type = 'error') {
    const existing = $('#customAlertModal');
    if (existing) existing.remove();

    const iconMap = {
      error: '❌',
      success: '✅',
      warning: '⚠️',
      info: 'ℹ️'
    };

    const colorMap = {
      error: '#e74c3c',
      success: '#27ae60',
      warning: '#f39c12',
      info: '#2f5b88'
    };

    const modal = document.createElement('div');
    modal.id = 'customAlertModal';
    modal.className = 'rt-modal';
    modal.innerHTML = `
      <div class="rt-modal__backdrop"></div>
      <div class="rt-card rt-step" style="max-width: 450px; display: block;">
        <div style="text-align: center; margin-bottom: 20px;">
          <span style="font-size: 48px;">${iconMap[type]}</span>
        </div>
        <h3 style="color: ${colorMap[type]}; margin-bottom: 16px; text-align: center;">
          ${title}
        </h3>
        <p style="color: #666; margin-bottom: 24px; line-height: 1.6; text-align: center;">
          ${message}
        </p>
        <div class="rt-actions" style="justify-content: center;">
          <button class="rt-btn main" style="min-width: 120px;" onclick="document.getElementById('customAlertModal').remove()">OK</button>
        </div>
      </div>
    `;
    modal.hidden = false;
    document.body.appendChild(modal);

    modal.querySelector('.rt-modal__backdrop').addEventListener('click', () => {
      modal.remove();
    });
  }
 // ===== SAVED ADDRESSES FUNCTIONALITY =====

  async function loadSavedAddresses() {
    const container = $('#savedAddressesContainer');
    const select = $('#savedAddressSelect');
    if (!select) return; // Not on delivery page

    try {
      const response = await fetch('/RADS-TOOLING/backend/api/customer_addresses.php?action=list', {
        credentials: 'include'
      });
      const data = await response.json();

      if (!data.success || !data.addresses || data.addresses.length === 0) {
        // No saved addresses - hide the container
        if (container) container.style.display = 'none';
        return;
      }

      // Show the container
      if (container) container.style.display = 'block';

      // Populate select options
      select.innerHTML = '<option value="">-- Select a saved address --</option>';

      data.addresses.forEach(addr => {
        const option = document.createElement('option');
        option.value = addr.id;
        option.textContent = addr.address_nickname
          ? `${addr.address_nickname} (${addr.street_block_lot.substring(0, 30)}...)`
          : `${addr.full_name} - ${addr.city_municipality}`;
        option.dataset.address = JSON.stringify(addr);

        if (addr.is_default == 1) {
          option.textContent += ' (Default)';
        }
        select.appendChild(option);
      });

      // Handle address selection
      select.addEventListener('change', (e) => {
        const selectedOption = e.target.options[e.target.selectedIndex];
        if (!selectedOption.value) return;

        const addr = JSON.parse(selectedOption.dataset.address);
        fillDeliveryForm(addr);
      });

    } catch (error) {
      console.error('Failed to load saved addresses:', error);
      if (container) container.style.display = 'none';
    }
  }

  function fillDeliveryForm(addr) {
    // Split full name into first and last name
    const nameParts = addr.full_name.trim().split(' ');
    const firstName = nameParts[0] || '';
    const lastName = nameParts.slice(1).join(' ') || '';

    // Fill personal information
    const firstNameInput = $('input[name="first_name"]');
    const lastNameInput = $('input[name="last_name"]');
    if (firstNameInput) firstNameInput.value = firstName;
    if (lastNameInput) lastNameInput.value = lastName;

    // Fill phone number
    const phoneLocalInput = $('#phoneLocal');
    if (phoneLocalInput && addr.mobile_number) {
      const localNumber = addr.mobile_number.replace('+63', '');
      phoneLocalInput.value = localNumber;
      phoneLocalInput.dispatchEvent(new Event('input'));
    }

    // Fill email
    const emailInput = $('input[name="email"]');
    if (emailInput && addr.email) emailInput.value = addr.email;

    // Fill address fields
    const provinceSelect = $('#province');
    const citySelect = $('#city');
    const barangaySelect = $('#barangaySelect');
    const streetInput = $('input[name="street"]');
    const postalInput = $('input[name="postal"]');

    if (provinceSelect) {
      provinceSelect.value = addr.province;
      provinceSelect.dispatchEvent(new Event('change'));

      // Wait for cities to load, then set city
      setTimeout(() => {
        if (citySelect) {
          citySelect.value = addr.city_municipality;
          citySelect.dispatchEvent(new Event('change'));

          // Wait for barangays to load, then set barangay
          setTimeout(() => {
            if (barangaySelect) {
              barangaySelect.value = addr.barangay;
            }
          }, 500);
        }
      }, 500);
    }

    if (streetInput) streetInput.value = addr.street_block_lot;
    if (postalInput && addr.postal_code) postalInput.value = addr.postal_code;
  }

  // Function to clear form and show new address form
  window.showNewAddressForm = function() {
    const select = $('#savedAddressSelect');
    if (select) select.value = '';

    // Clear all form fields
    const form = $('#deliveryForm');
    if (form) form.reset();
  };
// ===== AUTO-FILL PICKUP FORM FROM PROFILE =====
  async function autoFillPickupForm() {
    const pickupForm = $('#pickupForm');
    if (!pickupForm) return; // Not on pickup page

    try {
      const response = await fetch('/RADS-TOOLING/backend/api/customer_profile.php', {
        credentials: 'include'
      });

      const data = await response.json();

      if (!data.success || !data.customer) {
        console.log('No customer data available for auto-fill');
        return;
      }
      const customer = data.customer;

      // Split full name into first and last name
      if (customer.full_name) {
        const nameParts = customer.full_name.trim().split(' ');
        const firstName = nameParts[0] || '';
        const lastName = nameParts.slice(1).join(' ') || '';

        const firstNameInput = pickupForm.querySelector('input[name="first_name"]');
        const lastNameInput = pickupForm.querySelector('input[name="last_name"]');

        if (firstNameInput && !firstNameInput.value) firstNameInput.value = firstName;
        if (lastNameInput && !lastNameInput.value) lastNameInput.value = lastName;
      }

      // Fill phone number
      if (customer.phone) {
        const phoneLocalInput = pickupForm.querySelector('#phoneLocal');
        if (phoneLocalInput && !phoneLocalInput.value) {
          const localNumber = customer.phone.replace('+63', '');
          phoneLocalInput.value = localNumber;
          phoneLocalInput.dispatchEvent(new Event('input'));
        }
      }
      // Fill email
      if (customer.email) {
        const emailInput = pickupForm.querySelector('input[name="email"]');
        if (emailInput && !emailInput.value) emailInput.value = customer.email;
      }
      console.log('✅ Pickup form auto-filled from customer profile');
    } catch (error) {
      console.error('Failed to auto-fill pickup form:', error);
    }
  }
// ===== SAVED ADDRESSES FUNCTIONALITY =====
  async function loadSavedAddresses() {
    const container = $('#savedAddressesContainer');
    const select = $('#savedAddressSelect');

    if (!select) return; // Not on delivery page

    try {
      const response = await fetch('/RADS-TOOLING/backend/api/customer_addresses.php?action=list', {
        credentials: 'include'
      });

      const data = await response.json();

      if (!data.success || !data.addresses || data.addresses.length === 0) {
        // No saved addresses - hide the container
        if (container) container.style.display = 'none';
        return;
      }

      // Show the container
      if (container) container.style.display = 'block';

      // Populate select options
      select.innerHTML = '<option value="">-- Select a saved address --</option>';

      data.addresses.forEach(addr => {
        const option = document.createElement('option');
        option.value = addr.id;
        option.textContent = addr.address_nickname
          ? `${addr.address_nickname} (${addr.street_block_lot.substring(0, 30)}...)`
          : `${addr.full_name} - ${addr.city_municipality}`;
        option.dataset.address = JSON.stringify(addr);

        if (addr.is_default == 1) {
          option.textContent += ' (Default)';
        }

        select.appendChild(option);
      });

      // Handle address selection
      select.addEventListener('change', (e) => {
        const selectedOption = e.target.options[e.target.selectedIndex];
        if (!selectedOption.value) return;

        const addr = JSON.parse(selectedOption.dataset.address);
        fillDeliveryForm(addr);
      });

    } catch (error) {
      console.error('Failed to load saved addresses:', error);
      if (container) container.style.display = 'none';
    }
  }

  function fillDeliveryForm(addr) {
    // Split full name into first and last name
    const nameParts = addr.full_name.trim().split(' ');
    const firstName = nameParts[0] || '';
    const lastName = nameParts.slice(1).join(' ') || '';

    // Fill personal information
    const firstNameInput = $('input[name="first_name"]');
    const lastNameInput = $('input[name="last_name"]');
    if (firstNameInput) firstNameInput.value = firstName;
    if (lastNameInput) lastNameInput.value = lastName;

    // Fill phone number
    const phoneLocalInput = $('#phoneLocal');
    if (phoneLocalInput && addr.mobile_number) {
      const localNumber = addr.mobile_number.replace('+63', '');
      phoneLocalInput.value = localNumber;
      phoneLocalInput.dispatchEvent(new Event('input'));
    }

    // Fill email
    const emailInput = $('input[name="email"]');
    if (emailInput && addr.email) emailInput.value = addr.email;

    // Fill address fields
    const provinceSelect = $('#province');
    const citySelect = $('#city');
    const barangaySelect = $('#barangaySelect');
    const streetInput = $('input[name="street"]');
    const postalInput = $('input[name="postal"]');

    if (provinceSelect) {
      provinceSelect.value = addr.province;
      provinceSelect.dispatchEvent(new Event('change'));

      // Wait for cities to load, then set city
      setTimeout(() => {
        if (citySelect) {
          citySelect.value = addr.city_municipality;
          citySelect.dispatchEvent(new Event('change'));

          // Wait for barangays to load, then set barangay
          setTimeout(() => {
            if (barangaySelect) {
              barangaySelect.value = addr.barangay;
            }
          }, 500);
        }
      }, 500);
    }

    if (streetInput) streetInput.value = addr.street_block_lot;
    if (postalInput && addr.postal_code) postalInput.value = addr.postal_code;
  }

  // Function to clear form and show new address form
  window.showNewAddressForm = function() {
    const select = $('#savedAddressSelect');
    if (select) select.value = '';

    // Clear all form fields
    const form = $('#deliveryForm');
    if (form) form.reset();
  };

  // ===== AUTO-FILL PICKUP FORM FROM PROFILE =====
  async function autoFillPickupForm() {
    const pickupForm = $('#pickupForm');
    if (!pickupForm) return; // Not on pickup page

    try {
      const response = await fetch('/RADS-TOOLING/backend/api/customer_profile.php', {
        credentials: 'include'
      });

      const data = await response.json();

      if (!data.success || !data.customer) {
        console.log('No customer data available for auto-fill');
        return;
      }

      const customer = data.customer;

      // Split full name into first and last name
      if (customer.full_name) {
        const nameParts = customer.full_name.trim().split(' ');
        const firstName = nameParts[0] || '';
        const lastName = nameParts.slice(1).join(' ') || '';

        const firstNameInput = pickupForm.querySelector('input[name="first_name"]');
        const lastNameInput = pickupForm.querySelector('input[name="last_name"]');

        if (firstNameInput && !firstNameInput.value) firstNameInput.value = firstName;
        if (lastNameInput && !lastNameInput.value) lastNameInput.value = lastName;
      }

      // Fill phone number
      if (customer.phone) {
        const phoneLocalInput = pickupForm.querySelector('#phoneLocal');
        if (phoneLocalInput && !phoneLocalInput.value) {
          const localNumber = customer.phone.replace('+63', '');
          phoneLocalInput.value = localNumber;
          phoneLocalInput.dispatchEvent(new Event('input'));
        }
      }

      // Fill email
      if (customer.email) {
        const emailInput = pickupForm.querySelector('input[name="email"]');
        if (emailInput && !emailInput.value) emailInput.value = customer.email;
      }

      console.log('✅ Pickup form auto-filled from customer profile');
    } catch (error) {
      console.error('Failed to auto-fill pickup form:', error);
    }
  }
  // Close modal handlers
  document.addEventListener('click', (e) => {
    const closeBtn = e.target.closest('[data-close]');
    if (closeBtn) {
      const targetModal = closeBtn.getAttribute('data-close');
      if (targetModal) {
        closeModal(targetModal);
      } else {
        closeModal('#rtModal');
        closeAllSteps();
      }
    }

    const backBtn = e.target.closest('[data-back]');
    if (backBtn) {
      const targetStep = backBtn.getAttribute('data-back');
      showStep(targetStep);
    }
  });

  // ===== Phone Number Handler =====
  function wirePhone() {
    const local = $('#phoneLocal');
    const full = $('#phoneFull');
    if (!local || !full) return;

    const sync = () => {
      const digits = (local.value || '').replace(/\D+/g, '').slice(0, 10);
      local.value = digits;
      full.value = digits.length ? ('+63' + digits) : '';
    };

    local.addEventListener('input', sync);
    sync();
  }

  // ===== ✅ COMPLETE FIXED: PSGC Address Loader with NCR =====
  async function loadPSGC() {
    const provSel = $('#province');
    const citySel = $('#city');
    const brgySel = $('#barangaySelect');
    const provInput = $('#provinceInput');
    const cityInput = $('#cityInput');
    const brgyInput = $('#barangayInput');
    const pVal = $('#provinceVal');
    const cVal = $('#cityVal');
    const bVal = $('#barangayVal');

    if (!provSel || !citySel || !brgySel) return;

    // ✅ FIXED: NCR + CALABARZON provinces
    const ALLOWED_PROVINCES = [
      // NCR variations
      'National Capital Region',
      'Metro Manila',
      'NCR',
      // Calabarzon provinces
      'Cavite',
      'Laguna',
      'Batangas',
      'Rizal',
      'Quezon'
    ];

    // ✅ NCR Cities (all 16 cities + 1 municipality)
    const NCR_CITIES = [
      'Caloocan',
      'Las Piñas',
      'Makati',
      'Malabon',
      'Mandaluyong',
      'Manila',
      'Marikina',
      'Muntinlupa',
      'Navotas',
      'Parañaque',
      'Pasay',
      'Pasig',
      'Pateros',
      'Quezon City',
      'San Juan',
      'Taguig',
      'Valenzuela'
    ];

    function showText(field, on) {
      if (field === 'province' && provInput) {
        provSel.disabled = on;
        provInput.hidden = !on;
        provInput.disabled = !on;
        provInput.required = !!on;
      }
      if (field === 'city' && cityInput) {
        citySel.disabled = on;
        cityInput.hidden = !on;
        cityInput.disabled = !on;
        cityInput.required = !!on;
      }
      if (field === 'barangay' && brgyInput) {
        brgySel.disabled = on;
        brgyInput.hidden = !on;
        brgyInput.disabled = !on;
        brgyInput.required = !!on;
      }
    }

    provInput?.addEventListener('input', () => { if (pVal) pVal.value = (provInput.value || '').trim(); });
    cityInput?.addEventListener('input', () => { if (cVal) cVal.value = (cityInput.value || '').trim(); });
    brgyInput?.addEventListener('input', () => { if (bVal) bVal.value = (brgyInput.value || '').trim(); });

    async function getJSON(url) {
      try {
        const r = await fetch(url, { cache: 'no-store' });
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return await r.json();
      } catch (err) {
        console.warn('PSGC fetch failed:', url, err);
        return null;
      }
    }

    // ✅ FETCH PROVINCES (with NCR)
    async function fetchProvinces() {
      // Try local API first
      let j = await getJSON('/RADS-TOOLING/backend/api/psgc.php?action=provinces');
      if (Array.isArray(j) && j.length) {
        const filtered = j.map(x => x.name || x).filter(name => 
          ALLOWED_PROVINCES.some(allowed => 
            name.toLowerCase().includes(allowed.toLowerCase()) || 
            allowed.toLowerCase().includes(name.toLowerCase())
          )
        );
        if (filtered.length) return filtered;
      }

      // Try cloud API
      j = await getJSON('https://psgc.cloud/api/provinces');
      if (Array.isArray(j) && j.length) {
        const filtered = j.map(x => x.name).filter(name => 
          ALLOWED_PROVINCES.some(allowed => 
            name.toLowerCase().includes(allowed.toLowerCase()) || 
            allowed.toLowerCase().includes(name.toLowerCase())
          )
        );
        if (filtered.length) return filtered;
      }

      // ✅ FALLBACK: Return hardcoded list with NCR
      return [
        'Metro Manila',
        'Cavite',
        'Laguna',
        'Batangas',
        'Rizal',
        'Quezon'
      ];
    }

    // ✅ FETCH CITIES (with NCR special handling)
    async function fetchCities(provinceName) {
      // ✅ SPECIAL HANDLING: NCR cities
      const isNCR = provinceName && (
        provinceName.toLowerCase().includes('ncr') ||
        provinceName.toLowerCase().includes('metro manila') ||
        provinceName.toLowerCase().includes('national capital')
      );

      if (isNCR) {
        console.log('✅ NCR detected, returning NCR cities');
        return NCR_CITIES;
      }

      // Try local API
      let j = await getJSON('/RADS-TOOLING/backend/api/psgc.php?action=cities&province=' + encodeURIComponent(provinceName));
      if (Array.isArray(j) && j.length) return j.map(x => x.name || x);

      // Try cloud API
      const provList = await getJSON('https://psgc.cloud/api/provinces');
      if (Array.isArray(provList) && provList.length) {
        const found = provList.find(p => (p.name || '').toLowerCase() === (provinceName || '').toLowerCase());
        if (found && found.code) {
          const data = await getJSON(`https://psgc.cloud/api/provinces/${found.code}/cities-municipalities`);
          if (Array.isArray(data) && data.length) return data.map(x => x.name).filter(Boolean);
        }
      }

      return [];
    }

    // ✅ FETCH BARANGAYS
    async function fetchBarangays(cityName, provinceName) {
      let j = await getJSON('/RADS-TOOLING/backend/api/psgc.php?action=barangays&city=' + encodeURIComponent(cityName));
      if (Array.isArray(j) && j.length) return j.map(x => x.name || x);

      const norm = s => (s || '').toLowerCase().trim();

      let cityData = await getJSON('https://psgc.cloud/api/cities');
      if (Array.isArray(cityData)) {
        let hit = cityData.find(x => norm(x.name) === norm(cityName));
        if (hit && hit.code) {
          j = await getJSON(`https://psgc.cloud/api/cities/${hit.code}/barangays`);
          if (Array.isArray(j) && j.length) return j.map(x => x.name).filter(Boolean);
        }
      }

      let munData = await getJSON('https://psgc.cloud/api/municipalities');
      if (Array.isArray(munData)) {
        let hit = munData.find(x => norm(x.name) === norm(cityName));
        if (hit && hit.code) {
          j = await getJSON(`https://psgc.cloud/api/municipalities/${hit.code}/barangays`);
          if (Array.isArray(j) && j.length) return j.map(x => x.name).filter(Boolean);
        }
      }

      return [];
    }

    // ✅ BOOTSTRAP PROVINCES
    console.log('🔄 Loading PSGC data...');
    const provinces = await fetchProvinces();
    
    if (!provinces.length) {
      console.warn('⚠️ No provinces loaded, showing text inputs');
      showText('province', true);
      showText('city', true);
      showText('barangay', true);
      return;
    }

    console.log('✅ Loaded provinces:', provinces);

    provSel.innerHTML =
      '<option value="">Select province</option>' +
      provinces.sort((a, b) => a.localeCompare(b)).map(n => `<option value="${n}">${n}</option>`).join('');
    provSel.disabled = false;

    // ✅ PROVINCE CHANGE HANDLER
    provSel.addEventListener('change', async () => {
      const pv = provSel.value;
      if (pVal) pVal.value = pv;

      citySel.innerHTML = '<option value="">Select city/municipality</option>';
      brgySel.innerHTML = '<option value="">Select barangay</option>';
      citySel.disabled = !pv;
      brgySel.disabled = true;

      if (cityInput) { cityInput.hidden = true; cityInput.disabled = true; cityInput.required = false; }
      if (brgyInput) { brgyInput.hidden = true; brgyInput.disabled = true; brgyInput.required = false; }

      if (!pv) return;

      console.log('🔄 Fetching cities for:', pv);
      const cities = await fetchCities(pv);

      if (!cities.length) {
        console.warn('⚠️ No cities found, showing text input');
        citySel.disabled = true;
        if (cityInput) { cityInput.hidden = false; cityInput.disabled = false; cityInput.required = true; }

        brgySel.disabled = true;
        if (brgyInput) { brgyInput.hidden = false; brgyInput.disabled = false; brgyInput.required = true; }
        return;
      }

      console.log('✅ Loaded cities:', cities.length, 'cities');
      citySel.innerHTML =
        '<option value="">Select city/municipality</option>' +
        cities.sort((a, b) => a.localeCompare(b)).map(n => `<option value="${n}">${n}</option>`).join('');
      citySel.disabled = false;
    });

    // ✅ CITY CHANGE HANDLER
    citySel.addEventListener('change', async () => {
      const cv = citySel.value;
      if (cVal) cVal.value = cv;

      brgySel.innerHTML = '<option value="">Select barangay</option>';
      brgySel.disabled = !cv;

      if (brgyInput) { brgyInput.hidden = true; brgyInput.disabled = true; brgyInput.required = false; }
      if (!cv) return;

      const pv = provSel ? provSel.value : '';
      console.log('🔄 Fetching barangays for:', cv);
      const brgys = await fetchBarangays(cv, pv);

      if (!brgys.length) {
        console.warn('⚠️ No barangays found, showing text input');
        brgySel.disabled = true;
        if (brgyInput) { brgyInput.hidden = false; brgyInput.disabled = false; brgyInput.required = true; }
        return;
      }

      console.log('✅ Loaded barangays:', brgys.length, 'barangays');
      brgySel.innerHTML =
        '<option value="">Select barangay</option>' +
        brgys.sort((a, b) => a.localeCompare(b)).map(n => `<option value="${n}">${n}</option>`).join('');
      brgySel.disabled = false;
    });

    // ✅ BARANGAY CHANGE HANDLER
    brgySel.addEventListener('change', () => {
      const bv = brgySel.value;
      if (bVal) bVal.value = bv;
    });
  }

  // ===== Form Validation =====
  function wireContinue() {
    const btn = $('#btnContinue');
    if (!btn) return;

    btn.addEventListener('click', () => {
      const form = $('#deliveryForm') || $('#pickupForm');
      if (!form) return;

      const invalids = Array.from(form.querySelectorAll('input:required, select:required')).filter(el => !el.value);
      invalids.forEach(el => el.style.borderColor = '#ef4444');

      if (invalids.length) {
        openModal('#invalidModal');
        return;
      }

      form.submit();
    });
  }

  function wireClear() {
    const btn = $('#btnClear');
    if (!btn) return;
    btn.addEventListener('click', () => {
      const form = $('#deliveryForm') || $('#pickupForm');
      form?.reset();
    });
  }

  // ===== ✅ COMPLETE FIXED: Payment Flow =====
  let ORDER_ID = null;
  let ORDER_CODE = null;
  let AMOUNT_DUE = 0;
  let PAYMENT_METHOD = null;

  function wirePayment() {
    const btnBuy = $('#inlineBuyBtn');
    if (!btnBuy) return;

    // ✅ FIXED: Payment method selection with visual feedback
    $$('[data-pay]').forEach(btn => {
      btn.addEventListener('click', () => {
        $$('[data-pay]').forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        $('#paymentMethod').value = btn.getAttribute('data-pay');
        $('#btnChooseDeposit').disabled = false;
        console.log('✅ Payment method selected:', btn.getAttribute('data-pay'));
      });
    });

    // ✅ FIXED: Deposit selection with visual feedback
    $$('[data-dep]').forEach(btn => {
      btn.addEventListener('click', () => {
        $$('[data-dep]').forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        $('#depositRate').value = btn.getAttribute('data-dep');
        $('#btnPayNow').disabled = false;
        console.log('✅ Deposit rate selected:', btn.getAttribute('data-dep') + '%');
      });
    });

    btnBuy.addEventListener('click', () => {
      openModal('#rtModal');
      showStep('#methodModal');
      console.log('💳 Payment wizard opened');
    });

    $('#btnChooseDeposit')?.addEventListener('click', () => {
      showStep('#depositModal');
      console.log('💰 Deposit selection opened');
    });

    // ✅ FIXED: Order creation with proper payload
    $('#btnPayNow')?.addEventListener('click', async () => {
      const method = $('#paymentMethod')?.value;
      const dep = parseInt($('#depositRate')?.value || '0', 10);

      if (!method || !dep) {
        showModalAlert('Selection Required', 'Please select both payment method and deposit amount.', 'warning');
        return;
      }
      
      PAYMENT_METHOD = method;

      const orderData = window.RT_ORDER || {};

      // ✅ STEP 1: Create order
      if (!ORDER_ID) {
        try {
          const url = `${location.origin}/RADS-TOOLING/backend/api/order_create.php`;
          
          // ✅ FIXED: Proper payload structure matching backend expectations
          const payload = {
            pid: orderData.pid || 0,
            qty: orderData.qty || 1,
            subtotal: orderData.subtotal || 0,
            vat: orderData.vat || 0,
            total: orderData.total || 0,
            mode: orderData.mode || 'pickup',
            info: orderData.info || {}
          };

          console.log('📤 Sending order_create payload:', JSON.stringify(payload, null, 2));

          const r1 = await fetch(url, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
          });

          const raw1 = await r1.text();
          console.log('📥 Response status:', r1.status, '| Body:', raw1);

          if (!r1.ok) {
            let errorMsg = `Server returned status ${r1.status}.`;
            try {
              const errData = JSON.parse(raw1);
              if (errData.message) errorMsg = errData.message;
              if (errData.errors) errorMsg += '\n\nDetails:\n• ' + errData.errors.join('\n• ');
            } catch (e) {
              errorMsg += '\n\nPlease check that all required information is provided.';
            }
            showModalAlert('Order Creation Error', errorMsg, 'error');
            return;
          }

          let result;
          try { 
            result = JSON.parse(raw1); 
          } catch {
            showModalAlert('Invalid Response', 'Server returned invalid data. Please contact support.', 'error');
            return;
          }

          if (!result?.success) {
            showModalAlert('Order Failed', result?.message || 'Could not create order.', 'error');
            return;
          }

          ORDER_ID = result.order_id || null;
          ORDER_CODE = result.order_code || null;

          if (!ORDER_ID) {
            showModalAlert('Order Error', 'Order created but no ID returned. Contact support.', 'error');
            console.error('❌ Invalid order_create result:', result);
            return;
          }

          console.log('✅ Order created:', ORDER_ID, '(' + ORDER_CODE + ')');

        } catch (err) {
          console.error('❌ Order create fetch error:', err);
          showModalAlert('Network Error', 'Could not connect to server. Check your connection.', 'error');
          return;
        }
      }

      // ✅ STEP 2: Save payment decision
      try {
        const url = `${location.origin}/RADS-TOOLING/backend/api/payment_decision.php`;
        const r2 = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          credentials: 'same-origin',
          body: JSON.stringify({ order_id: ORDER_ID, method: method, deposit_rate: dep })
        });
        
        const raw2 = await r2.text();
        console.log('📥 Payment decision response:', r2.status, raw2);
        
        if (!r2.ok) {
          showModalAlert('Payment Setup Error', `Could not setup payment terms (Status ${r2.status}).`, 'error');
          return;
        }
        
        const result2 = JSON.parse(raw2);

        if (!result2 || !result2.success) {
          showModalAlert('Payment Failed', result2?.message || 'Could not set payment terms.', 'error');
          return;
        }
        
        AMOUNT_DUE = result2.data.amount_due || 0;
        console.log('✅ Payment decision saved. Amount due:', AMOUNT_DUE);
        
      } catch (err) {
        console.error('❌ Payment decision error:', err);
        showModalAlert('Network Error', 'Could not set payment terms.', 'error');
        return;
      }

      // ✅ STEP 3: Fetch QR code
      try {
        const url = `${location.origin}/RADS-TOOLING/backend/api/content_mgmt.php`;
        const r3 = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          credentials: 'same-origin',
          body: 'action=get_payment_qr'
        });
        
        const raw3 = await r3.text();
        console.log('📥 QR fetch response:', r3.status, raw3);
        
        let result3;
        try { result3 = JSON.parse(raw3); } catch { result3 = null; }

        const qrBox = $('#qrBox');
        if (qrBox) {
          if (result3 && result3.success && result3.data) {
            const qrData = method === 'gcash' ? result3.data.gcash : result3.data.bpi;
            
            if (qrData && qrData.image_path) {
              const imageUrl = `/RADS-TOOLING/${qrData.image_path}`;
              console.log(`✅ Displaying ${method.toUpperCase()} QR:`, imageUrl);
              
              qrBox.innerHTML = `
                <img 
                  src="${imageUrl}?v=${Date.now()}" 
                  alt="${method.toUpperCase()} QR" 
                  style="width:100%;height:100%;object-fit:contain;cursor:pointer;padding:8px;" 
                  onclick="window.openQrZoom('${imageUrl}')"
                  onerror="this.parentElement.innerHTML='<span style=\\'color:#e74c3c;\\'>❌ Failed to load QR</span>'"
                >`;
            } else {
              console.warn(`⚠️ No ${method.toUpperCase()} QR configured`);
              qrBox.innerHTML = '<span style="color:#999">No QR configured</span>';
            }
          } else {
            console.error('❌ Invalid QR API response:', result3);
            qrBox.innerHTML = '<span style="color:#999">Failed to load QR</span>';
          }
        }
      } catch (err) {
        console.error('❌ QR fetch error:', err);
        const qrBox = $('#qrBox');
        if (qrBox) qrBox.innerHTML = '<span style="color:#999">Failed to load QR</span>';
      }

      const amtLabel = $('#amountDueLabel');
      if (amtLabel) amtLabel.textContent = '₱' + AMOUNT_DUE.toLocaleString('en-PH', { minimumFractionDigits: 2 });

      showStep('#qrModal');
    });

    $('#btnIpaid')?.addEventListener('click', () => {
      showStep('#verifyModal');
      console.log('📝 Verification form opened');
    });

    // ✅ FIXED: Verify payment with validation
    $('#btnVerify')?.addEventListener('click', async () => {
      const name = $('#vpName');
      const num = $('#vpNum');
      const ref = $('#vpRef');
      const amt = $('#vpAmt');
      const shot = $('#vpShot');

      const reqs = [name, num, ref, amt, shot];
      let ok = true;
      reqs.forEach(el => {
        const good = !!(el && el.value);
        el.style.borderColor = good ? '' : '#ef4444';
        if (!good) ok = false;
      });

      if (!ok || !ORDER_ID) {
        showModalAlert('Incomplete Form', 'Please fill in all required fields.', 'warning');
        return;
      }

      const accountNum = num.value.trim();
      const refNum = ref.value.trim();
      
     // Validate account number (digits only)
      if (!/^\d+$/.test(accountNum)) {
        showModalAlert('Invalid Account Number', 'Account number must contain only digits.', 'error');
        num.style.borderColor = '#ef4444';
        return;
      }

      // Validate GCash account number (max 11 digits)
       if (PAYMENT_METHOD === 'gcash' && accountNum.length > 11) {
        showModalAlert('Invalid GCash Account', 'GCash account number must be maximum 11 digits.', 'error');
        num.style.borderColor = '#ef4444';
        return;
      }

      // Validate reference number (digits only, flexible length)
      if (!/^\d+$/.test(refNum)) {
        showModalAlert('Invalid Reference Number', 'Reference number must contain only digits.', 'error');
        ref.style.borderColor = '#ef4444';
        return;
      }

      // Validate reference number length (reasonable limit)
      if (refNum.length > 30) {
        showModalAlert('Invalid Reference Number', 'Reference number too long (max 30 digits).', 'error');
        ref.style.borderColor = '#ef4444';
        return;
      }

      // Validate amount paid (must EXACTLY match the expected amount)
      const amountPaid = parseFloat(amt.value);
      const expectedAmount = AMOUNT_DUE;

      if (amountPaid !== expectedAmount) {
        showModalAlert(
          'Amount Mismatch',
          `Amount paid must equal order total.\n\nExpected: ₱${expectedAmount.toLocaleString('en-PH', {minimumFractionDigits: 2})}\nYou entered: ₱${amountPaid.toLocaleString('en-PH', {minimumFractionDigits: 2})}`,
          'error'
        );
        amt.style.borderColor = '#ef4444';
        return;
      }

      // Show T&C modal instead of directly submitting
      showStep('#termsModal');

      // Store form data for later submission
      window.VERIFICATION_DATA = {
        account_name: name.value,
        account_number: num.value,
        reference_number: ref.value,
        amount_paid: amt.value,
        screenshot: shot.files[0] || null
      };

      console.log('✅ Verification data validated, showing T&C modal');
    });

    // Handle T&C checkbox
    const termsCheckbox = $('#acceptTermsCheckbox');
    const btnAcceptTerms = $('#btnAcceptTerms');

    if (termsCheckbox && btnAcceptTerms) {
      termsCheckbox.addEventListener('change', (e) => {
        btnAcceptTerms.disabled = !e.target.checked;
        if (e.target.checked) {
          btnAcceptTerms.style.opacity = '1';
          btnAcceptTerms.style.cursor = 'pointer';
        } else {
          btnAcceptTerms.style.opacity = '0.5';
          btnAcceptTerms.style.cursor = 'not-allowed';
        }
      });
    }

    // Handle final payment submission after T&C acceptance
    btnAcceptTerms?.addEventListener('click', async () => {
      const verData = window.VERIFICATION_DATA;
      if (!verData) {
        showModalAlert('Error', 'Verification data not found. Please try again.', 'error');
        return;
      }

      const form = new FormData();
      form.append('order_id', ORDER_ID);
      form.append('order_code', ORDER_CODE || '');
      form.append('amount_due', AMOUNT_DUE || 0);
      form.append('account_name', verData.account_name);
      form.append('account_number', verData.account_number);
      form.append('reference_number', verData.reference_number);
      form.append('amount_paid', verData.amount_paid);
      form.append('screenshot', verData.screenshot);
      form.append('terms_accepted', '1'); // Flag that T&C was accepted

      try {
        console.log('📤 Submitting payment verification with T&C acceptance...');

        // Disable button to prevent double submission
        btnAcceptTerms.disabled = true;
        btnAcceptTerms.textContent = 'Submitting...';

        const r = await fetch('/RADS-TOOLING/backend/api/payment_submit.php', {
          method: 'POST',
          body: form,
          credentials: 'same-origin'
        });
        
        const result = await r.json();
        console.log('📥 Verification response:', result);

        if (!result || !result.success) {
          showModalAlert('Verification Failed', result?.message || 'Payment verification failed.', 'error');
          // Re-enable button on error
          btnAcceptTerms.disabled = false;
          btnAcceptTerms.textContent = 'Accept & Submit Payment';
          return;
        }

        console.log('✅ Payment verification submitted successfully!');
        showModalAlert('Payment Submitted!', 'Your payment is under verification. Check your orders page for approval status.', 'success');

        setTimeout(() => {
          showStep('#finalNotice');
          // Reset T&C checkbox for next time
          if (termsCheckbox) termsCheckbox.checked = false;
          btnAcceptTerms.disabled = true;
          btnAcceptTerms.textContent = 'Accept & Submit Payment';
        }, 2000);

      } catch (err) {
        console.error('❌ Payment submit error:', err);
        showModalAlert('Network Error', 'Could not submit payment verification.', 'error');
        // Re-enable button on error
        btnAcceptTerms.disabled = false;
        btnAcceptTerms.textContent = 'Accept & Submit Payment';
      }
    });

    $('#btnGoOrders')?.addEventListener('click', () => {
      location.href = '/RADS-TOOLING/customer/orders.php';
    });
  }

  // ===== Numeric Input Setup =====
  function setupNumericInputs() {
    const accountNum = $('#vpNum');
    if (accountNum) {
      accountNum.addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/\D/g, '');
      });
      accountNum.addEventListener('keypress', (e) => {
        if (!/\d/.test(e.key) && e.key !== 'Backspace') {
          e.preventDefault();
        }
      });
    }

    const refNum = $('#vpRef');
    if (refNum) {
      refNum.addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/\D/g, '');
      });
      refNum.addEventListener('keypress', (e) => {
        if (!/\d/.test(e.key) && e.key !== 'Backspace') {
          e.preventDefault();
        }
      });
    }
  }

  // ===== QR Zoom Functions =====
  window.openQrZoom = function(qrUrl) {
    const modal = $('#qrZoomModal');
    const img = $('#zoomQrImage');
    
    if (!modal || !img) {
      console.warn('⚠️ QR Zoom elements not found');
      return;
    }
    
    img.src = qrUrl;
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    const handleBackdropClick = (e) => {
      if (e.target === modal) {
        window.closeQrZoom();
      }
    };
    modal.addEventListener('click', handleBackdropClick);
    
    console.log('🔍 QR Zoom opened:', qrUrl);
  };

  window.closeQrZoom = function() {
    const modal = $('#qrZoomModal');
    if (!modal) return;
    
    modal.classList.remove('show');
    document.body.style.overflow = '';
    console.log('✅ QR Zoom closed');
  };

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const zoomModal = $('#qrZoomModal');
      if (zoomModal && zoomModal.classList.contains('show')) {
        window.closeQrZoom();
      }
    }
  });

  // ===== Initialize Everything =====
  document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Checkout.js loading...');
    
    wirePhone();
    loadPSGC();
    loadSavedAddresses(); // Load saved addresses for delivery auto-fill
    autoFillPickupForm(); // Auto-fill pickup form from customer profile
    wireContinue();
    wireClear();
    wirePayment();
    setupNumericInputs();

    console.log('✅ Checkout.js COMPLETE FIXED VERSION loaded!');
    console.log('✅ Features: NCR support, delivery/pickup auto-fill, active states, better errors, proper payload');

  });
})();