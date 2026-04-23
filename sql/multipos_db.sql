-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 17, 2026 at 11:20 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `multipos_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `store_id` int(11) DEFAULT NULL,
  `actor_user_id` int(11) NOT NULL,
  `actor_role` varchar(20) NOT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` bigint(20) DEFAULT NULL,
  `message` varchar(255) NOT NULL,
  `meta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_json`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `store_id`, `actor_user_id`, `actor_role`, `action`, `entity_type`, `entity_id`, `message`, `meta_json`, `ip_address`, `user_agent`, `created_at`) VALUES
(2, 1, 3, 'kasir', 'TEST_MANUAL', NULL, NULL, 'Test manual dari kasir_shift.php', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 12:26:27'),
(3, 1, 3, 'kasir', 'SHIFT_CLOSE', 'cashier_shift', 4, 'Close shift #4 (closing 0)', '{\"store_id\":1,\"closing_cash\":0,\"note\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 12:26:27'),
(4, 1, 3, 'kasir', 'TEST_MANUAL', NULL, NULL, 'Test manual dari kasir_shift.php', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 12:26:27'),
(5, 1, 3, 'kasir', 'SHIFT_OPEN', 'cashier_shift', 5, 'Open shift #5 (opening_cash 0)', '{\"store_id\":1,\"kasir_id\":3,\"opening_cash\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 12:28:25'),
(6, 1, 3, 'kasir', 'SALE_CREATE', 'sale', 4, 'Checkout INV20260209-000004 (sale #4) total 5000', '{\"invoice_no\":\"INV20260209-000004\",\"store_id\":1,\"shift_id\":5,\"total\":5000,\"paid\":5000,\"change\":0,\"payment_method\":\"qris\",\"order_note\":\"\",\"items\":[{\"product_id\":3,\"qty\":1,\"price\":5000,\"subtotal\":5000,\"discount_percent\":0}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 12:29:49'),
(7, 1, 2, 'admin', 'ADMIN_REGISTER', 'admin', 2, 'Admin self-registered #2 (leonardosaputra666@gmail.com)', '{\"method\":\"register_admin\",\"backfill\":true,\"admin_email\":\"leonardosaputra666@gmail.com\",\"admin_name\":\"Leonardo\",\"store_id\":1,\"store_name\":\"Toko Kita\"}', NULL, NULL, '2026-02-09 13:51:34'),
(9, 1, 3, 'kasir', 'SHIFT_CLOSE', 'cashier_shift', 5, 'Close shift #5 (closing_cash 0)', '{\"store_id\":1,\"kasir_id\":3,\"closing_cash\":0,\"note\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 14:57:12'),
(10, 1, 3, 'kasir', 'KASIR_SETTINGS_UPDATE', 'store', 1, 'Kasir mengubah pengaturan struk', '{\"receipt_show_logo\":1,\"receipt_auto_print\":1,\"receipt_paper\":\"80mm\",\"logo_changed\":false}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 16:34:17'),
(11, 1, 3, 'kasir', 'KASIR_SETTINGS_UPDATE', 'store', 1, 'Kasir mengubah pengaturan struk', '{\"receipt_show_logo\":1,\"receipt_auto_print\":1,\"receipt_paper\":\"80mm\",\"logo_changed\":false}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 16:34:47'),
(12, 1, 3, 'kasir', 'KASIR_SETTINGS_UPDATE', 'store', 1, 'Kasir mengubah pengaturan struk', '{\"receipt_show_logo\":1,\"receipt_auto_print\":1,\"receipt_paper\":\"80mm\",\"logo_changed\":false}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 16:34:53'),
(13, 1, 3, 'kasir', 'SHIFT_OPEN', 'cashier_shift', 6, 'Open shift #6 (opening_cash 0)', '{\"store_id\":1,\"kasir_id\":3,\"opening_cash\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 16:41:50'),
(14, 1, 3, 'kasir', 'SALE_CREATE', 'sale', 5, 'Checkout INV20260209-000005 (sale #5) total 5000', '{\"invoice_no\":\"INV20260209-000005\",\"store_id\":1,\"shift_id\":6,\"total\":5000,\"paid\":5000,\"change\":0,\"payment_method\":\"qris\",\"order_note\":\"\",\"items\":[{\"product_id\":3,\"qty\":1,\"price\":5000,\"subtotal\":5000,\"discount_percent\":0}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 16:41:55'),
(15, 1, 3, 'kasir', 'SALE_CREATE', 'sale', 9, 'Checkout INV20260209-000009 (sale #9) total 5000', '{\"invoice_no\":\"INV20260209-000009\",\"store_id\":1,\"shift_id\":6,\"total\":5000,\"paid\":10000,\"change\":5000,\"payment_method\":\"cash\",\"order_note\":\"\",\"items\":[{\"product_id\":3,\"qty\":1,\"price\":5000,\"subtotal\":5000,\"discount_percent\":0}],\"customer\":{\"id\":null,\"name\":\"\",\"phone\":\"\"}}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 20:23:39'),
(16, 1, 3, 'kasir', 'CASH_IN', 'cash_movement', 1, 'Kas masuk #1 (50000)', '{\"store_id\":1,\"shift_id\":6,\"kasir_id\":3,\"type\":\"in\",\"amount\":50000,\"category\":null,\"note\":\"test\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 21:56:30'),
(17, 1, 3, 'kasir', 'SHIFT_CLOSE', 'cashier_shift', 6, 'Close shift #6 (closing_cash 0)', '{\"store_id\":1,\"kasir_id\":3,\"closing_cash\":0,\"note\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 14:32:30'),
(18, 3, 8, 'admin', 'ADMIN_REGISTER', 'admin', 8, 'Admin self-registered #8 (basic@gmail.com)', '{\"method\":\"register_admin\",\"admin_email\":\"basic@gmail.com\",\"admin_name\":\"testing mode basic\",\"store_id\":3,\"store_name\":\"percobaan basic\",\"store_type\":\"basic\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 15:56:21'),
(23, 8, 16, 'admin', 'ADMIN_REGISTER', 'admin', 16, 'Admin self-registered #16 (HaifaChiken@gmail.com)', '{\"method\":\"register_admin\",\"admin_email\":\"HaifaChiken@gmail.com\",\"admin_name\":\"Haifa Chiken\",\"store_id\":8,\"store_name\":\"Haifa Chiken\",\"store_type\":\"bom\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-17 11:45:39'),
(24, 1, 1, 'developer', 'RESET_ADMIN_PASSWORD', 'users', 2, 'Developer reset password admin #2 untuk store #1', '{\"target_admin_id\":2,\"target_admin_email\":\"leonardosaputra666@gmail.com\",\"store_id\":1,\"store_name\":\"Toko Kita\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-17 15:40:19'),
(25, 1, 1, 'developer', 'RESET_ADMIN_PASSWORD', 'users', 2, 'Developer reset password admin #2 untuk store #1', '{\"target_admin_id\":2,\"target_admin_email\":\"leonardosaputra666@gmail.com\",\"store_id\":1,\"store_name\":\"Toko Kita\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-17 15:42:30');

-- --------------------------------------------------------

--
-- Table structure for table `bom_recipes`
--

CREATE TABLE `bom_recipes` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `instructions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bom_recipes`
--

INSERT INTO `bom_recipes` (`id`, `store_id`, `product_id`, `note`, `is_active`, `created_at`, `updated_at`, `instructions`) VALUES
(1, 1, 2, '', 1, '2026-02-08 05:09:52', '2026-03-11 02:08:40', 'masak nasi goreng seperti biasanya'),
(2, 1, 5, NULL, 1, '2026-02-10 00:29:08', '2026-02-10 00:29:08', 'buat es teh seperti biasanya'),
(3, 8, 18, NULL, 1, '2026-04-17 13:13:17', '2026-04-17 13:15:40', NULL),
(4, 8, 9, NULL, 1, '2026-04-17 13:16:09', '2026-04-17 13:16:38', NULL),
(5, 8, 10, NULL, 1, '2026-04-17 13:16:56', '2026-04-17 13:17:22', NULL),
(6, 8, 12, NULL, 1, '2026-04-17 13:17:45', '2026-04-17 13:18:12', NULL),
(7, 8, 13, NULL, 1, '2026-04-17 13:18:49', '2026-04-17 13:19:00', NULL),
(8, 8, 11, NULL, 1, '2026-04-17 13:19:26', '2026-04-17 13:19:35', NULL),
(9, 8, 19, NULL, 1, '2026-04-17 13:20:27', '2026-04-17 13:20:51', NULL),
(10, 8, 20, NULL, 1, '2026-04-17 13:21:36', '2026-04-17 13:22:24', NULL),
(11, 8, 22, NULL, 1, '2026-04-17 13:22:59', '2026-04-17 13:23:40', NULL),
(12, 8, 17, NULL, 1, '2026-04-17 13:24:21', '2026-04-17 13:24:21', NULL),
(13, 8, 23, NULL, 1, '2026-04-17 13:24:44', '2026-04-17 13:24:44', NULL),
(14, 8, 25, NULL, 1, '2026-04-17 13:27:14', '2026-04-17 13:27:30', NULL),
(15, 8, 27, NULL, 1, '2026-04-17 13:27:54', '2026-04-17 13:28:03', NULL),
(16, 8, 24, NULL, 1, '2026-04-17 13:29:01', '2026-04-17 13:29:08', NULL),
(17, 8, 26, NULL, 1, '2026-04-17 13:29:19', '2026-04-17 13:29:26', NULL),
(18, 8, 57, NULL, 1, '2026-04-17 13:30:02', '2026-04-17 13:30:27', NULL),
(19, 8, 52, NULL, 1, '2026-04-17 13:30:44', '2026-04-17 13:30:44', NULL),
(20, 8, 55, NULL, 1, '2026-04-17 13:31:12', '2026-04-17 13:32:02', NULL),
(21, 8, 56, NULL, 1, '2026-04-17 13:32:17', '2026-04-17 13:32:31', NULL),
(22, 8, 53, NULL, 1, '2026-04-17 13:32:47', '2026-04-17 13:32:47', NULL),
(23, 8, 54, NULL, 1, '2026-04-17 13:33:09', '2026-04-17 13:33:09', NULL),
(24, 8, 58, NULL, 1, '2026-04-17 13:33:35', '2026-04-17 13:33:35', NULL),
(25, 8, 49, NULL, 1, '2026-04-17 13:34:43', '2026-04-17 13:35:00', NULL),
(26, 8, 50, NULL, 1, '2026-04-17 13:35:16', '2026-04-17 13:35:23', NULL),
(27, 8, 51, NULL, 1, '2026-04-17 13:35:33', '2026-04-17 13:36:09', NULL),
(28, 8, 40, NULL, 1, '2026-04-17 13:36:38', '2026-04-17 13:36:52', NULL),
(29, 8, 39, NULL, 1, '2026-04-17 13:37:08', '2026-04-17 13:37:28', NULL),
(30, 8, 41, NULL, 1, '2026-04-17 13:37:44', '2026-04-17 13:38:07', NULL),
(31, 8, 42, NULL, 1, '2026-04-17 13:38:31', '2026-04-17 13:38:31', NULL),
(32, 8, 43, NULL, 1, '2026-04-17 13:41:12', '2026-04-17 13:41:37', NULL),
(33, 8, 44, NULL, 1, '2026-04-17 13:41:55', '2026-04-17 13:42:19', NULL),
(34, 8, 45, NULL, 1, '2026-04-17 13:42:31', '2026-04-17 13:42:56', NULL),
(35, 8, 46, NULL, 1, '2026-04-17 13:43:34', '2026-04-17 13:43:55', NULL),
(36, 8, 47, NULL, 1, '2026-04-17 13:44:07', '2026-04-17 13:44:22', NULL),
(37, 8, 48, NULL, 1, '2026-04-17 13:44:32', '2026-04-17 13:44:59', NULL),
(38, 8, 29, NULL, 1, '2026-04-17 13:45:37', '2026-04-17 13:46:24', NULL),
(39, 8, 32, NULL, 1, '2026-04-17 13:46:45', '2026-04-17 13:47:06', NULL),
(40, 8, 28, NULL, 1, '2026-04-17 13:47:25', '2026-04-17 13:47:46', NULL),
(41, 8, 31, NULL, 1, '2026-04-17 13:48:05', '2026-04-17 13:48:49', NULL),
(42, 8, 33, NULL, 1, '2026-04-17 13:49:19', '2026-04-17 13:49:41', NULL),
(43, 8, 30, NULL, 1, '2026-04-17 13:49:58', '2026-04-17 13:50:13', NULL),
(44, 8, 38, NULL, 1, '2026-04-17 13:50:30', '2026-04-17 13:50:45', NULL),
(45, 8, 35, NULL, 1, '2026-04-17 13:51:13', '2026-04-17 13:52:02', NULL),
(46, 8, 37, NULL, 1, '2026-04-17 13:52:16', '2026-04-17 13:52:27', NULL),
(47, 8, 36, NULL, 1, '2026-04-17 13:52:44', '2026-04-17 13:52:57', NULL),
(48, 8, 14, NULL, 1, '2026-04-17 13:54:10', '2026-04-17 13:54:45', NULL),
(49, 8, 15, NULL, 1, '2026-04-17 13:55:04', '2026-04-17 13:55:36', NULL),
(50, 8, 16, NULL, 1, '2026-04-17 13:56:17', '2026-04-17 13:56:45', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `bom_recipe_items`
--

CREATE TABLE `bom_recipe_items` (
  `id` int(11) NOT NULL,
  `recipe_id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `qty` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bom_recipe_items`
--

INSERT INTO `bom_recipe_items` (`id`, `recipe_id`, `ingredient_id`, `qty`, `created_at`) VALUES
(1, 1, 1, 1.000000, '2026-02-08 05:10:04'),
(2, 1, 3, 10.000000, '2026-03-11 02:07:39'),
(3, 1, 4, 5.000000, '2026-03-11 02:07:47'),
(4, 1, 2, 2.000000, '2026-03-11 02:07:55'),
(5, 1, 7, 1.000000, '2026-03-11 02:08:21'),
(6, 1, 8, 2.000000, '2026-03-11 02:08:25'),
(7, 1, 6, 2.000000, '2026-03-11 02:08:31'),
(8, 3, 13, 10.000000, '2026-04-17 13:13:17'),
(9, 3, 32, 140.000000, '2026-04-17 13:15:22'),
(10, 3, 33, 100.000000, '2026-04-17 13:15:40'),
(11, 4, 13, 10.000000, '2026-04-17 13:16:09'),
(12, 4, 14, 10.000000, '2026-04-17 13:16:19'),
(13, 4, 32, 100.000000, '2026-04-17 13:16:38'),
(14, 5, 13, 10.000000, '2026-04-17 13:16:56'),
(15, 5, 15, 20.000000, '2026-04-17 13:17:12'),
(16, 5, 32, 100.000000, '2026-04-17 13:17:22'),
(17, 6, 13, 15.000000, '2026-04-17 13:17:45'),
(18, 6, 32, 70.000000, '2026-04-17 13:18:12'),
(19, 7, 16, 15.000000, '2026-04-17 13:18:49'),
(20, 7, 32, 50.000001, '2026-04-17 13:19:00'),
(21, 8, 16, 15.000000, '2026-04-17 13:19:26'),
(22, 8, 15, 15.000000, '2026-04-17 13:19:35'),
(23, 9, 13, 30.000000, '2026-04-17 13:20:27'),
(24, 9, 17, 100.000000, '2026-04-17 13:20:51'),
(25, 10, 13, 15.000000, '2026-04-17 13:21:36'),
(28, 10, 17, 150.000000, '2026-04-17 13:22:09'),
(29, 10, 32, 100.000000, '2026-04-17 13:22:24'),
(30, 11, 19, 10.000000, '2026-04-17 13:22:59'),
(31, 11, 15, 10.000000, '2026-04-17 13:23:09'),
(32, 11, 13, 15.000000, '2026-04-17 13:23:20'),
(33, 11, 17, 150.000000, '2026-04-17 13:23:40'),
(34, 12, 13, 18.000000, '2026-04-17 13:24:21'),
(35, 13, 20, 10.000000, '2026-04-17 13:24:44'),
(36, 14, 20, 10.000000, '2026-04-17 13:27:14'),
(37, 14, 34, 130.000000, '2026-04-17 13:27:30'),
(38, 15, 20, 10.000000, '2026-04-17 13:27:54'),
(39, 15, 34, 130.000000, '2026-04-17 13:28:03'),
(40, 16, 20, 10.000000, '2026-04-17 13:29:01'),
(41, 16, 34, 130.000000, '2026-04-17 13:29:08'),
(42, 17, 20, 10.000000, '2026-04-17 13:29:19'),
(43, 17, 34, 130.000000, '2026-04-17 13:29:26'),
(45, 18, 15, 17.000000, '2026-04-17 13:30:16'),
(46, 18, 21, 20.000000, '2026-04-17 13:30:27'),
(47, 19, 15, 35.000000, '2026-04-17 13:30:44'),
(48, 20, 23, 1.000000, '2026-04-17 13:31:12'),
(49, 20, 15, 35.000000, '2026-04-17 13:31:23'),
(50, 20, 32, 100.000000, '2026-04-17 13:32:02'),
(51, 21, 22, 1.000000, '2026-04-17 13:32:17'),
(52, 21, 15, 35.000000, '2026-04-17 13:32:25'),
(53, 21, 32, 100.000000, '2026-04-17 13:32:31'),
(54, 22, 23, 1.000000, '2026-04-17 13:32:47'),
(55, 23, 22, 1.000000, '2026-04-17 13:33:09'),
(56, 24, 24, 150.000000, '2026-04-17 13:33:35'),
(57, 25, 25, 25.000000, '2026-04-17 13:34:43'),
(58, 25, 17, 80.000000, '2026-04-17 13:34:51'),
(59, 25, 32, 50.000000, '2026-04-17 13:35:00'),
(60, 26, 25, 25.000000, '2026-04-17 13:35:16'),
(61, 26, 17, 80.000000, '2026-04-17 13:35:23'),
(62, 27, 25, 25.000000, '2026-04-17 13:35:33'),
(63, 27, 17, 80.000000, '2026-04-17 13:35:41'),
(64, 27, 33, 50.000000, '2026-04-17 13:36:09'),
(65, 28, 15, 15.000000, '2026-04-17 13:36:38'),
(66, 28, 13, 15.000000, '2026-04-17 13:36:52'),
(67, 29, 26, 30.000000, '2026-04-17 13:37:08'),
(68, 29, 27, 15.000000, '2026-04-17 13:37:18'),
(69, 29, 17, 80.000000, '2026-04-17 13:37:28'),
(70, 30, 27, 15.000000, '2026-04-17 13:37:44'),
(71, 30, 17, 80.000000, '2026-04-17 13:37:54'),
(72, 30, 32, 70.000000, '2026-04-17 13:38:07'),
(73, 31, 28, 1.000000, '2026-04-17 13:38:31'),
(74, 32, 36, 18.000000, '2026-04-17 13:41:12'),
(75, 32, 35, 25.000000, '2026-04-17 13:41:23'),
(76, 32, 32, 100.000000, '2026-04-17 13:41:29'),
(77, 32, 33, 50.000000, '2026-04-17 13:41:37'),
(78, 33, 36, 18.000000, '2026-04-17 13:41:55'),
(79, 33, 35, 25.000000, '2026-04-17 13:42:08'),
(80, 33, 32, 100.000000, '2026-04-17 13:42:13'),
(81, 33, 33, 50.000000, '2026-04-17 13:42:19'),
(82, 34, 36, 18.000000, '2026-04-17 13:42:31'),
(83, 34, 35, 25.000000, '2026-04-17 13:42:39'),
(84, 34, 32, 100.000000, '2026-04-17 13:42:45'),
(85, 34, 33, 50.000000, '2026-04-17 13:42:56'),
(86, 35, 36, 18.000000, '2026-04-17 13:43:34'),
(87, 35, 35, 25.000000, '2026-04-17 13:43:40'),
(88, 35, 32, 100.000000, '2026-04-17 13:43:45'),
(89, 35, 33, 50.000000, '2026-04-17 13:43:55'),
(90, 36, 36, 18.000000, '2026-04-17 13:44:07'),
(91, 36, 35, 25.000000, '2026-04-17 13:44:12'),
(92, 36, 32, 100.000000, '2026-04-17 13:44:17'),
(93, 36, 33, 50.000000, '2026-04-17 13:44:22'),
(94, 37, 36, 18.000000, '2026-04-17 13:44:32'),
(95, 37, 35, 25.000000, '2026-04-17 13:44:40'),
(96, 37, 32, 100.000000, '2026-04-17 13:44:47'),
(97, 37, 33, 50.000000, '2026-04-17 13:44:59'),
(98, 38, 29, 18.000000, '2026-04-17 13:45:37'),
(100, 38, 32, 100.000000, '2026-04-17 13:45:53'),
(101, 38, 33, 50.000000, '2026-04-17 13:45:59'),
(102, 38, 15, 16.000000, '2026-04-17 13:46:24'),
(103, 39, 29, 18.000000, '2026-04-17 13:46:45'),
(104, 39, 15, 16.000000, '2026-04-17 13:46:56'),
(105, 39, 32, 100.000000, '2026-04-17 13:47:01'),
(106, 39, 33, 50.000000, '2026-04-17 13:47:06'),
(107, 40, 29, 18.000000, '2026-04-17 13:47:25'),
(108, 40, 15, 16.000000, '2026-04-17 13:47:34'),
(109, 40, 32, 100.000000, '2026-04-17 13:47:40'),
(110, 40, 33, 50.000000, '2026-04-17 13:47:46'),
(111, 41, 29, 18.000000, '2026-04-17 13:48:05'),
(112, 41, 15, 16.000000, '2026-04-17 13:48:21'),
(113, 41, 17, 150.000000, '2026-04-17 13:48:49'),
(114, 42, 30, 18.000000, '2026-04-17 13:49:19'),
(115, 42, 15, 16.000000, '2026-04-17 13:49:30'),
(116, 42, 17, 80.000000, '2026-04-17 13:49:41'),
(117, 43, 30, 18.000000, '2026-04-17 13:49:58'),
(118, 43, 15, 16.000000, '2026-04-17 13:50:06'),
(119, 43, 17, 80.000000, '2026-04-17 13:50:13'),
(120, 44, 30, 18.000000, '2026-04-17 13:50:30'),
(121, 44, 15, 16.000000, '2026-04-17 13:50:37'),
(122, 44, 17, 80.000000, '2026-04-17 13:50:45'),
(123, 45, 30, 18.000000, '2026-04-17 13:51:13'),
(124, 45, 15, 16.000000, '2026-04-17 13:51:56'),
(125, 45, 17, 80.000000, '2026-04-17 13:52:02'),
(126, 46, 30, 18.000000, '2026-04-17 13:52:16'),
(127, 46, 15, 16.000000, '2026-04-17 13:52:21'),
(128, 46, 17, 80.000000, '2026-04-17 13:52:27'),
(129, 47, 30, 18.000000, '2026-04-17 13:52:44'),
(130, 47, 15, 16.000000, '2026-04-17 13:52:48'),
(131, 47, 17, 80.000000, '2026-04-17 13:52:57'),
(132, 48, 31, 35.000000, '2026-04-17 13:54:10'),
(133, 48, 15, 20.000000, '2026-04-17 13:54:21'),
(134, 48, 17, 100.000000, '2026-04-17 13:54:34'),
(135, 48, 33, 80.000000, '2026-04-17 13:54:45'),
(136, 49, 31, 35.000000, '2026-04-17 13:55:04'),
(137, 49, 15, 20.000000, '2026-04-17 13:55:18'),
(138, 49, 17, 100.000000, '2026-04-17 13:55:25'),
(139, 49, 33, 80.000000, '2026-04-17 13:55:36'),
(140, 50, 31, 35.000000, '2026-04-17 13:56:17'),
(141, 50, 15, 20.000000, '2026-04-17 13:56:29'),
(142, 50, 17, 50.000000, '2026-04-17 13:56:38'),
(143, 50, 33, 80.000000, '2026-04-17 13:56:45');

-- --------------------------------------------------------

--
-- Table structure for table `cashier_shifts`
--

CREATE TABLE `cashier_shifts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `store_id` bigint(20) UNSIGNED NOT NULL,
  `kasir_id` bigint(20) UNSIGNED NOT NULL,
  `opened_at` datetime NOT NULL,
  `closed_at` datetime DEFAULT NULL,
  `opening_cash` int(11) NOT NULL DEFAULT 0,
  `closing_cash` int(11) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cashier_shifts`
--

INSERT INTO `cashier_shifts` (`id`, `store_id`, `kasir_id`, `opened_at`, `closed_at`, `opening_cash`, `closing_cash`, `note`, `status`) VALUES
(1, 1, 3, '2026-02-08 00:35:56', '2026-02-08 00:40:00', 0, 0, NULL, 'closed'),
(2, 1, 3, '2026-02-08 00:42:20', '2026-02-08 00:42:30', 0, 0, 'Penjualan', 'closed'),
(3, 1, 3, '2026-02-08 01:09:50', '2026-02-08 01:17:49', 0, 0, NULL, 'closed'),
(4, 1, 3, '2026-02-08 02:56:12', '2026-02-09 12:26:27', 0, 0, NULL, 'closed'),
(5, 1, 3, '2026-02-09 12:28:25', '2026-02-09 14:57:12', 0, 0, NULL, 'closed'),
(6, 1, 3, '2026-02-09 16:41:50', '2026-02-10 14:32:30', 0, 0, NULL, 'closed'),
(7, 3, 15, '2026-02-11 00:41:42', NULL, 0, NULL, '', 'open');

-- --------------------------------------------------------

--
-- Table structure for table `cash_movements`
--

CREATE TABLE `cash_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `store_id` bigint(20) UNSIGNED NOT NULL,
  `shift_id` bigint(20) UNSIGNED DEFAULT NULL,
  `kasir_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('in','out') NOT NULL,
  `amount` int(11) NOT NULL DEFAULT 0,
  `category` varchar(50) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cash_movements`
--

INSERT INTO `cash_movements` (`id`, `store_id`, `shift_id`, `kasir_id`, `type`, `amount`, `category`, `note`, `created_at`) VALUES
(1, 1, 6, 3, 'in', 50000, NULL, 'test', '2026-02-09 21:56:30');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `store_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `store_id`, `name`, `is_active`, `created_at`) VALUES
(1, 1, 'Makanan', 1, '2026-02-07 16:56:50'),
(2, 1, 'Minuman', 1, '2026-02-07 16:56:55'),
(3, 1, 'Tambahan', 1, '2026-02-07 17:16:05'),
(12, 3, 'Makanan', 1, '2026-02-10 17:34:30'),
(13, 3, 'Minuman', 1, '2026-02-10 17:34:50'),
(14, 3, 'Tambahan', 1, '2026-02-10 17:34:56'),
(17, 8, 'SIGNATURE', 1, '2026-04-17 04:53:05'),
(18, 8, 'TEA', 1, '2026-04-17 04:53:09'),
(19, 8, 'MILK BASED', 1, '2026-04-17 04:53:15'),
(20, 8, 'BISKUIT', 1, '2026-04-17 04:53:23'),
(21, 8, 'COFFE SERIES', 1, '2026-04-17 04:53:54'),
(22, 8, 'ESPRESSO SERIES', 1, '2026-04-17 04:54:06'),
(23, 8, 'SQUASH', 1, '2026-04-17 04:54:25'),
(24, 8, 'CLASSIC', 1, '2026-04-17 04:54:31'),
(25, 8, 'YOGURT', 1, '2026-04-17 04:54:39');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `store_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `notes` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_visit_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ingredients`
--

CREATE TABLE `ingredients` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'pcs',
  `stock` decimal(12,3) NOT NULL DEFAULT 0.000,
  `min_stock` decimal(12,3) NOT NULL DEFAULT 0.000,
  `safety_stock` decimal(12,3) NOT NULL DEFAULT 0.000,
  `lead_time_days` int(11) NOT NULL DEFAULT 1,
  `reorder_point` decimal(12,3) NOT NULL DEFAULT 0.000,
  `avg_daily_usage` decimal(12,3) NOT NULL DEFAULT 0.000,
  `suggested_restock_qty` decimal(12,3) NOT NULL DEFAULT 0.000,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ingredients`
--

INSERT INTO `ingredients` (`id`, `store_id`, `name`, `unit`, `stock`, `min_stock`, `safety_stock`, `lead_time_days`, `reorder_point`, `avg_daily_usage`, `suggested_restock_qty`, `is_active`, `created_at`, `updated_at`) VALUES
(2, 1, 'Cabe Rawit', 'gram', 1500.000, 100.000, 100.000, 1, 100.000, 0.000, 100.000, 1, '2026-02-08 05:33:54', '2026-03-11 02:12:02'),
(3, 1, 'Bawang Merah', 'gram', 1000.000, 100.000, 500.000, 2, 500.000, 0.000, 500.000, 1, '2026-02-08 05:34:29', '2026-03-11 02:11:19'),
(4, 1, 'Bawang Putih', 'gram', 2000.000, 100.000, 300.000, 3, 300.000, 0.000, 300.000, 1, '2026-02-08 05:34:44', '2026-03-11 02:11:33'),
(5, 1, 'Mie Goreng Instan', 'pcs', 10.000, 0.000, 5.000, 6, 5.000, 0.000, 5.000, 1, '2026-02-08 05:34:58', '2026-03-11 02:14:33'),
(6, 1, 'Telur', 'pcs', 45.000, 0.000, 10.000, 5, 10.000, 0.000, 10.000, 1, '2026-02-08 05:35:20', '2026-03-11 02:15:09'),
(7, 1, 'Gula', 'gram', 3000.000, 0.000, 100.000, 4, 100.000, 0.000, 100.000, 1, '2026-02-08 05:35:29', '2026-03-11 02:14:10'),
(8, 1, 'Garam', 'gram', 100.000, 0.000, 100.000, 10, 100.000, 0.000, 100.000, 1, '2026-02-08 05:35:40', '2026-03-11 02:13:03'),
(12, 1, 'Teh', 'pcs', 0.000, 0.000, 5.000, 1, 5.000, 0.000, 5.000, 1, '2026-03-11 02:10:59', '2026-03-11 02:10:59'),
(13, 8, 'Kopi Bubuk', 'gram', 3000.000, 0.000, 100.000, 1, 100.000, 0.000, 100.000, 1, '2026-04-17 12:57:00', '2026-04-17 14:06:58'),
(14, 8, 'Gula', 'gram', 1000.000, 0.000, 100.000, 1, 100.000, 0.000, 100.000, 1, '2026-04-17 12:57:15', '2026-04-17 14:06:00'),
(15, 8, 'SKM', 'gram', 5000.000, 0.000, 100.000, 1, 100.000, 0.000, 100.000, 1, '2026-04-17 12:57:38', '2026-04-17 14:08:01'),
(16, 8, 'Kopi Arabica', 'gram', 5000.000, 0.000, 200.000, 1, 200.000, 0.000, 200.000, 1, '2026-04-17 12:58:23', '2026-04-17 14:06:46'),
(17, 8, 'UHT Steam', 'milliliter', 10000.000, 0.000, 250.000, 1, 250.000, 0.000, 250.000, 1, '2026-04-17 12:59:25', '2026-04-17 14:08:58'),
(18, 8, 'Chocolate Powder', 'gram', 2000.000, 0.000, 500.000, 1, 500.000, 0.000, 500.000, 1, '2026-04-17 13:01:40', '2026-04-17 14:04:19'),
(19, 8, 'Vanila Powder', 'gram', 1000.000, 0.000, 500.000, 1, 500.000, 0.000, 500.000, 1, '2026-04-17 13:03:04', '2026-04-17 14:09:07'),
(20, 8, 'Sirup', 'milliliter', 10000.000, 0.000, 500.000, 1, 500.000, 0.000, 500.000, 1, '2026-04-17 13:03:53', '2026-04-17 14:07:49'),
(21, 8, 'Chocomalt', 'gram', 3000.000, 0.000, 400.000, 1, 400.000, 0.000, 400.000, 1, '2026-04-17 13:04:10', '2026-04-17 14:04:34'),
(22, 8, 'Kukubima', 'pcs', 24.000, 0.000, 10.000, 1, 10.000, 0.000, 10.000, 1, '2026-04-17 13:05:01', '2026-04-17 14:07:04'),
(23, 8, 'Extrajos', 'pcs', 24.000, 0.000, 10.000, 1, 10.000, 0.000, 10.000, 1, '2026-04-17 13:05:17', '2026-04-17 14:05:11'),
(24, 8, 'Fre', 'milliliter', 5000.000, 0.000, 1000.000, 1, 1000.000, 0.000, 1000.000, 1, '2026-04-17 13:05:38', '2026-04-17 14:05:39'),
(25, 8, 'Yogurt Powder', 'gram', 1000.000, 0.000, 500.000, 1, 500.000, 0.000, 500.000, 1, '2026-04-17 13:06:18', '2026-04-17 14:09:17'),
(26, 8, 'Butterschoth Powder', 'gram', 5000.000, 0.000, 499.999, 1, 499.999, 0.000, 499.999, 1, '2026-04-17 13:07:02', '2026-04-17 14:03:57'),
(27, 8, 'Gula Aren', 'milliliter', 2000.000, 0.000, 500.000, 1, 500.000, 0.000, 500.000, 1, '2026-04-17 13:07:39', '2026-04-17 14:06:11'),
(28, 8, 'Teh', 'pcs', 24.000, 0.000, 10.000, 1, 10.000, 0.000, 10.000, 1, '2026-04-17 13:08:41', '2026-04-17 14:08:49'),
(29, 8, 'HOT Powder', 'gram', 4000.000, 0.000, 600.000, 1, 600.000, 0.000, 600.000, 1, '2026-04-17 13:09:03', '2026-04-17 14:06:24'),
(30, 8, 'ICE Powder', 'gram', 5000.000, 0.000, 449.999, 1, 449.999, 0.000, 449.999, 1, '2026-04-17 13:09:21', '2026-04-17 14:06:35'),
(31, 8, 'Biskuit', 'gram', 2000.000, 0.000, 500.000, 1, 500.000, 0.000, 500.000, 1, '2026-04-17 13:09:38', '2026-04-17 14:03:01'),
(32, 8, 'Air', 'milliliter', 38000.000, 0.000, 19000.000, 1, 19000.000, 0.000, 19000.000, 1, '2026-04-17 13:14:09', '2026-04-17 14:02:47'),
(33, 8, 'Es Batu', 'gram', 2000.000, 0.000, 6000.000, 1, 6000.000, 0.000, 6000.000, 1, '2026-04-17 13:14:48', '2026-04-17 14:04:51'),
(34, 8, 'Sprite', 'milliliter', 6000.000, 0.000, 1000.000, 1, 1000.000, 0.000, 1000.000, 1, '2026-04-17 13:25:18', '2026-04-17 14:08:16'),
(35, 8, 'SS', 'milliliter', 6500.000, 0.000, 500.000, 1, 500.000, 0.000, 500.000, 1, '2026-04-17 13:40:36', '2026-04-17 14:08:37'),
(36, 8, 'Powder', 'gram', 4500.000, 0.000, 600.000, 1, 600.000, 0.000, 600.000, 1, '2026-04-17 13:40:54', '2026-04-17 14:07:26');

-- --------------------------------------------------------

--
-- Table structure for table `ingredient_unit_conversions`
--

CREATE TABLE `ingredient_unit_conversions` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `unit_name` varchar(40) NOT NULL,
  `to_base_qty` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `store_id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sku` varchar(60) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `price` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `stock` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `discount_is_active` tinyint(1) NOT NULL DEFAULT 0,
  `discount_percent` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `store_id`, `category_id`, `sku`, `image_path`, `name`, `price`, `stock`, `is_active`, `discount_is_active`, `discount_percent`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '001', 'assets/product_presets/kotak.png', 'Roti Tawar', 10000, 0, 1, 0, 0, '2026-02-07 16:57:12', '2026-02-07 17:19:14'),
(2, 1, 1, '002', 'assets/product_presets/makanan.png', 'Nasi Goreng', 10000, 0, 1, 0, 0, '2026-02-07 17:16:36', '2026-02-07 19:56:55'),
(3, 1, 2, '003', 'assets/product_presets/air botol.png', 'air mineral', 5000, 2, 1, 0, 0, '2026-02-07 18:17:14', '2026-02-09 13:23:39'),
(4, 1, 1, '004', 'assets/product_presets/makanan.png', 'Nasi kebuli', 10000, 0, 1, 0, 0, '2026-02-08 00:52:30', NULL),
(5, 1, 2, '005', 'publik/assets/product_presets/kotak.png', 'Es Teh', 5000, 0, 1, 0, 0, '2026-02-08 00:53:12', '2026-04-17 04:36:36'),
(7, 3, 12, '001', 'assets/product_presets/lingkaran.png', 'Soto', 10000, 8, 1, 0, 0, '2026-02-10 17:35:17', '2026-02-10 19:31:31'),
(8, 3, 13, '002', 'assets/product_presets/air botol.png', 'Es Teh', 5000, 20, 1, 0, 0, '2026-02-10 17:35:39', '2026-02-10 17:36:09'),
(9, 8, 21, '001', 'publik/assets/product_presets/kotak.png', 'Kopi Tubruk', 5000, 0, 1, 0, 0, '2026-04-17 04:57:44', NULL),
(10, 8, 21, '002', 'publik/assets/product_presets/kotak.png', 'Kopi Tubruk Susu', 7000, 0, 1, 0, 0, '2026-04-17 04:59:43', NULL),
(11, 8, 21, '003', 'publik/assets/product_presets/kotak.png', 'Vietnam Drip', 10000, 0, 1, 0, 0, '2026-04-17 05:00:00', NULL),
(12, 8, 21, '004', 'publik/assets/product_presets/kotak.png', 'V60', 13000, 0, 1, 0, 0, '2026-04-17 05:00:19', NULL),
(13, 8, 21, '005', 'publik/assets/product_presets/kotak.png', 'Japanese', 13000, 0, 1, 0, 0, '2026-04-17 05:00:40', NULL),
(14, 8, 20, '006', 'publik/assets/product_presets/snack.png', 'Oreo', 14000, 0, 1, 0, 0, '2026-04-17 05:01:22', NULL),
(15, 8, 20, '007', 'publik/assets/product_presets/snack.png', 'Regal', 14000, 0, 1, 0, 0, '2026-04-17 05:01:39', NULL),
(16, 8, 20, '008', 'publik/assets/product_presets/snack.png', 'Ovaltime Choco', 14000, 0, 1, 0, 0, '2026-04-17 05:01:59', '2026-04-17 05:55:34'),
(17, 8, 22, '009', 'publik/assets/product_presets/segitiga.png', 'Espresso', 8000, 0, 1, 0, 0, '2026-04-17 05:02:38', NULL),
(18, 8, 22, '010', 'publik/assets/product_presets/segitiga.png', 'Americano', 10000, 0, 1, 0, 0, '2026-04-17 05:03:10', '2026-04-17 05:55:12'),
(19, 8, 22, '011', 'publik/assets/product_presets/segitiga.png', 'Cappucino', 12000, 0, 1, 0, 0, '2026-04-17 05:03:32', NULL),
(20, 8, 22, '012', 'publik/assets/product_presets/segitiga.png', 'Coffe Late', 12000, 0, 1, 0, 0, '2026-04-17 05:03:57', '2026-04-17 05:55:02'),
(21, 8, 22, '013', 'publik/assets/product_presets/segitiga.png', 'Mocacino', 13000, 0, 1, 0, 0, '2026-04-17 05:05:05', NULL),
(22, 8, 22, '014', 'publik/assets/product_presets/segitiga.png', 'Vanilla Latte', 13000, 0, 1, 0, 0, '2026-04-17 05:05:26', NULL),
(23, 8, 23, '015', 'publik/assets/product_presets/lingkaran.png', 'Melon', 10000, 0, 1, 0, 0, '2026-04-17 05:05:51', '2026-04-17 05:54:49'),
(24, 8, 23, '016', 'publik/assets/product_presets/lingkaran.png', 'Strawberry Squash', 10000, 0, 1, 0, 0, '2026-04-17 05:06:14', '2026-04-17 06:28:29'),
(25, 8, 23, '017', 'publik/assets/product_presets/lingkaran.png', 'Manggo Squash', 10000, 0, 1, 0, 0, '2026-04-17 05:06:28', '2026-04-17 06:26:40'),
(26, 8, 23, '018', 'publik/assets/product_presets/lingkaran.png', 'Blue Ocean', 10000, 0, 1, 0, 0, '2026-04-17 05:06:48', NULL),
(27, 8, 23, '019', 'publik/assets/product_presets/lingkaran.png', 'Lyhee', 10000, 0, 1, 0, 0, '2026-04-17 05:07:11', NULL),
(28, 8, 19, '020', 'upload/products/397737c0aa29e241.jpg', 'Matcha', 10000, 0, 1, 0, 0, '2026-04-17 05:10:36', NULL),
(29, 8, 19, '021', 'upload/products/2b2481b1212b7964.jpg', 'Red Velved', 10000, 0, 1, 0, 0, '2026-04-17 05:11:10', NULL),
(30, 8, 19, '022', 'upload/products/2e95ba390bf70ecd.jpg', 'Bublegum', 10000, 0, 1, 0, 0, '2026-04-17 05:11:54', NULL),
(31, 8, 19, '023', 'upload/products/149db6e26cb35c95.jpg', 'Taro', 10000, 0, 1, 0, 0, '2026-04-17 05:12:13', '2026-04-17 05:12:42'),
(32, 8, 19, '024', 'upload/products/6c8be2c69c016a63.jpg', 'Vanila', 10000, 0, 1, 0, 0, '2026-04-17 05:12:34', '2026-04-17 05:12:59'),
(33, 8, 19, '025', 'upload/products/7e929fc923b8dc0a.jpg', 'Strawberry', 10000, 0, 1, 0, 0, '2026-04-17 05:13:24', NULL),
(34, 8, 19, '026', 'upload/products/663a2e7263abd7b6.jpg', 'Manggo', 10000, 0, 1, 0, 0, '2026-04-17 05:13:41', NULL),
(35, 8, 19, '027', 'upload/products/9bfb9b9b7f20edb3.jpg', 'Papermint', 10000, 0, 1, 0, 0, '2026-04-17 05:13:58', '2026-04-17 05:54:11'),
(36, 8, 19, '028', 'upload/products/494de3967757a80e.jpg', 'Pink Lava', 10000, 0, 1, 0, 0, '2026-04-17 05:14:16', NULL),
(37, 8, 19, '029', 'upload/products/e249f76ccee20e44.jpg', 'Cheese', 10000, 0, 1, 0, 0, '2026-04-17 05:14:39', NULL),
(38, 8, 19, '030', 'upload/products/81802239a79269c0.jpg', 'Thaitea', 10000, 0, 1, 0, 0, '2026-04-17 05:15:02', NULL),
(39, 8, 17, '031', 'upload/products/318e0fd375f349b5.png', 'Butterschoth Brown Sugar', 14000, 0, 1, 0, 0, '2026-04-17 05:24:30', '2026-04-17 05:54:23'),
(40, 8, 17, '032', 'upload/products/9054bee75ab801a6.png', 'Bombon Coffe', 13000, 0, 1, 0, 0, '2026-04-17 05:25:02', NULL),
(41, 8, 17, '033', 'upload/products/54adc5f339241516.png', 'Ice Coffe Brown Sugar', 12000, 0, 1, 0, 0, '2026-04-17 05:25:30', '2026-04-17 05:53:58'),
(42, 8, 18, '034', 'upload/products/24b72f7333db5d96.png', 'Original Tea', 5000, 0, 1, 0, 0, '2026-04-17 05:29:35', NULL),
(43, 8, 18, '035', 'upload/products/06a9415de0ba9931.png', 'Milk Tea', 8000, 0, 1, 0, 0, '2026-04-17 05:33:30', NULL),
(44, 8, 18, '036', 'upload/products/0326067966d3b51c.png', 'Lemon Tea', 8000, 0, 1, 0, 0, '2026-04-17 05:34:14', '2026-04-17 05:53:45'),
(45, 8, 18, '037', 'upload/products/48da31f4ec50318b.png', 'Lychee Tea', 8000, 0, 1, 0, 0, '2026-04-17 05:34:38', NULL),
(46, 8, 18, '038', 'upload/products/70f0fabc04ded63f.png', 'Peach Tea', 8000, 0, 1, 0, 0, '2026-04-17 05:35:06', NULL),
(47, 8, 18, '039', 'upload/products/2fb7e976799efbd6.png', 'Apple Tea', 8000, 0, 1, 0, 0, '2026-04-17 05:35:27', NULL),
(48, 8, 18, '040', 'upload/products/fe197d03e979d8b3.png', 'Blackcurrent Tea', 8000, 0, 1, 0, 0, '2026-04-17 05:36:05', '2026-04-17 05:48:40'),
(49, 8, 25, '041', 'upload/products/2bd4e4ee9cc3f699.png', 'Mangga', 10000, 0, 1, 0, 0, '2026-04-17 05:47:58', NULL),
(50, 8, 25, '042', 'upload/products/297a3895cbeeab6d.png', 'Blueberry', 10000, 0, 1, 0, 0, '2026-04-17 05:48:27', '2026-04-17 05:53:32'),
(51, 8, 25, '043', 'upload/products/8849387ab1f5c40b.png', 'Anggur', 10000, 0, 1, 0, 0, '2026-04-17 05:48:56', NULL),
(52, 8, 24, '044', 'upload/products/f5ffb0493e5e0217.png', 'Susu', 5000, 0, 1, 0, 0, '2026-04-17 05:51:04', NULL),
(53, 8, 24, '045', 'upload/products/a5c4140521e44df3.png', 'Extrajos', 5000, 0, 1, 0, 0, '2026-04-17 05:51:29', NULL),
(54, 8, 24, '046', 'upload/products/ae1f59be18324608.png', 'Kukubima', 5000, 0, 1, 0, 0, '2026-04-17 05:51:49', NULL),
(55, 8, 24, '047', 'upload/products/807768ec0852ad92.png', 'Josua', 8000, 0, 1, 0, 0, '2026-04-17 05:52:10', NULL),
(56, 8, 24, '048', 'upload/products/2206db5feee12aba.png', 'Kubisu', 8000, 0, 1, 0, 0, '2026-04-17 05:52:30', NULL),
(57, 8, 24, '049', 'upload/products/55bd6841605ec9fb.png', 'Milo', 8000, 0, 1, 0, 0, '2026-04-17 05:52:47', NULL),
(58, 8, 24, '050', 'upload/products/81e74eb19e18d999.png', 'Fresh Milk', 10000, 0, 1, 0, 0, '2026-04-17 05:53:08', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_packages`
--

CREATE TABLE `product_packages` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_packages`
--

INSERT INTO `product_packages` (`id`, `store_id`, `name`, `price`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Nasi Goreng Ceplok', 15000.00, 0, '2026-02-08 07:06:39', '2026-02-10 13:13:46'),
(5, 1, 'Makanan', 1000.00, 1, '2026-02-10 13:49:22', '2026-02-10 13:49:24');

-- --------------------------------------------------------

--
-- Table structure for table `product_package_items`
--

CREATE TABLE `product_package_items` (
  `id` int(11) NOT NULL,
  `package_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_package_items`
--

INSERT INTO `product_package_items` (`id`, `package_id`, `product_id`, `qty`, `created_at`) VALUES
(1, 1, 2, 1, '2026-02-08 07:06:51');

-- --------------------------------------------------------

--
-- Table structure for table `product_recipes`
--

CREATE TABLE `product_recipes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `store_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `qty_per_unit` decimal(12,3) NOT NULL DEFAULT 0.000,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `store_id` bigint(20) UNSIGNED NOT NULL,
  `kasir_id` bigint(20) UNSIGNED NOT NULL,
  `shift_id` bigint(20) UNSIGNED DEFAULT NULL,
  `invoice_no` varchar(40) DEFAULT NULL,
  `order_note` varchar(255) NOT NULL DEFAULT '',
  `total` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `paid` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `change_amount` int(11) NOT NULL DEFAULT 0,
  `payment_method` varchar(20) NOT NULL DEFAULT 'cash',
  `kitchen_done` tinyint(1) NOT NULL DEFAULT 0,
  `kitchen_done_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `store_id`, `kasir_id`, `shift_id`, `invoice_no`, `order_note`, `total`, `paid`, `change_amount`, `payment_method`, `kitchen_done`, `kitchen_done_at`, `created_at`) VALUES
(1, 1, 3, 4, 'INV20260207-000001', '', 20000, 20000, 0, 'cash', 1, '2026-02-08 02:58:11', '2026-02-07 19:56:55'),
(2, 1, 3, 4, 'INV20260208-000002', '', 5000, 5000, 0, 'qris', 0, NULL, '2026-02-08 05:20:18'),
(3, 1, 3, 4, 'INV20260208-000003', '', 5000, 5000, 0, 'qris', 0, NULL, '2026-02-08 06:36:24'),
(4, 1, 3, 5, 'INV20260209-000004', '', 5000, 5000, 0, 'qris', 0, NULL, '2026-02-09 05:29:49'),
(5, 1, 3, 6, 'INV20260209-000005', '', 5000, 5000, 0, 'qris', 0, NULL, '2026-02-09 09:41:55'),
(6, 1, 3, 6, 'INV20260209-000006', '', 5000, 5000, 0, 'qris', 1, '2026-02-09 22:33:43', '2026-02-09 13:03:39'),
(7, 1, 3, 6, 'INV20260209-000007', '', 5000, 10000, 5000, 'cash', 1, '2026-02-09 22:33:43', '2026-02-09 13:03:58'),
(8, 1, 3, 6, 'INV20260209-000008', '', 5000, 10000, 5000, 'cash', 1, '2026-02-09 22:33:42', '2026-02-09 13:12:35'),
(9, 1, 3, 6, 'INV20260209-000009', '', 5000, 10000, 5000, 'cash', 1, '2026-02-09 22:33:41', '2026-02-09 13:23:39'),
(10, 3, 15, 7, 'INV20260210-000001', '', 10000, 10000, 0, 'qris', 0, NULL, '2026-02-10 17:41:49'),
(11, 3, 15, 7, 'INV20260210-000002', '', 10000, 10000, 0, 'cash', 0, NULL, '2026-02-10 19:31:31');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sale_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `sku` varchar(60) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `price` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `discount_percent` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `discount_amount` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `qty` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `subtotal` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `sku`, `name`, `price`, `discount_percent`, `discount_amount`, `qty`, `subtotal`) VALUES
(1, 1, 2, '002', 'Nasi Goreng', 10000, 0, 0, 2, 20000),
(2, 2, 3, '003', 'air mineral', 5000, 0, 0, 1, 5000),
(3, 3, 3, '003', 'air mineral', 5000, 0, 0, 1, 5000),
(4, 4, 3, '003', 'air mineral', 5000, 0, 0, 1, 5000),
(5, 5, 3, '003', 'air mineral', 5000, 0, 0, 1, 5000),
(6, 6, 3, '003', 'air mineral', 5000, 0, 0, 1, 5000),
(7, 7, 3, '003', 'air mineral', 5000, 0, 0, 1, 5000),
(8, 8, 3, '003', 'air mineral', 5000, 0, 0, 1, 5000),
(9, 9, 3, '003', 'air mineral', 5000, 0, 0, 1, 5000),
(10, 10, 7, NULL, 'Soto', 10000, 0, 0, 1, 10000),
(11, 11, 7, NULL, 'Soto', 10000, 0, 0, 1, 10000);

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `target_type` enum('product','ingredient') NOT NULL,
  `target_id` int(11) NOT NULL,
  `direction` enum('in','out') NOT NULL,
  `qty` decimal(12,3) NOT NULL DEFAULT 0.000,
  `unit` varchar(20) NOT NULL DEFAULT 'pcs',
  `note` varchar(255) NOT NULL DEFAULT '',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `store_id`, `target_type`, `target_id`, `direction`, `qty`, `unit`, `note`, `created_by`, `created_at`) VALUES
