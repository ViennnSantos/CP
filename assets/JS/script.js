// Enhanced Admin Dashboard JavaScript with Complete Role-Based Access Control
console.log('Enhanced Admin Dashboard Script Loading...');

// Global state
let currentUserRole = null;
let currentUserData = null;

document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM Content Loaded');

    initializeSession()
        .then(() => {
            initializeNavigation();
            initializeProfileDropdown();
            initializeModals();
            initializeProfileActions();
            initializeActionBindings();
            initializeDashboard();
            initializeAccountManagement();
            initializeOrderManagement();
            initializeCustomerManagement();
            initializeFeedbackManagement();
            setupFloatingPwToggle();
            setupLogout();
        })
        .catch(error => {
            console.error('Initialization failed:', error);
            window.location.href = '/public/index.php';
        });
});

// ============================================================================
// SESSION MANAGEMENT & ROLE-BASED ACCESS
// ============================================================================

async function initializeSession() {
    try {
        const response = await fetch('/backend/api/auth.php?action=check_session', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        const result = await response.json();

        if (!result.success || !result.data.user) {
            throw new Error('No valid session');
        }

        currentUserData = result.data.user;
        currentUserRole = result.data.user.role;

        updateUserInterface();
        configureSidebarForRole();

        console.log('Session initialized for:', currentUserRole);

    } catch (error) {
        console.error('Session check failed:', error);
        throw error;
    }
}

function updateUserInterface() {
    if (!currentUserData) return;

    const adminNameEl = document.querySelector('.admin-name');
    if (adminNameEl) {
        adminNameEl.textContent = `Welcome, ${currentUserData.name}`;
    }

    const profileAvatar = document.getElementById('profileIcon');
    if (profileAvatar && currentUserData.avatar) {
        profileAvatar.src = '/' + currentUserData.avatar;
    }
}

function configureSidebarForRole() {
    const allNavItems = document.querySelectorAll('.nav-item');

    const rolePermissions = {
        'Owner': ['dashboard', 'account', 'customer', 'products', 'orders', 'reports', 'content', 'feedback', 'chat', 'payment'],
        'Admin': ['dashboard', 'account', 'customer', 'products', 'orders', 'reports', 'content', 'feedback', 'chat', 'payment'],
        'Secretary': ['dashboard', 'orders', 'reports', 'feedback', 'chat']
    };

    const allowedSections = rolePermissions[currentUserRole] || [];

    allNavItems.forEach(item => {
        const section = item.getAttribute('data-section');
        if (!allowedSections.includes(section)) {
            item.style.display = 'none';
        }
    });

    const currentActive = document.querySelector('.nav-item.active');
    if (currentActive && !allowedSections.includes(currentActive.getAttribute('data-section'))) {
        switchToSection('dashboard');
    }
}

function switchToSection(sectionName) {
    document.querySelectorAll('.main-section').forEach(s => {
        s.style.display = 'none';
        s.classList.remove('show');
    });

    const targetSection = document.querySelector(`.main-section[data-section="${sectionName}"]`);
    if (targetSection) {
        targetSection.style.display = 'block';
        targetSection.classList.add('show');
    }

    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    const targetNav = document.querySelector(`.nav-item[data-section="${sectionName}"]`);
    if (targetNav) {
        targetNav.classList.add('active');
    }
}

// ============================================================================
// PROFILE MANAGEMENT
// ============================================================================

function initializeProfileActions() {
    document.getElementById('btnEditProfile')?.addEventListener('click', async () => {
        await loadCurrentProfile();
        openModal('editProfileModal');
    });

    document.getElementById('btnChangePassword')?.addEventListener('click', () => {
        openModal('changePasswordModal');
    });

    const editProfileForm = document.getElementById('editProfileForm');
    if (editProfileForm) {
        editProfileForm.addEventListener('submit', handleEditProfile);
    }

    const changePasswordForm = document.getElementById('changePasswordForm');
    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', handleChangePassword);
    }

    const avatarInput = document.getElementById('editProfilePic');
    if (avatarInput) {
        avatarInput.addEventListener('change', handleAvatarUpload);
    }
}

async function loadCurrentProfile() {
    try {
        const response = await fetch('/backend/api/admin_profile.php?action=get_profile', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        const result = await response.json();

        if (result.success && result.data) {
            const profile = result.data;
            document.getElementById('ep-fullname').value = profile.full_name || '';
            document.getElementById('ep-username').value = profile.username || '';

            const avatarImg = document.getElementById('editProfileAvatar');
            if (avatarImg) {
                avatarImg.src = profile.profile_image ?
                    '/' + profile.profile_image :
                    '/assets/images/profile.png';
            }
        }
    } catch (error) {
        console.error('Failed to load profile:', error);
        showNotification('Failed to load profile data', 'error');
    }
}

async function handleEditProfile(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const data = {
        full_name: formData.get('full_name'),
        username: formData.get('username')
    };

    try {
        const response = await fetch('/backend/api/admin_profile.php?action=update_profile', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Profile updated successfully!', 'success');
            closeModal('editProfileModal');

            currentUserData.name = data.full_name;
            currentUserData.username = data.username;

            updateUserInterface();

            showLoadingOverlay();
            setTimeout(async () => {
                try {
                    await initializeSession();
                    hideLoadingOverlay();
                } catch (error) {
                    hideLoadingOverlay();
                    console.error('Session refresh failed:', error);
                }
            }, 1000);
        } else {
            showNotification(result.message || 'Failed to update profile', 'error');
        }
    } catch (error) {
        console.error('Profile update error:', error);
        showNotification('Failed to update profile', 'error');
    }
}

async function handleChangePassword(e) {
    e.preventDefault();

    const currentPassword = document.getElementById('cp-old').value;
    const newPassword = document.getElementById('cp-new').value;
    const confirmPassword = document.getElementById('cp-confirm').value;

    if (newPassword !== confirmPassword) {
        showNotification('New passwords do not match', 'error');
        return;
    }

    const data = {
        current_password: currentPassword,
        new_password: newPassword,
        confirm_password: confirmPassword
    };

    try {
        const response = await fetch('/backend/api/admin_profile.php?action=change_password', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Password changed successfully!', 'success');
            closeModal('changePasswordModal');
            e.target.reset();
        } else {
            showNotification(result.message || 'Failed to change password', 'error');
        }
    } catch (error) {
        console.error('Password change error:', error);
        showNotification('Failed to change password', 'error');
    }
}

async function handleAvatarUpload(e) {
    const file = e.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('avatar', file);

    try {
        const response = await fetch('/backend/api/admin_profile.php?action=upload_avatar', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Avatar updated successfully!', 'success');

            const avatarImg = document.getElementById('editProfileAvatar');
            const topbarAvatar = document.getElementById('profileIcon');

            if (avatarImg && result.data.avatar_url) {
                avatarImg.src = result.data.avatar_url;
            }
            if (topbarAvatar && result.data.avatar_url) {
                topbarAvatar.src = result.data.avatar_url;
            }
        } else {
            showNotification(result.message || 'Failed to upload avatar', 'error');
        }
    } catch (error) {
        console.error('Avatar upload error:', error);
        showNotification('Failed to upload avatar', 'error');
    }
}

function showLoadingOverlay() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.style.display = 'flex';
    }
}

function hideLoadingOverlay() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

// ============================================================================
// ACCOUNT MANAGEMENT
// ============================================================================

function initializeAccountManagement() {
    if (!['Owner', 'Admin'].includes(currentUserRole)) {
        return;
    }

    const accountNavItem = document.querySelector('[data-section="account"]');
    if (accountNavItem) {
        accountNavItem.addEventListener('click', function () {
            setTimeout(() => loadUsers(), 100);
        });
    }

    const addUserForm = document.getElementById('addUserForm');
    if (addUserForm) {
        addUserForm.addEventListener('submit', handleAddUser);
    }

    const currentSection = document.querySelector('.nav-item.active');
    if (currentSection && currentSection.dataset.section === 'account') {
        loadUsers();
    }
}

