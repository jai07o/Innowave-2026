-- =======================================================
-- INNOWAVE-2K26 — Official MySQL Database Import Schema
-- Compatible with phpMyAdmin, MySQL Workbench, MariaDB
-- =======================================================

CREATE DATABASE IF NOT EXISTS `innowave_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `innowave_db`;

CREATE TABLE IF NOT EXISTS `registrations` (
  `id`                       INT AUTO_INCREMENT PRIMARY KEY,
  `team_id`                  VARCHAR(100) UNIQUE,
  `reg_seq`                  INT,
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
  `ieee_member`              VARCHAR(10) NOT NULL,
  `ieee_id`                  VARCHAR(100),
  `ieee_card`                LONGTEXT,
  `ieee_verification_status`  VARCHAR(100),
  `ieee_email`               VARCHAR(255),
  `ieee_grade`               VARCHAR(100),
  `ieee_count`               INT DEFAULT 0,
  `non_ieee_count`           INT DEFAULT 0,
  `team_size`                INT DEFAULT 1,
  `member2`                  VARCHAR(255),
  `member3`                  VARCHAR(255),
  `member4`                  VARCHAR(255),
  `amount`                   INT DEFAULT 100,
  `fee_label`                VARCHAR(255),
  `payment_mode`             VARCHAR(100) DEFAULT 'Bank Transfer',
  `payment_status`           VARCHAR(100) DEFAULT 'Pending Payment Confirmation',
  `payment_ref`              VARCHAR(255),
  `payment_proof`            LONGTEXT,
  `paid_at`                  VARCHAR(100),
  `created_at`               VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
