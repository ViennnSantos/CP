<?php

declare(strict_types=1);

function normalize_ph_phone(string $raw): string
{
    // keep digits only
    $digits = preg_replace('/\D+/', '', $raw ?? '');

    // Accept: 09XXXXXXXXX, 9XXXXXXXXX, 639XXXXXXXXX
    if (preg_match('/^09\d{9}$/', $digits)) {
        $digits = substr($digits, 1);        // drop 0 -> 9XXXXXXXXX
    } elseif (preg_match('/^63\d{10}$/', $digits)) {
        $digits = substr($digits, 2);        // drop 63 -> 9XXXXXXXXX
    }

    // Final check: exactly 10 digits starting with 9
    if (strlen($digits) !== 10 || $digits[0] !== '9') {
        throw new RuntimeException('Invalid PH mobile format');
    }

    // Save as E.164 +63xxxxxxxxxx (length 13)
    return '+63' . $digits;
}

function phone_exists(PDO $pdo, string $phone, ?int $excludeId = null): bool
{
    if ($excludeId) {
        $stmt = $pdo->prepare('SELECT 1 FROM customers WHERE phone = ? AND id <> ? LIMIT 1');
        $stmt->execute([$phone, $excludeId]);
    } else {
        $stmt = $pdo->prepare('SELECT 1 FROM customers WHERE phone = ? LIMIT 1');
        $stmt->execute([$phone]);
    }
    return (bool)$stmt->fetchColumn();
}
