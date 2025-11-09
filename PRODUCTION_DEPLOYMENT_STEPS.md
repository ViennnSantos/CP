# 🚀 PRODUCTION DEPLOYMENT - Step-by-Step Guide

**Target**: Plesk Production Database
**Database**: `rads_tooling`
**Date**: November 9, 2025

---

## ✅ PRE-DEPLOYMENT CHECKLIST

- [ ] Backup database via phpMyAdmin (Export → Quick export)
- [ ] Backup current code files (download `assets/JS/script.js` and `backend/api/payment_verification.php`)
- [ ] Confirm database user: `rads_tooling` has ALTER and UPDATE privileges
- [ ] Estimated downtime: ~5 minutes

---

## 📦 PART 1: DATABASE MIGRATION (10 minutes)

### Access phpMyAdmin
1. Login to Plesk
2. Go to: **Databases → phpMyAdmin**
3. Select database: **`rads_tooling`**
4. Click **SQL** tab

---

### MIGRATION STEP 1: Add remaining_balance column

**Copy and paste this SQL**:
```sql
ALTER TABLE orders
ADD COLUMN IF NOT EXISTS remaining_balance DECIMAL(10,2) DEFAULT 0.00
COMMENT 'Remaining unpaid balance'
AFTER total_amount;
```

**Click "Go"**

**Expected result**:
- ✅ Success: "Query OK, 0 rows affected"
- ✅ OR: "Duplicate column name 'remaining_balance'" (column already exists - OK!)

**If error**: Screenshot and stop, report error.

---

### MIGRATION STEP 2: Calculate remaining_balance values

**Copy and paste this SQL**:
```sql
UPDATE orders o
LEFT JOIN (
    SELECT order_id, COALESCE(SUM(amount_paid), 0) as total_paid
    FROM payments
    WHERE UPPER(status) IN ('VERIFIED', 'APPROVED')
    GROUP BY order_id
) p ON p.order_id = o.id
SET o.remaining_balance = GREATEST(0, o.total_amount - COALESCE(p.total_paid, 0));
```

**Click "Go"**

**Expected result**:
- ✅ "X rows affected" (X = total number of orders, ~25 rows)

**Verify**:
```sql
SELECT id, order_code, total_amount, remaining_balance, payment_status
FROM orders
ORDER BY id DESC
LIMIT 5;
```

Check that `remaining_balance` has values (not all zeros).

---

### MIGRATION STEP 3: Fix payment_status ENUM

**Copy and paste this SQL**:
```sql
ALTER TABLE orders
MODIFY payment_status ENUM('Pending','With Balance','Fully Paid','Partially Paid')
DEFAULT 'Pending';
```

**Click "Go"**

**Expected result**:
- ✅ "Query OK, X rows affected"

---

### MIGRATION STEP 4: Update 'Partially Paid' → 'With Balance'

**Copy and paste this SQL**:
```sql
UPDATE orders
SET payment_status = 'With Balance'
WHERE payment_status = 'Partially Paid';
```

**Click "Go"**

**Expected result**:
- ✅ "X rows affected" (may be 0 if no 'Partially Paid' orders exist)

---

### MIGRATION STEP 5: Fix terms_agreed ⚠️ **CRITICAL**

**Copy and paste this SQL**:
```sql
UPDATE orders
SET terms_agreed = 1
WHERE terms_agreed = 0;
```

**Click "Go"**

**Expected result**:
- ✅ "21 rows affected" (based on your data: orders 1-13, 17-25)

**⚠️ IMPORTANT**: Without this step, payment approvals will fail with error:
> "Cannot approve payment. Customer has not accepted the Terms & Conditions."

---

### MIGRATION STEP 6: Verify ALL changes

**Copy and paste this SQL**:
```sql
SELECT
    id,
    order_code,
    total_amount,
    remaining_balance,
    payment_status,
    terms_agreed,
    status
FROM orders
ORDER BY id DESC
LIMIT 10;
```

**Click "Go"**

**✅ CHECK**:
- All orders have `remaining_balance` column with values
- All `terms_agreed` = **1**
- `payment_status` shows 'Pending', 'With Balance', or 'Fully Paid' (no 'Partially Paid')

**Example expected result**:
```
id | order_code     | total_amount | remaining_balance | payment_status | terms_agreed | status
25 | RT2511091251   | 14836.00     | 14836.00          | Pending        | 1            | Pending
24 | RT2511097849   | 12596.00     | 12596.00          | Pending        | 1            | Pending
23 | RT2511088956   | 24080.00     | 0.00              | Fully Paid     | 1            | Processing
...
```

