<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin']);

$pageTitle  = 'Schedule Class';
$activePage = 'classes';
$pdo    = db();
$errors = [];

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

    if (!$subjectId)  $errors[] = 'Subject is required.';
    if (empty($dayOfWeek)) $errors[] = 'Day of week is required.';
    if (empty($startTime) || empty($endTime)) $errors[] = 'Start and end times are required.';
    if ($startTime >= $endTime) $errors[] = 'End time must be after start time.';

    // Conflict check
    if (empty($errors) && $teacherId) {
        $conflict = $pdo->prepare(
            "SELECT id FROM classes WHERE teacher_id=? AND day_of_week=? AND status='scheduled'
             AND NOT (end_time <= ? OR start_time >= ?) LIMIT 1"
        );
        $conflict->execute([$teacherId, $dayOfWeek, $startTime, $endTime]);
        if ($conflict->fetch()) $errors[] = 'Time conflict: teacher already has a class in this time slot.';
    }

    if (empty($errors)) {
        try {
            $pdo->prepare(
                "INSERT INTO classes (subject_id,teacher_id,room,day_of_week,start_time,end_time,type,class_date,status)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            )->execute([$subjectId, $teacherId ?: null, $room, $dayOfWeek, $startTime, $endTime, $type, $classDate ?: null, 'scheduled']);
            log_activity('schedule_class','classes',"Scheduled class: $dayOfWeek $startTime");
            set_toast('success','Class scheduled successfully!');
            header('Location: ' . IMS_URL . '/modules/classes/index.php');
            exit;
        } catch (PDOException $e) { $errors[] = 'DB error.'; }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left"><h1 class="page-title">Schedule New Class</h1></div>
  <div class="page-header-actions"><a href="<?= IMS_URL ?>/modules/classes/index.php" class="btn btn-outline"><i class="ri-arrow-left-line"></i> Back</a></div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" style="max-width:680px; margin:0 auto 20px;"><i class="ri-error-warning-fill"></i>
  <div><ul style="padding-left:16px;"><?php foreach($errors as $er): ?><li><?= e($er)?></li><?php endforeach;?></ul></div>
</div>
<?php endif; ?>

<div class="card" style="max-width:680px; margin:0 auto;">
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
            <option value="<?= $sb['id'] ?>"><?= e($sb['course']) ?> → <?= e($sb['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Teacher</label>
          <select name="teacher_id" class="form-control">
            <option value="">-- Select Teacher --</option>
            <?php foreach ($teachers as $t): ?>
            <option value="<?= $t['id'] ?>"><?= e($t['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Day of Week <span class="required">*</span></label>
          <select name="day_of_week" class="form-control" required>
            <option value="">-- Select Day --</option>
            <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday'] as $d): ?>
            <option value="<?= $d ?>"><?= $d ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Room / Hall</label>
          <input type="text" name="room" class="form-control" placeholder="e.g. Room 101, Lab A" value="<?= e($_POST['room']??'') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Start Time <span class="required">*</span></label>
          <input type="time" name="start_time" class="form-control" required value="<?= e($_POST['start_time']??'09:00') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">End Time <span class="required">*</span></label>
          <input type="time" name="end_time" class="form-control" required value="<?= e($_POST['end_time']??'10:00') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Class Type</label>
          <select name="type" class="form-control">
            <option value="lecture">Lecture</option>
            <option value="lab">Lab</option>
            <option value="tutorial">Tutorial</option>
            <option value="exam">Exam</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Specific Date <small class="text-muted">(leave blank for recurring)</small></label>
          <input type="date" name="class_date" class="form-control" value="<?= e($_POST['class_date']??'') ?>">
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:20px;padding-top:20px;border-top:1px solid var(--border);">
        <a href="<?= IMS_URL ?>/modules/classes/index.php" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> Schedule Class</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