async function loadUsers() {
    try {
        const response = await fetch('/backend/api/admin_accounts.php?action=list', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        const result = await response.json();

        if (result.success) {
            displayUsers(result.data.users);
            updateAddUserButton(result.data.permissions);
        } else {
            showNotification(result.message || 'Failed to load users', 'error');
        }
    } catch (error) {
        console.error('Failed to load users:', error);
        showNotification('Failed to load users', 'error');
    }
}

function displayUsers(users) {
    const tbody = document.getElementById('userTableBody');
    if (!tbody) return;

    if (!users || users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#666;">No users found</td></tr>';
        return;
    }

    tbody.innerHTML = users.map(user => `
    <tr data-user-id="${user.id}">
        <td>${user.id}</td>
        <td>${escapeHtml(user.username)}</td>
        <td>${escapeHtml(user.full_name)}</td>
        <td><span class="badge badge-${user.role.toLowerCase()}">${user.role}</span></td>
        <td>
            <span class="badge badge-${user.status === 'active' ? 'active' : 'inactive'}">
                ${user.status === 'active' ? 'Active' : 'Inactive'}
            </span>
        </td>
        <td>
            ${(currentUserRole === 'Owner') ? `
                <button class="btn-action btn-reset-password" data-user-id="${user.id}" title="Reset Password">
                    <span class="material-symbols-rounded">key</span>
                </button>
            ` : ''}
            ${user.can_toggle ? `
                <button class="btn-action btn-toggle" data-user-id="${user.id}" 
                        data-current-status="${user.status}" 
                        title="Toggle Status (${user.status === 'active' ? 'Deactivate' : 'Activate'})">
                    <span class="material-symbols-rounded">
                        ${user.status === 'active' ? 'toggle_on' : 'toggle_off'}
                    </span>
                </button>
            ` : ''}
            ${!user.can_toggle && currentUserRole !== 'Owner' ? '<span style="color: #999;">No actions</span>' : ''}
        </td>
    </tr>
`).join('');
}

function updateAddUserButton(permissions) {
    const addButton = document.querySelector('.btn-add-user');
    if (!addButton) return;

    if (!permissions.can_create_owner && !permissions.can_create_admin && !permissions.can_create_secretary) {
        addButton.style.display = 'none';
    } else {
        addButton.style.display = 'flex';

        const roleSelect = document.getElementById('au-role');
        if (roleSelect) {
            roleSelect.innerHTML = '<option value="">Select Role</option>';

            if (permissions.can_create_owner) {
                roleSelect.innerHTML += '<option value="Owner">Owner</option>';
            }
            if (permissions.can_create_admin) {
                roleSelect.innerHTML += '<option value="Admin">Admin</option>';
            }
            if (permissions.can_create_secretary) {
                roleSelect.innerHTML += '<option value="Secretary">Secretary</option>';
            }
        }
    }
}

async function handleAddUser(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const data = {
        username: formData.get('username'),
        full_name: formData.get('full_name'),
        role: formData.get('role'),
        password: formData.get('password')
    };

    try {
        const response = await fetch('/backend/api/admin_accounts.php?action=create', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification('User created successfully!', 'success');
            closeModal('addUserModal');
            e.target.reset();
            loadUsers();
        } else {
            showNotification(result.message || 'Failed to create user', 'error');
        }
    } catch (error) {
        console.error('Create user error:', error);
        showNotification('Failed to create user', 'error');
    }
}

async function resetUserPassword(userId) {
    try {
        const response = await fetch('/backend/api/admin_accounts.php?action=list', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        const result = await response.json();

        if (result.success) {
            const user = result.data.users.find(u => u.id === userId);
            if (user) {
                createResetPasswordModal(user);
                openModal('resetPasswordModal');
            }
        }
    } catch (error) {
        console.error('Error loading user for password reset:', error);
        showNotification('Failed to load user data', 'error');
    }
}

function createResetPasswordModal(user) {
    const existingModal = document.getElementById('resetPasswordModal');
    if (existingModal) {
        existingModal.remove();
    }

    const modalHtml = `
        <div class="modal" id="resetPasswordModal">
            <div class="modal-content">
                <button class="modal-close" aria-label="Close">×</button>
                <h2>Reset Password for ${escapeHtml(user.full_name)}</h2>
                <form id="resetPasswordForm">
                    <input type="hidden" id="rp-id" value="${user.id}">
                    <input id="rp-new-password" type="password" placeholder="New Password" required />
                    <input id="rp-confirm-password" type="password" placeholder="Confirm New Password" required />
                    <div style="display:flex;gap:.5rem;justify-content:flex-end">
                        <button type="submit" class="primary">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    document.getElementById('resetPasswordForm').addEventListener('submit', handleResetPassword);
}

async function handleResetPassword(e) {
    e.preventDefault();

    const newPassword = document.getElementById('rp-new-password').value;
    const confirmPassword = document.getElementById('rp-confirm-password').value;

    if (newPassword !== confirmPassword) {
        showNotification('Passwords do not match', 'error');
        return;
    }

    if (newPassword.length < 6) {
        showNotification('Password must be at least 6 characters long', 'error');
        return;
    }

    const data = {
        id: parseInt(document.getElementById('rp-id').value),
        new_password: newPassword
    };

    try {
        const response = await fetch('/backend/api/admin_accounts.php?action=reset_password', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Password reset successfully!', 'success');
            closeModal('resetPasswordModal');
            document.getElementById('resetPasswordModal').remove();
        } else {
            showNotification(result.message || 'Failed to reset password', 'error');
        }
    } catch (error) {
        console.error('Reset password error:', error);
        showNotification('Failed to reset password', 'error');
    }
}

// ============================================================================
// CUSTOMER MANAGEMENT (View and Delete only - Edit removed)
// ============================================================================

function initializeCustomerManagement() {
    const customerNavItem = document.querySelector('[data-section="customer"]');
    if (customerNavItem) {
        customerNavItem.addEventListener('click', function () {
            setTimeout(() => loadCustomers(), 100);
        });
    }

    const customerSearch = document.getElementById('customer-search');
    if (customerSearch) {
        let searchTimeout;
        customerSearch.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadCustomers(this.value.trim());
            }, 300);
        });
    }

    const currentSection = document.querySelector('.nav-item.active');
    if (currentSection && currentSection.dataset.section === 'customer') {
        loadCustomers();
    }
}

async function loadCustomers(search = '') {
    try {
        const url = `/backend/api/admin_customers.php?action=list${search ? `&search=${encodeURIComponent(search)}` : ''}`;

        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });

        const responseText = await response.text();
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            throw new Error('Server returned invalid JSON response');
        }

        if (!result.success) {
            throw new Error(result.message || 'Failed to load customers');
        }

        displayCustomers(result.data.customers);
        updateDashboardStats();
    } catch (error) {
        console.error('Error loading customers:', error);
        showNotification('Failed to load customers: ' + error.message, 'error');
    }
}

function displayCustomers(customers) {
    const tbody = document.getElementById('customerTableBody');
    if (!tbody) return;

    if (!customers || customers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#666;">No customers found</td></tr>';
        return;
    }

    tbody.innerHTML = customers.map(customer => `
    <tr data-customer-id="${customer.id}">
        <td>${customer.id}</td>
        <td>${escapeHtml(customer.username || 'N/A')}</td>
        <td>${escapeHtml(customer.full_name || 'N/A')}</td>
        <td>${escapeHtml(customer.email || 'N/A')}</td>
        <td>${escapeHtml(customer.phone || 'N/A')}</td>
        <td>${customer.formatted_date || customer.created_at || 'N/A'}</td>
        <td>
            <button class="btn-action btn-view-customer" data-customer-id="${customer.id}" title="View Details">
                <span class="material-symbols-rounded">visibility</span>
            </button>
        </td>
    </tr>
`).join('');
}

async function viewCustomer(id) {
    try {
        const response = await fetch(`/backend/api/admin_customers.php?action=view&id=${id}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        const responseText = await response.text();
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            throw new Error('Server returned invalid response');
        }

        if (!result.success) {
            throw new Error(result.message || 'Failed to load customer details');
        }

        const customer = result.data;

        const existingModal = document.getElementById('viewCustomerModal');
        if (existingModal) {
            existingModal.remove();
        }

        const modalHtml = `
            <div class="modal" id="viewCustomerModal" style="display: flex;">
                <div class="modal-content">
                    <button class="modal-close" onclick="closeViewCustomerModal()">×</button>
                    <h2>Customer Details</h2>
                    <div class="customer-details" style="display: grid; gap: 0.5rem;">
                        <div class="detail-row"><strong>ID:</strong> ${customer.id}</div>
                        <div class="detail-row"><strong>Username:</strong> ${escapeHtml(customer.username)}</div>
                        <div class="detail-row"><strong>Full Name:</strong> ${escapeHtml(customer.full_name)}</div>
                        <div class="detail-row"><strong>Email:</strong> ${escapeHtml(customer.email)}</div>
                        <div class="detail-row"><strong>Phone:</strong> ${escapeHtml(customer.phone || 'N/A')}</div>
                        <div class="detail-row"><strong>Address:</strong> ${escapeHtml(customer.address || 'N/A')}</div>
                        <div class="detail-row"><strong>Email Verified:</strong> ${customer.email_verified ? 'Yes' : 'No'}</div>
                        <div class="detail-row"><strong>Member Since:</strong> ${formatDate(customer.created_at)}</div>
                        <div class="detail-row"><strong>Total Orders:</strong> ${customer.order_count || 0}</div>
                        <div class="detail-row"><strong>Total Spent:</strong> ₱${(customer.total_spent || 0).toLocaleString()}</div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

    } catch (error) {
        console.error('Error viewing customer:', error);
        showNotification('Failed to load customer details: ' + error.message, 'error');
    }
}

function closeViewCustomerModal() {
    const modal = document.getElementById('viewCustomerModal');
    if (modal) {
        modal.style.display = 'none';
        modal.remove();
    }
}

// ============================================================================
// ORDER MANAGEMENT
// ============================================================================

function initializeOrderManagement() {
    const orderNavItem = document.querySelector('[data-section="orders"]');
    if (orderNavItem) {
        orderNavItem.addEventListener('click', function () {
            setTimeout(() => loadOrders(), 100);
        });
    }

    const orderSearch = document.getElementById('order-search');
    if (orderSearch) {
        let searchTimeout;
        orderSearch.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadOrders(this.value.trim());
            }, 300);
        });
    }

    const statusFilter = document.getElementById('statusFilter');
    const paymentFilter = document.getElementById('paymentFilter');

    [statusFilter, paymentFilter].forEach(filter => {
        if (filter) {
            filter.addEventListener('change', () => loadOrders());
        }
    });

    const currentSection = document.querySelector('.nav-item.active');
    if (currentSection && currentSection.dataset.section === 'orders') {
        loadOrders();
    }
}

async function loadOrders(search = '') {
    try {
        const statusFilter = document.getElementById('statusFilter')?.value || '';
        const paymentFilter = document.getElementById('paymentFilter')?.value || '';

        const params = new URLSearchParams();
        if (search) params.append('search', search);
        if (statusFilter) params.append('status', statusFilter);
        if (paymentFilter) params.append('payment_status', paymentFilter);

        const url = `/backend/api/admin_orders.php?action=list&${params.toString()}`;

        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        const result = await response.json();

        if (result.success) {
            displayOrders(result.data.orders);
        } else {
            showNotification(result.message || 'Failed to load orders', 'error');
        }
    } catch (error) {
        console.error('Error loading orders:', error);
        showNotification('Failed to load orders: ' + error.message, 'error');
    }
}
async function viewAdminOrderDetails(orderId) {
    try {
        const response = await fetch(`/backend/api/admin_orders.php?action=details&id=${orderId}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to load order details');
        }
        const { order, items } = result.data;

        // Create modal content
        const existingModal = document.getElementById('adminOrderDetailsModal');
        if (existingModal) {
            existingModal.remove();
        }
         // Use stored tax_rate if available, otherwise default to 12%
        let taxPercentage = 12; // Default to 12%

        if (order.tax_rate !== undefined && order.tax_rate !== null) {
            taxPercentage = parseFloat(order.tax_rate);

        } else if (order.tax_percentage !== undefined && order.tax_percentage !== null) {
            const calculated = parseFloat(order.tax_percentage);

            // If calculated is close to 12%, use 12%; otherwise use calculated (for historical data)
            taxPercentage = (Math.abs(calculated - 12) < 1) ? 12 : Math.round(calculated);
        }
        const remainingBalance = parseFloat(order.remaining_balance || 0);
        const paymentStatusText = order.payment_status_text || 'Pending';
        const deliveryAddress = order.delivery_address || 'N/A';
        const modalHtml = `
            <div class="modal" id="adminOrderDetailsModal" style="display: flex;">
                <div class="modal-content" style="max-width: 900px;">
                    <button class="modal-close" onclick="closeAdminOrderModal()">×</button>
                    <h2>Order Details - ${escapeHtml(order.order_code)}</h2>

                    <div style="display: grid; gap: 1.5rem;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <h3 style="color: var(--brand); margin-bottom: 0.5rem;">Order Information</h3>
                                <p><strong>Order Code:</strong> ${escapeHtml(order.order_code)}</p>
                                <p><strong>Order Date:</strong> ${formatDate(order.order_date)}</p>
                                <p><strong>Order Status:</strong> <span class="badge badge-${order.status.toLowerCase()}">${escapeHtml(order.status)}</span></p>
                                <p><strong>Delivery Mode:</strong> ${escapeHtml(order.mode)}</p>
                            </div>
                            <div>
                                <h3 style="color: var(--brand); margin-bottom: 0.5rem;">Customer Information</h3>
                                <p><strong>Name:</strong> ${escapeHtml(order.customer_name)}</p>
                                <p><strong>Email:</strong> ${escapeHtml(order.customer_email)}</p>
                                <p><strong>Phone:</strong> ${escapeHtml(order.customer_phone || 'N/A')}</p>
                            </div>
                             <div>
                            <h3 style="color: var(--brand); margin-bottom: 0.5rem;">Delivery Address</h3>
                            <p style="white-space: pre-line;">${escapeHtml(deliveryAddress)}</p>
                        </div>
                        <div>
                            <h3 style="color: var(--brand); margin-bottom: 0.5rem;">Payment Information</h3>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div>
                                    <p><strong>Payment Method:</strong> ${escapeHtml((order.payment_method || 'N/A').toUpperCase())}</p>
                                    <p><strong>Payment Status:</strong> <span class="badge badge-${paymentStatusText === 'Fully Paid' ? 'completed' : 'pending'}">${paymentStatusText}</span></p>
                                </div>
                                <div>
                                    <p><strong>Amount Paid:</strong> ₱${parseFloat(order.amount_paid || 0).toLocaleString()}</p>
                                    <p><strong>Remaining Balance:</strong> <span style="color: ${remainingBalance > 0 ? 'var(--danger-color)' : 'var(--success-color)'}; font-weight: 600;">₱${remainingBalance.toLocaleString()}</span></p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 style="color: var(--brand); margin-bottom: 0.5rem;">Order Items</h3>
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="border-bottom: 2px solid #e3e3e3;">
                                        <th style="text-align: left; padding: 0.5rem;">ITEM</th>
                                        <th style="text-align: center; padding: 0.5rem;">QUANTITY</th>
                                        <th style="text-align: right; padding: 0.5rem;">PRICE</th>
                                        <th style="text-align: right; padding: 0.5rem;">TAX (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${items.map(item => `
                                        <tr style="border-bottom: 1px solid #e3e3e3;">
                                            <td style="padding: 0.5rem;">${escapeHtml(item.name)}</td>
                                            <td style="text-align: center; padding: 0.5rem;">${item.qty || item.quantity}</td>
                                            <td style="text-align: right; padding: 0.5rem;">₱${parseFloat(item.unit_price).toLocaleString()}</td>
                                            <td style="text-align: right; padding: 0.5rem;">${taxPercentage}%</td>
                                        </tr>
                                    `).join('')}
                                    </tbody>
                                <tfoot>
                                    <tr style="border-top: 2px solid var(--brand);">
                                        <td colspan="3" style="text-align: right; padding: 0.75rem; font-weight: 700; font-size: 1.05rem;">TOTAL AMOUNT</td>
                                        <td style="text-align: right; padding: 0.75rem; font-weight: 700; font-size: 1.2rem; color: var(--brand);">₱${parseFloat(order.total_amount).toLocaleString()}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                     </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

    } catch (error) {
        console.error('Error loading order details:', error);
        showNotification('Failed to load order details: ' + error.message, 'error');
    }
}

function closeAdminOrderModal() {
    const modal = document.getElementById('adminOrderDetailsModal');
    if (modal) {
        modal.style.display = 'none';
        modal.remove();
    }
}

async function openOrderEditPanel(orderId) {
    // Close the details modal first
    closeAdminOrderModal();

    try {
        // Fetch order details to populate the edit form
        const response = await fetch(`/backend/api/admin_orders.php?action=details&id=${orderId}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to load order details');
        }

        const { order } = result.data;
        const totalAmount = parseFloat(order.total_amount || 0);
        const amountPaid = parseFloat(order.amount_paid || 0);
        const remainingBalance = parseFloat(order.remaining_balance || 0);
        const paymentStatusText = order.payment_status_text || 'Pending';

        // Create edit modal
        const existingModal = document.getElementById('orderEditModal');
        if (existingModal) {
            existingModal.remove();
        }

        const modalHtml = `
            <div class="modal" id="orderEditModal" style="display: flex;">
                <div class="modal-content" style="max-width: 700px;">
                    <button class="modal-close" onclick="closeOrderEditPanel()">×</button>
                    <h2>Edit Order - ${escapeHtml(order.order_code)}</h2>

                    <div style="background: #f7fafc; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                        <h3 style="margin: 0 0 0.5rem 0; color: var(--brand);">Current Order Summary</h3>
                        <p style="margin: 0.25rem 0;"><strong>Order Status:</strong> ${escapeHtml(order.status)}</p>
                        <p style="margin: 0.25rem 0;"><strong>Payment Status:</strong> ${paymentStatusText}</p>
                        <p style="margin: 0.25rem 0;"><strong>Total Amount:</strong> ₱${totalAmount.toLocaleString()}</p>
                        <p style="margin: 0.25rem 0;"><strong>Amount Paid:</strong> ₱${amountPaid.toLocaleString()}</p>
                        <p style="margin: 0.25rem 0;"><strong>Remaining Balance:</strong> <span style="color: ${remainingBalance > 0 ? 'var(--danger-color)' : 'var(--success-color)'}; font-weight: 600;">₱${remainingBalance.toLocaleString()}</span></p>
                    </div>

                    <div style="margin: 1.5rem 0;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Order Status</label>
                        <select id="edit-order-status" style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem;">
                            <option value="Pending" ${order.status === 'Pending' ? 'selected' : ''}>Pending</option>
                            <option value="Processing" ${order.status === 'Processing' ? 'selected' : ''}>Processing</option>
                            <option value="Completed" ${order.status === 'Completed' ? 'selected' : ''} ${remainingBalance > 0 ? 'disabled' : ''}>Completed${remainingBalance > 0 ? ' (Balance must be ₱0.00)' : ''}</option>
                            <option value="Cancelled" ${order.status === 'Cancelled' ? 'selected' : ''}>Cancelled</option>
                        </select>
                        ${remainingBalance > 0 ? `<p style="margin-top: 0.5rem; font-size: 0.875rem; color: var(--danger-color);"><strong>Note:</strong> Order cannot be marked as Completed while remaining balance is ₱${remainingBalance.toFixed(2)}. Approve payments first.</p>` : ''}
                    </div>

                    <div style="margin: 1.5rem 0;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Payment Status</label>
                        <select id="edit-payment-status" style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem;">
                            <option value="Pending" ${paymentStatusText === 'Pending' ? 'selected' : ''}>Pending</option>
                            <option value="With Balance" ${paymentStatusText === 'With Balance' ? 'selected' : ''}>With Balance / Partially Paid</option>
                            <option value="Fully Paid" ${paymentStatusText === 'Fully Paid' ? 'selected' : ''}>Fully Paid</option>
                        </select>
                    </div>

                    <div style="margin: 1.5rem 0;">

                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Admin Notes (optional)</label>
                        <textarea id="edit-admin-notes" style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem; min-height: 80px;" placeholder="Add any notes about this status change..."></textarea>
                    </div>

                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1.5rem;">
                        <button onclick="closeOrderEditPanel()" class="btn-secondary">Cancel</button>
                        <button onclick="saveOrderChanges(${order.id})" class="btn-primary">Save Changes</button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

    } catch (error) {
        console.error('Error opening order edit panel:', error);
        showNotification('Failed to open order edit panel: ' + error.message, 'error');
    }
}

function closeOrderEditPanel() {
    const modal = document.getElementById('orderEditModal');
    if (modal) {
        modal.style.display = 'none';
        modal.remove();
    }
}

function closeSuccessModal() {
    closeModal('successModal');
    // Refresh order list to show updated payment status
    if (typeof loadOrders === 'function') {
        loadOrders();
    }
}

async function saveOrderChanges(orderId) {
    const newOrderStatus = document.getElementById('edit-order-status')?.value;
    const newPaymentStatus = document.getElementById('edit-payment-status')?.value;
    const adminNotes = document.getElementById('edit-admin-notes')?.value || '';

    if (!newOrderStatus || !newPaymentStatus) {
        showNotification('Please select both order status and payment status', 'error');
        return;
    }

    try {
        // Update order status first
        const orderStatusResponse = await fetch('/backend/api/admin_orders.php?action=update_status', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },

            body: JSON.stringify({
                order_id: orderId,
                status: newOrderStatus,
                notes: adminNotes
            })
        });

        const orderStatusResult = await orderStatusResponse.json();

        if (!orderStatusResult.success) {
            showNotification(orderStatusResult.message || 'Failed to update order status', 'error');
            return;
        }

        // Update payment status
        const paymentStatusResponse = await fetch('/backend/api/admin_orders.php?action=update_payment', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'

            },
            body: JSON.stringify({
                id: orderId,
                payment_status: newPaymentStatus
            })
        });

        const paymentStatusResult = await paymentStatusResponse.json();

        if (!paymentStatusResult.success) {
            showNotification(paymentStatusResult.message || 'Failed to update payment status', 'error');
            return;
        }

        showNotification('Order updated successfully!', 'success');
        closeOrderEditPanel();
        loadOrders(); // Reload orders table

    } catch (error) {
        console.error('Error saving order changes:', error);
        showNotification('Failed to save changes: ' + error.message, 'error');
    }
}

async function handleAdminPaymentStatusUpdate(orderId) {
    const selectEl = document.getElementById(`admin-payment-status-${orderId}`);
    if (!selectEl) return;

    const newPaymentStatus = selectEl.value;
    if (!newPaymentStatus) {
        showNotification('Please select a payment status', 'error');
        return;
    }

    try {
        const response = await fetch('/backend/api/admin_orders.php?action=update_payment', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                id: orderId,
                payment_status: newPaymentStatus
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Payment status updated successfully!', 'success');
            closeAdminOrderModal();
            loadOrders(); // Reload orders table
        } else {
            showNotification(result.message || 'Failed to update payment status', 'error');
        }
    } catch (error) {
        console.error('Error updating payment status:', error);
        showNotification('Failed to update payment status: ' + error.message, 'error');
    }
}

// ✅ FIXED: Added payment status validation before showing update modal
function updateOrderStatus(orderId, currentStatus) {
    // Close detail modal if open
    closeAdminOrderModal();

    // First, fetch order details to check payment status
    fetch(`/backend/api/admin_orders.php?action=details&id=${orderId}`, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
    })
    .then(response => response.json())
    .then(result => {
        if (!result.success) {
            showNotification('Failed to load order details', 'error');
            return;
        }

        const order = result.data.order;
        const remainingBalance = parseFloat(order.remaining_balance || 0);
        const paymentStatusText = order.payment_status_text || 'Pending';
        const isFullyPaid = paymentStatusText === 'Fully Paid' || remainingBalance <= 0.01;

        // Create status update modal
        const existingModal = document.getElementById('updateStatusModal');
        if (existingModal) {
            existingModal.remove();
        }

        const modalHtml = `
            <div class="modal" id="updateStatusModal" style="display: flex;">
                <div class="modal-content" style="max-width: 600px;">
                    <button class="modal-close" onclick="closeUpdateStatusModal()">×</button>
                    <h2>Update Order Status</h2>
                    
                    <form id="updateStatusForm">
                        <input type="hidden" id="update-order-id" value="${orderId}">
                        
                        <!-- Current Status -->
                        <div style="margin: 1.5rem 0;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Current Order Status:</label>
                            <p style="margin: 0; padding: 0.75rem; background: #f7fafc; border-radius: 6px;">
                                <span class="badge badge-${getStatusBadgeClass(currentStatus)}">${escapeHtml(currentStatus)}</span>
                            </p>
                        </div>

                        <!-- Payment Status Info -->
                        <div style="margin: 1.5rem 0; padding: 1rem; background: ${isFullyPaid ? '#d4edda' : '#fff3cd'}; border-radius: 6px; border: 1px solid ${isFullyPaid ? '#c3e6cb' : '#ffc107'};">
                            <p style="margin: 0 0 0.5rem 0; font-weight: 600; color: ${isFullyPaid ? '#155724' : '#856404'};">Payment Status: ${paymentStatusText}</p>
                            <p style="margin: 0; font-size: 0.9rem; color: ${isFullyPaid ? '#155724' : '#856404'};">
                                ${isFullyPaid ? 
                                    '✓ Payment is complete. Order can be marked as Completed.' : 
                                    `⚠ Remaining balance: ₱${remainingBalance.toFixed(2)}. Order cannot be completed until payment is fully settled.`
                                }
                            </p>
                        </div>

                        <!-- New Order Status -->
                        <div style="margin: 1.5rem 0;">
                            <label for="new-status" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">New Order Status:</label>
                            <select id="new-status" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem;">
                                <option value="">Select Status</option>
                                <option value="Pending" ${currentStatus === 'Pending' ? 'selected' : ''}>Pending</option>
                                <option value="Processing" ${currentStatus === 'Processing' ? 'selected' : ''}>Processing</option>
                                <option value="Completed" ${currentStatus === 'Completed' ? 'selected' : ''} ${!isFullyPaid ? 'disabled' : ''}>
                                    Completed${!isFullyPaid ? ' (Requires Full Payment)' : ''}
                                </option>
                                <option value="Cancelled" ${currentStatus === 'Cancelled' ? 'selected' : ''}>Cancelled</option>
                            </select>
                            ${!isFullyPaid ? `
                                <p style="margin-top: 0.5rem; font-size: 0.875rem; color: #e74c3c;">
                                    <strong>Note:</strong> You cannot set this order to Completed until payment is fully settled. Please approve pending payments first.
                                </p>
                            ` : ''}
                        </div>

                        <!-- Payment Status Update (if Owner/Admin) -->
                        ${(['Owner', 'Admin'].includes(currentUserRole)) ? `
                            <div style="margin: 1.5rem 0;">
                                <label for="new-payment-status" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Payment Status (Optional):</label>
                                <select id="new-payment-status" style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem;">
                                    <option value="">Keep current: ${paymentStatusText}</option>
                                    <option value="Pending">Pending</option>
                                    <option value="With Balance">With Balance</option>
                                    <option value="Fully Paid">Fully Paid</option>
                                </select>
                                <p style="margin-top: 0.5rem; font-size: 0.875rem; color: #666;">
                                    Leave unchanged unless manually adjusting payment status.
                                </p>
                            </div>
                        ` : ''}

                        <div style="background: #e8f4f8; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                            <p style="margin: 0; font-size: 0.9rem; color: #2f5b88;">
                                <strong>Note:</strong> Changing the order status will notify the customer and update their order tracking.
                            </p>
                        </div>

                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1.5rem;">
                            <button type="button" onclick="closeUpdateStatusModal()" class="btn-secondary">Cancel</button>
                            <button type="submit" class="btn-primary">Update Status</button>
                        </div>
                    </form>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Add form submit handler
        document.getElementById('updateStatusForm').addEventListener('submit', handleStatusUpdate);
    })
    .catch(error => {
        console.error('Error loading order details:', error);
        showNotification('Failed to load order details', 'error');
    });
}

