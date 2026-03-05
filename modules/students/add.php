<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin']);

$pageTitle  = 'Add Student';
$activePage = 'students';

$pdo     = db();
$errors  = [];
$success = false;

// Load courses for dropdown
$courses = $pdo->query("SELECT id, name, code FROM courses WHERE status='active' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Collect & sanitize inputs
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
    $batchYear     = (int)($_POST['batch_year'] ?? date('Y'));
    $admissionDate = sanitize($_POST['admission_date'] ?? date('Y-m-d'));
    $password      = $_POST['password'] ?? '';

    // Validation
    if (empty($fullName))   $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (empty($username))   $errors[] = 'Username is required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

    // Check unique email & username
    if (empty($errors)) {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
        $check->execute([$email, $username]);
        if ($check->fetch()) $errors[] = 'Email or username already in use.';
    }

    // Handle profile photo
    $photoFilename = null;
    if (!empty($_FILES['profile_photo']['name'])) {
        $file     = $_FILES['profile_photo'];
        $allowed  = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize  = 2 * 1024 * 1024;
        if (!in_array($file['type'], $allowed)) {
            $errors[] = 'Profile photo must be JPG, PNG or WebP.';
        } elseif ($file['size'] > $maxSize) {
            $errors[] = 'Profile photo must be under 2MB.';
        } else {
            $ext          = pathinfo($file['name'], PATHINFO_EXTENSION);
            $photoFilename = 'students/' . uniqid('stu_') . '.' . $ext;
            $uploadDir    = __DIR__ . '/../../uploads/students/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . basename($photoFilename))) {
                $errors[] = 'Failed to upload profile photo.';
                $photoFilename = null;
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Create user
            $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $userStmt = $pdo->prepare(
                "INSERT INTO users (role_id, username, email, password, full_name, phone, profile_photo, status)
                 VALUES (3, ?, ?, ?, ?, ?, ?, 'active')"
            );
            $userStmt->execute([$username, $email, $hashed, $fullName, $phone, $photoFilename]);
            $userId = (int)$pdo->lastInsertId();

            // Generate student ID
            $studentId = generate_student_id();

            // Create student record
            $studStmt = $pdo->prepare(
                "INSERT INTO students (user_id, student_id, course_id, date_of_birth, gender,
                  blood_group, address, guardian_name, guardian_phone, guardian_email,
                  admission_date, batch_year, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')"
            );
            $studStmt->execute([
                $userId, $studentId, $courseId ?: null, $dob ?: null,
                $gender ?: null, $bloodGroup ?: null, $address,
                $guardianName, $guardianPhone, $guardianEmail,
                $admissionDate, $batchYear,
            ]);
            $newStudId = (int)$pdo->lastInsertId();

            // Auto-enroll in course
            if ($courseId) {
                $pdo->prepare("INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (?, ?)")
                    ->execute([$newStudId, $courseId]);
            }

            $pdo->commit();

            log_activity('create_student', 'students', "Added student: $fullName ($studentId)");
            set_toast('success', "Student $fullName added successfully! ID: $studentId");
            header('Location: ' . IMS_URL . '/modules/students/index.php');
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log($e->getMessage());
            $errors[] = 'Database error. Please try again.';
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Add New Student</h1>
    <p class="page-subtitle">Create a student account and profile</p>
  </div>
  <div class="page-header-actions">
    <a href="<?= IMS_URL ?>/modules/students/index.php" class="btn btn-outline">
      <i class="ri-arrow-left-line"></i> Back to Students
    </a>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
  <i class="ri-error-warning-fill"></i>
  <div><strong>Please fix the following errors:</strong><ul style="margin-top:8px; padding-left:16px;">
    <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
  </ul></div>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" data-validate>
  <?= csrf_field() ?>

  <div style="display:grid; grid-template-columns: 1fr 2fr; gap: 24px; align-items:start;">

    <!-- Photo Upload -->
    <div class="card">
      <div class="card-header"><h3 class="card-title"><i class="ri-image-line"></i> Profile Photo</h3></div>
      <div class="card-body" style="text-align:center;">
        <img id="photoPreview" src="" alt="Preview" class="photo-preview" style="display:none; margin:0 auto 16px;">
        <div class="photo-upload-area" id="photoUploadArea">
          <div class="upload-icon"><i class="ri-upload-cloud-2-line"></i></div>
          <p><strong>Click to upload</strong> or drag & drop</p>
          <p>JPG, PNG, WebP - Max 2MB</p>
        </div>
        <input type="file" name="profile_photo" id="profile_photo" accept="image/*" style="display:none;">
      </div>
    </div>

    <!-- Main Form -->
    <div class="card">
      <div class="card-header"><h3 class="card-title"><i class="ri-user-3-line"></i> Student Information</h3></div>
      <div class="card-body">
        <!-- Tabs -->
        <div class="tabs" data-group="student-tabs">
          <button type="button" class="tab-btn active" data-tab="tab-personal">Personal Info</button>
          <button type="button" class="tab-btn" data-tab="tab-academic">Academic</button>
          <button type="button" class="tab-btn" data-tab="tab-guardian">Guardian</button>
          <button type="button" class="tab-btn" data-tab="tab-account">Account</button>
        </div>

        <!-- Personal Tab -->
        <div class="tab-pane active" id="tab-personal">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Full Name <span class="required">*</span></label>
              <input type="text" name="full_name" class="form-control" required
                     placeholder="e.g. John Doe"
                     value="<?= e($_POST['full_name'] ?? '') ?>">
              <span class="form-error">Full name is required.</span>
            </div>
            <div class="form-group">
              <label class="form-label">Date of Birth</label>
              <input type="date" name="date_of_birth" class="form-control"
                     value="<?= e($_POST['date_of_birth'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Gender</label>
              <select name="gender" class="form-control">
                <option value="">Select Gender</option>
                <option value="male"   <?= ($_POST['gender']??'')==='male'?'selected':'' ?>>Male</option>
                <option value="female" <?= ($_POST['gender']??'')==='female'?'selected':'' ?>>Female</option>
                <option value="other"  <?= ($_POST['gender']??'')==='other'?'selected':'' ?>>Other</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Blood Group</label>
              <select name="blood_group" class="form-control">
                <option value="">Select Blood Group</option>
                <?php foreach (['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $bg): ?>
                <option value="<?= $bg ?>" <?= ($_POST['blood_group']??'')===$bg?'selected':'' ?>><?= $bg ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Phone</label>
              <input type="tel" name="phone" class="form-control"
                     placeholder="+1 555 000 0000"
                     value="<?= e($_POST['phone'] ?? '') ?>">
            </div>
            <div class="form-group full-width">
              <label class="form-label">Address</label>
              <textarea name="address" class="form-control" rows="2"
                        placeholder="Student home address"><?= e($_POST['address'] ?? '') ?></textarea>
            </div>
          </div>
        </div>

        <!-- Academic Tab -->
        <div class="tab-pane" id="tab-academic">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Course</label>
              <select name="course_id" class="form-control">
                <option value="">-- Select Course --</option>
                <?php foreach ($courses as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($_POST['course_id']??'')==$c['id']?'selected':'' ?>>
                  <?= e($c['name']) ?> (<?= e($c['code']) ?>)
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Admission Date</label>
              <input type="date" name="admission_date" class="form-control"
                     value="<?= e($_POST['admission_date'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Batch Year</label>
              <input type="number" name="batch_year" class="form-control"
                     min="2000" max="<?= date('Y') + 2 ?>"
                     value="<?= e($_POST['batch_year'] ?? date('Y')) ?>">
            </div>
          </div>
        </div>

        <!-- Guardian Tab -->
        <div class="tab-pane" id="tab-guardian">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Guardian Name</label>
              <input type="text" name="guardian_name" class="form-control"
                     placeholder="Parent / Guardian name"
                     value="<?= e($_POST['guardian_name'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Guardian Phone</label>
              <input type="tel" name="guardian_phone" class="form-control"
                     placeholder="+1 555 000 0000"
                     value="<?= e($_POST['guardian_phone'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Guardian Email</label>
              <input type="email" name="guardian_email" class="form-control"
                     placeholder="guardian@email.com"
                     value="<?= e($_POST['guardian_email'] ?? '') ?>">
            </div>
          </div>
        </div>

        <!-- Account Tab -->
        <div class="tab-pane" id="tab-account">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Email <span class="required">*</span></label>
              <input type="email" name="email" class="form-control" required
                     placeholder="student@email.com"
                     value="<?= e($_POST['email'] ?? '') ?>">
              <span class="form-error">Valid email required.</span>
            </div>
            <div class="form-group">
              <label class="form-label">Username <span class="required">*</span></label>
              <input type="text" name="username" class="form-control" required
                     placeholder="e.g. john.doe"
                     value="<?= e($_POST['username'] ?? '') ?>">
              <span class="form-error">Username is required.</span>
            </div>
            <div class="form-group">
              <label class="form-label">Password <span class="required">*</span></label>
              <div class="password-toggle">
                <input type="password" name="password" class="form-control" required
                       placeholder="Min. 8 characters">
                <i class="ri-eye-line toggle-eye"></i>
              </div>
              <span class="form-error">Password must be at least 8 characters.</span>
            </div>
          </div>
        </div>

        <!-- Form Actions -->
        <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:24px; padding-top:20px; border-top:1px solid var(--border);">
          <a href="<?= IMS_URL ?>/modules/students/index.php" class="btn btn-outline">Cancel</a>
          <button type="submit" class="btn btn-primary">
            <i class="ri-save-line"></i> Save Student
          </button>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
// Wire up photo upload area
const area     = document.getElementById('photoUploadArea');
const input    = document.getElementById('profile_photo');
const preview  = document.getElementById('photoPreview');

area?.addEventListener('click', () => input.click());
input?.addEventListener('change', () => {
  if (input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
    reader.readAsDataURL(input.files[0]);
  }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
