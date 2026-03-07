<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin', 'teacher']);

$pageTitle  = 'Marks Entry';
$activePage = 'marks';
$pdo = db();

$courseId  = (int)($_GET['course_id']  ?? 0);
$subjectId = (int)($_GET['subject_id'] ?? 0);
$examType  = sanitize($_GET['exam_type'] ?? 'final');

$teacherFilter = '';
if (is_teacher()) {
    $tr = $pdo->prepare("SELECT id FROM teachers WHERE user_id=? LIMIT 1");
    $tr->execute([$_SESSION['user_id']]);
    $tid = (int)($tr->fetchColumn() ?: 0);
    $teacherFilter = " AND sb.teacher_id = $tid";
}

$subjects = $pdo->query(
    "SELECT sb.id, sb.name, sb.code, sb.max_marks, c.name AS course_name, c.id AS cid
     FROM subjects sb
     JOIN courses c ON c.id = sb.course_id
     WHERE 1=1 $teacherFilter
     ORDER BY c.name, sb.name"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    verify_csrf();
    $pid    = (int)($_POST['subject_id'] ?? 0);
    $pExam  = sanitize($_POST['exam_type'] ?? 'final');
    $pDate  = sanitize($_POST['exam_date'] ?? date('Y-m-d'));
    $marksArr = $_POST['marks'] ?? [];

    $allowed_exams = ['midterm','final','assignment','quiz','practical'];
    if (!in_array($pExam, $allowed_exams)) { set_toast('error','Invalid exam type.'); goto redirect; }

    $subRow = $pdo->prepare("SELECT max_marks FROM subjects WHERE id=? LIMIT 1");
    $subRow->execute([$pid]);
    $maxM = (float)($subRow->fetchColumn() ?: 100);

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            "INSERT INTO marks (student_id, subject_id, exam_type, marks_obtained, max_marks, grade, entered_by, exam_date)
             VALUES (?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE marks_obtained=VALUES(marks_obtained), grade=VALUES(grade), entered_by=VALUES(entered_by), exam_date=VALUES(exam_date)"
        );
        foreach ($marksArr as $studId => $marksObt) {
            $marksObt = (float)$marksObt;
            if ($marksObt < 0) $marksObt = 0;
            if ($marksObt > $maxM) $marksObt = $maxM;
            $pct   = $maxM > 0 ? ($marksObt / $maxM * 100) : 0;
            $grade = calculate_grade($pct);
            $stmt->execute([(int)$studId, $pid, $pExam, $marksObt, $maxM, $grade, $_SESSION['user_id'], $pDate]);
        }
        $pdo->commit();
        log_activity('enter_marks','marks',"Saved $pExam marks for subject $pid");
        set_toast('success','Marks saved successfully!');
    } catch (PDOException $e) {
        $pdo->rollBack();
        set_toast('error','Failed to save marks.');
    }
    redirect:
    header("Location: " . IMS_URL . "/modules/marks/index.php?subject_id=$pid&exam_type=$pExam");
    exit;
}

$students = [];
$subjectInfo = null;
if ($subjectId) {
    $si = $pdo->prepare("SELECT sb.*, c.id AS cid FROM subjects sb JOIN courses c ON c.id=sb.course_id WHERE sb.id=? LIMIT 1");
    $si->execute([$subjectId]);
    $subjectInfo = $si->fetch();
    if ($subjectInfo) {
        $students = $pdo->query(
            "SELECT s.id, u.full_name, u.profile_photo, s.student_id AS sid,
                    (SELECT m.marks_obtained FROM marks m WHERE m.student_id=s.id AND m.subject_id=$subjectId AND m.exam_type='$examType' LIMIT 1) AS marks_obtained,
                    (SELECT m.grade FROM marks m WHERE m.student_id=s.id AND m.subject_id=$subjectId AND m.exam_type='$examType' LIMIT 1) AS grade
             FROM students s
             JOIN users u ON u.id=s.user_id
             JOIN enrollments e ON e.student_id=s.id
             WHERE e.course_id={$subjectInfo['cid']} AND s.status='active'
             ORDER BY u.full_name"
        )->fetchAll();
    }
}

$examLabels = ['midterm'=>'Mid-Term','final'=>'Final','assignment'=>'Assignment','quiz'=>'Quiz','practical'=>'Practical'];
$examColors = ['midterm'=>'#2563EB','final'=>'#7C3AED','assignment'=>'#059669','quiz'=>'#D97706','practical'=>'#DC2626'];

