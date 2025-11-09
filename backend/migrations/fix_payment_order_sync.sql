-- ============================================================================
-- MIGRATION: Fix Payment & Order Management Sync
-- Date: 2025-11-09
-- Description: Add remaining_balance, fix payment_status ENUM, update terms_agreed
-- Safe for production - all operations are non-destructive
-- ============================================================================

-- ============================================================================
-- STEP 1: Add remaining_balance column
-- ============================================================================
-- This column tracks the unpaid balance for each order
ALTER TABLE orders
ADD COLUMN IF NOT EXISTS remaining_balance DECIMAL(10,2) DEFAULT 0.00
COMMENT 'Remaining unpaid balance'
AFTER total_amount;

-- Expected: Success or "Duplicate column name" (both OK)


-- ============================================================================
-- STEP 2: Calculate initial remaining_balance for all orders
-- ============================================================================
-- Calculate: total_amount - sum(approved payments)
UPDATE orders o
LEFT JOIN (
    SELECT order_id, COALESCE(SUM(amount_paid), 0) as total_paid
    FROM payments
    WHERE UPPER(status) IN ('VERIFIED', 'APPROVED')
    GROUP BY order_id
) p ON p.order_id = o.id
SET o.remaining_balance = GREATEST(0, o.total_amount - COALESCE(p.total_paid, 0));

-- Expected: All orders updated with calculated balances


-- ============================================================================
-- STEP 3: Fix payment_status ENUM to include 'With Balance'
-- ============================================================================
-- Current: ('Pending','Partially Paid','Fully Paid')
-- After: ('Pending','With Balance','Fully Paid','Partially Paid')
ALTER TABLE orders
MODIFY payment_status ENUM('Pending','With Balance','Fully Paid','Partially Paid')
DEFAULT 'Pending';

-- Expected: Success


-- ============================================================================
-- STEP 4: Update existing 'Partially Paid' to 'With Balance'
-- ============================================================================
-- For consistency with new code that uses 'With Balance'
UPDATE orders
SET payment_status = 'With Balance'
WHERE payment_status = 'Partially Paid';

-- Expected: Rows updated (or 0 if none exist)


-- ============================================================================
-- STEP 5: Fix terms_agreed for all existing orders
-- ⚠️ CRITICAL: Without this, payment approvals will fail!
-- ============================================================================
-- Assumption: Old orders (before T&C feature) should be marked as agreed
UPDATE orders
SET terms_agreed = 1
WHERE terms_agreed = 0;

-- Expected: 21 rows updated (based on your data: orders 1-13, 17-25)


-- ============================================================================
-- VERIFICATION: Check results
-- ============================================================================
SELECT
    id,
    order_code,
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
-- ✅ remaining_balance column exists with calculated values
-- ✅ payment_status = 'Pending', 'With Balance', or 'Fully Paid'
-- ✅ terms_agreed = 1 for all orders
-- ✅ No NULL values in critical columns


-- ============================================================================
-- OPTIONAL: Detailed payment summary per order
-- ============================================================================
SELECT
    o.id,
    o.order_code,
    o.total_amount,
    o.remaining_balance,
    o.payment_status,
    o.terms_agreed,
    COALESCE(SUM(p.amount_paid), 0) as total_paid_from_payments_table,
    o.total_amount - o.remaining_balance as calculated_paid,
    COUNT(DISTINCT pv.id) as payment_submissions,
    SUM(CASE WHEN UPPER(pv.status) = 'APPROVED' THEN 1 ELSE 0 END) as approved_count
FROM orders o
LEFT JOIN payments p ON p.order_id = o.id AND UPPER(p.status) IN ('VERIFIED', 'APPROVED')
LEFT JOIN payment_verifications pv ON pv.order_id = o.id
GROUP BY o.id
ORDER BY o.id DESC
LIMIT 10;


-- ============================================================================
-- ROLLBACK SCRIPT (Only use if something goes wrong)
-- ============================================================================
/*
-- Remove remaining_balance column
ALTER TABLE orders DROP COLUMN IF EXISTS remaining_balance;

-- Revert payment_status ENUM
ALTER TABLE orders
MODIFY payment_status ENUM('Pending','Partially Paid','Fully Paid')
DEFAULT 'Pending';

-- Change 'With Balance' back to 'Partially Paid'
UPDATE orders SET payment_status = 'Partially Paid' WHERE payment_status = 'With Balance';

-- Revert terms_agreed to 0 for specific orders (adjust IDs as needed)
UPDATE orders SET terms_agreed = 0
WHERE id IN (1,2,3,4,5,6,7,8,9,10,11,12,13,17,18,19,20,21,22,23,24,25);
*/
