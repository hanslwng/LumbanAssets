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
    </div>
</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
