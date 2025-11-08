/**
 * RADS-TOOLING Admin Chat System
 */
(function () {
  const API_BASE = '/backend/api/chat.php';

  let selectedCode = null;
  let lastId = 0;
  let pollTimer = null;
  let sentMessageIds = new Set(); // Track sent messages to prevent duplication

  // DOM Elements  
  const threadsList = document.getElementById('rtThreadList');
  const conv = document.getElementById('rtAdminConv');
  const replyInput = document.getElementById('rtAdminMsg');
  const sendBtn = document.getElementById('rtAdminSend');
  const faqTbody = document.getElementById('rtFaqTbody');
  const faqQ = document.getElementById('rtFaqQ');
  const faqA = document.getElementById('rtFaqA');
  const faqSave = document.getElementById('rtFaqSave');

  const chatSection = document.querySelector('section[data-section="chat"]');
  if (!chatSection) {
    console.log('Chat section not found - script will wait');
    return;
  }

  if (!threadsList || !conv) {
    console.error('Admin chat elements not found');
    return;
  }

  // ========== UTILITIES ==========

  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // ========== LOAD THREADS ==========

  async function loadThreads() {
    try {
      const response = await fetch(`${API_BASE}?action=threads`, {
        credentials: 'same-origin'
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const result = await response.json();

      if (!result.success) {
        threadsList.innerHTML = '<div style="padding:20px;color:#999;">Failed to load threads</div>';
        return;
      }

      if (!result.data || result.data.length === 0) {
        threadsList.innerHTML = '<div style="padding:20px;color:#999;">No customer threads</div>';
        return;
      }

      threadsList.innerHTML = result.data.map(t => threadHtml(t)).join('');

      threadsList.querySelectorAll('.rt-thread-item').forEach(item => {
        item.addEventListener('click', () => {
          const code = item.getAttribute('data-code');
          openConv(code);

          threadsList.querySelectorAll('.rt-thread-item').forEach(i => i.classList.remove('rt-active'));
          item.classList.add('rt-active');
        });
      });

    } catch (error) {
      console.error('Load threads error:', error);
      threadsList.innerHTML = '<div style="padding:20px;color:#e14d4d;">Error loading threads</div>';
    }
  }

  function threadHtml(t) {
    const name = t.display_name || t.customer_name || t.customer_email || 'Unknown';
    const time = t.last_message_at ? new Date(t.last_message_at).toLocaleString() : '';

    const unreadCount = parseInt(t.unread_count) || 0;
    const displayCount = unreadCount > 5 ? '5+' : unreadCount;

    const badgeHtml = unreadCount > 0
      ? `<span class="rt-unread-badge">${displayCount}</span>`
      : '';

    return `
      <div class="rt-thread-item ${t.has_unread ? 'rt-has-unread' : ''}" 
           data-code="${escapeHtml(t.thread_code)}">
        <div class="rt-thread-header">
          <div class="rt-thread-name">
            ${escapeHtml(name)}
            ${badgeHtml}
          </div>
          <div class="rt-thread-time">${time}</div>
        </div>
      </div>
    `;
  }

  // ========== LOAD CONVERSATION ==========

  async function openConv(code) {
    selectedCode = code;
    lastId = 0;
    sentMessageIds.clear();
    conv.innerHTML = '';
    if (pollTimer) clearInterval(pollTimer);

    const threadItem = document.querySelector(`.rt-thread-item[data-code="${code}"]`);
    if (threadItem) {
      threadItem.classList.remove('rt-has-unread');
      const badge = threadItem.querySelector('.rt-unread-badge');
      if (badge) badge.remove();
    }

    try {
      await fetch(`${API_BASE}?action=mark_read`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ thread_code: code })
      });
    } catch (error) {
      console.error('Mark read error:', error);
    }

    await poll();
    pollTimer = setInterval(poll, 2000);
  }

  async function poll() {
    if (!selectedCode) return;

    try {
      const response = await fetch(
        `${API_BASE}?action=messages_fetch&thread_code=${encodeURIComponent(selectedCode)}&after_id=${lastId}`,
        { credentials: 'same-origin' }
      );

      if (!response.ok) return;

      const result = await response.json();

      if (!result.success || !result.data) return;

      result.data.forEach(msg => {
        const msgId = parseInt(msg.id);
        if (msgId > lastId && !sentMessageIds.has(msgId)) {
          lastId = msgId;
          appendMsg(msg.sender_type, msg.body);
        }
      });

    } catch (error) {
      console.error('Poll error:', error);
    }
  }

  function appendMsg(sender, text) {
    const bubble = document.createElement('div');
    bubble.className = `rt-msg rt-msg-${sender}`;
    bubble.textContent = text;
    conv.appendChild(bubble);
    conv.scrollTop = conv.scrollHeight;
  }

  // ========== SEND MESSAGE ==========

  async function sendMessage() {
    if (!replyInput || !selectedCode) return;

    const text = replyInput.value.trim();
    if (!text) return;

    replyInput.value = '';

    try {
      const response = await fetch(`${API_BASE}?action=send_admin`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          thread_code: selectedCode,
          body: text
        })
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const result = await response.json();

      if (!result.success) {
        console.error('Send failed:', result.message);
        return;
      }

      poll();

    } catch (error) {
      console.error('Send error:', error);
    }
  }

  // ========== LOAD FAQs (with Edit button) ==========

  async function loadFaqs() {
    if (!faqTbody) {
      console.error('FAQ table body not found');
      return;
    }

    try {
      const response = await fetch(`${API_BASE}?action=faqs_list_admin`, {
        credentials: 'same-origin'
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const result = await response.json();

      if (!result.success) {
        console.error('FAQ load failed:', result.message);
        faqTbody.innerHTML = '<tr><td colspan="2" style="text-align:center;padding:20px;color:#e14d4d;">Failed to load FAQs</td></tr>';
        return;
      }

      if (!result.data || result.data.length === 0) {
        faqTbody.innerHTML = '<tr><td colspan="2" style="text-align:center;padding:20px;color:#999;">No FAQs found. Add one above.</td></tr>';
        return;
      }

      faqTbody.innerHTML = result.data.map(faq => `
      <tr data-faq-id="${faq.id}" class="rt-faq-row">
        <td>
          <div style="margin-bottom:4px;">
            <strong style="color:#2f5b88;">${escapeHtml(faq.question)}</strong>
          </div>
          <div>
            <span style="color:#666;font-size:14px;">${escapeHtml(faq.answer)}</span>
          </div>
        </td>
        <td style="text-align:center;white-space:nowrap;">
          <button class="rt-btn rt-btn-sm rt-btn-warning" onclick="editFaq(${faq.id})" style="padding:6px 12px;background:#f39c12;color:#fff;border:none;margin-right:4px;">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">edit</span>
            Edit
          </button>
          <button class="rt-btn rt-btn-sm rt-btn-danger" onclick="deleteFaq(${faq.id})" style="padding:6px 12px;">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">delete</span>
            Delete
          </button>
        </td>
      </tr>
    `).join('');

      console.log(`Loaded ${result.data.length} FAQs`);

    } catch (error) {
      console.error('Load FAQs error:', error);
      faqTbody.innerHTML = '<tr><td colspan="2" style="text-align:center;padding:20px;color:#e14d4d;">Error loading FAQs</td></tr>';
    }
  }

  // ========== SAVE FAQ ==========

  async function saveFaq() {
    if (!faqQ || !faqA) return;

    const question = faqQ.value.trim();
    const answer = faqA.value.trim();

    if (!question || !answer) {
      // Use modal instead of alert
      if (typeof showChatError === 'function') {
        showChatError('Please enter both question and answer');
      } else {
        alert('Please enter both question and answer');
      }
      return;
    }

    try {
      const response = await fetch(`${API_BASE}?action=faq_save`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ question, answer })
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const result = await response.json();

      if (!result.success) {
        if (typeof showChatError === 'function') {
          showChatError('Failed to save FAQ: ' + result.message);
        } else {
          alert('Failed to save FAQ: ' + result.message);
        }
        return;
      }

      faqQ.value = '';
      faqA.value = '';
      loadFaqs();

      if (typeof showChatSuccess === 'function') {
        showChatSuccess('Success', 'FAQ saved successfully');
      }

    } catch (error) {
      console.error('Save FAQ error:', error);
      if (typeof showChatError === 'function') {
        showChatError('Failed to save FAQ. Please try again.');
      } else {
        alert('Failed to save FAQ');
      }
    }
  }

  // ========== EDIT FAQ ==========

  let editingFaqId = null;

  window.editFaq = function (faqId) {
    if (!faqQ || !faqA) return;

    // Find the FAQ data from the table
    const row = document.querySelector(`tr[data-faq-id="${faqId}"]`);
    if (!row) return;

    const questionEl = row.querySelector('strong');
    const answerEl = row.querySelector('span[style*="color:#666"]');

    if (!questionEl || !answerEl) return;

    // Populate the input fields
    faqQ.value = questionEl.textContent;
    faqA.value = answerEl.textContent;

    // Store the ID being edited
    editingFaqId = faqId;

    // Change button text to "Update FAQ"
    if (faqSave) {
      faqSave.innerHTML = '<span class="material-symbols-rounded" style="vertical-align: middle; font-size: 18px;">edit</span> Update FAQ';
      faqSave.style.background = '#f39c12';
    }

    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  // ========== UPDATE FAQ ==========

  async function updateFaq(faqId, question, answer) {
    try {
      const response = await fetch(`${API_BASE}?action=faq_update`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ faq_id: faqId, question, answer })
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const result = await response.json();

      if (!result.success) {
        if (typeof showChatError === 'function') {
          showChatError('Failed to update FAQ: ' + result.message);
        } else {
          alert('Failed to update FAQ: ' + result.message);
        }
        return false;
      }

      return true;

    } catch (error) {
      console.error('Update FAQ error:', error);
      if (typeof showChatError === 'function') {
        showChatError('Failed to update FAQ. Please try again.');
      } else {
        alert('Failed to update FAQ');
      }
      return false;
    }
  }

  // ========== MODIFIED SAVE FAQ (handles both save and update) ==========

  async function saveFaq() {
    if (!faqQ || !faqA) return;

    const question = faqQ.value.trim();
    const answer = faqA.value.trim();

    if (!question || !answer) {
      if (typeof showChatError === 'function') {
        showChatError('Please enter both question and answer');
      } else {
        alert('Please enter both question and answer');
      }
      return;
    }

    // If editing, update instead of create
    if (editingFaqId) {
      const success = await updateFaq(editingFaqId, question, answer);
      if (success) {
        faqQ.value = '';
        faqA.value = '';
        editingFaqId = null;

        // Reset button
        if (faqSave) {
          faqSave.innerHTML = '<span class="material-symbols-rounded" style="vertical-align: middle; font-size: 18px;">save</span> Save FAQ';
          faqSave.style.background = '#2f5b88';
        }

        loadFaqs();

        if (typeof showChatSuccess === 'function') {
          showChatSuccess('Updated', 'FAQ updated successfully');
        }
      }
      return;
    }

    // Otherwise, create new FAQ
    try {
      const response = await fetch(`${API_BASE}?action=faq_save`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ question, answer })
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const result = await response.json();

      if (!result.success) {
        if (typeof showChatError === 'function') {
          showChatError('Failed to save FAQ: ' + result.message);
        } else {
          alert('Failed to save FAQ: ' + result.message);
        }
        return;
      }

      faqQ.value = '';
      faqA.value = '';
      loadFaqs();

      if (typeof showChatSuccess === 'function') {
        showChatSuccess('Success', 'FAQ saved successfully');
      }

    } catch (error) {
      console.error('Save FAQ error:', error);
      if (typeof showChatError === 'function') {
        showChatError('Failed to save FAQ. Please try again.');
      } else {
        alert('Failed to save FAQ');
      }
    }
  }

  // ========== DELETE FAQ (with modal confirmation) ==========

  window.deleteFaq = function (faqId) {
    // Show delete confirmation modal
    const modal = document.getElementById('chatDeleteModal');
    const confirmBtn = document.getElementById('chatDeleteConfirm');

    if (!modal || !confirmBtn) {
      // Fallback to confirm dialog
      if (!confirm('Delete this FAQ permanently?')) return;
      performDelete(faqId);
      return;
    }

    // Show modal
    modal.style.display = 'flex';

    // Set up confirmation handler
    confirmBtn.onclick = async function () {
      modal.style.display = 'none';
      await performDelete(faqId);
    };
  };

  async function performDelete(faqId) {
    try {
      const response = await fetch(`${API_BASE}?action=faq_delete`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ faq_id: faqId })
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const result = await response.json();

      if (!result.success) {
        if (typeof showChatError === 'function') {
          showChatError('Failed to delete FAQ: ' + result.message);
        } else {
          alert('Failed to delete FAQ: ' + result.message);
        }
        return;
      }

      loadFaqs();

      if (typeof showChatSuccess === 'function') {
        showChatSuccess('Deleted', 'FAQ removed successfully');
      }

    } catch (error) {
      console.error('Delete FAQ error:', error);
      if (typeof showChatError === 'function') {
        showChatError('Failed to delete FAQ. Please try again.');
      } else {
        alert('Failed to delete FAQ');
      }
    }
  }

  // ========== EVENT LISTENERS ==========

  if (sendBtn) {
    sendBtn.addEventListener('click', sendMessage);
  }

  if (replyInput) {
    replyInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });
  }

  if (faqSave) {
    faqSave.addEventListener('click', saveFaq);
  }

  // ========== CANCEL EDIT ==========

  // Add cancel button next to Save/Update when editing
  function showCancelButton() {
    if (!faqSave || !faqSave.parentElement) return;

    let cancelBtn = document.getElementById('rtFaqCancel');
    if (!cancelBtn) {
      cancelBtn = document.createElement('button');
      cancelBtn.id = 'rtFaqCancel';
      cancelBtn.className = 'rt-btn';
      cancelBtn.innerHTML = '<span class="material-symbols-rounded" style="vertical-align: middle; font-size: 18px;">close</span> Cancel';
      cancelBtn.style.background = '#95a5a6';
      cancelBtn.style.color = '#fff';
      cancelBtn.style.display = 'none';

      cancelBtn.onclick = function () {
        faqQ.value = '';
        faqA.value = '';
        editingFaqId = null;

        if (faqSave) {
          faqSave.innerHTML = '<span class="material-symbols-rounded" style="vertical-align: middle; font-size: 18px;">save</span> Save FAQ';
          faqSave.style.background = '#2f5b88';
        }

        cancelBtn.style.display = 'none';
      };

      faqSave.parentElement.appendChild(cancelBtn);
    }

    cancelBtn.style.display = 'inline-flex';
  }

  // Update editFaq to show cancel button
  window.editFaq = function (faqId) {
    if (!faqQ || !faqA) return;

    const row = document.querySelector(`tr[data-faq-id="${faqId}"]`);
    if (!row) return;

    const questionEl = row.querySelector('strong');
    const answerEl = row.querySelector('span[style*="color:#666"]');

    if (!questionEl || !answerEl) return;

    faqQ.value = questionEl.textContent;
    faqA.value = answerEl.textContent;
    editingFaqId = faqId;

    if (faqSave) {
      faqSave.innerHTML = '<span class="material-symbols-rounded" style="vertical-align: middle; font-size: 18px;">edit</span> Update FAQ';
      faqSave.style.background = '#f39c12';
    }

    showCancelButton();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  // ========== INITIALIZE ==========

  function initChat() {
    if (!threadsList || !conv) {
      console.log('Chat elements not ready yet');
      return;
    }

    console.log('Initializing admin chat...');
    loadThreads();
    loadFaqs();

    setInterval(loadThreads, 10000);

    console.log('Chat admin script loaded successfully');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initChat);
  } else {
    initChat();
  }
})();