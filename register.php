<?php
require __DIR__ . '/includes/bootstrap.php';
if (uid()) { header('Location: dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_token();
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $region = trim($_POST['region'] ?? '');
    $career = trim($_POST['career'] ?? '');
    $valid = mb_strlen($name) >= 2 && mb_strlen($name) <= 120
        && filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($password) >= 6
        && in_array($region, REGIONS, true) && in_array($career, CAREERS, true);
    if (!$valid) {
        $error = 'Complete all fields, choose a valid region and career interest, and use a password of at least 6 characters.';
    } else {
        try {
            $pdo->beginTransaction();
            $statement = $pdo->prepare('INSERT INTO users(full_name,email,password_hash) VALUES(?,?,?)');
            $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $id = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO profiles(user_id,region,career_interest) VALUES(?,?,?)')->execute([$id, $region, $career]);
            $pdo->commit();
            session_regenerate_id(true);
            $_SESSION['uid'] = $id;
            header('Location: profile.php'); exit;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                $error = 'That email is already registered. Please use another email or log in.';
            } else {
                error_log('KaziMates registration database error [' . $exception->getCode() . ']: ' . $exception->getMessage());
                $error = 'Account creation failed because the database is not ready. Please contact the site administrator.';
            }
        }
    }
}
head('Join KaziMates'); ?>
<section class="auth-shell register-shell"><div class="auth-story"><a class="back-home" href="index.php">← Back to KaziMates</a><span class="eyebrow">START WITH A CONNECTION</span><h1>Find your people.<br><em>Grow together.</em></h1><p>Build your circle with fellow job seekers who understand your goals, share your interests and are on a similar journey.</p><img src="assets/community-illustration.svg" alt="" class="auth-illustration"><div class="auth-caption">Your journey. Your community. Your pace.</div></div>
<div class="auth-panel"><div class="auth-panel-head"><span class="eyebrow">A FEW DETAILS TO GET STARTED</span><h2>Create your account.</h2><p>Join your community in just a moment.</p></div><?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="auth-form"><input type="hidden" name="csrf" value="<?= e(token()) ?>"><label>Full name<input name="name" placeholder="Your full name" autocomplete="name" required maxlength="120"></label><label>Email address<input type="email" name="email" placeholder="you@example.com" autocomplete="email" required maxlength="190"></label><label>Password <small>(at least 6 characters)</small><input type="password" name="password" placeholder="Create a password" autocomplete="new-password" minlength="6" required></label><div class="form-pair"><label>Your region<select name="region" required><option value="">Choose region</option><?= opts(REGIONS) ?></select></label><label>Career interest<select name="career" required><option value="">Choose interest</option><?= opts(CAREERS) ?></select></label></div><button class="button button-large">Create my account <span>→</span></button></form>
<p class="auth-switch">Already a member? <a href="login.php">Log in</a></p></div></section><?php foot(); ?>
