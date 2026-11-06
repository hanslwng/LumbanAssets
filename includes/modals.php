<!-- ═══ ENCODE MODAL ═══ -->
<div class="overlay" id="encode-modal">
<div class="modal modal-lg">
  <div class="m-hd"><span class="m-ttl">Encode New Property Item</span><button class="m-close" onclick="closeModal('encode-modal')">×</button></div>
  <div class="m-body">
    <p class="encode-sheet-hint">Excel-style entry — only <strong>Assign to office</strong> is required to save; everything else can be filled in later via edit.</p>
    <table class="encode-sheet" aria-label="Encode property item">
      <tbody>
        <tr>
          <th>Article</th>
          <td colspan="3"><input type="text" id="e-article" placeholder="Optional — e.g. Office table" oninput="liveClassify()"/></td>
        </tr>
        <tr>
          <th>Description</th>
          <td colspan="3"><input type="text" id="e-desc" placeholder="Optional — specifications / brand / model" oninput="liveClassify()"/></td>
        </tr>
        <tr>
          <th>Report section</th>
          <td colspan="3"><input type="text" id="e-group" placeholder="Optional — same text groups items under one red header + subtotal" title="Leave blank for no section header"/></td>
        </tr>
        <tr>
          <th>Unit of measure</th>
          <td><input type="text" id="e-uom" value="pc" placeholder="pc, set, unit"/></td>
          <th class="encode-sub">Unit value (₱)</th>
          <td><input type="text" inputmode="decimal" id="e-amt" class="lgue-money" placeholder="e.g. 10,000" autocomplete="off"/></td>
        </tr>
        <tr>
          <th>Qty (property card)</th>
          <td><input type="number" id="e-qty-pc" value="1" min="1" title="Optional for PPE — blank saves as 1. Book / ledger quantity"/></td>
          <th class="encode-sub">Qty (physical)</th>
          <td><input type="number" id="e-qty-phys" value="" min="0" placeholder="Optional; blank if not counted" title="Optional for all items — PPE may omit; blank treats count same as card where applicable"/></td>
        </tr>
        <tr>
          <th>Assign to office</th>
          <td colspan="3">
            <select id="e-off" class="encode-office-select" onchange="liveClassify()"><option value="">— select office —</option></select>
          </td>
        </tr>
        <tr>
          <th>Acquisition date</th>
          <td colspan="3"><input type="date" id="e-date"/></td>
        </tr>
        <tr class="encode-divider-row"><td colspan="4"><span class="encode-divider">Auto-classification</span></td></tr>
        <tr>
          <th>Category</th>
          <td colspan="3"><div class="classify-box" id="e-classify">Enter unit value (₱50k rule) — blank or ₱0 counts as Semi-Expendable</div></td>
        </tr>
        <tr>
          <th>Property # preview</th>
          <td colspan="3"><div class="prop-preview" id="e-propnum">—</div></td>
        </tr>
        <tr>
          <th>Remarks</th>
          <td colspan="3"><textarea id="e-remarks" rows="2" placeholder="GSO remarks column (optional)"></textarea></td>
        </tr>
      </tbody>
    </table>
  </div>
  <div class="m-foot">
    <button class="btn btn-ghost" onclick="closeModal('encode-modal')">Cancel</button>
    <button type="button" class="btn btn-blue" id="encode-save-btn" onclick="saveItem()">Save Item</button>
  </div>
</div>
</div>

