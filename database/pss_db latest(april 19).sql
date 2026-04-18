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
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,8,'update',62,'0','Selling Price','32.25','35.25','2026-04-08 02:25:06'),(2,8,'update',53,'0','Stock','0','50','2026-04-08 02:25:18'),(3,8,'update',53,'0','Status','inactive','active','2026-04-08 02:25:18'),(4,8,'update',53,'0','Unit Value','—','5.00','2026-04-08 02:25:28'),(5,8,'update',53,'0','Unit Measure','—','g','2026-04-08 02:25:28'),(6,8,'update',53,'0','Status','active','inactive (auto due to existing references)','2026-04-08 02:25:44'),(7,8,'update',55,'Mango','stock','20','22','2026-04-17 09:10:09'),(8,8,'update',27,'Downy Antibac Fabric Conditioner','stock','54','56','2026-04-17 14:50:36'),(9,8,'update',17,'C2 Green Tea Apple','stock','155','157','2026-04-17 16:09:45'),(10,8,'update',27,'Downy Antibac Fabric Conditioner','stock','56','57','2026-04-17 16:57:38'),(11,8,'update',26,'0','Product Name','Surf Cherry Blossom Laundry Powder','Surf Cherry Blossom Laundry Powder 6x65','2026-04-17 17:57:45'),(12,8,'update',26,'0','Category','Beverages','Condiments','2026-04-17 17:57:45'),(13,8,'update',26,'0','Unit Value','800.00','65.00','2026-04-17 17:57:45'),(14,8,'update',26,'0','Product Name','Surf Cherry Blossom Laundry Powder 6x65','Surf Cherry Blossom Laundry Powder 6x65g','2026-04-17 17:58:29'),(15,8,'update',26,'0','Unit Value','65.00','390.00','2026-04-17 17:58:29'),(16,8,'update',26,'0','Pcs per Box','1','21','2026-04-17 18:43:24'),(17,8,'update',26,'0','Pcs per Box','21','24','2026-04-17 18:43:28'),(18,8,'update',27,'0','Pcs per Box','12','17','2026-04-17 20:09:26'),(19,8,'update',27,'Downy Antibac Fabric Conditioner','stock','57','62','2026-04-17 20:11:24'),(20,8,'update',55,'0','Cost Price','35.00','3.00','2026-04-18 14:55:19'),(21,8,'update',55,'0','Selling Price','45.00','5.00','2026-04-18 14:55:19'),(22,8,'update',27,'Downy Antibac Fabric Conditioner','stock','62','67','2026-04-18 15:55:36'),(23,8,'update',25,'Head & Shoulders Cool Menthol','stock','72','74','2026-04-18 16:51:27'),(24,8,'update',24,'Creamsilk Standout Straight','stock','65','67','2026-04-18 17:12:30'),(25,8,'update',24,'Creamsilk Standout Straight','stock','67','69','2026-04-18 17:12:39'),(26,8,'update',25,'Head & Shoulders Cool Menthol','stock','74','76','2026-04-18 17:24:45'),(27,8,'update',27,'Downy Antibac Fabric Conditioner','stock','67','69','2026-04-18 18:14:34');
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
) ENGINE=InnoDB AUTO_INCREMENT=155 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart`
--

LOCK TABLES `cart` WRITE;
/*!40000 ALTER TABLE `cart` DISABLE KEYS */;
INSERT INTO `cart` VALUES (146,13,52,15,'2026-04-08 02:20:27','2026-04-08 02:20:27'),(154,7,55,1,'2026-04-18 15:47:43','2026-04-18 15:47:43');
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
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_logs`
--

