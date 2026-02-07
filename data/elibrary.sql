-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:4000
-- Generation Time: Feb 06, 2026 at 08:17 AM
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
-- Database: `elibrary`
--

-- --------------------------------------------------------

--
-- Table structure for table `app_settings`
--

CREATE TABLE `app_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `app_settings`
--

INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES
('notifications_email_enabled', '0'),
('notifications_inapp_enabled', '1'),
('notifications_phone_enabled', '0'),
('registration_enabled', '1'),
('registration_mode', 'open'),
('require_email_verification', '1');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `cover_image_path` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `cover_image_path`, `created_at`) VALUES
(3, 'Gynecology', 'uploads/category_covers/file_691ed9b3561542.88843433.jpg', '2025-11-20 10:04:51'),
(4, 'Family Planning', 'uploads/category_covers/file_691edb5ab22432.55686210.jpeg', '2025-11-20 10:05:13'),
(5, 'Law', 'uploads/category_covers/file_691edba8425c14.83472866.jpg', '2025-11-20 10:05:25'),
(6, 'Management', NULL, '2025-11-20 10:05:34'),
(7, 'Obstetrics', NULL, '2025-11-20 10:05:44'),
(8, 'Pathology', NULL, '2025-11-20 10:05:54'),
(9, 'Pediatrics', NULL, '2025-11-20 10:06:14'),
(10, 'Pharmacology', NULL, '2025-11-20 10:06:25'),
(11, 'Reproduction', NULL, '2025-11-20 10:06:38'),
(12, 'Research', NULL, '2025-11-20 10:06:46'),
(13, 'Anatomy & physiology', NULL, '2026-02-05 10:45:05'),
(14, 'Nursing Journals', NULL, '2026-02-05 11:01:55');

-- --------------------------------------------------------

--
-- Table structure for table `email_verification_tokens`
--

CREATE TABLE `email_verification_tokens` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `featured_resources`
--

CREATE TABLE `featured_resources` (
  `id` int(10) UNSIGNED NOT NULL,
  `resource_id` int(10) UNSIGNED NOT NULL,
  `section` varchar(50) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `link` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reading_progress`
--

CREATE TABLE `reading_progress` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `resource_id` int(10) UNSIGNED NOT NULL,
  `last_position` int(11) DEFAULT 0,
  `progress_percent` decimal(5,2) DEFAULT 0.00,
  `total_pages` int(11) DEFAULT NULL,
  `last_viewed_at` datetime DEFAULT current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reading_progress`
--

INSERT INTO `reading_progress` (`id`, `user_id`, `resource_id`, `last_position`, `progress_percent`, `total_pages`, `last_viewed_at`, `created_at`) VALUES
(1, 2, 114, 5, 0.96, 523, '2026-02-05 12:56:01', '2026-02-05 12:55:49'),
(2, 2, 95, 14, 0.42, 3370, '2026-02-05 13:04:25', '2026-02-05 13:00:56'),
(3, 2, 110, 11, 1.89, 581, '2026-02-05 15:59:32', '2026-02-05 15:58:54'),
(4, 2, 109, 5, 0.27, 1875, '2026-02-05 16:01:44', '2026-02-05 15:59:46'),
(5, 2, 96, 1, 0.08, 1297, '2026-02-05 16:03:44', '2026-02-05 16:02:18'),
(6, 1, 64, 1, 0.17, 592, '2026-02-05 16:06:19', '2026-02-05 16:04:56'),
(7, 2, 37, 8, 9.88, 81, '2026-02-05 16:11:50', '2026-02-05 16:11:36'),
(8, 1, 110, 5, 0.86, 581, '2026-02-05 16:18:55', '2026-02-05 16:17:06'),
(9, 1, 95, 1, 0.03, 3370, '2026-02-05 17:31:34', '2026-02-05 16:20:23'),
(10, 1, 98, 5, 1.07, 467, '2026-02-05 16:23:51', '2026-02-05 16:22:23'),
(11, 1, 103, 5, 1.26, 396, '2026-02-05 16:26:19', '2026-02-05 16:24:58'),
(12, 1, 11, 4, 0.63, 630, '2026-02-05 16:33:04', '2026-02-05 16:30:23'),
(13, 1, 76, 3, 0.32, 925, '2026-02-05 17:25:17', '2026-02-05 16:34:09'),
(14, 1, 99, 3, 0.94, 318, '2026-02-05 16:35:58', '2026-02-05 16:35:20'),
(15, 1, 92, 1, 0.22, 463, '2026-02-05 16:40:26', '2026-02-05 16:37:27'),
(16, 1, 89, 3, 0.67, 449, '2026-02-05 16:42:39', '2026-02-05 16:42:19'),
(17, 1, 93, 6, 0.45, 1345, '2026-02-05 17:26:18', '2026-02-05 16:43:23'),
(18, 1, 96, 4, 0.31, 1297, '2026-02-05 17:14:25', '2026-02-05 17:13:14'),
(19, 1, 65, 1, 0.22, 460, '2026-02-05 17:19:33', '2026-02-05 17:16:15'),
(20, 1, 55, 1, 0.07, 1462, '2026-02-05 17:30:57', '2026-02-05 17:20:23'),
(21, 1, 63, 0, 0.00, NULL, '2026-02-05 17:41:51', '2026-02-05 17:34:14'),
(22, 1, 67, 0, 0.00, NULL, '2026-02-05 17:47:19', '2026-02-05 17:40:26');

-- --------------------------------------------------------

--
-- Table structure for table `resources`
--

CREATE TABLE `resources` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `file_path` text DEFAULT NULL,
  `cover_image_path` varchar(255) DEFAULT NULL,
  `external_url` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'approved',
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `review_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `resources`
--

