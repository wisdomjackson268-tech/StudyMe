<?php
/**
 * StudyMe AI Platform — Admin Guard Middleware
 */
require_once dirname(__DIR__, 2) . '/config/main.php';
require_role(ROLE_ADMIN);
