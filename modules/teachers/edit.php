<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin']);
$pdo = db();
$id  = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . IMS_URL . '/modules/teachers/index.php'); exit; }

$stmt = $pdo->prepare("SELECT t.*, u.full_name, u.email, u.username, u.phone, u.profile_photo, u.status AS user_status FROM teachers t JOIN users u ON u.id=t.user_id WHERE t.id=? LIMIT 1");
$stmt->execute([$id]);
$teacher = $stmt->fetch();
if (!$teacher) { set_toast('error','Teacher not found.'); header('Location: ' . IMS_URL . '/modules/teachers/index.php'); exit; }

$pageTitle = 'Edit Teacher';
$activePage = 'teachers';
$errors = [];
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fullName      = sanitize($_POST['full_name'] ?? '');
    $email         = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone         = sanitize($_POST['phone'] ?? '');
    $deptId        = (int)($_POST['department_id'] ?? 0);
    $qualification = sanitize($_POST['qualification'] ?? '');
    $specialization= sanitize($_POST['specialization'] ?? '');
    $salary        = (float)($_POST['salary'] ?? 0);
    $joinDate      = sanitize($_POST['join_date'] ?? '');
    $newPassword   = $_POST['new_password'] ?? '';
    $status        = sanitize($_POST['status'] ?? 'active');

    if (empty($fullName)) $errors[] = 'Full name required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';

    if (empty($errors)) {
        try {
            $pdo->prepare("UPDATE users SET full_name=?,email=?,phone=?,status=? WHERE id=?")
                ->execute([$fullName,$email,$phone,$status,$teacher['user_id']]);
            if (!empty($newPassword) && strlen($newPassword)>=8) {
                $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($newPassword,PASSWORD_BCRYPT,['cost'=>12]),$teacher['user_id']]);
            }
            $pdo->prepare("UPDATE teachers SET department_id=?,qualification=?,specialization=?,salary=?,join_date=? WHERE id=?")
                ->execute([$deptId?:null,$qualification,$specialization,$salary?:null,$joinDate?:null,$id]);
            set_toast('success','Teacher profile updated successfully!');
            header('Location: ' . IMS_URL . '/modules/teachers/index.php');
            exit;
        } catch (PDOException $e) { $errors[] = 'Database error occurred.'; }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
