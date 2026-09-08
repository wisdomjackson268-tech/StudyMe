<?php
/**
 * StudyMe AI Platform — Teacher Guard Middleware
 */
require_once dirname(__DIR__, 2) . '/config/main.php';
require_role([ROLE_TEACHER, ROLE_ADMIN]);
