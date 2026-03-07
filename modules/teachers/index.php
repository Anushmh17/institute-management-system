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

// Totals for hero
$totalTeachers  = $pdo->query("SELECT COUNT(*) FROM teachers t JOIN users u ON u.id=t.user_id")->fetchColumn();
$activeTeachers = $pdo->query("SELECT COUNT(*) FROM teachers t JOIN users u ON u.id=t.user_id WHERE u.status='active'")->fetchColumn();
$deptCount      = $pdo->query("SELECT COUNT(DISTINCT department_id) FROM teachers WHERE department_id IS NOT NULL")->fetchColumn();

include __DIR__ . '/../../includes/header.php';
?>

<style>
/* ===== TEACHERS PAGE (inherits from students shared style prefix pg-) ===== */
.pg-hero { background:linear-gradient(135deg,#0F172A 0%,#065F46 55%,#1E293B 100%); border-radius:20px; padding:32px 36px; margin-bottom:26px; position:relative; overflow:hidden; }
.pg-hero::before { content:''; position:absolute; width:380px; height:380px; background:radial-gradient(circle,rgba(16,185,129,.22) 0%,transparent 70%); top:-100px; right:-40px; pointer-events:none; }
.pg-hero::after  { content:''; position:absolute; width:260px; height:260px; background:radial-gradient(circle,rgba(37,99,235,.12) 0%,transparent 70%); bottom:-70px; left:5%; pointer-events:none; }
.pg-hero-inner { position:relative; z-index:1; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:20px; }
.pg-hero h1 { font-family:var(--font-heading); font-size:30px; font-weight:800; color:#fff; margin:0 0 5px; letter-spacing:-.4px; }
.pg-hero p  { font-size:13px; color:rgba(255,255,255,.55); margin:0; }
.hero-kpis  { display:flex; gap:24px; flex-wrap:wrap; }
.hero-kpi   { text-align:center; }
.hero-kpi-val { font-family:var(--font-heading); font-size:26px; font-weight:800; color:#fff; line-height:1; }
.hero-kpi-lbl { font-size:10px; font-weight:700; color:rgba(255,255,255,.45); text-transform:uppercase; letter-spacing:.8px; margin-top:3px; }
.hero-kpi-divider { width:1px; background:rgba(255,255,255,.12); height:36px; align-self:center; }
.pg-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
.pg-toolbar-left { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.pg-search-wrap { position:relative; }
.pg-search-wrap > i { position:absolute; left:11px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:14px; pointer-events:none; }
.pg-search-input { padding:9px 14px 9px 34px; border:1.5px solid var(--border); border-radius:10px; background:var(--bg-card); color:var(--text-primary); font-size:13px; width:260px; outline:none; font-family:inherit; transition:all .2s; }
.pg-search-input:focus { border-color:var(--success); box-shadow:0 0 0 3px rgba(16,185,129,.12); }
.pg-btn-filter { display:inline-flex; align-items:center; gap:6px; padding:9px 18px; border-radius:10px; background:var(--success); color:#fff; font-size:13px; font-weight:600; border:none; cursor:pointer; transition:opacity .2s; }
.pg-btn-filter:hover { opacity:.88; }
.pg-btn-clear { display:inline-flex; align-items:center; gap:5px; padding:9px 14px; border-radius:10px; border:1.5px solid var(--border); background:var(--bg-card); color:var(--text-secondary); font-size:13px; font-weight:600; text-decoration:none; transition:all .2s; }
.pg-btn-clear:hover { background:var(--bg-hover); color:var(--text-primary); }
.pg-table-card { background:var(--bg-card); border:1px solid var(--border); border-radius:18px; overflow:hidden; box-shadow:var(--shadow-sm); }
.pg-table-head { display:flex; align-items:center; justify-content:space-between; padding:16px 22px; border-bottom:1px solid var(--border); background:var(--bg); gap:10px; flex-wrap:wrap; }
[data-theme="dark"] .pg-table-head { background:var(--bg-hover); }
.pg-table-head-title { font-size:14px; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:8px; }
.pg-table-head-title i { color:var(--success); font-size:16px; }
.pg-count-badge { font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; background:var(--success-light); color:var(--success-dark); }
.pg-showing { font-size:12px; color:var(--text-muted); font-weight:500; }
table.pg-table { width:100%; border-collapse:collapse; }
table.pg-table thead tr { border-bottom:1px solid var(--border); }
table.pg-table thead th { padding:11px 16px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--text-muted); text-align:left; white-space:nowrap; }
table.pg-table tbody tr { border-bottom:1px solid var(--border); transition:background .15s; }
table.pg-table tbody tr:last-child { border-bottom:none; }
table.pg-table tbody tr:hover { background:var(--bg-hover); }
table.pg-table tbody td { padding:13px 16px; font-size:13px; color:var(--text-primary); vertical-align:middle; }
.tch-avatar { width:38px; height:38px; border-radius:50%; object-fit:cover; border:2px solid var(--border); }
.tch-initials { width:38px; height:38px; border-radius:50%; background:linear-gradient(135deg,#059669,#0284C7); color:#fff; font-weight:700; font-size:14px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.tch-name { font-weight:600; color:var(--text-primary); font-size:13px; }
.tch-email { font-size:11px; color:var(--text-muted); margin-top:1px; }
.tch-id-badge { font-size:11px; font-weight:700; padding:3px 8px; border-radius:6px; background:var(--bg-hover); border:1px solid var(--border); color:var(--text-secondary); font-family:monospace; }
.status-pill { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.status-pill.active   { background:var(--success-light); color:var(--success-dark); }
.status-pill.inactive { background:var(--bg-hover); color:var(--text-muted); }
.status-dot { width:6px; height:6px; border-radius:50%; background:currentColor; }
.subj-badge { display:inline-flex; align-items:center; white-space:nowrap; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; background:var(--info-light); color:var(--primary); }
.tbl-action-btn { width:32px; height:32px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; font-size:14px; border:1.5px solid var(--border); background:var(--bg-card); color:var(--text-secondary); text-decoration:none; transition:all .18s; }
.tbl-action-btn:hover { background:var(--bg-hover); color:var(--text-primary); border-color:var(--border-strong); }
.tbl-action-btn.danger:hover { background:var(--danger-light); color:var(--danger); border-color:rgba(239,68,68,.3); }
.pg-pagination { display:flex; align-items:center; justify-content:space-between; padding:14px 20px; border-top:1px solid var(--border); flex-wrap:wrap; gap:10px; }
.pg-page-info { font-size:12px; color:var(--text-muted); font-weight:500; }
.pg-page-btns { display:flex; gap:5px; }
.pg-page-btn { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:600; color:var(--text-secondary); border:1.5px solid var(--border); background:var(--bg-card); text-decoration:none; transition:all .15s; }
.pg-page-btn:hover { background:var(--bg-hover); color:var(--text-primary); }
.pg-page-btn.active { background:var(--success); color:#fff; border-color:var(--success); }
.pg-page-btn[disabled] { opacity:.35; pointer-events:none; }
.pg-empty { padding:60px 20px; text-align:center; }
.pg-empty i { font-size:44px; color:var(--border-strong); display:block; margin:0 auto 14px; }
.pg-empty h3 { font-size:18px; font-weight:700; margin:0 0 6px; }
.pg-empty p  { font-size:13px; color:var(--text-muted); margin:0 0 18px; }
</style>

<!-- HERO -->
<div class="pg-hero">
  <div class="pg-hero-inner">
    <div>
      <h1><i class="ri-user-star-line" style="margin-right:10px; color:#34D399;"></i>Teachers</h1>
      <p>Manage all faculty members, departments, and assigned subjects.</p>
    </div>
    <div class="hero-kpis">
      <div class="hero-kpi">
        <div class="hero-kpi-val"><?= $totalTeachers ?></div>
        <div class="hero-kpi-lbl">Total</div>
      </div>
      <div class="hero-kpi-divider"></div>
      <div class="hero-kpi">
        <div class="hero-kpi-val" style="color:#34D399;"><?= $activeTeachers ?></div>
        <div class="hero-kpi-lbl">Active</div>
      </div>
      <div class="hero-kpi-divider"></div>
      <div class="hero-kpi">
        <div class="hero-kpi-val" style="color:#FBBF24;"><?= $deptCount ?></div>
        <div class="hero-kpi-lbl">Departments</div>
      </div>
    </div>
  </div>
</div>

<!-- TOOLBAR -->
<div class="pg-toolbar">
  <div class="pg-toolbar-left">
    <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
      <div class="pg-search-wrap">
        <i class="ri-search-line"></i>
        <input type="text" name="search" class="pg-search-input" placeholder="Search name, ID, email…" value="<?= e($search) ?>">
      </div>
      <button type="submit" class="pg-btn-filter"><i class="ri-filter-3-line"></i> Search</button>
      <?php if ($search): ?>
      <a href="<?= IMS_URL ?>/modules/teachers/index.php" class="pg-btn-clear"><i class="ri-refresh-line"></i> Clear</a>
      <?php endif; ?>
    </form>
  </div>
  <?php if (is_admin()): ?>
  <a href="<?= IMS_URL ?>/modules/teachers/add.php" class="btn btn-primary" style="border-radius:10px; font-size:13px; font-weight:600; background:var(--success); box-shadow:0 4px 14px rgba(5,150,105,.35);">
    <i class="ri-user-add-line"></i> Add Teacher
  </a>
  <?php endif; ?>
</div>

<!-- TABLE CARD -->
<div class="pg-table-card">
  <div class="pg-table-head">
    <div class="pg-table-head-title">
      <i class="ri-user-star-line"></i> Faculty Directory
      <span class="pg-count-badge"><?= number_format($total) ?></span>
    </div>
  </div>

  <?php if (empty($teachers)): ?>
  <div class="pg-empty">
    <i class="ri-user-star-line"></i>
    <h3>No Teachers Found</h3>
    <p>Add your first teacher to get started.</p>
    <?php if (is_admin()): ?>
    <a href="<?= IMS_URL ?>/modules/teachers/add.php" class="btn btn-primary" style="background:var(--success);">Add Teacher</a>
    <?php endif; ?>
  </div>
  <?php else: ?>

  <div style="overflow-x:auto;">
    <table class="pg-table">
      <thead>
        <tr>
          <th>Teacher ID</th>
          <th>Teacher</th>
          <th>Department</th>
          <th>Qualification</th>
          <th>Subjects</th>
          <th>Status</th>
          <?php if (is_admin()): ?><th style="text-align:right;">Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($teachers as $t): ?>
        <tr>
          <td><span class="tch-id-badge"><?= e($t['teacher_id']) ?></span></td>
          <td>
            <div style="display:flex; align-items:center; gap:10px;">
              <?php if (!empty($t['profile_photo'])): ?>
                <img src="<?= IMS_URL ?>/uploads/<?= e($t['profile_photo']) ?>" class="tch-avatar" alt="">
              <?php else: ?>
                <div class="tch-initials"><?= strtoupper(substr($t['full_name'], 0, 1)) ?></div>
              <?php endif; ?>
              <div>
                <div class="tch-name"><?= e($t['full_name']) ?></div>
                <div class="tch-email"><?= e($t['email']) ?></div>
              </div>
            </div>
          </td>
          <td style="color:var(--text-secondary); font-size:13px;"><?= e($t['dept_name'] ?? '—') ?></td>
          <td style="color:var(--text-secondary); font-size:13px;"><?= e($t['qualification'] ?? '—') ?></td>
          <td><span class="subj-badge"><?= $t['subject_count'] ?> subjects</span></td>
          <td>
            <span class="status-pill <?= $t['user_status'] === 'active' ? 'active' : 'inactive' ?>">
              <span class="status-dot"></span><?= ucfirst($t['user_status']) ?>
            </span>
          </td>
          <?php if (is_admin()): ?>
          <td style="text-align:right;">
            <div style="display:flex; gap:6px; justify-content:flex-end;">
              <a href="<?= IMS_URL ?>/modules/teachers/edit.php?id=<?= $t['id'] ?>" class="tbl-action-btn" title="Edit"><i class="ri-edit-line"></i></a>
              <a href="<?= IMS_URL ?>/modules/teachers/delete.php?id=<?= $t['id'] ?>" class="tbl-action-btn danger" title="Delete" data-confirm-delete="<?= e($t['full_name']) ?>"><i class="ri-delete-bin-line"></i></a>
            </div>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($paging['total_pages'] > 1): ?>
  <div class="pg-pagination">
    <span class="pg-page-info">Page <?= $paging['current'] ?> of <?= $paging['total_pages'] ?></span>
    <div class="pg-page-btns">
      <a href="?<?= http_build_query(array_merge($_GET,['page'=>1])) ?>" class="pg-page-btn" <?= !$paging['has_prev']?'disabled':'' ?>><i class="ri-arrow-left-double-line"></i></a>
      <a href="?<?= http_build_query(array_merge($_GET,['page'=>$paging['current']-1])) ?>" class="pg-page-btn" <?= !$paging['has_prev']?'disabled':'' ?>><i class="ri-arrow-left-s-line"></i></a>
      <?php for ($i=max(1,$paging['current']-2);$i<=min($paging['total_pages'],$paging['current']+2);$i++): ?>
      <a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="pg-page-btn <?= $i===$paging['current']?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <a href="?<?= http_build_query(array_merge($_GET,['page'=>$paging['current']+1])) ?>" class="pg-page-btn" <?= !$paging['has_next']?'disabled':'' ?>><i class="ri-arrow-right-s-line"></i></a>
      <a href="?<?= http_build_query(array_merge($_GET,['page'=>$paging['total_pages']])) ?>" class="pg-page-btn" <?= !$paging['has_next']?'disabled':'' ?>><i class="ri-arrow-right-double-line"></i></a>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
