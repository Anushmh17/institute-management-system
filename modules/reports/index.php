<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin', 'teacher', 'student']);

$pageTitle  = 'Reports';
$activePage = 'reports';
$pdo = db();

$studentId = (int)($_GET['student_id'] ?? 0);
$search    = sanitize($_GET['search'] ?? '');

// Security: Force students to only see their own report
if (is_student()) {
    $s_check = $pdo->prepare("SELECT id FROM students WHERE user_id=? LIMIT 1");
    $s_check->execute([$_SESSION['user_id']]);
    $studentId = (int)($s_check->fetchColumn() ?: 0);
    $search = '';
}

// Student list fetching
$students = [];
if (!is_student() && !$studentId) {
    $where  = '';
    $params = [];
    if (!empty($search)) {
        $where  = 'AND (u.full_name LIKE ? OR s.student_id LIKE ?)';
        $params = ["%$search%", "%$search%"];
    }
    $stmt = $pdo->prepare(
        "SELECT s.id, s.student_id AS sid, u.full_name, c.name AS course_name, u.profile_photo,
                (SELECT COUNT(*) FROM marks WHERE student_id = s.id) as has_marks
         FROM students s JOIN users u ON u.id=s.user_id LEFT JOIN courses c ON c.id=s.course_id
         WHERE s.status='active' $where ORDER BY u.full_name LIMIT 100"
    );
    $stmt->execute($params);
    $students = $stmt->fetchAll();
}

// If specific student - build report card data
$reportData = null;
if ($studentId) {
    $sStmt = $pdo->prepare(
        "SELECT s.*, u.full_name, u.email, u.phone, u.profile_photo,
                c.name AS course_name, c.duration_months
         FROM students s JOIN users u ON u.id=s.user_id
         LEFT JOIN courses c ON c.id=s.course_id
         WHERE s.id=? LIMIT 1"
    );
    $sStmt->execute([$studentId]);
    $reportData = $sStmt->fetch();

    if ($reportData) {
        // Marks
        $marksRows = $pdo->query(
            "SELECT sb.name AS subject, m.exam_type, m.marks_obtained, m.max_marks, m.grade
             FROM marks m JOIN subjects sb ON sb.id=m.subject_id
             WHERE m.student_id=$studentId
             ORDER BY sb.name, m.exam_type"
        )->fetchAll();

        // Attendance
        $attTotal = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE student_id=$studentId")->fetchColumn();
        $attPres  = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE student_id=$studentId AND status IN('present','late')")->fetchColumn();
        $attPct   = $attTotal > 0 ? round($attPres/$attTotal*100) : 0;

        $reportData['marks']      = $marksRows;
        $reportData['att_total']  = $attTotal;
        $reportData['att_present']= $attPres;
        $reportData['att_pct']    = $attPct;

        // Calculate overall
        $totalObt = array_sum(array_column($marksRows, 'marks_obtained'));
        $totalMax = array_sum(array_column($marksRows, 'max_marks'));
        $overallPct = $totalMax > 0 ? round($totalObt/$totalMax*100, 2) : 0;
        $overallGrade = calculate_grade($overallPct);
        $reportData['overall_pct']   = $overallPct;
        $reportData['overall_grade'] = $overallGrade;
    }
}

$instituteName = get_setting('institute_name', 'ExcelIMS');
$instituteAddress = get_setting('institute_address', '');

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Reports & Report Cards</h1>
    <p class="page-subtitle">Generate and view academic report cards</p>
  </div>
</div>

