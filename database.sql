CREATE DATABASE IF NOT EXISTS `apartment_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `apartment_db`;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('super_admin', 'admin', 'tenant') NOT NULL DEFAULT 'tenant',
    `phone` VARCHAR(20) NULL,
    `avatar` VARCHAR(255) NULL,
    `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `properties`;
CREATE TABLE `properties` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `owner_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `address` VARCHAR(255) NOT NULL,
    `total_floors` INT DEFAULT 3,
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_property_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `units`;
CREATE TABLE `units` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `property_id` INT NOT NULL,
    `unit_number` VARCHAR(30) NOT NULL,
    `floor_level` INT DEFAULT 1,
    `unit_type` VARCHAR(50) NOT NULL DEFAULT 'Studio',
    `monthly_rent` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `security_deposit` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `water_rate_per_unit` DECIMAL(8,2) DEFAULT 45.00,
    `electric_rate_per_kwh` DECIMAL(8,2) DEFAULT 14.50,
    `status` ENUM('vacant', 'occupied', 'maintenance', 'reserved') NOT NULL DEFAULT 'vacant',
    `max_occupants` INT DEFAULT 2,
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_unit_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `tenants`;
CREATE TABLE `tenants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `unit_id` INT NULL,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NULL,
    `phone` VARCHAR(20) NOT NULL,
    `emergency_contact_name` VARCHAR(100) NULL,
    `emergency_contact_phone` VARCHAR(20) NULL,
    `id_type` VARCHAR(50) DEFAULT 'UMID / National ID',
    `id_number` VARCHAR(50) NULL,
    `id_photo` VARCHAR(255) NULL,
    `lease_start` DATE NOT NULL,
    `lease_end` DATE NOT NULL,
    `rent_due_day` INT DEFAULT 5,
    `deposit_paid` DECIMAL(10,2) DEFAULT 0.00,
    `advance_paid` DECIMAL(10,2) DEFAULT 0.00,
    `status` ENUM('active', 'moved_out', 'evicted') NOT NULL DEFAULT 'active',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_tenant_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tenant_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `utility_readings`;
CREATE TABLE `utility_readings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `unit_id` INT NOT NULL,
    `tenant_id` INT NOT NULL,
    `billing_month` VARCHAR(7) NOT NULL, -- Format: YYYY-MM
    `prev_water_reading` DECIMAL(8,2) DEFAULT 0.00,
    `curr_water_reading` DECIMAL(8,2) DEFAULT 0.00,
    `water_consumption` DECIMAL(8,2) DEFAULT 0.00,
    `water_rate` DECIMAL(8,2) DEFAULT 45.00,
    `water_amount` DECIMAL(10,2) DEFAULT 0.00,
    `prev_electric_reading` DECIMAL(8,2) DEFAULT 0.00,
    `curr_electric_reading` DECIMAL(8,2) DEFAULT 0.00,
    `electric_consumption` DECIMAL(8,2) DEFAULT 0.00,
    `electric_rate` DECIMAL(8,2) DEFAULT 14.50,
    `electric_amount` DECIMAL(10,2) DEFAULT 0.00,
    `reading_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_reading_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reading_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_number` VARCHAR(30) NOT NULL UNIQUE,
    `tenant_id` INT NOT NULL,
    `unit_id` INT NOT NULL,
    `utility_reading_id` INT NULL,
    `billing_period_start` DATE NOT NULL,
    `billing_period_end` DATE NOT NULL,
    `due_date` DATE NOT NULL,
    `rent_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `water_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `electricity_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `penalty_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `other_charges` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `other_charges_notes` VARCHAR(255) NULL,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('unpaid', 'partially_paid', 'paid', 'overdue') NOT NULL DEFAULT 'unpaid',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_inv_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_inv_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_inv_reading` FOREIGN KEY (`utility_reading_id`) REFERENCES `utility_readings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL,
    `tenant_id` INT NOT NULL,
    `payment_reference` VARCHAR(40) NOT NULL UNIQUE,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('Cash', 'GCash', 'Maya', 'Bank Transfer') NOT NULL DEFAULT 'Cash',
    `transaction_ref_no` VARCHAR(100) NULL,
    `payment_date` DATE NOT NULL,
    `proof_of_payment` VARCHAR(255) NULL,
    `status` ENUM('confirmed', 'pending_verification', 'rejected') NOT NULL DEFAULT 'confirmed',
    `notes` TEXT NULL,
    `received_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pay_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pay_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pay_receiver` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `maintenance_requests`;
CREATE TABLE `maintenance_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `unit_id` INT NOT NULL,
    `tenant_id` INT NOT NULL,
    `issue_title` VARCHAR(150) NOT NULL,
    `category` VARCHAR(50) DEFAULT 'Plumbing',
    `description` TEXT NOT NULL,
    `priority` ENUM('low', 'medium', 'high', 'emergency') NOT NULL DEFAULT 'medium',
    `status` ENUM('pending', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    `assigned_to` VARCHAR(100) NULL,
    `repair_cost` DECIMAL(10,2) DEFAULT 0.00,
    `photo` VARCHAR(255) NULL,
    `requested_date` DATE NOT NULL,
    `resolved_date` DATE NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_maint_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_maint_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(50) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
    `setting_key` VARCHAR(50) PRIMARY KEY,
    `setting_value` TEXT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(100) NOT NULL,
    `code` VARCHAR(10) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL,
    INDEX (`email`),
    INDEX (`code`)
) ENGINE=InnoDB;

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('system_name', 'ResiPro Apartment Management System'),
('currency_symbol', '₱'),
('company_email', 'jeckdetera07@gmail.com'),
('company_phone', '+63 917 555 8921'),
('company_address', '108 Sampaguita St., Diliman, Quezon City, Metro Manila'),
('default_water_rate', '45.00'),
('default_electric_rate', '14.50'),
('default_penalty_rate', '250.00'),
('payment_gcash_name', 'JUAN DELA CRUZ'),
('payment_gcash_number', '0917-555-8921'),
('payment_bank_info', 'BDO Unibank | Account: 0048-1290-3456 (Juan Dela Cruz)')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

