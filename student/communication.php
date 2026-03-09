<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_student();

$student_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$success_msg = '';
$error_msg = '';

// Get student's classmates for messaging
$classmates = db()->fetchAll("
    SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, s.admission_number as roll_number
    FROM users u
    JOIN students s ON u.id = s.user_id
    JOIN class_enrollments ce1 ON u.id = ce1.student_id
    WHERE ce1.class_id IN (
        SELECT class_id FROM class_enrollments WHERE student_id = ?
    )
    AND u.id != ?
    AND u.status = 'active'
    ORDER BY u.last_name, u.first_name
", [$student_id, $student_id]);

// Get unread count
$unread_count = db()->fetchOne("
    SELECT COUNT(*) as count
    FROM student_messages
    WHERE to_student_id = ? AND is_read = 0
", [$student_id])['count'] ?? 0;

$page_title = 'Student Communication';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="../assets/js/theme-loader.js"></script>
        <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Inter:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">

    <style>
        .messaging-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            height: calc(100vh - 200px);
        }

        .contacts-panel {
            background: rgba(0, 191, 255, 0.05);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .contacts-header {
            padding: 20px;
            background: linear-gradient(135deg, rgba(0, 191, 255, 0.1), rgba(138, 43, 226, 0.1));
            border-bottom: 1px solid var(--glass-border);
        }

        .contact-search {
            width: 100%;
            padding: 10px 15px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--cyber-cyan);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            margin-top: 10px;
        }

        .contacts-list {
            overflow-y: auto;
            flex: 1;
        }

        .contact-item {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(0, 191, 255, 0.1);
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .contact-item:hover {
            background: rgba(0, 191, 255, 0.1);
        }

        .contact-item.active {
            background: linear-gradient(90deg, rgba(0, 191, 255, 0.2), rgba(138, 43, 226, 0.2));
            border-left: 3px solid var(--cyber-cyan);
        }

        .contact-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--cyber-cyan), var(--cyber-purple));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .contact-info {
            flex: 1;
        }

        .contact-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 3px;
        }

        .contact-status {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .unread-badge {
            background: var(--cyber-red);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .chat-panel {
            background: rgba(0, 191, 255, 0.05);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .chat-header {
            padding: 20px;
            background: linear-gradient(135deg, rgba(0, 191, 255, 0.1), rgba(138, 43, 226, 0.1));
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
        }

        .message-bubble {
            max-width: 70%;
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .message-bubble.sent {
            align-self: flex-end;
            margin-left: auto;
        }

        .message-bubble.received {
            align-self: flex-start;
        }

        .message-reply-to {
            background: rgba(0, 191, 255, 0.1);
            border-left: 3px solid var(--cyber-cyan);
            padding: 8px 12px;
            margin-bottom: 8px;
            border-radius: 8px;
            font-size: 0.85rem;
            opacity: 0.8;
        }

        .message-reply-to .reply-to-name {
            color: var(--cyber-cyan);
            font-weight: 600;
            margin-bottom: 3px;
        }

        .message-content {
            padding: 12px 16px;
            border-radius: 12px;
            word-wrap: break-word;
            cursor: pointer;
            position: relative;
        }

        .message-content:hover .message-actions {
            opacity: 1;
        }

        .message-actions {
            position: absolute;
            top: -25px;
            right: 10px;
            background: rgba(0, 0, 0, 0.9);
            border: 1px solid var(--cyber-cyan);
            border-radius: 8px;
            padding: 5px;
            display: flex;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .message-action-btn {
            background: none;
            border: none;
            color: var(--cyber-cyan);
            cursor: pointer;
            padding: 5px 10px;
            font-size: 0.9rem;
        }

        .message-action-btn:hover {
            color: var(--cyber-purple);
        }

        .message-bubble.sent .message-content {
            background: linear-gradient(135deg, var(--cyber-cyan), var(--cyber-purple));
            color: white;
        }

        .message-bubble.received .message-content {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid var(--glass-border);
            color: var(--text-primary);
        }

        .message-time {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 5px;
            padding: 0 5px;
        }

        .message-bubble.sent .message-time {
            text-align: right;
        }

        .chat-input-area {
            padding: 20px;
            background: rgba(0, 0, 0, 0.3);
            border-top: 1px solid var(--glass-border);
            display: flex;
            gap: 10px;
        }

        .chat-input {
            flex: 1;
            padding: 12px 16px;
            background: rgba(0, 191, 255, 0.05);
            border: 1px solid var(--cyber-cyan);
            border-radius: 25px;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            resize: none;
        }

        .send-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--cyber-cyan), var(--cyber-purple));
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .send-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 0 20px rgba(0, 191, 255, 0.5);
        }

        .empty-chat {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-muted);
        }

        .empty-chat i {
            font-size: 5rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .typing-indicator {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            max-width: 80px;
        }

        .typing-dot {
            width: 8px;
            height: 8px;
            background: var(--cyber-cyan);
            border-radius: 50%;
            animation: typingBounce 1.4s infinite;
        }

        .typing-dot:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-dot:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typingBounce {

            0%,
            60%,
            100% {
                transform: translateY(0);
            }

            30% {
                transform: translateY(-10px);
            }
        }

        .reply-preview {
            background: rgba(0, 191, 255, 0.1);
            border-left: 3px solid var(--cyber-cyan);
            padding: 10px 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 8px;
        }

        .reply-preview-content {
            flex: 1;
        }

        .reply-preview-name {
            color: var(--cyber-cyan);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .reply-preview-text {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 3px;
        }

        .cancel-reply-btn {
            background: none;
            border: none;
            color: var(--cyber-red);
            cursor: pointer;
            padding: 5px;
            font-size: 1.2rem;
        }

        .edit-name-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10001;
        }

        .edit-name-content {
            background: rgba(20, 20, 30, 0.95);
            border: 2px solid var(--cyber-cyan);
            border-radius: 16px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
        }

        .modal-input {
            width: 100%;
            padding: 12px;
            background: rgba(0, 191, 255, 0.05);
            border: 1px solid var(--cyber-cyan);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="starfield"></div>
    <div class="app-layout"></div>

    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>

        <main class="cyber-main">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div>
                        <h1 class="page-title">Student Communication</h1>
                        <p class="page-subtitle">Connect with your classmates</p>
                    </div>
                </div>
                <div class="header-actions">
                    <?php if ($unread_count > 0): ?>
                        <div class="cyber-badge" style="font-size:1.2rem;">
                            <i class="fas fa-envelope"></i> <?php echo $unread_count; ?> Unread
                        </div>
                    <?php endif; ?>
                </div>
            </header>

            <div class="app-layout">
                <?php if ($success_msg): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_msg); ?>
                    </div>
                <?php endif; ?>

                <div class="messaging-container">
                    <!-- Contacts Panel -->
                    <div class="contacts-panel">
                        <div class="contacts-header">
                            <h3 style="margin:0;color:var(--cyber-cyan);">
                                <i class="fas fa-user-friends"></i> Classmates
                            </h3>
                            <input type="text" id="contactSearch" class="contact-search" placeholder="Search classmates...">
                        </div>
                        <div class="contacts-list" id="contactsList">
                            <?php if (empty($classmates)): ?>
                                <div style="padding:40px 20px;text-align:center;color:var(--text-muted);">
                                    <i class="fas fa-users" style="font-size:3rem;opacity:0.3;margin-bottom:15px;"></i>
                                    <p>No classmates found. Enroll in classes to connect with peers!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($classmates as $classmate): ?>
                                    <div class="contact-item" data-user-id="<?php echo $classmate['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($classmate['first_name'] . ' ' . $classmate['last_name']); ?>"
                                        onclick="selectContact(<?php echo $classmate['id']; ?>, '<?php echo htmlspecialchars($classmate['first_name'] . ' ' . $classmate['last_name']); ?>')">
                                        <div class="contact-avatar">
                                            <?php echo strtoupper(substr($classmate['first_name'], 0, 1) . substr($classmate['last_name'], 0, 1)); ?>
                                        </div>
                                        <div class="contact-info">
                                            <div class="contact-name">
                                                <?php echo htmlspecialchars($classmate['first_name'] . ' ' . $classmate['last_name']); ?>
                                            </div>
                                            <div class="contact-status">
                                                Roll: <?php echo htmlspecialchars($classmate['roll_number']); ?>
                                            </div>
                                        </div>
                                        <div id="unread-<?php echo $classmate['id']; ?>"></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Chat Panel -->
                    <div class="chat-panel">
                        <div id="chatHeader" class="chat-header" style="display:none;">
                            <div class="chat-user-info">
                                <div class="contact-avatar" id="chatAvatar"></div>
                                <div>
                                    <div style="font-weight:600;color:var(--text-primary);display:flex;align-items:center;gap:10px;">
                                        <span id="chatUserName"></span>
                                        <button onclick="openEditNameModal()" class="message-action-btn" title="Edit contact name" style="opacity:1;">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                    <div style="font-size:0.85rem;color:var(--text-muted);" id="chatSubtitle">Classmate</div>
                                </div>
                            </div>
                            <button onclick="clearChat()" class="cyber-btn btn-sm" style="background:rgba(255,69,0,0.2);">
                                <i class="fas fa-times"></i> Close
                            </button>
                        </div>

                        <div id="messagesContainer" class="messages-container">
                            <div class="empty-chat">
                                <i class="fas fa-comments"></i>
                                <h3>Select a classmate to start chatting</h3>
                                <p>Choose someone from your classes on the left</p>
                            </div>
                        </div>

                        <div id="chatInputArea" class="chat-input-area" style="display:none;">
                            <div id="replyPreview" class="reply-preview" style="display:none;">
                                <div class="reply-preview-content">
                                    <div class="reply-preview-name" id="replyToName"></div>
                                    <div class="reply-preview-text" id="replyToText"></div>
                                </div>
                                <button onclick="cancelReply()" class="cancel-reply-btn">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <textarea id="messageInput" class="chat-input" placeholder="Type your message..." rows="1" onkeypress="handleEnter(event)" oninput="handleTyping()"></textarea>
                            <button onclick="sendMessage()" class="send-btn">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Contact Name Modal -->
    <div id="editNameModal" class="edit-name-modal">
        <div class="edit-name-content">
            <h3 style="margin:0 0 10px 0;color:var(--cyber-cyan);">
                <i class="fas fa-user-edit"></i> Edit Contact Name
            </h3>
            <p style="color:var(--text-muted);font-size:0.9rem;">Set a custom name for this contact (like WhatsApp)</p>
            <input type="text" id="customNameInput" class="modal-input" placeholder="Enter custom name...">
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button onclick="saveContactName()" class="cyber-btn" style="flex:1;">
                    <i class="fas fa-save"></i> Save
                </button>
                <button onclick="closeEditNameModal()" class="cyber-btn" style="flex:1;background:rgba(255,69,0,0.2);">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentContactId = null;
        let currentContactName = '';
        let currentActualName = '';
        let messageInterval = null;
        let typingTimeout = null;
        let replyToMessageId = null;
        let replyToMessage = null;

        // Contact search
        document.getElementById('contactSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const contacts = document.querySelectorAll('.contact-item');

            contacts.forEach(contact => {
                const name = contact.getAttribute('data-name').toLowerCase();
                if (name.includes(searchTerm)) {
                    contact.style.display = 'flex';
                } else {
                    contact.style.display = 'none';
                }
            });
        });

        async function selectContact(userId, userName) {
            currentContactId = userId;
            currentActualName = userName;

            // Get custom name if exists
            try {
                const response = await fetch(`../api/student-messaging.php?action=get_contact_name&contact_id=${userId}`);
                const data = await response.json();
                if (data.success && data.custom_name) {
                    currentContactName = data.custom_name;
                    document.getElementById('chatSubtitle').textContent = `aka ${data.actual_name}`;
                } else {
                    currentContactName = userName;
                    document.getElementById('chatSubtitle').textContent = 'Classmate';
                }
            } catch (error) {
                currentContactName = userName;
            }

            // Update active state
            document.querySelectorAll('.contact-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`[data-user-id="${userId}"]`).classList.add('active');

            // Update chat header
            document.getElementById('chatHeader').style.display = 'flex';
            document.getElementById('chatUserName').textContent = currentContactName;
            document.getElementById('chatAvatar').textContent = currentContactName.split(' ').map(n => n[0]).join('').toUpperCase();

            // Show input area
            document.getElementById('chatInputArea').style.display = 'flex';

            // Load messages
            loadMessages();

            // Start auto-refresh
            if (messageInterval) clearInterval(messageInterval);
            messageInterval = setInterval(loadMessages, 3000);
        }

        async function loadMessages() {
            if (!currentContactId) return;

            try {
                const response = await fetch(`../api/student-messaging.php?action=get_messages&contact_id=${currentContactId}`);
                const data = await response.json();

                if (data.success) {
                    displayMessages(data.messages);
                }
            } catch (error) {
                console.error('Failed to load messages:', error);
            }
        }

        function displayMessages(messages) {
            const container = document.getElementById('messagesContainer');

            if (messages.length === 0) {
                container.innerHTML = `
                    <div class="empty-chat">
                        <i class="fas fa-comment-dots"></i>
                        <h3>No messages yet</h3>
                        <p>Start the conversation with ${currentContactName}</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = messages.map(msg => {
                const isSent = msg.from_student_id == <?php echo $student_id; ?>;
                const time = new Date(msg.created_at).toLocaleString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit'
                });

                // Check if this is a reply
                let replyHtml = '';
                if (msg.parent_message_id) {
                    const parentMsg = messages.find(m => m.id == msg.parent_message_id);
                    if (parentMsg) {
                        const isParentSent = parentMsg.from_student_id == <?php echo $student_id; ?>;
                        replyHtml = `
                            <div class="message-reply-to">
                                <div class="reply-to-name">${isParentSent ? 'You' : currentContactName}</div>
                                <div>${escapeHtml(parentMsg.message.substring(0, 50))}${parentMsg.message.length > 50 ? '...' : ''}</div>
                            </div>
                        `;
                    }
                }

                return `
                    <div class="message-bubble ${isSent ? 'sent' : 'received'}" data-message-id="${msg.id}">
                        ${replyHtml}
                        <div class="message-content" oncontextmenu="showMessageActions(event, ${msg.id}, '${escapeHtml(msg.message)}', '${isSent ? 'You' : currentContactName}')">
                            <div class="message-actions">
                                <button class="message-action-btn" onclick="replyToMessage(${msg.id}, '${escapeHtml(msg.message)}', '${isSent ? 'You' : currentContactName}')" title="Reply">
                                    <i class="fas fa-reply"></i>
                                </button>
                            </div>
                            ${escapeHtml(msg.message)}
                        </div>
                        <div class="message-time">
                            ${time}
                            ${isSent && msg.is_read ? '<i class="fas fa-check-double" style="color:var(--cyber-cyan);"></i>' : ''}
                            ${isSent && !msg.is_read ? '<i class="fas fa-check" style="color:var(--text-muted);"></i>' : ''}
                        </div>
                    </div>
                `;
            }).join('');

            // Scroll to bottom
            container.scrollTop = container.scrollHeight;
        }

        async function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();

            if (!message || !currentContactId) return;

            try {
                const formData = new FormData();
                formData.append('action', 'send_message');
                formData.append('to_student_id', currentContactId);
                formData.append('message', message);
                if (replyToMessageId) {
                    formData.append('reply_to_id', replyToMessageId);
                }

                const response = await fetch('../api/student-messaging.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    input.value = '';
                    cancelReply();
                    loadMessages();
                    notifyTyping(false);
                } else {
                    alert('Failed to send message: ' + data.error);
                }
            } catch (error) {
                console.error('Failed to send message:', error);
                alert('Failed to send message. Please try again.');
            }
        }

        function replyToMessage(messageId, messageText, senderName) {
            replyToMessageId = messageId;
            replyToMessage = messageText;

            const preview = document.getElementById('replyPreview');
            document.getElementById('replyToName').textContent = senderName;
            document.getElementById('replyToText').textContent = messageText.substring(0, 100) + (messageText.length > 100 ? '...' : '');
            preview.style.display = 'flex';

            document.getElementById('messageInput').focus();
        }

        function cancelReply() {
            replyToMessageId = null;
            replyToMessage = null;
            document.getElementById('replyPreview').style.display = 'none';
        }

        function showMessageActions(event, messageId, messageText, senderName) {
            event.preventDefault();
            // Right-click menu (optional enhancement)
        }

        function openEditNameModal() {
            document.getElementById('customNameInput').value = currentContactName;
            document.getElementById('editNameModal').style.display = 'flex';
        }

        function closeEditNameModal() {
            document.getElementById('editNameModal').style.display = 'none';
        }

        async function saveContactName() {
            const customName = document.getElementById('customNameInput').value.trim();
            if (!customName) {
                alert('Please enter a name');
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'save_contact_name');
                formData.append('contact_id', currentContactId);
                formData.append('custom_name', customName);

                const response = await fetch('../api/student-messaging.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    currentContactName = customName;
                    document.getElementById('chatUserName').textContent = customName;
                    document.getElementById('chatAvatar').textContent = customName.split(' ').map(n => n[0]).join('').toUpperCase();
                    document.getElementById('chatSubtitle').textContent = `aka ${currentActualName}`;
                    closeEditNameModal();
                } else {
                    alert('Failed to save name: ' + data.error);
                }
            } catch (error) {
                console.error('Failed to save name:', error);
                alert('Failed to save name. Please try again.');
            }
        }

        function handleTyping() {
            notifyTyping(true);

            if (typingTimeout) clearTimeout(typingTimeout);
            typingTimeout = setTimeout(() => {
                notifyTyping(false);
            }, 3000);
        }

        async function notifyTyping(isTyping) {
            if (!currentContactId) return;

            try {
                const formData = new FormData();
                formData.append('action', 'set_typing');
                formData.append('contact_id', currentContactId);
                formData.append('is_typing', isTyping ? '1' : '0');

                await fetch('../api/student-messaging.php', {
                    method: 'POST',
                    body: formData
                });
            } catch (error) {
                // Silent fail
            }
        }

        function handleEnter(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendMessage();
            }
        }

        function clearChat() {
            if (messageInterval) clearInterval(messageInterval);
            currentContactId = null;
            currentContactName = '';
            currentActualName = '';
            cancelReply();

            document.getElementById('chatHeader').style.display = 'none';
            document.getElementById('chatInputArea').style.display = 'none';

            document.getElementById('messagesContainer').innerHTML = `
                <div class="empty-chat">
                    <i class="fas fa-comments"></i>
                    <h3>Select a classmate to start chatting</h3>
                    <p>Choose someone from your classes on the left</p>
                </div>
            `;

            document.querySelectorAll('.contact-item').forEach(item => {
                item.classList.remove('active');
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close modal on outside click
        document.getElementById('editNameModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditNameModal();
            }
        });

        // Initialize
        updateUnreadCounts();
        setInterval(updateUnreadCounts, 10000);
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>