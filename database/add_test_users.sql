-- ============================================
-- ADD TEST USERS FOR LGU INVENTORY SYSTEM
-- ============================================
-- Run this file to add test users with password: password123
-- ============================================

USE `lgu_inventory`;

-- Clear existing test users (optional - comment out if you want to keep existing users)
-- DELETE FROM users WHERE email IN ('admin@gso.local', 'staff1@gso.local', 'staff2@gso.local');

-- Insert test users with hashed passwords
-- Password for all users: password123
-- Hash generated using: password_hash('password123', PASSWORD_DEFAULT)

INSERT INTO `users` (`username`, `password`, `full_name`, `email`, `role`, `is_active`, `created_at`) 
VALUES 
    ('admin', '$2y$10$YourHashHere1234567890123456789012345678901234567890123', 'Admin User', 'admin@gso.local', 'Admin', 1, NOW()),
    ('staff1', '$2y$10$YourHashHere1234567890123456789012345678901234567890123', 'Staff Member 1', 'staff1@gso.local', 'User', 1, NOW()),
    ('staff2', '$2y$10$YourHashHere1234567890123456789012345678901234567890123', 'Staff Member 2', 'staff2@gso.local', 'User', 1, NOW())
ON DUPLICATE KEY UPDATE 
    `password` = VALUES(`password`),
    `full_name` = VALUES(`full_name`),
    `email` = VALUES(`email`),
    `role` = VALUES(`role`);

-- ============================================
-- VERIFICATION QUERY
-- ============================================
-- Run this to verify users were created:
-- SELECT id, username, email, full_name, role, is_active FROM users;
