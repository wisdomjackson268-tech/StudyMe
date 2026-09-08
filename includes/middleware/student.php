<?php
/**
 * StudyMe AI Platform — Student Guard Middleware
 */
require_once dirname(__DIR__, 2) . '/config/main.php';
require_role([ROLE_STUDENT, ROLE_ADMIN]);
