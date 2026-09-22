<?php

if (defined('STUDYME_BOOTSTRAPPED')) {
    return;
}
define('STUDYME_BOOTSTRAPPED', true);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/ai.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/aloc.php';

if (defined('APP_ENV') && APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

require_once __DIR__ . '/database.php';
require_once BASE_PATH . '/includes/functions/helpers.php';
require_once BASE_PATH . '/includes/functions/auth.php';
require_once BASE_PATH . '/includes/functions/subscription.php';
require_once BASE_PATH . '/includes/functions/security.php';
require_once BASE_PATH . '/includes/functions/pricing.php';
require_once BASE_PATH . '/includes/functions/courses.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';
require_once BASE_PATH . '/includes/functions/activity.php';
require_once BASE_PATH . '/includes/functions/seo.php';
require_once BASE_PATH . '/includes/functions/referrals.php';
require_once BASE_PATH . '/includes/functions/announcements.php';
require_once BASE_PATH . '/includes/functions/discussions.php';
require_once BASE_PATH . '/includes/functions/verification.php';
require_once BASE_PATH . '/includes/functions/aloc.php';

init_session();