<!-- ═══ ISSUE MODAL ═══ -->
<div class="overlay" id="issue-modal">
<div class="modal modal-lg iss-modal">
  <div class="m-hd iss-modal-hd">
    <div class="iss-modal-hd-main">
      <span class="iss-modal-badge" aria-hidden="true"><i class="fas fa-file-export"></i></span>
      <div class="iss-modal-hd-text">
        <span class="iss-modal-eyebrow">Issuance · PAR</span>
        <span class="m-ttl">Issue PAR</span>
        <p class="iss-modal-kicker">Filter by completed date, pick the purchase, then confirm. Nothing is saved until you confirm.</p>
      </div>
    </div>
    <button type="button" class="m-close iss-modal-close" onclick="closeIssueParModal()" aria-label="Close">×</button>
  </div>
  <form class="m-body iss-modal-body" id="issue-form" onsubmit="event.preventDefault(); return false;" autocomplete="off">
    <section class="iss-sec">
      <h3 class="iss-sec-title"><i class="fas fa-clipboard-check" aria-hidden="true"></i> Completed purchase request</h3>
      <div class="iss-filter-bar">
        <div class="iss-chips" role="group" aria-label="Completed date filter">
          <button type="button" class="iss-chip is-on" id="iss-filter-today">Today</button>
          <button type="button" class="iss-chip" id="iss-filter-all">All dates</button>
        </div>
        <label class="iss-date-field" for="iss-pr-date">
          <span>Completed date</span>
          <input type="date" id="iss-pr-date"/>
        </label>
      </div>
      <div class="fg full">
        <label for="iss-item">Purchase request *</label>
        <select id="iss-item" onchange="updateIssueForm()"><option value="">— select completed PR —</option></select>
        <p class="iss-note" id="iss-pr-filter-hint">Showing completed PRs from today that still need to be issued.</p>
      </div>
    </section>
    <div class="fg full" id="iss-pr-meta" hidden></div>
    <div class="fg full" id="iss-lines-wrap" hidden>
      <label>Items on this PAR</label>
      <div id="iss-lines-preview" class="iss-lines-wrap"></div>
      <p class="iss-note" id="iss-lines-note"></p>
    </div>
    <section class="iss-sec iss-sec-details" id="iss-details-sec" hidden>
      <h3 class="iss-sec-title"><i class="fas fa-user-check" aria-hidden="true"></i> Issue details</h3>
      <div class="form-grid">
        <div class="fg"><label for="iss-type">Form type</label><input type="text" id="iss-type" readonly tabindex="-1"/></div>
        <div class="fg"><label for="iss-class">After issue</label><input type="text" id="iss-class" readonly placeholder="SE and/or PPE" tabindex="-1"/></div>
        <div class="fg"><label for="iss-num">PAR number *</label>
          <input type="text" id="iss-num" placeholder="Filled from the selected PR" autocomplete="off"/>
          <p class="iss-note" id="iss-num-hint">Filled from the selected PR (PAR + the same year and number).</p>
        </div>
        <div class="fg"><label for="iss-office">Custodian office</label><input type="text" id="iss-office" readonly title="Office on the purchase request" tabindex="-1"/></div>
        <div class="fg"><label for="iss-to">Issued to (name) *</label><input type="text" id="iss-to" placeholder="Full name of the accountable person" autocomplete="off"/></div>
        <div class="fg"><label for="iss-pos">Position</label><input type="text" id="iss-pos" autocomplete="off"/></div>
        <div class="fg"><label for="iss-date">Issue date *</label><input type="date" id="iss-date"/></div>
        <input type="hidden" id="iss-proc-pr" value="">
        <div class="fg full"><label for="iss-remarks">Remarks</label><textarea id="iss-remarks" rows="2"></textarea></div>
      </div>
    </section>
    <p class="iss-confirm-hint" id="iss-confirm-hint">Select a purchase request and enter who will receive it, then confirm.</p>
  </form>
  <div class="m-foot iss-modal-foot">
    <button type="button" class="btn btn-ghost iss-modal-cancel" onclick="closeIssueParModal()">Cancel</button>
    <button type="button" class="btn btn-red iss-modal-save" id="issue-save-btn" onclick="saveIssuance()" disabled>Confirm issue</button>
  </div>
</div>
</div>

<!-- ═══ UNSERVICEABLE MODAL ═══ -->
<div class="overlay" id="unserv-modal">
<div class="modal">
  <div class="m-hd"><span class="m-ttl">Mark as Unserviceable</span><button class="m-close" onclick="closeModal('unserv-modal')">×</button></div>
  <div class="m-body">
    <div class="form-grid">
      <div class="fg full">
        <label for="uns-item-q">Find property item *</label>
        <p class="uns-pick-hint">Type a property number, item name, or office. Click a match — do not scroll a long list.</p>
        <input type="hidden" id="uns-item" value=""/>
        <div class="uns-pick">
          <div class="uns-pick-tools">
            <label class="uns-search-wrap uns-pick-search">
              <span class="sr-only">Search items</span>
              <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
              <input type="search" id="uns-item-q" placeholder="Search property #, description, or office…" autocomplete="off"/>
            </label>
            <select class="filter-sel" id="uns-item-office" title="Filter by department">
              <option value="">All departments</option>
            </select>
          </div>
          <div id="uns-item-chosen" class="uns-pick-chosen" hidden></div>
          <div id="uns-item-results" class="uns-pick-list" role="listbox" aria-label="Matching items"></div>
        </div>
      </div>
      <div class="fg"><label>Marked Date *</label><input type="date" id="uns-date"/></div>
      <div class="fg"><label>Marked By</label><input type="text" id="uns-by"/></div>
      <div class="fg full"><label>Reason *</label>
        <select id="uns-reason-category" onchange="syncUnservOtherReason()">
          <option value="">— select reason —</option>
          <option value="Damaged">Damaged</option>
          <option value="Not Working">Not Working</option>
          <option value="Broken">Broken</option>
          <option value="Missing Parts">Missing Parts</option>
          <option value="Worn Out">Worn Out</option>
          <option value="Other">Other</option>
        </select>
      </div>
      <div class="fg full" id="uns-reason-other-wrap" style="display:none"><label>Describe other reason *</label><textarea id="uns-reason" rows="3" placeholder="Describe why this item is unserviceable"></textarea></div>
      <div class="fg full"><label>Remarks</label><textarea id="uns-remarks" rows="2"></textarea></div>
    </div>
  </div>
  <div class="m-foot">
    <button class="btn btn-ghost" onclick="closeModal('unserv-modal')">Cancel</button>
    <button type="button" class="btn btn-red" id="unserv-save-btn" onclick="saveUnserviceable()">Mark Unserviceable</button>
  </div>
