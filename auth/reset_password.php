<?php
/**
 * auth/reset_password.php
 *
 * This page is no longer used. The password reset flow has been moved entirely
 * to forgot_password.php (OTP-only, single-page flow — no URL tokens required).
 *
 * Redirect any old/bookmarked links here back to the new flow.
 */
session_start();
header('Location: forgot_password.php');
exit;
