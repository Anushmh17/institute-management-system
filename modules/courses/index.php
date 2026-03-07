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

$totalCourses   = count($courses);
$activeCourses  = count(array_filter($courses, fn($c) => $c['status'] === 'active'));
$totalStudents  = array_sum(array_column($courses, 'enrolled'));
$totalSubjects  = array_sum(array_column($courses, 'subject_count'));

// Assign gradient palette per card index
$gradients = [
    ['from' => '#2563EB', 'to' => '#7C3AED'],
    ['from' => '#059669', 'to' => '#0284C7'],
    ['from' => '#D97706', 'to' => '#DC2626'],
    ['from' => '#7C3AED', 'to' => '#DB2777'],
    ['from' => '#0284C7', 'to' => '#059669'],
    ['from' => '#DC2626', 'to' => '#D97706'],
];

include __DIR__ . '/../../includes/header.php';
?>

<style>
/* ========== COURSES PAGE PREMIUM STYLES ========== */

/* --- Hero Banner --- */
.courses-hero {
  background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 50%, #1E293B 100%);
  border-radius: 20px;
  padding: 36px 40px;
  margin-bottom: 28px;
  position: relative;
  overflow: hidden;
}
.courses-hero::before {
  content: '';
  position: absolute;
  width: 400px; height: 400px;
  background: radial-gradient(circle, rgba(37,99,235,0.25) 0%, transparent 70%);
  top: -100px; right: -50px;
  pointer-events: none;
}
.courses-hero::after {
  content: '';
  position: absolute;
  width: 300px; height: 300px;
  background: radial-gradient(circle, rgba(245,158,11,0.15) 0%, transparent 70%);
  bottom: -80px; left: 10%;
  pointer-events: none;
}
.hero-content { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 24px; }
.hero-left h1 { font-family: var(--font-heading); font-size: 32px; font-weight: 800; color: #fff; margin: 0 0 6px; letter-spacing: -0.5px; }
.hero-left p  { font-size: 14px; color: rgba(255,255,255,0.6); margin: 0; }
.hero-stats   { display: flex; gap: 28px; flex-wrap: wrap; }
.hero-stat    { text-align: center; }
.hero-stat-val{ font-family: var(--font-heading); font-size: 28px; font-weight: 800; color: #fff; line-height: 1; }
.hero-stat-lbl{ font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.8px; margin-top: 4px; }
.hero-stat-divider { width: 1px; background: rgba(255,255,255,0.12); height: 40px; align-self: center; }

/* --- Toolbar --- */
.courses-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 24px;
}
.toolbar-left { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.toolbar-right { display: flex; align-items: center; gap: 10px; }

/* Filter tabs */
.filter-tabs { display: flex; gap: 4px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; padding: 4px; }
.filter-tab {
  padding: 7px 18px; border-radius: 7px; font-size: 13px; font-weight: 600;
  color: var(--text-muted); cursor: pointer; border: none; background: transparent;
  transition: all 0.18s ease; white-space: nowrap;
}
.filter-tab.active { background: var(--primary-light); color: #fff; box-shadow: 0 2px 8px rgba(37,99,235,0.35); }
.filter-tab:hover:not(.active) { background: var(--bg-hover); color: var(--text-primary); }

/* Search box */
.course-search-wrap { position: relative; }
.course-search-wrap > i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 15px; pointer-events: none; }
.course-search-input {
  padding: 9px 16px 9px 38px; border: 1px solid var(--border); border-radius: 10px;
  background: var(--bg-card); color: var(--text-primary); font-size: 13px;
  width: 280px; outline: none; transition: all 0.2s ease;
}
.course-search-input:focus { border-color: var(--primary-light); box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
.course-search-input::placeholder { color: var(--text-muted); }

/* --- Grid --- */
.courses-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
  gap: 22px;
}

/* --- Course Card --- */
.course-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 18px;
  overflow: hidden;
  box-shadow: var(--shadow-sm);
  transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
  display: flex;
  flex-direction: column;
  cursor: default;
}
.course-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 20px 50px rgba(0,0,0,0.13);
  border-color: transparent;
}

/* Gradient top strip */
.course-card-banner {
  height: 5px;
  background: linear-gradient(90deg, var(--card-from), var(--card-to));
  flex-shrink: 0;
}

