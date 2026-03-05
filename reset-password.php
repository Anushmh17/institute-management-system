<?php
/**
 * Reset Password Page
 * Verifies OTP from email and allows setting a new password
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . IMS_URL . '/dashboard.php');
    exit;
}

$error   = '';
$success = '';

$validRoles = ['admin', 'teacher', 'student'];
$roleInput = sanitize($_GET['role'] ?? ($_POST['login_role'] ?? 'student'));
$role = in_array($roleInput, $validRoles, true) ? $roleInput : 'student';

$verifiedReset = $_SESSION['reset_verify'] ?? null;
$otpVerified = is_array($verifiedReset)
    && isset($verifiedReset['id'], $verifiedReset['email'], $verifiedReset['role'])
    && $verifiedReset['role'] === $role;

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'verify_otp';

    if ($action === 'verify_otp') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $otp = preg_replace('/\D/', '', $_POST['otp'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($otp) !== 6) {
            $error = 'Please enter a valid 6-digit OTP.';
        } else {
            $stmt = $pdo->prepare(
                "SELECT id, email, role FROM password_resets
                 WHERE email = ? AND role = ? AND token = ? AND used = 0 AND expires_at > NOW()
                 LIMIT 1"
            );
            $stmt->execute([$email, $role, $otp]);
            $record = $stmt->fetch();

            if (!$record) {
                $error = 'Invalid or expired OTP. Please request a new OTP.';
            } else {
                $_SESSION['reset_verify'] = [
                    'id'    => (int)$record['id'],
                    'email' => (string)$record['email'],
                    'role'  => (string)$record['role'],
                ];
                $otpVerified = true;
            }
        }
    }

    if ($action === 'set_password') {
        if (!$otpVerified) {
            $error = 'Please verify OTP first.';
        } else {
            $newPass = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (strlen($newPass) < 8) {
                $error = 'Password must be at least 8 characters.';
            } elseif (!preg_match('/[A-Z]/', $newPass)) {
                $error = 'Password must contain at least one uppercase letter.';
            } elseif (!preg_match('/[0-9]/', $newPass)) {
                $error = 'Password must contain at least one number.';
            } elseif ($newPass !== $confirm) {
                $error = 'Passwords do not match.';
            } else {
                $pdo->beginTransaction();
                try {
                    $hashed = password_hash($newPass, PASSWORD_BCRYPT);
                    $resetId = (int)$verifiedReset['id'];
                    $email = (string)$verifiedReset['email'];

                    $pdo->prepare(
                        "UPDATE users SET password = ?, updated_at = NOW()
                         WHERE email = ? AND role_id = (SELECT id FROM roles WHERE name = ?)
                         LIMIT 1"
                    )->execute([$hashed, $email, $role]);

                    $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ? LIMIT 1")->execute([$resetId]);

                    $pdo->commit();
                    unset($_SESSION['reset_verify']);
                    $otpVerified = false;

                    log_activity('password_reset_complete', 'auth', "Password reset for {$email} ({$role})");
                    $success = 'Your password has been reset successfully. You can now login.';
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    error_log('Password reset failed: ' . $e->getMessage());
                    $error = 'Unable to reset password right now. Please try again.';
                }
            }
        }
    }
}

$instituteName = get_setting('institute_name', 'ExcelIMS');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password | <?= e($instituteName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= IMS_URL ?>/assets/css/login-premium.css?v=<?= time() ?>">
  <style>
    .alert-success{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.25);color:#6EE7B7;padding:12px;border-radius:10px;font-size:13px;margin-bottom:24px;display:flex;align-items:center;gap:10px;}
    .role-badge-login{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;margin-bottom:6px;}
    .role-badge-login.admin{background:rgba(239,68,68,.15);color:#FCA5A5;}
    .role-badge-login.teacher{background:rgba(245,158,11,.15);color:#FCD34D;}
    .role-badge-login.student{background:rgba(59,130,246,.15);color:#93C5FD;}
    .back-link{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:rgba(203,213,225,.6);text-decoration:none;margin-top:20px;transition:color .2s;}
    .back-link:hover{color:#fff;}
    .hint-text{font-size:12px;color:rgba(203,213,225,.5);text-align:center;margin-top:8px;}
  </style>
</head>
<body>
  <div class="shape shape-1"></div>
  <div class="shape shape-2"></div>

  <div class="login-container">
    <div class="glass-card">
      <div class="login-header">
        <div class="role-badge-login <?= $role ?>">
          <i class="ri-<?= $role==='admin'?'shield-star':($role==='teacher'?'user-star':'graduation-cap') ?>-line"></i>
          <?= ucfirst($role) ?> Portal
        </div>
        <h1>Reset Password</h1>
        <p><?= $otpVerified ? 'Set your new password' : 'Enter the OTP sent to your email' ?></p>
      </div>

      <?php if ($error): ?>
        <div class="alert">
          <i class="ri-error-warning-line"></i>
          <span><?= e($error) ?></span>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert-success">
          <i class="ri-checkbox-circle-line"></i>
          <span><?= e($success) ?></span>
        </div>
        <div style="text-align:center;">
          <a href="<?= IMS_URL ?>/index.php?role=<?= e($role) ?>" class="login-btn" style="display:inline-flex;text-decoration:none;margin-top:8px;">
            <i class="ri-login-box-line"></i> Go to Login
          </a>
        </div>
      <?php elseif (!$otpVerified): ?>
        <form method="POST" action="" class="login-form" id="otpForm">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="verify_otp">
          <input type="hidden" name="login_role" value="<?= e($role) ?>">

          <div class="form-group">
            <input type="email" name="email" class="form-input" placeholder=" "
                   value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email">
            <label class="form-label">Registered Email Address</label>
          </div>

          <div class="form-group">
            <input type="text" name="otp" class="form-input" placeholder=" " required
                   inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                   value="<?= e($_POST['otp'] ?? '') ?>">
            <label class="form-label">6-Digit OTP</label>
          </div>

          <button type="submit" class="login-btn"><i class="ri-shield-keyhole-line"></i> Verify OTP</button>
        </form>
        <p class="hint-text">OTP is valid for 10 minutes.</p>
      <?php else: ?>
        <form method="POST" action="" class="login-form" id="resetForm">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="set_password">
          <input type="hidden" name="login_role" value="<?= e($role) ?>">

          <div class="form-group">
            <div class="password-wrapper">
              <input type="password" id="newPassword" name="new_password" class="form-input"
                     placeholder=" " required autocomplete="new-password" minlength="8">
              <label for="newPassword" class="form-label">New Password</label>
              <i class="ri-eye-line toggle-password" id="toggleNew"></i>
            </div>
          </div>

          <div class="form-group">
            <div class="password-wrapper">
              <input type="password" id="confirmPassword" name="confirm_password" class="form-input"
                     placeholder=" " required autocomplete="new-password">
              <label for="confirmPassword" class="form-label">Confirm Password</label>
              <i class="ri-eye-line toggle-password" id="toggleConfirm"></i>
            </div>
          </div>

          <button type="submit" class="login-btn"><i class="ri-check-line"></i> Set New Password</button>
        </form>
      <?php endif; ?>

      <div style="text-align:center;">
        <a href="<?= IMS_URL ?>/forgot-password.php?role=<?= e($role) ?>" class="back-link">
          <i class="ri-arrow-left-line"></i> Back to Forgot Password
        </a>
      </div>

      <p class="copyright">
        © <?= date('Y') ?> <?= e($instituteName) ?> - Enterprise v1.0.0
      </p>
    </div>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    [['toggleNew', 'newPassword'], ['toggleConfirm', 'confirmPassword']].forEach(([btnId, fieldId]) => {
      const btn = document.getElementById(btnId);
      const field = document.getElementById(fieldId);
      if (!btn || !field) return;
      btn.addEventListener('click', () => {
        field.type = field.type === 'password' ? 'text' : 'password';
        btn.classList.toggle('ri-eye-line');
        btn.classList.toggle('ri-eye-off-line');
      });
    });
  });
  </script>
</body>
</html>
