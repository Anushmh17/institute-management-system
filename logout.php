<?php
require_once __DIR__ . '/includes/auth.php';
logout();
set_toast('info', 'You have been logged out successfully.');
header('Location: ' . IMS_URL . '/index.php');
exit;
