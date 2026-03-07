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
                c.name AS course_name, c.duration_months,
                s.gender, s.date_of_birth, s.blood_group, s.guardian_name, s.guardian_phone, s.address
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
<!-- ================= SCREEN REPORT CARD (Original Layout) ================= -->
<div class="card" id="screenReportCard">
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
      <button onclick="
        var prev = document.title;
        document.title = '<?= addslashes(e($reportData['full_name'])) ?> - Report Card';
        window.print();
        document.title = prev;
      " class="btn btn-outline">
        <i class="ri-printer-line"></i> Print / Save PDF
      </button>
      <a href="<?= IMS_URL ?>/modules/reports/index.php" class="btn btn-primary">
        <i class="ri-arrow-left-line"></i> Back to Reports
      </a>
    </div>
  </div>
</div>

<!-- ================= PRINT ONLY REPORT CARD (Premium Institutional Transcript) ================= -->
<div id="printReportCard">

  <!-- ── TOP ACCENT BAR ── -->
  <div style="height:6px; background:linear-gradient(90deg, #1e3a8a 0%, #3b63c9 100%);"></div>

  <!-- ── 1. HEADER ── -->
  <div style="display:flex; justify-content:space-between; align-items:center; padding:20px 44px; border-bottom:1px solid #dde3ec;">
    <div style="display:flex; align-items:center; gap:14px;">
      <div style="width:50px; height:50px; background:#1e3a8a; color:#fff; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0;">
        <i class="ri-government-fill"></i>
      </div>
      <div>
        <div style="font-size:19px; font-weight:800; color:#0f172a; line-height:1.1; letter-spacing:-0.3px;"><?= e($instituteName) ?></div>
        <div style="font-size:11px; color:#64748b; margin-top:3px;"><?= e($instituteAddress) ?></div>
      </div>
    </div>
    <div style="text-align:right; border-left:1px solid #e2e8f0; padding-left:20px;">
      <div style="font-size:12px; font-weight:800; color:#1e3a8a; text-transform:uppercase; letter-spacing:1px;">Academic Report Card</div>
      <div style="font-size:11px; color:#64748b; margin-top:3px;">Year: <strong style="color:#0f172a;"><?= get_setting('academic_year','2025-2026') ?></strong></div>
      <div style="font-size:11px; color:#64748b; margin-top:2px;">Issued: <strong style="color:#0f172a;"><?= date('d M Y') ?></strong></div>
    </div>
  </div>

  <!-- ── 2. STUDENT INFO + GRADE CARD ── -->
  <div style="display:flex; align-items:stretch; border-bottom:1px solid #dde3ec;">

    <!-- Student Info -->
    <div style="flex:1; padding:18px 44px;">
      <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
        <div style="width:3px; height:12px; background:#1e3a8a; border-radius:2px;"></div>
        <div style="font-size:9.5px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:1px;">Student Particulars</div>
      </div>
      <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:12px 24px;">
        <div>
          <div style="font-size:8.5px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:0.7px; margin-bottom:2px;">Full Name</div>
          <div style="font-size:13px; font-weight:700; color:#0f172a;"><?= e($reportData['full_name']) ?></div>
        </div>
        <div>
          <div style="font-size:8.5px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:0.7px; margin-bottom:2px;">Student ID</div>
          <div style="font-size:12px; font-weight:600; color:#0f172a; font-family:'Courier New',monospace;"><?= e($reportData['student_id']) ?></div>
        </div>
        <div>
          <div style="font-size:8.5px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:0.7px; margin-bottom:2px;">Programme</div>
          <div style="font-size:12px; font-weight:600; color:#1e3a8a;"><?= e($reportData['course_name']??'--') ?></div>
        </div>
        <div>
          <div style="font-size:8.5px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:0.7px; margin-bottom:2px;">Date of Birth</div>
          <div style="font-size:12px; font-weight:500; color:#334155;"><?= $reportData['date_of_birth'] ? date('d M Y', strtotime($reportData['date_of_birth'])) : '--' ?></div>
        </div>
        <div>
          <div style="font-size:8.5px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:0.7px; margin-bottom:2px;">Gender / Blood</div>
          <div style="font-size:12px; font-weight:500; color:#334155;"><?= ucfirst(e($reportData['gender']??'--')) ?> / <?= e($reportData['blood_group']??'--') ?></div>
        </div>
        <div>
          <div style="font-size:8.5px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:0.7px; margin-bottom:2px;">Batch / Year</div>
          <div style="font-size:11px; color:#475569;"><?= e($reportData['batch_year']??'--') ?> (<?= $reportData['admission_date'] ? date('Y', strtotime($reportData['admission_date'])) : '--' ?>)</div>
        </div>
        <div>
          <div style="font-size:8.5px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:0.7px; margin-bottom:2px;">Guardian Name</div>
          <div style="font-size:11px; color:#475569;"><?= e($reportData['guardian_name']??'--') ?></div>
        </div>
        <div>
          <div style="font-size:8.5px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:0.7px; margin-bottom:2px;">Phone / Guardian</div>
          <div style="font-size:11px; color:#475569;"><?= e($reportData['phone']??'--') ?> / <?= e($reportData['guardian_phone']??'--') ?></div>
        </div>
        <div>
          <div style="font-size:8.5px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:0.7px; margin-bottom:2px;">Residential Address</div>
          <div style="font-size:10px; color:#64748b; line-height:1.2;"><?= e($reportData['address']??'--') ?></div>
        </div>
      </div>
    </div>

    <!-- Grade Card & Verification -->
    <div style="width:160px; flex-shrink:0; border-left:1px solid #dde3ec; background:#f8fafc; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:16px;">
      <div style="text-align:center; margin-bottom:20px;">
        <div style="font-size:8.5px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">Overall Result</div>
        <div style="width:70px; height:70px; border-radius:50%; border:2.5px solid <?= $reportData['overall_grade']==='F' ? '#ef4444' : '#1e3a8a' ?>; background:#fff; display:flex; align-items:center; justify-content:center; margin:0 auto; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
          <div style="font-size:32px; font-weight:900; line-height:1; color:<?= $reportData['overall_grade']==='F' ? '#ef4444' : '#1e3a8a' ?>;"><?= e($reportData['overall_grade']) ?></div>
        </div>
        <div style="margin-top:8px; font-size:16px; font-weight:800; color:#0f172a;"><?= $reportData['overall_pct'] ?>%</div>
        <div style="font-size:8px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Cumulative</div>
      </div>

      <div style="text-align:center; padding-top:16px; border-top:1px solid #e2e8f0; width:100%;">
        <div style="font-size:8px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">Verification</div>
        <!-- Mock QR Code Placeholder -->
        <div style="width:64px; height:64px; background:#fff; border:1px solid #e2e8f0; margin:0 auto; display:flex; padding:4px;">
           <div style="border:2px solid #000; width:100%; height:100%; display:grid; grid-template-columns:repeat(4,1fr); grid-template-rows:repeat(4,1fr);">
              <?php for($qi=0;$qi<16;$qi++): ?>
                <div style="background:<?= (rand(0,10)>4)?'#000':'#fff' ?>;"></div>
              <?php endfor; ?>
           </div>
        </div>
        <div style="font-size:7.5px; color:#94a3b8; margin-top:4px; font-family:monospace;">STU-VALID: <?= substr(md5($reportData['student_id']),0,8) ?></div>
      </div>
    </div>
  </div>

  <!-- ── 3. TRANSCRIPT TABLE ── -->
  <div class="prc-table" style="padding:16px 44px 0;">
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
      <div style="width:3px; height:12px; background:#1e3a8a; border-radius:2px;"></div>
      <div style="font-size:9.5px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:1px;">Transcript Summary</div>
    </div>
    <table style="width:100%; border-collapse:collapse; font-family:'Inter','Segoe UI',sans-serif;">
      <thead>
        <tr style="background:#f1f5f9;">
          <th style="padding:11px 14px; text-align:left; font-size:10px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.6px; border-bottom:2px solid #cbd5e1;">Subject</th>
          <th style="padding:11px 14px; text-align:left; font-size:10px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.6px; border-bottom:2px solid #cbd5e1;">Assessment Type</th>
          <th style="padding:11px 14px; text-align:center; font-size:10px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.6px; border-bottom:2px solid #cbd5e1;">Score</th>
          <th style="padding:11px 14px; text-align:center; font-size:10px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.6px; border-bottom:2px solid #cbd5e1;">Max</th>
          <th style="padding:11px 14px; text-align:center; font-size:10px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.6px; border-bottom:2px solid #cbd5e1;">%</th>
          <th style="padding:11px 14px; text-align:center; font-size:10px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.6px; border-bottom:2px solid #cbd5e1;">Grade</th>
          <th style="padding:11px 14px; text-align:center; font-size:10px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.6px; border-bottom:2px solid #cbd5e1;">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($reportData['marks'] as $i => $m):
          $p = $m['max_marks'] > 0 ? round($m['marks_obtained'] / $m['max_marks'] * 100, 1) : 0;
          $gradeColor = $m['grade']==='F' ? '#ef4444' : '#1e3a8a';
          $isPass = $p >= 40;
        ?>
        <tr style="background:<?= $i % 2 === 0 ? '#ffffff' : '#f8fafc' ?>; border-bottom:1px solid #e8edf4;">
          <td style="padding:10px 14px; font-size:12px; font-weight:600; color:#0f172a;"><?= e($m['subject']) ?></td>
          <td style="padding:10px 14px; font-size:11px; color:#64748b;"><?= ucfirst(e($m['exam_type'])) ?></td>
          <td style="padding:10px 14px; font-size:12px; font-weight:700; color:#0f172a; text-align:center;"><?= e($m['marks_obtained']) ?></td>
          <td style="padding:10px 14px; font-size:11px; color:#94a3b8; text-align:center;"><?= e($m['max_marks']) ?></td>
          <td style="padding:10px 14px; font-size:11px; font-weight:500; color:#475569; text-align:center;"><?= $p ?>%</td>
          <td style="padding:10px 14px; text-align:center;">
            <span style="display:inline-block; padding:3px 10px; border-radius:20px; background:<?= $m['grade']==='F'?'#fef2f2':'#eff4ff' ?>; font-size:11px; font-weight:800; color:<?= $gradeColor ?>;">
              <?= e($m['grade']??'--') ?>
            </span>
          </td>
          <td style="padding:10px 14px; text-align:center;">
             <span style="font-size:10px; font-weight:700; color:<?= $isPass ? '#16a34a' : '#dc2626' ?>; text-transform:uppercase;"><?= $isPass ? 'PASS' : 'FAIL' ?></span>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <?php
      // Compute performance totals for summary bar
      $totalObtained = array_sum(array_column($reportData['marks'], 'marks_obtained'));
      $totalMax      = array_sum(array_column($reportData['marks'], 'max_marks'));
      $totalSubjects = count($reportData['marks']);
      $avgPct = $totalMax > 0 ? round($totalObtained / $totalMax * 100, 1) : 0;
      $standing = match(true) {
        $avgPct >= 90 => ['Distinction', '#065f46', '#d1fae5'],
        $avgPct >= 75 => ['Merit',       '#1e40af', '#dbeafe'],
        $avgPct >= 50 => ['Pass',         '#92400e', '#fef3c7'],
        default       => ['Fail',         '#991b1b', '#fee2e2'],
      };
    ?>

    <!-- ── PERFORMANCE SUMMARY STRIP ── -->
    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:0; margin-top:12px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden;">
      <div style="padding:10px 14px; background:#f8fafc; border-right:1px solid #e2e8f0; text-align:center;">
        <div style="font-size:8.5px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:3px;">Total Subjects</div>
        <div style="font-size:18px; font-weight:800; color:#0f172a;"><?= $totalSubjects ?></div>
      </div>
      <div style="padding:10px 14px; background:#f8fafc; border-right:1px solid #e2e8f0; text-align:center;">
        <div style="font-size:8.5px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:3px;">Marks Obtained</div>
        <div style="font-size:18px; font-weight:800; color:#0f172a;"><?= $totalObtained ?> <span style="font-size:11px; color:#94a3b8;">/ <?= $totalMax ?></span></div>
      </div>
      <div style="padding:10px 14px; background:#f8fafc; border-right:1px solid #e2e8f0; text-align:center;">
        <div style="font-size:8.5px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:3px;">Average Score</div>
        <div style="font-size:18px; font-weight:800; color:#1e3a8a;"><?= $avgPct ?>%</div>
      </div>
      <div style="padding:10px 14px; background:#f8fafc; text-align:center;">
        <div style="font-size:8.5px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:3px;">Academic Standing</div>
        <div style="display:inline-block; padding:2px 12px; border-radius:20px; background:<?= $standing[2] ?>; font-size:12px; font-weight:800; color:<?= $standing[1] ?>; margin-top:1px;"><?= $standing[0] ?></div>
      </div>
    </div>

  </div>

  <!-- ── GRADE SCALE + REMARKS (two-column) ── -->
  <div style="display:flex; gap:0; padding:14px 44px 0;">

    <!-- Grade Scale -->
    <div style="flex:1; padding-right:24px; border-right:1px solid #e2e8f0;">
      <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
        <div style="width:3px; height:12px; background:#1e3a8a; border-radius:2px;"></div>
        <div style="font-size:9.5px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:1px;">Grade Scale</div>
      </div>
      <table style="width:100%; border-collapse:collapse; font-size:11px;">
        <thead>
          <tr style="background:#f1f5f9;">
            <th style="padding:6px 10px; text-align:left; font-size:9px; font-weight:700; color:#475569; text-transform:uppercase; border-bottom:1px solid #e2e8f0;">Grade</th>
            <th style="padding:6px 10px; text-align:left; font-size:9px; font-weight:700; color:#475569; text-transform:uppercase; border-bottom:1px solid #e2e8f0;">Range</th>
            <th style="padding:6px 10px; text-align:left; font-size:9px; font-weight:700; color:#475569; text-transform:uppercase; border-bottom:1px solid #e2e8f0;">Remark</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ([
            ['A+', '90–100%', 'Outstanding',  '#065f46', '#d1fae5'],
            ['A',  '80–89%',  'Excellent',    '#1e3a8a', '#eff4ff'],
            ['B+', '70–79%',  'Very Good',    '#1e40af', '#dbeafe'],
            ['B',  '60–69%',  'Good',         '#0369a1', '#e0f2fe'],
            ['C',  '50–59%',  'Satisfactory', '#92400e', '#fef3c7'],
            ['F',  'Below 50','Fail',          '#991b1b', '#fee2e2'],
          ] as [$g, $r, $rm, $tc, $bc]): ?>
          <tr style="border-bottom:1px solid #f1f5f9;">
            <td style="padding:5px 10px;">
              <span style="display:inline-block; padding:1px 8px; border-radius:20px; background:<?= $bc ?>; font-size:10px; font-weight:800; color:<?= $tc ?>;"><?= $g ?></span>
            </td>
            <td style="padding:5px 10px; color:#334155; font-weight:600; font-size:11px;"><?= $r ?></td>
            <td style="padding:5px 10px; color:#64748b; font-size:11px;"><?= $rm ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Remarks -->
    <div style="flex:1; padding-left:24px;">
      <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
        <div style="width:3px; height:12px; background:#1e3a8a; border-radius:2px;"></div>
        <div style="font-size:9.5px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:1px;">Teacher's Remarks</div>
      </div>
      <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px;">
        <?php for ($rl = 0; $rl < 4; $rl++): ?>
        <div style="border-bottom:1px dashed #cbd5e1; height:22px; margin-bottom:4px;"></div>
        <?php endfor; ?>
        <div style="margin-top:14px; display:flex; justify-content:space-between; align-items:flex-end;">
          <div>
            <div style="border-bottom:1px solid #94a3b8; width:120px; margin-bottom:4px;"></div>
            <div style="font-size:8.5px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Class Teacher Sign.</div>
          </div>
          <div style="text-align:right;">
            <div style="border-bottom:1px solid #94a3b8; width:100px; margin-bottom:4px;"></div>
            <div style="font-size:8.5px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Date</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── ATTENDANCE (4 cards) ── -->
  <div style="padding:14px 44px 0;">
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
      <div style="width:3px; height:12px; background:#1e3a8a; border-radius:2px;"></div>
      <div style="font-size:9.5px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:1px;">Attendance Record</div>
    </div>
    <?php $attAbsent = $reportData['att_total'] - $reportData['att_present']; ?>
    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:10px;">
      <div style="padding:11px 10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; text-align:center;">
        <div style="font-size:8.5px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:4px;">Classes Held</div>
        <div style="font-size:20px; font-weight:800; color:#0f172a; line-height:1;"><?= $reportData['att_total'] ?></div>
        <div style="font-size:8px; color:#94a3b8; margin-top:2px;">Total Sessions</div>
      </div>
      <div style="padding:11px 10px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; text-align:center;">
        <div style="font-size:8.5px; font-weight:700; color:#16a34a; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:4px;">Present</div>
        <div style="font-size:20px; font-weight:800; color:#16a34a; line-height:1;"><?= $reportData['att_present'] ?></div>
        <div style="font-size:8px; color:#16a34a; margin-top:2px;">Days Attended</div>
      </div>
      <div style="padding:11px 10px; background:#fff7ed; border:1px solid #fed7aa; border-radius:8px; text-align:center;">
        <div style="font-size:8.5px; font-weight:700; color:#c2410c; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:4px;">Absent</div>
        <div style="font-size:20px; font-weight:800; color:#c2410c; line-height:1;"><?= $attAbsent ?></div>
        <div style="font-size:8px; color:#c2410c; margin-top:2px;">Days Missed</div>
      </div>
      <div style="padding:11px 10px; background:<?= $reportData['att_pct']>=75?'#eff6ff':'#fef2f2' ?>; border:1.5px solid <?= $reportData['att_pct']>=75?'#1e3a8a':'#fca5a5' ?>; border-radius:8px; text-align:center;">
        <div style="font-size:8.5px; font-weight:700; color:<?= $reportData['att_pct']>=75?'#1e3a8a':'#dc2626' ?>; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:4px;">Rate</div>
        <div style="font-size:20px; font-weight:800; color:<?= $reportData['att_pct']>=75?'#1e3a8a':'#dc2626' ?>; line-height:1;"><?= $reportData['att_pct'] ?>%</div>
        <div style="font-size:8px; color:<?= $reportData['att_pct']>=75?'#1e3a8a':'#dc2626' ?>; margin-top:2px;"><?= $reportData['att_pct']>=75?'Satisfactory':'Below Minimum' ?></div>
      </div>
    </div>
  </div>

  <!-- ── SIGNATURES ── -->
  <div style="padding:14px 44px 16px;">
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
      <div style="width:3px; height:12px; background:#1e3a8a; border-radius:2px;"></div>
      <div style="font-size:9.5px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:1px;">Authorized By</div>
    </div>
    <div style="display:flex;">
      <?php foreach (['Class Teacher', 'Head of Department', 'Principal'] as $sig): ?>
      <div style="flex:1; text-align:center; padding:0 20px;">
        <div style="height:40px;"></div>
        <div style="border-top:1px solid #94a3b8; padding-top:6px;">
          <div style="font-size:9px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.6px;"><?= $sig ?></div>
          <div style="font-size:8px; color:#94a3b8; margin-top:1px;">Signature &amp; Date</div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── 6. GENERAL NOTES ── -->
  <div style="padding:0 44px 14px; margin-top:-4px;">
    <div style="background:#f1f5f9; border-radius:8px; padding:10px 14px; border:1px solid #e2e8f0;">
      <div style="font-size:8px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">Important Notes</div>
      <div style="font-size:8.5px; color:#64748b; line-height:1.4;">
        &bull; This is a computer-generated official academic transcript and does not require a physical seal unless explicitly requested.<br>
        &bull; Any alteration or erasure on this document renders it invalid. Please report any discrepancies to the Registrar's Office within 7 days.<br>
        &bull; For online verification of this report, visit the institute portal or scan the QR code above.
      </div>
    </div>
  </div>

  <!-- ── FOOTER ── -->
  <div style="margin-top:auto; border-top:1px solid #e2e8f0; padding:10px 44px; display:flex; justify-content:space-between; align-items:center; background:#f8fafc; flex-shrink:0;">
    <div style="font-size:8.5px; color:#94a3b8;"><?= e($instituteName) ?> &bull; Official Academic Transcript &bull; Confidential Document</div>
    <div style="font-size:8.5px; color:#94a3b8;"><?= date('d M Y, H:i') ?></div>
  </div>
  <div style="height:4px; background:linear-gradient(90deg, #1e3a8a 0%, #3b63c9 100%); flex-shrink:0;"></div>

