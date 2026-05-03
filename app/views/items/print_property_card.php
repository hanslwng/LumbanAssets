<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Card - <?php echo htmlspecialchars($item['property_number']); ?></title>
    <style>
        /* TODO: Replace with official COA template once provided by client */
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 18px;
            margin: 5px 0;
        }
        .header p {
            margin: 3px 0;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            width: 150px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table th, table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        .signature-block {
            text-align: center;
            width: 200px;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 40px;
            padding-top: 5px;
        }
        @media print {
            body {
                margin: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background-color: #B22222; color: white; border: none; cursor: pointer; border-radius: 4px;">
            Print Property Card
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background-color: #666; color: white; border: none; cursor: pointer; border-radius: 4px; margin-left: 10px;">
            Close
        </button>
    </div>
    
    <div class="header">
        <h1>PROPERTY CARD</h1>
        <p>Local Government Unit</p>
        <p>General Services Office</p>
        <p style="margin-top: 15px; font-style: italic; color: #666;">
            <!-- TODO: Replace with official COA template once provided by client -->
        </p>
    </div>
    
    <div class="info-section">
        <div class="info-row">
            <div class="info-label">Property Number:</div>
            <div><?php echo htmlspecialchars($item['property_number']); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Item Name:</div>
            <div><?php echo htmlspecialchars($item['item_name']); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Classification:</div>
            <div><?php echo htmlspecialchars($item['classification']); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Office:</div>
            <div><?php echo htmlspecialchars($item['office_name']); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Unit Value:</div>
            <div>₱<?php echo number_format($item['unit_value'], 2); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Year Acquired:</div>
            <div><?php echo $item['year_acquired']; ?></div>
        </div>
        <?php if ($item['description']): ?>
        <div class="info-row">
            <div class="info-label">Description:</div>
            <div><?php echo htmlspecialchars($item['description']); ?></div>
        </div>
        <?php endif; ?>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Reference Number</th>
                <th>Receipt</th>
                <th>Issue</th>
                <th>Balance</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($propertyCard)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px;">No transactions recorded</td>
                </tr>
            <?php else: ?>
                <?php foreach ($propertyCard as $card): ?>
                    <tr>
                        <td><?php echo date('m/d/Y', strtotime($card['transaction_date'])); ?></td>
                        <td><?php echo htmlspecialchars($card['reference_number']); ?></td>
                        <td><?php echo ($card['transaction_type'] === 'IN') ? number_format($card['quantity']) : '-'; ?></td>
                        <td><?php echo ($card['transaction_type'] === 'OUT') ? number_format($card['quantity']) : '-'; ?></td>
                        <td><strong><?php echo number_format($card['balance']); ?></strong></td>
                        <td><?php echo htmlspecialchars($card['remarks'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="footer">
        <div class="signature-block">
            <div class="signature-line">Property Custodian</div>
        </div>
        <div class="signature-block">
            <div class="signature-line">GSO Head</div>
        </div>
    </div>
    
    <p style="margin-top: 30px; font-size: 10px; color: #666; text-align: center;">
        Generated on <?php echo date('F d, Y h:i A'); ?>
    </p>
</body>
</html>
