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

<style>
/* Course Form Premium Styles */
.cf-hero {
  background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 60%, #1E293B 100%);
  border-radius: 18px; padding: 30px 36px; margin-bottom: 28px;
  display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;
  position: relative; overflow: hidden;
}
.cf-hero::before {
  content: ''; position: absolute;
  width: 300px; height: 300px;
  background: radial-gradient(circle, rgba(37,99,235,0.2) 0%, transparent 70%);
  top: -80px; right: -40px; pointer-events: none;
}
.cf-hero h1 { font-family: var(--font-heading); font-size: 26px; font-weight: 800; color: #fff; margin: 0 0 4px; position: relative; z-index: 1; }
.cf-hero p  { font-size: 13px; color: rgba(255,255,255,0.55); margin: 0; position: relative; z-index: 1; }

.cf-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 20px;
  box-shadow: var(--shadow-md);
  max-width: 860px;
  overflow: hidden;
}
.cf-card-header {
  display: flex; align-items: center; gap: 14px;
  padding: 22px 28px;
  border-bottom: 1px solid var(--border);
  background: var(--bg);
}
[data-theme="dark"] .cf-card-header { background: var(--bg-hover); }
.cf-header-icon {
  width: 46px; height: 46px; border-radius: 13px;
  background: linear-gradient(135deg, #2563EB, #7C3AED);
  display: flex; align-items: center; justify-content: center;
  font-size: 21px; color: #fff; flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(37,99,235,0.3);
}
.cf-card-header h3 { font-family: var(--font-heading); font-size: 17px; font-weight: 700; margin: 0 0 2px; color: var(--text-primary); }
.cf-card-header p  { font-size: 12px; color: var(--text-muted); margin: 0; }
.cf-card-body { padding: 32px 28px; }

.cf-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
.cf-form-group { display: flex; flex-direction: column; gap: 7px; }
.cf-form-group.full { grid-column: 1 / -1; }
.cf-label {
  font-size: 12px; font-weight: 700; color: var(--text-secondary);
  text-transform: uppercase; letter-spacing: 0.6px;
  display: flex; align-items: center; gap: 5px;
}
.cf-label .required { color: var(--danger); font-size: 14px; }
.cf-input-wrap { position: relative; }
.cf-input-wrap > i {
  position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
  color: var(--text-muted); font-size: 15px; pointer-events: none;
}
.cf-input-wrap > i.top { top: 15px; transform: none; }
.cf-input, .cf-select, .cf-textarea {
  width: 100%; padding: 10px 14px; border: 1.5px solid var(--border);
  border-radius: 10px; background: var(--bg-card); color: var(--text-primary);
  font-size: 14px; font-family: inherit; outline: none;
  transition: all 0.2s ease;
}
.cf-input:focus, .cf-select:focus, .cf-textarea:focus {
  border-color: var(--primary-light);
  box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
}
.cf-input.has-icon, .cf-select.has-icon { padding-left: 38px; }
.cf-textarea { resize: vertical; min-height: 100px; padding-top: 12px; }
.cf-input-prefix {
  display: flex;
}
.cf-prefix-label {
  padding: 10px 14px; background: var(--bg-hover); border: 1.5px solid var(--border);
  border-right: none; border-radius: 10px 0 0 10px; color: var(--text-muted);
  font-weight: 700; font-size: 14px; display: flex; align-items: center;
}
.cf-input-prefix .cf-input { border-radius: 0 10px 10px 0; }

.cf-section-title {
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 1px; color: var(--primary-light);
  padding: 0 0 8px; border-bottom: 1px solid var(--border);
  margin-bottom: 20px; grid-column: 1 / -1;
  display: flex; align-items: center; gap: 8px;
}

.cf-error-box {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 14px 18px; background: rgba(239,68,68,0.06);
  border: 1px solid rgba(239,68,68,0.2);
  border-left: 4px solid var(--danger);
  border-radius: 12px; margin-bottom: 24px;
}
.cf-error-box i { font-size: 18px; color: var(--danger); flex-shrink: 0; margin-top: 1px; }
.cf-error-box ul { margin: 0; padding-left: 16px; font-size: 13px; font-weight: 500; color: var(--text-primary); }

.cf-footer {
  display: flex; justify-content: flex-end; gap: 12px;
  padding: 20px 28px;
  border-top: 1px solid var(--border);
  background: var(--bg);
}
[data-theme="dark"] .cf-footer { background: var(--bg-hover); }
.cf-btn-cancel {
  padding: 10px 24px; border: 1.5px solid var(--border); border-radius: 10px;
  background: var(--bg-card); color: var(--text-secondary); font-size: 14px;
  font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 7px;
  transition: all 0.18s ease;
}
.cf-btn-cancel:hover { border-color: var(--border-strong); color: var(--text-primary); background: var(--bg-hover); }
.cf-btn-submit {
  padding: 10px 28px; border-radius: 10px;
  background: linear-gradient(135deg, #2563EB, #7C3AED);
  color: #fff; font-size: 14px; font-weight: 700; border: none; cursor: pointer;
  display: flex; align-items: center; gap: 7px;
  box-shadow: 0 4px 14px rgba(37,99,235,0.4);
  transition: opacity 0.2s ease, transform 0.15s ease;
}
.cf-btn-submit:hover { opacity: 0.9; transform: scale(0.98); }

@media (max-width: 600px) {
  .cf-form-grid { grid-template-columns: 1fr; }
  .cf-card-body  { padding: 20px 16px; }
  .cf-footer { padding: 16px; }
  .cf-hero { padding: 22px 18px; }
}
</style>

<!-- Hero -->
<div class="cf-hero">
  <div>
    <h1><i class="ri-add-circle-line" style="color:var(--accent); margin-right:10px;"></i>New Course</h1>
    <p>Fill in the details below to add a new academic course to the system.</p>
  </div>
  <a href="<?= IMS_URL ?>/modules/courses/index.php" class="btn btn-outline" style="background:rgba(255,255,255,0.08); border-color:rgba(255,255,255,0.2); color:#fff; border-radius:10px; position:relative; z-index:1;">
    <i class="ri-arrow-left-line"></i> Back to Courses
  </a>
</div>

<!-- Error Box -->
<?php if (!empty($errors)): ?>
<div class="cf-error-box" style="max-width:860px; margin-bottom:20px;">
  <i class="ri-error-warning-fill"></i>
  <div><ul><?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
</div>
<?php endif; ?>

<!-- Form Card -->
<div class="cf-card">
  <div class="cf-card-header">
    <div class="cf-header-icon"><i class="ri-book-open-line"></i></div>
    <div>
      <h3>Course Specification</h3>
      <p>Fields marked with <span style="color:var(--danger);">*</span> are required</p>
    </div>
  </div>

  <div class="cf-card-body">
    <form method="POST" data-validate>
      <?= csrf_field() ?>
      <div class="cf-form-grid">

        <!-- Section: Identity -->
        <div class="cf-section-title"><i class="ri-fingerprint-line"></i> Course Identity</div>

        <div class="cf-form-group">
          <label class="cf-label"><i class="ri-text-snippet"></i> Course Name <span class="required">*</span></label>
          <div class="cf-input-wrap">
            <i class="ri-edit-2-line"></i>
            <input type="text" name="name" class="cf-input has-icon" required placeholder="e.g. B.Sc. Computer Science" value="<?= e($_POST['name']??'') ?>">
          </div>
        </div>

        <div class="cf-form-group">
          <label class="cf-label"><i class="ri-barcode-line"></i> Course Code <span class="required">*</span></label>
          <div class="cf-input-wrap">
            <i class="ri-hashtag"></i>
            <input type="text" name="code" class="cf-input has-icon" required placeholder="e.g. BCS-101" style="text-transform:uppercase;" value="<?= e($_POST['code']??'') ?>">
          </div>
        </div>

        <div class="cf-form-group">
          <label class="cf-label"><i class="ri-building-2-line"></i> Department / Faculty</label>
          <div class="cf-input-wrap">
            <i class="ri-community-line"></i>
            <select name="department_id" class="cf-select has-icon">
              <option value="">— None (Global) —</option>
              <?php foreach($departments as $d): ?>
              <option value="<?= $d['id'] ?>" <?= (isset($_POST['department_id']) && $_POST['department_id'] == $d['id']) ? 'selected' : '' ?>><?= e($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="cf-form-group">
          <label class="cf-label"><i class="ri-shield-check-line"></i> Publishing Status</label>
          <div class="cf-input-wrap">
            <i class="ri-toggle-line"></i>
            <select name="status" class="cf-select has-icon">
              <option value="active"   <?= (isset($_POST['status']) && $_POST['status'] === 'active')   ? 'selected' : '' ?>>Active (Visible)</option>
              <option value="inactive" <?= (isset($_POST['status']) && $_POST['status'] === 'inactive') ? 'selected' : '' ?>>Inactive (Hidden)</option>
            </select>
          </div>
        </div>

        <!-- Section: Academic Details -->
        <div class="cf-section-title"><i class="ri-bar-chart-grouped-line"></i> Academic Details</div>

        <div class="cf-form-group">
          <label class="cf-label"><i class="ri-time-line"></i> Duration (Months)</label>
          <div class="cf-input-wrap">
            <i class="ri-calendar-schedule-line"></i>
            <input type="number" name="duration_months" class="cf-input has-icon" min="1" max="120" value="<?= e($_POST['duration_months']??12) ?>">
          </div>
        </div>

        <div class="cf-form-group">
          <label class="cf-label"><i class="ri-money-dollar-circle-line"></i> Course Fee</label>
          <div class="cf-input-prefix">
            <span class="cf-prefix-label">$</span>
            <input type="number" name="fee" class="cf-input" step="0.01" min="0" value="<?= e($_POST['fee']??0) ?>">
          </div>
        </div>

        <div class="cf-form-group">
          <label class="cf-label"><i class="ri-group-line"></i> Max Student Intake</label>
          <div class="cf-input-wrap">
            <i class="ri-user-3-line"></i>
            <input type="number" name="max_students" class="cf-input has-icon" min="1" value="<?= e($_POST['max_students']??50) ?>">
          </div>
        </div>

        <!-- Description -->
        <div class="cf-form-group full">
          <label class="cf-label"><i class="ri-article-line"></i> Course Description / Overview</label>
          <div class="cf-input-wrap">
            <i class="ri-align-left top"></i>
            <textarea name="description" class="cf-textarea" style="padding-left:38px;" placeholder="Brief overview of course curriculum and objectives..."><?= e($_POST['description']??'') ?></textarea>
          </div>
        </div>

      </div><!-- /grid -->
    </form>
  </div>

  <div class="cf-footer">
    <a href="<?= IMS_URL ?>/modules/courses/index.php" class="cf-btn-cancel"><i class="ri-close-line"></i> Cancel</a>
    <button type="submit" form="" onclick="this.closest('.cf-card').querySelector('form').submit();" class="cf-btn-submit">
      <i class="ri-check-line"></i> Create Course
    </button>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
