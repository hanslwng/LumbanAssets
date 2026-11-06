# LGU INVENT - Complete System Documentation

**System Name:** LGU Property Inventory Management System (LGU INVENT)  
**Version:** 2.x  
**Technology Stack:** PHP 7.4+, MySQL 5.7+, JavaScript (Vanilla), Apache  
**Target Users:** Local Government Units (LGU) in the Philippines  
**Primary Purpose:** Property and inventory management, PAR/ICS issuance, procurement tracking, COA-compliant reporting

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Architecture](#2-architecture)
3. [User Roles & Permissions](#3-user-roles--permissions)
4. [Process Flows](#4-process-flows)
5. [Database Schema](#5-database-schema)
6. [Core Modules](#6-core-modules)
7. [API Endpoints](#7-api-endpoints)
8. [File Structure](#8-file-structure)
9. [Installation & Setup](#9-installation--setup)
10. [Security & Authentication](#10-security--authentication)
11. [Reports & Exports](#11-reports--exports)
12. [Troubleshooting](#12-troubleshooting)

---

## 1. System Overview

### 1.1 Purpose

LGU INVENT is a comprehensive property inventory management system designed specifically for Philippine Local Government Units (LGUs) to:

- Track property items (Property, Plant & Equipment and Semi-Expendable items)
- Manage property assignments to offices and personnel
- Process Property Acknowledgment Receipts (PAR) and Inventory Custody Slips (ICS)
- Handle returns, transfers, and unserviceable items
- Generate COA-compliant reports (RPCPPE, RPCSE)
- Manage procurement pipeline (PR, RFQ, PO, AIR)
- Forecast inventory demand using machine learning
- Maintain complete audit trails

### 1.2 Key Features

| Feature Category | Capabilities |
|-----------------|-------------|
| **Inventory Management** | CRUD operations, bulk import/export (Excel), property tagging, classification (SE/PPE) |
| **Issuance & Custody** | PAR/ICS generation, property assignment, electronic signatures, printable forms |
| **Returns & Transfers** | Return tracking, inter-office transfers, chain of custody |
| **Unserviceable Items** | Mark items as unserviceable, disposal tracking, replacement workflows |
| **Procurement** | Purchase Request (PR), Request for Quotation (RFQ), Purchase Order (PO), Acceptance/Inspection Report (AIR) |
| **Forecasting** | ML-based demand prediction (3-month horizon), smart reorder recommendations, stockout alerts |
| **Reporting** | RPCPPE, RPCSE, Physical Count Reports, Audit Logs, custom exports |
| **User Management** | Role-based access control (Admin, User/Staff, Viewer), office assignment, account limits |
| **Notifications** | Real-time in-app notifications, Server-Sent Events (SSE) streaming |
| **Audit Trail** | Complete activity logging, user actions, system changes |
| **AI Assistant** | GSO Chatbot powered by Claude AI for inventory queries |

### 1.3 Target Users

| User Type | Organization Level | Primary Use Case |
|-----------|-------------------|------------------|
| **GSO Administrator** | Municipal/City-wide | Full system management, all offices, approvals, reporting |
| **Department Staff** | Office/Department | Submit procurement requests, view assigned inventory |
| **Property Custodian** | Office/Department | Manage office-specific property, physical counts |
| **Auditor/Viewer** | Municipal/City-wide | Read-only access for audit and compliance review |

---

## 2. Architecture

### 2.1 Technology Stack

```
┌─────────────────────────────────────────────────────────────┐
│                    PRESENTATION LAYER                        │
│  HTML5 + Vanilla JavaScript + CSS3 + Bootstrap 5           │
│  Modern SPA for Admin | Separate Portal for Staff          │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                    APPLICATION LAYER                         │
│  PHP 7.4+ (MVC Pattern)                                     │
│  • Controllers: app/controllers/                            │
│  • Models: app/models/                                      │
│  • Views: app/views/                                        │
│  • Helpers: includes/                                       │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                      API LAYER (REST)                        │
│  JSON Endpoints: api/*.php                                  │
│  Authentication: Session-based + CSRF protection            │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                      DATA LAYER                              │
│  MySQL 5.7+ / MariaDB 10.2+                                 │
│  PDO with prepared statements                               │
│  Database: lgu_inventory (utf8mb4)                          │
└─────────────────────────────────────────────────────────────┘
```

### 2.2 Request Flow

```
User Request
    ↓
Apache mod_rewrite (.htaccess)
    ↓
router.php (root) → theme/router.php
    ↓
Authentication Check (session)
    ↓
Permission Check (PermissionHelper)
    ↓
Route Handler (Controller or View)
    ↓
API Call (AJAX → api/*.php)
    ↓
Model → Database (PDO)
    ↓
JSON Response
    ↓
JavaScript UI Update
```

### 2.3 MVC Pattern

```
┌─────────────────┐
│  USER REQUEST   │
└────────┬────────┘
         ↓
┌─────────────────┐
│   ROUTER        │  theme/router.php
│  (URL Mapping)  │  • /login → AuthController
└────────┬────────┘  • /staff → staff_portal.php
         ↓            • / → theme/index.php
┌─────────────────┐
│  CONTROLLER     │  app/controllers/
│  (Business      │  • AuthController.php
│   Logic)        │  • (API controllers in api/*.php)
└────────┬────────┘
         ↓
┌─────────────────┐
│    MODEL        │  app/models/
│  (Data Access)  │  • User.php
└────────┬────────┘  • (Other models inline in API files)
         ↓
┌─────────────────┐
│     VIEW        │  app/views/ + theme/
│  (Presentation) │  • login-modern.php
└─────────────────┘  • print_par.php, print_ics.php
```

---

## 3. User Roles & Permissions

### 3.1 Role Definitions

#### 3.1.1 Admin (Administrator)

**Role Code:** `admin`  
**Scope:** LGU-wide (unscoped access to all offices)  
**Dashboard:** Full admin SPA (theme/index.php)  

**Permissions:**
| Permission | Granted | Description |
|-----------|---------|-------------|
| `view` | ✓ | View all inventory across all offices |
| `create` | ✓ | Create items, issuances, PRs, offices, users |
| `edit` | ✓ | Modify any record system-wide |
| `delete` | ✓ | Delete items, users, offices |
| `export` | ✓ | Export Excel reports |
| `print` | ✓ | Generate printable forms |
| `manage_users` | ✓ | Create, edit, delete user accounts |
| `manage_offices` | ✓ | Create, edit, delete offices/departments |
| `approve` | ✓ | Approve PRs, issuances, procurement |

**Key Functions:**
- Create and manage user accounts (all roles)
- Create and manage offices/departments
- Full CRUD on inventory items (all offices)
- Process property issuances (PAR/ICS) for any office
- Approve/reject procurement requests
- Generate LGU-wide reports (RPCPPE, RPCSE)
- Configure system settings
- View complete audit logs
- Access demand forecasting module
- Manage property tags
- Process returns and unserviceable items

#### 3.1.2 User / Staff

**Role Code:** `staff` (previously `user`, normalized to `staff`)  
**Scope:** Department-scoped (assigned office only)  
**Dashboard:** Staff portal (theme/staff_portal.php)  

**Permissions:**
| Permission | Granted | Description |
|-----------|---------|-------------|
| `view` | ✓ | View inventory in assigned office |
| `create` | ✓ | Create PRs, requisitions |
| `edit` | ✓ | Edit own submissions (pending status) |
| `delete` | ✗ | Cannot delete |
| `export` | ✓ | Export office-specific reports |
| `print` | ✓ | Print office documents |
| `manage_users` | ✗ | Cannot manage users |
| `manage_offices` | ✗ | Cannot manage offices |
| `approve` | ✗ | Cannot approve |

**Key Functions:**
- View property assigned to their office
- Submit purchase requests (PRs)
- View procurement pipeline for their office
- Export office-specific inventory reports
- View notifications for their office

#### 3.1.3 Viewer

**Role Code:** `viewer`  
**Scope:** LGU-wide (read-only)  
**Dashboard:** Full admin view (read-only mode)  

**Permissions:**
| Permission | Granted | Description |
|-----------|---------|-------------|
| `view` | ✓ | View all inventory (read-only) |
| `create` | ✗ | Cannot create |
| `edit` | ✗ | Cannot edit |
| `delete` | ✗ | Cannot delete |
| `export` | ✓ | Export reports |
| `print` | ✓ | Print forms |
| `manage_users` | ✗ | Cannot manage users |
| `manage_offices` | ✗ | Cannot manage offices |
| `approve` | ✗ | Cannot approve |

**Key Functions:**
- View-only access to all inventory and reports
- Export and print for audit purposes
- View audit logs (no modification)

### 3.2 Permission Matrix

```php
// From app/helpers/PermissionHelper.php

private static $rolePermissions = [
    'admin' => [
        'view' => true,
        'create' => true,
        'edit' => true,
        'delete' => true,
        'export' => true,
        'print' => true,
        'manage_users' => true,
        'manage_offices' => true,
        'approve' => true
    ],
    'staff' => [
        'view' => true,
        'create' => true,
        'edit' => true,
        'delete' => false,
        'export' => true,
        'print' => true,
        'manage_users' => false,
        'manage_offices' => false,
        'approve' => false
    ],
    'viewer' => [
        'view' => true,
        'create' => false,
        'edit' => false,
        'delete' => false,
        'export' => true,
        'print' => true,
        'manage_users' => false,
        'manage_offices' => false,
        'approve' => false
    ]
];
```

### 3.3 Permission Checking

```php
// Check if user has permission
PermissionHelper::can('edit'); // Returns boolean

// Require permission (throws 403 if denied)
PermissionHelper::require('manage_users');

// Check specific roles
PermissionHelper::isAdmin();
PermissionHelper::isStaff();
PermissionHelper::isViewer();

// Check office scoping
PermissionHelper::isUnscopedAdmin(); // Admin sees all
PermissionHelper::isDepartmentScoped(); // Staff see own office
PermissionHelper::officeId(); // Get assigned office ID
```

---

## 4. Process Flows

### 4.1 Admin Process Flow

```
┌─────────────────────────────────────────────────────────────┐
│ Process Flow           │ Responsibility │ Description        │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│      ┌─────────┐                                             │
│      │  START  │        Admin           Admin logs into      │
│      └────┬────┘        (Authenticated) LGU INVENT with     │
│           ↓                             full permissions     │
│                                                               │
│   ┌──────────────┐                                           │
│   │ Access Admin │     System         System loads admin    │
│   │  Dashboard   │                    dashboard with        │
│   └──────┬───────┘                    unscoped access       │
│           ↓                                                  │
│                                                               │
│   ┌──────────────┐                                           │
│   │   Select     │     Admin          Choose from:          │
│   │   Admin      │                    • Manage Users        │
│   │  Function    │                    • Manage Offices      │
│   └──────┬───────┘                    • Manage Inventory    │
│           ↓                            • Process Issuances   │
│                                        • Manage Procurement  │
│   ┌──────────────┐                    • Generate Reports    │
│   │   Manage     │     Admin          Create/edit/delete   │
│   │    Users     │                    user accounts with   │
│   └──────┬───────┘                    role assignments     │
│           ↓                                                  │
│                                                               │
│   ┌──────────────┐                                           │
│   │   Manage     │     Admin          Create/edit/delete   │
│   │   Offices    │                    departments and       │
│   └──────┬───────┘                    configure limits      │
│           ↓                                                  │
│                                                               │
│   ┌──────────────┐                                           │
│   │   Manage     │     Admin          Full CRUD on         │
│   │  Inventory   │                    property items,       │
│   │    Items     │                    classifications       │
│   └──────┬───────┘                    imports/exports       │
│           ↓                                                  │
│                                                               │
│   ┌──────────────┐                                           │
│   │   Process    │     Admin          Create PAR/ICS       │
│   │  Property    │                    forms, assign tags,  │
│   │  Issuances   │                    approve issuances    │
│   └──────┬───────┘                                          │
│           ↓                                                  │
│                                                               │
│   ┌──────────────┐                                           │
│   │   Manage     │     Admin          Review PRs,          │
│   │ Procurement  │                    approve/reject,       │
│   │ & Forecasting│                    ML forecasting        │
│   └──────┬───────┘                                          │
│           ↓                                                  │
│                                                               │
│   ┌──────────────┐                                           │
│   │  Generate    │     Admin          RPCPPE, RPCSE,       │
│   │   Reports    │                    Physical Count,       │
│   │              │                    Audit Logs            │
│   └──────┬───────┘                                          │
│           ↓                                                  │
│                                                               │
│   ┌──────────────┐                                           │
│   │  Configure   │     Admin          System settings,     │
│   │   System     │                    notifications,        │
│   │  Settings    │                    thresholds            │
│   └──────┬───────┘                                          │
│           ↓                                                  │
│                                                               │
│   ┌──────────────┐                                           │
│   │   Review     │     Admin          Complete audit       │
│   │ Audit Logs & │                    trail monitoring,    │
│   │   Monitor    │                    GSO chatbot queries  │
│   └──────┬───────┘                                          │
│           ↓                                                  │
│                                                               │
│   ┌──────────────┐                                           │
│   │  Validate &  │     System         Permission checks,   │
│   │   Approve    │                    activity logging,     │
│   │   Actions    │                    notifications         │
│   └──────┬───────┘                                          │
│           ↓                                                  │
│                                                               │
│      ┌────────┐                                              │
│      │  END   │         System         Task complete,       │
│      └────────┘                        changes saved         │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

### 4.2 User Registration Flow

```
START
  ↓
Guest Opens Registration Page
  ↓
Guest Fills Registration Form
  • Full Name
  • Email
  • Password (strong requirements)
  • Assigned Office/Department
  ↓
System Validates Input
  • Email format
  • Password strength (8+ chars, uppercase, lowercase, digit, special)
  • Rate limiting (max 10/day per email)
  ↓
System Sends 6-Digit Verification Code
  • Via email (PHPMailer)
  • Code expires in 15 minutes
  • Stored in registration_codes table
  ↓
Guest Enters Verification Code
  ↓
System Verifies Code
  • Checks expiration
  • Validates code match
  ↓
System Creates User Account
  • Hashes password (PASSWORD_DEFAULT)
  • Assigns role (User/Staff by default)
  • Links to office
  • Sets is_active = 1
  ↓
System Logs Activity
  • Audit trail entry
  • Notification to admins
  ↓
Redirect to Login Page
  ↓
END — User can now sign in
```

### 4.3 Property Issuance Flow (PAR/ICS)

```
START — Admin initiates issuance
  ↓
Select Issuance Type
  • PAR (Property Acknowledgment Receipt) — for PPE
  • ICS (Inventory Custody Slip) — for Semi-Expendable
  ↓
Select Target Office/Personnel
  • Choose department
  • Optional: specific accountable officer
  ↓
Add Property Items
  • Search available items
  • Select items to issue
  • Specify quantities
  ↓
Assign Property Tags
  • Generate/assign unique tags
  • Link to property_tags table
  ↓
Set Issuance Details
  • Issuance date
  • Purpose/remarks
  • Authorized signatories
  ↓
System Validates
  • Item availability
  • Sufficient stock
  • Property classification matches form type
  ↓
Admin Approves & Saves
  • Create issuance_records entry
  • Create issuance_items entries
  • Update property_items status (Active → Issued)
  ↓
System Logs Activity
  • Audit trail
  • Notifications to receiving office
  ↓
Generate Printable Form
  • PAR format (print_par.php)
  • ICS format (print_ics.php)
  • Includes signatures, dates, item details
  ↓
Print & File
  ↓
END — Property issued and documented
```

### 4.4 Procurement Request Flow

```
START — Department user needs items
  ↓
Staff Accesses Procurement Module
  • Staff portal (department-scoped)
  ↓
Create Purchase Request (PR)
  • PR number (auto-generated or manual)
  • Purpose
  • Requested by
  • Approved by
  ↓
Add Line Items
  • Item description
  • Quantity
  • Unit
  • Estimated unit cost
  • Category
  ↓
Submit PR for Approval
  • Status: pending → quoting
  ↓
Admin Reviews PR
  • Check budget
  • Validate need
  • Review forecasting recommendations
  ↓
Admin Approves/Rejects
  ↓
[IF APPROVED]
  ↓
Generate Request for Quotation (RFQ)
  • Send to suppliers
  • Collect quotations
  ↓
Admin Reviews Quotations
  • Compare prices
  • Select winning supplier
  ↓
Create Purchase Order (PO)
  • Reference PR and quotation
  • Delivery terms
  • Payment terms
  ↓
Supplier Delivers
  ↓
Create Acceptance/Inspection Report (AIR)
  • Verify quantity
  • Check quality
  • Record actual cost
  ↓
Encode Received Items
  • Add to property_items
  • Update stock levels
  • Link to procurement records
  ↓
Close PR Pipeline
  • Status: delivered
  ↓
END — Items in inventory
```

### 4.5 Physical Count & Reconciliation Flow

```
START — Annual physical inventory
  ↓
Admin Initiates Physical Count
  • Set count date
  • Assign counters per office
  ↓
Generate Physical Count Sheets
  • Export current inventory
  • Group by office and category
  ↓
Physical Counting
  • Manual count of actual items
  • Record findings
  ↓
Import Physical Count Results
  • Excel upload (physical-count-import.php)
  • Map columns
  ↓
System Compares
  • Book quantity vs physical count
  • Calculate variances (shortage/overage)
  ↓
Generate Variance Report
  • Items with discrepancies
  • Variance reasons
  ↓
Admin Reviews & Adjusts
  • Investigate significant variances
  • Update quantities if verified
  • Document adjustments
  ↓
Generate RPCPPE & RPCSE Reports
  • Report on Physical Count of Property, Plant & Equipment
  • Report on Physical Count of Semi-Expendable Property
  • COA-compliant formats
  ↓
Submit to Auditor
  ↓
END — Physical count complete
```

---

## 5. Database Schema

### 5.1 Core Tables

#### 5.1.1 Users Table
```sql
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `role` enum('Admin','User','Viewer') NOT NULL DEFAULT 'User',
  `office_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `office_id` (`office_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Purpose:** Store user accounts with role-based access  
**Key Fields:**
- `role`: Admin (full access) | User (staff, department-scoped) | Viewer (read-only)
- `office_id`: NULL or 0 for unscoped admins, specific office ID for department users
- `is_active`: Enable/disable accounts without deletion

#### 5.1.2 Offices Table
```sql
CREATE TABLE `offices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `description` text,
  `account_limit` int(11) NOT NULL DEFAULT 5,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Purpose:** Departments/offices for property assignment and user scoping  
**Key Fields:**
- `account_limit`: Maximum number of user accounts per office
- `code`: Short code for reports and forms

#### 5.1.3 Property Items Table
```sql
CREATE TABLE `property_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `property_number` varchar(50) NOT NULL,
  `article` varchar(255) NOT NULL,
  `description` text,
  `quantity` decimal(14,4) NOT NULL DEFAULT 1.0000,
  `unit` varchar(50) DEFAULT NULL,
  `unit_cost` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_cost` decimal(14,2) GENERATED ALWAYS AS (quantity * unit_cost) STORED,
  `category` varchar(100) DEFAULT NULL,
  `item_type` enum('SE','PPE') NOT NULL DEFAULT 'PPE',
  `acquisition_date` date DEFAULT NULL,
  `acquisition_year` int(4) DEFAULT NULL,
  `assigned_office_id` int(11) DEFAULT NULL,
  `status` enum('Active','Issued','Returned','Unserviceable','Disposed') DEFAULT 'Active',
  `remarks` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `property_number` (`property_number`),
  KEY `assigned_office_id` (`assigned_office_id`),
  CONSTRAINT `property_items_ibfk_1` FOREIGN KEY (`assigned_office_id`) REFERENCES `offices` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Purpose:** Main inventory table for all property items  
**Key Fields:**
- `property_number`: Unique identifier (e.g., "2024-01-001")
- `item_type`: SE (Semi-Expendable) | PPE (Property, Plant & Equipment)
- `status`: Active | Issued | Returned | Unserviceable | Disposed
- `total_cost`: Computed column (quantity × unit_cost)

**Classification Logic:**
- PPE: unit_cost >= threshold (typically PHP 15,000)
- SE: unit_cost < threshold

#### 5.1.4 Issuance Records Table
```sql
CREATE TABLE `issuance_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `form_type` enum('PAR','ICS') NOT NULL,
  `form_number` varchar(100) DEFAULT NULL,
  `target_office_id` int(11) NOT NULL,
  `issued_by` varchar(255) DEFAULT NULL,
  `received_by` varchar(255) DEFAULT NULL,
  `issuance_date` date NOT NULL,
  `purpose` text,
  `status` enum('Draft','Issued','Returned','Cancelled') DEFAULT 'Draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `target_office_id` (`target_office_id`),
  CONSTRAINT `issuance_records_ibfk_1` FOREIGN KEY (`target_office_id`) REFERENCES `offices` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Purpose:** Track property issuances (PAR/ICS forms)  
**Key Fields:**
- `form_type`: PAR (for PPE) | ICS (for Semi-Expendable)
- `target_office_id`: Receiving office/department
- `status`: Draft | Issued | Returned | Cancelled

#### 5.1.5 Issuance Items Table
```sql
CREATE TABLE `issuance_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `issuance_id` int(11) NOT NULL,
  `property_item_id` int(11) NOT NULL,
  `quantity_issued` decimal(14,4) NOT NULL DEFAULT 1.0000,
  PRIMARY KEY (`id`),
  KEY `issuance_id` (`issuance_id`),
  KEY `property_item_id` (`property_item_id`),
  CONSTRAINT `issuance_items_ibfk_1` FOREIGN KEY (`issuance_id`) REFERENCES `issuance_records` (`id`) ON DELETE CASCADE,
  CONSTRAINT `issuance_items_ibfk_2` FOREIGN KEY (`property_item_id`) REFERENCES `property_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Purpose:** Line items for each issuance (many-to-many relationship)

#### 5.1.6 Procurement Tables

**Purchase Request (PR)**
```sql
CREATE TABLE `proc_pr` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pr_number` varchar(80) NOT NULL,
  `requesting_office_id` int(11) DEFAULT NULL,
  `purpose` text,
  `requested_by` varchar(255) DEFAULT NULL,
  `approved_by` varchar(255) DEFAULT NULL,
  `pr_date` date DEFAULT NULL,
  `status` enum('draft','pending','quoting','po_issued','delivered','cancelled') DEFAULT 'draft',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pr_number` (`pr_number`),
  KEY `requesting_office_id` (`requesting_office_id`),
  CONSTRAINT `proc_pr_ibfk_1` FOREIGN KEY (`requesting_office_id`) REFERENCES `offices` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `proc_pr_line` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pr_id` int(11) NOT NULL,
  `item_description` varchar(500) NOT NULL,
  `quantity` decimal(14,4) NOT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `estimated_unit_cost` decimal(14,2) DEFAULT 0.00,
  `estimated_total_cost` decimal(14,2) GENERATED ALWAYS AS (quantity * estimated_unit_cost) STORED,
  PRIMARY KEY (`id`),
  KEY `pr_id` (`pr_id`),
  CONSTRAINT `proc_pr_line_ibfk_1` FOREIGN KEY (`pr_id`) REFERENCES `proc_pr` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Purchase Order (PO), Quotations, AIR**: Similar structure linking PR → RFQ → PO → AIR

#### 5.1.7 Consumable Items & Stock

```sql
CREATE TABLE `consumable_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `category` varchar(100) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `reorder_level` decimal(14,4) DEFAULT 10.0000,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `consumable_stock` (
  `item_id` int(11) NOT NULL,
  `current_stock` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `last_updated` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`item_id`),
  CONSTRAINT `consumable_stock_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `consumable_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `consumable_stock_moves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `move_type` enum('IN','OUT','ADJUST') NOT NULL,
  `quantity` decimal(14,4) NOT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `moved_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `consumable_stock_moves_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `consumable_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Purpose:** Track consumable supplies (separate from property items)

#### 5.1.8 Audit & Activity Tables

```sql
CREATE TABLE `user_activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(100) NOT NULL,
  `action_description` text,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `entity_label` varchar(255) DEFAULT NULL,
  `old_value` text,
  `new_value` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `entity_type_id` (`entity_type`,`entity_id`),
  KEY `action_type` (`action_type`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Purpose:** Complete audit trail of all system activities

#### 5.1.9 Notifications Table

```sql
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(500) DEFAULT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `target_office_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `target_user_id` (`target_user_id`),
  KEY `target_office_id` (`target_office_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `notification_reads` (
  `user_id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `read_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`notification_id`),
  KEY `notification_id` (`notification_id`),
  CONSTRAINT `notification_reads_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notification_reads_ibfk_2` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Purpose:** Real-time notifications with read tracking

#### 5.1.10 Forecasting Tables

```sql
CREATE TABLE `demo_demand_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `office_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `year_month` varchar(7) NOT NULL,
  `demand_qty` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_history` (`office_id`,`category`,`year_month`,`is_demo`),
  KEY `is_demo` (`is_demo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `demo_forecast_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payload` longtext NOT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `is_demo` (`is_demo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Purpose:** Store ML forecasting data (monthly demand history and cached predictions)

### 5.2 Entity Relationships

```
┌──────────┐         ┌──────────┐         ┌──────────────┐
│  users   │────────>│ offices  │<────────│property_items│
└──────────┘ 1    *  └──────────┘  1   *  └──────────────┘
    │                     │                      │
    │ created_by          │ requesting           │
    │                     │                      │
    ↓                     ↓                      ↓
┌──────────────┐     ┌──────────┐         ┌──────────────┐
│issuance_     │────>│ proc_pr  │         │issuance_items│
│  records     │  *  └──────────┘         └──────────────┘
└──────────────┘          │                      │
    │                     │                      │
    │ 1                   │ 1                    │ *
    │                     │                      │
    ↓ *                   ↓ *                    ↓ 1
┌──────────────┐     ┌──────────┐         ┌──────────────┐
│issuance_items│     │proc_pr_  │         │property_items│
└──────────────┘     │   line   │         └──────────────┘
                     └──────────┘
```

---

## 6. Core Modules

### 6.1 Authentication Module

**Files:**
- `app/controllers/AuthController.php`
- `app/models/User.php`
- `app/views/auth/login-modern.php`
- `includes/login_guard.php`

**Features:**
- Email/password authentication
- Password strength enforcement (8+ chars, uppercase, lowercase, digit, special)
- Login attempt limiting (max 5 fails, 15-minute lockout)
- Session management (timeout ~1 hour)
- Password reset with email verification
- User registration with 6-digit email codes

**Key Functions:**
```php
// AuthController
public function login() // Process login
public function logout() // End session
public function register() // Send verification email
public function registerVerify() // Validate code and create account
public function forgotRequest() // Initiate password reset
public function forgotReset() // Change password
```

### 6.2 Inventory Module

**Files:**
- `api/items.php`
- `api/items-import.php`
- `api/items-export.php`
- `includes/items_import_helpers.php`

**Features:**
- CRUD operations on property items
- Bulk import from Excel (RPCPPE/RPCSE format)
- Bulk export to Excel
- Property number generation
- Classification (SE/PPE) based on unit cost
- Office assignment
- Status management (Active/Issued/Returned/Unserviceable/Disposed)

**API Endpoints:**
```
GET    /api/items.php          List/filter items
POST   /api/items.php          Create new item
PATCH  /api/items.php?id={id}  Update item
DELETE /api/items.php?id={id}  Delete item
POST   /api/items-import.php   Bulk import from Excel
GET    /api/items-export.php   Export to Excel
```

### 6.3 Issuance Module

**Files:**
- `api/issuance.php`
- `app/views/issuances/print_par.php`
- `app/views/issuances/print_ics.php`
- `includes/issuance_helpers.php`
- `includes/issuance_print_prepare.php`

**Features:**
- Create PAR (Property Acknowledgment Receipt) for PPE
- Create ICS (Inventory Custody Slip) for Semi-Expendable
- Property tag assignment
- Electronic signature capture
- Printable government-compliant forms
- Status tracking (Draft/Issued/Returned/Cancelled)

**API Endpoints:**
```
GET    /api/issuance.php           List issuances
POST   /api/issuance.php           Create issuance
PATCH  /api/issuance.php?id={id}   Update issuance
DELETE /api/issuance.php?id={id}   Delete issuance
```

### 6.4 Procurement Module

**Files:**
- `api/procurement.php`
- `assets/js/procurement-module.js`
- `includes/procurement_print_data.php`

**Features:**
- Purchase Request (PR) creation and tracking
- Request for Quotation (RFQ) management
- Purchase Order (PO) generation
- Acceptance/Inspection Report (AIR)
- Quotation comparison
- Supplier management
- Procurement pipeline status

**Procurement Workflow:**
```
PR (draft) → PR (pending) → RFQ (quoting) → PO (po_issued) → AIR (delivered)
```

**API Endpoints:**
```
GET    /api/procurement.php?type=pr&id={id}
POST   /api/procurement.php?type=pr
PATCH  /api/procurement.php?type=pr&id={id}
DELETE /api/procurement.php?type=pr&id={id}

Similar for: rfq, quotation, po, air
```

### 6.5 Forecasting Module

**Files:**
- `api/forecast.php`
- `assets/js/forecast-module.js`
- `includes/demo_forecast.php`
- `includes/consumable_forecast_ml.php`

**Features:**
- ML-based demand prediction (3-month horizon)
- Gradient boosting algorithm (decision stumps)
- 3-month moving average baseline
- Smart reorder recommendations
- Stockout risk alerts
- Overstock warnings
- Office × category grouping
- LGU-wide and office-specific forecasts

**Forecast Algorithm:**
```
Model: Gradient Boosting (PHP implementation)
Features: t (time index), month (1-12), lag1, lag3, ma3
Training: Last 80% for fit, last 20% for MAE
Horizon: 3 months ahead
Learning rate: 0.2
Max trees: 16 decision stumps
```

**Recommendation Formula:**
```
Order Qty = Predicted Demand (3 mo)
          + Safety Stock (1.25 × avg last 3 mo, min 1)
          − Current On-Hand
          − Incoming (open PRs)
```

**Status Logic:**
```
Stockout Risk: available < next_month_prediction
Reorder Now:   order_qty > 0 AND available ≤ reorder_point
Watch:         order_qty > 0 OR near reorder point
Overstock:     no order needed AND stock high vs demand
Sufficient:    all others
```

### 6.6 Reporting Module

**Files:**
- `app/views/reports/rpcppe_workbook.php`
- `app/views/reports/rpcse_workbook.php`
- `app/views/reports/print_physical_count_report.php`
- `includes/rpcppe_workbook.php`
- `includes/rpcse_workbook.php`

**Features:**
- RPCPPE (Report on Physical Count of Property, Plant & Equipment)
- RPCSE (Report on Physical Count of Semi-Expendable Property)
- Physical Count Reports with variance analysis
- Excel exports using PhpSpreadsheet
- COA-compliant formatting
- Office-specific and LGU-wide reports

**Report Formats:**
```
RPCPPE:
- Cover page
- Summary by category
- Detailed property listing
- Valuation summary
- Signatories

RPCSE:
- Cover page
- Grouped by category
- Quantity and value
- Variance report (book vs physical)
- Reconciliation notes
```

### 6.7 Property Tag Module

**Files:**
- `api/property-tags.php`
- `assets/js/property-tag.js`
- `includes/property_tag_helpers.php`

**Features:**
- Generate unique property tag numbers
- Print property tags (barcode-ready format)
- Assign tags to items
- Track tag history
- Office/division assignment

**Tag Format:**
```
Property Tag: {YEAR}-{OFFICE_CODE}-{SEQUENCE}
Example: 2024-GSO-001
```

### 6.8 Returns & Unserviceable Module

**Files:**
- `api/returns.php`
- `api/unserviceable.php`

**Features:**
- Track returns from issued property
- Mark items as unserviceable
- Disposal records and approvals
- Replacement workflows
- Transfer between offices

### 6.9 Notifications Module

**Files:**
- `api/notifications.php`
- `api/notifications-stream.php`
- `includes/notifications_helpers.php`

**Features:**
- Real-time Server-Sent Events (SSE)
- In-app notification bell
- Read/unread tracking
- Scoped notifications (user-specific or office-specific)
- Notification types: issuance, procurement, user, system, reorder

### 6.10 GSO Chatbot (AI Assistant)

**Files:**
- `api/chatbot.php`
- `assets/js/gso-chatbot.js`
- `includes/gso_assistant.php`

**Features:**
- Claude AI integration (Anthropic API)
- Natural language queries about inventory
- Live data access (counts, PRs, offices, items)
- Context-aware responses
- Conversation history
- Admin and staff access with scoped data

**Example Queries:**
- "How many computers do we have?"
- "Show me items at the Health Office"
- "What PRs are pending approval?"
- "When should we reorder bond paper?"

---

## 7. API Endpoints

### 7.1 Authentication

| Method | Endpoint | Purpose | Auth Required |
|--------|----------|---------|---------------|
| GET | `/login` | Show login page | No |
| POST | `/login` | Process login | No |
| POST | `/register` | Send verification code | No |
| POST | `/register/verify` | Verify code & create account | No |
| POST | `/forgot/request` | Request password reset | No |
| POST | `/forgot/verify` | Verify reset code | No |
| POST | `/forgot/reset` | Change password | No |
| GET | `/logout` | End session | Yes |

### 7.2 Items

| Method | Endpoint | Purpose | Permission |
|--------|----------|---------|------------|
| GET | `/api/items.php` | List/filter items | view |
| POST | `/api/items.php` | Create item | create |
| PATCH | `/api/items.php?id={id}` | Update item | edit |
| DELETE | `/api/items.php?id={id}` | Delete item | delete |
| POST | `/api/items-import.php` | Import Excel | create |
| GET | `/api/items-export.php` | Export Excel | export |

### 7.3 Offices

| Method | Endpoint | Purpose | Permission |
|--------|----------|---------|------------|
| GET | `/api/offices.php` | List offices | view |
| POST | `/api/offices.php` | Create office | manage_offices |
| PUT | `/api/offices.php?id={id}` | Update office | manage_offices |
| DELETE | `/api/offices.php?id={id}` | Delete office | manage_offices |

### 7.4 Issuance

| Method | Endpoint | Purpose | Permission |
|--------|----------|---------|------------|
| GET | `/api/issuance.php` | List issuances | view |
| POST | `/api/issuance.php` | Create issuance | create |
| PATCH | `/api/issuance.php?id={id}` | Update issuance | edit |
| DELETE | `/api/issuance.php?id={id}` | Delete issuance | delete |

### 7.5 Procurement

| Method | Endpoint | Purpose | Permission |
|--------|----------|---------|------------|
| GET | `/api/procurement.php?type={type}` | List by type (pr, rfq, po, air) | view |
| POST | `/api/procurement.php?type={type}` | Create record | create |
| PATCH | `/api/procurement.php?type={type}&id={id}` | Update record | edit |
| DELETE | `/api/procurement.php?type={type}&id={id}` | Delete record | delete |

### 7.6 Forecasting

| Method | Endpoint | Purpose | Permission |
|--------|----------|---------|------------|
| GET | `/api/forecast.php` | Get forecast data | view (admin only) |
| POST | `/api/forecast.php` | Rebuild forecast | edit (admin only) |

### 7.7 Notifications

| Method | Endpoint | Purpose | Permission |
|--------|----------|---------|------------|
| GET | `/api/notifications.php` | Get notifications | view |
| POST | `/api/notifications.php?action=mark_read` | Mark as read | view |
| GET | `/api/notifications-stream.php` | SSE stream | view |

### 7.8 Chatbot

| Method | Endpoint | Purpose | Permission |
|--------|----------|---------|------------|
| POST | `/api/chatbot.php` | Send message to AI | view |

### 7.9 Dashboard

| Method | Endpoint | Purpose | Permission |
|--------|----------|---------|------------|
| GET | `/api/dashboard.php` | Get dashboard stats | view |

### 7.10 Settings

| Method | Endpoint | Purpose | Permission |
|--------|----------|---------|------------|
| GET | `/api/settings.php` | Get settings | view |
| POST | `/api/settings.php?action=update` | Update settings | manage_offices (admin) |
| POST | `/api/settings.php?action=create_user` | Create user | manage_users (admin) |
| POST | `/api/settings.php?action=update_user` | Update user | manage_users (admin) |
| POST | `/api/settings.php?action=delete_user` | Delete user | manage_users (admin) |

### 7.11 Audit

| Method | Endpoint | Purpose | Permission |
|--------|----------|---------|------------|
| GET | `/api/audit.php` | Get activity logs | view (admin only) |

---

## 8. File Structure

```
lgu_invent/
│
├── .htaccess                      # URL rewriting, legacy path support
├── .env.example                   # Environment template
├── .env                          # Environment config (DO NOT COMMIT)
├── bootstrap.php                 # Project bootstrap, loads config
├── composer.json                 # PHP dependencies
├── router.php                    # Root router (delegates to theme/router.php)
├── index.php                     # Directory index (loads router.php)
│
├── app/                          # MVC Application Layer
│   ├── controllers/
│   │   └── AuthController.php    # Login, register, logout
│   ├── helpers/
│   │   └── PermissionHelper.php  # RBAC permission checks
│   ├── models/
│   │   └── User.php              # User model
│   └── views/
│       ├── auth/
│       │   └── login-modern.php  # Modern login UI
│       ├── issuances/
│       │   ├── print_par.php     # PAR printable form
│       │   └── print_ics.php     # ICS printable form
│       └── reports/
│           ├── rpcppe_workbook.php
│           ├── rpcse_workbook.php
│           └── print_physical_count_report.php
│
├── api/                          # JSON REST API Endpoints
│   ├── audit.php                 # Activity logs
│   ├── chatbot.php               # AI assistant
│   ├── dashboard.php             # Dashboard stats
│   ├── forecast.php              # Demand forecasting
│   ├── issuance.php              # PAR/ICS CRUD
│   ├── items.php                 # Property items CRUD
│   ├── items-import.php          # Bulk import
│   ├── items-export.php          # Bulk export
│   ├── notifications.php         # Notifications CRUD
│   ├── notifications-stream.php  # SSE stream
│   ├── offices.php               # Offices CRUD
│   ├── physical-count-import.php # Import physical count
│   ├── physical-count-save.php   # Save physical count
│   ├── procurement.php           # PR/RFQ/PO/AIR CRUD
│   ├── property-tags.php         # Property tag management
│   ├── reorder-items.php         # Reorder recommendations
│   ├── returns.php               # Returns tracking
│   ├── settings.php              # System & user settings
│   └── unserviceable.php         # Unserviceable items
│
├── assets/                       # Frontend Assets
│   ├── css/
│   │   ├── style.css             # Main stylesheet
│   │   ├── staff-portal.css      # Staff portal theme
│   │   ├── auth-modern.css       # Login page styles
│   │   ├── procurement-ui.css    # Procurement module
│   │   ├── property-tag.css      # Property tag printing
│   │   └── design-tokens.css     # Design system variables
│   └── js/
│       ├── app.js                # Main admin SPA logic
│       ├── staff-dashboard.js    # Staff portal logic
│       ├── forecast-module.js    # Forecasting UI
│       ├── procurement-module.js # Procurement UI
│       ├── property-tag.js       # Property tag UI
│       ├── settings-module.js    # Settings UI
│       └── gso-chatbot.js        # Chatbot UI
│
├── database/
│   └── lgu_inventory (7).sql    # Database schema & sample data
│
├── docs/
│   ├── FORECASTING.md            # Forecasting documentation
│   ├── FORECASTING-TALKING-POINTS.md
│   └── FULL-SYSTEM-DOCUMENTATION.md  # This file
│
├── includes/                     # PHP Helper Functions
│   ├── audit_log.php             # Activity logging
│   ├── consumable_forecast.php
│   ├── consumable_forecast_ml.php # ML forecasting engine
│   ├── demo_forecast.php         # Forecast builder
│   ├── env.php                   # Environment loader
│   ├── gso_assistant.php         # AI chatbot logic
│   ├── gso_chatbot_panel.php     # Chatbot UI component
│   ├── input_validate.php        # Input validation
│   ├── issuance_helpers.php
│   ├── issuance_print_prepare.php
│   ├── items_import_helpers.php
│   ├── login_guard.php           # Login attempt limiting
│   ├── mailer.php                # Email sending
│   ├── modals.php                # Modal dialogs
│   ├── notifications_helpers.php
│   ├── par_template_workbook.php
│   ├── password_reset.php
│   ├── physical_count_report_data.php
│   ├── procurement_print_data.php
│   ├── property_tag_helpers.php
│   ├── registration_verify.php
│   ├── rpcppe_workbook.php
│   ├── rpcse_workbook.php
│   ├── session.php               # Session management
│   └── settings_helpers.php
│
├── public/                       # Public Assets
│   └── assets/
│       ├── logo.png              # LGU logo
│       └── ...
│
├── scripts/                      # Setup & Maintenance Scripts
│   ├── setup-database.php        # Database initialization
│   ├── setup-test-users.php      # Create test accounts (dev only)
│   ├── seed-demo-forecast.php    # Rebuild forecast
│   └── checkup-*.php             # System health checks
│
├── theme/                        # Main Application Theme
│   ├── .htaccess                 # Deny direct HTTP access
│   ├── config.php                # Database config, thresholds
│   ├── router.php                # Application router (MVC)
│   ├── index.php                 # Admin dashboard SPA shell
│   └── staff_portal.php          # Staff/department portal
│
└── vendor/                       # Composer dependencies
    ├── phpoffice/phpspreadsheet  # Excel generation
    ├── phpmailer/phpmailer       # Email sending
    └── ...
```

---

## 9. Installation & Setup

### 9.1 Requirements

- **PHP:** 7.4 or higher
  - Extensions: PDO, pdo_mysql, json, mbstring, zip, gd
- **MySQL:** 5.7+ or MariaDB 10.2+
- **Apache:** 2.4+
  - mod_rewrite enabled
  - AllowOverride All
- **Composer:** For PHP dependencies

### 9.2 Installation Steps

#### Step 1: Download/Clone Repository

```bash
cd C:\xampp\htdocs
git clone <repository-url> lgu_invent
# OR extract ZIP to C:\xampp\htdocs\lgu_invent
```

#### Step 2: Install Dependencies

```bash
cd lgu_invent
composer install
```

If you don't have Composer installed:
- Download: https://getcomposer.org/
- Run: `composer.phar install`

#### Step 3: Configure Environment

```bash
# Copy environment template
copy .env.example .env

# Edit .env with your settings
notepad .env
```

**.env Configuration:**
```ini
# Database
DB_HOST=localhost
DB_NAME=lgu_inventory
DB_USER=root
DB_PASS=

# Email (SMTP)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
SMTP_FROM_EMAIL=noreply@yourlgu.gov.ph
SMTP_FROM_NAME=LGU INVENT

# AI Chatbot (Optional)
ANTHROPIC_API_KEY=sk-ant-...

# Base URL
BASE_URL=/lgu_invent/
```

#### Step 4: Database Setup

**Option A: Automated (Recommended)**
```
1. Start Apache and MySQL (XAMPP Control Panel)
2. Open browser: http://localhost/lgu_invent/scripts/setup-database.php
3. Follow on-screen instructions
```

**Option B: Manual**
```sql
-- Create database
CREATE DATABASE lgu_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Import schema
-- Use phpMyAdmin or command line:
mysql -u root -p lgu_inventory < database/lgu_inventory (7).sql
```

#### Step 5: Configure Apache

**Verify .htaccess is enabled:**

Edit `C:\xampp\apache\conf\httpd.conf`:
```apache
# Find and uncomment:
LoadModule rewrite_module modules/mod_rewrite.so

# Find <Directory> section and ensure:
AllowOverride All
```

**Restart Apache**

#### Step 6: Create Admin Account

**Option A: Registration Page**
```
1. Open http://localhost/lgu_invent/login
2. Click "Register"
3. Fill form, verify email
4. Manually change role to 'Admin' in database:
   UPDATE users SET role = 'Admin' WHERE email = 'your-email@example.com';
```

**Option B: Test Users Script (Development Only)**
```
Open: http://localhost/lgu_invent/scripts/setup-test-users.php

Creates:
- admin@test.com / password123 (Admin)
- user@test.com / password123 (User)

⚠️ CHANGE PASSWORDS IMMEDIATELY IN PRODUCTION
```

#### Step 7: Login & Configure

```
1. Open http://localhost/lgu_invent/
2. Login with admin account
3. Go to Settings → System
4. Configure:
   - Municipal name
   - GSO officer name
   - Mayor name (for reports)
   - PPE threshold (default: 15000)
   - Account limits per office
```

### 9.3 Post-Installation Checklist

- [ ] Database created and tables populated
- [ ] Admin account created
- [ ] Test login successful
- [ ] Create sample offices (Settings → Offices)
- [ ] Configure system settings
- [ ] Test email sending (password reset)
- [ ] Import sample inventory (optional)
- [ ] Set up SSL/HTTPS (production)
- [ ] Configure backups
- [ ] Review security settings

---

## 10. Security & Authentication

### 10.1 Authentication Flow

```
User submits email + password
    ↓
Login Guard: Check attempt limit
    ↓
    ├─ [Locked] → Return error (5 fails = 15 min lockout)
    └─ [OK] → Continue
    ↓
AuthController::login()
    ↓
User::authenticate(email, password)
    ↓
    ├─ Query users table
    ├─ Verify password_hash()
    └─ Return user record
    ↓
    ├─ [Invalid] → Log failed attempt → Increment counter
    └─ [Valid] → Continue
    ↓
Clear login guard counters
    ↓
session_regenerate_id(true)
    ↓
Set session variables:
    - user_id
    - user_email
    - user_name
    - user_role (normalized: admin|staff|viewer)
    - user_office_id
    - last_activity
    ↓
Update last_login timestamp
    ↓
Log auth activity
    ↓
Redirect:
    - Admin → /
    - Staff → /staff
```

### 10.2 Session Management

**Configuration** (includes/session.php):
```php
session_set_cookie_params([
    'lifetime' => 0,           // Session cookie (until browser close)
    'path' => '/lgu_invent/',
    'domain' => '',
    'secure' => false,         // Set TRUE for HTTPS
    'httponly' => true,        // Prevent JavaScript access
    'samesite' => 'Lax'       // CSRF protection
]);

session_name('LGU_INVENT_SESSION');
session_start();
```

**Session Timeout:**
- Inactivity timeout: ~1 hour (checked in theme/index.php)
- Absolute timeout: Until browser close

**Session Variables:**
```php
$_SESSION['user_id']         // int
$_SESSION['user_email']      // string
$_SESSION['user_name']       // string
$_SESSION['user_role']       // 'admin' | 'staff' | 'viewer'
$_SESSION['user_office_id']  // int | null
$_SESSION['user_office_name']// string
$_SESSION['last_activity']   // timestamp
```

### 10.3 Password Security

**Hashing:**
```php
// Store
$hash = password_hash($password, PASSWORD_DEFAULT);

// Verify
if (password_verify($inputPassword, $storedHash)) {
    // Valid
}
```

**Password Requirements:**
- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one digit
- At least one special character

**Validation Function:**
```php
private function isStrongPassword(string $password): bool {
    if (strlen($password) < 8) return false;
    if (!preg_match('/[A-Z]/', $password)) return false;
    if (!preg_match('/[a-z]/', $password)) return false;
    if (!preg_match('/[0-9]/', $password)) return false;
    if (!preg_match('/[^A-Za-z0-9]/', $password)) return false;
    return true;
}
```

### 10.4 Login Protection

**Rate Limiting** (includes/login_guard.php):
```php
Max attempts: 5
Lockout duration: 15 minutes
Tracking: In-memory (cleared on success or expiry)
```

### 10.5 Permission Enforcement

**API-Level:**
```php
// Start of API file
require_once '../bootstrap.php';
require_once '../app/helpers/PermissionHelper.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Check permission
PermissionHelper::require('edit'); // Throws 403 if denied
```

**UI-Level:**
```javascript
// JavaScript permission checks
if (window.userPermissions.can_edit) {
    // Show edit button
}

if (window.userRole === 'admin') {
    // Show admin-only features
}
```

### 10.6 SQL Injection Prevention

**All queries use PDO prepared statements:**
```php
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
```

### 10.7 XSS Prevention

**Output Escaping:**
```php
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');
```

**JavaScript:**
```javascript
element.textContent = userInput; // Safe (not innerHTML)
```

### 10.8 CSRF Protection

**Session-based:**
- SameSite cookie attribute: 'Lax'
- Session ID regeneration on login
- Origin validation for sensitive actions

### 10.9 File Upload Security

**Import Validation:**
```php
// File type check
$allowedTypes = [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel'
];
$fileType = $_FILES['file']['type'];
if (!in_array($fileType, $allowedTypes)) {
    throw new Exception('Invalid file type');
}

// File size limit
$maxSize = 10 * 1024 * 1024; // 10 MB
if ($_FILES['file']['size'] > $maxSize) {
    throw new Exception('File too large');
}

// Virus scan (recommended for production)
// Use ClamAV or similar
```

### 10.10 Audit Logging

**All sensitive actions are logged:**
```php
activityLog($conn, $entityType, $action, [
    'user_id' => $_SESSION['user_id'],
    'changed_by' => $_SESSION['user_name'],
    'office_id' => $_SESSION['user_office_id'],
    'entity_type' => 'property_item',
    'entity_id' => $itemId,
    'entity_label' => $propertyNumber,
    'old_value' => $oldValue,
    'new_value' => $newValue,
]);
```

**Logged Actions:**
- User authentication (login, logout, failed attempts)
- User management (create, edit, delete, role changes)
- Inventory changes (create, edit, delete items)
- Issuance processing
- Procurement actions
- Settings modifications
- Report generation

---

## 11. Reports & Exports

### 11.1 RPCPPE (Property, Plant & Equipment Report)

**File:** `app/views/reports/rpcppe_workbook.php`  
**Purpose:** COA-compliant report on property items (PPE)  
**Format:** Excel (.xlsx)

**Structure:**
```
Sheet 1: Cover Page
- Municipal/City name
- Report period
- Certified by (GSO officer)
- Noted by (Mayor)

Sheet 2: Summary
- Category-wise totals
- Acquisition cost
- Accumulated depreciation
- Book value

Sheet 3-N: Detail Sheets (by category)
- Property number
- Article/Description
- Model/Serial
- Acquisition date
- Unit cost
- Quantity
- Total cost
- Condition
- Location/Office
```

**Generation:**
```php
// From theme/index.php or API call
include 'app/views/reports/rpcppe_workbook.php';

// Outputs Excel file to browser
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="RPCPPE_' . date('Y-m-d') . '.xlsx"');
```

### 11.2 RPCSE (Semi-Expendable Property Report)

**File:** `app/views/reports/rpcse_workbook.php`  
**Purpose:** COA-compliant report on semi-expendable items  
**Format:** Excel (.xlsx)

**Structure:**
```
Sheet 1: Cover Page
Sheet 2: Summary by Category
Sheet 3-N: Detail Sheets (by category)
- Stock number
- Description
- Unit
- Quantity
- Unit cost
- Total cost
- Office assignment
```

### 11.3 Physical Count Report

**File:** `app/views/reports/print_physical_count_report.php`  
**Purpose:** Compare book quantities vs physical count  
**Format:** HTML (printable) or Excel

**Structure:**
```
- Property number
- Description
- Book quantity
- Physical count
- Variance (shortage/overage)
- Remarks
- Variance percentage
```

**Variance Calculation:**
```php
$variance = $physicalCount - $bookQuantity;
$variancePercent = ($bookQuantity > 0) 
    ? ($variance / $bookQuantity) * 100 
    : 0;
```

### 11.4 Property Tag Printing

**File:** `includes/property_tag_helpers.php`  
**Purpose:** Generate property tags for printing  
**Format:** HTML (printer-friendly, barcode-ready)

**Tag Layout:**
```
┌────────────────────────────┐
│  MUNICIPALITY OF [NAME]    │
│  General Services Office   │
├────────────────────────────┤
│  Property Number:          │
│  [2024-GSO-001]           │
│                            │
│  [Barcode]                │
│                            │
│  Description:              │
│  [Item description]        │
│                            │
│  Assigned to:              │
│  [Office/Division]         │
└────────────────────────────┘
```

### 11.5 PAR/ICS Forms

**PAR (Property Acknowledgment Receipt):**
- Government-standard format
- PPE items only
- Signatures: Issued by, Received by, Approved by
- Property details with acquisition dates

**ICS (Inventory Custody Slip):**
- Government-standard format
- Semi-expendable items
- Similar structure to PAR
- Quantity-focused

### 11.6 Audit Logs Export

**Endpoint:** `api/audit.php?export=1`  
**Format:** Excel (.xlsx) or CSV  
**Contents:**
- Date/Time
- User
- Action type
- Entity type
- Entity ID
- Old value
- New value
- IP address
- User agent

### 11.7 Inventory Export

**Endpoint:** `api/items-export.php`  
**Filters:**
- Office
- Category
- Status
- Date range
- Item type (SE/PPE)

**Format:** Excel (.xlsx)  
**Columns:**
- Property number
- Description
- Category
- Quantity
- Unit
- Unit cost
- Total cost
- Acquisition date
- Status
- Office assignment

---

## 12. Troubleshooting

### 12.1 Common Issues

#### Database Connection Failed

**Symptoms:**
- Blank page
- JSON error: "Database not available"
- `SQLSTATE[HY000]` error

**Solutions:**
1. Check MySQL is running (XAMPP Control Panel)
2. Verify `.env` credentials:
   ```ini
   DB_HOST=localhost
   DB_NAME=lgu_inventory
   DB_USER=root
   DB_PASS=
   ```
3. Check database exists:
   ```sql
   SHOW DATABASES LIKE 'lgu_inventory';
   ```
4. Run setup: `http://localhost/lgu_invent/scripts/setup-database.php`

#### 404 Errors on Routes

**Symptoms:**
- `/lgu_invent/login` returns 404
- `/lgu_invent/staff` not found

**Solutions:**
1. Check mod_rewrite enabled:
   ```apache
   # httpd.conf
   LoadModule rewrite_module modules/mod_rewrite.so
   ```
2. Verify AllowOverride:
   ```apache
   <Directory "C:/xampp/htdocs">
       AllowOverride All
   </Directory>
   ```
3. Check `.htaccess` RewriteBase:
   ```apache
   RewriteBase /lgu_invent/
   ```
4. Restart Apache

#### Session Timeout Too Aggressive

**Symptoms:**
- Logged out frequently
- "Session expired" messages

**Solutions:**
1. Edit `theme/index.php`:
   ```php
   $sessionTimeout = 7200; // 2 hours (default: 3600)
   ```
2. Or disable timeout check for testing:
   ```php
   // Comment out timeout check
   ```

#### Excel Import Fails

**Symptoms:**
- "Invalid file format"
- Import hangs or errors

**Solutions:**
1. Check file format: Must be `.xlsx` or `.xls`
2. Verify headers match expected format
3. Check file size: Max 10 MB
4. Review column mapping in import UI
5. Check PHP memory limit:
   ```ini
   ; php.ini
   memory_limit = 256M
   ```

#### Email Not Sending

**Symptoms:**
- Registration code not received
- Password reset fails

**Solutions:**
1. Check `.env` SMTP settings:
   ```ini
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_USER=your-email@gmail.com
   SMTP_PASS=your-app-password
   ```
2. For Gmail: Enable "App Passwords"
3. Test with script:
   ```php
   require 'includes/mailer.php';
   sendMail('test@example.com', 'Test', 'Test message');
   ```
4. Check PHP error logs

#### Forecasting Not Working

**Symptoms:**
- "No data available"
- Forecast shows zeros

**Solutions:**
1. Import inventory with dates:
   - acquisition_date or acquisition_year required
2. Rebuild forecast:
   - Admin → Planning → Forecast → Refresh Forecast
3. Or via CLI:
   ```bash
   C:\xampp\php\php.exe scripts\seed-demo-forecast.php
   ```
4. Check Settings → Forecast Mode = "Live"

#### Permissions Denied

**Symptoms:**
- 403 Forbidden errors
- "You do not have permission"

**Solutions:**
1. Check user role:
   ```sql
   SELECT id, email, role FROM users WHERE email = 'user@example.com';
   ```
2. Verify role permissions in `PermissionHelper.php`
3. Clear browser cache and re-login
4. Check session:
   ```php
   var_dump($_SESSION);
   ```

#### Chatbot Not Responding

**Symptoms:**
- Chatbot panel empty
- "API error" messages

**Solutions:**
1. Check API key in `.env`:
   ```ini
   ANTHROPIC_API_KEY=sk-ant-...
   ```
2. Verify internet connection
3. Check PHP cURL enabled:
   ```ini
   extension=curl
   ```
4. Review API usage limits (Anthropic)
5. Check error logs: `includes/gso_assistant.php`

### 12.2 Debug Mode

**Enable PHP errors** (development only):
```php
// theme/config.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
```

**JavaScript console:**
```javascript
// Check for errors
console.log(window.userPermissions);
console.log(window.userRole);
```

### 12.3 Logs

**PHP Error Log:**
- Location: `C:\xampp\php\logs\php_error_log`
- Or check Apache error.log

**Apache Error Log:**
- Location: `C:\xampp\apache\logs\error.log`

**Activity Logs (Database):**
```sql
SELECT * FROM user_activity 
ORDER BY created_at DESC 
LIMIT 100;
```

### 12.4 Performance Issues

**Slow queries:**
1. Add database indexes:
   ```sql
   CREATE INDEX idx_property_number ON property_items(property_number);
   CREATE INDEX idx_office ON property_items(assigned_office_id);
   CREATE INDEX idx_status ON property_items(status);
   ```

2. Enable MySQL query cache (if supported)

3. Optimize large reports:
   - Filter by date range
   - Paginate results
   - Export in batches

**Large imports:**
1. Increase PHP limits:
   ```ini
   ; php.ini
   max_execution_time = 300
   max_input_time = 300
   memory_limit = 512M
   post_max_size = 20M
   upload_max_filesize = 20M
   ```

2. Process in chunks (modify import script)

### 12.5 Backup & Recovery

**Database Backup:**
```bash
# Export
mysqldump -u root -p lgu_inventory > backup_YYYY-MM-DD.sql

# Import
mysql -u root -p lgu_inventory < backup_YYYY-MM-DD.sql
```

**File Backup:**
- Backup entire `/lgu_invent/` folder
- Exclude: `vendor/`, `node_modules/` (can be regenerated)
- Include: `uploads/`, `.env`, custom configurations

**Automated Backups:**
- Set up scheduled task (Windows) or cron job (Linux)
- Store backups off-site
- Test restoration regularly

### 12.6 Security Hardening (Production)

1. **Change default passwords**
2. **Enable HTTPS:**
   ```apache
   # .htaccess
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```
3. **Disable directory listing:**
   ```apache
   Options -Indexes
   ```
4. **Restrict scripts/ access:**
   ```apache
   <Directory "C:/xampp/htdocs/lgu_invent/scripts">
       Require ip 127.0.0.1
   </Directory>
   ```
5. **Set secure session cookies:**
   ```php
   'secure' => true, // Requires HTTPS
   ```
6. **Hide PHP version:**
   ```ini
   expose_php = Off
   ```
7. **Regular updates:**
   - PHP version
   - Composer packages: `composer update`
   - Database schema updates

---

## Appendices

### Appendix A: Glossary

| Term | Definition |
|------|------------|
| **AIR** | Acceptance and Inspection Report - document verifying received items |
| **COA** | Commission on Audit - Philippine government audit agency |
| **GSO** | General Services Office - LGU department managing property |
| **ICS** | Inventory Custody Slip - form for semi-expendable property custody |
| **LGU** | Local Government Unit - municipal or city government |
| **PAR** | Property Acknowledgment Receipt - form for PPE custody |
| **PO** | Purchase Order - formal order to supplier |
| **PPE** | Property, Plant & Equipment - capital assets ≥ PHP 15,000 |
| **PR** | Purchase Request - formal request to procure items |
| **RBAC** | Role-Based Access Control - permission system |
| **RFQ** | Request for Quotation - solicitation for supplier quotes |
| **RPCPPE** | Report on Physical Count of Property, Plant & Equipment |
| **RPCSE** | Report on Physical Count of Semi-Expendable Property |
| **SE** | Semi-Expendable - items < PPE threshold but trackable |
| **SSE** | Server-Sent Events - real-time notification protocol |

### Appendix B: Configuration Reference

**theme/config.php:**
```php
// Database
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'lgu_inventory');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// PPE Threshold
define('PPE_THRESHOLD', 15000); // PHP

// Procurement Print Constants
define('PROC_PRINT_APPROVED_BY', 'RHONALIZA A. ALVIAR');
define('PROC_PRINT_MAYOR_NAME', 'BELEN B. RAGA');
define('PROC_PRINT_MUNICIPALITY', 'Municipality of Lumban, Laguna');
```

### Appendix C: Database Maintenance

**Optimize tables:**
```sql
OPTIMIZE TABLE property_items;
OPTIMIZE TABLE issuance_records;
OPTIMIZE TABLE user_activity;
```

**Clean old logs:**
```sql
-- Delete activity logs older than 1 year
DELETE FROM user_activity 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

**Check table sizes:**
```sql
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
FROM information_schema.TABLES 
WHERE table_schema = 'lgu_inventory'
ORDER BY size_mb DESC;
```

### Appendix D: API Response Formats

**Success Response:**
```json
{
    "success": true,
    "message": "Operation completed successfully",
    "data": {
        "id": 123,
        "property_number": "2024-GSO-001"
    }
}
```

**Error Response:**
```json
{
    "success": false,
    "message": "Operation failed",
    "error": "Detailed error message",
    "code": "ERROR_CODE"
}
```

**List Response:**
```json
{
    "success": true,
    "data": [
        { "id": 1, "name": "Item 1" },
        { "id": 2, "name": "Item 2" }
    ],
    "total": 150,
    "page": 1,
    "per_page": 50
}
```

---

## Document Information

**Document Version:** 1.0  
**Last Updated:** October 29, 2026  
**Author:** LGU INVENT Development Team  
**Status:** Complete System Documentation  

**Revision History:**
| Version | Date | Changes |
|---------|------|---------|
| 1.0 | Oct 29, 2026 | Initial comprehensive documentation |

**Related Documents:**
- `FORECASTING.md` - Detailed forecasting module documentation
- `README.md` - Quick start guide
- `cursor-prompt-rpcppe-format.md` - RPCPPE template specification

---

**END OF DOCUMENT**