include __DIR__ . '/../../includes/header.php';
?>

<style>
/* ===== MARKS PAGE ===== */
.mk-hero { background:linear-gradient(135deg,#0F172A 0%,#312E81 55%,#1E293B 100%); border-radius:20px; padding:28px 36px; margin-bottom:26px; position:relative; overflow:hidden; }
.mk-hero::before { content:''; position:absolute; width:360px; height:360px; background:radial-gradient(circle,rgba(99,102,241,.22) 0%,transparent 70%); top:-100px; right:-30px; pointer-events:none; }
.mk-hero-inner { position:relative; z-index:1; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; }
.mk-hero h1 { font-family:var(--font-heading); font-size:28px; font-weight:800; color:#fff; margin:0 0 4px; }
.mk-hero p  { font-size:13px; color:rgba(255,255,255,.5); margin:0; }

.mk-filter-card { background:var(--bg-card); border:1px solid var(--border); border-radius:16px; padding:20px 24px; margin-bottom:22px; box-shadow:var(--shadow-sm); }
.mk-filter-card label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--text-muted); display:block; margin-bottom:6px; }
.mk-select { padding:9px 14px; border:1.5px solid var(--border); border-radius:10px; background:var(--bg-card); color:var(--text-primary); font-size:13px; outline:none; font-family:inherit; width:100%; transition:all .2s; }
.mk-select:focus { border-color:#6366F1; box-shadow:0 0 0 3px rgba(99,102,241,.12); }
.mk-input { padding:9px 14px; border:1.5px solid var(--border); border-radius:10px; background:var(--bg-card); color:var(--text-primary); font-size:13px; outline:none; font-family:inherit; transition:all .2s; }
.mk-input:focus { border-color:#6366F1; box-shadow:0 0 0 3px rgba(99,102,241,.12); }
.mk-btn-load { display:inline-flex; align-items:center; gap:7px; padding:10px 22px; border-radius:10px; background:#6366F1; color:#fff; font-size:13px; font-weight:700; border:none; cursor:pointer; box-shadow:0 4px 12px rgba(99,102,241,.4); transition:opacity .2s; }
.mk-btn-load:hover { opacity:.88; }

/* Exam type tabs */
.mk-exam-tabs { display:flex; gap:6px; flex-wrap:wrap; }
.mk-exam-tab { padding:7px 16px; border-radius:20px; font-size:12px; font-weight:700; cursor:pointer; border:1.5px solid var(--border); background:var(--bg-card); color:var(--text-muted); transition:all .18s; text-decoration:none; }
.mk-exam-tab:hover { background:var(--bg-hover); color:var(--text-primary); }
.mk-exam-tab.active { background:var(--primary-light); color:#fff; border-color:var(--primary-light); box-shadow:0 3px 10px rgba(37,99,235,.35); }

.mk-sheet-card { background:var(--bg-card); border:1px solid var(--border); border-radius:18px; overflow:hidden; box-shadow:var(--shadow-md); }
.mk-sheet-head { display:flex; align-items:center; justify-content:space-between; padding:18px 22px; border-bottom:1px solid var(--border); background:var(--bg); flex-wrap:wrap; gap:10px; }
[data-theme="dark"] .mk-sheet-head { background:var(--bg-hover); }
.mk-sheet-title { font-size:15px; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:8px; }
.mk-sheet-title i { color:#6366F1; }
.mk-exam-badge { display:inline-flex; align-items:center; gap:6px; padding:5px 13px; border-radius:20px; font-size:12px; font-weight:700; }

table.mk-table { width:100%; border-collapse:collapse; }
table.mk-table thead tr { border-bottom:1px solid var(--border); }
table.mk-table thead th { padding:11px 16px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--text-muted); text-align:left; }
table.mk-table tbody tr { border-bottom:1px solid var(--border); transition:background .15s; }
table.mk-table tbody tr:last-child { border-bottom:none; }
table.mk-table tbody tr:hover { background:var(--bg-hover); }
table.mk-table tbody td { padding:12px 16px; font-size:13px; vertical-align:middle; }

.mk-avatar { width:34px; height:34px; border-radius:50%; object-fit:cover; border:2px solid var(--border); }
.mk-initials { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,#6366F1,#7C3AED); color:#fff; font-weight:700; font-size:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.mk-stu-name { font-weight:600; font-size:13px; }
.mk-sid { font-size:11px; font-family:monospace; background:var(--bg-hover); padding:2px 7px; border-radius:5px; color:var(--text-muted); }
.mk-max-badge { font-size:12px; font-weight:600; color:var(--text-muted); }

.marks-input { width:110px; padding:8px 12px; border:1.5px solid var(--border); border-radius:8px; background:var(--bg-card); color:var(--text-primary); font-size:14px; font-weight:700; outline:none; font-family:inherit; transition:all .2s; text-align:center; }
.marks-input:focus { border-color:#6366F1; box-shadow:0 0 0 3px rgba(99,102,241,.12); }

.grade-pill { display:inline-block; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:800; }
.grade-pill.A  { background:var(--success-light); color:var(--success-dark); }
.grade-pill.Ap { background:var(--success-light); color:var(--success-dark); }
.grade-pill.Bp { background:var(--info-light);    color:var(--primary); }
.grade-pill.B  { background:var(--info-light);    color:var(--primary); }
.grade-pill.C  { background:var(--warning-light); color:var(--warning-dark); }
.grade-pill.D  { background:var(--warning-light); color:var(--warning-dark); }
.grade-pill.F  { background:var(--danger-light);  color:var(--danger-dark); }

.mk-sheet-foot { padding:16px 22px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:10px; }
.mk-save-btn { display:inline-flex; align-items:center; gap:7px; padding:10px 26px; border-radius:10px; background:linear-gradient(135deg,#6366F1,#7C3AED); color:#fff; font-size:14px; font-weight:700; border:none; cursor:pointer; box-shadow:0 4px 14px rgba(99,102,241,.35); transition:opacity .2s,transform .15s; }
.mk-save-btn:hover { opacity:.9; transform:scale(.98); }

.mk-placeholder { padding:70px 20px; text-align:center; }
.mk-placeholder i { font-size:48px; color:var(--border-strong); display:block; margin:0 auto 14px; }
.mk-placeholder h3 { font-size:18px; font-weight:700; margin:0 0 6px; }
.mk-placeholder p  { font-size:13px; color:var(--text-muted); margin:0; }
</style>

<!-- HERO -->
<div class="mk-hero">
  <div class="mk-hero-inner">
    <div>
      <h1><i class="ri-bar-chart-2-line" style="margin-right:10px; color:#A5B4FC;"></i>Marks Entry</h1>
      <p>Enter and manage student marks by subject and examination type.</p>
    </div>
    <div style="position:relative; z-index:1;">
      <div class="mk-exam-tabs">
        <?php foreach ($examLabels as $val => $lbl): ?>
        <?php $color = $examColors[$val] ?? var(--primary-light); ?>
        <a href="?subject_id=<?= $subjectId ?>&exam_type=<?= $val ?>"
           class="mk-exam-tab <?= $examType === $val ? 'active' : '' ?>"
           style="<?= $examType === $val ? "background:{$color}; border-color:{$color}; box-shadow:0 3px 10px {$color}55;" : '' ?>"><?= $lbl ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- FILTER CARD -->
<div class="mk-filter-card">
  <form method="GET" style="display:flex; gap:18px; align-items:flex-end; flex-wrap:wrap;">
    <input type="hidden" name="exam_type" value="<?= e($examType) ?>">
    <div style="flex:1; min-width:280px;">
      <label>Subject</label>
      <select name="subject_id" class="mk-select">
        <option value="">— Select Subject —</option>
        <?php foreach ($subjects as $sb): ?>
        <option value="<?= $sb['id'] ?>" <?= $subjectId==$sb['id']?'selected':'' ?>><?= e($sb['course_name']) ?> › <?= e($sb['name']) ?> (<?= e($sb['code']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <button type="submit" class="mk-btn-load"><i class="ri-filter-3-line"></i> Load</button>
    </div>
  </form>
</div>

<?php if ($subjectId && !empty($students)): ?>
<div class="mk-sheet-card">
  <div class="mk-sheet-head">
    <div class="mk-sheet-title">
      <i class="ri-bar-chart-2-line"></i>
      <?= e($subjectInfo['name'] ?? '') ?>
    </div>
    <div style="display:flex; gap:8px; align-items:center;">
      <span class="mk-exam-badge" style="background:<?= $examColors[$examType] ?? '#6366F1' ?>20; color:<?= $examColors[$examType] ?? '#6366F1' ?>; border:1px solid <?= $examColors[$examType] ?? '#6366F1' ?>30;">
        <?= $examLabels[$examType] ?? $examType ?> Exam
      </span>
      <span class="mk-max-badge">Max: <?= $subjectInfo['max_marks'] ?> marks</span>
    </div>
  </div>

  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="save_marks" value="1">
    <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
    <input type="hidden" name="exam_type" value="<?= e($examType) ?>">

    <div style="padding:16px 22px; display:flex; align-items:center; gap:14px; border-bottom:1px solid var(--border);">
      <label style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--text-muted); margin:0;">Exam Date</label>
      <input type="date" name="exam_date" class="mk-input" value="<?= date('Y-m-d') ?>" style="width:auto;">
    </div>

    <div style="overflow-x:auto;">
      <table class="mk-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Student</th>
            <th>ID</th>
            <th>Marks (/ <?= $subjectInfo['max_marks'] ?>)</th>
            <th>Grade</th>
            <th>Percentage</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($students as $idx => $s): ?>
          <tr>
            <td style="color:var(--text-muted); font-size:12px;"><?= $idx+1 ?></td>
            <td>
              <div style="display:flex; align-items:center; gap:10px;">
                <?php if (!empty($s['profile_photo'])): ?>
                  <img src="<?= IMS_URL ?>/uploads/<?= e($s['profile_photo']) ?>" class="mk-avatar" alt="">
                <?php else: ?>
                  <div class="mk-initials"><?= strtoupper(substr($s['full_name'],0,1)) ?></div>
                <?php endif; ?>
                <span class="mk-stu-name"><?= e($s['full_name']) ?></span>
              </div>
            </td>
            <td><span class="mk-sid"><?= e($s['sid']) ?></span></td>
            <td>
              <input type="number" name="marks[<?= $s['id'] ?>]" class="marks-input"
                     min="0" max="<?= $subjectInfo['max_marks'] ?>" step="0.5"
                     value="<?= $s['marks_obtained'] !== null ? e($s['marks_obtained']) : '' ?>"
                     data-max="<?= $subjectInfo['max_marks'] ?>" oninput="calcGrade(this)"
                     placeholder="—">
            </td>
            <td class="grade-cell">
              <?php if ($s['grade'] !== null):
                $gKey = str_replace('+','p',$s['grade']);
              ?>
                <span class="grade-pill <?= $gKey ?>"><?= e($s['grade']) ?></span>
              <?php else: echo '<span style="color:var(--text-muted);">—</span>'; endif; ?>
            </td>
            <td class="pct-cell" style="font-weight:600; color:var(--text-secondary);">
              <?= $s['marks_obtained'] !== null && $subjectInfo['max_marks'] > 0
                ? round($s['marks_obtained'] / $subjectInfo['max_marks'] * 100, 1) . '%' : '—' ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="mk-sheet-foot">
      <a href="<?= IMS_URL ?>/modules/marks/index.php" class="btn btn-outline" style="border-radius:10px;">Cancel</a>
      <button type="submit" class="mk-save-btn"><i class="ri-save-line"></i> Save Marks</button>
    </div>
  </form>
</div>

<script>
function calcGrade(input) {
  const val = parseFloat(input.value);
  const max = parseFloat(input.dataset.max) || 100;
  const row = input.closest('tr');
  const gradeCell = row.querySelector('.grade-cell');
  const pctCell   = row.querySelector('.pct-cell');
  if (isNaN(val) || val < 0) return;
  const pct = (val / max) * 100;
  let grade = 'F';
  if(pct>=90)grade='A+';else if(pct>=80)grade='A';else if(pct>=70)grade='B+';else if(pct>=60)grade='B';else if(pct>=50)grade='C';else if(pct>=40)grade='D';
  const gKey = grade.replace('+','p');
  gradeCell.innerHTML = `<span class="grade-pill ${gKey}">${grade}</span>`;
  pctCell.textContent = pct.toFixed(1) + '%';
}
</script>

<?php elseif ($subjectId): ?>
<div class="mk-sheet-card">
  <div class="mk-placeholder">
    <i class="ri-bar-chart-2-line"></i>
    <h3>No Students Found</h3>
    <p>No enrolled students found for this subject.</p>
  </div>
</div>
<?php else: ?>
<div class="mk-sheet-card">
  <div class="mk-placeholder">
    <i class="ri-bar-chart-2-line"></i>
    <h3>Select a Subject</h3>
    <p>Choose a subject and exam type above to enter marks.</p>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
