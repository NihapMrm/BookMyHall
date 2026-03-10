-- ============================================================
--  BookMyHall – Database Schema
--  Database : bookmyhall
--  Encoding : utf8mb4 / utf8mb4_unicode_ci
--  Run this file once to create all tables.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `bookmyhall`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `bookmyhall`;

-- ─── 1. users ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `user_id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `full_name`       VARCHAR(100)    NOT NULL,
    `email`           VARCHAR(150)    NOT NULL,
    `password`        VARCHAR(255)    NOT NULL,
    `phone`           VARCHAR(20)     DEFAULT NULL,
    `address`         TEXT            DEFAULT NULL,
    `role`            ENUM('admin','customer') NOT NULL DEFAULT 'customer',
    `status`          ENUM('active','blocked')  NOT NULL DEFAULT 'active',
    `profile_picture` VARCHAR(255)    DEFAULT NULL,
    `last_login`      DATETIME        DEFAULT NULL,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 2. hall ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `hall` (
    `hall_id`     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(150)  NOT NULL,
    `description` TEXT          DEFAULT NULL,
    `capacity`    INT UNSIGNED  NOT NULL DEFAULT 0,
    `location`    VARCHAR(255)  DEFAULT NULL,
    `size_sqft`   INT UNSIGNED  DEFAULT NULL,
    `base_price`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `features`    TEXT          DEFAULT NULL   COMMENT 'JSON array of feature flags',
    `status`      ENUM('available','unavailable','maintenance') NOT NULL DEFAULT 'available',
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`hall_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 3. hall_images ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `hall_images` (
    `image_id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `hall_id`     INT UNSIGNED NOT NULL,
    `filename`    VARCHAR(255) NOT NULL,
    `sort_order`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`image_id`),
    FOREIGN KEY (`hall_id`) REFERENCES `hall`(`hall_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 4. packages ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `packages` (
    `package_id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `hall_id`             INT UNSIGNED    NOT NULL,
    `parent_package_id`   INT UNSIGNED    DEFAULT NULL   COMMENT 'NULL = main package',
    `name`                VARCHAR(150)    NOT NULL,
    `type`                ENUM('main','sub') NOT NULL DEFAULT 'main',
    `price`               DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `seat_capacity`       INT UNSIGNED    DEFAULT NULL,
    `parking_capacity`    INT UNSIGNED    DEFAULT NULL,
    `description`         TEXT            DEFAULT NULL,
    `inclusions`          TEXT            DEFAULT NULL,
    `services`            TEXT            DEFAULT NULL   COMMENT 'JSON array: catering, ac, decoration, wifi, parking',
    `is_active`           TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME        DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`package_id`),
    FOREIGN KEY (`hall_id`)           REFERENCES `hall`(`hall_id`)         ON DELETE CASCADE,
    FOREIGN KEY (`parent_package_id`) REFERENCES `packages`(`package_id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 5. bookings ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `bookings` (
    `booking_id`       INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `customer_id`      INT UNSIGNED    NOT NULL,
    `hall_id`          INT UNSIGNED    NOT NULL,
    `sub_package_id`   INT UNSIGNED    NOT NULL,
    `event_date`       DATE            NOT NULL,
    `start_time`       TIME            NOT NULL,
    `end_time`         TIME            NOT NULL,
    `event_type`       VARCHAR(100)    DEFAULT NULL,
    `guest_count`      INT UNSIGNED    NOT NULL DEFAULT 0,
    `special_requests` TEXT            DEFAULT NULL,
    `total_amount`     DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `advance_amount`   DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `balance_amount`   DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `status`           ENUM('pending','approved','rejected','cancelled','completed') NOT NULL DEFAULT 'pending',
    `rejection_reason` TEXT            DEFAULT NULL,
    `cancellation_reason` TEXT         DEFAULT NULL,
    `is_deleted`       TINYINT(1)      NOT NULL DEFAULT 0,
    `completed_at`     DATETIME        DEFAULT NULL,
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME        DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`booking_id`),
    FOREIGN KEY (`customer_id`)    REFERENCES `users`(`user_id`)     ON DELETE RESTRICT,
    FOREIGN KEY (`hall_id`)        REFERENCES `hall`(`hall_id`)      ON DELETE RESTRICT,
    FOREIGN KEY (`sub_package_id`) REFERENCES `packages`(`package_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 6. payments ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `payments` (
    `payment_id`      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `booking_id`      INT UNSIGNED    NOT NULL,
    `payment_type`    ENUM('advance','balance','full') NOT NULL DEFAULT 'advance',
    `amount`          DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `method`          ENUM('cash','bank_transfer','card','online') NOT NULL DEFAULT 'cash',
    `reference`       VARCHAR(100)    DEFAULT NULL,
    `notes`           TEXT            DEFAULT NULL,
    `status`          ENUM('pending','paid','refunded','failed') NOT NULL DEFAULT 'pending',
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`payment_id`),
    FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`booking_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 7. transactions ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `transactions` (
    `transaction_id` INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `payment_id`     INT UNSIGNED  NOT NULL,
    `changed_by`     INT UNSIGNED  NOT NULL   COMMENT 'admin user_id',
    `old_status`     VARCHAR(20)   NOT NULL,
    `new_status`     VARCHAR(20)   NOT NULL,
    `note`           TEXT          DEFAULT NULL,
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`transaction_id`),
    FOREIGN KEY (`payment_id`)  REFERENCES `payments`(`payment_id`) ON DELETE CASCADE,
    FOREIGN KEY (`changed_by`)  REFERENCES `users`(`user_id`)       ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 8. feedback ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `feedback` (
    `feedback_id`  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `booking_id`   INT UNSIGNED  NOT NULL,
    `customer_id`  INT UNSIGNED  NOT NULL,
    `rating`       TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1–5 stars',
    `comment`      TEXT          DEFAULT NULL,
    `is_visible`   TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`feedback_id`),
    UNIQUE KEY `uq_booking_feedback` (`booking_id`),
    FOREIGN KEY (`booking_id`)  REFERENCES `bookings`(`booking_id`) ON DELETE CASCADE,
    FOREIGN KEY (`customer_id`) REFERENCES `users`(`user_id`)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