</div>
</div>

<!-- ═══ STAFF: REPORT UNSERVICEABLE ═══ -->
<div class="overlay" id="staff-unserv-modal">
<div class="modal">
  <div class="m-hd"><span class="m-ttl">Report as Unserviceable</span><button class="m-close" onclick="closeModal('staff-unserv-modal')">×</button></div>
  <div class="m-body">
    <input type="hidden" id="staff-uns-item-id" value=""/>
    <div class="form-grid">
      <div class="fg full"><label>Item</label><input type="text" id="staff-uns-item-label" readonly/></div>
      <div class="fg full"><label>Reason *</label>
        <select id="staff-uns-reason-category" onchange="syncStaffUnservOtherReason()">
          <option value="">— select reason —</option>
          <option value="Damaged">Damaged</option>
          <option value="Not Working">Not Working</option>
          <option value="Broken">Broken</option>
          <option value="Missing Parts">Missing Parts</option>
          <option value="Worn Out">Worn Out</option>
          <option value="Other">Other</option>
        </select>
      </div>
      <div class="fg full" id="staff-uns-other-wrap" style="display:none">
        <label>Describe other reason *</label>
        <textarea id="staff-uns-other" rows="3" placeholder="Please describe the problem"></textarea>
      </div>
      <div class="fg full"><label>Remarks</label><textarea id="staff-uns-remarks" rows="2" placeholder="Optional notes"></textarea></div>
    </div>
  </div>
  <div class="m-foot">
    <button class="btn btn-ghost" onclick="closeModal('staff-unserv-modal')">Cancel</button>
    <button type="button" class="btn btn-red" id="staff-unserv-save-btn" onclick="submitDeptUnserviceable()">Submit report</button>
  </div>
</div>
</div>

<!-- ═══ UNSERVICEABLE DETAIL MODAL (full reason / remarks) ═══ -->
<div class="overlay" id="uns-detail-modal">
<div class="modal uns-detail-modal">
  <div class="m-hd uns-detail-hd">
    <div class="uns-detail-hd-main">
      <span class="uns-detail-badge" aria-hidden="true"><i class="fas fa-triangle-exclamation"></i></span>
      <div class="uns-detail-hd-text">
        <span class="uns-detail-eyebrow">Unserviceable · Inspection</span>
        <span class="m-ttl">Unserviceable item details</span>
        <p class="uns-detail-kicker">Review the report. Dispose sends the item to Disposal Records and removes it from this list. That cannot be undone.</p>
      </div>
    </div>
    <button type="button" class="m-close uns-detail-close" onclick="closeModal('uns-detail-modal')" aria-label="Close"><i class="fas fa-xmark"></i></button>
  </div>
  <div class="m-body uns-detail-body">
    <input type="hidden" id="uns-detail-id" value=""/>
    <div class="uns-detail-meta">
      <div class="uns-detail-card"><span>Property #</span><strong id="uns-detail-propnum" class="mono">—</strong></div>
      <div class="uns-detail-card"><span>Status</span><strong id="uns-detail-status">—</strong></div>
      <div class="uns-detail-card"><span>Office</span><strong id="uns-detail-office">—</strong></div>
      <div class="uns-detail-card"><span>Marked date</span><strong id="uns-detail-date">—</strong></div>
    </div>
    <div class="fg full">
      <label>Description</label>
      <div id="uns-detail-desc" class="uns-detail-block"></div>
    </div>
    <div class="fg full">
      <label>Reason (complete)</label>
      <div id="uns-detail-reason" class="uns-detail-block uns-detail-block-pre"></div>
    </div>
    <div class="fg full">
      <label>Remarks</label>
      <div id="uns-detail-remarks" class="uns-detail-block uns-detail-block-pre"></div>
    </div>
  </div>
  <div class="m-foot uns-detail-foot">
    <div id="uns-detail-resolve" class="uns-resolve-actions" hidden>
      <button type="button" class="btn btn-green" onclick="resolveUnserviceableRepair('Serviceable')">Still works</button>
      <button type="button" class="btn btn-ghost" onclick="resolveUnserviceableRepair('Defective')">Cannot repair</button>
    </div>
    <button type="button" class="btn btn-ghost" onclick="closeModal('uns-detail-modal')">Close</button>
    <button type="button" class="btn btn-red uns-detail-dispose" id="uns-detail-dispose-btn" hidden onclick="openDisposeConfirmFromDetail()">Dispose</button>
  </div>
</div>
</div>

