<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['student']);

$pageTitle  = 'My Attendance';
$activePage = 'attendance';
$pdo = db();

$sRow = $pdo->prepare("SELECT id FROM students WHERE user_id=? LIMIT 1");
$sRow->execute([$_SESSION['user_id']]);
$studId = (int)($sRow->fetchColumn() ?: 0);

$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year']  ?? date('Y'));
$month = max(1, min(12, $month));

$records = $pdo->query(
    "SELECT a.date, a.status, sb.name AS subject, cl.start_time, cl.end_time
     FROM attendance a
     JOIN classes cl ON cl.id=a.class_id
     JOIN subjects sb ON sb.id=cl.subject_id
     WHERE a.student_id=$studId AND MONTH(a.date)=$month AND YEAR(a.date)=$year
     ORDER BY a.date DESC, cl.start_time"
)->fetchAll();

$total = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE student_id=$studId AND MONTH(date)=$month AND YEAR(date)=$year")->fetchColumn();
$pres  = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE student_id=$studId AND status IN('present','late') AND MONTH(date)=$month AND YEAR(date)=$year")->fetchColumn();
$pct   = $total > 0 ? round($pres/$total*100) : 0;

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">My Attendance</h1>
    <p class="page-subtitle"><?= date('F Y', mktime(0,0,0,$month,1,$year)) ?></p>
  </div>
  <div class="page-header-actions">
    <?php
    $pm = $month==1?12:$month-1; $py=$month==1?$year-1:$year;
    $nm = $month==12?1:$month+1; $ny=$month==12?$year+1:$year;
    ?>
    <a href="?month=<?= $pm ?>&year=<?= $py ?>" class="btn btn-outline"><i class="ri-arrow-left-s-line"></i> Prev</a>
    <a href="?month=<?= date('n') ?>&year=<?= date('Y') ?>" class="btn btn-ghost">Today</a>
    <a href="?month=<?= $nm ?>&year=<?= $ny ?>" class="btn btn-outline">Next <i class="ri-arrow-right-s-line"></i></a>
  </div>
</div>

<!-- Summary Cards -->
<div class="stats-grid" style="margin-bottom:24px;">
  <div class="stat-card primary">
    <div class="stat-icon"><i class="ri-calendar-2-line"></i></div>
    <div class="stat-info"><div class="stat-value"><?= $total ?></div><div class="stat-label">Total Classes</div></div>
  </div>
  <div class="stat-card success">
    <div class="stat-icon"><i class="ri-checkbox-circle-line"></i></div>
    <div class="stat-info"><div class="stat-value"><?= $pres ?></div><div class="stat-label">Present</div></div>
  </div>
  <div class="stat-card danger">
    <div class="stat-icon"><i class="ri-close-circle-line"></i></div>
    <div class="stat-info"><div class="stat-value"><?= $total - $pres ?></div><div class="stat-label">Absent</div></div>
  </div>
  <div class="stat-card <?= $pct>=75?'success':'danger' ?>">
    <div class="stat-icon"><i class="ri-percent-line"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $pct ?>%</div>
      <div class="stat-label">Attendance Rate</div>
      <?php if ($pct < 75): ?>
      <div style="font-size:11px;color:var(--danger);margin-top:4px;font-weight:600;">
        <i class="ri-error-warning-line" style="vertical-align:middle;"></i> Below 75% minimum
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Attendance Table -->
<div class="table-wrapper">
  <?php if (empty($records)): ?>
  <div class="empty-state"><i class="ri-calendar-check-line"></i><h3>No Records</h3><p>No attendance data for this month.</p></div>
  <?php else: ?>
  <table>
    <thead><tr>
      <th class="sortable">Date</th><th>Subject</th><th>Time</th><th>Status</th>
    </tr></thead>
    <tbody>
      <?php foreach ($records as $r): ?>
      <tr>
        <td><?= date('d M Y', strtotime($r['date'])) ?></td>
        <td><?= e($r['subject']) ?></td>
        <td><?= date('g:i A', strtotime($r['start_time'])) ?> - <?= date('g:i A', strtotime($r['end_time'])) ?></td>
        <td>
          <span class="badge badge-<?= match($r['status']){ 'present'=>'success','absent'=>'danger','late'=>'warning',default=>'muted' } ?>">
            <?= ucfirst($r['status']) ?>
          </span>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
