<?php
/**
 * Sidebar Navigation Component
 */
$role = $_SESSION['role'] ?? 'student';

$navItems = [
    'admin' => [
        ['icon' => 'ri-dashboard-line',     'label' => 'Dashboard',   'href' => IMS_URL . '/dashboard.php',                   'key' => 'dashboard'],
        ['icon' => 'ri-user-3-line',        'label' => 'Students',    'href' => IMS_URL . '/modules/students/index.php',       'key' => 'students'],
        ['icon' => 'ri-user-star-line',     'label' => 'Teachers',    'href' => IMS_URL . '/modules/teachers/index.php',       'key' => 'teachers'],
        ['icon' => 'ri-book-open-line',     'label' => 'Courses',     'href' => IMS_URL . '/modules/courses/index.php',        'key' => 'courses'],
        ['icon' => 'ri-draft-line',         'label' => 'Subjects',    'href' => IMS_URL . '/modules/subjects/index.php',       'key' => 'subjects'],
        ['icon' => 'ri-calendar-schedule-line','label' => 'Classes',  'href' => IMS_URL . '/modules/classes/index.php',        'key' => 'classes'],
        ['icon' => 'ri-user-follow-line',   'label' => 'Attendance',  'href' => IMS_URL . '/modules/attendance/index.php',     'key' => 'attendance'],
        ['icon' => 'ri-bar-chart-2-line',   'label' => 'Marks',       'href' => IMS_URL . '/modules/marks/index.php',          'key' => 'marks'],
        ['icon' => 'ri-file-chart-line',    'label' => 'Reports',     'href' => IMS_URL . '/modules/reports/index.php',        'key' => 'reports'],
        ['icon' => 'ri-settings-3-line',    'label' => 'Settings',    'href' => IMS_URL . '/modules/settings/index.php',       'key' => 'settings'],
    ],
    'teacher' => [
        ['icon' => 'ri-dashboard-line',     'label' => 'Dashboard',   'href' => IMS_URL . '/dashboard.php',                   'key' => 'dashboard'],
        ['icon' => 'ri-user-3-line',        'label' => 'My Students', 'href' => IMS_URL . '/modules/students/index.php',       'key' => 'students'],
        ['icon' => 'ri-calendar-schedule-line','label' => 'My Classes','href' => IMS_URL . '/modules/classes/index.php',       'key' => 'classes'],
        ['icon' => 'ri-user-follow-line',   'label' => 'Attendance',  'href' => IMS_URL . '/modules/attendance/index.php',     'key' => 'attendance'],
        ['icon' => 'ri-bar-chart-2-line',   'label' => 'Enter Marks', 'href' => IMS_URL . '/modules/marks/index.php',          'key' => 'marks'],
        ['icon' => 'ri-file-chart-line',    'label' => 'Reports',     'href' => IMS_URL . '/modules/reports/index.php',        'key' => 'reports'],
    ],
    'student' => [
        ['icon' => 'ri-dashboard-line',     'label' => 'Dashboard',   'href' => IMS_URL . '/dashboard.php',                   'key' => 'dashboard'],
        ['icon' => 'ri-user-3-line',        'label' => 'My Profile',  'href' => IMS_URL . '/modules/students/profile.php',    'key' => 'profile'],
        ['icon' => 'ri-calendar-check-line','label' => 'Attendance',  'href' => IMS_URL . '/modules/attendance/my.php',        'key' => 'attendance'],
        ['icon' => 'ri-bar-chart-2-line',   'label' => 'My Marks',    'href' => IMS_URL . '/modules/marks/my.php',             'key' => 'marks'],
        ['icon' => 'ri-calendar-schedule-line','label' => 'Timetable','href' => IMS_URL . '/modules/classes/index.php',        'key' => 'classes'],
        ['icon' => 'ri-file-chart-line',    'label' => 'Report Card', 'href' => IMS_URL . '/modules/reports/my.php',           'key' => 'reports'],
    ],
];

$items = $navItems[$role] ?? $navItems['student'];
$instituteName = get_setting('institute_name', 'ExcelIMS');
?>

<aside class="sidebar" id="sidebar">
  <!-- Logo -->
  <div class="sidebar-logo">
    <div class="logo-icon">
      <i class="ri-graduation-cap-fill"></i>
    </div>
    <div class="logo-text">
      <span class="logo-name"><?= e($instituteName) ?></span>
      <span class="logo-tagline">Management System</span>
    </div>
    <button class="sidebar-close" id="sidebarClose"><i class="ri-close-line"></i></button>
  </div>

  <!-- User Card -->
  <div class="sidebar-user-card">
    <?php if (!empty($_SESSION['photo'])): ?>
      <img src="<?= IMS_URL ?>/uploads/<?= e($_SESSION['photo']) ?>" alt="Profile" class="sidebar-avatar">
    <?php else: ?>
      <div class="sidebar-avatar-initials">
        <?= strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 2)) ?>
      </div>
    <?php endif; ?>
    <div class="sidebar-user-info">
      <p class="sidebar-user-name"><?= e($_SESSION['full_name'] ?? 'User') ?></p>
      <span class="sidebar-user-role role-badge role-<?= $role ?>"><?= ucfirst($role) ?></span>
    </div>
  </div>

  <!-- Nav Label -->
  <p class="sidebar-nav-label">NAVIGATION</p>

  <!-- Navigation -->
  <nav class="sidebar-nav">
    <ul>
      <?php foreach ($items as $item): ?>
        <?php $isActive = ($activePage === $item['key']); ?>
        <li>
          <a href="<?= e($item['href']) ?>"
             class="sidebar-nav-item <?= $isActive ? 'active' : '' ?>"
             title="<?= e($item['label']) ?>">
            <i class="<?= e($item['icon']) ?>"></i>
            <span><?= e($item['label']) ?></span>
            <?php if ($isActive): ?>
              <span class="active-indicator"></span>
            <?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </nav>

  <!-- Sidebar Footer -->
  <div class="sidebar-footer">
    <a href="<?= IMS_URL ?>/logout.php" class="sidebar-logout">
      <i class="ri-logout-box-r-line"></i>
      <span>Logout</span>
    </a>
    <p class="sidebar-version">v1.0.0 © <?= date('Y') ?></p>
  </div>
</aside>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
