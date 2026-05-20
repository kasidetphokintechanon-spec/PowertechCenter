-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 16, 2026 at 03:27 PM
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
-- Database: `powertech_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `level` varchar(50) DEFAULT 'info',
  `category` varchar(50) DEFAULT 'hr',
  `expirationDate` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `level`, `category`, `expirationDate`) VALUES
(18, 'กิจกรรม', 'รับประทานอาหารกลางวันที่ F16-17 เวลา 11.00 น.', 'info', 'general', '2025-12-21'),
(19, 'แจ้งเตือน', 'น้ำท้วมหลายพื้นที่', 'warning', 'general', '2025-12-17'),
(20, 'ดำฟหดหด', 'หกดหกเหเห', 'critical', 'general', '2025-12-18');

-- --------------------------------------------------------

--
-- Table structure for table `asset_categories`
--

CREATE TABLE `asset_categories` (
  `cat_id` int(11) NOT NULL,
  `cat_name` varchar(255) NOT NULL,
  `cat_abbr` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `asset_categories`
--

INSERT INTO `asset_categories` (`cat_id`, `cat_name`, `cat_abbr`) VALUES
(10, 'CCTV', 'CV'),
(11, 'Computer', 'PC'),
(12, 'Network', 'NT'),
(13, 'Notebook', 'NB'),
(14, 'Other', 'OTR'),
(15, 'Printer', 'PT'),
(16, 'Server', 'SV'),
(19, 'Monitor', 'Mr'),
(20, 'UPS', 'UPS');

-- --------------------------------------------------------

--
-- Table structure for table `asset_history`
--

CREATE TABLE `asset_history` (
  `history_id` int(11) NOT NULL,
  `asset_db_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` varchar(50) DEFAULT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `asset_history`
--

INSERT INTO `asset_history` (`history_id`, `asset_db_id`, `timestamp`, `user_id`, `user_name`, `event_type`, `details`) VALUES
(2, 24, '2025-12-17 23:20:16', '5808200', 'Mr. Kasidet Phokintechanon', 'NOTE', 'กระเป๋า'),
(3, 24, '2025-12-17 23:20:26', '5808200', 'Mr. Kasidet Phokintechanon', 'NOTE', 'Mouse'),
(4, 24, '2025-12-17 23:20:41', '5808200', 'Mr. Kasidet Phokintechanon', 'NOTE', 'สายชาจ'),
(9, 25, '2025-12-18 08:53:25', NULL, 'Mr. Kasidet Phokintechanon', 'LOAN_APPROVED', 'Loan approved for Mr. Kasidet Phokintechanon by Mr. Kasidet Phokintechanon'),
(10, 25, '2025-12-18 08:53:37', NULL, 'Mr. Kasidet Phokintechanon', 'LOAN_RETURNED', 'Asset returned and received by Mr. Kasidet Phokintechanon'),
(11, 24, '2025-12-19 14:45:43', NULL, 'Mr. Kasidet Phokintechanon', 'REPAIR_REQUEST', 'Ticket #PTA-MIS251219-02: เปิดไม่ติด'),
(12, 24, '2025-12-19 14:45:50', NULL, 'Mr. Kasidet Phokintechanon', 'REPAIR_REQUEST', 'Ticket #PTA-MIS251219-03: เปิดไม่ติด'),
(13, 24, '2025-12-19 14:46:23', NULL, 'Mr. Kasidet Phokintechanon', 'REPAIR_START', 'Ticket #PTA-MIS251219-03: Started repair'),
(14, 24, '2025-12-19 14:47:08', NULL, 'Mr. Kasidet Phokintechanon', 'REPAIR_COMPLETE', 'Ticket #PTA-MIS251219-03: Repair completed. Solution: สายไฟหลุด'),
(15, 24, '2025-12-19 14:51:34', NULL, 'Mr. Kasidet Phokintechanon', 'REPAIR_START', 'Ticket #PTA-MIS251219-03: Started repair'),
(16, 24, '2025-12-19 14:52:50', NULL, 'Mr. Kasidet Phokintechanon', 'REPAIR_START', 'Ticket #PTA-MIS251219-03: Started repair'),
(17, 24, '2025-12-19 14:53:09', NULL, 'Mr. Kasidet Phokintechanon', 'REPAIR_COMPLETE', 'Ticket #PTA-MIS251219-03: Repair completed. Solution: สายไฟหลุด\n[19/12/2568 21:52:14] พักงาน (Temporary Fix): -'),
(18, 24, '2025-12-19 14:56:12', NULL, 'Mr. Kasidet Phokintechanon', 'REPAIR_START', 'Ticket #PTA-MIS251219-03: Started repair'),
(19, 24, '2025-12-19 14:57:38', NULL, 'Mr. Kasidet Phokintechanon', 'REPAIR_COMPLETE', 'Ticket #PTA-MIS251219-03: Repair completed. Solution: เปลี่ยนสายไฟ'),
(20, 25, '2025-12-19 15:39:11', NULL, 'Mr. Kasidet Phokintechanon', 'LOAN_REQUEST', 'Requested by Mr. Kasidet Phokintechanon for ััั'),
(21, 25, '2025-12-19 15:39:54', NULL, 'Mr. Kasidet Phokintechanon', 'LOAN_APPROVED', 'Loan approved for Mr. Kasidet Phokintechanon by Mr. Kasidet Phokintechanon'),
(22, 25, '2025-12-19 15:40:06', NULL, 'Mr. Kasidet Phokintechanon', 'LOAN_RETURNED', 'Asset returned and received by Mr. Kasidet Phokintechanon'),
(23, 24, '2025-12-20 06:55:31', NULL, 'Mr. Kasidet Phokintechanon', 'REPAIR_START', 'Ticket #PTA-MIS251219-03: Started repair'),
(24, 24, '2025-12-20 06:56:25', NULL, 'Mr. Kasidet Phokintechanon', 'REPAIR_COMPLETE', 'Ticket #PTA-MIS251219-03: Repair completed. Solution: เปลี่ยนสายไฟ\n[19/12/2568 22:04:37] พักงาน (Waiting for Parts): -'),
(25, 25, '2026-01-06 07:48:25', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: F16'),
(26, 25, '2026-01-06 07:48:33', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: F16'),
(27, 25, '2026-01-06 07:49:34', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: เครื่องบินขับไล่ F16'),
(28, 25, '2026-01-06 07:49:53', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: เครื่องบินขับไล่ F16'),
(29, 24, '2026-01-06 07:57:03', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: Notebook ThinkPad E14'),
(30, 25, '2026-01-06 08:04:03', NULL, 'Mr. Kasidet Phokintechanon', 'REPAIR_REQUEST', 'Ticket #PTA-MIS260106-01: ปีกพัง'),
(31, 25, '2026-01-06 08:04:54', NULL, 'Mr. Kasidet Phokintechanon', 'REPAIR_START', 'Ticket #PTA-MIS260106-01: Started repair'),
(32, 25, '2026-01-06 08:05:20', NULL, 'Mr. Kasidet Phokintechanon', 'REPAIR_COMPLETE', 'Ticket #PTA-MIS260106-01: Repair completed. Solution: ซ่อมแล้ว เอากาวแปะ'),
(33, 25, '2026-01-07 09:01:33', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: เครื่องบินขับไล่ F16'),
(34, 25, '2026-01-07 09:08:20', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: เครื่องบินขับไล่ F16'),
(35, 25, '2026-01-07 09:08:35', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: เครื่องบินขับไล่ F16'),
(36, 25, '2026-01-07 09:08:48', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: เครื่องบินขับไล่ F16'),
(37, 25, '2026-01-07 09:09:05', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: เครื่องบินขับไล่ F16'),
(38, 25, '2026-01-07 09:26:49', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: เครื่องบินขับไล่ F16'),
(39, 25, '2026-01-07 09:29:09', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: เครื่องบินขับไล่ F16'),
(40, 25, '2026-01-07 09:30:26', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: เครื่องบินขับไล่ F16'),
(41, 25, '2026-01-07 09:30:55', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: เครื่องบินขับไล่ F16'),
(42, 25, '2026-01-09 01:40:17', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: เครื่องบินขับไล่ F16'),
(43, 25, '2026-01-09 01:40:34', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: เครื่องบินขับไล่ F16'),
(44, 25, '2026-01-09 01:43:29', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: เครื่องบินขับไล่ F16'),
(45, 25, '2026-01-09 01:46:08', NULL, 'Mr. Kasidet Phokintechanon', 'LOAN_REQUEST', 'Requested by Mr. Kasidet Phokintechanon for ยิมไปบินวันเด็ก'),
(46, 25, '2026-01-09 01:46:27', NULL, 'Mr. Kasidet Phokintechanon', 'LOAN_APPROVED', 'Loan approved for Mr. Kasidet Phokintechanon by Mr. Kasidet Phokintechanon'),
(47, 25, '2026-01-09 01:47:18', NULL, 'Mr. Kasidet Phokintechanon', 'LOAN_RETURNED', 'Asset returned and received by Mr. Kasidet Phokintechanon'),
(48, 25, '2026-01-09 01:51:28', NULL, 'Mr. Kasidet Phokintechanon', 'LOAN_REQUEST', 'Requested by Mr. Kasidet Phokintechanon for ยืมขับวันเด็ก'),
(49, 25, '2026-01-09 01:56:33', NULL, 'Mr. Kasidet Phokintechanon', 'LOAN_APPROVED', 'Loan approved for Mr. Kasidet Phokintechanon by Mr. Kasidet Phokintechanon'),
(50, 25, '2026-01-09 01:57:01', NULL, 'Mr. Kasidet Phokintechanon', 'LOAN_RETURNED', 'Asset returned and received by Mr. Kasidet Phokintechanon'),
(51, 25, '2026-01-09 03:36:31', NULL, 'Mr. Kasidet Phokintechanon', 'LOAN_REQUEST', 'Requested by Mr. Kasidet Phokintechanon for ยืมบินวันเด็ก\n'),
(52, 25, '2026-01-09 03:36:59', NULL, 'Mr. Kasidet Phokintechanon', 'LOAN_APPROVED', 'Loan approved for Mr. Kasidet Phokintechanon by Mr. Kasidet Phokintechanon'),
(53, 25, '2026-01-09 08:23:54', NULL, 'Mr. Kasidet Phokintechanon', 'LOAN_RETURNED', 'Asset returned and received by Mr. Kasidet Phokintechanon'),
(54, 0, '2026-01-09 09:44:40', NULL, 'Mr. Kasidet Phokintechanon', 'NOTE', '{\"text\":\"ประเป๋า\",\"image\":\"\"}'),
(55, 26, '2026-01-09 09:44:58', NULL, 'Mr. Kasidet Phokintechanon', 'CREATE', 'เพิ่มพัสดุใหม่: DELL Latitude 3440'),
(56, 27, '2026-01-09 09:51:02', NULL, 'Mr. Kasidet Phokintechanon', 'CREATE', 'เพิ่มพัสดุใหม่: Computer'),
(57, 27, '2026-01-09 09:59:01', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: Computer'),
(58, 28, '2026-01-09 10:54:45', NULL, 'Mr. Kasidet Phokintechanon', 'CREATE', 'เพิ่มพัสดุใหม่: Computer SET LCD'),
(59, 28, '2026-01-09 10:54:57', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: Computer SET LCD'),
(60, 29, '2026-01-09 10:57:43', NULL, 'Mr. Kasidet Phokintechanon', 'CREATE', 'เพิ่มพัสดุใหม่: Monitor'),
(61, 29, '2026-01-09 10:58:36', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: Monitor'),
(62, 29, '2026-01-09 10:58:44', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: Monitor'),
(63, 29, '2026-01-09 11:13:13', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: Monitor'),
(64, 0, '2026-01-11 02:14:52', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(65, 0, '2026-01-11 02:14:54', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(66, 0, '2026-01-11 02:14:55', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(67, 0, '2026-01-11 02:14:55', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(68, 0, '2026-01-11 02:14:55', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(69, 0, '2026-01-11 02:36:56', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(70, 0, '2026-01-11 02:36:56', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(71, 0, '2026-01-11 02:36:57', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(72, 0, '2026-01-11 02:36:57', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(73, 0, '2026-01-11 02:36:57', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(74, 0, '2026-01-11 02:36:58', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(75, 0, '2026-01-11 02:36:58', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(76, 0, '2026-01-11 02:38:14', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(77, 0, '2026-01-11 07:56:28', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(78, 0, '2026-01-11 07:56:28', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(79, 0, '2026-01-11 07:56:28', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(80, 0, '2026-01-11 07:56:29', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(81, 0, '2026-01-11 07:58:42', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(82, 0, '2026-01-11 07:58:43', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(83, 0, '2026-01-11 07:58:43', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(84, 0, '2026-01-11 07:58:43', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(85, 0, '2026-01-11 07:58:43', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(86, 0, '2026-01-11 07:58:45', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(87, 0, '2026-01-11 07:58:45', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(88, 0, '2026-01-11 07:58:46', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(89, 0, '2026-01-11 07:58:46', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(90, 0, '2026-01-11 08:15:45', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(91, 0, '2026-01-11 08:27:43', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(92, 0, '2026-01-11 08:27:46', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(93, 0, '2026-01-11 14:29:22', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(94, 0, '2026-01-11 14:29:30', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(95, 0, '2026-01-11 14:38:04', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(96, 0, '2026-01-11 14:38:05', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(97, 0, '2026-01-11 14:39:17', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(98, 0, '2026-01-11 14:39:55', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(99, 0, '2026-01-11 14:40:21', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(100, 0, '2026-01-11 14:40:36', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(101, 0, '2026-01-11 15:03:11', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(102, 0, '2026-01-11 15:04:50', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(103, 0, '2026-01-11 15:06:51', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(104, 29, '2026-01-11 15:30:25', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: Monitor'),
(105, 29, '2026-01-11 15:30:45', NULL, 'Mr. Kasidet Phokintechanon', 'UPDATE', 'แก้ไขข้อมูลพัสดุ: Monitor'),
(106, 0, '2026-01-15 07:42:57', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(107, 0, '2026-01-15 07:57:27', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(108, 0, '2026-01-15 07:57:28', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(109, 0, '2026-01-15 07:57:42', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(110, 0, '2026-01-15 08:06:45', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(111, 0, '2026-01-15 08:06:46', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(112, 0, '2026-01-15 08:13:05', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(113, 0, '2026-01-15 08:16:57', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(114, 0, '2026-01-15 08:16:59', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(115, 0, '2026-01-15 09:18:38', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(116, 0, '2026-01-15 09:19:39', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(117, 0, '2026-01-15 10:09:22', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(118, 0, '2026-01-15 10:12:05', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(119, 0, '2026-01-15 10:14:40', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(120, 0, '2026-01-15 10:15:00', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(121, 0, '2026-01-15 10:15:32', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(122, 0, '2026-01-15 10:18:37', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(123, 0, '2026-01-15 10:34:00', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(124, 0, '2026-01-15 13:07:05', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(125, 0, '2026-01-15 13:09:20', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(126, 0, '2026-01-15 13:10:49', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(127, 0, '2026-01-15 13:11:08', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(128, 0, '2026-01-15 13:18:56', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(129, 0, '2026-01-15 13:18:58', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(130, 0, '2026-01-15 13:24:55', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(131, 0, '2026-01-15 13:25:21', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(132, 0, '2026-01-15 13:39:52', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(133, 0, '2026-01-15 13:48:10', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: ระบบยืม-คืนอุปกรณ์ - SystemCenter (equipment.html)'),
(134, 0, '2026-01-15 15:28:54', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(135, 0, '2026-01-15 15:34:39', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(136, 0, '2026-01-15 15:45:59', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(137, 0, '2026-01-15 16:07:58', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(138, 0, '2026-01-15 16:33:55', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(139, 0, '2026-01-15 16:53:07', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(140, 0, '2026-01-15 16:53:16', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(141, 0, '2026-01-15 16:59:53', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(142, 0, '2026-01-15 17:00:56', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(143, 0, '2026-01-15 17:01:00', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(144, 0, '2026-01-15 17:04:50', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(145, 0, '2026-01-15 17:18:59', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(146, 0, '2026-01-15 17:19:05', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(147, 0, '2026-01-15 17:20:04', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(148, 0, '2026-01-15 17:21:50', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(149, 0, '2026-01-15 17:24:27', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech System (smart_booking.html)'),
(150, 0, '2026-01-15 17:24:35', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(151, 0, '2026-01-15 17:25:06', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(152, 0, '2026-01-15 17:37:08', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(153, 0, '2026-01-15 17:55:01', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(154, 0, '2026-01-15 17:55:03', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(155, 0, '2026-01-15 17:55:56', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(156, 0, '2026-01-15 18:01:05', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(157, 0, '2026-01-15 18:06:06', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(158, 0, '2026-01-15 18:08:38', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(159, 0, '2026-01-15 18:08:55', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(160, 0, '2026-01-15 18:09:24', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(161, 0, '2026-01-15 18:09:31', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(162, 0, '2026-01-15 18:09:31', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(163, 0, '2026-01-15 18:11:49', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(164, 0, '2026-01-15 18:12:17', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(165, 0, '2026-01-15 18:12:33', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(166, 0, '2026-01-15 18:29:37', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(167, 0, '2026-01-15 18:29:48', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(168, 0, '2026-01-15 18:31:28', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(169, 0, '2026-01-16 00:16:57', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(170, 0, '2026-01-16 00:21:30', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(171, 0, '2026-01-16 00:22:51', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(172, 0, '2026-01-16 00:34:59', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(173, 0, '2026-01-16 02:24:55', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(174, 0, '2026-01-16 02:25:35', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(175, 0, '2026-01-16 02:27:26', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(176, 0, '2026-01-16 02:28:49', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(177, 0, '2026-01-16 02:29:00', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(178, 0, '2026-01-16 02:29:01', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(179, 0, '2026-01-16 02:29:59', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(180, 0, '2026-01-16 02:29:59', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(181, 0, '2026-01-16 02:30:00', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(182, 0, '2026-01-16 02:30:03', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(183, 0, '2026-01-16 02:30:22', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(184, 0, '2026-01-16 02:32:54', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(185, 0, '2026-01-16 02:39:16', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(186, 0, '2026-01-16 02:39:35', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(187, 0, '2026-01-16 02:41:02', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(188, 0, '2026-01-16 02:41:28', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(189, 0, '2026-01-16 02:41:28', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(190, 0, '2026-01-16 02:41:32', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(191, 0, '2026-01-16 02:44:05', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(192, 0, '2026-01-16 02:45:04', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(193, 0, '2026-01-16 02:46:24', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(194, 0, '2026-01-16 02:47:55', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(195, 0, '2026-01-16 02:53:01', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(196, 0, '2026-01-16 02:55:04', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(197, 0, '2026-01-16 02:55:15', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(198, 0, '2026-01-16 02:55:15', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(199, 0, '2026-01-16 02:56:01', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(200, 0, '2026-01-16 02:56:48', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(201, 0, '2026-01-16 02:57:44', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(202, 0, '2026-01-16 03:06:29', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(203, 0, '2026-01-16 03:06:51', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(204, 0, '2026-01-16 03:17:37', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(205, 0, '2026-01-16 03:18:40', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(206, 0, '2026-01-16 04:08:56', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(207, 0, '2026-01-16 04:09:04', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(208, 0, '2026-01-16 04:09:16', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(209, 0, '2026-01-16 04:10:02', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(210, 0, '2026-01-16 04:10:09', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(211, 0, '2026-01-16 04:20:09', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(212, 0, '2026-01-16 04:20:10', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(213, 0, '2026-01-16 04:20:12', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking - Powertech (booking.html)'),
(214, 0, '2026-01-16 04:31:25', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(215, 0, '2026-01-16 04:32:12', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(216, 0, '2026-01-16 04:32:21', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(217, 0, '2026-01-16 04:53:38', NULL, 'admin', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(218, 0, '2026-01-16 07:42:22', NULL, 'admin', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(219, 0, '2026-01-16 07:42:32', NULL, 'admin', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(220, 0, '2026-01-16 07:47:14', NULL, 'admin', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(221, 0, '2026-01-16 07:47:20', NULL, 'admin', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(222, 0, '2026-01-16 07:47:21', NULL, 'admin', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(223, 0, '2026-01-16 07:49:17', NULL, 'admin', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(224, 0, '2026-01-16 07:49:18', NULL, 'admin', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(225, 0, '2026-01-16 07:49:40', NULL, 'admin', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(226, 0, '2026-01-16 07:50:12', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(227, 0, '2026-01-16 07:50:16', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(228, 0, '2026-01-16 07:50:24', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(229, 0, '2026-01-16 08:00:11', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(230, 0, '2026-01-16 08:00:12', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(231, 0, '2026-01-16 08:00:36', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(232, 0, '2026-01-16 08:02:58', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(233, 0, '2026-01-16 08:04:26', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(234, 0, '2026-01-16 08:05:09', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(235, 0, '2026-01-16 08:05:55', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(236, 0, '2026-01-16 08:09:36', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: Smart Booking V2 - Powertech (booking_v2.html)'),
(237, 0, '2026-01-16 09:24:52', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: จัดการโลโก้บริษัท - Powertech System (admin_logo.html)'),
(238, 0, '2026-01-16 09:25:04', NULL, 'Mr. Kasidet Phokintechanon', 'ACCESS', 'เข้าชมหน้า: จัดการโลโก้บริษัท - Powertech System (admin_logo.html)');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `user_id` varchar(50) DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `target_id` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `timestamp`, `user_id`, `user_name`, `action`, `target_id`, `details`, `ip_address`) VALUES
(1, '2025-12-16 13:39:07', '5808200', 'Mr. Kasidet Phokintechanon', 'update_item:services', 'IT -Service', '{\"id\":\"IT -Service\",\"icon\":\"fas fa-cog\",\"title\":\"แจ้งซ่อม ไอที\",\"description\":\"แจ้งซ่อม ไอที  โปรแกรม\\/Network\",\"url_primary\":\"request.html\",\"url_fallback\":\"\",\"color\":\"#3b82f6\",\"sort_order\":\"1\"}', '::1'),
(2, '2025-12-16 13:39:07', '5808200', 'Mr. Kasidet Phokintechanon', 'add_item:services', '0', '{\"id\":\"IT -Service\",\"icon\":\"fas fa-cog\",\"title\":\"แจ้งซ่อม ไอที\",\"description\":\"แจ้งซ่อม ไอที  โปรแกรม\\/Network\",\"url_primary\":\"request.html\",\"url_fallback\":\"\",\"color\":\"#3b82f6\",\"sort_order\":\"1\"}', '::1'),
(3, '2025-12-16 13:39:19', '5808200', 'Mr. Kasidet Phokintechanon', 'update_item:services', 'service-mt', '{\"id\":\"service-mt\",\"icon\":\"fas fa-tools\",\"title\":\"ระบบแจ้งซ่อม MT\",\"description\":\"แจ้งปัญหาและติดตามสถานะการซ่อมบำรุง\",\"url_primary\":\"http:\\/\\/192.168.0.18\\/SystemCenter\\/\",\"url_fallback\":\"http:\\/\\/110.170.174.148\\/SystemCenter\\/\",\"color\":\"#3b82f6\",\"sort_order\":\"2\"}', '::1'),
(4, '2025-12-16 13:39:19', '5808200', 'Mr. Kasidet Phokintechanon', 'add_item:services', '0', '{\"id\":\"service-mt\",\"icon\":\"fas fa-tools\",\"title\":\"ระบบแจ้งซ่อม MT\",\"description\":\"แจ้งปัญหาและติดตามสถานะการซ่อมบำรุง\",\"url_primary\":\"http:\\/\\/192.168.0.18\\/SystemCenter\\/\",\"url_fallback\":\"http:\\/\\/110.170.174.148\\/SystemCenter\\/\",\"color\":\"#3b82f6\",\"sort_order\":\"2\"}', '::1'),
(5, '2025-12-16 13:40:09', '5808200', 'Mr. Kasidet Phokintechanon', 'update_item:services', 'service-car', '{\"id\":\"service-car\",\"icon\":\"fas fa-car-side\",\"title\":\"ระบบจองรถ\",\"description\":\"จองรถส่วนกลาง ออนไลน์ (CARSYS)\",\"url_primary\":\"http:\\\\\\/\\\\\\/192.168.0.13\\\\\\/carsys\\\\\\/\",\"url_fallback\":\"http:\\\\\\/\\\\\\/110.170.174.149\\\\\\/carsys\\\\\\/\",\"color\":\"#22c55e\",\"sort_order\":\"3\"}', '::1'),
(6, '2025-12-16 13:40:09', '5808200', 'Mr. Kasidet Phokintechanon', 'add_item:services', '0', '{\"id\":\"service-car\",\"icon\":\"fas fa-car-side\",\"title\":\"ระบบจองรถ\",\"description\":\"จองรถส่วนกลาง ออนไลน์ (CARSYS)\",\"url_primary\":\"http:\\\\\\/\\\\\\/192.168.0.13\\\\\\/carsys\\\\\\/\",\"url_fallback\":\"http:\\\\\\/\\\\\\/110.170.174.149\\\\\\/carsys\\\\\\/\",\"color\":\"#22c55e\",\"sort_order\":\"3\"}', '::1'),
(7, '2025-12-16 13:40:15', '5808200', 'Mr. Kasidet Phokintechanon', 'update_item:services', 'service-room', '{\"id\":\"service-room\",\"icon\":\"fas fa-calendar-check\",\"title\":\"ระบบจองห้องประชุม\",\"description\":\"จองห้องประชุม ออนไลน์ (ROOMSYS)\",\"url_primary\":\"http:\\\\\\/\\\\\\/192.168.0.13\\\\\\/roomsys\\\\\\/\",\"url_fallback\":\"http:\\\\\\/\\\\\\/110.170.174.149\\\\\\/roomsys\\\\\\/\",\"color\":\"#8b5cf6\",\"sort_order\":\"4\"}', '::1'),
(8, '2025-12-16 13:40:15', '5808200', 'Mr. Kasidet Phokintechanon', 'add_item:services', '0', '{\"id\":\"service-room\",\"icon\":\"fas fa-calendar-check\",\"title\":\"ระบบจองห้องประชุม\",\"description\":\"จองห้องประชุม ออนไลน์ (ROOMSYS)\",\"url_primary\":\"http:\\\\\\/\\\\\\/192.168.0.13\\\\\\/roomsys\\\\\\/\",\"url_fallback\":\"http:\\\\\\/\\\\\\/110.170.174.149\\\\\\/roomsys\\\\\\/\",\"color\":\"#8b5cf6\",\"sort_order\":\"4\"}', '::1'),
(9, '2025-12-16 21:21:35', '5808200', 'Mr. Kasidet Phokintechanon', 'login_success', '5808200', NULL, '::1'),
(10, '2025-12-16 22:14:53', '5808200', 'Mr. Kasidet Phokintechanon', 'login_success', '5808200', NULL, '::1'),
(11, '2025-12-17 12:06:58', '5808200', 'Mr. Kasidet Phokintechanon', 'login_success', '5808200', NULL, '::1'),
(12, '2025-12-17 12:08:06', '5808200', 'Mr. Kasidet Phokintechanon', 'update_item:it_logs', 'PT4-MIS251216-01', '{\"id\":\"PT4-MIS251216-01\",\"date\":\"2025-12-16\",\"company\":\"PT4\",\"requester\":\"Ms. Pornpapat Phokintechanon\",\"department\":\"HR and GA\",\"requester_id\":\"8\",\"servicedBy\":\"Mr. Kasidet Phokintechanon\",\"servicedById\":\"5808200\",\"asset\":\"\",\"problem\":\"จองรถมีปัญหา\",\"solution\":null,\"repair_cost\":null,\"status\":\"In Progress\",\"completed_date\":null,\"type\":\"Software\",\"urgency\":\"ปกติ\",\"notes\":null,\"startDate\":null,\"endDate\":null,\"appointment_date\":null,\"attachment_count\":1,\"first_attachment\":\"1765865720-6940f8f8dad11-Gemini_Generated_Image_1pynmf1pynmf1pyn.png\"}', '::1'),
(13, '2025-12-17 12:08:28', '5808200', 'Mr. Kasidet Phokintechanon', 'update_item:it_logs', 'PT4-MIS251216-01', '{\"id\":\"PT4-MIS251216-01\",\"date\":\"2025-12-16\",\"company\":\"PT4\",\"requester\":\"Ms. Pornpapat Phokintechanon\",\"department\":\"HR and GA\",\"requester_id\":\"8\",\"servicedBy\":\"Mr. Kasidet Phokintechanon\",\"servicedById\":\"5808200\",\"asset\":\"\",\"problem\":\"จองรถมีปัญหา\",\"solution\":\"1123\",\"repair_cost\":null,\"status\":\"Completed\",\"completed_date\":null,\"type\":\"Software\",\"urgency\":\"ปกติ\",\"notes\":null,\"startDate\":null,\"endDate\":null,\"appointment_date\":null,\"attachment_count\":1,\"first_attachment\":\"1765865720-6940f8f8dad11-Gemini_Generated_Image_1pynmf1pynmf1pyn.png\"}', '::1'),
(14, '2025-12-17 12:31:02', '5808200', 'Mr. Kasidet Phokintechanon', 'login_success', '5808200', NULL, '::1'),
(15, '2025-12-17 13:38:13', '5808200', 'Mr. Kasidet Phokintechanon', 'create_booking', '1', '{\"room_id\":\"1\",\"title\":\"ประชุมปีใหม่\",\"start_time\":\"2025-12-17 19:30\",\"end_time\":\"2025-12-17 20:30\",\"is_public\":\"1\"}', '::1'),
(16, '2025-12-17 13:38:50', '5808200', 'Mr. Kasidet Phokintechanon', 'update_item:meeting_rooms', '1', '{\"id\":\"1\",\"name\":\"สีฟ้า\",\"company\":\"PTA\",\"location\":\"F16\",\"capacity\":\"15\",\"equipment\":\"[\\\"จอLED\\\"]\",\"is_active\":1,\"image\":\"uploads\\/meeting_rooms\\/room-69424ff9f3217.jpg\"}', '::1'),
(17, '2025-12-17 13:39:18', '5808200', 'Mr. Kasidet Phokintechanon', 'logout', '5808200', NULL, '::1'),
(18, '2025-12-17 13:39:24', '5808200', 'Mr. Kasidet Phokintechanon', 'login_success', '5808200', NULL, '::1'),
(19, '2025-12-17 13:40:43', '5808200', 'Mr. Kasidet Phokintechanon', 'update_item:meeting_rooms', '2', '{\"id\":\"2\",\"name\":\"Meeting Room\",\"company\":\"PTE\",\"location\":\"PTE\",\"capacity\":\"10\",\"equipment\":\"[]\",\"is_active\":1,\"image\":\"uploads\\/meeting_rooms\\/room-6942506b9ee30.jpg\"}', '::1'),
(20, '2025-12-17 14:33:24', '5808200', 'Mr. Kasidet Phokintechanon', 'update_item:meeting_amenities', '1', '{\"id\":\"1\",\"name\":\"ชุดกาแฟและเบรค\",\"unit\":\"ชุด\",\"is_active\":1}', '::1'),
(21, '2025-12-17 14:41:03', '5808200', 'Mr. Kasidet Phokintechanon', 'create_booking', '2', '{\"room_id\":\"2\",\"title\":\"ขัดจอ\",\"agenda\":\"ซีเรียส\",\"chairman\":\"กษิเดช\",\"use_for_department\":\"IT\",\"attendees\":\"Mr. Kasidet Phokintechanon, Ms. Pornpapat Phokintechanon\",\"contact_phone\":\"\",\"required_equipment\":\"[{\\\"id\\\":\\\"1\\\",\\\"name\\\":\\\"ชุดกาแฟและเบรค\\\",\\\"qty\\\":\\\"30\\\",\\\"unit\\\":\\\"ชุด\\\"},{\\\"id\\\":\\\"2\\\",\\\"name\\\":\\\"น้ำดื่ม\\\",\\\"qty\\\":\\\"10\\\",\\\"unit\\\":\\\"ขวด\\\"}]\",\"start_time\":\"2025-12-17 15:00\",\"end_time\":\"2025-12-17 16:00\",\"is_public\":\"1\"}', '::1'),
(22, '2025-12-17 14:50:46', '5808200', 'Mr. Kasidet Phokintechanon', 'create_booking', '3', '{\"room_id\":\"2\",\"title\":\"ประชุมปีใหม่\",\"agenda\":\"2143325315235\",\"chairman\":\"กษิเดช\",\"use_for_department\":\"IT\",\"attendees\":\"Mr. Kasidet Phokintechanon\",\"contact_phone\":\"0939259989\",\"required_equipment\":\"[{\\\"id\\\":\\\"6\\\",\\\"name\\\":\\\"ปลั๊กพ่วง\\\",\\\"qty\\\":\\\"1\\\",\\\"unit\\\":\\\"เส้น\\\"}]\",\"start_time\":\"2025-12-17 17:30\",\"end_time\":\"2025-12-17 17:30\"}', '::1'),
(23, '2025-12-17 14:52:52', '5808200', 'Mr. Kasidet Phokintechanon', 'update_item:services', 'service-room', '{\"id\":\"service-room\",\"icon\":\"fas fa-calendar-check\",\"title\":\"ระบบจองห้องประชุม\",\"description\":\"จองห้องประชุม ออนไลน์ (ROOMSYS)\",\"url_primary\":\"booking.html\",\"url_fallback\":\"http:\\\\\\/\\\\\\/110.170.174.149\\\\\\/roomsys\\\\\\/\",\"color\":\"#8b5cf6\",\"sort_order\":\"4\"}', '::1'),
(24, '2025-12-17 14:55:35', '5808200', 'Mr. Kasidet Phokintechanon', 'create_booking', '4', '{\"room_id\":\"3\",\"title\":\"ำพัพำัำพ\",\"agenda\":\"พำัำพเดกเกด\",\"chairman\":\"ุุพะพำะำพั้พำั\",\"use_for_department\":\"IT\",\"attendees\":\"Mr. Witthawat Sonbun, Korawee Songserm, Mr. Kasidet Phokintechanon\",\"contact_phone\":\"ำพั\",\"required_equipment\":\"[{\\\"id\\\":\\\"2\\\",\\\"name\\\":\\\"น้ำดื่ม\\\",\\\"qty\\\":\\\"1\\\",\\\"unit\\\":\\\"ขวด\\\"}]\",\"start_time\":\"2025-12-17 20:32\",\"end_time\":\"2025-12-17 19:30\"}', '::1'),
(25, '2025-12-17 15:09:48', '5808200', 'Mr. Kasidet Phokintechanon', 'logout', '5808200', NULL, '::1'),
(26, '2025-12-17 15:09:55', '5808200', 'Mr. Kasidet Phokintechanon', 'login_success', '5808200', NULL, '::1'),
(27, '2025-12-17 15:12:57', '5808200', 'Mr. Kasidet Phokintechanon', 'logout', '5808200', NULL, '::1'),
(28, '2025-12-17 15:13:10', '8', 'Ms. Pornpapat Phokintechanon', 'login_success', '8', NULL, '::1'),
(29, '2025-12-17 15:13:40', '8', 'Ms. Pornpapat Phokintechanon', 'logout', '8', NULL, '::1'),
(30, '2025-12-17 15:13:47', '5808200', 'Mr. Kasidet Phokintechanon', 'login_success', '5808200', NULL, '::1'),
(31, '2025-12-17 15:44:17', '5808200', 'Mr. Kasidet Phokintechanon', 'logout', '5808200', NULL, '::1'),
(32, '2025-12-17 15:44:25', '5808200', 'Mr. Kasidet Phokintechanon', 'login_success', '5808200', NULL, '::1'),
(33, '2025-12-17 21:28:57', '5808200', 'Mr. Kasidet Phokintechanon', 'create_booking', '5', '{\"room_id\":\"1\",\"title\":\"นัด supplier ช่างไฟ\",\"agenda\":\"คุยเรื่องทั่วไป\",\"chairman\":\"Mr. Kasidet Phokintechanon\",\"use_for_department\":\"IT\",\"attendees\":\"\",\"contact_phone\":\"0939259989\",\"required_equipment\":\"[{\\\"id\\\":\\\"5\\\",\\\"name\\\":\\\"ไวท์บอร์ดและปากกา\\\",\\\"qty\\\":\\\"1\\\",\\\"unit\\\":\\\"ชุด\\\"}]\",\"start_time\":\"2025-12-17 12:00\",\"end_time\":\"2025-12-17 13:00\"}', '::1'),
(34, '2025-12-17 21:50:38', '5808200', 'Mr. Kasidet Phokintechanon', 'create_booking', '6', '{\"room_id\":\"1\",\"title\":\"ประชุมปีใหม่\",\"agenda\":\"jjjj\",\"chairman\":\"Mr. Anan Atthajak\",\"use_for_department\":\"IT\",\"attendees\":\"Mr. Anan Atthajak, Mr. Jarong Boonsom, Mr. Jakkrit Thudthong, Mr.Phitak Jenjai, Ms.Jariya Thongkanha, Chuthamard Suchart, Mr. Chanon Moongmai, Intira Singpee\",\"contact_phone\":\"\",\"required_equipment\":\"[]\",\"action\":\"create_booking\",\"start_time\":\"2025-12-17 11:30\",\"end_time\":\"2025-12-17 12:30\"}', '::1'),
(35, '2025-12-17 21:51:29', '5808200', 'Mr. Kasidet Phokintechanon', 'move_booking', '6', '{\"start\":\"2025-12-24 05:30:00\",\"end\":\"2025-12-24 06:30:00\"}', '::1'),
(36, '2025-12-17 21:51:32', '5808200', 'Mr. Kasidet Phokintechanon', 'move_booking', '6', '{\"start\":\"2025-12-18 05:30:00\",\"end\":\"2025-12-18 06:30:00\"}', '::1'),
(37, '2025-12-17 22:02:40', '5808200', 'Mr. Kasidet Phokintechanon', 'logout', '5808200', NULL, '::1'),
(38, '2025-12-17 22:03:00', '8', 'Ms. Pornpapat Phokintechanon', 'login_success', '8', NULL, '::1'),
(39, '2025-12-17 22:31:24', '8', 'Ms. Pornpapat Phokintechanon', 'logout', '8', NULL, '::1'),
(40, '2025-12-17 22:31:38', '5808200', 'Mr. Kasidet Phokintechanon', 'login_success', '5808200', NULL, '::1'),
(41, '2025-12-17 23:03:05', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-002', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-002\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"location\":\"0\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"0.00\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(42, '2025-12-17 23:04:17', '5808200', 'Mr. Kasidet Phokintechanon', 'request_equipment', 'PTA-MIS-NB-2512-002', '{\"id\":\"PTA-MIS-NB-2512-002\",\"borrowerName\":\"Mr. Kasidet Phokintechanon\",\"department\":\"IT\",\"employeeId\":\"5808200\",\"returnDate\":\"2025-12-18\",\"purpose\":\"ำำำำ\"}', '::1'),
(43, '2025-12-17 23:04:50', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-002', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-002\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"location\":\"0\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"0.00\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(44, '2025-12-17 23:08:14', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-002', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-002\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"location\":\"0\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"0.00\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(45, '2025-12-17 23:15:09', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-003', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-003\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"location\":\"0\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"0.00\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(46, '2025-12-18 06:20:43', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-003', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-003\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"location\":\"0\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"0.00\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(47, '2025-12-18 06:21:37', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-003', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-003\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"location\":\"F4\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(48, '2025-12-18 06:38:28', '5808200', 'Mr. Kasidet Phokintechanon', 'request_equipment', 'PT4-MIS-OTR-2512-001', '{\"id\":\"PT4-MIS-OTR-2512-001\",\"borrowerName\":\"Mr. Kasidet Phokintechanon\",\"department\":\"IT\",\"employeeId\":\"5808200\",\"returnDate\":\"2025-12-19\",\"purpose\":\"บินเล่นวันเด็ก\"}', '::1'),
(49, '2025-12-18 06:47:12', '5808200', 'Mr. Kasidet Phokintechanon', 'request_equipment', 'PT4-MIS-OTR-2512-001', '{\"id\":\"PT4-MIS-OTR-2512-001\",\"borrowerName\":\"Mr. Kasidet Phokintechanon\",\"department\":\"IT\",\"employeeId\":\"5808200\",\"returnDate\":\"2025-12-19\",\"purpose\":\"บินทิ้งระเบิด\"}', '::1'),
(50, '2025-12-18 06:55:36', '5808200', 'Mr. Kasidet Phokintechanon', 'update_item:it_logs', 'PTA-MIS251215-02', '{\"id\":\"PTA-MIS251215-02\",\"date\":\"2025-12-15\",\"company\":\"PTA\",\"requester\":\"Mr. Kasidet Phokintechanon\",\"department\":\"IT\",\"requester_id\":\"5808200\",\"servicedBy\":\"Ms. Lalin Nuchsawart\",\"servicedById\":\"6806669\",\"asset\":\"\",\"problem\":\"แจ้งซ่อมตัวเอง\",\"solution\":\"[15\\/12\\/2568 22:45:58] พักงาน (Temporary Fix): -\",\"repair_cost\":null,\"status\":\"In Progress\",\"completed_date\":null,\"type\":\"Other\",\"urgency\":\"ด่วนที่สุด\",\"notes\":null,\"startDate\":null,\"endDate\":null,\"appointment_date\":null,\"attachment_count\":0,\"first_attachment\":null}', '::1'),
(51, '2025-12-18 07:07:42', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(52, '2025-12-18 07:12:35', '5808200', 'Mr. Kasidet Phokintechanon', 'logout', '5808200', NULL, '::1'),
(53, '2025-12-18 07:12:44', '8', 'Ms. Pornpapat Phokintechanon', 'login_success', '8', NULL, '::1'),
(54, '2025-12-18 07:13:26', '8', 'Ms. Pornpapat Phokintechanon', 'logout', '8', NULL, '::1'),
(55, '2025-12-18 07:13:32', '5808200', 'Mr. Kasidet Phokintechanon', 'login_success', '5808200', NULL, '::1'),
(56, '2025-12-18 07:14:41', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PT4-MIS-OTR-2512-001', '{\"db_id\":\"25\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"14\",\"equipment_id\":\"PT4-MIS-OTR-2512-001\",\"name\":\"F16\",\"brand\":\"จีนแดง\",\"model\":\"GS2000\",\"serial_number\":\"\",\"asset_id\":\"PTA-OE-2025054433\",\"location\":\"IT Room F4\",\"purchase_date\":\"2025-12-16\",\"warranty_expires_on\":\"2025-12-24\",\"purchase_price\":\"0.00\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTEMISOTR2512001-693f951a851fb.jpg\",\"action\":\"save_equipment\"}', '::1'),
(57, '2025-12-18 07:15:22', '5808200', 'Mr. Kasidet Phokintechanon', 'logout', '5808200', NULL, '::1'),
(58, '2025-12-18 07:15:28', '5808200', 'Mr. Kasidet Phokintechanon', 'login_success', '5808200', NULL, '::1'),
(59, '2025-12-18 07:17:23', '5808200', 'Mr. Kasidet Phokintechanon', 'update_item:services', 'IT -Service', '{\"id\":\"IT -Service\",\"icon\":\"fas fa-cog\",\"title\":\"แจ้งซ่อม ไอที\",\"description\":\"แจ้งซ่อม ไอที  โปรแกรม\\/Network\",\"url_primary\":\"request.html\",\"url_fallback\":\"\",\"color\":\"#3b82f6\",\"sort_order\":\"2\"}', '::1'),
(60, '2025-12-18 07:17:36', '5808200', 'Mr. Kasidet Phokintechanon', 'update_item:services', 'service-mt', '{\"id\":\"service-mt\",\"icon\":\"fas fa-tools\",\"title\":\"ระบบแจ้งซ่อม MT\",\"description\":\"แจ้งปัญหาและติดตามสถานะการซ่อมบำรุง\",\"url_primary\":\"http:\\/\\/192.168.0.18\\/SystemCenter\\/\",\"url_fallback\":\"http:\\/\\/110.170.174.148\\/SystemCenter\\/\",\"color\":\"#3b82f6\",\"sort_order\":\"2\"}', '::1'),
(61, '2025-12-18 08:13:20', '5808200', 'Mr. Kasidet Phokintechanon', 'login_success', '5808200', NULL, '::1'),
(62, '2025-12-18 08:17:47', '5808200', 'Mr. Kasidet Phokintechanon', 'login_success', '5808200', NULL, '192.168.0.150'),
(63, '2025-12-18 08:34:52', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(64, '2025-12-18 08:34:57', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(65, '2025-12-18 08:35:13', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(66, '2025-12-18 08:35:28', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(67, '2025-12-18 08:35:36', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PT4-MIS-OTR-2512-001', '{\"db_id\":\"25\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"14\",\"equipment_id\":\"PT4-MIS-OTR-2512-001\",\"name\":\"F16\",\"brand\":\"จีนแดง\",\"model\":\"GS2000\",\"serial_number\":\"\",\"asset_id\":\"PTA-OE-2025054433\",\"location\":\"IT Room F4\",\"purchase_date\":\"2025-12-16\",\"warranty_expires_on\":\"2025-12-24\",\"purchase_price\":\"0.00\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTEMISOTR2512001-693f951a851fb.jpg\",\"action\":\"save_equipment\"}', '::1'),
(68, '2025-12-18 08:37:56', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(69, '2025-12-18 08:38:09', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(70, '2025-12-18 08:38:39', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(71, '2025-12-18 08:40:49', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(72, '2025-12-18 08:41:43', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(73, '2025-12-18 08:42:16', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(74, '2025-12-18 08:45:41', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(75, '2025-12-18 08:45:50', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(76, '2025-12-18 08:52:01', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"spec_details\":\"\",\"ip_address\":\"\",\"mac_address\":\"\",\"supplier\":\"\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"po_number\":\"\",\"invoice_number\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(77, '2025-12-18 08:58:19', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"spec_details\":\"\",\"ip_address\":\"\",\"mac_address\":\"\",\"supplier\":\"\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"po_number\":\"\",\"invoice_number\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(78, '2025-12-18 08:58:23', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"spec_details\":\"\",\"ip_address\":\"\",\"mac_address\":\"\",\"supplier\":\"\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"po_number\":\"\",\"invoice_number\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(79, '2025-12-18 09:02:33', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"spec_details\":\"\",\"ip_address\":\"\",\"mac_address\":\"\",\"supplier\":\"\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"po_number\":\"\",\"invoice_number\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(80, '2025-12-18 09:02:43', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"spec_details\":\"\",\"ip_address\":\"\",\"mac_address\":\"\",\"supplier\":\"\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"po_number\":\"\",\"invoice_number\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(81, '2025-12-18 09:03:01', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"spec_details\":\"\",\"ip_address\":\"\",\"mac_address\":\"\",\"supplier\":\"\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"po_number\":\"\",\"invoice_number\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(82, '2025-12-18 09:06:53', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"spec_details\":\"\",\"ip_address\":\"\",\"mac_address\":\"\",\"supplier\":\"\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"po_number\":\"\",\"invoice_number\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(83, '2025-12-18 09:06:59', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"spec_details\":\"\",\"ip_address\":\"\",\"mac_address\":\"\",\"supplier\":\"\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"po_number\":\"\",\"invoice_number\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(84, '2025-12-18 09:07:45', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"spec_details\":\"\",\"ip_address\":\"\",\"mac_address\":\"\",\"supplier\":\"\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"po_number\":\"\",\"invoice_number\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(85, '2025-12-18 09:11:52', '5808200', 'Mr. Kasidet Phokintechanon', 'save_equipment', 'PTA-MIS-NB-2512-004', '{\"db_id\":\"24\",\"company\":\"PTA\",\"owning_department\":\"1\",\"asset_category\":\"13\",\"equipment_id\":\"PTA-MIS-NB-2512-004\",\"name\":\"Notebook ThinkPad E14\",\"brand\":\"Lenovo\",\"model\":\"E14\",\"serial_number\":\"PF5LL07F\",\"asset_id\":\"PTA-OE-202505442\",\"location\":\"F4\",\"spec_details\":\"\",\"ip_address\":\"\",\"mac_address\":\"\",\"supplier\":\"\",\"purchase_date\":\"2025-12-14\",\"warranty_expires_on\":\"2029-06-15\",\"purchase_price\":\"\",\"po_number\":\"\",\"invoice_number\":\"\",\"status\":\"ว่าง\",\"is_loanable\":\"1\",\"current_image_url\":\"uploads\\/equipment\\/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg\",\"action\":\"save_equipment\"}', '::1'),
(86, '2025-12-18 09:45:52', '5808200', 'Mr. Kasidet Phokintechanon', 'logout', '5808200', NULL, '::1'),
(87, '2025-12-18 09:45:58', '5808200', 'Mr. Kasidet Phokintechanon', 'login_success', '5808200', NULL, '::1'),
(88, '2025-12-18 10:09:51', '5808200', 'Mr. Kasidet Phokintechanon', 'update_item:it_logs', 'PT4-MIS251218-01', '{\"id\":\"PT4-MIS251218-01\",\"date\":\"2025-12-18\",\"company\":\"PT4\",\"requester\":\"Mr. Kasidet Phokintechanon\",\"department\":\"IT\",\"asset_id\":\"PT4-MIS-OTR-2512-001\",\"requester_id\":\"5808200\",\"servicedBy\":\"Mr. Kasidet Phokintechanon\",\"servicedById\":\"5808200\",\"asset\":null,\"problem\":\"น้ำมันหมด เติมด่วน\",\"contact\":\"0939259989\",\"image_path\":null,\"solution\":null,\"repair_cost\":null,\"status\":\"In Progress\",\"rating\":null,\"completed_date\":null,\"type\":\"Other\",\"urgency\":\"ด่วนที่สุด\",\"notes\":null,\"startDate\":null,\"endDate\":null,\"appointment_date\":null,\"attachment_count\":0,\"first_attachment\":null}', '::1'),
(89, '2025-12-18 10:10:15', '5808200', 'Mr. Kasidet Phokintechanon', 'update_item:it_logs', 'PT4-MIS251218-01', '{\"id\":\"PT4-MIS251218-01\",\"date\":\"2025-12-18\",\"company\":\"PT4\",\"requester\":\"Mr. Kasidet Phokintechanon\",\"department\":\"IT\",\"asset_id\":\"PT4-MIS-OTR-2512-001\",\"requester_id\":\"5808200\",\"servicedBy\":\"Mr. Kasidet Phokintechanon\",\"servicedById\":\"5808200\",\"asset\":null,\"problem\":\"น้ำมันหมด เติมด่วน\",\"contact\":\"0939259989\",\"image_path\":null,\"solution\":\"เติมแล้ว\",\"repair_cost\":null,\"status\":\"Completed\",\"rating\":null,\"completed_date\":null,\"type\":\"Other\",\"urgency\":\"ด่วนที่สุด\",\"notes\":null,\"startDate\":null,\"endDate\":null,\"appointment_date\":null,\"attachment_count\":0,\"first_attachment\":null}', '::1'),
(90, '2025-12-18 10:10:21', '5808200', 'Mr. Kasidet Phokintechanon', 'update_item:it_logs', 'PT4-MIS251218-02', '{\"id\":\"PT4-MIS251218-02\",\"date\":\"2025-12-18\",\"company\":\"PT4\",\"requester\":\"Mr. Kasidet Phokintechanon\",\"department\":\"IT\",\"asset_id\":\"PT4-MIS-OTR-2512-001\",\"requester_id\":\"5808200\",\"servicedBy\":\"Mr. Kasidet Phokintechanon\",\"servicedById\":\"5808200\",\"asset\":null,\"problem\":\"เติมจรวจ\",\"contact\":\"0939259989\",\"image_path\":null,\"solution\":null,\"repair_cost\":null,\"status\":\"In Progress\",\"rating\":null,\"completed_date\":null,\"type\":\"Other\",\"urgency\":\"ด่วนที่สุด\",\"notes\":null,\"startDate\":null,\"endDate\":null,\"appointment_date\":null,\"attachment_count\":0,\"first_attachment\":null}', '::1'),
(91, '2025-12-18 10:10:58', '5808200', 'Mr. Kasidet Phokintechanon', 'update_item:it_logs', 'PT4-MIS251218-02', '{\"id\":\"PT4-MIS251218-02\",\"date\":\"2025-12-18\",\"company\":\"PT4\",\"requester\":\"Mr. Kasidet Phokintechanon\",\"department\":\"IT\",\"asset_id\":\"PT4-MIS-OTR-2512-001\",\"requester_id\":\"5808200\",\"servicedBy\":\"Mr. Kasidet Phokintechanon\",\"servicedById\":\"5808200\",\"asset\":null,\"problem\":\"เติมจรวจ\",\"contact\":\"0939259989\",\"image_path\":null,\"solution\":\"ลุยต่อ\",\"repair_cost\":null,\"status\":\"Completed\",\"rating\":null,\"completed_date\":null,\"type\":\"Other\",\"urgency\":\"ด่วนที่สุด\",\"notes\":null,\"startDate\":null,\"endDate\":null,\"appointment_date\":null,\"attachment_count\":0,\"first_attachment\":null}', '::1'),
(92, '2025-12-18 11:03:26', '5808200', 'Mr. Kasidet Phokintechanon', 'logout', '5808200', NULL, '::1'),
(93, '2025-12-18 11:03:33', '5808200', 'Mr. Kasidet Phokintechanon', 'login_success', '5808200', NULL, '::1'),
(94, '2025-12-18 11:03:45', '5808200', 'Mr. Kasidet Phokintechanon', 'logout', '5808200', NULL, '::1'),
(95, '2025-12-18 11:03:50', '5808200', 'Mr. Kasidet Phokintechanon', 'login_success', '5808200', NULL, '::1'),
(96, '2025-12-18 11:03:57', '5808200', 'Mr. Kasidet Phokintechanon', 'logout', '5808200', NULL, '::1'),
(97, '2025-12-18 11:04:03', '5808200', 'Mr. Kasidet Phokintechanon', 'login_success', '5808200', NULL, '::1'),
(98, '2025-12-18 11:04:34', '5808200', 'Mr. Kasidet Phokintechanon', 'logout', '5808200', NULL, '::1'),
(99, '2025-12-18 11:04:41', '5808200', 'Mr. Kasidet Phokintechanon', 'login_success', '5808200', NULL, '::1'),
(100, '2025-12-18 11:05:56', '5808200', 'Mr. Kasidet Phokintechanon', 'request_equipment', 'PT4-MIS-OTR-2512-001', '{\"id\":\"PT4-MIS-OTR-2512-001\",\"borrowerName\":\"Mr. Kasidet Phokintechanon\",\"department\":\"IT\",\"employeeId\":\"5808200\",\"returnDate\":\"2025-12-18\",\"purpose\":\"ยืมไปส่งลูกที่โรงเรียน\"}', '::1');

-- --------------------------------------------------------

--
-- Table structure for table `chat_groups`
--

CREATE TABLE `chat_groups` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `creator_id` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_groups`
--

