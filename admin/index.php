<?php
require_once __DIR__ . '/../backend/config/app.php';
require_once __DIR__ . '/../includes/guard.php';
guard_require_staff();

// Initialize user session data
$isLoggedIn = false;
$adminName = 'Admin';
$userRole  = 'Secretary'; // Default role

if (isset($_SESSION['staff'])) {
    $isLoggedIn = true;
    $adminName  = $_SESSION['staff']['full_name'] ?? 'Admin';
    $userRole   = $_SESSION['staff']['role'] ?? 'Secretary';
} elseif (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $isLoggedIn = true;
    $adminName  = $_SESSION['admin_name'] ?? 'Admin';
    $userRole   = 'Owner'; // Legacy session defaults to Owner
}

// If not logged in, redirect to login
if (!$isLoggedIn) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dashboard | Rads Tooling</title>
    <!-- Icons & Fonts -->
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" />
    <!-- Quill Rich Text Editor -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <!-- Main CSS -->
    <link rel="stylesheet" href="/assets/CSS/style.css" />
    <link rel="stylesheet" href="/assets/CSS/chat-admin.css" />
    <link rel="stylesheet" href="/assets/CSS/content_mgmt.css" />

    <style>
        /* ===== Consolidated Modal, Loading, and UI styles (merged) ===== */
        .loading-overlay {
            position: fixed;
            inset: 0;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            flex-direction: column;
            gap: 1rem;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Role badges */
        .badge-owner {
            background: #8e44ad;
            color: #fff;
        }

        .badge-admin {
            background: #3498db;
            color: #fff;
        }

        .badge-secretary {
            background: #95a5a6;
            color: #fff;
        }

        /* Button actions */
        .btn-action {
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            font-size: 1.1em;
            margin-right: 4px;
            border-radius: 6px;
            transition: background .15s, color .15s;
        }

        .btn-action.btn-edit {
            color: #3498db
        }

        .btn-action.btn-delete {
            color: #e74c3c
        }

        .btn-action.btn-view {
            color: #27ae60
        }

        .btn-action:hover {
            background: #f3f6fb
        }

        /* Profile */
        .edit-profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--brand);
        }

        /* Chat overrides */
        section[data-section="chat"] .rt-admin-container {
            padding: 0 !important;
            margin: 0 !important;
            max-width: 100% !important;
        }

        section[data-section="chat"] .rt-thread-list {
            max-height: 500px;
            overflow-y: auto;
        }

        @media (max-width:1200px) {
            section[data-section="chat"] .rt-admin-container {
                flex-direction: column;
            }
        }

        /* Modal (single unified) */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .6);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, .12);
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 1;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #eaeaea;
            background: #fff;
            flex-shrink: 0;
            z-index: 10;
        }

        .modal-body-scrollable {
            overflow-y: auto;
            overflow-x: hidden;
            flex: 1;
            padding: 20px;
        }

        .modal-close,
        .close-modal {
            background: none;
            border: 0;
            cursor: pointer;
            font-size: 1.3rem;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding: 16px;
            border-top: 1px solid #eaeaea;
            position: relative;
            bottom: auto;
            background: #fff;
            z-index: 10;
        }

        .btn-primary {
            background: #2c5f8d;
            color: #fff;
            padding: 10px 16px;
            border-radius: 8px;
            border: 0;
            cursor: pointer;
            position: relative;
            z-index: 10;
            pointer-events: auto;
        }

        .btn-secondary {
            background: #e3e3e3;
            color: #333;
            padding: 10px 14px;
            border-radius: 8px;
            border: 0;
            cursor: pointer;
        }

        .btn-danger {
            background: #e14d4d;
            color: #fff;
            padding: 10px 14px;
            border-radius: 8px;
            border: 0;
            cursor: pointer;
        }

        .btn-success {
            background: #28a745;
            color: #fff;
            padding: 10px 14px;
            border-radius: 8px;
            border: 0;
            cursor: pointer;
        }

        /* Editor & preview */
        .editor-layout {
            display: grid;
            grid-template-columns: 500px 1fr;
            gap: 0;
            height: 70vh;
        }

        .editor-panel {
            border-right: 1px solid #e9e9e9;
            background: #fafafa;
            overflow: auto;
        }

        .preview-panel {
            background: #fff;
            display: flex;
            flex-direction: column;
        }

        .preview-iframe-container iframe {
            width: 100%;
            height: 100%;
            border: 0;
        }

        /* Forms & helpers */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        /* Toast */
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 99999;
        }

        .toast {
            padding: 12px 20px;
            border-radius: 8px;
            color: #fff;
            margin-top: 8px;
            display: none;
        }

        .toast.show {
            display: block;
        }

        /* small utility */
        .hidden {
            display: none !important;
        }
    </style>
</head>

