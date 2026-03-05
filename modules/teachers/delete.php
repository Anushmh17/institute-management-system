<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin']);
$pdo = db();
$id  = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . IMS_URL . '/modules/teachers/index.php'); exit; }
$stmt = $pdo->prepare("SELECT t.*, u.id AS uid, u.full_name, u.profile_photo FROM teachers t JOIN users u ON u.id=t.user_id WHERE t.id=? LIMIT 1");
$stmt->execute([$id]);
$teacher = $stmt->fetch();
if (!$teacher) { set_toast('error','Teacher not found.'); header('Location: ' . IMS_URL . '/modules/teachers/index.php'); exit; }
try {
    if ($teacher['profile_photo']) {
        $p = __DIR__.'/../../uploads/'.$teacher['profile_photo'];
        if (file_exists($p)) @unlink($p);
    }
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$teacher['uid']]);
    log_activity('delete_teacher','teachers',"Deleted: {$teacher['full_name']}");
    set_toast('success',"Teacher \"{$teacher['full_name']}\" deleted.");
} catch (PDOException $e) {
    error_log($e->getMessage());
    set_toast('error','Delete failed.');
}
header('Location: ' . IMS_URL . '/modules/teachers/index.php');
exit;
