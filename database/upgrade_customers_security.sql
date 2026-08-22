USE `ak_store`;

CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `full_name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `city` VARCHAR(80) DEFAULT NULL,
  `country` VARCHAR(80) DEFAULT 'Bangladesh',
  `postal_code` VARCHAR(20) DEFAULT NULL,
  `total_orders` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_spent` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('active','blocked') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_customer_email` (`email`),
  KEY `idx_customer_user` (`user_id`),
  KEY `idx_customer_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Link orders to customers table
ALTER TABLE `orders` ADD COLUMN `customer_id` INT UNSIGNED DEFAULT NULL AFTER `user_id`;

-- Login attempts for security
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` VARCHAR(45) NOT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin sessions / security settings
INSERT INTO `settings` (`key`,`value`,`type`) VALUES
('admin_login_max_attempts','5','string'),
('admin_login_lock_minutes','15','string'),
('session_timeout_minutes','120','string')
ON DUPLICATE KEY UPDATE `key`=`key`;

-- Migrate existing customers from users role_id=4
INSERT IGNORE INTO `customers` (`user_id`, `full_name`, `email`, `phone`, `address`, `city`, `country`)
SELECT u.id, u.name, u.email, u.phone, u.address, u.city, COALESCE(u.country,'Bangladesh')
FROM users u WHERE u.role_id = 4;
