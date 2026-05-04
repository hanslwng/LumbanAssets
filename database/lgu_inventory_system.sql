-- ============================================
-- LGU INVENTORY MANAGEMENT SYSTEM
-- Database Schema SQL File
-- ============================================
-- Version: 2.0
-- Created: 2026-05-03
-- Description: Complete database schema for Property Inventory Management System
-- ============================================

-- Create Database
CREATE DATABASE IF NOT EXISTS `lgu_inventory` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `lgu_inventory`;

-- ============================================
-- TABLE: users
-- Description: System users and authentication
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) UNIQUE,
    `role` ENUM('Admin', 'User', 'Viewer') DEFAULT 'User',
    `office_id` INT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_login` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_username` (`username`),
    INDEX `idx_role` (`role`),
    INDEX `idx_office` (`office_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: offices
-- Description: Government offices/departments
-- ============================================
CREATE TABLE IF NOT EXISTS `offices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `code` VARCHAR(50) UNIQUE NOT NULL,
    `head_name` VARCHAR(255) NULL,
    `head_position` VARCHAR(255) NULL,
    `contact_number` VARCHAR(50) NULL,
    `email` VARCHAR(255) NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_code` (`code`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: property_items
-- Description: Property and equipment inventory
-- ============================================
CREATE TABLE IF NOT EXISTS `property_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `property_number` VARCHAR(50) UNIQUE NOT NULL,
    `description` TEXT NOT NULL,
    `category` ENUM('SE', 'PPE') NOT NULL COMMENT 'SE=Semi-Expendable, PPE=Property Plant Equipment',
    `unit_value` DECIMAL(15,2) NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `office_id` INT NOT NULL,
    `status` ENUM('Active', 'Issued', 'Unserviceable', 'Disposed', 'Transferred') DEFAULT 'Active',
    `condition_status` ENUM('Good', 'Fair', 'Damaged') DEFAULT 'Good',
    `acquisition_date` DATE NULL,
    `acquisition_mode` VARCHAR(100) NULL COMMENT 'Purchase, Donation, Transfer, etc.',
    `supplier` VARCHAR(255) NULL,
    `invoice_number` VARCHAR(100) NULL,
    `serial_number` VARCHAR(100) NULL,
    `model` VARCHAR(100) NULL,
    `brand` VARCHAR(100) NULL,
    `estimated_useful_life` INT NULL COMMENT 'In years',
    `remarks` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`office_id`) REFERENCES `offices`(`id`) ON DELETE RESTRICT,
    INDEX `idx_property_number` (`property_number`),
    INDEX `idx_category` (`category`),
    INDEX `idx_status` (`status`),
    INDEX `idx_office` (`office_id`),
    INDEX `idx_condition` (`condition_status`),
    FULLTEXT INDEX `ft_description` (`description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: issuance_records
-- Description: Property issuance records (PAR/ICS)
-- ============================================
CREATE TABLE IF NOT EXISTS `issuance_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `form_type` ENUM('PAR', 'ICS') NOT NULL COMMENT 'PAR=Property Acknowledgement Receipt, ICS=Inventory Custodian Slip',
    `form_number` VARCHAR(50) UNIQUE NOT NULL,
    `property_item_id` INT NOT NULL,
    `office_id` INT NOT NULL,
    `issued_to` VARCHAR(255) NOT NULL,
    `position` VARCHAR(255) NULL,
    `issue_date` DATE NOT NULL,
    `received_by` VARCHAR(255) NULL,
    `received_date` DATE NULL,
    `issued_by` VARCHAR(255) NULL,
    `approved_by` VARCHAR(255) NULL,
    `status` ENUM('Active', 'Returned', 'Transferred', 'Unserviceable') DEFAULT 'Active',
    `remarks` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`property_item_id`) REFERENCES `property_items`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`office_id`) REFERENCES `offices`(`id`) ON DELETE RESTRICT,
    INDEX `idx_form_type` (`form_type`),
    INDEX `idx_form_number` (`form_number`),
    INDEX `idx_office` (`office_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_issue_date` (`issue_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: return_records
-- Description: Property return/transfer records
-- ============================================
CREATE TABLE IF NOT EXISTS `return_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `issuance_id` INT NOT NULL,
    `property_item_id` INT NOT NULL,
    `return_date` DATE NOT NULL,
    `returned_by` VARCHAR(255) NOT NULL,
    `return_action` ENUM('Unserviceable', 'Transfer', 'Returned') NOT NULL,
    `transfer_to_office_id` INT NULL,
    `condition_on_return` ENUM('Good', 'Fair', 'Damaged') NOT NULL,
    `received_by` VARCHAR(255) NULL,
    `approved_by` VARCHAR(255) NULL,
    `remarks` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`issuance_id`) REFERENCES `issuance_records`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`property_item_id`) REFERENCES `property_items`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`transfer_to_office_id`) REFERENCES `offices`(`id`) ON DELETE SET NULL,
    INDEX `idx_return_action` (`return_action`),
    INDEX `idx_return_date` (`return_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: unserviceable_records
-- Description: Unserviceable property records
-- ============================================
CREATE TABLE IF NOT EXISTS `unserviceable_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `property_item_id` INT NOT NULL,
    `marked_date` DATE NOT NULL,
    `reason` TEXT NOT NULL,
    `marked_by` VARCHAR(255) NULL,
    `disposal_status` ENUM('Pending', 'For Appraisal', 'Approved', 'Disposed') DEFAULT 'Pending',
    `disposal_date` DATE NULL,
    `disposal_method` VARCHAR(255) NULL COMMENT 'Sale, Donation, Destruction, etc.',
    `appraised_value` DECIMAL(15,2) NULL,
    `approved_by` VARCHAR(255) NULL,
    `approval_date` DATE NULL,
    `remarks` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`property_item_id`) REFERENCES `property_items`(`id`) ON DELETE RESTRICT,
    INDEX `idx_disposal_status` (`disposal_status`),
    INDEX `idx_marked_date` (`marked_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: disposal_records
-- Description: Property disposal records
-- ============================================
CREATE TABLE IF NOT EXISTS `disposal_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `unserviceable_id` INT NOT NULL,
    `property_item_id` INT NOT NULL,
    `disposal_date` DATE NOT NULL,
    `disposal_method` VARCHAR(255) NOT NULL,
    `appraised_value` DECIMAL(15,2) NULL,
    `sale_value` DECIMAL(15,2) NULL,
    `buyer_recipient` VARCHAR(255) NULL,
    `approved_by` VARCHAR(255) NULL,
    `witnessed_by` VARCHAR(255) NULL,
    `remarks` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`unserviceable_id`) REFERENCES `unserviceable_records`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`property_item_id`) REFERENCES `property_items`(`id`) ON DELETE RESTRICT,
    INDEX `idx_disposal_date` (`disposal_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: inventory_history
-- Description: Audit trail for inventory changes
-- ============================================
CREATE TABLE IF NOT EXISTS `inventory_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `property_item_id` INT NOT NULL,
    `action` VARCHAR(100) NOT NULL COMMENT 'Created, Updated, Issued, Returned, Transferred, etc.',
    `old_value` TEXT NULL,
    `new_value` TEXT NULL,
    `changed_by` VARCHAR(255) NULL,
    `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`property_item_id`) REFERENCES `property_items`(`id`) ON DELETE CASCADE,
    INDEX `idx_property` (`property_item_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_changed_at` (`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: system_settings
-- Description: System configuration settings
-- ============================================
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) UNIQUE NOT NULL,
    `setting_value` TEXT NULL,
    `setting_type` VARCHAR(50) DEFAULT 'string',
    `description` TEXT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SAMPLE DATA: Default Admin User
