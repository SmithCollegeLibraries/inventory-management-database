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
  `flag` boolean NOT NULL DEFAULT FALSE,
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
  `position` tinyint(2) UNSIGNED DEFAULT NULL,
  `active` boolean NOT NULL DEFAULT TRUE,
  `flag` boolean NOT NULL DEFAULT FALSE,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
  `status` varchar(25) NOT NULL,
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
-- Table structure for table `picklist`
--

CREATE TABLE IF NOT EXISTS `picklist` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id` int(11) UNSIGNED NOT NULL,
  `title` varchar(255),
  `volume` varchar(31),
  `user_id` int(11) UNSIGNED,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (item_id) REFERENCES item(id),
  FOREIGN KEY (user_id) REFERENCES user(id),
  UNIQUE (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `folio_validation`
--

CREATE TABLE IF NOT EXISTS `folio_validation` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `barcode` varchar(20) NOT NULL,
  `item_in_folio` boolean DEFAULT NULL,
  PRIMARY KEY (id),
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
  `default_collection` int(11) UNSIGNED,
  `passwordhash` varchar(255) NOT NULL,
  `access_token` varchar(255),
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (default_collection) REFERENCES collection(id),
  UNIQUE (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `old_barcode_tray`
--

CREATE TABLE IF NOT EXISTS `old_barcode_tray` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `boxbarcode` varchar(255) NOT NULL,
  `barcode` varchar(20) NOT NULL,
  `stream` varchar(255) NOT NULL,
  `initials` varchar(10) NOT NULL,
  `status` varchar(25) NOT NULL,
  `added` varchar(100) NOT NULL,
  `timestamp` varchar(30) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE (barcode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `old_tray_shelf`
--

CREATE TABLE IF NOT EXISTS `old_tray_shelf` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `boxbarcode` varchar(255) NOT NULL,
  `shelf` varchar(255) NOT NULL,
  `row` varchar(2),
  `side` varchar(1),
  `ladder` varchar(2),
  `shelf_number` varchar(2),
  `shelf_depth` varchar(5),
  `shelf_position` varchar(3),
  `initials` varchar(10),
  `added` varchar(100) NOT NULL,
  `timestamp` varchar(30) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE (boxbarcode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `collection_log`
--

CREATE TABLE IF NOT EXISTS `collection_log` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `collection_id` int(11) UNSIGNED NOT NULL,
  `action` varchar(63) NOT NULL,
  `details` tinytext,
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


-- Create indexes

CREATE INDEX idx_item_barcode ON item (barcode);
CREATE INDEX idx_item_status ON item (status);
CREATE INDEX idx_item_tray ON item (tray_id);
CREATE INDEX idx_item_collection ON item (collection_id);
CREATE INDEX idx_item_flag ON item (flag);
CREATE INDEX idx_item_active ON item (active);
CREATE INDEX idx_item_created ON item (created);
CREATE INDEX idx_item_updated ON item (updated);

CREATE INDEX idx_tray_barcode ON tray (barcode);
CREATE INDEX idx_tray_shelf ON tray (shelf_id);
CREATE INDEX idx_tray_depth ON tray (depth);
CREATE INDEX idx_tray_position ON tray (position);
CREATE INDEX idx_tray_flag ON tray (flag);
CREATE INDEX idx_tray_active ON tray (active);
CREATE INDEX idx_tray_created ON tray (created);
CREATE INDEX idx_tray_updated ON tray (updated);

CREATE INDEX idx_shelf_barcode ON shelf (barcode);
CREATE INDEX idx_shelf_row ON shelf (row);

-- Create indexes for old tables

CREATE INDEX idx_old_item_barcode ON old_barcode_tray (barcode);
CREATE INDEX idx_old_tray_barcode ON old_tray_shelf (boxbarcode);

-- Create indexes for logs

CREATE INDEX idx_item_log_item ON item_log (item_id);
CREATE INDEX idx_item_log_user ON item_log (user_id);
CREATE INDEX idx_item_log_action ON item_log (action);
CREATE INDEX idx_item_log_timestamp ON item_log (timestamp);

CREATE INDEX idx_tray_log_tray ON tray_log (tray_id);
CREATE INDEX idx_tray_log_user ON tray_log (user_id);
CREATE INDEX idx_tray_log_action ON tray_log (action);
CREATE INDEX idx_tray_log_timestamp ON tray_log (timestamp);

CREATE INDEX idx_shelf_log_shelf ON shelf_log (shelf_id);
CREATE INDEX idx_shelf_log_user ON shelf_log (user_id);
CREATE INDEX idx_shelf_log_action ON shelf_log (action);
CREATE INDEX idx_shelf_log_timestamp ON shelf_log (timestamp);
