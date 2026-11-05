<?php
require __DIR__ . '/includes/bootstrap.php';
need_login();
$attachmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
if (!$attachmentId) { http_response_code(404); exit('Attachment not found.'); }
$statement = $pdo->prepare('SELECT a.original_name,a.mime_type,a.file_size,a.file_contents,m.sender_id,m.receiver_id FROM message_attachments a JOIN messages m ON m.id=a.message_id WHERE a.id=? LIMIT 1');
$statement->execute([$attachmentId]);
$attachment = $statement->fetch();
$me = uid();
if (!$attachment) { http_response_code(404); exit('Attachment not found.'); }
$senderId = (int) $attachment['sender_id'];
$receiverId = (int) $attachment['receiver_id'];
$otherId = $senderId === $me ? $receiverId : $senderId;
if (($senderId !== $me && $receiverId !== $me) || !connected($pdo, $me, $otherId)) {
    http_response_code(404); exit('Attachment not found.');
}
$name = basename(str_replace('\\', '/', (string) $attachment['original_name']));
$name = preg_replace('/[\r\n\x00-\x1F\x7F]/', '', $name) ?: 'attachment';
header('Content-Type: application/octet-stream');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="attachment"; filename*=UTF-8\'\'' . rawurlencode($name));
header('Content-Length: ' . (int) $attachment['file_size']);
header('Cache-Control: private, no-store, max-age=0');
echo $attachment['file_contents'];