<div class="overlay overlay-confirm" id="uns-dispose-modal">
<div class="modal uns-dispose-modal">
  <div class="m-hd uns-detail-hd">
    <div class="uns-detail-hd-main">
      <span class="uns-detail-badge" aria-hidden="true"><i class="fas fa-trash-can"></i></span>
      <div class="uns-detail-hd-text">
        <span class="uns-detail-eyebrow">Disposal Records</span>
        <span class="m-ttl">Dispose items</span>
      </div>
    </div>
    <button type="button" class="m-close uns-detail-close" onclick="closeModal('uns-dispose-modal')" aria-label="Close"><i class="fas fa-xmark"></i></button>
  </div>
  <div class="m-body">
    <p class="uns-dispose-copy" id="uns-dispose-msg">This cannot be undone. Disposed items leave Unserviceable Items and stay in Disposal Records only.</p>
  </div>
  <div class="m-foot uns-detail-foot">
    <button type="button" class="btn btn-ghost" onclick="closeModal('uns-dispose-modal')">Cancel</button>
    <button type="button" class="btn btn-red" id="uns-dispose-confirm-btn" onclick="confirmDisposeUnserviceable()">Dispose</button>
  </div>
</div>
</div>

<!-- TRANSFER TO OFFICE MODAL -->
<div class="overlay" id="transfer-office-modal">
<div class="modal">
  <div class="m-hd"><span class="m-ttl">Transfer Item to Office</span><button class="m-close" onclick="closeModal('transfer-office-modal')">×</button></div>
  <div class="m-body">
    <div class="form-grid">
      <div class="fg full"><label>Item</label><input type="text" id="xfer-item-label" readonly style="opacity:0.85"/></div>
      <div class="fg full"><label>Transfer to Office *</label>
        <select id="xfer-office"><option value="">— select office —</option></select>
      </div>
      <div class="fg full"><label>Remarks</label><textarea id="xfer-remarks" rows="2" placeholder="Optional — reason for transfer"></textarea></div>
    </div>
  </div>
  <div class="m-foot">
    <button class="btn btn-ghost" onclick="closeModal('transfer-office-modal')">Cancel</button>
    <button type="button" class="btn btn-blue" id="xfer-save-btn" onclick="confirmTransferToOffice()">Transfer Item</button>
  </div>
</div>
</div>

<!-- ═══ PROCUREMENT · SUBMIT CANVASS ═══ -->
<div class="overlay overlay-confirm" id="proc-submit-modal">
<div class="modal" style="max-width:480px">
  <div class="m-hd">
    <span class="m-ttl" id="proc-submit-modal-ttl">Submit for canvass approval</span>
    <button type="button" class="m-close" onclick="closeModal('proc-submit-modal')" aria-label="Close">&times;</button>
  </div>
  <div class="m-body">
    <input type="hidden" id="proc-submit-pr-id" value=""/>
    <p style="margin:0;font-size:13px;line-height:1.5;color:var(--text2);">Department canvass submissions require admin approval. If rejected, the department must address the admin's feedback and resubmit.</p>
  </div>
  <div class="m-foot">
    <button type="button" class="btn btn-ghost" onclick="closeModal('proc-submit-modal')">Cancel</button>
    <button type="button" class="btn btn-dark" id="proc-submit-confirm-btn" onclick="procConfirmSubmitCanvass()">Submit for approval</button>
  </div>
</div>
</div>

<!-- ═══ PROCUREMENT · REJECT CANVASS ═══ -->
<div class="overlay overlay-confirm" id="proc-reject-modal">
<div class="modal" style="max-width:480px">
  <div class="m-hd">
    <span class="m-ttl">Reject canvass submission</span>
    <button type="button" class="m-close" onclick="closeModal('proc-reject-modal')" aria-label="Close">&times;</button>
  </div>
  <div class="m-body">
    <input type="hidden" id="proc-reject-pr-id" value=""/>
    <p style="margin:0 0 10px;font-size:13px;line-height:1.5;color:var(--text2);">Department canvass submissions require admin approval. If rejected, the department must address the admin's feedback and resubmit.</p>
    <div class="fg full">
      <label for="proc-reject-feedback">Feedback *</label>
      <textarea id="proc-reject-feedback" rows="4" placeholder="Explain what the department must correct before resubmitting"></textarea>
    </div>
  </div>
  <div class="m-foot">
    <button type="button" class="btn btn-ghost" onclick="closeModal('proc-reject-modal')">Cancel</button>
    <button type="button" class="btn btn-red" id="proc-reject-confirm-btn" onclick="procConfirmReject()">Send rejection</button>
  </div>
</div>
</div>

<!-- ═══ PROCUREMENT · DELETE DRAFT PR ═══ -->
<div class="overlay overlay-confirm" id="proc-delete-modal" role="presentation">
<div class="modal" style="max-width:420px" role="dialog" aria-modal="true" aria-labelledby="proc-delete-modal-ttl">
  <div class="m-hd">
    <span class="m-ttl" id="proc-delete-modal-ttl">Delete PR</span>
    <button type="button" class="m-close" onclick="closeModal('proc-delete-modal')" aria-label="Close">&times;</button>
  </div>
  <div class="m-body">
    <input type="hidden" id="proc-delete-pr-id" value=""/>
    <p style="margin:0;font-size:14px;line-height:1.5;color:var(--text2);">Delete this draft purchase request?</p>
  </div>
  <div class="m-foot">
    <button type="button" class="btn btn-ghost" onclick="closeModal('proc-delete-modal')">Cancel</button>
    <button type="button" class="btn btn-red" id="proc-delete-confirm-btn" onclick="procConfirmDeleteDraft()">Delete PR</button>
  </div>
