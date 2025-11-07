-- Customer Addresses Table
-- Stores multiple addresses per customer with PSGC support

CREATE TABLE IF NOT EXISTS `customer_addresses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `customer_id` INT(11) NOT NULL,
  `address_nickname` VARCHAR(100) DEFAULT NULL COMMENT 'Optional nickname like Home, Office',
  `full_name` VARCHAR(255) NOT NULL,
  `mobile_number` VARCHAR(20) NOT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `province` VARCHAR(100) NOT NULL,
  `province_code` VARCHAR(20) DEFAULT NULL COMMENT 'PSGC code',
  `city_municipality` VARCHAR(100) NOT NULL,
  `city_code` VARCHAR(20) DEFAULT NULL COMMENT 'PSGC code',
  `barangay` VARCHAR(100) NOT NULL,
  `barangay_code` VARCHAR(20) DEFAULT NULL COMMENT 'PSGC code',
  `street_block_lot` TEXT NOT NULL COMMENT 'House number, street, building, etc.',
  `postal_code` VARCHAR(10) DEFAULT NULL,
  `is_default` TINYINT(1) DEFAULT 0 COMMENT '1 = default address, 0 = not default',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_is_default` (`is_default`),
  KEY `idx_customer_default` (`customer_id`, `is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: Foreign key constraint removed to avoid data type mismatch errors
-- If you want to add it manually, first check customers.id data type:
--   SHOW CREATE TABLE customers;
-- Then add constraint matching that type:
--   ALTER TABLE customer_addresses
--   ADD CONSTRAINT fk_customer_addresses_customer
--   FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE;
