<?php $pageTitle = 'Inventory Management'; ?>
<?php require VIEW_PATH . '/layouts/header.php'; ?>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3><i class="fas fa-boxes"></i> Inventory Items</h3>
        <a href="/items/create" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Item
        </a>
    </div>
    <div class="card-body">
        <!-- Filters -->
        <form method="GET" action="/items" style="margin-bottom: 20px;">
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
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="Serviceable" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Serviceable') ? 'selected' : ''; ?>>Serviceable</option>
                        <option value="Unserviceable" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Unserviceable') ? 'selected' : ''; ?>>Unserviceable</option>
                        <option value="Damaged" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Damaged') ? 'selected' : ''; ?>>Damaged</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0; display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary" style="margin-right: 10px;">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="/items" class="btn btn-info">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
        
        <!-- Items Table -->
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
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px; color: #999;">
                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                                No items found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($item['property_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['office_code']); ?></td>
                                <td>
                                    <?php if ($item['classification'] === 'PPE'): ?>
                                        <span class="badge badge-primary">PPE</span>
                                    <?php else: ?>
                                        <span class="badge badge-info">Semi-Expendable</span>
                                    <?php endif; ?>
                                </td>
                                <td>₱<?php echo number_format($item['unit_value'], 2); ?></td>
                                <td><?php echo number_format($item['quantity']); ?></td>
                                <td><?php echo number_format($item['available_quantity']); ?></td>
                                <td>
                                    <?php if ($item['status'] === 'Serviceable'): ?>
                                        <span class="badge badge-success">Serviceable</span>
                                    <?php elseif ($item['status'] === 'Unserviceable'): ?>
                                        <span class="badge badge-danger">Unserviceable</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Damaged</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="/items/view/<?php echo $item['id']; ?>" class="btn btn-sm btn-info" title="View Details">
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
