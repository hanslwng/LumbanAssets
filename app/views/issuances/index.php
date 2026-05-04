<?php $pageTitle = 'Issuances (PAR/ICS)'; ?>
<?php require VIEW_PATH . '/layouts/header.php'; ?>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3><i class="fas fa-file-export"></i> Issuances</h3>
        <a href="/issuances/create" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create Issuance
        </a>
    </div>
    <div class="card-body">
        <!-- Filters -->
        <form method="GET" action="/issuances" style="margin-bottom: 20px;">
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
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="Active" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                        <option value="Returned" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Returned') ? 'selected' : ''; ?>>Returned</option>
                        <option value="Partial" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Partial') ? 'selected' : ''; ?>>Partial</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="form_type">Form Type</label>
                    <select name="form_type" id="form_type" class="form-control">
                        <option value="">All Types</option>
                        <option value="PAR" <?php echo (isset($_GET['form_type']) && $_GET['form_type'] == 'PAR') ? 'selected' : ''; ?>>PAR</option>
                        <option value="ICS" <?php echo (isset($_GET['form_type']) && $_GET['form_type'] == 'ICS') ? 'selected' : ''; ?>>ICS</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0; display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary" style="margin-right: 10px;">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="/issuances" class="btn btn-info">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
        
        <!-- Issuances Table -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Form Type</th>
                        <th>Form Number</th>
                        <th>Item</th>
                        <th>Issued To</th>
                        <th>Office</th>
                        <th>Quantity</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($issuances)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px; color: #999;">
                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                                No issuances found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($issuances as $issuance): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($issuance['issuance_date'])); ?></td>
                                <td>
                                    <?php if ($issuance['form_type'] === 'PAR'): ?>
                                        <span class="badge badge-primary">PAR</span>
                                    <?php else: ?>
                                        <span class="badge badge-info">ICS</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($issuance['form_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($issuance['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($issuance['issued_to']); ?></td>
                                <td><?php echo htmlspecialchars($issuance['office_name']); ?></td>
                                <td><?php echo number_format($issuance['quantity']); ?></td>
                                <td>
                                    <?php if ($issuance['status'] === 'Active'): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php elseif ($issuance['status'] === 'Returned'): ?>
                                        <span class="badge badge-danger">Returned</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Partial</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="/issuances/view/<?php echo $issuance['id']; ?>" class="btn btn-sm btn-info" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="/issuances/print/<?php echo $issuance['id']; ?>" class="btn btn-sm btn-primary" title="Print" target="_blank">
                                        <i class="fas fa-print"></i>
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
