-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 28, 2025 at 03:52 PM
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
-- Database: `security_alerts_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `client_alerts`
--

CREATE TABLE `client_alerts` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `alert_id` int(11) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `notified_via_email` tinyint(1) DEFAULT 0,
  `notified_via_sms` tinyint(1) DEFAULT 0,
  `notified_via_dashboard` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_alerts`
--

INSERT INTO `client_alerts` (`id`, `client_id`, `alert_id`, `is_read`, `notified_via_email`, `notified_via_sms`, `notified_via_dashboard`, `created_at`) VALUES
(61, 2, 18, 1, 1, 1, 1, '2025-09-21 13:21:24');

-- --------------------------------------------------------

--
-- Table structure for table `client_users`
--

CREATE TABLE `client_users` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `message`, `is_read`, `created_at`, `updated_at`, `is_deleted`, `deleted_by`, `deleted_at`) VALUES
(29, 1, 2, 'hi', 0, '2025-09-25 20:23:35', '2025-09-25 20:23:35', 0, NULL, NULL),
(38, 8, 1, 'hi', 1, '2025-09-25 20:51:30', '2025-09-25 21:04:08', 0, NULL, NULL),
(39, 1, 8, 'how are you today?', 1, '2025-09-25 20:51:50', '2025-09-25 20:51:51', 0, NULL, NULL),
(40, 8, 1, 'I am fine', 1, '2025-09-25 20:52:15', '2025-09-25 21:04:08', 0, NULL, NULL),
(41, 8, 1, 'hello', 1, '2025-09-25 21:03:45', '2025-09-25 21:04:08', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `created_at`, `expires_at`, `used`) VALUES
(2, 'rimround@gmail.com', 'b823f50d993588e0d92d596bf283d2cdda9c03fc974440e40cfdcbe09e1f3a79', '2025-09-10 09:52:45', '2025-09-10 11:52:45', 0);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reference` varchar(100) NOT NULL,
  `plan` varchar(20) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `user_id`, `reference`, `plan`, `amount`, `status`, `paid_at`, `created_at`, `updated_at`) VALUES
(1, 8, 'ADMIN_68d455ed64eee', 'monthly', 5000000.00, 'success', '2025-09-24 20:34:53', '2025-09-24 20:34:53', '2025-09-24 20:34:53');

-- --------------------------------------------------------

--
-- Table structure for table `security_alerts`
--

CREATE TABLE `security_alerts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `severity` enum('low','medium','high','critical') NOT NULL,
  `categories` varchar(255) NOT NULL,
  `alert_begins` datetime NOT NULL,
  `alert_expires` datetime NOT NULL,
  `event` text NOT NULL,
  `affected_areas` text NOT NULL,
  `time_frame` varchar(255) NOT NULL,
  `impact` text NOT NULL,
  `summary` text NOT NULL,
  `advice` text NOT NULL,
  `source` varchar(255) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `security_alerts`
--

INSERT INTO `security_alerts` (`id`, `title`, `severity`, `categories`, `alert_begins`, `alert_expires`, `event`, `affected_areas`, `time_frame`, `impact`, `summary`, `advice`, `source`, `image_path`, `latitude`, `longitude`, `created_by`, `created_at`, `updated_at`) VALUES
(18, 'fsdf', 'low', 'Armed Attack', '2025-09-21 14:21:00', '2025-09-22 14:21:00', 'sdfsd', 'sdfsd', '2 Day', 'sdfsd', 'sdfsd', 'sdfsd', 'sdfsd', '', 76.31867600, -63.07777100, 1, '2025-09-21 13:21:24', '2025-09-21 13:23:13');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('admin','client_individual','client_company') DEFAULT 'client_individual',
  `company_name` varchar(100) DEFAULT NULL,
  `company_size` varchar(50) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `payment_plan` varchar(20) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `payment_date` timestamp NULL DEFAULT NULL,
  `subscription_expiry` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `first_name`, `last_name`, `phone`, `address`, `password`, `user_type`, `company_name`, `company_size`, `status`, `payment_plan`, `payment_reference`, `payment_date`, `subscription_expiry`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@example.com', NULL, NULL, NULL, NULL, '$2y$10$PPOmf3VSh3PXphsBCEsf2.JfBku8qrEEvQDhQUHsFx30W8e8o9ukq', 'admin', NULL, NULL, 'approved', NULL, NULL, NULL, NULL, '2025-09-04 20:10:51', '2025-09-04 20:28:28'),
(2, 'nojfx', 'onojadanonoja@gmail.com', 'Onoja', 'DANIEL', '08078200765', NULL, '$2y$10$LjkOWz2vdwlxCiCjxUgY6O/G6OjwEg1zovNKVupUgn/yg23VpIhL2', 'client_individual', '', '', 'approved', NULL, NULL, NULL, NULL, '2025-09-07 14:29:15', '2025-09-07 14:29:15'),
(8, 'onojadaniel', 'rimround@gmail.com', 'ONOJA', 'DANIEL', '08078200765', NULL, '$2y$10$AjeZzkPkgHa2h4hZWgBZFOmo8S1yluQDG3TICeCoBYuIw2OysXMXO', 'client_individual', '', '', 'approved', 'monthly', NULL, '2025-09-24 20:34:53', '2025-10-24', '2025-09-24 20:34:53', '2025-09-24 20:34:53');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `client_alerts`
--
ALTER TABLE `client_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `alert_id` (`alert_id`);

--
-- Indexes for table `client_users`
--
ALTER TABLE `client_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_username` (`username`),
  ADD UNIQUE KEY `unique_email` (`email`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `idx_messages_sender_receiver` (`sender_id`,`receiver_id`),
  ADD KEY `idx_messages_created_at` (`created_at`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_token` (`token`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `security_alerts`
--
ALTER TABLE `security_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `client_alerts`
--
ALTER TABLE `client_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `client_users`
--
ALTER TABLE `client_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `security_alerts`
--
ALTER TABLE `security_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `client_alerts`
--
ALTER TABLE `client_alerts`
  ADD CONSTRAINT `client_alerts_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_alerts_ibfk_2` FOREIGN KEY (`alert_id`) REFERENCES `security_alerts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_users`
--
ALTER TABLE `client_users`
  ADD CONSTRAINT `client_users_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `security_alerts`
--
ALTER TABLE `security_alerts`
  ADD CONSTRAINT `security_alerts_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
