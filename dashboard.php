<?php
require_once __DIR__ . '/includes/auth.php';
require_login(IMS_URL . '/index.php');

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

$pdo  = db();
$role = $_SESSION['role'];
$name = $_SESSION['full_name'] ?? 'User';
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

// ── ADMIN ──────────────────────────────────────────────────────────────────────
$stats = [];
if ($role === 'admin') {
    $stats['students']     = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn();
    $stats['teachers']     = (int)$pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
    $stats['courses']      = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE status='active'")->fetchColumn();
    $stats['enrollments']  = (int)$pdo->query("SELECT COUNT(*) FROM enrollments WHERE status='active'")->fetchColumn();
    $stats['classes_today']= (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE day_of_week='".date('l')."' AND status='scheduled'")->fetchColumn();
    $stats['monthly_revenue'] = (float)$pdo->query(
        "SELECT COALESCE(SUM(c.fee),0) FROM enrollments e
         JOIN courses c ON c.id=e.course_id
         WHERE MONTH(e.enrolled_at)=MONTH(CURDATE()) AND YEAR(e.enrolled_at)=YEAR(CURDATE())"
    )->fetchColumn();

    $attStmt = $pdo->query("SELECT status, COUNT(*) AS cnt FROM attendance WHERE date>=DATE_SUB(CURDATE(),INTERVAL 7 DAY) GROUP BY status");
    $attData = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
    foreach ($attStmt->fetchAll() as $row) $attData[$row['status']] = (int)$row['cnt'];

    $recentStudents = $pdo->query(
        "SELECT s.student_id, u.full_name, u.email, u.profile_photo, c.name AS course, s.admission_date, s.status
         FROM students s JOIN users u ON u.id=s.user_id LEFT JOIN courses c ON c.id=s.course_id
         ORDER BY s.created_at DESC LIMIT 6"
    )->fetchAll();

    $recentActivity = $pdo->query(
        "SELECT l.action, l.module, l.description, l.created_at, u.full_name
         FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id
         ORDER BY l.created_at DESC LIMIT 8"
    )->fetchAll();

    $enrollChart = $pdo->query(
        "SELECT DATE_FORMAT(enrolled_at,'%b') AS month, COUNT(*) AS cnt
         FROM enrollments WHERE enrolled_at>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH)
         GROUP BY MONTH(enrolled_at), DATE_FORMAT(enrolled_at,'%b') ORDER BY MIN(enrolled_at)"
    )->fetchAll();
}

// ── TEACHER ────────────────────────────────────────────────────────────────────
if ($role === 'teacher') {
    $teacherRow = $pdo->prepare("SELECT id FROM teachers WHERE user_id=? LIMIT 1");
    $teacherRow->execute([$_SESSION['user_id']]);
    $teacherId = (int)($teacherRow->fetchColumn() ?: 0);

    $stats['today_classes'] = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE teacher_id=$teacherId AND day_of_week='".date('l')."' AND status='scheduled'")->fetchColumn();
    $stats['my_students']   = (int)$pdo->query("SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN subjects sb ON sb.course_id=e.course_id WHERE sb.teacher_id=$teacherId")->fetchColumn();
    $stats['my_subjects']   = (int)$pdo->query("SELECT COUNT(*) FROM subjects WHERE teacher_id=$teacherId")->fetchColumn();

    $recentStudents = $pdo->query(
        "SELECT DISTINCT s.student_id, u.full_name, u.profile_photo, c.name AS course, s.status
         FROM students s JOIN users u ON u.id=s.user_id
         JOIN enrollments e ON e.student_id=s.id
         JOIN subjects sb ON sb.course_id=e.course_id
         LEFT JOIN courses c ON c.id=e.course_id
         WHERE sb.teacher_id=$teacherId ORDER BY s.created_at DESC LIMIT 6"
    )->fetchAll();
}

