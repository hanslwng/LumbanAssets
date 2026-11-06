(function () {
    const selected = new Set();
    let currentItemId = 0;
    let saveTimer = null;

    function ptagLogoUrl() {
        return typeof lgueAppUrl === 'function'
            ? lgueAppUrl('/public/assets/property-tag-seal.png')
            : '/lgu_invent/public/assets/property-tag-seal.png';
    }

    function ptagApiUrl() {
        return typeof lgueAppUrl === 'function'
            ? lgueAppUrl('/api/property-tags.php')
            : 'api/property-tags.php';
    }

    function ptagPrintUrl(layout, ids) {
        const q = new URLSearchParams();
        q.set('layout', layout);
        q.set('ids', ids.join(','));
        const path = '/print/print-property-tags.php?' + q.toString();
        return typeof lgueAppUrl === 'function' ? lgueAppUrl(path) : 'print/print-property-tags.php?' + q.toString();
    }

    function ptagEsc(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function ptagSelectedIds() {
        return [...selected];
    }

    function ptagSyncChecks() {
        document.querySelectorAll('#pg-semi .ptag-check').forEach((box) => {
            box.checked = selected.has(Number(box.getAttribute('data-item-id')));
        });
        const n = selected.size;
        const label = n ? String(n) + ' selected' : '';
        ['ptag-selected-count', 'ptag-selected-count-detail'].forEach((id) => {
            const countEl = document.getElementById(id);
            if (countEl) countEl.textContent = label;
        });
    }

    window.ptagToggleRow = function (el) {
        const id = Number(el.getAttribute('data-item-id'));
        if (!id) return;
        if (el.checked) selected.add(id);
        else selected.delete(id);
        ptagSyncChecks();
    };

    window.ptagToggleOfficeAll = function (el) {
        const table = el.closest('table');
        if (!table) return;
        table.querySelectorAll('.ptag-check').forEach((box) => {
            box.checked = !!el.checked;
            const id = Number(box.getAttribute('data-item-id'));
            if (!id) return;
            if (el.checked) selected.add(id);
            else selected.delete(id);
        });
        ptagSyncChecks();
    };

    function ptagReadFields() {
        return {
            office_division: document.getElementById('ptag-office_division')?.value || '',
            person_accountable: document.getElementById('ptag-person_accountable')?.value || '',
            acquisition_date: document.getElementById('ptag-acquisition_date')?.value || '',
            description: document.getElementById('ptag-description')?.value || '',
            model_number: document.getElementById('ptag-model_number')?.value || '',
            serial_number: document.getElementById('ptag-serial_number')?.value || '',
        };
    }

    function ptagFillPreview(item, tag) {
        const box = document.getElementById('ptag-preview');
        if (!box) return;
        const t = tag || {};
        const logo = ptagLogoUrl();
        box.innerHTML =
            '<div class="ptag">' +
            '<div class="ptag-c ptag-title">PROPERTY TAG GSO</div>' +
            '<div class="ptag-c ptag-logo"><img src="' + ptagEsc(logo) + '" alt="Bayan ng Lumban seal"/></div>' +
            '<div class="ptag-c ptag-pn"><span class="ptag-lbl"><em>Property Number:</em></span><span class="ptag-val ptag-val-lock">' + ptagEsc(item.property_number || '') + '</span></div>' +
            '<div class="ptag-c ptag-od"><span class="ptag-lbl"><em>Office/Division:</em></span><input class="ptag-inp" id="ptag-office_division" type="text" value="' + ptagEsc(t.office_division || '') + '"/></div>' +
            '<div class="ptag-c ptag-pa"><span class="ptag-lbl"><em>Person Accountable:</em></span><input class="ptag-inp" id="ptag-person_accountable" type="text" value="' + ptagEsc(t.person_accountable || '') + '"/></div>' +
            '<div class="ptag-c ptag-ad"><span class="ptag-lbl"><em>Acquisition Date:</em></span><input class="ptag-inp" id="ptag-acquisition_date" type="date" value="' + ptagEsc(t.acquisition_date || '') + '"/></div>' +
            '<div class="ptag-c ptag-ac"><span class="ptag-lbl"><em>Acquisition Cost:</em></span><span class="ptag-val ptag-val-lock">' + ptagEsc(item.acquisition_cost || '') + '</span></div>' +
            '<div class="ptag-c ptag-desc"><span class="ptag-lbl"><em>Description of Property:</em></span><textarea class="ptag-inp" id="ptag-description" rows="2">' + ptagEsc(t.description || '') + '</textarea></div>' +
            '<div class="ptag-c ptag-mod"><span class="ptag-lbl"><em>Model Number:</em></span><input class="ptag-inp" id="ptag-model_number" type="text" value="' + ptagEsc(t.model_number || '') + '"/></div>' +
            '<div class="ptag-c ptag-ser"><span class="ptag-lbl"><em>Serial Number:</em></span><input class="ptag-inp" id="ptag-serial_number" type="text" value="' + ptagEsc(t.serial_number || '') + '"/></div>' +
            '<div class="ptag-c ptag-sign-lbl"><span class="ptag-lbl"><em>Date of Inventory and Signature of Inventory Team:</em></span></div>' +
            '<div class="ptag-c ptag-sign-l"></div>' +
            '<div class="ptag-c ptag-sign-r"></div>' +
            '<div class="ptag-c ptag-foot">TAMPERING OF THIS LABEL IS STRICTLY PROHIBITED</div>' +
            '</div>';
        const canEdit = typeof userPermissions !== 'undefined' && !!userPermissions.canEdit;
        box.querySelectorAll('.ptag-inp').forEach((inp) => {
            if (!canEdit) {
                inp.readOnly = true;
                return;
            }
            inp.addEventListener('input', () => {
                clearTimeout(saveTimer);
                saveTimer = setTimeout(() => window.savePropertyTag(true), 700);
            });
        });
    }

    window.openPropertyTag = async function (itemId) {
        const id = Number(itemId);
        if (!id) return;
        currentItemId = id;
        const hid = document.getElementById('ptag-item-id');
        if (hid) hid.value = String(id);
        const box = document.getElementById('ptag-preview');
        if (box) box.innerHTML = '<p class="muted">Loading tag…</p>';
        if (typeof openModal === 'function') openModal('property-tag-modal');
        const saveBtn = document.getElementById('ptag-save-btn');
        if (saveBtn) {
            const canEdit = typeof userPermissions !== 'undefined' && !!userPermissions.canEdit;
            saveBtn.style.display = canEdit ? '' : 'none';
        }
        try {
            const res = await fetch(ptagApiUrl() + '?item_id=' + encodeURIComponent(String(id)), {
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (!data.success || !data.data) {
                if (typeof showToast === 'function') showToast(data.message || 'Could not load tag', 'error');
                return;
            }
            ptagFillPreview(data.data.item, data.data.tag);
        } catch (e) {
            if (typeof showToast === 'function') showToast('Could not load tag', 'error');
        }
    };

    window.savePropertyTag = async function (silent) {
        const id = Number(document.getElementById('ptag-item-id')?.value || currentItemId || 0);
        if (!id) return;
        const payload = Object.assign({ item_id: id }, ptagReadFields());
        const btn = document.getElementById('ptag-save-btn');
        const work = async () => {
            const res = await fetch(ptagApiUrl(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            return res.json();
        };
        let data;
        try {
            data = typeof runBusy === 'function' && btn && !silent ? await runBusy('ptag-save-btn', work) : await work();
        } catch (e) {
            if (!silent && typeof showToast === 'function') showToast('Could not save tag fields', 'error');
            return;
        }
        if (!data) return;
        if (data.success) {
            if (!silent && typeof showToast === 'function') showToast('Tag fields saved');
        } else if (typeof showToast === 'function') {
            showToast(data.message || 'Could not save tag fields', 'error');
        }
    };

    window.ptagSelectedIds = ptagSelectedIds;

    window.ptagClearSelection = function () {
        selected.clear();
        ptagSyncChecks();
    };

    window.ptagSetSelectedIds = function (ids) {
        selected.clear();
        (ids || []).forEach((id) => {
            const n = Number(id);
            if (n) selected.add(n);
        });
        ptagSyncChecks();
    };

    window.printPropertyTags = function (layout) {
        const ids = ptagSelectedIds();
        if (!ids.length) {
            if (typeof showToast === 'function') showToast('Select at least one item to print.', 'error');
            return;
        }
        const which = layout === 'long' ? 'long' : 'a4';
        window.open(ptagPrintUrl(which, ids), '_blank', 'noopener');
    };

    window.__ptagSelected = selected;
    window.__ptagSyncChecks = ptagSyncChecks;
})();
