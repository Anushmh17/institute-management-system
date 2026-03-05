<?php
/**
 * Global Header - HTML head + top nav bar
 * Expects: $pageTitle (string), $activePage (string)
 */

if (!isset($pageTitle))  $pageTitle  = 'Dashboard';
if (!isset($activePage)) $activePage = 'dashboard';

$instituteName = get_setting('institute_name', 'ExcelIMS');
$toast = get_toast();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= e($instituteName) ?> - Institute Management System">
  <title><?= e($pageTitle) ?> | <?= e($instituteName) ?></title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Remix Icons -->
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

  <!-- Global Config -->
  <script>const IMS_URL = '<?= IMS_URL ?>/';</script>

  <!-- Main CSS -->
  <link rel="stylesheet" href="<?= IMS_URL ?>/assets/css/style.css?v=sidebar_alignment_v6">
</head>
<body>

<!-- Toast Notification -->
<?php if ($toast): ?>
<div class="toast toast-<?= e($toast['type']) ?>" id="globalToast" role="alert">
  <i class="ri-<?= $toast['type'] === 'success' ? 'checkbox-circle' : ($toast['type'] === 'error' ? 'error-warning' : 'information') ?>-fill"></i>
  <span><?= e($toast['message']) ?></span>
  <button onclick="this.parentElement.remove()" class="toast-close"><i class="ri-close-line"></i></button>
</div>
<?php endif; ?>

<!-- Layout Wrapper -->
<div class="layout-wrapper" id="layoutWrapper">

  <?php include __DIR__ . '/sidebar.php'; ?>

  <!-- Main Content -->
  <div class="main-content" id="mainContent">

    <!-- Top Navigation Bar -->
    <header class="topbar">
      <div class="topbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" title="Toggle Sidebar">
          <i class="ri-menu-line"></i>
        </button>
        <nav class="breadcrumb-nav" aria-label="breadcrumb">
          <span class="breadcrumb-item"><i class="ri-home-4-line"></i></span>
          <span class="breadcrumb-sep"><i class="ri-arrow-right-s-line"></i></span>
          <span class="breadcrumb-item active"><?= e($pageTitle) ?></span>
        </nav>
      </div>

      <div class="topbar-right">
        <!-- Search -->
        <div class="topbar-search">
          <i class="ri-search-line"></i>
          <input type="text" id="globalSearch" placeholder="Search anything..." autocomplete="off">
          <div class="search-results" id="searchResults"></div>
        </div>

        <!-- Notifications -->
        <div class="topbar-icon-btn" title="Notifications">
          <i class="ri-notification-3-line"></i>
          <span class="badge badge-danger">3</span>
        </div>

        <!-- Dark Mode -->
        <button class="topbar-icon-btn" id="darkModeToggle" title="Toggle Dark Mode">
          <i class="ri-moon-line" id="darkModeIcon"></i>
        </button>

        <!-- Profile Dropdown -->
        <div class="profile-dropdown" id="profileDropdown">
          <button class="profile-trigger" id="profileTrigger">
            <?php if (!empty($_SESSION['photo'])): ?>
              <img src="<?= IMS_URL ?>/uploads/<?= e($_SESSION['photo']) ?>" alt="Profile" class="avatar-sm">
            <?php else: ?>
              <div class="avatar-initials">
                <?= strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)) ?>
              </div>
            <?php endif; ?>
            <div class="profile-info">
              <span class="profile-name"><?= e($_SESSION['full_name'] ?? 'User') ?></span>
              <span class="profile-role"><?= ucfirst(e($_SESSION['role'] ?? '')) ?></span>
            </div>
            <i class="ri-arrow-down-s-line"></i>
          </button>
          <div class="dropdown-menu" id="profileMenu">
            <a href="<?= IMS_URL . (is_student() ? '/modules/students/profile.php' : '/modules/settings/profile.php') ?>" class="dropdown-item">
              <i class="ri-user-line"></i> My Profile
            </a>
            <?php if (is_admin()): ?>
            <a href="<?= IMS_URL ?>/modules/settings/index.php" class="dropdown-item">
              <i class="ri-settings-3-line"></i> Settings
            </a>
            <?php endif; ?>
            <div class="dropdown-divider"></div>
            <a href="<?= IMS_URL ?>/logout.php" class="dropdown-item text-danger">
              <i class="ri-logout-box-r-line"></i> Logout
            </a>
          </div>
        </div>

      </div>
    </header>

    <!-- Page Content Area -->
    <main class="content-area" id="contentArea">