function closeUpdateStatusModal() {
    const modal = document.getElementById('updateStatusModal');
    if (modal) {
        modal.style.display = 'none';
        modal.remove();
    }
}

// ✅ FIXED: Can now update both order status and payment status
async function handleStatusUpdate(e) {
    e.preventDefault();

    const orderId = parseInt(document.getElementById('update-order-id').value);
    const newStatus = document.getElementById('new-status').value;
    const newPaymentStatus = document.getElementById('new-payment-status')?.value;

    if (!newStatus) {
        showNotification('Please select a status', 'error');
        return;
    }

    try {
        // Update order status first
        const orderStatusResponse = await fetch('/backend/api/admin_orders.php?action=update_status', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                order_id: orderId,
                status: newStatus
            })
        });

        const orderStatusResult = await orderStatusResponse.json();

        if (!orderStatusResult.success) {
            showNotification(orderStatusResult.message || 'Failed to update status', 'error');
            return;
        }

        // Update payment status if provided
        if (newPaymentStatus && newPaymentStatus !== '') {
            const paymentStatusResponse = await fetch('/backend/api/admin_orders.php?action=update_payment', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    id: orderId,
                    payment_status: newPaymentStatus
                })
            });

            const paymentStatusResult = await paymentStatusResponse.json();
            
            if (!paymentStatusResult.success) {
                showNotification('Order status updated, but payment status update failed', 'warning');
            }
        }

        showNotification('Order status updated successfully!', 'success');
        closeUpdateStatusModal();
        loadOrders(); // Reload orders table

    } catch (error) {
        console.error('Error updating order status:', error);
        showNotification('Failed to update order status', 'error');
    }
}