INSERT INTO `chat_groups` (`id`, `name`, `creator_id`, `created_at`) VALUES
(1, 'กลุ่มลับ', '5808200', '2025-12-22 04:16:18'),
(2, 'กินข้าวssss', '8', '2025-12-22 04:36:43');

-- --------------------------------------------------------

--
-- Table structure for table `chat_group_members`
--

CREATE TABLE `chat_group_members` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_group_members`
--

INSERT INTO `chat_group_members` (`id`, `group_id`, `user_id`, `joined_at`) VALUES
(3, 2, '5808200', '2025-12-22 04:36:43'),
(4, 2, '8', '2025-12-22 04:36:43');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` varchar(50) NOT NULL,
  `receiver_id` varchar(50) NOT NULL,
  `company` varchar(10) NOT NULL,
  `message_text` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`message_id`, `sender_id`, `receiver_id`, `company`, `message_text`, `timestamp`, `is_read`) VALUES
(1, '8', '5808200', 'PT4', '{\"type\":\"text\",\"content\":\"สวัดดี\"}', '2025-12-21 15:06:05', 2),
(2, '8', '5808200', 'PT4', '{\"type\":\"text\",\"content\":\"ดดด\"}', '2025-12-21 15:06:38', 2),
(4, '8', '5808200', 'PT4', '{\"type\":\"text\",\"content\":\"สวัดดี\"}', '2025-12-21 15:13:29', 2),
(5, '5808200', '8', 'PTA', '{\"type\":\"text\",\"content\":\"Dddd\"}', '2025-12-21 15:14:25', 2),
(6, '8', '5808200', 'PT4', '{\"type\":\"text\",\"content\":\"ถถถถ\"}', '2025-12-21 15:14:35', 2),
(7, '8', '5808200', 'PT4', '{\"type\":\"text\",\"content\":\"ถกหะหเดกหเดก\"}', '2025-12-21 15:14:47', 2),
(11, '5808200', '8', 'PTA', '{\"type\":\"text\",\"content\":\"ทดสอบ\"}', '2025-12-21 15:20:17', 2),
(14, '5808200', '6901702', 'PTA', '{\"type\":\"text\",\"content\":\"{\\\"type\\\":\\\"image\\\",\\\"url\\\":\\\"http:\\/\\/localhost\\/powertechtest\\/uploads\\/chat_files\\/chat_6948c3afcad86.png\\\",\\\"caption\\\":\\\"\\\"}\"}', '2025-12-22 04:06:07', 0),
(17, '8', 'GROUP_1', 'PT4', '{\"type\":\"text\",\"content\":\"test\"}', '2025-12-22 04:18:31', 0),
(20, '8', 'GROUP_2', 'PT4', '{\"type\":\"text\",\"content\":\"กินข้าวหลามหนองมนมั้ยวันนี้\"}', '2025-12-22 04:37:10', 2),
(21, '5808200', 'GROUP_2', 'PTA', '{\"type\":\"text\",\"content\":\"ได้เลย\"}', '2025-12-22 04:38:00', 2),
(26, '8', '5808200', 'PT4', '{\"type\":\"text\",\"content\":\"4324324\"}', '2025-12-22 04:50:47', 2),
(27, '8', '5808200', 'PT4', '{\"type\":\"text\",\"content\":\"อยู่รียัง\"}', '2025-12-22 07:38:40', 2),
(28, '8', '5808200', 'PT4', '{\"type\":\"text\",\"content\":\"อยู๋ไหน\"}', '2025-12-23 03:32:51', 2),
(29, '8', '5808200', 'PT4', '{\"type\":\"text\",\"content\":\"โหลๆ\"}', '2025-12-23 03:32:58', 2),
(30, '5808200', '8', 'PTE', '{\"type\":\"text\",\"content\":\"ะหกเดหกดเ\"}', '2025-12-23 03:33:02', 2),
(31, '5808200', '8', 'PTE', '{\"type\":\"text\",\"content\":\"1150 มั้ยวันนนี้\"}', '2025-12-23 03:38:59', 2),
(32, '8', 'GROUP_2', 'PT4', '{\"type\":\"text\",\"content\":\"มายัง\"}', '2025-12-23 03:43:52', 1),
(33, '8', 'GROUP_2', 'PT4', '{\"type\":\"text\",\"content\":\"กินข้าว\"}', '2025-12-23 03:43:54', 1),
(34, '5808200', '8', 'PTE', '{\"type\":\"text\",\"content\":\"ะะ\"}', '2025-12-23 03:47:32', 2),
(35, '5808200', '8', 'PTE', '{\"type\":\"text\",\"content\":\"ถถถ\"}', '2025-12-23 03:47:37', 2),
(36, '8', 'GROUP_2', 'PT4', '{\"type\":\"text\",\"content\":\"ๅ/-ๅ/-ๅ/-\"}', '2025-12-23 03:48:46', 1),
(37, '5808200', 'GROUP_2', 'PTE', '{\"type\":\"text\",\"content\":\"ภภภภภ\"}', '2025-12-23 03:48:52', 1),
(39, '5808200', '8', 'PTE', '{\"type\":\"text\",\"content\":\"มามั้ยวันนนี้\"}', '2025-12-23 04:10:29', 2),
(40, '6604614', '5808200', 'PTA', '{\"type\":\"text\",\"content\":\"สวัสดีครับ\"}', '2025-12-23 04:12:41', 2),
(41, '5808200', '6604614', 'PTA', '{\"type\":\"text\",\"content\":\"tt123123123\"}', '2025-12-23 04:12:52', 1),
(43, '6604614', '5808200', 'PTA', '{\"type\":\"text\",\"content\":\"ไม่มี่ข้อมูล\"}', '2025-12-23 04:13:10', 2),
(44, '6604614', '5808200', 'PTA', '{\"type\":\"image\",\"url\":\"http://192.168.0.169/powertechtest/uploads/chat_files/chat_694a16e04bee4.png\",\"caption\":\"\"}', '2025-12-23 04:13:20', 2),
(45, '6604614', '5808200', 'PTA', '{\"type\":\"text\",\"content\":\"ทดสอบ\"}', '2025-12-23 04:14:08', 2),
(46, '5808200', '6604614', 'PTA', '{\"type\":\"text\",\"content\":\"1\"}', '2025-12-23 04:14:12', 0),
(47, '5808200', '6604614', 'PTA', '{\"type\":\"text\",\"content\":\"23\"}', '2025-12-23 04:14:15', 0),
(48, '5808200', '8', 'PTA', '{\"type\":\"text\",\"content\":\"123123\"}', '2025-12-23 04:19:34', 2),
(49, '5808200', '8', 'PTA', '{\"type\":\"text\",\"content\":\"333\"}', '2025-12-23 04:19:47', 2),
(50, '5808200', '8', 'PTA', '{\"type\":\"text\",\"content\":\"333\"}', '2025-12-23 04:19:50', 2),
(51, '5808200', '8', 'PTA', '{\"type\":\"text\",\"content\":\"11231\"}', '2025-12-23 04:23:37', 2),
(52, '5808200', '8', 'PTA', '{\"type\":\"text\",\"content\":\"12312312\"}', '2025-12-23 04:23:52', 2),
(53, '8', '5808200', 'PT4', '{\"type\":\"text\",\"content\":\"1241414\"}', '2025-12-23 04:23:57', 2),
(54, '8', '5808200', 'PT4', '{\"type\":\"text\",\"content\":\"Fs]ssq\"}', '2025-12-23 04:25:38', 2),
(55, '5808200', '8', 'PTA', '{\"type\":\"text\",\"content\":\"123123\"}', '2025-12-23 04:25:50', 2),
(56, '8', '5808200', 'PT4', '{\"type\":\"text\",\"content\":\"rdtgreyrst\"}', '2025-12-23 04:25:54', 2),
(57, '8', '5808200', 'PT4', '{\"type\":\"text\",\"content\":\"123123123123\"}', '2025-12-23 04:32:37', 2),
(58, '5808200', '8', 'PTE', '{\"type\":\"text\",\"content\":\"ๅ/-ๅ/-ๅ/-\"}', '2025-12-23 04:33:02', 2),
(59, '5808200', '8', 'PTE', '{\"type\":\"text\",\"content\":\"-ภถุ-ภถ-ภ\"}', '2025-12-23 04:33:12', 2),
(60, '8', '5808200', 'PT4', '{\"type\":\"text\",\"content\":\"e321321321\"}', '2025-12-23 04:33:15', 2),
(61, '5808200', '8', 'PTE', '{\"type\":\"text\",\"content\":\"ๅ\"}', '2025-12-23 04:36:26', 2),
(62, '8', '5808200', 'PT4', '{\"type\":\"text\",\"content\":\"1\"}', '2025-12-23 04:36:29', 2),
(63, '8', '5808200', 'PT4', '{\"type\":\"text\",\"content\":\"g-\"}', '2025-12-23 04:38:47', 2),
(64, '5808200', '8', 'PTA', '{\"type\":\"text\",\"content\":\"ๅ/-\"}', '2025-12-23 04:38:53', 2),
(65, '8', '5808200', 'PT4', '{\"type\":\"text\",\"content\":\"srydyt\"}', '2025-12-23 04:40:33', 2),
(66, '5808200', '8', 'PTA', '{\"type\":\"text\",\"content\":\"/-ถ/-ถภ/\"}', '2025-12-23 04:40:36', 2),
(67, '5808200', '8', 'PTA', '{\"type\":\"text\",\"content\":\"/-/-ภ/-ภ/-ภ\"}', '2025-12-23 04:45:29', 2),
(68, '8', '5808200', 'PT4', '{\"type\":\"text\",\"content\":\"1231234412412\"}', '2025-12-23 04:45:31', 2),
(69, '5808200', '8', 'PTA', '{\"type\":\"text\",\"content\":\"ๅ/-ๅ/-\"}', '2025-12-23 04:47:37', 2),
(70, '5808200', '8', 'PT4', '{\"type\":\"text\",\"content\":\"123213\"}', '2025-12-24 11:57:54', 2),
(71, '5808200', '8', 'PT4', '{\"type\":\"text\",\"content\":\"12321312312\"}', '2025-12-24 11:58:49', 2),
(72, '8', '5808200', 'PT4', '{\"type\":\"text\",\"content\":\"123213\"}', '2025-12-24 11:58:51', 2),
(73, '8', '5808200', 'PT4', '{\"type\":\"text\",\"content\":\"ๅ/\"}', '2026-01-11 00:05:15', 2);

