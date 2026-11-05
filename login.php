<?php
require __DIR__.'/includes/bootstrap.php';
if (uid()) { header('Location: ' . (current_user_is_admin() ? 'admin.php' : 'dashboard.php')); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_token();
    $email = strtolower(trim($_POST['email'] ?? '')); $ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
    $s = $pdo->prepare('SELECT COALESCE(SUM(email=?),0) e, COALESCE(SUM(ip=?),0) i FROM login_attempts WHERE attempted_at > NOW() - INTERVAL 15 MINUTE');
    $s->execute([$email, $ip]); $c = $s->fetch();
    if ((int)$c['e'] >= LOGIN_MAX_EMAIL || (int)$c['i'] >= LOGIN_MAX_IP) {
        $err = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        try {
            $s = $pdo->prepare('SELECT id,password_hash,is_active,role FROM users WHERE email=?');
            $s->execute([$email]); $u = $s->fetch();
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '42S22') throw $exception;
            $s = $pdo->prepare('SELECT id,password_hash FROM users WHERE email=?');
            $s->execute([$email]); $u = $s->fetch();
        }
        if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
            if ((int) ($u['is_active'] ?? 1) !== 1) {
                $err = 'This account is disabled. Please contact an administrator.';
            } else {
                $pdo->prepare('DELETE FROM login_attempts WHERE email=?')->execute([$email]);
                session_regenerate_id(true); $_SESSION['uid'] = (int)$u['id'];
                header('Location: ' . (($u['role'] ?? 'member') === 'admin' ? 'admin.php' : 'dashboard.php')); exit;
            }
        } else {
            $pdo->prepare('INSERT INTO login_attempts(email,ip) VALUES(?,?)')->execute([$email, $ip]);
            $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
            $err = 'Email or password is incorrect.';
        }
    }
}
head('Log in'); ?>
<section class="auth-shell"><div class="auth-story"><a class="back-home" href="index.php">← Back to KaziMates</a><span class="eyebrow">WELCOME BACK</span><h1>Your people are<br>right where you<br><em>left them.</em></h1><p>Pick up where you left off. Your community is here to share ideas, encouragement and the next step forward.</p><img src="assets/community-illustration.svg" alt="" class="auth-illustration"><div class="auth-caption">A little support can go a long way.</div></div>
<div class="auth-panel"><div class="auth-panel-head"><span class="eyebrow">YOUR COMMUNITY AWAITS</span><h2>Welcome back.</h2><p>Log in to your KaziMates account.</p></div><?php if ($err): ?><p class="error"><?=e($err)?></p><?php endif; ?>
<form method="post" class="auth-form"><input type="hidden" name="csrf" value="<?=e(token())?>"><label>Email address<input type="email" name="email" placeholder="you@example.com" autocomplete="email" required></label><label>Password<input type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required></label><button class="button button-large">Log in <span>→</span></button></form>
<p class="auth-switch">New to KaziMates? <a href="register.php">Create your account</a></p></div></section>
<?php foot();
