/**
 * GSO Assistant — free-form chat (api/chatbot.php). No preset question chips.
 */
(function () {
    'use strict';

    const HISTORY_LIMIT = 6;
    const history = [];

    function escHtml(text) {
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    function formatMarkdownish(s) {
        return escHtml(s)
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\n/g, '<br/>');
    }

    function chatbotUrl() {
        if (typeof lgueAppUrl === 'function') {
            return lgueAppUrl('/api/chatbot.php');
        }
        return 'api/chatbot.php';
    }

    const WELCOME_GUIDE =
        '**Kumusta.** Ako ang GSO Assistant para sa Property Inventory system na ito.\n\n' +
        'Magtanong nang libre — encode, SE/PPE, PAR, procurement, Forecast (consumable PR ₱), unserviceable, settings, live counts, at mabibigat na how-to.\n\n' +
        'Department login: masasagot ko ang tanong tungkol sa sistema, pero ang live numbers ay **office ninyo lang**.\n\n' +
        'Kung wala sa app, sasabihin ko nang direkta. Opisyal na desisyon — kumpirmahin sa GSO.';

    const WELCOME_AI =
        '**Kumusta.** AI mode: grounded sa **module map + live database + deep FAQ** ng kasalukuyang system.\n\n' +
        'Magtanong nang libre — even heavy questions (issuance blockers, Forecast allotted budget, PO/AIR vs PR lines, Settings, prints).\n\n' +
        'Department accounts: how-to for the whole workflow is OK; **other offices’ records stay private**.\n\n' +
        'Hindi ako mag-iimbento ng menu na wala sa app. Para sa opisyal na desisyon, GSO pa rin.';

    function appendBubble(container, role, html) {
        const div = document.createElement('div');
        div.className = 'gso-chatbot-msg gso-chatbot-msg--' + role;
        div.innerHTML = '<div class="gso-chatbot-bubble">' + html + '</div>';
        container.appendChild(div);
        container.scrollTop = container.scrollHeight;
    }

    function setModeLabel(aiReady) {
        const sub = document.getElementById('gso-chatbot-head-sub');
        if (sub) {
            sub.textContent = aiReady
                ? 'AI · ask freely · grounded in this system'
                : 'Guide · ask freely · this system + live data';
        }
        const badge = document.getElementById('gso-chatbot-mode');
        if (badge) {
            badge.hidden = false;
            badge.textContent = aiReady ? 'AI' : 'Guide';
            badge.classList.toggle('gso-chatbot-mode--ai', !!aiReady);
        }
    }

    async function fetchStatus() {
        try {
            const res = await fetch(chatbotUrl(), { credentials: 'same-origin' });
            const data = await res.json();
            return !!(data && data.success && data.ai_ready);
        } catch (e) {
            return false;
        }
    }

    async function askServer(text) {
        const payload = { message: text, history: history.slice(-HISTORY_LIMIT) };
        const res = await fetch(chatbotUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });
        const raw = await res.text();
        try {
            return JSON.parse(raw);
        } catch (e) {
            return { success: false, message: 'Assistant could not answer right now.' };
        }
    }

    async function sendFn(messagesEl, text, inputEl) {
        const trimmed = String(text || '').trim();
        if (!trimmed) return;
        appendBubble(messagesEl, 'user', escHtml(trimmed));

        appendBubble(messagesEl, 'assistant', '<span class="gso-chatbot-typing">Thinking…</span>');
        const nodes = messagesEl.querySelectorAll('.gso-chatbot-msg--assistant .gso-chatbot-bubble');
        const bubble = nodes[nodes.length - 1];

        const result = await askServer(trimmed);
        const answer = result && result.success
            ? String(result.answer || '')
            : (result && result.message) || 'Hindi makasagot ngayon. Subukan muli o tanungin ang GSO.';
        bubble.innerHTML = formatMarkdownish(answer);
        messagesEl.scrollTop = messagesEl.scrollHeight;

        if (result && result.success) {
            history.push({ role: 'user', content: trimmed });
            history.push({ role: 'assistant', content: answer });
            if (history.length > HISTORY_LIMIT * 2) {
                history.splice(0, history.length - HISTORY_LIMIT * 2);
            }
            if (typeof result.ai_ready === 'boolean') {
                setModeLabel(result.ai_ready);
            }
        }
        if (inputEl) {
            inputEl.focus();
        }
    }

    function autoResize(ta) {
        if (!ta) return;
        ta.style.height = 'auto';
        ta.style.height = Math.min(120, Math.max(44, ta.scrollHeight)) + 'px';
    }

    function init() {
        const root = document.getElementById('gso-chatbot-root');
        if (!root) return;

        const toggle = document.getElementById('gso-chatbot-toggle');
        const panel = document.getElementById('gso-chatbot-panel');
        const closeBtn = document.getElementById('gso-chatbot-close');
        const messagesEl = document.getElementById('gso-chatbot-messages');
        const form = document.getElementById('gso-chatbot-form');
        const input = document.getElementById('gso-chatbot-input');

        appendBubble(messagesEl, 'assistant', formatMarkdownish(WELCOME_GUIDE));
        fetchStatus().then(aiReady => {
            setModeLabel(aiReady);
            if (aiReady) {
                const first = messagesEl.querySelector('.gso-chatbot-msg--assistant .gso-chatbot-bubble');
                if (first && history.length === 0) {
                    first.innerHTML = formatMarkdownish(WELCOME_AI);
                }
            }
        });

        function openPanel(open) {
            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            root.classList.toggle('gso-chatbot-root--open', !!open);
            if (open) {
                setTimeout(() => {
                    input.focus();
                    autoResize(input);
                }, 100);
            }
        }

        toggle.addEventListener('click', () => openPanel(panel.hidden));
        closeBtn.addEventListener('click', () => openPanel(false));

        input.addEventListener('input', () => autoResize(input));
        input.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                form.requestSubmit();
            }
        });

        form.addEventListener('submit', async e => {
            e.preventDefault();
            const t = input.value;
            input.value = '';
            autoResize(input);
            const sendBtn = form.querySelector('[type="submit"]');
            if (typeof runBusy === 'function' && sendBtn) {
                await runBusy(sendBtn, () => sendFn(messagesEl, t, input), 'Sending…');
            } else {
                await sendFn(messagesEl, t, input);
            }
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && !panel.hidden) openPanel(false);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
