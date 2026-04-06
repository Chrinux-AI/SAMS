<?php
/**
 * SAMS AI Chatbot Interface - Advanced Learning & Navigation Assistant
 * Modern, accessible, and intelligent chat interface
 */

class SAMS_AI_Chatbot {
    private $ai_assistant;
    private $session_id;
    private $conversation_history;
    
    public function __construct($user_id, $user_role) {
        $this->ai_assistant = new SAMS_AI_Assistant($user_id, $user_role);
        $this->session_id = $this->generateSessionId();
        $this->conversation_history = $this->loadConversationHistory();
    }
    
    /**
     * Generate chatbot HTML interface
     */
    public function renderChatbot() {
        ob_start();
        ?>
        <!-- SAMS AI Chatbot -->
        <div id="sams-ai-chatbot" class="sams-ai-chatbot">
            <!-- Chat Toggle Button -->
            <button id="ai-chat-toggle" class="ai-chat-toggle" onclick="toggleAIChat()" aria-label="Open AI Assistant">
                <i class="fas fa-robot"></i>
                <span class="ai-status-indicator"></span>
            </button>
            
            <!-- Chat Window -->
            <div id="ai-chat-window" class="ai-chat-window">
                <!-- Chat Header -->
                <div class="ai-chat-header">
                    <div class="ai-avatar">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="ai-info">
                        <h3>SAMS AI Assistant</h3>
                        <span class="ai-status">Always here to help</span>
                    </div>
                    <div class="ai-actions">
                        <button onclick="clearAIChat()" class="ai-action-btn" title="Clear Chat">
                            <i class="fas fa-broom"></i>
                        </button>
                        <button onclick="toggleAIChat()" class="ai-action-btn" title="Close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Chat Messages -->
                <div id="ai-chat-messages" class="ai-chat-messages">
                    <div class="ai-welcome-message">
                        <div class="ai-message ai-assistant">
                            <div class="ai-message-content">
                                <p>Hello! 👋 I'm SAMS AI Assistant, your intelligent learning companion.</p>
                                <p>I can help you with:</p>
                                <ul>
                                    <li>📍 Navigation and finding pages</li>
                                    <li>📚 Learning and explanations</li>
                                    <li>📅 Schedule and attendance</li>
                                    <li>💬 Communication and messages</li>
                                    <li>🔍 Search and information</li>
                                </ul>
                                <p>What would you like help with today?</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="ai-quick-actions">
                    <button onclick="sendQuickMessage('Navigate to dashboard')" class="ai-quick-btn">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </button>
                    <button onclick="sendQuickMessage('Show my schedule')" class="ai-quick-btn">
                        <i class="fas fa-calendar"></i>
                        Schedule
                    </button>
                    <button onclick="sendQuickMessage('Check attendance')" class="ai-quick-btn">
                        <i class="fas fa-check-circle"></i>
                        Attendance
                    </button>
                    <button onclick="sendQuickMessage('Help with navigation')" class="ai-quick-btn">
                        <i class="fas fa-question-circle"></i>
                        Help
                    </button>
                </div>
                
                <!-- Chat Input -->
                <div class="ai-chat-input">
                    <div class="ai-input-container">
                        <input 
                            type="text" 
                            id="ai-chat-input" 
                            placeholder="Ask me anything..."
                            onkeypress="handleAIChatInput(event)"
                            aria-label="Type your message"
                        >
                        <button onclick="sendAIMessage()" class="ai-send-btn" aria-label="Send message">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                    <div class="ai-input-suggestions" id="ai-suggestions"></div>
                </div>
                
                <!-- Typing Indicator -->
                <div id="ai-typing" class="ai-typing" style="display: none;">
                    <div class="ai-avatar">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="typing-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        /* SAMS AI Chatbot Styles */
        .sams-ai-chatbot {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 10000;
            font-family: 'Inter', sans-serif;
        }
        
        .ai-chat-toggle {
            position: relative;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4F46E5, #7C3AED);
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(79, 70, 229, 0.3);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .ai-chat-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 30px rgba(79, 70, 229, 0.4);
        }
        
        .ai-status-indicator {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 12px;
            height: 12px;
            background: #10B981;
            border-radius: 50%;
            border: 2px solid white;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .ai-chat-window {
            position: absolute;
            bottom: 80px;
            right: 0;
            width: 380px;
            height: 600px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            display: none;
            flex-direction: column;
            overflow: hidden;
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .ai-chat-window.active {
            display: flex;
        }
        
        .ai-chat-header {
            background: linear-gradient(135deg, #4F46E5, #7C3AED);
            color: white;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .ai-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .ai-info {
            flex: 1;
        }
        
        .ai-info h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .ai-status {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .ai-actions {
            display: flex;
            gap: 8px;
        }
        
        .ai-action-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        
        .ai-action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .ai-chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #F9FAFB;
        }
        
        .ai-message {
            margin-bottom: 16px;
            display: flex;
            gap: 12px;
        }
        
        .ai-message.ai-user {
            flex-direction: row-reverse;
        }
        
        .ai-message-content {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 16px;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .ai-assistant .ai-message-content {
            background: white;
            color: #374151;
            border-bottom-left-radius: 4px;
        }
        
        .ai-user .ai-message-content {
            background: linear-gradient(135deg, #4F46E5, #7C3AED);
            color: white;
            border-bottom-right-radius: 4px;
        }
        
        .ai-quick-actions {
            padding: 15px;
            border-top: 1px solid #E5E7EB;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .ai-quick-btn {
            padding: 8px 12px;
            background: #F3F4F6;
            border: 1px solid #E5E7EB;
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .ai-quick-btn:hover {
            background: #E5E7EB;
            transform: translateY(-1px);
        }
        
        .ai-chat-input {
            padding: 15px;
            border-top: 1px solid #E5E7EB;
        }
        
        .ai-input-container {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        #ai-chat-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #E5E7EB;
            border-radius: 25px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        
        #ai-chat-input:focus {
            border-color: #4F46E5;
        }
        
        .ai-send-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4F46E5, #7C3AED);
            border: none;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s;
        }
        
        .ai-send-btn:hover {
            transform: scale(1.1);
        }
        
        .ai-typing {
            display: flex;
            gap: 12px;
            padding: 15px 20px;
            align-items: center;
        }
        
        .typing-dots {
            display: flex;
            gap: 4px;
            padding: 10px 15px;
            background: white;
            border-radius: 16px;
            border-bottom-left-radius: 4px;
        }
        
        .typing-dots span {
            width: 8px;
            height: 8px;
            background: #9CA3AF;
            border-radius: 50%;
            animation: typing 1.4s infinite;
        }
        
        .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
        .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
        
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-10px); }
        }
        
        /* Responsive Design */
        @media (max-width: 480px) {
            .sams-ai-chatbot {
                bottom: 10px;
                right: 10px;
            }
            
            .ai-chat-window {
                width: calc(100vw - 40px);
                height: 70vh;
                right: -10px;
            }
        }
        
        /* Accessibility */
        .ai-chat-toggle:focus,
        .ai-action-btn:focus,
        .ai-quick-btn:focus,
        .ai-send-btn:focus,
        #ai-chat-input:focus {
            outline: 2px solid #4F46E5;
            outline-offset: 2px;
        }
        </style>
        
        <script>
        // AI Chatbot JavaScript
        let aiChatOpen = false;
        let conversationHistory = [];
        
        function toggleAIChat() {
            const chatWindow = document.getElementById('ai-chat-window');
            const toggleBtn = document.getElementById('ai-chat-toggle');
            
            aiChatOpen = !aiChatOpen;
            
            if (aiChatOpen) {
                chatWindow.classList.add('active');
                toggleBtn.innerHTML = '<i class="fas fa-times"></i><span class="ai-status-indicator"></span>';
                document.getElementById('ai-chat-input').focus();
            } else {
                chatWindow.classList.remove('active');
                toggleBtn.innerHTML = '<i class="fas fa-robot"></i><span class="ai-status-indicator"></span>';
            }
        }
        
        function sendAIMessage() {
            const input = document.getElementById('ai-chat-input');
            const message = input.value.trim();
            
            if (!message) return;
            
            // Add user message
            addMessage(message, 'user');
            input.value = '';
            
            // Show typing indicator
            showTypingIndicator();
            
            // Send to AI
            fetch('includes/ai-chat-handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message: message,
                    history: conversationHistory
                })
            })
            .then(response => response.json())
            .then(data => {
                hideTypingIndicator();
                if (data.success) {
                    addMessage(data.response, 'assistant');
                    if (data.suggestions && data.suggestions.length > 0) {
                        showSuggestions(data.suggestions);
                    }
                }
            })
            .catch(error => {
                hideTypingIndicator();
                addMessage('Sorry, I encountered an error. Please try again.', 'assistant');
            });
        }
        
