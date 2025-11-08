<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $sql = "CREATE TABLE IF NOT EXISTS customer_carts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        cart_data JSON NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_customer_cart (customer_id),
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conn->exec($sql);

    echo "✓ customer_carts table created successfully\n";
} catch (PDOException $e) {
    echo "✗ Error creating table: " . $e->getMessage() . "\n";
    exit(1);
}
