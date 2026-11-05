<?php
require __DIR__ . '/includes/bootstrap.php';
need_admin();
$me = uid();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_token();
    $action = $_POST['action'] ?? '';
    try {
        if (in_array($action, ['suspend_member', 'restore_member'], true)) {
            $target = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT) ?: 0;
            if (!$target || $target === $me) {
                note('You cannot change your own account status.');
            } else {
                $statement = $pdo->prepare('SELECT role,is_active FROM users WHERE id=?');
                $statement->execute([$target]);
                $member = $statement->fetch();
                if (!$member || $member['role'] !== 'member') {
                    note('Only member accounts can be suspended or restored here.');
                } else {
                    $active = $action === 'restore_member' ? 1 : 0;
                    if ((int) $member['is_active'] === $active) {
                        note($active ? 'That member is already active.' : 'That member is already suspended.');
                    } else {
                        $pdo->beginTransaction();
                        $pdo->prepare('UPDATE users SET is_active=? WHERE id=? AND role=\'member\'')->execute([$active, $target]);
                        $auditAction = $active ? 'member.restored' : 'member.suspended';
                        $pdo->prepare('INSERT INTO admin_audit(admin_id,action,target_id,details) VALUES(?,?,?,?)')->execute([$me, $auditAction, $target, 'Member account status changed']);
                        $pdo->commit();
                        note($active ? 'Member account restored.' : 'Member account suspended.');
                    }
                }
            }
        } elseif ($action === 'resolve_report') {
            $reportId = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT) ?: 0;
            $status = $_POST['status'] ?? '';
            if (!$reportId || !in_array($status, ['reviewed', 'dismissed'], true)) {
                note('Invalid report update.');
            } else {
                $pdo->beginTransaction();
                $statement = $pdo->prepare("UPDATE reports SET status=?,reviewed_by=?,reviewed_at=NOW() WHERE id=? AND status='open'");
                $statement->execute([$status, $me, $reportId]);
                if ($statement->rowCount() === 1) {
                    $pdo->prepare('INSERT INTO admin_audit(admin_id,action,target_id,details) VALUES(?,?,?,?)')->execute([$me, 'report.' . $status, $reportId, 'Report review status updated']);
                    $pdo->commit();
                    note($status === 'reviewed' ? 'Report marked reviewed.' : 'Report dismissed.');
                } else {
                    $pdo->rollBack();
                    note('That report is no longer open. Refresh the list.');
                }
            }
        } else {
            note('Unknown admin action.');
        }
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('KaziMates admin action error [' . $exception->getCode() . ']: ' . $exception->getMessage());
        note('The change could not be saved. Check the admin database migration and try again.');
    }
    header('Location: admin.php'); exit;
}

$memberCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='member'")->fetchColumn();
$activeCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='member' AND is_active=1")->fetchColumn();
$suspendedCount = $memberCount - $activeCount;
$openReportCount = (int) $pdo->query("SELECT COUNT(*) FROM reports WHERE status='open'")->fetchColumn();
$messageCount = (int) $pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn();
$filter = $_GET['reports'] ?? 'open';
if (!in_array($filter, ['open', 'reviewed', 'dismissed', 'all'], true)) $filter = 'open';
$reportSql = 'SELECT r.id,r.reporter_id,r.reported_user_id,r.reason,r.details,r.status,r.created_at,reporter.full_name AS reporter_name,reported.full_name AS reported_name FROM reports r JOIN users reporter ON reporter.id=r.reporter_id JOIN users reported ON reported.id=r.reported_user_id';
if ($filter !== 'all') $reportSql .= ' WHERE r.status=' . $pdo->quote($filter);
$reportSql .= ' ORDER BY CASE r.status WHEN \'open\' THEN 0 ELSE 1 END,r.created_at DESC LIMIT 100';
$reports = $pdo->query($reportSql)->fetchAll();
$members = $pdo->query('SELECT id,full_name,email,role,is_active,created_at FROM users ORDER BY created_at DESC,id DESC LIMIT 100')->fetchAll();
$audit = $pdo->query('SELECT a.action,a.target_id,a.details,a.created_at,u.full_name FROM admin_audit a JOIN users u ON u.id=a.admin_id ORDER BY a.created_at DESC,a.id DESC LIMIT 30')->fetchAll();
head('Admin'); ?>
<section class="admin-page">
  <div class="admin-heading"><div><span class="eyebrow">KAZIMATES CONTROL ROOM</span><h1>Admin dashboard</h1><p>Review community reports and manage member access.</p></div><span class="admin-badge">Admin access</span></div>
  <div class="admin-stats">
    <article class="admin-stat"><span>Members</span><strong><?= number_format($memberCount) ?></strong></article>
    <article class="admin-stat"><span>Active</span><strong><?= number_format($activeCount) ?></strong></article>
    <article class="admin-stat"><span>Suspended</span><strong><?= number_format($suspendedCount) ?></strong></article>
    <article class="admin-stat"><span>Open reports</span><strong><?= number_format($openReportCount) ?></strong></article>
    <article class="admin-stat"><span>Messages</span><strong><?= number_format($messageCount) ?></strong></article>
  </div>

  <section class="admin-section" id="reports"><div class="admin-section-heading"><div><h2>Member reports</h2><p>Review reports submitted by members. Closing a report does not automatically suspend an account.</p></div>
    <nav class="admin-filters"><a href="admin.php?reports=open">Open</a><a href="admin.php?reports=reviewed">Reviewed</a><a href="admin.php?reports=dismissed">Dismissed</a><a href="admin.php?reports=all">All</a></nav></div>
    <?php if (!$reports): ?><p class="admin-empty">No reports in this view.</p><?php endif; ?>
    <?php foreach ($reports as $report): ?><article class="admin-report"><div class="admin-report-top"><div><b>Report #<?= (int) $report['id'] ?> · <?= e($report['reason']) ?></b><small><?= e($report['reporter_name']) ?> reported <?= e($report['reported_name']) ?> · <?= e(date('d M Y H:i', strtotime($report['created_at']))) ?></small></div><span class="status-pill status-<?= e($report['status']) ?>"><?= e(ucfirst($report['status'])) ?></span></div>
      <?php if (trim((string) $report['details']) !== ''): ?><p class="report-details"><?= nl2br(e($report['details'])) ?></p><?php endif; ?>
      <?php if ($report['status'] === 'open'): ?><form method="post" class="admin-actions"><input type="hidden" name="csrf" value="<?= e(token()) ?>"><input type="hidden" name="action" value="resolve_report"><input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>"><button name="status" value="reviewed">Mark reviewed</button><button class="secondary" name="status" value="dismissed">Dismiss</button></form><?php endif; ?>
    </article><?php endforeach; ?>
  </section>

  <section class="admin-section"><div class="admin-section-heading"><div><h2>Member accounts</h2><p>Showing the latest 100 accounts. Admin roles can only be assigned directly in the database.</p></div></div>
    <div class="admin-member-list"><?php foreach ($members as $member): ?><article class="admin-member"><div><b><?= e($member['full_name']) ?></b><small><?= e($member['email']) ?> · Joined <?= e(date('d M Y', strtotime($member['created_at']))) ?> · <?= e(ucfirst($member['role'])) ?></small></div><span class="status-pill <?= (int) $member['is_active'] === 1 ? 'status-active' : 'status-suspended' ?>"><?= (int) $member['is_active'] === 1 ? 'Active' : 'Suspended' ?></span>
      <?php if ($member['role'] === 'member'): ?><form method="post"><input type="hidden" name="csrf" value="<?= e(token()) ?>"><input type="hidden" name="user_id" value="<?= (int) $member['id'] ?>"><input type="hidden" name="action" value="<?= (int) $member['is_active'] === 1 ? 'suspend_member' : 'restore_member' ?>"><button class="<?= (int) $member['is_active'] === 1 ? 'danger-button' : 'secondary' ?>" <?= (int) $member['id'] === $me ? 'disabled' : '' ?>><?= (int) $member['is_active'] === 1 ? 'Suspend' : 'Restore' ?></button></form><?php endif; ?>
    </article><?php endforeach; ?></div>
  </section>

  <section class="admin-section"><div class="admin-section-heading"><div><h2>Recent admin activity</h2><p>Actions are recorded for accountability.</p></div></div>
    <?php if (!$audit): ?><p class="admin-empty">No admin actions have been recorded yet.</p><?php endif; ?>
    <div class="admin-audit-list"><?php foreach ($audit as $entry): ?><div><b><?= e($entry['action']) ?></b><small><?= e($entry['full_name']) ?> · Target #<?= (int) $entry['target_id'] ?> · <?= e($entry['details']) ?> · <?= e(date('d M Y H:i', strtotime($entry['created_at']))) ?></small></div><?php endforeach; ?></div>
  </section>
</section>
<?php foot(); ?>