function displayOrders(orders) {
    const tbody = document.getElementById('orderTableBody');
    if (!tbody) return;

    if (!orders || orders.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#666;">No orders found</td></tr>';
        return;
    }

    tbody.innerHTML = orders.map(order => `
        <tr data-order-id="${order.id}">
            <td>${escapeHtml(order.order_code || order.id)}</td>
            <td>${escapeHtml(order.product_name || 'N/A')}</td>
            <td>${escapeHtml(order.customer_name || 'Unknown')}</td>
            <td>${formatDate(order.order_date)}</td>
            <td>₱${parseFloat(order.total_amount || 0).toLocaleString()}</td>
            <td><span class="badge badge-${getPaymentBadgeClass(order.payment_status)}">${order.payment_status || 'With Balance'}</span></td>
<td><span class="badge badge-${getStatusBadgeClass(order.status)}">${order.status || 'Pending'}</span></td>

            <td>
                <button class="btn-action btn-view" onclick="viewAdminOrderDetails(${order.id})" title="View Details">
                    <span class="material-symbols-rounded">visibility</span>
                </button>
                ${(['Owner', 'Admin'].includes(currentUserRole)) ? `
                    <button class="btn-action btn-edit" onclick="updateOrderStatus(${order.id}, '${escapeHtml(order.status)}')" title="Update Status">
                        <span class="material-symbols-rounded">edit</span>
                    </button>
                ` : ''}
            </td>
        </tr>
    `).join('');
}

function getStatusBadgeClass(status) {
    const s = (status || '').toLowerCase();
    switch (s) {
        case 'pending': return 'pending';
        case 'processing': return 'processing';
        case 'completed': return 'completed';
        case 'cancelled': return 'cancelled';
        default: return 'pending'; // unknown/null -> pending
    }
}


function getPaymentBadgeClass(paymentStatus) {
    switch (paymentStatus?.toLowerCase()) {
        case 'fully paid': return 'paid';
        case 'with balance': return 'partial';
        default: return 'partial';
    }
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    try {
        const d = new Date(dateString);
        const date = d.toLocaleDateString(undefined, { year: 'numeric', month: '2-digit', day: '2-digit' });
        // 12-hr with AM/PM
        const time = d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit', hour12: true });
        return `${date} ${time}`;
    } catch (e) {
        return dateString || 'N/A';
    }
}


// /assets/JS/checkout.js
document.addEventListener('DOMContentLoaded', function () {
    const proceedBtn = document.getElementById('proceedToPayBtn') || document.querySelector('.btn-proceed');

    if (!proceedBtn) return;

    proceedBtn.addEventListener('click', async function (e) {
        e.preventDefault();

        // Get selected deposit rate
        const selectedDeposit = document.querySelector('input[name="deposit"]:checked');
        if (!selectedDeposit) {
            alert('Please select a deposit option (30%, 50%, or 100%)');
            return;
        }

        const depositRate = parseInt(selectedDeposit.value);

        // Determine if delivery or pickup mode
        const isDelivery = window.location.pathname.includes('checkout_delivery');

        // Get form values
        const firstName = document.getElementById('firstName')?.value || '';
        const lastName = document.getElementById('lastName')?.value || '';
        const phone = document.getElementById('phone')?.value || '';
        const email = document.getElementById('email')?.value || '';

        // Validate required fields
        if (!firstName || !lastName || !phone || !email) {
            alert('Please fill in all required fields');
            return;
        }

        // Additional validation for delivery
        if (isDelivery) {
            const province = document.getElementById('province')?.value || '';
            const city = document.getElementById('city')?.value || '';
            const barangay = document.getElementById('barangay')?.value || '';
            const street = document.getElementById('street')?.value || '';

            if (!province || !city || !barangay || !street) {
                alert('Please fill in all delivery address fields');
                return;
            }
        }

        // Get product and pricing info
        const urlParams = new URLSearchParams(window.location.search);
        const pid = urlParams.get('pid') || '';
        const totalAmount = parseFloat(document.getElementById('totalAmount')?.textContent?.replace(/[₱,]/g, '') || '0');
        const subtotalAmount = parseFloat(document.getElementById('subtotalAmount')?.textContent?.replace(/[₱,]/g, '') || '0');
        const vatAmount = parseFloat(document.getElementById('vatAmount')?.textContent?.replace(/[₱,]/g, '') || '0');

        if (!pid || totalAmount <= 0) {
            alert('Invalid order information');
            return;
        }

        try {
            const orderData = {
                pid: pid,
                qty: 1,
                subtotal: subtotalAmount,
                vat: vatAmount,
                total: totalAmount,
                mode: isDelivery ? 'delivery' : 'pickup',
                deposit_rate: depositRate,
                info: {
                    first_name: firstName,
                    last_name: lastName,
                    phone: phone,
                    email: email,
                    province: document.getElementById('province')?.value || '',
                    city: document.getElementById('city')?.value || '',
                    barangay: document.getElementById('barangay')?.value || '',
                    street: document.getElementById('street')?.value || '',
                    postal: document.getElementById('postal')?.value || ''
                }
            };

            console.log('Sending order data:', orderData);

            const response = await fetch('/backend/api/order_create.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify(orderData)
            });

            const result = await response.json();
            console.log('Server response:', result);

            if (!result.success) {
                if (result.redirect) {
                    alert(result.message);
                    window.location.href = result.redirect;
                    return;
                }
                throw new Error(result.message || 'Failed to create order');
            }

            // Success - redirect to payment submission page
            window.location.href = '/customer/payment_submit.php?order_id=' + result.order_id + '&deposit_rate=' + depositRate;

        } catch (error) {
            console.error('Order creation error:', error);
            alert('Failed to create order: ' + error.message);
        }
    });
});
// ============================================================================
// DASHBOARD
// ============================================================================

function initializeDashboard() {
    initializeChart('week');
    setupReports();
    updateDashboardStats();
    loadRecentOrders();
    loadRecentFeedback();
    console.log('Dashboard initialized');
}

