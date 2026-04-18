-- MySQL dump 10.13  Distrib 8.0.43, for Win64 (x86_64)
--
-- Host: localhost    Database: pss_db
-- ------------------------------------------------------
-- Server version	8.0.43

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `action` enum('add','update','delete') NOT NULL,
  `product_id` int DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `field_changed` varchar(100) DEFAULT NULL,
  `old_value` text,
  `new_value` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bad_orders_summary`
--

DROP TABLE IF EXISTS `bad_orders_summary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bad_orders_summary` (
  `summary_id` int NOT NULL AUTO_INCREMENT,
  `supplier_id` int NOT NULL,
  `total_rejected_qty` int NOT NULL DEFAULT '0',
  `total_rejected_value` decimal(12,2) NOT NULL DEFAULT '0.00',
  `expired_count` int DEFAULT '0',
  `damaged_count` int DEFAULT '0',
  `wrong_item_count` int DEFAULT '0',
  `near_expiry_count` int DEFAULT '0',
  `resolved_count` int DEFAULT '0',
  `pending_count` int DEFAULT '0',
  `last_reject_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`summary_id`),
  UNIQUE KEY `uq_supplier_summary` (`supplier_id`),
  KEY `idx_summary_supplier` (`supplier_id`),
  CONSTRAINT `fk_summary_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bad_orders_summary`
--

