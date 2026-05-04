<?php $pageTitle = 'Unserviceable Items Report'; ?>
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
        <h3><i class="fas fa-exclamation-triangle"></i> Unserviceable Items Report</h3>
    </div>
    <div class="card-body">
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
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px;">
                                <i class="fas fa-check-circle" style="font-size: 48px; color: #28A745; margin-bottom: 10px; display: block;"></i>
                                No unserviceable items found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['property_number']); ?></td>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['office_code']); ?></td>
                                <td><?php echo htmlspecialchars($item['classification']); ?></td>
                                <td>₱<?php echo number_format($item['unit_value'], 2); ?></td>
                                <td><?php echo number_format($item['quantity']); ?></td>
                                <td><span class="badge badge-danger">Unserviceable</span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (!empty($items)): ?>
        <div style="margin-top: 30px; padding: 20px; background-color: #FFF3CD; border-radius: 4px;">
            <h4 style="color: #856404; margin-bottom: 10px;">
                <i class="fas fa-info-circle"></i> Note
            </h4>
            <p style="color: #856404; margin: 0;">
                These items have been marked as unserviceable and cannot be issued. 
                They should be processed for disposal according to COA guidelines.
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
