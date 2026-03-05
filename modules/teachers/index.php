<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin', 'teacher']);

$pageTitle  = 'Teachers';
$activePage = 'teachers';
$pdo = db();

$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$search  = sanitize($_GET['search'] ?? '');

$where  = 'WHERE 1=1';
$params = [];
if ($search) {
    $where  .= ' AND (u.full_name LIKE ? OR t.teacher_id LIKE ? OR u.email LIKE ?)';
    $params  = ["%$search%", "%$search%", "%$search%"];
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM teachers t JOIN users u ON u.id = t.user_id $where");
$countStmt->execute($params);
$total  = (int)$countStmt->fetchColumn();
$paging = paginate($total, $perPage, $page);

$stmt = $pdo->prepare(
    "SELECT t.*, u.full_name, u.email, u.phone, u.profile_photo, u.status AS user_status,
            d.name AS dept_name,
            (SELECT COUNT(DISTINCT course_id) FROM subjects WHERE teacher_id = t.id) AS subject_count
     FROM teachers t
     JOIN users u ON u.id = t.user_id
     LEFT JOIN departments d ON d.id = t.department_id
     $where
     ORDER BY t.created_at DESC
     LIMIT $perPage OFFSET {$paging['offset']}"
);
$stmt->execute($params);
$teachers = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Teachers</h1>
    <p class="page-subtitle"><?= number_format($total) ?> teacher<?= $total !== 1 ? 's' : '' ?></p>
  </div>
  <?php if (is_admin()): ?>
  <div class="page-header-actions">
    <a href="<?= IMS_URL ?>/modules/teachers/add.php" class="btn btn-primary">
      <i class="ri-user-add-line"></i> Add Teacher
    </a>
  </div>
  <?php endif; ?>
</div>

<div class="table-wrapper">
  <div class="table-toolbar">
    <div class="table-toolbar-left">
      <form method="GET" class="d-flex gap-2 align-center">
        <div class="search-control">
          <i class="ri-search-line"></i>
          <input type="text" name="search" class="form-control" placeholder="Search teachers..."
                 value="<?= e($search) ?>" style="width:240px;">
        </div>
        <button type="submit" class="btn btn-primary btn-sm"><i class="ri-filter-3-line"></i> Search</button>
        <?php if ($search): ?>
        <a href="<?= IMS_URL ?>/modules/teachers/index.php" class="btn btn-outline btn-sm"><i class="ri-refresh-line"></i> Clear</a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <?php if (empty($teachers)): ?>
  <div class="empty-state">
    <i class="ri-user-star-line"></i>
    <h3>No Teachers Found</h3>
    <p>Add your first teacher to get started.</p>
    <?php if (is_admin()): ?>
    <a href="<?= IMS_URL ?>/modules/teachers/add.php" class="btn btn-primary" style="margin-top:16px;">Add Teacher</a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <table>
    <thead><tr>
      <th class="sortable">Teacher ID</th>
      <th class="sortable">Name</th>
      <th>Department</th>
      <th>Qualification</th>
      <th>Subjects</th>
      <th>Status</th>
      <?php if (is_admin()): ?><th style="text-align:right;">Actions</th><?php endif; ?>
    </tr></thead>
    <tbody>
      <?php foreach ($teachers as $t): ?>
      <tr>
        <td><code style="font-size:12px;"><?= e($t['teacher_id']) ?></code></td>
        <td>
          <div class="d-flex align-center gap-2">
            <?php if (!empty($t['profile_photo'])): ?>
              <img src="<?= IMS_URL ?>/uploads/<?= e($t['profile_photo']) ?>"
                   style="width:32px;height:32px;border-radius:50%;object-fit:cover;" alt="">
            <?php else: ?>
              <div class="avatar-initials" style="width:32px;height:32px;font-size:12px;">
                <?= strtoupper(substr($t['full_name'], 0, 1)) ?>
              </div>
            <?php endif; ?>
            <div>
              <div class="font-semi"><?= e($t['full_name']) ?></div>
              <div style="font-size:11px;color:var(--text-muted);"><?= e($t['email']) ?></div>
            </div>
          </div>
        </td>
        <td><?= e($t['dept_name'] ?? '--') ?></td>
        <td><?= e($t['qualification'] ?? '--') ?></td>
        <td><span class="badge badge-primary"><?= $t['subject_count'] ?> subjects</span></td>
        <td><span class="badge badge-<?= $t['user_status']==='active'?'success':'muted' ?>"><?= ucfirst(e($t['user_status'])) ?></span></td>
        <?php if (is_admin()): ?>
        <td style="text-align:right;">
          <div class="d-flex gap-2" style="justify-content:flex-end;">
            <a href="<?= IMS_URL ?>/modules/teachers/edit.php?id=<?= $t['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Edit"><i class="ri-edit-line"></i></a>
            <a href="<?= IMS_URL ?>/modules/teachers/delete.php?id=<?= $t['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Delete" data-confirm-delete="<?= e($t['full_name']) ?>">
              <i class="ri-delete-bin-line" style="color:var(--danger);"></i>
            </a>
          </div>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <?php if ($paging['total_pages'] > 1): ?>
  <div class="pagination">
    <span class="pagination-info">Page <?= $paging['current'] ?> of <?= $paging['total_pages'] ?></span>
    <div class="d-flex gap-2">
      <a href="?<?= http_build_query(array_merge($_GET,['page'=>1])) ?>" class="page-btn" <?= !$paging['has_prev']?'disabled':'' ?>><i class="ri-arrow-left-double-line"></i></a>
      <a href="?<?= http_build_query(array_merge($_GET,['page'=>$paging['current']-1])) ?>" class="page-btn" <?= !$paging['has_prev']?'disabled':'' ?>><i class="ri-arrow-left-s-line"></i></a>
      <?php for ($i=max(1,$paging['current']-2);$i<=min($paging['total_pages'],$paging['current']+2);$i++): ?>
      <a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="page-btn <?= $i===$paging['current']?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <a href="?<?= http_build_query(array_merge($_GET,['page'=>$paging['current']+1])) ?>" class="page-btn" <?= !$paging['has_next']?'disabled':'' ?>><i class="ri-arrow-right-s-line"></i></a>
      <a href="?<?= http_build_query(array_merge($_GET,['page'=>$paging['total_pages']])) ?>" class="page-btn" <?= !$paging['has_next']?'disabled':'' ?>><i class="ri-arrow-right-double-line"></i></a>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
