<!-- partials/notification_modal.php -->
<!-- Reusable Notification Modal / Toast Component -->
<style>
/* Notification Modal Styles */
.notification-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 99999;
    animation: fadeIn 0.2s ease-in-out;
}

.notification-overlay.show {
    display: flex;
}

.notification-modal {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border-radius: 16px;
    padding: 30px;
    max-width: 450px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
    animation: slideUp 0.3s ease-out;
    position: relative;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.notification-modal.success {
    border-left: 4px solid #10b981;
}

.notification-modal.error {
    border-left: 4px solid #ef4444;
}

.notification-modal.warning {
    border-left: 4px solid #f59e0b;
}

.notification-modal.info {
    border-left: 4px solid #3b82f6;
}

.notification-header {
    display: flex;
    align-items: center;
    margin-bottom: 16px;
    gap: 12px;
}

.notification-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}

.notification-modal.success .notification-icon {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.notification-modal.error .notification-icon {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.notification-modal.warning .notification-icon {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.notification-modal.info .notification-icon {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
}

.notification-title {
    font-size: 20px;
    font-weight: 700;
    color: #f1f5f9;
    margin: 0;
    flex-grow: 1;
}

.notification-body {
    color: #cbd5e1;
    font-size: 15px;
    line-height: 1.6;
    margin-bottom: 24px;
}

.notification-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.notification-btn {
    padding: 10px 24px;
    border-radius: 8px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
}

.notification-btn-primary {
    background: #3b82f6;
    color: white;
}

.notification-btn-primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.notification-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
}

.notification-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Toast Notification Styles */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 999999;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.toast-notification {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border-radius: 12px;
    padding: 16px 20px;
    min-width: 320px;
    max-width: 400px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideInRight 0.3s ease-out;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.toast-notification.success {
    border-left: 4px solid #10b981;
}

.toast-notification.error {
    border-left: 4px solid #ef4444;
}

.toast-notification.warning {
    border-left: 4px solid #f59e0b;
}

.toast-notification.info {
    border-left: 4px solid #3b82f6;
}

.toast-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.toast-notification.success .toast-icon {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.toast-notification.error .toast-icon {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.toast-notification.warning .toast-icon {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.toast-notification.info .toast-icon {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
}

.toast-content {
    flex-grow: 1;
}

.toast-title {
    font-size: 14px;
    font-weight: 600;
    color: #f1f5f9;
    margin: 0 0 4px 0;
}

.toast-message {
    font-size: 13px;
    color: #cbd5e1;
    margin: 0;
}

.toast-close {
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    font-size: 20px;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s;
}

.toast-close:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(100px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slideOutRight {
    from {
        opacity: 1;
        transform: translateX(0);
    }
    to {
        opacity: 0;
        transform: translateX(100px);
    }
}
</style>

<!-- Notification Modal Structure -->
<div id="notificationOverlay" class="notification-overlay">
    <div id="notificationModal" class="notification-modal">
        <div class="notification-header">
            <div class="notification-icon" id="notificationIcon">✓</div>
            <h3 class="notification-title" id="notificationTitle">Success</h3>
        </div>
        <div class="notification-body" id="notificationMessage">
            Operation completed successfully.
        </div>
        <div class="notification-actions">
            <button class="notification-btn notification-btn-primary" id="notificationOkBtn">OK</button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="toast-container"></div>

<script>
// Notification Modal & Toast JavaScript
const NotificationSystem = {
    // Show modal notification
    showModal: function(options) {
        const {
            type = 'info',      // success, error, warning, info
            title = '',
            message = '',
            okText = 'OK',
            onOk = null
        } = options;

        const overlay = document.getElementById('notificationOverlay');
        const modal = document.getElementById('notificationModal');
        const icon = document.getElementById('notificationIcon');
        const titleEl = document.getElementById('notificationTitle');
        const messageEl = document.getElementById('notificationMessage');
        const okBtn = document.getElementById('notificationOkBtn');

        // Set icon based on type
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };

        // Remove old type classes
        modal.classList.remove('success', 'error', 'warning', 'info');
        modal.classList.add(type);

        icon.textContent = icons[type] || icons.info;
        titleEl.textContent = title || type.charAt(0).toUpperCase() + type.slice(1);
        messageEl.textContent = message;
        okBtn.textContent = okText;

        // Show overlay
        overlay.classList.add('show');

        // Handle OK button
        okBtn.onclick = () => {
            overlay.classList.remove('show');
            if (onOk && typeof onOk === 'function') {
                onOk();
            }
        };

        // Close on overlay click
        overlay.onclick = (e) => {
            if (e.target === overlay) {
                overlay.classList.remove('show');
            }
        };
    },

    // Show toast notification
    showToast: function(options) {
        const {
            type = 'info',      // success, error, warning, info
            title = '',
            message = '',
            duration = 5000     // auto-hide after 5 seconds
        } = options;

        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}`;

        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };

        toast.innerHTML = `
            <div class="toast-icon">${icons[type] || icons.info}</div>
            <div class="toast-content">
                ${title ? `<div class="toast-title">${this.escapeHtml(title)}</div>` : ''}
                <div class="toast-message">${this.escapeHtml(message)}</div>
            </div>
            <button class="toast-close">×</button>
        `;

        container.appendChild(toast);

        // Close button handler
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.onclick = () => this.removeToast(toast);

        // Auto-hide after duration
        if (duration > 0) {
            setTimeout(() => this.removeToast(toast), duration);
        }
    },

    removeToast: function(toast) {
        toast.style.animation = 'slideOutRight 0.3s ease-out';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    },

    escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Convenience functions for quick usage
function showNotification(message, type = 'info', title = '') {
    NotificationSystem.showModal({ type, title, message });
}

function showToast(message, type = 'info', title = '') {
    NotificationSystem.showToast({ type, title, message });
}

// Success shortcuts
function showSuccess(message, title = 'Success') {
    NotificationSystem.showModal({ type: 'success', title, message });
}

function showError(message, title = 'Error') {
    NotificationSystem.showModal({ type: 'error', title, message });
}

function showWarning(message, title = 'Warning') {
    NotificationSystem.showModal({ type: 'warning', title, message });
}

function showInfo(message, title = 'Information') {
    NotificationSystem.showModal({ type: 'info', title, message });
}

// Toast shortcuts
function toastSuccess(message, title = 'Success') {
    NotificationSystem.showToast({ type: 'success', title, message });
}

function toastError(message, title = 'Error') {
    NotificationSystem.showToast({ type: 'error', title, message });
}

function toastWarning(message, title = 'Warning') {
    NotificationSystem.showToast({ type: 'warning', title, message });
}

function toastInfo(message, title = '') {
    NotificationSystem.showToast({ type: 'info', title, message });
}
</script>