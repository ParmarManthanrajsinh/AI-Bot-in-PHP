-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Apr 19, 2025 at 08:13 AM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ai-chat`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE IF NOT EXISTS `admin_users` (
  `admin_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`admin_id`, `username`, `password_hash`, `email`, `last_login`) VALUES
(1, 'admin', '$2y$10$MkrzqbmmdnK7eZMzqJC0rOv.s1CZ285.EgpmLl3.DSYHFp7a2kFi6', 'admin@gmail.com', '2025-04-19 08:13:23');

-- --------------------------------------------------------

--
-- Table structure for table `api_performance`
--

DROP TABLE IF EXISTS `api_performance`;
CREATE TABLE IF NOT EXISTS `api_performance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `model` varchar(50) NOT NULL,
  `response_time` float NOT NULL,
  `http_code` int NOT NULL,
  `is_success` tinyint(1) NOT NULL,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `model_idx` (`model`),
  KEY `timestamp_idx` (`timestamp`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `api_performance`
--

INSERT INTO `api_performance` (`id`, `model`, `response_time`, `http_code`, `is_success`, `timestamp`) VALUES
(1, 'gemini-2.0-flash', 1.45266, 200, 1, '2025-04-07 17:40:07'),
(2, 'gemini-2.0-flash', 1.38637, 200, 1, '2025-04-07 17:40:26'),
(3, 'gemini-2.0-flash', 19.7892, 200, 1, '2025-04-08 04:01:47'),
(4, 'gemini-2.0-flash', 2.63666, 200, 1, '2025-04-08 12:43:29'),
(5, 'gemini-2.0-flash', 2.4675, 200, 1, '2025-04-08 13:02:25');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

DROP TABLE IF EXISTS `chat_messages`;
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `session_id` varchar(64) DEFAULT NULL,
  `user_id` varchar(64) DEFAULT NULL,
  `role` enum('user','assistant') NOT NULL,
  `content` text NOT NULL,
  `response_time` float DEFAULT NULL,
  `token_count` int DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `session_idx` (`session_id`),
  KEY `user_idx` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `error_logs`
--

DROP TABLE IF EXISTS `error_logs`;
CREATE TABLE IF NOT EXISTS `error_logs` (
  `error_id` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) DEFAULT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `error_type` varchar(50) DEFAULT NULL,
  `error_message` text,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`error_id`),
  KEY `user_id` (`user_id`),
  KEY `session_id` (`session_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message_categories`
--

DROP TABLE IF EXISTS `message_categories`;
CREATE TABLE IF NOT EXISTS `message_categories` (
  `message_id` int NOT NULL,
  `category_id` int NOT NULL,
  `confidence` float DEFAULT '0',
  PRIMARY KEY (`message_id`,`category_id`),
  KEY `category_id` (`category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `metrics_daily`
--

DROP TABLE IF EXISTS `metrics_daily`;
CREATE TABLE IF NOT EXISTS `metrics_daily` (
  `date` date NOT NULL,
  `total_users` int DEFAULT '0',
  `new_users` int DEFAULT '0',
  `total_sessions` int DEFAULT '0',
  `total_interactions` int DEFAULT '0',
  `avg_response_time` float DEFAULT '0',
  `error_count` int DEFAULT '0',
  PRIMARY KEY (`date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `metrics_daily`
--

INSERT INTO `metrics_daily` (`date`, `total_users`, `new_users`, `total_sessions`, `total_interactions`, `avg_response_time`, `error_count`) VALUES
('2025-04-07', 2, 2, 2, 18, 0, 0),
('2025-04-08', 3, 3, 3, 2, 0, 0),
('2025-04-19', 1, 1, 1, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE IF NOT EXISTS `sessions` (
  `session_id` varchar(64) NOT NULL,
  `user_id` varchar(64) NOT NULL,
  `start_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `end_time` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `device_type` varchar(50) DEFAULT NULL,
  `browser` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`session_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`session_id`, `user_id`, `start_time`, `end_time`, `is_active`, `device_type`, `browser`) VALUES
('a25fb22670eaf2c4601c2713b1575ceb', 'ae51ee4a6b8f4b3b0bde527e8b3a0e97', '2025-04-07 17:39:57', NULL, 1, 'desktop', 'Chrome'),
('f55da864c0b6f19605712dc9bbe4e97f', '95989e4404f29e61075704c1f8a8755a', '2025-04-07 18:00:51', NULL, 1, 'desktop', 'Chrome'),
('8f6c957f8cfce6290d4d91ecd27f3616', '870f96d4d777eff1add771cd24d4d7a5', '2025-04-08 03:59:25', NULL, 1, 'desktop', 'Chrome'),
('8254291428cd5ca3a67631f73d7acab7', '4a65cf89d9507aa7e494556ebd9e84ed', '2025-04-08 12:16:47', NULL, 1, 'desktop', 'Chrome'),
('35a8f66a5d3dcfd4326843f604064b2c', '77a52e5c77b12472c341e1cc5e83cea0', '2025-04-08 12:50:28', NULL, 1, 'desktop', 'Chrome'),
('12b81e8405b0a8775e2a70145bdcc1b0', 'cf5afc3ef5e5c3cb3bb32d32e5d8af4a', '2025-04-19 08:12:16', NULL, 1, 'desktop', 'Chrome');

-- --------------------------------------------------------

--
-- Table structure for table `topic_categories`
--

DROP TABLE IF EXISTS `topic_categories`;
CREATE TABLE IF NOT EXISTS `topic_categories` (
  `category_id` int NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `description` text,
  PRIMARY KEY (`category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` varchar(64) NOT NULL,
  `first_seen` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `user_agent` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `session_count` int DEFAULT '0',
  PRIMARY KEY (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_seen`, `last_seen`, `user_agent`, `ip_address`, `session_count`) VALUES
('ae51ee4a6b8f4b3b0bde527e8b3a0e97', '2025-04-07 17:39:57', '2025-04-07 17:41:40', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '::1', 1),
('95989e4404f29e61075704c1f8a8755a', '2025-04-07 18:00:51', '2025-04-07 18:01:36', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '::1', 1),
('870f96d4d777eff1add771cd24d4d7a5', '2025-04-08 03:59:25', '2025-04-08 04:39:56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '::1', 1),
('4a65cf89d9507aa7e494556ebd9e84ed', '2025-04-08 12:16:47', '2025-04-08 12:43:32', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '::1', 1),
('77a52e5c77b12472c341e1cc5e83cea0', '2025-04-08 12:50:28', '2025-04-08 13:02:22', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '::1', 1),
('cf5afc3ef5e5c3cb3bb32d32e5d8af4a', '2025-04-19 08:12:16', '2025-04-19 08:13:01', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '::1', 1);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
