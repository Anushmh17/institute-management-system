<?php
/**
 * IMS Database Installer
 * Visit this page ONCE to set up the database.
 * Then delete or rename this file for security.
 */

// Prevent re-running if already installed (optional safety check)
define('BASE_PATH', __DIR__);

// Define dynamic base URL for installer independence
$root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$dir  = str_replace('\\', '/', dirname(__DIR__));
$baseUrl = str_replace($root, '', $dir);
if (substr($baseUrl,0,1) !== '/') $baseUrl = '/' . $baseUrl;
define('IMS_URL', rtrim($baseUrl, '/'));

$host    = 'localhost';
$port    = '3307';
$user    = 'root';
$pass    = '';  // Change if you have a MySQL password
$dbName  = 'institute_management';
$charset = 'utf8mb4';

$errors   = [];
$success  = false;
$steps    = [];

if (isset($_POST['install'])) {
    try {
        // Step 1: Connect without DB
        $pdo = new PDO(
            "mysql:host=$host;port=$port;charset=$charset",
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $steps[] = ['ok', 'Connected to MySQL server'];

        // Step 2: Create/Verify Database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbName` ");
        $steps[] = ['ok', "Database `$dbName` verified/created"];

        // Step 3: Load and Prepare SQL
        $sqlFile = __DIR__ . '/install.sql';
        if (!file_exists($sqlFile)) throw new Exception('install.sql not found at: ' . $sqlFile);
        $sqlContent = file_get_contents($sqlFile);
        
        // Prepare splitting: remove comments
        $sqlContent = preg_replace('/(\/\*.*?\*\/|--.*?(\r?\n|$))/s', '', $sqlContent);
        
        // Use a better SQL query splitter that handles strings (ignoring semicolons in quotes)
        // Also split by any line-ending semicolon correctly
        $queries = preg_split("/;(?=(?:[^']*'[^']*')*[^']*$)/", $sqlContent);
        
        // Remove empty queries
        $queries = array_filter(array_map('trim', $queries));
        $steps[] = ['ok', 'SQL file loaded and pre-processed (' . count($queries) . ' queries)'];

        // Step 4: Execute Table Structure
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $successCount = 0;
        foreach ($queries as $query) {
            // Skip database creation/use if they are in the file
            if (preg_match('/^\s*(CREATE DATABASE|USE)\s+/i', $query)) continue;
            
            try {
                $pdo->exec($query);
                $successCount++;
            } catch (PDOException $e) {
                // If the error is "Table already exists", we can ignore it for an installer
                if ($e->getCode() == '42S01') continue; 
                throw new Exception("Error in query $successCount: " . $e->getMessage() . "\nQuery: " . substr($query, 0, 100) . "...");
            }
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        $steps[] = ['ok', "Successfully executed $successCount SQL queries"];

        // Step 5: Verify Critical Tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('users', $tables) || !in_array('roles', $tables)) {
            throw new Exception("Critical tables (users/roles) were not created. Found: " . implode(', ', $tables));
        }
        $steps[] = ['ok', 'Core table structure verified'];

        // Step 6: Create/Update Default Accounts individual calls
        $seedQueries = [
            'REPLACE INTO `users` (`id`, `role_id`, `username`, `email`, `password`, `full_name`, `status`) VALUES (1, 1, "admin", "admin@institute.com", \'$2y$10$gSqvzuvy53wO/Sht1WUzMuF.19ivtp9PaiHUityYhWB8FtDyhqON.\', "System Administrator", "active")',
            'REPLACE INTO `users` (`id`, `role_id`, `username`, `email`, `password`, `full_name`, `status`) VALUES (2, 2, "teacher1", "teacher1@institute.com", \'$2y$10$uQk1GODeAps8o13.r3btz.AbSeRqBBouFHFB6pRXTj0DH7i79spky\', "Sarah Johnson", "active")',
            'REPLACE INTO `users` (`id`, `role_id`, `username`, `email`, `password`, `full_name`, `status`) VALUES (3, 3, "student1", "student1@institute.com", \'$2y$10$Ov4lCLIYbZXO2k/MW/2K0u8F/XPiZpA2IXqP/Vz.01DWyBVzoqBLS\', "James Wilson", "active")',
            "INSERT IGNORE INTO `departments` (`id`, `name`, `code`) VALUES (1, 'Computer Science', 'CS')",
            "REPLACE INTO `teachers` (`id`, `user_id`, `teacher_id`, `department_id`) VALUES (1, 2, 'TCH001', 1)",
            "INSERT IGNORE INTO `courses` (`id`, `department_id`, `name`, `code`) VALUES (1, 1, 'Web Development', 'WD101')",
            "REPLACE INTO `students` (`id`, `user_id`, `student_id`, `course_id`) VALUES (1, 3, 'STU001', 1)"
        ];

        foreach ($seedQueries as $q) {
            $pdo->exec($q);
        }
        $steps[] = ['ok', 'Default accounts (Admin, Teacher, Student) have been verified and updated.'];

        $success = true;
        $steps[] = ['ok', 'Installation complete!'];

    } catch (Exception $e) {
        $errors[] = $e->getMessage();
        $steps[]  = ['error', $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>IMS Installer</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#0F172A 0%,#1E3A8A 60%,#2563EB 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
    .card{background:#fff;border-radius:20px;box-shadow:0 24px 64px rgba(0,0,0,.3);width:100%;max-width:520px;overflow:hidden;}
    .card-head{background:linear-gradient(135deg,#1E3A8A,#2563EB);padding:32px;color:#fff;text-align:center;}
    .card-head .icon{width:64px;height:64px;background:rgba(255,255,255,.15);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 16px;}
    h1{font-size:22px;font-weight:700;}
    p.sub{font-size:13px;opacity:.8;margin-top:8px;}
    .card-body{padding:32px;}
    .step{display:flex;align-items:center;gap:12px;padding:10px;border-radius:8px;margin-bottom:8px;font-size:14px;}
    .step.ok   {background:#ECFDF5;color:#065f46;}
    .step.warn {background:#FEF3C7;color:#92400e;}
    .step.error{background:#FEF2F2;color:#991b1b;}
    .step i{font-size:18px;flex-shrink:0;}
    .btn{display:inline-flex;align-items:center;gap:8px;padding:12px 28px;background:#2563EB;color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;width:100%;justify-content:center;transition:.2s;}
    .btn:hover{background:#1D4ED8;transform:translateY(-1px);}
    .btn-success{background:#10B981;}
    .btn-success:hover{background:#059669;}
    .warn-box{background:#FEF3C7;border:1px solid #D97706;border-radius:10px;padding:14px 16px;font-size:13px;color:#92400e;margin-bottom:20px;display:flex;gap:10px;align-items:flex-start;}
    .info-box{background:#DBEAFE;border:1px solid #3B82F6;border-radius:10px;padding:14px 16px;font-size:13px;color:#1e40af;margin-bottom:20px;}
    .info-box ul{padding-left:20px;margin-top:8px;}
    .info-box li{margin-bottom:4px;}
    code{background:#F1F5F9;padding:2px 6px;border-radius:4px;font-size:12px;}
  </style>
</head>
<body>
<div class="card">
  <div class="card-head">
    <div class="icon"><i class="ri-graduation-cap-fill"></i></div>
    <h1>Institute Management System</h1>
    <p class="sub">Database Installer v1.0</p>
  </div>
  <div class="card-body">

    <?php if ($success): ?>
      <?php foreach ($steps as [$type, $msg]): ?>
      <div class="step <?= $type ?>">
        <i class="ri-<?= $type==='ok'?'checkbox-circle':'alert' ?>-fill"></i>
        <?= htmlspecialchars($msg) ?>
      </div>
      <?php endforeach; ?>
      <div style="margin-top:24px; padding-top:20px; border-top:1px solid #E2E8F0;">
        <div class="info-box">
          <strong>✅ Installation Successful!</strong>
          <ul>
            <li>Database: <code>institute_management</code></li>
            <li>Admin: <code>admin</code> / <code>Admin@1234</code></li>
            <li><strong>Delete or rename this installer file for security!</strong></li>
          </ul>
        </div>
        <a href="<?= IMS_URL ?>/index.php" class="btn btn-success">
          <i class="ri-login-box-line"></i> Go to Login Page
        </a>
      </div>
    <?php elseif (!empty($steps)): ?>
      <?php foreach ($steps as [$type, $msg]): ?>
      <div class="step <?= $type ?>">
        <i class="ri-<?= $type==='ok'?'checkbox-circle':'error-warning' ?>-fill"></i>
        <?= htmlspecialchars($msg) ?>
      </div>
      <?php endforeach; ?>
      <div style="margin-top:20px;">
        <div class="warn-box"><i class="ri-alert-line" style="font-size:18px;flex-shrink:0;"></i>
          <div>Installation failed. Check your MySQL server is running in XAMPP and the credentials are correct.</div>
        </div>
        <form method="POST">
          <button type="submit" name="install" class="btn"><i class="ri-refresh-line"></i> Retry Installation</button>
        </form>
      </div>
    <?php else: ?>
      <div class="info-box">
        <strong>Before installing:</strong>
        <ul>
          <li>Make sure XAMPP MySQL is <strong>running</strong></li>
          <li>Default connection: <code>localhost:3306</code>, user <code>root</code>, no password</li>
          <li>If you changed these, edit <code>config/install.php</code> and <code>config/database.php</code></li>
        </ul>
      </div>
      <div class="warn-box"><i class="ri-alert-line" style="font-size:18px;flex-shrink:0;"></i>
        <div><strong>Security:</strong> Delete this file after installation!</div>
      </div>
      <form method="POST">
        <button type="submit" name="install" class="btn">
          <i class="ri-database-2-line"></i> Install Database
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
