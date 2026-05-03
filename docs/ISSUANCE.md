# Issuance Module Documentation

## Purpose
The Issuance module handles the distribution of inventory items to personnel and offices. It automatically generates the appropriate form (PAR or ICS) based on item classification and maintains property card records.

## How It Works

### Form Type Determination
The system automatically selects the correct form based on item classification:
- **PPE items** (≥ ₱50,000) → **PAR** (Property Acknowledgement Receipt)
- **Semi-Expendable items** (< ₱50,000) → **ICS** (Inventory Custodian Slip)

This is automatic and follows COA guidelines.

### Issuance Process
1. User selects an item from available inventory
2. Enters quantity, recipient, and office
3. Provides form number and optional RIS number
4. System validates:
   - Item has sufficient available quantity
   - All required fields are filled
5. On submission:
   - Issuance record is created
   - Available quantity is reduced
   - Property card is updated (OUT transaction)
   - Form type (PAR/ICS) is auto-assigned

## Features

### 1. Create Issuance
**Required Fields**:
- Item (dropdown of available items)
- Quantity
- Issued To (person's name)
- Office
- Issuance Date
- Form Number (PAR-YYYY-XXX or ICS-YYYY-XXX)

**Optional Fields**:
- RIS Number (Requisition Issue Slip)
- Purpose

### 2. Issuance Status
- **Active**: Item is currently issued
- **Returned**: All items have been returned
- **Partial**: Some items returned, some still issued

### 3. Property Card Integration
Each issuance automatically creates a property card entry:
- Transaction Type: OUT
- Quantity: Issued amount
- Balance: Updated available quantity
- Reference: Form number
- Remarks: "Issued to: [Person Name]"

### 4. Filtering and Search
Filter issuances by:
- Office
- Status (Active, Returned, Partial)
- Form Type (PAR, ICS)

## Files Structure

```
app/
├── controllers/
│   └── IssuanceController.php  # Issuance management
├── models/
│   └── Issuance.php            # Issuance data and logic
└── views/
    └── issuances/
        ├── index.php           # Issuance list
        ├── create.php          # Create issuance
        ├── view.php            # Issuance details
        ├── print_par.php       # PAR template
        └── print_ics.php       # ICS template
```

## Key Functions

### IssuanceController
- `index()`: List all issuances with filters
- `create()`: Show issuance creation form
- `store()`: Process and save issuance
- `view($id)`: Display issuance details
- `printForm($id)`: Generate PAR or ICS form

### Issuance Model
- `createIssuance($data)`: Create new issuance with validations
- `getAllIssuances($filters)`: Retrieve issuances with filtering
- `getIssuanceById($id)`: Get single issuance details
- `updateStatus($id, $status)`: Change issuance status
- `getActiveIssuancesByItem($itemId)`: Get active issuances for an item

## Database Schema

### issuances Table
```sql
- id (Primary Key)
- item_id (Foreign Key to items)
- quantity
- issued_to (Person's name)
- office_id (Foreign Key to offices)
- issuance_date
- form_type (PAR or ICS, auto-determined)
- form_number (Unique identifier)
- ris_number (Optional)
- purpose
- status (Active, Returned, Partial)
- created_by (Foreign Key to users)
- created_at
- updated_at
```

## Business Rules

### 1. Quantity Validation
- Cannot issue more than available quantity
- System checks before creating issuance
- Error message if insufficient quantity

### 2. Form Type Rules
- PPE → PAR (cannot be changed)
- Semi-Expendable → ICS (cannot be changed)
- Form type is determined by item classification

### 3. Status Updates
- Status changes to "Partial" when some items returned
- Status changes to "Returned" when all items returned
- Active issuances can be viewed in item details

### 4. Property Card Updates
- Every issuance creates an OUT entry
- Balance is automatically calculated
- Reference number links to form number

## What to Configure Later

1. **Form Templates**: Replace placeholder PAR/ICS with official COA templates
2. **Digital Signatures**: Implement electronic signature capture
3. **Approval Workflow**: Add multi-level approval process
4. **Email Notifications**: Send notifications to recipients
5. **Barcode Scanning**: Implement barcode scanning for items
6. **Batch Issuance**: Issue multiple items in one transaction
7. **Recurring Issuances**: Set up recurring issuance schedules
8. **Mobile App**: Create mobile app for field issuances

## Usage Examples

### Creating an Issuance

**Example 1: PPE Item**
- Item: Desktop Computer (₱55,000)
- Quantity: 1
- Issued To: Juan Dela Cruz
- Office: Municipal Mayor Office
- Form Number: PAR-2024-001
- Result: System creates PAR form

**Example 2: Semi-Expendable Item**
- Item: Office Chair (₱3,500)
- Quantity: 5
- Issued To: Maria Santos
- Office: Municipal Treasurer Office
- Form Number: ICS-2024-001
- Result: System creates ICS form

### Viewing Issuance Details
1. Navigate to Issuances list
2. Click "View" on any issuance
3. See complete details including:
   - Item information
   - Recipient details
   - Form type and number
   - Return history (if any)
4. Click "Print" to generate official form

### Printing Forms
1. View issuance details
2. Click "Print PAR" or "Print ICS"
3. Official form opens in new window
4. Print or save as PDF

## Form Templates

### PAR (Property Acknowledgement Receipt)
Used for PPE items. Includes:
- Property details
- Recipient information
- Acknowledgement section
- Signature blocks

**TODO**: Replace with official COA PAR template

### ICS (Inventory Custodian Slip)
Used for Semi-Expendable items. Includes:
- Item details
- Custodian information
- Quantity and value
- Signature blocks

**TODO**: Replace with official COA ICS template

## Integration with Other Modules

### With Inventory Module
- Checks available quantity before issuance
- Updates available quantity after issuance
- Creates property card entries

### With Returns Module
- Returns reference the issuance
- Status updates based on returns
- Quantity reconciliation

### With Reports Module
- Issuance data feeds into reports
- Statistics for dashboard
- Audit trail for COA compliance

## Troubleshooting

### Cannot Create Issuance
- Check if item has available quantity
- Verify all required fields are filled
- Check database connection

### Wrong Form Type Generated
- Verify item classification
- Check unit value threshold (₱50,000)
- Review item details

### Status Not Updating
- Check if returns are being processed
- Verify return quantities match issuance
- Review return module integration

---

**Last Updated**: <?php echo date('F d, Y'); ?>
