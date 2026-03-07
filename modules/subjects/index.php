<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin']);

$pageTitle  = 'Subjects';
$activePage = 'subjects';
$pdo = db();

$courseId = (int)($_GET['course_id'] ?? 0);
$courses = $pdo->query("SELECT id, name, code FROM courses WHERE status='active' ORDER BY name")->fetchAll();

$where  = 'WHERE 1=1';
$params = [];
if ($courseId) { $where .= ' AND sb.course_id=?'; $params[] = $courseId; }

$subjects = $pdo->prepare(
    "SELECT sb.*, c.name AS course_name, u.full_name AS teacher_name
     FROM subjects sb
     JOIN courses c ON c.id=sb.course_id
     LEFT JOIN teachers t ON t.id=sb.teacher_id
     LEFT JOIN users u ON u.id=t.user_id
     $where ORDER BY c.name, sb.name"
);
$subjects->execute($params);
$subjects = $subjects->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name     = sanitize($_POST['name'] ?? '');
    $code     = strtoupper(sanitize($_POST['code'] ?? ''));
    $cid      = (int)($_POST['course_id'] ?? 0);
    $tid      = (int)($_POST['teacher_id'] ?? 0);
    $credit   = (int)($_POST['credit_hours'] ?? 3);
    $maxM     = (int)($_POST['max_marks'] ?? 100);
    $passM    = (int)($_POST['pass_marks'] ?? 40);

    if (empty($name))   $errors[] = 'Subject name required.';
    if (empty($code))   $errors[] = 'Subject code required.';
    if (!$cid)          $errors[] = 'Course required.';
    if (!$tid)          $errors[] = 'Teacher required.';

    if (empty($errors)) {
        try {
            $pdo->prepare("INSERT INTO subjects (course_id,teacher_id,name,code,credit_hours,max_marks,pass_marks) VALUES (?,?,?,?,?,?,?)")
                ->execute([$cid, $tid ?: null, $name, $code, $credit, $maxM, $passM]);
            set_toast('success',"Subject \"$name\" added!");
            header("Location: " . IMS_URL . "/modules/subjects/index.php?course_id=$cid");
            exit;
        } catch (PDOException $e) {
            $errors[] = strpos($e->getMessage(), 'Duplicate') !== false
                ? 'Subject code "' . $code . '" is already in use.'
                : 'Could not save subject. Database error.';
        }
    }
}

$teachers = $pdo->query("SELECT t.id, u.full_name FROM teachers t JOIN users u ON u.id=t.user_id WHERE u.status='active' ORDER BY u.full_name")->fetchAll();

$totalSubjects  = count($subjects);
$uniqueCourses  = count(array_unique(array_column($subjects, 'course_name')));
$avgCredits     = $totalSubjects > 0 ? round(array_sum(array_column($subjects, 'credit_hours')) / $totalSubjects, 1) : 0;

include __DIR__ . '/../../includes/header.php';
?>

