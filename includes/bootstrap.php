<?php
declare(strict_types=1);
date_default_timezone_set('Africa/Dar_es_Salaam');
const REGIONS = ['Dar es Salaam','Morogoro','Dodoma','Arusha','Mwanza','Tanga','Mbeya','Iringa','Other'];
const CAREERS = ['Agribusiness','Agriculture','Finance','ICT','Marketing','Administration','Engineering','Health','Education','Research','Other'];
const JOB_TYPES = ['Any opportunity','Internship','Full-time','Part-time','Volunteer','Graduate trainee'];
const RESEND_DAYS = 7;
const LOGIN_MAX_EMAIL = 5;
const LOGIN_MAX_IP = 30;

if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    session_set_cookie_params(['httponly' => true, 'secure' => $https, 'samesite' => 'Lax']);
    session_start();
}

function config_env(string $key): ?string {
    $value = getenv($key);
    return $value === false || $value === '' ? null : $value;
}

$configFile = dirname(__DIR__) . '/config.php';
$config = is_file($configFile) ? require $configFile : [];
if (!is_array($config)) $config = [];

$dbUrl = config_env('MYSQL_URL') ?? config_env('DATABASE_URL');
$urlParts = $dbUrl ? parse_url($dbUrl) : false;
$urlConfig = [];
if (is_array($urlParts)) {
    $urlConfig = [
        'db_host' => $urlParts['host'] ?? null,
        'db_port' => $urlParts['port'] ?? null,
        'db_name' => isset($urlParts['path']) ? ltrim($urlParts['path'], '/') : null,
        'db_user' => isset($urlParts['user']) ? rawurldecode($urlParts['user']) : null,
        'db_pass' => isset($urlParts['pass']) ? rawurldecode($urlParts['pass']) : null,
    ];
}
$dbHost = config_env('DB_HOST') ?? config_env('MYSQLHOST') ?? ($urlConfig['db_host'] ?? null) ?? ($config['db_host'] ?? null);
$dbName = config_env('DB_NAME') ?? config_env('MYSQLDATABASE') ?? ($urlConfig['db_name'] ?? null) ?? ($config['db_name'] ?? null);
$dbUser = config_env('DB_USER') ?? config_env('MYSQLUSER') ?? ($urlConfig['db_user'] ?? null) ?? ($config['db_user'] ?? null);
$dbPass = config_env('DB_PASSWORD') ?? config_env('MYSQLPASSWORD') ?? ($urlConfig['db_pass'] ?? null) ?? ($config['db_pass'] ?? null);
$dbPort = config_env('DB_PORT') ?? config_env('MYSQLPORT') ?? ($urlConfig['db_port'] ?? null) ?? ($config['db_port'] ?? 3306);
if (!$dbHost || !$dbName || !$dbUser || $dbPass === null || !filter_var((string) $dbPort, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]])) {
    http_response_code(500);
    exit('Database is not configured. Set DB_HOST, DB_NAME, DB_USER, DB_PASSWORD and DB_PORT, or provide a config.php file.');
}

try {
    $pdo = new PDO('mysql:host=' . $dbHost . ';port=' . (int) $dbPort . ';dbname=' . $dbName . ';charset=utf8mb4', $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone='+03:00'",
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    exit('Database connection failed. Check the database variables and import database.sql into the configured database.');
}

function e($v): string { return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function opts(array $list, string $sel = ''): string { $o = ''; foreach ($list as $x) $o .= '<option' . ($x === $sel ? ' selected' : '') . '>' . e($x) . '</option>'; return $o; }
function uid(): int { return (int) ($_SESSION['uid'] ?? 0); }
function need_login(): void {
    global $pdo;
    if (!uid()) { header('Location: login.php'); exit; }
    try {
        $statement = $pdo->prepare('SELECT is_active FROM users WHERE id=?');
        $statement->execute([uid()]);
        $active = $statement->fetchColumn();
    } catch (PDOException $exception) {
        if ($exception->getCode() !== '42S22') throw $exception;
        return; // Keep the existing app available until the admin migration is applied.
    }
    if ($active === false || (int) $active !== 1) {
        $_SESSION = [];
        session_regenerate_id(true);
        note('This account is disabled. Please contact an administrator.');
        header('Location: login.php'); exit;
    }
    if (current_user_is_admin() && basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) !== 'admin.php') {
        header('Location: admin.php'); exit;
    }
}
function current_user_is_admin(): bool {
    global $pdo;
    if (!uid()) return false;
    try {
        $statement = $pdo->prepare("SELECT role FROM users WHERE id=? AND is_active=1");
        $statement->execute([uid()]);
        return $statement->fetchColumn() === 'admin';
    } catch (PDOException $exception) {
        if ($exception->getCode() === '42S22') return false;
        throw $exception;
    }
}
function need_admin(): void {
    need_login();
    if (!current_user_is_admin()) { http_response_code(403); exit('Admin access is required.'); }
}
function token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function check_token(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Form expired. Please go back and retry.'); } }
function note(string $m): void { $_SESSION['note'] = $m; }
function show_note(): void { if (isset($_SESSION['note'])) { echo '<div class="notice">' . e($_SESSION['note']) . '</div>'; unset($_SESSION['note']); } }
function connected(PDO $db, int $a, int $b): bool {
    $s = $db->prepare("SELECT COUNT(*) FROM connection_requests WHERE status='accepted' AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))");
    $s->execute([$a, $b, $b, $a]);
    return (int) $s->fetchColumn() > 0;
}
function resend_blocked(array $r, int $me): bool {
    return $r['status'] === 'rejected' && (int) $r['sender_id'] === $me && strtotime((string) $r['created_at']) > time() - RESEND_DAYS * 86400;
}
function unread_total(PDO $db): int {
    $s = $db->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id=? AND read_at IS NULL'); $s->execute([uid()]); return (int) $s->fetchColumn();
}
function pending_total(PDO $db): int {
    $s = $db->prepare("SELECT COUNT(*) FROM connection_requests WHERE receiver_id=? AND status='pending'"); $s->execute([uid()]); return (int) $s->fetchColumn();
}
function badge(int $n): string { return $n > 0 ? '<span class="badge">' . min($n, 99) . ($n > 99 ? '+' : '') . '</span>' : ''; }
function avatar(int $userId, string $class = '', int $version = 0): string {
    return '<img class="avatar ' . e($class) . '" src="avatar.php?id=' . $userId . ($version ? '&amp;v=' . $version : '') . '" alt="" loading="lazy" width="48" height="48">';
}
function head(string $title): void {
    global $pdo;
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . ' | KaziMates</title><link rel="stylesheet" href="assets/style.css?v=' . (int) @filemtime(__DIR__ . '/../assets/style.css') . '"></head><body><header><a class="logo" href="'.(uid()?'dashboard.php':'index.php').'">✦ KaziMates</a><nav>';
    if (uid()) {
        if (current_user_is_admin()) {
            echo '<a href="admin.php">Admin dashboard</a>';
        } else {
            echo '<a href="dashboard.php">Dashboard</a><a href="discover.php">Find Mates</a><a href="requests.php">Connections' . badge(pending_total($pdo) + unread_total($pdo)) . '</a><a href="profile.php">Profile</a>';
        }
        echo '<form method="post" action="auth/logout.php"><input type="hidden" name="csrf" value="' . e(token()) . '"><button class="linkbtn">Log out</button></form>';
    } else { echo '<a href="login.php">Log in</a><a href="register.php">Join free</a>'; }
    echo '</nav></header><main>'; show_note();
}
function foot(): void { echo '</main><footer>© KaziMates</footer></body></html>'; }