// ── STUDENT ────────────────────────────────────────────────────────────────────
if ($role === 'student') {
    $studRow = $pdo->prepare("SELECT id, student_id, course_id FROM students WHERE user_id=? LIMIT 1");
    $studRow->execute([$_SESSION['user_id']]);
    $student = $studRow->fetch();
    $studId  = $student ? (int)$student['id'] : 0;

    $attTotal = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE student_id=$studId")->fetchColumn();
    $attPres  = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE student_id=$studId AND status IN('present','late')")->fetchColumn();
    $stats['attendance_pct'] = $attTotal > 0 ? round($attPres / $attTotal * 100) : 0;
    $stats['total_classes']  = $attTotal;
    $stats['subjects_count'] = (int)$pdo->query("SELECT COUNT(DISTINCT subject_id) FROM marks WHERE student_id=$studId")->fetchColumn();

    $latestMarks = $pdo->query(
        "SELECT m.marks_obtained, m.max_marks, m.grade, m.exam_type, sb.name AS subject
         FROM marks m JOIN subjects sb ON sb.id=m.subject_id
         WHERE m.student_id=$studId ORDER BY m.created_at DESC LIMIT 5"
    )->fetchAll();

    $upcomingClasses = $pdo->query(
        "SELECT cl.day_of_week, cl.start_time, cl.end_time, cl.room, cl.type, sb.name AS subject
         FROM classes cl JOIN subjects sb ON sb.id=cl.subject_id
         JOIN enrollments e ON e.course_id=sb.course_id
         WHERE e.student_id=$studId AND cl.status='scheduled'
         ORDER BY FIELD(cl.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday') LIMIT 5"
    )->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>

<style>
/* ══════════════════════════════════════════
   DASHBOARD PREMIUM STYLES
══════════════════════════════════════════ */

/* ── Welcome Hero ─────────────────────── */
.db-hero {
  background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 50%, #1E293B 100%);
  border-radius: 22px; padding: 34px 40px; margin-bottom: 28px;
  position: relative; overflow: hidden;
}
.db-hero::before {
  content: ''; position: absolute;
  width: 500px; height: 500px;
  background: radial-gradient(circle, rgba(37,99,235,.2) 0%, transparent 65%);
  top: -160px; right: -80px; pointer-events: none;
}
.db-hero::after {
  content: ''; position: absolute;
  width: 300px; height: 300px;
  background: radial-gradient(circle, rgba(245,158,11,.12) 0%, transparent 70%);
  bottom: -100px; left: 8%; pointer-events: none;
}
/* Animated blob decorations */
.db-hero-blob {
  position: absolute; border-radius: 50%; filter: blur(60px);
  opacity: 0.18; pointer-events: none; animation: blobFloat 8s ease-in-out infinite;
}
.db-hero-blob.b1 { width:200px; height:200px; background:#6366F1; bottom:-60px; right:15%; animation-delay:0s; }
.db-hero-blob.b2 { width:150px; height:150px; background:#10B981; bottom:10px;  right:5%;  animation-delay:3s; }
@keyframes blobFloat { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-18px)} }

.db-hero-inner { position: relative; z-index:1; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:24px; }
.db-hero-left {}
.db-greeting  { font-size:13px; font-weight:600; color:rgba(255,255,255,.5); text-transform:uppercase; letter-spacing:1px; margin-bottom:6px; }
.db-hero-left h1 { font-family:var(--font-heading); font-size:32px; font-weight:800; color:#fff; margin:0 0 6px; letter-spacing:-.5px; }
.db-hero-left p  { font-size:14px; color:rgba(255,255,255,.55); margin:0; display:flex; align-items:center; gap:8px; }
.db-today-badge { display:inline-flex; align-items:center; gap:6px; padding:5px 14px; border-radius:20px; border:1px solid rgba(255,255,255,.15); background:rgba(255,255,255,.07); font-size:12px; color:rgba(255,255,255,.7); font-weight:600; }
.db-hero-actions { display:flex; gap:10px; flex-wrap:wrap; position:relative; z-index:1; }
.db-hero-btn { display:inline-flex; align-items:center; gap:7px; padding:10px 20px; border-radius:12px; font-size:13px; font-weight:700; text-decoration:none; transition:all .2s; border:none; cursor:pointer; }
.db-hero-btn.primary { background:linear-gradient(135deg,#2563EB,#1D4ED8); color:#fff; box-shadow:0 4px 16px rgba(37,99,235,.4); }
.db-hero-btn.primary:hover { opacity:.9; transform:translateY(-1px); color:#fff; }
.db-hero-btn.ghost  { background:rgba(255,255,255,.1); color:#fff; border:1px solid rgba(255,255,255,.2); }
.db-hero-btn.ghost:hover  { background:rgba(255,255,255,.18); color:#fff; }

/* ── KPI Cards ────────────────────────── */
.db-kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
  gap: 18px;
  margin-bottom: 26px;
}
.db-kpi-card {
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: 18px; padding: 22px;
  display: flex; align-items: center; gap: 16px;
  box-shadow: var(--shadow-sm); position: relative; overflow: hidden;
  transition: transform .2s ease, box-shadow .2s ease;
}
.db-kpi-card:hover { transform:translateY(-3px); box-shadow:var(--shadow-md); }
.db-kpi-card::before {
  content:''; position:absolute; top:0; left:0;
  width:4px; height:100%;
  background: var(--kpi-color); border-radius:4px 0 0 4px;
}
.db-kpi-icon {
  width:52px; height:52px; border-radius:14px;
  display:flex; align-items:center; justify-content:center; font-size:24px;
  flex-shrink:0; background:var(--kpi-bg); color:var(--kpi-color);
}
.db-kpi-val { font-family:var(--font-heading); font-size:26px; font-weight:800; color:var(--text-primary); line-height:1; }
.db-kpi-lbl { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); margin-top:3px; }
.db-kpi-sub { font-size:11px; font-weight:600; margin-top:5px; display:flex; align-items:center; gap:3px; }
.db-kpi-sub.up   { color:var(--success); }
.db-kpi-sub.info { color:var(--info); }
.db-kpi-sub.muted{ color:var(--text-muted); }

/* ── Quick Actions ────────────────────── */
.db-quicklinks {
  display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 26px;
}
.db-quicklink {
  display:inline-flex; align-items:center; gap:8px;
  padding:9px 18px; border-radius:12px;
  background:var(--bg-card); border:1.5px solid var(--border);
  color:var(--text-secondary); font-size:13px; font-weight:600;
  text-decoration:none; transition:all .18s; box-shadow:var(--shadow-xs);
}
.db-quicklink i { font-size:16px; }
.db-quicklink:hover { background:var(--primary-light); color:#fff; border-color:var(--primary-light); transform:translateY(-2px); box-shadow:0 6px 16px rgba(37,99,235,.25); }

/* ── Dashboard 2-col grid ─────────────── */
.db-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
.db-grid.three { grid-template-columns:2fr 1fr; }
.db-grid.full  { grid-template-columns:1fr; }

/* ── Section Card ─────────────────────── */
.db-card {
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:18px; box-shadow:var(--shadow-sm); overflow:hidden;
}
.db-card-head {
  display:flex; align-items:center; justify-content:space-between;
  padding:16px 20px; border-bottom:1px solid var(--border);
  background:var(--bg);
}
[data-theme="dark"] .db-card-head { background:var(--bg-hover); }
.db-card-title { font-size:14px; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:8px; }
.db-card-title i { font-size:16px; color:var(--primary-light); }
.db-view-all { font-size:12px; font-weight:600; color:var(--primary-light); text-decoration:none; display:flex; align-items:center; gap:4px; transition:opacity .2s; }
.db-view-all:hover { opacity:.75; color:var(--primary-light); }

/* ── Recent Students Table ────────────── */
table.db-table { width:100%; border-collapse:collapse; }
table.db-table thead th { padding:10px 16px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.7px; color:var(--text-muted); text-align:left; border-bottom:1px solid var(--border); }
table.db-table tbody tr { border-bottom:1px solid var(--border); transition:background .12s; }
table.db-table tbody tr:last-child { border-bottom:none; }
table.db-table tbody tr:hover { background:var(--bg-hover); }
table.db-table tbody td { padding:11px 16px; font-size:13px; vertical-align:middle; }
.db-stu-init { width:32px; height:32px; border-radius:50%; background:linear-gradient(135deg,#2563EB,#7C3AED); color:#fff; font-weight:700; font-size:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.db-stu-img  { width:32px; height:32px; border-radius:50%; object-fit:cover; border:2px solid var(--border); }
.db-stu-name { font-weight:600; font-size:13px; }
.db-stu-email{ font-size:11px; color:var(--text-muted); }
.db-sid-code { font-size:10px; font-weight:700; padding:2px 7px; border-radius:5px; background:var(--bg-hover); border:1px solid var(--border); color:var(--text-muted); font-family:monospace; }
.status-pill { display:inline-flex; align-items:center; gap:4px; padding:3px 9px; border-radius:20px; font-size:10px; font-weight:700; text-transform:uppercase; }
.status-pill.active    { background:var(--success-light); color:var(--success-dark); }
.status-pill.inactive  { background:var(--bg-hover);      color:var(--text-muted); }
.status-pill.graduated { background:var(--info-light);    color:var(--primary); }
.status-pill.dropped   { background:var(--danger-light);  color:var(--danger-dark); }
.status-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }

/* ── Activity Feed ────────────────────── */
.db-activity-list { padding:8px 0; }
.db-activity-item { display:flex; gap:12px; padding:10px 20px; border-bottom:1px solid var(--border); transition:background .12s; }
.db-activity-item:last-child { border-bottom:none; }
.db-activity-item:hover { background:var(--bg-hover); }
.db-activity-dot { width:8px; height:8px; border-radius:50%; background:var(--primary-light); flex-shrink:0; margin-top:5px; box-shadow:0 0 0 3px rgba(37,99,235,.15); }
.db-activity-text { font-size:13px; color:var(--text-primary); font-weight:500; line-height:1.4; }
.db-activity-text strong { color:var(--primary-light); }
.db-activity-time { font-size:11px; color:var(--text-muted); margin-top:2px; display:flex; align-items:center; gap:4px; }
.db-activity-time i { font-size:11px; }

/* ── Chart card ───────────────────────── */
.db-chart-body { padding:20px; }
.chart-container { position:relative; }
.chart-lg     { height:220px; }
.chart-donut  { height:180px; }

/* ── Att Legend ───────────────────────── */
.att-legend { display:flex; gap:10px; flex-wrap:wrap; justify-content:center; padding:12px 16px 16px; }
.att-legend-item { display:flex; align-items:center; gap:5px; font-size:12px; font-weight:600; color:var(--text-secondary); }
.att-legend-dot  { width:10px; height:10px; border-radius:50%; }

/* ── Attendance Progress (student) ────── */
.att-ring-wrap { display:flex; flex-direction:column; align-items:center; padding:24px; }
.att-pct-label { font-family:var(--font-heading); font-size:38px; font-weight:800; color:var(--text-primary); line-height:1; }
.att-pct-sub   { font-size:12px; color:var(--text-muted); font-weight:500; margin-top:4px; }
.att-prog-track { height:8px; background:var(--border); border-radius:10px; overflow:hidden; margin:14px 0 6px; }
.att-prog-fill  { height:100%; border-radius:10px; transition:width .6s cubic-bezier(.4,0,.2,1); }

/* ── Grade pill ───────────────────────── */
.grade-pill { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:800; }
.grade-A  { background:var(--success-light); color:var(--success-dark); }
.grade-Ap { background:var(--success-light); color:var(--success-dark); }
.grade-Bp { background:var(--info-light);    color:var(--primary); }
.grade-B  { background:var(--info-light);    color:var(--primary); }
.grade-C  { background:var(--warning-light); color:var(--warning-dark); }
.grade-D  { background:var(--warning-light); color:var(--warning-dark); }
.grade-F  { background:var(--danger-light);  color:var(--danger-dark); }

/* ── Day badge (schedule) ─────────────── */
.day-badge { display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:700; background:var(--info-light); color:var(--primary); }

/* ── Empty ────────────────────────────── */
.db-empty { padding:48px 20px; text-align:center; }
.db-empty i { font-size:40px; color:var(--border-strong); display:block; margin:0 auto 12px; }
.db-empty h3 { font-size:16px; font-weight:700; margin:0 0 5px; }
.db-empty p  { font-size:13px; color:var(--text-muted); margin:0; }

/* ── Responsive ───────────────────────── */
@media (max-width:900px) {
  .db-grid.three { grid-template-columns:1fr; }
  .db-grid { grid-template-columns:1fr; }
  .db-hero h1 { font-size:24px; }
  .db-hero { padding:24px 20px; }
  .db-kpi-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width:480px) {
  .db-kpi-grid { grid-template-columns: 1fr; }
}
</style>

<!-- ═══════════════════════════════════════════
     WELCOME HERO
═══════════════════════════════════════════ -->
<div class="db-hero">
  <div class="db-hero-blob b1"></div>
  <div class="db-hero-blob b2"></div>
  <div class="db-hero-inner">
    <div class="db-hero-left">
      <div class="db-greeting"><?= $greeting ?> 👋</div>
      <h1><?= e($name) ?></h1>
      <p>
        <span class="db-today-badge"><i class="ri-calendar-line"></i><?= date('l, d F Y') ?></span>
        &nbsp;
        <span style="color:rgba(255,255,255,.4);">·</span>
        &nbsp;
        <span style="color:rgba(255,255,255,.5); font-size:13px;">
          <?= ucfirst($role) ?> Portal
        </span>
      </p>
    </div>
    <div class="db-hero-actions">
      <?php if ($role === 'admin'): ?>
      <a href="<?= IMS_URL ?>/modules/students/add.php" class="db-hero-btn primary"><i class="ri-user-add-line"></i> Add Student</a>
      <a href="<?= IMS_URL ?>/modules/reports/index.php" class="db-hero-btn ghost"><i class="ri-file-chart-line"></i> Reports</a>
      <?php elseif ($role === 'teacher'): ?>
      <a href="<?= IMS_URL ?>/modules/attendance/index.php" class="db-hero-btn primary"><i class="ri-user-follow-line"></i> Take Attendance</a>
      <a href="<?= IMS_URL ?>/modules/marks/index.php" class="db-hero-btn ghost"><i class="ri-bar-chart-2-line"></i> Enter Marks</a>
      <?php else: ?>
      <a href="<?= IMS_URL ?>/modules/classes/index.php" class="db-hero-btn primary"><i class="ri-calendar-schedule-line"></i> My Timetable</a>
      <a href="<?= IMS_URL ?>/modules/marks/my.php" class="db-hero-btn ghost"><i class="ri-bar-chart-2-line"></i> My Marks</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($role === 'admin'): ?>
<!-- ═══════════════════════════════════════════
     ADMIN DASHBOARD
═══════════════════════════════════════════ -->

<!-- KPI Cards -->
<div class="db-kpi-grid">
  <!-- Students -->
  <div class="db-kpi-card" style="--kpi-color:#2563EB; --kpi-bg:#DBEAFE;">
    <div class="db-kpi-icon"><i class="ri-user-3-line"></i></div>
    <div>
      <div class="db-kpi-val"><?= number_format($stats['students']) ?></div>
      <div class="db-kpi-lbl">Active Students</div>
      <div class="db-kpi-sub up"><i class="ri-arrow-up-s-fill"></i><?= $stats['enrollments'] ?> enrolled</div>
    </div>
  </div>
  <!-- Teachers -->
  <div class="db-kpi-card" style="--kpi-color:#059669; --kpi-bg:#D1FAE5;">
    <div class="db-kpi-icon"><i class="ri-user-star-line"></i></div>
    <div>
      <div class="db-kpi-val"><?= number_format($stats['teachers']) ?></div>
      <div class="db-kpi-lbl">Teachers</div>
      <div class="db-kpi-sub info"><i class="ri-shield-check-line"></i>Active faculty</div>
    </div>
  </div>
  <!-- Courses -->
  <div class="db-kpi-card" style="--kpi-color:#D97706; --kpi-bg:#FEF3C7;">
    <div class="db-kpi-icon"><i class="ri-book-open-line"></i></div>
    <div>
      <div class="db-kpi-val"><?= number_format($stats['courses']) ?></div>
      <div class="db-kpi-lbl">Active Courses</div>
      <div class="db-kpi-sub muted"><i class="ri-draft-line"></i><?= $stats['enrollments'] ?> enrollments</div>
    </div>
  </div>
  <!-- Revenue -->
  <div class="db-kpi-card" style="--kpi-color:#7C3AED; --kpi-bg:#F3E8FF;">
    <div class="db-kpi-icon"><i class="ri-money-dollar-circle-line"></i></div>
    <div>
      <div class="db-kpi-val">$<?= number_format($stats['monthly_revenue'], 0) ?></div>
      <div class="db-kpi-lbl">Monthly Revenue</div>
      <div class="db-kpi-sub up"><i class="ri-arrow-up-s-fill"></i>This month</div>
    </div>
  </div>
  <!-- Classes Today -->
  <div class="db-kpi-card" style="--kpi-color:#0284C7; --kpi-bg:#E0F2FE;">
    <div class="db-kpi-icon"><i class="ri-calendar-schedule-line"></i></div>
    <div>
      <div class="db-kpi-val"><?= $stats['classes_today'] ?></div>
      <div class="db-kpi-lbl">Classes Today</div>
      <div class="db-kpi-sub info"><i class="ri-time-line"></i><?= date('l') ?></div>
    </div>
  </div>
</div>

<!-- Quick Links -->
<div class="db-quicklinks">
  <a href="<?= IMS_URL ?>/modules/students/add.php"    class="db-quicklink"><i class="ri-user-add-line"></i> Add Student</a>
  <a href="<?= IMS_URL ?>/modules/teachers/add.php"    class="db-quicklink"><i class="ri-user-star-line"></i> Add Teacher</a>
  <a href="<?= IMS_URL ?>/modules/courses/add.php"     class="db-quicklink"><i class="ri-book-add-line"></i> New Course</a>
  <a href="<?= IMS_URL ?>/modules/classes/add.php"     class="db-quicklink"><i class="ri-calendar-event-line"></i> Schedule Class</a>
  <a href="<?= IMS_URL ?>/modules/attendance/index.php"class="db-quicklink"><i class="ri-user-follow-line"></i> Attendance</a>
  <a href="<?= IMS_URL ?>/modules/marks/index.php"     class="db-quicklink"><i class="ri-bar-chart-2-line"></i> Enter Marks</a>
  <a href="<?= IMS_URL ?>/modules/reports/index.php"   class="db-quicklink"><i class="ri-file-chart-line"></i> Reports</a>
</div>

<!-- Charts + Activity -->
<div class="db-grid three" style="margin-bottom:20px;">
  <!-- Enrollment Chart -->
  <div class="db-card">
    <div class="db-card-head">
      <div class="db-card-title"><i class="ri-line-chart-line"></i> Enrollment Trend (6 Months)</div>
    </div>
    <div class="db-chart-body">
      <div class="chart-container chart-lg"><canvas id="enrollChart"></canvas></div>
    </div>
  </div>
  <!-- Attendance Donut -->
  <div class="db-card">
    <div class="db-card-head">
      <div class="db-card-title"><i class="ri-pie-chart-line"></i> Attendance (7 days)</div>
    </div>
    <div class="db-chart-body" style="padding-bottom:0;">
      <div class="chart-container chart-donut"><canvas id="attChart"></canvas></div>
    </div>
    <div class="att-legend">
      <span class="att-legend-item"><span class="att-legend-dot" style="background:#10B981;"></span>Present (<?= $attData['present'] ?>)</span>
      <span class="att-legend-item"><span class="att-legend-dot" style="background:#EF4444;"></span>Absent (<?= $attData['absent'] ?>)</span>
      <span class="att-legend-item"><span class="att-legend-dot" style="background:#FBBF24;"></span>Late (<?= $attData['late'] ?>)</span>
      <span class="att-legend-item"><span class="att-legend-dot" style="background:#94A3B8;"></span>Excused (<?= $attData['excused'] ?>)</span>
    </div>
  </div>
</div>

<!-- Recent + Activity -->
<div class="db-grid three">
  <!-- Recent Admissions -->
  <div class="db-card">
    <div class="db-card-head">
      <div class="db-card-title"><i class="ri-user-3-line"></i> Recent Admissions</div>
      <a href="<?= IMS_URL ?>/modules/students/index.php" class="db-view-all">View All <i class="ri-arrow-right-s-line"></i></a>
    </div>
    <?php if (empty($recentStudents)): ?>
    <div class="db-empty"><i class="ri-user-3-line"></i><h3>No Students Yet</h3><p>Add your first student.</p></div>
    <?php else: ?>
    <div style="overflow-x:auto;">
      <table class="db-table">
        <thead><tr><th>Student</th><th>Course</th><th>Date</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($recentStudents as $s): ?>
          <tr>
            <td>
              <div style="display:flex; align-items:center; gap:10px;">
                <?php if (!empty($s['profile_photo'])): ?>
                  <img src="<?= IMS_URL ?>/uploads/<?= e($s['profile_photo']) ?>" class="db-stu-img" alt="">
                <?php else: ?>
                  <div class="db-stu-init"><?= strtoupper(substr($s['full_name'],0,1)) ?></div>
                <?php endif; ?>
                <div>
                  <div class="db-stu-name"><?= e($s['full_name']) ?></div>
                  <div style="font-size:10px; font-family:monospace; color:var(--text-muted);"><?= e($s['student_id']) ?></div>
                </div>
              </div>
            </td>
            <td style="font-size:12px; color:var(--text-secondary);"><?= e($s['course'] ?? '—') ?></td>
            <td style="font-size:12px; color:var(--text-muted);">
              <?= $s['admission_date'] ? date('d M Y', strtotime($s['admission_date'])) : '—' ?>
            </td>
            <td>
              <?php $st = $s['status'] ?? 'inactive'; ?>
              <span class="status-pill <?= $st ?>"><span class="status-dot"></span><?= ucfirst($st) ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Activity Feed -->
  <div class="db-card">
    <div class="db-card-head">
      <div class="db-card-title"><i class="ri-history-line"></i> Recent Activity</div>
    </div>
    <?php if (empty($recentActivity)): ?>
    <div class="db-empty"><i class="ri-history-line"></i><h3>No Activity Yet</h3></div>
    <?php else: ?>
    <div class="db-activity-list">
      <?php foreach ($recentActivity as $log): ?>
      <div class="db-activity-item">
        <div class="db-activity-dot"></div>
        <div style="flex:1; min-width:0;">
          <div class="db-activity-text">
            <strong><?= e($log['full_name'] ?? 'System') ?></strong>
            — <?= e($log['description'] ?: $log['action']) ?>
          </div>
          <div class="db-activity-time">
            <i class="ri-time-line"></i><?= date('d M, g:i A', strtotime($log['created_at'])) ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
// ── Enrollment Bar Chart ─────────────────
const enrollCtx = document.getElementById('enrollChart');
if (enrollCtx) {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  const gridColor = isDark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.06)';
  const months = <?= json_encode(array_column($enrollChart, 'month')) ?>;
  const counts = <?= json_encode(array_map('intval', array_column($enrollChart, 'cnt'))) ?>;
  new Chart(enrollCtx, {
    type: 'bar',
    data: {
      labels: months.length ? months : ['No Data'],
      datasets: [{
        label: 'New Enrollments',
        data: counts.length ? counts : [0],
        backgroundColor: 'rgba(37,99,235,0.15)',
        borderColor: '#2563EB',
        borderWidth: 2,
        borderRadius: 8,
        borderSkipped: false,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: { callbacks: {
        label: ctx => ` ${ctx.parsed.y} enrollments`
      }}},
      scales: {
        y: { beginAtZero: true, ticks: { precision:0, color:'#94A3B8', font:{size:11} }, grid: { color: gridColor }, border:{display:false} },
        x: { ticks: { color:'#94A3B8', font:{size:11} }, grid: { display:false }, border:{display:false} }
      }
    }
  });
}

// ── Attendance Donut ─────────────────────
const attCtx = document.getElementById('attChart');
if (attCtx) {
  new Chart(attCtx, {
    type: 'doughnut',
    data: {
      labels: ['Present', 'Absent', 'Late', 'Excused'],
      datasets: [{
        data: [<?= $attData['present'] ?>, <?= $attData['absent'] ?>, <?= $attData['late'] ?>, <?= $attData['excused'] ?>],
        backgroundColor: ['#10B981','#EF4444','#FBBF24','#94A3B8'],
        borderWidth: 0, hoverOffset: 8,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '74%',
      plugins: { legend: { display:false } }
    }
  });
}
</script>

<?php elseif ($role === 'teacher'): ?>
<!-- ═══════════════════════════════════════════
     TEACHER DASHBOARD
═══════════════════════════════════════════ -->
<div class="db-kpi-grid">
  <div class="db-kpi-card" style="--kpi-color:#2563EB; --kpi-bg:#DBEAFE;">
    <div class="db-kpi-icon"><i class="ri-calendar-schedule-line"></i></div>
    <div>
      <div class="db-kpi-val"><?= $stats['today_classes'] ?></div>
      <div class="db-kpi-lbl">Today's Classes</div>
      <div class="db-kpi-sub info"><i class="ri-time-line"></i><?= date('l') ?></div>
    </div>
  </div>
  <div class="db-kpi-card" style="--kpi-color:#059669; --kpi-bg:#D1FAE5;">
    <div class="db-kpi-icon"><i class="ri-user-3-line"></i></div>
    <div>
      <div class="db-kpi-val"><?= $stats['my_students'] ?></div>
      <div class="db-kpi-lbl">My Students</div>
      <div class="db-kpi-sub muted"><i class="ri-group-line"></i>Across all subjects</div>
    </div>
  </div>
  <div class="db-kpi-card" style="--kpi-color:#D97706; --kpi-bg:#FEF3C7;">
    <div class="db-kpi-icon"><i class="ri-book-open-line"></i></div>
    <div>
      <div class="db-kpi-val"><?= $stats['my_subjects'] ?></div>
      <div class="db-kpi-lbl">My Subjects</div>
      <div class="db-kpi-sub muted"><i class="ri-draft-line"></i>Assigned subjects</div>
    </div>
  </div>
</div>

<div class="db-quicklinks" style="margin-bottom:24px;">
  <a href="<?= IMS_URL ?>/modules/attendance/index.php" class="db-quicklink"><i class="ri-user-follow-line"></i> Mark Attendance</a>
  <a href="<?= IMS_URL ?>/modules/marks/index.php"     class="db-quicklink"><i class="ri-bar-chart-2-line"></i> Enter Marks</a>
  <a href="<?= IMS_URL ?>/modules/classes/index.php"   class="db-quicklink"><i class="ri-calendar-schedule-line"></i> View Timetable</a>
</div>

<div class="db-card">
  <div class="db-card-head">
    <div class="db-card-title"><i class="ri-group-line"></i> My Students</div>
    <a href="<?= IMS_URL ?>/modules/students/index.php" class="db-view-all">View All <i class="ri-arrow-right-s-line"></i></a>
  </div>
  <?php if (empty($recentStudents)): ?>
  <div class="db-empty"><i class="ri-user-3-line"></i><h3>No Students Assigned</h3><p>Students enrolled in your courses will appear here.</p></div>
  <?php else: ?>
  <div style="overflow-x:auto;">
    <table class="db-table">
      <thead><tr><th>Student</th><th>ID</th><th>Course</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($recentStudents as $s): ?>
        <tr>
          <td>
            <div style="display:flex; align-items:center; gap:10px;">
              <?php if (!empty($s['profile_photo'])): ?>
                <img src="<?= IMS_URL ?>/uploads/<?= e($s['profile_photo']) ?>" class="db-stu-img" alt="">
              <?php else: ?>
                <div class="db-stu-init"><?= strtoupper(substr($s['full_name'],0,1)) ?></div>
              <?php endif; ?>
              <span class="db-stu-name"><?= e($s['full_name']) ?></span>
            </div>
          </td>
          <td><span class="db-sid-code"><?= e($s['student_id']) ?></span></td>
          <td style="font-size:12px; color:var(--text-secondary);"><?= e($s['course'] ?? '—') ?></td>
          <td>
            <?php $st = $s['status'] ?? 'inactive'; ?>
            <span class="status-pill <?= $st ?>"><span class="status-dot"></span><?= ucfirst($st) ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php elseif ($role === 'student'): ?>
<!-- ═══════════════════════════════════════════
     STUDENT DASHBOARD
═══════════════════════════════════════════ -->
<div class="db-kpi-grid">
  <?php $attColor = $stats['attendance_pct'] >= 75 ? '#059669' : '#EF4444'; $attBg = $stats['attendance_pct'] >= 75 ? '#D1FAE5' : '#FEE2E2'; ?>
  <div class="db-kpi-card" style="--kpi-color:<?= $attColor ?>; --kpi-bg:<?= $attBg ?>;">
    <div class="db-kpi-icon"><i class="ri-user-follow-line"></i></div>
    <div style="flex:1;">
      <div class="db-kpi-val"><?= $stats['attendance_pct'] ?>%</div>
      <div class="db-kpi-lbl">Attendance</div>
      <div class="att-prog-track" style="margin-top:8px;">
        <div class="att-prog-fill" style="width:<?= $stats['attendance_pct'] ?>%; background:<?= $attColor ?>;"></div>
      </div>
    </div>
  </div>
  <div class="db-kpi-card" style="--kpi-color:#2563EB; --kpi-bg:#DBEAFE;">
    <div class="db-kpi-icon"><i class="ri-calendar-check-line"></i></div>
    <div>
      <div class="db-kpi-val"><?= $stats['total_classes'] ?></div>
      <div class="db-kpi-lbl">Classes Logged</div>
      <div class="db-kpi-sub info"><i class="ri-history-line"></i>All time</div>
    </div>
  </div>
  <div class="db-kpi-card" style="--kpi-color:#7C3AED; --kpi-bg:#F3E8FF;">
    <div class="db-kpi-icon"><i class="ri-draft-line"></i></div>
    <div>
      <div class="db-kpi-val"><?= $stats['subjects_count'] ?></div>
      <div class="db-kpi-lbl">Subjects Assessed</div>
      <div class="db-kpi-sub muted"><i class="ri-bar-chart-2-line"></i>With marks</div>
    </div>
  </div>
</div>

<div class="db-quicklinks" style="margin-bottom:24px;">
  <a href="<?= IMS_URL ?>/modules/classes/index.php"    class="db-quicklink"><i class="ri-calendar-schedule-line"></i> Timetable</a>
  <a href="<?= IMS_URL ?>/modules/marks/my.php"         class="db-quicklink"><i class="ri-bar-chart-2-line"></i> My Marks</a>
  <a href="<?= IMS_URL ?>/modules/attendance/my.php"    class="db-quicklink"><i class="ri-user-follow-line"></i> My Attendance</a>
</div>

<div class="db-grid">
  <!-- Latest Marks -->
  <div class="db-card">
    <div class="db-card-head">
      <div class="db-card-title"><i class="ri-bar-chart-2-line"></i> Latest Marks</div>
      <a href="<?= IMS_URL ?>/modules/marks/my.php" class="db-view-all">View All <i class="ri-arrow-right-s-line"></i></a>
    </div>
    <?php if (empty($latestMarks)): ?>
    <div class="db-empty"><i class="ri-bar-chart-2-line"></i><h3>No Marks Yet</h3><p>Marks will appear here once entered by your teachers.</p></div>
    <?php else: ?>
    <div style="overflow-x:auto;">
      <table class="db-table">
        <thead><tr><th>Subject</th><th>Type</th><th>Marks</th><th>Grade</th></tr></thead>
        <tbody>
          <?php foreach ($latestMarks as $m): ?>
          <tr>
            <td style="font-weight:600;"><?= e($m['subject']) ?></td>
            <td><span style="font-size:11px; font-weight:600; color:var(--text-muted);"><?= ucfirst(e($m['exam_type'])) ?></span></td>
            <td style="font-weight:700;"><?= e($m['marks_obtained']) ?><span style="color:var(--text-muted); font-weight:400;">/<?= e($m['max_marks']) ?></span></td>
            <td>
              <?php $gk = str_replace('+','p',$m['grade'] ?? 'F'); ?>
              <span class="grade-pill grade-<?= $gk ?>"><?= e($m['grade'] ?? '—') ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Upcoming Classes -->
  <div class="db-card">
    <div class="db-card-head">
      <div class="db-card-title"><i class="ri-calendar-schedule-line"></i> My Schedule</div>
      <a href="<?= IMS_URL ?>/modules/classes/index.php" class="db-view-all">Full Timetable <i class="ri-arrow-right-s-line"></i></a>
    </div>
    <?php if (empty($upcomingClasses)): ?>
    <div class="db-empty"><i class="ri-calendar-line"></i><h3>No Classes Scheduled</h3><p>Your scheduled classes will appear here.</p></div>
    <?php else: ?>
    <div style="overflow-x:auto;">
      <table class="db-table">
        <thead><tr><th>Subject</th><th>Day</th><th>Time</th><th>Room</th></tr></thead>
        <tbody>
          <?php foreach ($upcomingClasses as $cl): ?>
          <tr>
            <td style="font-weight:600; font-size:13px;"><?= e($cl['subject']) ?></td>
            <td><span class="day-badge"><i class="ri-calendar-event-line"></i><?= substr($cl['day_of_week'],0,3) ?></span></td>
            <td style="font-size:12px; color:var(--text-secondary);">
              <?= date('g:i', strtotime($cl['start_time'])) ?> – <?= date('g:i A', strtotime($cl['end_time'])) ?>
            </td>
            <td style="font-size:12px; color:var(--text-muted);"><?= e($cl['room'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
