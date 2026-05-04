# Returns Module Documentation

## Purpose
The Returns module handles the return of issued items, assesses their condition, and determines appropriate actions. It maintains property card records and updates inventory quantities accordingly.

## How It Works

### Return Process
1. User selects an active issuance
2. Enters return quantity and date
3. Assesses item condition (Good, Fair, Damaged)
4. Selects action to take
5. System processes return and updates:
   - Issuance status
   - Item quantity (if applicable)
   - Property card
   - Unserviceable records (if applicable)

### Condition Assessment
- **Good**: Item is in excellent condition
- **Fair**: Item shows normal wear but is functional
- **Damaged**: Item is broken or non-functional

### Action Options
1. **Return to Storage**: Add back to available inventory
2. **Transfer to Another Office**: Move to different office
3. **Mark as Unserviceable**: Flag as unusable

## Features

### 1. Return Recording
**Required Fields**:
- Issuance (dropdown of active issuances)
- Quantity
- Return Date
- Condition Status
- Action Taken

**Conditional Fields**:
- Transfer Office (required if action is "Transfer")
- Remarks (optional)

### 2. Business Rules

#### Rule 1: Unserviceable Items Cannot Be Transferred
- If condition is "Damaged" and action is "Transfer", system rejects
- Damaged items must be returned to storage or marked unserviceable
- Validation occurs before processing

#### Rule 2: Quantity Validation
- Cannot return more than issued quantity
- System tracks total returned vs issued
- Prevents over-returning

#### Rule 3: Status Updates
- Issuance status updates based on returns:
  - All returned → "Returned"
  - Partial return → "Partial"
  - None returned → "Active"

### 3. Property Card Integration
Each return creates a property card entry:
- Transaction Type: IN (for returns to storage)
- Quantity: Returned amount
- Balance: Updated available quantity
- Reference: RETURN-[ID] or UNSERV-[ID]
- Remarks: Includes condition and action

## Files Structure

```
app/
├── controllers/
│   └── ReturnController.php    # Return management
├── models/
│   └── ReturnModel.php         # Return data and logic
└── views/
    └── returns/
        ├── index.php           # Returns list
        ├── create.php          # Process return
        └── view.php            # Return details
```

## Key Functions

### ReturnController
- `index()`: List all returns
- `create()`: Show return processing form
- `store()`: Process and save return
- `view($id)`: Display return details

### ReturnModel
- `createReturn($data)`: Process return with validations
- `getAllReturns($filters)`: Retrieve returns with filtering
- `getReturnById($id)`: Get single return details
- `getReturnsByIssuance($issuanceId)`: Get returns for an issuance
- `getTotalReturnedQuantity($issuanceId)`: Calculate total returned

## Database Schema

### returns Table
```sql
- id (Primary Key)
- issuance_id (Foreign Key to issuances)
- item_id (Foreign Key to items)
- quantity
- return_date
- condition_status (Good, Fair, Damaged)
- action_taken (Return to Storage, Transfer, Mark as Unserviceable)
- transfer_office_id (Foreign Key to offices, nullable)
- remarks
- created_by (Foreign Key to users)
- created_at
```

### unserviceable_items Table
```sql
- id (Primary Key)
- item_id (Foreign Key to items)
- quantity
- reason
- date_marked
- marked_by (Foreign Key to users)
- remarks
- created_at
```

## Action Processing

### Action 1: Return to Storage
**Process**:
1. Add quantity back to available inventory
2. Update item's available_quantity
3. Create property card IN entry
4. Update issuance status

**Example**:
- Issued: 5 chairs
- Returned: 5 chairs (Good condition)
- Action: Return to Storage
- Result: Available quantity increases by 5

### Action 2: Transfer to Another Office
**Process**:
1. Add quantity back to available inventory
2. Create property card IN entry
3. Mark for transfer (future: auto-create transfer issuance)
4. Update issuance status

**Restriction**: Cannot transfer damaged items

**Example**:
- Issued: 3 computers to Office A
- Returned: 3 computers (Good condition)
- Action: Transfer to Office B
- Result: Available for re-issuance to Office B

### Action 3: Mark as Unserviceable
**Process**:
1. Update item status to "Unserviceable"
2. Create unserviceable_items record
3. Create property card IN entry
4. Update issuance status
5. Item cannot be issued again

**Example**:
- Issued: 1 printer
- Returned: 1 printer (Damaged)
- Action: Mark as Unserviceable
- Result: Item flagged, tracked separately

## What to Configure Later

1. **Repair Workflow**: Add repair tracking for damaged items
2. **Disposal Process**: Implement formal disposal procedures
3. **Transfer Automation**: Auto-create issuance for transfers
4. **Photo Documentation**: Upload photos of damaged items
5. **Approval Workflow**: Require approval for unserviceable marking
6. **Warranty Tracking**: Track items under warranty
7. **Replacement Requests**: Link returns to procurement requests
8. **Condition Grading**: More detailed condition assessment

## Usage Examples

### Example 1: Good Condition Return
**Scenario**: Office chair returned in good condition
- Issuance: ICS-2024-001 (5 chairs)
- Return Quantity: 5
- Condition: Good
- Action: Return to Storage
- Result: 5 chairs added back to inventory

### Example 2: Damaged Item
**Scenario**: Computer returned damaged
- Issuance: PAR-2024-001 (1 computer)
- Return Quantity: 1
- Condition: Damaged
- Action: Mark as Unserviceable
- Result: Computer marked unserviceable, tracked separately

### Example 3: Partial Return
**Scenario**: 3 of 5 items returned
- Issuance: ICS-2024-002 (5 items)
- Return Quantity: 3
- Condition: Good
- Action: Return to Storage
- Result: Issuance status = "Partial", 3 items back in inventory

### Example 4: Transfer Attempt (Rejected)
**Scenario**: Trying to transfer damaged item
- Condition: Damaged
- Action: Transfer to Another Office
- Result: System rejects with error message

## Integration with Other Modules

### With Issuance Module
- References active issuances
- Updates issuance status
- Tracks return quantities

### With Inventory Module
- Updates available quantities
- Creates property card entries
- Updates item status

### With Reports Module
- Return data for reports
- Unserviceable item tracking
- Condition statistics

## Validation Rules

1. **Quantity Check**: Return quantity ≤ Issued quantity
2. **Condition-Action Validation**: Damaged items cannot be transferred
3. **Active Issuance**: Can only return from active/partial issuances
4. **Date Validation**: Return date ≥ Issuance date
5. **Office Validation**: Transfer office must exist and be different

## Troubleshooting

### Cannot Process Return
- Verify issuance is active or partial
- Check return quantity is valid
- Ensure condition and action are compatible

### Quantity Not Updating
- Check action taken
- Verify property card entry created
- Review item available_quantity

### Transfer Not Working
- Verify item condition is not "Damaged"
- Check transfer office is selected
- Review validation rules

### Unserviceable Not Flagging
- Check if action is "Mark as Unserviceable"
- Verify unserviceable_items record created
- Review item status update

---

**Last Updated**: <?php echo date('F d, Y'); ?>
