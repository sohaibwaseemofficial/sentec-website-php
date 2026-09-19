-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 03, 2025 at 05:24 PM
-- Server version: 10.6.17-MariaDB-cll-lve
-- PHP Version: 8.3.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mshayan_sentec`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password`) VALUES
(1, 'abc', '1234')

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(100) NOT NULL,
  `event_date` date NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('upcoming','ongoing','completed','past') DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gallery`
--

CREATE TABLE `gallery` (
  `id` int(11) NOT NULL,
  `group_title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `main_image_url` varchar(255) NOT NULL,
  `additional_image_url` varchar(255) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `partners`
--

CREATE TABLE `partners` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `section` enum('current','past') NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team_members`
--

CREATE TABLE `team_members` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `category` enum('Presiding Board','Executive Committee','Directorate','Member','Alumni') NOT NULL,
  `domain` varchar(255) DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `linkedin` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;



--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gallery`
--
ALTER TABLE `gallery`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `partners`
--
ALTER TABLE `partners`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `team_members`
--
ALTER TABLE `team_members`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `gallery`
--
ALTER TABLE `gallery`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `partners`
--
ALTER TABLE `partners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `team_members`
--
ALTER TABLE `team_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
-- SQL table for event registrations
-- Run this SQL to create the event_registrations table

CREATE TABLE `event_registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `institution_type` enum('NED University Student','Non-NED University Student','College Student') NOT NULL,
  `team_name` varchar(255) NOT NULL,
  `module_selection` varchar(255) NOT NULL,
  `fees_screenshot` varchar(255) NOT NULL,
  
  -- First Participant (Mandatory)
  `participant1_name` varchar(255) NOT NULL,
  `participant1_contact` varchar(20) NOT NULL,
  `participant1_email` varchar(255) NOT NULL,
  `participant1_cnic` varchar(20) NOT NULL,
  `participant1_roll_number` varchar(100) NOT NULL,
  `participant1_face_image` varchar(255) NOT NULL,
  `participant1_id_card` varchar(255) NOT NULL,
  
  -- Second Participant (Optional)
  `participant2_name` varchar(255) DEFAULT NULL,
  `participant2_contact` varchar(20) DEFAULT NULL,
  `participant2_email` varchar(255) DEFAULT NULL,
  `participant2_cnic` varchar(20) DEFAULT NULL,
  `participant2_roll_number` varchar(100) DEFAULT NULL,
  `participant2_face_image` varchar(255) DEFAULT NULL,
  `participant2_id_card` varchar(255) DEFAULT NULL,
  
  -- Third Participant (Optional)
  `participant3_name` varchar(255) DEFAULT NULL,
  `participant3_contact` varchar(20) DEFAULT NULL,
  `participant3_email` varchar(255) DEFAULT NULL,
  `participant3_cnic` varchar(20) DEFAULT NULL,
  `participant3_roll_number` varchar(100) DEFAULT NULL,
  `participant3_face_image` varchar(255) DEFAULT NULL,
  `participant3_id_card` varchar(255) DEFAULT NULL,
  
  -- Fourth Participant (Optional)
  `participant4_name` varchar(255) DEFAULT NULL,
  `participant4_contact` varchar(20) DEFAULT NULL,
  `participant4_email` varchar(255) DEFAULT NULL,
  `participant4_cnic` varchar(20) DEFAULT NULL,
  `participant4_roll_number` varchar(100) DEFAULT NULL,
  `participant4_face_image` varchar(255) DEFAULT NULL,
  `participant4_id_card` varchar(255) DEFAULT NULL,
  
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
