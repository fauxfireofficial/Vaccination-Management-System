-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 22, 2026 at 04:42 PM
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
-- Database: `vaccination_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES
(1, 8, 'add_child', 'Added child: Unknown Child', '::1', '2026-03-08 08:54:38'),
(2, 9, 'delete_user', 'Deleted user: System Admin (admin)', '::1', '2026-03-08 09:56:39');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `child_id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `vaccine_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time DEFAULT NULL,
  `token_number` varchar(20) DEFAULT NULL,
  `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `child_id`, `hospital_id`, `vaccine_id`, `appointment_date`, `appointment_time`, `token_number`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(2, 2, 2, 4, '2026-03-13', NULL, 'VAC-2024002', 'pending', NULL, '2026-03-08 08:48:20', '2026-03-08 10:03:46'),
(7, 3, 4, 11, '2026-03-09', '00:00:00', 'VAC-20260308-D7E13B', 'completed', 'no\n\n\n\n', '2026-03-08 09:33:17', '2026-03-09 10:15:21'),
(8, 3, 4, 26, '2026-03-10', '16:00:00', 'VAC-20260309-3400BD', 'confirmed', '\n', '2026-03-09 09:53:07', '2026-03-09 10:03:17'),
(9, 3, 4, 9, '2026-03-14', '11:00:00', 'VAC-20260313-67E26A', 'confirmed', '\nCONFROM KARDIYA HA APNA BACHY KA DETAILS WAGERA STH LAKY ANA', '2026-03-13 11:22:46', '2026-03-13 11:28:49');

-- --------------------------------------------------------

--
-- Table structure for table `children`
--

CREATE TABLE `children` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `birth_weight` decimal(5,2) DEFAULT NULL,
  `birth_complications` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `children`
--

