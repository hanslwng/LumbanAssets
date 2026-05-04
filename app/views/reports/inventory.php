<?php $pageTitle = 'Inventory Report'; ?>
<?php require VIEW_PATH . '/layouts/header.php'; ?>

<div style="margin-bottom: 20px;">
    <a href="/reports" class="btn btn-info">
        <i class="fas fa-arrow-left"></i> Back to Reports
    </a>
    <button onclick="window.print()" class="btn btn-primary">
        <i class="fas fa-print"></i> Print Report
    </button>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-boxes"></i> Complete Inventory Report</h3>
    </div>
    <div class="card-body">
        <!-- Filters -->
        <form method="GET" action="/reports/inventory" style="margin-bottom: 20px;" class="no-print">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="office_id">Filter by Office</label>
                    <select name="office_id" id="office_id" class="form-control">
                        <option value="">All Offices</option>
                        <?php foreach ($offices as $office): ?>
                            <option value="<?php echo $office['id']; ?>" <?php echo (isset($_GET['office_id']) && $_GET['office_id'] == $office['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($office['office_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="classification">Classification</label>
                    <select name="classification" id="classification" class="form-control">
                        <option value="">All Classifications</option>
                        <option value="PPE" <?php echo (isset($_GET['classification']) && $_GET['classification'] == 'PPE') ? 'selected' : ''; ?>>PPE</option>
                        <option value="Semi-Expendable" <?php echo (isset($_GET['classification']) && $_GET['classification'] == 'Semi-Expendable') ? 'selected' : ''; ?>>Semi-Expendable</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="view_mode">View Mode</label>
                    <select name="view_mode" id="view_mode" class="form-control">
                        <option value="standard" <?php echo (!isset($_GET['view_mode']) || $_GET['view_mode'] == 'standard') ? 'selected' : ''; ?>>Standard Table</option>
                        <option value="expandable" <?php echo (isset($_GET['view_mode']) && $_GET['view_mode'] == 'expandable') ? 'selected' : ''; ?>>Semi-Expandable (Grouped)</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0; display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </div>
        </form>
        
        <div style="text-align: center; margin-bottom: 20px;">
            <p style="font-size: 12px; color: #666;">Generated on <?php echo date('F d, Y h:i A'); ?></p>
        </div>
        
        <?php 
        $viewMode = isset($_GET['view_mode']) ? $_GET['view_mode'] : 'standard';
        
        if ($viewMode === 'expandable'): 
            // Group items by category for semi-expandable view
            $groupedItems = [];
            $grandTotal = 0;
            
            foreach ($items as $item) {
                // Determine category based on item classification or description
                $category = 'UNCATEGORIZED';
                
                // You can customize this logic based on your item categorization
                if (stripos($item['item_name'], 'table') !== false || 
                    stripos($item['item_name'], 'chair') !== false || 
                    stripos($item['item_name'], 'cabinet') !== false ||
                    stripos($item['item_name'], 'partition') !== false) {
                    $category = 'OFFICE FIXTURES & FURNITURES';
                } elseif (stripos($item['item_name'], 'computer') !== false || 
                          stripos($item['item_name'], 'laptop') !== false || 
                          stripos($item['item_name'], 'printer') !== false ||
                          stripos($item['item_name'], 'monitor') !== false) {
                    $category = 'INFORMATION AND COMMUNICATION TECHNOLOGY EQUIPMENT';
                } elseif (stripos($item['item_name'], 'air con') !== false || 
                          stripos($item['item_name'], 'fan') !== false || 
                          stripos($item['item_name'], 'dispenser') !== false) {
                    $category = 'OFFICE EQUIPMENT';
                }
                
                if (!isset($groupedItems[$category])) {
                    $groupedItems[$category] = [
                        'items' => [],
                        'total' => 0
                    ];
                }
                
                $itemTotal = $item['unit_value'] * $item['quantity'];
                $groupedItems[$category]['items'][] = $item;
                $groupedItems[$category]['total'] += $itemTotal;
                $grandTotal += $itemTotal;
            }
        ?>
        
        <div style="margin-bottom: 10px;">
            <button id="expand-all-btn" class="expand-all-btn" onclick="expandAllCategories()">▶ Expand All</button>
        </div>
        
        <div class="table-responsive">
            <table class="semi-expandable-table">
                <thead>
                    <tr>
                        <th style="width: 20%;">Article / Item</th>
                        <th style="width: 25%;">Description</th>
                        <th style="width: 15%;">Property Number</th>
                        <th style="width: 10%;">Unit of Measure</th>
                        <th style="width: 10%;">Unit Value</th>
                        <th style="width: 10%;">Balance Per Card</th>
                        <th style="width: 10%;">On Hand Count</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $categoryIndex = 0;
                    foreach ($groupedItems as $categoryName => $categoryData): 
                        $categoryId = 'cat-' . $categoryIndex;
                    ?>
                        <!-- Category Header Row -->
                        <tr class="category-header-row" data-category-id="<?php echo $categoryId; ?>" onclick="toggleCategory('<?php echo $categoryId; ?>')">
                            <td class="category-header-cell" colspan="5">
                                <span class="expand-icon">▶</span>
                                <?php echo htmlspecialchars($categoryName); ?>
                            </td>
                            <td class="category-total-cell" colspan="3">
                                ₱<?php echo number_format($categoryData['total'], 2); ?>
                            </td>
                        </tr>
                        
                        <!-- Category Items Row (hidden by default) -->
                        <tr class="category-items-row" id="items-<?php echo $categoryId; ?>">
                            <td colspan="8" class="category-items-cell">
                                <table class="nested-items-table">
                                    <?php foreach ($categoryData['items'] as $item): ?>
                                        <tr>
                                            <td style="width: 20%;"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                            <td style="width: 25%;"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                            <td style="width: 15%;" class="mono"><?php echo htmlspecialchars($item['property_number']); ?></td>
                                            <td style="width: 10%;">pc</td>
                                            <td style="width: 10%;">₱<?php echo number_format($item['unit_value'], 2); ?></td>
                                            <td style="width: 10%; text-align: center;"><?php echo number_format($item['quantity']); ?></td>
                                            <td style="width: 10%; text-align: center;"><?php echo number_format($item['available_quantity']); ?></td>
                                            <td><?php echo htmlspecialchars($item['remarks'] ?: '—'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    
                                    <!-- Category Subtotal -->
                                    <tr class="category-subtotal-row">
                                        <td colspan="5"><strong>Subtotal - <?php echo htmlspecialchars($categoryName); ?></strong></td>
                                        <td colspan="3" style="text-align: right;"><strong>₱<?php echo number_format($categoryData['total'], 2); ?></strong></td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    <?php 
                        $categoryIndex++;
                    endforeach; 
                    ?>
                    
                    <!-- Grand Total Row -->
                    <tr class="grand-total-row">
                        <td colspan="5">GRAND TOTAL</td>
                        <td colspan="3" style="text-align: right;">₱<?php echo number_format($grandTotal, 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <?php else: ?>
        
        <!-- Standard Table View -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Property Number</th>
                        <th>Item Name</th>
                        <th>Office</th>
                        <th>Classification</th>
                        <th>Unit Value</th>
                        <th>Quantity</th>
                        <th>Available</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px;">No items found</td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $totalValue = 0;
                        foreach ($items as $item): 
                            $totalValue += $item['unit_value'] * $item['quantity'];
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['property_number']); ?></td>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['office_code']); ?></td>
                                <td><?php echo htmlspecialchars($item['classification']); ?></td>
                                <td>₱<?php echo number_format($item['unit_value'], 2); ?></td>
                                <td><?php echo number_format($item['quantity']); ?></td>
                                <td><?php echo number_format($item['available_quantity']); ?></td>
                                <td><?php echo htmlspecialchars($item['status']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="background-color: #f0f0f0; font-weight: bold;">
                            <td colspan="4">TOTAL</td>
                            <td>₱<?php echo number_format($totalValue, 2); ?></td>
                            <td colspan="3"></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php endif; ?>
    </div>
</div>

<script>
// Toggle category function for semi-expandable table
function toggleCategory(categoryId) {
    const headerRow = document.querySelector(`[data-category-id="${categoryId}"]`);
    const itemsRow = document.getElementById(`items-${categoryId}`);
    
    if (!headerRow || !itemsRow) return;
    
    const isExpanded = headerRow.classList.contains('expanded');
    
    if (isExpanded) {
        headerRow.classList.remove('expanded');
        itemsRow.classList.remove('visible');
    } else {
        headerRow.classList.add('expanded');
        itemsRow.classList.add('visible');
    }
}

// Expand all categories
function expandAllCategories() {
    const allHeaders = document.querySelectorAll('.category-header-row');
    const allItems = document.querySelectorAll('.category-items-row');
    
    allHeaders.forEach(header => header.classList.add('expanded'));
    allItems.forEach(items => items.classList.add('visible'));
    
    const btn = document.getElementById('expand-all-btn');
    if (btn) {
        btn.textContent = '▼ Collapse All';
        btn.onclick = collapseAllCategories;
    }
}

// Collapse all categories
function collapseAllCategories() {
    const allHeaders = document.querySelectorAll('.category-header-row');
    const allItems = document.querySelectorAll('.category-items-row');
    
    allHeaders.forEach(header => header.classList.remove('expanded'));
    allItems.forEach(items => items.classList.remove('visible'));
    
    const btn = document.getElementById('expand-all-btn');
    if (btn) {
        btn.textContent = '▶ Expand All';
        btn.onclick = expandAllCategories;
    }
}
</script>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
