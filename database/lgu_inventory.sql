-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 05, 2026 at 08:28 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lgu_inventory`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_inventory_summary_by_office` (IN `office_id_param` INT)   BEGIN
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_property_history` (IN `property_id_param` INT)   BEGIN
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

-- --------------------------------------------------------

--
-- Table structure for table `disposal_records`
--

CREATE TABLE `disposal_records` (
  `id` int(11) NOT NULL,
  `unserviceable_id` int(11) NOT NULL,
  `property_item_id` int(11) NOT NULL,
  `disposal_date` date NOT NULL,
  `disposal_method` varchar(255) NOT NULL,
  `appraised_value` decimal(15,2) DEFAULT NULL,
  `sale_value` decimal(15,2) DEFAULT NULL,
  `buyer_recipient` varchar(255) DEFAULT NULL,
  `approved_by` varchar(255) DEFAULT NULL,
  `witnessed_by` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_history`
--

CREATE TABLE `inventory_history` (
  `id` int(11) NOT NULL,
  `property_item_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL COMMENT 'Created, Updated, Issued, Returned, Transferred, etc.',
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `changed_by` varchar(255) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `issuance_records`
--

CREATE TABLE `issuance_records` (
  `id` int(11) NOT NULL,
  `form_type` enum('PAR','ICS') NOT NULL COMMENT 'PAR=Property Acknowledgement Receipt, ICS=Inventory Custodian Slip',
  `form_number` varchar(50) NOT NULL,
  `property_item_id` int(11) NOT NULL,
  `office_id` int(11) NOT NULL,
  `issued_to` varchar(255) NOT NULL,
  `position` varchar(255) DEFAULT NULL,
  `issue_date` date NOT NULL,
  `received_by` varchar(255) DEFAULT NULL,
  `received_date` date DEFAULT NULL,
  `issued_by` varchar(255) DEFAULT NULL,
  `approved_by` varchar(255) DEFAULT NULL,
  `status` enum('Active','Returned','Transferred','Unserviceable') DEFAULT 'Active',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `offices`
--

CREATE TABLE `offices` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) NOT NULL,
  `head_name` varchar(255) DEFAULT NULL,
  `head_position` varchar(255) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `offices`
--

INSERT INTO `offices` (`id`, `name`, `code`, `head_name`, `head_position`, `contact_number`, `email`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Office of the Mayor', 'MAYOR', NULL, 'Municipal Mayor', NULL, NULL, 1, '2026-05-05 01:25:14', '2026-05-05 01:25:14'),
(2, 'Office of the Vice Mayor', 'VMAY', NULL, 'Vice Mayor', NULL, NULL, 1, '2026-05-05 01:25:14', '2026-05-05 01:25:14'),
(3, 'Accounting Office', 'ACCT', NULL, 'Municipal Accountant', NULL, NULL, 1, '2026-05-05 01:25:14', '2026-05-05 01:25:14'),
(4, 'Budget Office', 'BUDG', NULL, 'Budget Officer', NULL, NULL, 1, '2026-05-05 01:25:14', '2026-05-05 01:25:14'),
(5, 'Treasury Office', 'TRES', NULL, 'Municipal Treasurer', NULL, NULL, 1, '2026-05-05 01:25:14', '2026-05-05 01:25:14'),
(6, 'Engineering Office', 'ENGR', NULL, 'Municipal Engineer', NULL, NULL, 1, '2026-05-05 01:25:14', '2026-05-05 01:25:14'),
(7, 'Health Office', 'HLTH', NULL, 'Municipal Health Officer', NULL, NULL, 1, '2026-05-05 01:25:14', '2026-05-05 01:25:14'),
(8, 'Social Welfare Office', 'SWDO', NULL, 'Social Welfare Officer', NULL, NULL, 1, '2026-05-05 01:25:14', '2026-05-05 01:25:14'),
(9, 'Planning Office', 'PLAN', NULL, 'Municipal Planning Officer', NULL, NULL, 1, '2026-05-05 01:25:14', '2026-05-05 01:25:14'),
(10, 'General Services Office', 'GSO', NULL, 'General Services Officer', NULL, NULL, 1, '2026-05-05 01:25:14', '2026-05-05 01:25:14'),
(11, 'Human Resource Office', 'HRMO', NULL, 'Human Resource Officer', NULL, NULL, 1, '2026-05-05 01:25:14', '2026-05-05 01:25:14'),
(12, 'Assessor Office', 'ASSO', NULL, 'Municipal Assessor', NULL, NULL, 1, '2026-05-05 01:25:14', '2026-05-05 01:25:14'),
(13, 'Agriculture Office', 'AGRI', NULL, 'Municipal Agriculturist', NULL, NULL, 1, '2026-05-05 01:25:14', '2026-05-05 01:25:14'),
(14, 'Environment Office', 'ENRO', NULL, 'Environment Officer', NULL, NULL, 1, '2026-05-05 01:25:14', '2026-05-05 01:25:14'),
(15, 'Disaster Risk Office', 'MDRRMO', NULL, 'MDRRMO Officer', NULL, NULL, 1, '2026-05-05 01:25:14', '2026-05-05 01:25:14');

-- --------------------------------------------------------

--
-- Table structure for table `property_items`
--

CREATE TABLE `property_items` (
  `id` int(11) NOT NULL,
  `property_number` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `category` enum('SE','PPE') NOT NULL COMMENT 'SE=Semi-Expendable, PPE=Property Plant Equipment',
  `unit_value` decimal(15,2) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `office_id` int(11) NOT NULL,
  `status` enum('Active','Issued','Unserviceable','Disposed','Transferred') DEFAULT 'Active',
  `condition_status` enum('Good','Fair','Damaged') DEFAULT 'Good',
  `acquisition_date` date DEFAULT NULL,
  `acquisition_mode` varchar(100) DEFAULT NULL COMMENT 'Purchase, Donation, Transfer, etc.',
  `supplier` varchar(255) DEFAULT NULL,
  `invoice_number` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `estimated_useful_life` int(11) DEFAULT NULL COMMENT 'In years',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `property_items`
--

INSERT INTO `property_items` (`id`, `property_number`, `description`, `category`, `unit_value`, `quantity`, `office_id`, `status`, `condition_status`, `acquisition_date`, `acquisition_mode`, `supplier`, `invoice_number`, `serial_number`, `model`, `brand`, `estimated_useful_life`, `remarks`, `created_at`, `updated_at`) VALUES
(1, '2026-SE-000001', 'sample', 'SE', 222.00, 12, 13, 'Active', 'Good', '2026-05-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '2026-05-05 01:29:30', '2026-05-05 01:29:30'),
(2, '2026-SE-000002', 'asdasds', 'SE', 222.00, 22, 12, 'Active', 'Good', '2026-05-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '2026-05-05 01:31:06', '2026-05-05 01:31:06');

--
-- Triggers `property_items`
--
DELIMITER $$
CREATE TRIGGER `trg_property_update` AFTER UPDATE ON `property_items` FOR EACH ROW BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO inventory_history (property_item_id, action, old_value, new_value)
        VALUES (NEW.id, 'Status Changed', OLD.status, NEW.status);
    END IF;
    
    IF OLD.office_id != NEW.office_id THEN
        INSERT INTO inventory_history (property_item_id, action, old_value, new_value)
        VALUES (NEW.id, 'Office Transferred', OLD.office_id, NEW.office_id);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `return_records`
--

CREATE TABLE `return_records` (
  `id` int(11) NOT NULL,
  `issuance_id` int(11) NOT NULL,
  `property_item_id` int(11) NOT NULL,
  `return_date` date NOT NULL,
  `returned_by` varchar(255) NOT NULL,
  `return_action` enum('Unserviceable','Transfer','Returned') NOT NULL,
  `transfer_to_office_id` int(11) DEFAULT NULL,
  `condition_on_return` enum('Good','Fair','Damaged') NOT NULL,
  `received_by` varchar(255) DEFAULT NULL,
  `approved_by` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` varchar(50) DEFAULT 'string',
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES
(1, 'system_name', 'Property Inventory Management System', 'string', 'System name', '2026-05-05 01:25:14'),
(2, 'agency_name', 'LGU · Property Office', 'string', 'Agency name', '2026-05-05 01:25:14'),
(3, 'ppe_threshold', '50000', 'number', 'PPE classification threshold', '2026-05-05 01:25:14'),
(4, 'se_threshold', '50000', 'number', 'Semi-Expendable classification threshold', '2026-05-05 01:25:14'),
(5, 'fiscal_year', '2026', 'number', 'Current fiscal year', '2026-05-05 01:25:14'),
(6, 'enable_notifications', '1', 'boolean', 'Enable system notifications', '2026-05-05 01:25:14'),
(7, 'items_per_page', '20', 'number', 'Default items per page', '2026-05-05 01:25:14');

-- --------------------------------------------------------

--
-- Table structure for table `unserviceable_records`
--

CREATE TABLE `unserviceable_records` (
  `id` int(11) NOT NULL,
  `property_item_id` int(11) NOT NULL,
  `marked_date` date NOT NULL,
  `reason` text NOT NULL,
  `marked_by` varchar(255) DEFAULT NULL,
  `disposal_status` enum('Pending','For Appraisal','Approved','Disposed') DEFAULT 'Pending',
  `disposal_date` date DEFAULT NULL,
  `disposal_method` varchar(255) DEFAULT NULL COMMENT 'Sale, Donation, Destruction, etc.',
  `appraised_value` decimal(15,2) DEFAULT NULL,
  `approved_by` varchar(255) DEFAULT NULL,
  `approval_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role` enum('Admin','User','Viewer') DEFAULT 'User',
  `office_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `role`, `office_id`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(6, 'admin', '$2y$10$RI/wyU8Hwp4CxxjgytAJEe1DabKYUipifSPvg1gpuWybiJyWP.I1K', 'sample', 'admin@local.com', 'Admin', NULL, 1, '2026-05-05 01:28:48', '2026-05-05 01:28:41', '2026-05-05 01:28:48'),
(7, 'staff', '$2y$10$.BK.gnIeKVHjr.Zmx6adMOlf/urY1uoKlA5pbao.iR/sb5dNKlIXO', 'sample2', 'staff@local.com', 'User', NULL, 1, '2026-05-05 01:30:13', '2026-05-05 01:30:03', '2026-05-05 01:30:13'),
(8, 'viewer', '$2y$10$1t3T1lP6C2yT687BfaeYvOj47GMo199vrNXj6UXaXPLGGasSG73gq', 'sample3', 'viewer@local.com', 'Viewer', NULL, 1, '2026-05-05 01:30:55', '2026-05-05 01:30:43', '2026-05-05 01:30:55');

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_active_inventory`
-- (See below for the actual view)
--
CREATE TABLE `view_active_inventory` (
`id` int(11)
,`property_number` varchar(50)
,`description` text
,`category` enum('SE','PPE')
,`unit_value` decimal(15,2)
,`quantity` int(11)
,`status` enum('Active','Issued','Unserviceable','Disposed','Transferred')
,`condition_status` enum('Good','Fair','Damaged')
,`office_name` varchar(255)
,`office_code` varchar(50)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_issued_items`
-- (See below for the actual view)
--
CREATE TABLE `view_issued_items` (
`id` int(11)
,`form_type` enum('PAR','ICS')
,`form_number` varchar(50)
,`issued_to` varchar(255)
,`position` varchar(255)
,`issue_date` date
,`property_number` varchar(50)
,`description` text
,`unit_value` decimal(15,2)
,`office_name` varchar(255)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_unserviceable_items`
-- (See below for the actual view)
--
CREATE TABLE `view_unserviceable_items` (
`id` int(11)
,`marked_date` date
,`reason` text
,`disposal_status` enum('Pending','For Appraisal','Approved','Disposed')
,`property_number` varchar(50)
,`description` text
,`unit_value` decimal(15,2)
,`office_name` varchar(255)
);

-- --------------------------------------------------------

--
-- Structure for view `view_active_inventory`
--
DROP TABLE IF EXISTS `view_active_inventory`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_active_inventory`  AS SELECT `pi`.`id` AS `id`, `pi`.`property_number` AS `property_number`, `pi`.`description` AS `description`, `pi`.`category` AS `category`, `pi`.`unit_value` AS `unit_value`, `pi`.`quantity` AS `quantity`, `pi`.`status` AS `status`, `pi`.`condition_status` AS `condition_status`, `o`.`name` AS `office_name`, `o`.`code` AS `office_code` FROM (`property_items` `pi` join `offices` `o` on(`pi`.`office_id` = `o`.`id`)) WHERE `pi`.`status` = 'Active' ;

-- --------------------------------------------------------

--
-- Structure for view `view_issued_items`
--
DROP TABLE IF EXISTS `view_issued_items`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_issued_items`  AS SELECT `ir`.`id` AS `id`, `ir`.`form_type` AS `form_type`, `ir`.`form_number` AS `form_number`, `ir`.`issued_to` AS `issued_to`, `ir`.`position` AS `position`, `ir`.`issue_date` AS `issue_date`, `pi`.`property_number` AS `property_number`, `pi`.`description` AS `description`, `pi`.`unit_value` AS `unit_value`, `o`.`name` AS `office_name` FROM ((`issuance_records` `ir` join `property_items` `pi` on(`ir`.`property_item_id` = `pi`.`id`)) join `offices` `o` on(`ir`.`office_id` = `o`.`id`)) WHERE `ir`.`status` = 'Active' ;

-- --------------------------------------------------------

--
-- Structure for view `view_unserviceable_items`
--
DROP TABLE IF EXISTS `view_unserviceable_items`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_unserviceable_items`  AS SELECT `ur`.`id` AS `id`, `ur`.`marked_date` AS `marked_date`, `ur`.`reason` AS `reason`, `ur`.`disposal_status` AS `disposal_status`, `pi`.`property_number` AS `property_number`, `pi`.`description` AS `description`, `pi`.`unit_value` AS `unit_value`, `o`.`name` AS `office_name` FROM ((`unserviceable_records` `ur` join `property_items` `pi` on(`ur`.`property_item_id` = `pi`.`id`)) join `offices` `o` on(`pi`.`office_id` = `o`.`id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `disposal_records`
--
ALTER TABLE `disposal_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `unserviceable_id` (`unserviceable_id`),
  ADD KEY `property_item_id` (`property_item_id`),
  ADD KEY `idx_disposal_date` (`disposal_date`);

--
-- Indexes for table `inventory_history`
--
ALTER TABLE `inventory_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_property` (`property_item_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_changed_at` (`changed_at`);

--
-- Indexes for table `issuance_records`
--
ALTER TABLE `issuance_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `form_number` (`form_number`),
  ADD KEY `property_item_id` (`property_item_id`),
  ADD KEY `idx_form_type` (`form_type`),
  ADD KEY `idx_form_number` (`form_number`),
  ADD KEY `idx_office` (`office_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_issue_date` (`issue_date`),
  ADD KEY `idx_issuance_office_date` (`office_id`,`issue_date`);

--
-- Indexes for table `offices`
--
ALTER TABLE `offices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `property_items`
--
ALTER TABLE `property_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `property_number` (`property_number`),
  ADD KEY `idx_property_number` (`property_number`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_office` (`office_id`),
  ADD KEY `idx_condition` (`condition_status`),
  ADD KEY `idx_property_office_status` (`office_id`,`status`),
  ADD KEY `idx_property_category_status` (`category`,`status`);
ALTER TABLE `property_items` ADD FULLTEXT KEY `ft_description` (`description`);

--
-- Indexes for table `return_records`
--
ALTER TABLE `return_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `issuance_id` (`issuance_id`),
  ADD KEY `property_item_id` (`property_item_id`),
  ADD KEY `transfer_to_office_id` (`transfer_to_office_id`),
  ADD KEY `idx_return_action` (`return_action`),
  ADD KEY `idx_return_date` (`return_date`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_key` (`setting_key`);

--
-- Indexes for table `unserviceable_records`
--
ALTER TABLE `unserviceable_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_item_id` (`property_item_id`),
  ADD KEY `idx_disposal_status` (`disposal_status`),
  ADD KEY `idx_marked_date` (`marked_date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_office` (`office_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `disposal_records`
--
ALTER TABLE `disposal_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_history`
--
ALTER TABLE `inventory_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `issuance_records`
--
ALTER TABLE `issuance_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offices`
--
ALTER TABLE `offices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `property_items`
--
ALTER TABLE `property_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `return_records`
--
ALTER TABLE `return_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `unserviceable_records`
--
ALTER TABLE `unserviceable_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `disposal_records`
--
ALTER TABLE `disposal_records`
  ADD CONSTRAINT `disposal_records_ibfk_1` FOREIGN KEY (`unserviceable_id`) REFERENCES `unserviceable_records` (`id`),
  ADD CONSTRAINT `disposal_records_ibfk_2` FOREIGN KEY (`property_item_id`) REFERENCES `property_items` (`id`);

--
-- Constraints for table `inventory_history`
--
ALTER TABLE `inventory_history`
  ADD CONSTRAINT `inventory_history_ibfk_1` FOREIGN KEY (`property_item_id`) REFERENCES `property_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `issuance_records`
--
ALTER TABLE `issuance_records`
  ADD CONSTRAINT `issuance_records_ibfk_1` FOREIGN KEY (`property_item_id`) REFERENCES `property_items` (`id`),
  ADD CONSTRAINT `issuance_records_ibfk_2` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`);

--
-- Constraints for table `property_items`
--
ALTER TABLE `property_items`
  ADD CONSTRAINT `property_items_ibfk_1` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`);

--
-- Constraints for table `return_records`
--
ALTER TABLE `return_records`
  ADD CONSTRAINT `return_records_ibfk_1` FOREIGN KEY (`issuance_id`) REFERENCES `issuance_records` (`id`),
  ADD CONSTRAINT `return_records_ibfk_2` FOREIGN KEY (`property_item_id`) REFERENCES `property_items` (`id`),
  ADD CONSTRAINT `return_records_ibfk_3` FOREIGN KEY (`transfer_to_office_id`) REFERENCES `offices` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `unserviceable_records`
--
ALTER TABLE `unserviceable_records`
  ADD CONSTRAINT `unserviceable_records_ibfk_1` FOREIGN KEY (`property_item_id`) REFERENCES `property_items` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