INSERT INTO `children` (`id`, `parent_id`, `full_name`, `date_of_birth`, `gender`, `blood_group`, `birth_weight`, `birth_complications`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Ali Raza', '2024-03-08', 'male', 'A+', 3.20, NULL, 1, '2026-03-08 08:48:20', NULL),
(2, 1, 'Sara Khan', '2025-09-08', 'female', 'B+', 2.90, NULL, 1, '2026-03-08 08:48:20', NULL),
(3, 4, 'Unknown Child', '2025-06-24', 'other', 'B+', 0.56, 'dasdasd', 1, '2026-03-08 08:54:38', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `hospitals`
--

CREATE TABLE `hospitals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `license_number` varchar(50) NOT NULL,
  `city` varchar(50) NOT NULL,
  `registration_date` date DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `capacity` int(11) DEFAULT 50,
  `working_hours` varchar(100) DEFAULT '9:00 AM - 5:00 PM',
  `emergency_services` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `hospitals`
--

INSERT INTO `hospitals` (`id`, `user_id`, `license_number`, `city`, `registration_date`, `is_verified`, `capacity`, `working_hours`, `emergency_services`, `created_at`, `updated_at`) VALUES
(2, 3, 'HOSP-2024-0002', 'Karachi', NULL, 1, 75, '9:00 AM - 5:00 PM', 0, '2026-03-08 08:48:19', NULL),
(4, 6, 'HOSP-2003-0001', 'Karachi', NULL, 1, 50, '9', 0, '2026-03-08 08:51:45', '2026-03-09 09:50:45');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('appointment','reminder','system') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `related_id`, `related_type`, `is_read`, `created_at`) VALUES
(2, 6, 'appointment', 'New Appointment Booking', 'A new appointment has been booked for your hospital.', 7, 'appointment', 0, '2026-03-08 09:33:17'),
(3, 6, 'appointment', 'New Appointment Booking', 'A new appointment has been booked for your hospital.', 8, 'appointment', 0, '2026-03-09 09:53:07'),
(4, 8, 'appointment', 'Appointment Confirmed', 'Your appointment for Unknown Child on 10 Mar 2026 has been confirmed.', 8, 'appointment', 0, '2026-03-09 10:03:17'),
(5, 8, 'appointment', 'Appointment Confirmed', 'Your appointment for Unknown Child on 09 Mar 2026 has been confirmed.', 7, 'appointment', 0, '2026-03-09 10:03:27'),
(6, 8, 'appointment', 'Vaccination Completed', 'Your child Unknown Child has received Pentavalent - 3 vaccine.', 7, 'appointment', 0, '2026-03-09 10:05:39'),
(7, 8, 'appointment', 'Appointment Cancelled', 'Your appointment for Unknown Child on 09 Mar 2026 has been cancelled. Reason: ', 7, 'appointment', 0, '2026-03-09 10:08:43'),
(8, 8, 'appointment', 'Vaccination Completed', 'Your child Unknown Child has received Pentavalent - 3 vaccine.', 7, 'appointment', 0, '2026-03-09 10:15:21'),
(9, 6, 'appointment', 'New Appointment Booking', 'A new appointment has been booked for your hospital.', 9, 'appointment', 0, '2026-03-13 11:22:47'),
(10, 8, 'appointment', 'Appointment Confirmed', 'Your appointment for Unknown Child on 14 Mar 2026 has been confirmed.', 9, 'appointment', 0, '2026-03-13 11:28:49');

-- --------------------------------------------------------

--
-- Table structure for table `otp_verification`
--

CREATE TABLE `otp_verification` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_verified` tinyint(1) DEFAULT 0,
  `purpose` enum('registration','password_reset') DEFAULT 'password_reset',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `otp_verification`
--

INSERT INTO `otp_verification` (`id`, `email`, `otp_code`, `expires_at`, `is_verified`, `purpose`, `created_at`) VALUES
(1, 'iqbal063032@gmail.com', '052270', '2026-03-10 17:09:27', 0, 'password_reset', '2026-03-10 16:59:27'),
(2, 'iqbal063034@gmail.com', '883594', '2026-03-10 17:10:40', 0, 'password_reset', '2026-03-10 17:00:40'),
(3, 'fauxfireofficial@gmail.com', '214049', '2026-03-10 17:02:23', 1, 'password_reset', '2026-03-10 17:01:45');

-- --------------------------------------------------------

--
-- Table structure for table `parents`
--

CREATE TABLE `parents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `cnic` varchar(15) NOT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `emergency_contact` varchar(15) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `parents`
--

INSERT INTO `parents` (`id`, `user_id`, `cnic`, `occupation`, `emergency_contact`, `created_at`) VALUES
(1, 5, '12345-1234567-1', 'Teacher', '03451234567', '2026-03-08 08:48:19'),
(4, 8, '12345-1234567-2', '', '', '2026-03-08 08:53:47');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','number','boolean','email','phone') DEFAULT 'text',
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_by`, `updated_at`) VALUES
(1, 'site_name', 'VMS', 'text', 'System name', NULL, '2026-03-10 16:15:52'),
(2, 'site_title', 'Vaccination Management System', 'text', 'Browser title', NULL, '2026-03-10 16:15:52'),
(3, 'site_description', 'Complete child vaccination management system', 'text', 'Site description', NULL, '2026-03-10 16:15:52'),
(4, 'site_keywords', 'vaccination, child immunization, vaccine tracker', 'text', 'SEO keywords', NULL, '2026-03-10 16:15:52'),
(5, 'admin_email', 'admin@vaccinecare.com', 'email', 'Primary admin email', NULL, '2026-03-10 16:15:52'),
(6, 'contact_email', 'contact@vaccinecare.com', 'email', 'Contact form email', NULL, '2026-03-10 16:15:52'),
(7, 'contact_phone', '+92 300 1234567', 'phone', 'Contact phone number', NULL, '2026-03-10 16:15:52'),
(8, 'contact_address', '123 Vaccine Street, Health City, Karachi', 'text', 'Office address', NULL, '2026-03-10 16:15:52'),
(9, 'items_per_page', '15', 'number', 'Number of items per page', NULL, '2026-03-10 16:15:52'),
(10, 'date_format', 'd M Y', 'text', 'Date display format', NULL, '2026-03-10 16:15:52'),
(11, 'time_format', 'h:i A', 'text', 'Time display format', NULL, '2026-03-10 16:15:52'),
(12, 'timezone', 'Asia/Karachi', 'text', 'System timezone', NULL, '2026-03-10 16:15:52'),
(13, 'maintenance_mode', '0', 'boolean', 'Maintenance mode (1=on, 0=off)', NULL, '2026-03-10 16:15:52'),
(14, 'allow_registration', '1', 'boolean', 'Allow new user registrations', NULL, '2026-03-10 16:15:52'),
(15, 'allow_appointments', '1', 'boolean', 'Allow appointment booking', NULL, '2026-03-10 16:15:52'),
(16, 'smtp_host', 'smtp.gmail.com', 'text', 'SMTP server', NULL, '2026-03-10 16:15:52'),
(17, 'smtp_port', '587', 'number', 'SMTP port', NULL, '2026-03-10 16:15:52'),
(18, 'smtp_username', '', 'text', 'SMTP username', NULL, '2026-03-10 16:15:52'),
(19, 'smtp_password', '', 'text', 'SMTP password', NULL, '2026-03-10 16:15:52'),
(20, 'smtp_encryption', 'tls', 'text', 'SMTP encryption (tls/ssl)', NULL, '2026-03-10 16:15:52'),
(21, 'email_notifications', '1', 'boolean', 'Send email notifications', NULL, '2026-03-10 16:15:52'),
(22, 'sms_notifications', '0', 'boolean', 'Send SMS notifications', NULL, '2026-03-10 16:15:52'),
(23, 'appointment_reminders', '1', 'boolean', 'Send appointment reminders', NULL, '2026-03-10 16:15:52'),
(24, 'reminder_days', '1', 'number', 'Days before appointment to remind', NULL, '2026-03-10 16:15:52'),
(25, 'auto_verify_hospitals', '0', 'boolean', 'Auto-verify new hospitals', NULL, '2026-03-10 16:15:52'),
(26, 'default_hospital_capacity', '50', 'number', 'Default daily capacity', NULL, '2026-03-10 16:15:52'),
(27, 'max_appointments_per_day', '100', 'number', 'Maximum appointments per day', NULL, '2026-03-10 16:15:52'),
(28, 'epi_schedule_version', '2024', 'number', 'EPI schedule version', NULL, '2026-03-10 16:15:52'),
(29, 'show_vaccine_info', '1', 'boolean', 'Show vaccine info to public', NULL, '2026-03-10 16:15:52'),
(30, 'password_min_length', '8', 'number', 'Minimum password length', NULL, '2026-03-10 16:15:52'),
(31, 'session_timeout', '30', 'number', 'Session timeout in minutes', NULL, '2026-03-10 16:15:52'),
(32, 'max_login_attempts', '5', 'number', 'Maximum login attempts', NULL, '2026-03-10 16:15:52'),
(33, 'lockout_time', '15', 'number', 'Lockout time in minutes', NULL, '2026-03-10 16:15:52');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('parent','hospital','admin') NOT NULL DEFAULT 'parent',
  `phone` varchar(15) NOT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `avatar` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `role`, `phone`, `address`, `status`, `last_login`, `remember_token`, `created_at`, `updated_at`, `avatar`) VALUES
