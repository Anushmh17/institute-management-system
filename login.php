<?php
/**
 * Login Redirector
 * Since index.php is now the login page, this file redirects users to index.php
 * or to the dashboard if already logged in.
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . IMS_URL . '/dashboard.php');
} else {
    header('Location: ' . IMS_URL . '/index.php');
}
exit;
