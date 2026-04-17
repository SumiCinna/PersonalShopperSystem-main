-- PO + BO (Damaged/Expired) module tables for Personal Shopper System
-- Safe to run on existing pss_db (uses IF NOT EXISTS)

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `suppliers` (
  `supplier_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `contact_person` varchar(120) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`supplier_id`),
  KEY `idx_suppliers_status` (`status`),
  UNIQUE KEY `uq_supplier_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `supplier_products` (
  `supplier_product_id` int NOT NULL AUTO_INCREMENT,
  `supplier_id` int NOT NULL,
  `product_id` int NOT NULL,
  `supplier_sku` varchar(100) DEFAULT NULL,
  `supplier_price` decimal(10,2) DEFAULT NULL,
  `lead_time_days` int NOT NULL DEFAULT 0,
  `is_preferred` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`supplier_product_id`),
  UNIQUE KEY `uq_supplier_product` (`supplier_id`,`product_id`),
  KEY `idx_supplier_products_product` (`product_id`),
  CONSTRAINT `fk_supplier_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_supplier_products_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `po_id` int NOT NULL AUTO_INCREMENT,
  `po_number` varchar(50) NOT NULL,
  `supplier_id` int NOT NULL,
  `status` enum('pending_approval','approved','rejected','ordered','shipped','delivered','partially_received','completed','cancelled') NOT NULL DEFAULT 'pending_approval',
  `order_date` date NOT NULL,
  `expected_delivery` date DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` text,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`po_id`),
  UNIQUE KEY `uq_po_number` (`po_number`),
  KEY `idx_po_status` (`status`),
  KEY `idx_po_supplier` (`supplier_id`),
  KEY `idx_po_created_by` (`created_by`),
  KEY `idx_po_approved_by` (`approved_by`),
  CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_po_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_po_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `po_item_id` int NOT NULL AUTO_INCREMENT,
  `po_id` int NOT NULL,
  `product_id` int NOT NULL,
  `ordered_qty` int NOT NULL,
  `received_qty` int NOT NULL DEFAULT 0,
  `rejected_qty` int NOT NULL DEFAULT 0,
  `unit_cost` decimal(10,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL,
  PRIMARY KEY (`po_item_id`),
  KEY `idx_po_items_po` (`po_id`),
  KEY `idx_po_items_product` (`product_id`),
  CONSTRAINT `fk_po_items_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_po_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `po_receivings` (
  `receiving_id` int NOT NULL AUTO_INCREMENT,
  `po_id` int NOT NULL,
  `received_by` int DEFAULT NULL,
  `received_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `remarks` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`receiving_id`),
  KEY `idx_receiving_po` (`po_id`),
  KEY `idx_receiving_user` (`received_by`),
  CONSTRAINT `fk_receiving_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_receiving_user` FOREIGN KEY (`received_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `po_receiving_items` (
  `receiving_item_id` int NOT NULL AUTO_INCREMENT,
  `receiving_id` int NOT NULL,
  `po_item_id` int NOT NULL,
  `product_id` int NOT NULL,
  `received_qty` int NOT NULL DEFAULT 0,
  `accepted_qty` int NOT NULL DEFAULT 0,
  `rejected_qty` int NOT NULL DEFAULT 0,
  `batch_number` varchar(80) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `reject_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`receiving_item_id`),
  KEY `idx_receiving_items_receiving` (`receiving_id`),
  KEY `idx_receiving_items_po_item` (`po_item_id`),
  KEY `idx_receiving_items_product` (`product_id`),
  CONSTRAINT `fk_receiving_items_receiving` FOREIGN KEY (`receiving_id`) REFERENCES `po_receivings` (`receiving_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_receiving_items_po_item` FOREIGN KEY (`po_item_id`) REFERENCES `purchase_order_items` (`po_item_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_receiving_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `supplier_returns` (
  `return_id` int NOT NULL AUTO_INCREMENT,
  `po_id` int NOT NULL,
  `po_item_id` int DEFAULT NULL,
  `product_id` int NOT NULL,
  `supplier_id` int NOT NULL,
  `rejected_qty` int NOT NULL,
  `reason` enum('expired','damaged_packaging','wrong_item','near_expiry','other') NOT NULL DEFAULT 'other',
  `reason_notes` varchar(255) DEFAULT NULL,
  `status` enum('pending_return','returned_to_supplier','resolved') NOT NULL DEFAULT 'pending_return',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resolved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`return_id`),
  KEY `idx_supplier_returns_po` (`po_id`),
  KEY `idx_supplier_returns_product` (`product_id`),
  KEY `idx_supplier_returns_supplier` (`supplier_id`),
  KEY `idx_supplier_returns_status` (`status`),
  KEY `idx_supplier_returns_creator` (`created_by`),
  CONSTRAINT `fk_supplier_returns_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_supplier_returns_po_item` FOREIGN KEY (`po_item_id`) REFERENCES `purchase_order_items` (`po_item_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_supplier_returns_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_supplier_returns_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_supplier_returns_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional seed suppliers (edit/remove as needed)
INSERT INTO `suppliers` (`name`, `contact_person`, `phone`, `email`, `address`)
SELECT * FROM (
  SELECT 'Local Farm', 'Farmer John', '09123456789', 'farm@local.com', 'Agriculture Road, Fresh City'
) AS tmp
WHERE NOT EXISTS (
  SELECT 1 FROM `suppliers` WHERE `name` = 'Local Farm'
) LIMIT 1;

INSERT INTO `suppliers` (`name`, `contact_person`, `phone`, `email`, `address`)
SELECT * FROM (
  SELECT 'Local Product Supplier', 'Jane Supplier', '09987654321', 'supply@local.com', 'Warehouse Blvd, Market City'
) AS tmp
WHERE NOT EXISTS (
  SELECT 1 FROM `suppliers` WHERE `name` = 'Local Product Supplier'
) LIMIT 1;
