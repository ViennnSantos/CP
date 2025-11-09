-- Add remaining_balance column to orders table
-- This column tracks the outstanding amount to be paid for each order

ALTER TABLE orders
ADD COLUMN IF NOT EXISTS remaining_balance DECIMAL(10,2) DEFAULT 0.00
COMMENT 'Outstanding balance to be paid'
AFTER total_amount;

-- Update existing orders to calculate their remaining balance
-- based on total_amount minus verified payments
UPDATE orders o
SET o.remaining_balance = GREATEST(0,
    o.total_amount - COALESCE((
        SELECT SUM(p.amount_paid)
        FROM payments p
        WHERE p.order_id = o.id
        AND UPPER(COALESCE(p.status,'')) IN ('VERIFIED','APPROVED')
    ), 0)
)
WHERE o.remaining_balance IS NULL OR o.remaining_balance = 0;
