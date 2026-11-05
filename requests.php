<?php
require __DIR__ . '/includes/bootstrap.php';
need_login();
$me = uid();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_token();
    $requestId = filter_input(INPUT_POST, 'rid', FILTER_VALIDATE_INT) ?: 0;
    $action = $_POST['act'] ?? '';
    if (!$requestId || !in_array($action, ['accept', 'decline'], true)) {
        note('Invalid connection request action.');
        header('Location: requests.php'); exit;
    }
    $status = $action === 'accept' ? 'accepted' : 'rejected';
    $statement = $pdo->prepare("UPDATE connection_requests SET status=?,created_at=NOW() WHERE id=? AND receiver_id=? AND status='pending'");
    $statement->execute([$status, $requestId, $me]);
    if ($statement->rowCount() === 1) {
        note($status === 'accepted' ? 'Connection accepted. You can now message this member.' : 'Request declined.');
    } else {
        note('That request is no longer available. Refresh the page and try again.');
    }
    header('Location: requests.php'); exit;
}
$statement = $pdo->prepare("SELECT r.id,u.full_name,p.region,p.career_interest FROM connection_requests r JOIN users u ON u.id=r.sender_id JOIN profiles p ON p.user_id=u.id WHERE r.receiver_id=? AND r.status='pending'");
$statement->execute([$me]);
$incoming = $statement->fetchAll();
$statement = $pdo->prepare("SELECT u.id,u.full_name,p.region,p.career_interest,(SELECT COUNT(*) FROM messages m WHERE m.sender_id=u.id AND m.receiver_id=? AND m.read_at IS NULL) AS unread FROM connection_requests r JOIN users u ON u.id=IF(r.sender_id=?,r.receiver_id,r.sender_id) JOIN profiles p ON p.user_id=u.id WHERE r.status='accepted' AND (r.sender_id=? OR r.receiver_id=?)");
$statement->execute([$me, $me, $me, $me]);
$connections = $statement->fetchAll();
head('Connections'); ?>
<h1>Connection requests</h1><div class="card"><h2>Received requests (<?= count($incoming) ?>)</h2>
<?php foreach ($incoming as $request): ?><div class="request"><div><b><?= e($request['full_name']) ?></b><small><?= e($request['career_interest']) ?> · <?= e($request['region']) ?></small></div>
<form method="post"><input type="hidden" name="csrf" value="<?= e(token()) ?>"><input type="hidden" name="rid" value="<?= (int) $request['id'] ?>"><button name="act" value="accept">Accept</button><button class="secondary" name="act" value="decline">Decline</button></form></div><?php endforeach; ?>
<?php if (!$incoming): ?><p>No incoming requests.</p><?php endif; ?></div><div class="card"><h2>Your connections</h2>
<?php foreach ($connections as $connection): ?><div class="request"><div><b><?= e($connection['full_name']) ?></b><small><?= e($connection['career_interest']) ?> · <?= e($connection['region']) ?></small></div><a class="button" href="chat.php?with=<?= (int) $connection['id'] ?>">Message<?= badge((int) $connection['unread']) ?></a></div><?php endforeach; ?>
<?php if (!$connections): ?><p>Your accepted connections will appear here.</p><?php endif; ?></div><?php foot(); ?>
