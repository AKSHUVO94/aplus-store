USE `ak_store`;
-- Add image snapshot column (ignore error if exists)
ALTER TABLE `order_items` ADD COLUMN `product_image` VARCHAR(255) DEFAULT NULL AFTER `product_sku`;
