<?php $pageTitle = 'Return Details'; ?>
<?php require VIEW_PATH . '/layouts/header.php'; ?>

<div style="margin-bottom: 20px;">
    <a href="/returns" class="btn btn-info">
        <i class="fas fa-arrow-left"></i> Back to Returns
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-undo"></i> Return Information</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
            <div>
                <strong>Return Date:</strong><br>
                <?php echo date('F d, Y', strtotime($return['return_date'])); ?>
            </div>
            <div>
                <strong>Quantity Returned:</strong><br>
                <span style="font-size: 18px; color: #B22222;"><?php echo number_format($return['quantity']); ?></span>
            </div>
            <div>
                <strong>Condition:</strong><br>
                <?php if ($return['condition_status'] === 'Good'): ?>
                    <span class="badge badge-success" style="font-size: 14px;">Good</span>
                <?php elseif ($return['condition_status'] === 'Fair'): ?>
                    <span class="badge badge-warning" style="font-size: 14px;">Fair</span>
                <?php else: ?>
                    <span class="badge badge-danger" style="font-size: 14px;">Damaged</span>
                <?php endif; ?>
            </div>
            <div>
                <strong>Action Taken:</strong><br>
                <?php echo htmlspecialchars($return['action_taken']); ?>
            </div>
            <?php if ($return['transfer_office']): ?>
            <div>
                <strong>Transfer Office:</strong><br>
                <?php echo htmlspecialchars($return['transfer_office']); ?>
            </div>
            <?php endif; ?>
            <div>
                <strong>Processed By:</strong><br>
                <?php echo htmlspecialchars($return['created_by_name']); ?>
            </div>
        </div>
        <?php if ($return['remarks']): ?>
        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
            <strong>Remarks:</strong><br>
            <?php echo nl2br(htmlspecialchars($return['remarks'])); ?>
        </div>
        <?php endif; ?>
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
                <?php echo htmlspecialchars($return['property_number']); ?>
            </div>
            <div>
                <strong>Item Name:</strong><br>
                <?php echo htmlspecialchars($return['item_name']); ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-file-export"></i> Original Issuance</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
            <div>
                <strong>Form Number:</strong><br>
                <?php echo htmlspecialchars($return['form_number']); ?>
            </div>
            <div>
                <strong>Issued To:</strong><br>
                <?php echo htmlspecialchars($return['issued_to']); ?>
            </div>
        </div>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
