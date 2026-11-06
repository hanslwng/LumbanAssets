<!-- GSO Assistant: free-form chat + optional Gemini/OpenAI -->
<?php
$gsoChatAvatar = function_exists('lgue_url')
    ? lgue_url('public/assets/chatbot-avatar.jpg', defined('SYSTEM_VERSION') ? SYSTEM_VERSION : null)
    : '/lgu_invent/public/assets/chatbot-avatar.jpg';
?>
<div id="gso-chatbot-root" class="gso-chatbot-root" aria-live="polite">
    <button type="button"
            id="gso-chatbot-toggle"
            class="gso-chatbot-fab"
            title="GSO Assistant"
            aria-expanded="false"
            aria-controls="gso-chatbot-panel">
        <span class="gso-chatbot-fab-inner" aria-hidden="true">
            <img class="gso-chatbot-fab-avatar"
                 src="<?php echo htmlspecialchars($gsoChatAvatar, ENT_QUOTES, 'UTF-8'); ?>"
                 alt="GSO Assistant"
                 width="56"
                 height="56"
                 decoding="async">
        </span>
    </button>
    <div id="gso-chatbot-panel" class="gso-chatbot-panel" role="dialog" aria-label="GSO Assistant" hidden>
        <div class="gso-chatbot-head">
            <div class="gso-chatbot-head-avatar" aria-hidden="true">
                <img src="<?php echo htmlspecialchars($gsoChatAvatar, ENT_QUOTES, 'UTF-8'); ?>" alt="">
            </div>
            <div class="gso-chatbot-head-text">
                <strong class="gso-chatbot-head-title">GSO Assistant <span id="gso-chatbot-mode" class="gso-chatbot-mode" hidden>Guide</span></strong>
                <span class="gso-chatbot-head-sub" id="gso-chatbot-head-sub">Ask about this system — department logins see only their office data</span>
            </div>
            <button type="button" id="gso-chatbot-close" class="gso-chatbot-iconbtn" aria-label="Close">&times;</button>
        </div>
        <div id="gso-chatbot-messages" class="gso-chatbot-messages"></div>
        <form id="gso-chatbot-form" class="gso-chatbot-form">
            <label class="gso-chatbot-composer">
                <textarea id="gso-chatbot-input"
                          class="gso-chatbot-input"
                          placeholder="Type your question freely (English or Filipino)…"
                          rows="2"
                          maxlength="1500"
                          autocomplete="off"></textarea>
            </label>
            <button type="submit" class="gso-chatbot-send" title="Send">Send</button>
        </form>
        <p class="gso-chatbot-hint">Enter to send · Shift+Enter for new line</p>
    </div>
</div>