/* ══════════════════════════════════════════
   EDIT TEACHER — PREMIUM DESIGN
══════════════════════════════════════════ */
.te-hero {
  background: linear-gradient(135deg, #0F172A 0%, #065F46 55%, #1E293B 100%);
  border-radius: 20px; padding: 36px 40px; position: relative; overflow: hidden;
  margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between;
  box-shadow: 0 10px 30px rgba(0,0,0,.15);
}
.te-hero::before {
  content:''; position:absolute; width:400px; height:400px;
  background: radial-gradient(circle, rgba(16,185,129,.2) 0%, transparent 70%);
  top:-120px; right:-80px; pointer-events:none;
}
.te-hero::after {
  content:''; position:absolute; inset:0;
  background-image: radial-gradient(circle at 2px 2px, rgba(255,255,255,.05) 1px, transparent 0);
  background-size: 24px 24px; pointer-events:none;
}
.te-hero-content { position: relative; z-index: 1; display:flex; gap: 20px; align-items:center; }
.te-hero-avatar {
  width: 72px; height: 72px; border-radius: 50%; object-fit: cover;
  border: 3px solid rgba(255,255,255,.2); box-shadow: 0 4px 15px rgba(0,0,0,.2);
}
.te-hero-initials {
  width: 72px; height: 72px; border-radius: 50%;
  background: linear-gradient(135deg, #10B981, #059669);
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-heading); font-size: 26px; font-weight: 800; color: #fff;
  border: 3px solid rgba(255,255,255,.2); box-shadow: 0 4px 15px rgba(0,0,0,.2);
}
.te-hero h1 { font-family: var(--font-heading); font-size: 26px; font-weight: 800; color: #fff; margin: 0 0 4px; }
.te-hero p { font-size: 14px; color: rgba(255,255,255,.7); margin: 0; display:flex; align-items:center; gap:8px;}
.te-badge { background: rgba(16,185,129,.2); color: #34D399; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; border: 1px solid rgba(16,185,129,.3); font-family: monospace; }
.te-back-btn {
  position: relative; z-index: 1; display: inline-flex; align-items: center; gap: 6px;
  padding: 10px 20px; border-radius: 12px; font-size: 13px; font-weight: 700;
  background: rgba(255,255,255,.1); color: #fff; text-decoration: none;
  border: 1px solid rgba(255,255,255,.2); backdrop-filter: blur(10px); transition: all .2s;
}
.te-back-btn:hover { background: rgba(255,255,255,.2); transform: translateY(-2px); }

/* Layout & Cards */
.te-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start; }
@media (max-width:900px) { .te-layout { grid-template-columns: 1fr; } }
.te-card {
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: 20px; overflow: hidden; box-shadow: var(--shadow-sm);
  margin-bottom: 24px;
}
.te-card-header {
  padding: 20px 24px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 12px; background: var(--bg);
}
[data-theme="dark"] .te-card-header { background: var(--bg-hover); }
.te-icon-box {
  width: 38px; height: 38px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center; font-size: 18px;
}
.te-card-title { font-size: 16px; font-weight: 700; color: var(--text-primary); margin:0; }
.te-card-sub { font-size: 12px; color: var(--text-muted); margin: 2px 0 0; }
.te-card-body { padding: 24px; }

/* Form Elements */
.te-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media (max-width:600px) { .te-grid { grid-template-columns: 1fr; } }
.te-field { margin-bottom: 20px; }
.te-field:last-child { margin-bottom: 0; }
.te-field.full { grid-column: 1 / -1; }
.te-label {
  display: block; font-size: 12px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .5px;
  color: var(--text-muted); margin-bottom: 8px;
}
.te-label i { margin-right: 4px; font-size: 13px; }
.te-req { color: var(--danger); }
.te-input, .te-select {
  width: 100%; padding: 12px 16px; font-size: 14px;
  border: 1.5px solid var(--border); border-radius: 12px;
  background: var(--bg-card); color: var(--text-primary);
  outline: none; transition: all .2s; box-sizing: border-box;
}
.te-input:focus, .te-select:focus {
  border-color: #10B981; box-shadow: 0 0 0 4px rgba(16,185,129,.1);
}
.te-input:read-only { background: var(--bg-hover); color: var(--text-muted); cursor: not-allowed; }

/* Status Toggle */
.te-status-wrap {
  display: flex; background: var(--bg-hover); border-radius: 12px;
  padding: 4px; border: 1.5px solid var(--border);
}
.te-status-wrap label {
  flex: 1; text-align: center; padding: 10px; font-size: 13px; font-weight: 700;
  border-radius: 8px; cursor: pointer; color: var(--text-secondary); transition: all .2s;
  margin: 0;
}
.te-status-wrap input:checked + label { background: var(--bg-card); color: var(--text-primary); box-shadow: var(--shadow-sm); }
.te-status-wrap input[value="active"]:checked + label { color: var(--success); }
.te-status-wrap input[value="inactive"]:checked + label { color: var(--danger); }

/* Password */
.te-pass-wrap { position: relative; }
.te-pass-eye { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); font-size: 18px; color: var(--text-muted); cursor: pointer; }

/* Action Footer */
.te-footer {
  display: flex; justify-content: flex-end; gap: 12px;
  padding: 24px; background: var(--bg); border-top: 1px solid var(--border);
}
[data-theme="dark"] .te-footer { background: var(--bg-hover); }
.te-btn-save {
  padding: 12px 28px; border-radius: 12px; background: linear-gradient(135deg, #10B981, #059669);
  color: #fff; font-size: 14px; font-weight: 700; border: none; cursor: pointer;
  box-shadow: 0 6px 20px rgba(16,185,129,.3); transition: transform .2s, box-shadow .2s;
  display: inline-flex; align-items: center; gap: 8px;
}
.te-btn-save:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16,185,129,.4); }
.te-btn-cancel {
  padding: 12px 24px; border-radius: 12px; background: var(--bg-card);
  color: var(--text-secondary); font-size: 14px; font-weight: 600; text-decoration: none;
  border: 1.5px solid var(--border); transition: all .2s; display: inline-flex; align-items: center; gap: 6px;
}
.te-btn-cancel:hover { background: var(--bg-hover); color: var(--text-primary); }

