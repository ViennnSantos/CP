# 🚀 COMPLETE DEPLOYMENT GUIDE - Payment & Order Management Fix

## 📋 OVERVIEW
This guide fixes:
- ✅ T&C validation before payment approval
- ✅ Styled modal confirmation (no browser alerts)
- ✅ Real-time Order Management UI updates after approval
- ✅ Correct display of T&C status and remaining balance

---

## PHASE 1: DATABASE MIGRATIONS ⚡

### 1.1 Access phpMyAdmin
1. Login to Plesk
2. Go to: Databases → phpMyAdmin
3. Select database: `rads_tooling`
4. Click "SQL" tab

### 1.2 Run Migration Scripts (Copy & Paste each one)

#### MIGRATION 1: Add remaining_balance column
```sql
-- Check if column exists
SELECT COLUMN_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'rads_tooling'
  AND TABLE_NAME = 'orders'
  AND COLUMN_NAME = 'remaining_balance';

-- If result is EMPTY, run this ALTER:
ALTER TABLE orders
ADD COLUMN remaining_balance DECIMAL(10,2) DEFAULT 0.00
COMMENT 'Remaining unpaid balance'
AFTER total_amount;
```

#### MIGRATION 2: Calculate initial remaining_balance values
```sql
-- Calculate remaining balance for all existing orders
UPDATE orders o
LEFT JOIN (
    SELECT order_id, COALESCE(SUM(amount_paid), 0) as total_paid
    FROM payments
    WHERE UPPER(status) IN ('VERIFIED', 'APPROVED')
    GROUP BY order_id
) p ON p.order_id = o.id
SET o.remaining_balance = GREATEST(0, o.total_amount - COALESCE(p.total_paid, 0));
```

#### MIGRATION 3: Fix payment_status ENUM
```sql
-- Add 'With Balance' to the ENUM (check current values first)
ALTER TABLE orders
MODIFY payment_status ENUM('Pending','With Balance','Fully Paid','Partially Paid')
DEFAULT 'Pending';

-- Update existing 'Partially Paid' to 'With Balance'
UPDATE orders
SET payment_status = 'With Balance'
WHERE payment_status = 'Partially Paid';
```

#### MIGRATION 4: Fix terms_agreed for existing orders ⚠️ CRITICAL
```sql
-- Set all existing orders to terms_agreed = 1
-- (Required or approvals will fail with "T&C not accepted" error)
UPDATE orders
SET terms_agreed = 1
WHERE terms_agreed = 0;
```

#### MIGRATION 5: Verify changes
```sql
-- Check the updates
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

**Expected result**: All orders should have:
- `remaining_balance` calculated correctly
- `payment_status` = 'With Balance' or 'Fully Paid'
- `terms_agreed` = 1

---

## PHASE 2: CODE DEPLOYMENT 📦

### METHOD A: Git Deployment (RECOMMENDED)

```bash
# SSH into your Plesk server
cd /var/www/vhosts/YOUR-DOMAIN.com/httpdocs  # Change to your actual path

# Fetch latest changes
git fetch origin

# Option 1: Use the feature branch directly
git checkout claude/fix-payment-tc-order-sync-011CUwb13s1eNcHVcHuwAurx
git pull origin claude/fix-payment-tc-order-sync-011CUwb13s1eNcHVcHuwAurx

# Option 2: Merge to main branch
git checkout main
git merge claude/fix-payment-tc-order-sync-011CUwb13s1eNcHVcHuwAurx
git push origin main