<style>
/* ===== SUBJECTS PAGE ===== */
.pg-hero { background:linear-gradient(135deg,#0F172A 0%,#4C1D95 55%,#1E293B 100%); border-radius:20px; padding:32px 36px; margin-bottom:26px; position:relative; overflow:hidden; }
.pg-hero::before { content:''; position:absolute; width:380px; height:380px; background:radial-gradient(circle,rgba(124,58,237,.24) 0%,transparent 70%); top:-110px; right:-40px; pointer-events:none; }
.pg-hero::after  { content:''; position:absolute; width:240px; height:240px; background:radial-gradient(circle,rgba(245,158,11,.12) 0%,transparent 70%); bottom:-60px; left:5%; pointer-events:none; }
.pg-hero-inner { position:relative; z-index:1; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:20px; }
.pg-hero h1 { font-family:var(--font-heading); font-size:30px; font-weight:800; color:#fff; margin:0 0 5px; letter-spacing:-.4px; }
.pg-hero p  { font-size:13px; color:rgba(255,255,255,.55); margin:0; }
.hero-kpis  { display:flex; gap:24px; flex-wrap:wrap; }
.hero-kpi   { text-align:center; }
.hero-kpi-val { font-family:var(--font-heading); font-size:26px; font-weight:800; color:#fff; line-height:1; }
.hero-kpi-lbl { font-size:10px; font-weight:700; color:rgba(255,255,255,.45); text-transform:uppercase; letter-spacing:.8px; margin-top:3px; }
.hero-kpi-divider { width:1px; background:rgba(255,255,255,.12); height:36px; align-self:center; }
.pg-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
.pg-toolbar-left { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.pg-select { padding:9px 14px; border:1.5px solid var(--border); border-radius:10px; background:var(--bg-card); color:var(--text-primary); font-size:13px; outline:none; font-family:inherit; cursor:pointer; transition:border .2s; }
.pg-select:focus { border-color:#7C3AED; }
.pg-btn-clear { display:inline-flex; align-items:center; gap:5px; padding:9px 14px; border-radius:10px; border:1.5px solid var(--border); background:var(--bg-card); color:var(--text-secondary); font-size:13px; font-weight:600; text-decoration:none; transition:all .2s; }
.pg-btn-clear:hover { background:var(--bg-hover); color:var(--text-primary); }
.pg-table-card { background:var(--bg-card); border:1px solid var(--border); border-radius:18px; overflow:hidden; box-shadow:var(--shadow-sm); }
.pg-table-head { display:flex; align-items:center; justify-content:space-between; padding:16px 22px; border-bottom:1px solid var(--border); background:var(--bg); gap:10px; flex-wrap:wrap; }
[data-theme="dark"] .pg-table-head { background:var(--bg-hover); }
.pg-table-head-title { font-size:14px; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:8px; }
.pg-table-head-title i { color:#7C3AED; font-size:16px; }
.pg-count-badge { font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; background:#F3E8FF; color:#7C3AED; }
[data-theme="dark"] .pg-count-badge { background:rgba(124,58,237,.2); color:#C4B5FD; }
table.pg-table { width:100%; border-collapse:collapse; }
table.pg-table thead tr { border-bottom:1px solid var(--border); }
table.pg-table thead th { padding:11px 16px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--text-muted); text-align:left; white-space:nowrap; }
table.pg-table tbody tr { border-bottom:1px solid var(--border); transition:background .15s; }
table.pg-table tbody tr:last-child { border-bottom:none; }
table.pg-table tbody tr:hover { background:var(--bg-hover); }
table.pg-table tbody td { padding:13px 16px; font-size:13px; color:var(--text-primary); vertical-align:middle; }
.sub-code-badge { font-size:11px; font-weight:700; padding:3px 9px; border-radius:6px; background:#F3E8FF; color:#7C3AED; border:1px solid rgba(124,58,237,.2); font-family:monospace; }
[data-theme="dark"] .sub-code-badge { background:rgba(124,58,237,.15); color:#C4B5FD; }
.sub-name { font-weight:600; font-size:13px; color:var(--text-primary); }
.course-tag { font-size:11px; color:var(--text-muted); font-style:italic; }
.credit-badge { font-size:11px; font-weight:700; padding:3px 9px; border-radius:20px; background:var(--info-light); color:var(--primary); }
.marks-badge { font-size:12px; font-weight:600; color:var(--text-secondary); }
.teacher-chip { display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--text-secondary); }
.teacher-chip i { color:#7C3AED; font-size:13px; }
.unassigned { font-size:12px; color:var(--text-muted); font-style:italic; }
.tbl-action-btn { width:32px; height:32px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; font-size:14px; border:1.5px solid var(--border); background:var(--bg-card); color:var(--text-secondary); text-decoration:none; transition:all .18s; cursor:pointer; }
.tbl-action-btn:hover { background:var(--bg-hover); color:var(--text-primary); border-color:var(--border-strong); }
.tbl-action-btn.danger:hover { background:var(--danger-light); color:var(--danger); border-color:rgba(239,68,68,.3); }
.pg-empty { padding:60px 20px; text-align:center; }
.pg-empty i { font-size:44px; color:var(--border-strong); display:block; margin:0 auto 14px; }
.pg-empty h3 { font-size:18px; font-weight:700; margin:0 0 6px; }
.pg-empty p  { font-size:13px; color:var(--text-muted); margin:0 0 18px; }

/* Error box */
.err-box { display:flex; align-items:flex-start; gap:12px; padding:14px 18px; background:rgba(239,68,68,.06); border:1px solid rgba(239,68,68,.2); border-left:4px solid var(--danger); border-radius:12px; margin-bottom:20px; }
.err-box i { font-size:18px; color:var(--danger); flex-shrink:0; margin-top:1px; }
.err-box ul { margin:0; padding-left:16px; font-size:13px; font-weight:500; }
</style>

<!-- HERO -->
<div class="pg-hero">
  <div class="pg-hero-inner">
    <div>
      <h1><i class="ri-draft-line" style="margin-right:10px; color:#C4B5FD;"></i>Subjects</h1>
      <p>Manage all academic subjects, assigned teachers and credit hours.</p>
    </div>
    <div class="hero-kpis">
      <div class="hero-kpi">
        <div class="hero-kpi-val"><?= $totalSubjects ?></div>
        <div class="hero-kpi-lbl">Subjects</div>
      </div>
      <div class="hero-kpi-divider"></div>
      <div class="hero-kpi">
        <div class="hero-kpi-val" style="color:#C4B5FD;"><?= $uniqueCourses ?></div>
        <div class="hero-kpi-lbl">Courses</div>
      </div>
      <div class="hero-kpi-divider"></div>
      <div class="hero-kpi">
        <div class="hero-kpi-val" style="color:#FCD34D;"><?= $avgCredits ?></div>
        <div class="hero-kpi-lbl">Avg Credits</div>
      </div>
    </div>
  </div>
</div>

<!-- Errors -->
<?php if (!empty($errors)): ?>
<div class="err-box">
  <i class="ri-error-warning-fill"></i>
  <div><ul><?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
</div>
<?php endif; ?>

<!-- TOOLBAR -->
<div class="pg-toolbar">
  <div class="pg-toolbar-left">
    <form method="GET" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
      <select name="course_id" class="pg-select" onchange="this.form.submit()">
        <option value="">All Courses</option>
        <?php foreach ($courses as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $courseId==$c['id']?'selected':'' ?>><?= e($c['name']) ?> (<?= e($c['code']) ?>)</option>
        <?php endforeach; ?>
      </select>
      <?php if ($courseId): ?><a href="<?= IMS_URL ?>/modules/subjects/index.php" class="pg-btn-clear"><i class="ri-refresh-line"></i> All</a><?php endif; ?>
    </form>
  </div>
  <button class="btn btn-primary" onclick="openModal('addSubjectModal')" style="border-radius:10px; font-size:13px; font-weight:600; background:#7C3AED; box-shadow:0 4px 14px rgba(124,58,237,.35);">
    <i class="ri-add-line"></i> Add Subject
  </button>
</div>

<!-- TABLE CARD -->
<div class="pg-table-card">
  <div class="pg-table-head">
    <div class="pg-table-head-title">
      <i class="ri-draft-line"></i> Subject Catalogue
      <span class="pg-count-badge"><?= $totalSubjects ?></span>
    </div>
    <?php if ($courseId): ?>
    <span style="font-size:12px; color:var(--text-muted);">Filtered by course</span>
    <?php endif; ?>
  </div>

  <?php if (empty($subjects)): ?>
  <div class="pg-empty">
    <i class="ri-draft-line"></i>
    <h3>No Subjects Found</h3>
    <p>Add subjects to your courses to get started.</p>
    <button onclick="openModal('addSubjectModal')" class="btn btn-primary" style="background:#7C3AED;"><i class="ri-add-line"></i> Add Subject</button>
  </div>
  <?php else: ?>
  <div style="overflow-x:auto;">
    <table class="pg-table">
      <thead>
        <tr>
          <th>Code</th>
          <th>Subject Name</th>
          <th>Course</th>
          <th>Teacher</th>
          <th>Credits</th>
          <th>Max / Pass</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($subjects as $sb): ?>
        <tr>
          <td><span class="sub-code-badge"><?= e($sb['code']) ?></span></td>
          <td>
            <div class="sub-name"><?= e($sb['name']) ?></div>
          </td>
          <td><span class="course-tag"><?= e($sb['course_name']) ?></span></td>
          <td>
            <?php if ($sb['teacher_name']): ?>
            <span class="teacher-chip"><i class="ri-user-star-line"></i><?= e($sb['teacher_name']) ?></span>
            <?php else: ?>
            <span class="unassigned">Unassigned</span>
            <?php endif; ?>
          </td>
          <td><span class="credit-badge"><?= $sb['credit_hours'] ?> cr</span></td>
          <td>
            <span class="marks-badge">
              <span style="color:var(--success); font-weight:700;"><?= $sb['max_marks'] ?></span>
              <span style="color:var(--text-muted);"> / </span>
              <span style="color:var(--danger); font-weight:700;"><?= $sb['pass_marks'] ?></span>
            </span>
          </td>
          <td style="text-align:right;">
            <a href="<?= IMS_URL ?>/modules/subjects/delete.php?id=<?= $sb['id'] ?>" class="tbl-action-btn danger" data-confirm-delete="subject '<?= e($sb['name']) ?>'">
              <i class="ri-delete-bin-line"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Add Subject Modal -->
<div class="modal-overlay" id="addSubjectModal" style="display:none;">
  <div class="modal modal-md">
    <div class="modal-header">
      <span class="modal-title-text"><i class="ri-draft-line"></i> Add New Subject</span>
      <button class="modal-close" onclick="closeModal('addSubjectModal')"><i class="ri-close-line"></i></button>
    </div>
    <form method="POST" data-validate>
      <?= csrf_field() ?>
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Course <span class="required">*</span></label>
            <select name="course_id" class="form-control" required>
              <option value="">--Select Course--</option>
              <?php foreach ($courses as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $courseId==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-error">Please select a course.</div>
          </div>
          <div class="form-group">
            <label class="form-label">Assigned Teacher <span class="required">*</span></label>
            <select name="teacher_id" class="form-control" required>
              <option value="">--Select Teacher--</option>
              <?php foreach ($teachers as $t): ?>
              <option value="<?= $t['id'] ?>"><?= e($t['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-error">Please select a teacher.</div>
          </div>
          <div class="form-group">
            <label class="form-label">Subject Name <span class="required">*</span></label>
            <input type="text" name="name" class="form-control" required placeholder="e.g. Data Structures">
            <div class="form-error">Subject name is required.</div>
          </div>
          <div class="form-group">
            <label class="form-label">Subject Code <span class="required">*</span></label>
            <input type="text" name="code" class="form-control" required placeholder="e.g. CS-201" style="text-transform:uppercase;">
            <div class="form-error">Unique subject code is required.</div>
          </div>
          <div class="form-group">
            <label class="form-label">Credit Hours</label>
            <input type="number" name="credit_hours" class="form-control" value="3" min="1" max="6">
          </div>
          <div class="form-group">
            <label class="form-label">Max Marks</label>
            <input type="number" name="max_marks" class="form-control" value="100" min="1">
          </div>
          <div class="form-group">
            <label class="form-label">Pass Marks</label>
            <input type="number" name="pass_marks" class="form-control" value="40" min="1">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addSubjectModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background:#7C3AED;"><i class="ri-save-line"></i> Add Subject</button>
      </div>
    </form>
  </div>
</div>

<?php if (!empty($errors)): ?>
<script>document.addEventListener('DOMContentLoaded',()=>openModal('addSubjectModal'));</script>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
