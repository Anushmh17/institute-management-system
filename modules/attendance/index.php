<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin', 'teacher']);

$pageTitle  = 'Attendance';
$activePage = 'attendance';
$pdo = db();

$date     = sanitize($_GET['date']     ?? date('Y-m-d'));
$classId  = (int)($_GET['class_id']   ?? 0);

$dayOfWeek = date('l', strtotime($date));

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

<style>
/* ===== ATTENDANCE PAGE ===== */
.att-hero { background:linear-gradient(135deg,#0F172A 0%,#065F46 40%,#0F172A 100%); border-radius:20px; padding:28px 36px; margin-bottom:26px; position:relative; overflow:hidden; }
.att-hero::before { content:''; position:absolute; width:350px; height:350px; background:radial-gradient(circle,rgba(16,185,129,.2) 0%,transparent 70%); top:-90px; right:-30px; pointer-events:none; }
.att-hero-inner { position:relative; z-index:1; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; }
.att-hero h1 { font-family:var(--font-heading); font-size:28px; font-weight:800; color:#fff; margin:0 0 4px; }
.att-hero p  { font-size:13px; color:rgba(255,255,255,.5); margin:0; }

.att-filter-card { background:var(--bg-card); border:1px solid var(--border); border-radius:16px; padding:20px 24px; margin-bottom:22px; box-shadow:var(--shadow-sm); }
.att-filter-card label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--text-muted); display:block; margin-bottom:6px; }
.att-input { padding:9px 14px; border:1.5px solid var(--border); border-radius:10px; background:var(--bg-card); color:var(--text-primary); font-size:13px; outline:none; font-family:inherit; transition:all .2s; }
.att-input:focus { border-color:var(--success); box-shadow:0 0 0 3px rgba(16,185,129,.12); }
.att-select { padding:9px 14px; border:1.5px solid var(--border); border-radius:10px; background:var(--bg-card); color:var(--text-primary); font-size:13px; outline:none; font-family:inherit; width:100%; transition:all .2s; }
.att-select:focus { border-color:var(--success); box-shadow:0 0 0 3px rgba(16,185,129,.12); }
.att-btn-load { display:inline-flex; align-items:center; gap:7px; padding:10px 22px; border-radius:10px; background:var(--success); color:#fff; font-size:13px; font-weight:700; border:none; cursor:pointer; box-shadow:0 4px 12px rgba(16,185,129,.35); transition:opacity .2s; }
.att-btn-load:hover { opacity:.88; }

.att-sheet-card { background:var(--bg-card); border:1px solid var(--border); border-radius:18px; overflow:hidden; box-shadow:var(--shadow-md); }
.att-sheet-head { display:flex; align-items:center; justify-content:space-between; padding:18px 22px; border-bottom:1px solid var(--border); background:var(--bg); flex-wrap:wrap; gap:10px; }
[data-theme="dark"] .att-sheet-head { background:var(--bg-hover); }
.att-sheet-title { font-size:15px; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:8px; }
.att-sheet-title i { color:var(--success); }
.att-date-badge { display:inline-flex; align-items:center; gap:6px; padding:5px 13px; border-radius:20px; background:var(--success-light); color:var(--success-dark); font-size:12px; font-weight:700; }

/* Summary bar */
.att-summary { display:flex; gap:20px; padding:14px 22px; background:var(--bg); border-bottom:1px solid var(--border); flex-wrap:wrap; }
[data-theme="dark"] .att-summary { background:rgba(0,0,0,.1); }
.att-stat { display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; }
.att-stat-num { font-size:20px; font-weight:800; line-height:1; }
.att-stat.present .att-stat-num { color:var(--success); }
.att-stat.absent  .att-stat-num { color:var(--danger); }
.att-stat.late    .att-stat-num { color:var(--warning-dark); }
.att-stat.excused .att-stat-num { color:var(--text-muted); }
.att-stat-lbl { font-size:11px; color:var(--text-muted); font-weight:500; }

/* Attendance table */
table.att-table { width:100%; border-collapse:collapse; }
table.att-table thead tr { border-bottom:1px solid var(--border); }
table.att-table thead th { padding:11px 16px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--text-muted); text-align:left; }
table.att-table thead th.status-th { text-align:center; }
table.att-table tbody tr { border-bottom:1px solid var(--border); transition:background .15s; }
table.att-table tbody tr:last-child { border-bottom:none; }
table.att-table tbody tr:hover { background:var(--bg-hover); }
table.att-table tbody td { padding:12px 16px; font-size:13px; vertical-align:middle; }
table.att-table tbody td.status-td { text-align:center; }

.att-radio-label { cursor:pointer; display:flex; justify-content:center; align-items:center; }
.att-radio-label input[type="radio"] { display:none; }
.att-radio-custom { width:30px; height:30px; border-radius:50%; border:2px solid var(--border); display:flex; align-items:center; justify-content:center; font-size:14px; transition:all .15s; cursor:pointer; }
.att-radio-label input[type="radio"]:checked + .att-radio-custom { border-color:currentColor; }
.att-radio-custom.present-rb { color:var(--success); }
.att-radio-custom.absent-rb  { color:var(--danger); }
.att-radio-custom.late-rb    { color:var(--warning-dark); }
.att-radio-custom.excused-rb { color:var(--text-muted); }
.att-radio-label input[type="radio"]:checked + .att-radio-custom.present-rb { background:var(--success-light); }
.att-radio-label input[type="radio"]:checked + .att-radio-custom.absent-rb  { background:var(--danger-light); }
.att-radio-label input[type="radio"]:checked + .att-radio-custom.late-rb    { background:var(--warning-light); }
.att-radio-label input[type="radio"]:checked + .att-radio-custom.excused-rb { background:var(--bg-hover); }

.att-avatar { width:34px; height:34px; border-radius:50%; object-fit:cover; border:2px solid var(--border); }
.att-initials { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,#059669,#0284C7); color:#fff; font-weight:700; font-size:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.att-stu-name { font-weight:600; font-size:13px; }
.att-sid { font-size:11px; font-family:monospace; background:var(--bg-hover); padding:2px 7px; border-radius:5px; color:var(--text-muted); }

.att-sheet-foot { padding:16px 22px; border-top:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
.att-mark-btns { display:flex; gap:8px; }
.att-mark-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:8px; font-size:12px; font-weight:600; border:1.5px solid var(--border); background:var(--bg-card); color:var(--text-secondary); cursor:pointer; transition:all .18s; }
.att-mark-btn:hover { background:var(--bg-hover); border-color:var(--border-strong); color:var(--text-primary); }
.att-save-btn { display:inline-flex; align-items:center; gap:7px; padding:10px 26px; border-radius:10px; background:linear-gradient(135deg,#059669,#0284C7); color:#fff; font-size:14px; font-weight:700; border:none; cursor:pointer; box-shadow:0 4px 14px rgba(5,150,105,.3); transition:opacity .2s,transform .15s; }
.att-save-btn:hover { opacity:.9; transform:scale(.98); }

.att-placeholder { padding:70px 20px; text-align:center; }
.att-placeholder i { font-size:48px; color:var(--border-strong); display:block; margin:0 auto 14px; }
.att-placeholder h3 { font-size:18px; font-weight:700; margin:0 0 6px; }
.att-placeholder p  { font-size:13px; color:var(--text-muted); margin:0; }
</style>

<!-- HERO -->
<div class="att-hero">
  <div class="att-hero-inner">
    <div>
      <h1><i class="ri-user-follow-line" style="margin-right:10px; color:#34D399;"></i>Attendance</h1>
      <p>Mark and manage daily student attendance by class session.</p>
    </div>
    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; position:relative; z-index:1;">
      <div style="text-align:center;">
        <div style="font-family:var(--font-heading); font-size:22px; font-weight:800; color:#fff;"><?= date('D, d M') ?></div>
        <div style="font-size:11px; color:rgba(255,255,255,.5); text-transform:uppercase; letter-spacing:.8px;"><?= $dayOfWeek ?></div>
      </div>
    </div>
  </div>
</div>

<!-- FILTER CARD -->
<div class="att-filter-card">
  <form method="GET" style="display:flex; gap:18px; align-items:flex-end; flex-wrap:wrap;">
    <div>
      <label>Date</label>
      <input type="date" name="date" class="att-input" value="<?= e($date) ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
    </div>
    <div style="flex:1; min-width:240px;">
      <label>Class Session — <?= $dayOfWeek ?></label>
      <select name="class_id" class="att-select">
        <option value="">— Select a Class —</option>
        <?php foreach ($classes as $cl): ?>
        <option value="<?= $cl['id'] ?>" <?= $classId==$cl['id']?'selected':'' ?>>
          <?= e($cl['subject_name']) ?> · <?= date('g:i A', strtotime($cl['start_time'])) ?> · <?= e($cl['course_name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <button type="submit" class="att-btn-load"><i class="ri-calendar-check-line"></i> Load Class</button>
    </div>
  </form>
</div>

<?php if ($classId && !empty($students)): ?>

<?php
$pStats = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
foreach ($students as $s) {
    $st = $s['att_status'] ?? 'absent';
    if (isset($pStats[$st])) $pStats[$st]++;
}
?>

<div class="att-sheet-card">
  <div class="att-sheet-head">
    <div class="att-sheet-title">
      <i class="ri-user-follow-line"></i>
      Attendance Sheet
    </div>
    <span class="att-date-badge"><i class="ri-calendar-line"></i><?= date('d F Y', strtotime($date)) ?></span>
  </div>

  <!-- Summary -->
  <div class="att-summary">
    <div class="att-stat present">
      <span class="att-stat-num" id="count-present"><?= $pStats['present'] ?></span>
      <div><div class="att-stat-lbl">Present</div></div>
    </div>
    <div class="att-stat absent">
      <span class="att-stat-num" id="count-absent"><?= $pStats['absent'] ?></span>
      <div><div class="att-stat-lbl">Absent</div></div>
    </div>
    <div class="att-stat late">
      <span class="att-stat-num" id="count-late"><?= $pStats['late'] ?></span>
      <div><div class="att-stat-lbl">Late</div></div>
    </div>
    <div class="att-stat excused">
      <span class="att-stat-num" id="count-excused"><?= $pStats['excused'] ?></span>
      <div><div class="att-stat-lbl">Excused</div></div>
    </div>
    <div style="margin-left:auto; font-size:12px; color:var(--text-muted); align-self:center;">
      <?= count($students) ?> students total
    </div>
  </div>

  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="save_attendance" value="1">
    <input type="hidden" name="class_id" value="<?= $classId ?>">
    <input type="hidden" name="att_date"  value="<?= e($date) ?>">

    <div style="overflow-x:auto;">
      <table class="att-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Student</th>
            <th>Student ID</th>
            <th class="status-th" style="color:var(--success);">Present</th>
            <th class="status-th" style="color:var(--danger);">Absent</th>
            <th class="status-th" style="color:var(--warning-dark);">Late</th>
            <th class="status-th" style="color:var(--text-muted);">Excused</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($students as $idx => $s):
            $cur = $s['att_status'] ?? 'absent';
          ?>
          <tr class="att-row" data-status="<?= $cur ?>">
            <td style="color:var(--text-muted); font-size:12px;"><?= $idx + 1 ?></td>
            <td>
              <div style="display:flex; align-items:center; gap:10px;">
                <?php if (!empty($s['profile_photo'])): ?>
                  <img src="<?= IMS_URL ?>/uploads/<?= e($s['profile_photo']) ?>" class="att-avatar" alt="">
                <?php else: ?>
                  <div class="att-initials"><?= strtoupper(substr($s['full_name'], 0, 1)) ?></div>
                <?php endif; ?>
                <span class="att-stu-name"><?= e($s['full_name']) ?></span>
              </div>
            </td>
            <td><span class="att-sid"><?= e($s['sid']) ?></span></td>
            <?php foreach (['present','absent','late','excused'] as $val):
              $rbClass = $val . '-rb';
              $icons = ['present'=>'ri-check-line','absent'=>'ri-close-line','late'=>'ri-time-line','excused'=>'ri-question-line'];
            ?>
            <td class="status-td">
              <label class="att-radio-label">
                <input type="radio" name="status[<?= $s['student_id'] ?>]" value="<?= $val ?>"
                       <?= $cur === $val ? 'checked' : '' ?> class="att-radio" data-status="<?= $val ?>">
                <span class="att-radio-custom <?= $rbClass ?>"><i class="<?= $icons[$val] ?>"></i></span>
              </label>
            </td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="att-sheet-foot">
      <div class="att-mark-btns">
        <button type="button" class="att-mark-btn" id="markAllPresent"><i class="ri-checkbox-circle-line" style="color:var(--success);"></i> All Present</button>
        <button type="button" class="att-mark-btn" id="markAllAbsent"><i class="ri-close-circle-line" style="color:var(--danger);"></i> All Absent</button>
      </div>
      <div style="display:flex; gap:10px;">
        <a href="<?= IMS_URL ?>/modules/attendance/index.php" class="btn btn-outline" style="border-radius:10px;">Cancel</a>
        <button type="submit" class="att-save-btn"><i class="ri-save-line"></i> Save Attendance</button>
      </div>
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
<div class="att-sheet-card">
  <div class="att-placeholder">
    <i class="ri-user-follow-line"></i>
    <h3>No Students Enrolled</h3>
    <p>No active students found for this class session.</p>
  </div>
</div>
<?php else: ?>
<div class="att-sheet-card">
  <div class="att-placeholder">
    <i class="ri-calendar-check-line"></i>
    <h3>Select a Class Session</h3>
    <p>Choose a date and class above to start marking attendance.</p>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
