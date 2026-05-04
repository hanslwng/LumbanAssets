<?php $pageTitle = 'Item Details - ' . $item['property_number']; ?>
<?php require VIEW_PATH . '/layouts/header.php'; ?>

<div style="margin-bottom: 20px;">
    <a href="/items" class="btn btn-info">
        <i class="fas fa-arrow-left"></i> Back to Inventory
    </a>
    <a href="/items/print-property-card/<?php echo $item['id']; ?>" class="btn btn-primary" target="_blank">
        <i class="fas fa-print"></i> Print Property Card
    </a>
</div>

<!-- Item Details -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-box"></i> Item Information</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
            <div>
                <strong>Property Number:</strong><br>
                <span style="font-size: 18px; color: #B22222;"><?php echo htmlspecialchars($item['property_number']); ?></span>
            </div>
            <div>
                <strong>Item Name:</strong><br>
                <?php echo htmlspecialchars($item['item_name']); ?>
            </div>
            <div>
                <strong>Classification:</strong><br>
                <?php if ($item['classification'] === 'PPE'): ?>
                    <span class="badge badge-primary">PPE</span>
                <?php else: ?>
                    <span class="badge badge-info">Semi-Expendable</span>
                <?php endif; ?>
            </div>
            <div>
                <strong>Office:</strong><br>
                <?php echo htmlspecialchars($item['office_name']); ?> (<?php echo htmlspecialchars($item['office_code']); ?>)
            </div>
            <div>
                <strong>Unit Value:</strong><br>
                ₱<?php echo number_format($item['unit_value'], 2); ?>
            </div>
            <div>
                <strong>Status:</strong><br>
                <?php if ($item['status'] === 'Serviceable'): ?>
                    <span class="badge badge-success">Serviceable</span>
                <?php elseif ($item['status'] === 'Unserviceable'): ?>
                    <span class="badge badge-danger">Unserviceable</span>
                <?php else: ?>
                    <span class="badge badge-warning">Damaged</span>
                <?php endif; ?>
            </div>
            <div>
                <strong>Total Quantity:</strong><br>
                <?php echo number_format($item['quantity']); ?>
            </div>
            <div>
                <strong>Available Quantity:</strong><br>
                <span style="font-size: 18px; color: #28A745;"><?php echo number_format($item['available_quantity']); ?></span>
            </div>
            <div>
                <strong>Year Acquired:</strong><br>
                <?php echo $item['year_acquired']; ?>
            </div>
            <?php if ($item['article']): ?>
            <div>
                <strong>Article/Category:</strong><br>
                <?php echo htmlspecialchars($item['article']); ?>
            </div>
            <?php endif; ?>
            <?php if ($item['po_number']): ?>
            <div>
                <strong>PO Number:</strong><br>
                <?php echo htmlspecialchars($item['po_number']); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($item['description']): ?>
        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
            <strong>Description:</strong><br>
            <?php echo nl2br(htmlspecialchars($item['description'])); ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Property Card -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-file-alt"></i> Property Card</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference Number</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Balance</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($propertyCard)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: #999;">
                                No transactions recorded
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($propertyCard as $card): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($card['transaction_date'])); ?></td>
                                <td><?php echo htmlspecialchars($card['reference_number']); ?></td>
                                <td>
                                    <?php if ($card['transaction_type'] === 'IN'): ?>
                                        <span class="badge badge-success">IN</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">OUT</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($card['quantity']); ?></td>
                                <td><strong><?php echo number_format($card['balance']); ?></strong></td>
                                <td><?php echo htmlspecialchars($card['remarks'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Active Issuances -->
<?php if (!empty($activeIssuances)): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-file-export"></i> Active Issuances</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Form Number</th>
                        <th>Issued To</th>
                        <th>Office</th>
                        <th>Quantity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activeIssuances as $issuance): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($issuance['issuance_date'])); ?></td>
                            <td><?php echo htmlspecialchars($issuance['form_number']); ?></td>
                            <td><?php echo htmlspecialchars($issuance['issued_to']); ?></td>
                            <td><?php echo htmlspecialchars($issuance['office_name']); ?></td>
                            <td><?php echo number_format($issuance['quantity']); ?></td>
                            <td><span class="badge badge-success"><?php echo $issuance['status']; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