-- --------------------------------------------------------

--
-- Table structure for table `chat_typing`
--

CREATE TABLE `chat_typing` (
  `user_id` varchar(50) NOT NULL,
  `typing_to` varchar(50) NOT NULL,
  `timestamp` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `dept_id` int(11) NOT NULL,
  `dept_name` varchar(255) NOT NULL,
  `dept_abbr` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`dept_id`, `dept_name`, `dept_abbr`) VALUES
(1, 'IT', 'MIS'),
(2, 'HR and GA', 'HGA'),
(3, 'Accounting', 'ACC'),
(4, 'Production', 'PD'),
(5, 'Production Engineer', 'PE'),
(6, 'Maintenance', 'MT'),
(7, 'Logistics', 'LG'),
(8, 'Quality Management', 'QM'),
(9, 'Safety & Environment', 'SE'),
(10, 'Management', 'MG');

-- --------------------------------------------------------

--
-- Table structure for table `department_contacts`
--

CREATE TABLE `department_contacts` (
  `id` int(11) NOT NULL,
  `company` varchar(10) NOT NULL,
  `department` varchar(100) NOT NULL,
  `sub_department` varchar(100) DEFAULT NULL,
  `phone` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `department_contacts`
--

INSERT INTO `department_contacts` (`id`, `company`, `department`, `sub_department`, `phone`) VALUES
(1, 'PTA', 'Management', NULL, '038-111-111'),
(2, 'PTA', 'Accounting', NULL, '(084) 768 5188'),
(3, 'PTA', 'HR and GA', 'จัดซื้อ', '(081) 170 7532'),
(4, 'PTA', 'HR and GA', 'บุคคล', '(081) 945 1796'),
(5, 'PTA', 'HR and GA', 'ธุรการ', '(092) 754 1496'),
(6, 'PTA', 'IT', NULL, '038-111-113'),
(7, 'PTA', 'Production', NULL, '(098) 264 9205'),
(8, 'PTA', 'Production Engineer', NULL, '(098) 264 9205'),
(9, 'PTA', 'Maintenance', NULL, '(081) 996 5349'),
(10, 'PTA', 'Logistics', NULL, '(081) 957 8139'),
(11, 'PTA', 'Quality Management', NULL, '(081) 967 5096'),
(12, 'PTA', 'Safety & Environment', NULL, '038-111-113'),
(13, 'PT4', 'Management', NULL, '(081) 735 5260'),
(14, 'PT4', 'Accounting', NULL, '(084) 768 5188'),
(15, 'PT4', 'HR and GA', 'จัดซื้อ', '(081) 170 7532'),
(16, 'PT4', 'HR and GA', 'บุคคล', '(081) 945 1796'),
(17, 'PT4', 'HR and GA', 'ธุรการ', '(092) 754 1496'),
(18, 'PT4', 'IT', NULL, '038-111-113'),
(19, 'PT4', 'Production', NULL, '(098) 264 9205'),
(20, 'PT4', 'Production Engineer', NULL, '(098) 264 9205'),
(21, 'PT4', 'Maintenance', NULL, '(081) 996 5349'),
(22, 'PT4', 'Logistics', NULL, '(081) 957 8139'),
(23, 'PT4', 'Quality Management', NULL, '(081) 967 5096'),
(24, 'PT4', 'Safety & Environment', NULL, '038-111-113'),
(25, 'PTE', 'Management', NULL, ''),
(26, 'PTE', 'Accounting', NULL, '(084) 768 5188'),
(27, 'PTE', 'HR and GA', 'จัดซื้อ', '(081) 170 7532'),
(28, 'PTE', 'HR and GA', 'บุคคล', '(081) 945 1796'),
(29, 'PTE', 'HR and GA', 'ธุรการ', '(092) 754 1496'),
(30, 'PTE', 'IT', NULL, ''),
(31, 'PTE', 'Production', NULL, '(081) 768 6177'),
(32, 'PTE', 'Production Engineer', NULL, '(081) 768 6177'),
(33, 'PTE', 'Maintenance', NULL, ''),
(34, 'PTE', 'Logistics', NULL, ''),
(35, 'PTE', 'Quality Management', NULL, ''),
(36, 'PTE', 'Safety & Environment', NULL, '');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_th` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL,
  `responsible_department` varchar(255) DEFAULT NULL,
  `remember_token` varchar(64) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `employment_status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `name`, `name_th`, `position`, `phone`, `image`, `username`, `password`, `role`, `responsible_department`, `remember_token`, `birthdate`, `start_date`, `employment_status`, `created_at`, `updated_at`) VALUES
