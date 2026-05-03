# Inventory Module Documentation

## Purpose
The Inventory module manages Property, Plant, and Equipment (PPE) and Semi-Expendable items following COA-compliant asset tracking standards. It handles item encoding, classification, and property card generation.

## How It Works

### Item Classification Logic
Items are automatically classified based on unit value:
- **PPE (Property, Plant, and Equipment)**: Unit value ≥ ₱50,000
- **Semi-Expendable**: Unit value < ₱50,000

This classification is automatic and cannot be manually overridden.

### Property Number Generation
Property numbers are auto-generated using the format:
- **PPE**: `YYYY-PPE-000001` (e.g., 2024-PPE-000001)
- **Semi-Expendable**: `YYYY-SE-000001` (e.g., 2024-SE-000001)

Where:
- `YYYY` = Year acquired
- Sequential number increments per year and classification

### Property Card Auto-Creation
When an item is created, the system automatically:
1. Generates a unique property number
2. Creates an initial property card entry (IN transaction)
3. Sets the initial balance equal to quantity
4. Records the transaction date and reference

## Features

### 1. Item Encoding
**Fields**:
- Item Name (required)
- Description (optional)
- Unit Value (required) - triggers auto-classification
- Quantity (required)
- Office (required) - items are office-specific
- Article/Category (optional)
- PO Number (optional)
- Year Acquired (required)

**Auto-Generated**:
- Property Number
- Classification
- Property Card
- Status (default: Serviceable)

### 2. Office-Based Inventory
- Items are grouped by office
- Each office has its own inventory view
- No mixing of items across offices
- Filter by office in inventory list

### 3. Property Card
Each item has a property card that tracks:
- **IN transactions**: Receipts, returns
- **OUT transactions**: Issuances
- **Balance**: Current available quantity
- **Reference**: Form numbers (PAR, ICS, etc.)
- **Remarks**: Transaction notes

### 4. Item Status
- **Serviceable**: Item is in good condition and available
- **Unserviceable**: Item is no longer usable
- **Damaged**: Item is damaged but may be repairable

## Files Structure

```
app/
├── controllers/
│   └── ItemController.php      # Item management
├── models/
│   └── Item.php                # Item data and logic
└── views/
    └── items/
        ├── index.php           # Item list
        ├── create.php          # Add new item
        ├── view.php            # Item details
        └── print_property_card.php  # Printable property card
```

## Key Functions

### ItemController
- `index()`: List all items with filters
- `create()`: Show item creation form
- `store()`: Save new item
- `view($id)`: Display item details and property card
- `printPropertyCard($id)`: Generate printable property card

### Item Model
- `generatePropertyNumber($classification, $year)`: Create unique property number
- `autoClassify($unitValue)`: Determine PPE or Semi-Expendable
- `createItem($data)`: Create new item with auto-generation
- `getAllItems($filters)`: Retrieve items with filtering
- `getItemById($id)`: Get single item details
- `getPropertyCard($itemId)`: Retrieve property card transactions
- `updateQuantity($itemId, $quantity, $type)`: Adjust available quantity
- `updateStatus($itemId, $status)`: Change item status
- `getDashboardStats()`: Get inventory statistics

## Database Schema

### items Table
```sql
- id (Primary Key)
- property_number (Unique, Auto-generated)
- item_name
- description
- unit_value (Decimal)
- quantity (Total quantity)
- available_quantity (Available for issuance)
- office_id (Foreign Key)
- classification (PPE or Semi-Expendable)
- article
- po_number
- year_acquired
- status (Serviceable, Unserviceable, Damaged)
- created_at
- updated_at
```

### property_cards Table
```sql
- id (Primary Key)
- item_id (Foreign Key)
- transaction_type (IN or OUT)
- quantity
- balance (Running balance)
- reference_number
- transaction_date
- remarks
- created_at
```

## What to Configure Later

1. **Custom Classification Rules**: Modify the ₱50,000 threshold if needed
2. **Property Number Format**: Adjust format if LGU has specific requirements
3. **Additional Fields**: Add custom fields specific to LGU needs
4. **Barcode/QR Code**: Implement barcode generation for items
5. **Photo Upload**: Add item photo upload capability
6. **Depreciation Tracking**: Implement asset depreciation calculation
7. **Maintenance Records**: Track maintenance and repairs
8. **Location Tracking**: Add sub-location within offices

## Usage Examples

### Adding a New Item
1. Navigate to Inventory → Add New Item
2. Fill in required fields:
   - Item Name: "Desktop Computer"
   - Unit Value: 45,000
   - Quantity: 5
   - Office: Select from dropdown
   - Year Acquired: 2024
3. System automatically:
   - Classifies as Semi-Expendable (< ₱50,000)
   - Generates property number: 2024-SE-000001
   - Creates property card with initial IN entry
4. Item is saved and viewable

### Viewing Property Card
1. Go to Inventory list
2. Click "View" on any item
3. Property card shows all IN/OUT transactions
4. Click "Print Property Card" for official document

### Filtering Inventory
Use filters to view:
- Items by specific office
- Only PPE or Semi-Expendable
- By status (Serviceable, Unserviceable, Damaged)

## COA Compliance Notes

This module follows Commission on Audit (COA) guidelines for:
- Property numbering system
- Property card maintenance
- Asset classification
- Transaction recording

**Important**: Property cards must be maintained for audit purposes and should never be deleted, only marked as unserviceable when appropriate.

## Troubleshooting

### Property Number Not Generating
- Check database connection
- Verify year_acquired is valid
- Check for duplicate property numbers

### Classification Incorrect
- Verify unit_value is correct
- Check if threshold (₱50,000) needs adjustment

### Property Card Missing Transactions
- Verify issuance/return was completed
- Check property_cards table for entries

---

**Last Updated**: <?php echo date('F d, Y'); ?>
