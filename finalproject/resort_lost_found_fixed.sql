-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 08, 2026 at 10:25 AM
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
-- Database: `resort_lost_found`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `retention_months` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `retention_months`) VALUES
(1, 'Small Size Item', 6),
(2, 'Medium Size Item', 6),
(3, 'Big Size Item', 6),
(4, 'Valuable Item', 8);

-- --------------------------------------------------------

--
-- Table structure for table `claims`
--

CREATE TABLE `claims` (
  `claim_id` int(11) NOT NULL,
  `found_item_id` int(11) NOT NULL,
  `lost_report_id` int(11) DEFAULT NULL,
  `claimant_id` int(11) NOT NULL,
  `handled_by_recorder_id` int(11) DEFAULT NULL,
  `proof_notes` text NOT NULL,
  `proof_image_path` varchar(255) DEFAULT NULL,
  `status` enum('Pending Verification','Approved','Rejected') NOT NULL DEFAULT 'Pending Verification',
  `staff_notes` text DEFAULT NULL,
  `date_claimed` datetime NOT NULL DEFAULT current_timestamp(),
  `date_processed` datetime DEFAULT NULL,
  `verification_notes` text DEFAULT NULL,
  `checkout_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `found_items`
--

CREATE TABLE `found_items` (
  `item_id` int(11) NOT NULL,
  `item_code` varchar(30) NOT NULL,
  `title` varchar(150) NOT NULL,
  `category_id` int(11) NOT NULL,
  `location_found` varchar(150) NOT NULL,
  `storage_location_id` int(11) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT 'default_item.jpg',
  `reporter_id` int(11) NOT NULL,
  `recorder_handler_id` int(11) DEFAULT NULL,
  `status` enum('Pending Check-in','Stored','Claim Pending','Claimed','Expired','Disposed') DEFAULT 'Pending Check-in',
  `date_found` date NOT NULL,
  `expiry_date` date NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lost_items`
--

CREATE TABLE `lost_items` (
  `report_id` int(11) NOT NULL,
  `reference_code` varchar(30) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `item_title` varchar(150) NOT NULL,
  `category_id` int(11) NOT NULL,
  `location_lost` varchar(150) NOT NULL,
  `date_lost` date NOT NULL,
  `description` text NOT NULL,
  `status` enum('Searching','Matched','Resolved','Closed') DEFAULT 'Searching',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `date_reported` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `found_item_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` enum('No','Yes') DEFAULT 'No',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `storage_locations`
--

CREATE TABLE `storage_locations` (
  `location_id` int(11) NOT NULL,
  `location_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `building_zone` varchar(100) DEFAULT NULL,
  `capacity` int(11) NOT NULL DEFAULT 10,
  `status` enum('Available','Full','Maintenance') NOT NULL DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','Recorder','Reporter') NOT NULL DEFAULT 'Reporter',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `phone_number`, `password`, `role`, `created_at`, `updated_at`) VALUES
(1, 'EDY NUR NAJMY HAMEDY', 'edynajmy@gmail.com', '01139358952', '$2y$10$jMAJBi822fe7z8FLlotuc.gaQJIkgjHfebTpyiWm0HhncRBYpKriu', 'Reporter', '2026-09-08 08:13:29', '2026-09-08 08:13:29');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `claims`
--
ALTER TABLE `claims`
  ADD PRIMARY KEY (`claim_id`),
  ADD KEY `fk_claim_found` (`found_item_id`),
  ADD KEY `fk_claim_lost` (`lost_report_id`),
  ADD KEY `fk_claimant` (`claimant_id`),
  ADD KEY `fk_claim_recorder` (`handled_by_recorder_id`);

--
-- Indexes for table `found_items`
--
ALTER TABLE `found_items`
  ADD PRIMARY KEY (`item_id`),
  ADD UNIQUE KEY `item_code` (`item_code`),
  ADD KEY `fk_found_category` (`category_id`),
  ADD KEY `fk_found_storage` (`storage_location_id`),
  ADD KEY `fk_found_reporter` (`reporter_id`),
  ADD KEY `fk_found_recorder` (`recorder_handler_id`);

--
-- Indexes for table `lost_items`
--
ALTER TABLE `lost_items`
  ADD PRIMARY KEY (`report_id`),
  ADD UNIQUE KEY `reference_code` (`reference_code`),
  ADD KEY `fk_lost_reporter` (`reporter_id`),
  ADD KEY `fk_lost_category` (`category_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `fk_notify_found` (`found_item_id`);

--
-- Indexes for table `storage_locations`
--
ALTER TABLE `storage_locations`
  ADD PRIMARY KEY (`location_id`),
  ADD UNIQUE KEY `location_name` (`location_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `claims`
--
ALTER TABLE `claims`
  MODIFY `claim_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `found_items`
--
ALTER TABLE `found_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lost_items`
--
ALTER TABLE `lost_items`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `storage_locations`
--
ALTER TABLE `storage_locations`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `claims`
--
ALTER TABLE `claims`
  ADD CONSTRAINT `fk_claim_found` FOREIGN KEY (`found_item_id`) REFERENCES `found_items` (`item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_claim_lost` FOREIGN KEY (`lost_report_id`) REFERENCES `lost_items` (`report_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_claimant` FOREIGN KEY (`claimant_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_claim_recorder` FOREIGN KEY (`handled_by_recorder_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `found_items`
--
ALTER TABLE `found_items`
  ADD CONSTRAINT `fk_found_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`),
  ADD CONSTRAINT `fk_found_recorder` FOREIGN KEY (`recorder_handler_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_found_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_found_storage` FOREIGN KEY (`storage_location_id`) REFERENCES `storage_locations` (`location_id`) ON DELETE SET NULL;

--
-- Constraints for table `lost_items`
--
ALTER TABLE `lost_items`
  ADD CONSTRAINT `fk_lost_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`),
  ADD CONSTRAINT `fk_lost_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notify_found` FOREIGN KEY (`found_item_id`) REFERENCES `found_items` (`item_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
