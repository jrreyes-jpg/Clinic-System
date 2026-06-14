<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
header('Content-Type: application/json');

$current = currentUser();
$userId = (int) ($current['id'] ?? 0);

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$limit = (int) ($_GET['limit'] ?? 20);
$limit = max(1, min(100, $limit));

$logs = getRecentAuditLogs($limit);

echo json_encode(['ok' => true, 'logs' => $logs]);
