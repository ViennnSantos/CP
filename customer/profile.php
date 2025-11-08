<?php

/**

 * Customer Profile Page

 * Manage profile, addresses, and password

 *

 * Security: Authentication required, CSRF protected

 */

 

session_start();

 

// ===== SECURITY: Require Customer Authentication =====

if (empty($_SESSION['user']) || ($_SESSION['user']['aud'] ?? '') !== 'customer') {

    header('Location: /customer/login.php');

    exit;

}

 

// ===== SECURITY: Generate CSRF Token =====

if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title>My Account - RADS Tooling</title>

 

    <!-- Fonts -->

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

 

    <!-- Stylesheets -->

    <link rel="stylesheet" href="/assets/CSS/profile.css">
    <link rel="stylesheet" href="/assets/CSS/profile_UI_POLISH_COMPLETE.css">
</head>

<body>

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

                        <div>

                            <h2>My Address</h2>

                            <p>Manage your delivery addresses</p>

                        </div>

                        <button type="button" class="btn btn-primary" onclick="showAddressForm()">

                            + Add New Address

                        </button>

                    </div>

 

                    <div class="content-body">

                        <div id="address-list-loading" style="display: none; text-align: center; padding: 40px;">

                            <div class="spinner" style="margin: 0 auto;"></div>

                            <p style="margin-top: 16px; color: #6b7280;">Loading addresses...</p>

                        </div>

 

                        <div id="no-addresses" style="display: none; text-align: center; padding: 60px 20px;">

                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5" style="margin: 0 auto 16px;">

                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>

                                <polyline points="9 22 9 12 15 12 15 22"></polyline>

                            </svg>

                            <h3 style="font-size: 18px; color: #6b7280; margin: 0 0 8px 0;">No addresses yet</h3>

                            <p style="color: #9ca3af; margin: 0;">Add your first delivery address to get started</p>

                        </div>

 

                        <div id="address-list"></div>

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

                            <button type="button" class="btn btn-outline" id="btnOpenPwReq">Request Password Reset (via email)</button>

                        </div>

                    </div>

                </div>

            </main>

        </div>

    </div>

 

    <!-- MODAL: Add/Edit Address with PSGC -->

    <div id="addressFormModal" class="modal hidden">

        <div class="modal-card">

            <div class="modal-card-header">

                <button type="button" class="modal-back-btn" id="addressModalBackBtn" aria-label="Back">

                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                        <line x1="19" y1="12" x2="5" y2="12"></line>

                        <polyline points="12 19 5 12 12 5"></polyline>

                    </svg>

                    Back

                </button>

                <h3 id="addressModalTitle">Add New Address</h3>

                <button type="button" class="modal-close-btn" id="addressModalCloseBtn" aria-label="Close">&times;</button>

            </div>

            <form id="addressManageForm">

                <input type="hidden" id="addressEditId">

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

 

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

 

                <div class="row">

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

 

    <!-- MODAL: Step 1 - Request Reset Code -->

    <div id="pwReqModal" class="modal hidden">

        <div class="modal-card">

            <h3>Send Reset Code</h3>

            <p class="muted">We will email you a 6-digit code to verify it's you.</p>

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

 

    <!-- MODAL: Step 2 - Verify Code + New Password -->

    <div id="pwCodeModal" class="modal hidden">

        <div class="modal-card">

            <h3>Verify Code</h3>

            <p class="muted">Enter the 6-digit code from your email, then set a new password.</p>

            <form id="pwCodeForm">

                <label>6-digit Code</label>

                <input type="text" id="pwCodeInput" pattern="^[0-9]{6}$" maxlength="6" required>

                <label>New Password (min 8)</label>

                <input type="password" id="pwNew1" minlength="8" required>

                <label>Confirm New Password</label>

                <input type="password" id="pwNew2" minlength="8" required>

                <div class="row">

                    <button type="button" class="btn btn-outline" id="pwCodeCancel">Cancel</button>

                    <button type="submit" class="btn btn-primary">Change Password</button>

                </div>

                <div id="pwCodeMsg" class="msg"></div>

            </form>

        </div>

    </div>

 

    <!-- JavaScript -->

    <script>

        const API_BASE = '/backend/api';

        const CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token']); ?>;

 

        let customerData = null;

 

        // ===== Tab Switching =====

        document.querySelectorAll('[data-tab]').forEach(link => {

            link.addEventListener('click', (e) => {

                e.preventDefault();

                const tabName = link.dataset.tab;

                switchTab(tabName);

            });

        });

 

        function switchTab(tabName) {

            // Update active menu item

            document.querySelectorAll('.menu-item').forEach(item => item.classList.remove('active'));

            document.querySelector(`[data-tab="${tabName}"]`)?.classList.add('active');

 

            // Update active content

            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

            document.getElementById(`${tabName}-tab`)?.classList.add('active');

        }

 

        // ===== UI Helpers =====

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

 

            setTimeout(() => container.innerHTML = '', 5000);

            window.scrollTo({ top: 0, behavior: 'smooth' });

        }

 

        // ===== Load Profile =====

        async function loadProfile() {

            showLoading();

            try {

                const res = await fetch(`${API_BASE}/customer_profile.php`, {

                    credentials: 'include',

                    headers: { 'Accept': 'application/json' }

                });

 

                const text = await res.text();

                let result;

                try {

                    result = JSON.parse(text);

                } catch {

                    console.error('Non-JSON response:', text.slice(0, 200));

                    throw new Error('Failed to load profile');

                }

 

                if (!res.ok || !result.success) {

                    if (result?.redirect) {

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

 

        // ===== Render Profile =====

        function renderProfile(customer) {

            const fullName = customer.full_name || customer.username || 'Customer';

            const initials = (fullName.split(' ').map(s => s[0]).join('').slice(0, 2) || 'U').toUpperCase();

            const profileImage = customer.profile_image ? `/${customer.profile_image}` : null;

 

            // Sidebar

            const sidebarAvatar = document.getElementById('sidebar-avatar');

            if (sidebarAvatar) {

                sidebarAvatar.innerHTML = profileImage ?

                    `<img src="${profileImage}?v=${Date.now()}" alt="Profile">` :

                    `<div class="avatar-placeholder">${initials}</div>`;

            }

 

            const sidebarName = document.getElementById('sidebar-name');

            if (sidebarName) sidebarName.textContent = fullName;

 

            // Form fields

            const setVal = (id, v) => {

                const el = document.getElementById(id);

                if (el) el.value = v ?? '';

            };

 

            setVal('username', customer.username);

            setVal('full_name', fullName);

            setVal('email', customer.email);

 

            // Phone (10 digits after +63)

            const phoneLocalEl = document.getElementById('phoneLocal');

            if (phoneLocalEl) {

                const digits = String(customer.phone || '').replace(/\D/g, '').replace(/^63/, '');

                phoneLocalEl.value = digits.slice(0, 10);

            }

 

            // Avatar preview

            const avatarPreview = document.getElementById('avatar-preview');

            if (avatarPreview) {

                avatarPreview.innerHTML = profileImage ?

                    `<img src="${profileImage}?v=${Date.now()}" alt="Profile">` :

                    `<div class="avatar-placeholder-large">${initials}</div>`;

            }

        }

 

        // ===== Update Profile =====

        async function updateProfile(event) {

            event.preventDefault();

 

            const saveBtn = document.getElementById('save-btn');

            const btnText = saveBtn.querySelector('.btn-text');

            const btnSpinner = saveBtn.querySelector('.btn-spinner');

 

            saveBtn.disabled = true;

            btnText.style.display = 'none';

            btnSpinner.style.display = 'inline-block';

 

            const local = (document.getElementById('phoneLocal')?.value || '').replace(/\D/g, '').slice(0, 10);

            const composedPhone = local ? `+63${local}` : '';

 

            if (!local || local.length !== 10) {

                showMessage('Enter 10 digits after +63 (e.g., 9123456789)', 'error');

                saveBtn.disabled = false;

                btnText.style.display = 'inline';

                btnSpinner.style.display = 'none';

                return;

            }

 

            const formData = {

                csrf_token: CSRF_TOKEN,

                full_name: document.getElementById('full_name').value.trim(),

                phone: composedPhone,

                address: ''

            };

 

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

                    if (result?.redirect) {

                        window.location.href = result.redirect;

                        return;

                    }

                    throw new Error(result?.message || `HTTP ${res.status}`);

                }

 

                customerData.full_name = result.data.full_name;

                customerData.phone = result.data.phone ?? customerData.phone;

 

                renderProfile(customerData);

                showMessage(result.message || 'Profile updated successfully!', 'success');

 

            } catch (err) {

                console.error('Update profile error:', err);

                showMessage(err.message || 'Failed to update profile', 'error');

            } finally {

                saveBtn.disabled = false;

                btnText.style.display = 'inline';

                btnSpinner.style.display = 'none';

            }

        }

 

        // ===== Upload Profile Image =====

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

            formData.append('profile_image', file);

            formData.append('csrf_token', CSRF_TOKEN);

 

            try {

                const res = await fetch(`${API_BASE}/upload_profile_image.php`, {

                    method: 'POST',

                    body: formData,

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

 

                const newPath = (result.data?.profile_image) ? result.data.profile_image : result.path;

                customerData.profile_image = newPath;

                renderProfile(customerData);

                showMessage(result.message || 'Profile image updated', 'success');

 

            } catch (err) {

                console.error('Upload image error:', err);

                showMessage(err.message || 'Failed to upload image', 'error');

            } finally {

                event.target.value = '';

            }

        }

 

        // ===== Change Password (Logged-in) =====

        document.getElementById('pwChangeForm')?.addEventListener('submit', async (e) => {

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

 

                const out = await res.json().catch(() => ({ success: false }));

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

        });

 

        // ===== OTP Password Reset Modals =====

        const btnOpenPwReq = document.getElementById('btnOpenPwReq');

        const pwReqModal = document.getElementById('pwReqModal');

        const pwReqCancel = document.getElementById('pwReqCancel');

        const pwReqEmail = document.getElementById('pwReqEmail');

        const pwReqForm = document.getElementById('pwReqForm');

        const pwReqMsg = document.getElementById('pwReqMsg');

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

            if (customerData?.email) {

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

 

                const out = await res.json().catch(() => ({ success: false }));

                if (!out.success) throw new Error(out.message || 'Failed to send code');

 

                pwReqMsg.textContent = 'Code sent! Please check your inbox (and spam).';

                pwReqMsg.className = 'msg success';

 

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

 

            const email = pwCodeModal.dataset.email || customerData?.email || '';

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

 

                const out = await res.json().catch(() => ({ success: false }));

                if (!out.success) throw new Error(out.message || 'Failed to change password');

 

                pwCodeMsg.textContent = 'Password changed! You can now log in with your new password.';

                pwCodeMsg.className = 'msg success';

                setTimeout(() => closeModal(pwCodeModal), 900);

 

            } catch (err) {

                pwCodeMsg.textContent = err.message || 'Failed to change password';

                pwCodeMsg.className = 'msg error';

            }

        });

 

        // ===== Phone Input Validation =====

        document.getElementById('phoneLocal')?.addEventListener('input', e => {

            e.target.value = e.target.value.replace(/\D/g, '').slice(0, 10);

        });

 

        // ===== Initialize on Load =====

        document.addEventListener('DOMContentLoaded', () => {

            loadProfile();

 

            // Handle hash navigation

            if (location.hash === '#address') {

                switchTab('address');

            } else if (location.hash === '#password') {

                switchTab('password');

            } else if (location.hash === '#password-reset') {

                switchTab('password');

                setTimeout(() => btnOpenPwReq?.click(), 100);

            }

        });

    </script>

 

    <!-- Address Management Script -->

    <script src="/assets/JS/address_management.js"></script>

</body>

</html>