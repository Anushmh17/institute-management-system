<?php
/**
 * AJAX Global Search Endpoint
 */
require_once __DIR__ . '/../includes/auth.php';
if (!is_logged_in()) { http_response_code(401); echo '[]'; exit; }

header('Content-Type: application/json');

$q   = sanitize($_GET['q'] ?? '');
$pdo = db();

if (strlen($q) < 2) { echo '[]'; exit; }

$results = [];

if (is_admin() || is_teacher()) {
    // Search students
    $stmt = $pdo->prepare(
        "SELECT s.student_id, u.full_name FROM students s JOIN users u ON u.id=s.user_id
         WHERE u.full_name LIKE ? OR s.student_id LIKE ? LIMIT 5"
    );
    $stmt->execute(["%$q%", "%$q%"]);
    foreach ($stmt->fetchAll() as $row) {
        $results[] = [
            'label' => $row['full_name'],
            'url'   => IMS_URL . '/modules/students/index.php?search=' . urlencode($q),
            'icon'  => 'ri-user-3-line',
            'type'  => 'Student | ' . $row['student_id'],
        ];
    }

    // Search courses
    $stmt = $pdo->prepare("SELECT name, code FROM courses WHERE name LIKE ? OR code LIKE ? LIMIT 3");
    $stmt->execute(["%$q%", "%$q%"]);
    foreach ($stmt->fetchAll() as $row) {
        $results[] = [
            'label' => $row['name'],
            'url'   => IMS_URL . '/modules/courses/index.php',
            'icon'  => 'ri-book-open-line',
            'type'  => 'Course | ' . $row['code'],
        ];
    }

    // Search teachers
    if (is_admin()) {
        $stmt = $pdo->prepare(
            "SELECT t.teacher_id, u.full_name FROM teachers t JOIN users u ON u.id=t.user_id
             WHERE u.full_name LIKE ? LIMIT 3"
        );
        $stmt->execute(["%$q%"]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = [
                'label' => $row['full_name'],
                'url'   => IMS_URL . '/modules/teachers/index.php?search=' . urlencode($q),
                'icon'  => 'ri-user-star-line',
                'type'  => 'Teacher | ' . $row['teacher_id'],
            ];
        }
    }
}

echo json_encode(array_slice($results, 0, 10));