/* Card top: icon + title + badge */
.course-card-top {
  padding: 24px 24px 16px;
  display: flex;
  gap: 16px;
  align-items: flex-start;
}
.course-icon-wrap {
  width: 52px; height: 52px; border-radius: 14px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-size: 24px;
  background: linear-gradient(135deg, var(--card-from), var(--card-to));
  color: #fff;
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}
.course-title-wrap { flex: 1; min-width: 0; }
.course-name {
  font-family: var(--font-heading);
  font-size: 16px; font-weight: 700; color: var(--text-primary);
  margin: 0 0 4px; line-height: 1.3;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.course-code {
  display: inline-block;
  font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;
  color: var(--text-muted);
  background: var(--bg-hover); padding: 2px 8px; border-radius: 5px;
}
.course-status-badge {
  flex-shrink: 0;
  padding: 4px 11px; border-radius: 20px; font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.5px;
}
.status-active { background: var(--success-light); color: var(--success-dark); }
.status-inactive { background: var(--danger-light); color: var(--danger-dark); }

/* Description */
.course-desc {
  padding: 0 24px 16px;
  font-size: 13px; color: var(--text-secondary); line-height: 1.6;
  display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2;
  -webkit-box-orient: vertical; overflow: hidden;
  flex-shrink: 0;
}

/* Stats row */
.course-stats-row {
  padding: 0 24px 16px;
  display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;
}
.cstat {
  background: var(--bg-hover); border-radius: 10px; padding: 10px 12px;
  text-align: center; border: 1px solid var(--border);
}
.cstat-val { font-size: 20px; font-weight: 800; line-height: 1; margin-bottom: 3px; }
.cstat-lbl { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); }
.cstat-val.blue  { color: var(--primary-light); }
.cstat-val.green { color: var(--success); }
.cstat-val.amber { color: var(--accent); }

/* Enrollment progress */
.enroll-progress-wrap {
  padding: 0 24px 16px;
}
.enroll-prog-label {
  display: flex; justify-content: space-between; align-items: center;
  font-size: 11px; color: var(--text-muted); font-weight: 600;
  margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;
}
.enroll-prog-track {
  height: 5px; background: var(--border); border-radius: 10px; overflow: hidden;
}
.enroll-prog-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--card-from), var(--card-to));
  border-radius: 10px;
  transition: width 0.6s cubic-bezier(0.4,0,0.2,1);
}

/* Metadata row */
.course-meta-row {
  padding: 12px 24px;
  background: var(--bg);
  border-top: 1px solid var(--border);
  display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
}
[data-theme="dark"] .course-meta-row { background: var(--bg-hover); }
.meta-pill {
  display: flex; align-items: center; gap: 5px;
  font-size: 12px; font-weight: 500; color: var(--text-secondary);
  background: var(--bg-card); border: 1px solid var(--border);
  padding: 4px 10px; border-radius: 20px;
}
.meta-pill i { font-size: 13px; }
.meta-pill.duration i { color: var(--primary-light); }
.meta-pill.fee     i { color: var(--success); }
.meta-pill.dept    i { color: var(--accent); }