</div>
</div>

<!-- ═══ PROCUREMENT · PR ═══ -->
<div class="overlay proc-modal-overlay proc-modal-overlay-pr" id="proc-pr-modal">
<div class="modal modal-lg proc-modal proc-modal-pr">
  <div class="m-hd proc-modal-hd proc-pr-modal-head">
    <div class="proc-pr-modal-head-main">
      <span class="proc-pr-modal-badge" aria-hidden="true"><i class="fas fa-file-circle-plus"></i></span>
      <div class="proc-pr-modal-head-text">
        <span class="proc-pr-modal-kicker">Purchase request · Draft</span>
        <span class="m-ttl proc-pr-modal-ttl"><span id="proc-pr-modal-mode">Purchase request</span></span>
        <p class="proc-pr-modal-lead">Pick a section, type an article (example: Table), then add each type as a description row.</p>
      </div>
    </div>
    <button type="button" class="m-close proc-pr-modal-close-btn" onclick="closeModal('proc-pr-modal')" aria-label="Close"><i class="fas fa-xmark"></i></button>
  </div>
  <div class="m-body proc-pr-modal-body">
    <input type="hidden" id="proc-pr-edit-id" value="">
    <input type="hidden" id="proc-pr-is-consumable" value="0">
    <section class="proc-pr-modal-section" aria-labelledby="proc-pr-sec-office-label">
      <h3 id="proc-pr-sec-office-label" class="proc-pr-modal-section-title"><i class="fas fa-building-columns" aria-hidden="true"></i> Office &amp; references</h3>
      <div class="form-grid">
        <div class="fg"><label for="proc-pr-office">Office *</label><select id="proc-pr-office" class="filter-sel proc-pr-modal-full"><option value="">— select office —</option></select></div>
        <div class="fg"><label for="proc-pr-date">PR date</label><input type="date" id="proc-pr-date" class="inp proc-pr-modal-full"></div>
        <div class="fg"><label for="proc-pr-section">Section *</label>
          <select id="proc-pr-section" class="filter-sel proc-pr-modal-full">
            <option value="">— inventory category —</option>
          </select>
          <p class="muted" id="proc-pr-section-hint" style="margin:6px 0 0;font-size:12px;display:none">Locked to Consumable for this request.</p>
        </div>
        <div class="fg"><label for="proc-pr-sai">SAI No.</label><input type="text" id="proc-pr-sai" class="inp" maxlength="80" placeholder="Stock Availability Inquiry"></div>
      </div>
    </section>
    <section class="proc-pr-modal-section" aria-labelledby="proc-pr-sec-purpose-label">
      <h3 id="proc-pr-sec-purpose-label" class="proc-pr-modal-section-title"><i class="fas fa-bullseye" aria-hidden="true"></i> Purpose &amp; allotment</h3>
      <div class="form-grid">
        <div class="fg full"><label for="proc-pr-purpose">Purpose</label><textarea id="proc-pr-purpose" class="inp" rows="2" placeholder="Justification / purpose"></textarea></div>
        <div class="fg full"><label for="proc-pr-budget">Budget received / allotment</label><input type="text" id="proc-pr-budget" class="inp" placeholder="Optional note"></div>
      </div>
    </section>
    <section class="proc-pr-modal-section" aria-labelledby="proc-pr-sec-sign-label">
      <h3 id="proc-pr-sec-sign-label" class="proc-pr-modal-section-title"><i class="fas fa-signature" aria-hidden="true"></i> Signatories</h3>
      <div class="form-grid proc-pr-sign-grid">
        <div class="fg"><label for="proc-pr-req-by">Requested — printed name</label><input type="text" id="proc-pr-req-by" class="inp"></div>
        <div class="fg"><label for="proc-pr-req-title">Requested — designation</label><input type="text" id="proc-pr-req-title" class="inp" placeholder="e.g. Municipal Administrator"></div>
        <div class="fg"><label for="proc-pr-req-signed">Requested — date signed</label><input type="date" id="proc-pr-req-signed" class="inp"></div>
        <div class="fg"><label for="proc-pr-approved">Approved — printed name</label><input type="text" id="proc-pr-approved" class="inp"></div>
        <div class="fg"><label for="proc-pr-approved-title">Approved — designation</label><input type="text" id="proc-pr-approved-title" class="inp" placeholder="e.g. Municipal Mayor"></div>
        <div class="fg"><label for="proc-pr-approved-signed">Approved — date signed</label><input type="date" id="proc-pr-approved-signed" class="inp"></div>
      </div>
    </section>
    <section class="proc-pr-modal-section proc-pr-modal-section-lines" aria-labelledby="proc-pr-sec-lines-label">
      <div class="proc-lines-toolbar proc-pr-lines-toolbar">
        <div class="proc-lines-toolbar-text">
          <h3 id="proc-pr-sec-lines-label" class="proc-pr-modal-section-title proc-pr-modal-section-title--inline"><i class="fas fa-table-list" aria-hidden="true"></i> Articles &amp; descriptions</h3>
          <span class="proc-pr-lines-hint" id="proc-pr-lines-hint">Article is the title (Table). Each row is a different description under that article. Unit cost of ₱50,000+ becomes PPE after issue — not shown on the printed PR.</span>
        </div>
        <button type="button" class="btn btn-dark btn-sm proc-pr-add-row-btn" id="proc-pr-add-article-btn" onclick="procAddPrArticleGroup()"><i class="fas fa-plus" aria-hidden="true"></i> Add article</button>
      </div>
      <div id="proc-pr-article-groups" class="proc-pr-article-groups"></div>
    </section>
  </div>
  <div class="m-foot proc-pr-modal-foot">
    <button type="button" class="btn btn-red proc-pr-modal-delete" id="proc-btn-delete-modal" hidden>Delete</button>
    <button type="button" class="btn btn-ghost proc-pr-modal-cancel" onclick="closeModal('proc-pr-modal')">Cancel</button>
    <button type="button" class="btn btn-blue proc-pr-modal-save" id="proc-btn-save-modal">Draft</button>
    <button type="button" class="btn btn-dark proc-pr-modal-submit" id="proc-btn-submit-modal">Submit</button>
  </div>
