<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin']);
$pdo = db();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . IMS_URL . '/modules/subjects/index.php'); exit; }
try {
    $pdo->prepare("DELETE FROM subjects WHERE id=?")->execute([$id]);
    set_toast('success','Subject deleted.');
} catch (PDOException $e) { set_toast('error','Cannot delete -- marks or classes may exist.'); }
header('Location: ' . IMS_URL . '/modules/subjects/index.php'); exit;
