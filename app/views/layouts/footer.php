            </main>
        </div>
    </div>
    
    <!-- Chatbot Button (Placeholder) -->
    <!-- TODO: Integrate chatbot API here (OpenAI or custom NLP) -->
    <button class="chatbot-button" id="chatbot-button" title="Chat Assistant">
        <i class="fas fa-comments"></i>
    </button>
    
    <!-- Chatbot Panel (Placeholder) -->
    <div class="chatbot-panel" id="chatbot-panel">
        <div class="chatbot-header">
            <span><i class="fas fa-robot"></i> Chat Assistant</span>
            <button id="chatbot-close" style="background: none; border: none; color: white; cursor: pointer; font-size: 18px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="chatbot-body" id="chatbot-body">
            <div style="padding: 20px; text-align: center; color: #666;">
                <i class="fas fa-robot" style="font-size: 48px; margin-bottom: 15px; color: #B22222;"></i>
                <p>Chat assistant is under development.</p>
                <p style="font-size: 12px; margin-top: 10px;">This will help you navigate the system and answer questions.</p>
            </div>
        </div>
        <div class="chatbot-footer">
            <input type="text" class="chatbot-input" id="chatbot-input" placeholder="Type a message...">
            <button class="btn btn-primary" id="chatbot-send">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="/public/assets/js/app.js"></script>
    <script src="/public/assets/js/dashboard-enhancements.js"></script>
</body>
</html>
