<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin']);

$pageTitle  = 'Add Teacher';
$activePage = 'teachers';
$pdo    = db();
$errors = [];

$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $fullName      = sanitize($_POST['full_name'] ?? '');
    $email         = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $username      = sanitize($_POST['username'] ?? '');
    $phone         = sanitize($_POST['phone'] ?? '');
    $deptId        = (int)($_POST['department_id'] ?? 0);
    $qualification = sanitize($_POST['qualification'] ?? '');
    $specialization= sanitize($_POST['specialization'] ?? '');
    $salary        = (float)($_POST['salary'] ?? 0);
    $joinDate      = sanitize($_POST['join_date'] ?? date('Y-m-d'));
    $address       = sanitize($_POST['address'] ?? '');
    $password      = $_POST['password'] ?? '';

    if (empty($fullName)) $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
    if (empty($username)) $errors[] = 'Username is required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

    if (empty($errors)) {
        $check = $pdo->prepare("SELECT id FROM users WHERE email=? OR username=? LIMIT 1");
        $check->execute([$email, $username]);
        if ($check->fetch()) $errors[] = 'Email or username already exists.';
    }

    // Photo
    $photoFilename = null;
    if (!empty($_FILES['profile_photo']['name'])) {
        $file = $_FILES['profile_photo'];
        $allowed = ['image/jpeg','image/png','image/webp'];
        if (!in_array($file['type'], $allowed)) $errors[] = 'Photo must be JPG/PNG/WebP.';
        elseif ($file['size'] > 2*1024*1024) $errors[] = 'Photo max 2MB.';
        else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $photoFilename = 'teachers/' . uniqid('tch_') . '.' . $ext;
            $dir = __DIR__ . '/../../uploads/teachers/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if (!move_uploaded_file($file['tmp_name'], $dir . basename($photoFilename))) {
                $errors[] = 'Upload failed.'; $photoFilename = null;
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]);
            $pdo->prepare("INSERT INTO users (role_id,username,email,password,full_name,phone,profile_photo,status) VALUES (2,?,?,?,?,?,?,'active')")
                ->execute([$username, $email, $hashed, $fullName, $phone, $photoFilename]);
            $uid = (int)$pdo->lastInsertId();

            $tid = generate_teacher_id();
            $pdo->prepare("INSERT INTO teachers (user_id,teacher_id,department_id,qualification,specialization,salary,join_date,address) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$uid, $tid, $deptId ?: null, $qualification, $specialization, $salary ?: null, $joinDate ?: null, $address]);
            $pdo->commit();

            log_activity('create_teacher', 'teachers', "Added teacher: $fullName ($tid)");
            set_toast('success', "Teacher $fullName added! ID: $tid");
            header('Location: ' . IMS_URL . '/modules/teachers/index.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Database error.';
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Add Teacher</h1>
    <p class="page-subtitle">Create a new teacher account</p>
  </div>
  <div class="page-header-actions">
    <a href="<?= IMS_URL ?>/modules/teachers/index.php" class="btn btn-outline"><i class="ri-arrow-left-line"></i> Back</a>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger"><i class="ri-error-warning-fill"></i>
  <div><strong>Errors:</strong><ul style="margin-top:6px;padding-left:16px;">
    <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
  </ul></div>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" data-validate>
  <?= csrf_field() ?>
  <div style="display:grid;grid-template-columns:1fr 2fr;gap:24px;align-items:start;">

    <div class="card">
      <div class="card-header"><h3 class="card-title"><i class="ri-image-line"></i> Photo</h3></div>
      <div class="card-body" style="text-align:center;">
        <img id="photoPreview" src="" class="photo-preview" style="display:none;margin:0 auto 16px;">
        <div class="photo-upload-area" id="photoUploadArea">
          <div class="upload-icon"><i class="ri-upload-cloud-2-line"></i></div>
          <p><strong>Click to upload</strong></p><p>JPG, PNG, WebP -- Max 2MB</p>
        </div>
        <input type="file" name="profile_photo" id="profile_photo" accept="image/*" style="display:none;">
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="card-title"><i class="ri-user-star-line"></i> Teacher Details</h3></div>
      <div class="card-body">
        <div class="tabs">
          <button type="button" class="tab-btn active" data-tab="tt-personal">Personal</button>
          <button type="button" class="tab-btn" data-tab="tt-professional">Professional</button>
          <button type="button" class="tab-btn" data-tab="tt-account">Account</button>
        </div>

        <div class="tab-pane active" id="tt-personal">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Full Name <span class="required">*</span></label>
              <input type="text" name="full_name" class="form-control" required placeholder="Dr. John Smith" value="<?= e($_POST['full_name']??'') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Phone</label>
              <input type="tel" name="phone" class="form-control" placeholder="+1 555 000 0000" value="<?= e($_POST['phone']??'') ?>">
            </div>
            <div class="form-group full-width">
              <label class="form-label">Address</label>
              <textarea name="address" class="form-control" rows="2"><?= e($_POST['address']??'') ?></textarea>
            </div>
          </div>
        </div>

        <div class="tab-pane" id="tt-professional">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Department</label>
              <select name="department_id" class="form-control">
                <option value="">-- Select Department --</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Qualification</label>
              <input type="text" name="qualification" class="form-control" placeholder="e.g. M.Sc. Computer Science" value="<?= e($_POST['qualification']??'') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Specialization</label>
              <input type="text" name="specialization" class="form-control" placeholder="e.g. Machine Learning" value="<?= e($_POST['specialization']??'') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Monthly Salary ($)</label>
              <input type="number" name="salary" class="form-control" step="0.01" min="0" placeholder="0.00" value="<?= e($_POST['salary']??'') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Join Date</label>
              <input type="date" name="join_date" class="form-control" value="<?= e($_POST['join_date']??date('Y-m-d')) ?>">
            </div>
          </div>
        </div>

        <div class="tab-pane" id="tt-account">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Email <span class="required">*</span></label>
              <input type="email" name="email" class="form-control" required placeholder="teacher@ims.com" value="<?= e($_POST['email']??'') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Username <span class="required">*</span></label>
              <input type="text" name="username" class="form-control" required placeholder="e.g. dr.smith" value="<?= e($_POST['username']??'') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Password <span class="required">*</span></label>
              <div class="password-toggle">
                <input type="password" name="password" class="form-control" required placeholder="Min. 8 characters">
                <i class="ri-eye-line toggle-eye"></i>
              </div>
            </div>
          </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--border);">
          <a href="<?= IMS_URL ?>/modules/teachers/index.php" class="btn btn-outline">Cancel</a>
          <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> Save Teacher</button>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
const area=document.getElementById('photoUploadArea'),input=document.getElementById('profile_photo'),preview=document.getElementById('photoPreview');
area?.addEventListener('click',()=>input.click());
input?.addEventListener('change',()=>{if(input.files[0]){const r=new FileReader();r.onload=e=>{preview.src=e.target.result;preview.style.display='block';};r.readAsDataURL(input.files[0]);}});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