(1, 1, 'product', 3, 'in', 10.000, 'pcs', 'tambah stok', 2, '2026-02-08 08:04:26'),
(2, 3, 'product', 8, 'in', 20.000, 'pcs', '', 8, '2026-02-11 00:36:09'),
(3, 3, 'product', 7, 'in', 10.000, 'pcs', '', 8, '2026-02-11 00:36:17'),
(4, 3, 'product', 7, 'out', 1.000, 'pcs', 'Sale INV20260210-000001 (#10) - Soto', 15, '2026-02-11 00:41:49'),
(5, 3, 'product', 7, 'out', 1.000, 'pcs', 'Sale INV20260210-000002 (#11) - Soto', 15, '2026-02-11 02:31:31'),
(6, 1, 'ingredient', 3, 'in', 1000.000, 'gram', 'restok', 2, '2026-03-11 02:06:09'),
(7, 1, 'ingredient', 4, 'in', 2000.000, 'gram', 'restok', 2, '2026-03-11 02:06:22'),
(8, 1, 'ingredient', 2, 'in', 1500.000, 'gram', 'restok', 2, '2026-03-11 02:06:34'),
(9, 1, 'ingredient', 8, 'in', 100.000, 'gram', 'restok', 2, '2026-03-11 02:06:43'),
(10, 1, 'ingredient', 7, 'in', 3000.000, 'gram', 'restok', 2, '2026-03-11 02:06:54'),
(11, 1, 'ingredient', 5, 'in', 10.000, 'pcs', 'restok', 2, '2026-03-11 02:07:03'),
(12, 1, 'ingredient', 6, 'in', 45.000, 'pcs', 'restok', 2, '2026-03-11 02:07:13'),
(13, 8, 'ingredient', 32, 'in', 38000.000, 'milliliter', 'restok', 16, '2026-04-17 14:02:47'),
(14, 8, 'ingredient', 31, 'in', 2000.000, 'gram', 'restok', 16, '2026-04-17 14:03:01'),
(15, 8, 'ingredient', 26, 'in', 5000.000, 'gram', 'restok', 16, '2026-04-17 14:03:57'),
(16, 8, 'ingredient', 18, 'in', 2000.000, 'gram', 'restok', 16, '2026-04-17 14:04:19'),
(17, 8, 'ingredient', 21, 'in', 3000.000, 'gram', 'restok', 16, '2026-04-17 14:04:34'),
(18, 8, 'ingredient', 33, 'in', 2000.000, 'gram', 'restok', 16, '2026-04-17 14:04:51'),
(19, 8, 'ingredient', 23, 'in', 24.000, 'pcs', 'restok', 16, '2026-04-17 14:05:11'),
(20, 8, 'ingredient', 24, 'in', 5000.000, 'milliliter', 'restok', 16, '2026-04-17 14:05:39'),
(21, 8, 'ingredient', 14, 'in', 1000.000, 'gram', 'restok', 16, '2026-04-17 14:06:00'),
(22, 8, 'ingredient', 27, 'in', 2000.000, 'milliliter', 'restok', 16, '2026-04-17 14:06:11'),
(23, 8, 'ingredient', 29, 'in', 4000.000, 'gram', 'restok', 16, '2026-04-17 14:06:24'),
(24, 8, 'ingredient', 30, 'in', 5000.000, 'gram', 'restok', 16, '2026-04-17 14:06:35'),
(25, 8, 'ingredient', 16, 'in', 5000.000, 'gram', 'restok', 16, '2026-04-17 14:06:46'),
(26, 8, 'ingredient', 13, 'in', 3000.000, 'gram', 'restok', 16, '2026-04-17 14:06:58'),
(27, 8, 'ingredient', 22, 'in', 24.000, 'pcs', '', 16, '2026-04-17 14:07:04'),
(28, 8, 'ingredient', 36, 'in', 4500.000, 'gram', 'restok', 16, '2026-04-17 14:07:26'),
(29, 8, 'ingredient', 20, 'in', 10000.000, 'milliliter', 'restok', 16, '2026-04-17 14:07:49'),
(30, 8, 'ingredient', 15, 'in', 5000.000, 'gram', 'restok', 16, '2026-04-17 14:08:01'),
(31, 8, 'ingredient', 34, 'in', 6000.000, 'milliliter', 'restok', 16, '2026-04-17 14:08:16'),
(32, 8, 'ingredient', 35, 'in', 6500.000, 'milliliter', 'restok', 16, '2026-04-17 14:08:37'),
(33, 8, 'ingredient', 28, 'in', 24.000, 'pcs', 'restok', 16, '2026-04-17 14:08:49'),
(34, 8, 'ingredient', 17, 'in', 10000.000, 'milliliter', 'restok', 16, '2026-04-17 14:08:58'),
(35, 8, 'ingredient', 19, 'in', 1000.000, 'gram', 'restok', 16, '2026-04-17 14:09:07'),
(36, 8, 'ingredient', 25, 'in', 1000.000, 'gram', 'restok', 16, '2026-04-17 14:09:17');

