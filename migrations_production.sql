-- ============================================================================
-- PRODUCTION DATABASE MIGRATIONS
-- Payment & Order Management Fix
-- Run these in order via phpMyAdmin SQL tab
-- Database: rads_tooling
-- ============================================================================

-- ============================================================================
-- MIGRATION 1: Add remaining_balance column
-- ============================================================================
-- First check if it exists
SELECT COLUMN_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'rads_tooling'
  AND TABLE_NAME = 'orders'
  AND COLUMN_NAME = 'remaining_balance';

-- If result is EMPTY, run this:
ALTER TABLE orders
ADD COLUMN remaining_balance DECIMAL(10,2) DEFAULT 0.00
COMMENT 'Remaining unpaid balance'
AFTER total_amount;


-- ============================================================================
-- MIGRATION 2: Calculate initial remaining_balance values
-- ============================================================================
UPDATE orders o
LEFT JOIN (
    SELECT order_id, COALESCE(SUM(amount_paid), 0) as total_paid
    FROM payments
    WHERE UPPER(status) IN ('VERIFIED', 'APPROVED')
    GROUP BY order_id
) p ON p.order_id = o.id
SET o.remaining_balance = GREATEST(0, o.total_amount - COALESCE(p.total_paid, 0));


-- ============================================================================
-- MIGRATION 3: Fix payment_status ENUM to include 'With Balance'
-- ============================================================================
ALTER TABLE orders
MODIFY payment_status ENUM('Pending','With Balance','Fully Paid','Partially Paid')
DEFAULT 'Pending';

-- Update existing 'Partially Paid' records to 'With Balance'
UPDATE orders
SET payment_status = 'With Balance'
WHERE payment_status = 'Partially Paid';


-- ============================================================================
-- MIGRATION 4: Fix terms_agreed for existing orders
-- ⚠️ CRITICAL: Required or payment approvals will fail
-- ============================================================================
UPDATE orders
SET terms_agreed = 1
WHERE terms_agreed = 0;


-- ============================================================================
-- VERIFICATION QUERY: Check all changes
-- ============================================================================
SELECT
    id,
    order_code,
    customer_id,
    total_amount,
    remaining_balance,
    payment_status,
    terms_agreed,
    status,
    order_date
FROM orders
ORDER BY id DESC
LIMIT 15;

-- Expected results:
-- ✅ remaining_balance should be calculated (total_amount - paid amount)
-- ✅ payment_status should be 'Pending', 'With Balance', or 'Fully Paid'
-- ✅ terms_agreed should be 1 for all orders
-- ✅ No NULL values in remaining_balance


-- ============================================================================
-- OPTIONAL: Check payment summary per order
-- ============================================================================
SELECT
    o.id,
    o.order_code,
    o.total_amount,
    o.remaining_balance,
    o.payment_status,
    o.terms_agreed,
    COALESCE(SUM(p.amount_paid), 0) as total_paid_from_payments,
    COUNT(pv.id) as payment_submissions,
    SUM(CASE WHEN pv.status = 'APPROVED' THEN 1 ELSE 0 END) as approved_payments
FROM orders o
LEFT JOIN payments p ON p.order_id = o.id AND UPPER(p.status) IN ('VERIFIED', 'APPROVED')
LEFT JOIN payment_verifications pv ON pv.order_id = o.id
GROUP BY o.id
ORDER BY o.id DESC
LIMIT 10;


-- ============================================================================
-- ROLLBACK SCRIPT (Only run if you need to undo changes)
-- ============================================================================
/*
-- Remove remaining_balance column
ALTER TABLE orders DROP COLUMN remaining_balance;

-- Revert payment_status ENUM
ALTER TABLE orders
MODIFY payment_status ENUM('Pending','Partially Paid','Fully Paid')
DEFAULT 'Pending';

UPDATE orders SET payment_status = 'Partially Paid' WHERE payment_status = 'With Balance';

-- Revert terms_agreed (specify IDs)
UPDATE orders SET terms_agreed = 0 WHERE id IN (1,2,3,4,5,6,7,8,9,10,11,12,13,17,18,19,20,21,22,23,24,25);
*/
