<?php
/**
 * Authentication & Session Helper
 * Handles login, role checks, CSRF, and security
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,   // set true in production with HTTPS
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// --- Base URL Detection ------------------------------------------------------
// This helps the project work regardless of subfolder depth in htdocs
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
$dir = str_replace('\\', '/', dirname(__DIR__));
$baseUrl = str_replace($docRoot, '', $dir);
if (substr($baseUrl, 0, 1) !== '/') $baseUrl = '/' . $baseUrl;
define('IMS_URL', rtrim($baseUrl, '/'));

require_once __DIR__ . '/../config/database.php';

// --- CSRF --------------------------------------------------------------------
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('CSRF token mismatch. Please go back and try again.');
    }
}

// --- Authentication ----------------------------------------------------------
function is_logged_in(): bool {
    return isset($_SESSION['user_id'], $_SESSION['role']);
}

function require_login(string $redirect = null): void {
    if ($redirect === null) $redirect = IMS_URL . '/index.php';
    if (!is_logged_in()) {
        header('Location: ' . $redirect);
        exit;
    }
}

function require_role(array|string $roles, string $redirect = null): void {
    if ($redirect === null) $redirect = IMS_URL . '/dashboard.php';
    require_login();
    $roles = (array)$roles;
    if (!in_array($_SESSION['role'], $roles, true)) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Access denied. You do not have permission.'];
        header('Location: ' . $redirect);
        exit;
    }
}

function is_admin(): bool {
    return ($_SESSION['role'] ?? '') === 'admin';
}
function is_teacher(): bool {
    return ($_SESSION['role'] ?? '') === 'teacher';
}
function is_student(): bool {
    return ($_SESSION['role'] ?? '') === 'student';
}

// --- Login / Logout ----------------------------------------------------------

/**
 * Role-based login: only allows login if the user's actual role matches $requiredRole.
 */
function attempt_login_role(string $username, string $password, string $requiredRole): array {
    try {
        $pdo  = db();
        $stmt = $pdo->prepare(
            "SELECT u.*, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE (u.username = ? OR u.email = ?)
               AND u.status = 'active'
             LIMIT 1"
        );
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        // Role mismatch – guide user to correct portal
        if ($user['role_name'] !== $requiredRole) {
            return [
                'success' => false,
                'message' => 'This account is not a ' . ucfirst($requiredRole) . ' account. '
                           . 'Please select the correct portal tab.',
            ];
        }

        session_regenerate_id(true);

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email']     = $user['email'];
        $_SESSION['role']      = $user['role_name'];
        $_SESSION['photo']     = $user['profile_photo'];

        $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
        log_activity('login', 'auth', 'User logged in as ' . $requiredRole);

        return ['success' => true, 'role' => $user['role_name']];

    } catch (PDOException $e) {
        error_log($e->getMessage());
        return ['success' => false, 'message' => 'Database error. Please try again.'];
    }
}

function attempt_login(string $username, string $password): array {
    try {
        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT u.*, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE (u.username = ? OR u.email = ?)
               AND u.status = 'active'
             LIMIT 1"
        );
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email']     = $user['email'];
        $_SESSION['role']      = $user['role_name'];
        $_SESSION['photo']     = $user['profile_photo'];

        // Update last login
        $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

        // Log activity
        log_activity('login', 'auth', 'User logged in');

        return ['success' => true, 'role' => $user['role_name']];

    } catch (PDOException $e) {
        error_log($e->getMessage());
        return ['success' => false, 'message' => 'Database error. Please try again.'];
    }
}

function logout(): void {
    log_activity('logout', 'auth', 'User logged out');
    $_SESSION = [];
    session_destroy();
    session_start();
    session_regenerate_id(true);
}

// --- XSS Protection ----------------------------------------------------------
function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function sanitize(string $value): string {
    return trim(strip_tags($value));
}

// --- Activity Logging --------------------------------------------------------
function log_activity(string $action, string $module = '', string $description = ''): void {
    try {
        $pdo = db();
        $stmt = $pdo->prepare(
            "INSERT INTO activity_logs (user_id, action, module, description, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            $module,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? '',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (PDOException $e) {
        // Silent fail for logging
    }
}

// --- Toast Messages ----------------------------------------------------------
function set_toast(string $type, string $message): void {
    $_SESSION['toast'] = ['type' => $type, 'message' => $message];
}

function get_toast(): ?array {
    $toast = $_SESSION['toast'] ?? null;
    unset($_SESSION['toast']);
    return $toast;
}

// --- Settings Helper ---------------------------------------------------------
function get_setting(string $key, string $default = ''): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        try {
            $stmt = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            $cache[$key] = $row ? (string)$row['setting_value'] : $default;
        } catch (PDOException $e) {
            $cache[$key] = $default;
        }
    }
    return $cache[$key];
}

// --- Student ID Generator ----------------------------------------------------
function generate_student_id(): string {
    $year  = date('Y');
    try {
        $count = (int)db()->query("SELECT COUNT(*) FROM students")->fetchColumn();
    } catch (PDOException $e) {
        $count = 0;
    }
    return 'STU-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

function generate_teacher_id(): string {
    $year  = date('Y');
    try {
        $count = (int)db()->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
    } catch (PDOException $e) {
        $count = 0;
    }
    return 'TCH-' . $year . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
}

// --- Grade Calculator --------------------------------------------------------
function calculate_grade(float $percentage): string {
    return match(true) {
        $percentage >= 90 => 'A+',
        $percentage >= 80 => 'A',
        $percentage >= 70 => 'B+',
        $percentage >= 60 => 'B',
        $percentage >= 50 => 'C',
        $percentage >= 40 => 'D',
        default           => 'F',
    };
}

// --- Pagination --------------------------------------------------------------
function paginate(int $total, int $perPage, int $currentPage): array {
    $totalPages = max(1, (int)ceil($total / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $currentPage,
        'total_pages' => $totalPages,
        'offset'      => ($currentPage - 1) * $perPage,
        'has_prev'    => $currentPage > 1,
        'has_next'    => $currentPage < $totalPages,
    ];
}