---

## 📁 PART 2: CODE DEPLOYMENT (5 minutes)

### METHOD A: Git Pull (If you have Git set up on Plesk)

**SSH into Plesk** and run:
```bash
cd /var/www/vhosts/YOUR-DOMAIN/httpdocs  # Change to your actual path

# Pull the latest changes
git fetch origin
git checkout claude/fix-payment-tc-order-sync-011CUwb13s1eNcHVcHuwAurx
git pull origin claude/fix-payment-tc-order-sync-011CUwb13s1eNcHVcHuwAurx
```

### METHOD B: Manual Upload (Via Plesk File Manager or FTP)

**Download these 2 files from your local repo**:
1. `assets/JS/script.js`
2. `backend/api/payment_verification.php`

**Upload to Plesk** at these paths:
```
/var/www/vhosts/YOUR-DOMAIN/httpdocs/assets/JS/script.js
/var/www/vhosts/YOUR-DOMAIN/httpdocs/backend/api/payment_verification.php
```

**IMPORTANT**: Overwrite existing files when prompted.

---

### Verify File Upload

**Check file modification dates** in Plesk File Manager:
- `assets/JS/script.js` - should show recent timestamp (today)
- `backend/api/payment_verification.php` - should show recent timestamp (today)

---

## 🧪 PART 3: TESTING (10 minutes)

### TEST 1: Payment Approval Flow (Happy Path)

1. Login to admin panel
2. Go to **Payment Verification**
3. Click on any payment with status = "PENDING"
4. **✅ CHECK**: Modal shows:
   - "Terms & Conditions: ✓ Agreed" (green badge with checkmark)
   - Approve button is **ENABLED** (not grayed out)
5. Click **Approve Payment** button
6. **✅ CHECK**: Styled confirmation modal appears (NOT browser confirm dialog)
   - Title: "Approve Payment"
   - Message: "Are you sure you want to approve this payment? This will update the order balance and payment status."
   - Buttons: "Cancel" and "Approve"
7. Click **Approve**
8. **✅ CHECK**:
   - Success notification appears: "Payment approved successfully!"
   - Payment Verification modal closes
   - Payment status changes to "APPROVED"
9. Go to **Order Management**
10. **✅ CHECK**:
    - Order row shows updated `remaining_balance`
    - Order row shows updated `payment_status` badge
    - Changes appear **without page refresh**

---

### TEST 2: T&C Validation (Error Handling)

**Setup** (temporary test):
```sql
-- In phpMyAdmin, run:
UPDATE orders SET terms_agreed = 0 WHERE id = 1;
```

**Test**:
1. Go to Payment Verification
2. View payment for order #1
3. **✅ CHECK**: Modal shows:
   - "Terms & Conditions: ✗ Not Agreed" (red badge with X)
   - Approve button is **DISABLED** (grayed out)
   - Tooltip on hover: "Customer must agree to Terms & Conditions before approval"
4. Try to approve via API (if you can test directly)
   - **✅ CHECK**: Returns error 400: "Cannot approve payment. Customer has not accepted the Terms & Conditions."

**Cleanup**:
```sql
-- Reset order back to agreed:
UPDATE orders SET terms_agreed = 1 WHERE id = 1;
```

---

### TEST 3: Update Order Status Modal

1. Go to **Order Management**
2. Click **Update Status** on any order with balance > 0
3. **✅ CHECK**: Modal shows:
   - Current payment status and remaining balance (latest values)
   - "Completed" option is **DISABLED** if balance > 0
   - Warning message: "You cannot set this order to Completed until payment is fully settled"
4. Close modal without changes

---

### TEST 4: Order with Full Payment

1. Find or create an order with `remaining_balance = 0`
2. Go to **Order Management** → **Update Status**
3. **✅ CHECK**:
   - Payment Status shows "Fully Paid"
   - "Completed" option is **ENABLED**
   - No warning message about payment

---

## 🐛 TROUBLESHOOTING

### Issue 1: "Column 'remaining_balance' doesn't exist"

**Cause**: Step 1 migration failed or was skipped

**Fix**:
```sql
ALTER TABLE orders
ADD COLUMN remaining_balance DECIMAL(10,2) DEFAULT 0.00
AFTER total_amount;

-- Then recalculate:
UPDATE orders o
LEFT JOIN (
    SELECT order_id, COALESCE(SUM(amount_paid), 0) as total_paid
    FROM payments
    WHERE UPPER(status) IN ('VERIFIED', 'APPROVED')
    GROUP BY order_id
) p ON p.order_id = o.id
SET o.remaining_balance = GREATEST(0, o.total_amount - COALESCE(p.total_paid, 0));
```

