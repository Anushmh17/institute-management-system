<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin', 'teacher']);

$pageTitle  = 'Attendance';
$activePage = 'attendance';
$pdo = db();

$date     = sanitize($_GET['date']     ?? date('Y-m-d'));
$classId  = (int)($_GET['class_id']   ?? 0);
$courseId = (int)($_GET['course_id']  ?? 0);

// Get classes for selected date/day
$dayOfWeek = date('l', strtotime($date));

// Teacher filter
$teacherFilter = '';
if (is_teacher()) {
    $tr = $pdo->prepare("SELECT id FROM teachers WHERE user_id=? LIMIT 1");
    $tr->execute([$_SESSION['user_id']]);
    $tid = (int)($tr->fetchColumn() ?: 0);
    $teacherFilter = " AND cl.teacher_id = $tid";
}

$classes = $pdo->query(
    "SELECT cl.id, cl.room, cl.start_time, cl.end_time, cl.type,
            sb.name AS subject_name, c.name AS course_name
     FROM classes cl
     JOIN subjects sb ON sb.id = cl.subject_id
     JOIN courses c ON c.id  = sb.course_id
     WHERE cl.day_of_week = '$dayOfWeek' AND cl.status='scheduled' $teacherFilter
     ORDER BY cl.start_time"
)->fetchAll();

// Handle POST - save attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    verify_csrf();
    $postClassId = (int)($_POST['class_id'] ?? 0);
    $postDate    = sanitize($_POST['att_date'] ?? date('Y-m-d'));
    $statuses    = $_POST['status'] ?? [];

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            "INSERT INTO attendance (student_id, class_id, date, status, marked_by)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status=VALUES(status), marked_by=VALUES(marked_by)"
        );
        foreach ($statuses as $studId => $status) {
            $allowed = ['present','absent','late','excused'];
            if (!in_array($status, $allowed)) continue;
            $stmt->execute([(int)$studId, $postClassId, $postDate, $status, $_SESSION['user_id']]);
        }
        $pdo->commit();
        log_activity('mark_attendance','attendance',"Marked for class $postClassId on $postDate");
        set_toast('success','Attendance saved successfully!');
        header("Location: " . IMS_URL . "/modules/attendance/index.php?date=$postDate&class_id=$postClassId");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        set_toast('error','Failed to save attendance.');
    }
}

