USE `ak_store`;

-- Extra themes
INSERT IGNORE INTO `themes` (`name`,`slug`,`is_active`,`is_dark`,`primary_color`,`secondary_color`,`accent_color`,`background`,`surface`,`text_primary`,`text_secondary`,`border_color`) VALUES
('Purple Luxe','purple-luxe',0,1,'#a855f7','#c084fc','#e9d5ff','#0f0a1a','#1a1228','#faf5ff','#c4b5fd','#2e1f4a'),
('Coral Pop','coral-pop',0,0,'#f97316','#fb923c','#fdba74','#fff7ed','#ffffff','#1c1917','#78716c','#ffedd5'),
('Ice Blue','ice-blue',0,0,'#0284c7','#0ea5e9','#38bdf8','#f0f9ff','#ffffff','#0c4a6e','#64748b','#e0f2fe'),
('Charcoal Red','charcoal-red',0,1,'#dc2626','#ef4444','#fca5a5','#121212','#1e1e1e','#fafafa','#a3a3a3','#333333'),
('Mint Fresh','mint-fresh',0,0,'#059669','#10b981','#6ee7b7','#ecfdf5','#ffffff','#064e3b','#6b7280','#d1fae5'),
('Royal Navy','royal-navy',0,1,'#1e40af','#3b82f6','#93c5fd','#020617','#0f172a','#f8fafc','#94a3b8','#1e293b');

-- Payment gateway settings
INSERT INTO `settings` (`key`,`value`,`type`) VALUES
('pay_cod_enabled','1','string'),
('pay_bkash_enabled','1','string'),
('pay_nagad_enabled','1','string'),
('pay_bank_enabled','1','string'),
('pay_card_enabled','0','string'),
('pay_bkash_number','01700000000','string'),
('pay_bkash_type','Personal','string'),
('pay_nagad_number','01800000000','string'),
('pay_nagad_type','Personal','string'),
('pay_bank_name','Dutch-Bangla Bank','string'),
('pay_bank_account_name','AK Clothing','string'),
('pay_bank_account_no','1234567890','string'),
('pay_bank_branch','Dhaka','string'),
('pay_instructions','After payment, please keep your transaction ID. We will confirm your order within 24 hours.','string')
ON DUPLICATE KEY UPDATE `key`=`key`;

-- Allow bank payment method
ALTER TABLE `orders` MODIFY `payment_method` ENUM('cod','bkash','nagad','bank','card') NOT NULL DEFAULT 'cod';
