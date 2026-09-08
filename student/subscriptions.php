<?php
/**
 * StudyMe AI Platform — Student Subscriptions View
 */
require_once dirname(__DIR__) . '/config/main.php';
secure_page(ROLE_STUDENT);

redirect('payments/history.php');
