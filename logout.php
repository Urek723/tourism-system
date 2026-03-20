<?php
/**
 * logout.php
 * Logs the action, destroys session, redirects to login.
 */

require_once 'db.php';
require_once 'functions.php';
require_once 'activity_logger.php';

start_secure_session();

if (is_logged_in()) {
    log_activity((int)$_SESSION['user_id'], 'logout');
    destroy_session();
}

// Explicit no-cache headers after destroy — prevents back-button bypass
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
}

start_secure_session();
set_flash('success', 'You have been signed out successfully.');
redirect(BASE_URL . 'login.php');