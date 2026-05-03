<?php $pageTitle = 'Issuance Details - ' . $issuance['form_number']; ?>
<?php require VIEW_PATH . '/layouts/header.php'; ?>

<div style="margin-bottom: 20px;">
    <a href="/issuances" class="btn btn-info">
        <i class="fas fa-arrow-left"></i> Back to Issuances
    </a>
    <a href="/issuances/print/<?php echo $issuance['id']; ?>" class="btn btn-primary" target="_blank">
        <i class="fas fa-print"></i> Print <?php echo $issuance['form_type']; ?>
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-file-export"></i> Issuance Information</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
            <div>
                <strong>Form Type:</strong><br>
                <?php if ($issuance['form_type'] === 'PAR'): ?>
                    <span class="badge badge-primary" style="font-size: 14px;">PAR</span>
                <?php else: ?>
                    <span class="badge badge-info" style="font-size: 14px;">ICS</span>
                <?php endif; ?>
            </div>
            <div>
                <strong>Form Number:</strong><br>
                <span style="font-size: 18px; color: #B22222;"><?php echo htmlspecialchars($issuance['form_number']); ?></span>
            </div>
            <div>
                <strong>Status:</strong><br>
                <?php if ($issuance['status'] === 'Active'): ?>
                    <span class="badge badge-success" style="font-size: 14px;">Active</span>
                <?php elseif ($issuance['status'] === 'Returned'): ?>
                    <span class="badge badge-danger" style="font-size: 14px;">Returned</span>
                <?php else: ?>
                    <span class="badge badge-warning" style="font-size: 14px;">Partial</span>
                <?php endif; ?>
            </div>
            <div>
                <strong>Issuance Date:</strong><br>
                <?php echo date('F d, Y', strtotime($issuance['issuance_date'])); ?>
            </div>
            <div>
                <strong>RIS Number:</strong><br>
                <?php echo htmlspecialchars($issuance['ris_number'] ?? 'N/A'); ?>
            </div>
            <div>
                <strong>Created By:</strong><br>
                <?php echo htmlspecialchars($issuance['created_by_name']); ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-box"></i> Item Details</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
            <div>
                <strong>Property Number:</strong><br>
                <?php echo htmlspecialchars($issuance['property_number']); ?>
            </div>
            <div>
                <strong>Item Name:</strong><br>
                <?php echo htmlspecialchars($issuance['item_name']); ?>
            </div>
            <div>
                <strong>Classification:</strong><br>
                <?php echo htmlspecialchars($issuance['classification']); ?>
            </div>
            <div>
                <strong>Quantity Issued:</strong><br>
                <span style="font-size: 18px; color: #B22222;"><?php echo number_format($issuance['quantity']); ?></span>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-user"></i> Recipient Information</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
            <div>
                <strong>Issued To:</strong><br>
                <span style="font-size: 16px;"><?php echo htmlspecialchars($issuance['issued_to']); ?></span>
            </div>
            <div>
                <strong>Office:</strong><br>
                <?php echo htmlspecialchars($issuance['office_name']); ?>
            </div>
            <?php if ($issuance['purpose']): ?>
            <div style="grid-column: 1 / -1;">
                <strong>Purpose:</strong><br>
                <?php echo nl2br(htmlspecialchars($issuance['purpose'])); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($returns)): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-undo"></i> Return History</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Return Date</th>
                        <th>Quantity</th>
                        <th>Condition</th>
                        <th>Action Taken</th>
                        <th>Processed By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($returns as $return): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($return['return_date'])); ?></td>
                            <td><?php echo number_format($return['quantity']); ?></td>
                            <td>
                                <?php if ($return['condition_status'] === 'Good'): ?>
                                    <span class="badge badge-success">Good</span>
                                <?php elseif ($return['condition_status'] === 'Fair'): ?>
                                    <span class="badge badge-warning">Fair</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Damaged</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($return['action_taken']); ?></td>
                            <td><?php echo htmlspecialchars($return['created_by_name']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
