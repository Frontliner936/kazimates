<?php
require __DIR__ . '/includes/bootstrap.php';
need_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: profile.php'); exit; }
check_token();
const AVATAR_MAX_BYTES = 5 * 1024 * 1024;
const AVATAR_MAX_PIXELS = 25000000;
const AVATAR_SIZE = 400;
$me = uid();

function avatar_fail(string $message): void { note($message); header('Location: profile.php'); exit; }

/** Centre-crops to a square, fixes phone-photo rotation, strips metadata by re-encoding as JPEG. Returns JPEG bytes. */
function avatar_process(string $path, string $mime): ?string {
    if (!function_exists('imagecreatefromstring')) return null;
    $data = file_get_contents($path);
    $src = $data === false ? false : @imagecreatefromstring($data);
    if (!$src) return null;
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $angle = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($angle) { $rotated = imagerotate($src, $angle, 0); if ($rotated) { imagedestroy($src); $src = $rotated; } }
    }
    $w = imagesx($src); $h = imagesy($src); $side = min($w, $h);
    $out = imagecreatetruecolor(AVATAR_SIZE, AVATAR_SIZE);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // flatten transparency onto white
    imagecopyresampled($out, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), AVATAR_SIZE, AVATAR_SIZE, $side, $side);
    ob_start(); imagejpeg($out, null, 85); $jpeg = ob_get_clean();
    imagedestroy($src); imagedestroy($out);
    return $jpeg ?: null;
}

try {
    if (($_POST['action'] ?? '') === 'remove') {
        $pdo->prepare('DELETE FROM profile_pictures WHERE user_id=?')->execute([$me]);
        avatar_fail('Profile picture removed.');
    }
    $upload = $_FILES['picture'] ?? null;
    if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) avatar_fail('Choose a picture to upload.');
    if ($upload['error'] === UPLOAD_ERR_INI_SIZE || $upload['error'] === UPLOAD_ERR_FORM_SIZE) avatar_fail('That picture is too large. Please use one under 5 MB.');
    if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) avatar_fail('The upload failed. Please try again.');
    if ((int) $upload['size'] > AVATAR_MAX_BYTES) avatar_fail('That picture is too large. Please use one under 5 MB.');

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    $info = @getimagesize($upload['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true) || !$info) avatar_fail('Please choose a JPG, PNG, GIF or WebP image.');
    if ($info[0] * $info[1] > AVATAR_MAX_PIXELS) avatar_fail('That image has too many pixels. Please choose a smaller one.');

    $bytes = avatar_process($upload['tmp_name'], $mime);
    if ($bytes !== null) { $mime = 'image/jpeg'; }
    else {
        // No image library on this server: keep the original, but only if it is small.
        if ((int) $upload['size'] > 1024 * 1024) avatar_fail('This server cannot resize images, so please upload a picture under 1 MB.');
        $bytes = file_get_contents($upload['tmp_name']);
    }
    $stmt = $pdo->prepare('INSERT INTO profile_pictures(user_id,mime_type,file_size,image_data) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE mime_type=VALUES(mime_type),file_size=VALUES(file_size),image_data=VALUES(image_data),updated_at=CURRENT_TIMESTAMP');
    $stmt->bindValue(1, $me, PDO::PARAM_INT);
    $stmt->bindValue(2, $mime, PDO::PARAM_STR);
    $stmt->bindValue(3, strlen($bytes), PDO::PARAM_INT);
    $stmt->bindValue(4, $bytes, PDO::PARAM_LOB);
    $stmt->execute();
    note('Profile picture updated.');
} catch (PDOException $exception) {
    error_log('KaziMates profile picture database error [' . $exception->getCode() . ']: ' . $exception->getMessage());
    note('Could not save your picture. The site administrator needs to run migration_reset_avatar.sql.');
}
header('Location: profile.php'); exit;