LOCK TABLES `inventory_logs` WRITE;
/*!40000 ALTER TABLE `inventory_logs` DISABLE KEYS */;
INSERT INTO `inventory_logs` VALUES (1,29,8,3,'PO PO-20260417-91432A receiving accepted quantity','2026-04-17 05:34:42'),(2,29,8,1,'PO PO-20260417-91432A receiving accepted quantity','2026-04-17 05:42:52'),(3,1,8,3,'PO PO-20260417-24DFA3 receiving accepted quantity','2026-04-17 07:01:02'),(4,1,8,1,'PO PO-20260417-24DFA3 receiving accepted quantity','2026-04-17 09:06:28'),(5,55,8,2,'PO PO-20260417-E30D08 receiving accepted quantity','2026-04-17 09:10:09'),(6,27,8,2,'PO PO-20260417-564C1B receiving accepted quantity','2026-04-17 14:50:36'),(7,17,8,2,'PO PO-20260417-FD9DB5 receiving accepted quantity','2026-04-17 16:09:45'),(8,27,8,1,'PO PO-20260417-564C1B receiving accepted quantity','2026-04-17 16:57:38'),(9,27,8,5,'PO PO-20260417-3691F0 receiving accepted quantity','2026-04-17 20:11:24'),(10,27,8,5,'PO PO-20260417-3691F0 receiving accepted quantity','2026-04-18 15:55:36'),(11,25,8,2,'Released batch RCV-260418-AD9E to store','2026-04-18 16:51:27'),(12,24,8,2,'Released batch RCV-260418-693A to store','2026-04-18 17:12:30'),(13,24,8,2,'Released batch RCV-260418-BF4A to store','2026-04-18 17:12:39'),(14,25,8,2,'Released batch RCV-260418-0F65 to store','2026-04-18 17:24:45'),(15,27,8,2,'Released batch RCV-260418-D2EE to store','2026-04-18 18:14:34');
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
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (1,2,'INV-20260224-5B79',61.61,7.39,0.00,69.00,'2026-02-24 19:50:04'),(2,3,'INV-20260224-43E8',418.75,50.25,0.00,469.00,'2026-02-24 20:37:29'),(3,4,'INV-20260224-1F12',308.04,36.96,0.00,345.00,'2026-02-24 20:49:36'),(4,5,'INV-20260224-0AA0',483.48,58.02,0.00,541.50,'2026-02-24 20:53:14'),(5,6,'INV-20260225-58C6',603.57,72.43,0.00,676.00,'2026-02-25 12:49:01'),(6,10,'INV-20260225-EB8D',797.32,95.68,0.00,893.00,'2026-02-25 18:13:41'),(7,12,'INV-20260225-C88A',450.00,54.00,0.00,504.00,'2026-02-25 19:22:53'),(8,13,'INV-20260225-0E11',179.46,21.54,0.00,201.00,'2026-02-25 19:52:19'),(9,14,'INV-20260225-78D8',51.79,6.21,0.00,58.00,'2026-02-25 20:40:46'),(10,15,'INV-20260225-9884',240.18,28.82,0.00,269.00,'2026-02-25 21:34:54'),(11,16,'INV-20260225-3E37',58.04,6.96,0.00,65.00,'2026-02-25 21:35:06'),(12,19,'INV-20260308-D99A',89.29,10.71,0.00,100.00,'2026-03-08 14:29:53'),(13,20,'INV-20260308-2D37',124.11,14.89,0.00,139.00,'2026-03-08 15:00:13'),(14,22,'INV-20260309-99B0',214.29,25.71,0.00,240.00,'2026-03-09 12:07:24'),(15,23,'INV-20260321-4BF4',104.46,12.54,0.00,117.00,'2026-03-21 17:50:15'),(16,21,'INV-20260330-628D',8.93,1.07,0.00,10.00,'2026-03-30 18:28:41'),(17,41,'INV-20260330-20A9',200.89,24.11,0.00,225.00,'2026-03-30 19:17:08'),(18,3,'INV-20260406-8C51',216.07,25.93,0.00,242.00,'2026-04-06 12:47:01'),(19,2,'INV-20260407-AF94',245.54,29.46,0.00,275.00,'2026-04-07 02:01:24'),(20,4,'INV-20260407-6275',481.25,57.75,0.00,539.00,'2026-04-07 02:14:29');
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
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,1,5,2,39.00),(2,1,53,5,12.00),(3,2,5,5,39.00),(4,2,52,4,20.00),(5,3,5,2,39.00),(6,3,52,4,20.00),(7,3,53,7,12.00),(8,4,10,2,22.00),(9,4,25,2,150.00),(10,4,28,3,65.00),(11,5,5,4,39.00),(12,5,53,4,12.00),(13,6,52,3,20.00),(14,7,5,7,39.00),(15,7,52,3,20.00),(16,8,5,5,39.00),(17,8,52,6,20.00),(18,9,52,15,20.00),(19,10,1,3,100.00),(20,11,55,1,5.00),(21,12,55,1,5.00),(22,13,55,1,5.00),(23,14,55,1,5.00),(24,15,55,1,5.00);
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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,'ORD-20260403-FE43',7,138.00,'gcash','paid','2026-04-03 16:00:00','full',138.00,0.00,NULL,'pending','2026-04-03 06:44:18',NULL,'pi_4czFFKRzCNME2V7Rr6AAQ6z8',NULL,NULL,0.00),(2,'ORD-20260403-EB4F',7,275.00,NULL,'paid','2026-04-03 16:00:00','full',275.00,0.00,NULL,'completed','2026-04-03 06:45:02',4,'pi_6pA4BTqVh8HsVdYEArCvQfmd',NULL,NULL,0.00),(3,'ORD-20260406-9F72',13,242.00,NULL,'paid','2026-04-07 10:00:00','partial_50',121.00,121.00,NULL,'completed','2026-04-06 12:40:51',4,'pi_2HQdCoB1kpwgNHf41f8VTHJR',NULL,NULL,0.00),(4,'ORD-20260407-8905',13,539.00,'gcash','paid','2026-04-08 10:00:00','full',539.00,0.00,NULL,'completed','2026-04-07 02:07:48',4,'pi_TEt7SwnEqDG27ytDiqMTytjn',NULL,NULL,0.00),(5,'ORD-20260407-4083',13,204.00,'gcash','paid','2026-04-08 10:00:00','partial_50',102.00,102.00,NULL,'pending','2026-04-07 14:25:13',NULL,'pi_BwoBkQZdcU24M1D88K8UVEnn',NULL,NULL,0.00),(6,'ORD-20260407-02DC',13,60.00,NULL,'pending','2026-04-08 10:00:00','full',60.00,0.00,NULL,'pending','2026-04-07 15:57:03',NULL,'pi_Gh6CZpC6xpbe3UGAitaaS8Mm',NULL,NULL,0.00),(7,'ORD-20260408-6F19',13,406.26,'gcash','paid','2026-04-08 11:30:00','full',406.26,0.00,NULL,'pending','2026-04-08 02:10:52',NULL,'pi_U28sePjhXMkEhKyqCq311yLv',NULL,NULL,0.00),(8,'ORD-20260408-77DA',7,384.30,'gcash','paid','2026-04-08 11:30:00','partial_50',192.15,192.15,NULL,'pending','2026-04-08 02:44:42',NULL,'pi_JKr3EAu7zwHTX4tYuWcgrhJL',NULL,NULL,0.00),(9,'ORD-20260417-0A84',7,366.00,'gcash','paid','2026-04-18 10:00:00','full',366.00,0.00,NULL,'pending','2026-04-17 12:08:28',NULL,'pi_jq3K41UEZ5BbwqBJgraxxBhe',NULL,NULL,0.00),(10,'ORD-20260417-CE39',7,366.00,'gcash','paid','2026-04-18 10:00:00','full',366.00,0.00,NULL,'pending','2026-04-17 12:18:27',NULL,'pi_AzvLV1GEUoqVsFrehNP3XxP7',NULL,NULL,0.00),(11,'ORD-20260418-A7D0',7,6.10,NULL,'pending','2026-04-19 10:00:00','partial_50',3.05,3.05,NULL,'pending','2026-04-18 14:58:14',NULL,'pi_on3T5mg7SLevQYMhRDHDjFUV',NULL,NULL,0.00),(12,'ORD-20260418-0200',7,6.10,NULL,'pending','2026-04-19 10:00:00','partial_50',3.05,3.05,NULL,'pending','2026-04-18 15:15:58',NULL,'pi_GZ7LJn5M34mmtpzrfnLziHEE',NULL,NULL,0.00),(13,'ORD-20260418-2DF5',7,6.10,NULL,'pending','2026-04-19 10:00:00','full',6.10,0.00,NULL,'pending','2026-04-18 15:29:00',NULL,'pi_G8jjU3LPJeS3M72RW6jZ8zFk',NULL,NULL,0.00),(14,'ORD-20260418-E7C9',7,6.10,NULL,'pending','2026-04-19 10:00:00','partial_30',1.83,4.27,NULL,'pending','2026-04-18 15:34:16',NULL,'pi_ofhnVHM4d6xr6x5m73ssSCYc',NULL,NULL,0.00),(15,'ORD-20260418-5A52',7,6.10,NULL,'pending','2026-04-19 10:00:00','partial_30',1.83,4.27,NULL,'pending','2026-04-18 15:48:00',NULL,'pi_6NQ8zb71YjMMnHqCRc6fA8Li',NULL,NULL,0.00);
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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
INSERT INTO `password_resets` VALUES (4,12,'3e492a38fd6424163c28392facc0d76bf4c5271bd3b33c96b909236bfcf3017e','2026-04-03 18:00:28',1,'2026-04-03 17:00:28'),(11,7,'40b5d1234a462ef75225181cee8d3c3241e4df143f99948a172561d854d615a6','2026-04-07 14:50:42',0,'2026-04-07 22:48:42');
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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `po_receiving_items`
--

