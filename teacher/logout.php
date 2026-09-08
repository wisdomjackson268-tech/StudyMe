<?php
/**
 * Teacher Logout — Delegates to central auth/logout.php
 */
require_once dirname(__DIR__) . '/config/main.php';
redirect('auth/logout.php');