async function updateDashboardStats() {
    try {
        const response = await fetch('/backend/api/dashboard.php?action=stats', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        const result = await response.json();

        if (result.success && result.data) {
            const ordersEl = document.getElementById('dash-orders');
            const salesEl = document.getElementById('dash-sales');
            const customersEl = document.getElementById('dash-customers');
            const downPaymentsEl = document.getElementById('dash-down-payments');
            const incomingSalesEl = document.getElementById('dash-incoming-sales');

            if (ordersEl) ordersEl.textContent = result.data.total_orders.toLocaleString();
            if (salesEl) salesEl.textContent = '₱' + result.data.total_sales.toLocaleString();
            if (customersEl) customersEl.textContent = result.data.total_customers.toLocaleString();
            if (downPaymentsEl) downPaymentsEl.textContent = '₱' + result.data.total_down_payments.toLocaleString();
            if (incomingSalesEl) incomingSalesEl.textContent = '₱' + result.data.incoming_sales.toLocaleString();
        }
    } catch (error) {
        console.error('Error updating dashboard stats:', error);
        const ordersEl = document.getElementById('dash-orders');
        const salesEl = document.getElementById('dash-sales');
        const customersEl = document.getElementById('dash-customers');
        const downPaymentsEl = document.getElementById('dash-down-payments');
        const incomingSalesEl = document.getElementById('dash-incoming-sales');
        if (ordersEl && ordersEl.textContent === 'Loading...') ordersEl.textContent = '0';
        if (salesEl && salesEl.textContent === 'Loading...') salesEl.textContent = '₱0';
        if (customersEl && customersEl.textContent === 'Loading...') customersEl.textContent = '0';
    }
}

async function loadRecentOrders() {
    try {
        const response = await fetch('/backend/api/dashboard.php?action=recent_orders', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        const result = await response.json();

        if (result.success && result.data) {
            const tbody = document.getElementById('dashRecentOrders');
            if (tbody) {
                tbody.innerHTML = result.data.length > 0 ? result.data.map(order => `
                    <tr>
                        <td>${escapeHtml(order.order_code)}</td>
                        <td>${escapeHtml(order.customer_name)}</td>
                        <td>${formatDate(order.order_date)}</td>
                        <td><span class="badge badge-${getStatusBadgeClass(order.status)}">${order.status || 'Pending'}</span></td>
                        <td>₱${parseFloat(order.total_amount || 0).toLocaleString()}</td>
                    </tr>
                `).join('') : '<tr><td colspan="5" style="text-align:center;">No recent orders</td></tr>';
            }
        }
    } catch (error) {
        console.error('Error loading recent orders:', error);
        const tbody = document.getElementById('dashRecentOrders');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">Error loading orders</td></tr>';
        }
    }
}

async function loadRecentFeedback() {
    try {
        const response = await fetch('/backend/api/dashboard.php?action=recent_feedback', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        const result = await response.json();

        if (result.success && result.data) {
            const ul = document.getElementById('dashFeedbackList');
            if (ul) {
                ul.innerHTML = result.data.length > 0 ? result.data.map(feedback => `
                    <li>
                        <span class="feedback-customer">${escapeHtml(feedback.customer_name)}</span>
                        <span class="feedback-rating">${'★'.repeat(feedback.rating || 0)}</span>
                        <span class="feedback-comment">${escapeHtml(feedback.comment || 'No comment')}</span>
                    </li>
                `).join('') : '<li>No recent feedback</li>';
            }
        }
    } catch (error) {
        console.error('Error loading recent feedback:', error);
        const ul = document.getElementById('dashFeedbackList');
        if (ul) {
            ul.innerHTML = '<li>Error loading feedback</li>';
        }
    }
}

let salesChart = null;

function initializeChart(period = 'week') {
    const ctx = document.getElementById('salesChart');
    if (!ctx || typeof Chart === 'undefined') return;

    // Add event listeners to period buttons
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            loadSalesChart(this.dataset.period);
        });
    });

    // Load initial chart
    loadSalesChart(period);
}

async function loadSalesChart(period) {
    try {
        const response = await fetch(`/backend/api/dashboard.php?action=sales_chart&period=${period}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to load chart data');
        }

        const ctx = document.getElementById('salesChart');

        // Destroy existing chart if it exists
        if (salesChart) {
            salesChart.destroy();
        }

        // Create new chart
        salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: result.data.labels,
                datasets: [{
                    label: 'Sales',
                    data: result.data.values,
                    borderColor: '#3db36b',
                    backgroundColor: 'rgba(61,179,107,.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return 'Sales: ₱' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    } catch (error) {
        console.error('Error loading sales chart:', error);
        showNotification('Failed to load sales chart', 'error');
    }
}

function setupReports() {
    // Set default dates
    const fromInput = document.getElementById('report-from');
    const toInput = document.getElementById('report-to');

    if (fromInput && toInput) {
        // Set default to current month
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);

        fromInput.valueAsDate = firstDay;
        toInput.valueAsDate = today;
    }

    const generateReportBtn = document.getElementById('generateReportBtn');
    if (generateReportBtn) {
        generateReportBtn.addEventListener('click', generateReport);
    }

    const exportPdfBtn = document.getElementById('exportPdfBtn');
    if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', exportReportPdf);
    }
}

async function generateReport() {
    const fromDate = document.getElementById('report-from').value;
    const toDate = document.getElementById('report-to').value;

    if (!fromDate || !toDate) {
        showNotification('Please select both From and To dates', 'error');
        return;
    }

    if (new Date(fromDate) > new Date(toDate)) {
        showNotification('From date must be before To date', 'error');
        return;
    }

    try {
        // Fetch report data
        const response = await fetch(`/backend/api/report_data.php?from=${fromDate}&to=${toDate}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to generate report');
        }

        // Update summary cards
        updateReportSummary(result.data);
        showNotification('Report generated successfully!', 'success');

    } catch (error) {
        console.error('Error generating report:', error);
        showNotification('Failed to generate report: ' + error.message, 'error');
    }
}

function updateReportSummary(data) {
    // Update each summary card
    const updates = {
        'rg-total-sales': '₱' + (data.total_sales || 0).toLocaleString(),
        'rg-total-orders': (data.total_orders || 0).toLocaleString(),
        'rg-avg-order': '₱' + (data.avg_order || 0).toLocaleString(),
        'rg-fully-paid': (data.fully_paid || 0).toLocaleString(),
        'rg-cancelled': (data.cancelled || 0).toLocaleString(),
        'rg-pending': (data.pending || 0).toLocaleString(),
        'rg-new-customers': (data.new_customers || 0).toLocaleString(),
        'rg-feedbacks': (data.feedbacks || 0).toLocaleString(),
        'rg-most-item': data.most_ordered_item || '—'
    };

    Object.entries(updates).forEach(([id, value]) => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    });
}

function exportReportPdf() {
    const fromDate = document.getElementById('report-from').value;
    const toDate = document.getElementById('report-to').value;

    if (!fromDate || !toDate) {
        showNotification('Please select both From and To dates', 'error');
        return;
    }

    if (new Date(fromDate) > new Date(toDate)) {
        showNotification('From date must be before To date', 'error');
        return;
    }

    // Show loading notification
    showNotification('Generating PDF report...', 'info');

    // Open PDF in new window (will trigger download)
    window.open(
        `/backend/api/generate_report.php?from=${fromDate}&to=${toDate}`,
        '_blank'
    );

    setTimeout(() => {
        showNotification('PDF report downloaded!', 'success');
    }, 1500);
}

// ============================================================================
// CORE FUNCTIONALITY
// ============================================================================

function initializeNavigation() {
    const sidebarNav = document.querySelector('.sidebar-nav');
    if (!sidebarNav) return;

    sidebarNav.addEventListener('click', (e) => {
        const navItem = e.target.closest('.nav-item');
        if (!navItem) return;

        e.preventDefault();

        const targetSection = navItem.getAttribute('data-section');
        if (!targetSection) return;

        if (navItem.style.display === 'none') return;

        switchToSection(targetSection);

        setTimeout(() => {
            if (targetSection === 'account') loadUsers();
            else if (targetSection === 'customer') loadCustomers();
            else if (targetSection === 'orders') loadOrders();
            else if (targetSection === 'payment') loadPaymentVerifications();
            else if (targetSection === 'feedback') loadFeedback(); // ✅ ADD THIS LINE
            else if (targetSection === 'dashboard') {
                updateDashboardStats();
                loadRecentOrders();
                loadRecentFeedback();
            }
        }, 100);
    });
}
function initializeProfileDropdown() {
    const profileBtn = document.getElementById('profileIcon');
    const profileDropdown = document.getElementById('profileDropdown');
    if (!profileBtn || !profileDropdown) return;

    profileBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        profileDropdown.classList.toggle('show');
    });

    document.addEventListener('click', (e) => {
        if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
            profileDropdown.classList.remove('show');
        }
    });

    profileDropdown.addEventListener('click', (e) => {
        if (e.target.tagName === 'BUTTON' || e.target.closest('button')) {
            profileDropdown.classList.remove('show');
        }
    });
}

function initializeModals() {
    window.openModal = function (id) {
        const m = document.getElementById(id);
        if (!m) return;
        m.classList.add('show');
        m.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    window.closeModal = function (id) {
        const m = document.getElementById(id);
        if (!m) return;
        m.classList.remove('show');
        m.style.display = 'none';
        document.body.style.overflow = '';
    };

    document.addEventListener('click', (e) => {
        // FIXED: Only close modal when clicking the backdrop itself, not any content inside
        const target = e.target;

        // Check if the target is a modal element
        if (target.classList && target.classList.contains('modal')) {
            // Make sure we're NOT clicking on the modal-content or anything inside it
            const modalContent = target.querySelector('.modal-content');
            if (modalContent && !modalContent.contains(e.target) && e.target === target) {
                const id = target.id;
                if (id) closeModal(id);
            }
        }

        // Close button handlers
        if (e.target.closest('.modal-close') || e.target.closest('[data-modal-close]')) {
            const modal = e.target.closest('.modal');
            if (modal && modal.id) closeModal(modal.id);
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.show').forEach(m => closeModal(m.id));
        }
    });
}

function initializeActionBindings() {
    // Toggle user status handler
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-toggle');
        if (!btn) return;
        e.preventDefault();

        const userId = btn.getAttribute('data-user-id');
        const currentStatus = btn.getAttribute('data-current-status');
        const row = btn.closest('tr');
        const userName = row?.querySelector('td:nth-child(3)')?.textContent?.trim() || 'this user';
        const newStatus = currentStatus === 'active' ? 'inactive' : 'active';

        showConfirm({
            title: `${newStatus === 'active' ? 'Activate' : 'Deactivate'} User`,
            message: `Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} <b>${userName}</b>?`,
            okText: newStatus === 'active' ? 'Activate' : 'Deactivate',
            onConfirm: async () => {
                try {
                    const response = await fetch('/backend/api/admin_accounts.php?action=toggle_status', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ id: parseInt(userId) })
                    });

                    const result = await response.json();

                    if (!result.success) {
                        throw new Error(result.message || 'Failed to toggle user status');
                    }

                    showNotification(`User ${newStatus === 'active' ? 'activated' : 'deactivated'} successfully`, 'success');
                    loadUsers();

                } catch (error) {
                    console.error('Error toggling user status:', error);
                    showNotification('Failed to toggle user status: ' + error.message, 'error');
                }
            }
        });
    });

    // Reset password handler
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-reset-password');
        if (!btn) return;
        e.preventDefault();

        const userId = btn.getAttribute('data-user-id');
        resetUserPassword(parseInt(userId));
    });

    // Customer action handlers
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-view-customer');
        if (btn) {
            e.preventDefault();
            const customerId = btn.getAttribute('data-customer-id');
            viewCustomer(parseInt(customerId));
        }
    });

    // Order verify handler
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-verify');
        if (!btn) return;
        e.preventDefault();

        const row = btn.closest('tr');
        const orderId = row?.querySelector('td:first-child')?.textContent?.trim() || 'this order';

        showConfirm({
            title: 'Verify Order',
            message: `Mark ${orderId} as verified?`,
            okText: 'Verify',
            onConfirm: () => {
                const statusBadge = row?.querySelector('td:nth-child(7) .badge');
                if (statusBadge) {
                    statusBadge.textContent = 'Processing';
                    statusBadge.className = 'badge badge-processing';
                }
                showNotification('Order verified.', 'success');
            }
        });
    });

    // View order handler
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-view');
        if (!btn) return;
        e.preventDefault();

        const row = btn.closest('tr');
        if (!row) return;

        // safe helper
        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = value;
        };

        // use optional chaining to read children text safely
        setText('vo-code', row.children[0]?.textContent?.trim() || '—');
        setText('vo-customer', row.children[2]?.textContent?.trim() || '—');
        setText('vo-date', row.children[3]?.textContent?.trim() || '—');
        setText('vo-total', row.children[4]?.textContent?.trim() || '₱0');
        setText('vo-status', row.children[6]?.textContent?.trim() || '—');
        setText('vo-payment', row.children[5]?.textContent?.trim() || '—');

        openModal('viewOrderModal');
    });
}

