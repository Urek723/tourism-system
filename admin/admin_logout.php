<?php
require_once '../db.php';
require_once '../functions.php';
require_once '../activity_logger.php';

start_secure_session();

if (is_logged_in()) {
    log_activity((int)$_SESSION['user_id'], 'admin_logout');
}

destroy_session();

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
}

session_name('TUPI_SESS');
session_start();
set_flash('success', 'You have been signed out of the admin panel.');

// admin_login.php is at root level — no 'admin/' prefix needed
redirect('admin_login.php');