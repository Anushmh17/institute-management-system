<?php
require_once __DIR__ . '/includes/auth.php';
require_login(IMS_URL . '/index.php');

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

$pdo = db();
$role = $_SESSION['role'];

// --- Stats (Admin) -----------------------------------------------------------
$stats = [];
if ($role === 'admin') {
    $stats['students']  = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn();
    $stats['teachers']  = (int)$pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
    $stats['courses']   = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE status='active'")->fetchColumn();
    $stats['enrollments'] = (int)$pdo->query("SELECT COUNT(*) FROM enrollments WHERE status='active'")->fetchColumn();

    // Classes this week
    $stats['classes_today'] = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE day_of_week = '" . date('l') . "' AND status='scheduled'")->fetchColumn();

    // Monthly revenue (course fees x enrollments this month)
    $stats['monthly_revenue'] = (float)($pdo->query(
        "SELECT COALESCE(SUM(c.fee),0) FROM enrollments e
         JOIN courses c ON c.id = e.course_id
         WHERE MONTH(e.enrolled_at) = MONTH(CURDATE())
           AND YEAR(e.enrolled_at) = YEAR(CURDATE())"
    )->fetchColumn());

    // Attendance overview last 7 days
    $attStmt = $pdo->query(
        "SELECT status, COUNT(*) AS cnt FROM attendance
         WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
         GROUP BY status"
    );
    $attData = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
    foreach ($attStmt->fetchAll() as $row) {
        $attData[$row['status']] = (int)$row['cnt'];
    }

    // Recent students
    $recentStudents = $pdo->query(
        "SELECT s.student_id, u.full_name, u.email, c.name AS course, s.admission_date, s.status
         FROM students s
         JOIN users u ON u.id = s.user_id
         LEFT JOIN courses c ON c.id = s.course_id
         ORDER BY s.created_at DESC LIMIT 5"
    )->fetchAll();

    // Recent activity
    $recentActivity = $pdo->query(
        "SELECT l.action, l.module, l.description, l.created_at, u.full_name
         FROM activity_logs l
         LEFT JOIN users u ON u.id = l.user_id
         ORDER BY l.created_at DESC LIMIT 8"
    )->fetchAll();

    // Monthly enrollments (last 6 months)
    $enrollChart = $pdo->query(
        "SELECT DATE_FORMAT(enrolled_at,'%b') AS month, COUNT(*) AS cnt
         FROM enrollments
         WHERE enrolled_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY MONTH(enrolled_at), DATE_FORMAT(enrolled_at,'%b')
         ORDER BY MIN(enrolled_at)"
    )->fetchAll();
}

// --- Teacher Dashboard -------------------------------------------------------
if ($role === 'teacher') {
    $teacherRow = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
    $teacherRow->execute([$_SESSION['user_id']]);
    $teacherId = (int)($teacherRow->fetchColumn() ?: 0);

    $stats['today_classes'] = (int)$pdo->prepare(
        "SELECT COUNT(*) FROM classes WHERE teacher_id=? AND day_of_week=? AND status='scheduled'"
    )->execute([$teacherId, date('l')]) ? $pdo->query(
        "SELECT COUNT(*) FROM classes WHERE teacher_id=$teacherId AND day_of_week='".date('l')."' AND status='scheduled'"
    )->fetchColumn() : 0;

    $stats['my_students'] = (int)$pdo->query(
        "SELECT COUNT(DISTINCT e.student_id)
         FROM enrollments e
         JOIN subjects sb ON sb.course_id = e.course_id
         WHERE sb.teacher_id = $teacherId"
    )->fetchColumn();

    $stats['my_subjects'] = (int)$pdo->query("SELECT COUNT(*) FROM subjects WHERE teacher_id=$teacherId")->fetchColumn();

    $recentStudents = $pdo->query(
        "SELECT DISTINCT s.student_id, u.full_name, c.name AS course, s.status
         FROM students s
         JOIN users u ON u.id = s.user_id
         JOIN enrollments e ON e.student_id = s.id
         JOIN subjects sb ON sb.course_id = e.course_id
         LEFT JOIN courses c ON c.id = e.course_id
         WHERE sb.teacher_id = $teacherId
         ORDER BY s.created_at DESC LIMIT 5"
    )->fetchAll();
}

