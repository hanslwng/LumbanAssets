/**
 * LGU GSO Assistant — rule-based answers (curated policy text) + optional live stats from api/dashboard.php.
 * No external LLM API. Requires apiGet from app.js (load this script after app.js).
 */
(function () {
    'use strict';

    function normalize(q) {
        return String(q || '')
            .toLowerCase()
            .replace(/\s+/g, ' ')
            .trim();
    }

    function escHtml(text) {
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    function formatPhp(n) {
        const v = parseFloat(n, 10);
        if (!Number.isFinite(v)) return '₱0';
        return '₱' + v.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    const DISCLAIMER =
        '\n\n— *Opisyal na transaksyon*: kumpletuhin ang tamang form at humingi ng kumpirmasyon sa GSO kung may alinlangan. / Official transactions need proper documentation and in-person processing at GSO when required.';

    const WELCOME =
        '**Kumusta.** Ako ang gabay para sa GSO (General Services Office) at sa **property inventory system** na ito.\n\n' +
        'Pumili ng tanong sa buttons sa ibaba, o mag-type ng tanong (English o Filipino).\n\n' +
        '***Paalala:*** Hindi ako humuhula ng patakaran na wala sa gabay namin. Para sa detalye ng inyong LGU, kumpirmahin sa GSO.';

    const TOPICS = [
        {
            id: 'greet',
            test: q => /^(hi|hello|hey|kamusta|musta|magandang|good\s(morning|afternoon|evening)|kumusta)\b/i.test(q),
            text:
                '**Hello.** Pwede akong tumulong sa:\n' +
                '1) Tungkol sa GSO (RIS, procurement, property, motor pool, janitorial, records)\n' +
                '2) Paano gamitin ang inventory app (encode, issue PAR/ICS, reports)\n' +
                '3) Live na bilang ng nakarekord na property items (kung may access ang account mo)\n\n' +
                'Ano ang gusto mong malaman?',
        },
        {
            id: 'stats',
            test: q =>
                /\b(stats|statistic|dashboard|counts?|inventory\s+count|total\s+(na\s+)?item|bilang\s+ng|ilan\s+ang|how\s+many)\b/i.test(
                    q,
                ),
            async: true,
        },
        {
            id: 'system',
            test: q =>
                /\b(app|system|software|inventory\s+system|encode|dashboard|sidebar|property\s+number|button)\b/i.test(
                    q,
                ) ||
                /\b(paano\s+(gamitin|mag-encode)|saan\s+(ang|andito))\b/i.test(q),
            text:
                '**Property inventory system (summary)**\n\n' +
                '**1.** **Dashboard** — buod ng bilang (Semi-Expendable, PPE, issued, atbp.).\n' +
                '**2.** **Encode / bagong item** — gamitin ang Encode sa topbar / modal; kailangan ang **office**.\n' +
                '**3.** Item na **≥ ₱50,000** unit value ay **PPE** (PAR); **below** ay **Semi-Expendable** (ICS).\n' +
                '**4.** **Issue** — magrekord ng PAR o ICS issuance; **Return** kapag ibinalik.\n' +
                '**5.** **COA report / physical count** — mula sa listahan ng SE o PPE kung available.\n\n' +
                '**Paalala:** Ang **department staff** ay karaniwang nakaka-access lamang sa **Procurement** workspace; ang **Admin** ang may buong sidebar (inventory, issuance, atbp.).',
        },
        {
            id: 'gso',
            test: q => /\bgso\b|general\s+services/i.test(q) || /\bagenc(y|iya)\s+ng\s+gso\b/i.test(q),
            text:
                '**GSO overview**\n\n' +
                'Ang **General Services Office** ay kadalasang nag-aasikaso ng housekeeping, maintenance, supply at **property/asset management**, at iba pang suportang serbisyo ng LGU. ' +
                'Maaari itong nasa ilalim ng **Office of the Mayor** o **Administrative Office** depende sa istruktura ng inyong LGU.\n\n' +
                'Pangunahing tungkulin: procurement, supply, **vehicle pool**, **property**, janitorial/maintenance, at tala ng government properties.',
        },
        {
            id: 'ris',
            test: q => /\bris\b|requisition|issue\s+slip/i.test(q) || /\bkailangan\s+ng\s+materyales\b/i.test(q),
            text:
                '**RIS (Requisition and Issue Slip)**\n\n' +
                '**1.** Mags accomplished ng **RIS** ang empleyado para humingi ng supply/materyales.\n' +
                '**2.** Dapat aprubahan ng **office/department head** bago ipadala sa GSO.\n' +
                '**3.** Sasagot ang GSO base sa available stock, o mag-uumpisa ng procurement kung kulang.\n' +
                '**4.** Dapat tugma ang kahilingan sa **Annual Procurement Plan (APP)**.',
        },
        {
            id: 'procurement',
            test: q =>
                /\b(9184|ra\s*9184|procurement|bidding|bac|purchase\s+request|\bpr\b|obr|shopping\s+procurement)\b/i.test(
                    q,
                ),
            text:
                '**Procurement (sundin ang RA 9184)**\n\n' +
                '**Modes** (hal.): Shopping, Small Value Procurement, Negotiated Procurement, Public Bidding.\n' +
                '**PR** dapat may tamang **budget certification (OBR)**.\n' +
                'Ang **BAC** ang namamahala sa public bidding.\n' +
                'Dapat **inspeksyunin ng Inspection Committee** ang delivered goods bago tanggapin.',
        },
        {
            id: 'property',
            test: q =>
                /\b(par\b|property\s+number|pps\b|ppe\b|semi|ics|custodian|physical\s+inventory|coa)\b/i.test(
                    q,
                ) || /\b(nawala|nasira)\s+ang\s+(ari-arian|property)\b/i.test(q),
            text:
                '**Property & asset management**\n\n' +
                '- Nirerekord ang lahat ng LGU properties at may **Property Number**.\n' +
                '- **PAR** — Property Acknowledgment Receipt para sa gamit ng empleyado.\n' +
                '- **ICS** — Inventory Custodian Slips para sa **semi-expendable** items.\n' +
                '- Taunang **physical inventory**, isinusumite sa **COA**.\n' +
                '- Kung **nawala o nasira**, i-report kaagad para sa tamang accountability.',
        },
        {
            id: 'vehicle',
            test: q => /\b(vehicle|motor\s+pool|trip\s+ticket|sasakyang\s+pampamahalaan)\b/i.test(q),
            text:
                '**Motor pool & vehicles**\n\n' +
                '**1.** Opisyal na gamit lamang ang government vehicles.\n' +
                '**2.** Magsubmit ng **Trip Ticket** bago gumamit.\n' +
                '**3.** Kailangan ng approval ng **Department Head** at **GSO**.\n' +
                '**4.** May **driver logbook** at **fuel consumption** reports bawat sasakyan.\n' +
                '**5.** Bawal ang hindi awtorisadong gamit ayon sa batas.',
        },
        {
            id: 'janitorial',
            test: q => /\b(janitorial|maintenance|job\s+order|kumpuni|gusali)\b/i.test(q),
            text:
                '**Janitorial & maintenance**\n\n' +
                '- Ang GSO kadalasang humahawak ng routine maintenance ng mga gusali at pasilidad.\n' +
                '- Gumamit ng **Job Order Request Form** para sa maintenance requests.\n' +
                '- Urgent repairs — nire-prioritize ng GSO Maintenance Section.\n' +
                '- May **preventive maintenance** schedule kada pasilidad.',
        },
        {
            id: 'records',
            test: q => /\b(surplus|disposal|ledger|consumption\s+report|unserviceable|waste\s+auction)\b/i.test(q),
            text:
                '**Records & inventory**\n\n' +
                '- May **general ledger** ng supplies at properties ang GSO.\n' +
                '- **Buwanang consumption reports** kadalasang isinumite sa Accounting.\n' +
                '- Surplus disposal — sundin ang **COA** at **GPPB**.\n' +
                '- Waste / unserviceable — auction o condemnation proceedings ayon sa proseso.',
        },
        {
            id: 'compliance',
            test: q => /\b(coa\b|policy|compliance|dokumento|unsigned)/i.test(q),
            text:
                '**Policies & compliance**\n\n' +
                '- Dapat sumunod ang transaksyon sa **COA** regulations.\n' +
                '- **Kumpleto at lagda** ang dokumento bago iproseso.\n' +
                '- Hindi kumpleto o walang pirma — maaaring **ibalik sa opisina**.',
        },
        {
            id: 'contact',
            test: q =>
                /\b(contact|punta|escalate|tumawag|saan\s+(ang|office)\s+gso|opisina)\b/i.test(q) ||
                /\b(hindi\s+k\s+nasagot)\b/i.test(q),
            text:
                '**Contact & escalation**\n\n' +
                '- Para sa hindi nasagot dito at **LGU-specific** na patakaran — **bisitahin ang GSO office** o **GSO Head**.\n' +
                '- Mga urgent na bagay — maaari sa **Administrative Officer** o **Office of the Mayor**.\n' +
                '- Opisyal na transaksyon — **personal** na may dokumento ay mas angkop.',
        },
    ];

    async function fetchStatsAnswer() {
        if (typeof apiGet !== 'function') {
            return 'Hindi makonekta ang tulong sa datos (technical). Subukan muli mamaya.';
        }
        const result = await apiGet('dashboard.php');
        if (!result || !result.success || !result.stats) {
            return (
                'Hindi makuha ang **live statistics** — maaaring kailanganin ang login o access sa **View**. ' +
                'Tingnan ang **Dashboard** sa sidebar para sa buod kapag nakalog-in ka.'
            );
        }
        const s = result.stats;
        const tv = formatPhp(s.total_value || 0);
        return (
            '**Live figures (inventory system)** · mula sa database ng ngayong session:\n\n' +
            `• Kabuuang nakarekord na items: **${s.total_items ?? 0}**\n` +
            `• Semi-Expendable: **${s.se_count ?? 0}**\n` +
            `• PPE: **${s.ppe_count ?? 0}**\n` +
            `• Active: **${s.active_count ?? 0}** · Issued: **${s.issued_count ?? 0}**\n` +
            `• Unserviceable: **${s.unserviceable_count ?? 0}** · Disposed: **${s.disposed_count ?? 0}**\n` +
            `• Issuance records (total): **${s.issuance_count ?? 0}**\n` +
            `• Return records (total): **${s.return_count ?? 0}**\n` +
            `• Total book value (Σ unit × qty): **${tv}**\n\n` +
            'Para sa iba pang detalye, buksan ang **Dashboard**, **Semi-Exp.,** o **PPE** pages.'
        );
    }

    async function resolveAnswer(qRaw) {
        const q = normalize(qRaw);
        if (!q) {
            return WELCOME;
        }
        for (const topic of TOPICS) {
            if (!topic.test(q)) continue;
            if (topic.async) {
                return (await fetchStatsAnswer()) + DISCLAIMER;
            }
            return topic.text + DISCLAIMER;
        }
        return (
            'Paumanhin, wala ako sa lista ng kasagutan para sa eksaktong tanong na iyon. ' +
            '**Kumpirmahin sa GSO** ang patakaran o form ng inyong LGU.\n\n' +
            'Subukan: *“RIS”*, *“procurement”*, *“PAR ICS”*, *“motor pool”*, *“how many items”*.'
        );
    }

    function formatMarkdownish(s) {
        return escHtml(s)
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\n/g, '<br/>');
    }

    function appendBubble(container, role, html) {
        const div = document.createElement('div');
        div.className = 'gso-chatbot-msg gso-chatbot-msg--' + role;
        div.innerHTML = '<div class="gso-chatbot-bubble">' + html + '</div>';
        container.appendChild(div);
        container.scrollTop = container.scrollHeight;
    }

    const CHIPS = [
        'Office ng GSO?',
        'Ano ang RIS?',
        'PAR at ICS?',
        'Ilan ang items? (live)',
        'Paano gamitin ang app?',
        'Motor pool / Trip ticket',
        'Kanino mag-escalate?',
    ];

    function wireChips(panel, chipsEl, messagesEl, sendFn) {
        chipsEl.innerHTML = '';
        CHIPS.forEach(label => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'gso-chatbot-chip';
            b.textContent = label;
            b.addEventListener('click', () => sendFn(label));
            chipsEl.appendChild(b);
        });
    }

    async function sendFn(messagesEl, chipsEl, text) {
        const trimmed = String(text || '').trim();
        if (!trimmed) return;
        appendBubble(messagesEl, 'user', escHtml(trimmed));

        appendBubble(messagesEl, 'assistant', '<span class="gso-chatbot-typing">…</span>');
        const nodes = messagesEl.querySelectorAll('.gso-chatbot-msg--assistant .gso-chatbot-bubble');
        const bubble = nodes[nodes.length - 1];

        const answer = await resolveAnswer(trimmed);
        bubble.innerHTML = formatMarkdownish(answer);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function init() {
        const root = document.getElementById('gso-chatbot-root');
        if (!root) return;

        const toggle = document.getElementById('gso-chatbot-toggle');
        const panel = document.getElementById('gso-chatbot-panel');
        const closeBtn = document.getElementById('gso-chatbot-close');
        const messagesEl = document.getElementById('gso-chatbot-messages');
        const chipsEl = document.getElementById('gso-chatbot-chips');
        const form = document.getElementById('gso-chatbot-form');
        const input = document.getElementById('gso-chatbot-input');

        wireChips(panel, chipsEl, messagesEl, t => sendFn(messagesEl, chipsEl, t));
        appendBubble(messagesEl, 'assistant', formatMarkdownish(WELCOME));

        function openPanel(open) {
            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                setTimeout(() => input.focus(), 100);
            }
        }

        toggle.addEventListener('click', () => openPanel(panel.hidden));
        closeBtn.addEventListener('click', () => openPanel(false));

        form.addEventListener('submit', async e => {
            e.preventDefault();
            const t = input.value;
            input.value = '';
            await sendFn(messagesEl, chipsEl, t);
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
