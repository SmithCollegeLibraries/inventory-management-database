-- phpMyAdmin SQL Dump
-- version 4.4.15.10
-- https://www.phpmyadmin.net
--
-- Host: liblive
-- Generation Time: Nov 28, 2018 at 09:30 AM
-- Server version: 5.5.60-MariaDB
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
-- Table structure for table `collection`
--

CREATE TABLE IF NOT EXISTS `collection` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `active` boolean NOT NULL DEFAULT TRUE,
  PRIMARY KEY (id),
  UNIQUE (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `shelf`
--

CREATE TABLE IF NOT EXISTS `shelf` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `barcode` varchar(20) NOT NULL,
  `row` char(2) DEFAULT NULL,
  `side` char(1) DEFAULT NULL,
  `ladder` char(2) DEFAULT NULL,
  `rung` char(2) DEFAULT NULL,
  `active` boolean NOT NULL DEFAULT TRUE,
  PRIMARY KEY (id),
  UNIQUE (barcode),
  CONSTRAINT shelf_label UNIQUE (row, side, ladder, rung)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `tray`
--

CREATE TABLE IF NOT EXISTS `tray` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `barcode` varchar(20) NOT NULL,
  `shelf_id` int(11) UNSIGNED,
  `depth` varchar(6) DEFAULT NULL,
  `position` varchar(3) DEFAULT NULL,
  `active` boolean NOT NULL DEFAULT TRUE,
  PRIMARY KEY (id),
  FOREIGN KEY (shelf_id) REFERENCES shelf(id),
  UNIQUE (barcode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `item`
--

CREATE TABLE IF NOT EXISTS `item` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `barcode` varchar(20) NOT NULL,
  `status` varchar(25) NOT NULL DEFAULT 'Trayed',
  `tray_id` int(11) UNSIGNED,
  `collection_id` int(11) UNSIGNED NOT NULL,
  `active` boolean NOT NULL DEFAULT TRUE,
  `flag` boolean NOT NULL DEFAULT FALSE,
  PRIMARY KEY (id),
  FOREIGN KEY (collection_id) REFERENCES collection(id),
  FOREIGN KEY (tray_id) REFERENCES tray(id),
  UNIQUE (barcode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE IF NOT EXISTS `user` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `name` varchar(31) NOT NULL,
  `level` int(3) NOT NULL DEFAULT 0,
  `passwordhash` varchar(255) NOT NULL,
  `access_token` varchar(255),
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `active` boolean NOT NULL DEFAULT TRUE,
  PRIMARY KEY (id),
  UNIQUE (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `collection_log`
--

CREATE TABLE IF NOT EXISTS `collection_log` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `collection_id` int(11) UNSIGNED NOT NULL,
  `action` varchar(63) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int(11) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (collection_id) REFERENCES collection(id),
  FOREIGN KEY (user_id) REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `item_log`
--

CREATE TABLE IF NOT EXISTS `item_log` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id` int(11) UNSIGNED NOT NULL,
  `action` varchar(63) NOT NULL,
  `details` tinytext,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int(11) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (item_id) REFERENCES item(id),
  FOREIGN KEY (user_id) REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `tray_log`
--

CREATE TABLE IF NOT EXISTS `tray_log` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tray_id` int(11) UNSIGNED NOT NULL,
  `action` varchar(63) NOT NULL,
  `details` tinytext,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int(11) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (tray_id) REFERENCES tray(id),
  FOREIGN KEY (user_id) REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `shelf_log`
--

CREATE TABLE IF NOT EXISTS `shelf_log` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `shelf_id` int(11) UNSIGNED NOT NULL,
  `action` varchar(63) NOT NULL,
  `details` tinytext,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int(11) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (shelf_id) REFERENCES shelf(id),
  FOREIGN KEY (user_id) REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `setting`
--

CREATE TABLE IF NOT EXISTS `setting` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(31) NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `setting_log`
--

CREATE TABLE IF NOT EXISTS `setting_log` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_id` int(11) UNSIGNED NOT NULL,
  `value` varchar(255) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int(11) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (setting_id) REFERENCES setting(id),
  FOREIGN KEY (user_id) REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;