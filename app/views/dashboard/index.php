<?php $pageTitle = 'Dashboard'; ?>
<?php require VIEW_PATH . '/layouts/header.php'; ?>

<!-- Dashboard Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fas fa-building"></i>
        </div>
        <div class="stat-details">
            <h4>Total PPE</h4>
            <div class="stat-number"><?php echo number_format($stats['total_ppe']); ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-box"></i>
        </div>
        <div class="stat-details">
            <h4>Total Semi-Expendable</h4>
            <div class="stat-number"><?php echo number_format($stats['total_semi']); ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-file-export"></i>
        </div>
        <div class="stat-details">
            <h4>Total Issued Items</h4>
            <div class="stat-number"><?php echo number_format($stats['total_issued']); ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-details">
            <h4>Unserviceable Items</h4>
            <div class="stat-number"><?php echo number_format($stats['total_unserviceable']); ?></div>
        </div>
    </div>
</div>

<!-- Recent Transactions -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-history"></i> Recent Transactions</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Property Number</th>
                        <th>Item Name</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Balance</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentTransactions)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: #999;">
                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                                No transactions yet
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentTransactions as $transaction): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($transaction['transaction_date'])); ?></td>
                                <td><?php echo htmlspecialchars($transaction['property_number']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['item_name']); ?></td>
                                <td>
                                    <?php if ($transaction['transaction_type'] === 'IN'): ?>
                                        <span class="badge badge-success">IN</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">OUT</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($transaction['quantity']); ?></td>
                                <td><?php echo number_format($transaction['balance']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['reference_number']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
    </div>
    <div class="card-body">
        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
            <a href="/items/create" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add New Item
            </a>
            <a href="/issuances/create" class="btn btn-success">
                <i class="fas fa-file-export"></i> Create Issuance
            </a>
            <a href="/returns/create" class="btn btn-info">
                <i class="fas fa-undo"></i> Process Return
            </a>
            <a href="/reports" class="btn btn-warning">
                <i class="fas fa-chart-bar"></i> View Reports
            </a>
        </div>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