// --- Student Dashboard -------------------------------------------------------
if ($role === 'student') {
    $studRow = $pdo->prepare("SELECT id, student_id, course_id FROM students WHERE user_id = ? LIMIT 1");
    $studRow->execute([$_SESSION['user_id']]);
    $student = $studRow->fetch();
    $studId  = $student ? (int)$student['id'] : 0;

    // Attendance %
    $attTotal = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE student_id=$studId")->fetchColumn();
    $attPres  = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE student_id=$studId AND status IN('present','late')")->fetchColumn();
    $stats['attendance_pct'] = $attTotal > 0 ? round($attPres / $attTotal * 100) : 0;

    // Latest marks
    $latestMarks = $pdo->query(
        "SELECT m.marks_obtained, m.max_marks, m.grade, m.exam_type, sb.name AS subject
         FROM marks m
         JOIN subjects sb ON sb.id = m.subject_id
         WHERE m.student_id = $studId
         ORDER BY m.created_at DESC LIMIT 5"
    )->fetchAll();

    // Upcoming classes
    $upcomingClasses = $pdo->query(
        "SELECT cl.day_of_week, cl.start_time, cl.end_time, cl.room, cl.type, sb.name AS subject
         FROM classes cl
         JOIN subjects sb ON sb.id = cl.subject_id
         JOIN enrollments e ON e.course_id = sb.course_id
         WHERE e.student_id = $studId AND cl.status='scheduled'
         ORDER BY FIELD(cl.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday')
         LIMIT 5"
    )->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">
      <?= $role === 'admin' ? '<i class="ri-shield-user-line"></i> Admin Dashboard' : ($role === 'teacher' ? '<i class="ri-book-read-line"></i> Teacher Dashboard' : '<i class="ri-graduation-cap-line"></i> Student Dashboard') ?>
    </h1>
    <p class="page-subtitle">
      <?= date('l, d F Y') ?> - Welcome, <?= e($_SESSION['full_name']) ?>
    </p>
  </div>
  <?php if ($role === 'admin'): ?>
  <div class="page-header-actions">
    <a href="<?= IMS_URL ?>/modules/students/add.php" class="btn btn-primary">
      <i class="ri-user-add-line"></i> Add Student
    </a>
    <a href="<?= IMS_URL ?>/modules/reports/index.php" class="btn btn-outline">
      <i class="ri-file-chart-line"></i> Reports
    </a>
  </div>
  <?php endif; ?>
</div>

<?php if ($role === 'admin'): ?>
<!-- --- ADMIN DASHBOARD ----------------------------------------- -->

<!-- Stats Grid -->
<div class="stats-grid">
  <div class="stat-card primary">
    <div class="stat-icon"><i class="ri-user-3-line"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= number_format($stats['students']) ?></div>
      <div class="stat-label">Total Students</div>
      <div class="stat-change up"><i class="ri-arrow-up-s-fill"></i><span>+12%</span><em>this month</em></div>
    </div>
  </div>
  <div class="stat-card success">
    <div class="stat-icon"><i class="ri-user-star-line"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= number_format($stats['teachers']) ?></div>
      <div class="stat-label">Teachers</div>
      <div class="stat-change up"><i class="ri-arrow-up-s-fill"></i><span>Active</span><em>faculty</em></div>
    </div>
  </div>
  <div class="stat-card accent">
    <div class="stat-icon"><i class="ri-book-open-line"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= number_format($stats['courses']) ?></div>
      <div class="stat-label">Active Courses</div>
      <div class="stat-change up"><i class="ri-arrow-up-s-fill"></i><span><?= $stats['enrollments'] ?></span><em>enrolled</em></div>
    </div>
  </div>
  <div class="stat-card warning">
    <div class="stat-icon"><i class="ri-money-dollar-circle-line"></i></div>
    <div class="stat-info">
      <div class="stat-value">$<?= number_format($stats['monthly_revenue'], 0) ?></div>
      <div class="stat-label">Monthly Revenue</div>
      <div class="stat-change up"><i class="ri-arrow-up-s-fill"></i><span>+8%</span><em>vs last month</em></div>
    </div>
  </div>
  <div class="stat-card info">
    <div class="stat-icon"><i class="ri-calendar-schedule-line"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $stats['classes_today'] ?></div>
      <div class="stat-label">Classes Today</div>
      <div class="stat-change up"><i class="ri-time-line"></i><span>Scheduled</span><em>today</em></div>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="quick-actions">
  <a href="<?= IMS_URL ?>/modules/students/add.php" class="quick-action-btn">
    <i class="ri-user-add-line"></i><span>Add Student</span>
  </a>
  <a href="<?= IMS_URL ?>/modules/teachers/add.php" class="quick-action-btn">
    <i class="ri-user-star-line"></i><span>Add Teacher</span>
  </a>
  <a href="<?= IMS_URL ?>/modules/courses/add.php" class="quick-action-btn">
    <i class="ri-book-add-line"></i><span>New Course</span>
  </a>
  <a href="<?= IMS_URL ?>/modules/attendance/index.php" class="quick-action-btn">
    <i class="ri-user-follow-line"></i><span>Attendance</span>
  </a>
  <a href="<?= IMS_URL ?>/modules/marks/index.php" class="quick-action-btn">
    <i class="ri-bar-chart-2-line"></i><span>Enter Marks</span>
  </a>
  <a href="<?= IMS_URL ?>/modules/reports/index.php" class="quick-action-btn">
    <i class="ri-file-chart-line"></i><span>Reports</span>
  </a>
</div>

<!-- Charts + Activity -->
<div class="dashboard-grid">
  <!-- Enrollment Chart -->
  <div class="card col-8">
    <div class="card-header">
      <h3 class="card-title"><i class="ri-line-chart-line"></i> Enrollment Trend (Last 6 Months)</h3>
    </div>
    <div class="card-body">
      <div class="chart-container chart-lg">
        <canvas id="enrollChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Attendance Donut -->
  <div class="card col-4">
    <div class="card-header">
      <h3 class="card-title"><i class="ri-pie-chart-line"></i> Attendance (7 days)</h3>
    </div>
    <div class="card-body">
      <div class="chart-container chart-donut">
        <canvas id="attChart"></canvas>
      </div>
      <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; justify-content:center; font-size:12px;">
        <span style="color:#10B981;"><i class="ri-checkbox-blank-circle-fill" style="font-size:8px;"></i> Present (<?= $attData['present'] ?>)</span>
        <span style="color:#EF4444;"><i class="ri-checkbox-blank-circle-fill" style="font-size:8px;"></i> Absent (<?= $attData['absent'] ?>)</span>
        <span style="color:#FBBF24;"><i class="ri-checkbox-blank-circle-fill" style="font-size:8px;"></i> Late (<?= $attData['late'] ?>)</span>
        <span style="color:#94A3B8;"><i class="ri-checkbox-blank-circle-fill" style="font-size:8px;"></i> Excused (<?= $attData['excused'] ?>)</span>
      </div>
    </div>
  </div>

  <!-- Recent Students -->
  <div class="card col-8">
    <div class="card-header">
      <h3 class="card-title"><i class="ri-user-3-line"></i> Recent Admissions</h3>
      <a href="<?= IMS_URL ?>/modules/students/index.php" class="btn btn-ghost btn-sm">View All <i class="ri-arrow-right-line"></i></a>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($recentStudents)): ?>
        <div class="empty-state" style="padding: 40px;">
          <i class="ri-user-3-line"></i>
          <h3>No Students Yet</h3>
          <p>Add your first student to get started.</p>
          <a href="<?= IMS_URL ?>/modules/students/add.php" class="btn btn-primary mt-4" style="margin-top:12px;">Add Student</a>
        </div>
      <?php else: ?>
      <table>
        <thead><tr>
          <th>Student ID</th><th>Name</th><th>Course</th><th>Admitted</th><th>Status</th>
        </tr></thead>
        <tbody>
          <?php foreach ($recentStudents as $s): ?>
          <tr>
            <td><code style="font-size:12px;"><?= e($s['student_id']) ?></code></td>
            <td><strong><?= e($s['full_name']) ?></strong></td>
            <td><?= e($s['course'] ?? '-') ?></td>
            <td><?= $s['admission_date'] ? date('d M Y', strtotime($s['admission_date'])) : '-' ?></td>
            <td><span class="badge badge-<?= $s['status'] === 'active' ? 'success' : 'muted' ?>"><?= ucfirst(e($s['status'])) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Activity Log -->
  <div class="card col-4">
    <div class="card-header">
      <h3 class="card-title"><i class="ri-history-line"></i> Recent Activity</h3>
    </div>
    <div class="card-body" style="padding:16px;">
      <?php if (empty($recentActivity)): ?>
        <p class="text-muted" style="font-size:13px; text-align:center; padding:20px 0;">No activity yet</p>
      <?php else: ?>
      <div class="activity-list">
        <?php foreach ($recentActivity as $log): ?>
        <div class="activity-item">
          <div class="activity-dot"><i class="ri-circle-fill" style="font-size:8px;"></i></div>
          <div class="activity-info">
            <p class="activity-text"><strong><?= e($log['full_name'] ?? 'System') ?></strong> - <?= e($log['description'] ?: $log['action']) ?></p>
            <p class="activity-time"><?= date('d M, g:i A', strtotime($log['created_at'])) ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
// Enrollment Chart
const enrollCtx = document.getElementById('enrollChart');
if (enrollCtx) {
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
        borderRadius: 6,
        fill: true,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(0,0,0,.06)' } },
        x: { grid: { display: false } }
      }
    }
  });
}

