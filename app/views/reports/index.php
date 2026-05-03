<?php $pageTitle = 'Reports'; ?>
<?php require VIEW_PATH . '/layouts/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-chart-bar"></i> Reports and Analytics</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <!-- Inventory Report -->
            <div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px;">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                    <div style="width: 60px; height: 60px; background-color: #B22222; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0;">Inventory Report</h4>
                        <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">Complete inventory listing</p>
                    </div>
                </div>
                <p style="color: #666; margin-bottom: 15px;">
                    View all items with details including property numbers, classifications, and quantities.
                </p>
                <a href="/reports/inventory" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-file-alt"></i> Generate Report
                </a>
            </div>
            
            <!-- Unserviceable Items Report -->
            <div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px;">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                    <div style="width: 60px; height: 60px; background-color: #DC3545; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0;">Unserviceable Items</h4>
                        <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">Damaged and unusable items</p>
                    </div>
                </div>
                <p style="color: #666; margin-bottom: 15px;">
                    List of all items marked as unserviceable or damaged.
                </p>
                <a href="/reports/unserviceable" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-file-alt"></i> Generate Report
                </a>
            </div>
            
            <!-- Issuance Report -->
            <div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px;">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                    <div style="width: 60px; height: 60px; background-color: #28A745; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
                        <i class="fas fa-file-export"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0;">Issuance Report</h4>
                        <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">PAR and ICS records</p>
                    </div>
                </div>
                <p style="color: #666; margin-bottom: 15px;">
                    View all issuances with PAR and ICS details.
                </p>
                <a href="/issuances" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-file-alt"></i> View Issuances
                </a>
            </div>
            
            <!-- Returns Report -->
            <div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px;">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                    <div style="width: 60px; height: 60px; background-color: #17A2B8; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
                        <i class="fas fa-undo"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0;">Returns Report</h4>
                        <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">Item return records</p>
                    </div>
                </div>
                <p style="color: #666; margin-bottom: 15px;">
                    View all returned items with condition assessments.
                </p>
                <a href="/returns" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-file-alt"></i> View Returns
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Export Options -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-download"></i> Export Options</h3>
    </div>
    <div class="card-body">
        <div style="background-color: #E8F4F8; padding: 20px; border-radius: 4px; margin-bottom: 20px;">
            <h4 style="margin-bottom: 10px; color: #0C5460;">
                <i class="fas fa-info-circle"></i> Export Functionality
            </h4>
            <p style="color: #0C5460; margin-bottom: 10px;">
                Reports can be exported to PDF format. Excel export is available as a placeholder.
            </p>
            <p style="color: #0C5460; font-size: 13px; margin: 0;">
                <strong>TODO:</strong> Import official Excel template when provided by client
            </p>
        </div>
        
        <div style="display: flex; gap: 15px;">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-file-pdf"></i> Export to PDF
            </button>
            <button class="btn btn-success" disabled title="Excel template not yet configured">
                <i class="fas fa-file-excel"></i> Export to Excel (Coming Soon)
            </button>
        </div>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
