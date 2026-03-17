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
) ENGINE=InnoDB AUTO_INCREMENT=113 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart`
--

LOCK TABLES `cart` WRITE;
/*!40000 ALTER TABLE `cart` DISABLE KEYS */;
INSERT INTO `cart` VALUES (109,7,5,1,'2026-03-17 18:10:13','2026-03-17 18:10:13'),(110,7,52,1,'2026-03-17 18:22:03','2026-03-17 18:22:03'),(112,7,31,1,'2026-03-17 18:35:24','2026-03-17 18:35:24');
/*!40000 ALTER TABLE `cart` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (1,2,'INV-20260224-5B79',61.61,7.39,0.00,69.00,'2026-02-24 19:50:04'),(2,3,'INV-20260224-43E8',418.75,50.25,0.00,469.00,'2026-02-24 20:37:29'),(3,4,'INV-20260224-1F12',308.04,36.96,0.00,345.00,'2026-02-24 20:49:36'),(4,5,'INV-20260224-0AA0',483.48,58.02,0.00,541.50,'2026-02-24 20:53:14'),(5,6,'INV-20260225-58C6',603.57,72.43,0.00,676.00,'2026-02-25 12:49:01'),(6,10,'INV-20260225-EB8D',797.32,95.68,0.00,893.00,'2026-02-25 18:13:41'),(7,12,'INV-20260225-C88A',450.00,54.00,0.00,504.00,'2026-02-25 19:22:53'),(8,13,'INV-20260225-0E11',179.46,21.54,0.00,201.00,'2026-02-25 19:52:19'),(9,14,'INV-20260225-78D8',51.79,6.21,0.00,58.00,'2026-02-25 20:40:46'),(10,15,'INV-20260225-9884',240.18,28.82,0.00,269.00,'2026-02-25 21:34:54'),(11,16,'INV-20260225-3E37',58.04,6.96,0.00,65.00,'2026-02-25 21:35:06'),(12,19,'INV-20260308-D99A',89.29,10.71,0.00,100.00,'2026-03-08 14:29:53'),(13,20,'INV-20260308-2D37',124.11,14.89,0.00,139.00,'2026-03-08 15:00:13'),(14,22,'INV-20260309-99B0',214.29,25.71,0.00,240.00,'2026-03-09 12:07:24');
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
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,1,1,3,100.00),(2,1,3,4,34.50),(3,2,3,2,34.50),(4,3,1,4,100.00),(5,3,3,2,34.50),(6,4,3,10,34.50),(7,5,1,3,100.00),(8,5,3,7,34.50),(9,6,1,4,100.00),(10,6,3,8,34.50),(11,7,3,1,34.50),(12,7,19,1,18.00),(13,7,21,7,10.00),(14,8,1,1,100.00),(15,8,5,1,39.00),(16,8,17,1,28.00),(17,8,19,10,18.00),(18,9,26,5,80.00),(19,9,27,5,130.00),(20,10,4,1,195.00),(21,10,5,2,39.00),(22,10,25,3,150.00),(23,10,28,2,85.00),(24,11,18,1,16.00),(25,11,19,1,18.00),(26,12,4,2,195.00),(27,12,5,1,39.00),(28,12,16,1,75.00),(29,13,1,1,100.00),(30,13,7,1,23.00),(31,13,22,1,78.00),(32,14,19,1,18.00),(33,14,21,4,10.00),(34,15,1,1,100.00),(35,15,5,1,39.00),(36,15,27,1,130.00),(37,16,28,1,65.00),(38,17,1,28,100.00),(39,18,1,1,100.00),(40,19,1,1,100.00),(41,20,1,1,100.00),(42,20,5,1,39.00),(43,21,21,1,10.00),(44,22,1,1,100.00),(45,22,17,5,28.00),(46,23,5,3,39.00);
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
  `payment_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gcash',
  `payment_status` enum('pending','paid','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `pickup_datetime` datetime DEFAULT NULL,
  `payment_type` enum('full','partial') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full',
  `upfront_payment` decimal(10,2) NOT NULL DEFAULT '0.00',
  `balance_due` decimal(10,2) NOT NULL DEFAULT '0.00',
  `online_reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_status` enum('pending','processing','ready','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_by` int DEFAULT NULL,
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `tracking_no` (`tracking_no`),
  KEY `user_id` (`user_id`),
  KEY `fk_order_cashier` (`processed_by`),
  CONSTRAINT `fk_order_cashier` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,'ORD-20260224-4E94',1,438.00,'pay_on_pickup','pending',NULL,'full',0.00,0.00,NULL,'completed','2026-02-24 09:00:32',4),(2,'ORD-20260224-78A3',1,69.00,'pay_on_pickup','paid',NULL,'full',0.00,0.00,NULL,'completed','2026-02-24 18:00:57',4),(3,'ORD-20260224-9C1E',1,469.00,'pay_on_pickup','paid',NULL,'full',0.00,0.00,NULL,'completed','2026-02-24 20:36:42',4),(4,'ORD-20260224-EEBF',1,345.00,'pay_on_pickup','paid',NULL,'full',0.00,0.00,NULL,'completed','2026-02-24 20:49:05',4),(5,'ORD-20260224-CFA2',1,541.50,'pay_on_pickup','paid',NULL,'full',0.00,0.00,NULL,'completed','2026-02-24 20:52:56',4),(6,'ORD-20260225-18BA',1,676.00,'pay_on_pickup','paid',NULL,'full',0.00,0.00,NULL,'completed','2026-02-25 12:46:40',4),(7,'ORD-20260225-93A2',1,122.50,'pay_on_pickup','pending',NULL,'full',0.00,0.00,NULL,'cancelled','2026-02-25 16:24:24',NULL),(8,'ORD-20260225-A3AA',1,347.00,'pay_on_pickup','pending',NULL,'full',0.00,0.00,NULL,'cancelled','2026-02-25 18:02:53',NULL),(9,'ORD-20260225-26E3',1,1050.00,'online_payment','pending',NULL,'full',0.00,0.00,NULL,'cancelled','2026-02-25 18:08:43',6),(10,'ORD-20260225-2549',1,893.00,'pay_on_pickup','paid',NULL,'full',0.00,0.00,NULL,'completed','2026-02-25 18:12:18',6),(11,'ORD-20260225-8351',1,34.00,'pay_on_pickup','pending',NULL,'full',0.00,0.00,NULL,'cancelled','2026-02-25 18:13:57',6),(12,'ORD-20260225-A3F0',1,504.00,'gcash','paid','2026-02-26 13:10:00','partial',252.00,252.00,'312512312624213','completed','2026-02-25 19:22:16',6),(13,'ORD-20260225-2488',1,201.00,'gcash','paid','2026-02-26 16:44:00','full',201.00,0.00,'21315123125234','completed','2026-02-25 19:44:25',6),(14,'ORD-20260225-C69A',1,58.00,'gcash','paid','2026-02-26 14:02:00','full',58.00,0.00,'21512316345234132','completed','2026-02-25 20:40:28',6),(15,'ORD-20260225-0C5B',1,269.00,'gcash','paid','2026-02-26 13:00:00','partial',80.70,188.30,'123153215424321','completed','2026-02-25 20:41:41',4),(16,'ORD-20260225-1159',1,65.00,'gcash','paid','2026-02-26 14:00:00','full',65.00,0.00,'423234236324234','completed','2026-02-25 21:33:04',4),(17,'ORD-20260225-68B9',1,2800.00,'gcash','pending','2026-02-26 12:31:00','full',2800.00,0.00,'12412312123123123','cancelled','2026-02-25 21:36:54',NULL),(18,'ORD-20260308-B570',7,100.00,'gcash','pending','2026-03-09 21:09:00','full',100.00,0.00,'123456789101','cancelled','2026-03-08 12:09:58',NULL),(19,'ORD-20260308-9298',7,100.00,'gcash','paid','2026-03-09 10:00:00','full',100.00,0.00,'2222222222222','completed','2026-03-08 14:16:39',4),(20,'ORD-20260308-7972',7,139.00,'gcash','paid','2026-03-10 10:00:00','full',139.00,0.00,'4444444444444','completed','2026-03-08 14:30:48',4),(21,'ORD-20260308-CB14',7,10.00,'gcash','pending','2026-03-11 10:00:00','full',10.00,0.00,'7777777777777','pending','2026-03-08 14:34:00',NULL),(22,'ORD-20260308-3942',7,240.00,'gcash','paid','2026-03-09 10:00:00','full',240.00,0.00,'5555555555555','completed','2026-03-08 16:19:39',4),(23,'ORD-20260309-F7AC',7,117.00,'gcash','pending','2026-03-10 10:00:00','partial',58.50,58.50,'4444444444444','pending','2026-03-09 12:02:43',NULL);
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
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
  PRIMARY KEY (`product_id`),
  UNIQUE KEY `sku` (`sku`),
  KEY `idx_category` (`category`),
  KEY `idx_status` (`status`),
  KEY `idx_price` (`price`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'Bear Brand Fortified Powdered Milk','','Bear Brand','Dairy',83.00,'BEAR-MILK-050G',100.00,NULL,50.00,'g',25,50,'https://cdn.store-assets.com/s/377840/i/53513617.jpeg','active','2026-02-23 18:04:25','2026-03-08 16:19:39'),(3,'Piattos Potato Crisps Cheese','','JACK\'n JILL','Snacks',28.00,'PIATTOS-CHIPS-085G',34.50,NULL,85.00,'g',197,50,'https://ever.ph/cdn/shop/files/9000010103-Piattos-Potato-Crisps-Cheese-85g-201124.jpg?v=1614307767&width=823','active','2026-02-24 08:16:07','2026-03-17 17:59:31'),(4,'Spam Classic','Fully cooked canned pork meat. The classic breakfast favorite.','Spam','Canned Goods',160.00,'SPM-CLS-340G',195.00,NULL,340.00,'g',82,20,'https://ever.ph/cdn/shop/files/9000003193-Spam-Luncheon-Meat-Regular-Classic-12oz-210106_6796ff87-d6db-45c7-a4fe-c699ad746444.jpg?v=1772343802&width=823','active','2026-02-25 15:50:19','2026-03-17 18:03:10'),(5,'Argentina Corned Beef','Deliciously flavorful and filling corned beef for your pandesal.','Argentina','Canned Goods',32.00,'ARG-CBF-150G',39.00,NULL,150.00,'g',112,30,'https://ever.ph/cdn/shop/files/9000003128-Argentina-Corned-Beef-150g-210106_84069cc7-dbca-407e-a893-fbcefa7e00b6.jpg?v=1759465213&width=823','active','2026-02-25 15:50:19','2026-03-17 17:43:37'),(6,'San Marino Corned Tuna','The perfect combination of corned beef taste and tuna health benefits.','San Marino','Canned Goods',35.00,'SMR-CTN-180G',42.00,NULL,180.00,'g',90,20,'https://ever.ph/cdn/shop/files/100000045698-San-Marino-Corned-Tuna-Easy-Open-180g-210406_06a6b521-d0d9-4bb7-8c26-599278980de8.jpg?v=1768713784&width=823','active','2026-02-25 15:50:19','2026-03-17 18:01:41'),(7,'Mega Sardines in Tomato Sauce','Premium quality sardines in rich tomato sauce. Catch to can in 12 hours.','Mega','Canned Goods',19.00,'MGA-SAR-155G',23.00,NULL,155.00,'g',199,50,'https://ever.ph/cdn/shop/files/9000003423-Mega-Sardines-in-Tomato-Sauce-Easy-Open-155g-210406_6e57234c-8c2e-4213-8ef8-fa705f95a464.jpg?v=1771133246&width=823','active','2026-02-25 15:50:19','2026-03-17 17:57:19'),(8,'Nissin Seafood Cup Noodles','Instant cup noodles with rich and savory seafood broth.','Nissin','Beverages',35.00,'NIS-SEA-070G',42.00,NULL,70.00,'g',150,40,'https://ever.ph/cdn/shop/files/9000009932-Nissin-Cup-Noodles-Seafood-60g-210415.jpg?v=1619082354&width=823','active','2026-02-25 15:50:19','2026-03-17 17:58:06'),(9,'Maggi Magic Sarap','All-in-one seasoning granules for that extra umami.','Maggi','Condiments',4.50,'MAG-SAR-008G',6.00,NULL,8.00,'g',500,100,'https://i5.walmartimages.com/seo/Maggi-Magic-Sarap-All-in-One-Seasoning-8g-12pc_4acf4da8-ba52-411c-970d-5ab883855cff.0e5df89b72a6b2b9180148a9556264d4.jpeg','active','2026-02-25 15:50:19','2026-03-17 17:56:18'),(10,'Knorr Sinigang sa Sampaloc Mix','Tamarind soup base for authentic, perfectly sour sinigang.','Knorr','Condiments',18.00,'KNO-SIN-040G',22.00,NULL,40.00,'g',180,40,'https://ever.ph/cdn/shop/files/9000010774-Knorr-Sinigang-sa-Sampalok-22g-220704_04952265-f2bf-410a-a60d-c6e1599ddc6c.jpg?v=1752991716&width=823','active','2026-02-25 15:50:19','2026-03-17 17:54:31'),(11,'Mang Tomas All-Purpose Sauce','Classic lechon sauce perfect for any fried or roasted dish.','Mang Tomas','Condiments',38.00,'MTO-APS-330G',45.00,NULL,330.00,'g',75,15,'https://ever.ph/cdn/shop/files/9000008794-Mang-Tomas-All-Around-Sarsa-Regular-325g-10.5-101320_a6e98bc8-7ccb-46b7-98d9-0fcc897ef613.jpg?v=1770956522&width=823','active','2026-02-25 15:50:19','2026-03-17 17:56:47'),(12,'UFC Banana Ketchup','Tamis-anghang banana ketchup, a Filipino dining staple.','UFC','Condiments',30.00,'UFC-KET-320G',36.00,NULL,320.00,'g',110,25,'https://ever.ph/cdn/shop/files/100000006967-UFC-Tamis-Anghang-Banana-Catsup-Spouch-320g-210125.jpg?v=1614315385&width=823','active','2026-02-25 15:50:19','2026-03-17 18:05:19'),(13,'Eden Cheese','Creamy and melt-in-your-mouth processed filled cheese.','Eden','Dairy',45.00,'EDN-CHS-165G',54.00,NULL,165.00,'g',95,20,'https://ever.ph/cdn/shop/files/9000007109-Eden-Cheese-Original-160g-240415.jpg?v=1713756753&width=823','active','2026-02-25 15:50:19','2026-03-17 17:52:13'),(14,'Lady\'s Choice Real Mayonnaise','Made with real eggs and healthy oils for the best macaroni salad.','Lady\'s Choice','Condiments',145.00,'LAD-MAY-470ML',170.00,NULL,470.00,'ml',40,10,'https://ever.ph/cdn/shop/files/9000008479-Lady_s-Choice-Real-Mayonnaise-470ml-240415_a23bf414-b243-463f-b278-996bb3936c35.jpg?v=1772343956&width=823','active','2026-02-25 15:50:19','2026-03-17 17:55:30'),(15,'Del Monte Tomato Sauce','100% real tomatoes for your sweet Filipino-style spaghetti.','Del Monte','Condiments',32.00,'DEL-TOM-250G',39.00,NULL,250.00,'g',130,30,'https://ever.ph/cdn/shop/files/9000008556-Del-Monte-Tomato-Sauce-Original_Style-Pouch-250g-210414_4e5e8de7-7bae-49b8-a220-0a159e542692.jpg?v=1767244313&width=823','active','2026-02-25 15:50:19','2026-03-17 17:49:23'),(16,'Coca-Cola Original Taste 1.5L','Refreshing classic cola beverage, perfect for sharing.','Coca-Cola','Beverages',60.00,'COK-ORG-1.5L',75.00,NULL,1.50,'L',199,50,'https://ever.ph/cdn/shop/files/9000002369-Coke-Coca-Cola-Original-Taste-Less-Sugar-PET-1.5L-230206.jpg?v=1676528483&width=823','active','2026-02-25 15:50:19','2026-03-17 17:46:11'),(17,'C2 Green Tea Apple','Brewed from 100% natural green tea leaves with a hint of apple.','Universal Robina','Beverages',22.00,'C2-APP-500ML',28.00,NULL,500.00,'ml',155,40,'https://ever.ph/cdn/shop/files/9000002272-C2-Apple-Flavored-Green-Tea-455ml-250604.jpg?v=1749029755&width=823','active','2026-02-25 15:50:19','2026-03-17 17:45:45'),(18,'Oishi Prawn Crackers','Classic spicy and crispy prawn crackers.','Oishi','Snacks',12.00,'OIS-PRA-060G',16.00,NULL,60.00,'g',219,50,'https://ever.ph/cdn/shop/files/9000010290-Oishi_Prawn_Crackers_60g-200909_f695e55a-bb08-439a-9aa0-4a5e7d944a16.jpg?v=1725269937&width=823','active','2026-02-25 15:50:19','2026-03-17 17:58:55'),(19,'Boy Bawang Cornick Garlic','Crispy, extremely garlicky, and flavorful fried corn nuts.','Boy Bawang','Snacks',14.00,'BOY-GAR-100G',18.00,NULL,100.00,'g',138,30,'https://ever.ph/cdn/shop/files/100000003412-Boy-Bawang-Cornick-Garlic-90g-210712_0c8f46e6-9574-4b81-9e99-dcdaa38d918d.jpg?v=1768963420&width=823','active','2026-02-25 15:50:19','2026-03-17 17:44:40'),(20,'SkyFlakes Crackers','The undisputed number 1 cracker in the Philippines.','Monde Nissin','Snacks',45.00,'SKY-CRA-250G',55.00,NULL,250.00,'g',110,25,'https://ever.ph/cdn/shop/files/9000005957-SkyFlakes-Crackers-10x25g-221003_73a26cca-5c10-49ce-95fb-ecdeb19efac6.jpg?v=1772343845&width=823','active','2026-02-25 15:50:19','2026-03-17 18:02:40'),(21,'Cream-O Vanilla','Chocolate sandwich cookies with sweet vanilla cream filling.','Universal Robina','Snacks',8.00,'CRE-VAN-030G',10.00,NULL,30.00,'g',295,60,'https://urc.com.my/wp-content/uploads/2024/03/Cream-O-Vanilla-64g-min.png','active','2026-02-25 15:50:19','2026-03-17 17:47:17'),(22,'Gardenia Classic White Bread','Soft, freshly baked white sliced bread. A breakfast classic.','Gardenia','Beverages',68.00,'GAR-WHI-400G',78.00,NULL,400.00,'g',24,10,'https://ever.ph/cdn/shop/files/9000000120-Gardenia-White-Bread-Classic-Loaf-600g-230614_9d09c386-ae4a-462a-ba4e-516d42e15667.jpg?v=1770000747&width=823','active','2026-02-25 15:50:19','2026-03-17 17:52:41'),(23,'Safeguard Pure White Bar Soap','Antibacterial family bath soap that removes 99.9% of germs.','Safeguard','Beverages',38.00,'SAF-WHI-130G',45.00,NULL,130.00,'g',180,40,'https://ever.ph/cdn/shop/files/9000015237-Safeguard-Pure-White-Large-Bar-115g-250731.jpg?v=1754019346&width=823','active','2026-02-25 15:50:19','2026-03-17 18:00:59'),(24,'Creamsilk Standout Straight','Hair conditioner for straight and impeccably smooth hair.','Creamsilk','Beverages',110.00,'CRE-STR-180ML',135.00,NULL,180.00,'ml',65,15,'https://shophygiene.com.ph/cdn/shop/files/FOP_50kb_62731174_CREAMSILKROULTREBORNSTRT48X6X22MLx6.jpg?v=1747117010&width=1946','active','2026-02-25 15:50:19','2026-03-17 17:48:40'),(25,'Head & Shoulders Cool Menthol','Anti-dandruff shampoo with an incredibly cooling sensation.','Head & Shoulders','Beverages',125.00,'HNS-MEN-170ML',150.00,NULL,170.00,'ml',72,20,'https://ever.ph/cdn/shop/files/100000063537-Head-_-Shoulders-Anti-Dandruff-Shampoo-Cool-Menthol-170ml-260305.jpg?v=1772694859&width=823','active','2026-02-25 15:50:19','2026-03-17 17:53:45'),(26,'Surf Cherry Blossom Laundry Powder','Effective stain removal with a long-lasting floral scent.','Surf','Beverages',65.00,'SRF-CHE-800G',80.00,NULL,800.00,'g',85,25,'https://ever.ph/cdn/shop/files/100000091039-Surf-Ultra-Power-Liquid-Detergent-Cherry-Blossom-6x64ml-220608.jpg?v=1655103694&width=823','active','2026-02-25 15:50:19','2026-03-17 18:04:24'),(27,'Downy Antibac Fabric Conditioner','Softens clothes and prevents bacterial growth and kulob odor.','Downy','Beverages',105.00,'DWN-ANT-680ML',130.00,NULL,680.00,'ml',54,15,'https://ever.ph/cdn/shop/files/100000086679-Downy-Perfume-Antibac-Power-Kontra-Kulob-750ml-250918_9fb9f4ed-c657-4d34-aab2-41fda2d603cd.jpg?v=1761642348&width=823','active','2026-02-25 15:50:19','2026-03-17 17:51:42'),(28,'Green Cross Isopropyl Alcohol','70% solution antiseptic and disinfectant.','Green Cross','Beverages',70.00,'GRE-ISO-500ML',85.00,65.00,500.00,'ml',137,100,'https://ever.ph/cdn/shop/files/9000016032-Green-Cross-70_-Isoprophyl-Alcohol-with-Moisturizer-500ml-201012_55f6cb18-d432-40b2-a6a6-f816858f0ace.jpg?v=1731663444&width=823','active','2026-02-25 15:50:19','2026-03-17 17:53:10'),(29,'Whole Chicken','Frozen chicken from Magnolia. A trusted local brand for poultry. Chickens are raised in climate-controlled environments, unexposed to urban sprawl thus decreasing the threat of disease.','Magnolia','Meat & Poultry',200.00,'W-CKEN-1.7KG',240.00,NULL,1.70,'kg',5,10,'https://s3.ap-southeast-1.amazonaws.com/control-center.builtamart.com/public/products/3641-009-22-2025-120959-278.jpg?rand=0.8160431322287965','active','2026-03-17 16:31:59','2026-03-17 16:39:56'),(30,'Chicken Breast','Freshly chilled chicken from Magnolia. A trusted local brand for poultry. Chickens are raised in climate-controlled environments, unexposed to urban sprawl thus decreasing the threat of disease.','Magnolia','Meat & Poultry',160.00,'CHK-BRST-800G',190.00,NULL,800.00,'g',5,10,'https://control-center.builtamart.com/public-generated/products/6208-008-14-2025-120859-040-sd.jpg','active','2026-03-17 16:39:34','2026-03-17 16:39:34'),(31,'Chicken Wings','Freshly chilled chicken from Magnolia. A trusted local brand for poultry. Chickens are raised in climate-controlled environments, unexposed to urban sprawl thus decreasing the threat of disease.','Magnolia','Meat & Poultry',150.00,'CHK-WGS-1KG',185.00,NULL,1.00,'kg',20,10,'https://control-center.builtamart.com/public-generated/products/3546-008-14-2025-120826-953-sd.jpg','active','2026-03-17 16:54:11','2026-03-17 16:54:11'),(52,'Apple','Fresh red apples','Dole','Fresh Produce',15.00,'APPLE-DDE',20.00,NULL,NULL,NULL,30,10,'https://pngimg.com/uploads/apple/apple_PNG12405.png','active','2026-03-17 17:10:55','2026-03-17 17:20:37'),(53,'Banana','Fresh yellow bananas','Dole','Fresh Produce',8.00,'BANANA-DDE',12.00,NULL,NULL,NULL,50,10,'https://pngimg.com/uploads/banana/banana_PNG835.png','active','2026-03-17 17:10:55','2026-03-17 17:20:37'),(54,'Orange','Fresh juicy oranges','Sunkist','Fresh Produce',18.00,'ORANGE-SUN',25.00,NULL,NULL,NULL,25,10,'https://pngimg.com/uploads/orange/orange_PNG785.png','active','2026-03-17 17:10:55','2026-03-17 17:20:37'),(55,'Mango','Premium fresh mangoes','Gumaras Fresh','Fresh Produce',35.00,'MANGO-GUM',45.00,NULL,NULL,NULL,20,5,'https://pngimg.com/uploads/mango/mango_PNG9184.png','active','2026-03-17 17:10:55','2026-03-17 17:20:37'),(56,'Pineapple','Fresh sweet pineapples','Del Monte','Fresh Produce',40.00,'PINEAPPLE-DM',55.00,NULL,NULL,NULL,15,5,'https://pngimg.com/uploads/pineapple/pineapple_PNG2744.png','active','2026-03-17 17:10:55','2026-03-17 17:20:37'),(57,'Watermelon','Fresh sweet watermelons','Local Farm','Fresh Produce',60.00,'WATERMELON-LF',80.00,NULL,NULL,NULL,0,3,'https://pngimg.com/uploads/watermelon/watermelon_PNG2654.png','active','2026-03-17 17:10:55','2026-03-17 18:37:07'),(58,'Tomato','Fresh ripe tomatoes','Local Farm','Fresh Produce',10.00,'TOMATO-LF',14.00,NULL,NULL,NULL,40,15,'https://pngimg.com/uploads/tomato/tomato_PNG12591.png','active','2026-03-17 17:10:55','2026-03-17 17:20:37'),(59,'Onion','Fresh yellow onions','Local Farm','Fresh Produce',12.00,'ONION-LF',18.00,NULL,NULL,NULL,35,15,'https://pngimg.com/uploads/onion/onion_PNG99215.png','active','2026-03-17 17:10:55','2026-03-17 17:20:37'),(60,'Garlic','Fresh garlic bulbs','Local Farm','Fresh Produce',15.00,'GARLIC-LF',22.00,NULL,NULL,NULL,25,10,'https://pngimg.com/uploads/garlic/garlic_PNG12799.png','active','2026-03-17 17:10:55','2026-03-17 17:20:37'),(61,'Potato','Fresh potatoes','Baguio Fresh','Fresh Produce',20.00,'POTATO-BF',28.00,NULL,NULL,NULL,40,15,'https://pngimg.com/uploads/potato/potato_PNG7078.png','active','2026-03-17 17:10:55','2026-03-17 18:48:56');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
INSERT INTO `transactions` VALUES (1,1,4,'payment','cash',69.00,100.00,31.00,'','2026-02-24 19:50:04'),(2,2,4,'payment','cash',469.00,500.00,31.00,'','2026-02-24 20:37:29'),(3,3,4,'payment','cash',345.00,350.00,5.00,'','2026-02-24 20:49:36'),(4,4,4,'payment','cash',541.50,550.00,8.50,'','2026-02-24 20:53:14'),(5,5,4,'payment','cash',676.00,700.00,24.00,'','2026-02-25 12:49:01'),(6,6,6,'payment','cash',893.00,900.00,7.00,'','2026-02-25 18:13:41'),(7,7,6,'payment','cash',504.00,252.00,0.00,'','2026-02-25 19:22:53'),(8,8,6,'payment','prepaid',201.00,NULL,0.00,'21315123125234','2026-02-25 19:52:19'),(9,9,6,'payment','prepaid',58.00,NULL,0.00,'21512316345234132','2026-02-25 20:40:46'),(10,10,4,'payment','cash',269.00,200.00,11.70,'','2026-02-25 21:34:54'),(11,11,4,'payment','prepaid',65.00,NULL,0.00,'423234236324234','2026-02-25 21:35:06'),(12,12,4,'payment','prepaid',100.00,NULL,0.00,'2222222222222','2026-03-08 14:29:53'),(13,13,4,'payment','prepaid',139.00,NULL,0.00,'4444444444444','2026-03-08 15:00:13'),(14,14,4,'payment','prepaid',240.00,NULL,0.00,'5555555555555','2026-03-09 12:07:24');
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_addresses`
--

DROP TABLE IF EXISTS `user_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_addresses` (
  `address_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `address_label` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Home',
  `region` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `province` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `barangay` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `block_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lot_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`address_id`),
  KEY `idx_user_address` (`user_id`),
  CONSTRAINT `fk_address_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_addresses`
--

LOCK TABLES `user_addresses` WRITE;
/*!40000 ALTER TABLE `user_addresses` DISABLE KEYS */;
INSERT INTO `user_addresses` VALUES (1,1,'Home','NCR','Metro Manila','Caloocan City','176','Block 1','Lot 24','1428',1),(2,2,'Home','NCR','Metro Manila','Caloocan City','176','Block 1','Lot 1','1428',1),(3,7,'Home','NCR','METRO MANILA','CALOOCAN CITY','171','1','2','1428',1);
/*!40000 ALTER TABLE `user_addresses` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_profiles`
--

LOCK TABLES `user_profiles` WRITE;
/*!40000 ALTER TABLE `user_profiles` DISABLE KEYS */;
INSERT INTO `user_profiles` VALUES (1,1,'Ralp Anjelo','Mendoza','Armario','Jr.','+639971376355'),(2,2,'Hanjel','','Gatchi','','+639971376355'),(3,5,'Andrea','','Asierto',NULL,'09971376355'),(4,6,'Niña',NULL,'Manzanero',NULL,'09185637682'),(5,7,'John Noel','D.A.','Orano',NULL,'09923139504'),(6,8,'inventory','','1','','09923139504');
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
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Hanjel','$2y$10$G/5krO6C0KxKuKB7p16N2OCdzvvnBP9wsCghObh8p4tmI5wC62W7K','ralparmario202@gmail.com','customer',1,'active','2026-02-25 21:29:57','2026-02-20 20:07:51','2026-02-25 21:29:57'),(2,'Hansel','$2y$10$X.TdDJRAdLCvS7zuUA9m9u1NU/yt87TPnoGI.ItMEKhBxDkwVIstG','hanjel@gmail.com','customer',1,'active','2026-02-20 21:03:20','2026-02-20 21:03:02','2026-02-20 21:03:20'),(3,'admin','admin123','admin@pss.com','admin',0,'active','2026-03-17 15:43:07','2026-02-23 14:11:22','2026-03-17 15:43:07'),(4,'cashier1','cashier123','cashier@pss.com','cashier',0,'active','2026-03-17 16:43:17','2026-02-23 14:11:22','2026-03-17 16:43:17'),(5,'Andrea_Asierto','$2y$10$XuwQvn1bprrcrmdE6utUtuk.k4rMSLdVMrgmKnDUB0JWP4KCpY3d6','aseirtoandrea@gmail.com','cashier',1,'active',NULL,'2026-02-25 14:16:38','2026-02-25 14:16:38'),(6,'CAS-MANZANERO-2668','$2y$10$sdRjY7Mmz6l3zgxNpHQ3ZOZJQftl4SA6IHx8MCQIksvmA9G2Zyl/6','ninamanzanero@gmail.com','cashier',1,'active','2026-02-25 18:10:16','2026-02-25 14:27:24','2026-02-25 18:10:16'),(7,'Sumi','$2y$10$lfyS94XlRJFREIePUcIz8.d1V3KMgEGC.vKp2t9uWOXeSXL53St/2','johnnoelorano@gmail.com','customer',1,'active','2026-03-17 19:25:00','2026-03-06 13:43:07','2026-03-17 19:25:00'),(8,'INV-STAFF-6534','$2y$10$hgFIzA9VQcA2B4FS1GuM6uhDr/iCKc7wbbGb3eq2TBQh6LNhcS1bu','inventory@pss.com','inventory',1,'active','2026-03-17 19:26:00','2026-03-17 15:49:31','2026-03-17 19:26:00');
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wishlist`
--

LOCK TABLES `wishlist` WRITE;
/*!40000 ALTER TABLE `wishlist` DISABLE KEYS */;
INSERT INTO `wishlist` VALUES (6,1,3,'2026-02-25 15:38:47'),(9,7,29,'2026-03-17 16:36:16');
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

-- Dump completed on 2026-03-18  3:30:30