// Load students for selected class
$students = [];
if ($classId) {
    $classInfo = $pdo->prepare("SELECT cl.*, sb.course_id FROM classes cl JOIN subjects sb ON sb.id=cl.subject_id WHERE cl.id=?");
    $classInfo->execute([$classId]);
    $selectedClass = $classInfo->fetch();

    if ($selectedClass) {
        $studStmt = $pdo->query(
            "SELECT s.id AS student_id, u.full_name, u.profile_photo, s.student_id AS sid,
                    (SELECT att.status FROM attendance att WHERE att.student_id=s.id AND att.class_id=$classId AND att.date='$date' LIMIT 1) AS att_status
             FROM students s
             JOIN users u ON u.id=s.user_id
             JOIN enrollments e ON e.student_id=s.id
             WHERE e.course_id={$selectedClass['course_id']} AND s.status='active'
             ORDER BY u.full_name"
        );
        $students = $studStmt->fetchAll();
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Attendance</h1>
    <p class="page-subtitle">Mark and review student attendance</p>
  </div>
</div>

<!-- Filter Form -->
<div class="card mb-6" style="margin-bottom:24px;">
  <div class="card-body">
    <form method="GET" class="d-flex gap-3 align-center" style="flex-wrap:wrap;">
      <div class="form-group" style="margin:0; width: 160px;">
        <label class="form-label">Date</label>
        <input type="date" name="date" class="form-control" value="<?= e($date) ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
      </div>
      <div class="form-group" style="margin:0; width: 320px;">
        <label class="form-label">Class (<?= $dayOfWeek ?>)</label>
        <select name="class_id" class="form-control">
          <option value="">-- Select Class --</option>
          <?php foreach ($classes as $cl): ?>
          <option value="<?= $cl['id'] ?>" <?= $classId==$cl['id']?'selected':'' ?>>
            <?= e($cl['subject_name']) ?> <span class="divider"></span> <?= date('g:i A', strtotime($cl['start_time'])) ?>
            (<?= e($cl['course_name']) ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0; align-self:flex-end;">
        <button type="submit" class="btn btn-primary"><i class="ri-filter-3-line"></i> Load Class</button>
      </div>
    </form>
  </div>
</div>

<?php if ($classId && !empty($students)): ?>
<!-- Attendance Sheet -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">
      <i class="ri-user-follow-line"></i>
      Attendance Sheet <i class="ri-arrow-right-s-line"></i> <?= date('d F Y', strtotime($date)) ?>
    </h3>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-outline btn-sm" id="markAllPresent">
        <i class="ri-checkbox-circle-line"></i> All Present
      </button>
      <button type="button" class="btn btn-outline btn-sm" id="markAllAbsent">
        <i class="ri-close-circle-line"></i> All Absent
      </button>
    </div>
  </div>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="save_attendance" value="1">
    <input type="hidden" name="class_id" value="<?= $classId ?>">
    <input type="hidden" name="att_date"  value="<?= e($date) ?>">

    <div style="padding:0;">
      <?php
      $pStats = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
      foreach ($students as $s) {
          $st = $s['att_status'] ?? 'absent';
          if (isset($pStats[$st])) $pStats[$st]++;
      }
      ?>
      <!-- Summary Row -->
      <div style="display:flex;gap:16px;padding:16px 20px;background:var(--bg);border-bottom:1px solid var(--border);flex-wrap:wrap;">
        <?php foreach ([['present','success','checkbox-circle'],['absent','danger','close-circle'],['late','warning','time'],['excused','muted','question']] as [$k,$cl,$ico]): ?>
        <div style="display:flex;align-items:center;gap:8px;font-size:13px;">
          <i class="ri-<?= $ico ?>-fill" style="color:var(--<?= $cl==='muted'?'text-muted':$cl ?>);font-size:18px;"></i>
          <span id="count-<?= $k ?>"><?= $pStats[$k] ?></span> <?= ucfirst($k) ?>
        </div>
        <?php endforeach; ?>
      </div>

      <table>
        <thead><tr>
          <th>#</th><th>Student</th><th>ID</th>
          <th style="color:var(--success);">Present</th>
          <th style="color:var(--danger);">Absent</th>
          <th style="color:var(--warning);">Late</th>
          <th style="color:var(--text-muted);">Excused</th>
        </tr></thead>
        <tbody>
          <?php foreach ($students as $idx => $s): ?>
          <?php $cur = $s['att_status'] ?? 'absent'; ?>
          <tr class="att-row" data-status="<?= $cur ?>">
            <td style="color:var(--text-muted);font-size:12px;"><?= $idx + 1 ?></td>
            <td>
              <div class="d-flex align-center gap-2">
                <?php if (!empty($s['profile_photo'])): ?>
                  <img src="<?= IMS_URL ?>/uploads/<?= e($s['profile_photo']) ?>"
                       style="width:30px;height:30px;border-radius:50%;object-fit:cover;" alt="">
                <?php else: ?>
                  <div class="avatar-initials" style="width:30px;height:30px;font-size:11px;">
                    <?= strtoupper(substr($s['full_name'], 0, 1)) ?>
                  </div>
                <?php endif; ?>
                <strong style="font-size:13px;"><?= e($s['full_name']) ?></strong>
              </div>
            </td>
            <td><code style="font-size:11px;"><?= e($s['sid']) ?></code></td>
            <?php foreach (['present','absent','late','excused'] as $val): ?>
            <td style="text-align:center;">
              <label style="cursor:pointer;display:flex;justify-content:center;">
                <input type="radio" name="status[<?= $s['student_id'] ?>]"
                       value="<?= $val ?>"
                       <?= $cur === $val ? 'checked' : '' ?>
                       class="att-radio"
                       data-status="<?= $val ?>"
                       style="width:18px;height:18px;cursor:pointer;accent-color:var(--<?= $val==='present'?'success':($val==='absent'?'danger':($val==='late'?'warning':'text-muted')) ?>);">
              </label>
            </td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:12px;">
      <a href="<?= IMS_URL ?>/modules/attendance/index.php" class="btn btn-outline">Cancel</a>
      <button type="submit" class="btn btn-success">
        <i class="ri-save-line"></i> Save Attendance
      </button>
    </div>
  </form>
</div>

<script>
document.getElementById('markAllPresent')?.addEventListener('click',()=>markAll('present'));
document.getElementById('markAllAbsent')?.addEventListener('click',()=>markAll('absent'));
function markAll(status){
  document.querySelectorAll(`.att-radio[data-status="${status}"]`).forEach(r=>r.checked=true);
}
</script>

<?php elseif ($classId): ?>
<div class="empty-state">
  <i class="ri-user-follow-line"></i>
  <h3>No Students Enrolled</h3>
  <p>No active students found for this class.</p>
</div>
<?php else: ?>
<div class="card">
  <div class="card-body">
    <div class="empty-state" style="padding:48px 0;">
      <i class="ri-calendar-check-line"></i>
      <h3>Select a Class</h3>
      <p>Choose a date and class to mark attendance.</p>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
