<?php
/**
 * logout.php  [UPDATED v4]
 * Added: activity log before session is destroyed.
 */

require_once 'db.php';
require_once 'functions.php';
require_once 'activity_logger.php';

start_secure_session();

if (is_logged_in()) {
    log_activity((int)$_SESSION['user_id'], 'logout');
    destroy_session();
}

start_secure_session();
set_flash('success', 'You have been signed out successfully.');
redirect('login.php');