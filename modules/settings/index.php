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
        'timezone'          => sanitize($_POST['timezone'] ?? ''),
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
    <p class="page-subtitle">Configure institute information and preferences</p>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:24px;align-items:start;">

  <!-- Left Nav -->
  <div class="card">
    <div class="card-body" style="padding:8px;">
      <?php foreach ([
        ['general','General','ri-settings-3-line'],
        ['academic','Academic','ri-book-open-line'],
        ['security','Security','ri-shield-check-line'],
      ] as [$tab,$label,$icon]): ?>
      <button type="button" class="tab-btn <?= $tab==='general'?'active':'' ?> d-flex align-center gap-2"
              data-tab="settings-<?= $tab ?>"
              style="width:100%;text-align:left;padding:12px 16px;border-radius:8px;margin-bottom:4px;">
        <i class="<?= $icon ?>"></i> <?= $label ?>
      </button>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Right Content -->
  <form method="POST" data-validate>
    <?= csrf_field() ?>

    <div class="tab-pane active" id="settings-general">
      <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="ri-building-line"></i> Institute Information</h3></div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Institute Name <span class="required">*</span></label>
              <input type="text" name="institute_name" class="form-control" required value="<?= e($settings['institute_name']??'') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Academic Year</label>
              <input type="text" name="academic_year" class="form-control" placeholder="e.g. 2025-2026" value="<?= e($settings['academic_year']??'') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Phone</label>
              <input type="tel" name="institute_phone" class="form-control" value="<?= e($settings['institute_phone']??'') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Email</label>
              <input type="email" name="institute_email" class="form-control" value="<?= e($settings['institute_email']??'') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Website</label>
              <input type="url" name="institute_website" class="form-control" placeholder="https://" value="<?= e($settings['institute_website']??'') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Currency Symbol</label>
              <input type="text" name="currency_symbol" class="form-control" style="max-width:80px;" value="<?= e($settings['currency_symbol']??'$') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Timezone</label>
              <select name="timezone" class="form-control">
                <?php foreach (['Asia/Kolkata','UTC','America/New_York','America/Chicago','America/Los_Angeles','Europe/London','Europe/Paris'] as $tz): ?>
                <option value="<?= $tz ?>" <?= ($settings['timezone']??'')===$tz?'selected':'' ?>><?= $tz ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Date Format</label>
              <select name="date_format" class="form-control">
                <?php foreach (['d M Y'=>'01 Jan 2025','Y-m-d'=>'2025-01-01','m/d/Y'=>'01/01/2025','d/m/Y'=>'01/01/2025'] as $fmt=>$ex): ?>
                <option value="<?= $fmt ?>" <?= ($settings['date_format']??'')===$fmt?'selected':'' ?>><?= $ex ?> (<?= $fmt ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group full-width">
              <label class="form-label">Address</label>
              <textarea name="institute_address" class="form-control" rows="2"><?= e($settings['institute_address']??'') ?></textarea>
            </div>
          </div>
        </div>
        <div class="card-footer" style="display:flex;justify-content:flex-end;">
          <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> Save Settings</button>
        </div>
      </div>
    </div>

    <div class="tab-pane" id="settings-academic">
      <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="ri-book-open-line"></i> Academic Settings</h3></div>
        <div class="card-body">
          <div class="alert alert-info"><i class="ri-information-line"></i> Academic configuration is managed through Courses and Departments modules.</div>
          <a href="<?= IMS_URL ?>/modules/courses/index.php" class="btn btn-outline"><i class="ri-book-open-line"></i> Manage Courses</a>
        </div>
      </div>
    </div>

    <div class="tab-pane" id="settings-security">
      <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="ri-shield-check-line"></i> Security & Access</h3></div>
        <div class="card-body">
          <div class="alert alert-warning"><i class="ri-alert-line"></i> Security settings affect all user sessions. Changes take effect on next login.</div>
          <div class="detail-grid">
            <div class="detail-item"><label>Password Hashing</label><span class="badge badge-success">bcrypt (cost 12)</span></div>
            <div class="detail-item"><label>CSRF Protection</label><span class="badge badge-success">Enabled</span></div>
            <div class="detail-item"><label>XSS Protection</label><span class="badge badge-success">htmlspecialchars</span></div>
            <div class="detail-item"><label>SQL Injection</label><span class="badge badge-success">PDO Prepared Statements</span></div>
            <div class="detail-item"><label>Session Security</label><span class="badge badge-success">HttpOnly, SameSite=Strict</span></div>
          </div>

          <hr style="margin:20px 0;border:none;border-top:1px solid var(--border-color);">
          <h4 style="margin:0 0 14px;font-size:15px;"><i class="ri-mail-send-line"></i> SMTP Mail Configuration</h4>
          <div class="form-grid">
            <div class="form-group full-width">
              <label class="form-label" style="display:flex;align-items:center;gap:10px;">
                <input type="checkbox" name="smtp_enabled" value="1" <?= ($settings['smtp_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                Enable SMTP for password reset emails
              </label>
            </div>

            <div class="form-group">
              <label class="form-label">SMTP Host</label>
              <input type="text" name="smtp_host" class="form-control" placeholder="smtp.gmail.com" value="<?= e($settings['smtp_host'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">SMTP Port</label>
              <input type="number" name="smtp_port" class="form-control" placeholder="587" value="<?= e($settings['smtp_port'] ?? '587') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Encryption</label>
              <select name="smtp_encryption" class="form-control">
                <?php foreach (['tls'=>'STARTTLS (recommended)','ssl'=>'SSL/TLS','none'=>'None'] as $enc=>$label): ?>
                <option value="<?= $enc ?>" <?= ($settings['smtp_encryption'] ?? 'tls') === $enc ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">SMTP Username</label>
              <input type="text" name="smtp_username" class="form-control" placeholder="your-email@example.com" value="<?= e($settings['smtp_username'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">SMTP Password</label>
              <input type="password" name="smtp_password" class="form-control" placeholder="Leave blank to keep existing">
            </div>
            <div class="form-group">
              <label class="form-label">From Email</label>
              <input type="email" name="smtp_from_email" class="form-control" placeholder="noreply@example.com" value="<?= e($settings['smtp_from_email'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">From Name</label>
              <input type="text" name="smtp_from_name" class="form-control" placeholder="Institute Support" value="<?= e($settings['smtp_from_name'] ?? '') ?>">
            </div>
          </div>
          <p style="margin-top:8px;font-size:12px;color:var(--text-muted);">
            Example: Gmail uses Host <code>smtp.gmail.com</code>, Port <code>587</code>, Encryption <code>tls</code>, and an App Password.
          </p>
        </div>
      </div>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