LOCK TABLES `bad_orders_summary` WRITE;
/*!40000 ALTER TABLE `bad_orders_summary` DISABLE KEYS */;
/*!40000 ALTER TABLE `bad_orders_summary` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cart`
--

DROP TABLE IF EXISTS `cart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cart` (
  `cart_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cart_id`),
  UNIQUE KEY `unique_user_product` (`user_id`,`product_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart`
--

LOCK TABLES `cart` WRITE;
/*!40000 ALTER TABLE `cart` DISABLE KEYS */;
/*!40000 ALTER TABLE `cart` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_verifications`
--

DROP TABLE IF EXISTS `email_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_verifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_email_verif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_verifications`
--

LOCK TABLES `email_verifications` WRITE;
/*!40000 ALTER TABLE `email_verifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_verifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_logs`
--

DROP TABLE IF EXISTS `inventory_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `user_id` int NOT NULL,
  `quantity_added` int NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `product_id` (`product_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `inventory_logs_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  CONSTRAINT `inventory_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_logs`
--

LOCK TABLES `inventory_logs` WRITE;
/*!40000 ALTER TABLE `inventory_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `invoice_id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `invoice_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `grand_total` decimal(10,2) NOT NULL,
  `issued_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`invoice_id`),
  UNIQUE KEY `invoice_no` (`invoice_no`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `fk_invoice_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `order_item_id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL,
  `price_at_checkout` decimal(10,2) NOT NULL,
  PRIMARY KEY (`order_item_id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `order_id` int NOT NULL AUTO_INCREMENT,
  `tracking_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_status` enum('pending','paid','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `pickup_datetime` datetime DEFAULT NULL,
  `payment_type` enum('full','partial','partial_50','partial_30') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full',
  `upfront_payment` decimal(10,2) NOT NULL DEFAULT '0.00',
  `balance_due` decimal(10,2) NOT NULL DEFAULT '0.00',
  `online_reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_status` enum('pending','processing','ready','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_by` int DEFAULT NULL,
  `payment_intent_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_paid_online` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `tracking_no` (`tracking_no`),
  KEY `user_id` (`user_id`),
  KEY `fk_order_cashier` (`processed_by`),
  CONSTRAINT `fk_order_cashier` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `reset_id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reset_id`),
  UNIQUE KEY `uq_token_hash` (`token_hash`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `po_receiving_items`
--

DROP TABLE IF EXISTS `po_receiving_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `po_receiving_items` (
  `receiving_item_id` int NOT NULL AUTO_INCREMENT,
  `receiving_id` int NOT NULL,
  `po_item_id` int NOT NULL,
  `product_id` int NOT NULL,
  `received_qty` int NOT NULL DEFAULT '0',
  `accepted_qty` int NOT NULL DEFAULT '0',
  `rejected_qty` int NOT NULL DEFAULT '0',
  `batch_number` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `reject_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manufacture_date` date DEFAULT NULL,
  PRIMARY KEY (`receiving_item_id`),
  KEY `idx_receiving_items_receiving` (`receiving_id`),
  KEY `idx_receiving_items_po_item` (`po_item_id`),
  KEY `idx_receiving_items_product` (`product_id`),
  CONSTRAINT `fk_receiving_items_po_item` FOREIGN KEY (`po_item_id`) REFERENCES `purchase_order_items` (`po_item_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_receiving_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_receiving_items_receiving` FOREIGN KEY (`receiving_id`) REFERENCES `po_receivings` (`receiving_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `po_receiving_items`
--

LOCK TABLES `po_receiving_items` WRITE;
/*!40000 ALTER TABLE `po_receiving_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `po_receiving_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `po_receivings`
--

DROP TABLE IF EXISTS `po_receivings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `po_receivings` (
  `receiving_id` int NOT NULL AUTO_INCREMENT,
  `po_id` int NOT NULL,
  `received_by` int DEFAULT NULL,
  `received_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `remarks` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`receiving_id`),
  KEY `idx_receiving_po` (`po_id`),
  KEY `idx_receiving_user` (`received_by`),
  CONSTRAINT `fk_receiving_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_receiving_user` FOREIGN KEY (`received_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `po_receivings`
--

LOCK TABLES `po_receivings` WRITE;
/*!40000 ALTER TABLE `po_receivings` DISABLE KEYS */;
/*!40000 ALTER TABLE `po_receivings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_batches`
--

DROP TABLE IF EXISTS `product_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_batches` (
  `batch_id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL COMMENT 'Links to the specific product',
  `po_id` int DEFAULT NULL COMMENT 'Links to the PO (if you want to track where it came from)',
  `batch_number` varchar(100) NOT NULL COMMENT 'e.g., BATCH-001, could be provided by supplier',
  `manufacture_date` date DEFAULT NULL,
  `expiry_date` date NOT NULL COMMENT 'Used for FEFO calculation',
  `initial_quantity` int NOT NULL COMMENT 'Total quantity ordered and arrived',
  `remaining_quantity` int NOT NULL COMMENT 'Quantity left available for customers to buy',
  `status` enum('Pending','Released','Exhausted','Expired') NOT NULL DEFAULT 'Pending' COMMENT 'Default is Pending until you release them',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`batch_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_expiry_date` (`expiry_date`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_batches`
--

LOCK TABLES `product_batches` WRITE;
/*!40000 ALTER TABLE `product_batches` DISABLE KEYS */;
INSERT INTO `product_batches` VALUES (1,1,NULL,'BATCH-1-01','2026-04-18','2026-05-19',29,29,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(2,3,NULL,'BATCH-3-01','2026-04-18','2026-05-19',199,199,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(3,4,NULL,'BATCH-4-01','2026-04-18','2026-05-19',82,82,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(4,5,NULL,'BATCH-5-01','2026-04-18','2026-05-19',111,111,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(5,6,NULL,'BATCH-6-01','2026-04-18','2026-05-19',90,90,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(6,7,NULL,'BATCH-7-01','2026-04-18','2026-05-19',199,199,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(7,8,NULL,'BATCH-8-01','2026-04-18','2026-05-19',150,150,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(8,9,NULL,'BATCH-9-01','2026-04-18','2026-05-19',500,500,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(9,10,NULL,'BATCH-10-01','2026-04-18','2026-05-19',180,180,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(10,11,NULL,'BATCH-11-01','2026-04-18','2026-05-19',75,75,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(11,12,NULL,'BATCH-12-01','2026-04-18','2026-05-19',110,110,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(12,13,NULL,'BATCH-13-01','2026-04-18','2026-05-19',95,95,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(13,15,NULL,'BATCH-15-01','2026-04-18','2026-05-19',133,133,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(14,16,NULL,'BATCH-16-01','2026-04-18','2026-05-19',199,199,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(15,17,NULL,'BATCH-17-01','2026-04-18','2026-05-19',157,157,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(16,18,NULL,'BATCH-18-01','2026-04-18','2026-05-19',219,219,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(17,19,NULL,'BATCH-19-01','2026-04-18','2026-05-19',138,138,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(18,20,NULL,'BATCH-20-01','2026-04-18','2026-05-19',110,110,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(19,22,NULL,'BATCH-22-01','2026-04-18','2026-05-19',24,24,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(20,23,NULL,'BATCH-23-01','2026-04-18','2026-05-19',180,180,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(21,24,NULL,'BATCH-24-01','2026-04-18','2026-05-19',71,71,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(22,25,NULL,'BATCH-25-01','2026-04-18','2026-05-19',76,76,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(23,26,NULL,'BATCH-26-01','2026-04-18','2026-05-19',85,85,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(24,27,NULL,'BATCH-27-01','2026-04-18','2026-05-19',69,69,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(25,28,NULL,'BATCH-28-01','2026-04-18','2026-05-19',137,137,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(26,29,NULL,'BATCH-29-01','2026-04-18','2026-05-19',9,9,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(27,30,NULL,'BATCH-30-01','2026-04-18','2026-05-19',5,5,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(28,31,NULL,'BATCH-31-01','2026-04-18','2026-05-19',20,20,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(29,52,NULL,'BATCH-52-01','2026-04-18','2026-05-19',26,26,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(30,54,NULL,'BATCH-54-01','2026-04-18','2026-05-19',25,25,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(31,55,NULL,'BATCH-55-01','2026-04-18','2026-05-19',22,22,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(32,56,NULL,'BATCH-56-01','2026-04-18','2026-05-19',15,15,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(33,58,NULL,'BATCH-58-01','2026-04-18','2026-05-19',40,40,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(34,59,NULL,'BATCH-59-01','2026-04-18','2026-05-19',35,35,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(35,60,NULL,'BATCH-60-01','2026-04-18','2026-05-19',50,50,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(36,61,NULL,'BATCH-61-01','2026-04-18','2026-05-19',50,50,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26'),(37,62,NULL,'BATCH-62-01','2026-04-18','2026-05-19',100,100,'Released','2026-04-18 20:26:26','2026-04-18 20:26:26');
/*!40000 ALTER TABLE `product_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `product_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `brand` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `sku` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `discount_price` decimal(10,2) DEFAULT NULL,
  `unit_value` decimal(10,2) DEFAULT NULL,
  `unit_measure` enum('g','kg','ml','L','oz','pc','pack','box') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `low_stock_threshold` int NOT NULL DEFAULT '10',
  `image_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `pcs_per_box` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`product_id`),
  UNIQUE KEY `sku` (`sku`),
  KEY `idx_category` (`category`),
  KEY `idx_status` (`status`),
  KEY `idx_price` (`price`)
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'Bear Brand Fortified Powdered Milk','A trusted staple for Filipino families, Bear Brand Fortified Powdered Milk provides essential vitamins and minerals to support strong bones and a healthy immune system.','Bear Brand','Dairy',83.00,'BEAR-MILK-050G',100.00,NULL,50.00,'g',29,50,'https://cdn.store-assets.com/s/377840/i/53513617.jpeg','active','2026-02-23 18:04:25','2026-04-17 18:47:54',12),(3,'Piattos Potato Crisps Cheese','Piattos delivers a uniquely thin, crispy potato crisp in a rich cheese flavor that keeps you reaching for more with every satisfying bite.','JACK\'n JILL','Snacks',28.00,'PIATTOS-CHIPS-085G',34.50,NULL,85.00,'g',199,50,'https://ever.ph/cdn/shop/files/9000010103-Piattos-Potato-Crisps-Cheese-85g-201124.jpg?v=1614307767&width=823','active','2026-02-24 08:16:07','2026-04-17 18:47:54',12),(4,'Spam Classic','Fully cooked canned pork meat. The classic breakfast favorite.','Spam','Canned Goods',160.00,'SPM-CLS-340G',195.00,NULL,340.00,'g',82,20,'https://ever.ph/cdn/shop/files/9000003193-Spam-Luncheon-Meat-Regular-Classic-12oz-210106_6796ff87-d6db-45c7-a4fe-c699ad746444.jpg?v=1772343802&width=823','active','2026-02-25 15:50:19','2026-04-17 18:47:54',12),(5,'Argentina Corned Beef','Deliciously flavorful and filling corned beef for your pandesal.','Argentina','Canned Goods',32.00,'ARG-CBF-150G',39.00,NULL,150.00,'g',111,30,'https://ever.ph/cdn/shop/files/9000003128-Argentina-Corned-Beef-150g-210106_84069cc7-dbca-407e-a893-fbcefa7e00b6.jpg?v=1759465213&width=823','active','2026-02-25 15:50:19','2026-04-17 18:47:54',12),(6,'San Marino Corned Tuna','The perfect combination of corned beef taste and tuna health benefits.','San Marino','Canned Goods',35.00,'SMR-CTN-180G',42.00,NULL,180.00,'g',90,20,'https://ever.ph/cdn/shop/files/100000045698-San-Marino-Corned-Tuna-Easy-Open-180g-210406_06a6b521-d0d9-4bb7-8c26-599278980de8.jpg?v=1768713784&width=823','active','2026-02-25 15:50:19','2026-04-17 18:47:54',12),(7,'Mega Sardines in Tomato Sauce','Premium quality sardines in rich tomato sauce. Catch to can in 12 hours.','Mega','Canned Goods',19.00,'MGA-SAR-155G',23.00,NULL,155.00,'g',199,50,'https://ever.ph/cdn/shop/files/9000003423-Mega-Sardines-in-Tomato-Sauce-Easy-Open-155g-210406_6e57234c-8c2e-4213-8ef8-fa705f95a464.jpg?v=1771133246&width=823','active','2026-02-25 15:50:19','2026-04-17 18:47:54',12),(8,'Nissin Seafood Cup Noodles','Instant cup noodles with rich and savory seafood broth.','Nissin','Beverages',35.00,'NIS-SEA-070G',42.00,NULL,70.00,'g',150,40,'https://ever.ph/cdn/shop/files/9000009932-Nissin-Cup-Noodles-Seafood-60g-210415.jpg?v=1619082354&width=823','active','2026-02-25 15:50:19','2026-04-17 18:47:54',12),(9,'Maggi Magic Sarap','All-in-one seasoning granules for that extra umami.','Maggi','Condiments',4.50,'MAG-SAR-008G',6.00,NULL,8.00,'g',500,100,'https://i5.walmartimages.com/seo/Maggi-Magic-Sarap-All-in-One-Seasoning-8g-12pc_4acf4da8-ba52-411c-970d-5ab883855cff.0e5df89b72a6b2b9180148a9556264d4.jpeg','active','2026-02-25 15:50:19','2026-04-17 18:47:54',12),(10,'Knorr Sinigang sa Sampaloc Mix','Tamarind soup base for authentic, perfectly sour sinigang.','Knorr','Condiments',18.00,'KNO-SIN-040G',22.00,NULL,40.00,'g',180,40,'https://ever.ph/cdn/shop/files/9000010774-Knorr-Sinigang-sa-Sampalok-22g-220704_04952265-f2bf-410a-a60d-c6e1599ddc6c.jpg?v=1752991716&width=823','active','2026-02-25 15:50:19','2026-04-17 18:47:54',12),(11,'Mang Tomas All-Purpose Sauce','Classic lechon sauce perfect for any fried or roasted dish.','Mang Tomas','Condiments',38.00,'MTO-APS-330G',45.00,NULL,330.00,'g',75,15,'https://ever.ph/cdn/shop/files/9000008794-Mang-Tomas-All-Around-Sarsa-Regular-325g-10.5-101320_a6e98bc8-7ccb-46b7-98d9-0fcc897ef613.jpg?v=1770956522&width=823','active','2026-02-25 15:50:19','2026-04-17 18:47:54',12),(12,'UFC Banana Ketchup','Tamis-anghang banana ketchup, a Filipino dining staple.','UFC','Condiments',30.00,'UFC-KET-320G',36.00,NULL,320.00,'g',110,25,'https://ever.ph/cdn/shop/files/100000006967-UFC-Tamis-Anghang-Banana-Catsup-Spouch-320g-210125.jpg?v=1614315385&width=823','active','2026-02-25 15:50:19','2026-04-17 18:47:54',12),(13,'Eden Cheese','Creamy and melt-in-your-mouth processed filled cheese.','Eden','Dairy',45.00,'EDN-CHS-165G',54.00,NULL,165.00,'g',95,20,'https://ever.ph/cdn/shop/files/9000007109-Eden-Cheese-Original-160g-240415.jpg?v=1713756753&width=823','active','2026-02-25 15:50:19','2026-04-17 18:47:54',12),(15,'Del Monte Tomato Sauce','100% real tomatoes for your sweet Filipino-style spaghetti.','Del Monte','Condiments',32.00,'DEL-TOM-250G',39.00,NULL,250.00,'g',133,30,'https://ever.ph/cdn/shop/files/9000008556-Del-Monte-Tomato-Sauce-Original_Style-Pouch-250g-210414_4e5e8de7-7bae-49b8-a220-0a159e542692.jpg?v=1767244313&width=823','active','2026-02-25 15:50:19','2026-04-17 18:47:54',12),(16,'Coca-Cola Original Taste 1.5L','Refreshing classic cola beverage, perfect for sharing.','Coca-Cola','Beverages',60.00,'COK-ORG-1.5L',75.00,NULL,1.50,'L',199,50,'https://ever.ph/cdn/shop/files/9000002369-Coke-Coca-Cola-Original-Taste-Less-Sugar-PET-1.5L-230206.jpg?v=1676528483&width=823','active','2026-02-25 15:50:19','2026-04-17 18:47:54',12),(17,'C2 Green Tea Apple','Brewed from 100% natural green tea leaves with a hint of apple.','Universal Robina','Beverages',22.00,'C2-APP-500ML',28.00,NULL,500.00,'ml',157,40,'https://ever.ph/cdn/shop/files/9000002272-C2-Apple-Flavored-Green-Tea-455ml-250604.jpg?v=1749029755&width=823','active','2026-02-25 15:50:19','2026-04-17 18:47:54',12),(18,'Oishi Prawn Crackers','Classic spicy and crispy prawn crackers.','Oishi','Snacks',12.00,'OIS-PRA-060G',16.00,NULL,60.00,'g',219,50,'https://ever.ph/cdn/shop/files/9000010290-Oishi_Prawn_Crackers_60g-200909_f695e55a-bb08-439a-9aa0-4a5e7d944a16.jpg?v=1725269937&width=823','active','2026-02-25 15:50:19','2026-04-17 18:47:54',12),(19,'Boy Bawang Cornick Garlic','Crispy, extremely garlicky, and flavorful fried corn nuts.','Boy Bawang','Snacks',14.00,'BOY-GAR-100G',18.00,NULL,100.00,'g',138,30,'https://ever.ph/cdn/shop/files/100000003412-Boy-Bawang-Cornick-Garlic-90g-210712_0c8f46e6-9574-4b81-9e99-dcdaa38d918d.jpg?v=1768963420&width=823','active','2026-02-25 15:50:19','2026-04-17 18:47:54',12),(20,'SkyFlakes Crackers','The undisputed number 1 cracker in the Philippines.','Monde Nissin','Snacks',45.00,'SKY-CRA-250G',55.00,NULL,250.00,'g',110,25,'https://ever.ph/cdn/shop/files/9000005957-SkyFlakes-Crackers-10x25g-221003_73a26cca-5c10-49ce-95fb-ecdeb19efac6.jpg?v=1772343845&width=823','active','2026-02-25 15:50:19','2026-04-17 18:47:54',12),(22,'Gardenia Classic White Bread','Soft, freshly baked white sliced bread. A breakfast classic.','Gardenia','Beverages',68.00,'GAR-WHI-400G',78.00,NULL,400.00,'g',24,10,'https://ever.ph/cdn/shop/files/9000000120-Gardenia-White-Bread-Classic-Loaf-600g-230614_9d09c386-ae4a-462a-ba4e-516d42e15667.jpg?v=1770000747&width=823','active','2026-02-25 15:50:19','2026-04-17 18:47:54',12),(23,'Safeguard Pure White Bar Soap','Antibacterial family bath soap that removes 99.9% of germs.','Safeguard','Beverages',38.00,'SAF-WHI-130G',45.00,NULL,130.00,'g',180,40,'https://ever.ph/cdn/shop/files/9000015237-Safeguard-Pure-White-Large-Bar-115g-250731.jpg?v=1754019346&width=823','active','2026-02-25 15:50:19','2026-04-17 18:47:54',12),(24,'Creamsilk Standout Straight','Hair conditioner for straight and impeccably smooth hair.','Creamsilk','Beverages',110.00,'CRE-STR-180ML',135.00,NULL,180.00,'ml',71,15,'https://shophygiene.com.ph/cdn/shop/files/FOP_50kb_62731174_CREAMSILKROULTREBORNSTRT48X6X22MLx6.jpg?v=1747117010&width=1946','active','2026-02-25 15:50:19','2026-04-18 17:28:29',12),(25,'Head & Shoulders Cool Menthol','Anti-dandruff shampoo with an incredibly cooling sensation.','Head & Shoulders','Beverages',125.00,'HNS-MEN-170ML',150.00,NULL,170.00,'ml',76,20,'https://ever.ph/cdn/shop/files/100000063537-Head-_-Shoulders-Anti-Dandruff-Shampoo-Cool-Menthol-170ml-260305.jpg?v=1772694859&width=823','active','2026-02-25 15:50:19','2026-04-18 18:02:13',12),(26,'Surf Cherry Blossom Laundry Powder 6x65g','Effective stain removal with a long-lasting floral scent.','Surf','Condiments',65.00,'SRF-CHE-800G',80.00,NULL,390.00,'g',85,25,'https://ever.ph/cdn/shop/files/100000091039-Surf-Ultra-Power-Liquid-Detergent-Cherry-Blossom-6x64ml-220608.jpg?v=1655103694&width=823','active','2026-02-25 15:50:19','2026-04-17 18:47:54',12),(27,'Downy Antibac Fabric Conditioner','Softens clothes and prevents bacterial growth and kulob odor.','Downy','Beverages',105.00,'DWN-ANT-680ML',130.00,NULL,680.00,'ml',69,15,'https://ever.ph/cdn/shop/files/100000086679-Downy-Perfume-Antibac-Power-Kontra-Kulob-750ml-250918_9fb9f4ed-c657-4d34-aab2-41fda2d603cd.jpg?v=1761642348&width=823','active','2026-02-25 15:50:19','2026-04-18 18:14:34',17),(28,'Green Cross Isopropyl Alcohol','70% solution antiseptic and disinfectant.','Green Cross','Beverages',70.00,'GRE-ISO-500ML',85.00,65.00,500.00,'ml',137,100,'https://ever.ph/cdn/shop/files/9000016032-Green-Cross-70_-Isoprophyl-Alcohol-with-Moisturizer-500ml-201012_55f6cb18-d432-40b2-a6a6-f816858f0ace.jpg?v=1731663444&width=823','active','2026-02-25 15:50:19','2026-04-17 18:47:54',12),(29,'Whole Chicken','Frozen chicken from Magnolia. A trusted local brand for poultry. Chickens are raised in climate-controlled environments, unexposed to urban sprawl thus decreasing the threat of disease.','Magnolia','Meat & Poultry',200.00,'W-CKEN-1.7KG',240.00,NULL,1.70,'kg',9,10,'https://s3.ap-southeast-1.amazonaws.com/control-center.builtamart.com/public/products/3641-009-22-2025-120959-278.jpg?rand=0.8160431322287965','active','2026-03-17 16:31:59','2026-04-17 18:47:54',12),(30,'Chicken Breast','Freshly chilled chicken from Magnolia. A trusted local brand for poultry. Chickens are raised in climate-controlled environments, unexposed to urban sprawl thus decreasing the threat of disease.','Magnolia','Meat & Poultry',160.00,'CHK-BRST-800G',190.00,NULL,800.00,'g',5,10,'https://control-center.builtamart.com/public-generated/products/6208-008-14-2025-120859-040-sd.jpg','active','2026-03-17 16:39:34','2026-04-17 18:47:54',12),(31,'Chicken Wings','Freshly chilled chicken from Magnolia. A trusted local brand for poultry. Chickens are raised in climate-controlled environments, unexposed to urban sprawl thus decreasing the threat of disease.','Magnolia','Meat & Poultry',150.00,'CHK-WGS-1KG',185.00,NULL,1.00,'kg',20,10,'https://control-center.builtamart.com/public-generated/products/3546-008-14-2025-120826-953-sd.jpg','active','2026-03-17 16:54:11','2026-04-17 18:47:54',12),(52,'Apple','Sweet, crisp, and freshly harvested red apples — perfect for snacking, fruit salads, or lunchbox treats for the whole family.','Dole','Fresh Produce',15.00,'APPLE-DDE',20.00,NULL,NULL,NULL,26,10,'https://pngimg.com/uploads/apple/apple_PNG12405.png','active','2026-03-17 17:10:55','2026-04-17 18:47:54',12),(53,'Banana','Ripe, naturally sweet Dole bananas packed with potassium and energy — a nutritious grab-and-go snack for any time of the day.','Dole','Fresh Produce',8.00,'BANANA-DDE',12.00,NULL,5.00,'g',0,10,'https://pngimg.com/uploads/banana/banana_PNG835.png','inactive','2026-03-17 17:10:55','2026-04-17 18:47:54',12),(54,'Orange','Bright, juicy Sunkist oranges bursting with Vitamin C — great for fresh-squeezed juice, fruit platters, or healthy snacking.','Sunkist','Fresh Produce',18.00,'ORANGE-SUN',25.00,NULL,NULL,NULL,25,10,'https://pngimg.com/uploads/orange/orange_PNG785.png','active','2026-03-17 17:10:55','2026-04-17 18:47:54',12),(55,'Mango','Sourced from the mango capital of the Philippines, these premium Guimaras mangoes are exceptionally sweet, fragrant, and melt-in-your-mouth delicious.','Gumaras Fresh','Fresh Produce',3.00,'MANGO-GUM',5.00,NULL,NULL,NULL,22,5,'https://pngimg.com/uploads/mango/mango_PNG9184.png','active','2026-03-17 17:10:55','2026-04-18 14:55:19',12),(56,'Pineapple','Freshly harvested Del Monte pineapples with a perfectly balanced sweet and tangy flavor — ideal for eating fresh, juicing, or adding to your favorite dishes.','Del Monte','Fresh Produce',40.00,'PINEAPPLE-DM',55.00,NULL,NULL,NULL,15,5,'https://pngimg.com/uploads/pineapple/pineapple_PNG2744.png','active','2026-03-17 17:10:55','2026-04-17 18:47:54',12),(57,'Watermelon','Locally grown sweet watermelons, refreshingly hydrating and perfect for beating the Philippine heat — best served chilled and shared with family.','Local Farm','Fresh Produce',60.00,'WATERMELON-LF',80.00,NULL,NULL,NULL,0,3,'https://pngimg.com/uploads/watermelon/watermelon_PNG2654.png','active','2026-03-17 17:10:55','2026-04-17 18:47:54',12),(58,'Tomato','Farm-fresh ripe tomatoes with a naturally bright, tangy flavor — an everyday kitchen essential for sautéing, soups, sauces, and salads.','Local Farm','Fresh Produce',10.00,'TOMATO-LF',14.00,NULL,NULL,NULL,40,15,'https://pngimg.com/uploads/tomato/tomato_PNG12591.png','active','2026-03-17 17:10:55','2026-04-17 18:47:54',12),(59,'Onion','Freshly sourced yellow onions with a robust, savory aroma that forms the flavor base of countless Filipino dishes like sinigang, adobo, and more.','Local Farm','Fresh Produce',12.00,'ONION-LF',18.00,NULL,NULL,NULL,35,15,'https://pngimg.com/uploads/onion/onion_PNG99215.png','active','2026-03-17 17:10:55','2026-04-17 18:47:54',12),(60,'Garlic','Plump, pungent garlic bulbs that add deep, aromatic flavor to any dish — a must-have ingredient in every Filipino pantry.','Local Farm','Fresh Produce',15.00,'GARLIC-LF',22.00,NULL,NULL,NULL,50,10,'https://pngimg.com/uploads/garlic/garlic_PNG12799.png','active','2026-03-17 17:10:55','2026-04-17 18:47:54',12),(61,'Potato','Fresh Baguio potatoes with a smooth texture and mild flavor — versatile for frying, boiling, mashing, or adding to hearty soups and stews.','Baguio Fresh','Fresh Produce',20.00,'POTATO-BF',29.00,NULL,NULL,NULL,50,15,'https://pngimg.com/uploads/potato/potato_PNG7078.png','active','2026-03-17 17:10:55','2026-04-17 18:47:54',12),(62,'Lady\'s Choice Mayonnaise Sachet','Creamy and tangy Lady\'s Choice Mayonnaise in a convenient sachet size — perfect for sandwiches, salads, fruit salad dressing, and dipping sauces.','Lady\'s Choice','Condiments',30.00,'LADY-MAYO-80ML',35.25,NULL,80.00,'ml',100,10,'https://www.srssulit.com/wp-content/uploads/products/5793-1.png','active','2026-04-06 12:57:44','2026-04-17 18:47:54',12);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_order_items`
--

DROP TABLE IF EXISTS `purchase_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_order_items` (
  `po_item_id` int NOT NULL AUTO_INCREMENT,
  `po_id` int NOT NULL,
  `product_id` int NOT NULL,
  `ordered_qty` int NOT NULL,
  `order_type` enum('retail','wholesale') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'retail',
  `box_quantity` int NOT NULL DEFAULT '0',
  `received_qty` int NOT NULL DEFAULT '0',
  `rejected_qty` int NOT NULL DEFAULT '0',
  `unit_cost` decimal(10,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL,
  PRIMARY KEY (`po_item_id`),
  KEY `idx_po_items_po` (`po_id`),
  KEY `idx_po_items_product` (`product_id`),
  CONSTRAINT `fk_po_items_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_po_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_order_items`
--

LOCK TABLES `purchase_order_items` WRITE;
/*!40000 ALTER TABLE `purchase_order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_orders`
--

DROP TABLE IF EXISTS `purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_orders` (
  `po_id` int NOT NULL AUTO_INCREMENT,
  `po_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_id` int NOT NULL,
  `status` enum('pending_approval','approved','rejected','ordered','shipped','delivered','partially_received','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_approval',
  `order_date` date NOT NULL,
  `expected_delivery` date DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `grand_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `credit_applied` decimal(12,2) DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `rejection_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `manufacture_date` date DEFAULT NULL,
  PRIMARY KEY (`po_id`),
  UNIQUE KEY `uq_po_number` (`po_number`),
  KEY `idx_po_status` (`status`),
  KEY `idx_po_supplier` (`supplier_id`),
  KEY `idx_po_created_by` (`created_by`),
  KEY `idx_po_approved_by` (`approved_by`),
  CONSTRAINT `fk_po_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_po_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_orders`
--

LOCK TABLES `purchase_orders` WRITE;
/*!40000 ALTER TABLE `purchase_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `return_area_inventory`
--

DROP TABLE IF EXISTS `return_area_inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `return_area_inventory` (
  `return_area_id` int NOT NULL AUTO_INCREMENT,
  `return_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL,
  `location` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Physical location in return area e.g., Shelf A1',
  `status` enum('pending_return','sent_to_supplier','resolved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_return',
  `batch_number` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reject_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reject_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`return_area_id`),
  KEY `idx_return_area_return` (`return_id`),
  KEY `idx_return_area_product` (`product_id`),
  KEY `idx_return_area_status` (`status`),
  KEY `idx_return_area_location` (`location`),
  CONSTRAINT `fk_return_area_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_return_area_return` FOREIGN KEY (`return_id`) REFERENCES `supplier_returns` (`return_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `return_area_inventory`
--

LOCK TABLES `return_area_inventory` WRITE;
/*!40000 ALTER TABLE `return_area_inventory` DISABLE KEYS */;
/*!40000 ALTER TABLE `return_area_inventory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shift_logs`
--

DROP TABLE IF EXISTS `shift_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shift_logs` (
  `shift_id` int NOT NULL AUTO_INCREMENT,
  `cashier_id` int NOT NULL,
  `cashier_name` varchar(100) NOT NULL,
  `login_time` datetime NOT NULL,
  `logout_time` datetime DEFAULT NULL,
  `total_sales` decimal(10,2) DEFAULT '0.00',
  `date` date NOT NULL,
  PRIMARY KEY (`shift_id`),
  KEY `cashier_id` (`cashier_id`),
  CONSTRAINT `shift_logs_ibfk_1` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shift_logs`
--

LOCK TABLES `shift_logs` WRITE;
/*!40000 ALTER TABLE `shift_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `shift_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_credit_logs`
--

DROP TABLE IF EXISTS `supplier_credit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supplier_credit_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `supplier_id` int NOT NULL,
  `return_id` int DEFAULT NULL,
  `po_id` int DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `action` enum('credit_added','credit_used') NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_credit_logs`
--

LOCK TABLES `supplier_credit_logs` WRITE;
/*!40000 ALTER TABLE `supplier_credit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `supplier_credit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_products`
--

DROP TABLE IF EXISTS `supplier_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supplier_products` (
  `supplier_product_id` int NOT NULL AUTO_INCREMENT,
  `supplier_id` int NOT NULL,
  `product_id` int NOT NULL,
  `supplier_sku` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_price` decimal(10,2) DEFAULT NULL,
  `lead_time_days` int NOT NULL DEFAULT '0',
  `is_preferred` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`supplier_product_id`),
  UNIQUE KEY `uq_supplier_product` (`supplier_id`,`product_id`),
  KEY `idx_supplier_products_product` (`product_id`),
  CONSTRAINT `fk_supplier_products_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_supplier_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_products`
--

LOCK TABLES `supplier_products` WRITE;
/*!40000 ALTER TABLE `supplier_products` DISABLE KEYS */;
/*!40000 ALTER TABLE `supplier_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_return_updates`
--

DROP TABLE IF EXISTS `supplier_return_updates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supplier_return_updates` (
  `update_id` int NOT NULL AUTO_INCREMENT,
  `return_id` int NOT NULL,
  `old_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `update_type` enum('status_change','resolution_set','note_added','pickup_scheduled','item_received') COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `updated_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`update_id`),
  KEY `idx_return_updates_return` (`return_id`),
  KEY `idx_return_updates_type` (`update_type`),
  KEY `idx_return_updates_user` (`updated_by`),
  CONSTRAINT `fk_return_updates_return` FOREIGN KEY (`return_id`) REFERENCES `supplier_returns` (`return_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_return_updates_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_return_updates`
--

LOCK TABLES `supplier_return_updates` WRITE;
/*!40000 ALTER TABLE `supplier_return_updates` DISABLE KEYS */;
/*!40000 ALTER TABLE `supplier_return_updates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_returns`
--

DROP TABLE IF EXISTS `supplier_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supplier_returns` (
  `return_id` int NOT NULL AUTO_INCREMENT,
  `po_id` int NOT NULL,
  `po_item_id` int DEFAULT NULL,
  `product_id` int NOT NULL,
  `supplier_id` int NOT NULL,
  `rejected_qty` int NOT NULL,
  `reason` enum('expired','damaged_packaging','wrong_item','near_expiry','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `reason_notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending_return','returned_to_supplier','resolved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_return',
  `resolution_type` enum('replace','credit_memo','pending') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `replacement_po_id` int DEFAULT NULL,
  `credit_memo_amount` decimal(12,2) DEFAULT NULL,
  `return_notes` text COLLATE utf8mb4_unicode_ci,
  `sent_to_supplier_at` datetime DEFAULT NULL,
  `received_by_supplier_at` datetime DEFAULT NULL,
  `sent_by_user` int DEFAULT NULL,
  `resolved_by_user` int DEFAULT NULL,
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
  KEY `fk_supplier_returns_po_item` (`po_item_id`),
  KEY `idx_supplier_returns_resolution` (`resolution_type`),
  KEY `idx_supplier_returns_replacement_po` (`replacement_po_id`),
  KEY `idx_supplier_returns_sent_by` (`sent_by_user`),
  KEY `idx_supplier_returns_resolved_by` (`resolved_by_user`),
  CONSTRAINT `fk_supplier_returns_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_supplier_returns_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_supplier_returns_po_item` FOREIGN KEY (`po_item_id`) REFERENCES `purchase_order_items` (`po_item_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_supplier_returns_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_supplier_returns_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_returns`
--

LOCK TABLES `supplier_returns` WRITE;
/*!40000 ALTER TABLE `supplier_returns` DISABLE KEYS */;
/*!40000 ALTER TABLE `supplier_returns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suppliers` (
  `supplier_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_type` enum('Manufacturer','Wholesaler','Distributor') COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplied_categories` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `credit_balance` decimal(12,2) DEFAULT '0.00',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`supplier_id`),
  UNIQUE KEY `uq_supplier_name` (`name`),
  KEY `idx_suppliers_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
INSERT INTO `suppliers` VALUES (1,'Local Farm','Manufacturer','[\"Fresh Produce\"]','Local Farmer',NULL,'09171234567','farm@local.com','Quezon City, PH',200.00,'active','2026-04-17 05:08:29','2026-04-18 19:23:20'),(2,'General Local Supplier','Manufacturer',NULL,'Jane Supplier',NULL,'09179876543','supply@local.com','Bulacan, PH',67.00,'active','2026-04-17 05:08:29','2026-04-18 18:55:11'),(3,'Beverages Supply','Manufacturer','Beverages','Beverage Company',NULL,'09171234567','supplybeverage@email.com','Quezon City, PH',0.00,'active','2026-04-17 15:36:41','2026-04-17 15:36:41'),(4,'Watsons','Manufacturer','[\"Snacks\"]','Vaughn Maco',NULL,'0992139504','vaughmaco.97@gmail.com','Quezon City, NCR',0.00,'active','2026-04-17 16:20:10','2026-04-17 16:20:10');
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `transaction_id` int NOT NULL AUTO_INCREMENT,
  `invoice_id` int NOT NULL,
  `cashier_id` int NOT NULL,
  `transaction_type` enum('payment','refund') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'payment',
  `payment_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `amount_paid` decimal(10,2) NOT NULL,
  `cash_tendered` decimal(10,2) DEFAULT NULL,
  `change_given` decimal(10,2) DEFAULT NULL,
  `reference_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`transaction_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `cashier_id` (`cashier_id`),
  CONSTRAINT `fk_transaction_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_transaction_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`invoice_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_profiles`
--

DROP TABLE IF EXISTS `user_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_profiles` (
  `profile_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `firstname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `middlename` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `surname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `suffix` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`profile_id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_profiles`
--

LOCK TABLES `user_profiles` WRITE;
/*!40000 ALTER TABLE `user_profiles` DISABLE KEYS */;
INSERT INTO `user_profiles` VALUES (1,1,'Ralp Anjelo','Mendoza','Armario','Jr.','+639971376355'),(2,2,'Hanjel','','Gatchi','','+639971376355'),(3,5,'Andrea','','Asierto',NULL,'09971376355'),(4,6,'Niña',NULL,'Manzanero',NULL,'09185637682'),(5,7,'John Noel','D.A.','Orano',NULL,'09923139504'),(6,8,'inventory','','1','','09923139504'),(25,12,'JN','DA','ORANO',NULL,'09999999999'),(26,13,'sadsaa','adsa','asdawdsa',NULL,'09999999222');
/*!40000 ALTER TABLE `user_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('customer','admin','cashier','inventory') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer',
  `terms_agreed` tinyint(1) DEFAULT '0',
  `status` enum('active','inactive','suspended') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `email_verified` tinyint(1) NOT NULL DEFAULT '0',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Hanjel','$2y$10$G/5krO6C0KxKuKB7p16N2OCdzvvnBP9wsCghObh8p4tmI5wC62W7K','ralparmario202@gmail.com','customer',1,'active',1,'2026-02-25 21:29:57','2026-02-20 20:07:51','2026-04-03 07:00:18'),(2,'Hansel','$2y$10$X.TdDJRAdLCvS7zuUA9m9u1NU/yt87TPnoGI.ItMEKhBxDkwVIstG','hanjel@gmail.com','customer',1,'active',1,'2026-02-20 21:03:20','2026-02-20 21:03:02','2026-04-03 07:00:18'),(3,'admin','admin123','admin@pss.com','admin',0,'active',1,'2026-04-18 20:15:13','2026-02-23 14:11:22','2026-04-18 20:15:13'),(4,'cashier1','cashier123','cashier@pss.com','cashier',0,'active',1,'2026-04-18 20:12:55','2026-02-23 14:11:22','2026-04-18 20:12:55'),(5,'Andrea_Asierto','$2y$10$XuwQvn1bprrcrmdE6utUtuk.k4rMSLdVMrgmKnDUB0JWP4KCpY3d6','aseirtoandrea@gmail.com','cashier',1,'active',1,NULL,'2026-02-25 14:16:38','2026-04-03 07:00:18'),(6,'CAS-MANZANERO-2668','$2y$10$sdRjY7Mmz6l3zgxNpHQ3ZOZJQftl4SA6IHx8MCQIksvmA9G2Zyl/6','ninamanzanero@gmail.com','cashier',1,'active',1,'2026-02-25 18:10:16','2026-02-25 14:27:24','2026-04-03 07:00:18'),(7,'Sumi','$2y$10$lfyS94XlRJFREIePUcIz8.d1V3KMgEGC.vKp2t9uWOXeSXL53St/2','johnnoelorano@gmail.com','customer',1,'active',1,'2026-04-18 16:55:56','2026-03-06 13:43:07','2026-04-18 16:55:56'),(8,'INV-STAFF-6534','$2y$10$hgFIzA9VQcA2B4FS1GuM6uhDr/iCKc7wbbGb3eq2TBQh6LNhcS1bu','inventory@pss.com','inventory',1,'active',1,'2026-04-18 20:24:15','2026-03-17 15:49:31','2026-04-18 20:24:15'),(12,'JNO','$2y$10$nURFvoiebkMYHqK.c2ZMaepe8t3zjFjeC7ksMxlS34GNCIGuP6sHy','almondtofu25@gmail.com','customer',1,'active',1,'2026-04-03 09:10:31','2026-04-03 08:48:38','2026-04-03 09:10:31'),(13,'Pogi','$2y$10$z.f66ewBymc5cyvGtSL5E.sl.rQok9VFtwkel4gKw/fmmLD8sf88K','cidkag1210@gmail.com','customer',1,'active',1,'2026-04-08 02:20:07','2026-04-06 12:34:49','2026-04-08 02:20:07');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wishlist`
--

DROP TABLE IF EXISTS `wishlist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wishlist` (
  `wishlist_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `product_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`wishlist_id`),
  UNIQUE KEY `unique_user_product` (`user_id`,`product_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_wishlist_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wishlist_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wishlist`
--

LOCK TABLES `wishlist` WRITE;
/*!40000 ALTER TABLE `wishlist` DISABLE KEYS */;
/*!40000 ALTER TABLE `wishlist` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'pss_db'
--

--
-- Dumping routines for database 'pss_db'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-19  4:57:05
