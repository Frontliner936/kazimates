<?php
// Serves a member's profile picture, or a generated initials placeholder when they have none
// (or when the viewer is not allowed to see it, or the picture table is not installed yet).
require __DIR__ . '/includes/bootstrap.php';
$me = uid();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
if (!$me || !$id) { http_response_code(403); exit; }
session_write_close();

$name = '';
$picture = false;
try {
    $s = $pdo->prepare('SELECT u.full_name,p.is_visible FROM users u LEFT JOIN profiles p ON p.user_id=u.id WHERE u.id=? AND u.is_active=1');
    $s->execute([$id]);
    $user = $s->fetch();
    if ($user) {
        $name = (string) $user['full_name'];
        $allowed = $id === $me || (int) ($user['is_visible'] ?? 0) === 1 || current_user_is_admin();
        if (!$allowed) {
            $s = $pdo->prepare('SELECT 1 FROM connection_requests WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) LIMIT 1');
            $s->execute([$me, $id, $id, $me]);
            $allowed = (bool) $s->fetchColumn();
        }
        if ($allowed) {
            $s = $pdo->prepare('SELECT mime_type,file_size,image_data,UNIX_TIMESTAMP(updated_at) AS ts FROM profile_pictures WHERE user_id=?');
            $s->execute([$id]);
            $picture = $s->fetch();
        }
    }
} catch (PDOException $exception) {
    if ($exception->getCode() !== '42S02') error_log('KaziMates avatar error [' . $exception->getCode() . ']: ' . $exception->getMessage());
}

header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
header('Cache-Control: private, max-age=300');
if ($picture && in_array($picture['mime_type'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
    $etag = '"' . $id . '-' . (int) $picture['ts'] . '"';
    header('ETag: ' . $etag);
    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); exit; }
    header('Content-Type: ' . $picture['mime_type']);
    header('Content-Length: ' . (int) $picture['file_size']);
    echo $picture['image_data'];
    exit;
}

// Initials placeholder
$words = preg_split('/\s+/u', trim($name)) ?: [];
$initials = '';
foreach (array_slice(array_filter($words), 0, 2) as $word) $initials .= mb_strtoupper(mb_substr($word, 0, 1));
if ($initials === '') $initials = '?';
$palette = ['#0f2347', '#c2410c', '#1b3a6b', '#b45309', '#0f766e', '#1e40af', '#9a3412'];
$color = $palette[$id % count($palette)];
header('Content-Type: image/svg+xml; charset=utf-8');
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="' . $color . '"/>'
    . '<text x="50" y="50" dy=".35em" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-size="40" font-weight="700" fill="#fff">' . htmlspecialchars($initials, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</text></svg>';
