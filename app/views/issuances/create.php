<?php $pageTitle = 'Create Issuance'; ?>
<?php require VIEW_PATH . '/layouts/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-file-export"></i> Create Issuance (PAR/ICS)</h3>
    </div>
    <div class="card-body">
        <form action="/issuances/store" method="POST" data-validate">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                <div class="form-group">
                    <label for="item_id">Select Item <span style="color: red;">*</span></label>
                    <select id="item_id" name="item_id" class="form-control" required>
                        <option value="">Select Item</option>
                        <?php foreach ($items as $item): ?>
                            <?php if ($item['available_quantity'] > 0): ?>
                                <option value="<?php echo $item['id']; ?>" 
                                        data-available="<?php echo $item['available_quantity']; ?>"
                                        data-classification="<?php echo $item['classification']; ?>">
                                    <?php echo htmlspecialchars($item['property_number'] . ' - ' . $item['item_name']); ?> 
                                    (Available: <?php echo $item['available_quantity']; ?>)
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <small id="form-type-hint" style="color: #666;"></small>
                </div>
                
                <div class="form-group">
                    <label for="quantity">Quantity <span style="color: red;">*</span></label>
                    <input type="number" id="quantity" name="quantity" class="form-control" min="1" required>
                    <small id="available-qty-display" style="color: #666;"></small>
                </div>
                
                <div class="form-group">
                    <label for="issued_to">Issued To (Person) <span style="color: red;">*</span></label>
                    <input type="text" id="issued_to" name="issued_to" class="form-control" required placeholder="Full Name">
                </div>
                
                <div class="form-group">
                    <label for="office_id">Office <span style="color: red;">*</span></label>
                    <select id="office_id" name="office_id" class="form-control" required>
                        <option value="">Select Office</option>
                        <?php foreach ($offices as $office): ?>
                            <option value="<?php echo $office['id']; ?>">
                                <?php echo htmlspecialchars($office['office_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="issuance_date">Issuance Date <span style="color: red;">*</span></label>
                    <input type="date" id="issuance_date" name="issuance_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="form_number">PAR/ICS Number <span style="color: red;">*</span></label>
                    <input type="text" id="form_number" name="form_number" class="form-control" required placeholder="e.g., PAR-2024-001">
                </div>
                
                <div class="form-group">
                    <label for="ris_number">RIS Number</label>
                    <input type="text" id="ris_number" name="ris_number" class="form-control" placeholder="Requisition Issue Slip Number">
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="purpose">Purpose</label>
                    <textarea id="purpose" name="purpose" class="form-control" rows="3" placeholder="Purpose of issuance"></textarea>
                </div>
            </div>
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                <div style="background-color: #FFF3CD; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    <h4 style="margin-bottom: 10px; color: #856404;">
                        <i class="fas fa-info-circle"></i> Issuance Rules
                    </h4>
                    <ul style="margin-left: 20px; color: #856404;">
                        <li>PPE items (≥ ₱50,000) require PAR (Property Acknowledgement Receipt)</li>
                        <li>Semi-Expendable items (< ₱50,000) require ICS (Inventory Custodian Slip)</li>
                        <li>Property Card will be automatically updated</li>
                        <li>Available quantity will be reduced</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Issuance
                </button>
                <a href="/issuances" class="btn btn-info">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