INSERT INTO `users` (`id`, `name`, `username`, `email`, `password`, `role`, `phone`, `status`) VALUES
(1, 'System Super Administrator', 'superadmin', 'jeckdetera07@gmail.com', 'superadmin123', 'super_admin', '09170000001', 'active'),
(2, 'Engr. Juan Dela Cruz (Landlord)', 'admin', 'juan.delacruz@gmail.com', 'admin123', 'admin', '09175558921', 'active'),
(3, 'Maria Clara Santos', 'maria.santos', 'maria.santos@gmail.com', 'tenant123', 'tenant', '09181234567', 'active'),
(4, 'Joshua David Ramos', 'joshua.ramos', 'joshua.ramos@gmail.com', 'tenant123', 'tenant', '09228889999', 'active')
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`), `password` = VALUES(`password`), `name` = VALUES(`name`);

INSERT INTO `properties` (`id`, `owner_id`, `name`, `address`, `total_floors`, `description`) VALUES
(1, 2, 'Casa Mabini Residences', '108 Mabini St., Brgy. Malate, Manila', 4, 'Modern 4-storey residential apartment building with gated security and sub-meters.')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `units` (`id`, `property_id`, `unit_number`, `floor_level`, `unit_type`, `monthly_rent`, `security_deposit`, `water_rate_per_unit`, `electric_rate_per_kwh`, `status`, `max_occupants`, `description`) VALUES
(1, 1, 'Unit 101', 1, 'Studio', 8500.00, 8500.00, 45.00, 14.50, 'occupied', 2, 'Ground floor studio unit with private kitchen sink and bathroom.'),
(2, 1, 'Unit 102', 1, '1-Bedroom', 12000.00, 12000.00, 45.00, 14.50, 'occupied', 3, 'Spacious 1-bedroom unit with built-in closet and laundry area.'),
(3, 1, 'Unit 201', 2, 'Studio', 8500.00, 8500.00, 45.00, 14.50, 'vacant', 2, '2nd floor studio unit with balcony view, ready for occupancy.'),
(4, 1, 'Unit 202', 2, '2-Bedroom', 16500.00, 16500.00, 45.00, 14.50, 'occupied', 4, 'Corner 2-bedroom deluxe unit, air-conditioned ready.'),
(5, 1, 'Unit 301', 3, 'Studio Deluxe', 9500.00, 9500.00, 45.00, 14.50, 'maintenance', 2, 'Undergoing repainting and water line fixture maintenance.'),
(6, 1, 'Unit 302', 3, '1-Bedroom', 12500.00, 12500.00, 45.00, 14.50, 'vacant', 3, 'Clean 3rd floor unit with panoramic street view.')
ON DUPLICATE KEY UPDATE `monthly_rent` = VALUES(`monthly_rent`);

INSERT INTO `tenants` (`id`, `user_id`, `unit_id`, `first_name`, `last_name`, `email`, `phone`, `emergency_contact_name`, `emergency_contact_phone`, `id_type`, `id_number`, `lease_start`, `lease_end`, `rent_due_day`, `deposit_paid`, `advance_paid`, `status`, `notes`) VALUES
(1, 3, 1, 'Maria Clara', 'Santos', 'maria.santos@gmail.com', '09181234567', 'Roberto Santos (Father)', '09171112233', 'Passport', 'P9876543A', '2026-01-01', '2026-12-31', 5, 8500.00, 8500.00, 'active', 'Reliable tenant, corporate employee in BGC.'),
(2, 4, 2, 'Joshua David', 'Ramos', 'joshua.ramos@gmail.com', '09228889999', 'Elena Ramos (Mother)', '09194445566', 'Driver License', 'N01-22-123456', '2026-02-15', '2027-02-14', 15, 12000.00, 12000.00, 'active', 'Software engineer working remotely.')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

INSERT INTO `utility_readings` (`id`, `unit_id`, `tenant_id`, `billing_month`, `prev_water_reading`, `curr_water_reading`, `water_consumption`, `water_rate`, `water_amount`, `prev_electric_reading`, `curr_electric_reading`, `electric_consumption`, `electric_rate`, `electric_amount`, `reading_date`) VALUES
(1, 1, 1, '2026-08', 120.00, 128.50, 8.50, 45.00, 382.50, 1450.00, 1515.00, 65.00, 14.50, 942.50, '2026-08-01'),
(2, 2, 2, '2026-08', 95.00, 106.00, 11.00, 45.00, 495.00, 2100.00, 2190.00, 90.00, 14.50, 1305.00, '2026-08-01')
ON DUPLICATE KEY UPDATE `water_amount` = VALUES(`water_amount`);

INSERT INTO `invoices` (`id`, `invoice_number`, `tenant_id`, `unit_id`, `utility_reading_id`, `billing_period_start`, `billing_period_end`, `due_date`, `rent_amount`, `water_amount`, `electricity_amount`, `penalty_amount`, `other_charges`, `other_charges_notes`, `total_amount`, `paid_amount`, `balance`, `status`) VALUES
(1, 'INV-202608-001', 1, 1, 1, '2026-08-01', '2026-08-31', '2026-08-05', 8500.00, 382.50, 942.50, 0.00, 0.00, NULL, 9825.00, 9825.00, 0.00, 'paid'),
(2, 'INV-202608-002', 2, 2, 2, '2026-08-15', '2026-09-14', '2026-08-20', 12000.00, 495.00, 1305.00, 0.00, 150.00, 'Garbage Collection Fee', 13950.00, 0.00, 13950.00, 'overdue')
ON DUPLICATE KEY UPDATE `balance` = VALUES(`balance`);

INSERT INTO `payments` (`id`, `invoice_id`, `tenant_id`, `payment_reference`, `amount`, `payment_method`, `transaction_ref_no`, `payment_date`, `status`, `notes`, `received_by`) VALUES
(1, 1, 1, 'PAY-20260805-001', 9825.00, 'GCash', 'GC-8934789123', '2026-08-04', 'confirmed', 'Paid on time via GCash express send.', 2)
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

INSERT INTO `maintenance_requests` (`id`, `unit_id`, `tenant_id`, `issue_title`, `category`, `description`, `priority`, `status`, `assigned_to`, `repair_cost`, `requested_date`, `resolved_date`, `notes`) VALUES
(1, 1, 1, 'Kitchen Faucet Dripping', 'Plumbing', 'The sink faucet in the kitchen continues to drip slowly even when tightly closed.', 'low', 'completed', 'Mang Berto (Plumber)', 350.00, '2026-08-02', '2026-08-03', 'Replaced rubber washer and seal.'),
(2, 2, 2, 'AC Unit Making Buzzing Sound', 'Electrical', 'The split-type aircon creates a vibrating buzzing sound during night mode.', 'medium', 'in_progress', 'CoolBreeze HVAC Techs', 0.00, '2026-08-24', NULL, 'Technician scheduled for inspection tomorrow morning.')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `details`, `ip_address`) VALUES
(1, 2, 'LOGIN', 'Admin Juan Dela Cruz logged in successfully.', '127.0.0.1'),
(2, 2, 'INVOICE_GENERATED', 'Generated August 2026 billing invoice INV-202608-001 for Unit 101.', '127.0.0.1'),
(3, 2, 'PAYMENT_RECORDED', 'Confirmed GCash payment PAY-20260805-001 amounting to ₱9,825.00.', '127.0.0.1')
ON DUPLICATE KEY UPDATE `action` = VALUES(`action`);
