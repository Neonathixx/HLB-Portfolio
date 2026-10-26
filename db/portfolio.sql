-- ============================================================================
--  HLB PORTFOLIO — ACCOUNT SYSTEM SCHEMA
--  MySQL 5.7+ / MariaDB 10.3+  (InnoDB, utf8mb4)
--
--  Import with:   mysql -u root -p < schema.sql
--  or paste into phpMyAdmin → SQL tab.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `hlb_portfolio`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `hlb_portfolio`;

-- ---------------------------------------------------------------------------
--  users
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `display_name`  VARCHAR(60)  NOT NULL,
    `username`      VARCHAR(32)  NOT NULL,
    `email`         VARCHAR(190) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role`          ENUM('member','admin') NOT NULL DEFAULT 'member',
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,
    `last_login_at` DATETIME     NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`),
    UNIQUE KEY `uq_users_email`    (`email`),
    KEY `idx_users_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
--  login_attempts  — brute-force throttling (per email + IP, time windowed)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`        VARCHAR(190) NOT NULL,
    `ip_address`   VARBINARY(16) NOT NULL,
    `successful`   TINYINT(1)   NOT NULL DEFAULT 0,
    `attempted_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_attempts_email_time` (`email`, `attempted_at`),
    KEY `idx_attempts_ip_time`    (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
--  Optional: promote your own account to admin after registering
-- ---------------------------------------------------------------------------
-- UPDATE `users` SET `role` = 'admin' WHERE `email` = 'hiambartolome@gmail.com';