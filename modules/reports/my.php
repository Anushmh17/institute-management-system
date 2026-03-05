<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['student']);

$pageTitle  = 'My Report Card';
$activePage = 'reports';
$pdo = db();

$sRow = $pdo->prepare("SELECT id FROM students WHERE user_id=? LIMIT 1");
$sRow->execute([$_SESSION['user_id']]);
$studId = (int)($sRow->fetchColumn() ?: 0);

// Redirect to main reports with student_id
header('Location: ' . IMS_URL . '/modules/reports/index.php?student_id=' . $studId);
exit;