<?php if (!is_student()): ?>
<!-- Search -->
<div class="card" style="margin-bottom:24px;">
  <div class="card-body">
    <form method="GET" class="d-flex gap-3 align-center" style="flex-wrap:wrap;">
      <div class="search-control" style="flex:1;max-width:400px;">
        <i class="ri-search-line"></i>
        <input type="text" name="search" class="form-control" placeholder="Search student by name or ID..." value="<?= e($search) ?>">
      </div>
      <button type="submit" class="btn btn-primary"><i class="ri-search-line"></i> Search</button>
    </form>
    <?php if (!$studentId): ?>
    <div style="margin-top:24px;">
      <h3 style="font-size:16px; font-weight:700; margin-bottom:16px; color:var(--text-secondary);">
        <?= $search ? 'Search Results' : 'All Students' ?>
      </h3>
      <div style="display:flex; flex-direction:column; gap:12px;">
        <?php foreach ($students as $s): ?>
        <div class="d-flex align-center gap-3"
             style="padding:14px 18px; border:1px solid var(--border); border-radius:12px; background:var(--bg-card); transition:all .2s; box-shadow:var(--shadow-sm);">
          <?php if (!empty($s['profile_photo'])): ?>
            <img src="<?= IMS_URL ?>/uploads/<?= e($s['profile_photo']) ?>" style="width:44px;height:44px;border-radius:50%;object-fit:cover;" alt="">
          <?php else: ?>
            <div class="avatar-initials" style="width:44px;height:44px;font-size:14px;"><?= strtoupper(substr($s['full_name'],0,1)) ?></div>
          <?php endif; ?>
          <div style="flex:1;">
            <div class="font-semi" style="font-size:15px;"><?= e($s['full_name']) ?></div>
            <div style="font-size:12px;color:var(--text-muted);">
              <?= e($s['sid']) ?> | <?= e($s['course_name']??'--') ?>
              <?php if($s['has_marks'] > 0): ?>
                <span class="badge badge-success" style="margin-left:8px; font-size:10px;">Marks Ready</span>
              <?php else: ?>
                <span class="badge badge-muted" style="margin-left:8px; font-size:10px;">No Marks</span>
              <?php endif; ?>
            </div>
          </div>
          <a href="?student_id=<?= $s['id'] ?>" class="btn btn-primary btn-sm">
            <i class="ri-file-chart-line"></i> View Report
          </a>
        </div>
        <?php endforeach; ?>
        <?php if (empty($students)): ?>
          <div class="empty-state" style="padding:40px;">
            <i class="ri-user-search-line"></i>
            <h3>No Students Found</h3>
            <p>No active students match your criteria.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($reportData): ?>