/* Actions footer */
.course-card-footer {
  padding: 14px 20px;
  border-top: 1px solid var(--border);
  display: flex; gap: 8px; align-items: center;
}
.btn-manage {
  flex: 1; display: flex; align-items: center; justify-content: center;
  gap: 7px; padding: 9px 16px;
  background: linear-gradient(135deg, var(--card-from), var(--card-to));
  color: #fff; border-radius: 10px; font-size: 13px; font-weight: 600;
  border: none; cursor: pointer; text-decoration: none;
  transition: opacity 0.2s ease, transform 0.15s ease;
  box-shadow: 0 4px 12px rgba(37,99,235,0.25);
}
.btn-manage:hover { opacity: 0.88; transform: scale(0.98); color: #fff; }
.btn-action-icon {
  width: 38px; height: 38px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 16px; border: 1px solid var(--border);
  background: var(--bg-card); color: var(--text-secondary);
  text-decoration: none; transition: all 0.18s ease; cursor: pointer;
  flex-shrink: 0;
}
.btn-action-icon:hover { background: var(--bg-hover); color: var(--text-primary); border-color: var(--border-strong); }
.btn-action-icon.danger:hover { background: var(--danger-light); color: var(--danger); border-color: rgba(239,68,68,0.3); }

/* Empty state */
.empty-state-courses {
  grid-column: 1 / -1;
  background: var(--bg-card); border: 2px dashed var(--border);
  border-radius: 20px; padding: 80px 40px; text-align: center;
}
.empty-state-courses .empty-icon {
  width: 90px; height: 90px; margin: 0 auto 20px;
  background: var(--info-light); border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 36px; color: var(--primary-light);
}
.empty-state-courses h3 { font-size: 20px; font-weight: 700; margin: 0 0 8px; }
.empty-state-courses p  { font-size: 14px; color: var(--text-muted); margin: 0 0 20px; }

/* No-results message (inline filter) */
.no-results-msg {
  display: none;
  grid-column: 1 / -1;
  text-align: center; padding: 48px;
  color: var(--text-muted); font-size: 15px;
}
.no-results-msg i { display: block; font-size: 40px; margin-bottom: 10px; }

/* Animations */
@keyframes cardFadeIn {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}
.course-card { animation: cardFadeIn 0.35s ease both; }
.course-card:nth-child(2) { animation-delay: 0.05s; }
.course-card:nth-child(3) { animation-delay: 0.10s; }
.course-card:nth-child(4) { animation-delay: 0.15s; }
.course-card:nth-child(5) { animation-delay: 0.20s; }
.course-card:nth-child(6) { animation-delay: 0.25s; }

@media (max-width: 640px) {
  .courses-grid { grid-template-columns: 1fr; }
  .hero-stats { gap: 16px; }
  .hero-left h1 { font-size: 24px; }
  .courses-hero { padding: 24px 20px; }
  .filter-tabs { overflow-x: auto; }
  .course-search-input { width: 100%; }
  .courses-toolbar { flex-direction: column; align-items: stretch; }
  .toolbar-left, .toolbar-right { width: 100%; }
}
</style>

<!-- HERO BANNER -->
<div class="courses-hero">
  <div class="hero-content">
    <div class="hero-left">
      <h1><i class="ri-book-open-line" style="margin-right:10px; color: var(--accent);"></i>Course Catalogue</h1>
      <p>Manage, monitor and organize all academic courses from one place.</p>
    </div>
    <div class="hero-stats">
      <div class="hero-stat">
        <div class="hero-stat-val" id="heroTotal"><?= $totalCourses ?></div>
        <div class="hero-stat-lbl">Total</div>
      </div>
      <div class="hero-stat-divider"></div>
      <div class="hero-stat">
        <div class="hero-stat-val" style="color:#34D399;"><?= $activeCourses ?></div>
        <div class="hero-stat-lbl">Active</div>
      </div>
      <div class="hero-stat-divider"></div>
      <div class="hero-stat">
        <div class="hero-stat-val" style="color:#FBBF24;"><?= $totalStudents ?></div>
        <div class="hero-stat-lbl">Students</div>
      </div>
      <div class="hero-stat-divider"></div>
      <div class="hero-stat">
        <div class="hero-stat-val" style="color:#818CF8;"><?= $totalSubjects ?></div>
        <div class="hero-stat-lbl">Subjects</div>
      </div>
    </div>
  </div>
</div>

<!-- TOOLBAR -->
<div class="courses-toolbar">
  <div class="toolbar-left">
    <!-- Filter Tabs -->
    <div class="filter-tabs" role="tablist">
      <button class="filter-tab active" data-filter="all"   onclick="setFilter(this,'all')">All (<?= $totalCourses ?>)</button>
      <button class="filter-tab"        data-filter="active" onclick="setFilter(this,'active')">Active (<?= $activeCourses ?>)</button>
      <button class="filter-tab"        data-filter="inactive" onclick="setFilter(this,'inactive')">Inactive (<?= $totalCourses - $activeCourses ?>)</button>
    </div>
    <!-- Search -->
    <div class="course-search-wrap">
      <i class="ri-search-line"></i>
      <input type="text" id="courseSearch" class="course-search-input" placeholder="Search name or code…" oninput="applyFilters()">
    </div>
  </div>
  <div class="toolbar-right">
    <a href="<?= IMS_URL ?>/modules/courses/add.php" class="btn btn-primary" style="border-radius:10px; font-size:13px; font-weight:600;">
      <i class="ri-add-line"></i> New Course
    </a>
  </div>
</div>

<!-- COURSES GRID -->
<div class="courses-grid" id="coursesGrid">

  <?php if (empty($courses)): ?>
  <div class="empty-state-courses">
    <div class="empty-icon"><i class="ri-book-open-line"></i></div>
    <h3>No Courses Yet</h3>
    <p>Get started by creating your first academic course.</p>
    <a href="<?= IMS_URL ?>/modules/courses/add.php" class="btn btn-primary"><i class="ri-add-line"></i> Add First Course</a>
  </div>

  <?php else: ?>

  <?php foreach ($courses as $i => $c):
    $g   = $gradients[$i % count($gradients)];
    $pct = $c['max_students'] > 0 ? min(100, round($c['enrolled'] / $c['max_students'] * 100)) : 0;
    // Pick an icon based on index
    $icons = ['ri-graduation-cap-line','ri-computer-line','ri-flask-line','ri-palette-line','ri-calculator-line','ri-microscope-line','ri-code-s-slash-line','ri-book-2-line'];
    $icon  = $icons[$i % count($icons)];
  ?>
  <div class="course-card"
       style="--card-from:<?= $g['from'] ?>; --card-to:<?= $g['to'] ?>;"
       data-name="<?= strtolower(e($c['name'])) ?>"
       data-code="<?= strtolower(e($c['code'])) ?>"
       data-status="<?= $c['status'] ?>">

    <!-- Gradient top strip -->
    <div class="course-card-banner"></div>

    <!-- Top section: icon + name + status -->
    <div class="course-card-top">
      <div class="course-icon-wrap">
        <i class="<?= $icon ?>"></i>
      </div>
      <div class="course-title-wrap">
        <h3 class="course-name" title="<?= e($c['name']) ?>"><?= e($c['name']) ?></h3>
        <span class="course-code"><?= e($c['code']) ?></span>
      </div>
      <span class="course-status-badge <?= $c['status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
        <?= $c['status'] === 'active' ? '● Active' : '○ Inactive' ?>
      </span>
    </div>

    <!-- Description -->
    <p class="course-desc"><?= e($c['description'] ?? 'No description provided for this course.') ?></p>

    <!-- Stats -->
    <div class="course-stats-row">
      <div class="cstat">
        <div class="cstat-val blue"><?= $c['enrolled'] ?></div>
        <div class="cstat-lbl">Enrolled</div>
      </div>
      <div class="cstat">
        <div class="cstat-val green"><?= $c['subject_count'] ?></div>
        <div class="cstat-lbl">Subjects</div>
      </div>
      <div class="cstat">
        <div class="cstat-val amber"><?= $c['duration_months'] ?>m</div>
        <div class="cstat-lbl">Duration</div>
      </div>
    </div>

    <!-- Enrollment progress -->
    <?php if ($c['max_students'] > 0): ?>
    <div class="enroll-progress-wrap">
      <div class="enroll-prog-label">
        <span>Enrollment Capacity</span>
        <span><?= $c['enrolled'] ?> / <?= $c['max_students'] ?> &nbsp;(<?= $pct ?>%)</span>
      </div>
      <div class="enroll-prog-track">
        <div class="enroll-prog-fill" style="width: <?= $pct ?>%;"></div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Meta pills -->
    <div class="course-meta-row">
      <span class="meta-pill duration"><i class="ri-timer-2-line"></i> <?= $c['duration_months'] ?> Months</span>
      <span class="meta-pill fee"><i class="ri-money-dollar-circle-line"></i> $<?= number_format($c['fee'], 0) ?></span>
      <?php if ($c['dept_name'] ?? null): ?>
      <span class="meta-pill dept"><i class="ri-building-line"></i> <?= e($c['dept_name']) ?></span>
      <?php endif; ?>
    </div>

    <!-- Action buttons -->
    <div class="course-card-footer">
      <a href="<?= IMS_URL ?>/modules/subjects/index.php?course_id=<?= $c['id'] ?>" class="btn-manage">
        <i class="ri-book-2-line"></i> Manage Subjects
      </a>
      <a href="<?= IMS_URL ?>/modules/courses/edit.php?id=<?= $c['id'] ?>" class="btn-action-icon" title="Edit Course">
        <i class="ri-edit-line"></i>
      </a>
      <a href="<?= IMS_URL ?>/modules/courses/delete.php?id=<?= $c['id'] ?>" class="btn-action-icon danger" data-confirm-delete="course '<?= e($c['name']) ?>'" title="Delete Course">
        <i class="ri-delete-bin-line"></i>
      </a>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- No results (shown via JS) -->
  <div class="no-results-msg" id="noResults">
    <i class="ri-search-line"></i>
    No courses match your search.
  </div>

  <?php endif; ?>
</div>

<script>
let currentFilter = 'all';

function setFilter(btn, filter) {
  document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  currentFilter = filter;
  applyFilters();
}

function applyFilters() {
  const query  = (document.getElementById('courseSearch').value || '').toLowerCase();
  const cards  = document.querySelectorAll('.course-card');
  let visible  = 0;

  cards.forEach(card => {
    const name   = card.getAttribute('data-name') || '';
    const code   = card.getAttribute('data-code') || '';
    const status = card.getAttribute('data-status') || '';
    const matchSearch = !query || name.includes(query) || code.includes(query);
    const matchFilter = currentFilter === 'all' || status === currentFilter;

    if (matchSearch && matchFilter) {
      card.style.display = '';
      visible++;
    } else {
      card.style.display = 'none';
    }
  });

  const noMsg = document.getElementById('noResults');
  if (noMsg) noMsg.style.display = visible === 0 ? 'block' : 'none';
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
