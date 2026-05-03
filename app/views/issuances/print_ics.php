<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ICS - <?php echo htmlspecialchars($issuance['form_number']); ?></title>
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table th, table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        table th {
            background-color: #f0f0f0;
        }
        .info-row {
            display: flex;
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: bold;
            width: 150px;
        }
        .signature-section {
            margin-top: 50px;
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
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background-color: #B22222; color: white; border: none; cursor: pointer; border-radius: 4px;">
            Print ICS
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background-color: #666; color: white; border: none; cursor: pointer; border-radius: 4px; margin-left: 10px;">
            Close
        </button>
    </div>
    
    <div class="header">
        <h1>INVENTORY CUSTODIAN SLIP (ICS)</h1>
        <p>Local Government Unit</p>
        <p>General Services Office</p>
        <p style="margin-top: 15px; font-style: italic; color: #666;">
            <!-- TODO: Replace with official COA template once provided by client -->
        </p>
    </div>
    
    <div style="margin-bottom: 20px;">
        <div class="info-row">
            <div class="info-label">ICS Number:</div>
            <div><?php echo htmlspecialchars($issuance['form_number']); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Date:</div>
            <div><?php echo date('F d, Y', strtotime($issuance['issuance_date'])); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">RIS Number:</div>
            <div><?php echo htmlspecialchars($issuance['ris_number'] ?? 'N/A'); ?></div>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Property Number</th>
                <th>Description</th>
                <th>Unit Value</th>
                <th>Quantity</th>
                <th>Total Value</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?php echo htmlspecialchars($issuance['property_number']); ?></td>
                <td><?php echo htmlspecialchars($issuance['item_name']); ?></td>
                <td>₱<?php echo number_format($issuance['unit_value'] ?? 0, 2); ?></td>
                <td><?php echo number_format($issuance['quantity']); ?></td>
                <td>₱<?php echo number_format(($issuance['unit_value'] ?? 0) * $issuance['quantity'], 2); ?></td>
            </tr>
        </tbody>
    </table>
    
    <div style="margin: 20px 0;">
        <div class="info-row">
            <div class="info-label">Custodian:</div>
            <div><strong><?php echo htmlspecialchars($issuance['issued_to']); ?></strong></div>
        </div>
        <div class="info-row">
            <div class="info-label">Office:</div>
            <div><?php echo htmlspecialchars($issuance['office_name']); ?></div>
        </div>
        <?php if ($issuance['purpose']): ?>
        <div class="info-row">
            <div class="info-label">Purpose:</div>
            <div><?php echo htmlspecialchars($issuance['purpose']); ?></div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="signature-section">
        <div class="signature-block">
            <div class="signature-line">Received by</div>
            <p style="margin-top: 5px; font-size: 11px;"><?php echo htmlspecialchars($issuance['issued_to']); ?></p>
        </div>
        <div class="signature-block">
            <div class="signature-line">Issued by</div>
            <p style="margin-top: 5px; font-size: 11px;">Property Custodian</p>
        </div>
        <div class="signature-block">
            <div class="signature-line">Approved by</div>
            <p style="margin-top: 5px; font-size: 11px;">GSO Head</p>
        </div>
    </div>
    
    <p style="margin-top: 30px; font-size: 10px; color: #666; text-align: center;">
        Generated on <?php echo date('F d, Y h:i A'); ?>
    </p>
</body>
</html>