-- ============================================
INSERT INTO `users` (`username`, `password`, `full_name`, `email`, `role`, `is_active`) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@lgu.gov.ph', 'Admin', 1)
ON DUPLICATE KEY UPDATE `username` = `username`;
-- Default password: password

-- ============================================
-- SAMPLE DATA: Offices
-- ============================================
INSERT INTO `offices` (`name`, `code`, `head_position`) VALUES
('Office of the Mayor', 'MAYOR', 'Municipal Mayor'),
('Office of the Vice Mayor', 'VMAY', 'Vice Mayor'),
('Accounting Office', 'ACCT', 'Municipal Accountant'),
('Budget Office', 'BUDG', 'Budget Officer'),
('Treasury Office', 'TRES', 'Municipal Treasurer'),
('Engineering Office', 'ENGR', 'Municipal Engineer'),
('Health Office', 'HLTH', 'Municipal Health Officer'),
('Social Welfare Office', 'SWDO', 'Social Welfare Officer'),
('Planning Office', 'PLAN', 'Municipal Planning Officer'),
('General Services Office', 'GSO', 'General Services Officer'),
('Human Resource Office', 'HRMO', 'Human Resource Officer'),
('Assessor Office', 'ASSO', 'Municipal Assessor'),
('Agriculture Office', 'AGRI', 'Municipal Agriculturist'),
('Environment Office', 'ENRO', 'Environment Officer'),
('Disaster Risk Office', 'MDRRMO', 'MDRRMO Officer')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ============================================
-- SAMPLE DATA: System Settings
-- ============================================
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('system_name', 'Property Inventory Management System', 'string', 'System name'),
('agency_name', 'LGU · Property Office', 'string', 'Agency name'),
('ppe_threshold', '50000', 'number', 'PPE classification threshold'),
('se_threshold', '50000', 'number', 'Semi-Expendable classification threshold'),
('fiscal_year', '2026', 'number', 'Current fiscal year'),
('enable_notifications', '1', 'boolean', 'Enable system notifications'),
('items_per_page', '20', 'number', 'Default items per page')
ON DUPLICATE KEY UPDATE `setting_key` = VALUES(`setting_key`);

