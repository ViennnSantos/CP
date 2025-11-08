-- Add terms_agreed column to orders table
ALTER TABLE orders
ADD COLUMN IF NOT EXISTS terms_agreed TINYINT(1) DEFAULT 0 COMMENT 'Whether customer agreed to Terms & Conditions'
AFTER total_amount;
