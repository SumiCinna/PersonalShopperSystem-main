-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 28, 2026 at 05:18 AM
-- Server version: 8.0.40
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pss_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int NOT NULL,
  `user_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `invoice_id` int NOT NULL,
  `order_id` int NOT NULL,
  `invoice_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `grand_total` decimal(10,2) NOT NULL,
  `issued_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`invoice_id`, `order_id`, `invoice_no`, `subtotal`, `tax_amount`, `discount_amount`, `grand_total`, `issued_at`) VALUES
(1, 2, 'INV-20260224-5B79', 61.61, 7.39, 0.00, 69.00, '2026-02-24 19:50:04'),
(2, 3, 'INV-20260224-43E8', 418.75, 50.25, 0.00, 469.00, '2026-02-24 20:37:29'),
(3, 4, 'INV-20260224-1F12', 308.04, 36.96, 0.00, 345.00, '2026-02-24 20:49:36'),
(4, 5, 'INV-20260224-0AA0', 483.48, 58.02, 0.00, 541.50, '2026-02-24 20:53:14'),
(5, 6, 'INV-20260225-58C6', 603.57, 72.43, 0.00, 676.00, '2026-02-25 12:49:01'),
(6, 10, 'INV-20260225-EB8D', 797.32, 95.68, 0.00, 893.00, '2026-02-25 18:13:41'),
(7, 12, 'INV-20260225-C88A', 450.00, 54.00, 0.00, 504.00, '2026-02-25 19:22:53'),
(8, 13, 'INV-20260225-0E11', 179.46, 21.54, 0.00, 201.00, '2026-02-25 19:52:19'),
(9, 14, 'INV-20260225-78D8', 51.79, 6.21, 0.00, 58.00, '2026-02-25 20:40:46'),
(10, 15, 'INV-20260225-9884', 240.18, 28.82, 0.00, 269.00, '2026-02-25 21:34:54'),
(11, 16, 'INV-20260225-3E37', 58.04, 6.96, 0.00, 65.00, '2026-02-25 21:35:06');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int NOT NULL,
  `tracking_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gcash',
  `payment_status` enum('pending','paid','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `pickup_datetime` datetime DEFAULT NULL,
  `payment_type` enum('full','partial') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full',
  `upfront_payment` decimal(10,2) NOT NULL DEFAULT '0.00',
  `balance_due` decimal(10,2) NOT NULL DEFAULT '0.00',
  `online_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_status` enum('pending','processing','ready','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `tracking_no`, `user_id`, `total_amount`, `payment_method`, `payment_status`, `pickup_datetime`, `payment_type`, `upfront_payment`, `balance_due`, `online_reference`, `order_status`, `created_at`, `processed_by`) VALUES
(1, 'ORD-20260224-4E94', 1, 438.00, 'pay_on_pickup', 'pending', NULL, 'full', 0.00, 0.00, NULL, 'completed', '2026-02-24 09:00:32', 4),
(2, 'ORD-20260224-78A3', 1, 69.00, 'pay_on_pickup', 'paid', NULL, 'full', 0.00, 0.00, NULL, 'completed', '2026-02-24 18:00:57', 4),
(3, 'ORD-20260224-9C1E', 1, 469.00, 'pay_on_pickup', 'paid', NULL, 'full', 0.00, 0.00, NULL, 'completed', '2026-02-24 20:36:42', 4),
(4, 'ORD-20260224-EEBF', 1, 345.00, 'pay_on_pickup', 'paid', NULL, 'full', 0.00, 0.00, NULL, 'completed', '2026-02-24 20:49:05', 4),
(5, 'ORD-20260224-CFA2', 1, 541.50, 'pay_on_pickup', 'paid', NULL, 'full', 0.00, 0.00, NULL, 'completed', '2026-02-24 20:52:56', 4),
(6, 'ORD-20260225-18BA', 1, 676.00, 'pay_on_pickup', 'paid', NULL, 'full', 0.00, 0.00, NULL, 'completed', '2026-02-25 12:46:40', 4),
(7, 'ORD-20260225-93A2', 1, 122.50, 'pay_on_pickup', 'pending', NULL, 'full', 0.00, 0.00, NULL, 'cancelled', '2026-02-25 16:24:24', NULL),
(8, 'ORD-20260225-A3AA', 1, 347.00, 'pay_on_pickup', 'pending', NULL, 'full', 0.00, 0.00, NULL, 'cancelled', '2026-02-25 18:02:53', NULL),
(9, 'ORD-20260225-26E3', 1, 1050.00, 'online_payment', 'pending', NULL, 'full', 0.00, 0.00, NULL, 'cancelled', '2026-02-25 18:08:43', 6),
(10, 'ORD-20260225-2549', 1, 893.00, 'pay_on_pickup', 'paid', NULL, 'full', 0.00, 0.00, NULL, 'completed', '2026-02-25 18:12:18', 6),
(11, 'ORD-20260225-8351', 1, 34.00, 'pay_on_pickup', 'pending', NULL, 'full', 0.00, 0.00, NULL, 'cancelled', '2026-02-25 18:13:57', 6),
(12, 'ORD-20260225-A3F0', 1, 504.00, 'gcash', 'paid', '2026-02-26 13:10:00', 'partial', 252.00, 252.00, '312512312624213', 'completed', '2026-02-25 19:22:16', 6),
(13, 'ORD-20260225-2488', 1, 201.00, 'gcash', 'paid', '2026-02-26 16:44:00', 'full', 201.00, 0.00, '21315123125234', 'completed', '2026-02-25 19:44:25', 6),
(14, 'ORD-20260225-C69A', 1, 58.00, 'gcash', 'paid', '2026-02-26 14:02:00', 'full', 58.00, 0.00, '21512316345234132', 'completed', '2026-02-25 20:40:28', 6),
(15, 'ORD-20260225-0C5B', 1, 269.00, 'gcash', 'paid', '2026-02-26 13:00:00', 'partial', 80.70, 188.30, '123153215424321', 'completed', '2026-02-25 20:41:41', 4),
(16, 'ORD-20260225-1159', 1, 65.00, 'gcash', 'paid', '2026-02-26 14:00:00', 'full', 65.00, 0.00, '423234236324234', 'completed', '2026-02-25 21:33:04', 4),
(17, 'ORD-20260225-68B9', 1, 2800.00, 'gcash', 'pending', '2026-02-26 12:31:00', 'full', 2800.00, 0.00, '12412312123123123', 'cancelled', '2026-02-25 21:36:54', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int NOT NULL,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL,
  `price_at_checkout` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `quantity`, `price_at_checkout`) VALUES
(1, 1, 1, 3, 100.00),
(2, 1, 3, 4, 34.50),
(3, 2, 3, 2, 34.50),
(4, 3, 1, 4, 100.00),
(5, 3, 3, 2, 34.50),
(6, 4, 3, 10, 34.50),
(7, 5, 1, 3, 100.00),
(8, 5, 3, 7, 34.50),
(9, 6, 1, 4, 100.00),
(10, 6, 3, 8, 34.50),
(11, 7, 3, 1, 34.50),
(12, 7, 19, 1, 18.00),
(13, 7, 21, 7, 10.00),
(14, 8, 1, 1, 100.00),
(15, 8, 5, 1, 39.00),
(16, 8, 17, 1, 28.00),
(17, 8, 19, 10, 18.00),
(18, 9, 26, 5, 80.00),
(19, 9, 27, 5, 130.00),
(20, 10, 4, 1, 195.00),
(21, 10, 5, 2, 39.00),
(22, 10, 25, 3, 150.00),
(23, 10, 28, 2, 85.00),
(24, 11, 18, 1, 16.00),
(25, 11, 19, 1, 18.00),
(26, 12, 4, 2, 195.00),
(27, 12, 5, 1, 39.00),
(28, 12, 16, 1, 75.00),
(29, 13, 1, 1, 100.00),
(30, 13, 7, 1, 23.00),
(31, 13, 22, 1, 78.00),
(32, 14, 19, 1, 18.00),
(33, 14, 21, 4, 10.00),
(34, 15, 1, 1, 100.00),
(35, 15, 5, 1, 39.00),
(36, 15, 27, 1, 130.00),
(37, 16, 28, 1, 65.00),
(38, 17, 1, 28, 100.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `name`, `description`, `brand`, `category`, `cost_price`, `sku`, `price`, `discount_price`, `unit_value`, `unit_measure`, `stock`, `low_stock_threshold`, `image_url`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Bear Brand Fortified Powdered Milk', '', 'Bear Brand', 'Dairy', 83.00, 'BEAR-MILK-050G', 100.00, NULL, 50.00, 'g', 28, 50, 'https://cdn.store-assets.com/s/377840/i/53513617.jpeg', 'active', '2026-02-23 18:04:25', '2026-02-25 21:37:20'),
(3, 'Piattos Potato Crisps Cheese', '', 'JACK\'n JILL', 'Snacks', 28.00, 'PIATTOS-CHIPS-085G', 34.50, NULL, 85.00, 'g', 197, 50, 'https://ever.ph/cdn/shop/files/9000010103-Piattos-Potato-Crisps-Cheese-85g-201124_766c6f44-61a4-427f-9d05-5de994a9326f.jpg?v=1769925338&width=990', 'active', '2026-02-24 08:16:07', '2026-02-25 17:57:26'),
(4, 'Spam Classic', 'Fully cooked canned pork meat. The classic breakfast favorite.', 'Spam', 'Canned Goods', 160.00, 'SPM-CLS-340G', 195.00, NULL, 340.00, 'g', 82, 20, 'https://placehold.co/400x400/eeeeee/1e293b?text=Spam+Classic', 'active', '2026-02-25 15:50:19', '2026-02-25 19:22:16'),
(5, 'Argentina Corned Beef', 'Deliciously flavorful and filling corned beef for your pandesal.', 'Argentina', 'Canned Goods', 32.00, 'ARG-CBF-150G', 39.00, NULL, 150.00, 'g', 116, 30, 'https://placehold.co/400x400/eeeeee/1e293b?text=Argentina+Corned+Beef', 'active', '2026-02-25 15:50:19', '2026-02-25 20:41:41'),
(6, 'San Marino Corned Tuna', 'The perfect combination of corned beef taste and tuna health benefits.', 'San Marino', 'Canned Goods', 35.00, 'SMR-CTN-180G', 42.00, NULL, 180.00, 'g', 90, 20, 'https://placehold.co/400x400/eeeeee/1e293b?text=San+Marino+Tuna', 'active', '2026-02-25 15:50:19', '2026-02-25 15:50:19'),
(7, 'Mega Sardines in Tomato Sauce', 'Premium quality sardines in rich tomato sauce. Catch to can in 12 hours.', 'Mega', 'Canned Goods', 19.00, 'MGA-SAR-155G', 23.00, NULL, 155.00, 'g', 199, 50, 'https://placehold.co/400x400/eeeeee/1e293b?text=Mega+Sardines', 'active', '2026-02-25 15:50:19', '2026-02-25 19:44:25'),
(8, 'Nissin Seafood Cup Noodles', 'Instant cup noodles with rich and savory seafood broth.', 'Nissin', 'Noodles & Pasta', 35.00, 'NIS-SEA-070G', 42.00, NULL, 70.00, 'g', 150, 40, 'https://placehold.co/400x400/eeeeee/1e293b?text=Nissin+Cup+Noodles', 'active', '2026-02-25 15:50:19', '2026-02-25 15:50:19'),
(9, 'Maggi Magic Sarap', 'All-in-one seasoning granules for that extra umami.', 'Maggi', 'Condiments', 4.50, 'MAG-SAR-008G', 6.00, NULL, 8.00, 'g', 500, 100, 'https://placehold.co/400x400/eeeeee/1e293b?text=Magic+Sarap', 'active', '2026-02-25 15:50:19', '2026-02-25 15:50:19'),
(10, 'Knorr Sinigang sa Sampaloc Mix', 'Tamarind soup base for authentic, perfectly sour sinigang.', 'Knorr', 'Condiments', 18.00, 'KNO-SIN-040G', 22.00, NULL, 40.00, 'g', 180, 40, 'https://placehold.co/400x400/eeeeee/1e293b?text=Knorr+Sinigang', 'active', '2026-02-25 15:50:19', '2026-02-25 15:50:19'),
(11, 'Mang Tomas All-Purpose Sauce', 'Classic lechon sauce perfect for any fried or roasted dish.', 'Mang Tomas', 'Condiments', 38.00, 'MTO-APS-330G', 45.00, NULL, 330.00, 'g', 75, 15, 'https://placehold.co/400x400/eeeeee/1e293b?text=Mang+Tomas', 'active', '2026-02-25 15:50:19', '2026-02-25 15:50:19'),
(12, 'UFC Banana Ketchup', 'Tamis-anghang banana ketchup, a Filipino dining staple.', 'UFC', 'Condiments', 30.00, 'UFC-KET-320G', 36.00, NULL, 320.00, 'g', 110, 25, 'https://placehold.co/400x400/eeeeee/1e293b?text=UFC+Banana+Ketchup', 'active', '2026-02-25 15:50:19', '2026-02-25 15:50:19'),
(13, 'Eden Cheese', 'Creamy and melt-in-your-mouth processed filled cheese.', 'Eden', 'Dairy', 45.00, 'EDN-CHS-165G', 54.00, NULL, 165.00, 'g', 95, 20, 'https://placehold.co/400x400/eeeeee/1e293b?text=Eden+Cheese', 'active', '2026-02-25 15:50:19', '2026-02-25 15:50:19'),
(14, 'Lady\'s Choice Real Mayonnaise', 'Made with real eggs and healthy oils for the best macaroni salad.', 'Lady\'s Choice', 'Condiments', 145.00, 'LAD-MAY-470ML', 170.00, NULL, 470.00, 'ml', 40, 10, 'https://placehold.co/400x400/eeeeee/1e293b?text=Lady+Choice+Mayo', 'active', '2026-02-25 15:50:19', '2026-02-25 15:50:19'),
(15, 'Del Monte Tomato Sauce', '100% real tomatoes for your sweet Filipino-style spaghetti.', 'Del Monte', 'Condiments', 32.00, 'DEL-TOM-250G', 39.00, NULL, 250.00, 'g', 130, 30, 'https://placehold.co/400x400/eeeeee/1e293b?text=Del+Monte+Tomato', 'active', '2026-02-25 15:50:19', '2026-02-25 15:50:19'),
(16, 'Coca-Cola Original Taste 1.5L', 'Refreshing classic cola beverage, perfect for sharing.', 'Coca-Cola', 'Beverages', 60.00, 'COK-ORG-1.5L', 75.00, NULL, 1.50, 'L', 199, 50, 'https://placehold.co/400x400/eeeeee/1e293b?text=Coca-Cola+1.5L', 'active', '2026-02-25 15:50:19', '2026-02-25 19:22:16'),
(17, 'C2 Green Tea Apple', 'Brewed from 100% natural green tea leaves with a hint of apple.', 'Universal Robina', 'Beverages', 22.00, 'C2-APP-500ML', 28.00, NULL, 500.00, 'ml', 160, 40, 'https://placehold.co/400x400/eeeeee/1e293b?text=C2+Apple', 'active', '2026-02-25 15:50:19', '2026-02-25 18:03:11'),
(18, 'Oishi Prawn Crackers', 'Classic spicy and crispy prawn crackers.', 'Oishi', 'Snacks', 12.00, 'OIS-PRA-060G', 16.00, NULL, 60.00, 'g', 219, 50, 'https://placehold.co/400x400/eeeeee/1e293b?text=Oishi+Prawn+Crackers', 'active', '2026-02-25 15:50:19', '2026-02-25 18:13:57'),
(19, 'Boy Bawang Cornick Garlic', 'Crispy, extremely garlicky, and flavorful fried corn nuts.', 'Boy Bawang', 'Snacks', 14.00, 'BOY-GAR-100G', 18.00, NULL, 100.00, 'g', 138, 30, 'https://placehold.co/400x400/eeeeee/1e293b?text=Boy+Bawang', 'active', '2026-02-25 15:50:19', '2026-02-25 20:40:28'),
(20, 'SkyFlakes Crackers', 'The undisputed number 1 cracker in the Philippines.', 'Monde Nissin', 'Snacks', 45.00, 'SKY-CRA-250G', 55.00, NULL, 250.00, 'g', 110, 25, 'https://placehold.co/400x400/eeeeee/1e293b?text=SkyFlakes', 'active', '2026-02-25 15:50:19', '2026-02-25 15:50:19'),
(21, 'Cream-O Vanilla', 'Chocolate sandwich cookies with sweet vanilla cream filling.', 'Universal Robina', 'Snacks', 8.00, 'CRE-VAN-030G', 10.00, NULL, 30.00, 'g', 296, 60, 'https://placehold.co/400x400/eeeeee/1e293b?text=Cream-O', 'active', '2026-02-25 15:50:19', '2026-02-25 20:40:28'),
(22, 'Gardenia Classic White Bread', 'Soft, freshly baked white sliced bread. A breakfast classic.', 'Gardenia', 'Bakery', 68.00, 'GAR-WHI-400G', 78.00, NULL, 400.00, 'g', 24, 10, 'https://placehold.co/400x400/eeeeee/1e293b?text=Gardenia+Bread', 'active', '2026-02-25 15:50:19', '2026-02-25 19:44:25'),
(23, 'Safeguard Pure White Bar Soap', 'Antibacterial family bath soap that removes 99.9% of germs.', 'Safeguard', 'Personal Care', 38.00, 'SAF-WHI-130G', 45.00, NULL, 130.00, 'g', 180, 40, 'https://placehold.co/400x400/eeeeee/1e293b?text=Safeguard+Soap', 'active', '2026-02-25 15:50:19', '2026-02-25 15:50:19'),
(24, 'Creamsilk Standout Straight', 'Hair conditioner for straight and impeccably smooth hair.', 'Creamsilk', 'Personal Care', 110.00, 'CRE-STR-180ML', 135.00, NULL, 180.00, 'ml', 65, 15, 'https://placehold.co/400x400/eeeeee/1e293b?text=Creamsilk', 'active', '2026-02-25 15:50:19', '2026-02-25 15:50:19'),
(25, 'Head & Shoulders Cool Menthol', 'Anti-dandruff shampoo with an incredibly cooling sensation.', 'Head & Shoulders', 'Personal Care', 125.00, 'HNS-MEN-170ML', 150.00, NULL, 170.00, 'ml', 72, 20, 'https://placehold.co/400x400/eeeeee/1e293b?text=Head+and+Shoulders', 'active', '2026-02-25 15:50:19', '2026-02-25 18:12:18'),
(26, 'Surf Cherry Blossom Laundry Powder', 'Effective stain removal with a long-lasting floral scent.', 'Surf', 'Household', 65.00, 'SRF-CHE-800G', 80.00, NULL, 800.00, 'g', 85, 25, 'https://placehold.co/400x400/eeeeee/1e293b?text=Surf+Powder', 'active', '2026-02-25 15:50:19', '2026-02-25 18:08:43'),
(27, 'Downy Antibac Fabric Conditioner', 'Softens clothes and prevents bacterial growth and kulob odor.', 'Downy', 'Household', 105.00, 'DWN-ANT-680ML', 130.00, NULL, 680.00, 'ml', 54, 15, 'https://placehold.co/400x400/eeeeee/1e293b?text=Downy+Antibac', 'active', '2026-02-25 15:50:19', '2026-02-25 20:41:41'),
(28, 'Green Cross Isopropyl Alcohol', '70% solution antiseptic and disinfectant.', 'Green Cross', 'Beverages', 70.00, 'GRE-ISO-500ML', 85.00, 65.00, 500.00, 'ml', 137, 100, 'https://placehold.co/400x400/eeeeee/1e293b?text=Green+Cross+Alcohol', 'active', '2026-02-25 15:50:19', '2026-02-25 21:33:04');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int NOT NULL,
  `invoice_id` int NOT NULL,
  `cashier_id` int NOT NULL,
  `transaction_type` enum('payment','refund') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'payment',
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `amount_paid` decimal(10,2) NOT NULL,
  `cash_tendered` decimal(10,2) DEFAULT NULL,
  `change_given` decimal(10,2) DEFAULT NULL,
  `reference_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `invoice_id`, `cashier_id`, `transaction_type`, `payment_method`, `amount_paid`, `cash_tendered`, `change_given`, `reference_no`, `transaction_date`) VALUES
(1, 1, 4, 'payment', 'cash', 69.00, 100.00, 31.00, '', '2026-02-24 19:50:04'),
(2, 2, 4, 'payment', 'cash', 469.00, 500.00, 31.00, '', '2026-02-24 20:37:29'),
(3, 3, 4, 'payment', 'cash', 345.00, 350.00, 5.00, '', '2026-02-24 20:49:36'),
(4, 4, 4, 'payment', 'cash', 541.50, 550.00, 8.50, '', '2026-02-24 20:53:14'),
(5, 5, 4, 'payment', 'cash', 676.00, 700.00, 24.00, '', '2026-02-25 12:49:01'),
(6, 6, 6, 'payment', 'cash', 893.00, 900.00, 7.00, '', '2026-02-25 18:13:41'),
(7, 7, 6, 'payment', 'cash', 504.00, 252.00, 0.00, '', '2026-02-25 19:22:53'),
(8, 8, 6, 'payment', 'prepaid', 201.00, NULL, 0.00, '21315123125234', '2026-02-25 19:52:19'),
(9, 9, 6, 'payment', 'prepaid', 58.00, NULL, 0.00, '21512316345234132', '2026-02-25 20:40:46'),
(10, 10, 4, 'payment', 'cash', 269.00, 200.00, 11.70, '', '2026-02-25 21:34:54'),
(11, 11, 4, 'payment', 'prepaid', 65.00, NULL, 0.00, '423234236324234', '2026-02-25 21:35:06');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('customer','admin','cashier') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer',
  `terms_agreed` tinyint(1) DEFAULT '0',
  `status` enum('active','inactive','suspended') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `email`, `role`, `terms_agreed`, `status`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'Hanjel', '$2y$10$G/5krO6C0KxKuKB7p16N2OCdzvvnBP9wsCghObh8p4tmI5wC62W7K', 'ralparmario202@gmail.com', 'customer', 1, 'active', '2026-02-25 21:29:57', '2026-02-20 20:07:51', '2026-02-25 21:29:57'),
(2, 'Hansel', '$2y$10$X.TdDJRAdLCvS7zuUA9m9u1NU/yt87TPnoGI.ItMEKhBxDkwVIstG', 'hanjel@gmail.com', 'customer', 1, 'active', '2026-02-20 21:03:20', '2026-02-20 21:03:02', '2026-02-20 21:03:20'),
(3, 'admin', 'admin123', 'admin@pss.com', 'admin', 0, 'active', '2026-02-25 21:35:39', '2026-02-23 14:11:22', '2026-02-25 21:35:39'),
(4, 'cashier1', 'cashier123', 'cashier@pss.com', 'cashier', 0, 'active', '2026-02-25 21:33:36', '2026-02-23 14:11:22', '2026-02-25 21:33:36'),
(5, 'Andrea_Asierto', '$2y$10$XuwQvn1bprrcrmdE6utUtuk.k4rMSLdVMrgmKnDUB0JWP4KCpY3d6', 'aseirtoandrea@gmail.com', 'cashier', 1, 'active', NULL, '2026-02-25 14:16:38', '2026-02-25 14:16:38'),
(6, 'CAS-MANZANERO-2668', '$2y$10$sdRjY7Mmz6l3zgxNpHQ3ZOZJQftl4SA6IHx8MCQIksvmA9G2Zyl/6', 'ninamanzanero@gmail.com', 'cashier', 1, 'active', '2026-02-25 18:10:16', '2026-02-25 14:27:24', '2026-02-25 18:10:16');

-- --------------------------------------------------------

--
-- Table structure for table `user_addresses`
--

CREATE TABLE `user_addresses` (
  `address_id` int NOT NULL,
  `user_id` int NOT NULL,
  `address_label` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Home',
  `region` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `province` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `barangay` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `block_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lot_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_addresses`
--

INSERT INTO `user_addresses` (`address_id`, `user_id`, `address_label`, `region`, `province`, `city`, `barangay`, `block_no`, `lot_no`, `postal_code`, `is_default`) VALUES
(1, 1, 'Home', 'NCR', 'Metro Manila', 'Caloocan City', '176', 'Block 1', 'Lot 24', '1428', 1),
(2, 2, 'Home', 'NCR', 'Metro Manila', 'Caloocan City', '176', 'Block 1', 'Lot 1', '1428', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `profile_id` int NOT NULL,
  `user_id` int NOT NULL,
  `firstname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `middlename` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `surname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `suffix` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`profile_id`, `user_id`, `firstname`, `middlename`, `surname`, `suffix`, `mobile`) VALUES
(1, 1, 'Ralp Anjelo', 'Mendoza', 'Armario', 'Jr.', '+639971376355'),
(2, 2, 'Hanjel', '', 'Gatchi', '', '+639971376355'),
(3, 5, 'Andrea', '', 'Asierto', NULL, '09971376355'),
(4, 6, 'Niña', NULL, 'Manzanero', NULL, '09185637682');

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `wishlist_id` int NOT NULL,
  `user_id` int NOT NULL,
  `product_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wishlist`
--

INSERT INTO `wishlist` (`wishlist_id`, `user_id`, `product_id`, `created_at`) VALUES
(6, 1, 3, '2026-02-25 15:38:47');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD UNIQUE KEY `unique_user_product` (`user_id`,`product_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`invoice_id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD UNIQUE KEY `tracking_no` (`tracking_no`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_order_cashier` (`processed_by`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_price` (`price`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `cashier_id` (`cashier_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD PRIMARY KEY (`address_id`),
  ADD KEY `idx_user_address` (`user_id`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`profile_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`wishlist_id`),
  ADD UNIQUE KEY `unique_user_product` (`user_id`,`product_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `invoice_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_addresses`
--
ALTER TABLE `user_addresses`
  MODIFY `address_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_profiles`
--
ALTER TABLE `user_profiles`
  MODIFY `profile_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `wishlist_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_invoice_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE RESTRICT;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_order_cashier` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_transaction_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_transaction_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`invoice_id`) ON DELETE RESTRICT;

--
-- Constraints for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD CONSTRAINT `fk_address_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `fk_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `fk_wishlist_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_wishlist_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
