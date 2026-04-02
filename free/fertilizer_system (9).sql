-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 29, 2025 at 06:24 AM
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
-- Database: `fertilizer_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `type` enum('info','warning','success','danger') DEFAULT 'info',
  `target_role` enum('all','admin','supplier','driver') DEFAULT 'all',
  `created_by` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `document_path` varchar(255) NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `id` int(11) NOT NULL,
  `certificate_number` varchar(20) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `document_path` text DEFAULT NULL,
  `qr_code_path` text DEFAULT NULL,
  `status` enum('Pending','Approved','Revoked','Expired') DEFAULT 'Pending',
  `issued_on` date DEFAULT NULL,
  `expires_on` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `application_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `certificates`
--

INSERT INTO `certificates` (`id`, `certificate_number`, `supplier_id`, `document_path`, `qr_code_path`, `status`, `issued_on`, `expires_on`, `created_at`, `application_id`) VALUES
(1, 'CERT-2025-00001', 1, '../uploads/applications/app_1_1763876881.pdf', '../uploads/qrcodes/cert_1.png', 'Approved', '2025-11-23', '2027-11-23', '2025-11-23 06:07:33', 2),
(2, 'CERT-2025-00002', 1, '../uploads/applications/app_1_1764073401.png', '../uploads/qrcodes/cert_2.png', 'Approved', '2025-11-25', '2027-11-25', '2025-11-25 12:23:47', 3),
(3, 'CERT-2025-00003', 1, '../uploads/applications/app_1_1764164765.jpg', '../uploads/qrcodes/cert_3.png', 'Approved', '2025-11-26', '2028-11-26', '2025-11-26 13:46:44', 4),
(4, 'CERT-2025-00004', 1, '../uploads/applications/app_1_1764392664.jpg', '../uploads/qrcodes/cert_4.png', 'Approved', '2025-11-29', '2027-11-29', '2025-11-29 05:05:14', 5);

-- --------------------------------------------------------

--
-- Table structure for table `certificate_applications`
--

CREATE TABLE `certificate_applications` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `qr_link_id` int(11) DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificate_applications`
--

INSERT INTO `certificate_applications` (`id`, `supplier_id`, `qr_link_id`, `document_path`, `details`, `status`, `submitted_at`, `reviewed_by`, `reviewed_at`, `review_notes`) VALUES
(2, 1, NULL, '../uploads/applications/app_1_1763876881.pdf', '{\"business_name\":\"Supplier one\",\"business_reg_no\":\"BRN999292\",\"business_address\":\"BLANYRE\",\"contact_phone\":\"0299293838\",\"fertilizer_types\":\"UREQS\"}', 'Approved', '2025-11-23 05:48:01', 2, '2025-11-23 06:07:33', ''),
(3, 1, NULL, '../uploads/applications/app_1_1764073401.png', '{\"business_name\":\"Supplier one\",\"business_reg_no\":\"HDHDHDH\",\"business_address\":\"XHHHDHD\",\"contact_phone\":\"020299292929\",\"fertilizer_types\":\"HDDGGD\"}', 'Approved', '2025-11-25 12:23:21', 2, '2025-11-25 12:23:48', ''),
(4, 1, NULL, '../uploads/applications/app_1_1764164765.jpg', '{\"business_name\":\"Supplier one\",\"business_reg_no\":\"HDFHFH\",\"business_address\":\"HDHDH\",\"contact_phone\":\"2645\",\"fertilizer_types\":\"HFHFHF\"}', 'Approved', '2025-11-26 13:46:05', 2, '2025-11-26 13:46:45', ''),
(5, 1, NULL, '../uploads/applications/app_1_1764392664.jpg', '{\"business_name\":\"Supplier one\",\"business_reg_no\":\"SJJSJS9999\",\"business_address\":\"DHHDHDHD\",\"contact_phone\":\"29299292929\",\"fertilizer_types\":\"SHSHSHSH\"}', 'Approved', '2025-11-29 05:04:24', 2, '2025-11-29 05:05:14', '');

-- --------------------------------------------------------

--
-- Table structure for table `deliveries`
--

CREATE TABLE `deliveries` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `current_latitude` decimal(10,8) DEFAULT NULL,
  `current_longitude` decimal(11,8) DEFAULT NULL,
  `status` enum('Pending','In Transit','Delivered') NOT NULL DEFAULT 'Pending',
  `expected_arrival` datetime DEFAULT NULL,
  `delivered_on` datetime DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `deliveries`
--

INSERT INTO `deliveries` (`id`, `order_id`, `admin_id`, `supplier_id`, `driver_id`, `current_latitude`, `current_longitude`, `status`, `expected_arrival`, `delivered_on`, `last_updated`) VALUES
(1, 1, 2, 1, 3, NULL, NULL, 'Delivered', '2025-11-23 12:15:00', '2025-11-23 11:16:47', '2025-11-23 10:16:47'),
(2, 4, 2, 1, 3, -13.97140000, 33.79200000, 'In Transit', '2025-11-28 13:35:00', NULL, '2025-11-28 20:24:36'),
(3, 5, 2, 1, 3, NULL, NULL, 'In Transit', '2025-11-30 15:48:00', NULL, '2025-11-26 13:56:24'),
(4, 3, 2, 1, 3, NULL, NULL, 'In Transit', '2025-11-30 15:49:00', NULL, '2025-11-26 13:56:27'),
(5, 6, 2, 1, 3, NULL, NULL, 'Delivered', '2025-11-30 15:55:00', '2025-11-26 16:02:35', '2025-11-26 14:02:35'),
(6, 7, 2, 1, 3, NULL, NULL, 'In Transit', '2025-12-26 22:22:00', NULL, '2025-11-28 20:24:32'),
(7, 9, 2, 1, 3, NULL, NULL, 'Pending', '2025-11-29 07:02:00', NULL, '2025-11-29 05:02:57');

-- --------------------------------------------------------

--
-- Table structure for table `driver_locations`
--

CREATE TABLE `driver_locations` (
  `id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `address` text DEFAULT NULL,
  `location_type` enum('current','home','depot') DEFAULT 'current',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `driver_locations`
