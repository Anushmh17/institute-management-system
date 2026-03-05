<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin', 'teacher', 'student']);

$pageTitle  = 'My Profile';
$activePage = 'settings';
$pdo = db();

$errors = [];
$user = null;
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) { header('Location: ' . IMS_URL . '/dashboard.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fullName    = sanitize($_POST['full_name'] ?? '');
    $email       = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone       = sanitize($_POST['phone'] ?? '');
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';

    if (empty($fullName)) $errors[] = 'Name required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';

    if (!empty($newPass)) {
        if (!password_verify($currentPass, $user['password'])) $errors[] = 'Current password is incorrect.';
        if (strlen($newPass) < 8) $errors[] = 'New password must be at least 8 characters.';
    }

    if (empty($errors)) {
        $pdo->prepare("UPDATE users SET full_name=?,email=?,phone=? WHERE id=?")
            ->execute([$fullName,$email,$phone,$_SESSION['user_id']]);
        if (!empty($newPass) && empty($errors)) {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                ->execute([password_hash($newPass,PASSWORD_BCRYPT,['cost'=>12]),$_SESSION['user_id']]);
        }
        $_SESSION['full_name'] = $fullName;
        $_SESSION['email']     = $email;
        set_toast('success','Profile updated!');
        header('Location: ' . IMS_URL . '/modules/settings/profile.php');
        exit;
    }
}

include __DIR__ . '/../../includes/header.php';
?>
<div class="page-header"><div class="page-header-left"><h1 class="page-title">My Profile</h1><p class="page-subtitle">Update your personal information</p></div></div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger"><i class="ri-error-warning-fill"></i>
  <div><ul style="padding-left:16px;"><?php foreach($errors as $e2): ?><li><?= e($e2)?></li><?php endforeach;?></ul></div>
</div>
<?php endif; ?>

<div class="card" style="max-width:580px;">
  <div class="card-header"><h3 class="card-title"><i class="ri-user-line"></i> Account Details</h3></div>
  <div class="card-body">
    <form method="POST" data-validate>
      <?= csrf_field() ?>
      <div class="form-group"><label class="form-label">Full Name <span class="required">*</span></label>
        <input type="text" name="full_name" class="form-control" required value="<?= e($user['full_name']) ?>"></div>
      <div class="form-group"><label class="form-label">Email <span class="required">*</span></label>
        <input type="email" name="email" class="form-control" required value="<?= e($user['email']) ?>"></div>
      <div class="form-group"><label class="form-label">Phone</label>
        <input type="tel" name="phone" class="form-control" value="<?= e($user['phone']??'') ?>"></div>
      <hr style="margin:20px 0; border-color:var(--border);">
      <p style="font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:16px;">Change Password (optional)</p>
      <div class="form-group"><label class="form-label">Current Password</label>
        <div class="password-toggle"><input type="password" name="current_password" class="form-control"><i class="ri-eye-line toggle-eye"></i></div></div>
      <div class="form-group"><label class="form-label">New Password</label>
        <div class="password-toggle"><input type="password" name="new_password" class="form-control" placeholder="Min. 8 chars"><i class="ri-eye-line toggle-eye"></i></div></div>
      <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:20px;padding-top:20px;border-top:1px solid var(--border);">
        <a href="<?= IMS_URL ?>/dashboard.php" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
