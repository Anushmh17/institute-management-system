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

    if (empty($name)) $errors[] = 'Subject name required.';
    if (empty($code)) $errors[] = 'Subject code required.';
    if (!$cid)        $errors[] = 'Course required.';

    if (empty($errors)) {
        try {
            $pdo->prepare("INSERT INTO subjects (course_id,teacher_id,name,code,credit_hours,max_marks,pass_marks) VALUES (?,?,?,?,?,?,?)")
                ->execute([$cid, $tid ?: null, $name, $code, $credit, $maxM, $passM]);
            set_toast('success',"Subject \"$name\" added!");
            header("Location: " . IMS_URL . "/modules/subjects/index.php?course_id=$cid");
            exit;
        } catch (PDOException $e) { $errors[] = 'Code may already exist.'; }
    }
}

$teachers = $pdo->query("SELECT t.id, u.full_name FROM teachers t JOIN users u ON u.id=t.user_id WHERE u.status='active' ORDER BY u.full_name")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Subjects</h1>
    <p class="page-subtitle"><?= count($subjects) ?> subjects</p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-primary" onclick="openModal('addSubjectModal')"><i class="ri-add-line"></i> Add Subject</button>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger"><i class="ri-error-warning-fill"></i>
  <div><ul style="padding-left:16px;"><?php foreach($errors as $e): ?><li><?= e($e)?></li><?php endforeach;?></ul></div>
</div>
<?php endif; ?>

<!-- Filer -->
<div style="margin-bottom:16px;">
  <form method="GET" class="d-flex gap-2 align-center">
    <select name="course_id" class="form-control" style="max-width:250px;" onchange="this.form.submit()">
      <option value="">All Courses</option>
      <?php foreach ($courses as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $courseId==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if($courseId): ?><a href="<?= IMS_URL ?>/modules/subjects/index.php" class="btn btn-outline btn-sm">Clear</a><?php endif; ?>
  </form>
</div>

<div class="table-wrapper">
  <?php if (empty($subjects)): ?>
  <div class="empty-state"><i class="ri-draft-line"></i><h3>No Subjects</h3><p>Add subjects to courses.</p></div>
  <?php else: ?>
  <table>
    <thead><tr>
      <th>Code</th><th class="sortable">Subject Name</th><th>Course</th>
      <th>Teacher</th><th>Credits</th><th>Max Marks</th><th>Pass Marks</th>
      <th style="text-align:right;">Actions</th>
    </tr></thead>
    <tbody>
      <?php foreach ($subjects as $sb): ?>
      <tr>
        <td><code style="font-size:12px;"><?= e($sb['code']) ?></code></td>
        <td><strong><?= e($sb['name']) ?></strong></td>
        <td><?= e($sb['course_name']) ?></td>
        <td><?= e($sb['teacher_name'] ?? '<span style="color:var(--text-muted);">Unassigned</span>') ?></td>
        <td><?= $sb['credit_hours'] ?></td>
        <td><?= $sb['max_marks'] ?></td>
        <td><?= $sb['pass_marks'] ?></td>
        <td style="text-align:right;">
          <div class="d-flex gap-2" style="justify-content:flex-end;">
            <a href="<?= IMS_URL ?>/modules/subjects/delete.php?id=<?= $sb['id'] ?>" class="btn btn-outline btn-sm btn-icon" data-confirm-delete="subject '<?= e($sb['name']) ?>'">
              <i class="ri-delete-bin-line" style="color:var(--danger);"></i>
            </a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- Add Subject Modal -->
<div class="modal-overlay" id="addSubjectModal" style="display:none;">
  <div class="modal modal-md">
    <div class="modal-header">
      <span class="modal-title-text"><i class="ri-draft-line"></i> Add Subject</span>
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
          </div>
          <div class="form-group">
            <label class="form-label">Teacher</label>
            <select name="teacher_id" class="form-control">
              <option value="">--Select Teacher--</option>
              <?php foreach ($teachers as $t): ?>
              <option value="<?= $t['id'] ?>"><?= e($t['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Subject Name <span class="required">*</span></label>
            <input type="text" name="name" class="form-control" required placeholder="e.g. Data Structures">
          </div>
          <div class="form-group">
            <label class="form-label">Subject Code <span class="required">*</span></label>
            <input type="text" name="code" class="form-control" required placeholder="e.g. CS-201" style="text-transform:uppercase;">
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
        <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> Add Subject</button>
      </div>
    </form>
  </div>
</div>

<?php if (!empty($errors)): ?>
<script>document.addEventListener('DOMContentLoaded',()=>openModal('addSubjectModal'));</script>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