</div>

<style>
/* ── Screen: hide print card ── */
#printReportCard { display: none; }

@media print {
  @page {
    size: A4 portrait;
    margin: 0;
  }

  body, html {
    margin: 0 !important;
    padding: 0 !important;
    background: #fff !important;
  }

  /* Hide all screen UI chrome */
  .sidebar,
  .topbar,
  .page-header,
  .card,
  form,
  .btn,
  .report-actions,
  footer,
  #screenReportCard { display: none !important; }

  /* Keep wrappers visible so #printReportCard shows through */
  .main-content,
  .content-area,
  .content-wrapper { display: block !important; margin: 0 !important; padding: 0 !important; }

  /* Show the print card */
  #printReportCard {
    display: flex !important;
    flex-direction: column !important;
    width: 210mm !important;
    height: 297mm !important;
    margin: 0 !important;
    padding: 0 !important;
    background: #ffffff !important;
    font-family: 'Inter', 'Segoe UI', Helvetica, Arial, sans-serif;
    box-sizing: border-box;
    overflow: hidden;
  }

  /* Transcript table section grows to fill remaining space */
  #printReportCard > div.prc-table {
    flex: 1 !important;
    overflow: hidden;
  }

  /* Force backgrounds and colors to print */
  * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }

  /* Prevent page breaks inside sections */
  table, tr, td, th { page-break-inside: avoid; }
}
</style>

<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
