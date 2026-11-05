<?php
require __DIR__.'/includes/bootstrap.php';
need_login();
$me = uid();
$q = trim($_GET['q'] ?? ''); $region = trim($_GET['region'] ?? ''); $career = trim($_GET['career'] ?? '');

$sql = "SELECT u.id,u.full_name,p.region,p.career_interest,p.education,p.skills,p.experience,p.bio,
        (p.region=(SELECT region FROM profiles WHERE user_id=?))+(p.career_interest=(SELECT career_interest FROM profiles WHERE user_id=?))*2 AS score
        FROM users u JOIN profiles p ON p.user_id=u.id WHERE u.id<>? AND p.is_visible=1";
$args = [$me, $me, $me];
if ($region) { $sql .= ' AND p.region=?'; $args[] = $region; }
if ($career) { $sql .= ' AND p.career_interest=?'; $args[] = $career; }
if ($q) {
    $sql .= ' AND (u.full_name LIKE ? OR p.skills LIKE ? OR p.education LIKE ?)';
    $v = '%'.addcslashes($q, '%_\\').'%';   // treat % and _ typed by the user literally
    array_push($args, $v, $v, $v);
}
$sql .= ' ORDER BY score DESC,u.full_name LIMIT 100';
$s = $pdo->prepare($sql); $s->execute($args); $members = $s->fetchAll();

$s = $pdo->prepare('SELECT sender_id,receiver_id,status,created_at FROM connection_requests WHERE sender_id=? OR receiver_id=?');
$s->execute([$me, $me]);
$states = [];
foreach ($s as $r) {
    $other = (int)($r['sender_id'] == $me ? $r['receiver_id'] : $r['sender_id']);
    if ($r['status'] === 'accepted') $states[$other] = 'accepted';
    elseif ($r['status'] === 'pending') $states[$other] = $r['sender_id'] == $me ? 'sent' : 'incoming';
    else $states[$other] = resend_blocked($r, $me) ? 'declined' : '';   // declined: show Connect again once allowed
}
head('Find Mates'); ?>
<h1>Find your KaziMates</h1><p>Profiles are matched by career interest and region. Members control whether their profiles are discoverable.</p>
<form class="filters"><input name="q" placeholder="Search name, skills, education" value="<?=e($q)?>"><select name="region"><option value="">All regions</option><?=opts(REGIONS, $region)?></select><select name="career"><option value="">All career interests</option><?=opts(CAREERS, $career)?></select><button>Search</button></form>
<div class="grid"><?php foreach ($members as $m): $st = $states[$m['id']] ?? ''; ?>
<div class="card"><h2><?=e($m['full_name'])?></h2><b><?=e($m['career_interest'])?></b> · <?=e($m['region'])?><p><?=e($m['education'])?></p><p><?=e($m['bio'])?></p><small>Skills: <?=e($m['skills'])?></small>
<div class="actions"><?php if ($st === 'accepted'): ?><a class="button" href="chat.php?with=<?=(int)$m['id']?>">Message</a>
<?php elseif ($st === 'sent'): ?>Request sent
<?php elseif ($st === 'declined'): ?><small>Not accepted yet — you can try again later.</small>
<?php elseif ($st === 'incoming'): ?><a href="requests.php">Respond to request</a>
<?php else: ?><form method="post" action="send_request.php"><input type="hidden" name="csrf" value="<?=e(token())?>"><input type="hidden" name="user_id" value="<?=(int)$m['id']?>"><button>Connect</button></form><?php endif; ?></div></div>
<?php endforeach; ?><?php if (!$members): ?><div class="card">No members found. Try broadening your search.</div><?php endif; ?></div>
<?php foot();
