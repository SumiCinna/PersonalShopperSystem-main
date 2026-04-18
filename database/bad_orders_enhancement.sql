-- Bad Orders (Return to Vendor) Enhancement Tables
-- Enhances the existing PO + BO module with comprehensive return tracking
-- Safe to run on existing pss_db (uses IF NOT EXISTS / ALTER)

SET NAMES utf8mb4;

-- ========================================
-- 1. ENHANCE supplier_returns table
-- ========================================
-- Add resolution tracking and return area management fields

ALTER TABLE `supplier_returns` 
ADD COLUMN `resolution_type` enum('replace','credit_memo','pending') NOT NULL DEFAULT 'pending' AFTER `status`,
ADD COLUMN `replacement_po_id` int DEFAULT NULL AFTER `resolution_type`,
ADD COLUMN `credit_memo_amount` decimal(12,2) DEFAULT NULL AFTER `replacement_po_id`,
ADD COLUMN `return_notes` text DEFAULT NULL AFTER `credit_memo_amount`,
ADD COLUMN `sent_to_supplier_at` datetime DEFAULT NULL AFTER `return_notes`,
ADD COLUMN `received_by_supplier_at` datetime DEFAULT NULL AFTER `sent_to_supplier_at`,
ADD COLUMN `sent_by_user` int DEFAULT NULL AFTER `received_by_supplier_at`,
ADD COLUMN `resolved_by_user` int DEFAULT NULL AFTER `sent_by_user`,
ADD KEY `idx_supplier_returns_resolution` (`resolution_type`),
ADD KEY `idx_supplier_returns_replacement_po` (`replacement_po_id`),
ADD KEY `idx_supplier_returns_sent_by` (`sent_by_user`),
ADD KEY `idx_supplier_returns_resolved_by` (`resolved_by_user`);

-- ========================================
-- 2. CREATE return_area_inventory table
-- ========================================
-- Tracks rejected items segregated in return area

CREATE TABLE IF NOT EXISTS `return_area_inventory` (
  `return_area_id` int NOT NULL AUTO_INCREMENT,
  `return_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL,
  `location` varchar(100) DEFAULT NULL COMMENT 'Physical location in return area e.g., Shelf A1',
  `status` enum('pending_return','sent_to_supplier','resolved') NOT NULL DEFAULT 'pending_return',
  `batch_number` varchar(80) DEFAULT NULL,
  `reject_reason` varchar(255) DEFAULT NULL,
  `reject_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`return_area_id`),
  KEY `idx_return_area_return` (`return_id`),
  KEY `idx_return_area_product` (`product_id`),
  KEY `idx_return_area_status` (`status`),
  KEY `idx_return_area_location` (`location`),
  CONSTRAINT `fk_return_area_return` FOREIGN KEY (`return_id`) REFERENCES `supplier_returns` (`return_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_return_area_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 3. CREATE supplier_return_updates table
-- ========================================
-- Audit trail for return status changes and communications

CREATE TABLE IF NOT EXISTS `supplier_return_updates` (
  `update_id` int NOT NULL AUTO_INCREMENT,
  `return_id` int NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `update_type` enum('status_change','resolution_set','note_added','pickup_scheduled','item_received') NOT NULL,
  `notes` text DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`update_id`),
  KEY `idx_return_updates_return` (`return_id`),
  KEY `idx_return_updates_type` (`update_type`),
  KEY `idx_return_updates_user` (`updated_by`),
  CONSTRAINT `fk_return_updates_return` FOREIGN KEY (`return_id`) REFERENCES `supplier_returns` (`return_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_return_updates_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 4. CREATE bad_orders_summary table (optional)
-- ========================================
-- For quick analytics and reporting

CREATE TABLE IF NOT EXISTS `bad_orders_summary` (
  `summary_id` int NOT NULL AUTO_INCREMENT,
  `supplier_id` int NOT NULL,
  `total_rejected_qty` int NOT NULL DEFAULT 0,
  `total_rejected_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expired_count` int DEFAULT 0,
  `damaged_count` int DEFAULT 0,
  `wrong_item_count` int DEFAULT 0,
  `near_expiry_count` int DEFAULT 0,
  `resolved_count` int DEFAULT 0,
  `pending_count` int DEFAULT 0,
  `last_reject_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`summary_id`),
  UNIQUE KEY `uq_supplier_summary` (`supplier_id`),
  KEY `idx_summary_supplier` (`supplier_id`),
  CONSTRAINT `fk_summary_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
