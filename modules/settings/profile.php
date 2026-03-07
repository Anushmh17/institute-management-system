<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin', 'teacher', 'student']);

$pageTitle  = 'My Profile';
$activePage = 'settings';
$pdo = db();

$errors = [];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) { header('Location: ' . IMS_URL . '/dashboard.php'); exit; }

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    verify_csrf();
    $file = $_FILES['avatar'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        if (in_array($file['type'], $allowed) && $file['size'] <= 2 * 1024 * 1024) {
            $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
            $name = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
            $dest = __DIR__ . '/../../uploads/' . $name;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $pdo->prepare("UPDATE users SET profile_photo=? WHERE id=?")->execute([$name, $_SESSION['user_id']]);
                $_SESSION['photo'] = $name;
                $user['profile_photo'] = $name;
            }
        } else {
            $errors[] = 'Avatar must be JPG/PNG/GIF/WEBP and under 2 MB.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    verify_csrf();
    $fullName    = sanitize($_POST['full_name'] ?? '');
    $email       = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone       = sanitize($_POST['phone'] ?? '');
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';

    if (empty($fullName)) $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if (!empty($newPass)) {
        if (!password_verify($currentPass, $user['password'])) $errors[] = 'Current password is incorrect.';
        if (strlen($newPass) < 8) $errors[] = 'New password must be at least 8 characters.';
    }

    if (empty($errors)) {
        $pdo->prepare("UPDATE users SET full_name=?,email=?,phone=? WHERE id=?")
            ->execute([$fullName, $email, $phone, $_SESSION['user_id']]);
        if (!empty($newPass)) {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                ->execute([password_hash($newPass, PASSWORD_BCRYPT, ['cost'=>12]), $_SESSION['user_id']]);
        }
        $_SESSION['full_name'] = $fullName;
        $_SESSION['email']     = $email;
        set_toast('success', 'Profile updated successfully!');
        header('Location: ' . IMS_URL . '/modules/settings/profile.php');
        exit;
    }
}

