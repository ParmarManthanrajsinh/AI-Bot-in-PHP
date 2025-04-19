-- Analytics Database Setup for AI Bot

-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` varchar(64) NOT NULL,
  `first_seen` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `user_agent` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `session_count` int DEFAULT 0,
  PRIMARY KEY (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Sessions Table
CREATE TABLE IF NOT EXISTS `sessions` (
  `session_id` varchar(64) NOT NULL,
  `user_id` varchar(64) NOT NULL,
  `start_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `end_time` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `device_type` varchar(50) DEFAULT NULL,
  `browser` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`session_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Interactions Table (extends existing chat_messages)
-- Check if columns exist before adding them
SELECT COUNT(*) INTO @session_id_exists FROM information_schema.columns 
WHERE table_schema = DATABASE() AND table_name = 'chat_messages' AND column_name = 'session_id';

SELECT COUNT(*) INTO @user_id_exists FROM information_schema.columns 
WHERE table_schema = DATABASE() AND table_name = 'chat_messages' AND column_name = 'user_id';

SELECT COUNT(*) INTO @response_time_exists FROM information_schema.columns 
WHERE table_schema = DATABASE() AND table_name = 'chat_messages' AND column_name = 'response_time';

SELECT COUNT(*) INTO @token_count_exists FROM information_schema.columns 
WHERE table_schema = DATABASE() AND table_name = 'chat_messages' AND column_name = 'token_count';

SELECT COUNT(*) INTO @ip_address_exists FROM information_schema.columns 
WHERE table_schema = DATABASE() AND table_name = 'chat_messages' AND column_name = 'ip_address';

-- Add columns if they don't exist
SET @add_session_id = IF(@session_id_exists = 0, 'ALTER TABLE `chat_messages` ADD COLUMN `session_id` varchar(64) DEFAULT NULL AFTER `id`', 'SELECT 1');
PREPARE stmt FROM @add_session_id;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_user_id = IF(@user_id_exists = 0, 'ALTER TABLE `chat_messages` ADD COLUMN `user_id` varchar(64) DEFAULT NULL AFTER `session_id`', 'SELECT 1');
PREPARE stmt FROM @add_user_id;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_response_time = IF(@response_time_exists = 0, 'ALTER TABLE `chat_messages` ADD COLUMN `response_time` float DEFAULT NULL AFTER `content`', 'SELECT 1');
PREPARE stmt FROM @add_response_time;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_token_count = IF(@token_count_exists = 0, 'ALTER TABLE `chat_messages` ADD COLUMN `token_count` int DEFAULT NULL AFTER `response_time`', 'SELECT 1');
PREPARE stmt FROM @add_token_count;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_ip_address = IF(@ip_address_exists = 0, 'ALTER TABLE `chat_messages` ADD COLUMN `ip_address` varchar(45) DEFAULT NULL AFTER `token_count`', 'SELECT 1');
PREPARE stmt FROM @add_ip_address;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add indexes if they don't exist
SELECT COUNT(*) INTO @session_idx_exists FROM information_schema.statistics 
WHERE table_schema = DATABASE() AND table_name = 'chat_messages' AND index_name = 'session_idx';

SELECT COUNT(*) INTO @user_idx_exists FROM information_schema.statistics 
WHERE table_schema = DATABASE() AND table_name = 'chat_messages' AND index_name = 'user_idx';

SET @add_session_idx = IF(@session_idx_exists = 0, 'ALTER TABLE `chat_messages` ADD INDEX `session_idx` (`session_id`)', 'SELECT 1');
PREPARE stmt FROM @add_session_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_user_idx = IF(@user_idx_exists = 0, 'ALTER TABLE `chat_messages` ADD INDEX `user_idx` (`user_id`)', 'SELECT 1');
PREPARE stmt FROM @add_user_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Metrics Table for Aggregated Data
CREATE TABLE IF NOT EXISTS `metrics_daily` (
  `date` date NOT NULL,
  `total_users` int DEFAULT 0,
  `new_users` int DEFAULT 0,
  `total_sessions` int DEFAULT 0,
  `total_interactions` int DEFAULT 0,
  `avg_response_time` float DEFAULT 0,
  `error_count` int DEFAULT 0,
  PRIMARY KEY (`date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Error Tracking Table
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

-- Topic Categories Table
CREATE TABLE IF NOT EXISTS `topic_categories` (
  `category_id` int NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `description` text,
  PRIMARY KEY (`category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Message Categories Junction Table
CREATE TABLE IF NOT EXISTS `message_categories` (
  `message_id` int NOT NULL,
  `category_id` int NOT NULL,
  `confidence` float DEFAULT 0,
  PRIMARY KEY (`message_id`, `category_id`),
  KEY `category_id` (`category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Admin Users Table
CREATE TABLE IF NOT EXISTS `admin_users` (
  `admin_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;