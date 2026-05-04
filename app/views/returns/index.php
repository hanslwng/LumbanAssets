<?php $pageTitle = 'Returns Management'; ?>
<?php require VIEW_PATH . '/layouts/header.php'; ?>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3><i class="fas fa-undo"></i> Item Returns</h3>
        <a href="/returns/create" class="btn btn-primary">
            <i class="fas fa-plus"></i> Process Return
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Return Date</th>
                        <th>Item</th>
                        <th>Form Number</th>
                        <th>Issued To</th>
                        <th>Quantity</th>
                        <th>Condition</th>
                        <th>Action Taken</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($returns)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px; color: #999;">
                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                                No returns recorded
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($returns as $return): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($return['return_date'])); ?></td>
                                <td><?php echo htmlspecialchars($return['item_name']); ?></td>
                                <td><strong><?php echo htmlspecialchars($return['form_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($return['issued_to']); ?></td>
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
                                <td>
                                    <a href="/returns/view/<?php echo $return['id']; ?>" class="btn btn-sm btn-info" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
