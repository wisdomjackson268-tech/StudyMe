<?php

// Path Constants
if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__));
if (!defined('CONFIG_PATH')) define('CONFIG_PATH', BASE_PATH . '/config');
if (!defined('INCLUDES_PATH')) define('INCLUDES_PATH', BASE_PATH . '/includes');
if (!defined('STORAGE_PATH')) define('STORAGE_PATH', BASE_PATH . '/storage');
if (!defined('UPLOADS_PATH')) define('UPLOADS_PATH', BASE_PATH . '/uploads');

/**
 * Lightweight .env file loader for local development and staging.
 * Parses KEY=VALUE definitions, handles comments and quotes, and populates getenv, $_ENV, and $_SERVER.
 */
if (!function_exists('load_environment_file')) {
    function load_environment_file(string $filePath): void {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            // Ignore comments and blank lines
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Parse key=value
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            if ($key === '') {
                continue;
            }

            // Strip quotes if present
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            // Only set if not already set in system environment (server env takes priority)
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
            }
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
            if (!isset($_SERVER[$key])) {
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Load local .env file if it exists
load_environment_file(BASE_PATH . '/.env');

/**
 * Retrieve an environment variable value with fallback.
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $val = getenv($key);
        if ($val !== false) {
            return $val;
        }
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        return $default;
    }
}

// Application Constants
if (!defined('APP_NAME')) define('APP_NAME', env('APP_NAME', 'StudyMe'));
if (!defined('APP_URL')) define('APP_URL', env('APP_URL', 'http://localhost/StudyMe'));
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');
if (!defined('APP_ENV')) define('APP_ENV', env('APP_ENV', 'development'));

// Development & Free Testing Mode
// When FREE_TESTING_MODE is true, StudyMe operates as a free testing platform:
// all authenticated users (students, teachers, admins) have full access to dashboards and courses without payment.
if (!defined('FREE_TESTING_MODE')) define('FREE_TESTING_MODE', true);
if (!defined('DEVELOPMENT_MODE')) define('DEVELOPMENT_MODE', true);

// Official StudyMe Pricing (NGN)
// Tech: ₦10,000 | Secondary: ₦3,000 | University: ₦5,000 | Teacher Registration: ₦4,000
if (!defined('OFFICIAL_PRICE_TECH')) define('OFFICIAL_PRICE_TECH', 10000.00);
if (!defined('OFFICIAL_PRICE_SECONDARY')) define('OFFICIAL_PRICE_SECONDARY', 3000.00);
if (!defined('OFFICIAL_PRICE_UNIVERSITY')) define('OFFICIAL_PRICE_UNIVERSITY', 5000.00);
if (!defined('OFFICIAL_PRICE_TEACHER')) define('OFFICIAL_PRICE_TEACHER', 4000.00);

if (!defined('PAYMENT_MODE')) define('PAYMENT_MODE', 'demo'); // "demo" or "paystack"
if (!defined('DEMO_BANK_NAME')) define('DEMO_BANK_NAME', 'Demo Bank');
if (!defined('DEMO_BANK_ACCOUNT_NAME')) define('DEMO_BANK_ACCOUNT_NAME', 'StudyMe Demo Account');
if (!defined('DEMO_BANK_ACCOUNT_NUMBER')) define('DEMO_BANK_ACCOUNT_NUMBER', '0000000000');

// Timezone Configuration
date_default_timezone_set('Africa/Lagos');