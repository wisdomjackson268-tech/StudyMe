<?php
function no_cache_headers(): void {
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    }
}

function secure_page($requiredRole = null): void {
    no_cache_headers();
    require_login();
    if ($requiredRole !== null) {
        require_role($requiredRole);
    }
}