LOCK TABLES `po_receiving_items` WRITE;
/*!40000 ALTER TABLE `po_receiving_items` DISABLE KEYS */;
INSERT INTO `po_receiving_items` VALUES (1,1,3,29,4,3,1,'1','2026-04-16','expired',NULL),(2,2,3,29,1,1,0,'1',NULL,NULL,NULL),(3,7,4,1,3,3,0,'RCV-260417-E4B7',NULL,NULL,NULL),(4,8,4,1,1,1,0,'RCV-260417-AE7E','2026-04-23',NULL,NULL),(5,9,5,55,2,2,0,'RCV-260417-8E3B','2026-04-30',NULL,NULL),(6,10,6,27,3,2,1,'RCV-260417-5FF3','2026-04-30','expired',NULL),(7,14,7,17,4,2,2,'RCV-260417-DF4F','2026-04-18','expired',NULL),(8,17,6,27,2,1,1,'RCV-260417-A7BB',NULL,'expired',NULL),(9,18,13,27,10,5,5,'RCV-260417-1FFF',NULL,'expired',NULL),(10,19,13,27,10,5,5,'RCV-260418-0368',NULL,'expired',NULL),(11,20,14,24,6,3,3,'RCV-260418-B64A',NULL,'expired',NULL),(12,20,15,25,6,3,3,'RCV-260418-050E',NULL,'expired',NULL),(13,21,14,24,3,2,1,'RCV-260418-693A','2026-05-23','near_expiry','2026-04-19'),(14,21,15,25,3,2,1,'RCV-260418-AD9E','2026-05-19','expired','2026-04-19'),(15,22,14,24,3,2,1,'RCV-260418-BF4A','2026-05-27','expired','2026-04-19'),(16,22,15,25,3,2,1,'RCV-260418-0F65','2026-05-27','expired','2026-04-19'),(17,23,13,27,5,0,5,'RCV-260418-D07D','2026-05-29','near_expiry','2026-04-19'),(18,24,13,27,3,2,1,'RCV-260418-D2EE','2026-05-30','near_expiry','2026-04-20'),(19,25,13,27,2,1,1,'RCV-260418-5F67','2026-05-27','expired','2026-04-21');
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
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `po_receivings`
--

