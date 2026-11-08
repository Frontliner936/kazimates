<?php
require __DIR__ . '/includes/bootstrap.php';
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$valid = (bool) preg_match('/^[a-f0-9]{64}$/', $token);
$reset = false;
if ($valid) {
    try {
        $s = $pdo->prepare('SELECT r.id,r.user_id,u.email FROM password_resets r JOIN users u ON u.id=r.user_id WHERE r.token_hash=? AND r.used_at IS NULL AND r.expires_at > NOW() AND u.is_active=1 LIMIT 1');
        $s->execute([hash('sha256', $token)]);
        $reset = $s->fetch();
    } catch (PDOException $exception) {
        error_log('KaziMates reset lookup error [' . $exception->getCode() . ']: ' . $exception->getMessage() . ' (run migration_reset_avatar.sql)');
    }
}
$error = '';
if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_token();
    $password = (string) ($_POST['password'] ?? '');
    if (strlen($password) <= 6) {
        $error = 'Use a password of more than 6 characters.';
    } elseif (!hash_equals($password, (string) ($_POST['confirm'] ?? ''))) {
        $error = 'The two passwords do not match.';
    } else {
        try {
            $pdo->beginTransaction();
            $claim = $pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=? AND used_at IS NULL AND expires_at > NOW()');
            $claim->execute([(int) $reset['id']]);
            if ($claim->rowCount() !== 1) { $pdo->rollBack(); $reset = false; }
            else {
                $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), (int) $reset['user_id']]);
                $pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([(int) $reset['user_id']]);
                $pdo->prepare('DELETE FROM login_attempts WHERE email=?')->execute([strtolower((string) $reset['email'])]);
                $pdo->commit();
                $_SESSION = [];
                session_regenerate_id(true);
                note('Password updated. Please log in with your new password.');
                header('Location: login.php'); exit;
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('KaziMates password reset error [' . $exception->getCode() . ']: ' . $exception->getMessage());
            $error = 'Could not update your password. Please try again.';
        }
    }
}
head('Reset password'); ?>
<section class="auth-shell"><div class="auth-story"><a class="back-home" href="login.php">← Back to log in</a><span class="eyebrow">ALMOST THERE</span><h1>Choose a new<br><em>password.</em></h1><p>Pick something you haven't used elsewhere. You'll be asked to log in again afterwards.</p><img src="assets/community-illustration.svg" alt="" class="auth-illustration"><div class="auth-caption">A fresh start, in one step.</div></div>
<div class="auth-panel"><div class="auth-panel-head"><span class="eyebrow">SET A NEW PASSWORD</span><h2>Reset your password.</h2></div>
<?php if (!$reset): ?><p class="error">This reset link is invalid or has expired. Reset links work once and last 60 minutes.</p><p class="auth-switch"><a href="forgot_password.php">Request a new link</a></p>
<?php else: ?><?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="auth-form"><input type="hidden" name="csrf" value="<?= e(token()) ?>"><input type="hidden" name="token" value="<?= e($token) ?>"><label>New password <small>(more than 6 characters)</small><input type="password" name="password" autocomplete="new-password" minlength="7" required></label><label>Confirm new password<input type="password" name="confirm" autocomplete="new-password" minlength="7" required></label><button class="button button-large">Update password <span>→</span></button></form><?php endif; ?></div></section><?php foot(); ?>
