<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin']);

$pageTitle  = 'System Settings';
$activePage = 'settings';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $smtpPasswordInput = trim($_POST['smtp_password'] ?? '');
    $smtpEncryption = sanitize($_POST['smtp_encryption'] ?? 'tls');
    if (!in_array($smtpEncryption, ['none', 'tls', 'ssl'], true)) {
        $smtpEncryption = 'tls';
    }
    $smtpPort = (int)($_POST['smtp_port'] ?? 587);
    if ($smtpPort <= 0 || $smtpPort > 65535) {
        $smtpPort = 587;
    }
    $settingsToSave = [
        'institute_name'    => sanitize($_POST['institute_name'] ?? ''),
        'institute_address' => sanitize($_POST['institute_address'] ?? ''),
        'institute_phone'   => sanitize($_POST['institute_phone'] ?? ''),
        'institute_email'   => sanitize($_POST['institute_email'] ?? ''),
        'institute_website' => sanitize($_POST['institute_website'] ?? ''),
        'academic_year'     => sanitize($_POST['academic_year'] ?? ''),
        'currency_symbol'   => sanitize($_POST['currency_symbol'] ?? '$'),
        'timezone'          => sanitize($_POST['timezone'] ?? 'Asia/Kolkata'),
        'date_format'       => sanitize($_POST['date_format'] ?? 'd M Y'),
        'smtp_enabled'      => isset($_POST['smtp_enabled']) ? '1' : '0',
        'smtp_host'         => sanitize($_POST['smtp_host'] ?? ''),
        'smtp_port'         => (string)$smtpPort,
        'smtp_encryption'   => $smtpEncryption,
        'smtp_username'     => sanitize($_POST['smtp_username'] ?? ''),
        'smtp_from_email'   => sanitize($_POST['smtp_from_email'] ?? ''),
        'smtp_from_name'    => sanitize($_POST['smtp_from_name'] ?? ''),
    ];
    if ($smtpPasswordInput !== '') {
        $settingsToSave['smtp_password'] = $smtpPasswordInput;
    }

    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    foreach ($settingsToSave as $key => $value) {
        $stmt->execute([$key, $value]);
    }
    log_activity('update_settings','settings','Updated system settings');
    set_toast('success','Settings saved successfully!');
    header('Location: ' . IMS_URL . '/modules/settings/index.php');
    exit;
}

