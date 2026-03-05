<?php
/**
 * Login Page – Role-Based (Admin / Teacher / Student)
 * This is now the entry point (index.php)
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . IMS_URL . '/dashboard.php');
    exit;
}

$error   = '';
$success = '';

// Valid roles for login tab
$validRoles = ['admin', 'teacher', 'student'];
$activeRole = in_array($_GET['role'] ?? '', $validRoles) ? $_GET['role'] : 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username   = sanitize($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';
    $loginRole  = $_POST['login_role'] ?? 'admin';

    if (!in_array($loginRole, $validRoles)) $loginRole = 'admin';
    $activeRole = $loginRole;

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $result = attempt_login_role($username, $password, $loginRole);
        if ($result['success']) {
            set_toast('success', 'Welcome back, ' . e($_SESSION['full_name']) . '!');
            header('Location: ' . IMS_URL . '/dashboard.php');
            exit;
        } else {
            $error = $result['message'];
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
  <title>Login | <?= e($instituteName) ?> - IMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= IMS_URL ?>/assets/css/login-premium.css?v=<?= time() ?>">
  <style>
    /* ── Role Tabs ─────────────────────────────────────── */
    .role-tabs {
      display: flex;
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.1);
      border-radius: 10px;
      padding: 4px;
      margin-bottom: 28px;
      gap: 4px;
    }
    .role-tab {
      flex: 1;
      padding: 9px 4px;
      border: none;
      border-radius: 7px;
      cursor: pointer;
      font-size: 12px;
      font-weight: 600;
      letter-spacing: .3px;
      color: rgba(203,213,225,.6);
      background: transparent;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      transition: all .25s ease;
    }
    .role-tab i { font-size: 15px; }
    .role-tab:hover { color: #fff; background: rgba(255,255,255,.07); }
    .role-tab.active { color: #fff; }
    .role-tab[data-role="admin"].active   { background: linear-gradient(135deg,#EF4444,#DC2626); box-shadow: 0 4px 14px rgba(239,68,68,.4); }
    .role-tab[data-role="teacher"].active { background: linear-gradient(135deg,#F59E0B,#D97706); box-shadow: 0 4px 14px rgba(245,158,11,.4); }
    .role-tab[data-role="student"].active { background: linear-gradient(135deg,#3B82F6,#2563EB); box-shadow: 0 4px 14px rgba(59,130,246,.4); }

    /* ── Forgot Link ────────────────────────────────────── */
    .forgot-row {
      display: flex;
      justify-content: flex-end;
      margin-top: -14px;
      margin-bottom: 20px;
    }
    .forgot-link {
      font-size: 12px;
      color: rgba(203,213,225,.65);
      text-decoration: none;
      transition: color .2s;
    }
    .forgot-link:hover { color: var(--accent-glow); }

    /* ── Success Alert ──────────────────────────────────── */
    .alert-success {
      background: rgba(16,185,129,.1);
      border: 1px solid rgba(16,185,129,.25);
      color: #6EE7B7;
      padding: 12px;
      border-radius: 10px;
      font-size: 13px;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    /* ── Role badge in header ───────────────────────────── */
    .role-badge-login {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 3px 10px;
      border-radius: 99px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .5px;
      text-transform: uppercase;
      margin-bottom: 6px;
    }
    .role-badge-login.admin   { background:rgba(239,68,68,.15);   color:#FCA5A5; }
    .role-badge-login.teacher { background:rgba(245,158,11,.15);  color:#FCD34D; }
    .role-badge-login.student { background:rgba(59,130,246,.15);  color:#93C5FD; }
  </style>
</head>
<body>
  <div class="shape shape-1"></div>
  <div class="shape shape-2"></div>

  <div class="login-container">
    <div class="glass-card">

      <!-- Header -->
      <div class="login-header">
        <div id="roleBadge" class="role-badge-login <?= $activeRole ?>">
          <i id="roleBadgeIcon" class="ri-<?= $activeRole === 'admin' ? 'shield-star' : ($activeRole === 'teacher' ? 'user-star' : 'graduation-cap') ?>-line"></i>
          <span id="roleBadgeText"><?= ucfirst($activeRole) ?> Portal</span>
        </div>
        <h1>Welcome back</h1>
        <p>Sign in to access your dashboard</p>
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
      <?php endif; ?>

      <form method="POST" action="" class="login-form" id="loginForm">
        <?= csrf_field() ?>
        <input type="hidden" name="login_role" id="loginRoleInput" value="<?= e($activeRole) ?>">

        <div class="form-group">
          <input type="text" id="username" name="username" class="form-input"
                 placeholder=" " value="<?= e($_POST['username'] ?? '') ?>" required
                 autocomplete="username">
          <label for="username" class="form-label">Username or Email</label>
        </div>

        <div class="form-group">
          <div class="password-wrapper">
            <input type="password" id="password" name="password" class="form-input"
                   placeholder=" " required autocomplete="current-password">
            <label for="password" class="form-label">Password</label>
            <i class="ri-eye-line toggle-password" id="togglePassword"></i>
          </div>
        </div>

        <div class="forgot-row">
          <a href="<?= IMS_URL ?>/forgot-password.php?role=<?= e($activeRole) ?>"
             id="forgotLink" class="forgot-link">Forgot password?</a>
        </div>

        <button type="submit" class="login-btn" id="loginBtn">
          <span class="btn-text">Sign In</span>
          <div class="spinner"></div>
          <i class="ri-arrow-right-line btn-text"></i>
        </button>
      </form>

      <p class="copyright">
        © <?= date('Y') ?> <?= e($instituteName) ?> - Enterprise v1.0.0
      </p>
    </div>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    // ── Password Toggle ──────────────────────────────────
    const pwField  = document.getElementById('password');
    const toggleBtn = document.getElementById('togglePassword');
    toggleBtn.addEventListener('click', () => {
      const t = pwField.type === 'password' ? 'text' : 'password';
      pwField.type = t;
      toggleBtn.classList.toggle('ri-eye-line');
      toggleBtn.classList.toggle('ri-eye-off-line');
    });

    // ── Role Tab Switching ───────────────────────────────
    const tabs       = document.querySelectorAll('.role-tab');
    const roleInput  = document.getElementById('loginRoleInput');
    const badge      = document.getElementById('roleBadge');
    const badgeIcon  = document.getElementById('roleBadgeIcon');
    const badgeText  = document.getElementById('roleBadgeText');
    const forgotLink = document.getElementById('forgotLink');

    const roleConfig = {
      admin:   { icon: 'ri-shield-star-line',     label: 'Admin Portal'   },
      teacher: { icon: 'ri-user-star-line',        label: 'Teacher Portal' },
      student: { icon: 'ri-graduation-cap-line',   label: 'Student Portal' },
    };

    function setRole(role) {
      tabs.forEach(t => t.classList.toggle('active', t.dataset.role === role));
      roleInput.value = role;
      badge.className = 'role-badge-login ' + role;
      badgeIcon.className = roleConfig[role].icon;
      badgeText.textContent = roleConfig[role].label;
      // Update forgot link
      const url = new URL(forgotLink.href);
      url.searchParams.set('role', role);
      forgotLink.href = url.toString();
    }

    tabs.forEach(tab => {
      tab.addEventListener('click', () => setRole(tab.dataset.role));
    });

    // ── Loading state ────────────────────────────────────
    document.getElementById('loginForm').addEventListener('submit', () => {
      const btn = document.getElementById('loginBtn');
      btn.classList.add('loading');
      btn.style.opacity = '.8';
      btn.style.pointerEvents = 'none';
    });

    // Autofill fix
    document.querySelectorAll('.form-input').forEach(inp => {
      inp.addEventListener('change', () => {
        if (inp.value) inp.classList.add('has-content');
      });
    });
  });
  </script>
</body>
</html>
