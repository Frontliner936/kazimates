<?php
require __DIR__ . '/includes/bootstrap.php';
need_login();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
echo json_encode([
    'unread_messages' => unread_total($pdo),
    'pending_requests' => pending_total($pdo),
], JSON_THROW_ON_ERROR);