# Clear any PHP cache
php artisan cache:clear  # If using Laravel
# or
rm -rf /tmp/php_cache/*  # Generic PHP cache
```

### METHOD B: Manual File Upload (If no Git access)

Download these 2 files from your local repo and upload via Plesk File Manager or FTP:

**File 1**: `assets/JS/script.js`
**File 2**: `backend/api/payment_verification.php`

**Upload locations**:
```
/var/www/vhosts/YOUR-DOMAIN.com/httpdocs/assets/JS/script.js
/var/www/vhosts/YOUR-DOMAIN.com/httpdocs/backend/api/payment_verification.php
```

---

## PHASE 3: CODE CHANGES SUMMARY 📝

### Changed Files Overview

#### File 1: `assets/JS/script.js`
**Lines Modified**: 2754-2809

**Changes**:
1. ✅ Added `showApprovalConfirmModal()` function (lines 2755-2762)
2. ✅ Updated `approvePayment()` to reload orders after success (lines 2797-2800)
3. ✅ Removed duplicate event listeners (deleted old lines 2853-2871)

**Key Addition**:
```javascript
// Show approval confirmation modal with styled confirm dialog
function showApprovalConfirmModal(verificationId) {
    showConfirm({
        title: 'Approve Payment',
        message: 'Are you sure you want to approve this payment? This will update the order balance and payment status.',
        okText: 'Approve',
        onConfirm: () => approvePayment(verificationId)
    });
}

async function approvePayment(verificationId) {
    try {
        const response = await fetch('/backend/api/payment_verification.php?action=approve', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ id: verificationId })
        });

        const text = await response.text();
        if (!response.ok) {
            throw new Error(`Server returned ${response.status}: ${text || response.statusText}`);
        }

        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            throw new Error('Invalid JSON response from server: ' + text.slice(0, 300));
        }

        if (result.success) {
            showNotification(result.message || 'Payment approved successfully!', 'success');
            closeModal('paymentDetailsModal');

            // ✅ NEW: Reload payment verifications list
            loadPaymentVerifications();

            // ✅ NEW: Reload orders list to reflect updated balance and payment status
            if (typeof loadOrders === 'function') {
                loadOrders();
            }
        } else {
            showNotification(result.message || 'Failed to approve payment', 'error');
        }

    } catch (error) {
        console.error('Error approving payment:', error);
        showNotification('Failed to approve payment: ' + error.message, 'error');
    }
}
```

#### File 2: `backend/api/payment_verification.php`
**Lines Modified**: 349-357, 389-403

**Changes**:
1. ✅ Added T&C validation before approval (lines 349-357)
2. ✅ Return updated order data in response (lines 389-403)

**Key Additions**:
```php
// ✅ VALIDATE: Check if customer agreed to Terms & Conditions
$termsCheck = $conn->prepare("SELECT terms_agreed FROM orders WHERE id = :oid LIMIT 1");
$termsCheck->execute([':oid' => $orderId]);
$termsAgreed = (int)$termsCheck->fetchColumn();

if (!$termsAgreed) {
    $conn->rollBack();
    send_json(['success' => false, 'message' => 'Cannot approve payment. Customer has not accepted the Terms & Conditions.'], 400);
}

// ... existing approval logic ...

// ✅ RETURN: Get updated order data to refresh UI
$orderData = $conn->prepare("SELECT remaining_balance, payment_status FROM orders WHERE id = :oid LIMIT 1");
$orderData->execute([':oid' => $orderId]);
$orderInfo = $orderData->fetch(PDO::FETCH_ASSOC);

$conn->commit();
send_json([
    'success' => true,
    'message' => 'Payment approved successfully',
    'data' => [
        'order_id' => $orderId,
        'pv_id' => $pv_id,
        'remaining_balance' => (float)($orderInfo['remaining_balance'] ?? 0),
        'payment_status' => $orderInfo['payment_status'] ?? 'Pending'
    ]
]);
```

---

## PHASE 4: TESTING & VERIFICATION ✅

### Test 1: Payment Approval with T&C Agreed

**Steps**:
1. Login to Admin panel
2. Go to Payment Verification
3. Click on a payment with `terms_agreed = 1`
4. Verify modal shows: "Terms & Conditions: ✓ Agreed" (green badge)
5. Verify Approve button is **enabled**
6. Click Approve button
7. **Expected**: Styled confirmation modal appears (NOT browser confirm)
8. Click "Approve" in modal
9. **Expected**: Success notification appears
10. **Expected**: Payment Verification modal closes
11. **Expected**: Order Management table updates with new balance
12. Go to Order Management
13. **Expected**: Order row shows updated remaining_balance and payment_status

### Test 2: Payment Approval with T&C Not Agreed (Error case)

**Steps**:
1. Temporarily set one order to `terms_agreed = 0`:
   ```sql
   UPDATE orders SET terms_agreed = 0 WHERE id = 1;
   ```
2. Go to Payment Verification for that order
3. **Expected**: Modal shows "Terms & Conditions: ✗ Not Agreed" (red badge)
4. **Expected**: Approve button is **disabled** with tooltip
5. Try to approve (should be blocked)
6. Reset the order:
   ```sql
   UPDATE orders SET terms_agreed = 1 WHERE id = 1;
   ```

### Test 3: Order Status Update Modal

**Steps**:
1. Go to Order Management
2. Click "Update Status" on any order
3. **Expected**: Modal opens with current payment status and remaining balance
4. **Expected**: If balance > 0, "Completed" option is disabled
5. **Expected**: Shows warning: "You cannot set this order to Completed until payment is fully settled"

### Test 4: Real-time UI Update

**Steps**:
1. Open Order Management in one browser tab
2. Open Payment Verification in another tab
3. Approve a payment in the Payment Verification tab
4. Switch back to Order Management tab
5. **Expected**: Table automatically updates with new balance (no refresh needed)

---

## PHASE 5: TROUBLESHOOTING 🔧

### Issue 1: "Cannot approve payment. Customer has not accepted the Terms & Conditions."

**Solution**:
```sql
-- Set the specific order to terms_agreed = 1
UPDATE orders SET terms_agreed = 1 WHERE id = [ORDER_ID];

-- Or set ALL orders:
UPDATE orders SET terms_agreed = 1 WHERE terms_agreed = 0;
```

### Issue 2: Approve button still disabled even with T&C agreed

**Check**:
1. Browser console for JS errors (F12 → Console)
2. Verify `hasAgreedTerms` in console:
   ```javascript
   // In Payment Details modal, check:
   console.log(payment.agreed_terms, payment.terms_agreed, payment.order_terms_agreed);
   ```
3. Clear browser cache: Ctrl+Shift+R (hard refresh)

### Issue 3: Order Management not updating after approval

**Check**:
1. Browser console for errors
2. Verify `loadOrders()` function exists:
   ```javascript
   console.log(typeof loadOrders); // Should be 'function'
   ```
3. Check network tab for `/backend/api/admin_orders.php` request

### Issue 4: Missing remaining_balance column error

**Solution**:
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

### Issue 5: payment_status shows 'Partially Paid' instead of 'With Balance'

**Solution**:
```sql
UPDATE orders
SET payment_status = 'With Balance'
WHERE payment_status = 'Partially Paid';
```

---

## PHASE 6: ROLLBACK PLAN (If something breaks) 🔄

### Rollback Database Changes

```sql
-- Remove remaining_balance column
ALTER TABLE orders DROP COLUMN remaining_balance;

-- Revert payment_status ENUM
ALTER TABLE orders
MODIFY payment_status ENUM('Pending','Partially Paid','Fully Paid')
DEFAULT 'Pending';

-- Revert terms_agreed (if needed)
UPDATE orders SET terms_agreed = 0 WHERE id IN (1,2,3,...);  -- List affected IDs
```

### Rollback Code Changes

```bash
# Via Git
git checkout main  # or your previous branch
git reset --hard HEAD~1  # Undo last commit

# Via File Manager
# Restore old versions of:
# - assets/JS/script.js
# - backend/api/payment_verification.php
```

---

## CHECKLIST ✓

Before deployment:
- [ ] Backup database (export via phpMyAdmin)
- [ ] Backup code files (download script.js and payment_verification.php)
- [ ] Test on staging/dev environment first (if available)

During deployment:
- [ ] Run all 5 database migrations in order
- [ ] Verify migration results with SELECT query
- [ ] Upload/deploy updated code files
- [ ] Clear PHP cache (if any)
- [ ] Clear browser cache

After deployment:
- [ ] Test payment approval with T&C agreed
- [ ] Test payment approval UI flow (styled modal)
- [ ] Test Order Management real-time update
- [ ] Test Update Order Status modal
- [ ] Check error logs: `backend/logs/payment_verification_errors.log`

---

## SUPPORT & CONTACTS 📞

If issues persist:
1. Check `backend/logs/payment_verification_errors.log`
2. Check browser console (F12) for JS errors
3. Check network tab for API errors
4. Verify database credentials in `backend/config/database.php`

---

## COMMIT REFERENCE

**Branch**: `claude/fix-payment-tc-order-sync-011CUwb13s1eNcHVcHuwAurx`
**Commit**: `406e6b0`
**Commit Message**: "fix(payment): validate T&C before approval, use styled modal confirm, sync order UI after approve"

**Files Changed**:
- `assets/JS/script.js` (43 lines changed)
- `backend/api/payment_verification.php` (24 lines changed)

---

## COMPLETION TIME ESTIMATE

- Database migrations: 5 minutes
- Code deployment: 5-10 minutes
- Testing: 10-15 minutes
- **Total**: ~30 minutes

Good luck! 🚀