-- ============================================
-- VIEWS: Useful database views
-- ============================================

-- View: Active Inventory Summary
CREATE OR REPLACE VIEW `view_active_inventory` AS
SELECT 
    pi.id,
    pi.property_number,
    pi.description,
    pi.category,
    pi.unit_value,
    pi.quantity,
    pi.status,
    pi.condition_status,
    o.name AS office_name,
    o.code AS office_code
FROM property_items pi
INNER JOIN offices o ON pi.office_id = o.id
WHERE pi.status = 'Active';

-- View: Issued Items Summary
CREATE OR REPLACE VIEW `view_issued_items` AS
SELECT 
    ir.id,
    ir.form_type,
    ir.form_number,
    ir.issued_to,
    ir.position,
    ir.issue_date,
    pi.property_number,
    pi.description,
    pi.unit_value,
    o.name AS office_name
FROM issuance_records ir
INNER JOIN property_items pi ON ir.property_item_id = pi.id
INNER JOIN offices o ON ir.office_id = o.id
WHERE ir.status = 'Active';

-- View: Unserviceable Items Summary
CREATE OR REPLACE VIEW `view_unserviceable_items` AS
SELECT 
    ur.id,
    ur.marked_date,
    ur.reason,
    ur.disposal_status,
    pi.property_number,
    pi.description,
    pi.unit_value,
    o.name AS office_name
FROM unserviceable_records ur
INNER JOIN property_items pi ON ur.property_item_id = pi.id
INNER JOIN offices o ON pi.office_id = o.id;

-- ============================================
-- STORED PROCEDURES
-- ============================================

-- Procedure: Get Inventory Summary by Office
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS `sp_inventory_summary_by_office`(IN office_id_param INT)
BEGIN
    SELECT 
        category,
        COUNT(*) AS total_items,
        SUM(quantity) AS total_quantity,
        SUM(unit_value * quantity) AS total_value,
        status
    FROM property_items
    WHERE office_id = office_id_param
    GROUP BY category, status;
END$$
DELIMITER ;

-- Procedure: Get Property History
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS `sp_property_history`(IN property_id_param INT)
BEGIN
    SELECT 
        action,
        old_value,
        new_value,
        changed_by,
        changed_at
    FROM inventory_history
    WHERE property_item_id = property_id_param
    ORDER BY changed_at DESC;
END$$
DELIMITER ;

-- ============================================
-- TRIGGERS: Audit Trail
-- ============================================

-- Trigger: Log property item updates
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS `trg_property_update` 
AFTER UPDATE ON `property_items`
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO inventory_history (property_item_id, action, old_value, new_value)
        VALUES (NEW.id, 'Status Changed', OLD.status, NEW.status);
    END IF;
    
    IF OLD.office_id != NEW.office_id THEN
        INSERT INTO inventory_history (property_item_id, action, old_value, new_value)
        VALUES (NEW.id, 'Office Transferred', OLD.office_id, NEW.office_id);
    END IF;
END$$
DELIMITER ;

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================
-- Additional composite indexes for common queries
CREATE INDEX `idx_property_office_status` ON `property_items`(`office_id`, `status`);
CREATE INDEX `idx_issuance_office_date` ON `issuance_records`(`office_id`, `issue_date`);
CREATE INDEX `idx_property_category_status` ON `property_items`(`category`, `status`);

-- ============================================
-- DATABASE SETUP COMPLETE
-- ============================================
-- Total Tables: 10
-- Total Views: 3
-- Total Procedures: 2
-- Total Triggers: 1
-- ============================================
