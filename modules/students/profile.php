<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['student']);

$pageTitle  = 'My Profile';
$activePage = 'profile';
$pdo = db();

$stmt = $pdo->prepare(
    "SELECT s.*, u.full_name, u.email, u.username, u.phone, u.profile_photo, u.created_at, u.last_login, u.status AS user_status,
            c.name AS course_name
     FROM students s JOIN users u ON u.id=s.user_id
     LEFT JOIN courses c ON c.id=s.course_id
     WHERE s.user_id=? LIMIT 1"
);
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

if (!$student) {
    set_toast('error','Profile not found.');
    header('Location: ' . IMS_URL . '/dashboard.php');
    exit;
}

$initials    = get_initials($student['full_name'] ?? 'U');
$memberSince = isset($student['created_at']) ? date('M Y', strtotime($student['created_at'])) : 'N/A';
$lastLogin   = isset($student['last_login'])  ? date('d M Y, g:i A', strtotime($student['last_login'])) : 'Never';

// Attendance stats
$attTotal = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE student_id={$student['id']}")->fetchColumn();
$attPres  = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE student_id={$student['id']} AND status IN('present','late')")->fetchColumn();
$attPct   = $attTotal > 0 ? round($attPres / $attTotal * 100) : 0;

// Marks
$marksCount = (int)$pdo->query("SELECT COUNT(*) FROM marks WHERE student_id={$student['id']}")->fetchColumn();

include __DIR__ . '/../../includes/header.php';
?>

<style>
/* ══════════════════════════════════════════
   STUDENT PROFILE — PREMIUM STYLES
══════════════════════════════════════════ */
.spf-hero {
  background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 50%, #1E293B 100%);
  border-radius: 22px; height: 180px; position: relative; overflow: hidden; margin-bottom: 0;
}
.spf-hero::before {
  content:''; position:absolute; width:420px; height:420px;
  background:radial-gradient(circle,rgba(37,99,235,.25) 0%,transparent 65%);
  top:-150px; right:-60px; pointer-events:none;
}
.spf-hero::after {
  content:''; position:absolute; width:240px; height:240px;
  background:radial-gradient(circle,rgba(16,185,129,.12) 0%,transparent 70%);
  bottom:-80px; left:8%; pointer-events:none;
}
.spf-hero-pattern {
  position:absolute; inset:0;
  background-image: radial-gradient(circle at 2px 2px, rgba(255,255,255,.05) 1px, transparent 0);
  background-size: 28px 28px;
}