$role       = $_SESSION['role'] ?? 'admin';
$initials   = get_initials($user['full_name'] ?? 'User');
$memberSince = isset($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : 'N/A';
$lastLogin   = isset($user['last_login'])  ? date('d M Y, g:i A', strtotime($user['last_login'])) : 'Never';

$roleColors = ['admin'=>'#7C3AED','teacher'=>'#059669','student'=>'#2563EB'];
$roleIcons  = ['admin'=>'ri-shield-user-line','teacher'=>'ri-book-read-line','student'=>'ri-graduation-cap-line'];
$accentColor = $roleColors[$role] ?? '#2563EB';

include __DIR__ . '/../../includes/header.php';
?>

<style>
/* ══════════════════════════════════════════
   PROFILE PAGE — PREMIUM STYLES
══════════════════════════════════════════ */

/* ── Hero Banner ──────────────────────────── */
.pf-hero {
  background: linear-gradient(135deg, #0F172A 0%, #1E1B4B 45%, #0F172A 100%);
  border-radius: 22px; height: 180px; position: relative; overflow: hidden;
  margin-bottom: 0;
}
.pf-hero::before {
  content:''; position:absolute;
  width:400px; height:400px;
  background: radial-gradient(circle, rgba(124,58,237,.28) 0%, transparent 65%);
  top:-140px; right:-70px; pointer-events:none;
}
.pf-hero::after {
  content:''; position:absolute;
  width:250px; height:250px;
  background: radial-gradient(circle, rgba(245,158,11,.12) 0%, transparent 70%);
  bottom:-80px; left:5%; pointer-events:none;
}
.pf-hero-pattern {
  position:absolute; inset:0;
  background-image: radial-gradient(circle at 2px 2px, rgba(255,255,255,.06) 1px, transparent 0);
  background-size: 28px 28px;
}

/* ── Profile Header Card ──────────────────── */
.pf-header-card {
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: 0 0 20px 20px; border-top: none;
  padding: 0 32px 26px;
  box-shadow: var(--shadow-md);
  margin-bottom: 26px;
}
.pf-avatar-row {
  display: flex; align-items: flex-end; justify-content: space-between;
  flex-wrap: wrap; gap: 16px;
}
.pf-avatar-group {
  display: flex; align-items: flex-end; gap: 20px;
}
.pf-avatar-wrap {
  position: relative; flex-shrink: 0;
  margin-top: -50px;
}
.pf-avatar {
  width: 100px; height: 100px; border-radius: 50%;
  object-fit: cover;
  border: 4px solid var(--bg-card);
  box-shadow: 0 4px 20px rgba(0,0,0,.2);
}
.pf-avatar-initials {
  width: 100px; height: 100px; border-radius: 50%;
  background: linear-gradient(135deg, #7C3AED, #2563EB);
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-heading); font-size: 34px; font-weight: 800; color: #fff;
  border: 4px solid var(--bg-card);
  box-shadow: 0 4px 20px rgba(0,0,0,.2);
  letter-spacing: -.5px;
}
.pf-avatar-upload-btn {
  position: absolute; bottom:4px; right:4px;
  width: 28px; height: 28px; border-radius: 50%;
  background: var(--primary-light); color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; cursor: pointer; border: 2px solid var(--bg-card);
  transition: transform .2s, background .2s;
}
.pf-avatar-upload-btn:hover { transform: scale(1.1); background: #1D4ED8; }
#avatarInput { display: none; }

.pf-identity { padding-bottom: 6px; }
.pf-identity h2 {
  font-family: var(--font-heading); font-size: 22px; font-weight: 800;
  color: var(--text-primary); margin: 0 0 5px; line-height: 1.2;
}
.pf-role-badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .5px;
}
.pf-header-actions { display: flex; gap: 10px; align-items: center; padding-bottom: 6px; }
.pf-meta-row {
  display: flex; gap: 24px; flex-wrap: wrap;
  margin-top: 18px; padding-top: 18px;
  border-top: 1px solid var(--border);
}
.pf-meta-item {
  display: flex; align-items: center; gap: 8px;
  font-size: 13px; color: var(--text-muted);
}
.pf-meta-item i { font-size: 15px; color: var(--text-muted); }
.pf-meta-item strong { color: var(--text-primary); font-weight: 600; }

/* ── Two-column layout ──────────────────── */
.pf-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media (max-width:820px) { .pf-layout { grid-template-columns: 1fr; } }

/* ── Section Card ──────────────────────── */
.pf-card {
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: 18px; overflow: hidden;
  box-shadow: var(--shadow-sm);
}
.pf-card-head {
  display: flex; align-items: center; gap: 10px;
  padding: 16px 22px; border-bottom: 1px solid var(--border);
  background: var(--bg);
}
[data-theme="dark"] .pf-card-head { background: var(--bg-hover); }
.pf-card-head-icon {
  width: 36px; height: 36px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 16px; flex-shrink: 0;
}
.pf-card-head-title { font-size: 14px; font-weight: 700; color: var(--text-primary); }
.pf-card-head-sub   { font-size: 11px; color: var(--text-muted); margin-top: 1px; }
.pf-card-body { padding: 22px; }

/* ── Form fields ───────────────────────── */
.pf-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width:540px) { .pf-form-row { grid-template-columns: 1fr; } }
.pf-field { margin-bottom: 18px; }
.pf-field:last-child { margin-bottom: 0; }
.pf-label {
  display: flex; align-items: center; gap: 5px;
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .5px; color: var(--text-muted); margin-bottom: 7px;
}
.pf-label i { font-size: 13px; }
.pf-input {
  width: 100%; padding: 10px 14px; font-size: 13px;
  border: 1.5px solid var(--border); border-radius: 10px;
  background: var(--bg-card); color: var(--text-primary);
  font-family: inherit; outline: none; transition: all .2s;
  box-sizing: border-box;
}
.pf-input:focus { border-color: #7C3AED; box-shadow: 0 0 0 3px rgba(124,58,237,.12); }
.pf-input:read-only { background: var(--bg-hover); color: var(--text-muted); cursor: not-allowed; }
.pf-pass-wrap { position: relative; }
.pf-pass-wrap .pf-input { padding-right: 40px; }
.pf-pass-eye {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  font-size: 16px; color: var(--text-muted); cursor: pointer; transition: color .2s;
}
.pf-pass-eye:hover { color: var(--text-primary); }

/* ── Section divider ───────────────────── */
.pf-section-divider {
  display: flex; align-items: center; gap: 12px;
  margin: 22px 0 18px;
}
.pf-section-divider-label {
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .8px; color: var(--text-muted); white-space: nowrap;
}
.pf-section-divider::before,
.pf-section-divider::after {
  content: ''; flex: 1; height: 1px; background: var(--border);
}

/* ── Save footer ───────────────────────── */
.pf-footer {
  display: flex; justify-content: flex-end; align-items: center;
  gap: 10px; padding-top: 20px; margin-top: 22px;
  border-top: 1px solid var(--border);
}
.pf-save-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 10px 26px; border-radius: 10px;
  background: linear-gradient(135deg, #7C3AED, #2563EB);
  color: #fff; font-size: 14px; font-weight: 700;
  border: none; cursor: pointer;
  box-shadow: 0 4px 14px rgba(124,58,237,.35);
  transition: opacity .2s, transform .15s;
}
.pf-save-btn:hover { opacity: .9; transform: translateY(-1px); }
.pf-cancel-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 10px 20px; border-radius: 10px;
  background: var(--bg-card); color: var(--text-secondary);
  font-size: 14px; font-weight: 600;
  border: 1.5px solid var(--border); text-decoration: none;
  transition: all .18s;
}
.pf-cancel-btn:hover { background: var(--bg-hover); color: var(--text-primary); }

/* ── Error box ──────────────────────────── */
.pf-err {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 14px 18px; border-left: 4px solid var(--danger);
  background: rgba(239,68,68,.06); border-radius: 12px;
  margin-bottom: 20px;
}
.pf-err i { font-size: 18px; color: var(--danger); flex-shrink:0; margin-top:1px; }
.pf-err ul { margin:0; padding-left:16px; font-size:13px; font-weight:500; }

/* ── Info stat items ───────────────────── */
.pf-info-grid { display: flex; flex-direction: column; gap: 0; }
.pf-info-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: 12px 0; border-bottom: 1px solid var(--border);
  font-size: 13px;
}
.pf-info-row:last-child { border-bottom: none; }
.pf-info-label { color: var(--text-muted); font-weight: 500; display:flex; align-items:center; gap:6px; }
.pf-info-label i { font-size: 14px; }
.pf-info-value { font-weight: 600; color: var(--text-primary); }
.pf-info-badge {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;
}
</style>

