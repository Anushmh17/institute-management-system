<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin']);

$pageTitle  = 'Add Course';
$activePage = 'courses';
$pdo    = db();
$errors = [];
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name       = sanitize($_POST['name'] ?? '');
    $code       = strtoupper(sanitize($_POST['code'] ?? ''));
    $deptId     = (int)($_POST['department_id'] ?? 0);
    $desc       = sanitize($_POST['description'] ?? '');
    $duration   = (int)($_POST['duration_months'] ?? 12);
    $fee        = (float)($_POST['fee'] ?? 0);
    $maxStudents= (int)($_POST['max_students'] ?? 50);
    $status     = sanitize($_POST['status'] ?? 'active');

    if (empty($name)) $errors[] = 'Course name is required.';
    if (empty($code)) $errors[] = 'Course code is required.';
    if (empty($errors)) {
        $dup = $pdo->prepare("SELECT id FROM courses WHERE code=? LIMIT 1");
        $dup->execute([$code]);
        if ($dup->fetch()) $errors[] = 'Course code already exists.';
    }
    if (empty($errors)) {
        try {
            $pdo->prepare("INSERT INTO courses (department_id,name,code,description,duration_months,fee,max_students,status) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$deptId ?: null, $name, $code, $desc, $duration, $fee, $maxStudents, $status]);
            log_activity('create_course','courses',"Created: $name ($code)");
            set_toast('success',"Course \"$name\" created!");
            header('Location: ' . IMS_URL . '/modules/courses/index.php');
            exit;
        } catch (PDOException $e) { $errors[] = 'DB error.'; }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left"><h1 class="page-title">New Course</h1></div>
  <div class="page-header-actions"><a href="<?= IMS_URL ?>/modules/courses/index.php" class="btn btn-outline"><i class="ri-arrow-left-line"></i> Back</a></div>
</div>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger"><i class="ri-error-warning-fill"></i>
  <div><ul style="padding-left:16px;"><?php foreach($errors as $e): ?><li><?= e($e)?></li><?php endforeach;?></ul></div>
</div>
<?php endif; ?>

<div class="card" style="max-width:680px;">
  <div class="card-header"><h3 class="card-title"><i class="ri-book-open-line"></i> Course Details</h3></div>
  <div class="card-body">
    <form method="POST" data-validate>
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Course Name <span class="required">*</span></label>
          <input type="text" name="name" class="form-control" required placeholder="e.g. Bachelor of Computer Science" value="<?= e($_POST['name']??'') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Course Code <span class="required">*</span></label>
          <input type="text" name="code" class="form-control" required placeholder="e.g. BCS-101" value="<?= e($_POST['code']??'') ?>" style="text-transform:uppercase;">
        </div>
        <div class="form-group">
          <label class="form-label">Department</label>
          <select name="department_id" class="form-control">
            <option value="">-- None --</option>
            <?php foreach($departments as $d): ?>
            <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Duration (Months)</label>
          <input type="number" name="duration_months" class="form-control" min="1" max="120" value="<?= e($_POST['duration_months']??12) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Course Fee ($)</label>
          <input type="number" name="fee" class="form-control" step="0.01" min="0" value="<?= e($_POST['fee']??0) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Max Students</label>
          <input type="number" name="max_students" class="form-control" min="1" value="<?= e($_POST['max_students']??50) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <div class="form-group full-width">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="3" placeholder="Brief overview of this course"><?= e($_POST['description']??'') ?></textarea>
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:20px;padding-top:20px;border-top:1px solid var(--border);">
        <a href="<?= IMS_URL ?>/modules/courses/index.php" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> Create Course</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
