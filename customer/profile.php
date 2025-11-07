<?php
session_start();
if (empty($_SESSION['user']) || ($_SESSION['user']['aud'] ?? '') !== 'customer') {
    header('Location: /RADS-TOOLING/customer/login.php');
    exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - RADS Tooling</title>
    <link rel="stylesheet" href="/RADS-TOOLING/assets/css/profile.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap">
</head>

<body>
<!-- Back Button -->
    <div class="back-button-container">
        <button class="back-button" onclick="goBack()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Back
        </button>
    </div>

    <!-- Main Container -->
    <div class="profile-wrapper">
        <div class="profile-layout">
            <!-- Sidebar -->
            <aside class="profile-sidebar">
                <div class="sidebar-profile">
                    <div class="sidebar-avatar" id="sidebar-avatar"></div>
                    <div class="sidebar-info">
                        <div class="sidebar-name" id="sidebar-name"></div>
                        <a href="#" class="sidebar-edit">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                            Edit Profile
                        </a>
                    </div>
                </div>

                <nav class="sidebar-menu">
                    <div class="menu-section">
                        <div class="menu-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            My Account
                        </div>
                        <a href="#profile" class="menu-item active" data-tab="profile">Profile</a>
                        <a href="#address" class="menu-item" data-tab="address">Address</a>
                        <a href="#password" class="menu-item" data-tab="password">Change Password</a>
                    </div>
                </nav>
            </aside>

            <!-- Main Content Area -->
            <main class="profile-content">
                <!-- Loading State -->
                <div id="loading" class="loading-state" style="display: none;">
                    <div class="spinner"></div>
                    <p>Loading...</p>
                </div>

                <!-- Message Container -->
                <div id="message-container"></div>

                <!-- Profile Tab Content -->
                <div id="profile-tab" class="tab-content active">
                    <div class="content-header">
                        <h2>My Profile</h2>
                        <p>Manage and protect your account</p>
                    </div>

                    <div class="content-body">
                        <form id="profile-form" class="profile-form" onsubmit="updateProfile(event)">
                            <div class="form-split">
                                <div class="form-section">
                                    <div class="form-group">
                                        <label>Username</label>
                                        <input type="text" id="username" disabled>
                                    </div>

                                    <div class="form-group">
                                        <label>Full Name</label>
                                        <input type="text" id="full_name" name="full_name" required>
                                    </div>

                                    <div class="form-group">
                                        <label>Email</label>
                                        <input type="email" id="email" disabled>
                                    </div>

                                    <div class="form-group">
                                        <label>Phone Number</label>
                                        <div class="phone-group">
                                            <span class="phone-prefix">+63</span>
                                            <input type="tel" id="phoneLocal" inputmode="numeric" maxlength="10" placeholder="9123456789">
                                            <!-- keep hidden for compatibility -->
                                            <input type="hidden" id="phone" name="phone">
                                        </div>
                                    </div>


                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-primary" id="save-btn">
                                            <span class="btn-text">Save Changes</span>
                                            <span class="btn-spinner" style="display: none;">
                                                <div class="mini-spinner"></div>
                                            </span>
                                        </button>
                                    </div>
                                </div>

                                <div class="form-divider"></div>

                                <div class="avatar-section">
                                    <div class="avatar-upload">
                                        <div class="avatar-preview" id="avatar-preview"></div>
                                        <button type="button" class="btn btn-outline" onclick="document.getElementById('profile_image_input').click()">
                                            Select Image
                                        </button>
                                        <input type="file" id="profile_image_input" accept="image/jpeg,image/png,image/jpg" style="display: none;" onchange="uploadProfileImage(event)">
                                        <p class="upload-hint">Maximum file size: 5MB<br>Format: JPG, PNG</p>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Address Tab Content -->
                <div id="address-tab" class="tab-content">
                    <div class="content-header">
                        <h2>My Addresses</h2>
                        <p>Manage your delivery addresses</p>
                        <button class="btn btn-primary" onclick="showAddressForm()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Add New Address
                        </button>
                    </div>
                    <div class="content-body">
                        <!-- Address List -->
                        <div id="address-list-container">
                            <div id="address-list-loading" class="loading-state" style="display: none;">
                               <div class="spinner"></div>
                                <p>Loading addresses...</p>
                            </div>
                            <div id="address-list"></div>
                            <div id="no-addresses" style="display: none; text-align: center; padding: 40px; color: #999;">
                                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 16px;">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                                <p>No saved addresses yet</p>
                                <button class="btn btn-outline" onclick="showAddressForm()" style="margin-top: 16px;">Add Your First Address</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Password Tab Content -->
                <div id="password-tab" class="tab-content">
                    <div class="content-header">
                        <h2>Change Password</h2>
                        <p>You can change your password while logged in, or use an email code if you forgot it.</p>
                    </div>

                    <div class="content-body">
                        <!-- Logged-in change form -->
                        <form id="pwChangeForm" class="profile-form">
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" id="curr_pw" required autocomplete="current-password">
                            </div>

                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" id="new_pw" minlength="8" required autocomplete="new-password">
                            </div>

                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" id="new_pw2" minlength="8" required autocomplete="new-password">
                            </div>

                            <div class="form-actions">
                                <button class="btn btn-primary" id="pwSaveBtn">
                                    <span class="btn-text">Update Password</span>
                                    <span class="btn-spinner" style="display:none">
                                        <div class="mini-spinner"></div>
                                    </span>
                                </button>
                            </div>
                        </form>

                        <div class="form-divider" style="margin:20px 0;"></div>

                        <!-- Forgot password shortcut -->
                        <div>
                            <p><strong>Forgot your password?</strong></p>
                            <a href="#" class="btn btn-outline" id="btnOpenPwReq">Request Password Reset (via email)</a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Profile Tab (existing content) -->
    <div id="profileTab" class="tab-content active">
        <!-- Your existing profile form content -->
    </div>

    <style>
        .profile-tabs {
            display: flex;
            gap: 0.5rem;
            border-bottom: 2px solid #e3e3e3;
        }

        .tab-btn {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-weight: 500;
            color: #666;
            transition: all 0.2s;
        }

        .tab-btn.active {
            color: #2f5b88;
            border-bottom-color: #2f5b88;
        }

        .tab-btn:hover {
            color: #2f5b88;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .orders-filter {
            display: flex;
            gap: 0.5rem;
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            background: #f5f7fa;
            border: 1px solid #e3e3e3;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .filter-btn.active {
            background: #2f5b88;
            color: white;
            border-color: #2f5b88;
        }

        .filter-btn:hover {
            background: #e3edfb;
        }

        .order-card {
            background: white;
            border: 1px solid #e3e3e3;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: box-shadow 0.2s;
        }

        .order-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e3e3e3;
        }

        .order-code {
            font-weight: 600;
            color: #2f5b88;
            font-size: 1.1rem;
        }

        .order-date {
            color: #666;
            font-size: 0.9rem;
        }

        .order-body {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1rem;
        }

        .order-items {
            color: #333;
        }

        .order-summary {
            text-align: right;
        }

        .order-total {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2f5b88;
            margin: 0.5rem 0;
        }

        .order-footer {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e3e3e3;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .status-badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-processing {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .payment-status {
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .payment-verified {
            color: #28a745;
        }

        .payment-pending {
            color: #ffc107;
        }

        .payment-rejected {
            color: #dc3545;
        }

        .btn-view-order {
            padding: 0.5rem 1rem;
            background: #2f5b88;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.2s;
        }

        .btn-view-order:hover {
            background: #17416d;
        }

        .order-timeline {
            margin-top: 1.5rem;
            padding: 1rem;
            background: #f7fafc;
            border-radius: 8px;
        }

        .timeline-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.75rem;
            position: relative;
        }

        .timeline-item:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 8px;
            top: 24px;
            width: 2px;
            height: calc(100% + 0.75rem);
            background: #e3e3e3;
        }

        .timeline-icon {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #2f5b88;
            flex-shrink: 0;
            z-index: 1;
        }

        .timeline-icon.inactive {
            background: #e3e3e3;
        }

        .timeline-text {
            font-size: 0.9rem;
        }

        .timeline-text strong {
            color: #2f5b88;
        }
        
         /* Back Button Styles */
        .back-button-container {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
        }

        .back-button {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: white;
            border: 1px solid #e3e3e3;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 500;
            color: #333;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .back-button:hover { 
            background: #f8f9fa;
            border-color: #2f5b88;
            color: #2f5b88;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .back-button svg {
            flex-shrink: 0;
        }

        @media (max-width: 768px) {
            .back-button-container {
                top: 10px;
                left: 10px;
            }

            .back-button {
                padding: 8px 12px;
                font-size: 0.9rem;
            }
        }

        /* Address Card Styles */
        .address-card {
            background: white;
            border: 1px solid #e3e3e3;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: box-shadow 0.2s, border-color 0.2s;
        }

        .address-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-color: #2f5b88;
        }

        .address-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #f0f0f0;
        }

        .address-header-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .address-header-left strong {
            color: #2f5b88;
            font-size: 1rem;
        }

        .address-actions {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            padding: 0;
            background: transparent;
            border: 1px solid #e3e3e3;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-icon:hover {
            background: #f0f7ff;
            border-color: #2f5b88;
        }

        .btn-icon:hover svg {
            stroke: #2f5b88;
        }

        .btn-icon.btn-delete:hover {
            background: #fee;
            border-color: #dc3545;
        }

        .btn-icon.btn-delete:hover svg {
            stroke: #dc3545;
        }

        .address-card-body p {
            margin: 0.5rem 0;
            line-height: 1.6;
        }

        .address-card-body .address-text {
            color: #666;
            font-size: 0.95rem;
            margin-top: 0.75rem;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-primary {
            background: #2f5b88;
            color: white;
        }

        /* Address List Container */
        #address-list {
            display: grid;
            gap: 1rem;
        }

        /* Modal Styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 20px;
        }

        .modal.hidden {
            display: none;
        }

        .modal-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 500px;
            width: 100%;
            max-height: 85vh;
            overflow-y: auto;
        }

        .modal-card h3 {
            margin: 0 0 1rem 0;
            color: #2f5b88;
        }

        .modal-card .form-group {
            margin-bottom: 1rem;
        }

        .modal-card label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        .modal-card input,
        .modal-card select,
        .modal-card textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e3e3e3;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }

        .modal-card input:focus,
        .modal-card select:focus,
        .modal-card textarea:focus {
            outline: none;
            border-color: #2f5b88;
        }

        .modal-card .phone-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-card .phone-prefix {
            padding: 0.75rem 1rem;
            background: #f5f5f5;
            border: 1px solid #e3e3e3;
            border-radius: 6px;
            font-weight: 500;
            color: #666;
        }

        .modal-card .phone-group input {
            flex: 1;
        }

        .modal-card .row {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .modal-card .msg {
            margin-top: 1rem;
            padding: 0.75rem;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .modal-card .msg.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .modal-card .msg.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .content-header button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        @media (max-width: 768px) {
            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .content-header button {
                width: 100%;
                justify-content: center;
            }

            .address-card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .address-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>

    <script>
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tabName = this.dataset.tab;

                // Update active tab button
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                // Update active content
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.style.display = 'none';
                    content.classList.remove('active');
                });

                const targetTab = document.getElementById(tabName + 'Tab');
                if (targetTab) {
                    targetTab.style.display = 'block';
                    targetTab.classList.add('active');
                }

                // Load orders when orders tab is clicked
                if (tabName === 'orders') {
                    loadCustomerOrders();
                }
            });
        });
    </script>
    <script>
        const API_BASE = '/RADS-TOOLING/backend/api';
        const CSRF_TOKEN = <?php echo json_encode($CSRF); ?>;

        let customerData = null;

        // Tab switching
        document.querySelectorAll('[data-tab]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const tabName = link.dataset.tab;
                switchTab(tabName);
            });
        });

        function switchTab(tabName) {
            // Update active menu item
            document.querySelectorAll('.menu-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');

            // Update active content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(`${tabName}-tab`).classList.add('active');
        }

        function showLoading() {
            document.getElementById('loading').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loading').style.display = 'none';
        }

        function showMessage(message, type = 'success') {
            const container = document.getElementById('message-container');
            const icon = type === 'success' ?
                '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>' :
                '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>';

            container.innerHTML = `
                <div class="alert alert-${type}">
                    ${icon}
                    ${message}
                </div>
            `;

            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);

            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        async function loadProfile() {
            showLoading();
            try {
                const res = await fetch(`${API_BASE}/customer_profile.php`, {
                    credentials: 'include',
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                const text = await res.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch {
                    console.error('Non-JSON response from customer_profile.php:', text.slice(0, 200));
                    throw new Error('Failed to load profile');
                }

                if (!res.ok || !result.success) {
                    if (result && result.redirect) {
                        window.location.href = result.redirect;
                        return;
                    }
                    throw new Error(result?.message || `HTTP ${res.status}`);
                }

                customerData = result.data.customer;
                renderProfile(customerData);
            } catch (error) {
                console.error('Load profile error:', error);
                showMessage(error.message || 'Failed to load profile data', 'error');
            } finally {
                hideLoading();
            }
        }

        function renderProfile(customer) {
            const fullName = customer.full_name || customer.username || 'Customer';
            const initials = (fullName.split(' ').map(s => s[0]).join('').slice(0, 2) || 'U').toUpperCase();
            const profileImage = customer.profile_image ? `/RADS-TOOLING/${customer.profile_image}` : null;

            // nav username (kung wala yung element, tahimik lang)
            const navUser = document.getElementById('nav-username');
            if (navUser) navUser.textContent = fullName;

            // sidebar
            const sidebarAvatar = document.getElementById('sidebar-avatar');
            if (sidebarAvatar) {
                sidebarAvatar.innerHTML = profileImage ?
                    `<img src="${profileImage}?v=${Date.now()}" alt="Profile">` :
                    `<div class="avatar-placeholder">${initials}</div>`;
            }
            const sidebarName = document.getElementById('sidebar-name');
            if (sidebarName) sidebarName.textContent = fullName;

            // form fields
            const setVal = (id, v) => {
                const el = document.getElementById(id);
                if (el) el.value = v ?? '';
            };
            setVal('username', customer.username);
            setVal('full_name', fullName);
            setVal('email', customer.email);
            setVal('address', customer.address);

            // Prefill phoneLocal (10 digits after +63)
            const phoneLocalEl = document.getElementById('phoneLocal');
            if (phoneLocalEl) {
                const digits = String(customer.phone || '')
                    .replace(/\D/g, '') // keep digits
                    .replace(/^63/, ''); // drop country code
                phoneLocalEl.value = digits.slice(0, 10);
            }

            // avatar preview
            const avatarPreview = document.getElementById('avatar-preview');
            if (avatarPreview) {
                avatarPreview.innerHTML = profileImage ?
                    `<img src="${profileImage}?v=${Date.now()}" alt="Profile">` :
                    `<div class="avatar-placeholder-large">${initials}</div>`;
            }
        }

        async function updateProfile(event) {
            event.preventDefault();

            const saveBtn = document.getElementById('save-btn');
            const btnText = saveBtn.querySelector('.btn-text');
            const btnSpinner = saveBtn.querySelector('.btn-spinner');

            saveBtn.disabled = true;
            btnText.style.display = 'none';
            btnSpinner.style.display = 'inline-block';

            const local = (document.getElementById('phoneLocal')?.value || '')
                .replace(/\D/g, '').slice(0, 10);
            const composedPhone = local ? `+63${local}` : ''; // allow blank if gusto mo

            // sync hidden (optional)
            const hiddenPhone = document.getElementById('phone');
            if (hiddenPhone) hiddenPhone.value = composedPhone;

            const formData = {
                csrf_token: CSRF_TOKEN,
                full_name: document.getElementById('full_name').value.trim(),
                phone: composedPhone, // << ito na ipapasa
                address: document.getElementById('address').value.trim()
            };

            if (!local || local.length !== 10) {
                showMessage('Enter 10 digits after +63 (e.g., 9123456789)', 'error');
                saveBtn.disabled = false;
                btnText.style.display = 'inline';
                btnSpinner.style.display = 'none';
                return;
            }

            try {
                const res = await fetch(`${API_BASE}/customer_profile.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify(formData)
                });

                const text = await res.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch {
                    console.error('Non-JSON response:', text.slice(0, 200));
                    throw new Error('Failed to update profile');
                }

                if (!res.ok || !result.success) {
                    if (result && result.redirect) {
                        window.location.href = result.redirect;
                        return;
                    }
                    throw new Error(result?.message || `HTTP ${res.status}`);
                }

                // sync local state
                customerData.full_name = result.data.full_name;
                customerData.phone = result.data.phone ?? customerData.phone;
                customerData.address = result.data.address ?? customerData.address;

                // refresh UI (sidebar, preview, fields)
                renderProfile(customerData);

                // optional: update nav username kung meron
                const navUser = document.getElementById('nav-username');
                if (navUser) navUser.textContent = customerData.full_name;

                showMessage(result.message || 'Saved!', 'success');

            } catch (err) {
                console.error('Update profile error:', err);
                showMessage(err.message || 'Failed to update profile', 'error');
            } finally {
                saveBtn.disabled = false;
                btnText.style.display = 'inline';
                btnSpinner.style.display = 'none';
            }
        }

        async function updateAddress(event) {
            event.preventDefault();

            const formData = {
                csrf_token: CSRF_TOKEN,
                full_name: customerData.full_name,
                phone: customerData.phone || '',
                address: document.getElementById('address').value.trim()
            };

            const response = await fetch(`${API_BASE}/customer_profile.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify(formData)
            });

            const result = await response.json().catch(() => ({
                success: false
            }));

            if (!result.success) {
                throw new Error(result.message || 'Failed to update address');
            }

            // sync local + refresh UI
            customerData.address = formData.address;
            renderProfile(customerData);
            showMessage('Address updated successfully', 'success');
        }


        async function uploadProfileImage(event) {
            const file = event.target.files[0];
            if (!file) return;

            const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!allowedTypes.includes(file.type)) {
                showMessage('Only JPG, JPEG, and PNG files are allowed', 'error');
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                showMessage('File size must be less than 5MB', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('profile_image', file); // <-- backend dapat $_FILES['profile_image']
            formData.append('csrf_token', CSRF_TOKEN);

            try {
                const res = await fetch(`${API_BASE}/upload_profile_image.php`, {
                    method: 'POST',
                    body: formData, // wag lagyan ng Content-Type
                    credentials: 'include'
                });

                const text = await res.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch {
                    console.error('Non-JSON upload response:', text.slice(0, 200));
                    throw new Error('Failed to upload image');
                }

                if (!res.ok || !result.success) {
                    throw new Error(result?.message || `HTTP ${res.status}`);
                }

                // success: sync local + refresh UI
                // depende sa response mo: result.data.profile_image or result.path
                const newPath = (result.data && result.data.profile_image) ? result.data.profile_image : result.path;
                customerData.profile_image = newPath;
                renderProfile(customerData); // may ?v=Date.now() sa render para cache-bust
                showMessage(result.message || 'Profile image updated', 'success');

            } catch (err) {
                console.error('Upload image error:', err);
                showMessage(err.message || 'Failed to upload image', 'error');
            } finally {
                event.target.value = ''; // reset input
            }
        }

        document.addEventListener('DOMContentLoaded', loadProfile);
    </script>

    <!-- MODAL: Step 1 - request code -->
    <div id="pwReqModal" class="modal hidden">
        <div class="modal-card">
            <h3>Send reset code</h3>
            <p class="muted">We will email you a 6-digit code to verify it’s you.</p>
            <form id="pwReqForm">
                <label>Email</label>
                <input type="email" id="pwReqEmail" required>
                <div class="row">
                    <button type="button" class="btn btn-outline" id="pwReqCancel">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Code</button>
                </div>
                <div id="pwReqMsg" class="msg"></div>
            </form>
        </div>
    </div>

    <!-- MODAL: Step 2 - verify code + new password -->
    <div id="pwCodeModal" class="modal hidden">
        <div class="modal-card">
            <h3>Verify code</h3>
            <p class="muted">Enter the 6-digit code from your email, then set a new password.</p>
            <form id="pwCodeForm">
                <label>6-digit code</label>
                <input type="text" id="pwCodeInput" pattern="^[0-9]{6}$" maxlength="6" required>
                <label>New password (min 8)</label>
                <input type="password" id="pwNew1" minlength="8" required>
                <label>Confirm new password</label>
                <input type="password" id="pwNew2" minlength="8" required>
                <div class="row">
                    <button type="button" class="btn btn-outline" id="pwCodeCancel">Cancel</button>
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </div>
                <div id="pwCodeMsg" class="msg"></div>
            </form>
        </div>
    </div>

<!-- MODAL: Add/Edit Address with PSGC -->
    <div id="addressFormModal" class="modal hidden">
        <div class="modal-card" style="max-width: 600px; max-height: 90vh; overflow-y: auto;">
            <h3 id="addressModalTitle">Add New Address</h3>
            <form id="addressManageForm">
                <input type="hidden" id="addressEditId">
 
                <div class="form-group">
                    <label>Address Nickname (Optional)</label>
                    <input type="text" id="addressNickname" placeholder="e.g., Home, Office">
                </div>

                <div class="form-group">
                    <label>Full Name <span style="color: red;">*</span></label>
                    <input type="text" id="addressFullName" required>
                </div>

                <div class="form-group">
                    <label>Mobile Number <span style="color: red;">*</span></label>
                    <div class="phone-group">
                        <span class="phone-prefix">+63</span>
                        <input type="tel" id="addressPhoneLocal" inputmode="numeric" maxlength="10" placeholder="9123456789" required>
                        <input type="hidden" id="addressPhone">
                    </div>
                </div>

                <div class="form-group">
                    <label>Email (Optional)</label>
                    <input type="email" id="addressEmail">
                </div>

                <div class="form-group">
                    <label>Province <span style="color: red;">*</span></label>
                    <select id="addressProvince" required>
                        <option value="">Select Province</option>
                    </select>
                    <input type="hidden" id="addressProvinceCode">
                </div>

                <div class="form-group">
                    <label>City/Municipality <span style="color: red;">*</span></label>
                    <select id="addressCity" required>
                        <option value="">Select City/Municipality</option>
                    </select>
                    <input type="hidden" id="addressCityCode">
                </div>

                <div class="form-group">
                    <label>Barangay <span style="color: red;">*</span></label>
                    <select id="addressBarangay" required>
                        <option value="">Select Barangay</option>
                    </select>
                    <input type="hidden" id="addressBarangayCode">
                </div>

                <div class="form-group">
                    <label>Street / Block / Lot <span style="color: red;">*</span></label>
                    <textarea id="addressStreet" rows="2" placeholder="House No., Street Name, Building, etc." required></textarea>
                </div>

                <div class="form-group">
                    <label>Postal Code (Optional)</label>
                    <input type="text" id="addressPostal" pattern="\d{4,5}" placeholder="1234">
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="addressIsDefault" style="width: auto;">
                    <label for="addressIsDefault" style="margin: 0; font-weight: normal;">Set as default address</label>
                </div>

                <div class="row" style="margin-top: 20px;">
                   <button type="button" class="btn btn-outline" id="addressFormCancel">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addressFormSubmit">
                        <span class="btn-text">Save Address</span>
                        <span class="btn-spinner" style="display: none;">
                            <div class="mini-spinner"></div>
                        </span>
                    </button>
                </div>
                <div id="addressFormMsg" class="msg"></div>
            </form>
        </div>
    </div>

 
    <script>
        /***** CHANGE PASSWORD (LOGGED-IN) *****/
        document.getElementById('pwChangeForm')?.addEventListener('submit', changePasswordLoggedIn);

        async function changePasswordLoggedIn(e) {
            e.preventDefault();

            const btn = document.getElementById('pwSaveBtn');
            const t = btn.querySelector('.btn-text');
            const s = btn.querySelector('.btn-spinner');
            btn.disabled = true;
            t.style.display = 'none';
            s.style.display = 'inline-block';

            const current = document.getElementById('curr_pw').value;
            const newPw = document.getElementById('new_pw').value;
            const confirm = document.getElementById('new_pw2').value;

            if (newPw !== confirm) {
                showMessage('New password and confirmation do not match', 'error');
                btn.disabled = false;
                t.style.display = 'inline';
                s.style.display = 'none';
                return;
            }
            if (newPw.length < 8) {
                showMessage('Password must be at least 8 characters', 'error');
                btn.disabled = false;
                t.style.display = 'inline';
                s.style.display = 'none';
                return;
            }

            try {
                const res = await fetch(`${API_BASE}/change_password.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        csrf_token: CSRF_TOKEN,
                        current_password: current,
                        new_password: newPw,
                        confirm_password: confirm
                    })
                });

                const out = await res.json().catch(() => ({
                    success: false
                }));
                showMessage(out.message || (out.success ? 'Password updated' : 'Update failed'),
                    out.success ? 'success' : 'error');

                if (out.success) {
                    document.getElementById('curr_pw').value = '';
                    document.getElementById('new_pw').value = '';
                    document.getElementById('new_pw2').value = '';
                }
            } catch {
                showMessage('Network error', 'error');
            } finally {
                btn.disabled = false;
                t.style.display = 'inline';
                s.style.display = 'none';
            }
        }

        /***** OTP MODALS (FORGOT PASSWORD FLOW) *****/
        const btnOpenPwReq = document.getElementById('btnOpenPwReq');

        // Request modal elements
        const pwReqModal = document.getElementById('pwReqModal');
        const pwReqCancel = document.getElementById('pwReqCancel');
        const pwReqEmail = document.getElementById('pwReqEmail');
        const pwReqForm = document.getElementById('pwReqForm');
        const pwReqMsg = document.getElementById('pwReqMsg');

        // Verify modal elements
        const pwCodeModal = document.getElementById('pwCodeModal');
        const pwCodeCancel = document.getElementById('pwCodeCancel');
        const pwCodeForm = document.getElementById('pwCodeForm');
        const pwCodeMsg = document.getElementById('pwCodeMsg');

        function openModal(el) {
            el.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(el) {
            el.classList.add('hidden');
            document.body.style.overflow = '';
        }

        // Open request modal
        btnOpenPwReq?.addEventListener('click', (e) => {
            e.preventDefault();
            if (typeof customerData !== 'undefined' && customerData?.email) {
                pwReqEmail.value = customerData.email;
            }
            pwReqMsg.textContent = '';
            pwReqMsg.className = 'msg';
            openModal(pwReqModal);
        });

        // Close handlers
        pwReqCancel?.addEventListener('click', () => closeModal(pwReqModal));
        pwCodeCancel?.addEventListener('click', () => closeModal(pwCodeModal));
        [pwReqModal, pwCodeModal].forEach(m => m?.addEventListener('click', e => {
            if (e.target === m) closeModal(m);
        }));
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                if (!pwReqModal.classList.contains('hidden')) closeModal(pwReqModal);
                if (!pwCodeModal.classList.contains('hidden')) closeModal(pwCodeModal);
            }
        });

        // STEP 1: Send reset code
        pwReqForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            pwReqMsg.textContent = 'Sending...';
            pwReqMsg.className = 'msg';

            try {
                const res = await fetch(`${API_BASE}/password.php?action=request`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        csrf_token: CSRF_TOKEN,
                        email: pwReqEmail.value.trim()
                    })
                });

                const out = await res.json().catch(() => ({
                    success: false
                }));
                if (!out.success) throw new Error(out.message || 'Failed to send code');

                pwReqMsg.textContent = 'Code sent! Please check your inbox (and spam).';
                pwReqMsg.className = 'msg success';

                // Open Step 2 after a short delay
                setTimeout(() => {
                    closeModal(pwReqModal);
                    pwCodeMsg.textContent = '';
                    pwCodeMsg.className = 'msg';
                    pwCodeModal.dataset.email = pwReqEmail.value.trim();
                    openModal(pwCodeModal);
                }, 700);

            } catch (err) {
                pwReqMsg.textContent = err.message || 'Failed to send code';
                pwReqMsg.className = 'msg error';
            }
        });

        // STEP 2: Verify code + set new password
        pwCodeForm?.addEventListener('submit', async (e) => {
            e.preventDefault();

            const email = pwCodeModal.dataset.email || (typeof customerData !== 'undefined' ? (customerData?.email || '') : '');
            const code = document.getElementById('pwCodeInput').value.trim();
            const p1 = document.getElementById('pwNew1').value;
            const p2 = document.getElementById('pwNew2').value;

            if (p1 !== p2) {
                pwCodeMsg.textContent = 'Passwords do not match';
                pwCodeMsg.className = 'msg error';
                return;
            }
            if (p1.length < 8) {
                pwCodeMsg.textContent = 'Password must be at least 8 characters';
                pwCodeMsg.className = 'msg error';
                return;
            }

            pwCodeMsg.textContent = 'Updating...';
            pwCodeMsg.className = 'msg';

            try {
                const res = await fetch(`${API_BASE}/password.php?action=reset`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        csrf_token: CSRF_TOKEN,
                        email,
                        code,
                        new_password: p1,
                        confirm: p2
                    })
                });

                const out = await res.json().catch(() => ({
                    success: false
                }));
                if (!out.success) throw new Error(out.message || 'Failed to change password');

                pwCodeMsg.textContent = 'Password changed! You can now log in with your new password.';
                pwCodeMsg.className = 'msg success';
                setTimeout(() => closeModal(pwCodeModal), 900);

            } catch (err) {
                pwCodeMsg.textContent = err.message || 'Failed to change password';
                pwCodeMsg.className = 'msg error';
            }
        });

        /***** OPTIONAL: open password tab or modal via URL hash *****/
        document.addEventListener('DOMContentLoaded', () => {
            if (location.hash === '#password') {
                switchTab?.('password');
            } else if (location.hash === '#password-reset') {
                switchTab?.('password');
                btnOpenPwReq?.click();
            }
        });

        document.getElementById('phoneLocal')?.addEventListener('input', e => {
            e.target.value = e.target.value.replace(/\D/g, '').slice(0, 10);
        });
    // Sync phone inputs in address form
        document.getElementById('addressPhoneLocal')?.addEventListener('input', e => {
            e.target.value = e.target.value.replace(/\D/g, '').slice(0, 10);
            document.getElementById('addressPhone').value = '+63' + e.target.value;
        });
    </script>

    <script>
        /***** BACK BUTTON FUNCTIONALITY *****/
        function goBack() {
            // Check if there's history
            if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) {
                // Internal navigation - go back
                window.history.back();
            } else {
                // External or no history - go to homepage
                window.location.href = '/RADS-TOOLING/customer/index.php';
            }
        }
    </script>

    <script>
        // Tab switching for sidebar menu
        document.querySelectorAll('.menu-item[data-tab]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const tabName = this.dataset.tab;

                // Update active menu item
                document.querySelectorAll('.menu-item').forEach(item => {
                    item.classList.remove('active');
                });
                this.classList.add('active');

                // Update active content
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });

                const targetTab = document.getElementById(`${tabName}-tab`);
                if (targetTab) {
                    targetTab.classList.add('active');
                }
            });
        });

    </script>
    <script src="/RADS-TOOLING/assets/JS/address_management.js"></script>

</body>

</html>
