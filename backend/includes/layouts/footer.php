<?php
/**
 * SAMS Layout Footer
 * Global footer template for all pages
 */
?>
            </div><!-- /sams-content -->
            
            <!-- Footer -->
            <footer class="sams-footer">
                <div class="footer-left">
                    <span class="copyright">&copy; <?= date('Y') ?> SAMS</span>
                    <span class="version">v2.0.0</span>
                </div>
                <div class="footer-right">
                    <a href="/help.php">Help</a>
                    <a href="/privacy.php">Privacy</a>
                    <a href="/terms.php">Terms</a>
                </div>
            </footer>
        </main>
    </div><!-- /sams-app -->
    
    <!-- Chatbot Widget -->
    <div id="chatbot-widget" class="chatbot-widget">
        <div class="chatbot-header">
            <i class="fas fa-robot"></i>
            <span>SAMS Assistant</span>
            <button id="chatbotClose" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="chatbot-messages" id="chatbotMessages">
            <div class="message bot-message">
                Hi! I'm your SAMS assistant. How can I help you today?
            </div>
        </div>
        <div class="chatbot-input">
            <input type="text" id="chatbotInput" placeholder="Type your question...">
            <button id="chatbotSend" aria-label="Send">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
    
    <!-- Scripts -->
    <script>
        // Sidebar Toggle
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
        });
        
        // Chatbot Toggle
        document.getElementById('chatbotToggle')?.addEventListener('click', function() {
            document.getElementById('chatbot-widget').classList.toggle('open');
        });
        
        document.getElementById('chatbotClose')?.addEventListener('click', function() {
            document.getElementById('chatbot-widget').classList.remove('open');
        });
        
        // Chatbot Send
        document.getElementById('chatbotSend')?.addEventListener('click', sendChatbotMessage);
        document.getElementById('chatbotInput')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendChatbotMessage();
        });
        
        function sendChatbotMessage() {
            const input = document.getElementById('chatbotInput');
            const message = input.value.trim();
            if (!message) return;
            
            // Add user message
            addMessage(message, 'user');
            input.value = '';
            
            // Send to API
            fetch('/api/chatbot.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: message })
            })
            .then(res => res.json())
            .then(data => {
                addMessage(data.response || 'I\'m not sure how to help with that.', 'bot');
            })
            .catch(() => {
                addMessage('Sorry, I\'m having trouble connecting right now.', 'bot');
            });
        }
        
        function addMessage(text, sender) {
            const container = document.getElementById('chatbotMessages');
            const div = document.createElement('div');
            div.className = 'message ' + sender + '-message';
            div.textContent = text;
            container.appendChild(div);
            container.scrollTop = container.scrollHeight;
        }
    </script>
    
    <style>
        .sams-footer {
            height: 48px;
            background: white;
            border-top: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            font-size: 0.875rem;
            color: #64748b;
        }
        .footer-left {
            display: flex;
            gap: 1rem;
        }
        .footer-right {
            display: flex;
            gap: 1rem;
        }
        .footer-right a {
            color: #64748b;
            text-decoration: none;
        }
        .footer-right a:hover {
            color: #4f46e5;
        }
        
        /* Chatbot Widget */
        .chatbot-widget {
            position: fixed;
            bottom: 80px;
            right: 20px;
            width: 350px;
            height: 450px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: none;
            flex-direction: column;
            z-index: 1000;
            overflow: hidden;
        }
        .chatbot-widget.open {
            display: flex;
        }
        .chatbot-header {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .chatbot-header button {
            margin-left: auto;
            background: none;
            border: none;
            color: white;
            cursor: pointer;
        }
        .chatbot-messages {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .message {
            max-width: 80%;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            font-size: 0.9rem;
        }
        .bot-message {
            background: #f1f5f9;
            color: #1e293b;
            align-self: flex-start;
        }
        .user-message {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            align-self: flex-end;
        }
        .chatbot-input {
            padding: 1rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 0.5rem;
        }
        .chatbot-input input {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        .chatbot-input button {
            background: #4f46e5;
            color: white;
            border: none;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            cursor: pointer;
        }
    </style>
</body>
</html>
