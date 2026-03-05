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
if (!$teacher) { set_toast('error','Not found.'); header('Location: ' . IMS_URL . '/modules/teachers/index.php'); exit; }

$pageTitle = 'Edit Teacher'; $activePage = 'teachers'; $errors = [];
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
            if (!empty($newPassword) && strlen($newPassword)>=8)
                $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($newPassword,PASSWORD_BCRYPT,['cost'=>12]),$teacher['user_id']]);
            $pdo->prepare("UPDATE teachers SET department_id=?,qualification=?,specialization=?,salary=?,join_date=? WHERE id=?")
                ->execute([$deptId?:null,$qualification,$specialization,$salary?:null,$joinDate?:null,$id]);
            set_toast('success','Teacher updated!');
            header('Location: ' . IMS_URL . '/modules/teachers/index.php');
            exit;
        } catch (PDOException $e) { $errors[] = 'DB error.'; }
    }
}

include __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
  <div class="page-header-left"><h1 class="page-title">Edit Teacher</h1><p class="page-subtitle"><?= e($teacher['full_name']) ?> (<?= e($teacher['teacher_id']) ?>)</p></div>
  <div class="page-header-actions"><a href="<?= IMS_URL ?>/modules/teachers/index.php" class="btn btn-outline"><i class="ri-arrow-left-line"></i> Back</a></div>
</div>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger"><i class="ri-error-warning-fill"></i>
  <div><ul style="padding-left:16px;"><?php foreach($errors as $e2): ?><li><?= e($e2)?></li><?php endforeach;?></ul></div>
</div>
<?php endif; ?>

<div class="card" style="max-width:700px;">
  <div class="card-header"><h3 class="card-title"><i class="ri-edit-line"></i> Update Details</h3></div>
  <div class="card-body">
    <form method="POST" data-validate>
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group"><label class="form-label">Full Name <span class="required">*</span></label>
          <input type="text" name="full_name" class="form-control" required value="<?= e($teacher['full_name']) ?>"></div>
        <div class="form-group"><label class="form-label">Email <span class="required">*</span></label>
          <input type="email" name="email" class="form-control" required value="<?= e($teacher['email']) ?>"></div>
        <div class="form-group"><label class="form-label">Phone</label>
          <input type="tel" name="phone" class="form-control" value="<?= e($teacher['phone']??'') ?>"></div>
        <div class="form-group"><label class="form-label">Department</label>
          <select name="department_id" class="form-control">
            <option value="">-- None --</option>
            <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $teacher['department_id']==$d['id']?'selected':'' ?>><?= e($d['name']) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="form-group"><label class="form-label">Qualification</label>
          <input type="text" name="qualification" class="form-control" value="<?= e($teacher['qualification']??'') ?>"></div>
        <div class="form-group"><label class="form-label">Specialization</label>
          <input type="text" name="specialization" class="form-control" value="<?= e($teacher['specialization']??'') ?>"></div>
        <div class="form-group"><label class="form-label">Salary ($)</label>
          <input type="number" name="salary" class="form-control" step="0.01" value="<?= e($teacher['salary']??'') ?>"></div>
        <div class="form-group"><label class="form-label">Join Date</label>
          <input type="date" name="join_date" class="form-control" value="<?= e($teacher['join_date']??'') ?>"></div>
        <div class="form-group"><label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="active" <?= $teacher['user_status']==='active'?'selected':'' ?>>Active</option>
            <option value="inactive" <?= $teacher['user_status']==='inactive'?'selected':'' ?>>Inactive</option>
          </select></div>
        <div class="form-group"><label class="form-label">New Password <small class="text-muted">(leave blank to keep)</small></label>
          <div class="password-toggle"><input type="password" name="new_password" class="form-control" placeholder="Min 8 chars"><i class="ri-eye-line toggle-eye"></i></div></div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:20px;padding-top:20px;border-top:1px solid var(--border);">
        <a href="<?= IMS_URL ?>/modules/teachers/index.php" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> Update</button>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