        function sendQuickMessage(message) {
            document.getElementById('ai-chat-input').value = message;
            sendAIMessage();
        }
        
        function addMessage(message, sender) {
            const messagesContainer = document.getElementById('ai-chat-messages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `ai-message ai-${sender}`;
            
            const content = document.createElement('div');
            content.className = 'ai-message-content';
            content.textContent = message;
            
            messageDiv.appendChild(content);
            messagesContainer.appendChild(messageDiv);
            
            // Scroll to bottom
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            
            // Add to history
            conversationHistory.push({ message, sender, timestamp: Date.now() });
            
            // Keep history manageable
            if (conversationHistory.length > 50) {
                conversationHistory = conversationHistory.slice(-50);
            }
        }
        
        function showTypingIndicator() {
            document.getElementById('ai-typing').style.display = 'flex';
        }
        
        function hideTypingIndicator() {
            document.getElementById('ai-typing').style.display = 'none';
        }
        
        function showSuggestions(suggestions) {
            const container = document.getElementById('ai-suggestions');
            container.innerHTML = '';
            
            suggestions.forEach(suggestion => {
                const btn = document.createElement('button');
                btn.className = 'ai-suggestion-btn';
                btn.textContent = suggestion;
                btn.onclick = () => sendQuickMessage(suggestion);
                container.appendChild(btn);
            });
        }
        
        function clearAIChat() {
            const messagesContainer = document.getElementById('ai-chat-messages');
            messagesContainer.innerHTML = `
                <div class="ai-welcome-message">
                    <div class="ai-message ai-assistant">
                        <div class="ai-message-content">
                            <p>Chat cleared! How can I help you?</p>
                        </div>
                    </div>
                </div>
            `;
            conversationHistory = [];
        }
        
        function handleAIChatInput(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendAIMessage();
            }
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            // Ctrl/Cmd + K to open chat
            if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
                event.preventDefault();
                if (!aiChatOpen) {
                    toggleAIChat();
                } else {
                    document.getElementById('ai-chat-input').focus();
                }
            }
            
            // Escape to close chat
            if (event.key === 'Escape' && aiChatOpen) {
                toggleAIChat();
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    private function generateSessionId() {
        return session_id() . '_' . time();
    }
    
    private function loadConversationHistory() {
        // Load conversation history from database or session
        return [];
    }
}
?>
