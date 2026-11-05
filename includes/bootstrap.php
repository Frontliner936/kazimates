<?php
declare(strict_types=1);
date_default_timezone_set('Africa/Dar_es_Salaam');
const REGIONS = ['Dar es Salaam','Morogoro','Dodoma','Arusha','Mwanza','Tanga','Mbeya','Iringa','Other'];
const CAREERS = ['Agribusiness','Agriculture','Finance','ICT','Marketing','Administration','Engineering','Health','Education','Research','Other'];
const JOB_TYPES = ['Any opportunity','Internship','Full-time','Part-time','Volunteer','Graduate trainee'];
const RESEND_DAYS = 7;          // wait before re-sending a declined request
const LOGIN_MAX_EMAIL = 5;      // failed logins per email in 15 min
const LOGIN_MAX_IP = 30;        // failed logins per IP in 15 min (high: hostels share one IP)

if (session_status() !== PHP_SESSION_ACTIVE) { session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']),'samesite'=>'Lax']); session_start(); }
$cf = dirname(__DIR__).'/config.php';
if (!file_exists($cf)) { http_response_code(500); exit('Setup needed: copy config.example.php to config.php and set your database credentials.'); }
$config = require $cf;
try {
    $pdo = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4", $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone='+03:00'", // keep MySQL and PHP on East Africa Time
    ]);
} catch (PDOException $e) { http_response_code(500); exit('Database connection failed. Check config.php and import database.sql.'); }

function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function opts(array $list, string $sel = ''): string { $o = ''; foreach ($list as $x) $o .= '<option'.($x === $sel ? ' selected' : '').'>'.e($x).'</option>'; return $o; }
function uid(): int { return (int)($_SESSION['uid'] ?? 0); }
function need_login(): void { if (!uid()) { header('Location: login.php'); exit; } }
function token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function check_token(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Form expired. Please go back and retry.'); } }
function note(string $m): void { $_SESSION['note'] = $m; }
function show_note(): void { if (isset($_SESSION['note'])) { echo '<div class="notice">'.e($_SESSION['note']).'</div>'; unset($_SESSION['note']); } }

function connected(PDO $db, int $a, int $b): bool {
    $s = $db->prepare("SELECT COUNT(*) FROM connection_requests WHERE status='accepted' AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))");
    $s->execute([$a, $b, $b, $a]);
    return (int)$s->fetchColumn() > 0;
}
/** True when $me sent this request, it was declined, and the cooldown has not passed yet. */
function resend_blocked(array $r, int $me): bool {
    return $r['status'] === 'rejected' && (int)$r['sender_id'] === $me && strtotime((string)$r['created_at']) > time() - RESEND_DAYS * 86400;
}
function unread_total(PDO $db): int {
    $s = $db->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id=? AND read_at IS NULL'); $s->execute([uid()]); return (int)$s->fetchColumn();
}
function pending_total(PDO $db): int {
    $s = $db->prepare("SELECT COUNT(*) FROM connection_requests WHERE receiver_id=? AND status='pending'"); $s->execute([uid()]); return (int)$s->fetchColumn();
}
function badge(int $n): string { return $n > 0 ? '<span class="badge">'.min($n, 99).($n > 99 ? '+' : '').'</span>' : ''; }

function head(string $title): void {
    global $pdo;
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' | KaziMates</title><link rel="stylesheet" href="assets/style.css"></head><body><header><a class="logo" href="dashboard.php">🟢 KaziMates</a><nav>';
    if (uid()) {
        echo '<a href="dashboard.php">Dashboard</a><a href="discover.php">Find Mates</a><a href="requests.php">Connections'.badge(pending_total($pdo) + unread_total($pdo)).'</a><a href="profile.php">Profile</a>'
           . '<form method="post" action="auth/logout.php"><input type="hidden" name="csrf" value="'.e(token()).'"><button class="linkbtn">Log out</button></form>';
    } else { echo '<a href="login.php">Log in</a><a href="register.php">Join free</a>'; }
    echo '</nav></header><main>'; show_note();
}
function foot(): void { echo '</main><footer>KaziMates · Find Your People. Share Opportunities. Grow Together.</footer></body></html>'; }
