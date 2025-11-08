// ==========================================
// ORDERS PAGE - JavaScript Functions
// WITH ORDER_ADDRESSES AND TAX CALCULATION
// ==========================================

let allOrders = [];
let currentFilter = 'all';

// ==========================================
// LOAD CUSTOMER ORDERS
// ==========================================
async function loadCustomerOrders(status = 'all') {
    currentFilter = status;
    window.__ordersCurrentFilter = status;
    
    const container = document.getElementById('ordersContainer');
    if (!container) return;

    // Show loading state
    container.innerHTML = `
        <div class="loading-state">
            <div class="spinner"></div>
            <p>Loading your orders...</p>
        </div>
    `;

    try {
        // For loading list
const response = await fetch(`/backend/api/customer_orders.php?status=${status}`);

        const data = await response.json();

        if (data.success) {
            allOrders = data.orders || [];
            updateBadgeCounts(data.counts || {});
            renderOrders(allOrders);
        } else {
            showError(data.message || 'Failed to load orders');
        }
    } catch (error) {
        console.error('Error loading orders:', error);
        showError('Network error. Please try again.');
    }
}

// ==========================================
// RENDER ORDERS
// ==========================================
function renderOrders(orders) {
    const container = document.getElementById('ordersContainer');
    if (!container) return;

    if (!orders || orders.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <span class="material-symbols-rounded">shopping_bag</span>
                <p>No orders found</p>
            </div>
        `;
        return;
    }

    container.innerHTML = orders.map(order => createOrderCard(order)).join('');
}

// ==========================================
// CREATE ORDER CARD
// ==========================================
function createOrderCard(order) {
    const statusClass = `status-${order.status.toLowerCase()}`;
    const statusIcon = getStatusIcon(order.status);
    
    const items = order.items || [];
    const itemsHtml = items.slice(0, 3).map(item => `
        <div class="order-item">
            <img src="${item.image || '/assets/images/cab1.jpg'}" 
                 alt="${escapeHtml(item.name)}" 
                 class="item-thumbnail"
                 onerror="this.src='/assets/images/cab1.jpg'">
            <div class="item-info">
                <div class="item-name">${escapeHtml(item.name)}</div>
                <div class="item-meta">Qty: ${item.quantity}</div>
            </div>
            <div class="item-price">₱${formatNumber(item.subtotal)}</div>
        </div>
    `).join('');

    const moreItems = items.length > 3 ? `
        <div style="text-align: center; padding: 0.5rem; color: var(--gray-600); font-size: 0.875rem;">
            +${items.length - 3} more item${items.length - 3 > 1 ? 's' : ''}
        </div>
    ` : '';

    const isCompleted = order.status.toLowerCase() === 'completed';
    const hasFeedback = order.has_feedback === true || order.has_feedback === 1;
    
    let actionButtons = '';
    
    if (isCompleted) {
        if (hasFeedback) {
            actionButtons = `
                <button class="btn btn-primary" onclick="viewOrderDetails(${order.id})">
                    <span class="material-symbols-rounded">visibility</span>
                    View Details
                </button>
                <button class="btn btn-feedback-submitted" disabled>
                    <span class="material-symbols-rounded">check_circle</span>
                    Feedback Submitted
                </button>
            `;
        } else {
            actionButtons = `
                <button class="btn btn-primary" onclick="viewOrderDetails(${order.id})">
                    <span class="material-symbols-rounded">visibility</span>
                    View Details
                </button>
                <button class="btn btn-success" onclick="openFeedbackModal(${order.id})">
                    <span class="material-symbols-rounded">check_circle</span>
                    Mark as Received
                </button>
            `;
        }
    } else {
        actionButtons = `
            <button class="btn btn-primary" onclick="viewOrderDetails(${order.id})">
                <span class="material-symbols-rounded">visibility</span>
                View Details
            </button>
        `;
    }

    return `
        <div class="order-card" data-order-id="${order.id}" data-status="${order.status.toLowerCase()}">
            <div class="order-card-header">
                <div class="order-info">
                    <div class="order-code">${escapeHtml(order.order_code)}</div>
                    <div class="order-date">
                        <span class="material-symbols-rounded">schedule</span>
                        ${formatDate(order.order_date)}
                    </div>
                </div>
                <div class="order-status-badge ${statusClass}">
                    <span class="material-symbols-rounded">${statusIcon}</span>
                    ${order.status}
                </div>
            </div>
            <div class="order-card-body">
                <div class="order-items-header">
                    <span class="material-symbols-rounded">shopping_cart</span>
                    Order Items:
                </div>
                <div class="order-items-list">
                    ${itemsHtml}
                    ${moreItems}
                </div>
            </div>
            <div class="order-card-footer">
                <div class="order-total">
                    <div class="total-label">Total Amount</div>
                    <div class="total-amount">₱${formatNumber(order.total_amount)}</div>
                </div>
                <div class="order-actions">
                    ${actionButtons}
                </div>
            </div>
        </div>
    `;
}

// ==========================================
// VIEW ORDER DETAILS
// ==========================================
async function viewOrderDetails(orderId) {
    const modal = document.getElementById('orderDetailsModal');
    const content = document.getElementById('orderDetailsContent');
    
    if (!modal || !content) return;

    content.innerHTML = `
        <div class="loading-state">
            <div class="spinner"></div>
            <p>Loading order details...</p>
        </div>
    `;
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';

    try {
        // For viewing details
const response = await fetch(`/backend/api/customer_orders.php?action=details&id=${orderId}`);

        const data = await response.json();

        if (data.success && data.order) {
            const order = data.order;
            content.innerHTML = renderOrderDetails(order);
        } else {
            content.innerHTML = `<div class="empty-state"><p>${data.message || 'Order not found'}</p></div>`;
        }
    } catch (error) {
        console.error('Error loading order details:', error);
        content.innerHTML = `<div class="empty-state"><p>Failed to load order details</p></div>`;
    }
}

// ==========================================
// RENDER ORDER DETAILS - SIMPLIFIED TABLE
// NEW FORMAT: ITEM | QUANTITY | PRICE | TAX (%)
// ONLY TOTAL AMOUNT AT BOTTOM
// ==========================================
function renderOrderDetails(order) {
    const items = order.items || [];
    const itemsSubtotal = parseFloat(order.items_subtotal || 0);
    const totalAmount = parseFloat(order.total_amount || 0);
    const taxAmount = parseFloat(order.tax_amount || 0);
    
    // Calculate tax percentage
    const taxPercentage = itemsSubtotal > 0 ? ((taxAmount / itemsSubtotal) * 100) : 0;
    
    // Build table rows with new format
    const itemsRows = items.map(item => {
        const itemPrice = parseFloat(item.price || 0);
        const itemQty = parseInt(item.quantity || 0);
        
        return `
            <tr>
                <td>${escapeHtml(item.name)}</td>
                <td>${itemQty}</td>
                <td>₱${formatNumber(itemPrice)}</td>
                <td>${taxPercentage.toFixed(0)}%</td>
            </tr>
        `;
    }).join('');

    // Payment information
    const paymentMethod = order.payment_method || 'N/A';
    const paymentStatus = order.payment_status_text || 'Pending';
    const amountPaid = parseFloat(order.amount_paid || 0);
    const remainingBalance = parseFloat(order.remaining_balance || 0);

    // Delivery address - now properly displays from order_addresses table
    const deliveryAddress = order.delivery_address || 'N/A';
    const deliveryMode = order.mode || 'N/A';

    return `
        <div class="detail-section">
            <div class="detail-section-title">
                <span class="material-symbols-rounded">info</span>
                Order Information
            </div>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Order Code</div>
                    <div class="detail-value">${escapeHtml(order.order_code)}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Order Date</div>
                    <div class="detail-value">${formatDate(order.order_date)}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">${order.status}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Delivery Mode</div>
                    <div class="detail-value">${deliveryMode}</div>
                </div>
            </div>
        </div>

        <div class="detail-section">
            <div class="detail-section-title">
                <span class="material-symbols-rounded">location_on</span>
                Delivery Address
            </div>
            <div class="detail-value" style="white-space: pre-line;">${deliveryAddress}</div>
        </div>

        <div class="detail-section">
            <div class="detail-section-title">
                <span class="material-symbols-rounded">payment</span>
                Payment Information
            </div>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Payment Method</div>
                    <div class="detail-value">${paymentMethod.toUpperCase()}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Payment Status</div>
                    <div class="detail-value">${paymentStatus}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Amount Paid</div>
                    <div class="detail-value">₱${formatNumber(amountPaid)}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Remaining Balance</div>
                    <div class="detail-value" style="color: ${remainingBalance > 0 ? 'var(--danger-color)' : 'var(--success-color)'}; font-weight: 600;">
                        ₱${formatNumber(remainingBalance)}
                    </div>
                </div>
            </div>
        </div>

        <div class="detail-section">
            <div class="detail-section-title">
                <span class="material-symbols-rounded">shopping_cart</span>
                Order Items
            </div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>ITEM</th>
                        <th>QUANTITY</th>
                        <th>PRICE</th>
                        <th>TAX (%)</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsRows}
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right; padding-right: 1rem;"><strong>TOTAL AMOUNT</strong></td>
                        <td><strong>₱${formatNumber(totalAmount)}</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
}

