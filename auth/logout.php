<?php
/**
 * auth/logout.php
 * Destroys session and redirects to login.
 */
session_start();
session_unset();
session_destroy();
header('Location: /PARE/auth/login.php?msg=logged_out');
exit;