--

INSERT INTO `driver_locations` (`id`, `driver_id`, `latitude`, `longitude`, `address`, `location_type`, `updated_at`) VALUES
(1, 3, -15.82121300, 35.06425500, '', 'current', '2025-11-28 20:37:39');

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` int(11) NOT NULL,
  `question` varchar(500) NOT NULL,
  `answer` text NOT NULL,
  `category` varchar(100) NOT NULL,
  `target_role` enum('all','admin','supplier','driver') DEFAULT 'all',
  `helpful_links` text DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fertilizers`
--

CREATE TABLE `fertilizers` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `price_per_unit` decimal(10,2) NOT NULL,
  `type` varchar(100) DEFAULT NULL,
  `npk_value` varchar(50) DEFAULT NULL,
  `batch_no` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `stock_remaining` int(11) DEFAULT 0,
  `minimum_stock` int(11) DEFAULT 0,
  `certified` tinyint(1) DEFAULT 1,
  `depot_location` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `fertilizers`
--

INSERT INTO `fertilizers` (`id`, `admin_id`, `name`, `price_per_unit`, `type`, `npk_value`, `batch_no`, `expiry_date`, `stock`, `stock_remaining`, `minimum_stock`, `certified`, `depot_location`, `price`) VALUES
(1, 2, 'NSSSS', 2000.00, 'Phosphorus', '377373', 'YYYY3', '2026-02-25', 22, 15, 12, 1, 'DHHDHD', 2000.00),
(2, 2, 'DGDGDG', 1900.00, 'Nitrogen', 'E536', 'DGDGDG', '2025-11-30', 199, 183, 14, 1, 'FGGFG', 1900.00);

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `affected_record_id` int(11) DEFAULT NULL,
  `affected_table` varchar(50) DEFAULT NULL,
  `before_state` text DEFAULT NULL,
  `after_state` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `logs`
--