</div>
</div>

<!-- ═══ PROCUREMENT · PR classification (before New PR form) ═══ -->
<div class="overlay" id="proc-pr-class-modal">
<div class="modal proc-pr-class-modal" role="dialog" aria-labelledby="proc-pr-class-title" aria-modal="true">
  <div class="m-hd proc-pr-class-hd">
    <div class="proc-pr-class-hd-main">
      <span class="proc-pr-class-badge" aria-hidden="true"><i class="fas fa-box-open"></i></span>
      <div>
        <span class="m-ttl">New purchase request</span>
        <p class="proc-pr-class-hd-sub">Choose the type of request first</p>
      </div>
    </div>
    <button type="button" class="m-close" onclick="closeModal('proc-pr-class-modal')" aria-label="Close">&times;</button>
  </div>
  <div class="m-body proc-pr-class-body">
    <h3 id="proc-pr-class-title" class="proc-pr-class-question">Is this request for consumable items?</h3>
    <p class="proc-pr-class-lead">Consumables are supplies that get used up over time — mga bagay na nauubos.</p>
    <ul class="proc-pr-class-examples" aria-label="Examples of consumables">
      <li>Papel</li>
      <li>Ballpen</li>
      <li>Sabon</li>
      <li>Other supplies that run out</li>
    </ul>
    <div class="proc-pr-class-actions">
      <button type="button" class="proc-pr-class-choice proc-pr-class-choice-no" id="proc-pr-class-no">
        <span class="proc-pr-class-choice-ico" aria-hidden="true"><i class="fas fa-times"></i></span>
        <span class="proc-pr-class-choice-text">
          <strong>No</strong>
          <em>Equipment / property items</em>
        </span>
      </button>
      <button type="button" class="proc-pr-class-choice proc-pr-class-choice-yes" id="proc-pr-class-yes">
        <span class="proc-pr-class-choice-ico" aria-hidden="true"><i class="fas fa-check"></i></span>
        <span class="proc-pr-class-choice-text">
          <strong>Yes</strong>
          <em>Consumable supplies</em>
        </span>
      </button>
    </div>
  </div>
</div>
</div>

<!-- ═══ PROCUREMENT · PR submit classification confirm ═══ -->
<div class="overlay overlay-confirm" id="proc-pr-class-confirm-modal">
<div class="modal" style="max-width:460px">
  <div class="m-hd">
    <span class="m-ttl">Submit this purchase request?</span>
    <button type="button" class="m-close" onclick="closeModal('proc-pr-class-confirm-modal')" aria-label="Close">&times;</button>
  </div>
  <div class="m-body">
    <p id="proc-pr-class-confirm-text" style="margin:0;font-size:14px;line-height:1.5;color:var(--text2)">You’re submitting this as: —</p>
    <p style="margin:10px 0 0;font-size:12px;line-height:1.45;color:var(--text3);">This is not sent for canvass approval until you confirm.</p>
  </div>
  <div class="m-foot" style="justify-content:flex-end;gap:8px">
    <button type="button" class="btn btn-ghost" onclick="closeModal('proc-pr-class-confirm-modal')">Back</button>
    <button type="button" class="btn btn-dark" id="proc-pr-class-confirm-go">Confirm &amp; submit</button>
  </div>
</div>
</div>