function setupLogout() {
    const logoutBtn = document.getElementById('logoutBtn');
    if (!logoutBtn) return;

    const newLogoutBtn = logoutBtn.cloneNode(true);
    logoutBtn.parentNode.replaceChild(newLogoutBtn, logoutBtn);

    newLogoutBtn.addEventListener('click', function (e) {
        e.preventDefault();
        showConfirm({
            title: 'Logout',
            message: 'Do you really want to log out?',
            okText: 'Logout',
            onConfirm: async () => {
                try {
                    sessionStorage.removeItem('rads_admin_session');
                    await fetch('/backend/api/auth.php?action=logout', {
                        method: 'POST',
                        credentials: 'same-origin'
                    });
                } catch (_) {
                    // ignore network errors during logout 
                }
                location.href = '/index.php';
            }
        });
    });
}

function setupFloatingPwToggle() {
    const eye = document.createElement('span');
    eye.className = 'material-symbols-rounded';
    eye.textContent = 'visibility';
    Object.assign(eye.style, {
        position: 'fixed',
        display: 'none',
        fontSize: '22px',
        color: '#6b7b93',
        cursor: 'pointer',
        zIndex: '2001',
        userSelect: 'none',
        padding: '2px',
        background: '#fff',
        borderRadius: '12px',
        lineHeight: '1'
    });
    eye.style.fontFamily = '"Material Symbols Rounded"';

    document.body.appendChild(eye);

    let activeInput = null;

    function placeEyeFor(input) {
        const r = input.getBoundingClientRect();
        const insetX = 36;
        const eyeH = 22;
        eye.style.left = `${r.right - insetX}px`;
        eye.style.top = `${r.top + (r.height - eyeH) / 2}px`;
    }

    function showEye(input) {
        activeInput = input;
        placeEyeFor(input);
        eye.style.display = 'block';
        eye.textContent = input.type === 'password' ? 'visibility' : 'visibility_off';
    }

    function hideEye() {
        eye.style.display = 'none';
        activeInput = null;
    }

    eye.addEventListener('click', () => {
        if (!activeInput) return;
        if (activeInput.type === 'password') {
            activeInput.type = 'text';
            eye.textContent = 'visibility_off';
        } else {
            activeInput.type = 'password';
            eye.textContent = 'visibility';
        }
        activeInput.focus();
        placeEyeFor(activeInput);
    });

    document.addEventListener('focusin', (e) => {
        const input = e.target.closest('input[type="password"], input[data-pw="1"]');
        if (!input) {
            hideEye();
            return;
        }
        input.setAttribute('data-pw', '1');
        showEye(input);
    });

    document.addEventListener('mousedown', (e) => {
        if (e.target === eye) return;
        if (activeInput && (e.target === activeInput || e.target.closest('input') === activeInput)) return;
        hideEye();
    });
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

function showNotification(message, type = 'info') {
    const el = document.createElement('div');
    el.className = `notification notification-${type}`;
    el.textContent = message;

    let backgroundColor;
    switch (type) {
        case 'success': backgroundColor = '#3db36b'; break;
        case 'error': backgroundColor = '#e14d4d'; break;
        default: backgroundColor = '#2f5b88';
    }

    Object.assign(el.style, {
        position: 'fixed',
        right: '16px',
        bottom: '16px',
        background: backgroundColor,
        color: '#fff',
        padding: '10px 14px',
        borderRadius: '8px',
        boxShadow: '0 2px 12px rgba(0,0,0,.15)',
        zIndex: 2000
    });
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 4000);
}

function showConfirm({ title = 'Confirm', message = 'Are you sure?', okText = 'OK', onConfirm } = {}) {
    const modal = document.getElementById('confirmModal');
    if (!modal) return console.warn('confirmModal not found in DOM.');

    modal.querySelector('#confirmTitle').textContent = title;
    const msgEl = modal.querySelector('#confirmMessage');
    msgEl.innerHTML = message;

    const okBtn = modal.querySelector('#confirmOkBtn');
    okBtn.textContent = okText;

    const newOkBtn = okBtn.cloneNode(true);
    okBtn.parentNode.replaceChild(newOkBtn, okBtn);
    newOkBtn.addEventListener('click', () => {
        if (typeof onConfirm === 'function') onConfirm();
        closeModal('confirmModal');
    });

    openModal('confirmModal');
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Check for unread messages and show notification dot
async function checkUnreadMessages() {
    try {
        const response = await fetch('/backend/api/chat.php?action=threads', {
            credentials: 'same-origin'
        });

        if (!response.ok) return;

        const result = await response.json();

        if (result.success && result.data) {
            const hasUnread = result.data.some(t => t.has_unanswered);

            const chatNav = document.querySelector('.nav-item[data-section="chat"]');
            if (chatNav) {
                let dot = chatNav.querySelector('.rt-notification-dot');

                if (hasUnread && !dot) {
                    dot = document.createElement('span');
                    dot.className = 'rt-notification-dot';
                    chatNav.appendChild(dot);
                } else if (!hasUnread && dot) {
                    dot.remove();
                }
            }
        }
    } catch (error) {
        console.error('Failed to check unread messages:', error);
    }
}

// Check every 10 seconds
if (document.querySelector('.nav-item[data-section="chat"]')) {
    setInterval(checkUnreadMessages, 10000);
    checkUnreadMessages(); // Initial check
}
// ============================================================================
// FEEDBACK MANAGEMENT
// ============================================================================

function initializeFeedbackManagement() {
    const feedbackNavItem = document.querySelector('[data-section="feedback"]');
    if (feedbackNavItem) {
        feedbackNavItem.addEventListener('click', function () {
            setTimeout(() => loadFeedback(), 100);
        });
    }

    const currentSection = document.querySelector('.nav-item.active');
    if (currentSection && currentSection.dataset.section === 'feedback') {
        loadFeedback();
    }
}

async function loadFeedback() {
    const tbody = document.getElementById('feedbackTableBody');
    if (!tbody) return;

    try {
        const resp = await fetch('/backend/api/feedback/admin_list.php', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        // log status
        console.log('loadFeedback: HTTP', resp.status, resp.statusText);

        // always read raw text so we can see server error output
        const raw = await resp.text();
        console.log('loadFeedback: raw response:', raw.slice(0, 2000)); // show up to 2000 chars

        // try parse JSON if applicable
        let json = null;
        try {
            json = JSON.parse(raw);
        } catch (err) {
            console.warn('loadFeedback: JSON parse failed', err);
        }

        if (!resp.ok) {
            // resp is not OK (e.g. 500). show error with body preview.
            throw new Error(`Server returned HTTP ${resp.status}: ${raw.slice(0, 200)}`);
        }

        if (!json || !json.success) {
            // either invalid JSON or success:false
            const msg = (json && json.message) ? json.message : 'Invalid or missing JSON response';
            throw new Error(msg + (raw ? ` — raw: ${raw.slice(0, 200)}` : ''));
        }

        displayFeedback(json.data || []);

    } catch (error) {
        console.error('Error loading feedback:', error);
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#999;">Error loading feedback</td></tr>';
        // optional: showNotification('Failed to load feedback: ' + error.message, 'error');
    }
}


function displayFeedback(feedbackList) {
    const tbody = document.getElementById('feedbackTableBody');
    if (!tbody) return;

    // If empty
    if (!feedbackList || feedbackList.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#666;">No feedback found</td></tr>';
        return;
    }

    tbody.innerHTML = feedbackList.map(feedback => {
        const rating = parseInt(feedback.rating || 0, 10);
        const stars = '★'.repeat(rating) + '☆'.repeat(Math.max(0, 5 - rating));

        // Keep a small excerpt for comment
        const comment = feedback.comment ? String(feedback.comment) : 'No comment';

        return `
            <tr data-feedback-id="${feedback.id}">
                <td>${feedback.id}</td>
                <td>${escapeHtml(feedback.customer_name)}</td>
                <td style="color: #fbbf24; font-size: 1.2rem;">${stars}</td>
                <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(comment)}">${escapeHtml(comment)}</td>
                <td>${escapeHtml(feedback.order_code)}</td>
                <td>${formatDate(feedback.created_at)}</td>
            </tr>
        `;
    }).join('');
}

/*async function releaseFeedback(feedbackId) {
    try {
        const response = await fetch('/backend/api/feedback/release.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                action: 'release',
                feedback_id: feedbackId
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Feedback released to public testimonials!', 'success');
            loadFeedback();
        } else {
            showNotification(result.message || 'Failed to release feedback', 'error');
        }
    } catch (error) {
        console.error('Error releasing feedback:', error);
        showNotification('Failed to release feedback', 'error');
    }
}

async function hideFeedback(feedbackId) {
    showConfirm({
        title: 'Hide Feedback',
        message: 'Remove this feedback from public testimonials?',
        okText: 'Hide',
        onConfirm: async () => {
            try {
                const response = await fetch('/backend/api/feedback/release.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'hide',
                        feedback_id: feedbackId
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showNotification('Feedback hidden from public', 'success');
                    loadFeedback();
                } else {
                    showNotification(result.message || 'Failed to hide feedback', 'error');
                }
            } catch (error) {
                console.error('Error hiding feedback:', error);
                showNotification('Failed to hide feedback', 'error');
            }
        }
    });
}
console.log('Enhanced Admin Dashboard Script Loaded Successfully!'); 

// admin release/hide click handlers (use event delegation)
document.addEventListener('click', function (e) {
    const rel = e.target.closest('.btn-release-feedback');
    if (rel) {
        e.preventDefault();
        const id = rel.getAttribute('data-id');
        if (id && confirm('Release this feedback to public?')) releaseFeedback(parseInt(id,10));
        return;
    }
    const hid = e.target.closest('.btn-hide-feedback');
    if (hid) {
        e.preventDefault();
        const id = hid.getAttribute('data-id');
        if (id && confirm('Hide this feedback from public?')) hideFeedback(parseInt(id,10));
        return;
    }
});*/


// ============================================================================
// PAYMENT VERIFICATION
// ============================================================================

function initializePaymentVerfication() {
    const paymentNavItem = document.querySelector('[data-section="payment"]');
    if (paymentNavItem) {
        paymentNavItem.addEventListener('click', function () {
            setTimeout(() => loadPaymentVerifications(), 100);
        });
    }

    // Note: order-search ID is reused in payment section - consider renaming in HTML
    const orderSearch = document.getElementById('order-search');
    if (orderSearch) {
        let searchTimeout;
        orderSearch.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            const activeSection = document.querySelector('.nav-item.active');
            if (activeSection && activeSection.dataset.section === 'payment') {
                searchTimeout = setTimeout(() => {
                    loadPaymentVerifications();
                }, 300);
            }
        });
    }

    const paymentFilter = document.getElementById('paymentFilter');
    if (paymentFilter) {
        paymentFilter.addEventListener('change', () => {
            const activeSection = document.querySelector('.nav-item.active');
            if (activeSection && activeSection.dataset.section === 'payment') {
                loadPaymentVerifications();
            }
        });
    }

    const currentSection = document.querySelector('.nav-item.active');
    if (currentSection && currentSection.dataset.section === 'payment') {
        loadPaymentVerifications();
    }
}

