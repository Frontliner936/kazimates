<?php
require __DIR__ . '/includes/bootstrap.php';
need_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: discover.php'); exit; }
check_token();
$me = uid();
$target = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT) ?: 0;
$reason = trim($_POST['reason'] ?? '');
$details = trim($_POST['details'] ?? '');
$allowedReasons = ['Spam or scam', 'Harassment or bullying', 'Inappropriate content', 'Impersonation', 'Other'];
if (!$target || $target === $me || !in_array($reason, $allowedReasons, true) || mb_strlen($details) > 1000) {
    note('Please choose a valid report reason and keep details under 1,000 characters.');
    header('Location: discover.php'); exit;
}
$statement = $pdo->prepare('SELECT id FROM users WHERE id=? AND is_active=1');
try {
    $statement->execute([$target]);
    if (!$statement->fetchColumn()) {
        note('That member is no longer available.');
    } else {
        $statement = $pdo->prepare("SELECT id FROM reports WHERE reporter_id=? AND reported_user_id=? AND status='open' LIMIT 1");
        $statement->execute([$me, $target]);
        if ($statement->fetchColumn()) {
            note('You already have an open report for this member.');
        } else {
            $statement = $pdo->prepare('INSERT INTO reports(reporter_id,reported_user_id,reason,details) VALUES(?,?,?,?)');
            $statement->execute([$me, $target, $reason, $details !== '' ? $details : null]);
            note('Report sent to the KaziMates administrators for review.');
        }
    }
} catch (PDOException $exception) {
    if (!in_array($exception->getCode(), ['42S02', '42S22'], true)) throw $exception;
    note('Reports are not enabled yet. An administrator must apply migration_admin.sql first.');
}
header('Location: discover.php'); exit;