(3, 'Children Medical Complex', 'children@hospital.com', '$2y$10$2oKan2fTOXy74SmpDh3gfO3/MI4n3VplUzinKtOPjZ/Pa0YftSwHS', 'hospital', '03441234567', 'Hospital Road, Karachi', 'active', NULL, NULL, '2026-03-08 08:48:19', NULL, NULL),
(5, 'Test Parent', 'parent@test.com', '$2y$10$TNQLV0IhHeqtCVYd4/B19Otg9ygOSnYHikkx.MYjeZekH1Ch.XTfC', 'parent', '03331234569', 'House 123, Block A, Lahore', 'active', '2026-03-22 15:35:55', NULL, '2026-03-08 08:48:19', '2026-03-22 15:35:55', NULL),
(6, 'MUHAMMAD IQBAL', 'fauxfireofficial@gmail.com', '$2y$10$Gov.kWKNcy86dTPwhvz6POgd.h1ntN2yu6g7MLsDHJrbH6T4iZSQ2', 'hospital', '03162115711', 'SEC 38/A, SCH 33,\r\nFL D-7, SADDAIN ARCADE RIZWAN SOCIETY', 'active', '2026-03-13 11:21:55', 'ea25c5b4be1cf755edb2e4d76d7a4810bdd62b00ddd0267cdc2da963fd02a38c', '2026-03-08 08:51:45', '2026-03-13 11:21:55', NULL),
(8, 'M.Iqbal', 'dangerboy04200@gmail.com', '$2y$10$4zDyDyy9aid/ujlPEp6Muu5tmakjd8rBZaN.xLC9YhIJtPuw10vE6', 'parent', '03162115711', '12345-1234567-112345-1234567-1', 'active', '2026-03-22 15:37:06', '2f3e0541ff66db97ed404e715e0860b4d338b88546273aaf91a9a0e90ee1376d', '2026-03-08 08:53:47', '2026-03-22 15:37:06', NULL),
(9, 'MUHAMMAD IQBAL', 'iqbal100q@gmail.com', '$2y$10$TmDYe7oViK/OxG8ocPDfj.NSOBVQnEK15wnoaX6JdejkKxxGeGvym', 'admin', '03162115711', 'Your Address Here', 'active', '2026-03-09 10:24:41', 'd6933e1c8b0b17c987c11d8e480183c3b6ebf72a47b62897e09245e1402d51d3', '2026-03-08 09:54:35', '2026-03-09 10:24:41', NULL),
(12, 'System Admin', 'admin@vaccinecare.com', '$2y$10$.YqQ5E87Spqr5yvDy5ohgOxGGlumsaYBc7sOqDRWoWt/rJrnRGEUa', 'admin', '03001234567', 'Karachi', 'active', '2026-03-22 15:31:08', NULL, '2026-03-22 15:29:54', '2026-03-22 15:31:08', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vaccination_records`
--

CREATE TABLE `vaccination_records` (
  `id` int(11) NOT NULL,
  `child_id` int(11) NOT NULL,
  `vaccine_id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `administered_date` date NOT NULL,
  `dose_number` int(11) NOT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `administered_by` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `vaccination_records`
--

INSERT INTO `vaccination_records` (`id`, `child_id`, `vaccine_id`, `hospital_id`, `administered_date`, `dose_number`, `batch_number`, `next_due_date`, `administered_by`, `notes`, `created_at`) VALUES
(3, 3, 11, 4, '2026-03-08', 3, NULL, NULL, NULL, 'Completed from appointment', '2026-03-08 10:03:50'),
(4, 3, 11, 4, '2026-03-09', 3, '0', '2026-03-23', NULL, '', '2026-03-09 10:05:39'),
(5, 3, 11, 4, '2026-03-09', 3, '0', NULL, NULL, '', '2026-03-09 10:15:21');

-- --------------------------------------------------------

--
-- Table structure for table `vaccines`
--

CREATE TABLE `vaccines` (
  `id` int(11) NOT NULL,
  `vaccine_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `age_group` varchar(50) NOT NULL,
  `dose_number` int(11) DEFAULT NULL,
  `is_epi` tinyint(1) DEFAULT 1,
  `is_mandatory` tinyint(1) DEFAULT 1,
  `status` enum('available','unavailable') DEFAULT 'available',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `vaccines`
--

INSERT INTO `vaccines` (`id`, `vaccine_name`, `description`, `age_group`, `dose_number`, `is_epi`, `is_mandatory`, `status`, `notes`, `created_at`) VALUES
(1, 'BCG', 'Protects against Tuberculosis', 'At Birth', 1, 1, 1, 'available', 'Given within 24 hours of birth', '2026-03-08 08:48:20'),
(2, 'OPV-0', 'Oral Polio Vaccine - Dose 0', 'At Birth', 1, 1, 1, 'available', 'Given at birth', '2026-03-08 08:48:20'),
(3, 'Hepatitis B - 1', 'First dose of Hepatitis B', 'At Birth', 1, 1, 1, 'available', 'Given within 24 hours', '2026-03-08 08:48:20'),
(4, 'Pentavalent - 1', 'Diphtheria, Tetanus, Pertussis, Hepatitis B, Hib', '6 Weeks', 1, 1, 1, 'available', 'First dose', '2026-03-08 08:48:20'),
(5, 'PCV - 1', 'Pneumococcal Vaccine', '6 Weeks', 1, 1, 1, 'available', 'First dose', '2026-03-08 08:48:20'),
(6, 'Rotavirus - 1', 'Protects against severe diarrhea', '6 Weeks', 1, 1, 1, 'available', 'First dose', '2026-03-08 08:48:20'),
(7, 'IPV - 1', 'Inactivated Polio Vaccine', '6 Weeks', 1, 1, 1, 'available', 'First dose', '2026-03-08 08:48:20'),
(8, 'Pentavalent - 2', 'Second dose of Pentavalent', '10 Weeks', 2, 1, 1, 'available', 'Second dose', '2026-03-08 08:48:20'),
(9, 'PCV - 2', 'Second dose of PCV', '10 Weeks', 2, 1, 1, 'available', 'Second dose', '2026-03-08 08:48:20'),
(10, 'Rotavirus - 2', 'Second dose of Rotavirus', '10 Weeks', 2, 1, 1, 'available', 'Second dose', '2026-03-08 08:48:20'),
(11, 'Pentavalent - 3', 'Third dose of Pentavalent', '14 Weeks', 3, 1, 1, 'available', 'Third dose', '2026-03-08 08:48:20'),
(12, 'PCV - 3', 'Third dose of PCV', '14 Weeks', 3, 1, 1, 'available', 'Third dose', '2026-03-08 08:48:20'),
(13, 'IPV - 2', 'Second dose of IPV', '14 Weeks', 2, 1, 1, 'available', 'Second dose', '2026-03-08 08:48:20'),
(14, 'Measles - 1', 'First dose of Measles vaccine', '9 Months', 1, 1, 1, 'available', 'First measles dose', '2026-03-08 08:48:20'),
(15, 'Vitamin A', 'Vitamin A supplement', '9 Months', 1, 1, 1, 'available', 'Given every 6 months', '2026-03-08 08:48:20'),
(16, 'MMR - 1', 'Measles, Mumps, Rubella', '12 Months', 1, 1, 1, 'available', 'First dose', '2026-03-08 08:48:20'),
(17, 'Typhoid', 'Protects against Typhoid fever', '12 Months', 1, 1, 1, 'available', 'Single dose', '2026-03-08 08:48:20'),
(18, 'Pentavalent Booster', 'Booster dose of Pentavalent', '18 Months', 4, 1, 1, 'available', 'Booster dose', '2026-03-08 08:48:20'),
(19, 'IPV Booster', 'Booster dose of IPV', '18 Months', 3, 1, 1, 'available', 'Booster dose', '2026-03-08 08:48:20'),
(20, 'Measles - 2', 'Second dose of Measles', '18 Months', 2, 1, 1, 'available', 'Second dose', '2026-03-08 08:48:20'),
(21, 'Vitamin A - 2', 'Vitamin A supplement', '18 Months', 2, 1, 1, 'available', 'Second dose', '2026-03-08 08:48:20'),
(22, 'DT Booster', 'Diphtheria, Tetanus booster', '4-5 Years', 1, 1, 1, 'available', 'School entry booster', '2026-03-08 08:48:20'),
(23, 'OPV Booster', 'Oral Polio Vaccine booster', '4-5 Years', 1, 1, 1, 'available', 'Booster dose', '2026-03-08 08:48:20'),
(24, 'MMR - 2', 'Second dose of MMR', '4-5 Years', 2, 1, 1, 'available', 'Second dose', '2026-03-08 08:48:20'),
(25, 'Tdap', 'Tetanus, Diphtheria, Pertussis', '11-12 Years', 1, 1, 1, 'available', 'Adolescent booster', '2026-03-08 08:48:20'),
(26, 'HPV', 'Human Papillomavirus (for girls)', '11-12 Years', 2, 1, 1, 'available', 'Two doses, 6 months apart', '2026-03-08 08:48:20'),
(27, 'Td Booster', 'Tetanus, Diphtheria booster', '15-16 Years', 2, 1, 1, 'available', 'Final booster', '2026-03-08 08:48:20'),
(28, 'HVSC', '', '15-16 Years', 1, 1, 1, 'unavailable', '', '2026-03-09 10:27:27');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_number` (`token_number`),
  ADD KEY `child_id` (`child_id`),
  ADD KEY `hospital_id` (`hospital_id`),
  ADD KEY `vaccine_id` (`vaccine_id`);

--
-- Indexes for table `children`
--
ALTER TABLE `children`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `hospitals`
--
ALTER TABLE `hospitals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_number` (`license_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `otp_verification`
--
ALTER TABLE `otp_verification`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_otp` (`otp_code`);

--
-- Indexes for table `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cnic` (`cnic`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `child_id` (`child_id`),
  ADD KEY `vaccine_id` (`vaccine_id`),
  ADD KEY `hospital_id` (`hospital_id`);

--
-- Indexes for table `vaccines`
--
ALTER TABLE `vaccines`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `children`
--
ALTER TABLE `children`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `hospitals`
--
ALTER TABLE `hospitals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `otp_verification`
--
ALTER TABLE `otp_verification`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `parents`
--
ALTER TABLE `parents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `vaccines`
--
ALTER TABLE `vaccines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`child_id`) REFERENCES `children` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `children`
--
ALTER TABLE `children`
  ADD CONSTRAINT `children_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hospitals`
--
ALTER TABLE `hospitals`
  ADD CONSTRAINT `hospitals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `parents`
--
ALTER TABLE `parents`
  ADD CONSTRAINT `parents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `settings`
--
ALTER TABLE `settings`
  ADD CONSTRAINT `settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  ADD CONSTRAINT `vaccination_records_ibfk_1` FOREIGN KEY (`child_id`) REFERENCES `children` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vaccination_records_ibfk_2` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vaccination_records_ibfk_3` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