<!-- ═══ BANNER ════════════════════════════════ -->
<div class="pf-hero"><div class="pf-hero-pattern"></div></div>

<!-- ═══ PROFILE HEADER ════════════════════════ -->
<div class="pf-header-card">
  <div class="pf-avatar-row">
    <!-- Avatar + Name -->
    <div class="pf-avatar-group">
      <div class="pf-avatar-wrap">
        <?php if (!empty($user['profile_photo'])): ?>
          <img src="<?= IMS_URL ?>/uploads/<?= e($user['profile_photo']) ?>" class="pf-avatar" alt="Profile" id="avatarPreview">
        <?php else: ?>
          <div class="pf-avatar-initials" id="avatarPreview"><?= $initials ?></div>
        <?php endif; ?>
        <label for="avatarInput" class="pf-avatar-upload-btn" title="Change avatar">
          <i class="ri-camera-line"></i>
        </label>
        <form method="POST" enctype="multipart/form-data" id="avatarForm">
          <?= csrf_field() ?>
          <input type="file" id="avatarInput" name="avatar" accept="image/*">
        </form>
      </div>
      <div class="pf-identity">
        <h2><?= e($user['full_name']) ?></h2>
        <span class="pf-role-badge"
              style="background:<?= $accentColor ?>20; color:<?= $accentColor ?>; border:1px solid <?= $accentColor ?>30;">
          <i class="<?= $roleIcons[$role] ?? 'ri-user-line' ?>"></i>
          <?= ucfirst($role) ?>
        </span>
      </div>
    </div>
    <!-- Actions -->
    <div class="pf-header-actions">
      <a href="<?= IMS_URL ?>/dashboard.php" class="pf-cancel-btn">
        <i class="ri-arrow-left-line"></i> Dashboard
      </a>
    </div>
  </div>

  <!-- Meta Row -->
  <div class="pf-meta-row">
    <div class="pf-meta-item"><i class="ri-user-line"></i> <strong><?= e($user['username']) ?></strong></div>
    <div class="pf-meta-item"><i class="ri-mail-line"></i> <strong><?= e($user['email']) ?></strong></div>
    <?php if (!empty($user['phone'])): ?>
    <div class="pf-meta-item"><i class="ri-phone-line"></i> <strong><?= e($user['phone']) ?></strong></div>
    <?php endif; ?>
    <div class="pf-meta-item"><i class="ri-calendar-line"></i> Member since <strong><?= $memberSince ?></strong></div>
    <div class="pf-meta-item"><i class="ri-time-line"></i> Last login <strong><?= $lastLogin ?></strong></div>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="pf-err">
  <i class="ri-error-warning-fill"></i>
  <div><ul><?php foreach($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
</div>
<?php endif; ?>

<!-- ═══ MAIN LAYOUT ═══════════════════════════ -->
<div class="pf-layout">

  <!-- LEFT: Edit Profile Form -->
  <div class="pf-card">
    <div class="pf-card-head">
      <div class="pf-card-head-icon" style="background:rgba(124,58,237,.12); color:#7C3AED;">
        <i class="ri-edit-2-line"></i>
      </div>
      <div>
        <div class="pf-card-head-title">Edit Profile</div>
        <div class="pf-card-head-sub">Update your personal information</div>
      </div>
    </div>
    <div class="pf-card-body">
      <form method="POST" data-validate>
        <?= csrf_field() ?>
        <input type="hidden" name="save_profile" value="1">

        <div class="pf-field">
          <label class="pf-label"><i class="ri-user-line"></i> Full Name <span style="color:var(--danger);">*</span></label>
          <input type="text" name="full_name" class="pf-input" required value="<?= e($user['full_name']) ?>" placeholder="Your full name">
        </div>

        <div class="pf-form-row">
          <div class="pf-field">
            <label class="pf-label"><i class="ri-mail-line"></i> Email <span style="color:var(--danger);">*</span></label>
            <input type="email" name="email" class="pf-input" required value="<?= e($user['email']) ?>" placeholder="email@example.com">
          </div>
          <div class="pf-field">
            <label class="pf-label"><i class="ri-phone-line"></i> Phone</label>
            <input type="tel" name="phone" class="pf-input" value="<?= e($user['phone'] ?? '') ?>" placeholder="+1 000 000 0000">
          </div>
        </div>

        <div class="pf-field">
          <label class="pf-label"><i class="ri-fingerprint-line"></i> Username</label>
          <input type="text" class="pf-input" value="<?= e($user['username']) ?>" readonly>
        </div>

        <!-- Change Password -->
        <div class="pf-section-divider">
          <span class="pf-section-divider-label"><i class="ri-lock-line" style="color:var(--text-muted);"></i> Change Password</span>
        </div>

        <div class="pf-field">
          <label class="pf-label"><i class="ri-lock-password-line"></i> Current Password</label>
          <div class="pf-pass-wrap">
            <input type="password" name="current_password" class="pf-input" placeholder="Enter current password" id="curPass">
            <i class="ri-eye-line pf-pass-eye" onclick="togglePass('curPass',this)"></i>
          </div>
        </div>

        <div class="pf-field">
          <label class="pf-label"><i class="ri-lock-unlock-line"></i> New Password</label>
          <div class="pf-pass-wrap">
            <input type="password" name="new_password" class="pf-input" placeholder="Min. 8 characters" id="newPass">
            <i class="ri-eye-line pf-pass-eye" onclick="togglePass('newPass',this)"></i>
          </div>
        </div>

        <div class="pf-footer">
          <a href="<?= IMS_URL ?>/dashboard.php" class="pf-cancel-btn">Cancel</a>
          <button type="submit" class="pf-save-btn"><i class="ri-save-line"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!-- RIGHT: Account Info -->
  <div style="display:flex; flex-direction:column; gap:20px;">

    <!-- Account Details -->
    <div class="pf-card">
      <div class="pf-card-head">
        <div class="pf-card-head-icon" style="background:rgba(37,99,235,.12); color:#2563EB;">
          <i class="ri-information-line"></i>
        </div>
        <div>
          <div class="pf-card-head-title">Account Details</div>
          <div class="pf-card-head-sub">Your account information</div>
        </div>
      </div>
      <div class="pf-card-body">
        <div class="pf-info-grid">
          <div class="pf-info-row">
            <span class="pf-info-label"><i class="ri-fingerprint-line"></i> Account ID</span>
            <span class="pf-info-value" style="font-family:monospace; font-size:12px;">#<?= str_pad($user['id'], 4, '0', STR_PAD_LEFT) ?></span>
          </div>
          <div class="pf-info-row">
            <span class="pf-info-label"><i class="ri-shield-user-line"></i> Role</span>
            <span class="pf-info-badge" style="background:<?= $accentColor ?>18; color:<?= $accentColor ?>;">
              <?= ucfirst($role) ?>
            </span>
          </div>
          <div class="pf-info-row">
            <span class="pf-info-label"><i class="ri-toggle-line"></i> Status</span>
            <?php $active = ($user['status'] ?? 'active') === 'active'; ?>
            <span class="pf-info-badge" style="background:<?= $active ? 'var(--success-light)' : 'var(--danger-light)' ?>; color:<?= $active ? 'var(--success-dark)' : 'var(--danger-dark)' ?>;">
              <span style="width:5px;height:5px;border-radius:50%;background:currentColor;display:inline-block;"></span>
              <?= $active ? 'Active' : 'Inactive' ?>
            </span>
          </div>
          <div class="pf-info-row">
            <span class="pf-info-label"><i class="ri-calendar-line"></i> Joined</span>
            <span class="pf-info-value"><?= $memberSince ?></span>
          </div>
          <div class="pf-info-row">
            <span class="pf-info-label"><i class="ri-time-line"></i> Last Login</span>
            <span class="pf-info-value" style="font-size:12px;"><?= $lastLogin ?></span>
          </div>
          <div class="pf-info-row">
            <span class="pf-info-label"><i class="ri-user-line"></i> Username</span>
            <span class="pf-info-value"><?= e($user['username']) ?></span>
          </div>
        </div>
      </div>
    </div><!-- /Account Details -->

    <!-- Security Tips -->
    <div class="pf-card">
      <div class="pf-card-head">
        <div class="pf-card-head-icon" style="background:rgba(245,158,11,.12); color:#D97706;">
          <i class="ri-shield-keyhole-line"></i>
        </div>
        <div>
          <div class="pf-card-head-title">Security Tips</div>
          <div class="pf-card-head-sub">Keep your account safe</div>
        </div>
      </div>
      <div class="pf-card-body" style="padding:16px 22px;">
        <?php
        $tips = [
          ['ri-lock-password-line','Use a strong, unique password with letters, numbers & symbols.','#7C3AED'],
          ['ri-refresh-line','Change your password every 3–6 months.','#059669'],
          ['ri-alert-line','Never share your password with anyone.','#DC2626'],
          ['ri-mail-open-line','Keep your email up to date for notifications.','#2563EB'],
        ];
        foreach($tips as $t): ?>
        <div style="display:flex; align-items:flex-start; gap:10px; padding:10px 0; border-bottom:1px solid var(--border);">
          <div style="width:28px;height:28px;border-radius:8px;background:<?= $t[2] ?>18;color:<?= $t[2] ?>;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;">
            <i class="<?= $t[0] ?>"></i>
          </div>
          <span style="font-size:12px; color:var(--text-secondary); line-height:1.5;"><?= $t[1] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div><!-- /Security -->

  </div><!-- /right col -->
</div><!-- /pf-layout -->

<script>
// Toggle password visibility
function togglePass(id, icon) {
  const input = document.getElementById(id);
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  icon.className = show ? 'ri-eye-off-line pf-pass-eye' : 'ri-eye-line pf-pass-eye';
}

// Avatar preview & auto-submit
document.getElementById('avatarInput')?.addEventListener('change', function() {
  const file = this.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    const preview = document.getElementById('avatarPreview');
    if (preview.tagName === 'IMG') {
      preview.src = e.target.result;
    } else {
      // replace initials div with img
      const img = document.createElement('img');
      img.src = e.target.result;
      img.className = 'pf-avatar';
      img.id = 'avatarPreview';
      preview.replaceWith(img);
    }
  };
  reader.readAsDataURL(file);
  document.getElementById('avatarForm').submit();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
