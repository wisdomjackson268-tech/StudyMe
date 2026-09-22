<?php
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function sanitize($value) {
    return e($value);
}

function url($path = '') {
    $cleanPath = ltrim($path, '/');
    $configured = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
    if ($configured !== '') {
        return $cleanPath !== '' ? $configured . '/' . $cleanPath : $configured;
    }
    $currentBase = '';
    if (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $currentBase = $scheme . '://' . $_SERVER['HTTP_HOST'];
    }
    return $cleanPath !== '' ? $currentBase . '/' . $cleanPath : ($currentBase ?: '/');
}

function asset($path) {
    return url('assets/' . ltrim($path, '/'));
}

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

function is_post() {
    return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
}

function is_get() {
    return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET';
}

function set_flash($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        init_session();
    }
    $_SESSION['flash_messages'][$type][] = $message;
}

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

function get_teacher_avatar_url($avatar = null, $name = 'Instructor', $id = 0) {
    $avatar = trim($avatar ?? '');
    $genericPlaceholder = 'photo-1573496359142-b8d87734a5a2';

    if (!empty($avatar) && strpos($avatar, $genericPlaceholder) === false) {
        if (strpos($avatar, 'http://') === 0 || strpos($avatar, 'https://') === 0) {
            return $avatar;
        }
        $clean = ltrim($avatar, '/\\');
        if (strpos($clean, 'uploads/') === 0) {
            return url($clean);
        }
        return url('uploads/' . $clean);
    }

    static $namedPortraits = [
        'sarah'   => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=400&q=80',
        'alex'    => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&q=80',
        'michael' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&q=80',
        'amanda'  => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=400&q=80',
        'marcus'  => 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&q=80',
        'fatima'  => 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?w=400&q=80',
        'john'    => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=400&q=80',
        'david'   => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=400&q=80',
        'emily'   => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=400&q=80',
    ];

    $cleanName = strtolower(trim($name));
    foreach ($namedPortraits as $key => $portraitUrl) {
        if (strpos($cleanName, $key) !== false) {
            return $portraitUrl;
        }
    }

    $roster = [
        'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=400&q=80',
        'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&q=80',
        'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&q=80',
        'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=400&q=80',
        'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&q=80',
        'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?w=400&q=80',
        'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=400&q=80',
        'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=400&q=80',
    ];

    $index = abs((int)$id ?: crc32($name)) % count($roster);
    return $roster[$index];
}
