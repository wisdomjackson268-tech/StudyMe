<?php
/**
 * StudyMe AI Platform — Security Helpers
 *
 * Include this at the top of every protected dashboard page to:
 * 1. Send no-cache headers preventing browser back-button access after logout
 * 2. Enforce authentication (redirects to login if no session)
 * 3. Optionally enforce a specific role
 *
 * Usage:
 *   require_once BASE_PATH . '/includes/functions/security.php';
 *   secure_page();              // Requires any logged-in user
 *   secure_page('student');     // Requires student role
 *   secure_page('teacher');     // Requires teacher role
 *   secure_page('admin');       // Requires admin role
 *   secure_page(['admin','teacher']); // Multiple allowed roles
 */

/**
 * Send HTTP headers that prevent the browser from caching authenticated pages.
 * This ensures that pressing Back after logout does NOT reveal dashboard content.
 *
 * @return void
 */
function no_cache_headers(): void {
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    }
}

/**
 * Secure a protected page: send no-cache headers, enforce login, and
 * optionally enforce a required role (or list of roles).
 *
 * @param string|array|null $requiredRole  Role(s) allowed. null = any authenticated user.
 * @return void
 */
function secure_page($requiredRole = null): void {
    // 1. Prevent browser caching of protected content
    no_cache_headers();

    // 2. Must be logged in
    require_login();

    // 3. If a specific role is required, enforce it
    if ($requiredRole !== null) {
        require_role($requiredRole);
    }
}