.spf-header-card {
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:0 0 20px 20px; border-top:none;
  padding:0 32px 24px; box-shadow:var(--shadow-md); margin-bottom:26px;
}
.spf-avatar-row {
  display:flex; align-items:flex-end; justify-content:space-between;
  flex-wrap:wrap; gap:16px;
}
.spf-avatar-group { display:flex; align-items:flex-end; gap:20px; }
.spf-avatar {
  width:100px; height:100px; border-radius:50%;
  object-fit:cover; border:4px solid var(--bg-card);
  box-shadow:0 4px 20px rgba(0,0,0,.2); margin-top:-50px;
}
.spf-initials {
  width:100px; height:100px; border-radius:50%;
  background:linear-gradient(135deg,#2563EB,#0284C7);
  display:flex; align-items:center; justify-content:center;
  font-family:var(--font-heading); font-size:34px; font-weight:800; color:#fff;
  border:4px solid var(--bg-card); box-shadow:0 4px 20px rgba(0,0,0,.2); margin-top:-50px;
}
.spf-identity { padding-bottom:6px; }
.spf-identity h2 {
  font-family:var(--font-heading); font-size:22px; font-weight:800;
  color:var(--text-primary); margin:0 0 6px;
}
.spf-header-actions { display:flex; gap:10px; padding-bottom:6px; }
.spf-action-btn {
  display:inline-flex; align-items:center; gap:7px;
  padding:9px 18px; border-radius:10px; font-size:13px; font-weight:700;
  text-decoration:none; border:none; cursor:pointer; transition:all .18s;
}
.spf-action-btn.prim { background:linear-gradient(135deg,#2563EB,#1D4ED8); color:#fff; box-shadow:0 4px 14px rgba(37,99,235,.35); }
.spf-action-btn.prim:hover { opacity:.9; color:#fff; }
.spf-action-btn.ghost { background:var(--bg-card); color:var(--text-secondary); border:1.5px solid var(--border); }
.spf-action-btn.ghost:hover { background:var(--bg-hover); color:var(--text-primary); }

.spf-meta-row {
  display:flex; gap:24px; flex-wrap:wrap;
  margin-top:18px; padding-top:18px; border-top:1px solid var(--border);
}
.spf-meta-item { display:flex; align-items:center; gap:7px; font-size:13px; color:var(--text-muted); }
.spf-meta-item i { font-size:14px; }
.spf-meta-item strong { color:var(--text-primary); font-weight:600; }

/* ── KPI mini strip ─────────────────────── */
.spf-kpi-row { display:flex; gap:14px; flex-wrap:wrap; margin-bottom:24px; }
.spf-kpi {
  flex:1; min-width:130px;
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:14px; padding:16px 18px;
  display:flex; align-items:center; gap:14px;
  box-shadow:var(--shadow-sm);
}
.spf-kpi-icon {
  width:40px; height:40px; border-radius:10px; flex-shrink:0;
  display:flex; align-items:center; justify-content:center; font-size:18px;
}
.spf-kpi-val  { font-family:var(--font-heading); font-size:22px; font-weight:800; color:var(--text-primary); line-height:1; }
.spf-kpi-lbl  { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); margin-top:3px; }

/* ── Tabs ──────────────────────────────── */
.spf-tabs-row { display:flex; gap:4px; margin-bottom:20px; flex-wrap:wrap; }
.spf-tab {
  padding:9px 20px; border-radius:10px; font-size:13px; font-weight:600;
  border:1.5px solid var(--border); background:var(--bg-card);
  color:var(--text-muted); cursor:pointer; transition:all .18s;
  display:flex; align-items:center; gap:6px;
}
.spf-tab:hover { background:var(--bg-hover); color:var(--text-primary); }
.spf-tab.active { background:#2563EB; color:#fff; border-color:#2563EB; box-shadow:0 3px 10px rgba(37,99,235,.3); }

/* ── Detail pane ───────────────────────── */
.spf-pane { display:none; }
.spf-pane.active { display:block; }
.spf-detail-card {
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:18px; overflow:hidden; box-shadow:var(--shadow-sm);
}
.spf-detail-head {
  display:flex; align-items:center; gap:10px;
  padding:15px 20px; border-bottom:1px solid var(--border);
  background:var(--bg);
}
[data-theme="dark"] .spf-detail-head { background:var(--bg-hover); }
.spf-detail-head-icon {
  width:34px; height:34px; border-radius:9px;
  display:flex; align-items:center; justify-content:center; font-size:15px;
}
.spf-detail-head-title { font-size:14px; font-weight:700; color:var(--text-primary); }

.spf-detail-grid {
  display:grid; grid-template-columns:1fr 1fr; gap:0;
}
@media (max-width:520px) { .spf-detail-grid { grid-template-columns:1fr; } }
.spf-detail-item {
  padding:14px 20px; border-bottom:1px solid var(--border);
}
.spf-detail-item:nth-last-child(-n+2):not([style]) { border-bottom:none; }
.spf-detail-item.full { grid-column:1/-1; }
.spf-detail-item label {
  display:block; font-size:10px; font-weight:700; text-transform:uppercase;
  letter-spacing:.5px; color:var(--text-muted); margin-bottom:5px;
}
.spf-detail-item span {
  font-size:13px; font-weight:600; color:var(--text-primary);
}
.spf-status-pill {
  display:inline-flex; align-items:center; gap:4px;
  padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700;
}
</style>

<!-- BANNER -->
<div class="spf-hero"><div class="spf-hero-pattern"></div></div>

<!-- PROFILE HEADER -->
<div class="spf-header-card">
  <div class="spf-avatar-row">
    <div class="spf-avatar-group">
      <?php if (!empty($student['profile_photo'])): ?>
        <img src="<?= IMS_URL ?>/uploads/<?= e($student['profile_photo']) ?>" class="spf-avatar" alt="Profile">
      <?php else: ?>
        <div class="spf-initials"><?= $initials ?></div>
      <?php endif; ?>
      <div class="spf-identity">
        <h2><?= e($student['full_name']) ?></h2>
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
          <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 11px;border-radius:20px;font-size:11px;font-weight:700;background:#DBEAFE;color:#1D4ED8;">
            <i class="ri-graduation-cap-line"></i> Student
          </span>
          <span style="font-size:12px; color:var(--text-muted); font-family:monospace;"><?= e($student['student_id']) ?></span>
          <?php if (!empty($student['course_name'])): ?>
          <span style="font-size:12px; color:var(--text-secondary);">
            <i class="ri-book-open-line" style="font-size:11px;"></i> <?= e($student['course_name']) ?>
          </span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="spf-header-actions">
      <a href="<?= IMS_URL ?>/modules/settings/profile.php" class="spf-action-btn prim">
        <i class="ri-settings-3-line"></i> Account Settings
      </a>
      <a href="<?= IMS_URL ?>/dashboard.php" class="spf-action-btn ghost">
        <i class="ri-arrow-left-line"></i> Dashboard
      </a>
    </div>
  </div>

  <div class="spf-meta-row">
    <div class="spf-meta-item"><i class="ri-mail-line"></i><strong><?= e($student['email']) ?></strong></div>
    <?php if (!empty($student['phone'])): ?>
    <div class="spf-meta-item"><i class="ri-phone-line"></i><strong><?= e($student['phone']) ?></strong></div>
    <?php endif; ?>
    <div class="spf-meta-item"><i class="ri-calendar-line"></i>Joined <strong><?= $memberSince ?></strong></div>
    <div class="spf-meta-item"><i class="ri-time-line"></i>Last login <strong><?= $lastLogin ?></strong></div>
  </div>
</div>

<!-- KPI MINI STRIP -->
<div class="spf-kpi-row">
  <div class="spf-kpi">
    <div class="spf-kpi-icon" style="background:#DBEAFE; color:#2563EB;"><i class="ri-user-follow-line"></i></div>
    <div>
      <div class="spf-kpi-val"><?= $attPct ?>%</div>
      <div class="spf-kpi-lbl">Attendance</div>
    </div>
  </div>
  <div class="spf-kpi">
    <div class="spf-kpi-icon" style="background:#D1FAE5; color:#059669;"><i class="ri-calendar-check-line"></i></div>
    <div>
      <div class="spf-kpi-val"><?= $attTotal ?></div>
      <div class="spf-kpi-lbl">Total Classes</div>
    </div>
  </div>
  <div class="spf-kpi">
    <div class="spf-kpi-icon" style="background:#F3E8FF; color:#7C3AED;"><i class="ri-bar-chart-2-line"></i></div>
    <div>
      <div class="spf-kpi-val"><?= $marksCount ?></div>
      <div class="spf-kpi-lbl">Assessments</div>
    </div>
  </div>
  <?php if (!empty($student['admission_date'])): ?>
  <div class="spf-kpi">
    <div class="spf-kpi-icon" style="background:#FEF3C7; color:#D97706;"><i class="ri-school-line"></i></div>
    <div>
      <div class="spf-kpi-val"><?= date('Y') - date('Y', strtotime($student['admission_date'])) ?>yr</div>
      <div class="spf-kpi-lbl">Enrolled For</div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- TABS -->
<div class="spf-tabs-row">
  <button class="spf-tab active" onclick="switchTab('personal',this)"><i class="ri-user-line"></i> Personal</button>
  <button class="spf-tab" onclick="switchTab('academic',this)"><i class="ri-book-open-line"></i> Academic</button>
  <button class="spf-tab" onclick="switchTab('guardian',this)"><i class="ri-parent-line"></i> Guardian</button>
</div>

<!-- Personal Tab -->
<div class="spf-pane active" id="tab-personal">
  <div class="spf-detail-card">
    <div class="spf-detail-head">
      <div class="spf-detail-head-icon" style="background:#DBEAFE; color:#2563EB;"><i class="ri-user-line"></i></div>
      <div class="spf-detail-head-title">Personal Information</div>
    </div>
    <div class="spf-detail-grid">
      <div class="spf-detail-item"><label>Full Name</label><span><?= e($student['full_name']) ?></span></div>
      <div class="spf-detail-item"><label>Email Address</label><span><?= e($student['email']) ?></span></div>
      <div class="spf-detail-item"><label>Phone</label><span><?= e($student['phone'] ?? '—') ?></span></div>
      <div class="spf-detail-item"><label>Date of Birth</label><span><?= $student['date_of_birth'] ? date('d M Y', strtotime($student['date_of_birth'])) : '—' ?></span></div>
      <div class="spf-detail-item"><label>Gender</label><span><?= ucfirst($student['gender'] ?? '—') ?></span></div>
      <div class="spf-detail-item"><label>Blood Group</label><span><?= e($student['blood_group'] ?? '—') ?></span></div>
      <div class="spf-detail-item full"><label>Address</label><span><?= e($student['address'] ?? '—') ?></span></div>
    </div>
  </div>
</div>

<!-- Academic Tab -->
<div class="spf-pane" id="tab-academic">
  <div class="spf-detail-card">
    <div class="spf-detail-head">
      <div class="spf-detail-head-icon" style="background:#D1FAE5; color:#059669;"><i class="ri-graduation-cap-line"></i></div>
      <div class="spf-detail-head-title">Academic Information</div>
    </div>
    <div class="spf-detail-grid">
      <div class="spf-detail-item"><label>Student ID</label><span style="font-family:monospace;"><?= e($student['student_id']) ?></span></div>
      <div class="spf-detail-item"><label>Course</label><span><?= e($student['course_name'] ?? '—') ?></span></div>
      <div class="spf-detail-item"><label>Admission Date</label><span><?= $student['admission_date'] ? date('d M Y', strtotime($student['admission_date'])) : '—' ?></span></div>
      <div class="spf-detail-item"><label>Batch Year</label><span><?= e($student['batch_year'] ?? '—') ?></span></div>
      <div class="spf-detail-item"><label>Status</label>
        <?php $active = ($student['status'] ?? '') === 'active'; ?>
        <span class="spf-status-pill" style="background:<?= $active ? 'var(--success-light)' : 'var(--danger-light)' ?>;color:<?= $active ? 'var(--success-dark)' : 'var(--danger-dark)' ?>;">
          <span style="width:5px;height:5px;border-radius:50%;background:currentColor;display:inline-block;"></span>
          <?= ucfirst($student['status'] ?? '—') ?>
        </span>
      </div>
      <div class="spf-detail-item"><label>Attendance</label>
        <span style="font-weight:800; color:<?= $attPct >= 75 ? 'var(--success)' : 'var(--danger)' ?>;"><?= $attPct ?>%</span>
      </div>
    </div>
  </div>
</div>

<!-- Guardian Tab -->
<div class="spf-pane" id="tab-guardian">
  <div class="spf-detail-card">
    <div class="spf-detail-head">
      <div class="spf-detail-head-icon" style="background:#FEF3C7; color:#D97706;"><i class="ri-parent-line"></i></div>
      <div class="spf-detail-head-title">Guardian / Emergency Contact</div>
    </div>
    <div class="spf-detail-grid">
      <div class="spf-detail-item"><label>Guardian Name</label><span><?= e($student['guardian_name'] ?? '—') ?></span></div>
      <div class="spf-detail-item"><label>Guardian Phone</label><span><?= e($student['guardian_phone'] ?? '—') ?></span></div>
      <div class="spf-detail-item full"><label>Guardian Email</label><span><?= e($student['guardian_email'] ?? '—') ?></span></div>
    </div>
  </div>
</div>

<script>
function switchTab(name, btn) {
  document.querySelectorAll('.spf-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.spf-tab').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name)?.classList.add('active');
  btn.classList.add('active');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
