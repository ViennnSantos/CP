#!/bin/bash
echo "=== GIT STATUS ==="
git status -sb

echo -e "\n=== FILES IN assets/JS/ ==="
ls -la assets/JS/address_mana*.js 2>&1

echo -e "\n=== LINE 143 of profile.php ==="
sed -n '143p' customer/profile.php

echo -e "\n=== LINE 154 of profile.php ==="
sed -n '154p' customer/profile.php

echo -e "\n=== LINE 1470 of profile.php ==="
sed -n '1470p' customer/profile.php

echo -e "\n=== VERIFY showAddressForm in JS ==="
grep -c "window.showAddressForm = showAddressForm" assets/JS/address_management.js 2>&1 || echo "FILE NOT FOUND!"

echo -e "\n=== LATEST COMMITS ==="
git log --oneline -3
