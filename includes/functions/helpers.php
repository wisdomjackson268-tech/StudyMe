<?php
/**
 * StudyMe AI-Powered Learning Platform - General Helper Functions
 */

/**
 * Escape output for safe HTML rendering.
 *
 * @param string|null $value
 * @return string
 */
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Alias for e() function.
 *
 * @param string|null $value
 * @return string
 */
function sanitize($value) {
    return e($value);
}

/**
 * Generate full URL relative to application root.
 *
 * @param string $path
 * @return string
 */
function url($path = '') {
    $cleanPath = ltrim($path, '/');

    // Prefer the configured APP_URL when it matches the current host.
    // If the configured APP_URL points to a different host than the current request,
    // return a host-relative path to avoid cross-host asset/redirect issues (useful during local dev).
    // Use the configured APP_URL when available to ensure correct subpath handling
    $configured = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
    if ($configured !== '') {
        return $cleanPath !== '' ? $configured . '/' . $cleanPath : $configured;
    }

    // Fallback to host-relative path if APP_URL is not configured
    $currentBase = '';
    if (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $currentBase = $scheme . '://' . $_SERVER['HTTP_HOST'];
    }
    return $cleanPath !== '' ? $currentBase . '/' . $cleanPath : ($currentBase ?: '/');
}

/**
 * Generate asset URL.
 *
 * @param string $path
 * @return string
 */
function asset($path) {
    return url('assets/' . ltrim($path, '/'));
}

/**
 * Safely redirect to a given relative or absolute URL.
 *
 * @param string $path
 * @return void
 */
function redirect($path) {
    if (!headers_sent()) {
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            header('Location: ' . $path);
        } else {
            header('Location: ' . url($path));
        }
        exit;
    }
    echo '<script>window.location.href="' . e(url($path)) . '";</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . e(url($path)) . '"></noscript>';
    exit;
}

/**
 * Check if current request method is POST.
 *
 * @return bool
 */
function is_post() {
    return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Check if current request method is GET.
 *
 * @return bool
 */
function is_get() {
    return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET';
}

/**
 * Set flash message in session.
 *
 * @param string $type ('success', 'error', 'warning', 'info')
 * @param string $message
 * @return void
 */
function set_flash($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        init_session();
    }
    $_SESSION['flash_messages'][$type][] = $message;
}

/**
 * Retrieve and clear flash messages from session.
 *
 * @param string|null $type
 * @return array
 */
function get_flash($type = null) {
    if (session_status() === PHP_SESSION_NONE) {
        init_session();
    }
    
    if (!isset($_SESSION['flash_messages'])) {
        return [];
    }

    $messages = $_SESSION['flash_messages'];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Resolve avatar image URL gracefully.
 * Supports absolute URLs, relative upload paths ('uploads/avatars/...', 'avatars/...'), and UI Avatars fallback.
 *
 * @param string|null $avatar
 * @param string $name
 * @return string
 */
function get_avatar_url($avatar = null, $name = 'User') {
    $avatar = trim($avatar ?? '');
    if (!empty($avatar)) {
        if (strpos($avatar, 'http://') === 0 || strpos($avatar, 'https://') === 0) {
            return $avatar;
        }
        $clean = ltrim($avatar, '/\\');
        if (strpos($clean, 'uploads/') === 0) {
            return url($clean);
        }
        return url('uploads/' . $clean);
    }
    $encodedName = urlencode($name ?: 'User');
    return "https://ui-avatars.com/api/?name={$encodedName}&background=4f46e5&color=ffffff&bold=true";
}