// Load current settings
$settingsRows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
$settings = [];
foreach ($settingsRows as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">System Settings</h1>
    <p class="page-subtitle">Manage your institution's global configuration and security</p>
  </div>
</div>

<div class="settings-dashboard">
  <!-- Top Quick Info Cards -->
  <div class="settings-stats-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-bottom: 30px;">
    
    <div class="card" style="border:1px solid var(--border); box-shadow: var(--shadow-sm); background: var(--bg-card); border-radius:18px; overflow:hidden;">
      <div class="card-body d-flex align-items-center" style="padding: 24px; gap: 20px;">
        <div class="icon-circle" style="width:50px; height:50px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:24px; background: rgba(30, 58, 138, 0.1); color: var(--primary);">
          <i class="ri-book-open-fill"></i>
        </div>
        <div>
          <h4 style="margin:0; font-size:16px; font-weight:700; color:var(--text-primary);">Academic Status</h4>
          <p style="margin:4px 0 0; font-size:13px; color:var(--text-muted);">Session: <?= e($settings['academic_year'] ?? 'Set Below') ?></p>
        </div>
        <a href="<?= IMS_URL ?>/modules/courses/index.php" class="btn btn-sm btn-outline" style="margin-left:auto; border-radius: 8px; display: flex; align-items: center; gap: 6px;">
          <i class="ri-book-open-line"></i> Courses
        </a>
      </div>
    </div>

    <div class="card" style="border:1px solid var(--border); box-shadow: var(--shadow-sm); background: var(--bg-card); border-radius:18px; overflow:hidden;">
      <div class="card-body d-flex align-items-center" style="padding: 24px; gap: 20px;">
        <div class="icon-circle" style="width:50px; height:50px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:24px; background: rgba(16, 185, 129, 0.1); color: var(--success);">
          <i class="ri-shield-check-fill"></i>
        </div>
        <div>
          <h4 style="margin:0; font-size:16px; font-weight:700; color:var(--text-primary);">Security Scan</h4>
          <p style="margin:4px 0 0; font-size:13px; color:var(--text-muted);">Status: <span style="color:var(--success); font-weight:600;">ACTIVE</span></p>
        </div>
        <div style="margin-left:auto; text-align:right;">
           <span style="font-size:11px; display:block; color:var(--text-muted);">SSL Active</span>
           <span style="font-size:11px; display:block; color:var(--text-muted);">CSRF Safe</span>
        </div>
      </div>
    </div>

  </div>

  <form method="POST" data-validate>
    <?= csrf_field() ?>
    
    <div class="card mb-4" style="border:none; box-shadow: var(--shadow-md);">
      <div class="card-header d-flex justify-between align-items-center" style="background:transparent; border-bottom: 1px solid var(--border);">
        <h3 class="card-title" style="font-size:18px; font-weight:700;"><i class="ri-building-line" style="color:var(--primary); margin-right: 12px;"></i> Institute Information</h3>
        <span class="text-muted" style="font-size:12px;">Global identity settings</span>
      </div>
      <div class="card-body" style="padding: 30px;">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Institute Name <span class="required">*</span></label>
            <input type="text" name="institute_name" class="form-control" required value="<?= e($settings['institute_name']??'') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Current Academic Year</label>
            <input type="text" name="academic_year" class="form-control" placeholder="e.g. 2025-2026" value="<?= e($settings['academic_year']??'') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Contact Phone</label>
            <input type="tel" name="institute_phone" class="form-control" value="<?= e($settings['institute_phone']??'') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Contact Email</label>
            <input type="email" name="institute_email" class="form-control" value="<?= e($settings['institute_email']??'') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Official Website</label>
            <input type="url" name="institute_website" class="form-control" placeholder="https://" value="<?= e($settings['institute_website']??'') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Currency Symbol</label>
            <input type="text" name="currency_symbol" class="form-control" style="max-width:120px;" value="<?= e($settings['currency_symbol']??'$') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">System Timezone</label>
            <select name="timezone" class="form-control">
              <?php foreach (['Asia/Kolkata','UTC','America/New_York','America/Chicago','America/Los_Angeles','Europe/London','Europe/Paris'] as $tz): ?>
              <option value="<?= $tz ?>" <?= ($settings['timezone']??'Asia/Kolkata')===$tz?'selected':'' ?>><?= $tz ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Global Date Format</label>
            <select name="date_format" class="form-control">
              <?php foreach (['d M Y'=>'01 Jan 2025','Y-m-d'=>'2025-01-01','m/d/Y'=>'01/01/2025','d/m/Y'=>'01/01/2025'] as $fmt=>$ex): ?>
              <option value="<?= $fmt ?>" <?= ($settings['date_format']??'d M Y')===$fmt?'selected':'' ?>><?= $ex ?> (<?= $fmt ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group full-width">
            <label class="form-label">Physical Mailing Address</label>
            <textarea name="institute_address" class="form-control" rows="3"><?= e($settings['institute_address']??'') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-4" style="border:none; box-shadow: var(--shadow-md);">
      <div class="card-header d-flex justify-between align-items-center" style="background:transparent; border-bottom: 1px solid var(--border);">
        <h3 class="card-title" style="font-size:18px; font-weight:700;"><i class="ri-mail-send-line" style="color:var(--primary); margin-right: 12px;"></i> SMTP Mail Configuration</h3>
        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; background: var(--bg-hover); padding: 6px 14px; border-radius: 20px;">
          <input type="checkbox" name="smtp_enabled" value="1" <?= ($settings['smtp_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
          <span style="font-size:13px; font-weight:600; color:var(--text-primary);">Enable Automailer</span>
        </label>
      </div>
      <div class="card-body" style="padding: 30px;">
        <div class="alert" style="background: rgba(30, 58, 138, 0.05); color: var(--primary); border:none; border-left: 4px solid var(--primary); margin-bottom: 24px; display: flex; align-items: center;">
          <i class="ri-information-line" style="margin-right: 12px; font-size: 18px;"></i> Configure SMTP settings to enable password recovery and automated student alerts.
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">SMTP Host</label>
            <input type="text" name="smtp_host" class="form-control" placeholder="e.g. smtp.gmail.com" value="<?= e($settings['smtp_host'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">SMTP Port</label>
            <input type="number" name="smtp_port" class="form-control" placeholder="587" value="<?= e($settings['smtp_port'] ?? '587') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Encryption Type</label>
            <select name="smtp_encryption" class="form-control">
              <?php foreach (['tls'=>'STARTTLS (Recommended)','ssl'=>'SSL/TLS','none'=>'None'] as $enc=>$label): ?>
              <option value="<?= $enc ?>" <?= ($settings['smtp_encryption'] ?? 'tls') === $enc ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">SMTP User</label>
            <input type="text" name="smtp_username" class="form-control" placeholder="account@example.com" value="<?= e($settings['smtp_username'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">SMTP Password</label>
            <div style="position:relative;">
              <input type="password" name="smtp_password" class="form-control" placeholder="••••••••">
              <span style="font-size:11px; color:var(--text-muted); padding-top:4px; display:block;">Leave blank to keep existing</span>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Sender Name</label>
            <input type="text" name="smtp_from_name" class="form-control" placeholder="e.g. ExcelIMS System" value="<?= e($settings['smtp_from_name'] ?? '') ?>">
          </div>
          <div class="form-group">
              <label class="form-label">Sender Address</label>
              <input type="email" name="smtp_from_email" class="form-control" placeholder="noreply@example.com" value="<?= e($settings['smtp_from_email'] ?? '') ?>">
          </div>
        </div>
      </div>
    </div>

    <div class="settings-actions d-flex justify-end gap-3" style="padding: 20px 0 40px;">
      <button type="reset" class="btn btn-outline" style="border-radius: 10px; padding: 12px 24px; display: flex; align-items: center; gap: 8px;">
        <i class="ri-close-circle-line"></i> Discard
      </button>
      <button type="submit" class="btn btn-primary" style="border-radius: 10px; padding: 12px 32px; font-weight: 600; box-shadow: var(--shadow-md); display: flex; align-items: center; gap: 8px;">
        <i class="ri-save-line"></i> Update Configuration
      </button>
    </div>

  </form>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
