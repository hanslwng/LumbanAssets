<?php $pageTitle = 'Add New Item'; ?>
<?php require VIEW_PATH . '/layouts/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-plus"></i> Add New Item</h3>
    </div>
    <div class="card-body">
        <form action="/items/store" method="POST" data-validate>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                <div class="form-group">
                    <label for="item_name">Item Name <span style="color: red;">*</span></label>
                    <input type="text" id="item_name" name="item_name" class="form-control" required>
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
                    <label for="unit_value">Unit Value (₱) <span style="color: red;">*</span></label>
                    <input type="number" id="unit_value" name="unit_value" class="form-control" step="0.01" min="0" required>
                    <small style="color: #666;">Auto-classifies: PPE if ≥ ₱50,000, Semi-Expendable if < ₱50,000</small>
                </div>
                
                <div class="form-group">
                    <label for="quantity">Quantity <span style="color: red;">*</span></label>
                    <input type="number" id="quantity" name="quantity" class="form-control" min="1" required>
                </div>
                
                <div class="form-group">
                    <label for="article">Article/Category</label>
                    <input type="text" id="article" name="article" class="form-control" placeholder="e.g., Office Equipment, Furniture">
                </div>
                
                <div class="form-group">
                    <label for="po_number">PO Number</label>
                    <input type="text" id="po_number" name="po_number" class="form-control" placeholder="Purchase Order Number">
                </div>
                
                <div class="form-group">
                    <label for="year_acquired">Year Acquired <span style="color: red;">*</span></label>
                    <input type="number" id="year_acquired" name="year_acquired" class="form-control" min="1900" max="<?php echo date('Y'); ?>" value="<?php echo date('Y'); ?>" required>
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3" placeholder="Item description and specifications"></textarea>
                </div>
            </div>
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                <div style="background-color: #E8F4F8; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    <h4 style="margin-bottom: 10px; color: #0C5460;">
                        <i class="fas fa-info-circle"></i> Auto-Generated Information
                    </h4>
                    <ul style="margin-left: 20px; color: #0C5460;">
                        <li>Property Number will be auto-generated (Format: YYYY-PPE-000001 or YYYY-SE-000001)</li>
                        <li>Classification will be auto-determined based on unit value</li>
                        <li>Property Card will be auto-created for this item</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Item
                </button>
                <a href="/items" class="btn btn-info">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