<!-- ═══ PROCUREMENT · QUOTATION ═══ -->
<div class="overlay proc-modal-overlay proc-modal-overlay-quote" id="proc-quote-modal">
<div class="modal modal-lg proc-modal proc-modal-quote">
  <div class="m-hd proc-modal-hd"><span class="m-ttl">Supplier quotation · canvass slot</span><button type="button" class="m-close" onclick="closeModal('proc-quote-modal')" aria-label="Close">&times;</button></div>
  <div class="m-body">
    <input type="hidden" id="proc-q-pr-id" value="">
    <input type="hidden" id="proc-q-slot" value="1">
    <div class="form-grid">
      <div class="fg full"><label>Supplier *</label><input type="text" id="proc-q-supplier" class="inp" placeholder="Firm name"></div>
      <div class="fg full"><label>Address</label><input type="text" id="proc-q-addr" class="inp" placeholder="Optional"></div>
      <div class="fg"><label>Quotation date</label><input type="date" id="proc-q-date" class="inp"></div>
    </div>
    <div class="proc-quote-table-wrap">
      <table class="proc-quote-table"><thead><tr><th style="text-align:left">Item</th><th>Unit price (₱)</th><th>Tot ₱ ref</th></tr></thead><tbody id="proc-q-price-body"></tbody></table>
    </div>
    <p class="muted proc-quote-hint">Enter one unit price per line. Totals rebuild on save.</p>
  </div>
  <div class="m-foot">
    <button type="button" class="btn btn-ghost" onclick="closeModal('proc-quote-modal')">Cancel</button>
    <button type="button" class="btn btn-dark" id="proc-btn-save-quote">Save quotation</button>
  </div>
</div>
</div>

<!-- ═══ ITEM DETAIL MODAL ═══ -->
<div class="overlay" id="detail-modal">
<div class="modal modal-lg">
  <div class="m-hd"><span class="m-ttl">Property Item Details</span><button class="m-close" onclick="closeModal('detail-modal')">×</button></div>
  <div class="m-body" id="detail-content">
    <!-- Content loaded dynamically -->
  </div>
  <div class="m-foot detail-m-foot">
    <button type="button" class="btn btn-ghost" onclick="closeModal('detail-modal')">Close</button>
    <div class="detail-m-foot-spacer" aria-hidden="true"></div>
    <button type="button" class="btn btn-red" id="detail-btn-unserv" style="display:none" onclick="markUnserviceableFromDetail()">Mark Unserviceable</button>
    <button type="button" class="btn btn-ghost" id="detail-btn-transfer" style="display:none" onclick="openTransferToOfficeFromDetail()">Transfer to Office</button>
    <button type="button" class="btn btn-ghost" id="detail-btn-cancel" style="display:none" onclick="cancelItemDetailEdit()">Cancel</button>
    <button type="button" class="btn btn-blue" id="detail-btn-save" style="display:none" onclick="saveItemDetail()">Save</button>
    <button type="button" class="btn btn-blue" id="detail-btn-edit" style="display:none" onclick="enterItemDetailEdit()">Edit</button>
  </div>
</div>
</div>

<!-- ═══ AUDIT TRAIL (read-only) ═══ -->
<div class="overlay" id="audit-history-modal">
<div class="modal modal-lg">
  <div class="m-hd">
    <span class="m-ttl" id="audit-history-title">Activity log</span>
    <button type="button" class="m-close" onclick="closeModal('audit-history-modal')" aria-label="Close">×</button>
  </div>
  <div class="m-body">
    <p class="audit-readonly-note">Read-only record. Past entries cannot be edited or deleted.</p>
    <div id="audit-history-body" class="audit-readonly"></div>
  </div>
  <div class="m-foot">
    <button type="button" class="btn btn-ghost" onclick="closeModal('audit-history-modal')">Close</button>
  </div>
</div>
</div>

<!-- ═══ SIGN OUT CONFIRM ═══ -->
<div class="overlay overlay-confirm" id="logout-confirm-modal">
<div class="modal" style="max-width:420px">
  <div class="m-hd">
    <span class="m-ttl">Sign out</span>
    <button type="button" class="m-close" onclick="closeModal('logout-confirm-modal')" aria-label="Close">&times;</button>
  </div>
  <div class="m-body">
    <p style="margin:0;font-size:14px;line-height:1.5;color:var(--text2);">Are you sure you want to sign out? You will need to sign in again to access the system.</p>
  </div>
  <div class="m-foot">
    <button type="button" class="btn btn-ghost" onclick="closeModal('logout-confirm-modal')">Cancel</button>
    <button type="button" class="btn btn-red" id="logout-confirm-btn" onclick="confirmLogout()">Sign out</button>
  </div>
</div>
</div>