async function loadPaymentVerifications() {
    const searchValue = document.getElementById('order-search')?.value || '';
    const statusFilter = document.getElementById('paymentFilter')?.value || '';

    const tbody = document.getElementById('paymentsTableBody');

    if (!tbody) {
        console.log('Payment table not found - skipping payment verification load');
        return;
    }

    try {
        // Build URL with filters
        let url = '/backend/api/payment_verification.php?action=list';
        if (searchValue) url += '&search=' + encodeURIComponent(searchValue);
        if (statusFilter) url += '&payment_status=' + encodeURIComponent(statusFilter);


        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        if (!response.ok) {
            throw new Error('Failed to load payment verifications');
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to load payments');
        }

        displayPaymentVerifications(result.data || []);

    } catch (error) {
        console.error('Error loading payment verifications:', error);
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#999;">No payment verifications found</td></tr>';
    }
}

function displayPaymentVerifications(verifications) {
    const tbody = document.getElementById('paymentsTableBody');
    if (!tbody) return;

    if (!verifications || verifications.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#666;">No payment verifications found</td></tr>';
        return;
    }

    tbody.innerHTML = verifications.map(payment => {
        const statusClass = payment.status === 'PENDING' ? 'pending' :
            payment.status === 'APPROVED' ? 'completed' : 'cancelled';
        const statusText = payment.status.charAt(0) + payment.status.slice(1).toLowerCase();

        return `
            <tr data-payment-id="${payment.id}">
                <td>${escapeHtml(payment.order_code)}</td>
                <td>${escapeHtml(payment.customer_name)}</td>
                <td>₱${parseFloat(payment.amount_reported || 0).toLocaleString()}</td>
                <td>${escapeHtml(payment.method.toUpperCase())}</td>
                <td><span class="badge badge-${statusClass}">${statusText}</span></td>
                <td>${formatDate(payment.created_at)}</td>
                <td>
                    <button class="btn-action btn-view" onclick="viewPaymentDetails(${payment.id})" title="View Details">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}
async function viewPaymentDetails(verificationId) {
    // --- helpers (local) ---
    const money = v => '₱' + (Number(v || 0)).toLocaleString();
    const pct = v => (v === null || v === undefined || v === '') ? 'N/A' : (Number(v) + '%');
    const escapeHtml = s => String(s ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    const text = v => (v === null || v === undefined || v === '') ? 'N/A' : escapeHtml(String(v));
    const formatDate = d => {
        try {
            const dt = new Date(d);
            if (Number.isNaN(dt.getTime())) return text(d);
            return dt.toLocaleString();
        } catch (e) { return text(d); }
    };

    try {
        // fetch
        const response = await fetch(`/backend/api/payment_verification.php?action=details&id=${verificationId}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        // read text first so we can show meaningful errors
        const raw = await response.text();
        if (!response.ok) {
            let bodyMsg = raw;
            try { const parsed = JSON.parse(raw); bodyMsg = parsed.message || JSON.stringify(parsed); } catch (e) { }
            throw new Error(`Server returned ${response.status}: ${bodyMsg}`);
        }

        let result;
        try { result = JSON.parse(raw); } catch (e) { throw new Error('Invalid JSON response from server'); }
        if (!result.success) throw new Error(result.message || 'Failed to load payment details');

        const payment = result.data || {};
        const content = document.getElementById('paymentDetailsContent');
        if (!content) throw new Error('Modal content container not found');

        // --- normalize proof src (make absolute if relative) ---
        let proofSrc = payment.screenshot_path || payment.proof_path || payment.image || '';
        if (proofSrc) {
            if (!/^https?:\/\//i.test(proofSrc) && !proofSrc.startsWith('/')) {
                // prefix with project base - change '/' if different
                proofSrc = window.location.origin + '/' + proofSrc.replace(/^(\.\/|\/)+/, '');
            } else if (proofSrc.startsWith('/')) {
                proofSrc = window.location.origin + proofSrc;
            }
        }

        // --- build full address HTML (ordered, labeled, de-duplicated) ---
        const addressCandidates = [
            // order shipping variants (common aliases)
            payment.order_shipping_street, payment.order_shipping_barangay, payment.order_shipping_city, payment.order_shipping_province, payment.order_shipping_postal_code,
            payment.shipping_street, payment.shipping_barangay, payment.shipping_city, payment.shipping_province, payment.shipping_postal_code,

            // order address common
            payment.order_street, payment.order_barangay, payment.order_city, payment.order_province, payment.order_postal_code,
            payment.street, payment.barangay, payment.city, payment.province, payment.postal_code, payment.zip,

            // customer address fallback fields
            payment.customer_street, payment.customer_barangay, payment.customer_city, payment.customer_province, payment.customer_postal_code,
            payment.customer_address, payment.address
        ];

        // Friendly labeled mapping when possible (key names may not be present in all responses)
        // We'll create labeled lines for the first occurrences of street/barangay/city/province/postal in that order
        const labelOrder = [
            { keys: ['order_shipping_street', 'shipping_street', 'order_street', 'street', 'customer_street'], label: 'Street' },
            { keys: ['order_shipping_barangay', 'shipping_barangay', 'order_barangay', 'barangay', 'customer_barangay'], label: 'Barangay' },
            { keys: ['order_shipping_city', 'shipping_city', 'order_city', 'city', 'customer_city'], label: 'City' },
            { keys: ['order_shipping_province', 'shipping_province', 'order_province', 'province', 'customer_province'], label: 'Province' },
            { keys: ['order_shipping_postal_code', 'shipping_postal_code', 'order_postal_code', 'postal_code', 'zip', 'customer_postal_code'], label: 'Postal' }
        ];

        // Build lines: prefer specific fields if present in payment object; also collect any combined address fallback
        const seen = new Set();
        const addrLines = [];

        // add labeled lines by scanning labelOrder keys for the first non-empty value
        for (const item of labelOrder) {
            for (const k of item.keys) {
                if (Object.prototype.hasOwnProperty.call(payment, k) && payment[k]) {
                    const val = String(payment[k]).trim();
                    if (val && !seen.has(val)) {
                        seen.add(val);
                        addrLines.push(`${item.label}: ${escapeHtml(val)}`);
                        break;
                    }
                }
            }
        }

        // also include any combined address string if present and not duplicate
        const combinedCandidates = [payment.address, payment.customer_address];
        for (const c of combinedCandidates) {
            if (c) {
                const s = String(c).trim();
                if (s && !seen.has(s)) {
                    seen.add(s);
                    addrLines.push(escapeHtml(s)); // no label - combined line
                }
            }
        }

        const addressHtml = addrLines.length ? addrLines.join('<br>') : 'N/A';

        // --- build image html with onerror fallback ---
        const imgHtml = proofSrc
            ? `<img id="pv-proof-img" src="${escapeHtml(proofSrc)}" alt="payment proof" style="max-width:100%;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,0.08);" onerror="this.style.display='none'; this.insertAdjacentHTML('afterend','<div style=\\'color:#6b7280;\\'>Payment proof not available</div>');">`
            : `<div style="color:#6b7280;">Payment proof not available</div>`;

         // --- render content with improved layout: left details, right proof ---
        content.innerHTML = `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
        <!-- LEFT SIDE: Order & Customer Info -->
        <div>
          <h3 style="margin-bottom:.75rem;color:var(--brand);font-size:1.1rem;">Order Information</h3>
          <p style="margin:.5rem 0;"><strong>Order Code:</strong> ${text(payment.order_code)}</p>
          <p style="margin:.5rem 0;"><strong>Order Date:</strong> ${payment.order_date ? formatDate(payment.order_date) : 'N/A'}</p>
          <p style="margin:.5rem 0;"><strong>Total Amount:</strong> ${money(payment.total_amount)}</p>
          <p style="margin:.5rem 0;"><strong>Items:</strong> ${escapeHtml(payment.items || payment.order_name || 'N/A')}</p>
          <p style="margin:.5rem 0;"><strong>Delivery Mode:</strong> ${escapeHtml(payment.delivery_mode || 'N/A')}</p>
          <h3 style="margin:1.5rem 0 .75rem 0;color:var(--brand);font-size:1.1rem;">Customer Information</h3>
          <p style="margin:.5rem 0;"><strong>Name:</strong> ${text(payment.customer_name || payment.full_name || payment.name)}</p>
          <p style="margin:.5rem 0;"><strong>Email:</strong> ${text(payment.customer_email || payment.email)}</p>
          <p style="margin:.5rem 0;"><strong>Phone:</strong> ${text(payment.customer_phone || payment.phone)}</p>
          <p style="margin:.5rem 0;"><strong>Address:</strong><br>${addressHtml}</p>
        </div>

        <!-- RIGHT SIDE: Proof of Payment -->
        <div>
          <h3 style="margin-bottom:.75rem;color:var(--brand);font-size:1.1rem;">Proof of Payment</h3>
          <div style="background:#f9fafb;border:2px solid #e5e7eb;border-radius:8px;padding:1rem;min-height:300px;display:flex;align-items:center;justify-content:center;">
            ${imgHtml}
          </div>
        </div>
      </div>

      <div style="background:#f7fafc;padding:1.25rem;border-radius:8px;margin-top:1.5rem;">
        <h4 style="margin:0 0 1rem 0;color:var(--brand);font-size:1.1rem;">Payment Details</h4>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
          <p style="margin:.25rem 0;"><strong>Payment Method:</strong> ${text((payment.method || '').toUpperCase())}</p>
          <p style="margin:.25rem 0;"><strong>Account Name:</strong> ${text(payment.account_name)}</p>
          <p style="margin:.25rem 0;"><strong>Account Number:</strong> ${text(payment.account_number)}</p>
          <p style="margin:.25rem 0;"><strong>Reference Number:</strong> ${text(payment.reference_number)}</p>
          <p style="margin:.25rem 0;"><strong>Amount Reported:</strong> ${money(payment.amount_reported || payment.amount_paid)}</p>
          <p style="margin:.25rem 0;"><strong>Deposit Rate:</strong> ${pct(payment.deposit_rate)}</p>
          <p style="margin:.25rem 0;"><strong>Amount Due:</strong> ${money(payment.amount_due ?? (payment.total_amount - (payment.amount_paid || 0)))}</p>
          <p style="margin:.25rem 0;"><strong>Status:</strong> <span class="badge badge-${(payment.status || 'PENDING').toLowerCase()}">${escapeHtml(payment.status || 'PENDING')}</span></p>
        </div>
      </div>
    `;

        // add click-to-open for proof image if present
        setTimeout(() => {
            const proofImg = document.getElementById('pv-proof-img');
            if (proofImg) {
                proofImg.style.cursor = 'zoom-in';
                proofImg.addEventListener('click', () => {
                    try { window.open(proofImg.src, '_blank'); } catch (e) { }
                });
            }
        }, 50);

        // --- approve/reject button wiring + labels ---
        const approveBtn = document.getElementById('btnApprovePayment');
        const rejectBtn = document.getElementById('btnRejectPayment');
        const status = (payment.status || '').toUpperCase();
        const hasProof = !!proofSrc;

        if (approveBtn && rejectBtn) {
            // Remove any previous event listeners by cloning
            const newApproveBtn = approveBtn.cloneNode(true);
            const newRejectBtn = rejectBtn.cloneNode(true);
            approveBtn.replaceWith(newApproveBtn);
            rejectBtn.replaceWith(newRejectBtn);


            // Set button text based on status
            if (status === 'APPROVED') {
                newApproveBtn.textContent = 'Re-approve';
                newRejectBtn.textContent = 'Reject (change)';


            } else if (status === 'REJECTED') {
                newApproveBtn.textContent = 'Approve (change)';
                newRejectBtn.textContent = 'Re-reject';

            } else {
                newApproveBtn.textContent = 'Approve Payment';
                newRejectBtn.textContent = 'Reject Payment';
            }

            // Always enable buttons (no restrictions)
            newApproveBtn.disabled = false;
            newApproveBtn.title = '';
            newApproveBtn.style.opacity = '1';
            newApproveBtn.style.cursor = 'pointer';

            newApproveBtn.style.display = 'inline-block';
            newRejectBtn.style.display = 'inline-block';


            if (status === 'APPROVED') newApproveBtn.classList.add('muted'); else newApproveBtn.classList.remove('muted');
            if (status === 'REJECTED') newRejectBtn.classList.add('muted'); else newRejectBtn.classList.remove('muted');

            // Store verification ID in button dataset for approval modal
            newApproveBtn.dataset.verificationId = verificationId;
            newRejectBtn.dataset.verificationId = verificationId;

            newApproveBtn.addEventListener('click', () => {
                // Open approval confirmation modal
                const confirmBtn = document.getElementById('btnConfirmApprove');
                if (confirmBtn) {
                    confirmBtn.dataset.verificationId = verificationId;
                }
                openModal('approvePaymentModal');
            });

            newRejectBtn.addEventListener('click', () => {
                // Open reject modal instead of prompt
                document.getElementById('btnConfirmReject').dataset.verificationId = verificationId;
                document.getElementById('rejectReason').value = '';
                openModal('rejectReasonModal');
            });
        }

        openModal('paymentDetailsModal');
    } catch (error) {
        console.error('Error viewing payment details:', error);
        showNotification('Failed to load payment details: ' + (error.message || error), 'error');
    }
}



// Approve payment (robust fetch + error handling)
async function approvePayment(verificationId) {
    try {
        const response = await fetch('/backend/api/payment_verification.php?action=approve', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ id: verificationId })
        });

        const text = await response.text(); // always read raw text first
        if (!response.ok) {
            // show raw server message when status != 2xx (helps debug 500)
            throw new Error(`Server returned ${response.status}: ${text || response.statusText}`);
        }

        // try parse JSON, else throw
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            throw new Error('Invalid JSON response from server: ' + text.slice(0, 300));
        }
		
        if (result.success) {
            // Close payment details modal
            closeModal('paymentDetailsModal');

            // Display success modal with order details
            const order = result.data?.order;
            const successTitle = document.getElementById('successTitle');
            const successMessage = document.getElementById('successMessage');
            const successDetails = document.getElementById('successDetails');

            if (successTitle) successTitle.textContent = 'Payment Approved Successfully!';
            if (successMessage) successMessage.textContent = result.message || 'The payment has been approved and the order has been updated.';

            if (successDetails && order) {
                const totalAmount = parseFloat(order.total_amount || 0);
                const amountPaid = parseFloat(order.amount_paid || 0);
                const remainingBalance = parseFloat(order.remaining_balance || 0);
                const paymentStatus = order.payment_status || 'Pending';
                const orderStatus = order.status || 'Pending';

                successDetails.innerHTML = `
                    <p style="margin: 0.5rem 0;"><strong>Order Status:</strong> ${orderStatus}</p>
                    <p style="margin: 0.5rem 0;"><strong>Payment Status:</strong> <span style="color: ${paymentStatus === 'Fully Paid' ? '#10b981' : '#f59e0b'};">${paymentStatus}</span></p>
                    <p style="margin: 0.5rem 0;"><strong>Total Amount:</strong> ₱${totalAmount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                    <p style="margin: 0.5rem 0;"><strong>Amount Paid:</strong> ₱${amountPaid.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                    <p style="margin: 0.5rem 0;"><strong>Remaining Balance:</strong> <span style="color: ${remainingBalance === 0 ? '#10b981' : '#ef4444'};">₱${remainingBalance.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span></p>
                `;
            }
            openModal('successModal');

            // Reload both payment verifications and orders lists
            loadPaymentVerifications();
            if (typeof loadOrders === 'function') {
                loadOrders();
            }
        } else {
            showNotification(result.message || 'Failed to approve payment', 'error');
        }

    } catch (error) {
        console.error('Error approving payment:', error);
        showNotification('Failed to approve payment: ' + error.message, 'error');
    }
}

