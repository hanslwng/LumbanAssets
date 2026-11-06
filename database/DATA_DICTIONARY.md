# LGU Inventory System - Data Dictionary

**Database Name:** `lgu_inventory`  
**Generated:** October 29, 2026  
**Server Version:** MariaDB 10.4.32  

---

## Table of Contents

1. [Consumable Items Management](#1-consumable-items-management)
2. [Property/Asset Management](#2-propertyasset-management)
3. [Issuance & Distribution](#3-issuance--distribution)
4. [Procurement Process](#4-procurement-process)
5. [Users & Authentication](#5-users--authentication)
6. [Notifications](#6-notifications)
7. [System & Configuration](#7-system--configuration)
8. [Forecasting & Analytics](#8-forecasting--analytics)
9. [Views](#9-views)

---

## 1. Consumable Items Management

### 1.1 `consumable_items`
Master table for consumable items (office supplies, medical supplies, etc.)

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key, unique item identifier |
| `name` | VARCHAR(255) | NO | - | Display name of the consumable item |
| `name_key` | VARCHAR(255) | NO | - | Normalized lowercase name for matching/searching |
| `unit` | VARCHAR(40) | YES | NULL | Unit of measurement (pc, box, ream, bottle, etc.) |
| `aliases` | TEXT | YES | NULL | Alternative names for the item (JSON or comma-separated) |
| `is_active` | TINYINT(1) | NO | 1 | Status flag (1=active, 0=inactive/archived) |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Record creation timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE KEY `name_key` (`name_key`)
- KEY `idx_active` (`is_active`)

---

### 1.2 `consumable_stock`
Current stock levels for consumable items

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `item_id` | INT(11) | NO | - | Foreign key to `consumable_items.id` |
| `current_stock` | DECIMAL(14,4) | NO | 0.0000 | Current available stock quantity |
| `updated_at` | TIMESTAMP | NO | current_timestamp() ON UPDATE current_timestamp() | Last stock update timestamp |

**Indexes:**
- PRIMARY KEY (`item_id`)
- FOREIGN KEY (`item_id`) REFERENCES `consumable_items` (`id`) ON DELETE CASCADE

**Notes:**
- Maintains real-time inventory levels
- Updated automatically via triggers on `consumable_stock_moves`

---

### 1.3 `consumable_stock_moves`
Transaction log for all consumable stock movements

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key, unique move identifier |
| `item_id` | INT(11) | NO | - | Foreign key to `consumable_items.id` |
| `move_type` | ENUM | NO | - | Type: 'starting', 'restock', 'release', 'adjust' |
| `quantity` | DECIMAL(14,4) | NO | - | Quantity moved (positive=increase, contextual by type) |
| `unit_cost` | DECIMAL(14,4) | YES | NULL | Cost per unit at time of transaction |
| `office_id` | INT(11) | YES | NULL | Foreign key to `offices.id` (destination/source office) |
| `proc_pr_id` | INT(11) | YES | NULL | Foreign key to `proc_pr.id` (related purchase request) |
| `proc_pr_line_id` | INT(11) | YES | NULL | Foreign key to `proc_pr_line.id` (specific PR line item) |
| `issuance_id` | INT(11) | YES | NULL | Foreign key to `issuance_records.id` (for releases) |
| `note` | VARCHAR(255) | YES | NULL | Additional notes or reason for move |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Transaction timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `idx_item` (`item_id`)
- KEY `idx_type` (`move_type`)
- KEY `idx_office` (`office_id`)
- KEY `idx_created` (`created_at`)

**Move Type Values:**
- `starting` - Initial stock entry
- `restock` - Stock replenishment (increases inventory)
- `release` - Stock issued to offices (decreases inventory)
- `adjust` - Manual adjustment (reconciliation, corrections)

---

## 2. Property/Asset Management

### 2.1 `property_items`
Master table for all property and equipment assets

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key, unique property identifier |
| `property_number` | VARCHAR(50) | NO | - | Official property/asset number |
| `category` | VARCHAR(100) | YES | NULL | Category/classification of property |
| `article` | VARCHAR(255) | YES | NULL | General article name or type |
| `description` | TEXT | YES | NULL | Detailed description of the property |
| `model` | VARCHAR(255) | YES | NULL | Model number or name |
| `serial_number` | VARCHAR(255) | YES | NULL | Serial number |
| `quantity` | INT(11) | NO | 1 | Quantity (typically 1 for tracked assets) |
| `unit` | VARCHAR(40) | YES | NULL | Unit of measurement |
| `unit_value` | DECIMAL(14,2) | YES | NULL | Value per unit |
| `total_value` | DECIMAL(14,2) | YES | NULL | Total value (quantity × unit_value) |
| `date_acquired` | DATE | YES | NULL | Acquisition/purchase date |
| `acquisition_mode` | VARCHAR(100) | YES | NULL | How acquired (purchase, donation, transfer, etc.) |
| `status` | ENUM | NO | 'active' | Status: 'active', 'issued', 'disposed', 'under_repair' |
| `condition_status` | VARCHAR(50) | YES | NULL | Physical condition (good, fair, poor, etc.) |
| `life_span` | INT(11) | YES | NULL | Expected useful life in years |
| `estimated_useful_life` | INT(11) | YES | NULL | Remaining useful life |
| `remarks` | TEXT | YES | NULL | Additional notes |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Record creation timestamp |
| `updated_at` | TIMESTAMP | NO | current_timestamp() ON UPDATE current_timestamp() | Last update timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE KEY `property_number` (`property_number`)
- KEY `idx_status` (`status`)
- KEY `idx_category` (`category`)
- KEY `idx_date_acquired` (`date_acquired`)

---

### 2.2 `property_tags`
QR code tags and office assignment for property items

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `property_item_id` | INT(11) | NO | - | Foreign key to `property_items.id` |
| `office_division` | VARCHAR(180) | NO | '' | Assigned office/division |
| `office_location` | VARCHAR(180) | YES | NULL | Physical location within office |
| `person_accountable` | VARCHAR(255) | YES | NULL | Name of person responsible |
| `qr_code_data` | TEXT | YES | NULL | QR code content/data |
| `qr_code_image` | LONGTEXT | YES | NULL | Base64 encoded QR code image |
| `tag_generated_at` | TIMESTAMP | YES | NULL | When QR tag was generated |
| `tag_printed_at` | TIMESTAMP | YES | NULL | When QR tag was printed |

**Indexes:**
- PRIMARY KEY (`property_item_id`)
- KEY `idx_office` (`office_division`)
- FOREIGN KEY (`property_item_id`) REFERENCES `property_items` (`id`) ON DELETE CASCADE

---

### 2.3 `inventory_history`
Audit trail for property item changes

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `property_item_id` | INT(11) | NO | - | Foreign key to `property_items.id` |
| `action` | VARCHAR(50) | NO | - | Action type (created, updated, issued, returned, disposed) |
| `old_values` | TEXT | YES | NULL | JSON of previous values |
| `new_values` | TEXT | YES | NULL | JSON of new values |
| `changed_by` | INT(11) | YES | NULL | Foreign key to `users.id` |
| `changed_at` | TIMESTAMP | NO | current_timestamp() | Timestamp of change |
| `remarks` | TEXT | YES | NULL | Additional notes about the change |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `idx_property` (`property_item_id`)
- KEY `idx_action` (`action`)
- KEY `idx_changed_at` (`changed_at`)

---

### 2.4 `unserviceable_records`
Records for damaged or unserviceable property

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `property_item_id` | INT(11) | NO | - | Foreign key to `property_items.id` |
| `marked_date` | DATE | NO | - | Date marked as unserviceable |
| `reason` | TEXT | YES | NULL | Reason for marking unserviceable |
| `condition_description` | TEXT | YES | NULL | Detailed condition description |
| `appraised_value` | DECIMAL(14,2) | YES | NULL | Current appraised value |
| `disposal_method` | VARCHAR(100) | YES | NULL | Planned/actual disposal method |
| `marked_by` | INT(11) | YES | NULL | Foreign key to `users.id` |
| `approved_by` | INT(11) | YES | NULL | Foreign key to `users.id` (approver) |
| `status` | ENUM | NO | 'pending' | Status: 'pending', 'approved', 'disposed' |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Record creation timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `idx_property` (`property_item_id`)
- KEY `idx_status` (`status`)
- KEY `idx_marked_date` (`marked_date`)

---

### 2.5 `disposal_records`
Final disposal records for unserviceable items

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `unserviceable_id` | INT(11) | NO | - | Foreign key to `unserviceable_records.id` |
| `disposal_date` | DATE | NO | - | Actual disposal date |
| `disposal_method` | VARCHAR(100) | NO | - | Method used (auction, donation, destruction, etc.) |
| `disposal_value` | DECIMAL(14,2) | YES | NULL | Value recovered (if any) |
| `buyer_recipient` | VARCHAR(255) | YES | NULL | Buyer or recipient name |
| `document_reference` | VARCHAR(255) | YES | NULL | Reference to disposal documents |
| `disposed_by` | INT(11) | YES | NULL | Foreign key to `users.id` |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Record creation timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `idx_unserviceable` (`unserviceable_id`)
- KEY `idx_disposal_date` (`disposal_date`)

---

## 3. Issuance & Distribution

### 3.1 `issuance_records`
Master records for property and equipment issuance

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `form_type` | ENUM | NO | - | 'PAR' (Property Acknowledgement Receipt) or 'ICS' (Inventory Custodian Slip) |
| `par_ics_number` | VARCHAR(100) | YES | NULL | PAR/ICS reference number |
| `office_id` | INT(11) | NO | - | Foreign key to `offices.id` (receiving office) |
| `issued_to` | VARCHAR(255) | YES | NULL | Name of recipient/accountable person |
| `position` | VARCHAR(255) | YES | NULL | Position/title of recipient |
| `fund_cluster` | VARCHAR(100) | YES | NULL | Funding source/cluster |
| `issued_date` | DATE | YES | NULL | Date of issuance |
| `issued_by` | INT(11) | YES | NULL | Foreign key to `users.id` (issuer) |
| `received_by` | VARCHAR(255) | YES | NULL | Name of person who received |
| `status` | ENUM | NO | 'draft' | Status: 'draft', 'issued', 'returned', 'cancelled' |
| `notes` | TEXT | YES | NULL | Additional notes |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Record creation timestamp |
| `updated_at` | TIMESTAMP | NO | current_timestamp() ON UPDATE current_timestamp() | Last update timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `idx_form_type` (`form_type`)
- KEY `idx_office` (`office_id`)
- KEY `idx_status` (`status`)
- KEY `idx_issued_date` (`issued_date`)

**Form Types:**
- **PAR (Property Acknowledgement Receipt)** - For property/equipment with property numbers
- **ICS (Inventory Custodian Slip)** - For semi-expendable items without property numbers

---

### 3.2 `issuance_items`
Line items for property/equipment issued (PAR/ICS)

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `issuance_id` | INT(11) | NO | - | Foreign key to `issuance_records.id` |
| `property_item_id` | INT(11) | NO | - | Foreign key to `property_items.id` |
| `quantity` | INT(11) | NO | 1 | Quantity issued |
| `unit_value` | DECIMAL(14,2) | YES | NULL | Value per unit at time of issuance |
| `total_value` | DECIMAL(14,2) | YES | NULL | Total value (quantity × unit_value) |
| `remarks` | TEXT | YES | NULL | Item-specific notes |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Record creation timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `idx_issuance` (`issuance_id`)
- KEY `idx_property` (`property_item_id`)
- FOREIGN KEY (`issuance_id`) REFERENCES `issuance_records` (`id`) ON DELETE CASCADE

---

### 3.3 `issuance_consumable_lines`
Line items for consumable items issued

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `issuance_id` | INT(11) | NO | - | Foreign key to `issuance_records.id` |
| `item_id` | INT(11) | NO | - | Foreign key to `consumable_items.id` |
| `quantity` | DECIMAL(14,4) | NO | - | Quantity issued |
| `unit_cost` | DECIMAL(14,2) | YES | NULL | Cost per unit |
| `total_cost` | DECIMAL(14,2) | YES | NULL | Total cost (quantity × unit_cost) |
| `stock_move_id` | INT(11) | YES | NULL | Foreign key to `consumable_stock_moves.id` |
| `remarks` | TEXT | YES | NULL | Item-specific notes |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Record creation timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `idx_issuance` (`issuance_id`)
- KEY `idx_item` (`item_id`)
- FOREIGN KEY (`issuance_id`) REFERENCES `issuance_records` (`id`) ON DELETE CASCADE

---

### 3.4 `return_records`
Records for returned property/equipment

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `issuance_id` | INT(11) | NO | - | Foreign key to `issuance_records.id` (original issuance) |
| `return_date` | DATE | NO | - | Date of return |
| `returned_by` | VARCHAR(255) | YES | NULL | Name of person returning |
| `received_by` | INT(11) | YES | NULL | Foreign key to `users.id` (receiver) |
| `condition_on_return` | TEXT | YES | NULL | Condition description at return |
| `reason` | TEXT | YES | NULL | Reason for return |
| `remarks` | TEXT | YES | NULL | Additional notes |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Record creation timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `idx_issuance` (`issuance_id`)
- KEY `idx_return_date` (`return_date`)

---

## 4. Procurement Process

### 4.1 `proc_pr` (Purchase Request)
Master table for purchase requests

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `pr_number` | VARCHAR(80) | NO | - | Purchase request number |
| `purpose` | TEXT | YES | NULL | Purpose/justification for purchase |
| `office_id` | INT(11) | NO | - | Foreign key to `offices.id` (requesting office) |
| `requested_by` | INT(11) | YES | NULL | Foreign key to `users.id` (requester) |
| `approved_by` | INT(11) | YES | NULL | Foreign key to `users.id` (approver) |
| `sai_number` | VARCHAR(80) | YES | NULL | Supply and Services Inventory number |
| `sai_date` | DATE | YES | NULL | SAI date |
| `alobs_number` | VARCHAR(80) | YES | NULL | Allotment/Obligation Slip number |
| `alobs_date` | DATE | YES | NULL | ALOBS date |
| `total_cost` | DECIMAL(14,2) | YES | NULL | Total estimated cost |
| `status` | ENUM | NO | 'draft' | Status: 'draft', 'submitted', 'approved', 'cancelled', 'completed' |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Record creation timestamp |
| `updated_at` | TIMESTAMP | NO | current_timestamp() ON UPDATE current_timestamp() | Last update timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE KEY `pr_number` (`pr_number`)
- KEY `idx_office` (`office_id`)
- KEY `idx_status` (`status`)

---

### 4.2 `proc_pr_line` (Purchase Request Lines)
Line items in purchase requests

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `pr_id` | INT(11) | NO | - | Foreign key to `proc_pr.id` |
| `item_type` | ENUM | NO | - | 'consumable' or 'property' |
| `item_id` | INT(11) | YES | NULL | Foreign key to consumable_items or property_items |
| `stock_number` | VARCHAR(100) | YES | NULL | Stock/item number |
| `description` | TEXT | YES | NULL | Item description |
| `unit` | VARCHAR(40) | YES | NULL | Unit of measurement |
| `quantity` | DECIMAL(14,4) | NO | - | Quantity requested |
| `unit_cost` | DECIMAL(14,2) | YES | NULL | Estimated unit cost |
| `total_cost` | DECIMAL(14,2) | YES | NULL | Total cost (quantity × unit_cost) |
| `purpose` | TEXT | YES | NULL | Specific purpose for this item |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Record creation timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `idx_pr` (`pr_id`)
- KEY `idx_item_type` (`item_type`)
- FOREIGN KEY (`pr_id`) REFERENCES `proc_pr` (`id`) ON DELETE CASCADE

---

### 4.3 `proc_quotation` (Supplier Quotations)
Supplier quotations for purchase requests

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `pr_id` | INT(11) | NO | - | Foreign key to `proc_pr.id` |
| `supplier_name` | VARCHAR(255) | NO | - | Name of supplier |
| `supplier_address` | TEXT | YES | NULL | Supplier address |
| `supplier_contact` | VARCHAR(255) | YES | NULL | Contact information |
| `tin` | VARCHAR(50) | YES | NULL | Tax Identification Number |
| `quotation_date` | DATE | YES | NULL | Date of quotation |
| `validity_date` | DATE | YES | NULL | Quotation validity date |
| `payment_terms` | VARCHAR(255) | YES | NULL | Payment terms |
| `delivery_terms` | VARCHAR(255) | YES | NULL | Delivery terms |
| `total_amount` | DECIMAL(14,2) | YES | NULL | Total quoted amount |
| `is_selected` | TINYINT(1) | NO | 0 | Whether this quotation was selected (1=yes, 0=no) |
| `remarks` | TEXT | YES | NULL | Additional notes |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Record creation timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `idx_pr` (`pr_id`)
- KEY `idx_supplier` (`supplier_name`)

---

### 4.4 `proc_quotation_line` (Quotation Line Items)
Line items in supplier quotations

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `quotation_id` | INT(11) | NO | - | Foreign key to `proc_quotation.id` |
| `pr_line_id` | INT(11) | NO | - | Foreign key to `proc_pr_line.id` |
| `unit_price` | DECIMAL(14,2) | YES | NULL | Quoted unit price |
| `total_price` | DECIMAL(14,2) | YES | NULL | Total price for this line |
| `brand_model` | VARCHAR(255) | YES | NULL | Brand/model offered |
| `specifications` | TEXT | YES | NULL | Item specifications |
| `remarks` | TEXT | YES | NULL | Additional notes |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `idx_quotation` (`quotation_id`)
- KEY `idx_pr_line` (`pr_line_id`)
- FOREIGN KEY (`quotation_id`) REFERENCES `proc_quotation` (`id`) ON DELETE CASCADE

---

### 4.5 `proc_po` (Purchase Order)
Purchase orders issued to suppliers

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `pr_id` | INT(11) | NO | - | Foreign key to `proc_pr.id` |
| `po_number` | VARCHAR(80) | NO | - | Purchase order number |
| `quotation_id` | INT(11) | YES | NULL | Foreign key to `proc_quotation.id` (winning quotation) |
| `supplier_name` | VARCHAR(255) | NO | - | Supplier name |
| `supplier_address` | TEXT | YES | NULL | Supplier address |
| `supplier_tin` | VARCHAR(50) | YES | NULL | Supplier TIN |
| `po_date` | DATE | YES | NULL | Purchase order date |
| `delivery_place` | VARCHAR(255) | YES | NULL | Delivery location |
| `delivery_date` | DATE | YES | NULL | Expected delivery date |
| `delivery_term` | VARCHAR(255) | YES | NULL | Delivery terms |
| `payment_term` | VARCHAR(255) | YES | NULL | Payment terms |
| `total_amount` | DECIMAL(14,2) | YES | NULL | Total PO amount |
| `funds_available` | VARCHAR(255) | YES | NULL | Funds certification |
| `status` | ENUM | NO | 'draft' | Status: 'draft', 'issued', 'partially_received', 'completed', 'cancelled' |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Record creation timestamp |
| `updated_at` | TIMESTAMP | NO | current_timestamp() ON UPDATE current_timestamp() | Last update timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE KEY `po_number` (`po_number`)
- KEY `idx_pr` (`pr_id`)
- KEY `idx_status` (`status`)

---

### 4.6 `proc_po_line` (Purchase Order Lines)
Line items in purchase orders

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `po_id` | INT(11) | NO | - | Foreign key to `proc_po.id` |
| `pr_line_id` | INT(11) | YES | NULL | Foreign key to `proc_pr_line.id` |
| `stock_number` | VARCHAR(100) | YES | NULL | Stock/item number |
| `description` | TEXT | YES | NULL | Item description |
| `unit` | VARCHAR(40) | YES | NULL | Unit of measurement |
| `quantity` | DECIMAL(14,4) | NO | - | Quantity ordered |
| `unit_price` | DECIMAL(14,2) | YES | NULL | Unit price |
| `total_price` | DECIMAL(14,2) | YES | NULL | Total price (quantity × unit_price) |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Record creation timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `idx_po` (`po_id`)
- KEY `idx_pr_line` (`pr_line_id`)
- FOREIGN KEY (`po_id`) REFERENCES `proc_po` (`id`) ON DELETE CASCADE

---

### 4.7 `proc_air` (Acceptance and Inspection Report)
Inspection reports for received items

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `po_id` | INT(11) | NO | - | Foreign key to `proc_po.id` |
| `air_number` | VARCHAR(80) | YES | NULL | AIR reference number |
| `inspection_date` | DATE | YES | NULL | Date of inspection |
| `inspected_by` | INT(11) | YES | NULL | Foreign key to `users.id` (inspector) |
| `invoice_number` | VARCHAR(100) | YES | NULL | Supplier invoice number |
| `invoice_date` | DATE | YES | NULL | Invoice date |
| `invoice_amount` | DECIMAL(14,2) | YES | NULL | Invoice amount |
| `delivery_receipt_number` | VARCHAR(100) | YES | NULL | Delivery receipt number |
| `delivery_date` | DATE | YES | NULL | Actual delivery date |
| `inspection_result` | ENUM | YES | NULL | Result: 'accepted', 'rejected', 'partial' |
| `remarks` | TEXT | YES | NULL | Inspection remarks |
| `status` | ENUM | NO | 'draft' | Status: 'draft', 'completed', 'cancelled' |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Record creation timestamp |
| `updated_at` | TIMESTAMP | NO | current_timestamp() ON UPDATE current_timestamp() | Last update timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `idx_po` (`po_id`)
- KEY `idx_status` (`status`)

---

### 4.8 `proc_air_line` (AIR Line Items)
Line items in acceptance and inspection reports

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `air_id` | INT(11) | NO | - | Foreign key to `proc_air.id` |
| `po_line_id` | INT(11) | YES | NULL | Foreign key to `proc_po_line.id` |
| `description` | TEXT | YES | NULL | Item description |
| `unit` | VARCHAR(40) | YES | NULL | Unit of measurement |
| `quantity_ordered` | DECIMAL(14,4) | YES | NULL | Quantity ordered |
| `quantity_delivered` | DECIMAL(14,4) | YES | NULL | Quantity actually delivered |
| `quantity_accepted` | DECIMAL(14,4) | YES | NULL | Quantity accepted after inspection |
| `quantity_rejected` | DECIMAL(14,4) | YES | NULL | Quantity rejected |
| `unit_price` | DECIMAL(14,2) | YES | NULL | Unit price |
| `total_amount` | DECIMAL(14,2) | YES | NULL | Total amount accepted |
| `acceptance_status` | ENUM | YES | NULL | Status: 'accepted', 'rejected', 'partial' |
| `remarks` | TEXT | YES | NULL | Item-specific remarks |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Record creation timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `idx_air` (`air_id`)
- KEY `idx_po_line` (`po_line_id`)
- FOREIGN KEY (`air_id`) REFERENCES `proc_air` (`id`) ON DELETE CASCADE

---

## 5. Users & Authentication

### 5.1 `users`
User accounts and authentication

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `username` | VARCHAR(100) | NO | - | Unique username |
| `email` | VARCHAR(255) | NO | - | Email address |
| `password` | VARCHAR(255) | NO | - | Hashed password |
| `full_name` | VARCHAR(255) | YES | NULL | Full name of user |
| `role` | ENUM | NO | 'staff' | Role: 'admin', 'gso_head', 'gso_staff', 'procurement', 'office_head', 'staff' |
| `office_id` | INT(11) | YES | NULL | Foreign key to `offices.id` (assigned office) |
| `position` | VARCHAR(255) | YES | NULL | Job position/title |
| `is_active` | TINYINT(1) | NO | 1 | Account status (1=active, 0=inactive) |
| `last_login` | TIMESTAMP | YES | NULL | Last login timestamp |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Account creation timestamp |
| `updated_at` | TIMESTAMP | NO | current_timestamp() ON UPDATE current_timestamp() | Last update timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE KEY `username` (`username`)
- UNIQUE KEY `email` (`email`)
- KEY `idx_role` (`role`)
- KEY `idx_office` (`office_id`)

**User Roles:**
- `admin` - System administrator (full access)
- `gso_head` - General Services Office head
- `gso_staff` - GSO staff member
- `procurement` - Procurement officer
- `office_head` - Department/office head
- `staff` - Regular staff member

---

### 5.2 `user_settings`
User-specific notification preferences

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `user_id` | INT(11) | NO | - | Foreign key to `users.id` |
| `notify_inapp` | TINYINT(1) | NO | 1 | Enable in-app notifications (1=yes, 0=no) |
| `notify_email` | TINYINT(1) | NO | 0 | Enable email notifications (1=yes, 0=no) |
| `updated_at` | TIMESTAMP | NO | current_timestamp() ON UPDATE current_timestamp() | Last update timestamp |

**Indexes:**
- PRIMARY KEY (`user_id`)
- FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE

---

### 5.3 `user_activity`
User activity and audit log

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `user_id` | INT(11) | YES | NULL | Foreign key to `users.id` (NULL for anonymous) |
| `activity_type` | VARCHAR(50) | NO | - | Type of activity (login, logout, create, update, delete, view) |
| `table_name` | VARCHAR(100) | YES | NULL | Table affected (if applicable) |
| `record_id` | INT(11) | YES | NULL | ID of record affected |
| `description` | TEXT | YES | NULL | Activity description |
| `ip_address` | VARCHAR(45) | YES | NULL | IP address of user |
| `user_agent` | TEXT | YES | NULL | Browser/client user agent |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Activity timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `idx_user` (`user_id`)
- KEY `idx_type` (`activity_type`)
- KEY `idx_created` (`created_at`)

---

### 5.4 `registration_codes`
Email verification codes for new user registration

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `email` | VARCHAR(255) | NO | - | Email address for registration |
| `code` | VARCHAR(10) | NO | - | Verification code |
| `expires_at` | TIMESTAMP | NO | - | Code expiration timestamp |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Code generation timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `idx_email` (`email`)
- KEY `idx_code` (`code`)

---

### 5.5 `registration_daily`
Rate limiting for registration attempts

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `email` | VARCHAR(255) | NO | - | Email address |
| `attempt_date` | DATE | NO | - | Date of attempts |
| `attempt_count` | INT(11) | NO | 0 | Number of attempts |

**Indexes:**
- PRIMARY KEY (`email`, `attempt_date`)

---

### 5.6 `password_reset_codes`
Password reset verification codes

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `user_id` | INT(11) | NO | - | Foreign key to `users.id` |
| `code` | VARCHAR(10) | NO | - | Reset code |
| `expires_at` | TIMESTAMP | NO | - | Code expiration timestamp |
| `used` | TINYINT(1) | NO | 0 | Whether code has been used (1=yes, 0=no) |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Code generation timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `idx_user` (`user_id`)
- KEY `idx_code` (`code`)

---

### 5.7 `password_reset_daily`
Rate limiting for password reset attempts

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `email` | VARCHAR(255) | NO | - | Email address |
| `attempt_date` | DATE | NO | - | Date of attempts |
| `attempt_count` | INT(11) | NO | 0 | Number of attempts |

**Indexes:**
- PRIMARY KEY (`email`, `attempt_date`)

---

## 6. Notifications

### 6.1 `notifications`
System notifications

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `type` | VARCHAR(50) | NO | - | Notification type (pr_approved, stock_low, item_returned, etc.) |
| `title` | VARCHAR(255) | NO | - | Notification title |
| `message` | TEXT | YES | NULL | Notification message content |
| `related_table` | VARCHAR(100) | YES | NULL | Related table name |
| `related_id` | INT(11) | YES | NULL | Related record ID |
| `priority` | ENUM | NO | 'normal' | Priority: 'low', 'normal', 'high', 'urgent' |
| `target_role` | VARCHAR(50) | YES | NULL | Target user role (NULL=all users) |
| `target_user_id` | INT(11) | YES | NULL | Specific target user (NULL=role-based) |
| `target_office_id` | INT(11) | YES | NULL | Target office (NULL=all offices) |
| `action_url` | VARCHAR(255) | YES | NULL | URL for notification action |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Notification creation timestamp |
| `expires_at` | TIMESTAMP | YES | NULL | Notification expiration |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `idx_type` (`type`)
- KEY `idx_target_role` (`target_role`)
- KEY `idx_target_user` (`target_user_id`)
- KEY `idx_created` (`created_at`)

---

### 6.2 `notification_reads`
Tracks which users have read notifications

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `user_id` | INT(11) | NO | - | Foreign key to `users.id` |
| `notification_id` | INT(11) | NO | - | Foreign key to `notifications.id` |
| `read_at` | TIMESTAMP | NO | current_timestamp() | Timestamp when notification was read |

**Indexes:**
- PRIMARY KEY (`user_id`, `notification_id`)
- KEY `idx_notification` (`notification_id`)
- FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
- FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE

---

## 7. System & Configuration

### 7.1 `offices`
Organizational units/departments

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `name` | VARCHAR(255) | NO | - | Office/department name |
| `code` | VARCHAR(50) | YES | NULL | Office code/abbreviation |
| `parent_office_id` | INT(11) | YES | NULL | Parent office (for hierarchical structure) |
| `office_head` | VARCHAR(255) | YES | NULL | Name of office head |
| `location` | VARCHAR(255) | YES | NULL | Physical location |
| `contact_number` | VARCHAR(50) | YES | NULL | Contact number |
| `email` | VARCHAR(255) | YES | NULL | Office email |
| `is_active` | TINYINT(1) | NO | 1 | Status (1=active, 0=inactive) |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Record creation timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE KEY `name` (`name`)
- KEY `idx_parent` (`parent_office_id`)

---

### 7.2 `system_settings`
System-wide configuration settings

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `setting_key` | VARCHAR(100) | NO | - | Setting key/name |
| `setting_value` | TEXT | YES | NULL | Setting value (can be JSON) |
| `setting_type` | VARCHAR(50) | NO | 'string' | Data type: 'string', 'number', 'boolean', 'json' |
| `description` | TEXT | YES | NULL | Setting description |
| `is_public` | TINYINT(1) | NO | 0 | Whether setting is visible to non-admins (1=yes, 0=no) |
| `updated_at` | TIMESTAMP | NO | current_timestamp() ON UPDATE current_timestamp() | Last update timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE KEY `setting_key` (`setting_key`)

**Common Settings:**
- `system_name` - Organization/system name
- `reorder_threshold` - Low stock alert threshold
- `fiscal_year` - Current fiscal year
- `email_notifications_enabled` - Global email notification toggle

---

## 8. Forecasting & Analytics

### 8.1 `demo_demand_history`
Historical demand data for forecasting

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `office_id` | INT(11) | NO | - | Foreign key to `offices.id` |
| `item_id` | INT(11) | NO | - | Foreign key to `consumable_items.id` |
| `year` | INT(11) | NO | - | Year of demand |
| `month` | INT(11) | NO | - | Month of demand (1-12) |
| `quantity` | DECIMAL(14,4) | NO | - | Quantity demanded |
| `created_at` | TIMESTAMP | NO | current_timestamp() | Record creation timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `idx_office_item` (`office_id`, `item_id`)
- KEY `idx_year_month` (`year`, `month`)

**Notes:**
- Aggregates historical issuance data
- Used for demand forecasting algorithms

---

### 8.2 `demo_forecast_cache`
Cached forecast results

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | INT(11) | NO | AUTO_INCREMENT | Primary key |
| `payload` | LONGTEXT | NO | - | JSON forecast data |
| `forecast_type` | VARCHAR(50) | YES | NULL | Type of forecast (linear, exponential, ml, etc.) |
| `generated_at` | TIMESTAMP | NO | current_timestamp() | Cache generation timestamp |
| `valid_until` | TIMESTAMP | YES | NULL | Cache expiration timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `idx_type` (`forecast_type`)

**Notes:**
- Stores pre-computed forecasts to improve performance
- Invalidated when new demand data is added

---

## 9. Views

### 9.1 `view_active_inventory`
Virtual view of currently active property items

**Purpose:** Provides a quick view of all property items that are active (not disposed)

**Base Query:**
```sql
SELECT * FROM property_items WHERE status = 'active'
```

**Columns:** Same as `property_items` table

---

### 9.2 `view_issued_items`
Virtual view of all issued property (PAR/ICS)

**Purpose:** Shows all property currently issued to offices/individuals

**Base Query:**
```sql
SELECT 
    ir.*,
    o.name as office_name,
    u.full_name as issued_by_name
FROM issuance_records ir
LEFT JOIN offices o ON ir.office_id = o.id
LEFT JOIN users u ON ir.issued_by = u.id
WHERE ir.status = 'issued'
```

**Columns:**
- All columns from `issuance_records`
- `office_name` - Name of receiving office
- `issued_by_name` - Full name of issuer

---

### 9.3 `view_unserviceable_items`
Virtual view of unserviceable property

**Purpose:** Shows all property marked as unserviceable

**Base Query:**
```sql
SELECT 
    ur.*,
    pi.property_number,
    pi.description,
    pi.article
FROM unserviceable_records ur
LEFT JOIN property_items pi ON ur.property_item_id = pi.id
WHERE ur.status IN ('pending', 'approved')
```

**Columns:**
- All columns from `unserviceable_records`
- `property_number` - Property number from property_items
- `description` - Description from property_items
- `article` - Article from property_items

---

## Database Relationships Summary

### Primary Relationships

1. **Consumable Management**
   - `consumable_items` → `consumable_stock` (1:1)
   - `consumable_items` ← `consumable_stock_moves` (1:many)
   - `consumable_stock_moves` → `consumable_items` (many:1)
   - `consumable_stock_moves` → `offices` (many:1)
   - `consumable_stock_moves` → `proc_pr` (many:1)
   - `consumable_stock_moves` → `issuance_records` (many:1)

2. **Property Management**
   - `property_items` → `property_tags` (1:1)
   - `property_items` ← `inventory_history` (1:many)
   - `property_items` ← `unserviceable_records` (1:many)
   - `unserviceable_records` ← `disposal_records` (1:many)

3. **Issuance System**
   - `issuance_records` ← `issuance_items` (1:many)
   - `issuance_records` ← `issuance_consumable_lines` (1:many)
   - `issuance_records` ← `return_records` (1:many)
   - `issuance_records` → `offices` (many:1)
   - `issuance_records` → `users` (many:1)

4. **Procurement Chain**
   - `proc_pr` ← `proc_pr_line` (1:many)
   - `proc_pr` ← `proc_quotation` (1:many)
   - `proc_pr` ← `proc_po` (1:many)
   - `proc_quotation` ← `proc_quotation_line` (1:many)
   - `proc_po` ← `proc_po_line` (1:many)
   - `proc_po` ← `proc_air` (1:many)
   - `proc_air` ← `proc_air_line` (1:many)

5. **User & Office Structure**
   - `offices` ← `users` (1:many)
   - `users` ← `user_activity` (1:many)
   - `users` ← `user_settings` (1:1)
   - `users` ← `password_reset_codes` (1:many)
   - `users` ← `notification_reads` (1:many)

6. **Notifications**
   - `notifications` ← `notification_reads` (1:many)
   - `notifications` → `users` (many:1) [target]
   - `notifications` → `offices` (many:1) [target]

---

## Enum Values Reference

### issuance_records.form_type
- `PAR` - Property Acknowledgement Receipt
- `ICS` - Inventory Custodian Slip

### issuance_records.status
- `draft` - Being prepared
- `issued` - Officially issued
- `returned` - Returned to inventory
- `cancelled` - Cancelled

### consumable_stock_moves.move_type
- `starting` - Initial stock
- `restock` - Stock replenishment
- `release` - Issued to office
- `adjust` - Manual adjustment

### property_items.status
- `active` - Available in inventory
- `issued` - Issued to an office
- `disposed` - Disposed/scrapped
- `under_repair` - Under maintenance

### unserviceable_records.status
- `pending` - Awaiting approval
- `approved` - Approved for disposal
- `disposed` - Already disposed

### proc_pr.status
- `draft` - Being prepared
- `submitted` - Submitted for approval
- `approved` - Approved
- `cancelled` - Cancelled
- `completed` - Completed (PO issued)

### proc_po.status
- `draft` - Being prepared
- `issued` - Issued to supplier
- `partially_received` - Partially delivered
- `completed` - Fully delivered
- `cancelled` - Cancelled

### proc_air.status / proc_air.inspection_result
- `draft` - Being prepared
- `completed` - Inspection complete
- `accepted` - Items accepted
- `rejected` - Items rejected
- `partial` - Partially accepted

### users.role
- `admin` - System administrator
- `gso_head` - GSO Head
- `gso_staff` - GSO Staff
- `procurement` - Procurement officer
- `office_head` - Office/dept head
- `staff` - Regular staff

### notifications.priority
- `low` - Low priority
- `normal` - Normal priority
- `high` - High priority
- `urgent` - Urgent

---

## Data Dictionary Conventions

### Naming Conventions
- **Tables:** Lowercase with underscores (snake_case)
- **Columns:** Lowercase with underscores (snake_case)
- **Foreign Keys:** `[table_name]_id` format
- **Timestamps:** `created_at`, `updated_at`, `[action]_at`
- **Status Flags:** `is_[property]` for boolean values

### Common Field Patterns
- `id` - Primary key (INT AUTO_INCREMENT)
- `[entity]_id` - Foreign key reference
- `[entity]_number` - Human-readable identifier
- `created_at` - Creation timestamp
- `updated_at` - Last modification timestamp
- `is_active` - Soft delete / active status flag
- `status` - Workflow state (usually ENUM)
- `remarks` / `notes` - Additional comments

### Decimal Precision
- **Quantities:** DECIMAL(14,4) - Up to 9999999999.9999
- **Currency:** DECIMAL(14,2) - Up to 999999999999.99
- **Percentages:** DECIMAL(5,2) - Up to 100.00

### Timestamp Behavior
- `DEFAULT current_timestamp()` - Set on insert
- `ON UPDATE current_timestamp()` - Update automatically
- Most audit fields use both for complete tracking

---

## Notes

1. **Cascading Deletes:** Most foreign keys use `ON DELETE CASCADE` to maintain referential integrity
2. **Soft Deletes:** Many tables use `is_active` or status flags instead of physical deletion
3. **Audit Trail:** Most transactions are logged in history or activity tables
4. **Multi-tenancy:** Office-based segregation through `office_id` foreign keys
5. **Normalization:** Database follows 3NF (Third Normal Form) principles
6. **UTF-8 Encoding:** All tables use utf8mb4_general_ci for full Unicode support

---

**End of Data Dictionary**
