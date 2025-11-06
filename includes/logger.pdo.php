<?php
// includes/logger.pdo.php

function log_action_pdo(PDO $pdo, string $user_type, int $user_id, string $action, ?string $details = null): bool
{
    $sql = "INSERT INTO `user_logs` (`user_type`, `user_id`, `action`, `details`) VALUES (:user_type, :user_id, :action, :details)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':user_type' => $user_type,
        ':user_id'   => $user_id,
        ':action'    => $action,
        ':details'   => $details
    ]);
}
