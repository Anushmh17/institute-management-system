<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin', 'teacher']);

$pageTitle  = 'Edit Class';
$activePage = 'classes';
$pdo    = db();
$errors = [];

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . IMS_URL . '/modules/classes/index.php');
    exit;
}

// Fetch class data
$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$class = $stmt->fetch();

if (!$class) {
    set_toast('error', 'Class not found.');
    header('Location: ' . IMS_URL . '/modules/classes/index.php');
    exit;
}

// Security: Teachers can only edit their own classes
if (is_teacher()) {
    $tr = $pdo->prepare("SELECT id FROM teachers WHERE user_id=? LIMIT 1");
    $tr->execute([$_SESSION['user_id']]);
    $tid = (int)($tr->fetchColumn() ?: 0);
    if ($class['teacher_id'] != $tid) {
        set_toast('error', 'Access denied. You can only edit your own classes.');
        header('Location: ' . IMS_URL . '/modules/classes/index.php');
        exit;
    }
}

$subjects = $pdo->query(
    "SELECT sb.id, sb.name, sb.code, c.name AS course FROM subjects sb
     JOIN courses c ON c.id=sb.course_id ORDER BY c.name, sb.name"
)->fetchAll();

$teachers = $pdo->query(
    "SELECT t.id, u.full_name FROM teachers t JOIN users u ON u.id=t.user_id WHERE u.status='active' ORDER BY u.full_name"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $room      = sanitize($_POST['room'] ?? '');
    $dayOfWeek = sanitize($_POST['day_of_week'] ?? '');
    $startTime = sanitize($_POST['start_time'] ?? '');
    $endTime   = sanitize($_POST['end_time'] ?? '');
    $type      = sanitize($_POST['type'] ?? 'lecture');
    $classDate = sanitize($_POST['class_date'] ?? '');
    $status    = sanitize($_POST['status'] ?? 'scheduled');

    if (!$subjectId)  $errors[] = 'Subject is required.';
    if (empty($dayOfWeek)) $errors[] = 'Day of week is required.';
    if (empty($startTime) || empty($endTime)) $errors[] = 'Start and end times are required.';
    if ($startTime >= $endTime) $errors[] = 'End time must be after start time.';

    // Conflict check (excluding current class)
    if (empty($errors) && $teacherId) {
        $conflict = $pdo->prepare(
            "SELECT id FROM classes WHERE teacher_id=? AND day_of_week=? AND status='scheduled'
             AND id != ? AND NOT (end_time <= ? OR start_time >= ?) LIMIT 1"
        );
        $conflict->execute([$teacherId, $dayOfWeek, $id, $startTime, $endTime]);
        if ($conflict->fetch()) $errors[] = 'Time conflict: teacher already has a class in this time slot.';
    }

    if (empty($errors)) {
        try {
            $pdo->prepare(
                "UPDATE classes SET subject_id=?, teacher_id=?, room=?, day_of_week=?, 
                        start_time=?, end_time=?, type=?, class_date=?, status=?
                 WHERE id=?"
            )->execute([
                $subjectId, $teacherId ?: null, $room, $dayOfWeek, 
                $startTime, $endTime, $type, $classDate ?: null, $status, $id
            ]);
            
            log_activity('edit_class', 'classes', "Updated class ID $id: $dayOfWeek $startTime");
            set_toast('success', 'Class schedule updated successfully!');
            header('Location: ' . IMS_URL . '/modules/classes/index.php');
            exit;
        } catch (PDOException $e) { $errors[] = 'Database error. Please try again.'; }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Edit Class Schedule</h1>
    <p class="page-subtitle">Modifying schedule for ID: <?= $id ?></p>
  </div>
  <div class="page-header-actions">
    <a href="<?= IMS_URL ?>/modules/classes/index.php" class="btn btn-outline">
      <i class="ri-arrow-left-line"></i> Back
    </a>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger"><i class="ri-error-warning-fill"></i>
  <div><ul style="padding-left:16px;"><?php foreach($errors as $er): ?><li><?= e($er)?></li><?php endforeach;?></ul></div>
</div>
<?php endif; ?>

<div class="card" style="max-width:680px;">
  <div class="card-header"><h3 class="card-title"><i class="ri-calendar-schedule-line"></i> Class Details</h3></div>
  <div class="card-body">
    <form method="POST" data-validate>
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Subject <span class="required">*</span></label>
          <select name="subject_id" class="form-control" required>
            <option value="">-- Select Subject --</option>
            <?php foreach ($subjects as $sb): ?>
            <option value="<?= $sb['id'] ?>" <?= $class['subject_id'] == $sb['id'] ? 'selected' : '' ?>>
              <?= e($sb['course']) ?> → <?= e($sb['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="form-group">
          <label class="form-label">Teacher</label>
          <select name="teacher_id" class="form-control" <?= is_teacher() ? 'disabled' : '' ?>>
            <option value="">-- Select Teacher --</option>
            <?php foreach ($teachers as $t): ?>
            <option value="<?= $t['id'] ?>" <?= $class['teacher_id'] == $t['id'] ? 'selected' : '' ?>>
              <?= e($t['full_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <?php if (is_teacher()): ?>
            <input type="hidden" name="teacher_id" value="<?= $class['teacher_id'] ?>">
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label">Day of Week <span class="required">*</span></label>
          <select name="day_of_week" class="form-control" required>
            <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday'] as $d): ?>
            <option value="<?= $d ?>" <?= $class['day_of_week'] === $d ? 'selected' : '' ?>><?= $d ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Room / Hall</label>
          <input type="text" name="room" class="form-control" value="<?= e($class['room']) ?>">
        </div>

        <div class="form-group">
          <label class="form-label">Start Time <span class="required">*</span></label>
          <input type="time" name="start_time" class="form-control" required value="<?= substr($class['start_time'], 0, 5) ?>">
        </div>

        <div class="form-group">
          <label class="form-label">End Time <span class="required">*</span></label>
          <input type="time" name="end_time" class="form-control" required value="<?= substr($class['end_time'], 0, 5) ?>">
        </div>

        <div class="form-group">
          <label class="form-label">Class Type</label>
          <select name="type" class="form-control">
            <?php foreach (['lecture','lab','tutorial','exam'] as $tp): ?>
            <option value="<?= $tp ?>" <?= $class['type'] === $tp ? 'selected' : '' ?>><?= ucfirst($tp) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <?php foreach (['scheduled','completed','cancelled'] as $st): ?>
            <option value="<?= $st ?>" <?= $class['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Specific Date <small class="text-muted">(Optional)</small></label>
          <input type="date" name="class_date" class="form-control" value="<?= e($class['class_date']) ?>">
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:20px;padding-top:20px;border-top:1px solid var(--border);">
        <a href="<?= IMS_URL ?>/modules/classes/index.php" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> Update Class</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
