<?php
require_once 'db.php';
require_once 'functions.php';
require_once 'activity_logger.php';

start_secure_session();

if (is_logged_in()) {
    log_activity((int)$_SESSION['user_id'], 'logout');
}

destroy_session();

// Cache-control headers prevent back-button bypass after session destroy
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
}

// Start fresh session only to carry the flash message
session_name('TUPI_SESS');
session_start();
set_flash('success', 'You have been signed out successfully.');

// redirect() prepends BASE_URL — do NOT pass BASE_URL here
redirect('login.php');