<?php
require __DIR__.'/includes/bootstrap.php';
if (uid()) { header('Location: dashboard.php'); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_token();
    $email = strtolower(trim($_POST['email'] ?? '')); $ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
    $s = $pdo->prepare('SELECT COALESCE(SUM(email=?),0) e, COALESCE(SUM(ip=?),0) i FROM login_attempts WHERE attempted_at > NOW() - INTERVAL 15 MINUTE');
    $s->execute([$email, $ip]); $c = $s->fetch();
    if ((int)$c['e'] >= LOGIN_MAX_EMAIL || (int)$c['i'] >= LOGIN_MAX_IP) {
        $err = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $s = $pdo->prepare('SELECT id,password_hash FROM users WHERE email=?'); $s->execute([$email]); $u = $s->fetch();
        if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
            $pdo->prepare('DELETE FROM login_attempts WHERE email=?')->execute([$email]);
            session_regenerate_id(true); $_SESSION['uid'] = (int)$u['id'];
            header('Location: dashboard.php'); exit;
        }
        $pdo->prepare('INSERT INTO login_attempts(email,ip) VALUES(?,?)')->execute([$email, $ip]);
        $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
        $err = 'Email or password is incorrect.';
    }
}
head('Log in'); ?>
<section class="auth"><div><small>WELCOME BACK</small><h1>Connections can open <em>new doors.</em></h1><p>Log in to find job seeker mates and grow your network.</p></div>
<div class="card"><h2>Log in</h2><?php if ($err): ?><p class="error"><?=e($err)?></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(token())?>"><label>Email<input type="email" name="email" required></label><label>Password<input type="password" name="password" required></label><button>Log in</button></form>
<p>New here? <a href="register.php">Create an account</a></p></div></section>
<?php foot();
