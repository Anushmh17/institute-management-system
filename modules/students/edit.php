<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin', 'teacher']);

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . IMS_URL . '/modules/students/index.php'); exit; }

// Fetch existing data
$stmt = $pdo->prepare(
    "SELECT s.*, u.full_name, u.email, u.username, u.phone, u.profile_photo
     FROM students s JOIN users u ON u.id = s.user_id
     WHERE s.id = ? LIMIT 1"
);
$stmt->execute([$id]);
$student = $stmt->fetch();
if (!$student) { set_toast('error', 'Student not found.'); header('Location: ' . IMS_URL . '/modules/students/index.php'); exit; }

$pageTitle  = 'Edit Student';
$activePage = 'students';
$errors     = [];
$courses    = $pdo->query("SELECT id, name, code FROM courses WHERE status='active' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $fullName      = sanitize($_POST['full_name'] ?? '');
    $email         = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $username      = sanitize($_POST['username'] ?? '');
    $phone         = sanitize($_POST['phone'] ?? '');
    $courseId      = (int)($_POST['course_id'] ?? 0);
    $dob           = sanitize($_POST['date_of_birth'] ?? '');
    $gender        = sanitize($_POST['gender'] ?? '');
    $bloodGroup    = sanitize($_POST['blood_group'] ?? '');
    $address       = sanitize($_POST['address'] ?? '');
    $guardianName  = sanitize($_POST['guardian_name'] ?? '');
    $guardianPhone = sanitize($_POST['guardian_phone'] ?? '');
    $guardianEmail = sanitize($_POST['guardian_email'] ?? '');
    $batchYear     = (int)($_POST['batch_year'] ?? $student['batch_year']);
    $admissionDate = sanitize($_POST['admission_date'] ?? $student['admission_date']);
    $status        = sanitize($_POST['status'] ?? 'active');
    $newPassword   = $_POST['new_password'] ?? '';

    if (empty($fullName)) $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';

    // Check unique email/username (excluding self)
    if (empty($errors)) {
        $check = $pdo->prepare("SELECT id FROM users WHERE (email=? OR username=?) AND id != ? LIMIT 1");
        $check->execute([$email, $username, $student['user_id']]);
        if ($check->fetch()) $errors[] = 'Email or username already in use by another account.';
    }

    // Handle new photo
    $photoFilename = $student['profile_photo'];
    if (!empty($_FILES['profile_photo']['name'])) {
        $file    = $_FILES['profile_photo'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowed)) {
            $errors[] = 'Photo must be JPG, PNG or WebP.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Photo must be under 2MB.';
        } else {
            $ext          = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFilename  = 'students/' . uniqid('stu_') . '.' . $ext;
            $uploadDir    = __DIR__ . '/../../uploads/students/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            if (move_uploaded_file($file['tmp_name'], $uploadDir . basename($newFilename))) {
                // Delete old photo
                if ($photoFilename && file_exists(__DIR__ . '/../../uploads/' . $photoFilename)) {
                    @unlink(__DIR__ . '/../../uploads/' . $photoFilename);
                }
                $photoFilename = $newFilename;
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Update user record
            $userSql = "UPDATE users SET full_name=?, email=?, username=?, phone=?, profile_photo=? WHERE id=?";
            $pdo->prepare($userSql)->execute([$fullName, $email, $username, $phone, $photoFilename, $student['user_id']]);

            // Update password if provided
            if (!empty($newPassword)) {
                if (strlen($newPassword) < 8) {
                    $errors[] = 'New password must be at least 8 characters.';
                } else {
                    $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                        ->execute([password_hash($newPassword, PASSWORD_BCRYPT, ['cost'=>12]), $student['user_id']]);
                }
            }

            // Update student record
            $pdo->prepare(
                "UPDATE students SET course_id=?, date_of_birth=?, gender=?, blood_group=?,
                  address=?, guardian_name=?, guardian_phone=?, guardian_email=?,
                  admission_date=?, batch_year=?, status=? WHERE id=?"
            )->execute([
                $courseId ?: null, $dob ?: null, $gender ?: null, $bloodGroup ?: null,
                $address, $guardianName, $guardianPhone, $guardianEmail,
                $admissionDate, $batchYear, $status, $id
            ]);

            // Update enrollment if course changed
            if ($courseId) {
                $pdo->prepare("INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (?,?)")
                    ->execute([$id, $courseId]);
            }

            $pdo->commit();
            log_activity('edit_student', 'students', "Updated student ID $id");
            set_toast('success', 'Student updated successfully!');
            header('Location: ' . IMS_URL . '/modules/students/index.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Database error. Please try again.';
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Edit Student</h1>
    <p class="page-subtitle">Updating: <strong><?= e($student['full_name']) ?></strong> (<?= e($student['student_id']) ?>)</p>
  </div>
  <div class="page-header-actions">
    <a href="<?= IMS_URL ?>/modules/students/index.php" class="btn btn-outline">
      <i class="ri-arrow-left-line"></i> Back
    </a>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
  <i class="ri-error-warning-fill"></i>
  <div><strong>Errors:</strong><ul style="margin-top:6px; padding-left:16px;">
    <?php foreach ($errors as $e2): ?><li><?= e($e2) ?></li><?php endforeach; ?>
  </ul></div>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" data-validate>
  <?= csrf_field() ?>

  <div style="display:grid; grid-template-columns:1fr 2fr; gap:24px; align-items:start;">

    <!-- Photo & Status -->
    <div class="card">
      <div class="card-header"><h3 class="card-title"><i class="ri-image-line"></i> Photo & Status</h3></div>
      <div class="card-body" style="text-align:center;">
        <?php $currentPhoto = $student['profile_photo'] ? IMS_URL . '/uploads/' . e($student['profile_photo']) : ''; ?>
        <img id="photoPreview"
             src="<?= $currentPhoto ?>"
             alt="Preview" class="photo-preview"
             style="<?= $currentPhoto ? 'display:block;' : 'display:none;' ?> margin:0 auto 16px;">
        <div class="photo-upload-area" id="photoUploadArea">
          <div class="upload-icon"><i class="ri-upload-cloud-2-line"></i></div>
          <p><strong>Click to change photo</strong></p>
          <p>JPG, PNG, WebP -- Max 2MB</p>
        </div>
        <input type="file" name="profile_photo" id="profile_photo" accept="image/*" style="display:none;">

        <div class="form-group" style="margin-top:20px; text-align:left;">
          <label class="form-label">Account Status</label>
          <select name="status" class="form-control">
            <?php foreach (['active'=>'Active','inactive'=>'Inactive','graduated'=>'Graduated','dropped'=>'Dropped'] as $val => $lbl): ?>
            <option value="<?= $val ?>" <?= $student['status']===$val?'selected':'' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group" style="text-align:left;">
          <label class="form-label">Student ID</label>
          <input type="text" class="form-control" value="<?= e($student['student_id']) ?>" readonly>
        </div>
      </div>
    </div>

    <!-- Main Form Tabs -->
    <div class="card">
      <div class="card-header"><h3 class="card-title"><i class="ri-edit-line"></i> Edit Details</h3></div>
      <div class="card-body">
        <div class="tabs">
          <button type="button" class="tab-btn active" data-tab="ep-personal">Personal</button>
          <button type="button" class="tab-btn" data-tab="ep-academic">Academic</button>
          <button type="button" class="tab-btn" data-tab="ep-guardian">Guardian</button>
          <button type="button" class="tab-btn" data-tab="ep-account">Account</button>
        </div>

        <div class="tab-pane active" id="ep-personal">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Full Name <span class="required">*</span></label>
              <input type="text" name="full_name" class="form-control" required value="<?= e($student['full_name']) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Date of Birth</label>
              <input type="date" name="date_of_birth" class="form-control" value="<?= e($student['date_of_birth'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Gender</label>
              <select name="gender" class="form-control">
                <option value="">Select</option>
                <?php foreach (['male','female','other'] as $g): ?>
                <option value="<?= $g ?>" <?= $student['gender']===$g?'selected':'' ?>><?= ucfirst($g) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Blood Group</label>
              <select name="blood_group" class="form-control">
                <option value="">Select</option>
                <?php foreach (['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $bg): ?>
                <option value="<?= $bg ?>" <?= $student['blood_group']===$bg?'selected':'' ?>><?= $bg ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Phone</label>
              <input type="tel" name="phone" class="form-control" value="<?= e($student['phone'] ?? '') ?>">
            </div>
            <div class="form-group full-width">
              <label class="form-label">Address</label>
              <textarea name="address" class="form-control" rows="2"><?= e($student['address'] ?? '') ?></textarea>
            </div>
          </div>
        </div>

        <div class="tab-pane" id="ep-academic">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Course</label>
              <select name="course_id" class="form-control">
                <option value="">-- None --</option>
                <?php foreach ($courses as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $student['course_id']==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Admission Date</label>
              <input type="date" name="admission_date" class="form-control" value="<?= e($student['admission_date']) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Batch Year</label>
              <input type="number" name="batch_year" class="form-control" value="<?= e($student['batch_year'] ?? date('Y')) ?>">
            </div>
          </div>
        </div>

        <div class="tab-pane" id="ep-guardian">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Guardian Name</label>
              <input type="text" name="guardian_name" class="form-control" value="<?= e($student['guardian_name'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Guardian Phone</label>
              <input type="tel" name="guardian_phone" class="form-control" value="<?= e($student['guardian_phone'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Guardian Email</label>
              <input type="email" name="guardian_email" class="form-control" value="<?= e($student['guardian_email'] ?? '') ?>">
            </div>
          </div>
        </div>

        <div class="tab-pane" id="ep-account">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Email <span class="required">*</span></label>
              <input type="email" name="email" class="form-control" required value="<?= e($student['email']) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Username</label>
              <input type="text" name="username" class="form-control" value="<?= e($student['username']) ?>">
            </div>
            <div class="form-group full-width">
              <label class="form-label">New Password <small class="text-muted">(leave blank to keep current)</small></label>
              <div class="password-toggle">
                <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current">
                <i class="ri-eye-line toggle-eye"></i>
              </div>
            </div>
          </div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:24px; padding-top:20px; border-top:1px solid var(--border);">
          <a href="<?= IMS_URL ?>/modules/students/index.php" class="btn btn-outline">Cancel</a>
          <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> Update Student</button>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
const area    = document.getElementById('photoUploadArea');
const input   = document.getElementById('profile_photo');
const preview = document.getElementById('photoPreview');
area?.addEventListener('click', () => input.click());
input?.addEventListener('change', () => {
  if (input.files[0]) {
    const r = new FileReader();
    r.onload = ev => { preview.src = ev.target.result; preview.style.display = 'block'; };
    r.readAsDataURL(input.files[0]);
  }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
