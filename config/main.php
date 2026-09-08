<?php
/**
 * StudyMe AI-Powered Learning Platform - Central Application Bootstrap File
 * 
 * Future PHP pages can include this single file:
 * require_once __DIR__ . '/config/main.php'; (or relative path to config/main.php)
 */

// Prevent duplicate initialization
if (defined('STUDYME_BOOTSTRAPPED')) {
    return;
}
define('STUDYME_BOOTSTRAPPED', true);

// 1. Load Application Configuration & Path Constants
require_once __DIR__ . '/app.php';

// 2. Load Application Constants (Roles, Statuses, Session Keys)
require_once __DIR__ . '/constants.php';

// 2b. Load AI Assistant Configuration
require_once __DIR__ . '/ai.php';

// 3. Centralized Development Error Handling
if (defined('APP_ENV') && APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

// 4. Load Database Connection Setup ($pdo and getDBConnection())
require_once __DIR__ . '/database.php';

// 5. Load Helper Functions
require_once BASE_PATH . '/includes/functions/helpers.php';

// 6. Load Auth & Session Functions
require_once BASE_PATH . '/includes/functions/auth.php';

// 7. Load Subscription & Payment Functions
require_once BASE_PATH . '/includes/functions/subscription.php';

// 8. Load Security & Cache-Control Helpers (no_cache_headers, secure_page)
require_once BASE_PATH . '/includes/functions/security.php';

// 9. Load Pricing, Activity, SEO, Referral, Announcement, Courses & Enrollment Functions
require_once BASE_PATH . '/includes/functions/pricing.php';
require_once BASE_PATH . '/includes/functions/courses.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';
require_once BASE_PATH . '/includes/functions/activity.php';
require_once BASE_PATH . '/includes/functions/seo.php';
require_once BASE_PATH . '/includes/functions/referrals.php';
require_once BASE_PATH . '/includes/functions/announcements.php';
require_once BASE_PATH . '/includes/functions/discussions.php';

// 10. Initialize Secure Session Foundation
init_session();
