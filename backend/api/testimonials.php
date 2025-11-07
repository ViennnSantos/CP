<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

try {
  $db = Database::getInstance()->getConnection();
  $stmt = $db->query(
    'SELECT f.rating, f.comment, f.created_at,
            COALESCE(c.full_name, CONCAT("Customer #", f.customer_id)) AS customer_name
     FROM feedback f
     JOIN customers c ON c.id = f.customer_id
     WHERE f.status="released"
     ORDER BY f.created_at DESC
     LIMIT 50'
  );
  echo json_encode(['success'=>true,'testimonials'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Failed to load testimonials.']);
}
