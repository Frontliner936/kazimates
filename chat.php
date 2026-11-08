<?php
require __DIR__ . '/includes/bootstrap.php';
need_login();
$me = uid();
$other = filter_input(INPUT_GET, 'with', FILTER_VALIDATE_INT) ?: 0;
if (!$other || $other === $me || !connected($pdo, $me, $other)) {
    note('Messaging is available only with an accepted connection.');
    header('Location: requests.php'); exit;
}
$statement = $pdo->prepare('SELECT full_name,is_active FROM users WHERE id=?');
try {
    $statement->execute([$other]);
} catch (PDOException $exception) {
    if ($exception->getCode() !== '42S22') throw $exception;
    $statement = $pdo->prepare('SELECT full_name FROM users WHERE id=?');
    $statement->execute([$other]);
}
$person = $statement->fetch();
if (!$person || (int) ($person['is_active'] ?? 1) !== 1) { note('That member is no longer available.'); header('Location: requests.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_token();
    $body = trim($_POST['body'] ?? '');
    $upload = $_FILES['attachment'] ?? null;
    $hasFile = $upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $error = '';

    if ($body === '' && !$hasFile) {
        $error = 'Write a message or choose a file to attach.';
    } elseif (mb_strlen($body) > 4000) {
        $error = 'Messages can contain up to 4,000 characters.';
    } elseif (!connected($pdo, $me, $other)) {
        $error = 'Messaging is available only with an accepted connection.';
    }

    $originalName = '';
    $mimeType = '';
    $fileSize = 0;
    $fileContents = null;
    if ($error === '' && $hasFile) {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'] ?? '')) {
            $error = 'The file could not be uploaded. Please try again.';
        } elseif (($upload['size'] ?? 0) < 1 || ($upload['size'] ?? 0) > 5 * 1024 * 1024) {
            $error = 'Attachments must be smaller than 5 MB.';
        } else {
            $originalName = basename(str_replace('\\', '/', (string) ($upload['name'] ?? 'attachment')));
            $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?: 'attachment';
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $suffix = $extension !== '' ? '.' . $extension : '';
            $stem = pathinfo($originalName, PATHINFO_FILENAME);
            $originalName = mb_strcut($stem, 0, max(1, 255 - strlen($suffix)), 'UTF-8') . $suffix;
            $allowed = [
                'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf',
                'txt' => 'text/plain',
            ];
            $detectedMime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
            if (!isset($allowed[$extension]) || $detectedMime !== $allowed[$extension]) {
                $error = 'Use a JPG, PNG, GIF, WebP, PDF, or TXT file.';
            } else {
                $fileContents = file_get_contents($upload['tmp_name']);
                if ($fileContents === false) {
                    $error = 'The file could not be read. Please try again.';
                } else {
                    $mimeType = $detectedMime;
                    $fileSize = (int) $upload['size'];
                }
            }
        }
    }

    if ($error !== '') {
        note($error);
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO messages(sender_id,receiver_id,body) VALUES(?,?,?)')->execute([$me, $other, $body]);
            if ($hasFile) {
                $messageId = (int) $pdo->lastInsertId();
                $attachment = $pdo->prepare('INSERT INTO message_attachments(message_id,original_name,mime_type,file_size,file_contents) VALUES(?,?,?,?,?)');
                $attachment->bindValue(1, $messageId, PDO::PARAM_INT);
                $attachment->bindValue(2, $originalName, PDO::PARAM_STR);
                $attachment->bindValue(3, $mimeType, PDO::PARAM_STR);
                $attachment->bindValue(4, $fileSize, PDO::PARAM_INT);
                $attachment->bindValue(5, $fileContents, PDO::PARAM_LOB);
                $attachment->execute();
            }
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('KaziMates attachment/message database error [' . $exception->getCode() . ']: ' . $exception->getMessage());
            note('The message could not be saved. Please check that the attachment database update has been applied.');
        }
    }
    header('Location: chat.php?with=' . $other); exit;
}

$pdo->prepare('UPDATE messages SET read_at=NOW() WHERE sender_id=? AND receiver_id=? AND read_at IS NULL')->execute([$other, $me]);
$attachmentsEnabled = true;
try {
    $statement = $pdo->prepare('SELECT m.id,m.sender_id,m.body,m.created_at,a.id AS attachment_id,a.original_name,a.file_size FROM messages m LEFT JOIN message_attachments a ON a.message_id=m.id WHERE (m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?) ORDER BY m.created_at DESC,m.id DESC LIMIT 300');
    $statement->execute([$me, $other, $other, $me]);
    $messages = array_reverse($statement->fetchAll());
} catch (PDOException $exception) {
    if ($exception->getCode() !== '42S02') throw $exception;
    $attachmentsEnabled = false;
    $statement = $pdo->prepare('SELECT id,sender_id,body,created_at FROM messages WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY created_at DESC,id DESC LIMIT 300');
    $statement->execute([$me, $other, $other, $me]);
    $messages = array_reverse($statement->fetchAll());
    note('File attachments are not enabled yet. Run migration_attachments.sql on the KaziMates database to turn them on.');
}
head('Messages'); ?>
<div class="card"><div class="person"><?= avatar((int) $other, 'avatar-lg') ?><h1>Chat with <?= e($person['full_name']) ?></h1></div><div class="chat">
<?php foreach ($messages as $message): ?><div class="bubble <?= (int) $message['sender_id'] === $me ? 'mine' : '' ?>">
<?php if ($message['body'] !== ''): ?><?= nl2br(e($message['body'])) ?><?php endif; ?>
<?php if ($message['attachment_id']): ?><a class="message-attachment" href="download_attachment.php?id=<?= (int) $message['attachment_id'] ?>"><span aria-hidden="true">↧</span><?= e($message['original_name']) ?><small><?= number_format(((int) $message['file_size']) / 1024, 0) ?> KB · Download</small></a><?php endif; ?>
<small><?= e(date('d M H:i', strtotime($message['created_at']))) ?></small></div><?php endforeach; ?>
<?php if (!$messages): ?><p>No messages yet.</p><?php endif; ?></div>
<form method="post" <?= $attachmentsEnabled ? 'enctype="multipart/form-data"' : '' ?> class="chat-compose"><input type="hidden" name="csrf" value="<?= e(token()) ?>"><?php if ($attachmentsEnabled): ?><label for="attachment">Attach file <span>(max 5 MB)</span></label><input id="attachment" name="attachment" type="file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,image/jpeg,image/png,image/gif,image/webp,application/pdf,text/plain"><?php endif; ?><textarea name="body" maxlength="4000" placeholder="Write a message..."></textarea><button>Send</button></form></div>
<script>var c=document.querySelector('.chat');if(c)c.scrollTop=c.scrollHeight;</script><?php foot(); ?>
