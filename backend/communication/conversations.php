<?php

/**
 * SAMS Communication — WhatsApp-style messaging
 * Real-time conversations with typing, read receipts, and delivery status
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

if (!is_logged_in()) {
  header('Location: ../login.php');
  exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'student';
$full_name = $_SESSION['full_name'] ?? 'User';
$page_title = 'Messages';
$page_icon = 'mail';
$page_subtitle = 'Real-time messaging';
$hide_header = true;
$csrf_token = generate_csrf_token();

ob_start();
?>
<style>
  .content-wrapper {
    padding: 0 !important;
    overflow: hidden;
  }

  .chat-app {
    display: flex;
    height: calc(100vh - 60px);
    background: var(--bg-primary, #f8fafc);
    overflow: hidden;
  }

  /* Sidebar — Conversation list */
  .chat-sidebar {
    width: 360px;
    min-width: 300px;
    border-right: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    flex-direction: column;
    background: var(--bg-secondary, #fff);
  }

  .chat-sidebar-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .chat-sidebar-header h2 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary, #0f172a);
    margin: 0;
  }

  .chat-search {
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
  }

  .chat-search input {
    width: 100%;
    padding: 10px 16px 10px 40px;
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 10px;
    font-size: 0.875rem;
    background: var(--bg-primary, #f1f5f9);
    color: var(--text-primary);
    box-sizing: border-box;
  }

  .chat-search input:focus {
    outline: none;
    border-color: var(--primary, #3b82f6);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  }

  .chat-search {
    position: relative;
  }

  .chat-search span {
    position: absolute;
    left: 28px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted, #94a3b8);
    font-size: 1.2rem;
  }

  .conv-list {
    flex: 1;
    overflow-y: auto;
  }

  .conv-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 20px;
    cursor: pointer;
    border-bottom: 1px solid var(--border-color, #f1f5f9);
    transition: background 0.15s;
  }

  .conv-item:hover {
    background: var(--bg-primary, #f8fafc);
  }

  .conv-item.active {
    background: rgba(59, 130, 246, 0.08);
    border-left: 3px solid var(--primary, #3b82f6);
  }

  .conv-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
    color: #fff;
    flex-shrink: 0;
  }

  .conv-info {
    flex: 1;
    min-width: 0;
  }

  .conv-name {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text-primary, #0f172a);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .conv-preview {
    font-size: 0.8rem;
    color: var(--text-muted, #64748b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 2px;
  }

  .conv-meta {
    text-align: right;
    flex-shrink: 0;
  }

  .conv-time {
    font-size: 0.7rem;
    color: var(--text-muted, #94a3b8);
  }

  .conv-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    border-radius: 10px;
    background: var(--primary, #3b82f6);
    color: #fff;
    font-size: 0.7rem;
    font-weight: 700;
    margin-top: 4px;
    padding: 0 6px;
  }

  /* Main chat area */
  .chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: var(--bg-primary, #f8fafc);
  }

  .chat-header {
    padding: 14px 24px;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    align-items: center;
    gap: 14px;
    background: var(--bg-secondary, #fff);
  }

  .chat-header-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
  }

  .chat-header-info h3 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
  }

  .chat-header-info p {
    font-size: 0.75rem;
    color: var(--text-muted, #64748b);
    margin: 2px 0 0;
  }

  .chat-header-actions {
    margin-left: auto;
    display: flex;
    gap: 8px;
  }

  .chat-header-actions button {
    background: none;
    border: none;
    color: var(--text-muted);
    font-size: 1.1rem;
    cursor: pointer;
    padding: 8px;
    border-radius: 8px;
    transition: all 0.15s;
  }

  .chat-header-actions button:hover {
    background: var(--bg-primary, #f1f5f9);
    color: var(--text-primary);
  }

  .chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .msg-group {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin-bottom: 8px;
  }

  .msg-bubble {
    max-width: 70%;
    padding: 10px 14px;
    border-radius: 16px;
    font-size: 0.875rem;
    line-height: 1.45;
    word-wrap: break-word;
    position: relative;
  }

  .msg-sent {
    align-self: flex-end;
    background: var(--primary, #3b82f6);
    color: #fff;
    border-bottom-right-radius: 4px;
  }

  .msg-received {
    align-self: flex-start;
    background: var(--bg-secondary, #fff);
    color: var(--text-primary);
    border: 1px solid var(--border-color, #e2e8f0);
    border-bottom-left-radius: 4px;
  }

  .msg-sender {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--primary, #3b82f6);
    margin-bottom: 2px;
  }

  .msg-time {
    font-size: 0.65rem;
    margin-top: 4px;
    opacity: 0.7;
    display: flex;
    align-items: center;
    gap: 4px;
    justify-content: flex-end;
  }

  .msg-sent .msg-time {
    color: rgba(255, 255, 255, 0.8);
  }

  .msg-received .msg-time {
    color: var(--text-muted);
  }

  .msg-status {
    font-size: 0.7rem;
  }

  .msg-reply-preview {
    background: rgba(0, 0, 0, 0.08);
    border-radius: 8px;
    padding: 6px 10px;
    margin-bottom: 6px;
    font-size: 0.78rem;
    border-left: 3px solid var(--primary, #3b82f6);
  }

  .msg-sent .msg-reply-preview {
    background: rgba(255, 255, 255, 0.15);
  }

  .msg-deleted {
    font-style: italic;
    opacity: 0.6;
  }

  .msg-date-sep {
    text-align: center;
    padding: 12px 0;
  }

  .msg-date-sep span {
    background: var(--bg-secondary, #e2e8f0);
    color: var(--text-muted);
    font-size: 0.72rem;
    font-weight: 600;
    padding: 4px 14px;
    border-radius: 8px;
  }

  .typing-indicator {
    padding: 8px 14px;
    font-size: 0.8rem;
    color: var(--text-muted);
    font-style: italic;
  }

  .typing-indicator .dots span {
    animation: typeDot 1.4s infinite;
    display: inline-block;
  }

  .typing-indicator .dots span:nth-child(2) {
    animation-delay: 0.2s;
  }

  .typing-indicator .dots span:nth-child(3) {
    animation-delay: 0.4s;
  }

  @keyframes typeDot {

    0%,
    60%,
    100% {
      opacity: 0.3
    }

    30% {
      opacity: 1
    }
  }

  /* Input area */
  .chat-input {
    padding: 14px 24px;
    border-top: 1px solid var(--border-color, #e2e8f0);
    background: var(--bg-secondary, #fff);
    display: flex;
    align-items: flex-end;
    gap: 12px;
  }

  .chat-input-box {
    flex: 1;
    position: relative;
  }

  .chat-input-box textarea {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 24px;
    font-size: 0.875rem;
    font-family: 'Inter', sans-serif;
    resize: none;
    max-height: 120px;
    min-height: 44px;
    background: var(--bg-primary, #f1f5f9);
    color: var(--text-primary);
    box-sizing: border-box;
    line-height: 1.4;
  }

  .chat-input-box textarea:focus {
    outline: none;
    border-color: var(--primary, #3b82f6);
  }

  .chat-send-btn {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: var(--primary, #3b82f6);
    color: #fff;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    transition: all 0.15s;
    flex-shrink: 0;
  }

  .chat-send-btn:hover {
    background: #2563eb;
    transform: scale(1.05);
  }

  .chat-send-btn:disabled {
    opacity: 0.4;
    cursor: default;
    transform: none;
  }

  .reply-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 16px;
    background: var(--bg-primary, #f1f5f9);
    border-radius: 12px 12px 0 0;
    border: 1px solid var(--border-color);
    border-bottom: none;
    margin-bottom: -1px;
    font-size: 0.8rem;
  }

  .reply-bar .reply-text {
    flex: 1;
    color: var(--text-muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .reply-bar .reply-close {
    cursor: pointer;
    color: var(--text-muted);
    padding: 4px;
  }

  /* Empty state */
  .chat-empty {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 16px;
    color: var(--text-muted);
  }

  .chat-empty span {
    font-size: 4rem;
    opacity: 0.3;
  }

  .chat-empty h3 {
    font-size: 1.2rem;
    margin: 0;
    color: var(--text-secondary);
  }

  .chat-empty p {
    font-size: 0.875rem;
    max-width: 360px;
    text-align: center;
  }

  /* New conversation modal */
  .modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
  }

  .modal-overlay.active {
    display: flex;
  }

  .modal {
    background: var(--bg-secondary, #fff);
    border-radius: 16px;
    width: 90%;
    max-width: 480px;
    max-height: 80vh;
    overflow: hidden;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
  }

  .modal-header {
    padding: 18px 24px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .modal-header h3 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 600;
  }

  .modal-close {
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: var(--text-muted);
    padding: 4px;
  }

  .modal-body {
    padding: 16px 24px;
    max-height: 60vh;
    overflow-y: auto;
  }

  .user-search-input {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    font-size: 0.875rem;
    margin-bottom: 12px;
    box-sizing: border-box;
    background: var(--bg-primary);
    color: var(--text-primary);
  }

  .user-result {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 8px;
    border-radius: 10px;
    cursor: pointer;
    transition: background 0.15s;
  }

  .user-result:hover {
    background: var(--bg-primary, #f1f5f9);
  }

  .user-result-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: #fff;
    font-size: 0.9rem;
    flex-shrink: 0;
  }

  .user-result-info {
    flex: 1;
  }

  .user-result-info h4 {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 500;
  }

  .user-result-info span {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-transform: capitalize;
  }

  /* Mobile */
  .mobile-back {
    display: none;
    background: none;
    border: none;
    font-size: 1.1rem;
    color: var(--text-primary);
    cursor: pointer;
    padding: 8px;
  }

  @media (max-width: 768px) {
    .chat-sidebar {
      width: 100%;
      position: absolute;
      inset: 0;
      z-index: 2;
    }

    .chat-main {
      position: absolute;
      inset: 0;
      z-index: 1;
    }

    .chat-app {
      position: relative;
    }

    .chat-app.conv-open .chat-sidebar {
      display: none;
    }

    .chat-app:not(.conv-open) .chat-main {
      display: none;
    }

    .mobile-back {
      display: block;
    }

    .msg-bubble {
      max-width: 85%;
    }
  }
</style>

<div class="chat-app" id="chatApp">
  <!-- Left: Conversation List -->
  <div class="chat-sidebar">
    <div class="chat-sidebar-header">
      <h2><span class="material-symbols-outlined" style="margin-right:8px;color:var(--primary,#3b82f6);font-size:1.5rem;">mail</span> Messages</h2>
      <button onclick="openNewChat()" style="background:var(--primary,#3b82f6);color:#fff;border:none;border-radius:10px;padding:8px 14px;cursor:pointer;font-size:0.8rem;font-weight:600;display:flex;align-items:center;gap:6px;">
        <span class="material-symbols-outlined" style="font-size:1rem;">add</span> New
      </button>
    </div>
    <div class="chat-search">
      <span class="material-symbols-outlined">search</span>
      <input type="text" placeholder="Search conversations..." id="convSearch" oninput="filterConversations(this.value)">
    </div>
    <div class="conv-list" id="convList">
      <div style="padding:40px;text-align:center;color:var(--text-muted);">
        <span class="material-symbols-outlined" style="font-size:2rem;display:block;animation:spin 2s linear infinite;">sync</span>
        <p style="margin-top:8px;font-size:0.85rem;">Loading conversations...</p>
      </div>
    </div>
  </div>

  <!-- Right: Chat Window -->
  <div class="chat-main" id="chatMain">
    <div class="chat-empty" id="chatEmpty">
      <span class="material-symbols-outlined">chat</span>
      <h3>Welcome to Messages</h3>
      <p>Select a conversation from the left or start a new one to begin messaging.</p>
      <button onclick="openNewChat()" style="background:var(--primary,#3b82f6);color:#fff;border:none;border-radius:12px;padding:12px 24px;cursor:pointer;font-size:0.9rem;font-weight:600;display:flex;align-items:center;gap:8px;">
        <span class="material-symbols-outlined">add</span> Start a Conversation
      </button>
    </div>

    <div id="chatView" style="display:none;flex-direction:column;height:100%;">
      <div class="chat-header" id="chatHeader">
        <button class="mobile-back" onclick="closeChat()"><span class="material-symbols-outlined">arrow_back</span></button>
        <div class="chat-header-avatar" id="chatAvatar"></div>
        <div class="chat-header-info">
          <h3 id="chatName">—</h3>
          <p id="chatStatus">—</p>
        </div>
        <div class="chat-header-actions">
          <button onclick="loadConvInfo()" title="Info"><span class="material-symbols-outlined">info</span></button>
        </div>
      </div>

      <div class="chat-messages" id="chatMessages"></div>

      <div id="typingArea" class="typing-indicator" style="display:none;"></div>

      <div id="replyBar" class="reply-bar" style="display:none;margin:0 24px;">
        <span class="material-symbols-outlined" style="color:var(--primary);font-size:1rem;">reply</span>
        <span class="reply-text" id="replyText"></span>
        <span class="reply-close" onclick="cancelReply()"><span class="material-symbols-outlined" style="font-size:1rem;">close</span></span>
      </div>

      <div class="chat-input">
        <div class="chat-input-box">
          <textarea id="msgInput" placeholder="Type a message..." rows="1"
            oninput="autoResize(this); sendTyping();"
            onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage();}"></textarea>
        </div>
        <button class="chat-send-btn" onclick="sendMessage()" id="sendBtn" title="Send">
          <span class="material-symbols-outlined" style="font-size:1.3rem;">send</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- New Conversation Modal -->
<div class="modal-overlay" id="newChatModal">
  <div class="modal">
    <div class="modal-header">
      <h3><span class="material-symbols-outlined" style="color:var(--primary);margin-right:8px;">add_circle</span> New Conversation</h3>
      <button class="modal-close" onclick="closeNewChat()"><span class="material-symbols-outlined">close</span></button>
    </div>
    <div class="modal-body">
      <input type="text" class="user-search-input" id="userSearchInput" placeholder="Search by name or email..." oninput="searchUsers(this.value)">
      <div id="userResults">
        <p style="text-align:center;color:var(--text-muted);font-size:0.85rem;padding:20px;">Type a name to search for users</p>
      </div>
    </div>
  </div>
</div>

<script>
  const API = '../communication/api/messages.php';
  const CURRENT_USER_ID = <?= $user_id ?>;
  const CURRENT_USER_ROLE = '<?= htmlspecialchars($user_role) ?>';

  let conversations = [];
  let activeConvId = null;
  let lastMsgId = 0;
  let replyToId = null;
  let typingTimer = null;
  let pollTimer = null;
  let typingPollTimer = null;
  let convPollTimer = null;

  // ── Conversations ──
  async function loadConversations() {
    try {
      const r = await fetch(API + '?action=conversations');
      const data = await r.json();
      if (data.conversations) {
        conversations = data.conversations;
        renderConversations(conversations);
      }
    } catch (e) {
      console.error('Load conversations failed:', e);
    }
  }

  function renderConversations(list) {
    const el = document.getElementById('convList');
    if (!list.length) {
      el.innerHTML = '<div style="padding:40px 20px;text-align:center;color:var(--text-muted);"><span class="material-symbols-outlined" style="font-size:2.5rem;opacity:0.3;display:block;margin-bottom:12px;">inbox</span><p style="font-size:0.85rem;">No conversations yet</p></div>';
      return;
    }
    el.innerHTML = list.map(c => {
      const active = c.id == activeConvId ? ' active' : '';
      const name = escHtml(c.display_name || c.title || 'Chat');
      const preview = c.last_message ? escHtml(c.last_message.substring(0, 60)) : '<i>No messages yet</i>';
      const time = c.last_message_at ? formatTime(c.last_message_at) : '';
      const badge = c.unread_count > 0 ? `<div class="conv-badge">${c.unread_count}</div>` : '';
      const initials = getInitials(c.display_name || c.title || '?');
      const color = avatarColor(c.id);
      const roleTag = c.display_role ? `<span style="font-size:0.65rem;color:var(--text-muted);text-transform:capitalize;">  ${escHtml(c.display_role)}</span>` : '';
      return `<div class="conv-item${active}" onclick="openConversation(${c.id})" data-id="${c.id}" data-search="${(c.display_name||'').toLowerCase()}">
            <div class="conv-avatar" style="background:${color}">${initials}</div>
            <div class="conv-info">
                <div class="conv-name">${name}${roleTag}</div>
                <div class="conv-preview">${preview}</div>
            </div>
            <div class="conv-meta">
                <div class="conv-time">${time}</div>
                ${badge}
            </div>
        </div>`;
    }).join('');
  }

  function filterConversations(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.conv-item').forEach(el => {
      el.style.display = el.dataset.search.includes(q) ? 'flex' : 'none';
    });
  }

  // ── Open Conversation ──
  async function openConversation(convId) {
    activeConvId = convId;
    lastMsgId = 0;
    replyToId = null;

    document.getElementById('chatEmpty').style.display = 'none';
    document.getElementById('chatView').style.display = 'flex';
    document.getElementById('chatApp').classList.add('conv-open');
    document.getElementById('replyBar').style.display = 'none';

    // Highlight in sidebar
    document.querySelectorAll('.conv-item').forEach(el => {
      el.classList.toggle('active', el.dataset.id == convId);
    });

    // Find conversation info
    const conv = conversations.find(c => c.id == convId);
    if (conv) {
      const name = conv.display_name || conv.title || 'Chat';
      document.getElementById('chatName').textContent = name;
      document.getElementById('chatStatus').textContent = conv.display_role ? capitalize(conv.display_role) : (conv.type === 'group' ? (conv.participant_count || 0) + ' members' : '');
      document.getElementById('chatAvatar').textContent = getInitials(name);
      document.getElementById('chatAvatar').style.background = avatarColor(convId);
      // Clear unread in sidebar
      const badge = document.querySelector(`.conv-item[data-id="${convId}"] .conv-badge`);
      if (badge) badge.remove();
    }

    await loadMessages(convId);
    startPolling(convId);
  }

  function closeChat() {
    activeConvId = null;
    document.getElementById('chatApp').classList.remove('conv-open');
    document.getElementById('chatView').style.display = 'none';
    document.getElementById('chatEmpty').style.display = 'flex';
    stopPolling();
  }

  // ── Messages ──
  async function loadMessages(convId) {
    const el = document.getElementById('chatMessages');
    el.innerHTML = '<div style="text-align:center;padding:40px;"><span class="material-symbols-outlined" style="font-size:1.5rem;color:var(--text-muted);animation:spin 2s linear infinite;">sync</span></div>';
    try {
      const r = await fetch(API + `?action=messages&conversation_id=${convId}&limit=80`);
      const data = await r.json();
      if (data.error) {
        el.innerHTML = `<div style="text-align:center;padding:40px;color:var(--text-muted);">${escHtml(data.error)}</div>`;
        return;
      }
      renderMessages(data.messages || []);
      if (data.messages && data.messages.length) {
        lastMsgId = Math.max(...data.messages.map(m => m.id));
      }
    } catch (e) {
      el.innerHTML = '<div style="text-align:center;padding:40px;color:#ef4444;">Failed to load messages</div>';
    }
  }

  function renderMessages(msgs) {
    const el = document.getElementById('chatMessages');
    if (!msgs.length) {
      el.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted);font-size:0.85rem;"><span class="material-symbols-outlined" style="font-size:2rem;margin-bottom:8px;display:block;opacity:0.4;">waving_hand</span>No messages yet. Say hello!</div>';
      return;
    }

    let html = '';
    let lastDate = '';

    msgs.forEach(m => {
      const d = m.created_at ? m.created_at.substring(0, 10) : '';
      if (d !== lastDate) {
        lastDate = d;
        html += `<div class="msg-date-sep"><span>${formatDate(d)}</span></div>`;
      }

      const isMine = m.sender_id == CURRENT_USER_ID;
      const cls = isMine ? 'msg-sent' : 'msg-received';
      const time = m.created_at ? formatMsgTime(m.created_at) : '';
      const readIcon = isMine ? getReadStatus(m) : '';

      let replyHtml = '';
      if (m.reply_preview) {
        replyHtml = `<div class="msg-reply-preview"><strong>${escHtml(m.reply_preview.sender_name || '')}</strong><br>${escHtml((m.reply_preview.body || '').substring(0, 80))}</div>`;
      }

      const senderHtml = !isMine && m.sender_name ? `<div class="msg-sender">${escHtml(m.sender_name)}</div>` : '';
      const body = m.is_deleted ? '<span class="msg-deleted"><span class="material-symbols-outlined" style="font-size:0.8rem;vertical-align:middle;">block</span> Message deleted</span>' : escHtml(m.body);
      const attHtml = (m.attachments || []).map(a => `<div style="margin-top:4px;"><a href="../communication/uploads/${encodeURIComponent(a.file_path)}" target="_blank" style="color:inherit;font-size:0.8rem;"><span class="material-symbols-outlined" style="font-size:0.8rem;vertical-align:middle;">attachment</span> ${escHtml(a.file_name)}</a></div>`).join('');

      html += `<div class="msg-bubble ${cls}" data-id="${m.id}" oncontextmenu="msgContext(event,${m.id},${isMine})">
            ${replyHtml}
            ${senderHtml}
            <div>${body}</div>
            ${attHtml}
            <div class="msg-time">${time}<span class="msg-status">${readIcon}</span></div>
        </div>`;
    });

    el.innerHTML = html;
    el.scrollTop = el.scrollHeight;
  }

  function getReadStatus(m) {
    if (!m.reads || !m.reads.length) return '<i class="fas fa-check msg-status" title="Sent"></i>';
    return '<i class="fas fa-check-double msg-status" style="color:#34d399;" title="Read"></i>';
  }

  // ── Send Message ──
  async function sendMessage() {
    const input = document.getElementById('msgInput');
    const body = input.value.trim();
    if (!body || !activeConvId) return;

    input.value = '';
    autoResize(input);

    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('conversation_id', activeConvId);
    formData.append('body', body);
    if (replyToId) formData.append('reply_to_id', replyToId);

    cancelReply();

    try {
      const r = await fetch(API, {
        method: 'POST',
        body: formData
      });
      const data = await r.json();
      if (data.success && data.message) {
        appendMessage(data.message);
        loadConversations(); // refresh sidebar
      }
    } catch (e) {
      console.error('Send failed:', e);
    }
  }

  function appendMessage(m) {
    const el = document.getElementById('chatMessages');
    const emptyMsg = el.querySelector('.fa-hand-peace');
    if (emptyMsg) el.innerHTML = '';

    const isMine = m.sender_id == CURRENT_USER_ID;
    const cls = isMine ? 'msg-sent' : 'msg-received';
    const time = m.created_at ? formatMsgTime(m.created_at) : 'now';
    const senderHtml = !isMine && m.sender_name ? `<div class="msg-sender">${escHtml(m.sender_name)}</div>` : '';

    const div = document.createElement('div');
    div.className = `msg-bubble ${cls}`;
    div.dataset.id = m.id;
    div.innerHTML = `${senderHtml}<div>${escHtml(m.body)}</div><div class="msg-time">${time} ${isMine ? '<i class="fas fa-check msg-status"></i>' : ''}</div>`;
    el.appendChild(div);
    el.scrollTop = el.scrollHeight;

    if (m.id > lastMsgId) lastMsgId = m.id;
  }

  // ── Reply ──
  function startReply(msgId) {
    replyToId = msgId;
    const bubble = document.querySelector(`.msg-bubble[data-id="${msgId}"]`);
    const text = bubble ? bubble.textContent.substring(0, 80) : '';
    document.getElementById('replyText').textContent = text;
    document.getElementById('replyBar').style.display = 'flex';
    document.getElementById('msgInput').focus();
  }

  function cancelReply() {
    replyToId = null;
    document.getElementById('replyBar').style.display = 'none';
  }

  function msgContext(e, msgId, isMine) {
    e.preventDefault();
    const actions = ['Reply'];
    if (isMine || CURRENT_USER_ROLE === 'admin') actions.push('Delete');

    const choice = prompt(`Message #${msgId}\nActions: ${actions.join(', ')}\n\nType action:`);
    if (!choice) return;
    const act = choice.toLowerCase().trim();
    if (act === 'reply') startReply(msgId);
    else if (act === 'delete' && (isMine || CURRENT_USER_ROLE === 'admin')) deleteMessage(msgId);
  }

  async function deleteMessage(msgId) {
    if (!confirm('Delete this message?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_message');
    fd.append('message_id', msgId);
    const r = await fetch(API, {
      method: 'POST',
      body: fd
    });
    const data = await r.json();
    if (data.success) {
      const bubble = document.querySelector(`.msg-bubble[data-id="${msgId}"]`);
      if (bubble) bubble.innerHTML = '<span class="msg-deleted"><span class="material-symbols-outlined" style="font-size:0.8rem;vertical-align:middle;">block</span> Message deleted</span>';
    }
  }

  // ── Typing ──
  function sendTyping() {
    if (!activeConvId) return;
    clearTimeout(typingTimer);
    const fd = new FormData();
    fd.append('action', 'typing');
    fd.append('conversation_id', activeConvId);
    fetch(API, {
      method: 'POST',
      body: fd
    }).catch(() => {});
    typingTimer = setTimeout(() => {
      const fd2 = new FormData();
      fd2.append('action', 'stop_typing');
      fd2.append('conversation_id', activeConvId);
      fetch(API, {
        method: 'POST',
        body: fd2
      }).catch(() => {});
    }, 3000);
  }

  async function checkTyping() {
    if (!activeConvId) return;
    try {
      const r = await fetch(API + `?action=typing_users&conversation_id=${activeConvId}`);
      const data = await r.json();
      const el = document.getElementById('typingArea');
      if (data.users && data.users.length) {
        const names = data.users.map(u => u.name).join(', ');
        el.innerHTML = `${escHtml(names)} is typing<span class="dots"><span>.</span><span>.</span><span>.</span></span>`;
        el.style.display = 'block';
      } else {
        el.style.display = 'none';
      }
    } catch (e) {}
  }

  // ── Polling ──
  function startPolling(convId) {
    stopPolling();
    pollTimer = setInterval(() => pollNewMessages(convId), 3000);
    typingPollTimer = setInterval(checkTyping, 2000);
  }

  function stopPolling() {
    clearInterval(pollTimer);
    clearInterval(typingPollTimer);
  }

  async function pollNewMessages(convId) {
    if (convId !== activeConvId) return;
    try {
      const r = await fetch(API + `?action=poll&conversation_id=${convId}&after=${lastMsgId}`);
      const data = await r.json();
      if (data.messages && data.messages.length) {
        data.messages.forEach(m => {
          if (m.id > lastMsgId) {
            appendMessage(m);
            lastMsgId = m.id;
          }
        });
      }
    } catch (e) {}
  }

  // ── New Conversation ──
  function openNewChat() {
    document.getElementById('newChatModal').classList.add('active');
    document.getElementById('userSearchInput').focus();
  }

  function closeNewChat() {
    document.getElementById('newChatModal').classList.remove('active');
    document.getElementById('userSearchInput').value = '';
    document.getElementById('userResults').innerHTML = '<p style="text-align:center;color:var(--text-muted);font-size:0.85rem;padding:20px;">Type a name to search for users</p>';
  }

  let searchDebounce;

  function searchUsers(q) {
    clearTimeout(searchDebounce);
    if (q.length < 2) {
      document.getElementById('userResults').innerHTML = '<p style="text-align:center;color:var(--text-muted);font-size:0.85rem;padding:20px;">Type at least 2 characters</p>';
      return;
    }
    searchDebounce = setTimeout(async () => {
      try {
        const r = await fetch(API + `?action=search_users&q=${encodeURIComponent(q)}`);
        const data = await r.json();
        const el = document.getElementById('userResults');
        if (!data.users || !data.users.length) {
          el.innerHTML = '<p style="text-align:center;color:var(--text-muted);font-size:0.85rem;padding:20px;">No users found</p>';
          return;
        }
        el.innerHTML = data.users.map(u => {
          const name = `${u.first_name} ${u.last_name}`;
          const initials = getInitials(name);
          const color = avatarColor(u.id);
          return `<div class="user-result" onclick="startDirectChat(${u.id})">
                    <div class="user-result-avatar" style="background:${color}">${initials}</div>
                    <div class="user-result-info">
                        <h4>${escHtml(name)}</h4>
                        <span>${escHtml(u.role)}</span>
                    </div>
                </div>`;
        }).join('');
      } catch (e) {
        document.getElementById('userResults').innerHTML = '<p style="text-align:center;color:#ef4444;padding:20px;">Search failed</p>';
      }
    }, 300);
  }

  async function startDirectChat(userId) {
    const fd = new FormData();
    fd.append('action', 'create_conversation');
    fd.append('user_id', userId);
    fd.append('type', 'direct');
    try {
      const r = await fetch(API, {
        method: 'POST',
        body: fd
      });
      const data = await r.json();
      if (data.error) {
        alert(data.error);
        return;
      }
      closeNewChat();
      await loadConversations();
      openConversation(data.conversation_id);
    } catch (e) {
      alert('Failed to create conversation');
    }
  }

  function loadConvInfo() {
    if (!activeConvId) return;
    alert('Conversation info: #' + activeConvId + '\nParticipants and details available in API.');
  }

  // ── Utils ──
  function escHtml(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function getInitials(name) {
    const p = (name || '?').split(' ');
    return (p[0]?.[0] || '') + (p[1]?.[0] || '');
  }

  function capitalize(s) {
    return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
  }

  function avatarColor(id) {
    const colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#ef4444', '#6366f1', '#14b8a6', '#f97316', '#06b6d4'];
    return colors[(id || 0) % colors.length];
  }

  function formatTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr.replace(' ', 'T'));
    const now = new Date();
    const diff = now - d;
    if (diff < 86400000 && d.getDate() === now.getDate()) return d.toLocaleTimeString([], {
      hour: '2-digit',
      minute: '2-digit'
    });
    if (diff < 172800000) return 'Yesterday';
    return d.toLocaleDateString([], {
      month: 'short',
      day: 'numeric'
    });
  }

  function formatMsgTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr.replace(' ', 'T'));
    return d.toLocaleTimeString([], {
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const target = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    const diff = (today - target) / 86400000;
    if (diff === 0) return 'Today';
    if (diff === 1) return 'Yesterday';
    return d.toLocaleDateString([], {
      weekday: 'long',
      month: 'long',
      day: 'numeric',
      year: 'numeric'
    });
  }

  function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
  }

  // ── Init ──
  document.addEventListener('DOMContentLoaded', () => {
    loadConversations();
    convPollTimer = setInterval(loadConversations, 10000);
  });
</script>

<style>
  @keyframes spin {
    to { transform: rotate(360deg); }
  }
</style>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../resources/ui-core/layouts/master-dashboard.php';
?>
