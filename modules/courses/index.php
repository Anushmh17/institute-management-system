<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin']);

$pageTitle  = 'Courses';
$activePage = 'courses';
$pdo = db();

$courses = $pdo->query(
    "SELECT c.*, d.name AS dept_name,
            (SELECT COUNT(*) FROM enrollments e WHERE e.course_id=c.id AND e.status='active') AS enrolled,
            (SELECT COUNT(*) FROM subjects s WHERE s.course_id=c.id) AS subject_count
     FROM courses c
     LEFT JOIN departments d ON d.id=c.department_id
     ORDER BY c.created_at DESC"
)->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Courses</h1>
    <p class="page-subtitle"><?= count($courses) ?> courses in system</p>
  </div>
  <div class="page-header-actions">
    <a href="<?= IMS_URL ?>/modules/courses/add.php" class="btn btn-primary"><i class="ri-add-line"></i> New Course</a>
  </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;">
  <?php if (empty($courses)): ?>
  <div class="empty-state" style="grid-column:1/-1;">
    <i class="ri-book-open-line"></i>
    <h3>No Courses Yet</h3>
    <p>Create your first course to get started.</p>
    <a href="<?= IMS_URL ?>/modules/courses/add.php" class="btn btn-primary" style="margin-top:16px;">Add Course</a>
  </div>
  <?php else: ?>
  <?php foreach ($courses as $c): ?>
  <div class="card" style="transition:all .25s ease;">
    <div class="card-body">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px;">
        <div>
          <h3 style="font-family:var(--font-heading);font-size:16px;font-weight:700;"><?= e($c['name']) ?></h3>
          <code style="font-size:11px;background:var(--bg);padding:2px 6px;border-radius:4px;"><?= e($c['code']) ?></code>
        </div>
        <span class="badge badge-<?= $c['status']==='active'?'success':'muted' ?>"><?= ucfirst(e($c['status'])) ?></span>
      </div>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;min-height:36px;">
        <?= e(mb_strimwidth($c['description'] ?? 'No description.', 0, 80, '...')) ?>
      </p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px;">
        <div style="background:var(--bg);border-radius:8px;padding:10px;text-align:center;">
          <div style="font-size:20px;font-weight:700;color:var(--primary-light);"><?= $c['enrolled'] ?></div>
          <div style="font-size:11px;color:var(--text-muted);">Students</div>
        </div>
        <div style="background:var(--bg);border-radius:8px;padding:10px;text-align:center;">
          <div style="font-size:20px;font-weight:700;color:var(--success);"><?= $c['subject_count'] ?></div>
          <div style="font-size:11px;color:var(--text-muted);">Subjects</div>
        </div>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;color:var(--text-muted);margin-bottom:12px;">
        <span><i class="ri-time-line"></i> <?= $c['duration_months'] ?> months</span>
        <span><i class="ri-money-dollar-circle-line"></i> $<?= number_format($c['fee'], 0) ?></span>
        <?php if ($c['dept_name']): ?><span><i class="ri-building-line"></i> <?= e($c['dept_name']) ?></span><?php endif; ?>
      </div>
      <div style="display:flex;gap:8px;">
        <a href="<?= IMS_URL ?>/modules/courses/edit.php?id=<?= $c['id'] ?>" class="btn btn-outline btn-sm" style="flex:1;justify-content:center;">
          <i class="ri-edit-line"></i> Edit
        </a>
        <a href="<?= IMS_URL ?>/modules/subjects/index.php?course_id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm" style="flex:1;justify-content:center;">
          <i class="ri-book-2-line"></i> Subjects
        </a>
        <a href="<?= IMS_URL ?>/modules/courses/delete.php?id=<?= $c['id'] ?>" class="btn btn-outline btn-sm btn-icon"
           data-confirm-delete="course '<?= e($c['name']) ?>'">
          <i class="ri-delete-bin-line" style="color:var(--danger);"></i>
        </a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