/* Err box */
.te-err { background: rgba(239,68,68,.1); border-left: 4px solid var(--danger); padding: 16px; border-radius: 12px; margin-bottom: 24px; display:flex; gap:12px; }
.te-err i { color: var(--danger); font-size: 20px; }
.te-err ul { margin:0; padding-left:18px; font-size:13px; font-weight:500; color:var(--text-primary); }
</style>

<!-- HEADER BANNER -->
<div class="te-hero">
  <div class="te-hero-content">
    <?php if (!empty($teacher['profile_photo'])): ?>
      <img src="<?= IMS_URL ?>/uploads/<?= e($teacher['profile_photo']) ?>" class="te-hero-avatar" alt="Profile">
    <?php else: ?>
      <div class="te-hero-initials"><?= get_initials($teacher['full_name']) ?></div>
    <?php endif; ?>
    <div>
      <h1><?= e($teacher['full_name']) ?></h1>
      <p>
        <span class="te-badge"><?= e($teacher['teacher_id']) ?></span>
        <span>Teacher Profile Modification</span>
      </p>
    </div>
  </div>
  <a href="<?= IMS_URL ?>/modules/teachers/index.php" class="te-back-btn">
    <i class="ri-arrow-left-line"></i> Back to Directory
  </a>
</div>

<?php if (!empty($errors)): ?>
<div class="te-err">
  <i class="ri-error-warning-fill"></i>
  <div><ul><?php foreach($errors as $e2): ?><li><?= e($e2)?></li><?php endforeach;?></ul></div>
</div>
<?php endif; ?>