('5404005', 'Ms. thannaree sitthilertnarong', '', 'Senior Supervisor', '093 5465149', '5404005-68d1253473020.jpg', '5404005', '$2y$10$hX1YREbbTuvj/yKD7JHFp.evEfJOSHo/qvIlD2ceUfpZ30m724CqC', 'staff', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2026-01-07 08:40:13'),
('5405010', 'Mr.Anan Atthajak', '', 'Plan Manager', '089 6040708', '5405010-68d12a731803c.png', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:43:07'),
('5406014', 'Ms. Thasipha Amornnaranon', '', 'Senior Officer', '090 9639170', '5406014-68d12ab4058b1.jpg', '5406014', '$2y$10$kH8Qa52kNKewut9l41cKBOytX8GPXdcgEe5ttGEz3WCDjts6i02.y', 'staff', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:48:29'),
('5406019', 'Ms.Nanthaya Chunson', '', 'Officer', '082 7162529', '5406019-68d12b520bd62.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:41:41'),
('5406027', 'Mr.Samart Jitthaisong', NULL, 'Senior Springer', '', '5406027-68d2467e81dee.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('5406033', 'Ms. Puckaporn Prachayapinis', NULL, 'Officer', '086 0968546', NULL, '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('5406036', 'Mr.Somchai Meanthim', NULL, 'Senior Springer', '', '5406036-68d2214ddcb7d.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('5406037', 'Mr.Manote Puangmanee', NULL, 'Supervisor (Lv1)', '', '5406037-68d221365ea0c.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('5406048', 'Ms.Natchanan Klaiklang', '', 'Senior Officer', '', '5406048-68d218c8a1104.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:43:35'),
('5407049', 'Mr.Dacha Sermsopit', NULL, 'Manager (Acting)', '', '5407049-68d12a536ff16.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('5407058', 'Mr.Nithiaseelan Vesuvanathan', NULL, 'Head of Testing', '', '5407058-68d2415db929c.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('5410063', 'Ms.Teerayut Tong-on', '', 'Manager', '', '5410063-68f6efcfa23ad.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-10-21 02:28:31'),
('5411065', 'Mr.Suwachai Chaiyakhotr', '', 'Supervisor (Lv2)', '084 7047647', '5411065-68d1261f61e7d.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:42:41'),
('5507087', 'Mr.Michael Welser', '', 'Managing Director', '(081) 833 6619', '5507087-68d1249b501da.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-19 07:07:21'),
('5507089', 'Ms.Prapaphan Pichaikham', NULL, 'Executive Director and General Manager For Administration Division', '(082) 516 3565', '5507089-68d124a9beb94.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('5609111', 'Ms.Phatira Suwanna', NULL, 'Senior Officer', '086 0302443', '5609111-68d124e472d16.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('5610115', 'Ms.Chanicha Sapmun', '', 'Supervisor (Lv1)', '063 4566229', '5610115-68d2198650624.png', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-10-29 04:56:35'),
('5802155', 'Mr.Weerasak Krapatcharasri', NULL, 'Senior Technician', '098 8247588', NULL, '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('5802161', 'Mr.Prayoon Piakaenkaew', '', 'Staff', '085 1732806', '5802161-68d12609e2e0f.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:42:02'),
('5804168', 'Mr.Pornchai Traipherm', '', 'Head of Operations', '081 9367697', '5804168-68d0cf537babd.png', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-10-28 02:27:04'),
('5804169', 'Ms.Wanna.Suwannarun', '', 'Manager', '094-146-6261', '5804169-68d12c888f4f6.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:40:39'),
('5805184', 'Thichutha Rungruang', NULL, 'Supervisor (Lv2)', '087 9415946', '5805184-68d126da0f6c8.png', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('5806185', 'Mr.Jarong Boonsom', '', 'Supervisor (Lv3)', '', '5806185-68d21970c4806.png', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:44:21'),
('5807187', 'Ms. Nutnicha Aree', '', 'Senior Officer', '061 5369916', '5807187-68d125514eabb.jpg', '5807187', '$2y$10$vrJ0vNaJiB6VW3alG.fgseAFsPIxQfiKMPVsSkEUzgqAJ13vybyc.', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:48:17'),
('5807190', 'Mr.Manop Umnangrong', NULL, 'Assistant Manger', '090 9027252', '5807190-68d218ae2bbb4.png', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('5808198', 'Ms.Nathaphorn Tachaphornrat', '', 'Officer', '095 4969261', '5808198-68d25c76cf151.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:42:21'),
('5808200', 'Mr. Kasidet Phokintechanon', 'กษิเดช โภคินเตชนนท์', 'Staff', '0939259989', '5808200-68d125eb719e1.jpg', '5808200', '$2y$10$gLJJNfFS4BLMOC6xMDiCYegl0j0ryKvp4QqR9xSLFmilLRe.Q3J/a', 'admin', NULL, NULL, '1987-12-26', '2015-08-03', 'active', '2025-09-25 07:58:25', '2025-12-15 18:16:56'),
('5809205', 'Mr.Jakkrit Thudthong', '', 'Manager', '081 7686177', '5809205-68d2110026fdb.png', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:44:05'),
('5810218', 'Ms.Sathita Srilachai', NULL, 'Officer', '089 0404229', '5810218-68d218e18ed81.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('5810236', 'Mr. Somya Wongyapan', 'สมยา วงยาปาน', 'Assistant Manger', '082 1059988', '5810236-68d12aef82443.jpg', '5810236', '$2y$10$bOCv/7yRC.BlXqbS/4LIGOVql32XgdB2pGJCRAzB9BGlYNKUd2q0m', 'admin', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2026-01-07 08:55:13'),
('5811252', 'Mr.Peng Boonkrang', NULL, 'Springer', '090 7887344', '5811252-68d219a23403b.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('5811258', 'Mr. Chanon Moongmai', NULL, 'Supervisor (Lv3)', '', '5811258-68d2195e3000b.png', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('5901264', 'Ms. Warinthon Kanchanakulsawas', '', 'Officer', '094 1956939', '5901264-68d12ac3acd87.jpg', '5901264', '$2y$10$EbLOjrS4b3qO1yUwDd1DJOFHxNqGXg4mh5aJnWz2XvfIgn3Pkw0Hq', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:49:04'),
('5902267', 'Mr.Nutthachai Yuwaphunkhul', '', 'Head of Operations', '094 5198994', '5902267-68d12a5e8f818.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-10-28 02:27:17'),
('5907282', 'Ms. Chanita Waiyamas', '', 'Officer', '0957936209', '5907282-68ca3d4a3927f.jpg', '5907282', '$2y$10$zornNs6/ocMGllVWr5NpHessl.kTAyQ3BqL41xZZCi.hI7j469AHq', 'staff', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2026-01-07 06:16:25'),
('5908284', 'Ms.Chanyanut Uangtidchai', NULL, 'Supervisor (Lv2)', '089 9946991', '5908284-68d1268a73582.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('5909292', 'Ms.Ilada Pumanwat', '', 'Engineer(Lv1)', '084 6423532', '5909292-68d126ac682f6.png', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:45:57'),
('6005298', 'Mr.Kiatisak Areepong', '', 'Supervisor (Lv1)', '', '6005298-68d221675b887.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:45:28'),
('6102312', 'Ms. Ernie Yusnita Binti Ismail', NULL, 'Manager', '', '6102312-68d20d84611f9.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('6105319', 'Mr.Phitak Jenjai', NULL, 'Supervisor (Lv2)', '', '6105319-68d2194a8c663.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('6106330', 'MS.Siriporn Ruthirawut', '', 'Head of General Administratoin Division', '089 6841601', '6106330-68d0cc9996c3e.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:31:47'),
('6107339', 'Thanyakarn Thanyahiranrat', '', 'Officer', '085 0862176', '6107339-68d1257c4c94b.jpg', 'User2', '$2y$10$8dNV./rxtKpv6mQid1Ar6urwNm0O2/3T4/5c2T3/uomGhhZJbVXbq', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2026-01-06 07:19:38'),
('6107349', 'Mr.adisorn.detnonsri', '', 'Supervisor (Lv2)', '097 0608530', '6107349-68d128c0ab0e2.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:44:37'),
('6109362', 'Ms.Jariya Thongkanha', NULL, 'Staff', '', '6109362-68d25c657f199.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('6207444', 'Mr.Natapong Orapin', NULL, 'Senior Springer', '', '6207444-68d241365e764.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('6207445', 'Ms.Chuthamard Suchart', '', 'Officer', '087 7421072', '6207445-68d125aa1ae89.jpeg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:41:29'),
('6403472', 'Ms.Natthaphon Daorueangsri', NULL, 'Springer', '062 2859796', '6403472-68d247d9d1906.png', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('6412512', 'Ms.Intira Singpee', '', 'Staff', '062 8183265', '6412512-68d1267200904.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:46:27'),
('6504550', 'Ms.Uraiwan Songwaree', '', 'Manager', '092 5802444', '6504550-68d12b190a028.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:41:09'),
('6506568', 'Mr.Worawoot Det-udom', NULL, 'Engineer(Lv1)', '', '6506568-68d21cc538fbf.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('6508579', 'Mr.Chanaphat Wattiwong', NULL, 'Engineer(Lv1)', '080 5109270', '6508579-68d22102ceba3.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('6511594', 'Mr.Chawalit Krasprapan', NULL, 'Engineer(Lv1)', '099 1539469', '6511594-68d22119d1fb9.png', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('6604614', 'Mr.Kritsada Suksawat', '', 'Programmer', '085 2805462', '6604614-68d125d00d299.jpg', '6604614', '$2y$10$9zmeuoPTIEfn8bi.TEbwK.ph9GuYvc7VcSWENzyZmKnGvMYl8cHsG', 'admin', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-16 02:12:28'),
('6704644', 'Mr.Nattawut Deelerd', '', 'Factory and Mainteanace', '086 3750228', '6704644-68d1265940740.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-10-28 02:24:03'),
('6708653', 'Mr.Pathomphong Phengcharoen', NULL, 'Supervisor (Lv1)', '', '6708653-68d207a9bfbde.png', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('6709654', 'Ms. Sumitra Sengda', '', 'Officer', '095 5729156', '6709654-68d2191277022.png', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 04:04:10'),
('6709656', 'Mr.Kantaphon Tirasriwat', '', 'Assistant Manger', '088 9141598', '6709656-68d21ca4e5f87.png', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:45:10'),
('6709659', 'Ms.Chanisa Wongnim', '', 'Supervisor (Lv1)', '', '6709659-68d12b42667d0.png', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:40:48'),
('6710660', 'Ms. Sirinya Tidchai', '', 'Supervisor (Lv3)', '092 4980862', '6710660-68d12a8253a1e.png', '', '', 'user', NULL, NULL, NULL, NULL, 'inactive', '2025-09-25 07:58:25', '2025-12-22 02:42:17'),
('6711661', 'Mr.Panidh Mingmalairak', '', 'Engineer(Lv1)', '095 7608451', '6711661-68d21bc72ce98.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:46:13'),
('6803664', 'Ms. Panida Bumpenkit', '', 'Officer', '063 5341644', '6803664-68d12a8f63d1d.png', '6803664', '$2y$10$rlaaIhaq0LtlUHHhKZyHAeeuzctg3rJH2Kl9roQJ0xDJBkKMgn/7G', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:47:30'),
('6803665', 'Suphitchaya Chaithanee', NULL, 'Staff', '065 0861800', '6803665-68d1252099bd5.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('6804666', 'Korawee Songserm', NULL, 'Officer', '063 0989299', '6804666-68d124f577a7a.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('6805667', 'Ms. Chatpawee Hiranphatcharanon', '', 'Supervisor (Lv1)', '089 9612442', '6805667-68d124bd6cabb.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-10-21 02:40:49'),
('6806669', 'Ms. Lalin Nuchsawart', '', 'Programmer', '098 4413664', '6806669-68d125ddb20a2.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:40:05'),
('6806670', 'Ms.Kanyanat.Patkulkan', '', 'Officer', '082 4735343', '6806670-68d12a9f74ec7.png', '6806670', '$2y$10$l5U.Mh4PAhj99noYDQ.0OOwt.YuBbeXeJ6nlFt/y8E1pklnMvjgMy', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:47:12'),
('6807671', 'Ms.Natsuda Kaeowongbon', '', 'Officer', '092 1984981', '6807671-68d12ad219f44.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:39:35'),
('6807684', 'Mr.Siampath  Weerawitkul ', '', 'Manager', '089-1560099', '6807684-68d21b6b82ced.png', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:43:23'),
('6809693', 'Ms. Thirada Changlek ', '', 'Manager', '084-7526968', '6809693-68d0c38df0809.jpg', '6809693', '$2y$10$QaDpCpvAhhMuXom4IIFl9../WHaASr7T96jKXfeg7em1HxCFNX/Vi', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:47:01'),
('6809694', 'Mr.Phanu  Kaeasri', '', 'Engineer(Lv1)', '064-6475615', '6809694-68d21ba29ec73.png', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:44:54'),
('6809695', 'Mr. Theeraphong Phansri', NULL, 'Supervisor (Lv3)', '095-9294466', '6809695-68d23f70ced3b.png', '', '', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-22 14:59:40'),
('6809696', 'Mr. Teerapol  Haviros', '', 'Manager', '061-7306999', 'imageFile-68c8036c31f81.jpg', '', '', 'user', NULL, NULL, NULL, NULL, 'inactive', '2025-09-25 07:58:25', '2025-12-22 02:42:35'),
('6809697', 'Ms.Ratiros  Kaisorn', '', 'Assistant Manger', '082-6591459', '6809697-68d2106b9775d.jpg', '6809697', '$2y$10$TBIxD8mOHExOuWvS60vtcegbGYWiRRiWGUxrfl9SDwVwLH2HZf7Cy', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:47:58'),
('6809698', 'Ms. Natchaya  Klobklin', '', 'Officer', '061-36555522', '6809698-68d246d377f7a.jpg', '6809698', '$2y$10$Gv2.JxNgQXJwP8M3qtoDzeRcMU6E6JYoKlU9USRlWFdZNQAhUdEha', 'user', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2025-12-23 01:47:22'),
('6811701', 'Mr.Witthawat Sonbun', 'นาย วิทวัฒน์ สอนบุญ', 'Engineer(Lv1)', '062-9194529', '6811701-69001eb139bb8.png', '6811701', '$2y$10$I7EyPR3tr31e9pSC/3HcFu9wJsvpYo875GLzsAPW8El20o.VzY/pW', 'user', NULL, NULL, NULL, NULL, 'active', '2025-10-28 01:38:57', '2025-12-23 01:45:42'),
('6901702', 'Mr. Dusit Sajjawatthanawimol', 'นาย ดุสิต สัจจะวัฒนวิมล', 'Digital Transformation Analyst Manager', '089-8322332', 'emp_694b457929c0c.png', '6901702', '$2y$10$OmMra2O9.mBo2OMG85scSuspoBiCnQNhx/2gGsf3cF11tCUq/gQX.', 'admin', NULL, NULL, NULL, '2026-01-05', 'active', '2025-12-22 01:57:27', '2025-12-24 01:44:25'),
('8', 'Ms. Pornpapat Phokintechanon', '', 'Officer', '092 5162694', '8-68d218947b02e.jpg', 'User', '$2y$10$o8e7IAcckh2NzWJT20Wg0esjfEOswaILyog7qzxJqNYpvobN.UZF.', 'staff', NULL, NULL, NULL, NULL, 'active', '2025-09-25 07:58:25', '2026-01-09 14:08:33');

-- --------------------------------------------------------

--
-- Table structure for table `employee_assignments`
--

CREATE TABLE `employee_assignments` (
  `assignment_id` int(11) NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `company` varchar(50) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employee_assignments`
--

INSERT INTO `employee_assignments` (`assignment_id`, `employee_id`, `company`, `department`, `email`, `is_primary`) VALUES
(277, '5507089', 'PT4', 'Management', 'prapaphan.pichaikham@powertech2004.com', 1),
(278, '5507089', 'PTA', 'Management', 'prapaphan.pichaikham@powertechassembly.com', 0),
(279, '5507089', 'PTE', 'Management', 'prapaphan.pichaikham@powertechenergy.co.th', 0),
(315, '5407049', 'PT4', 'Production', 'dacha@powertech2004.com', 1),
(397, '6708653', 'PTE', 'Maintenance', 'pathomphong.phengcharoen@powertechenergy.co.th', 1),
(408, '6102312', 'PT4', 'Quality Management', 'ernie@powertech2004.com', 1),
(409, '6102312', 'PTE', 'Quality Management', 'ernieyusnita.ismail@powertechenergy.co.th', 0),
(427, '6105319', 'PT4', 'Production', 'phitak.jenjai@powertech2004.com', 1),
(428, '5811258', 'PTE', 'Production Engineer', 'chanon.moongmai@powertechenergy.co.th', 1),
(437, '6506568', 'PTE', 'Quality Management', 'worawoot.detudom@powertechenergy.co.th', 1),
(441, '5406037', 'PT4', 'Quality Management', 'manote@powertech2004.com', 1),
(442, '5406036', 'PT4', 'Quality Management', 'somchai.meanthim@powertech2004.com', 1),
(444, '6809695', 'PTE', 'Quality Management', 'theeraphong.phansri@powertechenergy.co.th', 1),
(445, '6207444', 'PTE', 'Quality Management', 'natapong.orapin@powertechenergy.co.th', 1),
(446, '5407058', 'PT4', 'Quality Management', 'seelan@powertech2004.com', 1),
(447, '5406027', 'PT4', 'Production', 'samart.jitthaisong@powertech2004.com', 1),
(462, '6804666', 'PTA', 'Accounting', 'korawee.songserm@powertechassembly.com', 1),
(463, '6803665', 'PTA', 'Accounting', 'suphitchaya.chaithanee@powertechassembly.com', 1),
(471, '5810218', 'PTA', 'Production', 'sathita.srilachai@powertechassembly.com', 1),
(479, '6511594', 'PTA', 'Production Engineer', 'chawalit.krasprapan@powertechassembly.com', 1),
(480, '6508579', 'PTA', 'Production Engineer', 'chanaphat.wattiwong@powertechassembly.com', 1),
(485, '5807190', 'PTA', 'Production', 'manop.umnangrong@powertechassembly.com', 1),
(487, '5805184', 'PTA', 'Quality Management', 'thichutha.rungruang@powertechassembly.com', 1),
(494, '5811252', 'PT4', 'Quality Management', 'peng.boonkrang@powertech2004.com', 1),
(497, '6109362', 'PT4', 'Logistics', 'jariya.thongkanha@powertech2004.com', 1),
(498, '6109362', 'PTE', 'Logistics', 'jariya.thongkanha@powertechenergy.co.th', 0),
(502, '6403472', 'PTE', 'Quality Management', 'natthaphon.daorueangsri@powertechenergy.co.th', 1),
(503, '5908284', 'PT4', 'Safety & Environment', 'chanyanut.uangtidchai@powertech2004.com', 1),
(509, '5406033', 'PT4', 'Maintenance', 'Puckaporn@powertech2004.com', 1),
(510, '5802155', 'PT4', 'Maintenance', 'weerasak.k@powertech2004.com', 1),
(515, '5609111', 'PTA', 'Accounting', 'phatira.suwanna@powertechassembly.com', 1),
(525, '5410063', 'PT4', 'Maintenance', 'teerayut@powertech2004.com', 1),
(526, '5410063', 'PTE', 'Maintenance', 'teerayut.tongon@powertechenergy.co.th', 0),
(531, '6805667', 'PTA', 'Accounting', 'chatpawee.hiranphatcharanon@powertechassembly.com', 1),
(532, '6805667', 'PTE', 'Accounting', 'chatpawee.hiranphatcharanon@powertechenergy.co.th', 0),
(547, '6704644', 'PT4', 'Maintenance', 'nattawut.deelerd@powertech2004.com', 1),
(548, '6704644', 'PTE', 'Maintenance', 'nattawut.deelerd@powertechenergy.co.th', 0),
(553, '5804168', 'PTA', 'Quality Management', 'pornchai.traipherm@powertechassembly.com', 1),
(554, '5804168', 'PTE', 'Quality Management', 'pornchai.traipherm@powertechenergy.co.th', 0),
(555, '5902267', 'PT4', 'Production', 'nutthachai.yuwaphunkhul@powertech2004.com', 1),
(571, '5610115', 'PT4', 'Logistics', 'chanicha.sabmun@powertech2004.com', 1),
(597, '6604614', 'PTA', 'IT', 'kritsada.suksawat@powertechassembly.com', 1),
(598, '6604614', 'PT4', 'IT', '', 0),
(599, '6604614', 'PTE', 'IT', '', 0),
(638, '6710660', 'PT4', 'Accounting', 'sirinya.tidchai@powertech2004.com', 1),
(639, '6710660', 'PTA', 'Accounting', '', 0),
(640, '6710660', 'PTE', 'Accounting', '', 0),
(641, '6809696', 'PT4', 'Quality Management', 'teerapol.haviros@powertech2004.com', 1),
(642, '6809696', 'PTA', 'Quality Management', 'teerapol.haviros@powertechassembly.com', 0),
(644, '6709654', 'PTE', 'Safety & Environment', 'sumitra.sengda@powertechenergy.co.th', 1),
(645, '6106330', 'PT4', 'Management', 'siriporn.ruthirawut@powertech2004.com', 1),
(646, '6106330', 'PTA', 'Management', 'siriporn.ruthirawut@powertechassembly.com', 0),
(647, '6106330', 'PTE', 'Management', 'siriporn.ruthirawut@powertechenergy.co.th', 0),
(658, '6807671', 'PTA', 'HR and GA', 'natsuda.kaeowongbon@powertechassembly.com', 1),
(659, '6806669', 'PT4', 'IT', 'lalin.nuchsawart@powertech2004.com', 1),
(660, '5804169', 'PTA', 'Logistics', 'wanna.suwannarun@powertechassembly.com', 1),
(661, '5804169', 'PTE', 'Logistics', 'wanna.suwannarun@powertechenergy.co.th', 0),
(662, '6709659', 'PT4', 'Logistics', 'chanisa.wongnim@powertech2004.com', 1),
(663, '6504550', 'PT4', 'Logistics', 'uraiwan.songwaree@powertech2004.com', 1),
(664, '6207445', 'PTA', 'Logistics', 'chuthamard.suchart@powertechassembly.com', 1),
(665, '5406019', 'PT4', 'Logistics', 'nanthaya@powertech2004.com', 1),
(666, '5802161', 'PTA', 'Logistics', 'prayoon.piakaenkaew@powertechassembly.com', 1),
(667, '5808198', 'PT4', 'Logistics', 'nathaphorn.tachaphornrat@powertech2004.com', 1),
(668, '5808198', 'PTE', 'Logistics', 'nathaphorn.tachaphornrat@powertechenergy.co.th', 0),
(669, '5411065', 'PTA', 'Maintenance', 'suwachai.haiyakhotr@powertechassembly.com', 1),
(670, '5405010', 'PTE', 'Management', 'anan.atthajak@powertechenergy.co.th', 1),
(671, '6807684', 'PTE', 'Production', 'siampath.weerawitkul@powertechenergy.co.th', 1),
(672, '5406048', 'PT4', 'Production', 'natchanan@powertech2004.com', 1),
(673, '5809205', 'PTE', 'Production Engineer', 'jakkrit.thudthong@powertechenergy.co.th', 1),
(674, '5806185', 'PTE', 'Production Engineer', 'jarong.boonsom@powertechenergy.co.th', 1),
(675, '6107349', 'PTA', 'Production Engineer', 'adisorn.detnonsri@powertechassembly.com', 1),
(676, '6809694', 'PTE', 'Production Engineer', 'phanu.kaeosri@powertechengergy.co.th', 1),
(677, '6709656', 'PTE', 'Quality Management', 'kantaphon.tirasriwat@powertechenergy.co.th', 1),
(678, '6709656', 'PTA', 'Quality Management', 'kantaphon.tirasriwat@powertechassembly.com', 0),
(679, '6005298', 'PT4', 'Quality Management', 'kiatisak.areepong@powertech2004.com', 1),
(680, '6811701', 'PTE', 'Quality Management', 'witthawat.sonbun@powertechenergy.co.th', 1),
(681, '5909292', 'PTA', 'Quality Management', 'ilada.pumanwat@powertechassembly.com', 1),
(682, '6711661', 'PTA', 'Quality Management', 'panidh.mingmalairak@powertechassembly.com', 1),
(683, '6412512', 'PTA', 'Quality Management', 'intira.singpee@powertechassembly.com', 1),
(687, '6806670', 'PT4', 'Accounting', 'kanyanat.patkulkan@powertech2004.com', 1),
(688, '6806670', 'PTE', 'Accounting', 'kanyanat.patkulkan@powertechenergy.co.th', 0),
(689, '6809698', 'PT4', 'Accounting', 'natchaya.klobklin@powertech2004.com', 1),
(690, '6803664', 'PT4', 'Accounting', 'panida.bumpenkit@powertech2004.com', 1),
(691, '6809697', 'PT4', 'HR and GA', 'ratiros.kaisorn@powertech2004.com', 1),
(692, '6809697', 'PTA', 'HR and GA', 'ratiros.kaisorn@powertechassembly.com', 0),
(693, '6809697', 'PTE', 'HR and GA', 'ratiros.kaisorn@powertechenergy.co.th', 0),
(697, '5807187', 'PT4', 'HR and GA', 'nutnicha.aree@powertech2004.com', 1),
(698, '5807187', 'PTA', 'HR and GA', 'nutnicha.aree@powertechassembly.com', 0),
(699, '5807187', 'PTE', 'HR and GA', 'nutnicha.aree@powertechenergy.co.th', 0),
(700, '5406014', 'PT4', 'HR and GA', 'thasipha.amornnaranon@powertech2004.com', 1),
(701, '5406014', 'PTE', 'HR and GA', 'thasipha.amornnaranon@powertechenergy.co.th', 0),
(702, '5901264', 'PT4', 'HR and GA', 'warinthon.kanchanakulsawas@powertech2004.com', 1),
(713, '6901702', 'PTA', 'IT', 'dusit.sajjawatthanawimol@powertechassembly.com', 1),
(714, '6901702', 'PT4', 'IT', 'dusit.sajjawatthanawimol@powertech2004.com', 0),
(715, '6901702', 'PTE', 'IT', 'dusit.sajjawatthanawimol@powertechenergy.co.th', 0),
(716, '6107339', 'PTA', 'HR and GA', 'thanyakarn.t@powertechassembly.com', 1),
(717, '6107339', 'PTE', 'HR and GA', 'Thanyakarn.T@powertechenergy.co.th', 0),
(718, '5907282', 'PTA', 'HR and GA', 'chanita.waiyamas@powertechassembly.com', 1),
(719, '5907282', 'PTE', 'HR and GA', 'chanita.waiyamas@powertechenergy.co.th', 0),
(720, '6809693', 'PT4', 'Accounting', 'thirada.changlek@powertech2004.com', 1),
(721, '6809693', 'PTA', 'Accounting', 'thirada.changlek@powertechassembly.com', 0),
(722, '6809693', 'PTE', 'Accounting', 'thirada.changlek@powertechenergy.co.th', 0),
(723, '5404005', 'PT4', 'HR and GA', 'thannaree@powertech2004.com', 1),
(724, '5404005', 'PTA', 'HR and GA', 'thannaree.sitthilertnarong@powertechassembly.com', 0),
(725, '5404005', 'PTE', 'HR and GA', 'thannaree.sitthilertnarong@powertechenergy.co.th', 0),
(726, '5507087', 'PT4', 'Management', 'michaelw@powertech2004.com', 1),
(727, '5507087', 'PTA', 'Management', 'michael.welser@powertechassembly.com', 0),
(728, '5507087', 'PTE', 'Management', 'michael.welser@powertechenergy.co.th', 0),
(729, '5810236', 'PT4', 'IT', 'somya.wongyapan@powertech2004.com', 1),
(730, '5810236', 'PTE', 'IT', 'somya.wongyapan@powertechenergy.co.th', 0),
(731, '5810236', 'PTA', 'IT', 'somya.wongyapan@powertechassembly.com', 0),
(732, '8', 'PT4', 'HR and GA', 'pornpapat.phokintechanon@powertech2004.com', 1),
(733, '5808200', 'PTA', 'IT', 'kasidet.phokintechanon@powertechassembly.com', 1),
(734, '5808200', 'PT4', 'IT', '', 0),
(735, '5808200', 'PTE', 'IT', '', 0);

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `holidays`
--

INSERT INTO `holidays` (`id`, `date`, `name`, `type`) VALUES
(4, '2025-01-10', 'วันหยุดบริษัท', 'company'),
(5, '2025-01-29', 'วันตรุษจีน', 'company'),
(6, '2025-02-12', 'วันมาฆบูชา', 'public'),
(7, '2025-04-07', 'ชดเชยวันจักรี', 'public'),
(8, '2025-04-14', 'วันสงกรานต์', 'public'),
(9, '2025-04-15', 'วันสงกรานต์', 'public'),
(10, '2025-05-01', 'วันแรงงานแห่งชาติ', 'public'),
(11, '2025-05-02', 'วันหยุดบริษัท', 'company'),
(12, '2025-05-05', 'วันฉัตรมงคล', 'public'),
(13, '2025-05-09', 'วันหยุดบริษัท', 'company'),
(14, '2025-05-12', 'วันวิสาขบูชา', 'public'),
(15, '2025-06-03', 'วันเฉลิมฯ พระราชินี', 'public'),
(16, '2025-06-06', 'วันหยุดพิเศษ', 'company'),
(17, '2025-06-09', 'วันหยุดบริษัท', 'company'),
(18, '2025-07-11', 'วันอาสาฬหบูชา', 'public'),
(19, '2025-07-14', 'วันหยุดพิเศษ', 'company'),
(20, '2025-07-28', 'วันเฉลิมฯ ร.10', 'public'),
(21, '2025-08-11', 'วันหยุดบริษัท', 'company'),
(22, '2025-08-12', 'วันแม่แห่งชาติ', 'public'),
(23, '2025-09-12', 'วันหยุดบริษัท', 'company'),
(25, '2025-10-13', 'วันคล้ายวันสวรรคต ร.9', 'public'),
(26, '2025-10-23', 'วันปิยมหาราช', 'public'),
(27, '2025-10-24', 'วันหยุดพิเศษ', 'company'),
(28, '2025-11-28', 'วันหยุดบริษัท', 'company'),
(29, '2025-12-05', 'วันพ่อแห่งชาติ', 'public'),
(30, '2025-12-10', 'วันรัฐธรรมนูญ', 'public'),
(31, '2025-12-22', 'วันหยุดบริษัท', 'company'),
(32, '2025-12-23', 'วันหยุดบริษัท', 'company'),
(33, '2025-12-24', 'วันหยุดพิเศษ', 'company'),
(34, '2025-12-25', 'วันหยุดบริษัท', 'company'),
(35, '2025-12-26', 'วันหยุดบริษัท', 'company'),
(36, '2025-12-31', 'วันสิ้นปี', 'company'),
(37, '2025-09-19', 'test', 'public'),
(38, '2025-10-21', 'วันหยุดส่วนตัว', 'company'),
(39, '2025-10-16', 'ๅ/-ๅ/-ๅ/ภถถ', 'public'),
(40, '2025-12-27', 'วันหยุดบริษัท', 'company'),
(41, '2025-12-29', 'วันหยุดบริษัท', 'company'),
(42, '2026-01-01', 'วันหยุดบริษัท', 'company');

-- --------------------------------------------------------

--
-- Table structure for table `it_assets`
--

CREATE TABLE `it_assets` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `equipment_id` varchar(100) NOT NULL COMMENT 'ID อุปกรณ์ (ตัวหลัก)',
  `name` varchar(255) NOT NULL COMMENT 'ชื่ออุปกรณ์',
  `brand` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `owning_department` int(11) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL COMMENT 'IP Address',
  `mac_address` varchar(50) DEFAULT NULL COMMENT 'MAC Address',
  `company` varchar(255) DEFAULT NULL,
  `custodian_employee_id` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL COMMENT 'รายละเอียด',
  `spec_details` text DEFAULT NULL COMMENT 'สเปคโดยละเอียด',
  `image_url` text DEFAULT NULL COMMENT 'รูปภาพ (URL)',
  `images` text DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'ว่าง' COMMENT 'สถานะ',
  `is_loanable` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = For Loan, 0 = Not for Loan',
  `asset_pool` varchar(50) NOT NULL DEFAULT 'general',
  `borrower` varchar(255) DEFAULT NULL COMMENT 'ผู้ยืม',
  `department` varchar(100) DEFAULT NULL COMMENT 'แผนกผู้ยืม',
  `borrower_id` varchar(50) DEFAULT NULL,
  `expected_return_date` date DEFAULT NULL,
  `serial_number` varchar(255) DEFAULT NULL COMMENT 'Serial Number',
  `asset_id` varchar(255) DEFAULT NULL COMMENT 'เลขทะเบียนทรัพย์สิน (จากบัญชี)',
  `asset_category` int(11) DEFAULT NULL COMMENT 'หมวดหมู่อุปกรณ์',
  `purchase_date` date DEFAULT NULL COMMENT 'วันที่ซื้อ',
  `warranty_expires_on` date DEFAULT NULL,
  `purchase_price` decimal(10,2) DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL COMMENT 'ผู้จำหน่าย/ร้านค้า',
  `po_number` varchar(100) DEFAULT NULL COMMENT 'เลขที่ใบสั่งซื้อ',
  `invoice_number` varchar(100) DEFAULT NULL COMMENT 'เลขที่ใบกำกับภาษี',
  `current_value` decimal(10,2) DEFAULT NULL,
  `accessories` text DEFAULT NULL,
  `purpose` text DEFAULT NULL COMMENT 'วัตถุประสงค์ที่ขอ',
  `return_date` date DEFAULT NULL COMMENT 'วันกำหนดคืน (จาก loan.html)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `it_assets`
--

INSERT INTO `it_assets` (`id`, `parent_id`, `equipment_id`, `name`, `brand`, `model`, `owning_department`, `location`, `ip_address`, `mac_address`, `company`, `custodian_employee_id`, `description`, `spec_details`, `image_url`, `images`, `status`, `is_loanable`, `asset_pool`, `borrower`, `department`, `borrower_id`, `expected_return_date`, `serial_number`, `asset_id`, `asset_category`, `purchase_date`, `warranty_expires_on`, `purchase_price`, `supplier`, `po_number`, `invoice_number`, `current_value`, `accessories`, `purpose`, `return_date`) VALUES
(24, NULL, 'PTA-MIS-NB-2512-004', 'Notebook ThinkPad E14', 'Lenovo', 'E14', 1, 'F4', '', '', 'PTA', NULL, NULL, '', 'uploads/equipment/PTA-MIS-NB-2511-001-6913df1ebc08d.jpg', NULL, 'ใช้งานปกติ', 1, 'general', 'Mr. Kasidet Phokintechanon', 'IT', '5808200', NULL, 'PF5LL07F', 'PTA-OE-202505442', 13, '2025-12-14', '2029-06-15', 0.00, '', '', '', NULL, '[]', 'ำำำำ', '2025-12-18'),
(25, NULL, 'PT4-MIS-OTR-2512-001', 'เครื่องบินขับไล่ F16', 'จีนแดง', 'GS2000', 1, 'IT Room F4', '', '', 'PTA', NULL, NULL, '', 'uploads/equipment/PTEMISOTR2512001-693f951a851fb.jpg', '[\"uploads\\/equipment\\/PTEMISOTR2512001-693f951a851fb.jpg\"]', 'ใช้งานปกติ', 1, 'general', NULL, NULL, NULL, NULL, '191', 'PTA-OE-2025054433', 14, '2025-12-16', '2025-12-24', 0.00, '', '', '', NULL, '[\"ล้อ\"]', NULL, NULL),
(26, NULL, 'PT4-MT-NT-0001', 'DELL Latitude 3440', 'DELL ', 'Latitude 3440', 6, 'F5 MT', '', '', 'PT4', NULL, NULL, '', 'http://localhost/PowertechCenter/uploads/equipment/PT4-MT-NT-0001-6960ce1a98f26-0.jpg', '[\"http:\\/\\/localhost\\/PowertechCenter\\/uploads\\/equipment\\/PT4-MT-NT-0001-6960ce1a98f26-0.jpg\"]', 'ใช้งานปกติ', 0, 'general', 'Ms.Teerayut Tong-on', NULL, NULL, NULL, '', '', 12, NULL, NULL, 0.00, '', '', '', NULL, '[]', NULL, NULL),
(27, NULL, 'PT4-MT-PC-0001', 'Computer', 'Computer SET', '', 6, 'F5 MT', '', '', 'PTA', NULL, NULL, 'จอ คอม เม้าคีบอด', 'http://localhost/PowertechCenter/uploads/equipment/PT4-MT-PC-0001-6960cf865a18d-0.jpg', '[\"http:\\/\\/localhost\\/PowertechCenter\\/uploads\\/equipment\\/PT4-MT-PC-0001-6960cf865a18d-0.jpg\",\"http:\\/\\/localhost\\/PowertechCenter\\/uploads\\/equipment\\/PT4-MT-PC-0001-6960cf865a628-1.jpg\"]', 'ใช้งานปกติ', 0, 'general', 'Mr.Suwachai Chaiyakhotr', NULL, NULL, NULL, '', 'PT4-OE-220325001', 11, NULL, NULL, 0.00, '', '', '', NULL, '[]', NULL, NULL),
(29, 27, 'PT4-MT-PC-0002', 'Monitor', 'Computer SET', '', 6, 'F5 MT', '', '', 'PTA', NULL, NULL, '', 'http://localhost/PowertechCenter/uploads/equipment/PT4-MT-PC-0002-6960df64458d2-0.jpg', '[\"http:\\/\\/localhost\\/PowertechCenter\\/uploads\\/equipment\\/PT4-MT-PC-0002-6960df64458d2-0.jpg\"]', 'ใช้งานปกติ', 0, 'general', 'Mr.Suwachai Chaiyakhotr', NULL, NULL, NULL, '', '', 19, NULL, NULL, 0.00, '', '', '', NULL, '[]', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `it_loan_history`
--

CREATE TABLE `it_loan_history` (
  `history_id` int(11) NOT NULL,
  `equipment_id` varchar(100) NOT NULL COMMENT 'ID อุปกรณ์',
  `equipment_name` varchar(255) NOT NULL COMMENT 'ชื่ออุปกรณ์',
  `borrower` varchar(255) NOT NULL COMMENT 'ผู้ยืม',
  `department` varchar(100) DEFAULT NULL COMMENT 'แผนก',
  `borrower_id` varchar(50) DEFAULT NULL,
  `purpose` text DEFAULT NULL COMMENT 'วัตถุประสงค์',
  `expected_return_date` date DEFAULT NULL,
  `approver_name` varchar(255) DEFAULT NULL,
  `borrower_signature` varchar(255) DEFAULT NULL COMMENT 'เก็บ path/URL รูปไฟล์ลายเซ็นผู้ยืม',
  `return_approver_signature` varchar(255) DEFAULT NULL COMMENT 'เก็บ path/URL รูปไฟล์ลายเซ็นผู้รับคืน',
  `condition_notes` text DEFAULT NULL,
  `lend_date` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'วันที่ยืม',
  `return_date` datetime DEFAULT NULL COMMENT 'วันที่คืน',
  `return_receiver_name` varchar(255) DEFAULT NULL,
  `return_condition` varchar(255) DEFAULT NULL,
  `return_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `it_loan_history`
--

INSERT INTO `it_loan_history` (`history_id`, `equipment_id`, `equipment_name`, `borrower`, `department`, `borrower_id`, `purpose`, `expected_return_date`, `approver_name`, `borrower_signature`, `return_approver_signature`, `condition_notes`, `lend_date`, `return_date`, `return_receiver_name`, `return_condition`, `return_notes`) VALUES
(1, 'PT4-MIS-OTR-2512-001', 'เครื่องบินขับไล่ F16', 'Mr. Kasidet Phokintechanon', 'IT', '5808200', 'ยืมบินวันเด็ก\n', '2026-01-10', 'Mr. Kasidet Phokintechanon', 'uploads/signatures/pending-696077dbab66b.png', 'uploads/signatures/active-6960bb1a1d049.png', NULL, '2026-01-09 10:36:59', '2026-01-09 15:23:54', 'Mr. Kasidet Phokintechanon', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `it_logs`
--

CREATE TABLE `it_logs` (
  `id` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `company` varchar(50) DEFAULT NULL,
  `requester` varchar(255) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `asset_id` varchar(100) DEFAULT NULL COMMENT 'รหัสทรัพย์สินที่เกี่ยวข้อง',
  `requester_id` varchar(50) DEFAULT NULL,
  `servicedBy` varchar(255) DEFAULT NULL,
  `servicedById` varchar(50) DEFAULT NULL,
  `asset` varchar(100) DEFAULT NULL,
  `problem` text NOT NULL,
  `contact` varchar(100) DEFAULT NULL COMMENT 'เบอร์ติดต่อกลับ',
  `image_path` varchar(255) DEFAULT NULL COMMENT 'รูปภาพประกอบการแจ้งซ่อม',
  `solution` text DEFAULT NULL,
  `repair_cost` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `rating` int(1) DEFAULT NULL COMMENT 'คะแนนความพึงพอใจ 1-5',
  `completed_date` datetime DEFAULT NULL,
  `last_work_start_time` datetime DEFAULT NULL COMMENT 'เวลาที่เริ่มทำงานล่าสุด',
  `total_work_duration_seconds` int(11) NOT NULL DEFAULT 0 COMMENT 'เวลารวมที่ทำงาน (วินาที)',
  `type` varchar(50) NOT NULL,
  `urgency` varchar(100) DEFAULT 'ปกติ',
  `notes` text DEFAULT NULL,
  `startDate` date DEFAULT NULL,
  `endDate` date DEFAULT NULL,
  `appointment_date` datetime DEFAULT NULL,
  `closed_date` datetime DEFAULT NULL,
  `evaluation_hardware` text DEFAULT NULL COMMENT 'ผลประเมินฮาร์ดแวร์',
  `evaluation_software` text DEFAULT NULL COMMENT 'ผลประเมินซอฟต์แวร์',
  `evaluation_repair_cost` decimal(10,2) DEFAULT NULL COMMENT 'ประมาณการค่าใช้จ่ายซ่อม',
  `evaluation_recommendation` varchar(20) DEFAULT NULL COMMENT 'ข้อแนะนำ: repair/replace',
  `evaluation_reason` text DEFAULT NULL COMMENT 'เหตุผลประกอบการตัดสินใจ',
  `pr_no` varchar(50) DEFAULT NULL,
  `po_no` varchar(50) DEFAULT NULL,
  `vendor_name` varchar(255) DEFAULT NULL,
  `eta_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `it_logs`
--

INSERT INTO `it_logs` (`id`, `date`, `company`, `requester`, `department`, `asset_id`, `requester_id`, `servicedBy`, `servicedById`, `asset`, `problem`, `contact`, `image_path`, `solution`, `repair_cost`, `status`, `rating`, `completed_date`, `last_work_start_time`, `total_work_duration_seconds`, `type`, `urgency`, `notes`, `startDate`, `endDate`, `appointment_date`, `closed_date`) VALUES
('PT4-MIS260109-01', '2026-01-09', 'PTA', 'Mr. Kasidet Phokintechanon', 'IT', '', '5808200', 'Mr. Kasidet Phokintechanon', '5808200', NULL, '123\n\n[Contact]: 0939259989', NULL, NULL, 'lkjhf[9/1/2569 21:42:44] พักงาน (Waiting for Parts): sadasdsadsa\n[16/1/2569 09:47:15] พักงาน (Waiting for Parts): 2', NULL, 'Completed', NULL, '2026-01-16 09:47:23', NULL, 38, 'Hardware', 'ปกติ', NULL, NULL, NULL, '2026-01-09 21:41:00', NULL),
('PTA-MIS260107-02', '2026-01-07', 'PTA', 'Ms. Chanita Waiyamas', 'HR and GA', '', '5907282', 'Mr. Kasidet Phokintechanon', '5808200', NULL, 'พัดลมไม่เย็น', NULL, NULL, '[7/1/2569 13:34:27] พักงาน (Waiting for Parts): -\n[7/1/2569 13:34:48] พักงาน (Temporary Fix): -\n[7/1/2569 13:36:59] พักงาน (Temporary Fix): 111\n[7/1/2569 13:38:26] พักงาน (Waiting for Parts): -', NULL, 'Completed', NULL, '2026-01-07 13:38:56', NULL, 80, 'Software', 'ปกติ', NULL, NULL, NULL, NULL, NULL),
('PTA-MIS260107-03', '2026-01-07', 'PTA', 'Ms. Chanita Waiyamas', 'HR and GA', '', '5907282', 'Mr. Kasidet Phokintechanon', '5808200', NULL, 'คอมไม่ติด', NULL, NULL, '[7/1/2569 13:56:38] พักงาน (Waiting for Parts): พักรออะไหล่', NULL, 'Completed', 5, '2026-01-07 14:04:11', NULL, 573, 'Other', 'ปกติ', NULL, NULL, NULL, NULL, NULL),
('PTA-MIS260107-04', '2026-01-07', 'PTA', 'Ms. Chanita Waiyamas', 'HR and GA', '', '5907282', 'Mr. Kasidet Phokintechanon', '5808200', NULL, 'ฝาหลุด', NULL, NULL, '112312', NULL, 'Completed', NULL, '2026-01-07 14:33:04', NULL, 1265, 'Software', 'ด่วนที่สุด', NULL, NULL, NULL, NULL, NULL),
('PTA-MIS260107-05', '2026-01-07', 'PTA', 'Ms. Chanita Waiyamas', 'HR and GA', '', '5907282', 'Mr. Kasidet Phokintechanon', '5808200', NULL, 'ติดตั้งโปรแกรม', NULL, NULL, 'ๅๅ', NULL, 'Completed', NULL, '2026-01-07 14:14:05', NULL, 73, 'Software', 'ปกติ', NULL, NULL, NULL, NULL, NULL),
('PTA-MIS260107-06', '2026-01-07', 'PTA', 'Ms. Chanita Waiyamas', 'HR and GA', '', '5907282', 'Mr. Kasidet Phokintechanon', '5808200', NULL, 'โปรแกรมหาย', NULL, NULL, '[7/1/2569 14:34:23] พักงาน (Waiting for Parts): -', NULL, 'Completed', NULL, '2026-01-07 14:34:46', NULL, 17, 'Software', 'ด่วน', NULL, NULL, NULL, NULL, NULL),
('PTA-MIS260107-07', '2026-01-07', 'PTA', 'Ms. Chanita Waiyamas', 'HR and GA', '', '5907282', 'Mr. Kasidet Phokintechanon', '5808200', NULL, 'โปรแกรมหาย\n\n[Contact]: 0957936209', NULL, NULL, 'ๅ/-ๅ/-ภๅ', NULL, 'Completed', 5, '2026-01-07 15:02:12', NULL, 136, 'Software', 'ด่วนที่สุด', NULL, NULL, NULL, NULL, NULL),
('PTA-MIS260110-01', '2026-01-10', 'PTA', 'Mr. Kasidet Phokintechanon', 'IT', '', '5808200', 'Mr. Kasidet Phokintechanon', '5808200', NULL, 'test\n\n[Contact]: 0939259989\n[CC Email]: kasidet.phokintechanon@powertechassembly.com', NULL, NULL, 'กดัีรนย', NULL, 'Completed', 5, '2026-01-10 21:55:09', NULL, 39, 'Software', 'ปกติ', NULL, NULL, NULL, '2026-01-10 21:48:00', NULL),
('PTA-MIS260110-02', '2026-01-10', 'PTA', 'Mr. Kasidet Phokintechanon', 'IT', '', '5808200', 'Mr. Kasidet Phokintechanon', '5808200', NULL, 'ฟกหกะกห\n\n[Contact]: 0939259989\n[CC Email]: kasidet.phokintechanon@powertechassembly.com', NULL, NULL, 'wqeqwe', NULL, 'Completed', NULL, '2026-01-16 14:41:51', NULL, 1803, 'Network', 'ปกติ', NULL, NULL, NULL, '2026-01-10 21:58:00', NULL),
('PTA-MIS260110-03', '2026-01-10', 'PTA', 'Mr. Kasidet Phokintechanon', 'IT', '', '5808200', 'Mr. Kasidet Phokintechanon', '5808200', NULL, 'ฟกหกะกห\n\n[Contact]: 0939259989\n[CC Email]: kasidet.phokintechanon@powertechassembly.com', NULL, NULL, 'qwetgff', NULL, 'Completed', NULL, '2026-01-10 22:35:18', NULL, 1804, 'Network', 'ปกติ', NULL, NULL, NULL, '2026-01-10 21:58:00', NULL),
('PTA-MIS260110-04', '2026-01-10', 'PTA', 'Mr. Kasidet Phokintechanon', 'IT', '', '5808200', 'Mr. Kasidet Phokintechanon', '5808200', NULL, 'ๅ/-/ๅ-\n\n[Contact]: 0939259989\n[CC Email]: kasidet.phokintechanon@powertechassembly.com', NULL, NULL, 'dsfdsfsdf', NULL, 'Completed', NULL, '2026-01-10 22:35:25', NULL, 1808, 'Software', 'ปกติ', NULL, NULL, NULL, '2026-01-10 22:04:00', NULL),
('PTA-MIS260115-01', '2026-01-15', 'PTA', 'Mr. Kasidet Phokintechanon', 'IT', '', '5808200', 'Mr. Kasidet Phokintechanon', '5808200', NULL, 'ๅ/-/ๅ-/ๅ\n\n[Contact]: 0939259989\n[CC Email]: kasidet.phokintechanon@powertechassembly.com', NULL, NULL, 'หพเ', NULL, 'Completed', NULL, '2026-01-15 11:35:05', NULL, 56, 'Network', 'ปกติ', NULL, NULL, NULL, '2026-01-15 11:28:00', NULL),
('PTE-MIS260111-01', '2026-01-11', 'PTE', 'Mr. Kasidet Phokintechanon', 'IT', '', '5808200', 'Mr. Kasidet Phokintechanon', '5808200', NULL, 'ฟดหกดหกด\n\n[Contact]: 0939259989', NULL, NULL, 'เรียบร้อย', NULL, 'Completed', NULL, '2026-01-11 08:36:18', NULL, 67, 'Hardware', 'ปกติ', NULL, NULL, NULL, '2026-01-11 08:34:00', NULL),
('PTE-MIS260111-02', '2026-01-11', 'PTE', 'Mr. Kasidet Phokintechanon', 'IT', '', '5808200', 'Mr. Kasidet Phokintechanon', '5808200', NULL, '123123\n\n[Contact]: 0939259989', NULL, NULL, 'eeeeeee', NULL, 'Completed', NULL, '2026-01-11 08:57:10', NULL, 20, 'Software', 'ปกติ', NULL, NULL, NULL, '2026-01-11 08:56:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `it_log_attachments`
--

CREATE TABLE `it_log_attachments` (
  `id` int(11) NOT NULL,
  `log_id` varchar(50) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `it_log_attachments`
--

INSERT INTO `it_log_attachments` (`id`, `log_id`, `original_filename`, `stored_filename`, `uploaded_at`) VALUES
(31, 'PTA-MIS260107-07', 'Screenshot2026-01-07083412.png', 'log_PTA-MIS260107-07_695e13054b2c8.png', '2026-01-07 08:02:13'),
(32, 'PTA-MIS260110-04', 'pasted_image_1768057480129.jpg', 'log_PTA-MIS260110-04_69626a95704b8.jpg', '2026-01-10 15:04:53'),
(33, 'PTE-MIS260111-01', 'Screenshot2026-01-07080206.png', 'log_PTE-MIS260111-01_6962fe9384ba5.png', '2026-01-11 01:36:19');

-- --------------------------------------------------------

--
-- Table structure for table `it_log_comments`
--

CREATE TABLE `it_log_comments` (
  `id` int(11) NOT NULL,
  `log_id` varchar(50) NOT NULL COMMENT 'เชื่อมโยงกับ it_logs.id',
  `user_id` varchar(50) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `comment` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_tickets`
--

CREATE TABLE `job_tickets` (
  `id` int(11) NOT NULL,
  `company` varchar(10) NOT NULL COMMENT 'PT4, PTA, PTE',
  `department` varchar(100) DEFAULT NULL,
  `building` varchar(255) DEFAULT NULL COMMENT 'รายชื่อตึก',
  `job_name` varchar(255) NOT NULL,
  `responsible` varchar(255) DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `is_receive` tinyint(1) DEFAULT 0,
  `is_doing` tinyint(1) DEFAULT 0,
  `is_send` tinyint(1) DEFAULT 0,
  `is_approve` tinyint(1) DEFAULT 0,
  `is_done` tinyint(1) DEFAULT 0,
  `last_update` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by_id` varchar(50) DEFAULT NULL,
  `created_by_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `job_tickets`
--

INSERT INTO `job_tickets` (`id`, `company`, `department`, `building`, `job_name`, `responsible`, `deadline`, `is_receive`, `is_doing`, `is_send`, `is_approve`, `is_done`, `last_update`, `created_by_id`, `created_by_name`) VALUES
(2, 'PTE', 'HR and GA', 'PTE', 'ล้างแอร์', 'ปาย', '2026-01-07', 0, 0, 0, 0, 0, '2026-01-09 15:22:14', '6107339', 'Thanyakarn Thanyahiranrat');

-- --------------------------------------------------------

--
-- Table structure for table `kb_articles`
--

CREATE TABLE `kb_articles` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `author_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `view_count` int(11) NOT NULL DEFAULT 0,
  `is_published` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `kb_articles`
--

INSERT INTO `kb_articles` (`id`, `category_id`, `title`, `content`, `tags`, `author_id`, `created_at`, `updated_at`, `view_count`, `is_published`) VALUES
(1, 1, 'วิธีเปลี่ยนรหัสผ่าน Windows', '# การเปลี่ยนรหัสผ่าน\n\n1. กดปุ่ม **Ctrl + Alt + Del**\n2. เลือกเมนู **Change a password**\n3. กรอกรหัสเดิมและรหัสใหม่\n4. กด Enter เพื่อยืนยัน', 'password, windows', NULL, '2025-12-21 16:25:57', '2026-01-09 15:04:01', 16, 1),
(2, 2, 'ต่อ WiFi ไม่ได้ทำอย่างไร?', 'หากพบปัญหาการเชื่อมต่อ WiFi ให้ลองทำตามนี้:\n\n- ตรวจสอบว่าเปิด WiFi หรือยัง\n- ลอง Forget Network แล้วต่อใหม่\n- แจ้ง IT หากยังใช้งานไม่ได้', 'wifi, internet', NULL, '2025-12-21 16:25:57', '2026-01-09 15:07:01', 8, 1),
(3, 3, 'การเข้าใช้งาน Printer ', '1. เข้าระหัส Printer โดยการใส่ ระหัสผนักงาน\n2. มีปัญหาติดต่อ IT', 'printer', '5808200', '2025-12-22 01:30:07', '2026-01-09 15:03:58', 6, 1),
(4, 4, 'ปริ้นไม่ออกทำอย่างไร (Printer Troubleshooting)', '# ปริ้นไม่ออกทำอย่างไร\n\n1. **ตรวจสอบกระดาษ**: ดูว่ากระดาษหมดหรือกระดาษติดหรือไม่\n2. **ตรวจสอบไฟสถานะ**: ดูที่เครื่องปริ้นเตอร์ว่ามีไฟกระพริบแจ้งเตือนหรือไม่\n3. **ตรวจสอบการเชื่อมต่อ**: สาย USB หรือสาย LAN หลวมหรือไม่\n4. **Restart**: ลองปิด-เปิดเครื่องปริ้นเตอร์ใหม่\n\nหากยังไม่ได้ผล กรุณาแจ้ง IT พร้อมระบุชื่อเครื่องปริ้นเตอร์', 'printer, paper jam, offline, ปริ้นไม่ออก', NULL, '2026-01-09 15:06:11', '2026-01-09 15:06:21', 1, 1),
(5, 6, 'วิธีเชื่อมต่อ WiFi สำหรับพนักงาน', '# การเชื่อมต่อ WiFi\n\n1. เลือกชื่อ WiFi: **Powertech-Staff**\n2. รหัสผ่าน: `P@wertech2024` (ตัวอย่าง)\n3. หากเชื่อมต่อไม่ได้ ให้ลองกด **Forget Network** แล้วเชื่อมต่อใหม่\n\n*หมายเหตุ: WiFi Guest สำหรับบุคคลภายนอกเท่านั้น*', 'wifi, internet, connect, ไวไฟ', NULL, '2026-01-09 15:06:11', '2026-01-09 15:07:30', 1, 1),
(6, 7, 'การตั้งค่าลายเซ็น (Signature) ใน Outlook', '# วิธีตั้งค่า Signature\n\n1. เปิด Outlook ไปที่ **File > Options**\n2. เลือก **Mail** > **Signatures...**\n3. กด **New** เพื่อสร้างลายเซ็นใหม่\n4. ใส่รายละเอียด ชื่อ, ตำแหน่ง, เบอร์โทร และโลโก้บริษัท\n5. กด OK เพื่อบันทึก\n\n*ควรใช้รูปแบบมาตรฐานตามที่ HR กำหนด*', 'outlook, email, signature, ลายเซ็น', NULL, '2026-01-09 15:06:11', '2026-01-09 15:07:14', 1, 1),
(7, 5, 'คอมพิวเตอร์ช้า เบื้องต้นควรทำอย่างไร', '# คอมพิวเตอร์ช้า\n\n1. **Restart เครื่อง**: การรีสตาร์ทช่วยเคลียร์หน่วยความจำและปิดโปรแกรมที่ค้างอยู่ได้\n2. **ปิดโปรแกรมที่ไม่ใช้**: ตรวจสอบ Taskbar ว่ามีโปรแกรมเปิดค้างไว้เยอะหรือไม่\n3. **ลบไฟล์ขยะ**: พิมพ์ `Disk Cleanup` ในช่องค้นหา Windows แล้วกด OK\n4. **สแกนไวรัส**: หากเครื่องช้าผิดปกติ อาจเกิดจากไวรัส', 'slow, performance, lag, คอมช้า', NULL, '2026-01-09 15:06:11', '2026-01-10 23:58:30', 2, 1),
(8, 8, 'ลืมรหัสผ่านเข้าระบบ (Forgot Password)', '# ลืมรหัสผ่าน\n\nหากท่านลืมรหัสผ่านเข้า Windows หรือเข้าระบบ ERP\n\n1. ติดต่อแผนก IT โทรภายใน **1234**\n2. หรือแจ้งผ่านระบบ Ticket เลือกหัวข้อ \'Reset Password\'\n3. เจ้าหน้าที่จะทำการรีเซ็ตและแจ้งรหัสชั่วคราวให้ทราบ\n\n**ข้อแนะนำ:** ควรเปลี่ยนรหัสผ่านทุกๆ 90 วัน', 'password, reset, login, ลืมรหัส', NULL, '2026-01-09 15:06:11', '2026-01-09 15:06:11', 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `kb_categories`
--

CREATE TABLE `kb_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(100) DEFAULT 'fa-book',
  `sort_order` int(11) DEFAULT 99
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `kb_categories`
--

INSERT INTO `kb_categories` (`id`, `name`, `description`, `icon`, `sort_order`) VALUES
(1, 'General IT', 'ปัญหาการใช้งานทั่วไป', 'fa-laptop', 1),
(2, 'Network & Internet', 'ปัญหาการเชื่อมต่อเครือข่าย', 'fa-wifi', 2),
(3, 'Printer', 'ปัญหาเครื่องพิมพ์ระหัสให้ใส่ระหัสผนักงาน', 'fa-print', 3),
(4, 'Hardware', 'ปัญหาเกี่ยวกับอุปกรณ์คอมพิวเตอร์ ปริ้นเตอร์ เมาส์ คีย์บอร์ด', 'fa-desktop', 99),
(5, 'Software', 'การใช้งานโปรแกรมต่างๆ Office, ERP, Windows', 'fa-window-maximize', 99),
(6, 'Network', 'อินเทอร์เน็ต, WiFi, VPN, เข้า Drive กลางไม่ได้', 'fa-wifi', 99),
(7, 'Email', 'การใช้งานอีเมล Outlook, การตั้งค่าลายเซ็น', 'fa-envelope', 99),
(8, 'Security', 'รหัสผ่าน, ไวรัส, ความปลอดภัยข้อมูล', 'fa-shield-alt', 99);

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `company` varchar(10) DEFAULT NULL COMMENT 'PTA, PT4, PTE or NULL for all',
  `is_active` tinyint(1) DEFAULT 1,
  `image` varchar(255) DEFAULT NULL COMMENT 'Path to map image',
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `name`, `company`, `is_active`, `image`, `sort_order`) VALUES
(1, 'Office F4', 'PTA', 1, NULL, 0),
(2, 'Office F5', 'PT4', 1, NULL, 0),
(3, 'Office F16', 'PTA', 1, NULL, 0),
(4, 'Production Line 1', 'PTA', 1, NULL, 0),
(5, 'Warehouse', 'PT4', 1, NULL, 0),
(6, 'Meeting Room', NULL, 1, NULL, 0),
(7, 'Canteen', NULL, 1, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 99,
  `button_text` varchar(100) DEFAULT 'เข้าใช้งาน',
  `url_primary` varchar(255) DEFAULT NULL,
  `url_fallback` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `title`, `description`, `icon`, `color`, `sort_order`, `button_text`, `url_primary`, `url_fallback`) VALUES
('IT -Service', 'ระบบแจ้งซ่อม ไอที', 'แจ้งซ่อม ไอที  โปรแกรม/Network', 'fas fa-cog', '#3b82f6', 1, 'เข้าใช้งาน', 'request.html', ''),
('kb', 'คลังความรู้ (KB)', 'ค้นหาวิธีแก้ปัญหาเบื้องต้น', 'fas fa-book-open', '#8b5cf6', 7, 'เข้าใช้งาน', 'kb.html', NULL),
('service-car', 'ระบบจองรถ', 'จองรถส่วนกลาง ออนไลน์ (CARSYS)', 'fas fa-car-side', '#22c55e', 3, 'เข้าใช้งาน', 'http:\\/\\/192.168.0.13\\/carsys\\/', 'http:\\/\\/110.170.174.149\\/carsys\\/'),
('service-contact', 'สมุดรายชื่อพนักงาน', 'ค้นหาและติดต่อเพื่อนร่วมงานได้สะดวกรวดเร็ว', 'fas fa-address-book', '#f97316', 8, 'เข้าใช้งาน', 'directory.html', ''),
('service-erp', 'ระบบ Micro ERP+', 'ระบบจัดการทรัพยากรขององค์กร', 'fas fa-warehouse', '#ec4899', 5, 'เข้าใช้งาน', 'http:\\/\\/192.168.0.11\\/microerp\\/authen', ''),
('service-it-loan', 'ยืม-คืนอุปกรณ์ IT', 'ระบบสำหรับยืมและคืนอุปกรณ์ IT ต่างๆ', 'fas fa-exchange-alt', '#6366f1', 6, 'เข้าใช้งาน', 'loan.html', ''),
('service-mt', 'ระบบแจ้งซ่อม MT', 'แจ้งปัญหาและติดตามสถานะการซ่อมบำรุง', 'fas fa-tools', '#3b82f6', 2, 'เข้าใช้งาน', 'http://192.168.0.18/SystemCenter/', 'http://110.170.174.148/SystemCenter/'),
('service-room', 'ระบบจองห้องประชุม', 'จองห้องประชุม ออนไลน์ (ROOMSYS)', 'fas fa-calendar-check', '#8b5cf6', 4, 'เข้าใช้งาน', 'http:\\/\\/192.168.0.13\\/roomsys\\/', 'http:\\/\\/110.170.174.149\\/roomsys\\/');

-- --------------------------------------------------------

--
-- Table structure for table `setup_history`
--

CREATE TABLE `setup_history` (
  `id` int(11) NOT NULL,
  `log_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `asset_code` varchar(100) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `pc_name` varchar(100) DEFAULT NULL,
  `user_company` varchar(50) DEFAULT NULL,
  `user_department` varchar(100) DEFAULT NULL,
  `user_name` varchar(255) NOT NULL,
  `setup_date` date NOT NULL,
  `tech_name` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `specific_software` text DEFAULT NULL,
  `checklist_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`checklist_data`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `setup_history`
--

INSERT INTO `setup_history` (`id`, `log_timestamp`, `asset_code`, `serial_number`, `pc_name`, `user_company`, `user_department`, `user_name`, `setup_date`, `tech_name`, `notes`, `specific_software`, `checklist_data`) VALUES
(1, '2025-09-26 13:04:36', '123124', '1244352', '356653334', 'PTA', 'IT', 'Kasidet.P', '2025-09-26', 'Kasidet.P', '', '', '{\"1\":true,\"2\":true,\"3\":true,\"4\":true,\"5\":true,\"6\":true,\"7\":true,\"8\":true,\"9\":true,\"10\":true,\"11\":true,\"12\":true,\"13\":true,\"14\":true,\"15\":true,\"16\":true,\"17\":true,\"18\":true,\"19\":true,\"20\":true,\"21\":true,\"22\":true,\"23\":true}'),
(2, '2025-09-26 13:57:34', '1', '12', '123', 'PT4', 'HR and GA', 'Ms. Pornpapat Phokintechanon', '2025-09-26', 'Kasidet.P', '', '', '{\"1\":true,\"2\":false,\"3\":false,\"4\":true,\"5\":false,\"6\":false,\"7\":false,\"8\":false,\"9\":false,\"10\":false,\"11\":false,\"12\":false,\"13\":false,\"14\":false,\"15\":false,\"16\":true,\"17\":false,\"18\":false,\"19\":true,\"20\":false,\"21\":false,\"22\":false,\"23\":true}'),
(3, '2025-09-26 14:21:53', 'TH-2025-02-002', 'TH5234266223', 'COM-2025010012', 'PTA', 'Accounting', 'Korawee Songserm', '2025-09-26', 'Mr. Somya Wongyapan', '', 'ติดตั้ง โปรแกรมบัญชี', '{\"1\":true,\"2\":true,\"3\":true,\"4\":true,\"5\":true,\"6\":true,\"7\":true,\"8\":true,\"9\":true,\"10\":true,\"11\":true,\"12\":true,\"13\":true,\"14\":true,\"15\":true,\"16\":true,\"17\":true,\"18\":true,\"19\":true,\"20\":true,\"21\":true,\"22\":true,\"23\":true}'),
(4, '2025-09-26 16:14:26', 'NB-003', '', 'Dell Inspiron 5468', 'PTE', 'IT', 'Mr. Kasidet phokintechanon', '2025-09-26', 'Kasidet.P', '', '', '{\"1\":true,\"2\":true,\"3\":false,\"4\":false,\"5\":false,\"6\":false,\"7\":false,\"8\":false,\"9\":false,\"10\":false,\"11\":false,\"12\":false,\"13\":false,\"14\":false,\"15\":false,\"16\":false,\"17\":false,\"18\":false,\"19\":false,\"20\":false,\"21\":false,\"22\":false,\"23\":false}');

-- --------------------------------------------------------

--
-- Table structure for table `supply_categories`
--

CREATE TABLE `supply_categories` (
  `cat_id` int(11) NOT NULL,
  `cat_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supply_categories`
--

INSERT INTO `supply_categories` (`cat_id`, `cat_name`) VALUES
(1, 'ปากกา'),
(2, 'เครื่องเขียน'),
(3, 'กระดาษ'),
(4, 'อุปกรณ์สำนักงาน'),
(5, 'วัสดุสิ้นเปลือง IT'),
(6, 'trtret');

-- --------------------------------------------------------

--
-- Table structure for table `supply_items`
--

CREATE TABLE `supply_items` (
  `item_id` int(11) NOT NULL,
  `item_code` varchar(50) DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `item_description` text DEFAULT NULL,
  `cat_id` int(11) DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `quantity_on_hand` int(11) NOT NULL DEFAULT 0,
  `quantity_reserved` int(11) NOT NULL DEFAULT 0,
  `min_stock_level` int(11) NOT NULL DEFAULT 0,
  `expiry_date` date DEFAULT NULL,
  `item_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supply_items`
--

INSERT INTO `supply_items` (`item_id`, `item_code`, `item_name`, `item_description`, `cat_id`, `unit_id`, `quantity_on_hand`, `quantity_reserved`, `min_stock_level`, `expiry_date`, `item_image`) VALUES
(5, 'STA-001', 'ปากกาลูกลื่น 0.7mm', NULL, NULL, 6, 200, 0, 50, NULL, 'uploads/items/STA-001_20250915_100349.jpg'),
(6, 'STA-002', 'สมุดโน้ต A5', NULL, NULL, 6, 120, 0, 30, NULL, 'uploads/items/STA-002_20250915_100400.jpg'),
(7, 'STA-003', 'กระดาษ A4 80gsm', NULL, NULL, 6, 80, 0, 20, NULL, 'uploads/items/STA-003_20250915_100332.jpg'),
(8, 'OFF-001', 'แฟ้มสันกว้าง A4', NULL, 4, 6, 60, 0, 10, NULL, NULL),
(9, 'IT-USB-64', 'แฟลชไดรฟ์ 64GB', NULL, 5, 6, 20, 0, 5, NULL, NULL),
(10, 'IT-MOUSE', 'เมาส์ USB', NULL, 5, 6, 30, 0, 5, NULL, NULL),
(17, '434556', 'ปากกา', NULL, NULL, 6, 155, 0, 0, NULL, 'uploads/items/434556_20250915_093218.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `supply_requisitions`
--

CREATE TABLE `supply_requisitions` (
  `req_id` int(11) NOT NULL,
  `request_by_id` varchar(100) DEFAULT NULL,
  `request_by_username` varchar(100) NOT NULL,
  `request_by_name` varchar(255) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `company` varchar(50) DEFAULT NULL,
  `request_date` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) NOT NULL DEFAULT 'Pending Manager',
  `approver_1_username` varchar(100) DEFAULT NULL,
  `approval_1_date` datetime DEFAULT NULL,
  `admin_dispense_username` varchar(100) DEFAULT NULL,
  `dispense_date` datetime DEFAULT NULL,
  `denial_reason` text DEFAULT NULL,
  `req_type` enum('OFFICE_SUPPLY','IT_LOAN') NOT NULL DEFAULT 'OFFICE_SUPPLY',
  `purpose` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supply_requisitions`
--

INSERT INTO `supply_requisitions` (`req_id`, `request_by_id`, `request_by_username`, `request_by_name`, `department`, `company`, `request_date`, `status`, `approver_1_username`, `approval_1_date`, `admin_dispense_username`, `dispense_date`, `denial_reason`, `req_type`, `purpose`) VALUES
(1, '5808200', '5808200', 'Mr.Kasidet phokintechanon', 'IT', NULL, '2025-09-14 14:39:04', 'Approved', '5808200', '2025-09-15 14:13:31', NULL, NULL, NULL, 'OFFICE_SUPPLY', NULL),
(2, '', '', 'Thawiwat Suksahwat', 'Logistics', NULL, '2025-09-14 15:02:02', 'Approved', '5808200', '2025-09-15 14:13:31', NULL, NULL, NULL, 'OFFICE_SUPPLY', NULL),
(3, '5808200', '5808200', 'Mr.Kasidet phokintechanon', 'IT', NULL, '2025-09-14 15:46:10', 'Approved', '5808200', '2025-09-15 14:13:29', NULL, NULL, NULL, 'OFFICE_SUPPLY', NULL),
(4, NULL, '5808200', 'Mr.Kasidet phokintechanon', 'IT', NULL, '2025-09-15 14:33:02', 'Approved', '5808200', '2025-09-15 14:33:38', NULL, NULL, NULL, 'OFFICE_SUPPLY', ''),
(5, NULL, '5808200', 'Mr.Kasidet phokintechanon', 'IT', 'PTA', '2025-09-15 15:05:54', 'Approved', '5808200', '2025-09-15 15:06:39', NULL, NULL, NULL, 'IT_LOAN', ''),
(6, NULL, '5808200', 'Mr.Kasidet phokintechanon', 'IT', 'PTA', '2025-09-15 15:06:14', 'Approved', '5808200', '2025-09-15 15:06:56', NULL, NULL, NULL, 'OFFICE_SUPPLY', ''),
(7, NULL, '6102312', 'Ernie Yusnita Binti Ismail', 'Quality Management', 'PTA', '2025-09-15 15:29:35', 'Pending Dispense', 'Kasidet P.', '2025-10-02 11:01:27', NULL, NULL, NULL, 'IT_LOAN', ''),
(8, NULL, '6102312', 'Ernie Yusnita Binti Ismail', 'Quality Management', 'PTE', '2025-09-15 15:29:42', 'Pending Dispense', 'Kasidet P.', '2025-10-02 11:01:24', NULL, NULL, NULL, 'IT_LOAN', 'rgtge'),
(9, NULL, '1234', 'Ms. Siriporn Ruthirawut', 'Management', 'PT4', '2025-09-15 15:46:48', 'Denied', 'Kasidet P.', '2025-10-02 11:01:21', NULL, NULL, '1', 'IT_LOAN', '');

-- --------------------------------------------------------

--
-- Table structure for table `supply_requisition_details`
--

CREATE TABLE `supply_requisition_details` (
  `detail_id` int(11) NOT NULL,
  `req_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `item_name_snapshot` varchar(255) NOT NULL,
  `quantity_requested` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supply_units`
--

CREATE TABLE `supply_units` (
  `unit_id` int(11) NOT NULL,
  `unit_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supply_units`
--

INSERT INTO `supply_units` (`unit_id`, `unit_name`) VALUES
(1, 'ด้าม'),
(2, 'กล่อง'),
(3, 'รีม'),
(4, 'ม้วน'),
(5, 'retrete'),
(6, 'ชิ้น'),
(7, 'เล่ม'),
(8, 'ชุด');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('line_active', '1'),
('line_channel_token', '0Y/1nFQMrC9GCbwjYNPOYKtEj6zcRpE3br1k50JidstA6P0VIZyXgE/S65pkV6HLjHWnmObqheizixRzWxFIW4oZErZG1w1/2dRobGroS+yvv8jR+6oQqry7F/bhCi3p8fitbKuONmFjhqWARYQwTgdB04t89/1O/w1cDnyilFU='),
('line_dest_id', 'Cd667eacfb93a443000d08d67f64c6f1b'),
('line_token', 'XNjtbvklMWb6c2pvmWGI7R0+HZl/Ig2ib5RRJbEKX/eIC5kWFSmeuyjxrM/YDF/BUl4WF2jQeKjIl6ibA74dld7+Eyo9pmAl8cRRi8u2JDQDS3pHfvTejc55YtHIvhlkrrJ0c8r4HRwhoGu4Bv80UgdB04t89/1O/w1cDnyilFU='),
('telegram_active', '1'),
('telegram_chat_id', '7391488473'),
('telegram_token', '8309013674:AAEhf-8kfGhozoesmz86HN2VKAJT7Emazao');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `role` enum('admin','staff','user') NOT NULL DEFAULT 'user',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `display_name`, `role`, `status`, `last_login`, `created_at`) VALUES
(3, 'admin', '$2y$10$nn2K5iFp8DLPGDdfq4YBauGOItuqsqu/Y3MDvQ.3AupsqeQxA7kEe', 'Admin User', 'admin', 'active', '2025-10-10 10:15:55', '2025-10-09 16:24:12');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `asset_categories`
--
ALTER TABLE `asset_categories`
  ADD PRIMARY KEY (`cat_id`);

--
-- Indexes for table `asset_history`
--
ALTER TABLE `asset_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `asset_db_id` (`asset_db_id`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_groups`
--
ALTER TABLE `chat_groups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_group_members`
--
ALTER TABLE `chat_group_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `group_user` (`group_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `company` (`company`);

--
-- Indexes for table `chat_typing`
--
ALTER TABLE `chat_typing`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`dept_id`);

--
-- Indexes for table `department_contacts`
--
ALTER TABLE `department_contacts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_contact` (`company`,`department`,`sub_department`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employees_status` (`employment_status`);

--
-- Indexes for table `employee_assignments`
--
ALTER TABLE `employee_assignments`
  ADD PRIMARY KEY (`assignment_id`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `it_assets`
--
ALTER TABLE `it_assets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `equipment_id` (`equipment_id`),
  ADD KEY `idx_borrower_id` (`borrower_id`),
  ADD KEY `idx_it_assets_status` (`status`),
  ADD KEY `idx_it_assets_company` (`company`);

--
-- Indexes for table `it_loan_history`
--
ALTER TABLE `it_loan_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `equipment_id_idx` (`equipment_id`),
  ADD KEY `idx_borrower_id_history` (`borrower_id`);

--
-- Indexes for table `it_logs`
--
ALTER TABLE `it_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_requester_id` (`requester_id`),
  ADD KEY `idx_servicedById` (`servicedById`),
  ADD KEY `idx_it_logs_asset_id` (`asset_id`),
  ADD KEY `idx_it_logs_status` (`status`),
  ADD KEY `idx_it_logs_company` (`company`),
  ADD KEY `idx_it_logs_type` (`type`);

--
-- Indexes for table `it_log_attachments`
--
ALTER TABLE `it_log_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `log_id_index` (`log_id`);

--
-- Indexes for table `it_log_comments`
--
ALTER TABLE `it_log_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_log_id` (`log_id`);

--
-- Indexes for table `job_tickets`
--
ALTER TABLE `job_tickets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kb_articles`
--
ALTER TABLE `kb_articles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `author_id` (`author_id`);

--
-- Indexes for table `kb_categories`
--
ALTER TABLE `kb_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `setup_history`
--
ALTER TABLE `setup_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_code_index` (`asset_code`);

--
-- Indexes for table `supply_categories`
--
ALTER TABLE `supply_categories`
  ADD PRIMARY KEY (`cat_id`);

--
-- Indexes for table `supply_items`
--
ALTER TABLE `supply_items`
  ADD PRIMARY KEY (`item_id`),
  ADD UNIQUE KEY `item_code` (`item_code`),
  ADD KEY `cat_id` (`cat_id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Indexes for table `supply_requisitions`
--
ALTER TABLE `supply_requisitions`
  ADD PRIMARY KEY (`req_id`),
  ADD KEY `request_by_username` (`request_by_username`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `supply_requisition_details`
--
ALTER TABLE `supply_requisition_details`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `fk_req` (`req_id`);

--
-- Indexes for table `supply_units`
--
ALTER TABLE `supply_units`
  ADD PRIMARY KEY (`unit_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `asset_categories`
--
ALTER TABLE `asset_categories`
  MODIFY `cat_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `asset_history`
--
ALTER TABLE `asset_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=239;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `chat_groups`
--
ALTER TABLE `chat_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `chat_group_members`
--
ALTER TABLE `chat_group_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `dept_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `department_contacts`
--
ALTER TABLE `department_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `employee_assignments`
--
ALTER TABLE `employee_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=736;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `it_assets`
--
ALTER TABLE `it_assets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `it_loan_history`
--
ALTER TABLE `it_loan_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `it_log_attachments`
--
ALTER TABLE `it_log_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `it_log_comments`
--
ALTER TABLE `it_log_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_tickets`
--
ALTER TABLE `job_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `kb_articles`
--
ALTER TABLE `kb_articles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `kb_categories`
--
ALTER TABLE `kb_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `setup_history`
--
ALTER TABLE `setup_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `supply_categories`
--
ALTER TABLE `supply_categories`
  MODIFY `cat_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `supply_items`
--
ALTER TABLE `supply_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `supply_requisitions`
--
ALTER TABLE `supply_requisitions`
  MODIFY `req_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `supply_requisition_details`
--
ALTER TABLE `supply_requisition_details`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supply_units`
--
ALTER TABLE `supply_units`
  MODIFY `unit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `chat_group_members`
--
ALTER TABLE `chat_group_members`
  ADD CONSTRAINT `chat_group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `chat_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `supply_requisition_details`
--
ALTER TABLE `supply_requisition_details`
  ADD CONSTRAINT `fk_req` FOREIGN KEY (`req_id`) REFERENCES `supply_requisitions` (`req_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
