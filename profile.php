<?php
require __DIR__ . '/includes/bootstrap.php';
need_login();
$id = uid();
$statement = $pdo->prepare('SELECT u.full_name,u.email,p.* FROM users u JOIN profiles p ON p.user_id=u.id WHERE u.id=?');
$statement->execute([$id]);
$profile = $statement->fetch();
if (!$profile) { http_response_code(404); exit('Profile not found.'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_token();
    $name = trim($_POST['name'] ?? '');
    $region = trim($_POST['region'] ?? '');
    $career = trim($_POST['career'] ?? '');
    $education = trim($_POST['education'] ?? '');
    $skills = trim($_POST['skills'] ?? '');
    $experience = trim($_POST['experience'] ?? '');
    $jobType = trim($_POST['job_type'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $valid = mb_strlen($name) >= 2 && mb_strlen($name) <= 120
        && in_array($region, REGIONS, true) && in_array($career, CAREERS, true)
        && mb_strlen($education) <= 190 && mb_strlen($skills) <= 5000
        && mb_strlen($experience) <= 190 && in_array($jobType, JOB_TYPES, true)
        && mb_strlen($bio) <= 5000;
    if (!$valid) {
        note('Check your profile fields: choose valid options and keep each field within its stated length.');
        header('Location: profile.php'); exit;
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET full_name=? WHERE id=?')->execute([$name, $id]);
        $pdo->prepare('UPDATE profiles SET region=?,career_interest=?,education=?,skills=?,experience=?,job_type=?,bio=?,is_visible=? WHERE user_id=?')
            ->execute([$region, $career, $education, $skills, $experience, $jobType, $bio, isset($_POST['visible']) ? 1 : 0, $id]);
        $pdo->commit();
        note('Profile updated.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        note('Could not update your profile. Please try again.');
    }
    header('Location: profile.php'); exit;
}
head('My profile'); ?>
<div class="card"><h1>My career profile</h1><p>Your email and private contact details are not shown in member search.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= e(token()) ?>"><label>Full name<input name="name" value="<?= e($profile['full_name']) ?>" required maxlength="120"></label><label>Email<input value="<?= e($profile['email']) ?>" disabled></label>
<label>Region<select name="region" required><?= opts(REGIONS, (string) $profile['region']) ?></select></label><label>Career interest<select name="career" required><?= opts(CAREERS, (string) $profile['career_interest']) ?></select></label>
<label>Education<input name="education" maxlength="190" value="<?= e($profile['education']) ?>"></label><label>Skills (comma separated)<textarea name="skills" maxlength="5000"><?= e($profile['skills']) ?></textarea></label>
<label>Experience<input name="experience" maxlength="190" value="<?= e($profile['experience']) ?>"></label><label>Opportunity sought<select name="job_type" required><?= opts(JOB_TYPES, (string) $profile['job_type']) ?></select></label>
<label>About me<textarea name="bio" maxlength="5000"><?= e($profile['bio']) ?></textarea></label><label class="check"><input type="checkbox" name="visible" <?= $profile['is_visible'] ? 'checked' : '' ?>> Allow members to discover my profile</label><button>Save profile</button></form></div><?php foot(); ?>
