/**
 * Chat Notification System - Admin Dashboard
 * Shows red dot when new customer messages need response
 */
(function () {
    const chatNav = document.querySelector('.nav-item[data-section="chat"]');

    if (!chatNav) {
        console.log('Chat nav not found - not on admin dashboard');
        return;
    }

    const API_BASE = '/RADS-TOOLING/backend/api/chat.php';
    let checkInterval = null;

    async function checkUnreadMessages() {
        try {
            const response = await fetch(`${API_BASE}?action=threads`, {
                credentials: 'same-origin'
            });

            if (!response.ok) {
                console.error('Failed to fetch threads:', response.status);
                return;
            }

            const result = await response.json();

            if (result.success && result.data) {
                // Check if ANY thread has unanswered messages
                const hasUnread = result.data.some(t => t.has_unread);

                let dot = chatNav.querySelector('.rt-notification-dot');

                if (hasUnread && !dot) {
                    // Add notification dot
                    dot = document.createElement('span');
                    dot.className = 'rt-notification-dot';
                    chatNav.style.position = 'relative';
                    chatNav.appendChild(dot);
                    console.log('Added notification dot - unread messages found');
                } else if (!hasUnread && dot) {
                    // Remove notification dot
                    dot.remove();
                    console.log('Removed notification dot - no unread messages');
                }
            }
        } catch (error) {
            console.error('Failed to check unread messages:', error);
        }
    }

    // Check immediately on load
    checkUnreadMessages();

    // Check every 10 seconds
    checkInterval = setInterval(checkUnreadMessages, 10000);

    // When navigating to chat section, check again after 2 seconds
    chatNav.addEventListener('click', () => {
        setTimeout(checkUnreadMessages, 2000);
    });

    console.log('Chat notification system initialized');
})();