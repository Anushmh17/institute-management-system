<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle  = 'Class Timetable';
$activePage = 'classes';
$pdo = db();

// ── Role-based filter ─────────────────────────────────────────────────────────
$whereExtra = '';
if (is_teacher()) {
    $tr = $pdo->prepare("SELECT id FROM teachers WHERE user_id=? LIMIT 1");
    $tr->execute([$_SESSION['user_id']]);
    $tid = (int)($tr->fetchColumn() ?: 0);
    $whereExtra = " AND cl.teacher_id = $tid";
}
if (is_student()) {
    $sr = $pdo->prepare("SELECT course_id FROM students WHERE user_id=? LIMIT 1");
    $sr->execute([$_SESSION['user_id']]);
    $cid = (int)($sr->fetchColumn() ?: 0);
    $whereExtra = " AND sb.course_id = $cid";
}

$classes = $pdo->query(
    "SELECT cl.*, sb.name AS subject, sb.code AS sub_code, c.name AS course,
            u.full_name AS teacher_name
     FROM classes cl
     JOIN subjects sb ON sb.id = cl.subject_id
     JOIN courses  c  ON c.id  = sb.course_id
     LEFT JOIN teachers t ON t.id = cl.teacher_id
     LEFT JOIN users    u ON u.id = t.user_id
     WHERE cl.status = 'scheduled' $whereExtra
     ORDER BY FIELD(cl.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday'),
              cl.start_time"
)->fetchAll();

$days    = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
$dayAbbr = ['Monday'=>'MON','Tuesday'=>'TUE','Wednesday'=>'WED','Thursday'=>'THU','Friday'=>'FRI'];
$schedule = [];
foreach ($classes as $cl) $schedule[$cl['day_of_week']][] = $cl;

$today        = date('l');
$nowMinutes   = (int)date('H') * 60 + (int)date('i');
$totalClasses = count($classes);

// Type config
$types = [
    'lecture'  => ['gradient'=>'linear-gradient(135deg,#3B82F6,#2563EB)', 'soft'=>'rgba(59,130,246,.08)', 'border'=>'#3B82F6', 'text'=>'#1D4ED8', 'icon'=>'ri-book-open-line',       'label'=>'Lecture'],
    'lab'      => ['gradient'=>'linear-gradient(135deg,#10B981,#059669)', 'soft'=>'rgba(16,185,129,.08)', 'border'=>'#10B981', 'text'=>'#065F46', 'icon'=>'ri-flask-line',            'label'=>'Lab'],
    'tutorial' => ['gradient'=>'linear-gradient(135deg,#F97316,#EA580C)', 'soft'=>'rgba(249,115,22,.08)', 'border'=>'#F97316', 'text'=>'#C2410C', 'icon'=>'ri-pencil-ruler-2-line',  'label'=>'Tutorial'],
    'exam'     => ['gradient'=>'linear-gradient(135deg,#EF4444,#DC2626)', 'soft'=>'rgba(239,68,68,.08)',  'border'=>'#EF4444', 'text'=>'#991B1B', 'icon'=>'ri-file-paper-2-line',     'label'=>'Exam'],
];

function isNowRunning(string $start, string $end, int $nowMin): bool {
    $s = (int)date('H', strtotime($start)) * 60 + (int)date('i', strtotime($start));
    $e = (int)date('H', strtotime($end))   * 60 + (int)date('i', strtotime($end));
    return $nowMin >= $s && $nowMin < $e;
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
/* ═══════════════════════════════════════════
   TIMETABLE – Premium v2
═══════════════════════════════════════════ */

/* ── Page hero stat strip ────────────────── */
.tt-stats {
  display: flex;
  gap: 12px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}
.tt-stat-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 40px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  box-shadow: var(--shadow-xs);
  font-size: 13px;
  font-weight: 600;
  color: var(--text-primary);
}
.tt-stat-pill i { font-size: 15px; }
.tt-stat-pill .tt-pill-num {
  font-size: 18px;
  font-weight: 800;
  line-height: 1;
}
.tt-stat-pill.accent { background: var(--primary); color: #fff; border-color: var(--primary); }

/* ── Outer card shell ────────────────────── */
.tt-shell {
  border-radius: 20px;
  overflow: hidden;
  border: 1px solid var(--border);
  box-shadow: 0 4px 32px rgba(0,0,0,.09);
  background: var(--bg-card);
}

/* ── Header row ──────────────────────────── */
.tt-head {
  display: grid;
  grid-template-columns: repeat(5,1fr);
  background: var(--bg);
  border-bottom: 1px solid var(--border);
}

.tt-head-cell {
  padding: 18px 14px 16px;
  text-align: center;
  border-right: 1px solid var(--border);
  position: relative;
  transition: background .2s;
}
.tt-head-cell:last-child { border-right: none; }

.tt-head-cell.today-col {
  background: linear-gradient(160deg, #1E3A8A 0%, #2563EB 100%);
}
.tt-head-cell .abbr-tag {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 2px;
  color: var(--text-muted);
  display: block;
  margin-bottom: 4px;
}
.tt-head-cell.today-col .abbr-tag { color: rgba(255,255,255,.6); }
.tt-head-cell .day-label {
  font-size: 16px;
  font-weight: 800;
  color: var(--text-primary);
  display: block;
  font-family: var(--font-heading);
  letter-spacing: -.3px;
}
.tt-head-cell.today-col .day-label { color: #fff; }
.tt-head-cell .class-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-top: 8px;
  padding: 3px 11px;
  border-radius: 99px;
  font-size: 11px;
  font-weight: 600;
  background: rgba(0,0,0,.05);
  color: var(--text-secondary);
}
.tt-head-cell.today-col .class-pill {
  background: rgba(255,255,255,.18);
  color: #fff;
}

/* Live pulsing dot */
.tt-live-ring {
  position: absolute;
  top: 12px;
  right: 12px;
  width: 10px;
  height: 10px;
}
.tt-live-ring::before,
.tt-live-ring::after {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: #fff;
}
.tt-live-ring::after {
  animation: live-pulse 1.8s ease-out infinite;
  background: rgba(255,255,255,.5);
}
@keyframes live-pulse {
  0%   { transform: scale(1);   opacity: .8; }
  100% { transform: scale(2.6); opacity: 0;  }
}

/* ── Body grid ───────────────────────────── */
.tt-body {
  display: grid;
  grid-template-columns: repeat(5,1fr);
}
.tt-col {
  border-right: 1px solid var(--border);
  padding: 14px 10px;
  min-height: 200px;
}
.tt-col:last-child { border-right: none; }
.tt-col.today-col {
  background: linear-gradient(180deg,rgba(37,99,235,.035) 0%,rgba(37,99,235,0) 80%);
}

/* ── Empty column ───────────────────────── */
.tt-no-class {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 130px;
  gap: 8px;
}
.tt-no-class i {
  font-size: 26px;
  color: var(--border-strong);
}
.tt-no-class span {
  font-size: 11px;
  color: var(--text-muted);
  font-weight: 500;
}

/* ── Class card ─────────────────────────── */
.tt-card {
  border-radius: 12px;
  margin-bottom: 10px;
  overflow: hidden;
  border: 1px solid transparent;
  box-shadow: 0 2px 8px rgba(0,0,0,.06);
  transition: transform .18s ease, box-shadow .18s ease;
  animation: slide-up .35s ease both;
  position: relative;
}
.tt-card:last-child { margin-bottom: 0; }
.tt-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 28px rgba(0,0,0,.13);
}
@keyframes slide-up {
  from { opacity:0; transform: translateY(12px); }
  to   { opacity:1; transform: translateY(0);    }
}

/* Running class glow */
.tt-card.running {
  box-shadow: 0 0 0 2px var(--card-border), 0 8px 24px rgba(0,0,0,.14);
  animation: slide-up .35s ease both, running-glow 2.5s ease-in-out infinite alternate;
}
@keyframes running-glow {
  from { box-shadow: 0 0 0 2px var(--card-border), 0 4px 14px rgba(0,0,0,.1); }
  to   { box-shadow: 0 0 0 2.5px var(--card-border), 0 8px 28px rgba(0,0,0,.2); }
}

/* Card colour strip across top */
.tt-card-stripe {
  height: 4px;
  width: 100%;
}

/* Card body */
.tt-card-body { padding: 10px 12px 12px; }

/* Subject name */
.tt-subject-name {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--text-primary);
  line-height: 1.35;
  margin-bottom: 6px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Type + Running badges row */
.tt-badges {
  display: flex;
  gap: 5px;
  margin-bottom: 8px;
  flex-wrap: wrap;
}
.tt-type-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  border-radius: 99px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .3px;
}
.tt-running-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  border-radius: 99px;
  font-size: 10px;
  font-weight: 700;
  background: rgba(16,185,129,.12);
  color: #059669;
}
.tt-running-badge::before {
  content:'';
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #10B981;
  display: inline-block;
  animation: blink .9s ease-in-out infinite alternate;
}
@keyframes blink { to { opacity:.2; } }