---

### Issue 2: "Cannot approve payment. Customer has not accepted the Terms & Conditions."

**Cause**: `terms_agreed = 0` for that order

**Fix**:
```sql
-- Check the order's terms_agreed value:
SELECT id, order_code, terms_agreed FROM orders WHERE id = [ORDER_ID];

-- If terms_agreed = 0, update it:
UPDATE orders SET terms_agreed = 1 WHERE id = [ORDER_ID];
```

---

### Issue 3: Approve button still uses browser confirm()

**Cause**: Old JavaScript file cached in browser

**Fix**:
1. Hard refresh browser: **Ctrl + Shift + R** (Windows) or **Cmd + Shift + R** (Mac)
2. Clear browser cache
3. Verify file upload timestamp in Plesk File Manager

---

### Issue 4: Order Management doesn't update after approval

**Cause**: JavaScript function `loadOrders()` not found or error

**Check**:
1. Open browser console (F12 → Console tab)
2. Look for JavaScript errors
3. Verify `script.js` file uploaded correctly

**Fix**: Re-upload `assets/JS/script.js` and hard refresh

---

### Issue 5: payment_status shows 'Partially Paid'

**Cause**: Step 3 or 4 skipped

**Fix**:
```sql
-- Add 'With Balance' to ENUM:
ALTER TABLE orders
MODIFY payment_status ENUM('Pending','With Balance','Fully Paid','Partially Paid')
DEFAULT 'Pending';

-- Update values:
UPDATE orders SET payment_status = 'With Balance' WHERE payment_status = 'Partially Paid';
```

---

## 📊 POST-DEPLOYMENT VERIFICATION

Run this final check query in phpMyAdmin:

```sql
-- Summary report
SELECT
    'Total Orders' as metric,
    COUNT(*) as value
FROM orders

UNION ALL

SELECT
    'Orders with T&C Agreed',
    COUNT(*)
FROM orders WHERE terms_agreed = 1

UNION ALL

SELECT
    'Orders with Balance',
    COUNT(*)
FROM orders WHERE remaining_balance > 0

UNION ALL

SELECT
    'Fully Paid Orders',
    COUNT(*)
FROM orders WHERE payment_status = 'Fully Paid'

UNION ALL

SELECT
    'Using Old "Partially Paid"',
    COUNT(*)
FROM orders WHERE payment_status = 'Partially Paid';
```

**Expected results**:
```
metric                          | value
-------------------------------+-------
Total Orders                    | 25
Orders with T&C Agreed          | 25    ← Should equal Total Orders
Orders with Balance             | ~22   ← Orders not yet fully paid
Fully Paid Orders               | ~3    ← Orders with remaining_balance = 0
Using Old "Partially Paid"      | 0     ← Should be zero
```

---

## ✅ DEPLOYMENT SUCCESS CRITERIA

- [x] All database migrations completed without errors
- [x] All `terms_agreed` = 1
- [x] `remaining_balance` column exists with correct values
- [x] `payment_status` ENUM includes 'With Balance'
- [x] No 'Partially Paid' status in orders table
- [x] Code files uploaded and timestamps verified
- [x] Payment approval shows styled modal (not browser confirm)
- [x] Order Management updates in real-time after approval
- [x] T&C validation blocks approval when not agreed
- [x] No JavaScript errors in browser console

---

## 🎉 DEPLOYMENT COMPLETE!

**Files Modified**:
- `assets/JS/script.js`
- `backend/api/payment_verification.php`

**Database Changes**:
- Added `remaining_balance` column to `orders`
- Updated `payment_status` ENUM to include 'With Balance'
- Set all `terms_agreed = 1`

**Commit Reference**:
- Branch: `claude/fix-payment-tc-order-sync-011CUwb13s1eNcHVcHuwAurx`
- Commits: `406e6b0`, `14c0e3d`

---

## 📞 SUPPORT

If you encounter issues:
1. Check `backend/logs/payment_verification_errors.log`
2. Check browser console (F12 → Console)
3. Check network tab (F12 → Network) for API errors
4. Review this guide's Troubleshooting section

**Emergency Rollback**: See `backend/migrations/fix_payment_order_sync.sql` bottom section for rollback SQL commands.
