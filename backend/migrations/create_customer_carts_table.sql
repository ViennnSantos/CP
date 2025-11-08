-- Create customer_carts table for persistent cart storage
-- Note: Foreign key constraint removed for compatibility - application enforces referential integrity
CREATE TABLE IF NOT EXISTS customer_carts (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT(11) UNSIGNED NOT NULL,
    cart_data LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'JSON formatted cart data',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_customer_cart (customer_id),
    KEY idx_customer_id (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