/* Meta rows */
.tt-meta { display: flex; flex-direction: column; gap: 4px; }
.tt-meta-row {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  color: var(--text-secondary);
}
.tt-meta-row i { font-size: 12px; flex-shrink: 0; }
.tt-course-tag {
  font-size: 10px;
  color: var(--text-muted);
  font-style: italic;
  margin-top: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Admin action strip */
.tt-actions {
  display: flex;
  gap: 5px;
  padding: 8px 12px;
  border-top: 1px solid var(--border);
  background: rgba(0,0,0,.018);
}

/* ── Footer bar ──────────────────────────── */
.tt-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 20px;
  border-top: 1px solid var(--border);
  background: var(--bg);
  flex-wrap: wrap;
  gap: 10px;
}
.tt-footer-left { font-size: 12px; color: var(--text-muted); display:flex;align-items:center;gap:6px; }
.tt-legend { display: flex; gap: 14px; flex-wrap: wrap; }
.tt-legend-item { display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 600; color: var(--text-secondary); }
.tt-legend-swatch { width: 12px; height: 12px; border-radius: 4px; }

/* ── Responsive ──────────────────────────── */
@media (max-width: 900px) {
  .tt-head, .tt-body { min-width: 820px; }
  .tt-shell { overflow-x: auto; }
}