// ==========================================
// CLOSE ORDER DETAILS MODAL
// ==========================================
function closeOrderDetailsModal() {
    const modal = document.getElementById('orderDetailsModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// ==========================================
// FILTER ORDERS
// ==========================================
function filterOrders(status) {
    document.querySelectorAll('.menu-item').forEach(item => {
        item.classList.remove('active');
    });
    document.querySelector(`[data-status="${status}"]`)?.classList.add('active');

    loadCustomerOrders(status);
}

// ==========================================
// SEARCH ORDERS
// ==========================================
function searchOrders() {
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) return;

    const searchTerm = searchInput.value.toLowerCase().trim();

    if (!searchTerm) {
        renderOrders(allOrders);
        return;
    }

    const filtered = allOrders.filter(order => {
        const orderCode = (order.order_code || '').toLowerCase();
        const items = (order.items || []).map(i => (i.name || '').toLowerCase()).join(' ');
        return orderCode.includes(searchTerm) || items.includes(searchTerm);
    });

    renderOrders(filtered);
}

// ==========================================
// UPDATE BADGE COUNTS
// ==========================================
function updateBadgeCounts(counts) {
    const badges = {
        'badge-all': counts.all || 0,
        'badge-pending': counts.pending || 0,
        'badge-processing': counts.processing || 0,
        'badge-completed': counts.completed || 0,
        'badge-cancelled': counts.cancelled || 0
    };

    Object.entries(badges).forEach(([id, count]) => {
        const badge = document.getElementById(id);
        if (badge) badge.textContent = count;
    });
}

// ==========================================
// UTILITY FUNCTIONS
// ==========================================
function getStatusIcon(status) {
    const icons = {
        'pending': 'schedule',
        'processing': 'autorenew',
        'completed': 'check_circle',
        'cancelled': 'cancel'
    };
    return icons[status.toLowerCase()] || 'info';
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatNumber(num) {
    if (!num) return '0.00';
    return parseFloat(num).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showError(message) {
    const container = document.getElementById('ordersContainer');
    if (container) {
        container.innerHTML = `
            <div class="empty-state">
                <span class="material-symbols-rounded">error</span>
                <p>${escapeHtml(message)}</p>
            </div>
        `;
    }
}

// ==========================================
// MODAL CLOSE ON OUTSIDE CLICK
// ==========================================
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        const modal = e.target.closest('.modal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
});

// ==========================================
// INITIALIZE ON PAGE LOAD
// ==========================================
document.addEventListener('DOMContentLoaded', () => {
    loadCustomerOrders('all');
});