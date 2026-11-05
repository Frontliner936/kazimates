<?php
require __DIR__ . '/includes/bootstrap.php';
need_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: discover.php'); exit; }
check_token();
$me = uid();
$other = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT) ?: 0;
if (!$other || $other === $me) { note('Invalid member.'); header('Location: discover.php'); exit; }
$statement = $pdo->prepare('SELECT 1 FROM profiles WHERE user_id=? AND is_visible=1');
$statement->execute([$other]);
if (!$statement->fetchColumn()) { note('That member is not available.'); header('Location: discover.php'); exit; }
if (connected($pdo, $me, $other)) { note('You are already connected.'); header('Location: discover.php'); exit; }
$statement = $pdo->prepare('SELECT id,sender_id,receiver_id,status,created_at FROM connection_requests WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY id DESC LIMIT 1');
$statement->execute([$me, $other, $other, $me]);
$request = $statement->fetch();
try {
    if ($request) {
        if ($request['status'] === 'pending') {
            note('A connection request is already waiting for a response.');
        } elseif ($request['status'] === 'accepted') {
            note('You are already connected.');
        } elseif ((int) $request['sender_id'] !== $me) {
            note('This member declined the request. They can contact you if they want to reconnect.');
        } elseif (resend_blocked($request, $me)) {
            note('You can try again after ' . RESEND_DAYS . ' days from the decline.');
        } else {
            $statement = $pdo->prepare("UPDATE connection_requests SET status='pending',created_at=NOW() WHERE id=? AND sender_id=? AND status='rejected'");
            $statement->execute([$request['id'], $me]);
            note($statement->rowCount() === 1 ? 'Request sent again.' : 'Could not resend the request. Please try again.');
        }
    } else {
        $pdo->prepare('INSERT INTO connection_requests(sender_id,receiver_id) VALUES(?,?)')->execute([$me, $other]);
        note('Connection request sent.');
    }
} catch (PDOException $exception) {
    note('A connection request already exists or could not be sent. Please refresh and try again.');
}
header('Location: discover.php'); exit;
