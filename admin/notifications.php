<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
header('Content-Type: application/json');

$current = currentUser();
$userId = (int) ($current['id'] ?? 0);

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $notifications = getNotificationsForUser($userId, 50);
        $unread = countUnreadNotifications($userId);
        echo json_encode(['ok' => true, 'notifications' => $notifications, 'unread' => $unread]);
        exit;
    }

    if ($method === 'POST') {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'mark_read') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                markNotificationRead($id);
                echo json_encode(['ok' => true]);
                exit;
            }
        }

        if ($action === 'mark_all_read') {
            // mark all visible notifications as read
            $pdo = getDatabaseConnection();
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE (user_id = :user_id OR user_id IS NULL)');
            $stmt->execute(['user_id' => $userId]);
            echo json_encode(['ok' => true]);
            exit;
        }
    }
} catch (PDOException $exception) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => databaseUnavailableMessage(), 'notifications' => [], 'unread' => 0]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'message' => 'Invalid request.']);
