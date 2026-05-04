# Property Card Module Documentation

## Purpose
The Property Card is a COA-required document that tracks all movements (IN and OUT) of each inventory item. It provides a complete audit trail from acquisition to disposal and is automatically maintained by the system.

## How It Works

### Auto-Generation
Property cards are **automatically created and updated** - never manually encoded. The system creates entries when:
1. **Item is created** → Initial IN entry
2. **Item is issued** → OUT entry
3. **Item is returned** → IN entry
4. **Item is transferred** → IN/OUT entries
5. **Item marked unserviceable** → IN entry with note

### Transaction Types
- **IN**: Item received, returned, or added to inventory
- **OUT**: Item issued, transferred, or removed from inventory

### Balance Calculation
The system maintains a running balance:
- Starts with initial quantity
- Decreases with OUT transactions
- Increases with IN transactions
- Always shows current available quantity

## Features

### 1. Automatic Entry Creation
Every inventory movement automatically creates a property card entry with:
- Transaction date
- Transaction type (IN/OUT)
- Quantity moved
- Running balance
- Reference number (PAR, ICS, RETURN, etc.)
- Remarks

### 2. Viewable Property Card
Users can view property card:
- From item details page
- Shows complete transaction history
- Chronological order (oldest to newest)
- Running balance visible

### 3. Printable Property Card
Official COA-format property card can be printed:
- Includes item details
- Complete transaction history
- Signature blocks
- Official format

**TODO**: Replace with official COA template when provided

## Files Structure

```
app/
├── models/
│   └── Item.php                # Contains property card functions
└── views/
    └── items/
        ├── view.php            # Shows property card
        └── print_property_card.php  # Printable format
```

## Key Functions

### Item Model (Property Card Functions)
- `createPropertyCardEntry()`: Create new entry (private)
- `addPropertyCardEntry()`: Public method for external calls
- `getPropertyCard($itemId)`: Retrieve all entries for an item

### Entry Creation Parameters
```php
addPropertyCardEntry(
    $itemId,           // Item ID
    $type,             // 'IN' or 'OUT'
    $quantity,         // Amount moved
    $balance,          // New balance after transaction
    $reference,        // Form number or reference
    $date,             // Transaction date
    $remarks           // Optional notes
)
```

## Property Card Structure

### Display Format
```
Date | Reference | Type | Quantity | Balance | Remarks
-----|-----------|------|----------|---------|--------
```

### Example Entries
```
01/15/2024 | 2024-PPE-000001 | IN  | 5  | 5  | Initial inventory
02/01/2024 | PAR-2024-001    | OUT | 2  | 3  | Issued to: Juan Dela Cruz
03/15/2024 | RETURN-001      | IN  | 1  | 4  | Returned - Condition: Good
```

## COA Compliance

### Required Elements
1. **Property Number**: Unique identifier
2. **Item Description**: Complete details
3. **Transaction History**: All movements
4. **Running Balance**: Current quantity
5. **References**: Form numbers
6. **Dates**: All transaction dates

### Audit Trail
Property cards provide:
- Complete history from acquisition
- Accountability tracking
- Verification of quantities
- Support for COA audits

### Retention
- Property cards must be retained per COA guidelines
- Never delete entries
- Maintain even for disposed items
- Archive when item is fully disposed

## Transaction Reference Formats

### Standard References
- **Initial Entry**: Property number (e.g., 2024-PPE-000001)
- **Issuance**: Form number (e.g., PAR-2024-001, ICS-2024-001)
- **Return**: RETURN-[ID] (e.g., RETURN-001)
- **Transfer**: TRANSFER-[ID] (e.g., TRANSFER-001)
- **Unserviceable**: UNSERV-[ID] (e.g., UNSERV-001)

### Custom References
Can be configured for:
- Donations
- Transfers between LGUs
- Repairs
- Upgrades

## What to Configure Later

1. **Official COA Template**: Replace placeholder with official format
2. **Digital Signatures**: Add electronic signature capability
3. **Barcode Integration**: Print barcodes on property cards
4. **Photo Attachment**: Attach item photos to property card
5. **Condition Notes**: Add detailed condition tracking
6. **Location History**: Track physical location changes
7. **Maintenance Log**: Integrate maintenance records
8. **Depreciation**: Show depreciation schedule
9. **Export Options**: Export to Excel with official template

## Usage Examples

### Viewing Property Card
1. Navigate to Inventory
2. Click "View" on any item
3. Scroll to "Property Card" section
4. See complete transaction history

### Printing Property Card
1. View item details
2. Click "Print Property Card"
3. Official format opens in new window
4. Print or save as PDF
5. Use for COA audits or documentation

### Understanding Balance
**Example Scenario**:
- Initial: 10 items (Balance: 10)
- Issue 3 items (Balance: 7)
- Return 1 item (Balance: 8)
- Issue 2 items (Balance: 6)
- Current available: 6 items

## Integration with Other Modules

### With Inventory Module
- Created when item is added
- Updated with quantity changes
- Reflects current status

### With Issuance Module
- OUT entry created on issuance
- References form number
- Reduces balance

### With Returns Module
- IN entry created on return
- Notes condition
- Increases balance (if applicable)

### With Reports Module
- Property card data feeds reports
- Transaction history for audits
- Balance verification

## Validation and Integrity

### Balance Verification
System ensures:
- Balance never goes negative
- Balance matches available quantity
- All transactions accounted for

### Data Integrity
- Entries cannot be deleted
- Entries cannot be modified
- Chronological order maintained
- References are unique

### Audit Support
Property cards support:
- COA annual audits
- Internal audits
- Inventory verification
- Accountability checks

## Troubleshooting

### Property Card Not Showing
- Verify item exists
- Check database connection
- Review property_cards table

### Balance Incorrect
- Verify all transactions recorded
- Check for missing entries
- Review quantity calculations

### Cannot Print
- Check browser print settings
- Verify print template exists
- Review CSS print styles

### Missing Transactions
- Verify issuance/return completed
- Check transaction was saved
- Review database entries

## Best Practices

1. **Regular Review**: Review property cards periodically
2. **Audit Preparation**: Keep property cards updated for audits
3. **Documentation**: Maintain supporting documents (PAR, ICS)
4. **Verification**: Verify balances match physical count
5. **Backup**: Regular database backups
6. **Training**: Train staff on property card importance

---

**Last Updated**: <?php echo date('F d, Y'); ?>
