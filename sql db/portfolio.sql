-- ============================================================================
--  HLB PORTFOLIO — ACCOUNT + NEWSLETTER SCHEMA
--  MySQL 5.7+ / MariaDB 10.3+  (InnoDB, utf8mb4)
--
--  Import with:   mysql -u root -p < portfolio.sql
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
--  newsletter_subscribers  — the subscribe box on page4.html (The Aisle)
--
--  A subscriber is NOT the same thing as a user account: people can get the
--  newsletter without registering, and a registered member may not want it.
--  `user_id` links the two when the email happens to match an account.
--
--  `confirm_token` supports double opt-in (send the subscriber a link to
--  newsletter.php?confirm=<token>). Until they click it, `confirmed` stays 0
--  and they should not be mailed. `unsubscribed_at` keeps the row instead of
--  deleting it, so a later re-subscribe can't silently overwrite the history.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`           VARCHAR(190) NOT NULL,
    `display_name`    VARCHAR(60)  NULL,
    `user_id`         INT UNSIGNED NULL,
    `confirmed`       TINYINT(1)   NOT NULL DEFAULT 0,
    `confirm_token`   CHAR(64)     NULL,
    `source`          VARCHAR(40)  NOT NULL DEFAULT 'page4',
    `ip_address`      VARBINARY(16) NULL,
    `subscribed_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `confirmed_at`    DATETIME     NULL,
    `unsubscribed_at` DATETIME     NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_subs_email` (`email`),
    UNIQUE KEY `uq_subs_token` (`confirm_token`),
    KEY `idx_subs_confirmed` (`confirmed`, `unsubscribed_at`),
    CONSTRAINT `fk_subs_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
--  Handy queries
-- ---------------------------------------------------------------------------
-- Everyone who should receive the next issue:
--   SELECT email, display_name FROM newsletter_subscribers
--   WHERE confirmed = 1 AND unsubscribed_at IS NULL;

-- Promote your own account to admin after registering:
--   UPDATE `users` SET `role` = 'admin' WHERE `email` = 'hiambartolome@gmail.com';

-- Clear throttle rows older than a day (safe to run on a cron):
--   DELETE FROM `login_attempts` WHERE `attempted_at` < NOW() - INTERVAL 1 DAY;
