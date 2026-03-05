<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin', 'teacher']);

$pageTitle  = 'Students';
$activePage = 'students';

$pdo = db();
$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$search  = sanitize($_GET['search'] ?? '');
$status  = sanitize($_GET['status'] ?? '');
$course  = (int)($_GET['course'] ?? 0);

// Count total
$where  = 'WHERE 1=1';
$params = [];
if ($search) {
    $where  .= ' AND (u.full_name LIKE ? OR s.student_id LIKE ? OR u.email LIKE ?)';
    $params  = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($status) { $where .= ' AND s.status = ?'; $params[] = $status; }
if ($course)  { $where .= ' AND s.course_id = ?'; $params[] = $course; }

// Teacher can only see their courses' students
if (is_teacher()) {
    $teacherRow = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
    $teacherRow->execute([$_SESSION['user_id']]);
    $tid = (int)($teacherRow->fetchColumn() ?: 0);
    $where .= " AND s.course_id IN (SELECT DISTINCT course_id FROM subjects WHERE teacher_id = $tid)";
}

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM students s JOIN users u ON u.id = s.user_id LEFT JOIN courses c ON c.id = s.course_id $where"
);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$paging = paginate($total, $perPage, $page);

$stmt = $pdo->prepare(
    "SELECT s.*, u.full_name, u.email, u.phone, u.profile_photo, u.status AS user_status,
            c.name AS course_name
     FROM students s
     JOIN users u ON u.id = s.user_id
     LEFT JOIN courses c ON c.id = s.course_id
     $where
     ORDER BY s.created_at DESC
     LIMIT $perPage OFFSET {$paging['offset']}"
);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Courses for filter
$courses = $pdo->query("SELECT id, name FROM courses WHERE status='active' ORDER BY name")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Students</h1>
    <p class="page-subtitle"><?= number_format($total) ?> student<?= $total !== 1 ? 's' : '' ?> found</p>
  </div>
  <?php if (is_admin()): ?>
  <div class="page-header-actions">
    <a href="<?= IMS_URL ?>/modules/students/add.php" class="btn btn-primary">
      <i class="ri-user-add-line"></i> Add Student
    </a>
  </div>
  <?php endif; ?>
</div>

