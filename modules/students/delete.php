<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['admin']);

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . IMS_URL . '/modules/students/index.php'); exit; }

$stmt = $pdo->prepare("SELECT s.*, u.full_name, u.profile_photo, u.id AS uid FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = ? LIMIT 1");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    set_toast('error', 'Student not found.');
    header('Location: ' . IMS_URL . '/modules/students/index.php');
    exit;
}

try {
    // Delete photo file if exists
    if ($student['profile_photo']) {
        $photoPath = __DIR__ . '/../../uploads/' . $student['profile_photo'];
        if (file_exists($photoPath)) @unlink($photoPath);
    }

    // Deleting user cascades to student (ON DELETE CASCADE)
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$student['uid']]);

    log_activity('delete_student', 'students', "Deleted student: {$student['full_name']} (ID: {$student['student_id']})");
    set_toast('success', "Student \"{$student['full_name']}\" deleted successfully.");

} catch (PDOException $e) {
    error_log($e->getMessage());
    set_toast('error', 'Failed to delete student. Please try again.');
}

header('Location: ' . IMS_URL . '/modules/students/index.php');
exit;