LOCK TABLES `po_receivings` WRITE;
/*!40000 ALTER TABLE `po_receivings` DISABLE KEYS */;
INSERT INTO `po_receivings` VALUES (1,1,8,'2026-04-17 13:34:42',''),(2,1,8,'2026-04-17 13:42:52',''),(7,2,8,'2026-04-17 15:01:02',''),(8,2,8,'2026-04-17 17:06:28',''),(9,3,8,'2026-04-17 17:10:09',''),(10,4,8,'2026-04-17 22:50:36',''),(14,5,8,'2026-04-18 00:09:45',''),(17,4,8,'2026-04-18 00:57:38',''),(18,9,8,'2026-04-18 04:11:24',''),(19,9,8,'2026-04-18 23:55:36','Sample'),(20,10,8,'2026-04-19 00:41:26',''),(21,10,8,'2026-04-19 00:51:05',''),(22,10,8,'2026-04-19 01:12:21',''),(23,9,8,'2026-04-19 02:12:01',''),(24,9,8,'2026-04-19 02:13:26',''),(25,9,8,'2026-04-19 02:16:58','');
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
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_batches`
--

LOCK TABLES `product_batches` WRITE;
/*!40000 ALTER TABLE `product_batches` DISABLE KEYS */;
INSERT INTO `product_batches` VALUES (3,24,10,'RCV-260418-BF4A','2026-04-18','2026-05-19',2,2,'Released','2026-04-18 17:12:21','2026-04-18 17:21:33'),(4,25,10,'RCV-260418-0F65','2026-04-18','2026-05-19',2,2,'Released','2026-04-18 17:12:21','2026-04-18 17:24:45'),(5,1,NULL,'BATCH-1-01','2026-04-18','2026-05-19',29,29,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(6,3,NULL,'BATCH-3-01','2026-04-18','2026-05-19',199,199,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(7,4,NULL,'BATCH-4-01','2026-04-18','2026-05-19',82,82,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(8,5,NULL,'BATCH-5-01','2026-04-18','2026-05-19',111,111,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(9,6,NULL,'BATCH-6-01','2026-04-18','2026-05-19',90,90,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(10,7,NULL,'BATCH-7-01','2026-04-18','2026-05-19',199,199,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(11,8,NULL,'BATCH-8-01','2026-04-18','2026-05-19',150,150,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(12,9,NULL,'BATCH-9-01','2026-04-18','2026-05-19',500,500,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(13,10,NULL,'BATCH-10-01','2026-04-18','2026-05-19',180,180,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(14,11,NULL,'BATCH-11-01','2026-04-18','2026-05-19',75,75,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(15,12,NULL,'BATCH-12-01','2026-04-18','2026-05-19',110,110,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(16,13,NULL,'BATCH-13-01','2026-04-18','2026-05-19',95,95,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(17,15,NULL,'BATCH-15-01','2026-04-18','2026-05-19',133,133,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(18,16,NULL,'BATCH-16-01','2026-04-18','2026-05-19',199,199,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(19,17,NULL,'BATCH-17-01','2026-04-18','2026-05-19',157,157,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(20,18,NULL,'BATCH-18-01','2026-04-18','2026-05-19',219,219,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(21,19,NULL,'BATCH-19-01','2026-04-18','2026-05-19',138,138,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(22,20,NULL,'BATCH-20-01','2026-04-18','2026-05-19',110,110,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(23,22,NULL,'BATCH-22-01','2026-04-18','2026-05-19',24,24,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(24,23,NULL,'BATCH-23-01','2026-04-18','2026-05-19',180,180,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(25,24,NULL,'BATCH-24-01','2026-04-18','2026-05-19',69,69,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(26,25,NULL,'BATCH-25-01','2026-04-18','2026-05-19',74,74,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(27,26,NULL,'BATCH-26-01','2026-04-18','2026-05-19',85,85,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(28,27,NULL,'BATCH-27-01','2026-04-18','2026-05-19',67,67,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(29,28,NULL,'BATCH-28-01','2026-04-18','2026-05-19',137,137,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(30,29,NULL,'BATCH-29-01','2026-04-18','2026-05-19',9,9,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(31,30,NULL,'BATCH-30-01','2026-04-18','2026-05-19',5,5,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(32,31,NULL,'BATCH-31-01','2026-04-18','2026-05-19',20,20,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(33,52,NULL,'BATCH-52-01','2026-04-18','2026-05-19',26,26,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(34,54,NULL,'BATCH-54-01','2026-04-18','2026-05-19',25,25,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(35,55,NULL,'BATCH-55-01','2026-04-18','2026-05-19',22,22,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(36,56,NULL,'BATCH-56-01','2026-04-18','2026-05-19',15,15,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(37,58,NULL,'BATCH-58-01','2026-04-18','2026-05-19',40,40,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(38,59,NULL,'BATCH-59-01','2026-04-18','2026-05-19',35,35,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(39,60,NULL,'BATCH-60-01','2026-04-18','2026-05-19',50,50,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(40,61,NULL,'BATCH-61-01','2026-04-18','2026-05-19',50,50,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(41,62,NULL,'BATCH-62-01','2026-04-18','2026-05-19',100,100,'Released','2026-04-18 17:23:59','2026-04-18 17:23:59'),(68,27,9,'RCV-260418-D2EE','2026-04-20','2026-05-30',2,2,'Released','2026-04-18 18:13:26','2026-04-18 18:14:34'),(69,27,9,'RCV-260418-5F67','2026-04-21','2026-05-27',1,1,'Pending','2026-04-18 18:16:58','2026-04-18 18:16:58');
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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_order_items`
--