<!-- Table Card -->
<div class="table-wrapper">
  <!-- Toolbar -->
  <div class="table-toolbar">
    <div class="table-toolbar-left">
      <form method="GET" class="d-flex gap-2 align-center" style="flex-wrap:wrap; gap:8px;">
        <div class="search-control">
          <i class="ri-search-line"></i>
          <input type="text" name="search" class="form-control" placeholder="Search students..."
                 value="<?= e($search) ?>" style="width:220px;">
        </div>
        <select name="status" class="form-control" style="width:130px;">
          <option value="">All Status</option>
          <option value="active"    <?= $status==='active'    ? 'selected':'' ?>>Active</option>
          <option value="inactive"  <?= $status==='inactive'  ? 'selected':'' ?>>Inactive</option>
          <option value="graduated" <?= $status==='graduated' ? 'selected':'' ?>>Graduated</option>
          <option value="dropped"   <?= $status==='dropped'   ? 'selected':'' ?>>Dropped</option>
        </select>
        <select name="course" class="form-control" style="width:170px;">
          <option value="">All Courses</option>
          <?php foreach ($courses as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $course==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i class="ri-filter-3-line"></i> Filter</button>
        <?php if ($search || $status || $course): ?>
        <a href="<?= IMS_URL ?>/modules/students/index.php" class="btn btn-outline btn-sm">
          <i class="ri-refresh-line"></i> Clear
        </a>
        <?php endif; ?>
      </form>
    </div>
    <div class="table-toolbar-right">
      <span style="font-size:12px; color:var(--text-muted);">
        Showing <?= $paging['offset'] + 1 ?>-<?= min($paging['offset'] + $perPage, $total) ?> of <?= $total ?>
      </span>
    </div>
  </div>

  <!-- Table -->
  <?php if (empty($students)): ?>
  <div class="empty-state">
    <i class="ri-user-3-line"></i>
    <h3>No Students Found</h3>
    <p>Try adjusting your filters or add a new student.</p>
    <?php if (is_admin()): ?>
    <a href="<?= IMS_URL ?>/modules/students/add.php" class="btn btn-primary" style="margin-top:16px;">
      <i class="ri-user-add-line"></i> Add First Student
    </a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th class="sortable">Student ID</th>
        <th class="sortable">Name</th>
        <th>Course</th>
        <th>Email</th>
        <th class="sortable">Admission</th>
        <th>Status</th>
        <th style="text-align:right;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($students as $s): ?>
      <tr>
        <td>
          <code style="font-size:12px; background:var(--bg); padding:2px 6px; border-radius:4px;">
            <?= e($s['student_id']) ?>
          </code>
        </td>
        <td>
          <div class="d-flex align-center gap-2">
            <?php if (!empty($s['profile_photo'])): ?>
              <img src="<?= IMS_URL ?>/uploads/<?= e($s['profile_photo']) ?>"
                   style="width:32px;height:32px;border-radius:50%;object-fit:cover;" alt="">
            <?php else: ?>
              <div class="avatar-initials" style="width:32px;height:32px;font-size:12px;">
                <?= strtoupper(substr($s['full_name'], 0, 1)) ?>
              </div>
            <?php endif; ?>
            <span class="font-semi"><?= e($s['full_name']) ?></span>
          </div>
        </td>
        <td><?= e($s['course_name'] ?? '--') ?></td>
        <td style="color:var(--text-muted); font-size:12px;"><?= e($s['email']) ?></td>
        <td><?= $s['admission_date'] ? date('d M Y', strtotime($s['admission_date'])) : '--' ?></td>
        <td>
          <span class="badge badge-<?= match($s['status']) {
            'active' => 'success', 'inactive' => 'muted',
            'graduated' => 'primary', 'dropped' => 'danger', default => 'muted'
          } ?>"><?= ucfirst(e($s['status'])) ?></span>
        </td>
        <td style="text-align:right;">
          <div class="d-flex gap-2" style="justify-content:flex-end;">
            <a href="<?= IMS_URL ?>/modules/students/edit.php?id=<?= $s['id'] ?>"
               class="btn btn-outline btn-sm btn-icon" title="Edit">
              <i class="ri-edit-line"></i>
            </a>
            <?php if (is_admin()): ?>
            <a href="<?= IMS_URL ?>/modules/students/delete.php?id=<?= $s['id'] ?>"
               class="btn btn-outline btn-sm btn-icon" title="Delete"
               data-confirm-delete="<?= e($s['full_name']) ?>">
              <i class="ri-delete-bin-line" style="color:var(--danger);"></i>
            </a>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- Pagination -->
  <?php if ($paging['total_pages'] > 1): ?>
  <div class="pagination">
    <span class="pagination-info">Page <?= $paging['current'] ?> of <?= $paging['total_pages'] ?></span>
    <div class="d-flex gap-2">
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>"
         class="page-btn" <?= !$paging['has_prev'] ? 'disabled' : '' ?>><i class="ri-arrow-left-double-line"></i></a>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $paging['current'] - 1])) ?>"
         class="page-btn" <?= !$paging['has_prev'] ? 'disabled' : '' ?>><i class="ri-arrow-left-s-line"></i></a>
      <?php for ($i = max(1, $paging['current'] - 2); $i <= min($paging['total_pages'], $paging['current'] + 2); $i++): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
           class="page-btn <?= $i === $paging['current'] ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $paging['current'] + 1])) ?>"
         class="page-btn" <?= !$paging['has_next'] ? 'disabled' : '' ?>><i class="ri-arrow-right-s-line"></i></a>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $paging['total_pages']])) ?>"
         class="page-btn" <?= !$paging['has_next'] ? 'disabled' : '' ?>><i class="ri-arrow-right-double-line"></i></a>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
