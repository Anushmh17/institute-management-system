<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['student']);

$pageTitle  = 'My Marks';
$activePage = 'marks';
$pdo = db();

$sRow = $pdo->prepare("SELECT id FROM students WHERE user_id=? LIMIT 1");
$sRow->execute([$_SESSION['user_id']]);
$studId = (int)($sRow->fetchColumn() ?: 0);

$marks = $pdo->query(
    "SELECT m.*, sb.name AS subject, sb.max_marks AS sub_max, sb.pass_marks
     FROM marks m JOIN subjects sb ON sb.id=m.subject_id
     WHERE m.student_id=$studId
     ORDER BY sb.name, m.exam_type"
)->fetchAll();

// Group by subject
$grouped = [];
foreach ($marks as $m) {
    $grouped[$m['subject']][] = $m;
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">My Marks</h1>
    <p class="page-subtitle">View your academic performance</p>
  </div>
  <a href="<?= IMS_URL ?>/modules/reports/my.php" class="btn btn-outline"><i class="ri-file-chart-line"></i> View Report Card</a>
</div>

<?php if (empty($marks)): ?>
<div class="empty-state"><i class="ri-bar-chart-2-line"></i><h3>No Marks Yet</h3><p>Marks will appear here once entered by your teacher.</p></div>
<?php else: ?>

<?php foreach ($grouped as $subjectName => $rows): ?>
<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <h3 class="card-title"><i class="ri-book-2-line"></i> <?= e($subjectName) ?></h3>
  </div>
  <div class="card-body" style="padding:0;">
    <table>
      <thead><tr><th>Exam Type</th><th>Marks</th><th>Max</th><th>Pass</th><th>Percentage</th><th>Grade</th><th>Exam Date</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $m): ?>
        <?php $pct = $m['max_marks']>0 ? round($m['marks_obtained']/$m['max_marks']*100,1) : 0; ?>
        <tr>
          <td><span class="badge badge-muted"><?= ucfirst(e($m['exam_type'])) ?></span></td>
          <td><strong><?= e($m['marks_obtained']) ?></strong></td>
          <td><?= e($m['max_marks']) ?></td>
          <td><?= e($m['pass_marks']) ?></td>
          <td>
            <div class="d-flex align-center gap-2">
              <div class="progress" style="flex:1;width:80px;">
                <div class="progress-bar <?= $pct>=60?'success':($pct>=40?'warning':'danger') ?>" style="width:<?= $pct ?>%;"></div>
              </div>
              <span style="font-size:12px;min-width:36px;"><?= $pct ?>%</span>
            </div>
          </td>
          <td>
            <?php $gc = match($m['grade']??'') { 'A+','A'=>'success','B+','B'=>'primary','C','D'=>'warning','F'=>'danger',default=>'muted' }; ?>
            <span class="badge badge-<?= $gc ?>"><?= e($m['grade']??'--') ?></span>
          </td>
          <td style="font-size:12px;color:var(--text-muted);"><?= $m['exam_date'] ? date('d M Y',strtotime($m['exam_date'])) : '--' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
