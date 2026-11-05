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
        && filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($password) >= 10
        && in_array($region, REGIONS, true) && in_array($career, CAREERS, true);
    if (!$valid) {
        $error = 'Complete all fields, choose a valid region and career interest, and use a password of at least 10 characters.';
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
            $error = 'That email may already be registered. Please try another.';
        }
    }
}
head('Join'); ?>
<section class="auth"><div><small>YOUR JOB SEARCH, TOGETHER</small><h1>Find your people.<br><em>Grow together.</em></h1><p>Meet people with similar career goals, share opportunities and build a professional network.</p></div>
<div class="card"><h2>Create account</h2><?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= e(token()) ?>"><label>Full name<input name="name" required maxlength="120"></label><label>Email<input type="email" name="email" required maxlength="190"></label><label>Password (10+ characters)<input type="password" name="password" minlength="10" required></label><label>Region<select name="region" required><?= opts(REGIONS) ?></select></label><label>Career interest<select name="career" required><?= opts(CAREERS) ?></select></label><button>Create my account</button></form><p>Already a member? <a href="login.php">Log in</a></p></div></section><?php foot(); ?>
