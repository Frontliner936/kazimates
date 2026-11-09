<?php
require __DIR__ . '/includes/bootstrap.php';
need_login();
$me = uid();
$statement = $pdo->prepare("SELECT u.id,u.full_name,p.region,p.career_interest,(SELECT COUNT(*) FROM messages m WHERE m.sender_id=u.id AND m.receiver_id=? AND m.read_at IS NULL) AS unread FROM connection_requests r JOIN users u ON u.id=IF(r.sender_id=?,r.receiver_id,r.sender_id) JOIN profiles p ON p.user_id=u.id WHERE r.status='accepted' AND (r.sender_id=? OR r.receiver_id=?) AND u.is_active=1 ORDER BY unread DESC,u.full_name");
$statement->execute([$me, $me, $me, $me]);
$conversations = $statement->fetchAll();
head('Messages'); ?>
<section class="message-inbox">
  <div class="message-inbox-heading"><div><span class="eyebrow">YOUR CONVERSATIONS</span><h1>Messages</h1><p>Unread messages: <strong><?= number_format(unread_total($pdo)) ?></strong></p></div></div>
  <div class="card message-inbox-list">
    <?php if (!$conversations): ?><p>You don’t have any connections to message yet. Find members and connect to start a conversation.</p><a class="button" href="discover.php">Find Mates</a><?php endif; ?>
    <?php foreach ($conversations as $conversation): ?>
      <article class="request message-conversation">
        <div class="person"><?= avatar((int) $conversation['id']) ?><div><b><?= e($conversation['full_name']) ?></b><small><?= e($conversation['career_interest']) ?> · <?= e($conversation['region']) ?></small></div></div>
        <a class="button" href="chat.php?with=<?= (int) $conversation['id'] ?>">Open chat<?= badge((int) $conversation['unread']) ?></a>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php foot();
