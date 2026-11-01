<?php
// notification_helper.php
// Uses the existing notifications table. No database schema changes required.

function createNotification(PDO $pdo, int $userId, int $foundItemId, string $message): bool
{
    if ($userId <= 0 || $foundItemId <= 0 || trim($message) === '') {
        return false;
    }

    $stmt = $pdo->prepare("\n        INSERT INTO notifications (user_id, found_item_id, message, is_read)\n        VALUES (?, ?, ?, 'No')\n    ");

    return $stmt->execute([$userId, $foundItemId, trim($message)]);
}

function createRoleNotifications(PDO $pdo, string $role, int $foundItemId, string $message): int
{
    if ($foundItemId <= 0 || trim($message) === '') {
        return 0;
    }

    $users = $pdo->prepare("SELECT user_id FROM users WHERE role = ?");
    $users->execute([$role]);
    $rows = $users->fetchAll(PDO::FETCH_COLUMN);

    if (!$rows) {
        return 0;
    }

    $insert = $pdo->prepare("\n        INSERT INTO notifications (user_id, found_item_id, message, is_read)\n        VALUES (?, ?, ?, 'No')\n    ");

    $count = 0;
    foreach ($rows as $userId) {
        if ($insert->execute([(int)$userId, $foundItemId, trim($message)])) {
            $count++;
        }
    }

    return $count;
}
