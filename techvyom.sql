-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 24, 2025 at 07:44 PM
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
-- Database: `techvyom`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `alumni_basic`
--

CREATE TABLE `alumni_basic` (
  `id` int(11) NOT NULL COMMENT 'set as Primary Key',
  `timestamp` datetime DEFAULT current_timestamp() COMMENT 'This stores Google form time',
  `email` varchar(100) NOT NULL COMMENT 'Keep unique later',
  `full_name` varchar(100) NOT NULL,
  `enrollment_no` varchar(50) DEFAULT NULL COMMENT 'In case missing for older alumni',
  `course` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `year_admission` int(4) DEFAULT NULL,
  `year_passing` int(4) DEFAULT NULL,
  `contact_number` varchar(15) DEFAULT NULL COMMENT 'for Indian phone numbers',
  `linkedin_profile` varchar(255) DEFAULT NULL,
  `college_doc_path` varchar(255) DEFAULT NULL,
  `verified` tinyint(1) NOT NULL COMMENT '0=pending, 1=approved, -1=rejected',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Auto record update time'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `alumni_education`
--

CREATE TABLE `alumni_education` (
  `edu_id` int(11) NOT NULL COMMENT 'Set as Primary Key',
  `alumni_id` int(11) NOT NULL COMMENT 'This will reference alumni_basic.id',
  `has_higher_edu` tinyint(1) NOT NULL COMMENT '1 = yes, 0 = no',
  `degree_name` varchar(100) DEFAULT NULL COMMENT 'Example: Btech, MBA',
  `year_admission` int(4) DEFAULT NULL,
  `institution_name` varchar(150) NOT NULL,
  `university_name` varchar(150) NOT NULL,
  `edu_doc_path` varchar(255) NOT NULL COMMENT 'Document proof (optional)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `alumni_employment`
--

CREATE TABLE `alumni_employment` (
  `emp_id` int(11) NOT NULL COMMENT 'set as primary key',
  `alumni_id` int(11) NOT NULL COMMENT 'foreign key linked to alumni_basic.id',
  `employment_status` enum('Working','Not working','Student','Other') NOT NULL COMMENT 'status of alumni',
  `organisation` varchar(150) DEFAULT NULL COMMENT 'current company/ organisation',
  `designation` varchar(100) DEFAULT NULL COMMENT 'job title',
  `location` varchar(100) DEFAULT NULL COMMENT 'job city/ country',
  `experience_years` decimal(4,1) DEFAULT NULL COMMENT 'Example: 2.5 years',
  `annual_package` varchar(50) DEFAULT NULL COMMENT 'package display text',
  `emp_doc_path` varchar(255) DEFAULT NULL COMMENT 'document proof if uploaded',
  `placed_through_spm` tinyint(1) NOT NULL COMMENT '1 if placed through college',
  `placement_company` varchar(150) DEFAULT NULL COMMENT 'company placed through college',
  `placement_role` varchar(100) DEFAULT NULL COMMENT 'role during placement',
  `placement_salary` varchar(50) DEFAULT NULL COMMENT 'package offered at placement',
  `past_experience` text DEFAULT NULL COMMENT 'earlier roles/ details',
  `past_exp_doc_path` varchar(255) DEFAULT NULL COMMENT 'proof docs for past roles'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `alumni_extras`
--

CREATE TABLE `alumni_extras` (
  `extra_id` int(11) NOT NULL COMMENT 'set as Primary key',
  `alumni_id` int(11) NOT NULL COMMENT 'foreign key referencing alumni_basic.id',
  `competitive_exam` varchar(150) NOT NULL COMMENT 'Example: UPSC, GATE',
  `exam_doc_path` varchar(255) NOT NULL COMMENT 'uploaded certificate path',
  `achievements` text NOT NULL COMMENT 'awards or achievements',
  `achievement_doc_path` varchar(255) NOT NULL COMMENT 'uploaded award proof',
  `career_help_text` text NOT NULL COMMENT 'how college helped career',
  `message_to_students` text NOT NULL COMMENT 'advice to juniors',
  `willing_to_mentor` tinyint(1) NOT NULL COMMENT '1= willing to mentor'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alumni_basic`
--
ALTER TABLE `alumni_basic`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `alumni_education`
--
ALTER TABLE `alumni_education`
  ADD PRIMARY KEY (`edu_id`);

--
-- Indexes for table `alumni_employment`
--
ALTER TABLE `alumni_employment`
  ADD PRIMARY KEY (`emp_id`);

--
-- Indexes for table `alumni_extras`
--
ALTER TABLE `alumni_extras`
  ADD PRIMARY KEY (`extra_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alumni_basic`
--
ALTER TABLE `alumni_basic`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'set as Primary Key';

--
-- AUTO_INCREMENT for table `alumni_education`
--
ALTER TABLE `alumni_education`
  MODIFY `edu_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Set as Primary Key';

--
-- AUTO_INCREMENT for table `alumni_employment`
--
ALTER TABLE `alumni_employment`
  MODIFY `emp_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'set as primary key';

--
-- AUTO_INCREMENT for table `alumni_extras`
--
ALTER TABLE `alumni_extras`
  MODIFY `extra_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'set as Primary key';
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
