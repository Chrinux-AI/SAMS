<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];
$page_title = 'Chat';
?>
<!DOCTYPE html>
<html lang="en">

<head><?php include_once "includes/favicon-loader.php"; ?>
    <script src="assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
    <title><?php echo $page_title; ?> - SAMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="assets/css/cyberpunk-ui.css" rel="stylesheet">
    <link href="assets/css/pwa-styles.css" rel="stylesheet">
    <style>
        :root {
            --chat-bg: #0a0e27;
            --chat-sidebar: #141b34;
            --chat-input-bg: #1a2342;
            --message-sent: linear-gradient(135deg, #00BFFF 0%, #0080ff 100%);
            --message-received: #1e2747;
            --hover-bg: #232d4f;
        }

        .chat-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            height: calc(100vh - 60px);
            background: var(--chat-bg);
            overflow: hidden;
        }

        /* Sidebar */
        .chat-sidebar {
            background: var(--chat-sidebar);
            border-right: 1px solid rgba(0, 191, 255, 0.2);
            display: flex;
            flex-direction: column;
        }

        .chat-sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(0, 191, 255, 0.2);
        }

        .chat-sidebar-header h2 {
            margin: 0 0 15px 0;
            color: var(--cyber-cyan);
            font-size: 1.5rem;
            font-family: 'Orbitron', sans-serif;
        }

        .chat-search {
            width: 100%;
            padding: 10px 15px;
            background: var(--chat-input-bg);
            border: 1px solid rgba(0, 191, 255, 0.3);
            border-radius: 25px;
            color: white;
            font-family: 'Inter', sans-serif;
        }

        .chat-search:focus {
            outline: none;
            border-color: var(--cyber-cyan);
            box-shadow: 0 0 10px rgba(0, 191, 255, 0.3);
        }

        .conversations-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }

        .conversation-item {
            display: flex;
            align-items: center;
            padding: 15px;
            margin-bottom: 5px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .conversation-item:hover {
            background: var(--hover-bg);
        }

        .conversation-item.active {
            background: var(--hover-bg);
            border-left-color: var(--cyber-cyan);
        }

        .conversation-item.unread {
            background: rgba(0, 191, 255, 0.05);
        }

        .avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--cyber-cyan), var(--cyber-purple));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
            color: white;
            margin-right: 15px;
            position: relative;
        }

        .avatar .online-indicator {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 12px;
            height: 12px;
            background: #10b981;
            border: 2px solid var(--chat-sidebar);
            border-radius: 50%;
        }

        .conversation-info {
            flex: 1;
            min-width: 0;
        }

        .conversation-name {
            font-weight: 600;
            color: white;
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .conversation-preview {
            color: #8892a6;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-time {
            font-size: 0.75rem;
            color: #6b7280;
        }

        .unread-badge {
            background: var(--cyber-cyan);
            color: var(--bg-dark);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            min-width: 20px;
            text-align: center;
        }

        /* Chat area */
        .chat-area {
            display: flex;
            flex-direction: column;
            background: var(--chat-bg);
        }

        .chat-header {
            padding: 20px;
            border-bottom: 1px solid rgba(0, 191, 255, 0.2);
            background: var(--chat-sidebar);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chat-header-info {
            display: flex;
            align-items: center;
        }

        .chat-header h3 {
            margin: 0;
            color: white;
            font-size: 1.2rem;
        }

        .chat-status {
            font-size: 0.85rem;
            color: #10b981;
            margin-top: 3px;
        }

        .chat-status.offline {
            color: #6b7280;
        }

        .chat-actions {
            display: flex;
            gap: 10px;
        }

        .chat-btn {
            background: none;
            border: 1px solid rgba(0, 191, 255, 0.3);
            color: var(--cyber-cyan);
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .chat-btn:hover {
            background: rgba(0, 191, 255, 0.1);
            border-color: var(--cyber-cyan);
        }

        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .message-group {
            display: flex;
            gap: 10px;
        }

        .message-group.sent {
            flex-direction: row-reverse;
        }

        .message {
            max-width: 60%;
            display: flex;
            flex-direction: column;
        }

        .message-bubble {
            padding: 12px 16px;
            border-radius: 18px;
            position: relative;
            word-wrap: break-word;
        }

        .message-group.sent .message-bubble {
            background: var(--message-sent);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message-group.received .message-bubble {
            background: var(--message-received);
            color: white;
            border-bottom-left-radius: 4px;
        }

        .message-reply {
            background: rgba(0, 0, 0, 0.2);
            padding: 8px 12px;
            border-radius: 8px;
            margin-bottom: 8px;
            border-left: 3px solid rgba(255, 255, 255, 0.3);
            font-size: 0.85rem;
            opacity: 0.8;
        }

        .message-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 5px;
            font-size: 0.75rem;
            color: #8892a6;
        }

        .message-reactions {
            display: flex;
            gap: 5px;
            margin-top: 5px;
        }

        .reaction {
            background: rgba(255, 255, 255, 0.1);
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .reaction:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }

        .reaction.active {
            background: var(--cyber-cyan);
            color: var(--bg-dark);
        }

        .message-attachment {
            margin-top: 8px;
            padding: 10px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .attachment-icon {
            font-size: 1.5rem;
            color: var(--cyber-cyan);
        }

        .typing-indicator {
            padding: 10px 15px;
            color: #8892a6;
            font-size: 0.9rem;
            font-style: italic;
        }

        .typing-dots {
            display: inline-flex;
            gap: 4px;
        }

        .typing-dots span {
            width: 6px;
            height: 6px;
            background: var(--cyber-cyan);
            border-radius: 50%;
            animation: typingBounce 1.4s infinite;
        }

        .typing-dots span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-dots span:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typingBounce {

            0%,
            60%,
            100% {
                transform: translateY(0);
            }

            30% {
                transform: translateY(-8px);
            }
        }

        .chat-input-container {
            padding: 20px;
            background: var(--chat-sidebar);
            border-top: 1px solid rgba(0, 191, 255, 0.2);
        }

        .chat-input-wrapper {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .chat-input {
            flex: 1;
            background: var(--chat-input-bg);
            border: 1px solid rgba(0, 191, 255, 0.3);
            border-radius: 25px;
            padding: 12px 20px;
            color: white;
            font-family: 'Inter', sans-serif;
            resize: none;
            max-height: 120px;
            overflow-y: auto;
        }

        .chat-input:focus {
            outline: none;
            border-color: var(--cyber-cyan);
            box-shadow: 0 0 15px rgba(0, 191, 255, 0.2);
        }

        .input-actions {
            display: flex;
            gap: 5px;
        }

        .input-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--chat-input-bg);
            border: 1px solid rgba(0, 191, 255, 0.3);
            color: var(--cyber-cyan);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .input-btn:hover {
            background: rgba(0, 191, 255, 0.1);
            border-color: var(--cyber-cyan);
        }

        .send-btn {
            background: var(--message-sent);
            border: none;
        }

        .send-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px rgba(0, 191, 255, 0.5);
        }

        .empty-state {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 5rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .new-chat-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--message-sent);
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 5px 25px rgba(0, 191, 255, 0.4);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .new-chat-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 35px rgba(0, 191, 255, 0.6);
        }

        @media (max-width: 768px) {
            .chat-container {
                grid-template-columns: 1fr;
            }

            .chat-sidebar {
                display: none;
            }

            .chat-sidebar.mobile-show {
                display: flex;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 999;
            }

            .message {
                max-width: 80%;
            }
        }

        /* Context menu for messages */
        .context-menu {
            position: absolute;
            background: var(--chat-sidebar);
            border: 1px solid rgba(0, 191, 255, 0.3);
            border-radius: 8px;
            padding: 5px;
            z-index: 1000;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.5);
        }

        .context-menu-item {
            padding: 10px 15px;
            cursor: pointer;
            border-radius: 5px;
            color: white;
            transition: all 0.2s ease;
        }

        .context-menu-item:hover {
            background: var(--hover-bg);
        }

        .context-menu-item i {
            margin-right: 10px;
            color: var(--cyber-cyan);
        }
    </style>
</head>

<body class="cyber-bg">
    <?php include 'includes/cyber-nav.php'; ?>
    <div class="cyber-layout">
        <div class="cyber-main">

            <div class="chat-container">
                <!-- Sidebar -->
                <div class="chat-sidebar" id="chatSidebar">
                    <div class="chat-sidebar-header">
                        <h2><i class="fas fa-comments"></i> Messages</h2>
                        <input type="text" class="chat-search" id="userSearch" placeholder="Search users..." oninput="searchUsers(this.value)">

                        <!-- Role filter tabs -->
                        <div style="display: flex; gap: 5px; margin-top: 10px; flex-wrap: wrap;">
                            <button class="chat-btn" style="font-size: 0.8rem; padding: 5px 10px;" onclick="showTab('conversations')">
                                <i class="fas fa-comments"></i> Chats
                            </button>
                            <button class="chat-btn" style="font-size: 0.8rem; padding: 5px 10px;" onclick="showTab('contacts')">
                                <i class="fas fa-address-book"></i> Contacts
                            </button>
                            <button class="chat-btn" style="font-size: 0.8rem; padding: 5px 10px;" onclick="showUsersByRole('student')">
                                <i class="fas fa-user-graduate"></i> Students
                            </button>
                            <button class="chat-btn" style="font-size: 0.8rem; padding: 5px 10px;" onclick="showUsersByRole('teacher')">
                                <i class="fas fa-chalkboard-teacher"></i> Teachers
                            </button>
                            <button class="chat-btn" style="font-size: 0.8rem; padding: 5px 10px;" onclick="showUsersByRole('parent')">
                                <i class="fas fa-user-friends"></i> Parents
                            </button>
                        </div>
                    </div>

                    <div class="conversations-list" id="conversationsList">
                        <!-- Conversations will be loaded here -->
                    </div>

                    <div class="conversations-list" id="contactsList" style="display: none;">
                        <!-- Contacts will be loaded here -->
                    </div>

                    <div class="conversations-list" id="usersList" style="display: none;">
                        <!-- Search results will be loaded here -->
                    </div>
                </div>

                <!-- Chat Area -->
                <div class="chat-area" id="chatArea">
                    <div class="empty-state" id="emptyState">
                        <i class="fas fa-comments"></i>
                        <h3>Select a conversation</h3>
                        <p>Choose from your existing conversations or start a new one</p>
                    </div>

                    <!-- Active chat (hidden by default) -->
                    <div id="activeChat" style="display: none; flex: 1; display: flex; flex-direction: column;">
                        <div class="chat-header">
                            <div class="chat-header-info">
                                <div class="avatar" id="chatAvatar"></div>
                                <div>
                                    <h3 id="chatName"></h3>
                                    <div class="chat-status" id="chatStatus">Offline</div>
                                </div>
                            </div>
                            <div class="chat-actions">
                                <button class="chat-btn" onclick="searchInChat()"><i class="fas fa-search"></i></button>
                                <button class="chat-btn" onclick="toggleChatInfo()"><i class="fas fa-info-circle"></i></button>
                            </div>
                        </div>

                        <div class="messages-container" id="messagesContainer">
                            <!-- Messages will be loaded here -->
                        </div>

                        <div class="typing-indicator" id="typingIndicator" style="display: none;">
                            <span id="typingText"></span>
                            <div class="typing-dots">
                                <span></span><span></span><span></span>
                            </div>
                        </div>

                        <div class="chat-input-container">
                            <div class="chat-input-wrapper">
                                <div class="input-actions">
                                    <button class="input-btn" onclick="attachFile()" title="Attach file">
                                        <i class="fas fa-paperclip"></i>
                                    </button>
                                    <button class="input-btn" onclick="openEmojiPicker()" title="Emoji">
                                        <i class="fas fa-smile"></i>
                                    </button>
                                </div>
                                <textarea
                                    class="chat-input"
                                    id="messageInput"
                                    placeholder="Type a message..."
                                    rows="1"
                                    onkeydown="handleInputKeydown(event)"
                                    oninput="handleTyping()"></textarea>
                                <button class="input-btn send-btn" onclick="sendMessage()">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New Chat Button -->
            <button class="new-chat-btn" onclick="showNewChatModal()" title="New Chat">
                <i class="fas fa-plus"></i>
            </button>

            <!-- File input (hidden) -->
            <input type="file" id="fileInput" multiple style="display: none;" onchange="handleFileSelect(event)">

            <?php include 'includes/sams-bot.php'; ?>

            <script src="assets/js/main.js"></script>
            <script src="assets/js/pwa-manager.js"></script>
            <script src="assets/js/pwa-analytics.js"></script>
            <script>
                let currentConversationId = null;
                let currentUserId = <?php echo $user_id; ?>;
                let pollingInterval = null;
                let typingTimeout = null;
                let replyToMessageId = null;

                // Load conversations on page load
                document.addEventListener('DOMContentLoaded', function() {
                    loadConversations();
                    startPolling();
                    updateOnlineStatus();
                    setInterval(updateOnlineStatus, 30000); // Update every 30 seconds
                });

                async function loadConversations() {
                    try {
                        const response = await fetch('api/chat.php?action=get_conversations');
                        const data = await response.json();

                        if (data.success) {
                            displayConversations(data.conversations);
                        }
                    } catch (error) {
                        console.error('Error loading conversations:', error);
                    }
                }

                function displayConversations(conversations) {
                    const list = document.getElementById('conversationsList');

                    if (conversations.length === 0) {
                        list.innerHTML = '<div style="padding: 20px; text-align: center; color: #6b7280;">No conversations yet</div>';
                        return;
                    }

                    list.innerHTML = conversations.map(conv => {
                        const initials = getInitials(conv.first_name, conv.last_name);
                        const isOnline = conv.is_online == 1;
                        const unreadBadge = conv.unread_count > 0 ? `<span class="unread-badge">${conv.unread_count}</span>` : '';
                        const onlineIndicator = isOnline ? '<span class="online-indicator"></span>' : '';

                        return `
                    <div class="conversation-item ${conv.unread_count > 0 ? 'unread' : ''}"
                         onclick="openConversation(${conv.id}, '${conv.first_name} ${conv.last_name}', ${conv.is_online})">
                        <div class="avatar">
                            ${initials}
                            ${onlineIndicator}
                        </div>
                        <div class="conversation-info">
                            <div class="conversation-name">
                                <span>${conv.first_name} ${conv.last_name}</span>
                                ${unreadBadge}
                            </div>
                            <div class="conversation-preview">${conv.last_message_text || 'No messages yet'}</div>
                        </div>
                        <div class="conversation-time">${formatTime(conv.last_message_at)}</div>
                    </div>
                `;
                    }).join('');
                }

                async function openConversation(conversationId, name, isOnline) {
                    currentConversationId = conversationId;

                    // Update UI
                    document.getElementById('emptyState').style.display = 'none';
                    document.getElementById('activeChat').style.display = 'flex';
                    document.getElementById('chatName').textContent = name;
                    document.getElementById('chatAvatar').textContent = getInitials(name.split(' ')[0], name.split(' ')[1] || '');
                    document.getElementById('chatStatus').textContent = isOnline ? 'Online' : 'Offline';
                    document.getElementById('chatStatus').className = 'chat-status' + (isOnline ? '' : ' offline');

                    // Mark active conversation
                    document.querySelectorAll('.conversation-item').forEach(item => {
                        item.classList.remove('active');
                    });
                    event.currentTarget.classList.add('active');

                    // Load messages
                    await loadMessages(conversationId);

                    // Start checking for typing
                    checkTypingIndicator();
                }

                async function loadMessages(conversationId, beforeId = 0) {
                    try {
                        const url = `api/chat.php?action=get_messages&conversation_id=${conversationId}${beforeId ? '&before_id=' + beforeId : ''}`;
                        const response = await fetch(url);
                        const data = await response.json();

                        if (data.success) {
                            displayMessages(data.messages);
                            scrollToBottom();
                        }
                    } catch (error) {
                        console.error('Error loading messages:', error);
                    }
                }

                function displayMessages(messages) {
                    const container = document.getElementById('messagesContainer');

                    container.innerHTML = messages.map(msg => {
                        const isSent = msg.sender_id == currentUserId;
                        const reactions = msg.reactions || [];
                        const hasReactions = reactions.length > 0;

                        let replyHtml = '';
                        if (msg.reply_to_text) {
                            replyHtml = `
                        <div class="message-reply">
                            <strong>${msg.reply_to_user_first_name} ${msg.reply_to_user_last_name}</strong>
                            <div>${msg.reply_to_text.substring(0, 100)}</div>
                        </div>
                    `;
                        }

                        let attachmentsHtml = '';
                        if (msg.attachments && msg.attachments.length > 0) {
                            attachmentsHtml = msg.attachments.map(att => `
                        <div class="message-attachment">
                            <i class="fas fa-file attachment-icon"></i>
                            <a href="${att.file_path}" target="_blank" style="color: var(--cyber-cyan);">${att.file_name}</a>
                        </div>
                    `).join('');
                        }

                        return `
                    <div class="message-group ${isSent ? 'sent' : 'received'}">
                        <div class="message" oncontextmenu="showMessageMenu(event, ${msg.id}, ${isSent})">
                            ${replyHtml}
                            <div class="message-bubble">
                                ${escapeHtml(msg.message_text)}
                                ${attachmentsHtml}
                            </div>
                            <div class="message-meta">
                                <span>${formatTime(msg.created_at)}</span>
                                ${msg.is_edited ? '<span><i class="fas fa-edit"></i> Edited</span>' : ''}
                                ${isSent ? `<i class="fas fa-check-double" style="color: ${msg.is_read_by.length > 1 ? '#10b981' : '#6b7280'};"></i>` : ''}
                            </div>
                            ${hasReactions ? `
                                <div class="message-reactions">
                                    ${getReactionButtons(msg.id, reactions)}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
                    }).join('');
                }

                function getReactionButtons(messageId, reactions) {
                    const grouped = {};
                    reactions.forEach(r => {
                        if (!grouped[r.reaction]) {
                            grouped[r.reaction] = [];
                        }
                        grouped[r.reaction].push(r.user_id);
                    });

                    return Object.entries(grouped).map(([emoji, users]) => {
                        const isActive = users.includes(currentUserId);
                        return `
                    <span class="reaction ${isActive ? 'active' : ''}"
                          onclick="toggleReaction(${messageId}, '${emoji}')"
                          title="${users.length} reaction${users.length > 1 ? 's' : ''}">
                        ${emoji} ${users.length}
                    </span>
                `;
                    }).join('');
                }

                async function sendMessage() {
                    const input = document.getElementById('messageInput');
                    const message = input.value.trim();

                    if (!message && !selectedFiles.length) return;

                    const formData = new FormData();
                    formData.append('action', 'send_message');
                    formData.append('conversation_id', currentConversationId);
                    formData.append('message', message);

                    if (replyToMessageId) {
                        formData.append('reply_to_message_id', replyToMessageId);
                        replyToMessageId = null;
                    }

                    // Add files
                    selectedFiles.forEach(file => {
                        formData.append('attachments[]', file);
                    });

                    try {
                        const response = await fetch('api/chat.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();

                        if (data.success) {
                            input.value = '';
                            selectedFiles = [];
                            await loadMessages(currentConversationId);
                            await loadConversations();
                        }
                    } catch (error) {
                        console.error('Error sending message:', error);
                    }
                }

                async function toggleReaction(messageId, emoji) {
                    const formData = new FormData();
                    formData.append('message_id', messageId);
                    formData.append('reaction', emoji);

                    // Check if we already reacted
                    const reactionEl = event.currentTarget;
                    const action = reactionEl.classList.contains('active') ? 'remove_reaction' : 'add_reaction';
                    formData.append('action', action);

                    try {
                        await fetch('api/chat.php', {
                            method: 'POST',
                            body: formData
                        });
                        await loadMessages(currentConversationId);
                    } catch (error) {
                        console.error('Error toggling reaction:', error);
                    }
                }

                function handleTyping() {
                    clearTimeout(typingTimeout);

                    // Send typing indicator
                    sendTypingIndicator(true);

                    // Stop typing after 3 seconds of no input
                    typingTimeout = setTimeout(() => {
                        sendTypingIndicator(false);
                    }, 3000);
                }

                async function sendTypingIndicator(isTyping) {
                    if (!currentConversationId) return;

                    const formData = new FormData();
                    formData.append('action', 'typing');
                    formData.append('conversation_id', currentConversationId);
                    formData.append('is_typing', isTyping ? 1 : 0);

                    try {
                        await fetch('api/chat.php', {
                            method: 'POST',
                            body: formData
                        });
                    } catch (error) {
                        console.error('Error sending typing indicator:', error);
                    }
                }

                async function checkTypingIndicator() {
                    if (!currentConversationId) return;

                    try {
                        const response = await fetch(`api/chat.php?action=get_typing&conversation_id=${currentConversationId}`);
                        const data = await response.json();

                        const indicator = document.getElementById('typingIndicator');
                        const typingText = document.getElementById('typingText');

                        if (data.success && data.typing_users.length > 0) {
                            const names = data.typing_users.map(u => u.first_name).join(', ');
                            typingText.textContent = `${names} ${data.typing_users.length > 1 ? 'are' : 'is'} typing`;
                            indicator.style.display = 'block';
                        } else {
                            indicator.style.display = 'none';
                        }
                    } catch (error) {
                        console.error('Error checking typing:', error);
                    }
                }

                function handleInputKeydown(event) {
                    if (event.key === 'Enter' && !event.shiftKey) {
                        event.preventDefault();
                        sendMessage();
                    }
                }

                function startPolling() {
                    pollingInterval = setInterval(() => {
                        if (currentConversationId) {
                            loadMessages(currentConversationId);
                            checkTypingIndicator();
                        }
                        loadConversations();
                    }, 3000); // Poll every 3 seconds
                }

                async function updateOnlineStatus() {
                    try {
                        await fetch('api/chat.php?action=update_online');
                    } catch (error) {
                        console.error('Error updating online status:', error);
                    }
                }

                let selectedFiles = [];

                function attachFile() {
                    document.getElementById('fileInput').click();
                }

                function handleFileSelect(event) {
                    selectedFiles = Array.from(event.target.files);
                    // TODO: Show preview of selected files
                }

                function openEmojiPicker() {
                    // Quick emoji reactions
                    const emojis = ['👍', '❤️', '😂', '😮', '😢', '🙏'];
                    // TODO: Implement emoji picker
                    console.log('Emoji picker coming soon!');
                }

                function showMessageMenu(event, messageId, isSent) {
                    event.preventDefault();
                    // TODO: Implement context menu for reply, delete, edit, react
                    console.log('Message menu for:', messageId);
                }

                function searchInChat() {
                    // TODO: Implement in-chat search
                    console.log('Search in chat');
                }

                function toggleChatInfo() {
                    // TODO: Show conversation info panel
                    console.log('Show chat info');
                }

                async function showNewChatModal() {
                    showTab('users');
                    document.getElementById('userSearch').focus();
                }

                let currentTab = 'conversations';

                function showTab(tab) {
                    currentTab = tab;
                    document.getElementById('conversationsList').style.display = tab === 'conversations' ? 'block' : 'none';
                    document.getElementById('contactsList').style.display = tab === 'contacts' ? 'block' : 'none';
                    document.getElementById('usersList').style.display = tab === 'users' ? 'block' : 'none';

                    if (tab === 'conversations') {
                        loadConversations();
                    } else if (tab === 'contacts') {
                        loadContacts();
                    }
                }

                async function loadContacts() {
                    try {
                        const response = await fetch('api/chat.php?action=get_contacts');
                        const data = await response.json();

                        if (data.success) {
                            displayContacts(data.contacts);
                        }
                    } catch (error) {
                        console.error('Error loading contacts:', error);
                    }
                }

                function displayContacts(contacts) {
                    const list = document.getElementById('contactsList');

                    if (contacts.length === 0) {
                        list.innerHTML = '<div style="padding: 20px; text-align: center; color: #6b7280;">No contacts saved<br><small>Search users and add them to contacts</small></div>';
                        return;
                    }

                    list.innerHTML = contacts.map(contact => {
                        const name = contact.nickname || `${contact.first_name} ${contact.last_name}`;
                        const initials = getInitials(contact.first_name, contact.last_name);
                        const isOnline = contact.is_online == 1;
                        const onlineIndicator = isOnline ? '<span class="online-indicator"></span>' : '';
                        const favoriteIcon = contact.is_favorite ? '<i class="fas fa-star" style="color: #fbbf24;"></i>' : '';

                        return `
                    <div class="conversation-item" onclick="startChatWithUser(${contact.contact_user_id}, '${name}')">
                        <div class="avatar">
                            ${initials}
                            ${onlineIndicator}
                        </div>
                        <div class="conversation-info">
                            <div class="conversation-name">
                                <span>${name} ${favoriteIcon}</span>
                                <button onclick="event.stopPropagation(); toggleFavoriteContact(${contact.contact_user_id})"
                                        style="background: none; border: none; color: var(--cyber-cyan); cursor: pointer;"
                                        title="Toggle favorite">
                                    <i class="fas ${contact.is_favorite ? 'fa-star' : 'fa-star-o'}"></i>
                                </button>
                            </div>
                            <div class="conversation-preview">${contact.role} • ${contact.email}</div>
                        </div>
                        <button onclick="event.stopPropagation(); removeContact(${contact.contact_user_id})"
                                style="background: none; border: none; color: #ef4444; cursor: pointer; padding: 5px;"
                                title="Remove contact">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `;
                    }).join('');
                }

                async function searchUsers(query) {
                    if (query.length < 2) {
                        if (currentTab === 'users') {
                            document.getElementById('usersList').innerHTML = '<div style="padding: 20px; text-align: center; color: #6b7280;">Type to search users...</div>';
                        }
                        return;
                    }

                    showTab('users');

                    try {
                        const response = await fetch(`api/chat.php?action=search_all_users&q=${encodeURIComponent(query)}`);
                        const data = await response.json();

                        if (data.success) {
                            displayUsers(data.users);
                        }
                    } catch (error) {
                        console.error('Error searching users:', error);
                    }
                }

                async function showUsersByRole(role) {
                    showTab('users');

                    try {
                        const response = await fetch(`api/chat.php?action=get_user_by_role&role=${role}`);
                        const data = await response.json();

                        if (data.success) {
                            displayUsers(data.users, `${role.charAt(0).toUpperCase() + role.slice(1)}s`);
                        }
                    } catch (error) {
                        console.error('Error loading users by role:', error);
                    }
                }

                function displayUsers(users, title = 'Search Results') {
                    const list = document.getElementById('usersList');

                    if (users.length === 0) {
                        list.innerHTML = '<div style="padding: 20px; text-align: center; color: #6b7280;">No users found</div>';
                        return;
                    }

                    const header = `<div style="padding: 15px; border-bottom: 1px solid rgba(0, 191, 255, 0.2);">
                <h3 style="margin: 0; color: var(--cyber-cyan); font-size: 1rem;">${title} (${users.length})</h3>
            </div>`;

                    const userItems = users.map(user => {
                        const name = `${user.first_name} ${user.last_name}`;
                        const initials = getInitials(user.first_name, user.last_name);
                        const isOnline = user.is_online == 1;
                        const onlineIndicator = isOnline ? '<span class="online-indicator"></span>' : '';
                        const roleColors = {
                            'student': '#3b82f6',
                            'teacher': '#10b981',
                            'parent': '#f59e0b',
                            'admin': '#ef4444'
                        };
                        const roleColor = roleColors[user.role] || '#6b7280';

                        const contactIcon = user.is_contact ?
                            `<i class="fas fa-user-check" style="color: var(--cyber-cyan);" title="In contacts"></i>` :
                            `<i class="fas fa-user-plus" style="color: #6b7280;" title="Add to contacts"></i>`;

                        return `
                    <div class="conversation-item">
                        <div class="avatar" style="background: linear-gradient(135deg, ${roleColor}, ${roleColor}99);">
                            ${initials}
                            ${onlineIndicator}
                        </div>
                        <div class="conversation-info">
                            <div class="conversation-name">
                                <span>${name}</span>
                                <span style="background: ${roleColor}; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem;">
                                    ${user.role.toUpperCase()}
                                </span>
                            </div>
                            <div class="conversation-preview">${user.email}</div>
                        </div>
                        <div style="display: flex; gap: 5px;">
                            <button onclick="toggleFavoriteContact(${user.id})"
                                    style="background: none; border: 1px solid rgba(0, 191, 255, 0.3); color: ${user.is_favorite ? '#fbbf24' : 'var(--cyber-cyan)'}; cursor: pointer; padding: 8px; border-radius: 5px;"
                                    title="${user.is_favorite ? 'Remove from favorites' : 'Add to favorites'}">
                                <i class="fas fa-star"></i>
                            </button>
                            <button onclick="startChatWithUser(${user.id}, '${name}')"
                                    style="background: var(--message-sent); border: none; color: white; cursor: pointer; padding: 8px 12px; border-radius: 5px;"
                                    title="Start chat">
                                <i class="fas fa-comment"></i>
                            </button>
                        </div>
                    </div>
                `;
                    }).join('');

                    list.innerHTML = header + userItems;
                }

                async function startChatWithUser(userId, userName) {
                    // Check if conversation exists or create new one
                    const formData = new FormData();
                    formData.append('action', 'send_message');
                    formData.append('recipient_id', userId);
                    formData.append('message', 'Hi!');
                    formData.append('conversation_id', 0);

                    try {
                        const response = await fetch('api/chat.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();

                        if (data.success) {
                            // Add to contacts if not already
                            await addContact(userId);

                            // Open the conversation
                            await loadConversations();
                            showTab('conversations');

                            // Find and open this conversation
                            setTimeout(() => {
                                const convElement = document.querySelector(`[onclick*="openConversation(${data.conversation_id}"]`);
                                if (convElement) convElement.click();
                            }, 500);
                        }
                    } catch (error) {
                        console.error('Error starting chat:', error);
                    }
                }

                async function addContact(userId) {
                    const formData = new FormData();
                    formData.append('action', 'add_contact');
                    formData.append('contact_user_id', userId);

                    try {
                        await fetch('api/chat.php', {
                            method: 'POST',
                            body: formData
                        });
                    } catch (error) {
                        console.error('Error adding contact:', error);
                    }
                }

                async function removeContact(userId) {
                    if (!confirm('Remove this contact?')) return;

                    const formData = new FormData();
                    formData.append('action', 'remove_contact');
                    formData.append('contact_user_id', userId);

                    try {
                        const response = await fetch('api/chat.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();

                        if (data.success) {
                            loadContacts();
                        }
                    } catch (error) {
                        console.error('Error removing contact:', error);
                    }
                }

                async function toggleFavoriteContact(userId) {
                    const formData = new FormData();
                    formData.append('action', 'toggle_favorite');
                    formData.append('contact_user_id', userId);

                    try {
                        await fetch('api/chat.php', {
                            method: 'POST',
                            body: formData
                        });

                        // Reload current view
                        if (currentTab === 'contacts') {
                            loadContacts();
                        } else if (currentTab === 'users') {
                            // Re-trigger last search
                            const searchValue = document.getElementById('userSearch').value;
                            if (searchValue) {
                                searchUsers(searchValue);
                            }
                        }
                    } catch (error) {
                        console.error('Error toggling favorite:', error);
                    }
                }

                function getInitials(firstName, lastName) {
                    return ((firstName || '').charAt(0) + (lastName || '').charAt(0)).toUpperCase();
                }

                function formatTime(datetime) {
                    if (!datetime) return '';
                    const date = new Date(datetime);
                    const now = new Date();
                    const diff = now - date;

                    if (diff < 60000) return 'Just now';
                    if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
                    if (diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';
                    if (diff < 604800000) return Math.floor(diff / 86400000) + 'd ago';

                    return date.toLocaleDateString();
                }

                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }

                function scrollToBottom() {
                    const container = document.getElementById('messagesContainer');
                    container.scrollTop = container.scrollHeight;
                }

                // Cleanup on page unload
                window.addEventListener('beforeunload', () => {
                    if (pollingInterval) clearInterval(pollingInterval);
                    sendTypingIndicator(false);
                });
            </script>
        </div>
    </div>
</body>

</html>
