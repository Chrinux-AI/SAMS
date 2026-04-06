<?php

/**
 * Attendance AI Bot - AI Assistant Widget
 * Context-aware chatbot with role-based responses
 * Floating widget available on all pages
 */

if (defined('SAMS_BOT_WIDGET_RENDERED')) {
    return;
}
define('SAMS_BOT_WIDGET_RENDERED', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get user context
$user_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? ($_SESSION['user_role'] ?? 'guest');
$user_id = $_SESSION['user_id'] ?? 0;
?>

<!-- Attendance AI Bot Floating Widget -->
<div id="samsBot" class="sams-bot-widget">
    <button id="samsBotToggle" class="bot-toggle-btn" onclick="toggleSamsBot()" title="Open Attendance AI Assistant">
        <i class="fas fa-robot"></i>
        <span class="bot-pulse"></span>
    </button>

    <div id="samsBotPanel" class="bot-panel" style="display: none;">
        <div class="bot-header">
            <div class="bot-avatar">
                <i class="fas fa-robot"></i>
            </div>
            <div class="bot-info">
                <div class="bot-name">Attendance AI Assistant</div>
                <div class="bot-status">
                    <span class="status-dot"></span> Ready to Help
                </div>
            </div>
            <button onclick="toggleSamsBot()" class="bot-close" title="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="bot-context-bar">
            <i class="fas fa-user-circle"></i>
            <span><?php echo htmlspecialchars($user_name); ?> | <?php echo ucfirst($user_role); ?></span>
        </div>

        <div id="botMessages" class="bot-messages">
            <div class="bot-message bot">
                <div class="message-avatar"><i class="fas fa-robot"></i></div>
                <div class="message-content">
                    <p>Hello <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>. I am your Attendance AI Assistant.</p>
                    <p><strong>I can help you with:</strong></p>
                    <ul>
                        <?php if ($user_role === 'student'): ?>
                            <li>Check attendance and grades</li>
                            <li>View your schedule</li>
                            <li>Assignment information</li>
                            <li>Page navigation help</li>
                        <?php elseif ($user_role === 'teacher'): ?>
                            <li>Draft parent messages</li>
                            <li>Class statistics guidance</li>
                            <li>Student insights and trends</li>
                            <li>Feature walkthroughs</li>
                        <?php elseif ($user_role === 'parent'): ?>
                            <li>Children status overview</li>
                            <li>Grade reports</li>
                            <li>Fee information</li>
                            <li>Teacher communication options</li>
                        <?php elseif ($user_role === 'admin'): ?>
                            <li>System analytics</li>
                            <li>User management guidance</li>
                            <li>Security log navigation</li>
                            <li>Technical support pointers</li>
                        <?php else: ?>
                            <li>General navigation assistance</li>
                            <li>Attendance and reporting help</li>
                            <li>Feature discovery</li>
                        <?php endif; ?>
                    </ul>
                    <p><small style="opacity: 0.7;">Tip: Use quick actions below or type your question.</small></p>
                </div>
            </div>
        </div>

        <div class="bot-quick-actions">
            <?php if ($user_role === 'student'): ?>
                <button onclick="quickAsk('What is my attendance percentage?')" class="quick-btn">
                    <i class="fas fa-chart-line"></i> My Attendance
                </button>
                <button onclick="quickAsk('Show my class schedule')" class="quick-btn">
                    <i class="fas fa-calendar"></i> Schedule
                </button>
                <button onclick="quickAsk('What assignments are due soon?')" class="quick-btn">
                    <i class="fas fa-tasks"></i> Assignments
                </button>
                <button onclick="quickAsk('How do I check my grades?')" class="quick-btn">
                    <i class="fas fa-graduation-cap"></i> Grades
                </button>
            <?php elseif ($user_role === 'teacher'): ?>
                <button onclick="quickAsk('Summarize today&#39;s attendance')" class="quick-btn">
                    <i class="fas fa-clipboard-check"></i> Today's Attendance
                </button>
                <button onclick="quickAsk('Draft parent message about field trip')" class="quick-btn">
                    <i class="fas fa-envelope"></i> Draft Message
                </button>
                <button onclick="quickAsk('How do I upload resources?')" class="quick-btn">
                    <i class="fas fa-upload"></i> Upload Guide
                </button>
                <button onclick="quickAsk('Show student behavior trends')" class="quick-btn">
                    <i class="fas fa-chart-bar"></i> Behavior Stats
                </button>
            <?php elseif ($user_role === 'parent'): ?>
                <button onclick="quickAsk('Show my children&#39;s attendance')" class="quick-btn">
                    <i class="fas fa-child"></i> Attendance
                </button>
                <button onclick="quickAsk('Are there any pending fees?')" class="quick-btn">
                    <i class="fas fa-wallet"></i> Fee Status
                </button>
                <button onclick="quickAsk('How do I book a teacher meeting?')" class="quick-btn">
                    <i class="fas fa-calendar-check"></i> Book Meeting
                </button>
                <button onclick="quickAsk('Check children&#39;s grades')" class="quick-btn">
                    <i class="fas fa-star"></i> Grades
                </button>
            <?php elseif ($user_role === 'admin'): ?>
                <button onclick="quickAsk('System health overview')" class="quick-btn">
                    <i class="fas fa-heartbeat"></i> System Health
                </button>
                <button onclick="quickAsk('How to backup database?')" class="quick-btn">
                    <i class="fas fa-database"></i> Backup Guide
                </button>
                <button onclick="quickAsk('Show recent security alerts')" class="quick-btn">
                    <i class="fas fa-shield-alt"></i> Security
                </button>
                <button onclick="quickAsk('User statistics summary')" class="quick-btn">
                    <i class="fas fa-users"></i> User Stats
                </button>
            <?php endif; ?>
        </div>

        <div class="bot-input-area">
            <input type="text" id="botInput" placeholder="Ask me anything..." class="bot-input" onkeypress="handleBotEnter(event)">
            <button onclick="sendBotMessage()" class="bot-send-btn" title="Send message">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<style>
    .sams-bot-widget {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 10000;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    .bot-toggle-btn {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: linear-gradient(135deg, #4F46E5, #6366F1);
        border: none;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);
        transition: all 0.3s ease;
        position: relative;
    }

    .bot-toggle-btn:hover {
        transform: scale(1.08);
        box-shadow: 0 6px 20px rgba(79, 70, 229, 0.5);
    }

    .bot-pulse {
        position: absolute;
        top: -3px;
        right: -3px;
        width: 14px;
        height: 14px;
        background: #10B981;
        border-radius: 50%;
        border: 2px solid #fff;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            transform: scale(1);
            opacity: 1;
        }

        50% {
            transform: scale(1.2);
            opacity: 0.7;
        }
    }

    .bot-panel {
        position: fixed;
        bottom: 86px;
        right: 20px;
        width: 380px;
        max-width: calc(100vw - 40px);
        height: 560px;
        max-height: calc(100vh - 120px);
        background: #ffffff;
        border: 1px solid #E5E7EB;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0, 0, 0, 0.05);
        display: flex;
        flex-direction: column;
        animation: slideUp 0.25s ease;
        overflow: hidden;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(12px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .bot-header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 18px;
        background: linear-gradient(135deg, #4F46E5, #6366F1);
        color: white;
    }

    .bot-avatar {
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: white;
        flex-shrink: 0;
    }

    .bot-info {
        flex: 1;
    }

    .bot-name {
        font-weight: 600;
        color: #ffffff;
        font-size: 0.95rem;
    }

    .bot-status {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.8);
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .status-dot {
        width: 7px;
        height: 7px;
        background: #34D399;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }

    .bot-close {
        background: rgba(255, 255, 255, 0.15);
        border: none;
        color: white;
        font-size: 1rem;
        cursor: pointer;
        padding: 6px 8px;
        border-radius: 8px;
        transition: background 0.2s;
    }

    .bot-close:hover {
        background: rgba(255, 255, 255, 0.25);
    }

    .bot-context-bar {
        padding: 8px 18px;
        background: #F9FAFB;
        border-bottom: 1px solid #E5E7EB;
        font-size: 0.8rem;
        color: #6B7280;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .bot-messages {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: #F9FAFB;
    }

    .bot-messages::-webkit-scrollbar {
        width: 4px;
    }

    .bot-messages::-webkit-scrollbar-track {
        background: transparent;
    }

    .bot-messages::-webkit-scrollbar-thumb {
        background: #D1D5DB;
        border-radius: 4px;
    }

    .bot-message {
        display: flex;
        gap: 8px;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(6px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .bot-message.user {
        flex-direction: row-reverse;
    }

    .message-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #4F46E5;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 0.8rem;
        flex-shrink: 0;
    }

    .bot-message.user .message-avatar {
        background: #6366F1;
    }

    .message-content {
        background: #ffffff;
        padding: 10px 14px;
        border-radius: 12px;
        border: 1px solid #E5E7EB;
        max-width: 78%;
        color: #1F2937;
        font-size: 0.88rem;
        line-height: 1.5;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    }

    .bot-message.user .message-content {
        background: #4F46E5;
        color: white;
        border-color: #4F46E5;
    }

    .message-content ul {
        margin: 8px 0;
        padding-left: 18px;
    }

    .message-content li {
        margin: 4px 0;
    }

    .message-content p {
        margin: 0 0 6px 0;
    }

    .message-content p:last-child {
        margin-bottom: 0;
    }

    .bot-rich-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
    }

    .bot-action-chip {
        border: 1px solid #c7d2fe;
        background: #eef2ff;
        color: #3730a3;
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 0.74rem;
        cursor: pointer;
        font-family: inherit;
    }

    .bot-learning-cards {
        margin-top: 10px;
        display: grid;
        gap: 8px;
    }

    .bot-learning-card {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        background: #ffffff;
        padding: 9px 10px;
    }

    .bot-learning-card strong {
        display: block;
        margin-bottom: 4px;
        font-size: 0.79rem;
        color: #111827;
    }

    .bot-learning-card p {
        font-size: 0.77rem;
        color: #4b5563;
        margin: 0 0 7px 0;
    }

    .bot-quick-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px;
        padding: 10px 16px;
        background: #ffffff;
        border-top: 1px solid #E5E7EB;
    }

    .quick-btn {
        padding: 7px 10px;
        background: #F3F4F6;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        color: #374151;
        font-size: 0.78rem;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 5px;
        font-family: inherit;
    }

    .quick-btn:hover {
        background: #EEF2FF;
        border-color: #C7D2FE;
        color: #4F46E5;
    }

    .quick-btn i {
        color: #6366F1;
        font-size: 0.75rem;
    }

    .bot-input-area {
        display: flex;
        gap: 8px;
        padding: 12px 16px;
        background: #ffffff;
        border-top: 1px solid #E5E7EB;
    }

    .bot-input {
        flex: 1;
        padding: 10px 14px;
        background: #F3F4F6;
        border: 1px solid #E5E7EB;
        border-radius: 24px;
        color: #1F2937;
        font-size: 0.88rem;
        font-family: inherit;
        transition: border-color 0.2s;
    }

    .bot-input::placeholder {
        color: #9CA3AF;
    }

    .bot-input:focus {
        outline: none;
        border-color: #4F46E5;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }

    .bot-send-btn {
        width: 40px;
        height: 40px;
        background: #4F46E5;
        border: none;
        border-radius: 50%;
        color: white;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .bot-send-btn:hover {
        background: #4338CA;
        transform: scale(1.05);
    }

    .typing-indicator {
        display: flex;
        gap: 4px;
        padding: 8px 12px;
    }

    .typing-dot {
        width: 7px;
        height: 7px;
        background: #9CA3AF;
        border-radius: 50%;
        animation: typing 1.4s infinite;
    }

    .typing-dot:nth-child(2) {
        animation-delay: 0.2s;
    }

    .typing-dot:nth-child(3) {
        animation-delay: 0.4s;
    }

    @keyframes typing {

        0%,
        60%,
        100% {
            transform: translateY(0);
        }

        30% {
            transform: translateY(-8px);
        }
    }

    @media (max-width: 480px) {
        .bot-panel {
            width: calc(100vw - 20px);
            right: 10px;
            bottom: 80px;
            height: calc(100vh - 100px);
        }

        .sams-bot-widget {
            bottom: 12px;
            right: 12px;
        }
    }
</style>

<script>
    function toggleSamsBot(forceOpen = null) {
        const panel = document.getElementById('samsBotPanel');
        if (!panel) return;

        const shouldOpen = forceOpen === null ? panel.style.display !== 'flex' : Boolean(forceOpen);
        panel.style.display = shouldOpen ? 'flex' : 'none';

        if (shouldOpen) {
            const input = document.getElementById('botInput');
            if (input) input.focus();
        }
    }

    function quickAsk(question) {
        document.getElementById('botInput').value = question;
        sendBotMessage();
    }

    function handleBotEnter(event) {
        if (event.key === 'Enter') {
            sendBotMessage();
        }
    }

    async function sendBotMessage() {
        const input = document.getElementById('botInput');
        const message = input.value.trim();

        if (!message) return;

        // Add user message
        addBotMessage(message, 'user');
        input.value = '';

        // Show typing indicator
        const typingId = addTypingIndicator();

        try {
            // Send to AI API
            const response = await fetch('/attendance/api/sams-bot.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    message: message,
                    user_role: '<?php echo $user_role; ?>',
                    user_id: '<?php echo $user_id; ?>'
                })
            });

            if (!response.ok) {
                throw new Error('Chatbot API request failed');
            }

            const data = await response.json();

            // Remove typing indicator
            removeTypingIndicator(typingId);

            // Add bot response
            if (data.success) {
                addBotRichMessage(data);
            } else {
                const retryInfo = data.retry_after ? ` Try again in ${data.retry_after}s.` : '';
                addBotMessage('Sorry, I encountered an error. Please try again.' + retryInfo, 'bot');
            }
        } catch (error) {
            removeTypingIndicator(typingId);
            addBotMessage('Sorry, I\'m having trouble connecting. Please check your internet and try again.', 'bot');
        }
    }

    function addBotMessage(content, type) {
        const messagesDiv = document.getElementById('botMessages');
        if (!messagesDiv) return;

        const messageDiv = document.createElement('div');
        messageDiv.className = `bot-message ${type}`;

        const avatar = document.createElement('div');
        avatar.className = 'message-avatar';
        avatar.innerHTML = type === 'bot' ? '<i class="fas fa-robot"></i>' : '<i class="fas fa-user"></i>';

        const contentDiv = document.createElement('div');
        contentDiv.className = 'message-content';
        contentDiv.innerHTML = `<p>${escapeBotHtml(content).replace(/\n/g, '<br>')}</p>`;

        messageDiv.appendChild(avatar);
        messageDiv.appendChild(contentDiv);
        messagesDiv.appendChild(messageDiv);

        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }

    function addBotRichMessage(payload) {
        const messagesDiv = document.getElementById('botMessages');
        if (!messagesDiv) return;

        const messageDiv = document.createElement('div');
        messageDiv.className = 'bot-message bot';

        const avatar = document.createElement('div');
        avatar.className = 'message-avatar';
        avatar.innerHTML = '<i class="fas fa-robot"></i>';

        const contentDiv = document.createElement('div');
        contentDiv.className = 'message-content';

        const msg = escapeBotHtml(payload.response || 'I can help with that.');
        const suggestions = Array.isArray(payload.suggestions) ? payload.suggestions.slice(0, 3) : [];
        const actions = Array.isArray(payload.actions) ? payload.actions : [];
        const cards = Array.isArray(payload.learning_cards) ? payload.learning_cards.slice(0, 2) : [];

        let html = `<p>${msg.replace(/\n/g, '<br>')}</p>`;
        if (suggestions.length) {
            html += `<p><small>Suggestions: ${suggestions.map(s => escapeBotHtml(s)).join(' | ')}</small></p>`;
        }

        if (actions.length) {
            html += '<div class="bot-rich-actions">';
            actions.slice(0, 4).forEach((action, idx) => {
                const label = escapeBotHtml(action.label || 'Open');
                const url = escapeBotHtml(action.url || '#');
                html += `<button class="bot-action-chip" type="button" onclick="botNavigate('${url}')">${label}</button>`;
            });
            html += '</div>';
        }

        if (cards.length) {
            html += '<div class="bot-learning-cards">';
            cards.forEach((card) => {
                const title = escapeBotHtml(card.title || 'Learning');
                const desc = escapeBotHtml(card.description || '');
                const actionLabel = escapeBotHtml(card.action_label || 'Open');
                const actionUrl = escapeBotHtml(card.action_url || '#');
                html += `
                    <div class="bot-learning-card">
                        <strong>${title}</strong>
                        <p>${desc}</p>
                        <button class="bot-action-chip" type="button" onclick="botNavigate('${actionUrl}')">${actionLabel}</button>
                    </div>
                `;
            });
            html += '</div>';
        }

        contentDiv.innerHTML = html;
        messageDiv.appendChild(avatar);
        messageDiv.appendChild(contentDiv);
        messagesDiv.appendChild(messageDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }

    function addTypingIndicator() {
        const messagesDiv = document.getElementById('botMessages');
        const typingDiv = document.createElement('div');
        const id = 'typing-' + Date.now();
        typingDiv.id = id;
        typingDiv.className = 'bot-message bot';
        typingDiv.innerHTML = `
        <div class="message-avatar"><i class="fas fa-robot"></i></div>
        <div class="message-content">
            <div class="typing-indicator">
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
            </div>
        </div>
    `;
        messagesDiv.appendChild(typingDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
        return id;
    }

    function removeTypingIndicator(id) {
        const element = document.getElementById(id);
        if (element) element.remove();
    }

    function escapeBotHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function botNavigate(url) {
        if (!url || url === '#') return;
        window.location.href = url;
    }
</script>