-- --------------------------------------------------------

--
-- Table structure for table `stock_opnames`
--

CREATE TABLE `stock_opnames` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `store_id` bigint(20) UNSIGNED NOT NULL,
  `admin_id` bigint(20) UNSIGNED NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_opname_items`
--

CREATE TABLE `stock_opname_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `opname_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `sku` varchar(60) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `stock_before` int(11) NOT NULL DEFAULT 0,
  `stock_after` int(11) NOT NULL DEFAULT 0,
  `diff` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stores`
--

CREATE TABLE `stores` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `owner_admin_id` bigint(20) UNSIGNED NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `store_type` enum('basic','bom') NOT NULL DEFAULT 'bom',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `receipt_header` varchar(255) DEFAULT NULL,
  `receipt_footer` varchar(255) DEFAULT NULL,
  `receipt_show_logo` tinyint(1) DEFAULT 1,
  `receipt_auto_print` tinyint(1) DEFAULT 1,
  `receipt_paper` enum('58mm','80mm') DEFAULT '80mm',
  `receipt_logo_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stores`
--

INSERT INTO `stores` (`id`, `name`, `address`, `phone`, `owner_admin_id`, `is_active`, `store_type`, `created_at`, `receipt_header`, `receipt_footer`, `receipt_show_logo`, `receipt_auto_print`, `receipt_paper`, `receipt_logo_path`) VALUES
(1, 'Toko Kita', NULL, NULL, 2, 1, 'bom', '2026-02-07 16:44:28', NULL, 'Terimakasih atas kunjungannya', 1, 1, '80mm', NULL),
(3, 'percobaan basic', NULL, NULL, 8, 1, 'basic', '2026-02-10 08:56:21', NULL, NULL, 1, 1, '80mm', NULL),
(8, 'Haifa Chiken', NULL, NULL, 16, 1, 'bom', '2026-04-17 04:45:39', NULL, NULL, 1, 1, '80mm', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `store_media`
--

CREATE TABLE `store_media` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `media_type` varchar(30) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `store_media`
--

INSERT INTO `store_media` (`id`, `store_id`, `media_type`, `file_path`, `created_at`, `updated_at`) VALUES
(1, 8, 'logo', '/upload/store_logos/store_8_6353c940eeed4b1d7a94.png', '2026-04-17 14:01:11', '2026-04-17 14:01:23');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `phone` varchar(32) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `store_id`, `name`, `phone`, `address`, `notes`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Leonardo Saputra', '6281229943647', 'Kamaltalon, Kamal, Kec. Kamal, Kabupaten Bangkalan, Jawa Timur', 'Toko Sembako Kamal', 1, '2026-02-10 02:44:12', NULL),
(2, 8, 'Toko Mujur', '628523288804', 'jl mangga 2 no, 19, RT.01/RW.04, Perumahan Kamal, Banyu Ajuh, Kec. Kamal, Kabupaten Bangkalan, Jawa Timur 69162', '10:00-22:00', 1, '2026-04-17 13:12:06', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('developer','admin','kasir','dapur') NOT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `password_reset_at` datetime DEFAULT NULL,
  `password_reset_by` int(11) DEFAULT NULL,
  `last_password_changed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `created_by`, `is_active`, `must_change_password`, `created_at`, `updated_at`, `password_reset_at`, `password_reset_by`, `last_password_changed_at`) VALUES
(1, 'Developer MultiPOS', 'developer@multipos.com', '$2y$10$IPZH/5LR5SjPrg6OSE0M6ukJbrjz3VfEcAA03mlobowmlHMc.30MO', 'developer', NULL, 1, 0, '2026-02-07 16:32:03', '2026-02-09 04:22:56', NULL, NULL, NULL),
(2, 'Leonardo', 'leonardosaputra666@gmail.com', '$2y$10$g6KkO2Pk8vc9fHuGfU2tbO.njW5As4dcnkM7Q.D5/be7xDwUg3uPS', 'admin', NULL, 1, 1, '2026-02-07 16:44:28', '2026-04-17 08:42:30', '2026-04-17 15:42:30', 1, NULL),
(3, 'Kasir toko kita', 'kasir@gmail.com', '$2y$10$lJdNJs6ffyypTnwR5bRhgu6IuoOQBrFsvFqheDvVv3PR51qycYCxG', 'kasir', 2, 1, 0, '2026-02-07 17:22:56', NULL, NULL, NULL, NULL),
(4, 'Leon', 'dapur@gmai.com', '$2y$10$pPlEZyB.cXdGHU4w/LcJiOHwapwnPA7sTTGAJlw2JueOt.RA2UzVK', 'dapur', 2, 1, 1, '2026-02-07 19:24:11', '2026-02-07 19:26:48', NULL, NULL, NULL),
(8, 'testing mode basic', 'basic@gmail.com', '$2y$10$jUMmvo7lumR.wqyLysX68OPEPXUKOJIECJshtTV83KNKzqAJkDsXW', 'admin', NULL, 1, 0, '2026-02-10 08:56:21', NULL, NULL, NULL, NULL),
(15, 'basicmode', 'abc@gmail.com', '$2y$10$GIKed3sCHljoWbpMbknWZeW5KocMhmEjCWhQXdwSHpDORh8NuW5pa', 'kasir', 8, 1, 1, '2026-02-10 17:37:39', NULL, NULL, NULL, NULL),
(16, 'Haifa Chiken', 'HaifaChiken@gmail.com', '$2y$10$PYlDQTT/lvcN3JgvivFDrewscLbtmnyKxeXQVfCZeTt8mm1olAqp6', 'admin', NULL, 1, 0, '2026-04-17 04:45:39', NULL, NULL, NULL, NULL),
(17, 'Kasir Haifa', 'KasirHaifaChiken@gmail.com', '$2y$10$0Fmju9h1r27uj45GGhsh8e0mPvoMNeLMPujtvUqEUu6Xc0YCEF/IG', 'kasir', 16, 1, 0, '2026-04-17 04:46:21', NULL, NULL, NULL, NULL),
(18, 'Dapur Haifa', 'DapurHaifaChiken@gmail.com', '$2y$10$BJA4ZuCIt80O8u2wNJd/tu8mwLNr/3HxF0OGt0l8r./opbtBiHxbm', 'dapur', 16, 1, 0, '2026-04-17 04:46:39', NULL, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_store_time` (`store_id`,`created_at`),
  ADD KEY `idx_actor_time` (`actor_user_id`,`created_at`),
  ADD KEY `idx_action_time` (`action`,`created_at`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `bom_recipes`
--
ALTER TABLE `bom_recipes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_store_product` (`store_id`,`product_id`),
  ADD KEY `idx_store` (`store_id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Indexes for table `bom_recipe_items`
--
ALTER TABLE `bom_recipe_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_recipe_ingredient` (`recipe_id`,`ingredient_id`),
  ADD KEY `idx_recipe` (`recipe_id`),
  ADD KEY `idx_ing` (`ingredient_id`);

--
-- Indexes for table `cashier_shifts`
--
ALTER TABLE `cashier_shifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shift_store` (`store_id`),
  ADD KEY `idx_shift_kasir` (`kasir_id`),
  ADD KEY `idx_shift_status` (`status`);

--
-- Indexes for table `cash_movements`
--
ALTER TABLE `cash_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_store_date` (`store_id`,`created_at`),
  ADD KEY `idx_shift` (`shift_id`),
  ADD KEY `idx_kasir` (`kasir_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cat_store_name` (`store_id`,`name`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customers_store` (`store_id`),
  ADD KEY `idx_customers_phone` (`store_id`,`phone`),
  ADD KEY `idx_customers_name` (`store_id`,`name`);

--
-- Indexes for table `ingredients`
--
ALTER TABLE `ingredients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_store_name` (`store_id`,`name`),
  ADD KEY `idx_store` (`store_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `ingredient_unit_conversions`
--
ALTER TABLE `ingredient_unit_conversions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_ing_unit` (`ingredient_id`,`unit_name`),
  ADD KEY `idx_store` (`store_id`),
  ADD KEY `idx_ing` (`ingredient_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_prod_store_sku` (`store_id`,`sku`),
  ADD KEY `idx_prod_store` (`store_id`),
  ADD KEY `idx_prod_cat` (`category_id`);

--
-- Indexes for table `product_packages`
--
ALTER TABLE `product_packages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_store` (`store_id`);

--
-- Indexes for table `product_package_items`
--
ALTER TABLE `product_package_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_pkg_product` (`package_id`,`product_id`),
  ADD KEY `idx_pkg` (`package_id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Indexes for table `product_recipes`
--
ALTER TABLE `product_recipes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_store_product_ing` (`store_id`,`product_id`,`ingredient_id`),
  ADD KEY `idx_store_product` (`store_id`,`product_id`),
  ADD KEY `idx_store_ing` (`store_id`,`ingredient_id`),
  ADD KEY `fk_recipe_product` (`product_id`),
  ADD KEY `fk_recipe_ing` (`ingredient_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sales_store` (`store_id`),
  ADD KEY `idx_sales_kasir` (`kasir_id`),
  ADD KEY `idx_sales_created` (`created_at`),
  ADD KEY `idx_sales_shift` (`shift_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_items_sale` (`sale_id`),
  ADD KEY `idx_items_product` (`product_id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_store` (`store_id`),
  ADD KEY `idx_target` (`target_type`,`target_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `stock_opnames`
--
ALTER TABLE `stock_opnames`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_op_store` (`store_id`),
  ADD KEY `idx_op_created` (`created_at`);

--
-- Indexes for table `stock_opname_items`
--
ALTER TABLE `stock_opname_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_opi_opname` (`opname_id`),
  ADD KEY `idx_opi_product` (`product_id`);

--
-- Indexes for table `stores`
--
ALTER TABLE `stores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_store_admin` (`owner_admin_id`);

--
-- Indexes for table `store_media`
--
ALTER TABLE `store_media`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_store_type` (`store_id`,`media_type`),
  ADD KEY `idx_store` (`store_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_store` (`store_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_created_by` (`created_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `bom_recipes`
--
ALTER TABLE `bom_recipes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `bom_recipe_items`
--
ALTER TABLE `bom_recipe_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=144;

--
-- AUTO_INCREMENT for table `cashier_shifts`
--
ALTER TABLE `cashier_shifts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `cash_movements`
--
ALTER TABLE `cash_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ingredients`
--
ALTER TABLE `ingredients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `ingredient_unit_conversions`
--
ALTER TABLE `ingredient_unit_conversions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `product_packages`
--
ALTER TABLE `product_packages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `product_package_items`
--
ALTER TABLE `product_package_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `product_recipes`
--
ALTER TABLE `product_recipes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `stock_opnames`
--
ALTER TABLE `stock_opnames`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_opname_items`
--
ALTER TABLE `stock_opname_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stores`
--
ALTER TABLE `stores`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `store_media`
--
ALTER TABLE `store_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bom_recipe_items`
--
ALTER TABLE `bom_recipe_items`
  ADD CONSTRAINT `fk_bom_recipe` FOREIGN KEY (`recipe_id`) REFERENCES `bom_recipes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cash_movements`
--
ALTER TABLE `cash_movements`
  ADD CONSTRAINT `fk_cash_kasir` FOREIGN KEY (`kasir_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cash_shift` FOREIGN KEY (`shift_id`) REFERENCES `cashier_shifts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cash_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_recipes`
--
ALTER TABLE `product_recipes`
  ADD CONSTRAINT `fk_recipe_ing` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_recipe_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `fk_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_opname_items`
--
ALTER TABLE `stock_opname_items`
  ADD CONSTRAINT `fk_opi_opname` FOREIGN KEY (`opname_id`) REFERENCES `stock_opnames` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stores`
--
ALTER TABLE `stores`
  ADD CONSTRAINT `fk_store_admin` FOREIGN KEY (`owner_admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
