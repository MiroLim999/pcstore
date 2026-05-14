-- ============================================================
-- PC Store — FULL DATABASE SETUP
-- Single file: creates database, tables, and seeds all data.
-- Import this ONE file in phpMyAdmin to get everything running.
-- ============================================================

-- admin@pcstore.com
-- password
-- cashier@pcstore.com
-- password
-- client@pcstore.com
-- password

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `pcstore`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `pcstore`;

-- ─── USERS ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `role` ENUM('client','cashier','admin','superadmin') NOT NULL DEFAULT 'client',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB;

-- ─── LOGIN ATTEMPTS (rate limiting) ──────────────────────────
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_login_email_time` (`email`, `attempted_at`)
) ENGINE=InnoDB;

-- ─── CATEGORIES ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(20) NOT NULL UNIQUE,
    `label` VARCHAR(50) NOT NULL,
    `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── PRODUCTS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT UNSIGNED NOT NULL,
    `sku` VARCHAR(50) NOT NULL UNIQUE,
    `barcode` VARCHAR(50) DEFAULT NULL,
    `name` VARCHAR(200) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `stock_qty` INT NOT NULL DEFAULT 0,
    `reserved_qty` INT NOT NULL DEFAULT 0,
    `low_stock_threshold` INT NOT NULL DEFAULT 5,
    `image_url` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_products_category` (`category_id`),
    INDEX `idx_products_sku` (`sku`),
    INDEX `idx_products_barcode` (`barcode`),
    INDEX `idx_products_active` (`is_active`),
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ─── PRODUCT SPECS (key-value) ────────────────────────────────
CREATE TABLE IF NOT EXISTS `product_specs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `spec_key` VARCHAR(50) NOT NULL,
    `spec_value` VARCHAR(200) NOT NULL,
    `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    INDEX `idx_specs_product` (`product_id`),
    CONSTRAINT `fk_specs_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── COMPATIBILITY ATTRIBUTES ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `compatibility_attrs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL UNIQUE,
    `socket` VARCHAR(20) DEFAULT NULL,
    `tdp` INT DEFAULT NULL,
    `cores` INT DEFAULT NULL,
    `threads` INT DEFAULT NULL,
    `boost_clock` DECIMAL(3,1) DEFAULT NULL,
    `supported_sockets` JSON DEFAULT NULL,
    `max_tdp` INT DEFAULT NULL,
    `form_factor` VARCHAR(10) DEFAULT NULL,
    `memory_type` VARCHAR(10) DEFAULT NULL,
    `m2_slots` INT DEFAULT NULL,
    `max_memory_speed` INT DEFAULT NULL,
    `memory_speed` INT DEFAULT NULL,
    `memory_capacity` INT DEFAULT NULL,
    `storage_interface` VARCHAR(10) DEFAULT NULL,
    `storage_form_factor` VARCHAR(10) DEFAULT NULL,
    `requires_m2` TINYINT(1) DEFAULT 0,
    `wattage` INT DEFAULT NULL,
    `supported_form_factors` JSON DEFAULT NULL,
    `gaming_tier` TINYINT DEFAULT NULL,
    `gaming_score` INT DEFAULT NULL,
    `base_fps` INT DEFAULT NULL,
    CONSTRAINT `fk_compat_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── BUILDS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `builds` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `status` ENUM('submitted','paid','expired','cancelled') NOT NULL DEFAULT 'submitted',
    `pickup_code` CHAR(6) NOT NULL,
    `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `reserved_until` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_builds_user` (`user_id`),
    INDEX `idx_builds_status` (`status`),
    INDEX `idx_builds_pickup` (`pickup_code`),
    CONSTRAINT `fk_builds_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ─── BUILD ITEMS ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `build_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `build_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `category_slug` VARCHAR(20) NOT NULL,
    `price_snapshot` DECIMAL(10,2) NOT NULL,
    `qty` INT NOT NULL DEFAULT 1,
    INDEX `idx_build_items_build` (`build_id`),
    CONSTRAINT `fk_build_items_build` FOREIGN KEY (`build_id`) REFERENCES `builds`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_build_items_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB;

-- ─── ORDERS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `build_id` INT UNSIGNED DEFAULT NULL,
    `cashier_id` INT UNSIGNED DEFAULT NULL,
    `receipt_no` VARCHAR(30) NOT NULL UNIQUE,
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `change_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_method` ENUM('cash','card','split') NOT NULL DEFAULT 'cash',
    `status` ENUM('completed','voided','refunded') NOT NULL DEFAULT 'completed',
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_orders_build` (`build_id`),
    INDEX `idx_orders_cashier` (`cashier_id`),
    INDEX `idx_orders_date` (`created_at`),
    CONSTRAINT `fk_orders_build` FOREIGN KEY (`build_id`) REFERENCES `builds`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_orders_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ─── ORDER ITEMS ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `price_snapshot` DECIMAL(10,2) NOT NULL,
    `cost_snapshot` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `qty` INT NOT NULL DEFAULT 1,
    `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    INDEX `idx_order_items_order` (`order_id`),
    CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB;

-- ─── INVENTORY TRANSACTIONS (ledger) ─────────────────────────
CREATE TABLE IF NOT EXISTS `inventory_transactions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `type` ENUM('sale','restock','reserve','release','adjust','return') NOT NULL,
    `qty` INT NOT NULL,
    `reference_type` VARCHAR(30) DEFAULT NULL,
    `reference_id` INT UNSIGNED DEFAULT NULL,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `reason` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_inv_product` (`product_id`),
    INDEX `idx_inv_type` (`type`),
    INDEX `idx_inv_date` (`created_at`),
    CONSTRAINT `fk_inv_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`),
    CONSTRAINT `fk_inv_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ─── AUDIT LOG ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(50) NOT NULL,
    `entity_type` VARCHAR(30) NOT NULL,
    `entity_id` INT UNSIGNED DEFAULT NULL,
    `old_value` JSON DEFAULT NULL,
    `new_value` JSON DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_entity` (`entity_type`, `entity_id`),
    INDEX `idx_audit_user` (`user_id`),
    INDEX `idx_audit_date` (`created_at`)
) ENGINE=InnoDB;

-- ─── SETTINGS (key-value) ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(50) NOT NULL UNIQUE,
    `setting_value` TEXT DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================
-- SEED DATA
-- ============================================================

-- ─── SETTINGS ─────────────────────────────────────────────────
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('store_name', 'PC Store'),
('store_address', '123 Tech Street, Manila, Philippines'),
('currency_symbol', '₱'),
('tax_rate', '0.12'),
('reservation_hours', '48'),
('receipt_footer', 'Thank you for shopping at PC Store!');

-- ─── CATEGORIES (8 builder slots) ────────────────────────────
INSERT INTO `categories` (`slug`, `label`, `sort_order`) VALUES
('cpu', 'Processor', 1),
('cooler', 'CPU Cooler', 2),
('mobo', 'Motherboard', 3),
('ram', 'Memory (RAM)', 4),
('gpu', 'Graphics Card', 5),
('ssd', 'Storage', 6),
('psu', 'Power Supply', 7),
('case', 'Case', 8);

-- ─── USERS ────────────────────────────────────────────────────
-- All passwords: "password"
INSERT INTO `users` (`email`, `password_hash`, `name`, `phone`, `role`) VALUES
('admin@pcstore.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin', '09171234567', 'superadmin'),
('cashier@pcstore.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Main Cashier', '09179876543', 'cashier'),
('client@pcstore.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan Dela Cruz', '09181112233', 'client');

-- ─── PRODUCTS ─────────────────────────────────────────────────
INSERT INTO `products` (`category_id`, `sku`, `barcode`, `name`, `description`, `price`, `cost`, `stock_qty`, `image_url`) VALUES
-- CPUs
(1, 'CPU-R5-5500', '4730000000001', 'AMD Ryzen 5 5500', '6-Core, 12-Thread, 4.2 GHz Boost, AM4', 7490.00, 5800.00, 15, 'assets/img/builder/cpu_ryzen.png'),
(1, 'CPU-R5-5600X', '4730000000002', 'AMD Ryzen 5 5600X', '6-Core, 12-Thread, 4.6 GHz Boost, AM4', 9990.00, 7500.00, 10, 'assets/img/builder/cpu_ryzen.png'),
(1, 'CPU-R7-5800X', '4730000000003', 'AMD Ryzen 7 5800X', '8-Core, 16-Thread, 4.7 GHz Boost, AM4', 13990.00, 10500.00, 8, 'assets/img/builder/cpu_ryzen.png'),
(1, 'CPU-I5-12400F', '4730000000004', 'Intel Core i5-12400F', '6-Core, 12-Thread, 4.4 GHz Boost, LGA1700', 8490.00, 6200.00, 12, 'assets/img/builder/cpu_ryzen.png'),
(1, 'CPU-I7-12700K', '4730000000005', 'Intel Core i7-12700K', '12-Core, 20-Thread, 5.0 GHz Boost, LGA1700', 17490.00, 13000.00, 6, 'assets/img/builder/cpu_ryzen.png'),
-- Coolers
(2, 'COOL-WRAITH', '4730000000010', 'AMD Wraith Stealth', 'Stock Cooler, Quiet & Efficient, 65W TDP', 0.00, 0.00, 50, 'assets/img/builder/cpu_cooler.png'),
(2, 'COOL-H212', '4730000000011', 'Cooler Master Hyper 212', '120mm Fan, 150W TDP, Direct Contact', 1750.00, 1200.00, 20, 'assets/img/builder/cpu_cooler.png'),
(2, 'COOL-NHD15', '4730000000012', 'Noctua NH-D15', 'Dual Tower, 250W TDP, Ultra Silent', 4990.00, 3800.00, 8, 'assets/img/builder/cpu_cooler.png'),
(2, 'COOL-AF34', '4730000000013', 'ARCTIC Freezer 34', '120mm Fan, 200W TDP, Push-Pull', 2000.00, 1400.00, 15, 'assets/img/builder/cpu_cooler.png'),
(2, 'COOL-DR4', '4730000000014', 'be quiet! Dark Rock 4', '135mm Fan, 200W TDP, Silent Wings 3', 3700.00, 2800.00, 10, 'assets/img/builder/cpu_cooler.png'),
-- Motherboards
(3, 'MB-AM4-MATX', '4730000000020', 'AM4 Micro-ATX Generic', 'AM4, mATX, DDR4', 4490.00, 3200.00, 10, 'assets/img/builder/motherboard.png'),
(3, 'MB-B550M-A', '4730000000021', 'ASUS PRIME B550M-A', 'AM4, mATX, PCIe 4.0', 5490.00, 4000.00, 12, 'assets/img/builder/motherboard.png'),
(3, 'MB-B550-TOM', '4730000000022', 'MSI MAG B550 TOMAHAWK', 'AM4, ATX, PCIe 4.0 M.2', 7990.00, 6000.00, 8, 'assets/img/builder/motherboard.png'),
(3, 'MB-B550-AORUS', '4730000000023', 'GIGABYTE B550 AORUS Pro', 'AM4, ATX, 2.5G LAN', 8990.00, 6800.00, 6, 'assets/img/builder/motherboard.png'),
(3, 'MB-B450M-PRO4', '4730000000024', 'ASRock B450M PRO4', 'AM4, mATX, DDR4', 3990.00, 2800.00, 14, 'assets/img/builder/motherboard.png'),
(3, 'MB-B660M-A', '4730000000025', 'MSI PRO B660M-A', 'LGA1700, mATX, DDR4', 6490.00, 4800.00, 10, 'assets/img/builder/motherboard.png'),
(3, 'MB-B660-PLUS', '4730000000026', 'ASUS PRIME B660-PLUS D4', 'LGA1700, ATX, DDR4', 6990.00, 5200.00, 9, 'assets/img/builder/motherboard.png'),
-- RAM
(4, 'RAM-TF-16', '4730000000030', 'TEAMGROUP T-FORCE 16GB', 'DDR4 3200MHz, 2x8GB, XMP 2.0', 2750.00, 2000.00, 20, 'assets/img/builder/ram_memory.png'),
(4, 'RAM-COR-16', '4730000000031', 'Corsair Vengeance LPX 16GB', 'DDR4 3200MHz, 2x8GB, Low Profile', 3250.00, 2400.00, 18, 'assets/img/builder/ram_memory.png'),
(4, 'RAM-GSK-16', '4730000000032', 'G.Skill Trident Z Neo 16GB', 'DDR4 3600MHz, 2x8GB, RGB', 4490.00, 3200.00, 12, 'assets/img/builder/ram_memory.png'),
(4, 'RAM-KNG-32', '4730000000033', 'Kingston Fury Beast 32GB', 'DDR4 3200MHz, 2x16GB, XMP 3.0', 5500.00, 4000.00, 10, 'assets/img/builder/ram_memory.png'),
(4, 'RAM-CRU-16', '4730000000034', 'Crucial Ballistix 16GB', 'DDR4 3600MHz, 2x8GB, Aluminium', 3500.00, 2600.00, 15, 'assets/img/builder/ram_memory.png'),
-- GPUs
(5, 'GPU-3060-12', '4730000000040', 'GIGABYTE RTX 3060 12GB', '12GB GDDR6, DLSS / Ray Tracing, 192-bit', 16490.00, 12500.00, 8, 'assets/img/builder/gpu_rtx3060.png'),
(5, 'GPU-3060TI', '4730000000041', 'GeForce RTX 3060 Ti', '8GB GDDR6, DLSS / Ray Tracing, 256-bit', 19990.00, 15000.00, 6, 'assets/img/builder/gpu_rtx3060.png'),
(5, 'GPU-6700XT', '4730000000042', 'AMD Radeon RX 6700 XT', '12GB GDDR6, FSR / Ray Tracing, 192-bit', 18490.00, 14000.00, 7, 'assets/img/builder/gpu_rtx3060.png'),
(5, 'GPU-3070', '4730000000043', 'GeForce RTX 3070', '8GB GDDR6, DLSS 2 / Ray Tracing, 256-bit', 24990.00, 19000.00, 5, 'assets/img/builder/gpu_rtx3060.png'),
(5, 'GPU-6600', '4730000000044', 'AMD Radeon RX 6600', '8GB GDDR6, FSR, 128-bit', 12490.00, 9500.00, 10, 'assets/img/builder/gpu_rtx3060.png'),
-- Storage
(6, 'SSD-NVME-500', '4730000000050', 'NVMe SSD 500GB', 'M.2 NVMe, High Speed, 3500 MB/s', 3250.00, 2400.00, 20, 'assets/img/builder/nvme_ssd.png'),
(6, 'SSD-870EVO-1T', '4730000000051', 'Samsung 870 EVO 1TB', 'SATA SSD, 560 MB/s, 2.5 inch Form', 4490.00, 3200.00, 15, 'assets/img/builder/nvme_ssd.png'),
(6, 'SSD-SN850-1T', '4730000000052', 'WD Black SN850 1TB', 'M.2 NVMe PCIe 4, 7000 MB/s, RGB', 7490.00, 5500.00, 8, 'assets/img/builder/nvme_ssd.png'),
(6, 'SSD-P3-1T', '4730000000053', 'Crucial P3 1TB NVMe', 'M.2 NVMe PCIe 3, 3500 MB/s, DRAM-less', 3750.00, 2700.00, 18, 'assets/img/builder/nvme_ssd.png'),
(6, 'HDD-BAR-2T', '4730000000054', 'Seagate Barracuda 2TB HDD', 'SATA HDD, 7200 RPM, 3.5 inch Form', 2750.00, 2000.00, 12, 'assets/img/builder/nvme_ssd.png'),
-- PSUs
(7, 'PSU-650-BRZ', '4730000000060', '650W 80+ Bronze', 'Reliable Power, 80+ Bronze, ATX', 3990.00, 2800.00, 15, 'assets/img/builder/psu_650w.png'),
(7, 'PSU-EVGA-650', '4730000000061', 'EVGA SuperNOVA 650 G5', 'Full Modular, 80+ Gold, 650W', 5490.00, 4000.00, 10, 'assets/img/builder/psu_650w.png'),
(7, 'PSU-RM750X', '4730000000062', 'Corsair RM750x', 'Full Modular, 80+ Gold, 750W', 6490.00, 4800.00, 8, 'assets/img/builder/psu_650w.png'),
(7, 'PSU-GX750', '4730000000063', 'SeaSonic Focus GX-750', 'Full Modular, 80+ Gold, 750W', 6990.00, 5200.00, 7, 'assets/img/builder/psu_650w.png'),
(7, 'PSU-TT-550', '4730000000064', 'Thermaltake Toughpower 550W', 'Semi Modular, 80+ Gold, 550W', 4490.00, 3200.00, 12, 'assets/img/builder/psu_650w.png'),
-- Cases
(8, 'CASE-WHT-MATX', '4730000000070', 'White mATX Case', 'Mesh Front, Good Airflow, mATX', 3490.00, 2400.00, 12, 'assets/img/builder/pc_case_white.png'),
(8, 'CASE-H510', '4730000000071', 'NZXT H510', 'Tempered Glass, ATX, Cable Mgmt', 3990.00, 2800.00, 10, 'assets/img/builder/pc_case_white.png'),
(8, 'CASE-POP-AIR', '4730000000072', 'Fractal Design Pop Air', 'Mesh Front, ATX, Excellent Airflow', 4200.00, 3000.00, 9, 'assets/img/builder/pc_case_white.png'),
(8, 'CASE-205M', '4730000000073', 'Lian Li LANCOOL 205M', 'Mesh Panels, mATX, Dual Chamber', 4490.00, 3200.00, 8, 'assets/img/builder/pc_case_white.png'),
(8, 'CASE-4000D', '4730000000074', 'Corsair 4000D Airflow', 'Mesh Front, ATX, High Airflow', 5200.00, 3800.00, 7, 'assets/img/builder/pc_case_white.png');

-- ─── COMPATIBILITY ATTRIBUTES ─────────────────────────────────
-- CPUs (IDs 1-5)
INSERT INTO `compatibility_attrs` (`product_id`, `socket`, `tdp`, `cores`, `threads`, `boost_clock`, `gaming_tier`, `gaming_score`) VALUES
(1, 'AM4', 65, 6, 12, 4.2, 2, 78),
(2, 'AM4', 65, 6, 12, 4.6, 3, 92),
(3, 'AM4', 105, 8, 16, 4.7, 4, 100),
(4, 'LGA1700', 65, 6, 12, 4.4, 3, 94),
(5, 'LGA1700', 125, 12, 20, 5.0, 5, 112);

-- Coolers (IDs 6-10)
INSERT INTO `compatibility_attrs` (`product_id`, `supported_sockets`, `max_tdp`) VALUES
(6, '["AM4"]', 65),
(7, '["AM4","LGA1700"]', 150),
(8, '["AM4","LGA1700"]', 250),
(9, '["AM4","LGA1700"]', 200),
(10, '["AM4","LGA1700"]', 200);

-- Motherboards (IDs 11-17)
INSERT INTO `compatibility_attrs` (`product_id`, `socket`, `form_factor`, `memory_type`, `m2_slots`, `max_memory_speed`) VALUES
(11, 'AM4', 'mATX', 'DDR4', 1, 3600),
(12, 'AM4', 'mATX', 'DDR4', 2, 4400),
(13, 'AM4', 'ATX', 'DDR4', 2, 4400),
(14, 'AM4', 'ATX', 'DDR4', 2, 4733),
(15, 'AM4', 'mATX', 'DDR4', 1, 3200),
(16, 'LGA1700', 'mATX', 'DDR4', 2, 4800),
(17, 'LGA1700', 'ATX', 'DDR4', 2, 5333);

-- RAM (IDs 18-22)
INSERT INTO `compatibility_attrs` (`product_id`, `memory_type`, `memory_speed`, `memory_capacity`) VALUES
(18, 'DDR4', 3200, 16),
(19, 'DDR4', 3200, 16),
(20, 'DDR4', 3600, 16),
(21, 'DDR4', 3200, 32),
(22, 'DDR4', 3600, 16);

-- GPUs (IDs 23-27)
INSERT INTO `compatibility_attrs` (`product_id`, `tdp`, `gaming_tier`, `gaming_score`, `base_fps`) VALUES
(23, 170, 3, 82, 92),
(24, 200, 4, 100, 112),
(25, 230, 4, 104, 118),
(26, 220, 5, 118, 135),
(27, 132, 2, 70, 78);

-- Storage (IDs 28-32)
INSERT INTO `compatibility_attrs` (`product_id`, `storage_interface`, `storage_form_factor`, `requires_m2`) VALUES
(28, 'NVMe', 'M.2', 1),
(29, 'SATA', '2.5"', 0),
(30, 'NVMe', 'M.2', 1),
(31, 'NVMe', 'M.2', 1),
(32, 'SATA', '3.5"', 0);

-- PSUs (IDs 33-37)
INSERT INTO `compatibility_attrs` (`product_id`, `wattage`) VALUES
(33, 650),
(34, 650),
(35, 750),
(36, 750),
(37, 550);

-- Cases (IDs 38-42)
INSERT INTO `compatibility_attrs` (`product_id`, `supported_form_factors`) VALUES
(38, '["mATX"]'),
(39, '["ATX","mATX"]'),
(40, '["ATX","mATX"]'),
(41, '["mATX"]'),
(42, '["ATX","mATX"]');

-- ─── PRODUCT SPECS (display specs for catalog/builder) ────────
INSERT INTO `product_specs` (`product_id`, `spec_key`, `spec_value`, `sort_order`) VALUES
(1, 'cores_threads', '6C / 12T', 1), (1, 'boost', '4.2 GHz Boost', 2), (1, 'socket', 'AM4', 3),
(2, 'cores_threads', '6C / 12T', 1), (2, 'boost', '4.6 GHz Boost', 2), (2, 'socket', 'AM4', 3),
(3, 'cores_threads', '8C / 16T', 1), (3, 'boost', '4.7 GHz Boost', 2), (3, 'socket', 'AM4', 3),
(4, 'cores_threads', '6C / 12T', 1), (4, 'boost', '4.4 GHz Boost', 2), (4, 'socket', 'LGA1700', 3),
(5, 'cores_threads', '12C / 20T', 1), (5, 'boost', '5.0 GHz Boost', 2), (5, 'socket', 'LGA1700', 3);

-- ============================================================
-- SETUP COMPLETE
-- ============================================================