<form method="POST" data-validate>
  <?= csrf_field() ?>
  <div class="te-layout">

    <!-- LEFT COLUMN -->
    <div>
      <!-- Personal Info -->
      <div class="te-card">
        <div class="te-card-header">
          <div class="te-icon-box" style="background:rgba(59,130,246,.15); color:#3B82F6;"><i class="ri-user-line"></i></div>
          <div>
            <h3 class="te-card-title">Personal Information</h3>
            <p class="te-card-sub">Basic contact and identity details</p>
          </div>
        </div>
        <div class="te-card-body te-grid">
          <div class="te-field full">
            <label class="te-label"><i class="ri-id-card-line"></i> Full Name <span class="te-req">*</span></label>
            <input type="text" name="full_name" class="te-input" required value="<?= e($teacher['full_name']) ?>">
          </div>
          <div class="te-field">
            <label class="te-label"><i class="ri-mail-line"></i> Email Address <span class="te-req">*</span></label>
            <input type="email" name="email" class="te-input" required value="<?= e($teacher['email']) ?>">
          </div>
          <div class="te-field">
            <label class="te-label"><i class="ri-phone-line"></i> Phone Number</label>
            <input type="tel" name="phone" class="te-input" value="<?= e($teacher['phone']??'') ?>">
          </div>
        </div>
      </div>

      <!-- Professional Info -->
      <div class="te-card">
        <div class="te-card-header">
          <div class="te-icon-box" style="background:rgba(16,185,129,.15); color:#10B981;"><i class="ri-briefcase-4-line"></i></div>
          <div>
            <h3 class="te-card-title">Professional Information</h3>
            <p class="te-card-sub">Academic and employment records</p>
          </div>
        </div>
        <div class="te-card-body te-grid">
          <div class="te-field full">
            <label class="te-label"><i class="ri-building-line"></i> Assign Department</label>
            <select name="department_id" class="te-select">
              <option value="">-- No Department --</option>
              <?php foreach ($departments as $d): ?>
              <option value="<?= $d['id'] ?>" <?= $teacher['department_id']==$d['id']?'selected':'' ?>><?= e($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="te-field">
            <label class="te-label"><i class="ri-medal-line"></i> Qualification</label>
            <input type="text" name="qualification" class="te-input" placeholder="e.g. M.Sc, Ph.D" value="<?= e($teacher['qualification']??'') ?>">
          </div>
          <div class="te-field">
            <label class="te-label"><i class="ri-focus-2-line"></i> Specialization</label>
            <input type="text" name="specialization" class="te-input" value="<?= e($teacher['specialization']??'') ?>">
          </div>
          <div class="te-field">
            <label class="te-label"><i class="ri-money-dollar-circle-line"></i> Monthly Salary</label>
            <input type="number" name="salary" class="te-input" step="0.01" value="<?= e($teacher['salary']??'') ?>">
          </div>
          <div class="te-field">
            <label class="te-label"><i class="ri-calendar-event-line"></i> Date of Joining</label>
            <input type="date" name="join_date" class="te-input" value="<?= e($teacher['join_date']??'') ?>">
          </div>
        </div>
      </div>
    </div> <!-- /left col -->

    <!-- RIGHT COLUMN -->
    <div>
      <!-- Account & Security -->
      <div class="te-card">
        <div class="te-card-header">
          <div class="te-icon-box" style="background:rgba(245,158,11,.15); color:#F59E0B;"><i class="ri-shield-keyhole-line"></i></div>
          <div>
            <h3 class="te-card-title">Account Access</h3>
            <p class="te-card-sub">Security and platform login</p>
          </div>
        </div>
        <div class="te-card-body">
          <div class="te-field">
            <label class="te-label"><i class="ri-fingerprint-line"></i> Username</label>
            <input type="text" class="te-input" value="<?= e($teacher['username']) ?>" readonly>
          </div>
          <div class="te-field">
            <label class="te-label"><i class="ri-lock-password-line"></i> Reset Password</label>
            <div class="te-pass-wrap">
              <input type="password" name="new_password" id="newPass" class="te-input" placeholder="Leave blank to keep current">
              <i class="ri-eye-line te-pass-eye" onclick="document.getElementById('newPass').type = document.getElementById('newPass').type === 'password' ? 'text' : 'password'; this.classList.toggle('ri-eye-off-line');"></i>
            </div>
            <p style="font-size:11px; color:var(--text-muted); margin:6px 0 0;">Minimum 8 characters if changing.</p>
          </div>
        </div>
      </div>

      <!-- Status -->
      <div class="te-card">
        <div class="te-card-header">
          <div class="te-icon-box" style="background:rgba(139,92,246,.15); color:#8B5CF6;"><i class="ri-toggle-line"></i></div>
          <div>
            <h3 class="te-card-title">Account Status</h3>
            <p class="te-card-sub">Enable or disable login access</p>
          </div>
        </div>
        <div class="te-card-body">
          <div class="te-status-wrap">
            <input type="radio" id="st_active" name="status" value="active" <?= $teacher['user_status']==='active'?'checked':'' ?> style="display:none;">
            <label for="st_active"><i class="ri-checkbox-circle-line"></i> Active</label>
            
            <input type="radio" id="st_inactive" name="status" value="inactive" <?= $teacher['user_status']==='inactive'?'checked':'' ?> style="display:none;">
            <label for="st_inactive"><i class="ri-close-circle-line"></i> Inactive</label>
          </div>
        </div>
      </div>

      <!-- Action Box -->
      <div class="te-card" style="background:transparent; border:none; box-shadow:none;">
        <button type="submit" class="te-btn-save" style="width:100%; justify-content:center; padding:16px;">
          <i class="ri-save-line" style="font-size:18px;"></i> Save All Changes
        </button>
      </div>

    </div> <!-- /right col -->
  </div> <!-- /layout -->
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
