<?php
/**
 * Forgot Password Page
 * Generates a one-time OTP and emails it to the user
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

if (is_logged_in()) {
    header('Location: ' . IMS_URL . '/dashboard.php');
    exit;
}

$validRoles = ['admin', 'teacher', 'student'];
$activeRole = in_array($_GET['role'] ?? '', $validRoles) ? $_GET['role'] : 'student';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email      = strtolower(trim($_POST['email'] ?? ''));
    $loginRole  = $_POST['login_role'] ?? 'student';
    if (!in_array($loginRole, $validRoles)) $loginRole = 'student';
    $activeRole = $loginRole;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $pdo  = db();
        // Verify user exists with that email AND that role
        $stmt = $pdo->prepare(
            "SELECT u.id, u.full_name, u.email FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = ? AND r.name = ? AND u.status = 'active'
             LIMIT 1"
        );
        $stmt->execute([$email, $loginRole]);
        $user = $stmt->fetch();

        // Always show success to prevent email enumeration
        if ($user) {
            // Delete any previous unused OTPs for this email+role
            $pdo->prepare("DELETE FROM password_resets WHERE email = ? AND role = ?")->execute([$email, $loginRole]);

            // Generate 6-digit OTP valid for 10 minutes (DB clock)
            $token   = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $pdo->prepare(
                "INSERT INTO password_resets (email, token, role, expires_at)
                 VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))"
            )->execute([$email, $token, $loginRole]);

            // Compose email
            $instituteName = get_setting('institute_name', 'ExcelIMS');
            $subject = "Your Password Reset OTP - {$instituteName}";
            $body    = "Hello {$user['full_name']},\r\n\r\n"
                     . "You (or someone else) requested a password reset for your " . ucfirst($loginRole) . " account.\r\n\r\n"
                     . "Your OTP is: {$token}\r\n"
                     . "This OTP is valid for 10 minutes.\r\n\r\n"
                     . "If you did not request this, please ignore this email.\r\n\r\n"
                     . "Regards,\r\n{$instituteName} Team";

            $mailSent = mailer_send($user['email'], $subject, $body, [
                'from_email' => get_setting('institute_email', ''),
                'from_name'  => $instituteName . ' Team',
            ]);
            if (!$mailSent) {
                error_log("Password reset mail failed for {$email} ({$loginRole})");
            }

            log_activity('password_reset_request', 'auth', "Reset requested for {$email} ({$loginRole})");
        }

        $success = 'OTP has been sent to your mail. Please check your inbox.';
    }
}

$instituteName = get_setting('institute_name', 'ExcelIMS');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password | <?= e($instituteName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= IMS_URL ?>/assets/css/login-premium.css?v=<?= time() ?>">
  <style>
    .role-tabs{display:flex;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:4px;margin-bottom:28px;gap:4px;}
    .role-tab{flex:1;padding:9px 4px;border:none;border-radius:7px;cursor:pointer;font-size:12px;font-weight:600;letter-spacing:.3px;color:rgba(203,213,225,.6);background:transparent;display:flex;align-items:center;justify-content:center;gap:6px;transition:all .25s ease;}
    .role-tab i{font-size:15px;}
    .role-tab:hover{color:#fff;background:rgba(255,255,255,.07);}
    .role-tab[data-role="admin"].active{background:linear-gradient(135deg,#EF4444,#DC2626);box-shadow:0 4px 14px rgba(239,68,68,.4);color:#fff;}
    .role-tab[data-role="teacher"].active{background:linear-gradient(135deg,#F59E0B,#D97706);box-shadow:0 4px 14px rgba(245,158,11,.4);color:#fff;}
    .role-tab[data-role="student"].active{background:linear-gradient(135deg,#3B82F6,#2563EB);box-shadow:0 4px 14px rgba(59,130,246,.4);color:#fff;}
    .back-link{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:rgba(203,213,225,.6);text-decoration:none;margin-top:20px;transition:color .2s;}
    .back-link:hover{color:#fff;}
    .alert-success{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.25);color:#6EE7B7;padding:12px;border-radius:10px;font-size:13px;margin-bottom:24px;display:flex;align-items:center;gap:10px;}
    .role-badge-login{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;margin-bottom:6px;}
    .role-badge-login.admin{background:rgba(239,68,68,.15);color:#FCA5A5;}
    .role-badge-login.teacher{background:rgba(245,158,11,.15);color:#FCD34D;}
    .role-badge-login.student{background:rgba(59,130,246,.15);color:#93C5FD;}
    .hint-text{font-size:12px;color:rgba(203,213,225,.5);text-align:center;margin-top:8px;}
  </style>
</head>
<body>
  <div class="shape shape-1"></div>
  <div class="shape shape-2"></div>

  <div class="login-container">
    <div class="glass-card">

      <div class="login-header">
        <div id="roleBadge" class="role-badge-login <?= $activeRole ?>">
          <i id="roleBadgeIcon" class="ri-<?= $activeRole==='admin'?'shield-star':($activeRole==='teacher'?'user-star':'graduation-cap') ?>-line"></i>
          <span id="roleBadgeText"><?= ucfirst($activeRole) ?> Portal</span>
        </div>
        <h1>Forgot Password?</h1>
        <p>Enter your registered email to receive an OTP</p>
      </div>

      <!-- Role Tabs -->
      <div class="role-tabs">
        <button type="button" class="role-tab <?= $activeRole==='admin'?'active':'' ?>" data-role="admin">
          <i class="ri-shield-star-line"></i> Admin
        </button>
        <button type="button" class="role-tab <?= $activeRole==='teacher'?'active':'' ?>" data-role="teacher">
          <i class="ri-user-star-line"></i> Teacher
        </button>
        <button type="button" class="role-tab <?= $activeRole==='student'?'active':'' ?>" data-role="student">
          <i class="ri-graduation-cap-line"></i> Student
        </button>
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
        <div style="text-align:center;margin-bottom:18px;">
          <a href="<?= IMS_URL ?>/reset-password.php?role=<?= e($activeRole) ?>" class="login-btn" style="display:inline-flex;text-decoration:none;">
            <i class="ri-shield-keyhole-line"></i> Enter OTP
          </a>
        </div>
      <?php endif; ?>

      <?php if (!$success): ?>
      <form method="POST" action="" class="login-form" id="forgotForm">
        <?= csrf_field() ?>
        <input type="hidden" name="login_role" id="loginRoleInput" value="<?= e($activeRole) ?>">

        <div class="form-group">
          <input type="email" id="email" name="email" class="form-input"
                 placeholder=" " value="<?= e($_POST['email'] ?? '') ?>" required
                 autocomplete="email">
          <label for="email" class="form-label">Registered Email Address</label>
        </div>

        <button type="submit" class="login-btn" id="submitBtn">
          <span class="btn-text">Send OTP</span>
          <div class="spinner"></div>
          <i class="ri-mail-send-line btn-text"></i>
        </button>
      </form>
      <p class="hint-text">OTP is valid for <strong style="color:rgba(203,213,225,.75)">10 minutes</strong></p>
      <?php endif; ?>

      <div style="text-align:center;">
        <a href="<?= IMS_URL ?>/index.php?role=<?= e($activeRole) ?>" class="back-link">
          <i class="ri-arrow-left-line"></i> Back to Login
        </a>
      </div>

      <p class="copyright">
        © <?= date('Y') ?> <?= e($instituteName) ?> - Enterprise v1.0.0
      </p>
    </div>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const tabs      = document.querySelectorAll('.role-tab');
    const roleInput = document.getElementById('loginRoleInput');
    const badge     = document.getElementById('roleBadge');
    const badgeIcon = document.getElementById('roleBadgeIcon');
    const badgeText = document.getElementById('roleBadgeText');

    const roleConfig = {
      admin:   { icon: 'ri-shield-star-line',   label: 'Admin Portal'   },
      teacher: { icon: 'ri-user-star-line',      label: 'Teacher Portal' },
      student: { icon: 'ri-graduation-cap-line', label: 'Student Portal' },
    };

    function setRole(role) {
      tabs.forEach(t => t.classList.toggle('active', t.dataset.role === role));
      roleInput.value = role;
      badge.className = 'role-badge-login ' + role;
      badgeIcon.className = roleConfig[role].icon;
      badgeText.textContent = roleConfig[role].label;
      // Update back link
      const backLink = document.querySelector('.back-link');
      if (backLink) {
        const url = new URL(backLink.href, location.origin);
        url.searchParams.set('role', role);
        backLink.href = url.pathname + url.search;
      }
    }

    tabs.forEach(tab => tab.addEventListener('click', () => setRole(tab.dataset.role)));

    const form = document.getElementById('forgotForm');
    if (form) {
      form.addEventListener('submit', () => {
        const btn = document.getElementById('submitBtn');
        btn.classList.add('loading');
        btn.style.opacity = '.8';
        btn.style.pointerEvents = 'none';
      });
    }
  });
  </script>
</body>
</html>
