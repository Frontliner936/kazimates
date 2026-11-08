<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/mailer.php';
if (uid()) { header('Location: ' . (current_user_is_admin() ? 'admin.php' : 'dashboard.php')); exit; }
const RESET_TTL_MINUTES = 60;
const RESET_MAX_PER_USER_HOUR = 3;
const RESET_MAX_PER_IP_HOUR = 10;
$error = '';
$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_token();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter the email address you registered with.';
    } elseif (!mail_configured()) {
        error_log('KaziMates password reset requested but email sending is not configured.');
        $error = 'Password reset email is not set up on this site yet. Please contact the site administrator.';
    } else {
        // The same message is shown whether or not the email exists, so accounts cannot be discovered here.
        $sent = true;
        try {
            $pdo->exec('DELETE FROM password_resets WHERE created_at < NOW() - INTERVAL 1 DAY');
            $s = $pdo->prepare('SELECT COUNT(*) FROM password_resets WHERE ip=? AND created_at > NOW() - INTERVAL 1 HOUR');
            $s->execute([$ip]);
            $ipCount = (int) $s->fetchColumn();
            $s = $pdo->prepare('SELECT id,full_name FROM users WHERE email=? AND is_active=1');
            $s->execute([$email]);
            $user = $s->fetch();
            if ($user && $ipCount < RESET_MAX_PER_IP_HOUR) {
                $s = $pdo->prepare('SELECT COUNT(*),COALESCE(SUM(created_at > NOW() - INTERVAL 1 MINUTE),0) FROM password_resets WHERE user_id=? AND created_at > NOW() - INTERVAL 1 HOUR');
                $s->execute([(int) $user['id']]);
                [$hourCount, $minuteCount] = array_map('intval', $s->fetch(PDO::FETCH_NUM));
                if ($hourCount < RESET_MAX_PER_USER_HOUR && $minuteCount === 0) {
                    $token = bin2hex(random_bytes(32));
                    $pdo->prepare('INSERT INTO password_resets(user_id,token_hash,ip,expires_at) VALUES(?,?,?,NOW() + INTERVAL ' . RESET_TTL_MINUTES . ' MINUTE)')
                        ->execute([(int) $user['id'], hash('sha256', $token), $ip]);
                    $link = app_url() . '/reset_password.php?token=' . $token;
                    $name = (string) $user['full_name'];
                    $text = "Hello $name,\n\nWe received a request to reset your KaziMates password. Open this link to choose a new one (valid for " . RESET_TTL_MINUTES . " minutes):\n\n$link\n\nIf you did not ask for this, you can ignore this email; your password will not change.\n\n— KaziMates";
                    $html = '<p>Hello ' . e($name) . ',</p><p>We received a request to reset your KaziMates password. Click the button below to choose a new one. The link is valid for ' . RESET_TTL_MINUTES . ' minutes.</p>'
                        . '<p><a href="' . e($link) . '" style="display:inline-block;background:#6d28d9;color:#fff;padding:12px 22px;border-radius:10px;text-decoration:none;font-weight:bold">Reset my password</a></p>'
                        . '<p style="color:#555;font-size:13px">Or paste this link into your browser:<br>' . e($link) . '</p>'
                        . '<p style="color:#555;font-size:13px">If you did not ask for this, you can ignore this email; your password will not change.</p><p>— KaziMates</p>';
                    send_mail($email, 'Reset your KaziMates password', $text, $html);
                }
            }
        } catch (PDOException $exception) {
            error_log('KaziMates password reset database error [' . $exception->getCode() . ']: ' . $exception->getMessage() . ' (run migration_reset_avatar.sql)');
        }
    }
}
head('Forgot password'); ?>
<section class="auth-shell"><div class="auth-story"><a class="back-home" href="login.php">← Back to log in</a><span class="eyebrow">NO WORRIES</span><h1>Locked out?<br><em>We'll get you back in.</em></h1><p>Enter the email you registered with and we'll send you a secure link to choose a new password.</p><img src="assets/community-illustration.svg" alt="" class="auth-illustration"><div class="auth-caption">It only takes a minute.</div></div>
<div class="auth-panel"><div class="auth-panel-head"><span class="eyebrow">RESET YOUR PASSWORD</span><h2>Forgot your password?</h2><p>We'll email you a reset link.</p></div>
<?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
<?php if ($sent): ?><div class="notice">If an account exists for that email, a reset link is on its way. It expires in <?= RESET_TTL_MINUTES ?> minutes. Check your spam folder too.</div><p class="auth-switch"><a href="login.php">Back to log in</a></p>
<?php else: ?><form method="post" class="auth-form"><input type="hidden" name="csrf" value="<?= e(token()) ?>"><label>Email address<input type="email" name="email" placeholder="you@example.com" autocomplete="email" required maxlength="190"></label><button class="button button-large">Send reset link <span>→</span></button></form>
<p class="auth-switch">Remembered it? <a href="login.php">Log in</a></p><?php endif; ?></div></section><?php foot(); ?>
