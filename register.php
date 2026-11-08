<?php
require __DIR__ . '/includes/bootstrap.php';
if (uid()) { header('Location: dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_token();
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $region = pick_or_type('region', REGIONS, 80);
    $career = pick_or_type('career', CAREERS, 120);
    $valid = mb_strlen($name) >= 2 && mb_strlen($name) <= 120
        && filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($password) > 6
        && $region !== null && $career !== null;
    if (!$valid) {
        $error = 'Complete all fields, choose an option or enter a region and career interest, and use a password of more than 6 characters.';
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
$regionChoice = (string) ($_POST['region'] ?? '');
$careerChoice = (string) ($_POST['career'] ?? '');
$regionOther = trim((string) ($_POST['region_other'] ?? ''));
$careerOther = trim((string) ($_POST['career_other'] ?? ''));
head('Join KaziMates'); ?>
<section class="auth-shell register-shell"><div class="auth-story"><a class="back-home" href="index.php">← Back to home</a><span class="eyebrow">KAZIMATES</span><h1>Create your<br><em>account.</em></h1><p>Connect with job seekers in your region and field.</p><img src="assets/community-illustration.svg" alt="" class="auth-illustration"></div>
<div class="auth-panel"><div class="auth-panel-head"><h2>Create account</h2><p>Enter your details below.</p></div><?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="auth-form"><input type="hidden" name="csrf" value="<?= e(token()) ?>"><label>Full name<input name="name" placeholder="Your full name" autocomplete="name" required maxlength="120" value="<?= e($_POST['name'] ?? '') ?>"></label><label>Email address<input type="email" name="email" placeholder="you@example.com" autocomplete="email" required maxlength="190" value="<?= e($_POST['email'] ?? '') ?>"></label><label>Password <small>(more than 6 characters)</small><input type="password" name="password" placeholder="Create a password" autocomplete="new-password" minlength="7" required></label>
<div class="form-pair"><label>Your region (Mkoa)<select name="region" id="register-region" required><option value="">Choose region</option><?= opts(REGIONS, $regionChoice) ?></select><input type="text" name="region_other" id="register-region-other" maxlength="80" placeholder="Enter your region (Mkoa)" value="<?= e($regionOther) ?>" hidden></label><label>Career interest (Kazi unayoweza)<select name="career" id="register-career" required><option value="">Choose career interest</option><?= opts(CAREERS, $careerChoice) ?></select><input type="text" name="career_other" id="register-career-other" maxlength="120" placeholder="Enter your career interest (Kazi unayoweza)" value="<?= e($careerOther) ?>" hidden></label></div>
<button class="button button-large">Create account <span>→</span></button></form>
<p class="auth-switch">Already registered? <a href="login.php">Log in</a></p></div></section>
<script>
(() => {
  [['register-region','register-region-other'], ['register-career','register-career-other']].forEach(([selectId, inputId]) => {
    const select = document.getElementById(selectId);
    const input = document.getElementById(inputId);
    const update = () => {
      const custom = select.value === 'Other';
      input.hidden = !custom;
      input.required = custom;
      if (custom) input.focus({ preventScroll: true });
    };
    select.addEventListener('change', update);
    update();
  });
})();
</script>
<?php foot();
