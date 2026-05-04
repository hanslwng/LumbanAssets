<!-- ═══ ENCODE MODAL ═══ -->
<div class="overlay" id="encode-modal">
<div class="modal">
  <div class="m-hd"><span class="m-ttl">Encode New Property Item</span><button class="m-close" onclick="closeModal('encode-modal')">×</button></div>
  <div class="m-body">
    <div class="form-grid">
      <div class="fg full"><label>Item Description *</label><input type="text" id="e-name" placeholder="e.g. Office table, Laptop computer" oninput="liveClassify()"/></div>
      <div class="fg"><label>Unit Value (₱) *</label><input type="number" id="e-amt" placeholder="e.g. 10500" min="0" oninput="liveClassify()"/></div>
      <div class="fg"><label>Quantity *</label><input type="number" id="e-qty" value="1" min="1"/></div>
      <div class="fg"><label>Assigned Office *</label>
        <select id="e-off" onchange="liveClassify()"><option value="">— select office —</option></select>
      </div>
      <div class="fg"><label>Acquisition Date</label><input type="date" id="e-date"/></div>
      <div class="divider"><span>Auto-Classification</span></div>
      <div class="fg full">
        <label>Category</label>
        <div class="classify-box" id="e-classify">Will auto-classify based on unit value</div>
      </div>
      <div class="fg full">
        <label>Property Number Preview</label>
        <div class="prop-preview" id="e-propnum">—</div>
      </div>
      <div class="fg full"><label>Remarks</label><textarea id="e-remarks" rows="2"></textarea></div>
    </div>
  </div>
  <div class="m-foot">
    <button class="btn btn-ghost" onclick="closeModal('encode-modal')">Cancel</button>
    <button class="btn btn-blue" onclick="saveItem()">Save Item</button>
  </div>
</div>
</div>

<!-- ═══ ISSUE MODAL ═══ -->
<div class="overlay" id="issue-modal">
<div class="modal modal-lg">
  <div class="m-hd"><span class="m-ttl">Issue Item (PAR / ICS)</span><button class="m-close" onclick="closeModal('issue-modal')">×</button></div>
  <div class="m-body">
    <div class="form-grid">
      <div class="fg full"><label>Select Property Item *</label><select id="iss-item" onchange="updateIssueForm()"><option value="">— select item —</option></select></div>
      <div class="fg"><label>Form Type</label><input type="text" id="iss-type" readonly/></div>
      <div class="fg"><label>Form Number *</label><input type="text" id="iss-num" placeholder="e.g. PAR-2025-001"/></div>
      <div class="fg"><label>Issued To (Name) *</label><input type="text" id="iss-to"/></div>
      <div class="fg"><label>Position</label><input type="text" id="iss-pos"/></div>
      <div class="fg"><label>Office</label><input type="text" id="iss-office" readonly/></div>
      <div class="fg"><label>Issue Date *</label><input type="date" id="iss-date"/></div>
      <div class="fg full"><label>Remarks</label><textarea id="iss-remarks" rows="2"></textarea></div>
    </div>
  </div>
  <div class="m-foot">
    <button class="btn btn-ghost" onclick="closeModal('issue-modal')">Cancel</button>
    <button class="btn btn-blue" onclick="saveIssuance()">Issue Item</button>
  </div>
</div>
</div>

<!-- ═══ RETURN MODAL ═══ -->
<div class="overlay" id="return-modal">
<div class="modal">
  <div class="m-hd"><span class="m-ttl">Record Return</span><button class="m-close" onclick="closeModal('return-modal')">×</button></div>
  <div class="m-body">
    <div class="form-grid">
      <div class="fg full"><label>Select Issued Item *</label><select id="ret-item" onchange="updateReturnForm()"><option value="">— select issued item —</option></select></div>
      <div class="fg"><label>Returned By *</label><input type="text" id="ret-by"/></div>
      <div class="fg"><label>Return Date *</label><input type="date" id="ret-date"/></div>
      <div class="fg full"><label>Condition on Return *</label>
        <select id="ret-cond"><option value="Good">Good</option><option value="Fair">Fair</option><option value="Damaged">Damaged</option></select>
      </div>
      <div class="divider"><span>Return Action</span></div>
      <div class="fg full">
        <div class="return-actions">
          <div class="raction" id="ra-uns" onclick="selectReturnAction('uns')">
            <div class="raction-icon">⚠️</div>
            <div class="raction-title">Mark Unserviceable</div>
            <div class="raction-desc">Item is damaged/unusable</div>
          </div>
          <div class="raction" id="ra-xfer" onclick="selectReturnAction('xfer')">
            <div class="raction-icon">🔄</div>
            <div class="raction-title">Transfer to Office</div>
            <div class="raction-desc">Reassign to another office</div>
          </div>
        </div>
      </div>
      <div class="fg full" id="xfer-office-wrap" style="display:none">
        <label>Transfer To Office *</label>
        <select id="ret-xfer-office"><option value="">— select office —</option></select>
      </div>
      <div class="fg full"><label>Remarks</label><textarea id="ret-remarks" rows="2"></textarea></div>
    </div>
  </div>
  <div class="m-foot">
    <button class="btn btn-ghost" onclick="closeModal('return-modal')">Cancel</button>
    <button class="btn btn-green" onclick="saveReturn()">Record Return</button>
  </div>
</div>
</div>

<!-- ═══ UNSERVICEABLE MODAL ═══ -->
<div class="overlay" id="unserv-modal">
<div class="modal">
  <div class="m-hd"><span class="m-ttl">Mark as Unserviceable</span><button class="m-close" onclick="closeModal('unserv-modal')">×</button></div>
  <div class="m-body">
    <div class="form-grid">
      <div class="fg full"><label>Select Property Item *</label><select id="uns-item"><option value="">— select item —</option></select></div>
      <div class="fg"><label>Marked Date *</label><input type="date" id="uns-date"/></div>
      <div class="fg"><label>Marked By</label><input type="text" id="uns-by"/></div>
      <div class="fg full"><label>Reason *</label><textarea id="uns-reason" rows="3" placeholder="Describe why this item is unserviceable"></textarea></div>
      <div class="fg full"><label>Remarks</label><textarea id="uns-remarks" rows="2"></textarea></div>
    </div>
  </div>
  <div class="m-foot">
    <button class="btn btn-ghost" onclick="closeModal('unserv-modal')">Cancel</button>
    <button class="btn btn-red" onclick="saveUnserviceable()">Mark Unserviceable</button>
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
  <div class="m-foot">
    <button class="btn btn-ghost" onclick="closeModal('detail-modal')">Close</button>
  </div>
</div>
</div>