<!-- REPORT CARD -->
<div class="card" id="reportCard">
  <!-- Header -->
  <div style="background:linear-gradient(135deg,var(--primary) 0%,var(--primary-light) 70%,var(--accent) 100%); padding:28px 32px; color:#fff; border-radius:16px 16px 0 0;">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
      <div style="display:flex;align-items:center;gap:16px;">
        <div style="width:56px;height:56px;background:rgba(255,255,255,.2);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:28px;">
          <i class="ri-graduation-cap-fill"></i>
        </div>
        <div>
          <h2 style="font-family:var(--font-heading);font-size:22px;font-weight:800;margin-bottom:4px;"><?= e($instituteName) ?></h2>
          <p style="font-size:13px;opacity:.8;"><?= e($instituteAddress) ?></p>
        </div>
      </div>
      <div style="text-align:right;">
        <div style="font-size:13px;opacity:.8;">Academic Year</div>
        <div style="font-family:var(--font-heading);font-size:16px;font-weight:700;"><?= get_setting('academic_year','2025-2026') ?></div>
        <div style="margin-top:8px;background:rgba(255,255,255,.2);padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;">OFFICIAL REPORT CARD</div>
      </div>
    </div>
  </div>

  <div class="card-body">
    <!-- Student Info -->
    <div style="display:flex;gap:24px;align-items:flex-start;padding-bottom:24px;border-bottom:1px solid var(--border);margin-bottom:24px;flex-wrap:wrap;">
      <?php if (!empty($reportData['profile_photo'])): ?>
        <img src="<?= IMS_URL ?>/uploads/<?= e($reportData['profile_photo']) ?>"
             style="width:90px;height:90px;border-radius:50%;border:3px solid var(--primary-light);object-fit:cover;flex-shrink:0;" alt="">
      <?php else: ?>
        <div class="avatar-initials" style="width:90px;height:90px;font-size:28px;border:3px solid var(--primary-light);flex-shrink:0;">
          <?= strtoupper(substr($reportData['full_name'],0,1)) ?>
        </div>
      <?php endif; ?>
      <div style="flex:1;">
        <h3 style="font-family:var(--font-heading);font-size:20px;font-weight:700;"><?= e($reportData['full_name']) ?></h3>
        <div class="detail-grid" style="margin-top:12px;">
          <div class="detail-item"><label>Student ID</label><span><?= e($reportData['student_id']) ?></span></div>
          <div class="detail-item"><label>Course</label><span><?= e($reportData['course_name']??'--') ?></span></div>
          <div class="detail-item"><label>Admission Date</label><span><?= $reportData['admission_date'] ? date('d M Y',strtotime($reportData['admission_date'])) : '--' ?></span></div>
          <div class="detail-item"><label>Batch Year</label><span><?= e($reportData['batch_year']??'--') ?></span></div>
          <div class="detail-item"><label>Email</label><span><?= e($reportData['email']) ?></span></div>
          <div class="detail-item"><label>Phone</label><span><?= e($reportData['phone']??'--') ?></span></div>
        </div>
      </div>
      <!-- Overall Badge -->
      <div style="text-align:center;flex-shrink:0;">
        <div style="width:90px;height:90px;border-radius:50%;background:<?= $reportData['overall_grade']==='F'?'var(--danger-light)':($reportData['overall_grade'][0]==='A'?'var(--success-light)':'var(--warning-light)') ?>;display:flex;flex-direction:column;align-items:center;justify-content:center;">
          <div style="font-family:var(--font-heading);font-size:28px;font-weight:800;color:<?= $reportData['overall_grade']==='F'?'var(--danger)':($reportData['overall_grade'][0]==='A'?'var(--success)':'var(--warning-dark)') ?>;"><?= e($reportData['overall_grade']) ?></div>
        </div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:6px;">Overall Grade</div>
        <div style="font-size:16px;font-weight:700;"><?= $reportData['overall_pct'] ?>%</div>
      </div>
    </div>

    <!-- Marks Table -->
    <h4 style="font-family:var(--font-heading);font-size:16px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
      <i class="ri-bar-chart-2-line" style="color:var(--primary-light);"></i> Academic Performance
    </h4>
    <?php if (empty($reportData['marks'])): ?>
    <div class="alert alert-warning"><i class="ri-information-line"></i> No marks recorded yet for this student.</div>
    <?php else: ?>
    <table style="margin-bottom:24px;">
      <thead><tr>
        <th>Subject</th><th>Exam Type</th><th>Marks</th><th>Max</th><th>Percentage</th><th>Grade</th>
      </tr></thead>
      <tbody>
        <?php foreach ($reportData['marks'] as $m): ?>
        <?php $p = $m['max_marks']>0 ? round($m['marks_obtained']/$m['max_marks']*100,1) : 0; ?>
        <tr>
          <td><?= e($m['subject']) ?></td>
          <td><span class="badge badge-muted"><?= ucfirst(e($m['exam_type'])) ?></span></td>
          <td><?= e($m['marks_obtained']) ?></td>
          <td><?= e($m['max_marks']) ?></td>
          <td>
            <div class="d-flex align-center gap-2">
              <div class="progress" style="flex:1;"><div class="progress-bar <?= $p>=60?'success':($p>=40?'warning':'danger') ?>" style="width:<?= $p ?>%;"></div></div>
              <span style="font-size:12px;min-width:40px;"><?= $p ?>%</span>
            </div>
          </td>
          <td>
            <?php $gc = match($m['grade']??'') { 'A+','A'=>'success','B+','B'=>'primary','C','D'=>'warning','F'=>'danger',default=>'muted' }; ?>
            <span class="badge badge-<?= $gc ?>"><?= e($m['grade']??'--') ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <!-- Attendance Summary -->
    <h4 style="font-family:var(--font-heading);font-size:16px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
      <i class="ri-user-follow-line" style="color:var(--primary-light);"></i> Attendance Summary
    </h4>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:32px;">
      <div style="background:var(--bg);padding:16px;border-radius:12px;text-align:center;">
        <div style="font-size:24px;font-weight:700;color:var(--primary-light);"><?= $reportData['att_total'] ?></div>
        <div style="font-size:12px;color:var(--text-muted);">Total Classes</div>
      </div>
      <div style="background:var(--success-light);padding:16px;border-radius:12px;text-align:center;">
        <div style="font-size:24px;font-weight:700;color:var(--success);"><?= $reportData['att_present'] ?></div>
        <div style="font-size:12px;color:var(--success-dark);">Present</div>
      </div>
      <div style="background:<?= $reportData['att_pct']>=75?'var(--success-light)':'var(--danger-light)' ?>;padding:16px;border-radius:12px;text-align:center;">
        <div style="font-size:24px;font-weight:700;color:<?= $reportData['att_pct']>=75?'var(--success)':'var(--danger)' ?>;"><?= $reportData['att_pct'] ?>%</div>
        <div style="font-size:12px;">Attendance Rate</div>
      </div>
    </div>

    <!-- Signature Area -->
    <div style="display:flex;justify-content:space-between;margin-top:32px;padding-top:24px;border-top:2px dashed var(--border);flex-wrap:wrap;gap:32px;">
      <?php foreach (['Class Teacher','Head of Department','Principal'] as $sig): ?>
      <div style="text-align:center;min-width:150px;">
        <div style="height:48px;border-bottom:2px solid var(--border-strong);margin-bottom:8px;"></div>
        <div style="font-size:12px;font-weight:600;color:var(--text-muted);"><?= $sig ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Actions -->
    <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:24px;">
      <button onclick="window.print()" class="btn btn-outline">
        <i class="ri-printer-line"></i> Print
      </button>
      <a href="<?= IMS_URL ?>/modules/reports/index.php" class="btn btn-primary">
        <i class="ri-arrow-left-line"></i> Back to Reports
      </a>
    </div>
  </div>
</div>

<style>
@media print {
  .sidebar, .topbar, .page-header, .card:not(#reportCard), form, .btn { display:none !important; }
  .main-content { margin-left:0 !important; }
  .content-area { padding:0 !important; }
  #reportCard { box-shadow:none; border:none; }
}
</style>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