INSERT INTO `resources` (`id`, `title`, `description`, `type`, `category_id`, `file_path`, `cover_image_path`, `external_url`, `created_at`, `created_by`, `status`, `approved_by`, `approved_at`, `review_notes`) VALUES
(7, 'Handbook of  Gynecology', '', 'pdf', 3, 'uploads/file_691ee6f13b4ce9.34179003.pdf', 'uploads/covers/file_691ee6f13be928.53340842.jpeg', NULL, '2025-11-20 11:01:21', 7, 'approved', NULL, NULL, NULL),
(10, 'HandbookofObstericsandGynaecology', '', 'pdf', 3, 'uploads/file_691eeee8ad5fe2.11891226.pdf', 'uploads/covers/file_691eeee8adb8e4.75786804.jpeg', NULL, '2025-11-20 11:35:20', 1, 'approved', NULL, NULL, NULL),
(11, 'Andrology_ Male Reproductive Health and Dysfunction', '', 'pdf', 11, 'uploads/file_691ef092ce4e30.36632832.pdf', 'uploads/covers/file_691ef092cea887.44417735.jpeg', NULL, '2025-11-20 11:42:26', 1, 'approved', NULL, NULL, NULL),
(12, 'Case Files Pediatrics', '', 'pdf', 3, 'uploads/file_691ef0aa07bf00.53601411.pdf', 'uploads/covers/file_691ef0aa081935.79380080.jpeg', NULL, '2025-11-20 11:42:50', 7, 'approved', NULL, NULL, NULL),
(13, 'Animal Biotechnology 1_ Reproductive Biotechnologies', '', 'pdf', 11, 'uploads/file_691ef117a22426.34179688.pdf', 'uploads/covers/file_691ef117a25bf6.22132334.jpeg', NULL, '2025-11-20 11:44:39', 1, 'approved', NULL, NULL, NULL),
(14, 'obstetrics and gynaecology', '', 'pdf', 3, 'uploads/file_691ef1366a63a2.65508431.pdf', 'uploads/covers/file_691ef1366aaeb3.05380150.jpeg', NULL, '2025-11-20 11:45:10', 7, 'approved', NULL, NULL, NULL),
(15, 'Gabbe’s Obstetrics Essentials Normal Problem Pregnancies', '', 'pdf', 3, 'uploads/file_691ef21804a081.08694342.pdf', 'uploads/covers/file_691ef21804fa26.12960929.jpeg', NULL, '2025-11-20 11:48:56', 7, 'approved', NULL, NULL, NULL),
(16, 'Basics of Human Andrology _ A Textbook', '', 'pdf', 11, 'uploads/file_691ef219df1444.74455540.pdf', 'uploads/covers/file_691ef219df7539.86128159.jpeg', NULL, '2025-11-20 11:48:57', 1, 'approved', NULL, NULL, NULL),
(17, 'Gynecology', '', 'pdf', 3, 'uploads/file_691ef25b6af1c5.18001578.pdf', 'uploads/covers/file_691ef25b6b5648.71988653.jpeg', NULL, '2025-11-20 11:50:03', 7, 'approved', NULL, NULL, NULL),
(18, 'Pediatric Decision-Making Strategies', '', 'pdf', 3, 'uploads/file_691ef2c36ea4e5.96481807.pdf', 'uploads/covers/file_691ef2c36f2483.91302386.jpeg', NULL, '2025-11-20 11:51:47', 7, 'approved', NULL, NULL, NULL),
(19, 'Pocket Obstetrics and Gynecology', '', 'pdf', 3, 'uploads/file_691ef2de8891b7.22847410.pdf', 'uploads/covers/file_691ef2de88d123.77531677.jpeg', NULL, '2025-11-20 11:52:14', 7, 'approved', NULL, NULL, NULL),
(20, 'Critical Interventions in the Ethics of Healthcare', '', 'pdf', 5, 'uploads/file_691ef3730930b7.24195555.pdf', 'uploads/covers/file_691ef373098613.14646456.jpeg', NULL, '2025-11-20 11:54:43', 7, 'approved', NULL, NULL, NULL),
(21, 'Everyday Medical Ethics and Law', '', 'pdf', 5, 'uploads/file_691ef38c694e21.35682635.pdf', 'uploads/covers/file_691ef38c69ae75.93897325.jpeg', NULL, '2025-11-20 11:55:08', 7, 'approved', NULL, NULL, NULL),
(22, 'Healthcare Research Ethics and Law', '', 'pdf', 5, 'uploads/file_691ef3db636274.91378971.pdf', 'uploads/covers/file_691ef3db639c71.28511641.jpeg', NULL, '2025-11-20 11:56:27', 7, 'approved', NULL, NULL, NULL),
(23, 'Medical Ethics and Medical Law', '', 'pdf', 5, 'uploads/file_691ef4057733b8.30743009.pdf', 'uploads/covers/file_691ef405778098.73341792.jpeg', NULL, '2025-11-20 11:57:09', 7, 'approved', NULL, NULL, NULL),
(24, 'Female_learning_objectives', '', 'pdf', 11, 'uploads/file_691ef424c23d29.97545697.pdf', 'uploads/covers/file_691ef424c27907.98498084.jpeg', NULL, '2025-11-20 11:57:40', 1, 'approved', NULL, NULL, NULL),
(25, 'Medical Ethics Today The BMA\'s Handbook of Ethics and Law', '', 'pdf', 5, 'uploads/file_691ef42664d933.74146359.pdf', 'uploads/covers/file_691ef4266535e7.87106366.jpeg', NULL, '2025-11-20 11:57:42', 7, 'approved', NULL, NULL, NULL),
(26, 'Medical Ethics Today', '', 'pdf', 5, 'uploads/file_691ef44d1edd25.24462033.pdf', 'uploads/covers/file_691ef44d1f2884.12655415.jpeg', NULL, '2025-11-20 11:58:21', 7, 'approved', NULL, NULL, NULL),
(27, 'Development and Reproduction in Humans and Animal Model Species', '', 'pdf', 11, 'uploads/file_691ef47fe5a034.42852191.pdf', 'uploads/covers/file_691ef47fe5d873.67293712.jpeg', NULL, '2025-11-20 11:59:11', 1, 'approved', NULL, NULL, NULL),
(28, 'Medical Law, Ethics, & Bioethics for the Health Professions', '', 'pdf', 5, 'uploads/file_691ef485ea04f5.92136725.pdf', 'uploads/covers/file_691ef485ea3e23.63977348.jpeg', NULL, '2025-11-20 11:59:17', 7, 'approved', NULL, NULL, NULL),
(29, 'Leadership, Management & Governance', '', 'pdf', 6, 'uploads/file_691ef523c997e9.94806380.pdf', 'uploads/covers/file_691ef523c9f4a7.69288534.jpeg', NULL, '2025-11-20 12:01:55', 7, 'approved', NULL, NULL, NULL),
(30, 'Endocrine and reproductive physiology', '', 'pdf', 11, 'uploads/file_691ef5324acf35.67033482.pdf', 'uploads/covers/file_691ef5324b23d9.40080627.jpeg', NULL, '2025-11-20 12:02:10', 1, 'approved', NULL, NULL, NULL),
(31, 'Basic & Clinical Pharmacology', '', 'pdf', 10, 'uploads/file_691ef55218a7d7.32985016.pdf', 'uploads/covers/file_691ef55218f266.54261928.jpeg', NULL, '2025-11-20 12:02:42', 1, 'approved', NULL, NULL, NULL),
(32, 'Nursing Midwifery Services', '', 'pdf', 6, 'uploads/file_691ef55fb49068.92058079.pdf', 'uploads/covers/file_691ef55fb4cec6.26142100.jpeg', NULL, '2025-11-20 12:02:55', 7, 'approved', NULL, NULL, NULL),
(33, 'journal', '', 'pdf', 5, 'uploads/file_691ef580de86e6.63760978.pdf', 'uploads/covers/file_691ef580dfc721.52046103.jpeg', NULL, '2025-11-20 12:03:28', 7, 'approved', NULL, NULL, NULL),
(34, 'Essentials of Medical Pharmacology, 6th Edition', '', 'pdf', 11, 'uploads/file_691ef58b0f41a6.58195375.pdf', 'uploads/covers/file_691ef58b0fc325.88127684.jpeg', NULL, '2025-11-20 12:03:39', 1, 'approved', NULL, NULL, NULL),
(35, 'Endocrinology of the Testis and Male Reproduction', '', 'pdf', 11, 'uploads/file_691ef5bae31795.66275721.pdf', 'uploads/covers/file_691ef5bae39720.90809537.jpeg', NULL, '2025-11-20 12:04:26', 1, 'approved', NULL, NULL, NULL),
(36, 'Quality Midwifery Care in the Midst of Crisis', '', 'pdf', 6, 'uploads/file_691ef5c69fc1c6.86565640.pdf', 'uploads/covers/file_691ef5c6a02a13.11715126.jpeg', NULL, '2025-11-20 12:04:38', 7, 'approved', NULL, NULL, NULL),
(37, 'Management and organisational approaches to safe nursing and midwifery staffing', '', 'pdf', 6, 'uploads/file_691ef60cad4ce3.09966720.pdf', 'uploads/covers/file_691ef60cae1eb8.37008588.jpeg', NULL, '2025-11-20 12:05:48', 7, 'approved', NULL, NULL, NULL),
(38, 'Beckmann and Ling’s Obstetrics and Gynecology', '', 'pdf', 7, 'uploads/file_691ef6473d7f15.58396520.pdf', 'uploads/covers/file_691ef6473ddde5.77260430.jpeg', NULL, '2025-11-20 12:06:47', 7, 'approved', NULL, NULL, NULL),
(39, 'Obstetrics & Gynecology', '', 'pdf', 7, 'uploads/file_691ef66a0ac288.91133970.pdf', 'uploads/covers/file_691ef66a0b0c56.11508319.jpeg', NULL, '2025-11-20 12:07:22', 7, 'approved', NULL, NULL, NULL),
(40, 'CASE FILES Obstetrics and Gynecology', '', 'pdf', 7, 'uploads/file_691ef6bce0b6e2.95627076.pdf', 'uploads/covers/file_691ef6bce102c0.23980605.jpeg', NULL, '2025-11-20 12:08:44', 7, 'approved', NULL, NULL, NULL),
(41, 'Textbook of ultrasound in obstetrics and Gynecology', '', 'pdf', 7, 'uploads/file_691ef6fbc89574.07208987.pdf', 'uploads/covers/file_691ef6fbc8e406.06033474.jpeg', NULL, '2025-11-20 12:09:47', 7, 'approved', NULL, NULL, NULL),
(42, 'Exercise and Human Reproduction_ Induced Fertility Disorders and Possible Therapies', '', 'pdf', 11, 'uploads/file_691ef7171ee842.50330702.pdf', 'uploads/covers/file_691ef7171f9df5.68536383.jpeg', NULL, '2025-11-20 12:10:15', 1, 'approved', NULL, NULL, NULL),
(43, 'Evidence-based obstetrics and gynecology', '', 'pdf', 7, 'uploads/file_691ef73a8049d2.56288667.pdf', 'uploads/covers/file_691ef73a80b8b2.13009952.jpeg', NULL, '2025-11-20 12:10:50', 7, 'approved', NULL, NULL, NULL),
(44, 'Examination Review for Ultrasound_ Abdomen and Obstetrics & Gynecology', '', 'pdf', 7, 'uploads/file_691ef762b1aeb3.29871200.pdf', 'uploads/covers/file_691ef762b20193.62498908.jpeg', NULL, '2025-11-20 12:11:30', 7, 'approved', NULL, NULL, NULL),
(45, 'First Aid for the Obstetrics & Gynecology Clerkship', '', 'pdf', 7, 'uploads/file_691ef7861dfc84.05110834.pdf', 'uploads/covers/file_691ef7861e3716.75249647.jpeg', NULL, '2025-11-20 12:12:06', 7, 'approved', NULL, NULL, NULL),
(46, 'All-in-One Nursing Care Planning Resource_ Medical-Surgical, Pediatric, Maternity, and Psychiatric-Mental Health', '', 'pdf', 9, 'uploads/file_691ef7afd913c6.57304463.pdf', 'uploads/covers/file_691ef7afd951b6.36743599.jpeg', NULL, '2025-11-20 12:12:47', 1, 'approved', NULL, NULL, NULL),
(47, 'kaplan-pharmacology', '', 'pdf', 10, 'uploads/file_691ef7c9de0698.24032413.pdf', 'uploads/covers/file_691ef7c9df4fc2.79648081.jpeg', NULL, '2025-11-20 12:13:13', 1, 'approved', NULL, NULL, NULL),
(48, 'First Aid for the Obstetrics & Gynecology Clerkship', '', 'pdf', 7, 'uploads/file_691ef7dc01b8f6.38640382.pdf', 'uploads/covers/file_691ef7dc022838.46679693.jpeg', NULL, '2025-11-20 12:13:32', 7, 'approved', NULL, NULL, NULL),
(49, 'Clinical Pediatric Neurology 2009.Guide line for intervention neurologyHandbook of Pediatric.', '', 'pdf', 9, 'uploads/file_691ef7f1234df4.48625494.pdf', 'uploads/covers/file_691ef7f129bc80.05265268.jpeg', NULL, '2025-11-20 12:13:53', 1, 'approved', NULL, NULL, NULL),
(50, 'Infectious Diseases in Obstetrics and Gynecology, fifth Edition', '', 'pdf', 7, 'uploads/file_691ef800e73e01.44100363.pdf', 'uploads/covers/file_691ef800e77648.38159723.jpeg', NULL, '2025-11-20 12:14:08', 7, 'approved', NULL, NULL, NULL),
(51, 'Obstetrics and gynaecology', '', 'pdf', 7, 'uploads/file_691ef8549d1b95.87990155.pdf', 'uploads/covers/file_691ef8549da306.71310489.jpeg', NULL, '2025-11-20 12:15:32', 7, 'approved', NULL, NULL, NULL),
(52, 'Katzung & Trevor’s  Pharmacology  Examination   & Board Review', '', 'pdf', 10, 'uploads/file_691ef8928152c7.50245887.pdf', 'uploads/covers/file_691ef892818e01.32556454.jpeg', NULL, '2025-11-20 12:16:34', 1, 'approved', NULL, NULL, NULL),
(53, 'Color Atlas & Synopsis of Clinical Ophthalmology - Wills Eye Institute Pediatric Ophthalmology', '', 'pdf', 9, 'uploads/file_691ef8a5e679d8.83811639.pdf', 'uploads/covers/file_691ef8a5e6e7c6.08911662.jpeg', NULL, '2025-11-20 12:16:53', 1, 'approved', NULL, NULL, NULL),
(54, 'current_diagnosis_and_treatment_pediatrics', '', 'pdf', 9, 'uploads/file_691ef8d9dde827.72630532.pdf', 'uploads/covers/file_691ef8d9de3817.51038405.jpeg', NULL, '2025-11-20 12:17:45', 1, 'approved', NULL, NULL, NULL),
(55, 'Current Pediatric Diagnosis & Treatment, 17th Edition (Current Pediatric Diagnosis and Treatment)', '', 'pdf', 9, 'uploads/file_691ef9136349e9.27048580.pdf', 'uploads/covers/file_691ef913639c30.97613584.jpeg', NULL, '2025-11-20 12:18:43', 1, 'approved', NULL, NULL, NULL),
(56, 'A guide to Family Planning', '', 'pdf', 4, 'uploads/file_691ef92519ac40.50028004.pdf', 'uploads/covers/file_691ef92519ff11.86854094.jpeg', NULL, '2025-11-20 12:19:01', 7, 'approved', NULL, NULL, NULL),
(57, 'Pharmacological Classification of Drugs with Doses and Preparations', '', 'pdf', 10, 'uploads/file_691ef95c745065.91482406.pdf', 'uploads/covers/file_691ef95c74b710.31841339.jpeg', NULL, '2025-11-20 12:19:56', 1, 'approved', NULL, NULL, NULL),
(58, 'Family_Plannin', '', 'pdf', 4, 'uploads/file_691ef95e857028.51909875.pdf', 'uploads/covers/file_691ef95e85bba6.46124632.jpeg', NULL, '2025-11-20 12:19:58', 7, 'approved', NULL, NULL, NULL),
(59, 'facts for family planning', '', 'pdf', 4, 'uploads/file_691ef988424247.72060185.pdf', 'uploads/covers/file_691ef98842aeb8.45833210.jpeg', NULL, '2025-11-20 12:20:40', 7, 'approved', NULL, NULL, NULL),
(60, 'Pharmacology', '', 'pdf', 10, 'uploads/file_691ef9a05a17c1.30245633.pdf', 'uploads/covers/file_691ef9a05a7477.35910355.jpeg', NULL, '2025-11-20 12:21:04', 1, 'approved', NULL, NULL, NULL),
(61, 'family_planning_a_global_handbook_for_providers', '', 'pdf', 4, 'uploads/file_691ef9aaad3605.85079676.pdf', 'uploads/covers/file_691ef9aaae8734.37667374.jpeg', NULL, '2025-11-20 12:21:14', 7, 'approved', NULL, NULL, NULL),
(62, 'handbook of PEDIATRIC EMERGENCY MEDICINE', '', 'pdf', 9, 'uploads/file_691ef9b97fa059.68698110.pdf', 'uploads/covers/file_691ef9b97fece9.56310122.jpeg', NULL, '2025-11-20 12:21:29', 1, 'approved', NULL, NULL, NULL),
(63, 'Family-Planning-Topic-Guide', '', 'pdf', 4, 'uploads/file_691ef9c7b2d933.75908004.pdf', 'uploads/covers/file_691ef9c7b322e6.31014630.jpeg', NULL, '2025-11-20 12:21:43', 7, 'approved', NULL, NULL, NULL),
(64, 'MCQs in Pediatrics Review of Nelson Textbook of Pediatrics', '', 'pdf', 9, 'uploads/file_691ef9f0a35ae4.64131978.pdf', 'uploads/covers/file_691ef9f0a3ce05.18179474.jpeg', NULL, '2025-11-20 12:22:24', 1, 'approved', NULL, NULL, NULL),
(65, 'Family-Planning-A GLOBAL HANDBOOK FOR PROVIDERS', '', 'pdf', 4, 'uploads/file_691efa2a5806c5.33657601.pdf', 'uploads/covers/file_691efa2a585374.26617956.jpeg', NULL, '2025-11-20 12:23:22', 7, 'approved', NULL, NULL, NULL),
(66, 'Basic pathology _ an introduction to the mechanisms of disease', '', 'pdf', 8, 'uploads/file_691efa78202ce3.95853116.pdf', 'uploads/covers/file_691efa782086a3.77149075.jpeg', NULL, '2025-11-20 12:24:40', 1, 'approved', NULL, NULL, NULL),
(67, 'Nigeria Reproductive Health, Chid Healh, and Education End- of-project Household Survey, 2009', '', 'pdf', 4, 'uploads/file_691efa8697dcc8.18276320.pdf', 'uploads/covers/file_691efa86981882.92588819.jpeg', NULL, '2025-11-20 12:24:54', 7, 'approved', NULL, NULL, NULL),
(68, 'Need-family-planning', '', 'pdf', 4, 'uploads/file_691efac1649362.20696286.pdf', 'uploads/covers/file_691efac164d7a6.91750868.jpeg', NULL, '2025-11-20 12:25:53', 7, 'approved', NULL, NULL, NULL),
(69, 'Breast Pathology_ A Volume in the Series_ Foundations in Diagnostic Pathology (Expert Consult - Online and Print), 2e', '', 'pdf', 8, 'uploads/file_691efd52bc3f56.83416793.pdf', 'uploads/covers/file_691efd52bccd44.65213926.jpeg', NULL, '2025-11-20 12:36:50', 1, 'approved', NULL, NULL, NULL),
(70, 'Atlas of Interstitial Lung Disease Pathology_ Pathology with High Resolution CT Correlations', '', 'pdf', 8, 'uploads/file_691efd938aa984.33177986.pdf', 'uploads/covers/file_691efd938b00c4.39074490.jpeg', NULL, '2025-11-20 12:37:55', 1, 'approved', NULL, NULL, NULL),
(71, 'Fundamental RESEARCH METHODOLOGY and STATISTICS', '', 'pdf', 12, 'uploads/file_691efdee7256e4.76876568.pdf', 'uploads/covers/file_691efdee72b6b9.39123954.jpeg', NULL, '2025-11-20 12:39:26', 7, 'approved', NULL, NULL, NULL),
(72, 'Cranial Nerves_ Anatomy, Pathology, Imaging', '', 'pdf', 8, 'uploads/file_691efdee953e44.24591053.pdf', 'uploads/covers/file_691efdee957ae3.79889986.jpeg', NULL, '2025-11-20 12:39:26', 1, 'approved', NULL, NULL, NULL),
(73, 'Pharmacology for Nurses, A Pathophysiological Approach, 4th Edition- Michael Adams', '', 'pdf', 10, 'uploads/file_691efdf61dc028.25963506.pdf', 'uploads/covers/file_691efdf61e3499.43189925.jpeg', NULL, '2025-11-20 12:39:34', 1, 'approved', NULL, NULL, NULL),
(74, 'English for Research_ Usage, Style, and Grammar', '', 'pdf', 12, 'uploads/file_691efe27770ca4.15840153.pdf', 'uploads/covers/file_691efe27774eb1.29822548.jpeg', NULL, '2025-11-20 12:40:23', 7, 'approved', NULL, NULL, NULL),
(75, 'Essentials of Research Design and Methodology 2005', '', 'pdf', 12, 'uploads/file_691efe44074962.65345355.pdf', 'uploads/covers/file_691efe4407c396.65342598.jpeg', NULL, '2025-11-20 12:40:52', 7, 'approved', NULL, NULL, NULL),
(76, 'Pharmacology for Nurses_ A Pathophysiologic Approach', '', 'pdf', 10, 'uploads/file_691efe73410067.03200635.pdf', 'uploads/covers/file_691efe73415397.08863570.jpeg', NULL, '2025-11-20 12:41:39', 1, 'approved', NULL, NULL, NULL),
(77, 'Diagnostic Gynecologic and Obstetric Pathology_ An Atlas and Text', '', 'pdf', 8, 'uploads/file_691efe7e91a530.06651274.pdf', 'uploads/covers/file_691efe7e97bd01.84624014.jpeg', NULL, '2025-11-20 12:41:50', 1, 'approved', NULL, NULL, NULL),
(78, 'Introduction to operations research', '', 'pdf', 12, 'uploads/file_691efe8f935325.35883964.pdf', 'uploads/covers/file_691efe8f93d478.04270228.jpeg', NULL, '2025-11-20 12:42:07', 7, 'approved', NULL, NULL, NULL),
(79, 'Lung Pathology (Current Clinical Pathology)', '', 'pdf', 8, 'uploads/file_691efed20786d6.61062109.pdf', 'uploads/covers/file_691efed207d355.73498879.jpeg', NULL, '2025-11-20 12:43:14', 1, 'approved', NULL, NULL, NULL),
(80, 'John W. Creswell-Research Design_ Qualitative, Quantitative, and Mixed Methods Approaches', '', 'pdf', 12, 'uploads/file_691efed91e9bc4.61739266.pdf', 'uploads/covers/file_691efed91eff33.88553501.jpeg', NULL, '2025-11-20 12:43:21', 7, 'approved', NULL, NULL, NULL),
(81, 'Principles and Practice of Clinical Research, Second Edition (Principles & Practice of Clinical Research', '', 'pdf', 12, 'uploads/file_691eff010ad790.09709402.pdf', 'uploads/covers/file_691eff010b3162.93708899.jpeg', NULL, '2025-11-20 12:44:01', 7, 'approved', NULL, NULL, NULL),
(82, 'Lung Pathology (Current Clinical Pathology)', '', 'pdf', 8, 'uploads/file_691eff0e551a95.76831588.pdf', 'uploads/covers/file_691eff0e55aeb0.86295481.jpeg', NULL, '2025-11-20 12:44:14', 1, 'approved', NULL, NULL, NULL),
(83, 'Qualitative Research Methods', '', 'pdf', 12, 'uploads/file_691eff21c57f04.97859517.pdf', 'uploads/covers/file_691eff21c62349.09313803.jpeg', NULL, '2025-11-20 12:44:33', 7, 'approved', NULL, NULL, NULL),
(84, 'Quantitative Data Analysis_ Doing Social Research to Test Ideas', '', 'pdf', 12, 'uploads/file_691eff49d61697.05107689.pdf', 'uploads/covers/file_691eff49d675d7.81294795.jpeg', NULL, '2025-11-20 12:45:13', 7, 'approved', NULL, NULL, NULL),
(85, 'Muir\'s Textbook of Pathology 14th Edition Elst', '', 'pdf', 8, 'uploads/file_691eff5a84dad9.01039068.pdf', 'uploads/covers/file_691eff5a851547.33042034.jpeg', NULL, '2025-11-20 12:45:30', 1, 'approved', NULL, NULL, NULL),
(86, 'Research_Methodology_A_Step-by-Step', '', 'pdf', 12, 'uploads/file_691eff6ea89f92.77889435.pdf', 'uploads/covers/file_691eff6ea8f430.65061034.jpeg', NULL, '2025-11-20 12:45:50', 7, 'approved', NULL, NULL, NULL),
(87, 'Research Design_ Quantitative, Qualitative, Mixed Methods, Arts-Based, and Community-Based Participatory Research Approaches', '', 'pdf', 12, 'uploads/file_691eff985e6336.32854466.pdf', 'uploads/covers/file_691eff985eb306.90892906.jpeg', NULL, '2025-11-20 12:46:32', 7, 'approved', NULL, NULL, NULL),
(88, 'Netter\'s Illustrated Human Pathology', '', 'pdf', 8, 'uploads/file_691effa53e8264.98312420.pdf', 'uploads/covers/file_691effa53eeb45.11562985.jpeg', NULL, '2025-11-20 12:46:45', 1, 'approved', NULL, NULL, NULL),
(89, 'Research Methods and Statistics', '', 'pdf', 12, 'uploads/file_691effe4e5bdc1.87324333.pdf', 'uploads/covers/file_691effe4e64908.18434669.jpeg', NULL, '2025-11-20 12:47:48', 7, 'approved', NULL, NULL, NULL),
(90, 'Osborn’s Brain_ imaging, pathology, and anatomy', '', 'pdf', 8, 'uploads/file_691effea8a07e9.22030183.pdf', 'uploads/covers/file_691effea8a6277.89043815.jpeg', NULL, '2025-11-20 12:47:54', 1, 'approved', NULL, NULL, NULL),
(91, 'Basic Statistics for Social Research', '', 'pdf', 12, 'uploads/file_691f00206bb0c1.66529903.pdf', 'uploads/covers/file_691f00206c07a3.48689697.jpeg', NULL, '2025-11-20 12:48:48', 7, 'approved', NULL, NULL, NULL),
(92, 'Research Methods in Education', '', 'pdf', 12, 'uploads/file_691f0053333d67.64734863.pdf', 'uploads/covers/file_691f0053337eb8.00695999.jpeg', NULL, '2025-11-20 12:49:39', 7, 'approved', NULL, NULL, NULL),
(93, 'Diagnostic Imaging Breast', '', 'pdf', 3, 'uploads/file_691f019c721681.12385916.pdf', 'uploads/covers/file_691f019c7289d3.49963251.jpeg', NULL, '2025-11-20 12:55:08', 7, 'approved', NULL, NULL, NULL),
(94, 'Obstetrics Normal and Problem Pregnancies', '', 'pdf', 3, 'uploads/file_691f01e7cd6a89.79351704.pdf', 'uploads/covers/file_691f01e7cda8a5.35955653.jpeg', NULL, '2025-11-20 12:56:23', 7, 'approved', NULL, NULL, NULL),
(95, 'Williams Obstetrics', '', 'pdf', 3, 'uploads/file_691f02120132b8.80165098.pdf', 'uploads/covers/file_691f0212017994.43748850.jpeg', NULL, '2025-11-20 12:57:06', 7, 'approved', NULL, NULL, NULL),
(96, 'Williams Gynecology', '', 'pdf', 11, 'uploads/file_691f037bd70d22.62643827.pdf', 'uploads/covers/file_691f037bd767d9.36950873.jpeg', NULL, '2025-11-20 13:03:07', 7, 'approved', NULL, NULL, NULL),
(97, 'Yen & Jaffe’s Reproductive Endocrinology_ Physiology, Pathophysiology, and Clinical Management', '', 'pdf', 11, 'uploads/file_691f03b3dff984.87704974.pdf', 'uploads/covers/file_691f03b3e03ad1.93425416.jpeg', NULL, '2025-11-20 13:04:03', 7, 'approved', NULL, NULL, NULL),
(98, 'Textbook of Assisted Reproductive Techniques', '', 'pdf', 11, 'uploads/file_691f03e7ad7263.84804338.pdf', 'uploads/covers/file_691f03e7adb7e7.00766936.jpeg', NULL, '2025-11-20 13:04:55', 7, 'approved', NULL, NULL, NULL),
(99, 'Reconstructive and Reproductive Surgery in Gynecology, Volume Two_ Gynecological Surgery', '', 'pdf', 11, 'uploads/file_691f0413b23d66.71091330.pdf', 'uploads/covers/file_691f0413b299d5.20067101.jpeg', NULL, '2025-11-20 13:05:39', 7, 'approved', NULL, NULL, NULL),
(100, 'Equine Clinical Medicine, Surgery and Reproduction', '', 'pdf', 11, 'uploads/file_691f043fda93b4.06274028.pdf', 'uploads/covers/file_691f043fdad801.05595786.jpeg', NULL, '2025-11-20 13:06:23', 7, 'approved', NULL, NULL, NULL),
(101, 'Diagnostic Gynecologic and Obstetric Pathology_ An Atlas and Text', '', 'pdf', 8, 'uploads/file_691f04b33a5924.40833298.pdf', 'uploads/covers/file_691f04b3a4c424.03825064.jpeg', NULL, '2025-11-20 13:08:19', 7, 'approved', NULL, NULL, NULL),
(102, 'Osborn’s Brain_ imaging, pathology, and anatomy', '', 'pdf', 8, 'uploads/file_691f051012de73.33915121.pdf', 'uploads/covers/file_691f0510131d58.89777285.jpeg', NULL, '2025-11-20 13:09:52', 7, 'approved', NULL, NULL, NULL),
(103, 'Breast Patholog_ Foundations in Diagnostic Pathology', '', 'pdf', 8, 'uploads/file_691f054e268d31.41272555.pdf', 'uploads/covers/file_691f054e27d792.82602040.jpeg', NULL, '2025-11-20 13:10:54', 7, 'approved', NULL, NULL, NULL),
(104, 'Robbins & Cotran Pathologic Basis of Disease', '', 'pdf', 8, 'uploads/file_691f057c184078.17683477.pdf', 'uploads/covers/file_691f057c188192.22857901.jpeg', NULL, '2025-11-20 13:11:40', 7, 'approved', NULL, NULL, NULL),
(105, 'Netter\'s Illustrated Human Pathology', '', 'pdf', 8, 'uploads/file_691f05ab8b84e8.97211053.pdf', 'uploads/covers/file_691f05ab9a2847.31882600.jpeg', NULL, '2025-11-20 13:12:27', 7, 'approved', NULL, NULL, NULL),
(106, 'Cranial Nerves_ Anatomy, Pathology, Imaging', '', 'pdf', 8, 'uploads/file_691f05daa9ca52.57610269.pdf', 'uploads/covers/file_691f05daaa1a26.13985768.jpeg', NULL, '2025-11-20 13:13:14', 7, 'approved', NULL, NULL, NULL),
(107, 'Robbins & Cotran Pathologic Basis of Disease', '', 'pdf', 8, 'uploads/file_691f060ab0ed77.37821628.pdf', 'uploads/covers/file_691f060b0b6d31.44951942.jpeg', NULL, '2025-11-20 13:14:03', 7, 'approved', NULL, NULL, NULL),
(108, 'Plant pathology concepts and laboratory exercises', '', 'pdf', 8, 'uploads/file_691f063ee4beb4.18078985.pdf', 'uploads/covers/file_691f063ee50868.97893593.jpeg', NULL, '2025-11-20 13:14:54', 7, 'approved', NULL, NULL, NULL),
(109, 'Pathophysiology of Disease_ An Introduction to Clinical Medicine', '', 'pdf', 8, 'uploads/file_691f066a8dd9c0.82950722.pdf', 'uploads/covers/file_691f066a8e2bb3.69724061.jpeg', NULL, '2025-11-20 13:15:38', 7, 'approved', NULL, NULL, NULL),
(110, 'Muir\'s Textbook of Pathology 14th Edition', '', 'pdf', 8, 'uploads/file_691f069a658c45.71683515.pdf', 'uploads/covers/file_691f069a65cfd7.25834225.jpeg', NULL, '2025-11-20 13:16:26', 7, 'approved', NULL, NULL, NULL),
(111, 'Atlas of Interstitial Lung Disease Pathology_ Pathology with High Resolution CT Correlations', '', 'pdf', 8, 'uploads/file_691f06c7762ba1.88409977.pdf', 'uploads/covers/file_691f06c7769003.98075855.jpeg', NULL, '2025-11-20 13:17:11', 7, 'approved', NULL, NULL, NULL),
(112, 'Lung Pathology (Current Clinical Pathology)', '', 'pdf', 8, 'uploads/file_691f06efba9661.05904939.pdf', 'uploads/covers/file_691f06efbaeda7.22436555.jpeg', NULL, '2025-11-20 13:17:51', 7, 'approved', NULL, NULL, NULL),
(113, 'Gupte-The-Short-Textbook-of-Pediatrics-11th-Ed-2009', '', 'pdf', 9, 'uploads/file_6984630a0a56f1.14643790.pdf', 'uploads/covers/file_6984630a4aace6.94270648.png', NULL, '2026-02-05 10:29:46', 1, 'approved', NULL, NULL, NULL),
(114, 'ross-willson-anatomy-and-physiology', '', 'pdf', 13, 'uploads/file_698466de3ee633.37898141.pdf', 'uploads/covers/file_698466de3f66b4.85759366.jpg', NULL, '2026-02-05 10:46:06', 1, 'approved', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `resource_comments`
--

CREATE TABLE `resource_comments` (
  `id` int(10) UNSIGNED NOT NULL,
  `resource_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `content` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'approved',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resource_downloads`
--

CREATE TABLE `resource_downloads` (
  `id` int(10) UNSIGNED NOT NULL,
  `resource_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resource_reports`
--

CREATE TABLE `resource_reports` (
  `id` int(10) UNSIGNED NOT NULL,
  `content_type` varchar(20) NOT NULL,
  `content_id` int(10) UNSIGNED NOT NULL,
  `reported_by` int(10) UNSIGNED NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resource_reviews`
--

CREATE TABLE `resource_reviews` (
  `id` int(10) UNSIGNED NOT NULL,
  `resource_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(1) NOT NULL,
  `review` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'approved',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resource_tags`
--

CREATE TABLE `resource_tags` (
  `id` int(10) UNSIGNED NOT NULL,
  `resource_id` int(10) UNSIGNED NOT NULL,
  `tag_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resource_views`
--

CREATE TABLE `resource_views` (
  `id` int(10) UNSIGNED NOT NULL,
  `resource_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `resource_views`
--

INSERT INTO `resource_views` (`id`, `resource_id`, `user_id`, `session_id`, `created_at`) VALUES
(1, 76, 1, 'n68q62nargec0n53rjpt0t2vom', '2026-02-05 16:34:10'),
(2, 99, 1, 'gqqnrjkcu5t8grv8hdr5a2fjua', '2026-02-05 16:35:20'),
(3, 99, 1, 'gqqnrjkcu5t8grv8hdr5a2fjua', '2026-02-05 16:35:20'),
(4, 92, 1, 'pjsalifvhof40bnn6914m19bne', '2026-02-05 16:37:27'),
(5, 89, 1, 'pjsalifvhof40bnn6914m19bne', '2026-02-05 16:42:19'),
(6, 93, 1, 'pjsalifvhof40bnn6914m19bne', '2026-02-05 16:43:24'),
(7, 93, 1, 'pjsalifvhof40bnn6914m19bne', '2026-02-05 16:43:24'),
(8, 76, 1, 'pjsalifvhof40bnn6914m19bne', '2026-02-05 16:50:03'),
(9, 76, 1, 'pjsalifvhof40bnn6914m19bne', '2026-02-05 16:50:04'),
(10, 76, 1, 'eur1befl12ne1qkbqrouu8dm13', '2026-02-05 17:11:48'),
(11, 96, 1, 'eur1befl12ne1qkbqrouu8dm13', '2026-02-05 17:13:14'),
(12, 65, 1, 'eur1befl12ne1qkbqrouu8dm13', '2026-02-05 17:16:15'),
(13, 55, 1, 'eur1befl12ne1qkbqrouu8dm13', '2026-02-05 17:20:23'),
(14, 55, 1, 'eur1befl12ne1qkbqrouu8dm13', '2026-02-05 17:20:24'),
(15, 76, 1, 'n68q62nargec0n53rjpt0t2vom', '2026-02-05 17:25:18'),
(16, 93, 1, 'n68q62nargec0n53rjpt0t2vom', '2026-02-05 17:26:18'),
(17, 55, 1, 'n68q62nargec0n53rjpt0t2vom', '2026-02-05 17:26:34'),
(18, 55, 1, 'n68q62nargec0n53rjpt0t2vom', '2026-02-05 17:28:03'),
(19, 55, 1, 'n68q62nargec0n53rjpt0t2vom', '2026-02-05 17:29:40'),
(20, 55, 1, 'n68q62nargec0n53rjpt0t2vom', '2026-02-05 17:30:57'),
(21, 95, 1, 'n68q62nargec0n53rjpt0t2vom', '2026-02-05 17:31:34'),
(22, 63, 1, 'n68q62nargec0n53rjpt0t2vom', '2026-02-05 17:34:14'),
(23, 63, 1, 'n68q62nargec0n53rjpt0t2vom', '2026-02-05 17:34:22'),
(24, 63, 1, 'n68q62nargec0n53rjpt0t2vom', '2026-02-05 17:37:22'),
(25, 63, 1, 'n68q62nargec0n53rjpt0t2vom', '2026-02-05 17:39:28'),
(26, 67, 1, 'meq82k6ajlt7s3lo32mo7p3gad', '2026-02-05 17:40:26'),
(27, 63, 1, 'n68q62nargec0n53rjpt0t2vom', '2026-02-05 17:41:51'),
(28, 67, 1, 'meq82k6ajlt7s3lo32mo7p3gad', '2026-02-05 17:43:08'),
(29, 67, 1, 'meq82k6ajlt7s3lo32mo7p3gad', '2026-02-05 17:44:57'),
(30, 67, 1, 'meq82k6ajlt7s3lo32mo7p3gad', '2026-02-05 17:45:23'),
(31, 67, 1, 'meq82k6ajlt7s3lo32mo7p3gad', '2026-02-05 17:46:35'),
(32, 67, 1, 'meq82k6ajlt7s3lo32mo7p3gad', '2026-02-05 17:47:19');

-- --------------------------------------------------------

--
-- Table structure for table `search_logs`
--

CREATE TABLE `search_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `query` text DEFAULT NULL,
  `filters` text DEFAULT NULL,
  `results_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `search_logs`
--

INSERT INTO `search_logs` (`id`, `user_id`, `query`, `filters`, `results_count`, `created_at`) VALUES
(1, 1, NULL, '{\"sort\":\"newest\"}', 106, '2026-02-05 16:47:15'),
(2, 1, NULL, '{\"sort\":\"newest\"}', 106, '2026-02-05 16:48:51'),
(3, 1, NULL, '{\"sort\":\"newest\"}', 106, '2026-02-05 17:01:08'),
(4, 2, NULL, '{\"sort\":\"newest\"}', 106, '2026-02-05 17:07:42'),
(5, 1, NULL, '{\"sort\":\"newest\"}', 106, '2026-02-05 17:10:16'),
(6, 1, NULL, '{\"sort\":\"newest\"}', 106, '2026-02-05 17:21:10'),
(7, 1, NULL, '{\"sort\":\"newest\"}', 106, '2026-02-05 17:21:28'),
(8, 2, NULL, '{\"sort\":\"newest\"}', 106, '2026-02-05 17:24:10'),
(9, 1, NULL, '{\"sort\":\"newest\"}', 106, '2026-02-05 17:25:06'),
(10, 1, NULL, '{\"sort\":\"newest\"}', 106, '2026-02-05 17:26:13'),
(11, 1, NULL, '{\"sort\":\"newest\"}', 106, '2026-02-05 17:26:28'),
(12, 1, NULL, '{\"sort\":\"newest\"}', 106, '2026-02-05 17:31:23'),
(13, 1, NULL, '{\"sort\":\"newest\"}', 106, '2026-02-05 17:31:25'),
(14, 1, NULL, '{\"sort\":\"newest\"}', 106, '2026-02-05 17:33:57'),
(15, 1, NULL, '{\"sort\":\"newest\"}', 106, '2026-02-05 17:39:54');

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE `tags` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `profile_image_path` varchar(255) DEFAULT NULL,
  `role` enum('admin','student','staff') NOT NULL DEFAULT 'student',
  `created_at` datetime DEFAULT current_timestamp(),
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `email_verified_at` datetime DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `profile_image_path`, `role`, `created_at`, `status`, `email_verified_at`, `approved_by`, `approved_at`) VALUES
(1, 'super-admin', 'ishikotevu@gmail.com', '$2y$12$g8VZ7xXhjS5uUHSdKfQrWuopJSdGa3.3IzFlNBmlFIP2nhLo8Hfs.', 'uploads/avatars/file_6983a6c9b4ddf8.79807402.png', 'admin', '2025-11-19 15:19:01', 'active', '2025-11-19 15:19:01', NULL, NULL),
(2, 'Okwor Marvellous', 'udechimarvellous@gmail.com', '$2y$12$g8VZ7xXhjS5uUHSdKfQrWuopJSdGa3.3IzFlNBmlFIP2nhLo8Hfs.', NULL, 'student', '2025-11-19 16:30:21', 'active', '2025-11-19 16:30:21', NULL, NULL),
(3, 'Head Librarian', 'librarian@example.com', '$2y$12$g8VZ7xXhjS5uUHSdKfQrWuopJSdGa3.3IzFlNBmlFIP2nhLo8Hfs.', NULL, 'staff', '2025-11-20 05:55:08', 'active', '2025-11-20 05:55:08', NULL, NULL),
(4, 'Assistant Librarian', 'assistant@example.com', '$2y$12$g8VZ7xXhjS5uUHSdKfQrWuopJSdGa3.3IzFlNBmlFIP2nhLo8Hfs.', NULL, 'staff', '2025-11-20 05:55:08', 'active', '2025-11-20 05:55:08', NULL, NULL),
(5, 'IT Support Staff', 'itstaff@example.com', '$2y$12$g8VZ7xXhjS5uUHSdKfQrWuopJSdGa3.3IzFlNBmlFIP2nhLo8Hfs.', NULL, 'staff', '2025-11-20 05:55:08', 'active', '2025-11-20 05:55:08', NULL, NULL),
(6, 'Demo Student', 'student@example.com', '$2y$12$R1wQHjDQMZLlIj8XxbdV3ewcfu8LSwa2kfW8VWD8ATO4TW0VhkKQm', NULL, 'student', '2025-11-20 06:22:57', 'active', '2025-11-20 06:22:57', NULL, NULL),
(7, 'Buchi', 'buchi@megamind.com', '$2y$12$GwfmVTyrOsNevtIeU3uVMuySQ4s4XFafxL7ybxODfHiYeSXGM78Ui', 'uploads/avatars/file_691ee0182109f5.45711067.png', 'admin', '2025-11-20 10:32:08', 'active', '2025-11-20 10:32:08', NULL, NULL),
(8, 'Alita Ayolade Ike', 'alita@gmail.com', '$2y$12$g8VZ7xXhjS5uUHSdKfQrWuopJSdGa3.3IzFlNBmlFIP2nhLo8Hfs.', NULL, 'student', '2025-11-20 13:55:03', 'active', '2025-11-20 13:55:03', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_bookmarks`
--

CREATE TABLE `user_bookmarks` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `resource_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `reg_no` varchar(50) DEFAULT NULL,
  `enrollment_year` year(4) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `staff_id` varchar(50) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `department_staff` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`id`, `user_id`, `reg_no`, `enrollment_year`, `department`, `staff_id`, `designation`, `department_staff`, `phone`, `gender`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, NULL, NULL, 'ADMIN-0001', 'System Administrator', 'ICT', '08000000000', 'Male', '2025-11-20 05:52:25', '2025-11-20 06:25:12'),
(2, 2, '2024/2025/0001', '2025', 'Nursing', NULL, NULL, NULL, '08011111111', 'Male', '2025-11-20 05:52:25', '2026-02-04 21:37:39'),
(6, 3, NULL, NULL, NULL, 'STAFF-0001', 'Head Librarian', 'Library', '08030000001', 'Female', '2025-11-20 05:56:56', '2025-11-20 05:56:56'),
(7, 4, NULL, NULL, NULL, 'STAFF-0002', 'Assistant Librarian', 'Library', '08030000002', 'Male', '2025-11-20 05:56:56', '2025-11-20 05:56:56'),
(8, 5, NULL, NULL, NULL, 'STAFF-0003', 'ICT Support', 'ICT', '08030000003', 'Male', '2025-11-20 05:56:56', '2025-11-20 05:56:56'),
(9, 7, NULL, NULL, NULL, 'ADMIN-0002', 'ICT Support', 'ICT', NULL, NULL, '2025-11-20 10:32:08', '2025-11-20 10:57:19'),
(10, 8, '1234', '2025', 'NURSING', NULL, NULL, NULL, NULL, NULL, '2025-11-20 13:56:10', '2025-11-20 13:56:10');

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `dark_mode` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`id`, `user_id`, `dark_mode`, `created_at`) VALUES
(1, 2, 0, '2026-02-05 12:55:22'),
(2, 1, 0, '2026-02-05 14:46:57');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `app_settings`
--
ALTER TABLE `app_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `email_verification_tokens`
--
ALTER TABLE `email_verification_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_email_verification_token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `featured_resources`
--
ALTER TABLE `featured_resources`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_featured` (`resource_id`,`section`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_featured_section` (`section`,`sort_order`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user_read` (`user_id`,`read_at`,`created_at`);

--
-- Indexes for table `reading_progress`
--
ALTER TABLE `reading_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_progress` (`user_id`,`resource_id`),
  ADD KEY `resource_id` (`resource_id`);

--
-- Indexes for table `resources`
--
ALTER TABLE `resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_resources_category` (`category_id`),
  ADD KEY `idx_resources_type` (`type`),
  ADD KEY `idx_resources_status` (`status`);

--
-- Indexes for table `resource_comments`
--
ALTER TABLE `resource_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `resource_id` (`resource_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_comments_status` (`status`);

--
-- Indexes for table `resource_downloads`
--
ALTER TABLE `resource_downloads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_resource_downloads_resource_date` (`resource_id`,`created_at`),
  ADD KEY `idx_resource_downloads_user_date` (`user_id`,`created_at`);

--
-- Indexes for table `resource_reports`
--
ALTER TABLE `resource_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reported_by` (`reported_by`);

--
-- Indexes for table `resource_reviews`
--
ALTER TABLE `resource_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_review` (`resource_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_reviews_status` (`status`);

--
-- Indexes for table `resource_tags`
--
ALTER TABLE `resource_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_resource_tag` (`resource_id`,`tag_id`),
  ADD KEY `idx_resource_tags_resource` (`resource_id`),
  ADD KEY `idx_resource_tags_tag` (`tag_id`);

--
-- Indexes for table `resource_views`
--
ALTER TABLE `resource_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_resource_views_resource_date` (`resource_id`,`created_at`),
  ADD KEY `idx_resource_views_user_date` (`user_id`,`created_at`);

--
-- Indexes for table `search_logs`
--
ALTER TABLE `search_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_search_logs_created` (`created_at`);

--
-- Indexes for table `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tag` (`slug`),
  ADD KEY `idx_tags_slug` (`slug`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_status` (`status`);

--
-- Indexes for table `user_bookmarks`
--
ALTER TABLE `user_bookmarks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_bookmark` (`user_id`,`resource_id`),
  ADD KEY `resource_id` (`resource_id`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `uniq_reg_no` (`reg_no`),
  ADD UNIQUE KEY `uniq_staff_id` (`staff_id`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_verification_tokens`
--
ALTER TABLE `email_verification_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `featured_resources`
--
ALTER TABLE `featured_resources`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reading_progress`
--
ALTER TABLE `reading_progress`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resources`
--
ALTER TABLE `resources`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resource_comments`
--
ALTER TABLE `resource_comments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resource_downloads`
--
ALTER TABLE `resource_downloads`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resource_reports`
--
ALTER TABLE `resource_reports`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resource_reviews`
--
ALTER TABLE `resource_reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resource_tags`
--
ALTER TABLE `resource_tags`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resource_views`
--
ALTER TABLE `resource_views`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `search_logs`
--
ALTER TABLE `search_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_bookmarks`
--
ALTER TABLE `user_bookmarks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_profiles`
--
ALTER TABLE `user_profiles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `email_verification_tokens`
--
ALTER TABLE `email_verification_tokens`
  ADD CONSTRAINT `email_verification_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `featured_resources`
--
ALTER TABLE `featured_resources`
  ADD CONSTRAINT `featured_resources_ibfk_1` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `featured_resources_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reading_progress`
--
ALTER TABLE `reading_progress`
  ADD CONSTRAINT `reading_progress_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reading_progress_ibfk_2` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `resources`
--
ALTER TABLE `resources`
  ADD CONSTRAINT `resources_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `resources_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `resource_comments`
--
ALTER TABLE `resource_comments`
  ADD CONSTRAINT `resource_comments_ibfk_1` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `resource_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `resource_downloads`
--
ALTER TABLE `resource_downloads`
  ADD CONSTRAINT `resource_downloads_ibfk_1` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `resource_downloads_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `resource_reports`
--
ALTER TABLE `resource_reports`
  ADD CONSTRAINT `resource_reports_ibfk_1` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `resource_reviews`
--
ALTER TABLE `resource_reviews`
  ADD CONSTRAINT `resource_reviews_ibfk_1` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `resource_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `resource_tags`
--
ALTER TABLE `resource_tags`
  ADD CONSTRAINT `resource_tags_ibfk_1` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `resource_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `resource_views`
--
ALTER TABLE `resource_views`
  ADD CONSTRAINT `resource_views_ibfk_1` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `resource_views_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `search_logs`
--
ALTER TABLE `search_logs`
  ADD CONSTRAINT `search_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_bookmarks`
--
ALTER TABLE `user_bookmarks`
  ADD CONSTRAINT `user_bookmarks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_bookmarks_ibfk_2` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `user_profiles_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
