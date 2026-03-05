<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['student']);

$pageTitle  = 'My Profile';
$activePage = 'profile';
$pdo = db();

$stmt = $pdo->prepare(
    "SELECT s.*, u.full_name, u.email, u.username, u.phone, u.profile_photo, c.name AS course_name
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

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">My Profile</h1>
    <p class="page-subtitle">View your personal and academic details</p>
  </div>
  <div class="page-header-actions">
    <a href="<?= IMS_URL ?>/modules/settings/profile.php" class="btn btn-outline">
      <i class="ri-settings-3-line"></i> Account Settings
    </a>
  </div>
</div>

<div class="profile-card">
  <div class="profile-banner"></div>
  <div class="profile-card-body">
    <div class="profile-avatar-wrap">
      <?php if (!empty($student['profile_photo'])): ?>
        <img src="<?= IMS_URL ?>/uploads/<?= e($student['profile_photo']) ?>" class="profile-photo" alt="Profile">
      <?php else: ?>
        <div class="profile-photo-initials"><?= strtoupper(substr($student['full_name'],0,1)) ?></div>
      <?php endif; ?>
      <div class="profile-name-block">
        <h2><?= e($student['full_name']) ?></h2>
        <p class="profile-id"><?= e($student['student_id']) ?> <span class="divider"></span> <?= e($student['course_name']??'--') ?></p>
      </div>
    </div>

    <div class="tabs">
      <button type="button" class="tab-btn active" data-tab="pf-personal">Personal</button>
      <button type="button" class="tab-btn" data-tab="pf-academic">Academic</button>
      <button type="button" class="tab-btn" data-tab="pf-guardian">Guardian</button>
    </div>

    <div class="tab-pane active" id="pf-personal">
      <div class="detail-grid">
        <div class="detail-item"><label>Full Name</label><span><?= e($student['full_name']) ?></span></div>
        <div class="detail-item"><label>Email</label><span><?= e($student['email']) ?></span></div>
        <div class="detail-item"><label>Phone</label><span><?= e($student['phone']??'--') ?></span></div>
        <div class="detail-item"><label>Date of Birth</label><span><?= $student['date_of_birth'] ? date('d M Y',strtotime($student['date_of_birth'])) : '--' ?></span></div>
        <div class="detail-item"><label>Gender</label><span><?= ucfirst($student['gender']??'--') ?></span></div>
        <div class="detail-item"><label>Blood Group</label><span><?= e($student['blood_group']??'--') ?></span></div>
        <div class="detail-item" style="grid-column:1/-1;"><label>Address</label><span><?= e($student['address']??'--') ?></span></div>
      </div>
    </div>

    <div class="tab-pane" id="pf-academic">
      <div class="detail-grid">
        <div class="detail-item"><label>Student ID</label><span><?= e($student['student_id']) ?></span></div>
        <div class="detail-item"><label>Course</label><span><?= e($student['course_name']??'--') ?></span></div>
        <div class="detail-item"><label>Admission Date</label><span><?= $student['admission_date'] ? date('d M Y',strtotime($student['admission_date'])) : '--' ?></span></div>
        <div class="detail-item"><label>Batch Year</label><span><?= e($student['batch_year']??'--') ?></span></div>
        <div class="detail-item"><label>Status</label>
          <span class="badge badge-<?= $student['status']==='active'?'success':'danger' ?>"><?= ucfirst(e($student['status'])) ?></span>
        </div>
      </div>
    </div>

    <div class="tab-pane" id="pf-guardian">
      <div class="detail-grid">
        <div class="detail-item"><label>Guardian Name</label><span><?= e($student['guardian_name']??'--') ?></span></div>
        <div class="detail-item"><label>Guardian Phone</label><span><?= e($student['guardian_phone']??'--') ?></span></div>
        <div class="detail-item"><label>Guardian Email</label><span><?= e($student['guardian_email']??'--') ?></span></div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
