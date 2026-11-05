<?php
require __DIR__ . '/includes/bootstrap.php';
need_login();
$me = uid();
$other = filter_input(INPUT_GET, 'with', FILTER_VALIDATE_INT) ?: 0;
if (!$other || $other === $me || !connected($pdo, $me, $other)) {
    note('Messaging is available only with an accepted connection.');
    header('Location: requests.php'); exit;
}
$statement = $pdo->prepare('SELECT full_name FROM users WHERE id=?');
$statement->execute([$other]);
$person = $statement->fetch();
if (!$person) { header('Location: requests.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_token();
    $body = trim($_POST['body'] ?? '');
    if ($body === '' || mb_strlen($body) > 4000) {
        note('Messages must contain between 1 and 4,000 characters.');
    } elseif (!connected($pdo, $me, $other)) {
        note('Messaging is available only with an accepted connection.');
    } else {
        $pdo->prepare('INSERT INTO messages(sender_id,receiver_id,body) VALUES(?,?,?)')->execute([$me, $other, $body]);
    }
    header('Location: chat.php?with=' . $other); exit;
}
$pdo->prepare('UPDATE messages SET read_at=NOW() WHERE sender_id=? AND receiver_id=? AND read_at IS NULL')->execute([$other, $me]);
$statement = $pdo->prepare('SELECT sender_id,body,created_at FROM messages WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY created_at DESC,id DESC LIMIT 300');
$statement->execute([$me, $other, $other, $me]);
$messages = array_reverse($statement->fetchAll());
head('Messages'); ?>
<div class="card"><h1>Chat with <?= e($person['full_name']) ?></h1><div class="chat">
<?php foreach ($messages as $message): ?><div class="bubble <?= (int) $message['sender_id'] === $me ? 'mine' : '' ?>"><?= nl2br(e($message['body'])) ?><small><?= e(date('d M H:i', strtotime($message['created_at']))) ?></small></div><?php endforeach; ?>
<?php if (!$messages): ?><p>Say hello and start the conversation.</p><?php endif; ?></div>
<form method="post"><input type="hidden" name="csrf" value="<?= e(token()) ?>"><textarea name="body" maxlength="4000" required placeholder="Write a message..."></textarea><button>Send message</button></form></div>
<script>var c=document.querySelector('.chat');if(c)c.scrollTop=c.scrollHeight;</script><?php foot(); ?>