LOCK TABLES `purchase_order_items` WRITE;
/*!40000 ALTER TABLE `purchase_order_items` DISABLE KEYS */;
INSERT INTO `purchase_order_items` VALUES (3,1,29,5,'retail',0,5,1,200.00,1000.00),(4,2,1,5,'retail',0,4,0,83.00,415.00),(5,3,55,5,'retail',0,2,0,35.00,175.00),(6,4,27,5,'retail',0,5,2,105.00,525.00),(7,5,17,5,'retail',0,4,2,22.00,110.00),(8,6,54,1,'wholesale',1,0,0,216.00,216.00),(9,6,59,1,'wholesale',1,0,0,144.00,144.00),(10,6,60,1,'wholesale',1,0,0,180.00,180.00),(11,7,24,12,'wholesale',1,0,0,1320.00,1320.00),(12,8,60,36,'wholesale',3,0,0,180.00,540.00),(13,9,27,34,'wholesale',2,30,17,1785.00,3570.00),(14,10,24,12,'wholesale',1,12,5,1320.00,1320.00),(15,10,25,12,'wholesale',1,12,5,1500.00,1500.00);
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_orders`
--

LOCK TABLES `purchase_orders` WRITE;
/*!40000 ALTER TABLE `purchase_orders` DISABLE KEYS */;
INSERT INTO `purchase_orders` VALUES (1,'PO-20260417-91432A',2,'completed','2026-04-17','2026-04-18',1000.00,0.00,1000.00,'',NULL,8,3,'2026-04-17 13:32:34','2026-04-17 05:31:57','2026-04-17 05:42:52',NULL),(2,'PO-20260417-24DFA3',1,'partially_received','2026-04-17','2026-04-15',415.00,0.00,415.00,'',NULL,8,3,'2026-04-17 13:43:47','2026-04-17 05:37:43','2026-04-17 09:06:28',NULL),(3,'PO-20260417-E30D08',2,'partially_received','2026-04-17','2026-04-30',175.00,0.00,175.00,'',NULL,8,3,'2026-04-17 17:09:15','2026-04-17 09:09:04','2026-04-17 09:10:09',NULL),(4,'PO-20260417-564C1B',2,'completed','2026-04-17',NULL,525.00,0.00,525.00,'',NULL,8,3,'2026-04-17 22:48:29','2026-04-17 14:48:20','2026-04-17 16:57:38',NULL),(5,'PO-20260417-FD9DB5',3,'partially_received','2026-04-18','2026-04-21',110.00,0.00,110.00,'',NULL,8,3,'2026-04-18 00:05:06','2026-04-17 16:04:47','2026-04-17 16:09:45',NULL),(6,'PO-20260417-CE41DA',1,'pending_approval','2026-04-18','2026-04-23',540.00,0.00,540.00,'',NULL,8,NULL,NULL,'2026-04-17 18:54:54','2026-04-17 18:54:54',NULL),(7,'PO-20260417-177384',3,'pending_approval','2026-04-18','2026-04-30',1320.00,0.00,1320.00,'',NULL,8,NULL,NULL,'2026-04-17 19:18:20','2026-04-17 19:18:20',NULL),(8,'PO-20260417-D604B1',1,'pending_approval','2026-04-18','2026-04-29',540.00,0.00,540.00,'',NULL,8,NULL,NULL,'2026-04-17 19:18:51','2026-04-17 19:18:51',NULL),(9,'PO-20260417-3691F0',2,'partially_received','2026-04-18','2026-04-30',3570.00,0.00,3570.00,'',NULL,8,3,'2026-04-18 04:10:20','2026-04-17 20:10:13','2026-04-17 20:11:24',NULL),(10,'PO-20260418-D715DC',3,'completed','2026-04-19','2026-04-21',2820.00,0.00,2820.00,'',NULL,8,3,'2026-04-19 00:20:20','2026-04-18 16:20:12','2026-04-18 17:12:21',NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shift_logs`
--

