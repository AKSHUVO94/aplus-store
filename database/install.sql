-- =====================================================
-- AK Clothing Store - Complete E-Commerce Install 2026
-- Brand: AK | Fashion Apparel
-- Import this ONE file in phpMyAdmin
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `ak_store` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ak_store`;

DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `product_images`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `contact_messages`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `themes`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `coupons`;

CREATE TABLE `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `slug` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `permissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `group_name` VARCHAR(50) DEFAULT 'general',
  PRIMARY KEY (`id`),
  UNIQUE KEY (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `role_permissions` (
  `role_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `address` TEXT,
  `city` VARCHAR(80) DEFAULT NULL,
  `country` VARCHAR(80) DEFAULT 'Bangladesh',
  `role_id` INT UNSIGNED NOT NULL DEFAULT 4,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `image` VARCHAR(255) DEFAULT NULL,
  `parent_id` INT UNSIGNED DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(200) NOT NULL,
  `sku` VARCHAR(50) DEFAULT NULL,
  `short_description` VARCHAR(500) DEFAULT NULL,
  `description` TEXT,
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `sale_price` DECIMAL(12,2) DEFAULT NULL,
  `stock` INT NOT NULL DEFAULT 0,
  `sizes` VARCHAR(255) DEFAULT 'S,M,L,XL',
  `colors` VARCHAR(255) DEFAULT 'Black,White',
  `material` VARCHAR(100) DEFAULT NULL,
  `gender` ENUM('men','women','unisex','kids') DEFAULT 'unisex',
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `is_new` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive','draft') NOT NULL DEFAULT 'active',
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`slug`),
  KEY (`category_id`),
  KEY (`status`),
  KEY (`gender`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `product_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `alt_text` VARCHAR(200) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pi_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number` VARCHAR(30) NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `customer_name` VARCHAR(100) NOT NULL,
  `customer_email` VARCHAR(150) NOT NULL,
  `customer_phone` VARCHAR(30) DEFAULT NULL,
  `shipping_address` TEXT NOT NULL,
  `shipping_city` VARCHAR(80) DEFAULT NULL,
  `shipping_country` VARCHAR(80) DEFAULT 'Bangladesh',
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `shipping_cost` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `discount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `payment_method` ENUM('cod','bkash','nagad','bank','card') NOT NULL DEFAULT 'cod',
  `payment_status` ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `status` ENUM('pending','confirmed','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `notes` TEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`order_number`),
  KEY (`status`),
  KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED DEFAULT NULL,
  `product_name` VARCHAR(200) NOT NULL,
  `product_sku` VARCHAR(50) DEFAULT NULL,
  `size` VARCHAR(20) DEFAULT NULL,
  `color` VARCHAR(50) DEFAULT NULL,
  `price` DECIMAL(12,2) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `total` DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(100) NOT NULL,
  `value` TEXT,
  `type` VARCHAR(20) NOT NULL DEFAULT 'string',
  PRIMARY KEY (`id`),
  UNIQUE KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `themes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `slug` VARCHAR(50) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 0,
  `is_dark` TINYINT(1) NOT NULL DEFAULT 0,
  `primary_color` VARCHAR(20) NOT NULL,
  `secondary_color` VARCHAR(20) NOT NULL,
  `accent_color` VARCHAR(20) NOT NULL,
  `background` VARCHAR(20) NOT NULL,
  `surface` VARCHAR(20) NOT NULL,
  `text_primary` VARCHAR(20) NOT NULL,
  `text_secondary` VARCHAR(20) NOT NULL,
  `border_color` VARCHAR(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `coupons` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(30) NOT NULL,
  `type` ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  `value` DECIMAL(12,2) NOT NULL,
  `min_order` DECIMAL(12,2) DEFAULT 0,
  `usage_limit` INT DEFAULT NULL,
  `used_count` INT NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `expires_at` DATE DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `contact_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `subject` VARCHAR(200) DEFAULT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('new','read') NOT NULL DEFAULT 'new',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ========== SEED ==========

INSERT INTO `roles` VALUES
(1,'Super Admin','super-admin'),
(2,'Admin','admin'),
(3,'Staff','staff'),
(4,'Customer','customer');

INSERT INTO `permissions` (`id`,`name`,`slug`,`group_name`) VALUES
(1,'View Dashboard','dashboard.view','dashboard'),
(2,'Manage Products','products.manage','catalog'),
(3,'Manage Categories','categories.manage','catalog'),
(4,'Manage Orders','orders.manage','sales'),
(5,'View Orders','orders.view','sales'),
(6,'Manage Customers','customers.manage','customers'),
(7,'Manage Coupons','coupons.manage','sales'),
(8,'Manage Settings','settings.manage','settings'),
(9,'Manage Themes','themes.manage','settings'),
(10,'View Messages','messages.view','messages');

INSERT INTO `role_permissions` SELECT 1, id FROM permissions;
INSERT INTO `role_permissions` SELECT 2, id FROM permissions WHERE slug != 'settings.manage' OR 1=1;
INSERT INTO `role_permissions` VALUES (3,1),(3,2),(3,4),(3,5),(3,10);

-- password: password123
INSERT INTO `users` (`name`,`email`,`password`,`phone`,`role_id`,`status`) VALUES
('AK Admin','admin@ak.com','$2y$10$hkzJUgA9kOLkyYS39/p6JOoOW2hxOjqcpcK/J0rDBsxTKqy.ILxwy','01700000000',1,'active'),
('Demo Customer','customer@ak.com','$2y$10$hkzJUgA9kOLkyYS39/p6JOoOW2hxOjqcpcK/J0rDBsxTKqy.ILxwy','01800000000',4,'active');

INSERT INTO `categories` (`id`,`name`,`slug`,`description`,`sort_order`,`status`) VALUES
(1,'Men','men','Premium menswear collection',1,'active'),
(2,'Women','women','Elegant women fashion',2,'active'),
(3,'T-Shirts','t-shirts','Casual and graphic tees',3,'active'),
(4,'Shirts','shirts','Formal and casual shirts',4,'active'),
(5,'Pants','pants','Trousers, jeans and chinos',5,'active'),
(6,'Jackets','jackets','Outerwear and jackets',6,'active'),
(7,'Hoodies','hoodies','Hoodies and sweatshirts',7,'active'),
(8,'Accessories','accessories','Caps, belts and more',8,'active');

INSERT INTO `products` (`category_id`,`name`,`slug`,`sku`,`short_description`,`description`,`price`,`sale_price`,`stock`,`sizes`,`colors`,`material`,`gender`,`is_featured`,`is_new`,`status`) VALUES
(3,'AK Classic Logo Tee','ak-classic-logo-tee','AK-TS-001','Soft cotton tee with signature AK logo.','Premium 100% combed cotton t-shirt. Regular fit, pre-shrunk, durable print. Perfect everyday essential.',1290.00,NULL,120,'S,M,L,XL,XXL','Black,White,Navy,Olive','100% Cotton','unisex',1,1,'active'),
(3,'AK Oversized Street Tee','ak-oversized-street-tee','AK-TS-002','Oversized fit street-style tee.','Heavyweight cotton oversized t-shirt. Drop shoulder, boxy fit. Urban streetwear essential.',1590.00,1390.00,80,'M,L,XL,XXL','Black,Grey,Beige','100% Cotton','unisex',1,1,'active'),
(3,'AK Minimal Text Tee','ak-minimal-text-tee','AK-TS-003','Clean minimal typography design.','Ultra-soft cotton with subtle AK wordmark. Timeless minimal aesthetic.',1190.00,NULL,95,'S,M,L,XL','White,Black,Cream','Organic Cotton','unisex',0,1,'active'),
(4,'AK Oxford Formal Shirt','ak-oxford-formal-shirt','AK-SH-001','Classic oxford button-down shirt.','Premium oxford cotton formal shirt. Button-down collar, regular fit. Office to evening ready.',2490.00,NULL,60,'S,M,L,XL','White,Light Blue,Pink','Oxford Cotton','men',1,0,'active'),
(4,'AK Linen Casual Shirt','ak-linen-casual-shirt','AK-SH-002','Breathable linen summer shirt.','100% pure linen. Relaxed fit, perfect for warm weather. Natural texture and drape.',2790.00,2490.00,45,'S,M,L,XL','White,Beige,Sky Blue','100% Linen','men',1,1,'active'),
(4,'AK Women Silk Blouse','ak-women-silk-blouse','AK-SH-003','Elegant silk-blend blouse.','Luxurious silk-blend blouse with soft drape. Perfect for work or special occasions.',3290.00,NULL,40,'XS,S,M,L','Ivory,Black,Blush','Silk Blend','women',1,1,'active'),
(5,'AK Slim Fit Chinos','ak-slim-chinos','AK-PT-001','Modern slim-fit chino pants.','Stretch cotton chino. Slim tapered fit, mid-rise. Versatile for casual and smart looks.',2290.00,1990.00,70,'28,30,32,34,36','Khaki,Navy,Black,Olive','Cotton Stretch','men',1,0,'active'),
(5,'AK Straight Denim Jeans','ak-straight-denim','AK-PT-002','Classic straight-leg denim.','Premium denim with comfortable stretch. Straight fit, medium wash. Everyday classic.',2690.00,NULL,85,'28,30,32,34,36','Blue,Black,Grey','Denim 98% Cotton 2% Elastane','unisex',1,0,'active'),
(5,'AK Women Wide Leg Pants','ak-women-wide-leg','AK-PT-003','Trendy high-waist wide leg pants.','High-waist wide leg trousers. Flowing silhouette, comfortable and stylish.',2390.00,2190.00,55,'XS,S,M,L','Black,Cream,Brown','Polyester Blend','women',1,1,'active'),
(6,'AK Bomber Jacket','ak-bomber-jacket','AK-JK-001','Modern classic bomber jacket.','Lightweight bomber with ribbed cuffs and hem. Clean design, multiple pockets.',3990.00,3490.00,35,'S,M,L,XL','Black,Olive,Navy','Polyester','unisex',1,1,'active'),
(6,'AK Denim Trucker Jacket','ak-denim-trucker','AK-JK-002','Iconic denim trucker jacket.','Classic trucker silhouette in rigid denim. Ages beautifully with wear.',3790.00,NULL,40,'S,M,L,XL','Blue,Black','100% Cotton Denim','unisex',0,0,'active'),
(6,'AK Women Blazer','ak-women-blazer','AK-JK-003','Tailored structured blazer.','Sharp tailored blazer. Single breasted, structured shoulders. Power dressing essential.',4490.00,3990.00,30,'XS,S,M,L','Black,Camel,Grey','Wool Blend','women',1,0,'active'),
(7,'AK Essential Hoodie','ak-essential-hoodie','AK-HD-001','Everyday premium hoodie.','Heavyweight fleece hoodie. Kangaroo pocket, adjustable hood. Ultimate comfort.',2890.00,NULL,90,'S,M,L,XL,XXL','Black,Grey,Navy,Cream','Cotton Fleece','unisex',1,1,'active'),
(7,'AK Zip-Up Hoodie','ak-zip-hoodie','AK-HD-002','Full-zip hoodie with clean lines.','Full zip front, side pockets. Perfect layering piece for any season.',3090.00,2790.00,65,'S,M,L,XL','Black,Olive,Charcoal','Cotton Fleece','unisex',0,1,'active'),
(7,'AK Cropped Women Hoodie','ak-cropped-hoodie','AK-HD-003','Trendy cropped hoodie for women.','Soft cropped fit hoodie. Modern silhouette, ribbed hem.',2690.00,NULL,50,'XS,S,M,L','Pink,Black,Lavender','Cotton Blend','women',1,1,'active'),
(8,'AK Signature Cap','ak-signature-cap','AK-AC-001','Embroidered AK logo cap.','Structured 6-panel cap with embroidered logo. Adjustable strap.',890.00,NULL,150,'One Size','Black,Navy,White,Olive','Cotton Twill','unisex',1,0,'active'),
(8,'AK Leather Belt','ak-leather-belt','AK-AC-002','Genuine leather belt with metal buckle.','Full-grain leather belt. Classic buckle, multiple sizes.',1490.00,NULL,80,'30,32,34,36,38','Black,Brown','Genuine Leather','unisex',0,0,'active'),
(8,'AK Crew Socks 3-Pack','ak-crew-socks','AK-AC-003','Comfortable crew socks pack of 3.','Soft cotton blend crew socks. Reinforced heel and toe.',690.00,590.00,200,'One Size','Black,White,Grey','Cotton Blend','unisex',0,1,'active'),
(1,'AK Performance Polo','ak-performance-polo','AK-MN-001','Breathable performance polo shirt.','Moisture-wicking fabric, modern fit. Perfect for active and casual wear.',1890.00,NULL,55,'S,M,L,XL','Navy,Black,White,Burgundy','Polyester Blend','men',0,1,'active'),
(2,'AK Women Midi Dress','ak-women-midi-dress','AK-WN-001','Elegant midi dress for any occasion.','Flattering midi length dress. Soft fabric, subtle waist definition.',3590.00,3190.00,35,'XS,S,M,L','Black,Navy,Emerald','Polyester','women',1,1,'active');

INSERT INTO `themes` (`name`,`slug`,`is_active`,`is_dark`,`primary_color`,`secondary_color`,`accent_color`,`background`,`surface`,`text_primary`,`text_secondary`,`border_color`) VALUES
('AK Dark','ak-dark',1,1,'#e11d48','#f43f5e','#fb7185','#0a0a0a','#171717','#fafafa','#a3a3a3','#262626'),
('AK Light','ak-light',0,0,'#e11d48','#be123c','#f43f5e','#fafafa','#ffffff','#0a0a0a','#525252','#e5e5e5'),
('Midnight Gold','midnight-gold',0,1,'#d4a017','#f0c040','#e8b923','#0c0b09','#1a1814','#f5f0e6','#a89f91','#2e2a24'),
('Ocean Noir','ocean-noir',0,1,'#0ea5e9','#38bdf8','#7dd3fc','#020617','#0f172a','#f1f5f9','#94a3b8','#1e293b'),
('Soft Rose','soft-rose',0,0,'#e11d48','#fb7185','#fda4af','#fff1f2','#ffffff','#1c1917','#78716c','#fecdd3'),
('Forest Night','forest-night',0,1,'#22c55e','#4ade80','#86efac','#052e16','#14532d','#f0fdf4','#86efac','#166534'),
('Purple Luxe','purple-luxe',0,1,'#a855f7','#c084fc','#e9d5ff','#0f0a1a','#1a1228','#faf5ff','#c4b5fd','#2e1f4a'),
('Coral Pop','coral-pop',0,0,'#f97316','#fb923c','#fdba74','#fff7ed','#ffffff','#1c1917','#78716c','#ffedd5'),
('Ice Blue','ice-blue',0,0,'#0284c7','#0ea5e9','#38bdf8','#f0f9ff','#ffffff','#0c4a6e','#64748b','#e0f2fe'),
('Charcoal Red','charcoal-red',0,1,'#dc2626','#ef4444','#fca5a5','#121212','#1e1e1e','#fafafa','#a3a3a3','#333333'),
('Mint Fresh','mint-fresh',0,0,'#059669','#10b981','#6ee7b7','#ecfdf5','#ffffff','#064e3b','#6b7280','#d1fae5'),
('Royal Navy','royal-navy',0,1,'#1e40af','#3b82f6','#93c5fd','#020617','#0f172a','#f8fafc','#94a3b8','#1e293b'),
('Emerald Dark','emerald-dark',0,1,'#10b981','#34d399','#6ee7b7','#022c22','#064e3b','#ecfdf5','#a7f3d0','#065f46'),
('Sunset Blush','sunset-blush',0,0,'#db2777','#f472b6','#f9a8d4','#fdf2f8','#ffffff','#831843','#9d174d','#fbcfe8'),
('Slate Pro','slate-pro',0,1,'#64748b','#94a3b8','#cbd5e1','#0f172a','#1e293b','#f8fafc','#94a3b8','#334155'),
('Amber Luxe','amber-luxe',0,1,'#f59e0b','#fbbf24','#fcd34d','#1c1917','#292524','#fafaf9','#a8a29e','#44403c'),
('Teal Clean','teal-clean',0,0,'#0d9488','#14b8a6','#5eead4','#f0fdfa','#ffffff','#134e4a','#5b7c7a','#ccfbf1'),
('Violet Night','violet-night',0,1,'#7c3aed','#8b5cf6','#a78bfa','#0c0a1a','#1a1433','#f5f3ff','#c4b5fd','#2e1065'),
('Crimson Light','crimson-light',0,0,'#b91c1c','#ef4444','#fca5a5','#fef2f2','#ffffff','#450a0a','#7f1d1d','#fecaca'),
('Graphite Gold','graphite-gold',0,1,'#ca8a04','#eab308','#fde047','#18181b','#27272a','#fafafa','#a1a1aa','#3f3f46');

INSERT INTO `settings` (`key`,`value`,`type`) VALUES
('site_name','AK','string'),
('site_tagline','Define Your Style','string'),
('site_description','AK is a modern clothing brand offering premium apparel for men and women. Quality fabrics, timeless designs.','string'),
('site_email','hello@ak.com','string'),
('site_phone','+880 1700-000000','string'),
('site_address','Dhaka, Bangladesh','string'),
('currency','BDT','string'),
('currency_symbol','৳','string'),
('shipping_cost','120','string'),
('free_shipping_min','3000','string'),
('active_theme','ak-dark','string');

INSERT INTO `coupons` (`code`,`type`,`value`,`min_order`,`usage_limit`,`status`,`expires_at`) VALUES
('AK10','percent',10,1000,100,'active','2026-12-31'),
('AK50','fixed',50,500,200,'active','2026-12-31'),
('WELCOME15','percent',15,1500,50,'active','2026-12-31');

INSERT INTO `orders` (`order_number`,`customer_name`,`customer_email`,`customer_phone`,`shipping_address`,`shipping_city`,`subtotal`,`shipping_cost`,`total`,`payment_method`,`payment_status`,`status`) VALUES
('AK-20260301-001','Rahim Ahmed','rahim@email.com','01711111111','House 12, Road 5, Banani','Dhaka',3880.00,120.00,4000.00,'cod','pending','pending'),
('AK-20260228-002','Nusrat Jahan','nusrat@email.com','01822222222','Flat 3B, Dhanmondi 27','Dhaka',5580.00,0.00,5580.00,'bkash','paid','confirmed');

INSERT INTO `order_items` (`order_id`,`product_id`,`product_name`,`product_sku`,`size`,`color`,`price`,`quantity`,`total`) VALUES
(1,1,'AK Classic Logo Tee','AK-TS-001','L','Black',1290.00,2,2580.00),
(1,16,'AK Signature Cap','AK-AC-001','One Size','Black',890.00,1,890.00),
(2,13,'AK Essential Hoodie','AK-HD-001','M','Grey',2890.00,1,2890.00),
(2,10,'AK Bomber Jacket','AK-JK-001','L','Black',3490.00,1,3490.00);

INSERT INTO `contact_messages` (`name`,`email`,`subject`,`message`,`status`) VALUES
('Karim Hassan','karim@mail.com','Size Guide Question','Do you have a detailed size chart for the oversized tees?','new'),
('Fatima Khan','fatima@mail.com','Wholesale Inquiry','Interested in bulk orders for our store. Please contact me.','new');

SELECT 'AK Store installed successfully! Login: admin@ak.com / password123' AS message;