// Reject payment (with reason) — robust fetch + error handling
async function rejectPayment(verificationId, reason) {
    try {
        const response = await fetch('/backend/api/payment_verification.php?action=reject', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ id: verificationId, reason })
        });

        const text = await response.text();

        if (!response.ok) {
            // Throw with server body so catch displays meaningful message
            throw new Error(`Server returned ${response.status}: ${text || response.statusText}`);
        }

        let result;
        try { result = JSON.parse(text); }
        catch (e) { throw new Error('Invalid JSON response from server: ' + text.slice(0, 300)); }

        if (result.success) {
            // Close modals
            closeModal('rejectReasonModal');
            closeModal('paymentDetailsModal');

            // Display success modal
            const successTitle = document.getElementById('successTitle');
            const successMessage = document.getElementById('successMessage');
            const successDetails = document.getElementById('successDetails');

            if (successTitle) successTitle.textContent = 'Payment Rejected';
            if (successMessage) successMessage.textContent = result.message || 'The payment has been rejected successfully.';
            if (successDetails) successDetails.innerHTML = '';

            openModal('successModal');

            // Reload both payment verifications and orders lists
            loadPaymentVerifications();
            if (typeof loadOrders === 'function') {
                loadOrders();
            }
        } else {
            showNotification(result.message || 'Failed to reject payment', 'error');
        }

    } catch (error) {
        console.error('Error rejecting payment:', error);
        // show the actual error message (includes server body when non-2xx)
        showNotification('Failed to reject payment: ' + error.message, 'error');
    }
}


// Event listeners for payment actions
document.getElementById('btnApprovePayment')?.addEventListener('click', function () {
    const verificationId = this.dataset.verificationId;
    if (!verificationId) return;

    const confirmBtn = document.getElementById('btnConfirmApprove');
    if (confirmBtn) {
        confirmBtn.dataset.verificationId = verificationId;
    }

    // Open custom approval modal instead of browser confirm
    openModal('approvePaymentModal');
});

document.getElementById('btnRejectPayment')?.addEventListener('click', function () {
    const verificationId = this.dataset.verificationId;
    if (!verificationId) return;

    document.getElementById('btnConfirmReject').dataset.verificationId = verificationId;
    openModal('rejectReasonModal');
});

document.getElementById('btnConfirmReject')?.addEventListener('click', function () {
    const verificationId = this.dataset.verificationId;
    const reason = document.getElementById('rejectReason').value.trim();

    if (!reason) {
        showNotification('Please provide a reason for rejection', 'error');
        return;
    }

    rejectPayment(parseInt(verificationId), reason);
});

document.getElementById('btnConfirmApprove')?.addEventListener('click', async function () {
    const verificationId = this.dataset.verificationId;
    if (!verificationId) return;
    closeModal('approvePaymentModal');
    await approvePayment(parseInt(verificationId));
});

// Customer-delete feedback handler (attach once)
document.addEventListener('click', async function (e) {
    const btn = e.target.closest('.btn-delete-own-feedback');
    if (!btn) return;
    e.preventDefault();

    const id = btn.getAttribute('data-id');
    if (!id) return;

    // Optional: ensure the button belongs to logged-in customer, using window.CURRENT_CUSTOMER_ID
    // (This is only a UI check; server enforces permissions)
    const ownerId = btn.getAttribute('data-owner-id'); // if you rendered owner id in DOM
    if (typeof window.CURRENT_CUSTOMER_ID !== 'undefined' && ownerId && String(window.CURRENT_CUSTOMER_ID) !== String(ownerId)) {
        alert('You can only delete your own feedback.');
        return;
    }

    if (!confirm('Are you sure you want to delete this feedback?')) return;

    try {
        const resp = await fetch('/backend/api/feedback/delete.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ feedback_id: parseInt(id, 10) })
        });

        const json = await resp.json();

        if (!resp.ok || !json.success) {
            alert(json.message || 'Failed to delete feedback');
            return;
        }

        // Remove UI element (card or row)
        // Try row first (admin table), then card
        let el = document.querySelector(`[data-feedback-id="${id}"]`);
        if (!el) {
            // maybe button inside a card: closest .feedback-card
            el = btn.closest('.feedback-card');
        }
        if (el) el.remove();

        // optional visual feedback
        if (typeof showNotification === 'function') {
            showNotification('Feedback deleted', 'success');
        } else {
            alert('Feedback deleted');
        }

    } catch (err) {
        console.error('Delete request failed', err);
        alert('Failed to delete feedback. Please try again.');
    }
});