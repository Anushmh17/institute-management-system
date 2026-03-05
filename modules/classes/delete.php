<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin']);
$pdo = db();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . IMS_URL . '/modules/classes/index.php'); exit; }
try {
    $pdo->prepare("DELETE FROM classes WHERE id=?")->execute([$id]);
    set_toast('success','Class removed from schedule.');
} catch (PDOException $e) { set_toast('error','Failed to delete class.'); }
header('Location: ' . IMS_URL . '/modules/classes/index.php'); exit;
