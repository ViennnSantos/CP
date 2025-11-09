-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 09, 2025 at 04:49 AM
-- Server version: 10.1.48-MariaDB-0ubuntu0.18.04.1
-- PHP Version: 8.4.7

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rads_tooling`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) UNSIGNED NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('Owner','Admin','Secretary') NOT NULL DEFAULT 'Secretary',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password`, `full_name`, `role`, `status`, `profile_image`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$keUVKBOWFJeuLAhnpZoCde82jdHMeNx./e8J0JfVyWYnSs5/rtAaO', 'System Owner44', 'Owner', 'active', 'uploads/avatars/avatar_1_1762298687.jpg', '2025-08-19 12:27:17', '2025-11-04 23:27:00'),
(3, 'kooqi', '$2y$10$kZsIWfAU7lDuZtxNEwtX7uI3VMr6cQEWdu.fhK6UufHSUFXMzcsUu', 'Muwen', 'Secretary', 'active', NULL, '2025-09-08 03:55:44', '2025-10-10 15:26:44'),
(5, 'moen1', '$2y$10$shzTFwmmyYjYhXsZieLgde5I4pCN8COVksfaX8WW6IqpiX/K8kYLK', 'moenmoen', 'Owner', 'active', 'uploads/avatars/avatar_5_1757631406.jpg', '2025-09-08 16:17:13', '2025-11-04 23:12:53'),
(7, 'admin2', '$2y$10$jhlRo2.mj1nXqS/W7aamzecqCWgRvgfTm7iyELp1PSrDVwmkwYATi', 'Admin2', 'Admin', 'active', 'uploads/avatars/avatar_7_1757533716.png', '2025-09-08 20:35:36', '2025-10-26 06:43:46'),
(8, 'moen', '$2y$10$ZeWD2l4zsfwWCzcmseZt8u2YKVVPBQPZmJlyKVTU/PZ7BVnOxBLNm', 'kooqi', 'Admin', 'active', NULL, '2025-09-10 18:33:15', '2025-10-07 13:06:08'),
(9, 'vim', '$2y$10$2MAPNPEUgvlTru18VV3zIOB5RvGeOnpT5d/rquwmw13czoqnX9Unq', 'Vien Santos', 'Admin', 'active', NULL, '2025-10-09 09:09:01', '2025-10-09 09:09:01'),
(10, 'xyriz', '$2y$10$Y4cSYJIhJLj3eVOCj3Hz5uQB0lnH/sPRkQqm3FudzUWolGVE29f6i', 'Xyriz Salog', 'Admin', 'active', NULL, '2025-10-12 17:50:27', '2025-10-12 17:50:27');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text,
  `type` enum('banner','promotion','announcement') DEFAULT 'announcement',
  `image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `display_order` int(11) DEFAULT '0',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `cabinets`
--

CREATE TABLE `cabinets` (
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` int(11) UNSIGNED NOT NULL,
  `customer_id` int(11) NOT NULL,
  `item_type` enum('product','cabinet') NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `cabinet_id` int(11) DEFAULT NULL,
  `customization_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT '1',
  `added_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `colors`
--

