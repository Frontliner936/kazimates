<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/regions.php';
need_login();
$me = uid();

// Members who allow themselves to be found, counted per region (only the region is ever shown, never names or addresses).
$sql = 'SELECT p.region, COUNT(*) AS total FROM profiles p JOIN users u ON u.id=p.user_id WHERE p.is_visible=1 AND u.is_active=1 AND u.id<>? GROUP BY p.region';
try {
    try { $s = $pdo->prepare($sql); $s->execute([$me]); }
    catch (PDOException $exception) {
        if ($exception->getCode() !== '42S22') throw $exception;
        $s = $pdo->prepare(str_replace(' AND u.is_active=1', '', $sql)); $s->execute([$me]);
    }
    $memberRows = $s->fetchAll();

    $s = $pdo->prepare("SELECT u.id,u.full_name,p.region,p.career_interest FROM connection_requests r JOIN users u ON u.id=IF(r.sender_id=?,r.receiver_id,r.sender_id) JOIN profiles p ON p.user_id=u.id WHERE r.status='accepted' AND (r.sender_id=? OR r.receiver_id=?) ORDER BY u.full_name");
    $s->execute([$me, $me, $me]);
    $connectionRows = $s->fetchAll();
} catch (PDOException $exception) {
    error_log('KaziMates map error [' . $exception->getCode() . ']: ' . $exception->getMessage());
    $memberRows = $connectionRows = [];
}

$members = []; $memberTotal = 0; $memberUnplaced = 0;
foreach ($memberRows as $row) {
    $count = (int) $row['total']; $memberTotal += $count;
    $place = region_place((string) $row['region']);
    if (!$place) { $memberUnplaced += $count; continue; }
    $key = $place['name'];
    if (!isset($members[$key])) $members[$key] = ['region' => $key, 'lat' => $place['lat'], 'lng' => $place['lng'], 'count' => 0, 'filter' => (string) $row['region']];
    $members[$key]['count'] += $count;
}
$connections = []; $unplaced = [];
foreach ($connectionRows as $row) {
    $place = region_place((string) $row['region']);
    $person = ['id' => (int) $row['id'], 'name' => (string) $row['full_name'], 'career' => (string) $row['career_interest'], 'region' => (string) $row['region']];
    if (!$place) { $unplaced[] = $person; continue; }
    $key = $place['name'];
    if (!isset($connections[$key])) $connections[$key] = ['region' => $key, 'lat' => $place['lat'], 'lng' => $place['lng'], 'people' => []];
    $connections[$key]['people'][] = $person;
}
ksort($connections);
$data = ['members' => array_values($members), 'connections' => array_values($connections)];
head('Tanzania map'); ?>
<h1>Tanzania map</h1>
<p>See which regions members and your connections are in. Only the region is shown, never an exact address.</p>
<div class="tz-stats">
  <div class="card"><b><?= $memberTotal - $memberUnplaced ?></b><small>members on the map, in <?= count($members) ?> region<?= count($members) === 1 ? '' : 's' ?></small></div>
  <div class="card"><b><?= count($connectionRows) - count($unplaced) ?></b><small>of your connections on the map, in <?= count($connections) ?> region<?= count($connections) === 1 ? '' : 's' ?></small></div>
</div>
<div class="card tz-map-card">
  <div class="tz-toggles">
    <label class="check"><input type="checkbox" id="tz-show-members" checked> <span class="tz-key tz-key-members"></span> Members</label>
    <label class="check"><input type="checkbox" id="tz-show-connections" checked> <span class="tz-key tz-key-connections"></span> My connections</label>
  </div>
  <div id="tz-map" role="application" aria-label="Map of Tanzania showing member regions"></div>
  <p class="tz-hint">Tap a circle to see the region. Numbers show how many people are there.</p>
</div>
<?php if ($memberUnplaced): ?><p class="tz-hint"><?= $memberUnplaced ?> member<?= $memberUnplaced === 1 ? ' has' : 's have' ?> a place we cannot show on the map yet.</p><?php endif; ?>
<?php if ($connections || $unplaced): ?>
<div class="card"><h2>My connections by region</h2>
<?php foreach ($connections as $group): ?><div class="request"><div><b><?= e($group['region']) ?></b><small><?= count($group['people']) ?> connection<?= count($group['people']) === 1 ? '' : 's' ?></small></div><div class="tz-people"><?php foreach ($group['people'] as $p): ?><a href="chat.php?with=<?= (int) $p['id'] ?>"><?= e($p['name']) ?></a><?php endforeach; ?></div></div><?php endforeach; ?>
<?php if ($unplaced): ?><div class="request"><div><b>Not on the map</b><small>Region not recognised</small></div><div class="tz-people"><?php foreach ($unplaced as $p): ?><a href="chat.php?with=<?= (int) $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['region']) ?>)</a><?php endforeach; ?></div></div><?php endif; ?>
</div>
<?php endif; ?>
<script type="application/json" id="tz-data"><?= json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="assets/tz-map.js?v=<?= (int) @filemtime(__DIR__ . '/assets/tz-map.js') ?>"></script>
<?php foot(); ?>
