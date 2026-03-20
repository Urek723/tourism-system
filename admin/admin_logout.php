<?php
/**
 * admin/admin_logout.php
 * Logs the action, destroys session, redirects to admin login.
 */

require_once '../db.php';
require_once '../functions.php';
require_once '../activity_logger.php';

start_secure_session();

if (is_logged_in()) {
    log_activity((int)$_SESSION['user_id'], 'admin_logout');
    destroy_session();
}

// Restart session just to set the flash message
start_secure_session();
set_flash('success', 'You have been signed out of the admin panel.');
redirect('admin_login.php');