CREATE TABLE `colors` (
  `id` int(11) UNSIGNED NOT NULL,
  `color_name` varchar(50) NOT NULL,
  `color_code` varchar(50) NOT NULL,
  `hex_value` varchar(7) NOT NULL,
  `base_price` decimal(10,2) DEFAULT '0.00',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `colors`
--

INSERT INTO `colors` (`id`, `color_name`, `color_code`, `hex_value`, `base_price`, `is_active`, `created_at`) VALUES
(2, 'Green', 'GREEN', '#209400', 222.00, 1, '2025-11-08 22:29:46');

-- --------------------------------------------------------

--
-- Table structure for table `color_allowed_parts`
--

CREATE TABLE `color_allowed_parts` (
  `id` int(11) UNSIGNED NOT NULL,
  `color_id` int(11) NOT NULL,
  `part_name` varchar(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) UNSIGNED NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT '0',
  `verification_code` varchar(10) DEFAULT NULL,
  `verification_expires` datetime DEFAULT NULL,
  `password_reset_code` varchar(10) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `username`, `full_name`, `email`, `profile_image`, `email_verified`, `verification_code`, `verification_expires`, `password_reset_code`, `password_reset_expires`, `phone`, `address`, `password`, `created_at`, `updated_at`) VALUES
(16, 'kooqi', 'Moen Secapupu', 'moen.secapuri27@gmail.com', 'assets/uploads/avatars/customer_16_1760622859.jpg', 1, NULL, NULL, '249164', '2025-10-29 00:25:59', '+639192004234', 'Blk 4 Lot 47 Laterraza Phase A', '$2y$10$5nm.I29rcsZTm3CvPHayYOC3B7GHa/qY8seqlWXxjinHaB0sJRhDy', '2025-10-05 23:16:30', '2025-10-28 22:15:59'),
(17, 'moen', 'Moen Secapuri', 'kooqicookie@gmail.com', NULL, 1, NULL, NULL, NULL, NULL, '+639192088080', '', '$2y$10$l5kgj0CZ8/g1knnrhReSAOPJV5FDSIP3pMlJgqxJ5YSAax51bFLzG', '2025-10-05 23:32:09', '2025-11-06 11:39:51'),
(32, 'vim', 'Vien Santos', 'viensantos98@gmail.com', 'assets/uploads/avatars/customer_32_1760996431.jpg', 1, NULL, NULL, NULL, NULL, '+639873214351', '', '$2y$10$Nv20w9oWAXHkt4ryMTSwIOMgpETaFifghskzZoeeJiE3Vt1cHMPwm', '2025-10-15 13:20:52', '2025-11-08 11:58:49'),
(33, 'abcde', 'vien ezekiel santos', 'santos2284@cgscavite.com', NULL, 1, NULL, NULL, NULL, NULL, '+639762284470', NULL, '$2y$10$WS97oPps2IKkYlwAtHrDveoPncRwsTShJ6M7n9Kp3ODa1cwQU3lhq', '2025-10-20 20:01:48', '2025-10-20 20:02:25'),
(34, 'ayeut', 'irish ferreras', 'ferreras122402@gmail.com', NULL, 1, NULL, NULL, NULL, NULL, '+639123123123', NULL, '$2y$10$IB5YffBRCGiFG0PUPOgVpO3EebsHznCp.yPpPbXWUVNitcWsuqrZG', '2025-10-20 20:04:50', '2025-10-20 20:05:45'),
(35, 'rico', 'henric orapa', 'henric242003@gmail.com', 'assets/uploads/avatars/customer_35_1762233724.jpg', 1, NULL, NULL, NULL, NULL, '+639123452349', '', '$2y$10$m8nbzhpHAvKJUNXoQovkWefmu1Ft2ojjijwO9HzxAWOQ12yMC0bcO', '2025-11-04 05:01:25', '2025-11-04 05:22:09'),
(36, 'moenpogi21', 'Moen Secapuri', 'moenpogi045@gmail.com', NULL, 1, NULL, NULL, NULL, NULL, '+639937060282', NULL, '$2y$10$0KChlHZaythMamZ9WRetWuTxWQj36kwy8fx5ommJereid1jL.gCkW', '2025-11-04 05:33:08', '2025-11-04 05:33:29'),
(37, 'capstonk', 'Capstonk Capstoink', 'capstoink@gmail.com', NULL, 1, NULL, NULL, NULL, NULL, '+639856268378', NULL, '$2y$10$ZEkHTgHb1fUkI26rxSSG/eqfkDcK8ryORT.UT5GRVizy/5otFPKvu', '2025-11-04 05:33:58', '2025-11-04 05:35:40'),
(38, 'berna', 'Berna Marasigan', 'bernabanana0531@gmail.com', NULL, 1, NULL, NULL, NULL, NULL, '+639199731913', NULL, '$2y$10$SrbUm.837LYhwhHC3BS/sObVcUTBnOLWqYU6Jrc1RR9FjQbDzPf06', '2025-11-06 11:37:12', '2025-11-06 11:37:42'),
(39, 'markpogi123', 'mark milca', 'milcamark7@gmail.com', NULL, 1, NULL, NULL, NULL, NULL, '+639150463860', NULL, '$2y$10$I1tFHAzt0lP/VmvU3SuDuOWzPP4Ia2hnrw6bcY4e0Yg4W4892R1wm', '2025-11-06 17:03:04', '2025-11-06 17:03:25'),
(40, 'aaronpogi', 'aaron pogie', 'aaroncortez2417@gmail.com', 'assets/uploads/avatars/customer_40_1762501815.jpg', 1, NULL, NULL, NULL, NULL, '+639683270099', '', '$2y$10$S.NtwIRGf/0GwvpATj51Pu9HGGrmNU/UBJf6x4LrtMLGVS/osyIMi', '2025-11-06 17:22:47', '2025-11-07 07:50:38'),
(41, 'Ayiayi', 'Irish joy Ferreras', '12242002@gmail.com', NULL, 0, '656308', '2025-11-08 07:57:52', NULL, NULL, '+639563206419', NULL, '$2y$10$fpNlCIq0jJQX1yxFZC9OBebgB8yf0AkrUO0PK5jqOgVnyffkG3t4u', '2025-11-08 07:42:53', '2025-11-08 07:47:52'),
(42, 'peng', 'shan cablao', 'shancablao1@gmail.com', NULL, 1, NULL, NULL, NULL, NULL, '+639184459446', NULL, '$2y$10$Hz5uPbucCFomSWm799sryemMMtSh1egjetVSiq.qfF/jqgE/uXYDK', '2025-11-09 00:11:34', '2025-11-09 00:12:03');

-- --------------------------------------------------------

--
-- Table structure for table `customer_addresses`
--

CREATE TABLE `customer_addresses` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `address_nickname` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional nickname like Home, Office',
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `province_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'PSGC code',
  `city_municipality` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'PSGC code',
  `barangay` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `barangay_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'PSGC code',
  `street_block_lot` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'House number, street, building, etc.',
  `postal_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT '0' COMMENT '1 = default address, 0 = not default',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_addresses`
--

INSERT INTO `customer_addresses` (`id`, `customer_id`, `address_nickname`, `full_name`, `mobile_number`, `email`, `province`, `province_code`, `city_municipality`, `city_code`, `barangay`, `barangay_code`, `street_block_lot`, `postal_code`, `is_default`, `created_at`, `updated_at`) VALUES
(2, 32, 'Home', 'vien ezekiel santos', '+639762284470', 'viensantos98@gmail.com', 'Cavite', '0402100000', 'City of Bacoor', '0402103000', 'P.F. Espiritu I', '0402103018', 'Blk 18 Lot 99', '4117', 1, '2025-11-08 04:20:22', '2025-11-08 05:42:18'),
(3, 32, 'Office', 'Irish Joy', '+639563206419', NULL, 'Cavite', '0402100000', 'City of DasmariÃ±as', '0402106000', 'Fatima II', '0402106051', 'Blk 20 Lot 65', '4119', 0, '2025-11-08 04:21:08', '2025-11-08 05:21:53'),
(4, 32, 'Tambayan', 'Ayi', '+639563206419', NULL, 'Cavite', '0402100000', 'Gen. Mariano Alvarez', '0402123000', 'Kapitan Kua', '0402123017', 'Blk 12 Lot3', '4115', 0, '2025-11-08 05:42:13', '2025-11-08 05:42:18');

-- --------------------------------------------------------

--
-- Table structure for table `customer_carts`
--

CREATE TABLE `customer_carts` (
  `id` int(11) UNSIGNED NOT NULL,
  `customer_id` int(11) UNSIGNED NOT NULL,
  `cart_data` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'JSON formatted cart data',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_carts`
--

INSERT INTO `customer_carts` (`id`, `customer_id`, `cart_data`, `created_at`, `updated_at`) VALUES
(1, 42, '[]', '2025-11-09 00:15:55', '2025-11-09 00:15:55');

-- --------------------------------------------------------

--
-- Table structure for table `customizations`
--

CREATE TABLE `customizations` (
  `id` int(11) UNSIGNED NOT NULL,
  `customer_id` int(11) NOT NULL,
  `cabinet_id` int(11) NOT NULL,
  `color` varchar(50) DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL,
  `layout` varchar(50) DEFAULT NULL,
  `material` varchar(50) DEFAULT NULL,
  `price_adjustment` decimal(10,2) DEFAULT '0.00',
  `width` decimal(10,2) DEFAULT NULL,
  `height` decimal(10,2) DEFAULT NULL,
  `depth` decimal(10,2) DEFAULT NULL,
  `texture_id` int(11) DEFAULT NULL,
  `color_id` int(11) DEFAULT NULL,
  `handle_id` int(11) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT '0.00',
  `measurement_unit` varchar(20) DEFAULT 'cm',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `customization_steps`
--

CREATE TABLE `customization_steps` (
  `id` int(11) UNSIGNED NOT NULL,
  `customization_id` int(11) NOT NULL,
  `step_name` enum('size','texture','color','handle') NOT NULL,
  `is_completed` tinyint(1) DEFAULT '0',
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) UNSIGNED NOT NULL,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `comment` text,
  `status` enum('pending','released') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_released` tinyint(1) DEFAULT '0',
  `released_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `order_id`, `customer_id`, `rating`, `comment`, `status`, `created_at`, `is_released`, `released_at`) VALUES
(1, 23, 16, 5, 'great', 'released', '2025-10-24 04:17:26', 1, '2025-10-25 03:53:18'),
(2, 47, 35, 3, 'yes', 'pending', '2025-11-04 11:42:23', 0, NULL),
(4, 22, 32, 3, 'awit', 'pending', '2025-11-08 22:26:01', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `handle_types`
--

CREATE TABLE `handle_types` (
  `id` int(11) UNSIGNED NOT NULL,
  `handle_name` varchar(100) NOT NULL,
  `handle_code` varchar(50) NOT NULL,
  `handle_image` varchar(255) NOT NULL,
  `base_price` decimal(10,2) DEFAULT '0.00',
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `measurement_units`
--

CREATE TABLE `measurement_units` (
  `id` int(11) UNSIGNED NOT NULL,
  `unit_name` varchar(20) NOT NULL,
  `abbreviation` varchar(10) NOT NULL,
  `conversion_to_cm` decimal(10,4) NOT NULL DEFAULT '1.0000',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) UNSIGNED NOT NULL,
  `sender_type` enum('customer','admin') NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_type` enum('customer','admin') NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `read_status` tinyint(1) DEFAULT '0',
  `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) UNSIGNED NOT NULL,
  `order_code` varchar(20) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `remaining_balance` decimal(10,2) DEFAULT '0.00' COMMENT 'Outstanding balance to be paid',
  `terms_agreed` tinyint(1) DEFAULT '0' COMMENT 'Whether customer agreed to Terms & Conditions',
  `payment_status` enum('Pending','Partially Paid','Fully Paid') DEFAULT 'Pending',
  `is_installment` tinyint(1) DEFAULT '0' COMMENT '1=split payment, 0=single payment',
  `status` enum('Pending','Processing','Ready for Pickup','Ready for Delivery','In Transit','Delivered','Completed','Cancelled') DEFAULT 'Pending',
  `order_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cancelled_by` enum('customer','admin') DEFAULT NULL,
  `cancellation_reason` text,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `received_at` datetime DEFAULT NULL COMMENT 'When customer marked as received',
  `received_by_customer` tinyint(1) DEFAULT '0',
  `is_received` tinyint(1) NOT NULL DEFAULT '0',
  `customer_received_at` datetime DEFAULT NULL,
  `mode` enum('delivery','pickup') NOT NULL DEFAULT 'pickup',
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `vat` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_code`, `customer_id`, `total_amount`, `remaining_balance`, `terms_agreed`, `payment_status`, `is_installment`, `status`, `order_date`, `cancelled_by`, `cancellation_reason`, `cancelled_at`, `received_at`, `received_by_customer`, `is_received`, `customer_received_at`, `mode`, `subtotal`, `vat`, `created_at`) VALUES
(1, 'RT2511062188', 32, 25700.00, 25700.00, 0, 'Pending', 1, 'Pending', '2025-11-06 02:52:54', NULL, NULL, NULL, NULL, 0, 0, NULL, 'delivery', 22500.00, 2700.00, '2025-11-06 02:52:54'),
(2, 'RT2511067021', 37, 25700.00, 25700.00, 0, 'Pending', 1, 'Pending', '2025-11-06 06:42:21', NULL, NULL, NULL, NULL, 0, 0, NULL, 'delivery', 22500.00, 2700.00, '2025-11-06 06:42:21'),
(3, 'RT2511060235', 37, 25700.00, 25700.00, 0, 'Pending', 1, 'Pending', '2025-11-06 06:43:38', NULL, NULL, NULL, NULL, 0, 0, NULL, 'delivery', 22500.00, 2700.00, '2025-11-06 06:43:38'),
(4, 'RT2511069524', 37, 20160.00, 20160.00, 0, 'Pending', 1, 'Pending', '2025-11-06 06:50:02', NULL, NULL, NULL, NULL, 0, 0, NULL, 'pickup', 18000.00, 2160.00, '2025-11-06 06:50:02'),
(5, 'RT2511064150', 37, 20160.00, 20160.00, 0, 'Pending', 1, 'Pending', '2025-11-06 06:52:15', NULL, NULL, NULL, NULL, 0, 0, NULL, 'pickup', 18000.00, 2160.00, '2025-11-06 06:52:15'),
(6, 'RT2511062445', 17, 20160.00, 20160.00, 0, 'Pending', 0, 'Pending', '2025-11-06 11:24:26', NULL, NULL, NULL, NULL, 0, 0, NULL, 'pickup', 18000.00, 2160.00, '2025-11-06 11:24:26'),
(7, 'RT2511061256', 17, 25200.00, 0.00, 0, 'Fully Paid', 0, 'Processing', '2025-11-06 11:28:38', NULL, NULL, NULL, NULL, 0, 0, NULL, 'pickup', 22500.00, 2700.00, '2025-11-06 11:28:38'),
(8, 'RT2511063292', 38, 25200.00, 0.00, 0, '', 1, 'Processing', '2025-11-06 11:47:10', NULL, NULL, NULL, NULL, 0, 0, NULL, 'pickup', 22500.00, 2700.00, '2025-11-06 11:47:10'),
(9, 'RT2511066850', 39, 25700.00, 25700.00, 0, 'Pending', 1, 'Pending', '2025-11-06 17:09:17', NULL, NULL, NULL, NULL, 0, 0, NULL, 'delivery', 22500.00, 2700.00, '2025-11-06 17:09:17'),
(10, 'RT2511072372', 37, 24080.00, 24080.00, 0, 'Pending', 1, 'Pending', '2025-11-07 05:47:06', NULL, NULL, NULL, NULL, 0, 0, NULL, 'pickup', 21500.00, 2580.00, '2025-11-07 05:47:06'),
(11, 'RT2511072060', 37, 14336.00, 7168.00, 0, 'Partially Paid', 1, 'Completed', '2025-11-07 12:44:36', NULL, NULL, NULL, NULL, 0, 0, NULL, 'pickup', 12800.00, 1536.00, '2025-11-07 12:44:36'),
(12, 'RT2511077342', 32, 14836.00, 14836.00, 0, 'Pending', 1, 'Pending', '2025-11-07 18:12:29', NULL, NULL, NULL, NULL, 0, 0, NULL, 'delivery', 12800.00, 1536.00, '2025-11-07 18:12:29'),
(13, 'RT2511082487', 32, 14836.00, 14836.00, 0, 'Pending', 1, 'Pending', '2025-11-08 17:37:31', NULL, NULL, NULL, NULL, 0, 0, NULL, 'delivery', 12800.00, 1536.00, '2025-11-08 17:37:31'),
(14, 'RT2511082185', 32, 7444.00, 7444.00, 1, 'Pending', 1, 'Pending', '2025-11-08 20:52:51', NULL, NULL, NULL, NULL, 0, 0, NULL, 'delivery', 6200.00, 744.00, '2025-11-08 20:52:51'),
(15, 'RT2511087048', 32, 6944.00, 6944.00, 1, 'Pending', 1, 'Pending', '2025-11-08 20:54:08', NULL, NULL, NULL, NULL, 0, 0, NULL, 'pickup', 6200.00, 744.00, '2025-11-08 20:54:08'),
(16, 'RT2511083864', 32, 7444.00, 7444.00, 1, 'Pending', 1, 'Pending', '2025-11-08 20:56:49', NULL, NULL, NULL, NULL, 0, 0, NULL, 'delivery', 6200.00, 744.00, '2025-11-08 20:56:49'),
(17, 'RT2511086623', 32, 7444.00, 7444.00, 0, 'Pending', 1, 'Pending', '2025-11-08 21:09:08', NULL, NULL, NULL, NULL, 0, 0, NULL, 'delivery', 6200.00, 744.00, '2025-11-08 21:09:08'),
(18, 'RT2511082126', 32, 14836.00, 14836.00, 0, 'Pending', 1, 'Pending', '2025-11-08 21:16:26', NULL, NULL, NULL, NULL, 0, 0, NULL, 'delivery', 12800.00, 1536.00, '2025-11-08 21:16:26'),
(19, 'RT2511083386', 32, 7444.00, 7444.00, 0, 'Pending', 1, 'Pending', '2025-11-08 21:28:56', NULL, NULL, NULL, NULL, 0, 0, NULL, 'delivery', 6200.00, 744.00, '2025-11-08 21:28:56'),
(20, 'RT2511082253', 32, 7444.00, 7444.00, 0, 'Pending', 1, 'Pending', '2025-11-08 21:29:32', NULL, NULL, NULL, NULL, 0, 0, NULL, 'delivery', 6200.00, 744.00, '2025-11-08 21:29:32'),
(21, 'RT2511081384', 32, 7444.00, 7444.00, 0, 'Pending', 1, 'Pending', '2025-11-08 21:42:03', NULL, NULL, NULL, NULL, 0, 0, NULL, 'delivery', 6200.00, 744.00, '2025-11-08 21:42:03'),
(22, 'RT2511087785', 32, 7444.00, 3722.00, 0, 'Partially Paid', 1, 'Completed', '2025-11-08 22:05:38', NULL, NULL, NULL, NULL, 0, 0, NULL, 'delivery', 6200.00, 744.00, '2025-11-08 22:05:38'),
(23, 'RT2511088956', 32, 24080.00, 12040.00, 0, 'Fully Paid', 1, 'Processing', '2025-11-08 22:20:45', NULL, NULL, NULL, NULL, 0, 0, NULL, 'pickup', 21500.00, 2580.00, '2025-11-08 22:20:45'),
(24, 'RT2511097849', 32, 12596.00, 12596.00, 0, 'Pending', 1, 'Pending', '2025-11-08 23:50:34', NULL, NULL, NULL, NULL, 0, 0, NULL, 'delivery', 10800.00, 1296.00, '2025-11-08 23:50:34'),
(25, 'RT2511091251', 32, 14836.00, 7418.00, 0, 'Fully Paid', 1, 'Processing', '2025-11-09 02:15:27', NULL, NULL, NULL, NULL, 0, 0, NULL, 'delivery', 12800.00, 1536.00, '2025-11-09 02:15:27');

-- --------------------------------------------------------

--
-- Table structure for table `order_addresses`
--

CREATE TABLE `order_addresses` (
  `id` int(11) UNSIGNED NOT NULL,
  `order_id` int(11) NOT NULL,
  `type` enum('delivery','pickup') NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `barangay` varchar(120) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `postal` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `order_addresses`
--

INSERT INTO `order_addresses` (`id`, `order_id`, `type`, `first_name`, `last_name`, `phone`, `email`, `province`, `city`, `barangay`, `street`, `postal`) VALUES
(1, 1, '', 'test', 'test', '+630156156165', '', 'Cavite', 'Rosario', 'Gumot-Nagcolaran', '12', '4117'),
(2, 2, '', 'Moen', 'Secapuri', '+639937060282', 'moenpogi045@gmail.com', 'Batangas', 'Laurel', 'Molinete', 'Blk 4 Lot 47 Phase A Laterraza Subdivision', '4103'),
(3, 3, '', 'Moen', 'Secapuri', '+639937060282', 'moenpogi045@gmail.com', 'Batangas', 'Laurel', 'Molinete', 'Blk 4 Lot 47 Phase A Laterraza Subdivision', '4103'),
(4, 4, '', 'Moen', 'Secapuri', '+639937060282', 'moenpogi045@gmail.com', '', '', '', '', ''),
(5, 5, '', 'Moen', 'Secapuri', '+639937060282', 'moenpogi045@gmail.com', '', '', '', '', ''),
(6, 6, '', 'halamang bingot', 'para sa shota mong panot', '+631232131231', 'bingot@bingot.com', '', '', '', '', ''),
(7, 7, '', 'Bingot', 'Swazzineggar', '+631231231232', 'bingot.things@gmail.com', '', '', '', '', ''),
(8, 8, '', 'Berna', 'Marasigan', '+639199731913', '', '', '', '', '', ''),
(9, 9, '', 'Ma Elena', 'Milca', '+639150463860', 'milcamark7@gmail.com', 'Cavite', 'City of DasmariÃ±as', 'Fatima III', 'Blk 3 Lot 3 Zone 11 Bulihan Silang Cavite', '4118'),
(10, 10, '', 'Moen', 'Secapuri', '+639937060282', 'moenpogi045@gmail.com', '', '', '', '', ''),
(11, 11, '', 'Moen', 'Secapuri', '+639937060282', 'moenpogi045@gmail.com', '', '', '', '', ''),
(12, 12, '', 'vien ezekiel', 'santos', '+639762284470', 'viensantos98@gmail.com', 'Cavite', 'City of DasmariÃ±as', 'Burol II', 'Barangay Severino, Delas Alas', '4117'),
(13, 13, '', 'vien', 'ezekiel santos', '+639762284470', 'viensantos98@gmail.com', 'Cavite', 'City of Bacoor', 'P.F. Espiritu I', 'Blk 18 Lot 99', '4117'),
(14, 14, '', 'vien', 'ezekiel santos', '+639762284470', 'viensantos98@gmail.com', 'Cavite', 'City of Bacoor', 'P.F. Espiritu I', 'Blk 18 Lot 99', '4117'),
(15, 15, '', 'vien ezekiel', 'santos', '+639762284470', 'viensantos98@gmail.com', '', '', '', '', ''),
(16, 16, '', 'vien', 'ezekiel santos', '+639762284470', 'viensantos98@gmail.com', 'Cavite', 'City of Bacoor', 'P.F. Espiritu I', 'Blk 18 Lot 99', '4117'),
(17, 17, '', 'vien', 'ezekiel santos', '+639762284470', 'viensantos98@gmail.com', 'Cavite', 'City of Bacoor', 'P.F. Espiritu I', 'Blk 18 Lot 99', '4117'),
(18, 18, '', 'vien', 'ezekiel santos', '+639762284470', 'viensantos98@gmail.com', 'Cavite', 'City of Bacoor', 'P.F. Espiritu I', 'Blk 18 Lot 99', '4117'),
(19, 19, '', 'vien', 'ezekiel santos', '+639762284470', 'viensantos98@gmail.com', 'Cavite', 'City of Bacoor', 'P.F. Espiritu I', 'Blk 18 Lot 99', '4117'),
(20, 20, '', 'vien', 'ezekiel santos', '+639762284470', 'viensantos98@gmail.com', 'Cavite', 'City of Bacoor', 'P.F. Espiritu I', 'Blk 18 Lot 99', '4117'),
(21, 21, '', 'vien', 'ezekiel santos', '+639762284470', 'viensantos98@gmail.com', 'Cavite', 'City of Bacoor', 'P.F. Espiritu I', 'Blk 18 Lot 99', '4117'),
(22, 22, '', 'vien', 'ezekiel santos', '+639762284470', 'viensantos98@gmail.com', 'Cavite', 'City of Bacoor', 'P.F. Espiritu I', 'Blk 18 Lot 99', '4117'),
(23, 23, '', 'vien ezekiel', 'santos', '+639762284470', 'viensantos98@gmail.com', '', '', '', '', ''),
(24, 24, '', 'vien', 'ezekiel santos', '+639762284470', 'viensantos98@gmail.com', 'Cavite', 'City of Bacoor', 'P.F. Espiritu I', 'Blk 18 Lot 99', '4117'),
(25, 25, '', 'vien', 'ezekiel santos', '+639762284470', 'viensantos98@gmail.com', 'Cavite', 'City of Bacoor', 'P.F. Espiritu I', 'Blk 18 Lot 99', '4117');

-- --------------------------------------------------------

--
-- Table structure for table `order_completions`
--

CREATE TABLE `order_completions` (
  `id` int(11) UNSIGNED NOT NULL,
  `order_id` int(11) NOT NULL,
  `completed_by` enum('customer','admin') NOT NULL DEFAULT 'customer',
  `completion_type` enum('pickup','delivery') NOT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `video_path` varchar(255) DEFAULT NULL,
  `notes` text,
  `completed_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) UNSIGNED NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_type` enum('product','cabinet') NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `cabinet_id` int(11) DEFAULT NULL,
  `customization_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT '1',
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `qty` int(11) DEFAULT NULL,
  `line_total` decimal(12,2) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL COMMENT 'Product image path'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `item_type`, `product_id`, `cabinet_id`, `customization_id`, `quantity`, `unit_price`, `subtotal`, `name`, `qty`, `line_total`, `image`) VALUES
(1, 1, 'product', 2, NULL, NULL, 1, 22500.00, 0.00, 'Rustic Oak Kitchen Cabinet', 1, 22500.00, NULL),
(2, 2, 'product', 2, NULL, NULL, 1, 22500.00, 0.00, 'Rustic Oak Kitchen Cabinet', 1, 22500.00, NULL),
(3, 3, 'product', 2, NULL, NULL, 1, 22500.00, 0.00, 'Rustic Oak Kitchen Cabinet', 1, 22500.00, NULL),
(4, 4, 'product', 1, NULL, NULL, 1, 18000.00, 0.00, 'Modern Glossy Kitchen Cabinet', 1, 18000.00, NULL),
(5, 5, 'product', 1, NULL, NULL, 1, 18000.00, 0.00, 'Modern Glossy Kitchen Cabinet', 1, 18000.00, NULL),
(6, 6, 'product', 1, NULL, NULL, 1, 18000.00, 0.00, 'Modern Glossy Kitchen Cabinet', 1, 18000.00, NULL),
(7, 7, 'product', 2, NULL, NULL, 1, 22500.00, 0.00, 'Rustic Oak Kitchen Cabinet', 1, 22500.00, NULL),
(8, 8, 'product', 2, NULL, NULL, 1, 22500.00, 0.00, 'Rustic Oak Kitchen Cabinet', 1, 22500.00, NULL),
(9, 9, 'product', 2, NULL, NULL, 1, 22500.00, 0.00, 'Rustic Oak Kitchen Cabinet', 1, 22500.00, NULL),
(10, 10, 'product', 9, NULL, NULL, 1, 21500.00, 0.00, 'Executive Wooden File Cabinet', 1, 21500.00, NULL),
(11, 11, 'product', 10, NULL, NULL, 1, 12800.00, 0.00, 'Floating Vanity Cabinet', 1, 12800.00, NULL),
(12, 12, 'product', 10, NULL, NULL, 1, 12800.00, 0.00, 'Floating Vanity Cabinet', 1, 12800.00, NULL),
(13, 13, 'product', 10, NULL, NULL, 1, 12800.00, 0.00, 'Floating Vanity Cabinet', 1, 12800.00, NULL),
(14, 14, 'product', 11, NULL, NULL, 1, 6200.00, 0.00, 'Compact Wall Cabinet', 1, 6200.00, NULL),
(15, 15, 'product', 11, NULL, NULL, 1, 6200.00, 0.00, 'Compact Wall Cabinet', 1, 6200.00, NULL),
(16, 16, 'product', 11, NULL, NULL, 1, 6200.00, 0.00, 'Compact Wall Cabinet', 1, 6200.00, NULL),
(17, 17, 'product', 11, NULL, NULL, 1, 6200.00, 0.00, 'Compact Wall Cabinet', 1, 6200.00, NULL),
(18, 18, 'product', 10, NULL, NULL, 1, 12800.00, 0.00, 'Floating Vanity Cabinet', 1, 12800.00, NULL),
(19, 19, 'product', 11, NULL, NULL, 1, 6200.00, 0.00, 'Compact Wall Cabinet', 1, 6200.00, NULL),
(20, 20, 'product', 11, NULL, NULL, 1, 6200.00, 0.00, 'Compact Wall Cabinet', 1, 6200.00, NULL),
(21, 21, 'product', 11, NULL, NULL, 1, 6200.00, 0.00, 'Compact Wall Cabinet', 1, 6200.00, NULL),
(22, 22, 'product', 11, NULL, NULL, 1, 6200.00, 0.00, 'Compact Wall Cabinet', 1, 6200.00, NULL),
(23, 23, 'product', 9, NULL, NULL, 1, 21500.00, 0.00, 'Executive Wooden File Cabinet', 1, 21500.00, NULL),
(24, 24, 'product', 7, NULL, NULL, 1, 10800.00, 0.00, 'Compact Wooden Cabinet', 1, 10800.00, NULL),
(25, 25, 'product', 10, NULL, NULL, 1, 12800.00, 0.00, 'Floating Vanity Cabinet', 1, 12800.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` int(11) UNSIGNED NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` enum('Pending','Processing','Completed','Cancelled') NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `notes` text,
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `order_status_history`
--

INSERT INTO `order_status_history` (`id`, `order_id`, `status`, `changed_by`, `notes`, `changed_at`) VALUES
(1, 23, '', 1, 'Payment status changed from \'Partially Paid\' to \'Fully Paid\' by Owner', '2025-11-08 23:47:17'),
(2, 23, '', 1, 'Payment status changed from \'Fully Paid\' to \'Fully Paid\' by Owner', '2025-11-08 23:47:44'),
(3, 23, '', 1, 'Payment status changed from \'Fully Paid\' to \'Fully Paid\' by Owner', '2025-11-08 23:47:53'),
(4, 23, '', 1, 'Payment status changed from \'Fully Paid\' to \'Fully Paid\' by Owner', '2025-11-08 23:48:04'),
(5, 23, '', 1, 'Payment status changed from \'Fully Paid\' to \'Fully Paid\' by Owner', '2025-11-08 23:54:56'),
(6, 23, '', 1, 'Payment status changed from \'Fully Paid\' to \'Fully Paid\' by Owner', '2025-11-08 23:55:00'),
(7, 23, 'Processing', 1, 'Status changed from \'Processing\' to \'Processing\' by Owner', '2025-11-09 01:31:24'),
(8, 23, '', 1, 'Payment status changed from \'Fully Paid\' to \'Fully Paid\' by Owner', '2025-11-09 01:31:24'),
(9, 23, 'Processing', 1, 'Status changed from \'Processing\' to \'Processing\' by Owner', '2025-11-09 01:31:30'),
(10, 23, '', 1, 'Payment status changed from \'Fully Paid\' to \'Fully Paid\' by Owner', '2025-11-09 01:31:30'),
(11, 23, 'Processing', 1, 'Status changed from \'Processing\' to \'Processing\' by Owner', '2025-11-09 01:36:05'),
(12, 23, '', 1, 'Payment status changed from \'Fully Paid\' to \'Fully Paid\' by Owner', '2025-11-09 01:36:05'),
(13, 23, 'Processing', 1, 'Status changed from \'Processing\' to \'Processing\' by Owner', '2025-11-09 02:13:31'),
(14, 23, '', 1, 'Payment status changed from \'Fully Paid\' to \'Fully Paid\' by Owner', '2025-11-09 02:13:31'),
(15, 25, 'Pending', 1, 'Status changed from \'Pending\' to \'Pending\' by Owner', '2025-11-09 02:18:29'),
(16, 25, '', 1, 'Payment status changed from \'Partially Paid\' to \'Fully Paid\' by Owner', '2025-11-09 02:18:29'),
(17, 25, 'Pending', 1, 'Status changed from \'Pending\' to \'Pending\' by Owner', '2025-11-09 02:52:21'),
(18, 25, '', 1, 'Payment status changed from \'Fully Paid\' to \'Fully Paid\' by Owner', '2025-11-09 02:52:21'),
(19, 25, 'Pending', 1, 'Status changed from \'Pending\' to \'Pending\' by Owner', '2025-11-09 02:52:37'),
(20, 25, '', 1, 'Payment status changed from \'Fully Paid\' to \'Fully Paid\' by Owner', '2025-11-09 02:52:37'),
(21, 25, 'Pending', 1, 'Status changed from \'Pending\' to \'Pending\' by Owner', '2025-11-09 02:56:56'),
(22, 25, '', 1, 'Payment status changed from \'Fully Paid\' to \'With Balance\' by Owner', '2025-11-09 02:56:56'),
(23, 8, 'Processing', 1, 'Status changed from \'Processing\' to \'Processing\' by Owner', '2025-11-09 02:57:04'),
(24, 8, '', 1, 'Payment status changed from \'Fully Paid\' to \'With Balance\' by Owner', '2025-11-09 02:57:04'),
(25, 25, 'Processing', 1, 'Status changed from \'Pending\' to \'Processing\' by Owner', '2025-11-09 03:34:47'),
(26, 25, 'Processing', 1, 'Status changed from \'Processing\' to \'Processing\' by Owner', '2025-11-09 03:49:26'),
(27, 25, '', 1, 'Payment status changed from \'\' to \'Fully Paid\' by Owner', '2025-11-09 03:49:26');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) UNSIGNED NOT NULL,
  `user_type` enum('customer','admin') NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) UNSIGNED NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_method` enum('GCash QR','Cash','Bank Transfer','Cheque') NOT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `method` enum('gcash','bpi') DEFAULT NULL,
  `deposit_rate` int(11) DEFAULT NULL,
  `amount_due` decimal(12,2) DEFAULT NULL,
  `status` enum('PENDING_VERIFICATION','VERIFIED','REJECTED') DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `order_id`, `user_id`, `amount_paid`, `payment_method`, `qr_code`, `payment_date`, `verified_by`, `verified_at`, `method`, `deposit_rate`, `amount_due`, `status`, `updated_at`) VALUES
(1, 1, 0, 0.00, 'GCash QR', NULL, '2025-11-06 02:52:54', NULL, NULL, 'gcash', 50, 10080.00, '', '2025-11-06 06:50:02'),
(2, 5, 0, 0.00, 'GCash QR', NULL, '2025-11-06 06:52:15', NULL, NULL, 'gcash', 50, 10080.00, '', '2025-11-06 06:52:15'),
(3, 6, 0, 0.00, 'GCash QR', NULL, '2025-11-06 11:24:27', NULL, NULL, 'gcash', 100, 20160.00, '', '2025-11-06 11:24:27'),
(4, 7, 0, 25200.00, 'GCash QR', NULL, '2025-11-06 11:28:39', 1, '2025-11-07 05:46:06', 'gcash', 100, 25200.00, 'VERIFIED', '2025-11-07 05:46:06'),
(5, 8, 0, 80000.00, 'GCash QR', NULL, '2025-11-06 11:47:10', 1, '2025-11-06 11:49:56', 'bpi', 30, 7560.00, 'VERIFIED', '2025-11-06 11:49:56'),
(6, 9, 0, 0.00, 'GCash QR', NULL, '2025-11-06 17:09:18', NULL, NULL, 'gcash', 50, 12850.00, 'REJECTED', '2025-11-08 21:18:29'),
(7, 10, 0, 0.00, 'GCash QR', NULL, '2025-11-07 05:47:06', NULL, NULL, 'gcash', 50, 12040.00, '', '2025-11-07 05:47:06'),
(8, 11, 0, 7168.00, 'GCash QR', NULL, '2025-11-07 12:44:36', 1, '2025-11-07 12:45:30', 'gcash', 50, 7168.00, 'VERIFIED', '2025-11-07 12:45:30'),
(9, 12, 0, 0.00, 'GCash QR', NULL, '2025-11-07 18:12:29', NULL, NULL, 'bpi', 50, 7418.00, '', '2025-11-07 18:12:29'),
(10, 13, 0, 0.00, 'GCash QR', NULL, '2025-11-08 17:37:31', NULL, NULL, 'gcash', 50, 7418.00, '', '2025-11-08 17:37:31'),
(11, 13, 0, 0.00, 'GCash QR', NULL, '2025-11-08 17:38:06', NULL, NULL, 'gcash', 50, 7418.00, '', '2025-11-08 17:38:06'),
(12, 13, 0, 0.00, 'GCash QR', NULL, '2025-11-08 17:38:33', NULL, NULL, 'gcash', 50, 7418.00, '', '2025-11-08 17:38:33'),
(13, 13, 0, 0.00, 'GCash QR', NULL, '2025-11-08 17:38:41', NULL, NULL, 'gcash', 50, 7418.00, '', '2025-11-08 17:38:41'),
(14, 13, 0, 0.00, 'GCash QR', NULL, '2025-11-08 17:41:30', NULL, NULL, 'gcash', 50, 7418.00, '', '2025-11-08 17:41:30'),
(15, 13, 0, 0.00, 'GCash QR', NULL, '2025-11-08 17:41:30', NULL, NULL, 'gcash', 50, 7418.00, '', '2025-11-08 17:41:30'),
(16, 14, 0, 0.00, 'GCash QR', NULL, '2025-11-08 20:52:51', NULL, NULL, 'gcash', 50, 3722.00, '', '2025-11-08 20:52:51'),
(17, 14, 0, 0.00, 'GCash QR', NULL, '2025-11-08 20:52:57', NULL, NULL, 'gcash', 50, 3722.00, '', '2025-11-08 20:52:57'),
(18, 14, 0, 0.00, 'GCash QR', NULL, '2025-11-08 20:53:37', NULL, NULL, 'gcash', 50, 3722.00, '', '2025-11-08 20:53:37'),
(19, 14, 0, 0.00, 'GCash QR', NULL, '2025-11-08 20:53:43', NULL, NULL, 'gcash', 50, 3722.00, '', '2025-11-08 20:53:43'),
(20, 15, 0, 0.00, 'GCash QR', NULL, '2025-11-08 20:54:08', NULL, NULL, 'bpi', 50, 3472.00, '', '2025-11-08 20:54:08'),
(21, 16, 0, 0.00, 'GCash QR', NULL, '2025-11-08 20:56:49', NULL, NULL, 'gcash', 50, 3722.00, '', '2025-11-08 20:56:49'),
(22, 17, 0, 0.00, 'GCash QR', NULL, '2025-11-08 21:09:08', NULL, NULL, 'gcash', 50, 3722.00, '', '2025-11-08 21:09:08'),
(23, 18, 0, 0.00, 'GCash QR', NULL, '2025-11-08 21:16:26', NULL, NULL, 'gcash', 50, 7418.00, '', '2025-11-08 21:16:26'),
(24, 19, 0, 0.00, 'GCash QR', NULL, '2025-11-08 21:28:56', NULL, NULL, 'gcash', 50, 3722.00, '', '2025-11-08 21:28:56'),
(25, 20, 0, 0.00, 'GCash QR', NULL, '2025-11-08 21:29:32', NULL, NULL, 'gcash', 50, 3722.00, '', '2025-11-08 21:29:32'),
(26, 21, 0, 0.00, 'GCash QR', NULL, '2025-11-08 21:42:03', NULL, NULL, 'gcash', 50, 3722.00, '', '2025-11-08 21:42:03'),
(27, 22, 0, 3722.00, 'GCash QR', NULL, '2025-11-08 22:05:38', 1, '2025-11-08 22:06:35', 'gcash', 50, 3722.00, 'VERIFIED', '2025-11-08 22:06:35'),
(28, 23, 0, 12040.00, 'GCash QR', NULL, '2025-11-08 22:20:45', 1, '2025-11-08 22:21:53', 'gcash', 50, 12040.00, 'VERIFIED', '2025-11-08 22:21:53'),
(29, 24, 0, 0.00, 'GCash QR', NULL, '2025-11-08 23:50:34', NULL, NULL, 'gcash', 50, 6298.00, '', '2025-11-08 23:50:34'),
(30, 25, 0, 7418.00, 'GCash QR', NULL, '2025-11-09 02:15:28', 1, '2025-11-09 02:18:18', 'gcash', 50, 7418.00, 'VERIFIED', '2025-11-09 02:18:18');

-- --------------------------------------------------------

--
-- Table structure for table `payment_installments`
--

CREATE TABLE `payment_installments` (
  `id` int(11) UNSIGNED NOT NULL,
  `order_id` int(11) NOT NULL COMMENT 'FK to orders table',
  `installment_number` int(11) NOT NULL COMMENT 'Sequence: 1=deposit, 2=2nd payment, etc.',
  `amount_due` decimal(12,2) NOT NULL COMMENT 'Amount to pay for this installment',
  `amount_paid` decimal(12,2) DEFAULT '0.00' COMMENT 'Actual amount paid',
  `due_date` date DEFAULT NULL COMMENT 'Optional payment deadline',
  `status` enum('PENDING','PAID','OVERDUE') DEFAULT 'PENDING',
  `payment_method` enum('gcash','bpi','cash') DEFAULT NULL,
  `reference_number` varchar(120) DEFAULT NULL COMMENT 'GCash/BPI reference',
  `screenshot_path` varchar(255) DEFAULT NULL COMMENT 'Payment proof path',
  `verified_by` int(11) DEFAULT NULL COMMENT 'Admin who verified',
  `verified_at` datetime DEFAULT NULL,
  `notes` text COMMENT 'Admin remarks',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `payment_installments`
--

INSERT INTO `payment_installments` (`id`, `order_id`, `installment_number`, `amount_due`, `amount_paid`, `due_date`, `status`, `payment_method`, `reference_number`, `screenshot_path`, `verified_by`, `verified_at`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 12850.00, 0.00, NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-06 02:52:54', '2025-11-06 02:52:54'),
(2, 1, 2, 12850.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-06 02:52:54', '2025-11-06 02:52:54'),
(3, 2, 1, 12850.00, 0.00, NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-06 06:42:21', '2025-11-06 06:42:21'),
(4, 2, 2, 12850.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-06 06:42:21', '2025-11-06 06:42:21'),
(5, 3, 1, 12850.00, 0.00, NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-06 06:43:38', '2025-11-06 06:43:38'),
(6, 3, 2, 12850.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-06 06:43:38', '2025-11-06 06:43:38'),
(7, 4, 1, 10080.00, 0.00, NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-06 06:50:02', '2025-11-06 06:50:02'),
(8, 4, 2, 10080.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-06 06:50:02', '2025-11-06 06:50:02'),
(9, 5, 1, 10080.00, 0.00, NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-06 06:52:15', '2025-11-06 06:52:15'),
(10, 5, 2, 10080.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-06 06:52:15', '2025-11-06 06:52:15'),
(11, 6, 1, 20160.00, 0.00, NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-06 11:24:27', '2025-11-06 11:24:27'),
(12, 7, 1, 25200.00, 25200.00, NULL, 'PAID', NULL, NULL, NULL, NULL, '2025-11-07 06:46:06', NULL, '2025-11-06 11:28:39', '2025-11-07 05:46:06'),
(13, 8, 1, 7560.00, 7560.00, NULL, 'PAID', NULL, NULL, NULL, NULL, '2025-11-06 12:49:56', NULL, '2025-11-06 11:47:10', '2025-11-06 11:49:56'),
(14, 8, 2, 17640.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-06 11:47:10', '2025-11-06 11:47:10'),
(15, 9, 1, 12850.00, 0.00, NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-06 17:09:18', '2025-11-06 17:09:18'),
(16, 9, 2, 12850.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-06 17:09:18', '2025-11-06 17:09:18'),
(17, 10, 1, 12040.00, 0.00, NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-07 05:47:06', '2025-11-07 05:47:06'),
(18, 10, 2, 12040.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-07 05:47:06', '2025-11-07 05:47:06'),
(19, 11, 1, 7168.00, 7168.00, NULL, 'PAID', NULL, NULL, NULL, NULL, '2025-11-07 13:45:30', NULL, '2025-11-07 12:44:36', '2025-11-07 12:45:30'),
(20, 11, 2, 7168.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-07 12:44:36', '2025-11-07 12:44:36'),
(21, 12, 1, 7418.00, 0.00, NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-07 18:12:29', '2025-11-07 18:12:29'),
(22, 12, 2, 7418.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-07 18:12:29', '2025-11-07 18:12:29'),
(23, 13, 1, 7418.00, 0.00, NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 17:37:31', '2025-11-08 17:37:31'),
(24, 13, 2, 7418.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 17:37:31', '2025-11-08 17:37:31'),
(25, 14, 1, 3722.00, 0.00, NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 20:52:51', '2025-11-08 20:52:51'),
(26, 14, 2, 3722.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 20:52:51', '2025-11-08 20:52:51'),
(27, 15, 1, 3472.00, 0.00, NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 20:54:08', '2025-11-08 20:54:08'),
(28, 15, 2, 3472.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 20:54:08', '2025-11-08 20:54:08'),
(29, 16, 1, 3722.00, 0.00, NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 20:56:49', '2025-11-08 20:56:49'),
(30, 16, 2, 3722.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 20:56:49', '2025-11-08 20:56:49'),
(31, 17, 1, 3722.00, 0.00, NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 21:09:08', '2025-11-08 21:09:08'),
(32, 17, 2, 3722.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 21:09:08', '2025-11-08 21:09:08'),
(33, 18, 1, 7418.00, 0.00, NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 21:16:26', '2025-11-08 21:16:26'),
(34, 18, 2, 7418.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 21:16:26', '2025-11-08 21:16:26'),
(35, 19, 1, 3722.00, 0.00, NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 21:28:56', '2025-11-08 21:28:56'),
(36, 19, 2, 3722.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 21:28:56', '2025-11-08 21:28:56'),
(37, 20, 1, 3722.00, 0.00, NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 21:29:32', '2025-11-08 21:29:32'),
(38, 20, 2, 3722.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 21:29:32', '2025-11-08 21:29:32'),
(39, 21, 1, 3722.00, 0.00, NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 21:42:03', '2025-11-08 21:42:03'),
(40, 21, 2, 3722.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 21:42:03', '2025-11-08 21:42:03'),
(41, 22, 1, 3722.00, 3722.00, NULL, 'PAID', NULL, NULL, NULL, NULL, '2025-11-08 23:06:35', NULL, '2025-11-08 22:05:38', '2025-11-08 22:06:35'),
(42, 22, 2, 3722.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 22:05:38', '2025-11-08 22:05:38'),
(43, 23, 1, 12040.00, 12040.00, NULL, 'PAID', NULL, NULL, NULL, NULL, '2025-11-08 23:21:53', NULL, '2025-11-08 22:20:45', '2025-11-08 22:21:53'),
(44, 23, 2, 12040.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 22:20:45', '2025-11-08 22:20:45'),
(45, 24, 1, 6298.00, 0.00, NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 23:50:34', '2025-11-08 23:50:34'),
(46, 24, 2, 6298.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 23:50:34', '2025-11-08 23:50:34'),
(47, 25, 1, 7418.00, 7418.00, NULL, 'PAID', NULL, NULL, NULL, NULL, '2025-11-09 03:18:18', NULL, '2025-11-09 02:15:28', '2025-11-09 02:18:18'),
(48, 25, 2, 7418.00, 0.00, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-09 02:15:28', '2025-11-09 02:15:28');

-- --------------------------------------------------------

--
-- Table structure for table `payment_qr`
--

CREATE TABLE `payment_qr` (
  `id` int(11) UNSIGNED NOT NULL,
  `method` enum('gcash','bpi') NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `payment_qr`
--

INSERT INTO `payment_qr` (`id`, `method`, `image_path`, `is_active`) VALUES
(1, 'gcash', 'uploads/qrs/gcash_1761692286.jpg', 1),
(2, 'bpi', 'uploads/qrs/bpi_1761692321.jpg', 1);

-- --------------------------------------------------------

--
-- Table structure for table `payment_verifications`
--

CREATE TABLE `payment_verifications` (
  `id` int(11) UNSIGNED NOT NULL,
  `order_id` int(11) NOT NULL,
  `method` enum('gcash','bpi') NOT NULL,
  `account_name` varchar(150) NOT NULL,
  `account_number` varchar(100) DEFAULT NULL,
  `reference_number` varchar(120) NOT NULL,
  `amount_reported` decimal(12,2) NOT NULL,
  `screenshot_path` varchar(255) DEFAULT NULL,
  `status` enum('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  `approved_by` int(11) DEFAULT NULL COMMENT 'Admin who approved the payment',
  `approved_at` datetime DEFAULT NULL COMMENT 'Timestamp when payment was approved',
  `rejected_by` int(11) DEFAULT NULL COMMENT 'Admin who rejected the payment',
  `rejected_at` datetime DEFAULT NULL COMMENT 'Timestamp when payment was rejected',
  `rejection_reason` text COMMENT 'Reason for rejecting payment',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reject_reason` text,
  `rejected_reason` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `payment_verifications`
--

INSERT INTO `payment_verifications` (`id`, `order_id`, `method`, `account_name`, `account_number`, `reference_number`, `amount_reported`, `screenshot_path`, `status`, `approved_by`, `approved_at`, `rejected_by`, `rejected_at`, `rejection_reason`, `created_at`, `reject_reason`, `rejected_reason`) VALUES
(2, 5, 'gcash', 'teest', '21212', '212121', 10080.00, 'uploads/payments/proof_5_1762411962.jpg', 'PENDING', NULL, NULL, NULL, NULL, NULL, '2025-11-06 06:52:42', NULL, NULL),
(3, 7, 'gcash', 'Bingot CC', '12312312321', '312312312312', 25200.00, 'uploads/payments/proof_7_1762428825.png', 'APPROVED', 1, '2025-11-07 06:46:06', NULL, NULL, NULL, '2025-11-06 11:33:45', NULL, NULL),
(4, 8, 'bpi', 'hsidfhsiadh', '823989128391283', '3239289829830', 80000.00, NULL, 'REJECTED', NULL, NULL, NULL, '2025-11-06 12:49:41', NULL, '2025-11-06 11:48:11', 'ang laki po ng binayad niyo', NULL),
(5, 8, 'bpi', 'hsidfhsiadh', '823989128391283', '3239289829830', 80000.00, NULL, 'APPROVED', 1, '2025-11-06 12:49:56', NULL, NULL, NULL, '2025-11-06 11:48:11', NULL, NULL),
(6, 9, 'gcash', 'fawfwa', '123124124124', '1312312312312', 132321312.00, 'uploads/payments/proof_9_1762448983.png', 'REJECTED', NULL, NULL, NULL, '2025-11-08 22:18:29', NULL, '2025-11-06 17:09:43', 'sobrang daming bayad ayaw ko nyan', NULL),
(7, 10, 'gcash', 'teest', '21212', '212121', 12040.00, 'uploads/payments/proof_10_1762494440.png', 'PENDING', NULL, NULL, NULL, NULL, NULL, '2025-11-07 05:47:20', NULL, NULL),
(8, 11, 'gcash', 'teest', '21212', '212121', 7168.00, 'uploads/payments/proof_11_1762519495.jpg', 'APPROVED', 1, '2025-11-07 13:45:30', NULL, NULL, NULL, '2025-11-07 12:44:55', NULL, NULL),
(9, 22, 'gcash', 'Vien', '09762284470', '1234567890', 3722.00, 'uploads/payments/proof_22_1762639557.jpg', 'APPROVED', 1, '2025-11-08 23:06:35', NULL, NULL, NULL, '2025-11-08 22:05:57', NULL, NULL),
(10, 23, 'gcash', 'Vien', '09696969696', '1234567890', 12040.00, 'uploads/payments/proof_23_1762640461.jpg', 'APPROVED', 1, '2025-11-08 23:21:53', NULL, NULL, NULL, '2025-11-08 22:21:01', NULL, NULL),
(11, 24, 'gcash', 'Vien', '09762284470', '1234567890', 6298.00, 'uploads/payments/proof_24_1762645852.png', 'PENDING', NULL, NULL, NULL, NULL, NULL, '2025-11-08 23:50:52', NULL, NULL),
(12, 25, 'gcash', 'Ayi', '09762284470', '1234567890', 7418.00, 'uploads/payments/proof_25_1762654556.jpeg', 'APPROVED', 1, '2025-11-09 03:18:18', NULL, NULL, NULL, '2025-11-09 02:15:56', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payment_verifications_backup_20251026`
--

CREATE TABLE `payment_verifications_backup_20251026` (
  `id` int(11) NOT NULL DEFAULT '0',
  `order_id` int(11) NOT NULL,
  `method` enum('gcash','bpi') NOT NULL,
  `account_name` varchar(150) NOT NULL,
  `account_number` varchar(100) DEFAULT NULL,
  `reference_number` varchar(120) NOT NULL,
  `amount_reported` decimal(12,2) NOT NULL,
  `screenshot_path` varchar(255) DEFAULT NULL,
  `status` enum('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `payment_verifications_backup_20251026`
--

INSERT INTO `payment_verifications_backup_20251026` (`id`, `order_id`, `method`, `account_name`, `account_number`, `reference_number`, `amount_reported`, `screenshot_path`, `status`, `created_at`) VALUES
(1, 1, 'gcash', 'dascasdasd', '1231231231', 'd12231123', 123.00, 'uploads/payments/proof_1_1760842501.png', 'APPROVED', '2025-10-19 02:55:01'),
(2, 2, 'bpi', 'dascasdasd', '1231231231', 'd12231123', 123.00, 'uploads/payments/proof_2_1760842557.png', 'REJECTED', '2025-10-19 02:55:57'),
(3, 23, 'gcash', 'test1', 'test2', 'test123', 112.00, 'uploads/payments/proof_23_1760889566.jpg', 'APPROVED', '2025-10-19 15:59:26'),
(4, 24, 'gcash', 'teest', 'tset', 'testt', 612.00, 'uploads/payments/proof_24_1760891708.jpg', 'APPROVED', '2025-10-19 16:35:08'),
(5, 25, 'gcash', 'tset', 'tetset', 'teststs', 56.00, 'uploads/payments/proof_25_1760896011.png', 'APPROVED', '2025-10-19 17:46:51'),
(6, 28, 'gcash', 'sadas', 'dasdas', 'dasdas', 123.00, 'uploads/payments/proof_28_1761010719.png', 'PENDING', '2025-10-21 01:38:39'),
(7, 29, 'bpi', 'ds', 'das', 'da', 12313.00, 'uploads/payments/proof_29_1761015499.png', 'REJECTED', '2025-10-21 02:58:19'),
(8, 30, 'bpi', 'awit', '6969', '6969', 123.00, 'uploads/payments/proof_30_1761015751.jpeg', 'REJECTED', '2025-10-21 03:02:31'),
(9, 31, 'gcash', 'dascasdasd', '1231231231', 'd12231123', 123.00, 'uploads/payments/proof_31_1761015774.png', 'APPROVED', '2025-10-21 03:02:54'),
(10, 32, 'bpi', 'dascasdasd', '1231231231', '123', 123.00, 'uploads/payments/proof_32_1761015835.png', 'APPROVED', '2025-10-21 03:03:55'),
(11, 34, 'bpi', 'dsad', 'asdas', 'ad123123', 123.00, 'uploads/payments/proof_34_1761016158.png', 'APPROVED', '2025-10-21 03:09:18');

-- --------------------------------------------------------

--
-- Table structure for table `php_mailer_info`
--

CREATE TABLE `php_mailer_info` (
  `id` int(11) UNSIGNED NOT NULL,
  `host` varchar(255) DEFAULT NULL,
  `port` varchar(255) DEFAULT NULL,
  `secure` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `from_email` varchar(255) DEFAULT NULL,
  `from_name` varchar(255) DEFAULT NULL,
  `reply_to` varchar(255) DEFAULT NULL,
  `effective_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) DEFAULT '0',
  `image` varchar(255) DEFAULT NULL COMMENT 'Primary product image (kept for backward compatibility)',
  `model_3d` varchar(255) DEFAULT NULL,
  `measurement_unit` enum('cm','mm','inch','meter') DEFAULT 'cm',
  `is_customizable` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `status` enum('draft','released') NOT NULL DEFAULT 'draft',
  `released_at` datetime DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT '0',
  `archived_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `type`, `description`, `price`, `stock`, `image`, `model_3d`, `measurement_unit`, `is_customizable`, `created_at`, `created_by`, `status`, `released_at`, `is_archived`, `archived_at`) VALUES
(1, 'Modern Glossy', 'Kitchen Cabinet', 'A sleek white glossy kitchen cabinet with aluminum handles and soft-close drawers. Perfect for modern kitchens.\n\nSize: 120cm (W) × 90cm (H) × 45cm (D)', 18000.00, 0, 'uploads/products/ModernGlossyKitchenCabinet_1_1762381440_33d3cc0a319c.png', NULL, 'cm', 0, '2025-11-05 19:41:05', 1, 'released', '2025-11-05 20:43:27', 0, NULL),
(2, 'Rustic Oak', 'Kitchen Cabinet', 'Rustic oak finish kitchen cabinet with matte black handles and countertop, great for farmhouse-style interiors.\n\nSize: 150cm (W) × 95cm (H) × 50cm (D)', 22500.00, 0, 'uploads/products/RusticOakKitchenCabinet_1_1762384372_a729ab92f9f6.jpg', NULL, 'cm', 0, '2025-11-05 19:42:51', 1, 'released', '2025-11-06 03:48:55', 0, NULL),
(4, 'Modern Grey Cabinet', 'Wardrobe', 'Modern matte grey wardrobe with open shelves, drawers, and hanging space.\nSize: 240cm (W) × 220cm (H) × 60cm (D)', 27500.00, 0, 'uploads/products/GrayDoorWardrobe_1_1762484594_57dd5a15c6e5.jpg', NULL, 'cm', 1, '2025-11-07 03:03:19', 1, 'released', '2025-11-07 04:04:07', 0, NULL),
(5, 'Classic Walnut Cabinet', 'Wardrobe', 'Elegant brown wardrobe with upper storage, hanging rod, and side shelves.\nSize:180cm (W) × 210cm (H) × 55cm (D)', 24800.00, 0, 'uploads/products/ClassicFour-DoorWardrobe_1_1762484690_4552e5f19913.jpg', NULL, 'cm', 0, '2025-11-07 03:04:52', 1, 'released', '2025-11-07 04:05:10', 0, NULL),
(6, 'Industrial Cabinet', 'Storage Cabinet', 'Heavy-duty steel cabinet with adjustable shelves and tool compartments.\nSize: 120cm (W) × 180cm (H) × 45cm (D)', 14500.00, 0, 'uploads/products/IndustrialCabinetStorage_1_1762484816_6a8f9af8ded2.jpg', NULL, 'cm', 0, '2025-11-07 03:06:58', 1, 'released', '2025-11-07 04:15:47', 0, NULL),
(7, 'Compact Wooden Cabinet', 'Storage Cabinet', 'Small beech wood cabinet with dual doors and adjustable shelves for multipurpose storage.\nSize:90cm (W) × 80cm (H) × 40cm (D)', 10800.00, 0, 'uploads/products/IndustrialSmallStorage_1_1762484901_9d36d053bbb4.jpg', NULL, 'cm', 0, '2025-11-07 03:08:25', 1, 'released', '2025-11-07 04:15:49', 0, NULL),
(8, 'Files Cabinet', 'Office Cabinet', 'Durable 3-drawer steel filing cabinet with lock for organized office storage.\nSize:90cm (W) × 110cm (H) × 45cm (D)', 8900.00, 0, 'uploads/products/OfficeFIlingCabine_1_1762485159_18a17a323d0c.jpg', NULL, 'cm', 1, '2025-11-07 03:12:42', 1, 'released', '2025-11-07 04:15:52', 0, NULL),
(9, 'Executive Wooden File Cabinet', 'Office Cabinet', 'Large walnut cabinet with glass doors and multiple shelves for files and documents.\nSize:240cm (W) × 200cm (H) × 45cm (D)', 21500.00, 0, 'uploads/products/BigStorageOfficeCabinet_1_1762485201_6c933b4e5aff.jpg', NULL, 'cm', 0, '2025-11-07 03:13:24', 1, 'released', '2025-11-07 04:15:54', 0, NULL),
(10, 'Floating Vanity Cabinet', 'Bathroom Cabinet', 'Wall-mounted vanity with white sink, soft-close drawer, and open shelving.\nSize:100cm (W) × 85cm (H) × 45cm (D)', 12800.00, 0, 'uploads/products/BathroomVanityCabinet_1_1762485295_efba1aed0733.jpg', NULL, 'cm', 0, '2025-11-07 03:14:58', 1, 'released', '2025-11-07 04:15:57', 0, NULL),
(11, 'Compact Wall Cabinet', 'Bathroom Cabinet', 'Sleek wall-mounted cabinet with glass shelves and mirrored doors for toiletries.\nSize:80cm (W) × 70cm (H) × 20cm (D)', 6200.00, 0, 'uploads/products/SmallBathroomCabinet_1_1762485340_6d93c8df9535.jpg', NULL, 'cm', 0, '2025-11-07 03:15:43', 1, 'released', '2025-11-07 04:15:59', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_colors`
--

CREATE TABLE `product_colors` (
  `id` int(11) UNSIGNED NOT NULL,
  `product_id` int(11) NOT NULL,
  `color_id` int(11) NOT NULL,
  `custom_price` decimal(10,2) DEFAULT NULL,
  `display_order` int(11) DEFAULT '0',
  `is_default` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `product_colors`
--

INSERT INTO `product_colors` (`id`, `product_id`, `color_id`, `custom_price`, `display_order`, `is_default`, `created_at`) VALUES
(4, 4, 2, NULL, 0, 0, '2025-11-08 22:33:07');

-- --------------------------------------------------------

--
-- Table structure for table `product_handles`
--

CREATE TABLE `product_handles` (
  `id` int(11) UNSIGNED NOT NULL,
  `product_id` int(11) NOT NULL,
  `handle_id` int(11) NOT NULL,
  `custom_price` decimal(10,2) DEFAULT NULL,
  `display_order` int(11) DEFAULT '0',
  `is_default` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `image_id` int(11) UNSIGNED NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT '0',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`image_id`, `product_id`, `image_path`, `display_order`, `is_primary`, `created_at`) VALUES
(4, 1, 'uploads/products/ModernGlossyKitchenCabinet_1_1762381440_33d3cc0a319c.png', 0, 1, '2025-11-05 22:24:01'),
(5, 1, 'uploads/products/ModernGlossyKitchenCabinet_2_1762381440_fbff101f140f.png', 1, 0, '2025-11-05 22:24:02'),
(6, 1, 'uploads/products/ModernGlossyKitchenCabinet_3_1762381440_a6329cd53f8c.png', 2, 0, '2025-11-05 22:24:02'),
(7, 2, 'uploads/products/RusticOakKitchenCabinet_1_1762384372_a729ab92f9f6.jpg', 0, 1, '2025-11-05 23:12:56'),
(8, 2, 'uploads/products/RusticOakKitchenCabinet_2_1762384372_b002f8bb5d06.jpg', 1, 0, '2025-11-05 23:12:56'),
(9, 2, 'uploads/products/RusticOakKitchenCabinet_3_1762384373_49ec544ab2ba.jpg', 2, 0, '2025-11-05 23:12:56'),
(10, 3, 'uploads/products/cab4_1762430738_1bac925875bc.jpg', 0, 1, '2025-11-06 12:06:55'),
(11, 4, 'uploads/products/GrayDoorWardrobe_1_1762484594_57dd5a15c6e5.jpg', 0, 1, '2025-11-07 03:03:19'),
(12, 4, 'uploads/products/GrayDoorWardrobe_2_1762484594_3e12f1a964e6.jpg', 1, 0, '2025-11-07 03:03:20'),
(13, 4, 'uploads/products/GrayDoorWardrobe_3_1762484594_5974d6b89075.jpg', 2, 0, '2025-11-07 03:03:20'),
(14, 5, 'uploads/products/ClassicFour-DoorWardrobe_1_1762484690_4552e5f19913.jpg', 0, 1, '2025-11-07 03:04:53'),
(15, 5, 'uploads/products/ClassicFour-DoorWardrobe_3_1762484690_30ac00136b17.jpg', 1, 0, '2025-11-07 03:04:53'),
(16, 5, 'uploads/products/ClassicFour-DoorWardrobe-2_1762484690_d58eb8c8e341.jpg', 2, 0, '2025-11-07 03:04:53'),
(17, 6, 'uploads/products/IndustrialCabinetStorage_1_1762484816_6a8f9af8ded2.jpg', 0, 1, '2025-11-07 03:06:59'),
(18, 6, 'uploads/products/IndustrialCabinetStorage_2_1762484816_cd987f4dde24.jpg', 1, 0, '2025-11-07 03:06:59'),
(19, 7, 'uploads/products/IndustrialSmallStorage_1_1762484901_9d36d053bbb4.jpg', 0, 1, '2025-11-07 03:08:25'),
(20, 7, 'uploads/products/IndustrialSmallStorage_2_1762484902_c71cd6c3e1c7.jpg', 1, 0, '2025-11-07 03:08:25'),
(21, 7, 'uploads/products/IndustrialSmallStorage_3_1762484902_7d32ecf1778b.jpg', 2, 0, '2025-11-07 03:08:25'),
(22, 8, 'uploads/products/OfficeFIlingCabine_1_1762485159_18a17a323d0c.jpg', 0, 1, '2025-11-07 03:12:42'),
(23, 8, 'uploads/products/OfficeFilingCabinet_2_1762485159_747a9e248af1.jpg', 1, 0, '2025-11-07 03:12:43'),
(24, 8, 'uploads/products/OfficeFilingCabinet_3_1762485159_c9ba9afdc6be.jpg', 2, 0, '2025-11-07 03:12:43'),
(25, 9, 'uploads/products/BigStorageOfficeCabinet_1_1762485201_6c933b4e5aff.jpg', 0, 1, '2025-11-07 03:13:24'),
(26, 9, 'uploads/products/BigStorageOfficeCabinet_2_1762485201_ac6806f1dccc.jpg', 1, 0, '2025-11-07 03:13:24'),
(27, 9, 'uploads/products/BigStorageOfficeCabinet_3_1762485201_13ff10f41212.jpg', 2, 0, '2025-11-07 03:13:24'),
(28, 10, 'uploads/products/BathroomVanityCabinet_1_1762485295_efba1aed0733.jpg', 0, 1, '2025-11-07 03:14:58'),
(29, 10, 'uploads/products/BathroomVanityCabinet_2_1762485295_5127bd7244eb.jpg', 1, 0, '2025-11-07 03:14:59'),
(30, 10, 'uploads/products/BathroonVanityCabinet_3_1762485295_d16b2bf361ab.jpg', 2, 0, '2025-11-07 03:14:59'),
(31, 11, 'uploads/products/SmallBathroomCabinet_1_1762485340_6d93c8df9535.jpg', 0, 1, '2025-11-07 03:15:43'),
(32, 11, 'uploads/products/SmallBathroomCabinet_2_1762485341_c70f4406604c.jpg', 1, 0, '2025-11-07 03:15:43'),
(33, 11, 'uploads/products/SmallBathroomCabinet_3_1762485341_a7e88aaf1121.jpg', 2, 0, '2025-11-07 03:15:44');

-- --------------------------------------------------------

--
-- Table structure for table `product_material_map`
--

CREATE TABLE `product_material_map` (
  `id` int(11) UNSIGNED NOT NULL,
  `product_id` int(11) NOT NULL,
  `map_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `product_size_config`
--

CREATE TABLE `product_size_config` (
  `id` int(11) UNSIGNED NOT NULL,
  `product_id` int(11) NOT NULL,
  `dimension_type` enum('width','height','depth') NOT NULL,
  `min_value` decimal(10,2) NOT NULL DEFAULT '0.00',
  `max_value` decimal(10,2) NOT NULL DEFAULT '300.00',
  `default_value` decimal(10,2) NOT NULL DEFAULT '100.00',
  `step_value` decimal(10,2) NOT NULL DEFAULT '1.00',
  `price_per_unit` decimal(10,2) DEFAULT '0.00',
  `measurement_unit` varchar(20) DEFAULT 'cm',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `price_block_cm` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_per_block` decimal(10,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `product_size_config`
--

INSERT INTO `product_size_config` (`id`, `product_id`, `dimension_type`, `min_value`, `max_value`, `default_value`, `step_value`, `price_per_unit`, `measurement_unit`, `created_at`, `price_block_cm`, `price_per_block`) VALUES
(10, 4, 'width', 200.00, 300.00, 200.00, 1.00, 100.00, 'cm', '2025-11-08 22:33:07', 0.00, 0.00),
(11, 4, 'height', 200.00, 300.00, 200.00, 1.00, 0.00, 'cm', '2025-11-08 22:33:07', 10.00, 250.00),
(12, 4, 'depth', 50.00, 180.00, 50.00, 1.00, 300.00, 'cm', '2025-11-08 22:33:07', 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `product_size_config_backup_20251026`
--

CREATE TABLE `product_size_config_backup_20251026` (
  `id` int(11) NOT NULL DEFAULT '0',
  `product_id` int(11) NOT NULL,
  `dimension_type` enum('width','height','depth') NOT NULL,
  `min_value` decimal(10,2) NOT NULL DEFAULT '0.00',
  `max_value` decimal(10,2) NOT NULL DEFAULT '300.00',
  `default_value` decimal(10,2) NOT NULL DEFAULT '100.00',
  `price_per_unit` decimal(10,2) DEFAULT '0.00',
  `measurement_unit` varchar(20) DEFAULT 'cm',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `price_block_cm` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_per_block` decimal(10,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `product_size_config_backup_20251026`
--

INSERT INTO `product_size_config_backup_20251026` (`id`, `product_id`, `dimension_type`, `min_value`, `max_value`, `default_value`, `price_per_unit`, `measurement_unit`, `created_at`, `price_block_cm`, `price_per_block`) VALUES
(1, 12, 'width', 0.00, 300.00, 100.00, 0.00, 'cm', '2025-10-15 11:27:37', 0.00, 0.00),
(2, 12, 'height', 0.00, 300.00, 100.00, 0.00, 'cm', '2025-10-15 11:27:37', 0.00, 0.00),
(3, 12, 'depth', 0.00, 300.00, 100.00, 0.00, 'cm', '2025-10-15 11:27:37', 0.00, 0.00),
(4, 13, 'width', 0.00, 300.00, 100.00, 0.00, 'cm', '2025-10-15 12:28:09', 0.00, 0.00),
(5, 13, 'height', 0.00, 300.00, 100.00, 0.00, 'cm', '2025-10-15 12:28:10', 0.00, 0.00),
(6, 13, 'depth', 0.00, 300.00, 100.00, 0.00, 'cm', '2025-10-15 12:28:10', 0.00, 0.00),
(76, 14, 'width', 50.00, 200.00, 100.00, 100.00, 'cm', '2025-10-24 19:25:57', 0.00, 0.00),
(77, 14, 'height', 80.00, 280.00, 100.00, 200.00, 'cm', '2025-10-24 19:25:58', 0.00, 0.00),
(78, 14, 'depth', 50.00, 250.00, 100.00, 550.00, 'cm', '2025-10-24 19:25:58', 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `product_textures`
--

CREATE TABLE `product_textures` (
  `id` int(11) UNSIGNED NOT NULL,
  `product_id` int(11) NOT NULL,
  `texture_id` int(11) NOT NULL,
  `custom_price` decimal(10,2) DEFAULT NULL,
  `display_order` int(11) DEFAULT '0',
  `is_default` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `product_texture_parts`
--

CREATE TABLE `product_texture_parts` (
  `id` int(11) UNSIGNED NOT NULL,
  `product_id` int(11) NOT NULL,
  `texture_id` int(11) NOT NULL,
  `part_key` varchar(32) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `rt_chat_messages`
--

CREATE TABLE `rt_chat_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `thread_id` int(11) NOT NULL,
  `sender_type` enum('customer','admin','bot') COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rt_chat_messages`
--

INSERT INTO `rt_chat_messages` (`id`, `thread_id`, `sender_type`, `sender_id`, `body`, `created_at`) VALUES
(1, 0, 'customer', 35, 'hi', '2025-11-04 13:10:43'),
(2, 3, 'customer', 17, 'hi n1gg3r', '2025-11-04 13:10:49'),
(3, 3, 'admin', 1, 'oy bawal yan', '2025-11-04 13:10:57'),
(4, 0, 'customer', 35, 'hello', '2025-11-04 13:46:51'),
(5, 0, 'customer', 35, 'panget chat support', '2025-11-04 13:46:58'),
(6, 0, 'customer', 35, 'walang nasagot', '2025-11-04 13:47:07'),
(7, 3, 'customer', 17, 'my niggggass', '2025-11-04 14:19:38'),
(8, 3, 'customer', 17, 'niggagniawi ewjd jiojwad', '2025-11-04 14:19:41'),
(9, 3, 'customer', 17, 'ang puke ko na kulay rosas', '2025-11-04 14:19:48'),
(10, 3, 'customer', 17, 'bumubukadkad pag hinimas himas', '2025-11-04 14:19:57'),
(11, 3, 'admin', 1, 'risist', '2025-11-04 14:20:30'),
(12, 0, 'admin', 1, 'eto na sasagot na', '2025-11-04 14:20:56'),
(13, 3, 'admin', 1, 'bawal yan po', '2025-11-04 14:34:49'),
(14, 3, 'admin', 1, 'HAHAHAHAHHAHHAHAHAAHAHAH', '2025-11-04 17:31:45'),
(15, 0, 'customer', 35, 'hi', '2025-11-04 17:33:09'),
(16, 0, 'bot', NULL, 'HAHAHAHAHAHAHHAHA', '2025-11-04 17:33:09'),
(17, 0, 'customer', 35, 'can i buy?', '2025-11-04 17:33:22'),
(18, 0, 'bot', NULL, 'wala sarado', '2025-11-04 17:33:22'),
(19, 3, 'customer', 17, 'mga nigggg3r', '2025-11-06 11:22:58'),
(20, 3, 'admin', 1, 'bawal po yan!', '2025-11-06 11:32:56'),
(21, 18, 'customer', 39, 'how can i customize a cabinet', '2025-11-06 17:05:22'),
(22, 17, 'customer', 37, 'aw', '2025-11-07 04:26:11');

-- --------------------------------------------------------

--
-- Table structure for table `rt_chat_threads`
--

CREATE TABLE `rt_chat_threads` (
  `id` int(11) UNSIGNED NOT NULL,
  `thread_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_email` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_phone` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('open','closed') COLLATE utf8mb4_unicode_ci DEFAULT 'open',
  `last_message_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `admin_last_read` datetime DEFAULT NULL,
  `customer_cleared_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rt_chat_threads`
--

INSERT INTO `rt_chat_threads` (`id`, `thread_code`, `customer_id`, `customer_name`, `customer_email`, `customer_phone`, `status`, `last_message_at`, `created_at`, `admin_last_read`, `customer_cleared_at`) VALUES
(1, 'E12BBF23C765B137ECC6DFDDA9B6E419', 16, 'Moen Secapuri', NULL, NULL, 'open', '2025-10-27 11:16:36', '2025-10-08 16:33:13', '2025-11-07 05:24:49', '2025-10-13 01:56:35'),
(2, '57F84B08CC89FE1733CF3FA628487512', 19, 'vien santos', NULL, NULL, 'open', '2025-10-09 19:47:48', '2025-10-08 16:36:33', '2025-11-07 05:24:45', '2025-10-09 19:48:14'),
(3, '8A4C83FDA3C3E38DD3548FF63EC0EF06', 17, 'Moen Secapuri', NULL, NULL, 'open', '2025-11-06 12:32:56', '2025-10-08 17:08:12', '2025-11-07 05:27:36', '2025-10-09 19:16:21'),
(4, 'EFEE22638F01990688999B16CA09EEB8', 23, 'Vien Santos', NULL, NULL, 'open', '2025-10-10 23:27:07', '2025-10-10 14:07:16', '2025-11-07 05:24:46', NULL),
(5, '1D71C7F2C061F237DD7B2EDCF001569B', 24, 'Vien Santos', NULL, NULL, 'open', '2025-10-10 23:29:09', '2025-10-10 15:28:51', '2025-11-07 05:24:47', '2025-10-10 23:29:23'),
(6, '0FC92274A86149522300CAEC5BF3D4BF', 32, 'Vien Santos', NULL, NULL, 'open', '2025-10-18 21:08:53', '2025-10-18 13:08:53', '2025-11-07 05:24:48', NULL),
(7, '97369A6D963C38E5CAEBF7B90A1D7DC5', 1, 'System Owner44', NULL, NULL, 'open', '2025-10-23 19:24:22', '2025-10-23 11:24:22', '2025-11-07 05:24:48', NULL),
(8, '76FD445FEC6815081967C89B25A2B16D', 35, 'henric orapa', NULL, NULL, 'open', '2025-11-04 18:33:22', '2025-11-04 13:06:30', '2025-11-07 05:24:51', NULL),
(16, 'F8025E101E392BB1032AC4BF191D5115', 38, 'Berna Marasigan', NULL, NULL, 'open', '2025-11-06 12:45:25', '2025-11-06 11:45:25', '2025-11-07 05:27:35', NULL),
(17, '8EA6C1CE11F97117075842BAA18BEBEF', 37, 'Capstonk Capstoink', NULL, NULL, 'open', '2025-11-07 05:26:11', '2025-11-06 12:28:09', '2025-11-07 05:27:33', '2025-11-07 05:26:14'),
(18, '3E430199A7CCB3527F09C10299C8AC9D', 39, 'mark milca', NULL, NULL, 'open', '2025-11-06 18:05:22', '2025-11-06 17:05:14', '2025-11-07 05:27:35', NULL),
(19, '3E7C87AA80B36FF39B11B4A111953BA2', 42, 'shan cablao', NULL, NULL, 'open', '2025-11-09 01:16:58', '2025-11-09 00:16:58', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `rt_cms_pages`
--

CREATE TABLE `rt_cms_pages` (
  `id` int(11) UNSIGNED NOT NULL,
  `page_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `status` enum('draft','published') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `version` int(11) DEFAULT '1',
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rt_cms_pages`
--

INSERT INTO `rt_cms_pages` (`id`, `page_key`, `page_name`, `content_data`, `status`, `version`, `updated_by`, `created_at`, `updated_at`) VALUES
(203, 'terms', 'Terms & Conditions', '{\"content\":\"<h1>Terms &amp; Conditions</h1><p><em>Effective Date: January 2024</em></p><h2>1. Acceptance of Terms</h2><p>By accessing and using the RADS Tooling website and services, you accept and agree to be bound by these Terms &amp; Conditions. If you do not agree to these terms, please do not use our services.</p><p>These terms apply to all visitors, users, customers, and others who access or use our services, including but not limited to browsing our website, placing orders, or engaging with our custom tooling services.</p><h2>2. Accounts and Registration</h2><h3>Account Creation</h3><p>To access certain features of our services, you may be required to create an account. When creating an account, you must:</p><ul><li>Provide accurate, current, and complete information</li><li>Maintain and promptly update your account information</li><li>Maintain the security of your password and account</li><li>Notify us immediately of any unauthorized use of your account</li></ul><h3>Account Responsibilities</h3><p>You are responsible for all activities that occur under your account. We reserve the right to suspend or terminate accounts that violate these terms.</p><h2>3. Products and Services</h2><h3>Product Descriptions</h3><p>We make every effort to provide accurate product descriptions, specifications, and images. However:</p><ul><li>Product images are for illustrative purposes and may differ slightly from actual products</li><li>Colors may appear differently depending on your display settings</li><li>We reserve the right to correct any errors or inaccuracies in product information</li></ul><h3>Custom Orders</h3><p>For custom cabinet orders:</p><ul><li>A 30% down payment is required before production begins</li><li>Final measurements and specifications must be approved before manufacturing</li><li>Production timeline will be provided upon order confirmation</li><li>Custom orders cannot be cancelled once production has started</li></ul><h2>4. Pricing and Payment</h2><h3>Pricing</h3><ul><li>All prices are listed in Philippine Pesos (PHP) unless otherwise stated</li><li>Prices are subject to change without notice</li><li>We reserve the right to correct pricing errors</li><li>Shipping and handling fees are additional unless stated otherwise</li></ul><h3>Payment Terms</h3><ul><li>Payment is due at the time of order placement unless otherwise agreed</li><li>We accept major credit cards, debit cards, and bank transfers</li><li>For large orders, payment plans may be available upon approval</li></ul><h2>5. Cancellation Policy</h2><h3>Standard Products</h3><ul><li>Orders may be cancelled within 24 hours of placement for a full refund</li><li>After 24 hours, cancellation fees may apply</li><li>Orders that have been shipped cannot be cancelled</li></ul><h3>Custom Products</h3><ul><li>Custom orders can be cancelled before production begins with a 10% restocking fee</li><li>Once production has started, custom orders cannot be cancelled</li><li>Down payments for cancelled custom orders are non-refundable if production has begun</li></ul><h2>6. Shipping and Delivery</h2><ul><li>Delivery timeframes are estimates and not guaranteed</li><li>Shipping costs are calculated based on weight, size, and destination</li><li>Risk of loss passes to you upon delivery to the carrier</li><li>You must inspect deliveries upon receipt and report any damage within 48 hours</li></ul><h2>7. Returns and Refunds</h2><h3>Return Policy</h3><ul><li>Standard products may be returned within 14 days of delivery</li><li>Products must be unused, in original packaging, and in resalable condition</li><li>Custom-made products are non-returnable</li><li>Customers are responsible for return shipping costs unless the product is defective</li></ul><h3>Refund Processing</h3><ul><li>Refunds will be processed within 7-14 business days of receiving the returned item</li><li>Refunds will be issued to the original payment method</li><li>Shipping fees are non-refundable</li></ul><h2>8. Warranties and Disclaimers</h2><h3>Product Warranty</h3><p>We warrant that our products are free from defects in materials and workmanship for a period of 1 year from the date of delivery. This warranty does not cover:</p><ul><li>Normal wear and tear</li><li>Damage caused by misuse or improper installation</li><li>Modifications or repairs by unauthorized parties</li><li>Damage from accidents or natural disasters</li></ul><h3>Disclaimer</h3><p>EXCEPT AS EXPRESSLY STATED, OUR SERVICES AND PRODUCTS ARE PROVIDED \\\"AS IS\\\" WITHOUT WARRANTY OF ANY KIND.</p><h2>9. Limitation of Liability</h2><p>To the maximum extent permitted by law, RADS Tooling shall not be liable for:</p><ul><li>Indirect, incidental, or consequential damages</li><li>Loss of profits, revenue, or data</li><li>Business interruption</li></ul><p>Our total liability shall not exceed the amount paid by you for the product or service in question.</p><h2>10. Intellectual Property</h2><p>All content on our website, including text, graphics, logos, images, and software, is the property of RADS Tooling and protected by intellectual property laws. You may not:</p><ul><li>Reproduce, distribute, or modify our content without permission</li><li>Use our trademarks or logos without authorization</li><li>Reverse engineer or decompile our software</li></ul><h2>11. Privacy</h2><p>Your use of our services is also governed by our Privacy Policy. Please review our Privacy Policy to understand our data practices.</p><h2>12. Changes to Terms</h2><p>We reserve the right to modify these terms at any time. Changes will be effective immediately upon posting. Your continued use of our services constitutes acceptance of the modified terms.</p><h2>13. Governing Law</h2><p>These terms are governed by the laws of the Republic of the Philippines. Any disputes shall be subject to the exclusive jurisdiction of the courts of Dasmariñas, Cavite.</p><h2>14. Contact Information</h2><p>For questions about these Terms &amp; Conditions, please contact us:</p><ul><li><strong>Company:</strong>&nbsp;RADS TOOLING INC.</li><li><strong>Address:</strong>&nbsp;Green Breeze, Piela, Dasmariñas, Cavite</li><li><strong>Phone:</strong>&nbsp;+63 976 228 4270</li><li><strong>Email:</strong>&nbsp;radstooling@gmail.com\\n</li></ul><p><br></p>\"}', 'draft', 6, 'System Owner', '2025-10-15 03:54:45', '2025-10-14 17:54:45'),
(204, 'terms', 'Terms & Conditions', '{\"content\":\"<h1>Terms &amp; Conditions</h1><p><em>Effective Date: January 2024</em></p><h2>1. Acceptance of Terms</h2><p>By accessing and using the RADS Tooling website and services, you accept and agree to be bound by these Terms &amp; Conditions. If you do not agree to these terms, please do not use our services.</p><p>These terms apply to all visitors, users, customers, and others who access or use our services, including but not limited to browsing our website, placing orders, or engaging with our custom tooling services.</p><h2>2. Accounts and Registration</h2><h3>Account Creation</h3><p>To access certain features of our services, you may be required to create an account. When creating an account, you must:</p><ul><li>Provide accurate, current, and complete information</li><li>Maintain and promptly update your account information</li><li>Maintain the security of your password and account</li><li>Notify us immediately of any unauthorized use of your account</li></ul><h3>Account Responsibilities</h3><p>You are responsible for all activities that occur under your account. We reserve the right to suspend or terminate accounts that violate these terms.</p><h2>3. Products and Services</h2><h3>Product Descriptions</h3><p>We make every effort to provide accurate product descriptions, specifications, and images. However:</p><ul><li>Product images are for illustrative purposes and may differ slightly from actual products</li><li>Colors may appear differently depending on your display settings</li><li>We reserve the right to correct any errors or inaccuracies in product information</li></ul><h3>Custom Orders</h3><p>For custom cabinet orders:</p><ul><li>A 30% down payment is required before production begins</li><li>Final measurements and specifications must be approved before manufacturing</li><li>Production timeline will be provided upon order confirmation</li><li>Custom orders cannot be cancelled once production has started</li></ul><h2>4. Pricing and Payment</h2><h3>Pricing</h3><ul><li>All prices are listed in Philippine Pesos (PHP) unless otherwise stated</li><li>Prices are subject to change without notice</li><li>We reserve the right to correct pricing errors</li><li>Shipping and handling fees are additional unless stated otherwise</li></ul><h3>Payment Terms</h3><ul><li>Payment is due at the time of order placement unless otherwise agreed</li><li>We accept major credit cards, debit cards, and bank transfers</li><li>For large orders, payment plans may be available upon approval</li></ul><h2>5. Cancellation Policy</h2><h3>Standard Products</h3><ul><li>Orders may be cancelled within 24 hours of placement for a full refund</li><li>After 24 hours, cancellation fees may apply</li><li>Orders that have been shipped cannot be cancelled</li></ul><h3>Custom Products</h3><ul><li>Custom orders can be cancelled before production begins with a 10% restocking fee</li><li>Once production has started, custom orders cannot be cancelled</li><li>Down payments for cancelled custom orders are non-refundable if production has begun</li></ul><h2>6. Shipping and Delivery</h2><ul><li>Delivery timeframes are estimates and not guaranteed</li><li>Shipping costs are calculated based on weight, size, and destination</li><li>Risk of loss passes to you upon delivery to the carrier</li><li>You must inspect deliveries upon receipt and report any damage within 48 hours</li></ul><h2>7. Returns and Refunds</h2><h3>Return Policy</h3><ul><li>Standard products may be returned within 14 days of delivery</li><li>Products must be unused, in original packaging, and in resalable condition</li><li>Custom-made products are non-returnable</li><li>Customers are responsible for return shipping costs unless the product is defective</li></ul><h3>Refund Processing</h3><ul><li>Refunds will be processed within 7-14 business days of receiving the returned item</li><li>Refunds will be issued to the original payment method</li><li>Shipping fees are non-refundable</li></ul><h2>8. Warranties and Disclaimers</h2><h3>Product Warranty</h3><p>We warrant that our products are free from defects in materials and workmanship for a period of 1 year from the date of delivery. This warranty does not cover:</p><ul><li>Normal wear and tear</li><li>Damage caused by misuse or improper installation</li><li>Modifications or repairs by unauthorized parties</li><li>Damage from accidents or natural disasters</li></ul><h3>Disclaimer</h3><p>EXCEPT AS EXPRESSLY STATED, OUR SERVICES AND PRODUCTS ARE PROVIDED \\\"AS IS\\\" WITHOUT WARRANTY OF ANY KIND.</p><h2>9. Limitation of Liability</h2><p>To the maximum extent permitted by law, RADS Tooling shall not be liable for:</p><ul><li>Indirect, incidental, or consequential damages</li><li>Loss of profits, revenue, or data</li><li>Business interruption</li></ul><p>Our total liability shall not exceed the amount paid by you for the product or service in question.</p><h2>10. Intellectual Property</h2><p>All content on our website, including text, graphics, logos, images, and software, is the property of RADS Tooling and protected by intellectual property laws. You may not:</p><ul><li>Reproduce, distribute, or modify our content without permission</li><li>Use our trademarks or logos without authorization</li><li>Reverse engineer or decompile our software</li></ul><h2>11. Privacy</h2><p>Your use of our services is also governed by our Privacy Policy. Please review our Privacy Policy to understand our data practices.</p><h2>12. Changes to Terms</h2><p>We reserve the right to modify these terms at any time. Changes will be effective immediately upon posting. Your continued use of our services constitutes acceptance of the modified terms.</p><h2>13. Governing Law</h2><p>These terms are governed by the laws of the Republic of the Philippines. Any disputes shall be subject to the exclusive jurisdiction of the courts of Dasmariñas, Cavite.</p><h2>14. Contact Information</h2><p>For questions about these Terms &amp; Conditions, please contact us:</p><ul><li><strong>Company:</strong>&nbsp;RADS TOOLING INC.</li><li><strong>Address:</strong>&nbsp;Green Breeze, Piela, Dasmariñas, Cavite</li><li><strong>Phone:</strong>&nbsp;+63 976 228 4270</li><li><strong>Email:</strong>&nbsp;radstooling@gmail.com\\n</li></ul><p><br></p>\"}', 'published', 6, 'System Owner', '2025-10-15 03:54:46', '2025-10-14 17:54:46'),
(224, 'about', 'About Us', '{\"about_hero_image\":\"/assets/images/store.jpg\",\"about_headline\":\"About Rads Tooling\",\"about_subheadline\":\"<p>Your trusted partner in precision tooling and industrial solutions</p>\",\"about_mission\":\"<p>To provide high-quality custom cabinets and tooling solutions that exceed customer expectations through superior craftsmanship, innovative design, and exceptional service.</p>\",\"about_vision\":\"<p>To be the leading cabinet manufacturer in Cavite, recognized for quality, reliability, and customer satisfaction.</p>\",\"about_story\":\"<p>Established in 2007, RADS Tooling has been serving customers for over 17 years. We started as a small workshop and have grown into a trusted name in custom cabinet manufacturing. Our commitment to quality and customer satisfaction has made us the preferred choice for homeowners and businesses alike.</p><p>Every cabinet we create is handcrafted by skilled artisans using premium materials and modern techniques. We combine traditional craftsmanship with innovative design to deliver products that stand the test of time.</p>\",\"about_address\":\"Green Breeze, Piela, Dasmariñas, Cavite\",\"about_phone\":\"+63 976 228 4270\",\"about_email\":\"radstooling@gmail.com\",\"about_hours_weekday\":\"Mon-Sat: 8:00 AM - 5:00 PM\",\"about_hours_sunday\":\"Sunday: Closed\",\"about_hero_path\":\"/assets/images/store.jpg\"}', 'published', 9, 'System Owner', '2025-10-15 21:38:09', '2025-10-15 11:38:09'),
(235, 'privacy', 'Privacy Policy', '{\"content\":\"<h1>Privacy Policy</h1><p><em>Last updated: January 2024</em></p><p><br></p><h2>1. Introduction</h2><p>Welcome to RADS Tooling. We are committed to protecting your personal information and your right to privacy. This Privacy Policy explains what information we collect, how we use it, and what rights you have in relation to it.</p><h2>2. Information We Collect</h2><h3>Personal Information</h3><p>We collect personal information that you voluntarily provide to us when you:</p><ul><li>Register for an account on our website</li><li>Place an order for our products</li><li>Request custom tooling solutions</li><li>Subscribe to our newsletter</li><li>Contact us for support or inquiries</li></ul><p>This information may include:</p><ul><li>Name and contact information (email, phone number, address)</li><li>Account credentials (username and password)</li><li>Payment information (credit card details, billing address)</li><li>Order history and preferences</li><li>Custom design specifications and requirements</li></ul><h3>Automatically Collected Information</h3><p>When you visit our website, we automatically collect certain information about your device, including:</p><ul><li>IP address and location data</li><li>Browser type and version</li><li>Device type and operating system</li><li>Pages visited and time spent on our site</li><li>Referring website or source</li></ul><h2>3. How We Use Your Information</h2><p>We use the information we collect to:</p><ul><li>Process and fulfill your orders</li><li>Provide customer support and respond to inquiries</li><li>Send order confirmations and shipping updates</li><li>Create and manage your account</li><li>Customize your experience on our website</li><li>Send marketing communications (with your consent)</li><li>Improve our products and services</li><li>Prevent fraud and enhance security</li><li>Comply with legal obligations</li></ul><h2>4. Cookies and Tracking Technologies</h2><p>We use cookies and similar tracking technologies to:</p><ul><li>Keep you logged in to your account</li><li>Remember your preferences and settings</li><li>Analyze website traffic and usage patterns</li><li>Improve website functionality and user experience</li><li>Deliver targeted advertisements (if applicable)</li></ul><p>You can control cookies through your browser settings. However, disabling cookies may limit some features of our website.</p><h2>5. Data Sharing and Disclosure</h2><p>We do not sell, trade, or rent your personal information to third parties. We may share your information with:</p><ul><li><strong>Service Providers:</strong>&nbsp;Third-party vendors who help us operate our business (e.g., payment processors, shipping companies, email service providers)</li><li><strong>Business Partners:</strong>&nbsp;Trusted partners for custom tooling projects (with your consent)</li><li><strong>Legal Requirements:</strong>&nbsp;When required by law or to protect our rights and safety</li><li><strong>Business Transfers:</strong>&nbsp;In connection with a merger, acquisition, or sale of assets</li></ul><h2>6. Data Security</h2><p>We implement appropriate technical and organizational security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction. These measures include:</p><ul><li>Encryption of sensitive data in transit and at rest</li><li>Regular security assessments and updates</li><li>Limited access to personal information on a need-to-know basis</li><li>Employee training on data protection and privacy</li></ul><p>However, no method of transmission over the Internet or electronic storage is 100% secure, and we cannot guarantee absolute security.</p><h2>7. Your Rights and Choices</h2><p>You have the right to:</p><ul><li><strong>Access:</strong>&nbsp;Request a copy of your personal information</li><li><strong>Correction:</strong>&nbsp;Update or correct inaccurate information</li><li><strong>Deletion:</strong>&nbsp;Request deletion of your personal information</li><li><strong>Opt-out:</strong>&nbsp;Unsubscribe from marketing communications</li><li><strong>Data Portability:</strong>&nbsp;Request your data in a portable format</li><li><strong>Withdraw Consent:</strong>&nbsp;Withdraw consent for data processing where applicable</li></ul><p>To exercise these rights, please contact us using the information provided below.</p><h2>8. Children\'s Privacy</h2><p>Our services are not intended for individuals under the age of 18. We do not knowingly collect personal information from children. If you believe we have collected information from a child, please contact us immediately.</p><h2>9. International Data Transfers</h2><p>Your information may be transferred to and processed in countries other than your country of residence. These countries may have different data protection laws. We ensure appropriate safeguards are in place to protect your information in accordance with this Privacy Policy.</p><h2>10. Changes to This Policy</h2><p>We may update this Privacy Policy from time to time to reflect changes in our practices or legal requirements. We will notify you of any material changes by posting the new Privacy Policy on this page and updating the \\\"Last updated\\\" date.</p><h2>11. Contact Us</h2><p>If you have questions, concerns, or requests regarding this Privacy Policy or our data practices, please contact us at:</p><ul><li><strong>Email:</strong>&nbsp;RadsTooling@gmail.com</li><li><strong>Phone:</strong>&nbsp;+63 (976) 228-4270</li><li><strong>Address:</strong>&nbsp;Green Breeze, Piela, Dasmariñas, Cavite, Philippines</li></ul><h2>12. Consent</h2><p>By using our website and services, you consent to the collection and use of your information as described in this Privacy Policy.</p>\"}', 'published', 13, 'System Owner', '2025-10-20 08:32:03', '2025-10-19 22:32:03'),
(250, 'about', 'About Us', '{\"about_hero_image\":\"/assets/images/store.jpg\",\"about_headline\":\"About Rads Tooling\",\"about_subheadline\":\"<p>Your trusted partner in precision tooling and industrial solutions</p>\",\"about_mission\":\"<p>To provide high-quality custom cabinets and tooling solutions that exceed customer expectations through superior craftsmanship, innovative design, and exceptional service.</p>\",\"about_vision\":\"<p>To be the leading cabinet manufacturer in Cavite, recognized for quality, reliability, and customer satisfaction.</p>\",\"about_story\":\"<p>Established in 2007, RADS Tooling has been serving customers for over 17 years. We started as a small workshop and have grown into a trusted name in custom cabinet manufacturing. Our commitment to quality and customer satisfaction has made us the preferred choice for homeowners and businesses alike.</p><p>Every cabinet we create is handcrafted by skilled artisans using premium materials and modern techniques. We combine traditional craftsmanship with innovative design to deliver products that stand the test of time.</p>\",\"about_address\":\"Green Breeze, Piela, Dasmariñas, Cavite\",\"about_phone\":\"+63 976 228 4270\",\"about_email\":\"radstooling@gmail.com\",\"about_hours_weekday\":\"Mon-Sat: 8:00 AM - 5:00 PM\",\"about_hours_sunday\":\"Sunday: Closed\",\"about_hero_path\":\"/uploads/general/Game1_1762031745_86b132605e2f.jpg\"}', 'draft', 10, 'System Owner44', '2025-11-02 05:15:46', '2025-11-01 20:15:46'),
(265, 'home_public', 'Public Homepage', '{\"hero_headline\":\"<h1><span style=\\\"color: rgb(47, 91, 136);\\\">C</span>ustomize Your Dream Cabinets</h1>\",\"hero_subtitle\":\"<p>Design, visualize, and order premium custom cabinets online. Choose your style, materials, and finishes with our 360° preview tool.</p>\",\"hero_image\":\"/uploads/general/Cab_Hero_1762146321_6de686ffd819.glb\",\"features_title\":\"<h2>Why Choose <span style=\\\"color: #2F5B88;\\\">RADS TOOLING</span>?</h2>\",\"features_subtitle\":\"<p>Everything you need to create your perfect cabinet</p>\",\"carousel_images\":[{\"image\":\"/assets/images/cab4.jpg\",\"title\":\"Bathroom Vanity\",\"description\":\"Water-resistant premium materials\"},{\"image\":\"/assets/images/cab3.jpg\",\"title\":\"Living Room Display\",\"description\":\"Showcase your style with custom shelving\"},{\"image\":\"/assets/images/cab1.jpg\",\"title\":\"Modern Kitchen\",\"description\":\"Contemporary design with premium finishes\"},{\"image\":\"/assets/images/cab5.jpg\",\"title\":\"Office Storage\",\"description\":\"Professional workspace solutions\"},{\"image\":\"/assets/images/cab2.jpg\",\"title\":\"Bedroom Wardrobe\",\"description\":\"Spacious storage with elegant styling\"}],\"video_title\":\"<h2><span style=\\\"color: #2f5b88;\\\">C</span>rafted with Passion &amp; Precision</h2>\",\"video_subtitle\":\"<p>Every cabinet is handcrafted by skilled artisans using premium materials. Watch our craftsmen bring your vision to life.</p>\",\"video_url\":\"/uploads/general/Cabinets.mp4\",\"video_poster\":\"/assets/images/video-poster.jpg\",\"cta_headline\":\"<h2>Ready to Design Your Dream Cabinet?</h2>\",\"cta_text\":\"<p>Join hundreds of satisfied customers who transformed their spaces</p>\",\"footer_company\":\"RADS TOOLING\",\"footer_description\":\"Premium custom cabinet manufacturer serving clients since 2007. Quality craftsmanship, affordable prices, and exceptional service.\",\"footer_address\":\"Green Breeze, Piela, Dasmariñas, Cavite\",\"footer_email\":\"RadsTooling@gmail.com\",\"footer_phone\":\"+63 976 228 4270\",\"footer_hours\":\"Mon-Sat: 8:00 AM - 5:00 PM\",\"footer_copyright\":\"© 2025 RADS TOOLING INC. All rights reserved.\"}', 'published', 27, 'System Owner44', '2025-11-03 13:05:30', '2025-11-06 09:06:51'),
(268, 'home_customer', 'Customer Homepage', '{\"welcome_message\":\"<h1>Welcome back, <span style=\\\"color: #2f5b88;\\\">{{customer_name}}</span>!</h1>\",\"intro_text\":\"<p>Explore our latest cabinet designs and continue your projectss</p>\",\"footer_email\":\"RadsTooling@gmail.com\",\"footer_phone\":\"+63 976 228 4270\",\"footer_address\":\"Green Breeze, Piela, Dasmariñas, Cavite\",\"footer_hours\":\"Mon-Sat: 8:00 AM - 5:00 PM\",\"welcome\":\"<h1>Welcome backs, <span style=\\\"color: #2f5b88;\\\">{{customer_name}}</span>!</h1>\",\"intro\":\"<p>Explore our latest cabinet designs and continue your projects</p>\",\"hero_image\":\"/assets/images/cabinet-hero.jpg\",\"cta_primary_text\":\"Start Browsing and Designing Products\",\"cta_secondary_text\":\"Browse Products\",\"footer_description\":\"Premium custom cabinet manufacturer serving clients since 2007. Quality craftsmanship, affordable prices, and exceptional service.\",\"customer_hero_image\":\"/assets/images/cabinet-hero.jpg\"}', 'draft', 9, 'System Owner44', '2025-11-06 09:54:54', '2025-11-06 08:54:54'),
(269, 'home_customer', 'Customer Homepage', '{\"welcome_message\":\"<h1>Welcome back, <span style=\\\"color: #2f5b88;\\\">{{customer_name}}</span>!</h1>\",\"intro_text\":\"<p>Explore our latest cabinet designs and continue your projectss</p>\",\"footer_email\":\"RadsTooling@gmail.com\",\"footer_phone\":\"+63 976 228 4270\",\"footer_address\":\"Green Breeze, Piela, Dasmariñas, Cavite\",\"footer_hours\":\"Mon-Sat: 8:00 AM - 5:00 PM\",\"welcome\":\"<h1>Welcome backs, <span style=\\\"color: #2f5b88;\\\">{{customer_name}}</span>!</h1>\",\"intro\":\"<p>Explore our latest cabinet designs and continue your projects</p>\",\"hero_image\":\"/assets/images/cabinet-hero.jpg\",\"cta_primary_text\":\"Start Browsing and Designing Products\",\"cta_secondary_text\":\"Browse Products\",\"footer_description\":\"Premium custom cabinet manufacturer serving clients since 2007. Quality craftsmanship, affordable prices, and exceptional service.\",\"customer_hero_image\":\"/assets/images/cabinet-hero.jpg\"}', 'published', 9, 'System Owner44', '2025-11-06 09:55:00', '2025-11-06 08:55:00');

-- --------------------------------------------------------

--
-- Table structure for table `rt_faqs`
--

CREATE TABLE `rt_faqs` (
  `id` int(11) UNSIGNED NOT NULL,
  `question` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rt_faqs`
--

INSERT INTO `rt_faqs` (`id`, `question`, `answer`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 'Can I customize size/color?', 'Yes! Share your preferred dimensions and color; we will confirm feasibility and pricing.', 1, '2025-10-06 14:42:30', '2025-10-09 10:53:24'),
(10, 'What are your lead times?', 'Typical build time is 7–14 days depending on customization.', 1, '2025-10-07 12:03:42', '2025-10-07 12:03:42'),
(12, 'What are your operating hours?', 'We operate from 8am-5pm', 1, '2025-10-07 14:24:18', '2025-10-07 17:16:12'),
(14, 'Do you deliver?', 'Yes! We deliver within Cavite and nearby areas!', 1, '2025-10-09 10:27:33', '2025-10-25 00:38:27'),
(16, 'can i buy?', 'wala sarado', 1, '2025-11-04 17:32:37', '2025-11-04 17:32:37');

-- --------------------------------------------------------

--
-- Table structure for table `textures`
--

CREATE TABLE `textures` (
  `id` int(11) UNSIGNED NOT NULL,
  `texture_name` varchar(100) NOT NULL,
  `texture_code` varchar(50) NOT NULL,
  `texture_image` varchar(255) NOT NULL,
  `allowed_parts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `base_price` decimal(10,2) DEFAULT '0.00',
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cabinets`
--
ALTER TABLE `cabinets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `colors`
--
ALTER TABLE `colors`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `color_allowed_parts`
--
ALTER TABLE `color_allowed_parts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `customer_addresses`
--
ALTER TABLE `customer_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_is_default` (`is_default`),
  ADD KEY `idx_customer_default` (`customer_id`,`is_default`);

--
-- Indexes for table `customer_carts`
--
ALTER TABLE `customer_carts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_customer_cart` (`customer_id`),
  ADD KEY `idx_customer_id` (`customer_id`);

--
-- Indexes for table `customizations`
--
ALTER TABLE `customizations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customization_steps`
--
ALTER TABLE `customization_steps`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `handle_types`
--
ALTER TABLE `handle_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `measurement_units`
--
ALTER TABLE `measurement_units`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `order_addresses`
--
ALTER TABLE `order_addresses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `order_completions`
--
ALTER TABLE `order_completions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_installments`
--
ALTER TABLE `payment_installments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_qr`
--
ALTER TABLE `payment_qr`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_verifications`
--
ALTER TABLE `payment_verifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `php_mailer_info`
--
ALTER TABLE `php_mailer_info`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_colors`
--
ALTER TABLE `product_colors`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_handles`
--
ALTER TABLE `product_handles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`image_id`);

--
-- Indexes for table `product_material_map`
--
ALTER TABLE `product_material_map`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_size_config`
--
ALTER TABLE `product_size_config`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_textures`
--
ALTER TABLE `product_textures`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_texture_parts`
--
ALTER TABLE `product_texture_parts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rt_chat_messages`
--
ALTER TABLE `rt_chat_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rt_chat_threads`
--
ALTER TABLE `rt_chat_threads`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rt_cms_pages`
--
ALTER TABLE `rt_cms_pages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rt_faqs`
--
ALTER TABLE `rt_faqs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `textures`
--
ALTER TABLE `textures`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cabinets`
--
ALTER TABLE `cabinets`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `colors`
--
ALTER TABLE `colors`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `color_allowed_parts`
--
ALTER TABLE `color_allowed_parts`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `customer_addresses`
--
ALTER TABLE `customer_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `customer_carts`
--
ALTER TABLE `customer_carts`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customizations`
--
ALTER TABLE `customizations`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customization_steps`
--
ALTER TABLE `customization_steps`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `handle_types`
--
ALTER TABLE `handle_types`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `measurement_units`
--
ALTER TABLE `measurement_units`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `order_addresses`
--
ALTER TABLE `order_addresses`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `order_completions`
--
ALTER TABLE `order_completions`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `payment_installments`
--
ALTER TABLE `payment_installments`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `payment_qr`
--
ALTER TABLE `payment_qr`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payment_verifications`
--
ALTER TABLE `payment_verifications`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `php_mailer_info`
--
ALTER TABLE `php_mailer_info`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `product_colors`
--
ALTER TABLE `product_colors`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `product_handles`
--
ALTER TABLE `product_handles`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `image_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `product_material_map`
--
ALTER TABLE `product_material_map`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_size_config`
--
ALTER TABLE `product_size_config`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `product_textures`
--
ALTER TABLE `product_textures`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_texture_parts`
--
ALTER TABLE `product_texture_parts`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rt_chat_messages`
--
ALTER TABLE `rt_chat_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `rt_chat_threads`
--
ALTER TABLE `rt_chat_threads`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `rt_cms_pages`
--
ALTER TABLE `rt_cms_pages`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=272;

--
-- AUTO_INCREMENT for table `rt_faqs`
--
ALTER TABLE `rt_faqs`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `textures`
--
ALTER TABLE `textures`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