LOCK TABLES `shift_logs` WRITE;
/*!40000 ALTER TABLE `shift_logs` DISABLE KEYS */;
INSERT INTO `shift_logs` VALUES (2,4,'cashier1','2026-04-07 10:08:16','2026-04-07 10:14:42',539.00,'2026-04-07'),(4,4,'cashier1','2026-04-17 19:57:34',NULL,0.00,'2026-04-17'),(5,4,'cashier1','2026-04-17 20:10:59','2026-04-17 22:37:29',0.00,'2026-04-17'),(6,4,'cashier1','2026-04-18 04:13:10',NULL,0.00,'2026-04-18');
/*!40000 ALTER TABLE `shift_logs` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_return_updates`
--

LOCK TABLES `supplier_return_updates` WRITE;
/*!40000 ALTER TABLE `supplier_return_updates` DISABLE KEYS */;
INSERT INTO `supplier_return_updates` VALUES (1,11,'pending_return','pending_return','resolution_set',NULL,8,'2026-04-18 18:01:01'),(2,12,'pending_return','resolved','resolution_set',NULL,8,'2026-04-18 18:01:41'),(3,14,'pending_return','resolved','resolution_set',NULL,8,'2026-04-18 18:13:51'),(4,15,'pending_return','pending_return','resolution_set',NULL,8,'2026-04-18 18:23:00');
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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_returns`
--

LOCK TABLES `supplier_returns` WRITE;
/*!40000 ALTER TABLE `supplier_returns` DISABLE KEYS */;
INSERT INTO `supplier_returns` VALUES (1,1,3,29,2,1,'expired','','pending_return','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,8,'2026-04-17 05:34:42','2026-04-17 05:34:42',NULL),(2,4,6,27,2,1,'expired','','pending_return','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,8,'2026-04-17 14:50:36','2026-04-17 14:50:36',NULL),(3,5,7,17,3,2,'expired','','pending_return','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,8,'2026-04-17 16:09:45','2026-04-17 16:10:44',NULL),(4,4,6,27,2,1,'expired','','pending_return','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,8,'2026-04-17 16:57:38','2026-04-17 16:57:38',NULL),(5,9,13,27,2,5,'expired','','pending_return','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,8,'2026-04-17 20:11:24','2026-04-17 20:11:24',NULL),(6,9,13,27,2,5,'expired','','pending_return','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,8,'2026-04-18 15:55:36','2026-04-18 15:55:36',NULL),(7,10,14,24,3,3,'expired','','pending_return','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,8,'2026-04-18 16:41:26','2026-04-18 16:41:26',NULL),(8,10,15,25,3,3,'expired','','pending_return','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,8,'2026-04-18 16:41:26','2026-04-18 16:41:26',NULL),(9,10,14,24,3,1,'near_expiry','','pending_return','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,8,'2026-04-18 16:51:05','2026-04-18 16:51:05',NULL),(10,10,15,25,3,1,'expired','','pending_return','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,8,'2026-04-18 16:51:05','2026-04-18 16:51:05',NULL),(11,10,14,24,3,1,'expired','','pending_return','replace',NULL,NULL,NULL,NULL,NULL,NULL,NULL,8,'2026-04-18 17:12:21','2026-04-18 18:01:01',NULL),(12,10,15,25,3,1,'expired','','resolved','replace',NULL,NULL,NULL,NULL,NULL,NULL,8,8,'2026-04-18 17:12:21','2026-04-18 18:01:41','2026-04-18 20:01:41'),(13,9,13,27,2,5,'near_expiry','','pending_return','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,8,'2026-04-18 18:12:01','2026-04-18 18:12:01',NULL),(14,9,13,27,2,1,'near_expiry','','resolved','replace',NULL,NULL,NULL,NULL,NULL,NULL,8,8,'2026-04-18 18:13:26','2026-04-18 18:13:51','2026-04-18 20:13:51'),(15,9,13,27,2,1,'expired','','pending_return','replace',NULL,NULL,NULL,NULL,NULL,NULL,NULL,8,'2026-04-18 18:16:58','2026-04-18 18:23:00',NULL);
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
INSERT INTO `suppliers` VALUES (1,'Local Farm','Manufacturer','[\"Fresh Produce\"]','Local Farmer',NULL,'09171234567','farm@local.com','Quezon City, PH','active','2026-04-17 05:08:29','2026-04-17 11:51:42'),(2,'General Local Supplier','Manufacturer',NULL,'Jane Supplier',NULL,'09179876543','supply@local.com','Bulacan, PH','active','2026-04-17 05:08:29','2026-04-17 15:34:38'),(3,'Beverages Supply','Manufacturer','Beverages','Beverage Company',NULL,'09171234567','supplybeverage@email.com','Quezon City, PH','active','2026-04-17 15:36:41','2026-04-17 15:36:41'),(4,'Watsons','Manufacturer','[\"Snacks\"]','Vaughn Maco',NULL,'0992139504','vaughmaco.97@gmail.com','Quezon City, NCR','active','2026-04-17 16:20:10','2026-04-17 16:20:10');
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
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
INSERT INTO `transactions` VALUES (1,1,4,'payment','cash',69.00,100.00,31.00,'','2026-02-24 19:50:04'),(2,2,4,'payment','cash',469.00,500.00,31.00,'','2026-02-24 20:37:29'),(3,3,4,'payment','cash',345.00,350.00,5.00,'','2026-02-24 20:49:36'),(4,4,4,'payment','cash',541.50,550.00,8.50,'','2026-02-24 20:53:14'),(5,5,4,'payment','cash',676.00,700.00,24.00,'','2026-02-25 12:49:01'),(6,6,6,'payment','cash',893.00,900.00,7.00,'','2026-02-25 18:13:41'),(7,7,6,'payment','cash',504.00,252.00,0.00,'','2026-02-25 19:22:53'),(8,8,6,'payment','prepaid',201.00,NULL,0.00,'21315123125234','2026-02-25 19:52:19'),(9,9,6,'payment','prepaid',58.00,NULL,0.00,'21512316345234132','2026-02-25 20:40:46'),(10,10,4,'payment','cash',269.00,200.00,11.70,'','2026-02-25 21:34:54'),(11,11,4,'payment','prepaid',65.00,NULL,0.00,'423234236324234','2026-02-25 21:35:06'),(12,12,4,'payment','prepaid',100.00,NULL,0.00,'2222222222222','2026-03-08 14:29:53'),(13,13,4,'payment','prepaid',139.00,NULL,0.00,'4444444444444','2026-03-08 15:00:13'),(14,14,4,'payment','prepaid',240.00,NULL,0.00,'5555555555555','2026-03-09 12:07:24'),(15,15,4,'payment','cash',117.00,100.00,41.50,'','2026-03-21 17:50:15'),(16,16,4,'payment','prepaid',10.00,NULL,0.00,'7777777777777','2026-03-30 18:28:41'),(17,17,4,'payment','cash',225.00,200.00,87.50,'','2026-03-30 19:17:08'),(18,18,4,'payment','cash',242.00,300.00,179.00,'','2026-04-06 12:47:01'),(19,19,4,'payment','prepaid',275.00,NULL,0.00,'12345678910','2026-04-07 02:01:24'),(20,20,4,'payment','prepaid',539.00,NULL,0.00,'22222222222','2026-04-07 02:14:29');
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
INSERT INTO `users` VALUES (1,'Hanjel','$2y$10$G/5krO6C0KxKuKB7p16N2OCdzvvnBP9wsCghObh8p4tmI5wC62W7K','ralparmario202@gmail.com','customer',1,'active',1,'2026-02-25 21:29:57','2026-02-20 20:07:51','2026-04-03 07:00:18'),(2,'Hansel','$2y$10$X.TdDJRAdLCvS7zuUA9m9u1NU/yt87TPnoGI.ItMEKhBxDkwVIstG','hanjel@gmail.com','customer',1,'active',1,'2026-02-20 21:03:20','2026-02-20 21:03:02','2026-04-03 07:00:18'),(3,'admin','admin123','admin@pss.com','admin',0,'active',1,'2026-04-18 16:19:26','2026-02-23 14:11:22','2026-04-18 16:19:26'),(4,'cashier1','cashier123','cashier@pss.com','cashier',0,'active',1,'2026-04-17 20:13:10','2026-02-23 14:11:22','2026-04-17 20:13:10'),(5,'Andrea_Asierto','$2y$10$XuwQvn1bprrcrmdE6utUtuk.k4rMSLdVMrgmKnDUB0JWP4KCpY3d6','aseirtoandrea@gmail.com','cashier',1,'active',1,NULL,'2026-02-25 14:16:38','2026-04-03 07:00:18'),(6,'CAS-MANZANERO-2668','$2y$10$sdRjY7Mmz6l3zgxNpHQ3ZOZJQftl4SA6IHx8MCQIksvmA9G2Zyl/6','ninamanzanero@gmail.com','cashier',1,'active',1,'2026-02-25 18:10:16','2026-02-25 14:27:24','2026-04-03 07:00:18'),(7,'Sumi','$2y$10$lfyS94XlRJFREIePUcIz8.d1V3KMgEGC.vKp2t9uWOXeSXL53St/2','johnnoelorano@gmail.com','customer',1,'active',1,'2026-04-18 16:55:56','2026-03-06 13:43:07','2026-04-18 16:55:56'),(8,'INV-STAFF-6534','$2y$10$hgFIzA9VQcA2B4FS1GuM6uhDr/iCKc7wbbGb3eq2TBQh6LNhcS1bu','inventory@pss.com','inventory',1,'active',1,'2026-04-18 15:52:34','2026-03-17 15:49:31','2026-04-18 15:52:34'),(12,'JNO','$2y$10$nURFvoiebkMYHqK.c2ZMaepe8t3zjFjeC7ksMxlS34GNCIGuP6sHy','almondtofu25@gmail.com','customer',1,'active',1,'2026-04-03 09:10:31','2026-04-03 08:48:38','2026-04-03 09:10:31'),(13,'Pogi','$2y$10$z.f66ewBymc5cyvGtSL5E.sl.rQok9VFtwkel4gKw/fmmLD8sf88K','cidkag1210@gmail.com','customer',1,'active',1,'2026-04-08 02:20:07','2026-04-06 12:34:49','2026-04-08 02:20:07');
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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wishlist`
--

LOCK TABLES `wishlist` WRITE;
/*!40000 ALTER TABLE `wishlist` DISABLE KEYS */;
INSERT INTO `wishlist` VALUES (6,1,3,'2026-02-25 15:38:47'),(9,7,29,'2026-03-17 16:36:16'),(10,13,52,'2026-04-06 12:37:46'),(11,13,5,'2026-04-06 12:37:46'),(12,7,55,'2026-04-18 15:47:42');
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

-- Dump completed on 2026-04-19  2:31:37
