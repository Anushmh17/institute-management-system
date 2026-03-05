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

// Teacher filter
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

// Handle POST - save marks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    verify_csrf();
    $pid    = (int)($_POST['subject_id'] ?? 0);
    $pExam  = sanitize($_POST['exam_type'] ?? 'final');
    $pDate  = sanitize($_POST['exam_date'] ?? date('Y-m-d'));
    $marksArr = $_POST['marks'] ?? [];

    $allowed_exams = ['midterm','final','assignment','quiz','practical'];
    if (!in_array($pExam, $allowed_exams)) { set_toast('error','Invalid exam type.'); goto redirect; }

    // Get max_marks for subject
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

// Load students for selected subject
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

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Marks Entry</h1>
    <p class="page-subtitle">Enter and manage student marks by subject & exam</p>
  </div>
</div>

<!-- Filter -->
<div class="card mb-6" style="margin-bottom:24px;">
  <div class="card-body">
    <form method="GET" class="d-flex gap-3 align-center" style="flex-wrap:wrap;">
      <div class="form-group" style="margin:0;min-width:280px;">
        <label class="form-label">Subject</label>
        <select name="subject_id" class="form-control">
          <option value="">-- Select Subject --</option>
          <?php foreach ($subjects as $sb): ?>
          <option value="<?= $sb['id'] ?>" <?= $subjectId==$sb['id']?'selected':'' ?>>
            <?= e($sb['course_name']) ?> <i class="ri-arrow-right-line" style="font-size:10px; opacity:0.5;"></i> <?= e($sb['name']) ?> (<?= e($sb['code']) ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;">
        <label class="form-label">Exam Type</label>
        <select name="exam_type" class="form-control">
          <?php foreach (['midterm'=>'Mid-Term','final'=>'Final','assignment'=>'Assignment','quiz'=>'Quiz','practical'=>'Practical'] as $val=>$lbl): ?>
          <option value="<?= $val ?>" <?= $examType===$val?'selected':'' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;align-self:flex-end;">
        <button type="submit" class="btn btn-primary"><i class="ri-filter-3-line"></i> Load</button>
      </div>
    </form>
  </div>
</div>

<?php if ($subjectId && !empty($students)): ?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title">
      <i class="ri-bar-chart-2-line"></i>
      <?= e($subjectInfo['name'] ?? '') ?> | <?= ucfirst($examType) ?> Exam
      <span class="badge badge-muted" style="margin-left:8px;">Max: <?= $subjectInfo['max_marks'] ?></span>
    </h3>
  </div>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="save_marks" value="1">
    <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
    <input type="hidden" name="exam_type" value="<?= e($examType) ?>">
    <div class="form-group" style="padding:16px 20px 0;">
      <label class="form-label">Exam Date</label>
      <input type="date" name="exam_date" class="form-control" style="max-width:200px;" value="<?= date('Y-m-d') ?>">
    </div>
    <table>
      <thead><tr>
        <th>#</th><th>Student</th><th>ID</th>
        <th>Marks (/ <?= $subjectInfo['max_marks'] ?>)</th>
        <th>Grade</th>
        <th>Percentage</th>
      </tr></thead>
      <tbody>
        <?php foreach ($students as $idx => $s): ?>
        <tr>
          <td style="color:var(--text-muted);font-size:12px;"><?= $idx+1 ?></td>
          <td>
            <div class="d-flex align-center gap-2">
              <?php if (!empty($s['profile_photo'])): ?>
                <img src="<?= IMS_URL ?>/uploads/<?= e($s['profile_photo']) ?>" style="width:28px;height:28px;border-radius:50%;object-fit:cover;" alt="">
              <?php else: ?>
                <div class="avatar-initials" style="width:28px;height:28px;font-size:10px;"><?= strtoupper(substr($s['full_name'],0,1)) ?></div>
              <?php endif; ?>
              <?= e($s['full_name']) ?>
            </div>
          </td>
          <td><code style="font-size:11px;"><?= e($s['sid']) ?></code></td>
          <td>
            <input type="number"
                   name="marks[<?= $s['id'] ?>]"
                   class="form-control marks-input"
                   min="0" max="<?= $subjectInfo['max_marks'] ?>"
                   step="0.5"
                   style="width:120px;"
                   value="<?= $s['marks_obtained'] !== null ? e($s['marks_obtained']) : '' ?>"
                   data-max="<?= $subjectInfo['max_marks'] ?>"
                   oninput="calcGrade(this)">
          </td>
          <td class="grade-cell"><?php
            if ($s['grade'] !== null):
              $gc = match($s['grade']) { 'A+','A' => 'success', 'B+','B' => 'primary', 'C','D' => 'warning', 'F' => 'danger', default => 'muted' };
              echo '<span class="badge badge-'.$gc.'">'.e($s['grade']).'</span>';
            else: echo '--'; endif; ?></td>
          <td class="pct-cell"><?= $s['marks_obtained'] !== null && $subjectInfo['max_marks'] > 0
            ? round($s['marks_obtained'] / $subjectInfo['max_marks'] * 100, 1) . '%' : '--' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:12px;">
      <a href="<?= IMS_URL ?>/modules/marks/index.php" class="btn btn-outline">Cancel</a>
      <button type="submit" class="btn btn-success"><i class="ri-save-line"></i> Save Marks</button>
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
  if (pct>=90)grade='A+';else if(pct>=80)grade='A';else if(pct>=70)grade='B+';else if(pct>=60)grade='B';else if(pct>=50)grade='C';else if(pct>=40)grade='D';
  const cls = {'A+':'success','A':'success','B+':'primary','B':'primary','C':'warning','D':'warning','F':'danger'}[grade]||'muted';
  gradeCell.innerHTML = `<span class="badge badge-${cls}">${grade}</span>`;
  pctCell.textContent = pct.toFixed(1) + '%';
}
</script>

<?php elseif ($subjectId): ?>
<div class="empty-state"><i class="ri-bar-chart-2-line"></i><h3>No Students</h3><p>No enrolled students found for this subject.</p></div>
<?php else: ?>
<div class="card"><div class="card-body"><div class="empty-state" style="padding:48px 0;">
  <i class="ri-bar-chart-2-line"></i><h3>Select a Subject</h3><p>Choose a subject and exam type above to enter marks.</p>
</div></div></div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
