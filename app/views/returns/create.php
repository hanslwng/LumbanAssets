<?php $pageTitle = 'Process Return'; ?>
<?php require VIEW_PATH . '/layouts/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-undo"></i> Process Item Return</h3>
    </div>
    <div class="card-body">
        <form action="/returns/store" method="POST" data-validate>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                <div class="form-group">
                    <label for="issuance_id">Select Issuance <span style="color: red;">*</span></label>
                    <select id="issuance_id" name="issuance_id" class="form-control" required>
                        <option value="">Select Active Issuance</option>
                        <?php foreach ($issuances as $issuance): ?>
                            <option value="<?php echo $issuance['id']; ?>">
                                <?php echo htmlspecialchars($issuance['form_number']); ?> - 
                                <?php echo htmlspecialchars($issuance['item_name']); ?> - 
                                Issued to: <?php echo htmlspecialchars($issuance['issued_to']); ?> 
                                (Qty: <?php echo $issuance['quantity']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="quantity">Return Quantity <span style="color: red;">*</span></label>
                    <input type="number" id="quantity" name="quantity" class="form-control" min="1" required>
                </div>
                
                <div class="form-group">
                    <label for="return_date">Return Date <span style="color: red;">*</span></label>
                    <input type="date" id="return_date" name="return_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="condition_status">Condition Status <span style="color: red;">*</span></label>
                    <select id="condition_status" name="condition_status" class="form-control" required>
                        <option value="">Select Condition</option>
                        <option value="Good">Good</option>
                        <option value="Fair">Fair</option>
                        <option value="Damaged">Damaged</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="action_taken">Action to Take <span style="color: red;">*</span></label>
                    <select id="action_taken" name="action_taken" class="form-control" required>
                        <option value="">Select Action</option>
                        <option value="Return to Storage">Return to Storage</option>
                        <option value="Transfer to Another Office">Transfer to Another Office</option>
                        <option value="Mark as Unserviceable">Mark as Unserviceable</option>
                    </select>
                </div>
                
                <div class="form-group" id="transfer-office-group" style="display: none;">
                    <label for="transfer_office_id">Transfer to Office</label>
                    <select id="transfer_office_id" name="transfer_office_id" class="form-control">
                        <option value="">Select Office</option>
                        <?php foreach ($offices as $office): ?>
                            <option value="<?php echo $office['id']; ?>">
                                <?php echo htmlspecialchars($office['office_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" class="form-control" rows="3" placeholder="Additional notes about the return"></textarea>
                </div>
            </div>
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                <div style="background-color: #FFF3CD; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    <h4 style="margin-bottom: 10px; color: #856404;">
                        <i class="fas fa-exclamation-triangle"></i> Important Rules
                    </h4>
                    <ul style="margin-left: 20px; color: #856404;">
                        <li><strong>Damaged items cannot be transferred</strong> to another office</li>
                        <li>Items returned to storage will be added back to available inventory</li>
                        <li>Unserviceable items will be flagged and cannot be issued again</li>
                        <li>Property card will be automatically updated</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Process Return
                </button>
                <a href="/returns" class="btn btn-info">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