<!-- ═══ SE: leave with selected items ═══ -->
<div class="overlay overlay-confirm" id="se-leave-selected-modal">
<div class="modal" style="max-width:480px">
  <div class="m-hd">
    <span class="m-ttl">Selected items</span>
    <button type="button" class="m-close" onclick="cancelSeLeaveSelected()" aria-label="Close">&times;</button>
  </div>
  <div class="m-body">
    <p id="se-leave-selected-msg" style="margin:0;font-size:14px;line-height:1.5;color:var(--text2);">You selected property items on Semi-Expendable for tagging. Leave with the selection, or unselect them first.</p>
  </div>
  <div class="m-foot" style="flex-wrap:wrap;gap:8px">
    <button type="button" class="btn btn-ghost" onclick="cancelSeLeaveSelected()">Stay here</button>
    <button type="button" class="btn btn-ghost" onclick="confirmSeLeaveUnselect()">Unselect items</button>
    <button type="button" class="btn btn-blue" onclick="confirmSeLeaveDontSave()">Leave</button>
  </div>
</div>
</div>

<!-- ═══ RPCSE: pick office before print ═══ -->
<div class="overlay" id="rpcse-office-modal">
<div class="modal">
  <div class="m-hd"><span class="m-ttl">Print physical count report</span><button type="button" class="m-close" onclick="closeModal('rpcse-office-modal')">×</button></div>
  <div class="m-body">
    <p class="uns-pick-hint">Select one or more offices. Two offices open two RPCSE sheets stacked on one page — one official form per office. <strong>All offices</strong> still exports the full Excel workbook.</p>
    <input type="hidden" id="rpcse-office-id" value=""/>
    <div class="uns-pick">
      <div class="uns-pick-tools">
        <label class="uns-search-wrap uns-pick-search">
          <span class="sr-only">Search offices</span>
          <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
          <input type="search" id="rpcse-office-q" placeholder="Search office name or code…" autocomplete="off" oninput="renderRpcseOfficePickList()"/>
        </label>
      </div>
      <div id="rpcse-office-chosen" class="uns-pick-chosen" hidden></div>
      <div id="rpcse-office-results" class="uns-pick-list" role="listbox" aria-multiselectable="true" aria-label="Offices"></div>
    </div>
  </div>
  <div class="m-foot">
    <button type="button" class="btn btn-ghost" onclick="closeModal('rpcse-office-modal')">Cancel</button>
    <button type="button" class="btn btn-blue" id="rpcse-office-print-btn" onclick="confirmPhysicalCountOfficePrint()">View this office</button>
  </div>
</div>
</div>

<!-- ═══ RPCPPE: pick Excel tab before print ═══ -->
<div class="overlay" id="rpcppe-tab-modal">
<div class="modal">
  <div class="m-hd"><span class="m-ttl">Print physical count report</span><button type="button" class="m-close" onclick="closeModal('rpcppe-tab-modal')">×</button></div>
  <div class="m-body">
    <p class="uns-pick-hint">Select one or more PPE tabs (asset groups, not offices). Two tabs open two RPCPPE sheets stacked on one page — one official form per tab. <strong>All tabs</strong> exports the full Excel workbook from live inventory.</p>
    <input type="hidden" id="rpcppe-tab-id" value=""/>
    <div class="uns-pick">
      <div class="uns-pick-tools">
        <label class="uns-search-wrap uns-pick-search">
          <span class="sr-only">Search PPE tabs</span>
          <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
          <input type="search" id="rpcppe-tab-q" placeholder="Search tab name…" autocomplete="off" oninput="renderRpcppeTabPickList()"/>
        </label>
      </div>
      <div id="rpcppe-tab-chosen" class="uns-pick-chosen" hidden></div>
      <div id="rpcppe-tab-results" class="uns-pick-list" role="listbox" aria-multiselectable="true" aria-label="PPE tabs"></div>
    </div>
  </div>
  <div class="m-foot">
    <button type="button" class="btn btn-ghost" onclick="closeModal('rpcppe-tab-modal')">Cancel</button>
    <button type="button" class="btn btn-blue" id="rpcppe-tab-print-btn" onclick="confirmPhysicalCountPpePrint()">View this tab</button>
  </div>
</div>
</div>

<!-- ═══ PROPERTY TAG GSO ═══ -->
<div class="overlay" id="property-tag-modal">
<div class="modal modal-lg modal-ptag">
  <div class="m-hd"><span class="m-ttl">Property Tag GSO</span><button class="m-close" onclick="closeModal('property-tag-modal')">×</button></div>
  <div class="m-body">
    <p class="ptag-modal-hint">Property Number and Acquisition Cost come from the inventory record and cannot be typed here. Other fields are saved on this tag so you do not retype them next time. The inventory-team signature stays blank for handwriting after print.</p>
    <input type="hidden" id="ptag-item-id" value=""/>
    <div class="ptag-preview" id="ptag-preview"></div>
  </div>
  <div class="m-foot">
    <button type="button" class="btn btn-ghost" onclick="closeModal('property-tag-modal')">Close</button>
    <button type="button" class="btn btn-dark" id="ptag-save-btn" onclick="savePropertyTag()">Save tag fields</button>
  </div>
</div>
</div>
