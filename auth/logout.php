<?php
/**
 * auth/logout.php
 * Destroys session and redirects to login.
 */
require_once '../config/db.php';
session_start();
session_unset();
session_destroy();
header('Location: ' . BASE_PATH . '/auth/login.php?msg=logged_out');
exit;