/* ── Dark mode tweaks ────────────────────── */
[data-theme="dark"] .tt-card  { box-shadow: 0 2px 10px rgba(0,0,0,.3); }
[data-theme="dark"] .tt-shell { box-shadow: 0 4px 32px rgba(0,0,0,.3); }
[data-theme="dark"] .tt-head-cell.today-col { background: linear-gradient(160deg, #0e1e50 0%, #1a3a8a 100%); }
</style>

<!-- ════════════════════════════════════════════════════════
     PAGE HEADER
════════════════════════════════════════════════════════ -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Class Timetable</h1>
    <p class="page-subtitle"><?= date('d M Y', strtotime('monday this week')) ?> - <?= date('d M Y', strtotime('friday this week')) ?></p>
  </div>
  <?php if (is_admin()): ?>
  <div class="page-header-actions">
    <a href="<?= IMS_URL ?>/modules/classes/add.php" class="btn btn-primary">
      <i class="ri-add-line"></i> Schedule Class
    </a>
  </div>
  <?php endif; ?>
</div>

<?php
// ── Count stats
$byType = [];
foreach ($classes as $c) {
    $t = strtolower($c['type'] ?? 'lecture');
    $byType[$t] = ($byType[$t] ?? 0) + 1;
}
$todayCount = count($schedule[$today] ?? []);
?>

<!-- ── Stat pills ─────────────────────────────────────────────────────── -->
<div class="tt-stats">
  <div class="tt-stat-pill accent">
    <i class="ri-calendar-check-line"></i>
    <span class="tt-pill-num"><?= $totalClasses ?></span>
    <span>classes this week</span>
  </div>
  <div class="tt-stat-pill">
    <i class="ri-sun-line" style="color:var(--accent);"></i>
    <span class="tt-pill-num"><?= $todayCount ?></span>
    <span>today (<?= $today ?>)</span>
  </div>
  <?php foreach ($byType as $typeName => $cnt):
    $tc = $types[$typeName] ?? $types['lecture']; ?>
  <div class="tt-stat-pill">
    <i class="<?= $tc['icon'] ?>" style="color:<?= $tc['border'] ?>;"></i>
    <span class="tt-pill-num" style="color:<?= $tc['text'] ?>;"><?= $cnt ?></span>
    <span><?= $tc['label'] ?><?= $cnt !== 1 ? 's' : '' ?></span>
  </div>
  <?php endforeach; ?>
</div>

<?php if (empty($classes)): ?>
<!-- ── Empty state ─────────────────────────────────────────────────── -->
<div class="empty-state">
  <i class="ri-calendar-schedule-line"></i>
  <h3>No Classes Scheduled</h3>
  <p>Schedule classes after adding courses and subjects.</p>
  <?php if (is_admin()): ?>
    <a href="<?= IMS_URL ?>/modules/classes/add.php" class="btn btn-primary" style="margin-top:16px;">
      Schedule Class
    </a>
  <?php endif; ?>
</div>

<?php else: ?>
<!-- ════════════════════════════════════════════════════════
     TIMETABLE GRID
════════════════════════════════════════════════════════ -->
<div class="tt-shell">

  <!-- DAY HEADERS -->
  <div class="tt-head">
    <?php foreach ($days as $d):
      $isToday = ($d === $today);
      $cnt     = count($schedule[$d] ?? []);
    ?>
    <div class="tt-head-cell <?= $isToday ? 'today-col' : '' ?>">
      <?php if ($isToday): ?><span class="tt-live-ring"></span><?php endif; ?>
      <span class="abbr-tag"><?= $dayAbbr[$d] ?></span>
      <span class="day-label"><?= $d ?></span>
      <div class="class-pill">
        <?= $cnt ?> <?= $cnt === 1 ? 'class' : 'classes' ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- DAY COLUMNS -->
  <div class="tt-body">
    <?php foreach ($days as $d):
      $isToday = ($d === $today);
      $colClasses = $schedule[$d] ?? [];
    ?>
    <div class="tt-col <?= $isToday ? 'today-col' : '' ?>">

      <?php if (empty($colClasses)): ?>
      <div class="tt-no-class">
        <i class="ri-moon-clear-line"></i>
        <span>No classes</span>
      </div>

      <?php else:
        foreach ($colClasses as $i => $cl):
          $type    = strtolower($cl['type'] ?? 'lecture');
          $tc      = $types[$type] ?? $types['lecture'];
          $running = $isToday && isNowRunning($cl['start_time'], $cl['end_time'], $nowMinutes);
          $delay   = $i * 60; // ms animation stagger
      ?>
      <div class="tt-card <?= $running ? 'running' : '' ?>"
           style="--card-border:<?= $tc['border'] ?>;animation-delay:<?= $delay ?>ms;">

        <!-- Colour stripe -->
        <div class="tt-card-stripe" style="background:<?= $tc['gradient'] ?>;"></div>

        <div class="tt-card-body" style="background:<?= $tc['soft'] ?>;">

          <!-- Subject -->
          <div class="tt-subject-name"><?= e($cl['subject']) ?></div>

          <!-- Badges -->
          <div class="tt-badges">
            <span class="tt-type-badge"
                  style="background:<?= $tc['border'] ?>18;color:<?= $tc['text'] ?>;border:1px solid <?= $tc['border'] ?>30;">
              <i class="<?= $tc['icon'] ?>" style="font-size:10px;"></i>
              <?= $tc['label'] ?>
            </span>
            <?php if ($running): ?>
            <span class="tt-running-badge">Live now</span>
            <?php endif; ?>
          </div>

          <!-- Meta -->
          <div class="tt-meta">
            <div class="tt-meta-row">
              <i class="ri-time-line" style="color:<?= $tc['border'] ?>;"></i>
              <span><?= date('g:i A', strtotime($cl['start_time'])) ?> - <?= date('g:i A', strtotime($cl['end_time'])) ?></span>
            </div>
            <?php if ($cl['room']): ?>
            <div class="tt-meta-row">
              <i class="ri-map-pin-2-line" style="color:<?= $tc['border'] ?>;"></i>
              <span><?= e($cl['room']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($cl['teacher_name']): ?>
            <div class="tt-meta-row">
              <i class="ri-user-star-line" style="color:<?= $tc['border'] ?>;"></i>
              <span><?= e($cl['teacher_name']) ?></span>
            </div>
            <?php endif; ?>
            <div class="tt-course-tag">
              <i class="ri-book-2-line"></i> <?= e($cl['course']) ?>
            </div>
          </div>
        </div>

        <!-- Admin / Teacher actions -->
        <?php if (is_admin() || is_teacher()): ?>
        <div class="tt-actions">
          <a href="<?= IMS_URL ?>/modules/classes/edit.php?id=<?= $cl['id'] ?>"
             class="btn btn-outline btn-sm"
             style="font-size:11px;padding:3px 10px;flex:1;justify-content:center;gap:4px;">
            <i class="ri-edit-line"></i> Edit
          </a>
          <a href="<?= IMS_URL ?>/modules/classes/delete.php?id=<?= $cl['id'] ?>"
             class="btn btn-outline btn-sm"
             style="font-size:11px;padding:3px 10px;color:var(--danger);border-color:var(--danger)20;"
             data-confirm-delete="this class">
            <i class="ri-delete-bin-line"></i>
          </a>
        </div>
        <?php endif; ?>

      </div><!-- /.tt-card -->

      <?php endforeach; endif; ?>
    </div><!-- /.tt-col -->
    <?php endforeach; ?>
  </div><!-- /.tt-body -->

  <!-- FOOTER LEGEND -->
  <div class="tt-footer">
    <div class="tt-footer-left">
      <i class="ri-information-line"></i>
      <?= $totalClasses ?> scheduled class<?= $totalClasses !== 1 ? 'es' : '' ?> this week
      <span class="divider"></span>
      Updated <?= date('g:i A') ?>
    </div>
    <div class="tt-legend">
      <?php foreach ($types as $typeName => $tc): ?>
      <div class="tt-legend-item">
        <span class="tt-legend-swatch" style="background:<?= $tc['gradient'] ?>;"></span>
        <?= $tc['label'] ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div><!-- /.tt-shell -->
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