INSERT INTO `logs` (`id`, `user_id`, `action`, `ip_address`, `affected_record_id`, `affected_table`, `before_state`, `after_state`, `timestamp`) VALUES
(1, 2, 'New user registered', '::1', NULL, NULL, NULL, NULL, '2025-11-23 04:04:41'),
(2, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 04:05:06'),
(3, 2, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 04:07:20'),
(4, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 04:08:35'),
(5, 2, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 05:29:24'),
(6, 1, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 05:29:43'),
(7, 1, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 05:31:16'),
(8, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 05:31:44'),
(9, 2, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 05:42:34'),
(10, 1, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 05:42:49'),
(11, 1, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 06:06:22'),
(12, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 06:07:17'),
(13, 2, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 06:08:11'),
(14, 1, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 06:08:30'),
(15, 1, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 08:04:36'),
(16, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 08:04:58'),
(17, 2, 'Updated order status', NULL, 2, 'orders', NULL, NULL, '2025-11-23 08:05:17'),
(18, 2, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 09:25:21'),
(19, 1, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 09:25:33'),
(20, 1, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 09:25:54'),
(21, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 09:26:27'),
(22, 2, 'Updated order status', NULL, 2, 'orders', NULL, NULL, '2025-11-23 09:26:38'),
(23, 1, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 09:27:13'),
(24, 1, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 09:28:50'),
(25, 3, 'New user registered', '::1', NULL, NULL, NULL, NULL, '2025-11-23 09:29:35'),
(26, 3, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 09:29:54'),
(27, 3, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 09:30:27'),
(28, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 09:30:40'),
(29, 2, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 10:09:06'),
(30, 1, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 10:09:21'),
(31, 2, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 10:17:04'),
(32, 3, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 10:17:20'),
(33, 3, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 10:30:06'),
(34, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 10:33:14'),
(35, 2, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 11:15:12'),
(36, 1, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 11:15:28'),
(37, 1, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 11:33:17'),
(38, 1, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 11:33:36'),
(39, 1, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 11:34:38'),
(40, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 11:35:23'),
(41, 1, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 11:41:55'),
(42, 3, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 11:42:21'),
(43, 2, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 14:40:33'),
(44, 1, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 14:40:47'),
(45, 1, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 15:37:08'),
(46, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 15:37:22'),
(47, 2, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 15:59:05'),
(48, 1, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 15:59:39'),
(49, 1, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 16:31:19'),
(50, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 16:31:42'),
(51, 2, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-23 18:48:32'),
(52, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-23 19:26:36'),
(53, 2, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-25 04:32:29'),
(54, 1, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-25 04:33:00'),
(55, 1, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-25 12:23:27'),
(56, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-25 12:23:42'),
(57, 2, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-25 16:24:02'),
(58, 4, 'New user registered', '::1', NULL, NULL, NULL, NULL, '2025-11-25 16:25:20'),
(59, 4, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-25 16:25:45'),
(60, 4, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:37:44'),
(61, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:37:59'),
(62, 2, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:45:03'),
(63, 1, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:45:29'),
(64, 1, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:46:15'),
(65, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:46:33'),
(66, 2, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:47:03'),
(67, 1, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:47:19'),
(68, 1, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:48:18'),
(69, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:48:36'),
(70, 2, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:49:30'),
(71, 1, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:49:43'),
(72, 1, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:50:27'),
(73, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:50:44'),
(74, 2, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:50:52'),
(75, 1, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:51:06'),
(76, 1, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:54:50'),
(77, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:55:27'),
(78, 2, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:55:47'),
(79, 3, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:56:06'),
(80, 3, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:58:05'),
(81, 1, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:58:18'),
(82, 1, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:59:24'),
(83, 3, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:59:38'),
(84, 3, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-26 13:59:51'),
(85, 1, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-26 14:00:08'),
(86, 1, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-26 14:02:10'),
(87, 3, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-26 14:02:25'),
(88, 3, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-26 14:02:40'),
(89, 1, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-26 14:02:53'),
(90, 1, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-26 14:03:11'),
(91, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-26 14:03:25'),
(92, 2, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-26 14:05:07'),
(93, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-26 14:06:19'),
(94, 3, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-28 20:17:37'),
(95, 1, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-28 20:18:21'),
(96, 1, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-28 20:19:52'),
(97, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-28 20:20:16'),
(98, 2, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-28 20:22:32'),
(99, 1, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-28 20:23:08'),
(100, 1, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-28 20:24:00'),
(101, 3, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-28 20:24:24'),
(102, 2, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-28 20:30:31'),
(103, 1, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-28 20:30:46'),
(104, 3, 'Updated location to: ', '::1', NULL, NULL, NULL, NULL, '2025-11-28 20:37:39'),
(105, 3, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-28 20:38:40'),
(106, 1, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-29 04:55:00'),
(107, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-29 04:56:27'),
(108, 2, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-29 04:57:05'),
(109, 1, 'User logged in', '::1', NULL, NULL, NULL, NULL, '2025-11-29 04:59:48'),
(110, 2, 'User logged out', '::1', NULL, NULL, NULL, NULL, '2025-11-29 05:23:31');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `recipient_id`, `subject`, `message`, `is_read`, `sent_at`, `read_at`) VALUES
(1, 1, 2, 'XBXXB', 'BXBBX', 1, '2025-11-23 14:44:56', '2025-11-23 15:38:20'),
(2, 2, 1, 'Re: XBXXB', 'HDGGDFDFFD', 0, '2025-11-23 15:38:16', NULL),
(3, 2, 4, 'GDGGD', 'TDTTDT', 0, '2025-11-26 13:42:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `fertilizer_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `price_per_unit` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `status` enum('Requested','Approved','Dispatched','Delivered','Cancelled') DEFAULT 'Requested',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `supplier_id`, `fertilizer_id`, `quantity`, `price_per_unit`, `total_price`, `status`, `order_date`) VALUES
(1, 1, 1, 1, 2000.00, 2000.00, 'Delivered', '2025-11-23 06:10:21'),
(2, 1, 1, 1, 2000.00, 2000.00, 'Delivered', '2025-11-23 06:21:12'),
(3, 1, 1, 3, 2000.00, 6000.00, 'Dispatched', '2025-11-23 11:15:37'),
(4, 1, 1, 1, 2000.00, 2000.00, 'Dispatched', '2025-11-23 11:34:05'),
(5, 1, 2, 11, 1900.00, 20900.00, 'Dispatched', '2025-11-26 13:48:01'),
(6, 1, 2, 2, 1900.00, 3800.00, 'Delivered', '2025-11-26 13:50:22'),
(7, 1, 2, 1, 1900.00, 1900.00, 'Dispatched', '2025-11-28 20:19:02'),
(8, 1, 1, 1, 2000.00, 2000.00, 'Requested', '2025-11-29 04:59:56'),
(9, 1, 2, 2, 1900.00, 3800.00, 'Dispatched', '2025-11-29 05:00:19');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `subsidy` decimal(10,2) DEFAULT NULL,
  `amount_paid` decimal(10,2) DEFAULT NULL,
  `payment_status` enum('Pending','Completed','Failed') DEFAULT 'Pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `receipt_path` text DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `order_id`, `total_price`, `subsidy`, `amount_paid`, `payment_status`, `payment_method`, `transaction_id`, `receipt_path`, `payment_date`) VALUES
(1, 2, 2000.00, 400.00, 1600.00, 'Completed', 'Stripe', 'pi_3SWbKqJOqqGZ4qxA2qDxkQuT', NULL, '2025-11-23 11:20:10'),
(2, 3, 6000.00, 1200.00, 4800.00, 'Completed', 'Stripe', 'pi_3SWbdEJOqqGZ4qxA2IwfHj5M', NULL, '2025-11-23 11:39:24'),
(3, 6, 3800.00, 760.00, 3040.00, 'Completed', 'Stripe', 'pi_3SXjAzJOqqGZ4qxA06NXZWgj', NULL, '2025-11-26 13:54:33'),
(4, 9, 3800.00, 760.00, 3040.00, 'Completed', 'Airtel Money', 'ZHZHHZHZ', NULL, '2025-11-29 05:01:27');

-- --------------------------------------------------------

--
-- Table structure for table `qr_links`
--

CREATE TABLE `qr_links` (
  `id` int(11) NOT NULL,
  `code` varchar(64) NOT NULL,
  `target_url` varchar(512) NOT NULL,
  `purpose` enum('certificate_application','info') NOT NULL DEFAULT 'certificate_application',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qr_links`
--

INSERT INTO `qr_links` (`id`, `code`, `target_url`, `purpose`, `created_by`, `created_at`, `expires_at`, `active`) VALUES
(1, '381d769df3ff3155afd0f1132827734b', 'http://localhost/free(fear)/free/supplier/apply_certificate.php?qr=381d769df3ff3155afd0f1132827734b', 'certificate_application', 2, '2025-11-23 10:33:42', '2025-12-23 11:33:42', 1);

-- --------------------------------------------------------

--
-- Table structure for table `sms_logs`
--

CREATE TABLE `sms_logs` (
  `id` int(11) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status_code` int(11) DEFAULT NULL,
  `response` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sms_logs`
--

INSERT INTO `sms_logs` (`id`, `phone_number`, `message`, `status_code`, `response`, `sent_at`) VALUES
(1, '+265994167753', 'Test SMS from FertilizerSys. If you received this, SMS integration works!', 401, 'The supplied authentication is invalid', '2025-11-24 19:15:02'),
(2, '+265994167753', 'Test SMS from FertilizerSys. If you received this, SMS integration works!', 401, 'The supplied authentication is invalid', '2025-11-24 19:17:57'),
(3, '+265994167753', 'Test SMS from FertilizerSys. If you received this, SMS integration works!', 401, 'The supplied authentication is invalid', '2025-11-24 19:23:44'),
(4, '+265994167753', 'Test SMS from FertilizerSys. If you received this, SMS integration works!', 401, 'The supplied authentication is invalid', '2025-11-24 19:24:00'),
(5, '+265994167753', 'Test SMS from FertilizerSys. If you received this, SMS integration works!', 401, 'The supplied authentication is invalid', '2025-11-24 19:24:49');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `fertilizer_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `movement_type` enum('IN','OUT') NOT NULL,
  `reason` text NOT NULL,
  `performed_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `user_id`, `company_name`, `phone`, `address`, `created_at`, `updated_at`) VALUES
(1, 1, 'Supplier one', '999999', 'DDGDGDG', '2025-11-22 19:23:58', '2025-11-22 19:23:58'),
(2, 4, 'supplier2', '265939387223', 'LIMBE', '2025-11-25 16:25:20', '2025-11-25 16:25:20');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_locations`
--

CREATE TABLE `supplier_locations` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('warehouse','distribution_point','retail') NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `address` text NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier_locations`
--

INSERT INTO `supplier_locations` (`id`, `supplier_id`, `name`, `type`, `latitude`, `longitude`, `address`, `is_primary`, `created_at`) VALUES
(1, 1, 'Main', 'warehouse', -15.80269000, 35.03429100, 'Blantyre', 1, '2025-11-23 06:01:11');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','supplier','driver') DEFAULT 'supplier',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `password`, `role`, `status`, `created_at`) VALUES
(1, 'Test', 'test@gmail.com', '009999999', '$2y$10$j4Fr3VwTBrpk38pCb7haiueT3zCJd1ZHLTI09NysSADH04d61c6Lm', 'supplier', 'active', '2025-11-22 19:06:02'),
(2, 'driver', 'admin@gmail.com', '939393933', '$2y$10$9.B9FFWlj7f7ujOVD3ItLOnno3sTKACjiQtoGqYYScW62tgG4Ynj2', 'admin', 'active', '2025-11-23 04:04:41'),
(3, 'driver', 'driver@gmail.com', '3939393933', '$2y$10$hnYc2WKW8lWaXLOYyw3Q6.ECyh1J4PiH5bYkwO3BtA9DJTpLq7PmO', 'driver', 'active', '2025-11-23 09:29:35'),
(4, 'supplier2', 'supplier2@gmail.com', '265939387223', '$2y$10$CO5EVPMsZywwD6K7d96CtuFytxySsFckrkmAuZ.r3YLjjHUvW11d6', 'supplier', 'active', '2025-11-25 16:25:20');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_applications_supplier` (`supplier_id`);

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `certificate_number` (`certificate_number`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `fk_cert_application` (`application_id`);

--
-- Indexes for table `certificate_applications`
--
ALTER TABLE `certificate_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `qr_link_id` (`qr_link_id`);

--
-- Indexes for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `driver_id_idx` (`driver_id`);

--
-- Indexes for table `driver_locations`
--
ALTER TABLE `driver_locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `driver_id` (`driver_id`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fertilizers`
--
ALTER TABLE `fertilizers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `fertilizer_id` (`fertilizer_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `qr_links`
--
ALTER TABLE `qr_links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_suppliers_user` (`user_id`);

--
-- Indexes for table `supplier_locations`
--
ALTER TABLE `supplier_locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `certificate_applications`
--
ALTER TABLE `certificate_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `driver_locations`
--
ALTER TABLE `driver_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fertilizers`
--
ALTER TABLE `fertilizers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `qr_links`
--
ALTER TABLE `qr_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sms_logs`
--
ALTER TABLE `sms_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `supplier_locations`
--
ALTER TABLE `supplier_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcement_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `fk_applications_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `certificates`
--
ALTER TABLE `certificates`
  ADD CONSTRAINT `fk_cert_application` FOREIGN KEY (`application_id`) REFERENCES `certificate_applications` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cert_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `certificate_applications`
--
ALTER TABLE `certificate_applications`
  ADD CONSTRAINT `fk_certapp_qr` FOREIGN KEY (`qr_link_id`) REFERENCES `qr_links` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_certapp_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD CONSTRAINT `fk_del_driver` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_del_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_del_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `driver_locations`
--
ALTER TABLE `driver_locations`
  ADD CONSTRAINT `fk_driver_location` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fertilizers`
--
ALTER TABLE `fertilizers`
  ADD CONSTRAINT `fk_fert_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_fertilizer` FOREIGN KEY (`fertilizer_id`) REFERENCES `fertilizers` (`id`),
  ADD CONSTRAINT `fk_orders_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`);

--
-- Constraints for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD CONSTRAINT `fk_supplier_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_locations`
--
ALTER TABLE `supplier_locations`
  ADD CONSTRAINT `fk_location_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
