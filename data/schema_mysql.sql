-- ==============================================================================
-- INNOWAVE-2K26 — Official Production MySQL Database Import Schema
-- Target Database: `innowave_db`
-- Compatible with: MySQL 5.7+, MySQL 8.0+, MariaDB, phpMyAdmin, cPanel
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `innowave_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `innowave_db`;

-- Drop existing table if recreating (uncomment if clean reset needed)
-- DROP TABLE IF EXISTS `registrations`;

CREATE TABLE IF NOT EXISTS `registrations` (
  `id`                       INT AUTO_INCREMENT PRIMARY KEY,
  `team_id`                  VARCHAR(100) UNIQUE NOT NULL,
  `reg_seq`                  INT NOT NULL DEFAULT 1,
  `project_title`            VARCHAR(255) DEFAULT 'INNOWAVE-2K26 Registration',
  `track`                    VARCHAR(255) DEFAULT 'Open Innovation',
  `events_selected`          TEXT,
  `description`              TEXT,
  `leader_name`              VARCHAR(255) NOT NULL,
  `leader_email`             VARCHAR(255) NOT NULL,
  `leader_phone`             VARCHAR(100) NOT NULL,
  `college_name`             VARCHAR(255),
  `roll_no`                  VARCHAR(100),
  `branch`                   VARCHAR(100),
  `year`                     VARCHAR(50),
  `ieee_member`              VARCHAR(10) NOT NULL DEFAULT 'No',
  `ieee_id`                  VARCHAR(100) DEFAULT NULL,
  `ieee_card`                LONGTEXT DEFAULT NULL,
  `ieee_verification_status`  VARCHAR(100) DEFAULT 'N/A',
  `ieee_email`               VARCHAR(255) DEFAULT NULL,
  `ieee_grade`               VARCHAR(100) DEFAULT NULL,
  `ieee_count`               INT DEFAULT 0,
  `non_ieee_count`           INT DEFAULT 0,
  `team_size`                INT DEFAULT 1,
  `member2`                  VARCHAR(255) DEFAULT NULL,
  `member3`                  VARCHAR(255) DEFAULT NULL,
  `member4`                  VARCHAR(255) DEFAULT NULL,
  `amount`                   INT DEFAULT 100,
  `fee_label`                VARCHAR(255) DEFAULT NULL,
  `payment_mode`             VARCHAR(100) DEFAULT 'UPI',
  `payment_status`           VARCHAR(100) DEFAULT 'Pending Payment Confirmation',
  `payment_ref`              VARCHAR(255) DEFAULT NULL,
  `payment_screenshot`        LONGTEXT DEFAULT NULL,
  `payment_proof`            LONGTEXT DEFAULT NULL,
  `duplicate_utr`            INT DEFAULT 0,
  `utr_mismatch`             INT DEFAULT 0,
  `utr_warning`              VARCHAR(255) DEFAULT NULL,
  `ieee_ocr_mismatch`        INT DEFAULT 0,
  `ieee_warning`             VARCHAR(255) DEFAULT NULL,
  `paid_at`                  VARCHAR(100) DEFAULT NULL,
  `created_at`               VARCHAR(100) NOT NULL,
  INDEX `idx_team_id` (`team_id`),
  INDEX `idx_leader_email` (`leader_email`),
  INDEX `idx_leader_phone` (`leader_phone`),
  INDEX `idx_ieee_id` (`ieee_id`),
  INDEX `idx_payment_ref` (`payment_ref`),
  INDEX `idx_payment_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1001;