<body>
    <!-- Loading overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
        <p>Loading dashboard...</p>
    </div>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-logo"><span class="logo-text">R</span>ADS <span class="logo-text">T</span>OOLING</div>
        <div class="sidebar-nav">
            <span class="nav-item active" data-section="dashboard"><a href="#"><span class="material-symbols-rounded">home</span>
                    <h2>Dashboard</h2>
                </a></span>
            <span class="nav-item" data-section="account"><a href="#"><span class="material-symbols-rounded">person</span>
                    <h2>Account Management</h2>
                </a></span>
            <span class="nav-item" data-section="customer"><a href="#"><span class="material-symbols-rounded">groups</span>
                    <h2>Customer Management</h2>
                </a></span>
            <span class="nav-item" data-section="products"><a href="#"><span class="material-symbols-rounded">door_sliding</span>
                    <h2>Products Management</h2>
                </a></span>
            <span class="nav-item" data-section="orders"><a href="#"><span class="material-symbols-rounded">list_alt</span>
                    <h2>Order Management</h2>
                </a></span>
            <span class="nav-item" data-section="reports"><a href="#"><span class="material-symbols-rounded">insights</span>
                    <h2>Report Generation</h2>
                </a></span>
            <span class="nav-item" data-section="content"><a href="#"><span class="material-symbols-rounded">campaign</span>
                    <h2>Content Management</h2>
                </a></span>
            <span class="nav-item" data-section="feedback"><a href="#"><span class="material-symbols-rounded">star</span>
                    <h2>Feedback & Ratings</h2>
                </a></span>
            <span class="nav-item" data-section="chat"><a href="#"><span class="material-symbols-rounded">chat</span>
                    <h2>Chat Support</h2>
                </a></span>
            <span class="nav-item" data-section="payment"><a href="#"><span class="material-symbols-rounded">credit_card</span>
                    <h2>Payment Verification</h2>
                </a></span>
        </div>
    </aside>

    <!-- MAIN -->
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-profile">
                <span class="admin-name">Welcome, <?php echo htmlspecialchars($adminName); ?></span>
                <div class="profile-menu">
                    <img src="/assets/images/profile.png" alt="Admin" class="profile-avatar" id="profileIcon" />
                    <div class="profile-dropdown" id="profileDropdown">
                        <button id="btnEditProfile"><span class="material-symbols-rounded">person</span> Edit Profile</button>
                        <button id="btnChangePassword"><span class="material-symbols-rounded">key</span> Change Password</button>
                        <button id="logoutBtn"><span class="material-symbols-rounded">logout</span> Logout</button>
                    </div>
                </div>
            </div>
        </header>

        <!-- DASHBOARD -->
        <section class="main-section show" data-section="dashboard">
            <div class="dashboard-widgets">
                <div class="dashboard-card card-orders">
                    <div class="card-title">Total Orders</div>
                    <div class="card-value" id="dash-orders">Loading...</div>
                </div>
                <div class="dashboard-card card-sales">
                    <div class="card-title">Total Sales</div>
                    <div class="card-value" id="dash-sales">Loading...</div>
                </div>
                <div class="dashboard-card card-down-payments">
                    <div class="card-title">Total Down Payments</div>
                    <div class="card-value" id="dash-down-payments">Loading...</div>
                </div>
                <div class="dashboard-card card-incoming-sales">
                    <div class="card-title">Incoming Sales</div>
                    <div class="card-value" id="dash-incoming-sales">Loading...</div>
                </div>
                <div class="dashboard-card card-customers">
                    <div class="card-title">Customers</div>
                    <div class="card-value" id="dash-customers">Loading...</div>
                </div>
            </div>

            <div class="dashboard-row">
                <div class="dashboard-chart">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
                        <h2>Sales Overview</h2>
                        <div class="chart-period-selector" style="display:flex;gap:.5rem">
                            <button class="period-btn active" data-period="day">Daily</button>
                            <button class="period-btn" data-period="week">Weekly</button>
                            <button class="period-btn" data-period="month">Monthly</button>
                            <button class="period-btn" data-period="year">Yearly</button>
                        </div>
                    </div>
                    <canvas id="salesChart"></canvas>
                </div>
                <div class="dashboard-orders">
                    <h2>Recent Orders</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="dashRecentOrders">
                            <tr>
                                <td colspan="5" style="text-align:center">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="dashboard-feedback">
                <h2>Latest Feedback</h2>
                <ul id="dashFeedbackList">
                    <li>Loading feedback...</li>
                </ul>
            </div>
        </section>

        <!-- ACCOUNT -->
        <section class="main-section" data-section="account">
            <div class="section-header">
                <h1>Account Management</h1>
                <button class="btn-add-user" onclick="openModal('addUserModal')"><span class="material-symbols-rounded">person_add</span> Add Staff/Admin</button>
            </div>
            <div class="account-table-container">
                <table class="account-table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody">
                        <tr>
                            <td colspan="6" style="text-align:center">Loading users...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- CUSTOMER -->
        <section class="main-section" data-section="customer">
            <div class="section-header">
                <h1>Customer Management</h1>
            </div>
            <div class="customer-controls" style="margin-bottom:1rem"><input type="text" id="customer-search" placeholder="Search by name, email, or username..." /></div>
            <div class="customer-table-container">
                <table class="customer-table">
                    <thead>
                        <tr>
                            <th>Customer ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="customerTableBody">
                        <tr>
                            <td colspan="7" style="text-align:center">Loading customers...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ORDERS -->
        <section class="main-section" data-section="orders">
            <div class="section-header">
                <h1>Order Management</h1>
            </div>
            <div class="order-controls">
                <input type="text" class="order-search" id="order-search" placeholder="Search orders..." />
                <select class="order-filter" id="statusFilter">
                    <option value="">All Status</option>
                    <option>Pending</option>
                    <option>Processing</option>
                    <option>Completed</option>
                    <option>Cancelled</option>
                </select>
                <select class="order-filter" id="paymentFilter">
                    <option value="">All Payment Status</option>
                    <option>Fully Paid</option>
                    <option>With Balance</option>
                </select>
            </div>
            <div class="orders-table-container">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Item</th>
                            <th>Customer</th>
                            <th>Date Ordered</th>
                            <th>Total</th>
                            <th>Payment Status</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="orderTableBody">
                        <tr>
                            <td colspan="8" style="text-align:center">Loading orders...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- PRODUCTS -->
        <section class="main-section" data-section="products">
            <div class="section-header">
                <h1>Product Management</h1>
                <div class="product-header-actions"><button class="btn-add-product" onclick="openModal('addProductModal')"><span class="material-symbols-rounded">add_circle</span> Add Product</button></div>
            </div>
            <div class="products-controls">
                <input type="text" class="products-search" id="product-search" placeholder="Search by name or description..." />
                <select class="products-filter" id="product-filter">
                    <option value="">All Types</option>
                    <option value="Kitchen Cabinet">Kitchen Cabinet</option>
                    <option value="Wardrobe">Wardrobe</option>
                    <option value="Office Cabinet">Office Cabinet</option>
                    <option value="Bathroom Cabinet">Bathroom Cabinet</option>
                    <option value="Storage Cabinet">Storage Cabinet</option>
                </select>
            </div>
            <div class="products-table-container">
                <table class="products-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Product Name</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Price</th>
                            <th>Customizable</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="productTableBody">
                        <tr>
                            <td colspan="8" style="text-align:center">Loading products...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- CONTENT MANAGEMENT -->
        <section class="main-section" data-section="content">
            <div class="section-header">
                <h1>Content Management</h1>
                <button class="btn-edit-content" id="btnEditContent"><span class="material-symbols-rounded">edit</span> Edit Content</button>
            </div>
            <div class="cm-page-selector"><label for="homepageType">Editing:</label><select id="homepageType" class="homepage-type-select">
                    <option value="home_public">Public Landing Page</option>
                    <option value="home_customer">Customer Homepage</option>
                </select></div>
            <div class="cm-tabs">
                <button class="cm-tab active" data-page="home_public"><span class="material-symbols-rounded">home</span> Homepage</button>
                <button class="cm-tab" data-page="about"><span class="material-symbols-rounded">info</span> About Us</button>
                <button class="cm-tab" data-page="privacy"><span class="material-symbols-rounded">shield</span> Privacy Policy</button>
                <button class="cm-tab" data-page="payment"><span class="material-symbols-rounded">qr_code</span> Payment Process Info</button>
                <button class="cm-tab" data-page="terms"><span class="material-symbols-rounded">gavel</span> Terms & Conditions</button>
            </div>
            <div class="cm-preview-container">
                <div class="cm-preview-card" id="previewCard"><iframe id="previewIframe" style="width:100%; height:600px; border:1px solid #e3edfb; border-radius:8px;"></iframe></div>
            </div>

            <!-- Payment QR Settings Section -->
            <div id="paymentSettingsSection" style="display: none; padding: 20px;">
                <div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 8px 0; color: #2f5b88; display: flex; align-items: center; gap: 8px;">
                        <span class="material-symbols-rounded">qr_code_2</span>
                        Payment QR Codes Management
                    </h3>

                    <p style="color: #666; margin: 0 0 24px 0; font-size: 14px;">
                        Upload and manage QR codes for GCash and BPI payments. These QR codes will be displayed to customers during checkout.
                    </p>

                    <!-- GCash QR Code Section -->
                    <div style="background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                        <h4 style="margin: 0 0 16px 0; color: #2f5b88; display: flex; align-items: center; gap: 8px; font-size: 16px;">
                            <span class="material-symbols-rounded">account_balance_wallet</span>
                            GCash QR Code
                        </h4>

                        <div style="margin-bottom: 16px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #333;">Current QR Code:</label>
                            <div id="gcashQRPreview" style="
                                background: white;
                                border: 2px dashed #ddd;
                                border-radius: 8px;
                                padding: 20px;
                                min-height: 200px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                color: #999;
                                font-size: 14px;
                            ">
                                No QR code uploaded yet
                            </div>
                        </div>

                        <input type="file" id="gcashQRUpload" accept="image/*" style="display: none;">
                        <button type="button" id="btnGCashUpload" class="btn-upload" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; background: #2f5b88; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px;">
                            <span class="material-symbols-rounded" style="font-size: 18px;">upload</span>
                            Upload GCash QR
                        </button>
                        <p style="font-size: 12px; color: #666; margin-top: 8px; margin-bottom: 0;">
                            Accepted formats: JPG, PNG, GIF, WEBP (Max 5MB)
                        </p>
                    </div>

                    <!-- BPI QR Code Section -->
                    <div style="background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px;">
                        <h4 style="margin: 0 0 16px 0; color: #2f5b88; display: flex; align-items: center; gap: 8px; font-size: 16px;">
                            <span class="material-symbols-rounded">account_balance</span>
                            BPI QR Code
                        </h4>

                        <div style="margin-bottom: 16px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #333;">Current QR Code:</label>
                            <div id="bpiQRPreview" style="
                                background: white;
                                border: 2px dashed #ddd;
                                border-radius: 8px;
                                padding: 20px;
                                min-height: 200px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                color: #999;
                                font-size: 14px;
                            ">
                                No QR code uploaded yet
                            </div>
                        </div>

                        <input type="file" id="bpiQRUpload" accept="image/*" style="display: none;">
                        <button type="button" id="btnBPIUpload" class="btn-upload" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; background: #2f5b88; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px;">
                            <span class="material-symbols-rounded" style="font-size: 18px;">upload</span>
                            Upload BPI QR
                        </button>
                        <p style="font-size: 12px; color: #666; margin-top: 8px; margin-bottom: 0;">
                            Accepted formats: JPG, PNG, GIF, WEBP (Max 5MB)
                        </p>
                    </div>

                    <!-- Info Note -->
                    <div style="margin-top: 20px; padding: 12px 16px; background: #cfe2ff; border: 1px solid #b6d4fe; border-radius: 6px; display: flex; align-items: flex-start; gap: 10px;">
                        <span class="material-symbols-rounded" style="font-size: 20px; color: #084298;">info</span>
                        <p style="margin: 0; font-size: 14px; color: #084298; line-height: 1.5;">
                            <strong>Note:</strong> After uploading new QR codes, they will be immediately available in the customer checkout process.
                            Make sure the QR codes are clear and scannable before uploading.
                        </p>
                    </div>
                </div>
            </div>
            <!-- END OF PAYMENT SECTION -->

        </section>
        <!-- REPORTS -->
        <section class="main-section" data-section="reports">
            <div class="section-header">
                <h1>Report Generation</h1>
            </div>
            <div class="report-controls">
                <label>From: <input type="date" class="report-date" id="report-from" /></label>
                <label>To: <input type="date" class="report-date" id="report-to" /></label>
                <button class="btn-generate" id="generateReportBtn">Generate Report</button>
                <button class="btn-export" id="exportPdfBtn"><span class="material-symbols-rounded">picture_as_pdf</span> Download PDF</button>
            </div>

            <div class="report-summary">
                <!-- summary cards (same as before) -->
                <div class="summary-card card-total-sales" style="cursor:pointer">
                    <div class="summary-icon" style="background:#3db36b1a;"><span class="material-symbols-rounded" style="color:#3db36b;">payments</span></div>
                    <div class="summary-title">Total Sales</div>
                    <div class="summary-value"><span id="rg-total-sales">₱0</span></div>
                </div>
                <div class="summary-card card-total-orders" style="cursor:pointer">
                    <div class="summary-icon" style="background:#3498db1a;"><span class="material-symbols-rounded" style="color:#3498db;">shopping_cart</span></div>
                    <div class="summary-title">Total Orders</div>
                    <div class="summary-value"><span id="rg-total-orders">0</span></div>
                </div>
                <div class="summary-card card-average-order" style="cursor:pointer">
                    <div class="summary-icon" style="background:#f7b7311a;"><span class="material-symbols-rounded" style="color:#f7b731;">equalizer</span></div>
                    <div class="summary-title">Avg. Order Value</div>
                    <div class="summary-value"><span id="rg-avg-order">₱0</span></div>
                </div>
                <div class="summary-card card-fully-paid" style="cursor:pointer">
                    <div class="summary-icon" style="background:#2f5b881a;"><span class="material-symbols-rounded" style="color:#2f5b88;">verified</span></div>
                    <div class="summary-title">Fully Paid Orders</div>
                    <div class="summary-value"><span id="rg-fully-paid">0</span></div>
                </div>
                <div class="summary-card card-cancelled" style="cursor:pointer">
                    <div class="summary-icon" style="background:#e14d4d1a;"><span class="material-symbols-rounded" style="color:#e14d4d;">block</span></div>
                    <div class="summary-title">Cancelled Orders</div>
                    <div class="summary-value"><span id="rg-cancelled">0</span></div>
                </div>
                <div class="summary-card card-pending" style="cursor:pointer">
                    <div class="summary-icon" style="background:#ffc4001a;"><span class="material-symbols-rounded" style="color:#ffc400;">pending</span></div>
                    <div class="summary-title">Pending Orders</div>
                    <div class="summary-value"><span id="rg-pending">0</span></div>
                </div>
                <div class="summary-card card-new-customer" style="cursor:pointer">
                    <div class="summary-icon" style="background:#8e44ad1a;"><span class="material-symbols-rounded" style="color:#8e44ad;">group_add</span></div>
                    <div class="summary-title">New Customers</div>
                    <div class="summary-value"><span id="rg-new-customers">0</span></div>
                </div>
                <div class="summary-card card-feedbacks" style="cursor:pointer">
                    <div class="summary-icon" style="background:#2980b91a;"><span class="material-symbols-rounded" style="color:#2980b9;">star</span></div>
                    <div class="summary-title">Feedbacks</div>
                    <div class="summary-value"><span id="rg-feedbacks">0</span></div>
                </div>
                <div class="summary-card card-most-ordered" style="cursor:pointer">
                    <div class="summary-icon" style="background:#26c6da1a;"><span class="material-symbols-rounded" style="color:#26c6da;">category</span></div>
                    <div class="summary-title">Most Ordered Item</div>
                    <div class="summary-value"><span id="rg-most-item">—</span></div>
                </div>
            </div>
        </section>

        <!-- FEEDBACK (merged: keeps Actions column, unified) -->
        <section class="main-section" data-section="feedback">
            <div class="section-header">
                <h1>Feedback & Ratings</h1>
            </div>
            <div class="feedback-table-container">
                <table class="feedback-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Rating</th>
                            <th>Comment</th>
                            <th>Order</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody id="feedbackTableBody">
                        <tr>
                            <td colspan="6" style="text-align:center">Loading feedback...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- CHAT SUPPORT (full merged) -->
        <section class="main-section" data-section="chat">
            <div class="section-header">
                <h1>Chat Support</h1>
                <div class="rt-user-info" style="font-size:14px;color:#666">Manage customer inquiries and FAQs</div>
            </div>
            <div class="rt-admin-container" style="padding:0;margin:0;max-width:100%;">
                <aside class="rt-admin-sidebar">
                    <div class="rt-admin-box">
                        <h3>Customer Threads</h3>
                        <input type="text" id="rtThreadSearch" class="rt-admin-search" placeholder="Search threads..." />
                        <div class="rt-thread-list" id="rtThreadList">
                            <div style="padding:12px;color:#999">Loading threads...</div>
                        </div>
                    </div>
                </aside>
                <main class="rt-admin-main">
                    <div class="rt-admin-box">
                        <h3>Conversation</h3>
                        <div class="rt-admin-conv" id="rtAdminConv"></div>
                        <div class="rt-input-row"><input type="text" id="rtAdminMsg" placeholder="Type a reply..." /><button id="rtAdminSend" class="rt-btn rt-btn-primary"><span class="material-symbols-rounded" style="vertical-align:middle;font-size:18px">send</span> Send</button></div>
                    </div>
                    <div class="rt-admin-box">
                        <h3>Manage FAQs (Auto-Reply System)</h3>
                        <div class="rt-input-row">
                            <input type="text" id="rtFaqQ" placeholder="Question (e.g., Do you deliver?)" style="flex:1" />
                            <input type="text" id="rtFaqA" placeholder="Answer (e.g., Yes, we deliver within Cavite...)" style="flex:2" />
                            <button id="rtFaqSave" class="rt-btn rt-btn-primary"><span class="material-symbols-rounded" style="vertical-align:middle;font-size:18px">save</span> Save FAQ</button>
                        </div>
                        <table class="rt-faq-table">
                            <thead>
                                <tr>
                                    <th>Question</th>
                                    <th style="width:180px">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="rtFaqTbody">
                                <tr>
                                    <td colspan="2" style="text-align:center;padding:20px;color:#999">Loading FAQs...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </main>
            </div>
        </section>

        <!-- PAYMENT VERIFICATION -->
        <section class="main-section" data-section="payment">
            <div class="section-header">
                <h1>Payment Verification</h1>
            </div>
            <div class="order-controls">
                <input type="text" class="order-search" id="order-search" placeholder="Search orders..." />
                <select class="order-filter" id="paymentFilter">
                    <option value="">All Payment Status</option>
                    <option value="APPROVED">Approved</option>
                    <option value="PENDING">Pending</option>
                    <option value="REJECTED">Rejected</option>
                </select>
            </div>
            <div class="payments-table-container">
                <table class="payments-table">
                    <thead>
                        <tr>
                            <th>Order Code</th>
                            <th>Customer</th>
                            <th>Amount Paid</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="paymentsTableBody">
                        <tr>
                            <td colspan="7" style="text-align:center">Loading payments...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Payment Details Modal -->
        <div class="modal" id="paymentDetailsModal">
            <div class="modal-content" style="max-width:900px;">
                <div class="modal-header">
                    <h2>Payment Verification Details</h2><button class="modal-close" onclick="closeModal('paymentDetailsModal')">×</button>
                </div>
                <div id="paymentDetailsContent" style="display:grid;gap:1.5rem;padding:16px">
                    <!-- populated by JS -->
                </div>
                <div class="modal-actions">
                    <button class="btn-secondary" onclick="closeModal('paymentDetailsModal')">Close</button>
                    <button id="btnRejectPayment" class="btn-danger">Reject</button>
                    <button id="btnApprovePayment" class="btn-primary">Approve</button>
                </div>
            </div>
        </div>

        <!-- Reject Reason Modal -->
        <div class="modal" id="rejectReasonModal">
            <div class="modal-content" style="max-width:500px;">
                <div class="modal-header">
                    <h2>Reject Payment</h2><button class="modal-close" onclick="closeModal('rejectReasonModal')">×</button>
                </div>
                <div style="padding:16px">
                    <p>Please provide a reason for rejecting this payment:</p>
                    <textarea id="rejectReason" rows="4" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px"></textarea>
                </div>
                <div class="modal-actions">
                    <button class="btn-secondary" onclick="closeModal('rejectReasonModal')">Cancel</button>
                    <button id="btnConfirmReject" class="btn-danger">Confirm Reject</button>
                </div>
            </div>
        </div>

        <!-- Edit Profile Modal -->
        <div class="modal" id="editProfileModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Edit Profile</h2><button class="modal-close" onclick="closeModal('editProfileModal')">×</button>
                </div>
                <form id="editProfileForm" style="padding:16px">
                    <div class="edit-profile-pic-group" style="display:flex;flex-direction:column;align-items:center;margin-bottom:1rem">
                        <img id="editProfileAvatar" class="edit-profile-avatar" src="/assets/images/profile.png" alt="Avatar" />
                        <label class="edit-pic-label" for="editProfilePic" style="cursor:pointer;margin-top:6px">Change Photo</label>
                        <input id="editProfilePic" type="file" accept="image/*" hidden />
                    </div>
                    <input id="ep-fullname" name="full_name" placeholder="Full Name" required style="width:100%;padding:10px;margin-bottom:8px" />
                    <input id="ep-username" name="username" placeholder="Username" required style="width:100%;padding:10px;margin-bottom:8px" />
                    <div style="display:flex;gap:.5rem;justify-content:flex-end"><button type="submit" class="btn-primary">Save Changes</button></div>
                </form>
            </div>
        </div>

        <!-- Change Password Modal -->
        <div class="modal" id="changePasswordModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Change Password</h2><button class="modal-close" onclick="closeModal('changePasswordModal')">×</button>
                </div>
                <form id="changePasswordForm" style="padding:16px">
                    <input id="cp-old" type="password" placeholder="Current Password" required style="width:100%;padding:10px;margin-bottom:8px" />
                    <input id="cp-new" type="password" placeholder="New Password" required style="width:100%;padding:10px;margin-bottom:8px" />
                    <input id="cp-confirm" type="password" placeholder="Confirm New Password" required style="width:100%;padding:10px;margin-bottom:8px" />
                    <div style="display:flex;gap:.5rem;justify-content:flex-end"><button type="submit" class="btn-primary">Update Password</button></div>
                </form>
            </div>
        </div>

        <!-- Add User Modal -->
        <div class="modal" id="addUserModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Add Staff/Admin</h2><button class="modal-close" onclick="closeModal('addUserModal')">×</button>
                </div>
                <form id="addUserForm" style="padding:16px">
                    <input id="au-username" name="username" placeholder="Username" required style="width:100%;padding:10px;margin-bottom:8px" />
                    <input id="au-fullname" name="full_name" placeholder="Full Name" required style="width:100%;padding:10px;margin-bottom:8px" />
                    <select id="au-role" name="role" required style="width:100%;padding:10px;margin-bottom:8px">
                        <option value="">Select Role</option>
                        <option value="Owner">Owner</option>
                        <option value="Admin">Admin</option>
                        <option value="Secretary">Secretary</option>
                    </select>
                    <input id="au-password" type="password" name="password" placeholder="Temporary Password" required style="width:100%;padding:10px;margin-bottom:8px" />
                    <div style="display:flex;gap:.5rem;justify-content:flex-end"><button type="submit" class="btn-primary">Create User</button></div>
                </form>
            </div>
        </div>

        <!-- View Order Modal
        <div class="modal" id="viewOrderModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Order Details</h2><button class="modal-close" onclick="closeModal('viewOrderModal')">×</button>
                </div>
                <div style="padding:16px">
                    <p>Order: <span id="vo-code">—</span></p>
                    <p>Customer: <span id="vo-customer">—</span></p>
                    <p>Date: <span id="vo-date">—</span></p>
                    <p>Total: <span id="vo-total">₱0</span></p>
                    <p>Status: <span id="vo-status">—</span></p>
                    <p>Payment: <span id="vo-payment">—</span></p>
                </div>
            </div>
        </div>-->

        <!-- Confirm Modal -->
        <div class="modal" id="confirmModal">
            <div class="modal-content" style="max-width:360px;text-align:center">
                <div class="modal-header">
                    <h2 id="confirmTitle">Confirm</h2><button class="modal-close" onclick="closeModal('confirmModal')">×</button>
                </div>
                <div style="padding:16px">
                    <p id="confirmMessage">Are you sure?</p><button id="confirmOkBtn" class="btn-primary" type="button">OK</button>
                </div>
            </div>
        </div>

        <!-- Content Management Edit Modal (full editor + preview) -->
        <div class="modal" id="editModal">
            <div class="modal-content" style="max-width:1200px">
                <div class="modal-header">
                    <h2 id="modalTitle">Edit Content</h2><button class="modal-close" id="btnCloseModal" onclick="closeModal('editModal')">×</button>
                </div>
                <div style="padding:0">
                    <div class="editor-layout">
                        <div class="editor-panel">
                            <div class="editor-header">
                                <h3>Edit Fields</h3>
                                <div class="editor-status"><span id="previewStatus" class="preview-status status-published">Published</span></div>
                            </div>
                            <div id="customerNotice" class="alert alert-info hidden" style="padding:12px"><span class="material-symbols-rounded">info</span>
                                <p>Use <code>{{customer_name}}</code> as placeholder for customer's name</p>
                            </div>
                            <div id="editorContainer" class="editor-fields" style="padding:20px">
                                <!-- injected by JS -->
                            </div>
                        </div>
                        <div class="preview-panel">
                            <div class="preview-header">
                                <h3>Live Preview</h3>
                            </div>
                            <div class="preview-iframe-container"><iframe id="livePreviewIframe" frameborder="0"></iframe></div>
                        </div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button id="btnDiscard" class="btn-secondary">Discard Changes</button>
                    <button id="btnSaveDraft" class="btn-primary">Save Draft</button>
                    <button id="btnPublish" class="btn-success">Publish</button>
                </div>
            </div>
        </div>

        <!-- Toast container -->
        <div id="toastContainer" class="toast-container"></div>

        <!-- Add Product Modal (merged) -->
        <div id="addProductModal" class="modal">
            <div class="modal-content" style="max-width:800px">
                <div class="modal-header">
                    <h2 id="addProductModalTitle">Add New Product</h2><button class="close-modal" onclick="closeModal('addProductModal')">×</button>
                </div>
                <form id="addProductForm" style="padding:16px">
                    <div class="form-row">
                        <div class="form-group"><label for="productName">Product Name *</label><input type="text" id="productName" required></div>
                        <div class="form-group"><label for="productType">Type *</label><select id="productType" required>
                                <option value="">Select Type</option>
                                <option value="Kitchen Cabinet">Kitchen Cabinet</option>
                                <option value="Wardrobe">Wardrobe</option>
                                <option value="Office Cabinet">Office Cabinet</option>
                                <option value="Bathroom Cabinet">Bathroom Cabinet</option>
                                <option value="Storage Cabinet">Storage Cabinet</option>
                            </select></div>
                    </div>
                    <div class="form-group"><label for="productDescription">Description</label><textarea id="productDescription" rows="3"></textarea></div>
                    <div class="form-row">
                        <div class="form-group"><label for="productPrice">Base Price (₱)</label><input type="number" id="productPrice" step="0.01" value="0"></div>
                        <div class="form-group"><label class="customizable-inline"><input type="checkbox" id="isCustomizable"><span>This product is customizable (3D)</span></label></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="productImage">
                                Product Images (Multiple)
                                <span style="color: #666; font-size: 12px; font-weight: normal;">
                                    - First image will be the primary display image
                                </span>
                            </label>
                            <input type="file" id="productImage" accept="image/*" multiple>
                            <div id="imagePreviewContainer" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px;"></div>
                            <img id="productImagePreview" style="display: none;">
                        </div>
                        <div class="form-group"><label for="productModel">3D Model (.glb)</label>
                            <div class="pm-filebox"><input type="file" id="productModel" accept=".glb,model/gltf-binary" disabled></div>
                            <div id="productModelPreview" style="margin-top:10px;display:none;color:#28a745"></div>
                        </div>
                    </div>
                    <div style="background:#e8f4f8;padding:15px;border-radius:8px;margin-top:15px">
                        <p style="margin:0;font-size:14px;color:#555"><strong>Note:</strong> After creating the product, use "Manage Customization Options" to assign textures, colors, and handles.</p>
                    </div>
                    <div class="modal-actions"><button type="button" class="btn-secondary" id="addProductCancelBtn" onclick="closeModal('addProductModal')">Cancel</button><button type="submit" class="btn-primary">Update Product</button></div>
                </form>
            </div>
        </div>

        <!-- Manage Customization Modal (merged version) -->
        <div id="manageCustomizationModal" class="modal">
            <div class="modal-content" style="max-width:900px">
                <div class="modal-header">
                    <h2>Manage Customization Options</h2><button class="close-modal" onclick="closeModal('manageCustomizationModal')">×</button>
                </div>
                <div class="modal-body-scrollable">
                    <input type="hidden" id="customProductId">
                    <div style="background:#e8f4f8;padding:15px;border-radius:8px;margin-bottom:20px">
                        <h3 style="margin:0;color:#2c5f8d">Product: <span id="customProductName"></span></h3>
                    </div>

                    <!-- tabs -->
                    <div class="customization-tabs" style="display:flex;gap:8px;margin-bottom:12px">
                        <button type="button" class="tab-btn active" onclick="switchCustomTab('size', event)">Size Sliders</button>
                        <button type="button" class="tab-btn" onclick="switchCustomTab('texture', event)">Textures</button>
                        <button type="button" class="tab-btn" onclick="switchCustomTab('color', event)">Colors</button>
                        <button type="button" class="tab-btn" onclick="switchCustomTab('handle', event)">Handles</button>
                    </div>

                    <!-- Size tab content -->
                    <div id="sizeTabContent" class="tab-content">
                        <h3 style="margin-bottom:15px">Size Slider Configuration</h3>

                        <!-- WIDTH -->
                        <div style="background:#f8f9fa;padding:15px;border-radius:8px;margin-bottom:15px">
                            <h4 style="margin-bottom:10px">Width Slider</h4>
                            <div class="form-row">
                                <div class="form-group"><label>Min Value</label><input type="number" id="widthMinCustom" step="0.1" value="0"></div>
                                <div class="form-group"><label>Max Value</label><input type="number" id="widthMaxCustom" step="0.1" value="300"></div>
                            </div>

                            <hr style="margin:10px 0;border:0;border-top:1px solid #ddd">
                            <div style="display:flex;gap:20px;align-items:center;margin-bottom:8px">
                                <label><input type="radio" name="widthPricingMode" value="percm" checked> Per cm</label>
                                <label><input type="radio" name="widthPricingMode" value="block"> Per block</label>
                            </div>

                            <div id="widthPerCmFields">
                                <div class="form-row">
                                    <div class="form-group"><label>Price per Unit (₱)</label><input type="number" id="widthPPU" step="0.01" value="0"></div>
                                </div>
                            </div>

                            <div id="widthBlockFields" style="display:none">
                                <div class="form-row">
                                    <div class="form-group"><label>Block Size (cm)</label><input type="number" id="widthBlockCM" step="0.1" value="10"></div>
                                    <div class="form-group"><label>Price per Block (₱)</label><input type="number" id="widthPerBlock" step="0.01" value="200"></div>
                                </div>
                                <small style="color:#666">Note: block size is always defined in centimeters.</small>
                            </div>
                        </div>

                        <!-- HEIGHT -->
                        <div style="background:#f8f9fa;padding:15px;border-radius:8px;margin-bottom:15px">
                            <h4 style="margin-bottom:10px">Height Slider</h4>
                            <div class="form-row">
                                <div class="form-group"><label>Min Value</label><input type="number" id="heightMinCustom" step="0.1" value="0"></div>
                                <div class="form-group"><label>Max Value</label><input type="number" id="heightMaxCustom" step="0.1" value="300"></div>
                            </div>
                            <hr style="margin:10px 0;border:0;border-top:1px solid #ddd">
                            <div style="display:flex;gap:20px;align-items:center;margin-bottom:8px">
                                <label><input type="radio" name="heightPricingMode" value="percm" checked> Per cm</label>
                                <label><input type="radio" name="heightPricingMode" value="block"> Per block</label>
                            </div>
                            <div id="heightPerCmFields">
                                <div class="form-row">
                                    <div class="form-group"><label>Price per Unit (₱)</label><input type="number" id="heightPPU" step="0.01" value="0"></div>
                                </div>
                            </div>
                            <div id="heightBlockFields" style="display:none">
                                <div class="form-row">
                                    <div class="form-group"><label>Block Size (cm)</label><input type="number" id="heightBlockCM" step="0.1" value="10"></div>
                                    <div class="form-group"><label>Price per Block (₱)</label><input type="number" id="heightPerBlock" step="0.01" value="300"></div>
                                </div><small style="color:#666">Note: block size is always defined in centimeters.</small>
                            </div>
                        </div>

                        <!-- DEPTH -->
                        <div style="background:#f8f9fa;padding:15px;border-radius:8px;margin-bottom:15px">
                            <h4 style="margin-bottom:10px">Depth Slider</h4>
                            <div class="form-row">
                                <div class="form-group"><label>Min Value</label><input type="number" id="depthMinCustom" step="0.1" value="0"></div>
                                <div class="form-group"><label>Max Value</label><input type="number" id="depthMaxCustom" step="0.1" value="300"></div>
                            </div>
                            <hr style="margin:10px 0;border:0;border-top:1px solid #ddd">
                            <div style="display:flex;gap:20px;align-items:center;margin-bottom:8px">
                                <label><input type="radio" name="depthPricingMode" value="percm" checked> Per cm</label>
                                <label><input type="radio" name="depthPricingMode" value="block"> Per block</label>
                            </div>
                            <div id="depthPerCmFields">
                                <div class="form-row">
                                    <div class="form-group"><label>Price per Unit (₱)</label><input type="number" id="depthPPU" step="0.01" value="0"></div>
                                </div>
                            </div>
                            <div id="depthBlockFields" style="display:none">
                                <div class="form-row">
                                    <div class="form-group"><label>Block Size (cm)</label><input type="number" id="depthBlockCM" step="0.1" value="10"></div>
                                    <div class="form-group"><label>Price per Block (₱)</label><input type="number" id="depthPerBlock" step="0.01" value="150"></div>
                                </div><small style="color:#666">Note: block size is always defined in centimeters.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Texture tab -->
                    <div id="textureTabContent" class="tab-content hidden" style="padding:8px;background:#f8f9fa;border-radius:8px">
                        <h3>Select Available Textures</h3>
                        <p style="color:#666">Check the textures that should be available for this product</p>
                        <div id="texturesListContainer" style="max-height:400px;overflow-y:auto;border:1px solid #ddd;padding:15px;border-radius:8px;background:#f8f9fa"></div>
                        <div style="margin-top:15px"><button type="button" class="btn-secondary" onclick="openAddTextureModal()"><span class="material-symbols-rounded">add</span> Add New Texture</button></div>
                    </div>

                    <!-- Color tab -->
                    <div id="colorTabContent" class="tab-content hidden" style="padding:8px">
                        <h3>Select Available Colors</h3>
                        <p style="color:#666">Check the colors that should be available for this product</p>
                        <div id="colorsListContainer" style="max-height:400px;overflow-y:auto;border:1px solid #ddd;padding:15px;border-radius:8px;background:#f8f9fa"></div>
                        <div style="margin-top:15px"><button type="button" class="btn-secondary" onclick="openAddColorModal()"><span class="material-symbols-rounded">add</span> Add New Color</button></div>
                    </div>

                    <!-- Handle tab -->
                    <div id="handleTabContent" class="tab-content hidden" style="padding:8px">
                        <h3>Select Available Handles</h3>
                        <p style="color:#666">Check the handle types that should be available for this product</p>
                        <div id="handlesListContainer" style="max-height:400px;overflow-y:auto;border:1px solid #ddd;padding:15px;border-radius:8px;background:#f8f9fa"></div>
                        <div style="margin-top:15px"><button type="button" class="btn-secondary" onclick="openAddHandleModal()"><span class="material-symbols-rounded">add</span> Add New Handle</button></div>
                    </div>

                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('manageCustomizationModal')">Cancel</button>
                    <button type="button" class="btn-primary" onclick="saveCustomizationOptions()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Texture / Color / Handle Modals (merged versions) -->
    <div id="addTextureModal" class="modal">
        <div class="modal-content" style="max-width:600px">
            <div class="modal-header">
                <h2>Add New Texture</h2><button class="close-modal" onclick="closeModal('addTextureModal')">×</button>
            </div>
            <div class="modal-body-scrollable">
                <form id="addTextureForm" onsubmit="handleAddTexture(event)">
                    <div class="form-group"><label for="textureName">Texture Name *</label><input type="text" id="textureName" required placeholder="e.g., Oak Wood, Marble White"></div>
                    <div class="form-group"><label for="textureCode">Texture Code *</label><input type="text" id="textureCode" required placeholder="e.g., WOOD_OAK, MARBLE_WHITE"><small style="color:#666">Use uppercase with underscores</small></div>
                    <div class="form-group"><label for="textureDescription">Description</label><textarea id="textureDescription" rows="3"></textarea></div>
                    <div class="form-group"><label for="textureBasePrice">Base Price (₱)</label><input type="number" id="textureBasePrice" step="0.01" value="0"></div>
                    <div class="form-group" style="margin-top:14px;">
                        <label style="font-weight:600; display:block; margin-bottom:8px;">Allowed Parts</label>
                        <div style="display: grid;grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));gap: 10px;text-align: center;padding: 10px 12px;border: 1px solid #ddd;border-radius: 8px;background: #f9fafb;">
                            <label style="display:flex;flex-direction:column;align-items:center;font-size:13px;color:#333;">
                                <input type="checkbox" name="textureParts" value="body" style="margin-bottom:4px;">
                                Body / Frame
                            </label>
                            <label style="display:flex;flex-direction:column;align-items:center;font-size:13px;color:#333;">
                                <input type="checkbox" name="textureParts" value="door" style="margin-bottom:4px;">
                                Door
                            </label>
                            <label style="display:flex;flex-direction:column;align-items:center;font-size:13px;color:#333;">
                                <input type="checkbox" name="textureParts" value="interior" style="margin-bottom:4px;">
                                Interior
                            </label>
                        </div>
                        <small style="color:#666; display:block; margin-top:6px;">
                            Select where this texture may be applied.
                        </small>
                    </div>
                    <div class="form-group"><label for="textureImage">Texture Image *</label><input type="file" id="textureImage" accept="image/*" required onchange="handleTextureImagePreview(event)"><img id="textureImagePreview" style="max-width:200px;margin-top:10px;display:none;border-radius:8px"></div>
                    <div class="form-group" style="display:flex;align-items:flex-start;gap:12px;margin-top:14px;">
                        <div style="display:flex;flex-direction:column;align-items:center;justify-content:flex-start;">
                            <input type="checkbox" id="textureIsActive" checked style="transform:scale(1.05);margin:4px 0 0 0;">
                        </div>
                        <div style="flex:1;">
                            <label for="textureIsActive" style="font-size:14px;color:#333;display:block;margin-top:2px;">Active (Available for selection)</label>
                            <small style="color:#666;display:block;margin-top:6px;">Uncheck this to temporarily hide the texture from customers.</small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('addTextureModal')">Cancel</button>
                <button type="submit" form="addTextureForm" class="btn-primary">Add Texture</button>
            </div>
        </div>
    </div>

    <div id="addColorModal" class="modal">
        <div class="modal-content" style="max-width:600px">
            <div class="modal-header">
                <h2>Add New Color</h2>
                <button class="close-modal" onclick="closeModal('addColorModal')">×</button>
            </div>
            <div class="modal-body-scrollable">
                <form id="addColorForm" onsubmit="handleAddColor(event)">
                    <div class="form-group">
                        <label for="colorName">Color Name *</label>
                        <input type="text" id="colorName" required placeholder="e.g., Matte Black, Glossy White">
                    </div>
                    <div class="form-group">
                        <label for="colorCode">Color Code *</label>
                        <input type="text" id="colorCode" required placeholder="e.g., BLACK_MATTE, WHITE_GLOSSY">
                        <small style="color:#666">Use uppercase with underscores</small>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="colorHex">Hex Color Value *</label>
                            <input type="color" id="colorHex" value="#000000" required style="height:50px">
                        </div>
                        <div class="form-group">
                            <label for="colorHexText">Hex Code</label>
                            <input type="text" id="colorHexText" value="#000000" pattern="^#[0-9A-Fa-f]{6}$" placeholder="#000000">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="colorBasePrice">Base Price (₱)</label>
                        <input type="number" id="colorBasePrice" step="0.01" value="0">
                    </div>

                    <!-- CORRECTED: name="colorParts" -->
                    <div class="form-group">
                        <label style="font-weight:600; display:block; margin-bottom:8px;">Allowed Parts</label>
                        <div style="display: grid;grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));gap: 10px;text-align: center;padding: 10px 12px;border: 1px solid #ddd;border-radius: 8px;background: #f9fafb;">
                            <label style="display:flex;flex-direction:column;align-items:center;font-size:13px;color:#333;">
                                <input type="checkbox" name="colorParts" value="body" checked style="margin-bottom:4px;">
                                Body / Frame
                            </label>
                            <label style="display:flex;flex-direction:column;align-items:center;font-size:13px;color:#333;">
                                <input type="checkbox" name="colorParts" value="door" checked style="margin-bottom:4px;">
                                Door
                            </label>
                            <label style="display:flex;flex-direction:column;align-items:center;font-size:13px;color:#333;">
                                <input type="checkbox" name="colorParts" value="interior" checked style="margin-bottom:4px;">
                                Interior
                            </label>
                        </div>
                        <small style="color:#666; display:block; margin-top:6px;">
                            Select where this color may be applied.
                        </small>
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-start;gap:12px;margin-top:14px;">
                        <div style="display:flex;flex-direction:column;align-items:center;justify-content:flex-start;">
                            <input type="checkbox" id="colorIsActive" checked style="transform:scale(1.05);margin:4px 0 0 0;">
                        </div>
                        <div style="flex:1;">
                            <label for="colorIsActive" style="font-size:14px;color:#333;display:block;margin-top:2px;">Active (Available for selection)</label>
                            <small style="color:#666;display:block;margin-top:6px;">Uncheck this to temporarily hide the color from customers.</small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('addColorModal')">Cancel</button>
                <button type="submit" form="addColorForm" class="btn-primary">Add Color</button>
            </div>
        </div>
    </div>

    <div id="addHandleModal" class="modal">
        <div class="modal-content" style="max-width:600px">
            <div class="modal-header">
                <h2>Add New Handle Type</h2><button class="close-modal" onclick="closeModal('addHandleModal')">×</button>
            </div>
            <div class="modal-body-scrollable">
                <form id="addHandleForm" onsubmit="handleAddHandle(event)">
                    <div class="form-group"><label for="handleName">Handle Name *</label><input type="text" id="handleName" required placeholder="e.g., Modern Silver, Classic Brass"></div>
                    <div class="form-group"><label for="handleCode">Handle Code *</label><input type="text" id="handleCode" required placeholder="e.g., MODERN_SILVER, CLASSIC_BRASS"><small style="color:#666">Use uppercase with underscores</small></div>
                    <div class="form-group"><label for="handleDescription">Description</label><textarea id="handleDescription" rows="3"></textarea></div>
                    <div class="form-group"><label for="handleBasePrice">Base Price (₱)</label><input type="number" id="handleBasePrice" step="0.01" value="0"></div>
                    <div class="form-group"><label for="handleImage">Handle Image *</label><input type="file" id="handleImage" accept="image/*" required onchange="handleHandleImagePreview(event)"><img id="handleImagePreview" style="max-width:200px;margin-top:10px;display:none;border-radius:8px"></div>
                    <div class="form-group" style="display:flex;align-items:flex-start;gap:12px;margin-top:14px;">
                        <div style="display:flex;flex-direction:column;align-items:center;justify-content:flex-start;">
                            <input type="checkbox" id="handleIsActive" checked style="transform:scale(1.05);margin:4px 0 0 0;">
                        </div>
                        <div style="flex:1;">
                            <label for="handleIsActive" style="font-size:14px;color:#333;display:block;margin-top:2px;">Active (Available for selection)</label>
                            <small style="color:#666;display:block;margin-top:6px;">Uncheck this to temporarily hide the handle from customers.</small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('addHandleModal')">Cancel</button>
                <button type="submit" form="addHandleForm" class="btn-primary">Add Handle</button>
            </div>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="/assets/JS/script.js"></script>
    <script src="/assets/JS/product_management.js"></script>
    <script src="/assets/JS/chat_admin.js"></script>
    <script src="/assets/JS/chat-notification.js"></script>
    <script src="/assets/JS/content_mgmt.js"></script>

    <script>
        // Open modals
        function openAddTextureModal() {
            openModal('addTextureModal');
        }

        function openAddColorModal() {
            openModal('addColorModal');
        }

        function openAddHandleModal() {
            openModal('addHandleModal');
        }

        // Texture Image Preview
        async function handleTextureImagePreview(e) {
            const file = e.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('image', file);

            try {
                const response = await fetch('/backend/api/admin_customization.php?action=upload_texture_image', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    const preview = document.getElementById('textureImagePreview');
                    preview.src = `/uploads/textures/${data.data.filename}`;
                    preview.style.display = 'block';
                    preview.dataset.filename = data.data.filename;
                    showNotification('success', 'Texture image uploaded successfully');
                } else {
                    showNotification('error', data.message);
                }
            } catch (error) {
                console.error('Upload texture image error:', error);
                showNotification('error', 'Failed to upload texture image');
            }
        }
    </script>

    <script>
        // Safe fallback if openModal / closeModal not defined in external scripts
        window.openModal = window.openModal || function(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('show');
        };
        window.closeModal = window.closeModal || function(id) {
            const el = document.getElementById(id);
            if (el) el.classList.remove('show');
        };

        // Hide loading overlay once DOM ready
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                const loadingOverlay = document.getElementById('loadingOverlay');
                if (loadingOverlay) loadingOverlay.style.display = 'none';

                // Initialize content manager if present and content section visible
                const contentSection = document.querySelector('section[data-section="content"]');
                if (contentSection && contentSection.classList.contains('show')) {
                    if (typeof CM !== 'undefined' && typeof CM.init === 'function') {
                        setTimeout(() => CM.init(), 100);
                    }
                }
            }, 300);
        });

        // switchCustomTab now accepts event param
        function switchCustomTab(tabName, event) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            const target = document.getElementById(`${tabName}TabContent`) || document.getElementById(`${tabName}TabContent`);
            // fallback: our IDs are e.g., sizeTabContent, textureTabContent...
            const el = document.getElementById(`${tabName}TabContent`) || document.getElementById(`${tabName}TabContent`);
            // more robust showing:
            const mapping = {
                size: document.getElementById('sizeTabContent'),
                texture: document.getElementById('textureTabContent'),
                color: document.getElementById('colorTabContent'),
                handle: document.getElementById('handleTabContent')
            };
            if (mapping[tabName]) mapping[tabName].classList.remove('hidden');
            if (event && event.target) event.target.classList.add('active');
        }

        // Pricing toggles binder (works for width/height/depth)
        function bindPricingToggles(dim) {
            const radios = document.querySelectorAll(`input[name="${dim}PricingMode"]`);
            const percm = document.getElementById(`${dim}PerCmFields`);
            const block = document.getElementById(`${dim}BlockFields`);
            const sync = () => {
                const mode = [...radios].find(r => r.checked)?.value || 'percm';
                if (percm) percm.style.display = (mode === 'percm' ? '' : 'none');
                if (block) block.style.display = (mode === 'block' ? '' : 'none');
            };
            radios.forEach(r => r.addEventListener('change', sync));
            sync();
        }
        ['width', 'height', 'depth'].forEach(bindPricingToggles);

        // Image upload previews & add handlers (kept as in original merged)
        async function handleTextureImagePreview(e) {
            const file = e.target.files[0];
            if (!file) return;
            const formData = new FormData();
            formData.append('image', file);
            try {
                const response = await fetch('/backend/api/admin_customization.php?action=upload_texture_image', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    const preview = document.getElementById('textureImagePreview');
                    if (preview) {
                        preview.src = `/uploads/textures/${data.data.filename}`;
                        preview.style.display = 'block';
                        preview.dataset.filename = data.data.filename;
                    }
                    showNotification('success', 'Texture image uploaded successfully');
                } else {
                    showNotification('error', data.message || 'Upload failed');
                }
            } catch (err) {
                console.error(err);
                showNotification('error', 'Failed to upload texture image');
            }
        }

        async function handleHandleImagePreview(e) {
            const file = e.target.files[0];
            if (!file) return;
            const formData = new FormData();
            formData.append('image', file);
            try {
                const response = await fetch('/backend/api/admin_customization.php?action=upload_handle_image', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    const preview = document.getElementById('handleImagePreview');
                    if (preview) {
                        preview.src = `/uploads/handles/${data.data.filename}`;
                        preview.style.display = 'block';
                        preview.dataset.filename = data.data.filename;
                    }
                    showNotification('success', 'Handle image uploaded successfully');
                } else {
                    showNotification('error', data.message || 'Upload failed');
                }
            } catch (err) {
                console.error(err);
                showNotification('error', 'Failed to upload handle image');
            }
        }

        // Add texture/color/handle handlers (merged)
        async function handleAddTexture(e) {
            e.preventDefault();

            const allowedParts = Array.from(document.querySelectorAll('input[name="textureParts"]:checked')).map(cb => cb.value);

            const formData = {
                texture_name: document.getElementById('textureName').value,
                texture_code: document.getElementById('textureCode').value,
                description: document.getElementById('textureDescription').value,
                base_price: parseFloat(document.getElementById('textureBasePrice').value) || 0,
                is_active: document.getElementById('textureIsActive').checked ? 1 : 0,
                texture_image: document.getElementById('textureImagePreview')?.dataset?.filename || '',
                allowed_parts: allowedParts
            };
            try {
                const res = await fetch('/backend/api/admin_customization.php?action=add_texture', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });
                const data = await res.json();
                if (data.success) {
                    showNotification('success', 'Texture added successfully');
                    closeModal('addTextureModal');
                    document.getElementById('addTextureForm').reset();
                    // Reload textures AND refresh the UI list
                    if (typeof loadTextures === 'function') {
                        await loadTextures();
                        // Refresh the texture list in the current product modal if open
                        if (window.currentProduct && typeof populateTexturesList === 'function') {
                            const productTextures = window.currentProduct.textures || window.currentProduct.raw_textures || [];
                            populateTexturesList(productTextures);
                        }
                    }
                } else showNotification('error', data.message || 'Failed to add texture');
            } catch (err) {
                console.error(err);
                showNotification('error', 'Failed to add texture');
            }
        }

        function handleAddColor(event) {
            event.preventDefault();

            // Collect parts
            const partCheckboxes = document.querySelectorAll('input[name="colorParts"]:checked');
            const allowedParts = Array.from(partCheckboxes).map(cb => cb.value);

            const colorData = {
                color_name: document.getElementById('colorName').value.trim(),
                color_code: document.getElementById('colorCode').value.trim(),
                hex_value: document.getElementById('colorHex').value,
                base_price: parseFloat(document.getElementById('colorBasePrice').value || 0),
                is_active: document.getElementById('colorIsActive').checked ? 1 : 0,
                allowed_parts: allowedParts
            };

            console.log('Sending color data:', colorData);

            fetch('/backend/api/admin_customization.php?action=add_color', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(colorData),
                    credentials: 'same-origin'
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('Color added!');
                        closeModal('addColorModal');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(err => {
                    console.error('Add color error:', err);
                    alert('Failed to add color');
                });
        }

        async function handleAddHandle(e) {
            e.preventDefault();
            const formData = {
                handle_name: document.getElementById('handleName').value,
                handle_code: document.getElementById('handleCode').value,
                description: document.getElementById('handleDescription').value,
                base_price: parseFloat(document.getElementById('handleBasePrice').value) || 0,
                is_active: document.getElementById('handleIsActive').checked ? 1 : 0,
                handle_image: document.getElementById('handleImagePreview')?.dataset?.filename || ''
            };
            try {
                const res = await fetch('/backend/api/admin_customization.php?action=add_handle', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });
                const data = await res.json();
                if (data.success) {
                    showNotification('success', 'Handle added successfully');
                    closeModal('addHandleModal');
                    document.getElementById('addHandleForm').reset();
                    // Reload handles AND refresh the UI list
                    if (typeof loadHandles === 'function') {
                        await loadHandles();
                        // Refresh the handle list in the current product modal if open
                        if (window.currentProduct && typeof populateHandlesList === 'function') {
                            const productHandles = window.currentProduct.handles || window.currentProduct.raw_handles || [];
                            populateHandlesList(productHandles);
                        }
                    }
                } else showNotification('error', data.message || 'Failed to add handle');
            } catch (err) {
                console.error(err);
                showNotification('error', 'Failed to add handle');
            }
        }

        // Sync color picker/text
        document.getElementById('colorHex')?.addEventListener('input', (e) => {
            const t = document.getElementById('colorHexText');
            if (t) t.value = e.target.value;
        });
        document.getElementById('colorHexText')?.addEventListener('input', (e) => {
            if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) document.getElementById('colorHex').value = e.target.value;
        });

        // Minimal notification helper (uses toast container)
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
            setTimeout(() => {
                t.classList.remove('show');
                t.remove();
            }, 3500);
        }
    </script>
</body>

</html>