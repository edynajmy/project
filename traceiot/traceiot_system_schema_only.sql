-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
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
-- Database: `traceiot_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_ID` int(11) NOT NULL,
  `name` varchar(45) DEFAULT NULL,
  `email` varchar(45) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `position` varchar(100) DEFAULT 'Laboratory Officer',
  `office_location` varchar(100) DEFAULT 'IoT Laboratory, Block A',
  `operating_hours` varchar(100) DEFAULT '8:00 AM - 5:00 PM (Mon - Fri)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `admin`
--

-- --------------------------------------------------------

--
-- Table structure for table `borrowing_detail`
--

CREATE TABLE `borrowing_detail` (
  `quantity_borrowed` varchar(45) DEFAULT NULL,
  `Record_ID` int(11) NOT NULL,
  `Component_ID` int(11) NOT NULL,
  `detail_id` int(11) NOT NULL,
  `return_condition` varchar(50) DEFAULT NULL,
  `return_remarks` text DEFAULT NULL,
  `return_proof` varchar(255) DEFAULT NULL,
  `good_quantity` int(11) DEFAULT 0,
  `minor_damage_quantity` int(11) DEFAULT 0,
  `damaged_quantity` int(11) DEFAULT 0,
  `missing_quantity` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `borrowing_detail`
--

-- --------------------------------------------------------

--
-- Table structure for table `borrowing_record`
--

CREATE TABLE `borrowing_record` (
  `Record_ID` int(11) NOT NULL,
  `borrow_date` varchar(45) DEFAULT NULL,
  `due_date` varchar(45) DEFAULT NULL,
  `return_date` varchar(45) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` varchar(45) DEFAULT NULL,
  `Student_ID` int(11) NOT NULL,
  `admin_ID` int(11) DEFAULT NULL,
  `return_proof` varchar(255) DEFAULT NULL,
  `collection_time` varchar(100) DEFAULT NULL,
  `collection_venue` varchar(255) DEFAULT NULL,
  `collection_day` varchar(20) DEFAULT NULL,
  `reminder_sent` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `borrowing_record`
--

-- --------------------------------------------------------

--
-- Table structure for table `component`
--

CREATE TABLE `component` (
  `Component_ID` int(11) NOT NULL,
  `name` varchar(45) DEFAULT NULL,
  `image` varchar(255) DEFAULT 'default.png',
  `category` varchar(45) DEFAULT NULL,
  `total_quantity` varchar(45) DEFAULT NULL,
  `cabinet_num` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `component`
--

-- --------------------------------------------------------

--
-- Table structure for table `damaged_item_report`
--

CREATE TABLE `damaged_item_report` (
  `report_id` int(11) NOT NULL,
  `Record_ID` int(11) NOT NULL,
  `Component_ID` int(11) NOT NULL,
  `issue_type` varchar(50) NOT NULL,
  `issue_quantity` int(11) NOT NULL,
  `remarks` text DEFAULT NULL,
  `report_status` varchar(50) DEFAULT 'Need Replacement',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `damaged_item_report`
--

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `Student_ID` int(11) NOT NULL,
  `name` varchar(45) DEFAULT NULL,
  `email` varchar(45) DEFAULT NULL,
  `phone_num` varchar(45) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `faculty` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `student`
--

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_ID`);

--
-- Indexes for table `borrowing_detail`
--
ALTER TABLE `borrowing_detail`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `fk_BORROWING_DETAIL_BORROWING_RECORD1_idx` (`Record_ID`),
  ADD KEY `fk_BORROWING_DETAIL_COMPONENT1_idx` (`Component_ID`);

--
-- Indexes for table `borrowing_record`
--
ALTER TABLE `borrowing_record`
  ADD PRIMARY KEY (`Record_ID`),
  ADD KEY `fk_BORROWING_RECORD_STUDENT_idx` (`Student_ID`),
  ADD KEY `fk_BORROWING_RECORD_STAFF1_idx` (`admin_ID`);

--
-- Indexes for table `component`
--
ALTER TABLE `component`
  ADD PRIMARY KEY (`Component_ID`);

--
-- Indexes for table `damaged_item_report`
--
ALTER TABLE `damaged_item_report`
  ADD PRIMARY KEY (`report_id`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`Student_ID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `borrowing_detail`
--
ALTER TABLE `borrowing_detail`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `borrowing_record`
--
ALTER TABLE `borrowing_record`
  MODIFY `Record_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `component`
--
ALTER TABLE `component`
  MODIFY `Component_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `damaged_item_report`
--
ALTER TABLE `damaged_item_report`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `student`
--
ALTER TABLE `student`
  MODIFY `Student_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `borrowing_detail`
--
ALTER TABLE `borrowing_detail`
  ADD CONSTRAINT `fk_BORROWING_DETAIL_BORROWING_RECORD1` FOREIGN KEY (`Record_ID`) REFERENCES `borrowing_record` (`Record_ID`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_BORROWING_DETAIL_COMPONENT1` FOREIGN KEY (`Component_ID`) REFERENCES `component` (`Component_ID`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `borrowing_record`
--
ALTER TABLE `borrowing_record`
  ADD CONSTRAINT `fk_BORROWING_RECORD_STAFF1` FOREIGN KEY (`admin_ID`) REFERENCES `admin` (`admin_ID`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_BORROWING_RECORD_STUDENT` FOREIGN KEY (`Student_ID`) REFERENCES `student` (`Student_ID`) ON DELETE NO ACTION ON UPDATE NO ACTION;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
