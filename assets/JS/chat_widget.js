/**
 * RADS-TOOLING Customer Chat Widget - FIXED VERSION
 * Proper thread isolation, database clear, FAQ matching
 */
(function () {
  if (window.__RT_CHAT_WIDGET_LOADED__) return;
  window.__RT_CHAT_WIDGET_LOADED__ = true;

  const API_BASE = '/backend/api/chat.php';

  let threadCode = null;
  let currentUserId = null;
  let lastMsgId = 0;
  let pollTimer = null;
  let isConnected = false;
  let isSending = false;
  const displayedMessages = new Set(); // Track by message ID, not hash

  // Comprehensive profanity list
  const PROFANITY_LIST = [
    'fuck', 'shit', 'ass', 'bitch', 'bastard', 'damn', 'hell',
    'crap', 'piss', 'dick', 'cock', 'pussy', 'whore', 'slut',
    'fck', 'fuk', 'wtf', 'stfu', 'bullshit', 'asshole', 'motherfucker',
    'cunt', 'twat', 'wanker', 'bollocks', 'bugger', 'arse',
    'jackass', 'douchebag', 'dipshit', 'shithead', 'moron',
    'gago', 'putang', 'tanga', 'bobo', 'tarantado', 'tangina',
    'leche', 'puta', 'ulol', 'hayop', 'animal', 'kingina',
    'putangina', 'punyeta', 'bwisit', 'kupal', 'pakshet',
    'pak', 'peste', 'hinayupak', 'walanghiya', 'walang hiya',
    'putcha', 'yawa', 'pisting', 'lintik', 'buwisit',
    'fvck', 'fck', 'fuk', 'fuc', 'phuck', 'sht', 'shyt',
    'btch', 'azz', 'fucc', 'p*ta', 'p*tang', 'tang*na', 'nigger',
    'nigga', 'nigg', 'niggster', 'nigg4', 'nigg3r'
  ];

  // DOM Elements
  const chatBtn = document.getElementById('rtChatBtn');
  const chatPopup = document.getElementById('rtChatPopup');
  const chatMessages = document.getElementById('rtChatMessages');
  const chatInput = document.getElementById('rtChatInput');
  const chatSend = document.getElementById('rtChatSend');
  const faqToggle = document.getElementById('rtFaqToggle');
  const faqDropdown = document.getElementById('rtFaqDropdown');
  const clearBtn = document.getElementById('rtClearChat');

  if (!chatBtn || !chatPopup) {
    console.error('Chat widget elements not found');
    return;
  }

  // ========== CLEAR CHAT MODAL ==========
  function showClearChatModal() {
    const existingModal = document.getElementById('rtClearModal');
    if (existingModal) existingModal.remove();

    const modalHtml = `
      <div class="rt-modal" id="rtClearModal">
        <div class="rt-modal-content">
          <h3>Clear Chat History?</h3>
          <p>This will <strong>permanently delete</strong> all messages from the database. This action cannot be undone.</p>
          <div class="rt-modal-actions">
            <button class="rt-modal-btn rt-modal-cancel" id="rtClearCancel">Cancel</button>
            <button class="rt-modal-btn rt-modal-confirm" id="rtClearConfirm">Clear Chat</button>
          </div>
        </div>
      </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);

    document.getElementById('rtClearConfirm').onclick = async () => {
      await clearChatHistory();
      document.getElementById('rtClearModal').remove();
    };

    document.getElementById('rtClearCancel').onclick = () => {
      document.getElementById('rtClearModal').remove();
    };
  }

  async function clearChatHistory() {
    if (!threadCode) {
      appendSystemMsg('No active chat to clear');
      return;
    }

    try {
      const response = await fetch(`${API_BASE}?action=clear_chat`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ thread_code: threadCode })
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const result = await response.json();

      if (!result.success) {
        appendSystemMsg('Failed to clear chat. Please try again.');
        return;
      }

      // CRITICAL: Clear UI completely
      if (chatMessages) {
        chatMessages.innerHTML = '';
      }
      displayedMessages.clear();

      // IMPORTANT: Don't reset lastMsgId to 0
      // Keep it so polling only fetches NEW messages
      // lastMsgId stays at current value

      const count = result.data?.message_count || 0;
      appendSystemMsg(`✓ Chat history cleared (${count} messages removed from your view)`);

      setTimeout(() => {
        appendSystemMsg('💡 Note: Admins can still see the full conversation history');
      }, 1000);

    } catch (error) {
      console.error('Clear chat error:', error);
      appendSystemMsg('Failed to clear chat. Please try again.');
    }
  }

  // ========== PROFANITY FILTER ==========
  function containsProfanity(text) {
    const lowerText = text.toLowerCase();
    return PROFANITY_LIST.some(word => {
      const regex = new RegExp(`\\b${word}\\b`, 'i');
      return regex.test(lowerText);
    });
  }

  // ========== UTILITIES ==========
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function appendMessage(sender, text, msgId = null) {
    if (!chatMessages) return;

    // REMOVED: Deduplication check - allow same message multiple times
    // Only track by msgId if provided (from database)
    if (msgId && displayedMessages.has(msgId)) return;
    if (msgId) displayedMessages.add(msgId);

    const bubble = document.createElement('div');
    bubble.className = `rt-msg rt-msg-${sender}`;
    bubble.textContent = text;
    if (msgId) bubble.setAttribute('data-msg-id', msgId);

    chatMessages.appendChild(bubble);
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  function appendSystemMsg(text) {
    if (!chatMessages) return;

    const msg = document.createElement('div');
    msg.className = 'rt-msg-system';
    msg.textContent = text;

    chatMessages.appendChild(msg);
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  // ========== FAQ LOADING ==========
  async function loadFAQs() {
    if (!faqDropdown) return;

    try {
      const response = await fetch(`${API_BASE}?action=faqs_list`, {
        credentials: 'same-origin'
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const result = await response.json();

      if (!result.success || !result.data || result.data.length === 0) {
        faqDropdown.innerHTML = '<div style="padding:12px;color:#999;">No FAQs available</div>';
        return;
      }

      faqDropdown.innerHTML = result.data.map(faq =>
        `<div class="rt-faq-chip" 
             data-question="${escapeHtml(faq.question)}"
             data-answer="${escapeHtml(faq.answer)}">
          ${escapeHtml(faq.question)}
        </div>`
      ).join('');

      faqDropdown.querySelectorAll('.rt-faq-chip').forEach(chip => {
        chip.addEventListener('click', () => {
          const question = chip.getAttribute('data-question');
          const answer = chip.getAttribute('data-answer');

          // Show question as customer message
          appendMessage('customer', question);

          // Show answer as bot response after delay
          setTimeout(() => appendMessage('bot', answer), 400);

          faqDropdown.classList.remove('rt-show');
        });
      });

    } catch (error) {
      console.error('Failed to load FAQs:', error);
    }
  }

  // ========== THREAD INITIALIZATION ==========
  async function initThread() {
    try {
      const response = await fetch(`${API_BASE}?action=thread_init`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({})
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const result = await response.json();

      if (!result.success) {
        if (response.status === 401) {
          appendSystemMsg('Please login or create an account to use chat support');
          return;
        }
        throw new Error(result.message || 'Failed to initialize chat');
      }

      if (!result.data.thread_code) {
        throw new Error('No thread code received');
      }

      threadCode = result.data.thread_code;
      currentUserId = result.data.user_id;

      // Store in sessionStorage with user-specific key
      sessionStorage.setItem(`rt_thread_user_${currentUserId}`, threadCode);

      isConnected = true;
      appendSystemMsg('Connected to chat support');

      // Load existing messages
      await loadHistory();

      startPolling();

    } catch (error) {
      console.error('Thread init error:', error);
      isConnected = false;
      appendSystemMsg('Unable to connect. Please try again later.');
    }
  }

  // ========== LOAD CHAT HISTORY ==========
  async function loadHistory() {
    if (!threadCode) return;

    try {
      const response = await fetch(
        `${API_BASE}?action=messages_fetch&thread_code=${encodeURIComponent(threadCode)}&after_id=0`,
        { credentials: 'same-origin' }
      );

      if (!response.ok) return;

      const result = await response.json();

      if (!result.success || !result.data) return;

      // Display all existing messages
      result.data.forEach(msg => {
        const msgId = parseInt(msg.id);
        lastMsgId = Math.max(lastMsgId, msgId);
        appendMessage(msg.sender_type, msg.body, msgId);
      });

    } catch (error) {
      console.error('Load history error:', error);
    }
  }

  // ========== MESSAGE POLLING ==========
  async function pollMessages() {
    if (!threadCode || !isConnected) return;

    try {
      const response = await fetch(
        `${API_BASE}?action=messages_fetch&thread_code=${encodeURIComponent(threadCode)}&after_id=${lastMsgId}`,
        { credentials: 'same-origin' }
      );

      if (!response.ok) return;

      const result = await response.json();

      if (!result.success || !result.data) return;

      result.data.forEach(msg => {
        const msgId = parseInt(msg.id);
        if (msgId > lastMsgId) {
          lastMsgId = msgId;
          appendMessage(msg.sender_type, msg.body, msgId);
        }
      });

    } catch (error) {
      console.error('Polling error:', error);
    }
  }

  function startPolling() {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(pollMessages, 2000);
  }

  function stopPolling() {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  // ========== SEND MESSAGE ==========
  async function sendMessage() {
    if (!chatInput || isSending) return;

    const text = chatInput.value.trim();
    if (!text) return;

    if (containsProfanity(text)) {
      appendSystemMsg('⚠️ Your message contains inappropriate content and cannot be sent.');
      chatInput.value = '';
      return;
    }

    if (!isConnected || !threadCode) {
      appendSystemMsg('Not connected. Please wait...');
      await initThread();
      if (!isConnected) return;
    }

    isSending = true;
    const messageText = text;
    chatInput.value = '';

    try {
      // Send to backend FIRST, don't show in UI yet
      const response = await fetch(`${API_BASE}?action=send_customer`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          thread_code: threadCode,
          body: messageText
        })
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const result = await response.json();

      if (!result.success) {
        appendSystemMsg('Failed to send. Please try again.');
        return;
      }

      // SUCCESS: Message saved to DB, now let polling fetch it
      // This prevents double-send

      // Force immediate poll to show message quickly
      setTimeout(() => pollMessages(), 100);

      // Show waiting message only if no auto-reply
      if (!result.data || !result.data.auto_reply) {
        setTimeout(() => {
          appendSystemMsg('⏳ Please wait for an admin to respond...');
        }, 1000);
      }

      // Update the sendMessage catch block:
    } catch (error) {
      console.error('Send error:', error);

      // Show specific error message
      if (error.message.includes('HTTP')) {
        appendSystemMsg('❌ Server error. Please try again.');
      } else if (error.message.includes('NetworkError') || error.message.includes('Failed to fetch')) {
        appendSystemMsg('❌ Network error. Check your connection.');
      } else {
        appendSystemMsg('❌ Failed to send. Please try again.');
      }
    } finally {
      isSending = false;
    }
  }

  // ========== EVENT LISTENERS ==========

  chatBtn.addEventListener('click', () => {
    const isOpen = chatPopup.classList.contains('rt-show');

    if (isOpen) {
      chatPopup.classList.remove('rt-show');
      stopPolling();
    } else {
      chatPopup.classList.add('rt-show');

      // Try to restore thread from session
      if (currentUserId) {
        const stored = sessionStorage.getItem(`rt_thread_user_${currentUserId}`);
        if (stored) {
          threadCode = stored;
          isConnected = true;
          loadHistory();
          startPolling();
        } else {
          initThread();
        }
      } else {
        initThread();
      }
    }
  });

  if (faqToggle) {
    faqToggle.addEventListener('click', () => {
      faqDropdown.classList.toggle('rt-show');
    });
  }

  if (chatSend) {
    chatSend.addEventListener('click', sendMessage);
  }

  if (chatInput) {
    chatInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', showClearChatModal);
  }

  // Listen for logout
  document.addEventListener('customer_logout', () => {
    stopPolling();
    threadCode = null;
    currentUserId = null;
    isConnected = false;
    if (chatMessages) chatMessages.innerHTML = '';
    displayedMessages.clear();

    // Clear session storage
    const keys = Object.keys(sessionStorage);
    keys.forEach(key => {
      if (key.startsWith('rt_thread_')) {
        sessionStorage.removeItem(key);
      }
    });
  });

  loadFAQs();
  console.log('RADS-TOOLING Chat Widget loaded (FIXED)');
})();