// Attendance Donut
const attCtx = document.getElementById('attChart');
if (attCtx) {
  new Chart(attCtx, {
    type: 'doughnut',
    data: {
      labels: ['Present', 'Absent', 'Late', 'Excused'],
      datasets: [{
        data: [<?= $attData['present'] ?>, <?= $attData['absent'] ?>, <?= $attData['late'] ?>, <?= $attData['excused'] ?>],
        backgroundColor: ['#10B981','#EF4444','#FBBF24','#94A3B8'],
        borderWidth: 0, hoverOffset: 6,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '72%',
      plugins: { legend: { display: false } }
    }
  });
}
</script>

<?php elseif ($role === 'teacher'): ?>
<!-- --- TEACHER DASHBOARD --------------------------------------- -->
<div class="stats-grid">
  <div class="stat-card primary">
    <div class="stat-icon"><i class="ri-calendar-schedule-line"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $stats['today_classes'] ?></div>
      <div class="stat-label">Today's Classes</div>
    </div>
  </div>
  <div class="stat-card success">
    <div class="stat-icon"><i class="ri-user-3-line"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $stats['my_students'] ?></div>
      <div class="stat-label">My Students</div>
    </div>
  </div>
  <div class="stat-card accent">
    <div class="stat-icon"><i class="ri-book-open-line"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $stats['my_subjects'] ?></div>
      <div class="stat-label">My Subjects</div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="ri-user-3-line"></i> My Students</h3>
    <a href="<?= IMS_URL ?>/modules/students/index.php" class="btn btn-ghost btn-sm">View All</a>
  </div>
  <div class="card-body" style="padding:0;">
    <?php if (empty($recentStudents)): ?>
      <div class="empty-state" style="padding:40px;"><i class="ri-user-3-line"></i><h3>No Students Assigned</h3></div>
    <?php else: ?>
    <table>
      <thead><tr><th>ID</th><th>Name</th><th>Course</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($recentStudents as $s): ?>
        <tr>
          <td><code style="font-size:12px;"><?= e($s['student_id']) ?></code></td>
          <td><?= e($s['full_name']) ?></td>
          <td><?= e($s['course'] ?? '--') ?></td>
          <td><span class="badge badge-<?= $s['status'] === 'active' ? 'success' : 'muted' ?>"><?= ucfirst(e($s['status'])) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($role === 'student'): ?>
<!-- --- STUDENT DASHBOARD --------------------------------------- -->
<div class="stats-grid">
  <div class="stat-card <?= $stats['attendance_pct'] >= 75 ? 'success' : 'danger' ?>">
    <div class="stat-icon"><i class="ri-user-follow-line"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $stats['attendance_pct'] ?>%</div>
      <div class="stat-label">Attendance</div>
      <div class="progress" style="margin-top:8px;">
        <div class="progress-bar <?= $stats['attendance_pct'] >= 75 ? 'success' : 'danger' ?>"
             style="width:<?= $stats['attendance_pct'] ?>%;"></div>
      </div>
    </div>
  </div>
</div>

<div class="dashboard-grid">
  <!-- Latest Marks -->
  <div class="card col-6">
    <div class="card-header">
      <h3 class="card-title"><i class="ri-bar-chart-2-line"></i> Latest Marks</h3>
      <a href="<?= IMS_URL ?>/modules/marks/my.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($latestMarks)): ?>
        <div class="empty-state" style="padding:30px;"><i class="ri-bar-chart-2-line"></i><h3>No Marks Yet</h3></div>
      <?php else: ?>
      <table>
        <thead><tr><th>Subject</th><th>Type</th><th>Marks</th><th>Grade</th></tr></thead>
        <tbody>
          <?php foreach ($latestMarks as $m): ?>
          <tr>
            <td><?= e($m['subject']) ?></td>
            <td><span class="badge badge-muted"><?= ucfirst(e($m['exam_type'])) ?></span></td>
            <td><?= e($m['marks_obtained']) ?>/<?= e($m['max_marks']) ?></td>
            <td><span class="badge badge-<?= $m['grade'] === 'F' ? 'danger' : 'success' ?>"><?= e($m['grade'] ?? '-') ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Upcoming Classes -->
  <div class="card col-6">
    <div class="card-header">
      <h3 class="card-title"><i class="ri-calendar-schedule-line"></i> Upcoming Classes</h3>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($upcomingClasses)): ?>
        <div class="empty-state" style="padding:30px;"><i class="ri-calendar-line"></i><h3>No Classes Scheduled</h3></div>
      <?php else: ?>
      <table>
        <thead><tr><th>Subject</th><th>Day</th><th>Time</th><th>Room</th></tr></thead>
        <tbody>
          <?php foreach ($upcomingClasses as $cl): ?>
          <tr>
            <td><?= e($cl['subject']) ?></td>
            <td><?= e($cl['day_of_week']) ?></td>
            <td><?= date('g:i A', strtotime($cl['start_time'])) ?> - <?= date('g:i A', strtotime($cl['end_time'])) ?></td>
            <td><?= e($cl['room'] ?? '-') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
