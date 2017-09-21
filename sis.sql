-- phpMyAdmin SQL Dump
-- version 4.4.15.10
-- https://www.phpmyadmin.net
--
-- Host: liblive
-- Generation Time: Sep 21, 2017 at 01:44 PM
-- Server version: 5.5.52-MariaDB
-- PHP Version: 5.4.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sis`
--

-- --------------------------------------------------------

--
-- Table structure for table `barcode_tray`
--

CREATE TABLE IF NOT EXISTS `barcode_tray` (
  `id` int(11) NOT NULL,
  `boxbarcode` varchar(255) NOT NULL,
  `barcode` varchar(20) NOT NULL,
  `stream` varchar(25) NOT NULL,
  `initials` varchar(10) NOT NULL,
  `status` varchar(25) NOT NULL DEFAULT 'Available',
  `added` varchar(100) NOT NULL,
  `timestamp` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `collections`
--

CREATE TABLE IF NOT EXISTS `collections` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `internal_requests`
--

CREATE TABLE IF NOT EXISTS `internal_requests` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `material` varchar(100) DEFAULT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `call_number` varchar(100) DEFAULT NULL,
  `volume_year` varchar(25) DEFAULT NULL,
  `full_run` varchar(10) DEFAULT 'false',
  `collection` varchar(100) DEFAULT NULL,
  `completed` varchar(10) DEFAULT 'false'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `tray_shelf`
--

CREATE TABLE IF NOT EXISTS `tray_shelf` (
  `id` int(11) NOT NULL,
  `boxbarcode` varchar(255) NOT NULL,
  `shelf` varchar(255) NOT NULL,
  `row` varchar(2) DEFAULT NULL,
  `side` varchar(1) DEFAULT NULL,
  `ladder` varchar(2) DEFAULT NULL,
  `shelf_number` varchar(2) DEFAULT NULL,
  `shelf_depth` varchar(5) DEFAULT NULL,
  `shelf_position` varchar(3) DEFAULT NULL,
  `initials` varchar(10) DEFAULT NULL,
  `added` varchar(100) NOT NULL,
  `timestamp` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `barcode_tray`
--
ALTER TABLE `barcode_tray`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `boxbarcode` (`boxbarcode`),
  ADD KEY `barcode_2` (`barcode`),
  ADD KEY `barcode_3` (`barcode`);

--
-- Indexes for table `collections`
--
ALTER TABLE `collections`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `internal_requests`
--
ALTER TABLE `internal_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tray_shelf`
--
ALTER TABLE `tray_shelf`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `boxbarcode_2` (`boxbarcode`),
  ADD KEY `boxbarcode` (`boxbarcode`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `barcode_tray`
--
ALTER TABLE `barcode_tray`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `collections`
--
ALTER TABLE `collections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `internal_requests`
--
ALTER TABLE `internal_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `tray_shelf`
--
ALTER TABLE `tray_shelf`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
