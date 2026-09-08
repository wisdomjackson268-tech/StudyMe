<?php
// StudyMe AI-Powered Learning Platform Application Constants

// Free Testing Mode flag
if (!defined('FREE_TESTING_MODE')) define('FREE_TESTING_MODE', true);
if (!defined('DEVELOPMENT_MODE')) define('DEVELOPMENT_MODE', true);

// User Roles
if (!defined('ROLE_ADMIN')) define('ROLE_ADMIN', 'admin');
if (!defined('ROLE_TEACHER')) define('ROLE_TEACHER', 'teacher');
if (!defined('ROLE_STUDENT')) define('ROLE_STUDENT', 'student');

// User Statuses
if (!defined('STATUS_ACTIVE')) define('STATUS_ACTIVE', 'active');
if (!defined('STATUS_INACTIVE')) define('STATUS_INACTIVE', 'inactive');
if (!defined('STATUS_SUSPENDED')) define('STATUS_SUSPENDED', 'suspended');
if (!defined('STATUS_PENDING')) define('STATUS_PENDING', 'pending');

// Subscription Statuses
if (!defined('SUB_ACTIVE')) define('SUB_ACTIVE', 'active');
if (!defined('SUB_PENDING')) define('SUB_PENDING', 'pending');
if (!defined('SUB_EXPIRED')) define('SUB_EXPIRED', 'expired');
if (!defined('SUB_CANCELLED')) define('SUB_CANCELLED', 'cancelled');
if (!defined('SUB_UNPAID')) define('SUB_UNPAID', 'unpaid');

// Mandatory Pricing Constants (NGN)
if (!defined('PRICE_TECH')) define('PRICE_TECH', 10000.00);
if (!defined('PRICE_SECONDARY')) define('PRICE_SECONDARY', 3000.00);
if (!defined('PRICE_UNIVERSITY')) define('PRICE_UNIVERSITY', 4000.00);
if (!defined('PRICE_TEACHER')) define('PRICE_TEACHER', 5000.00);

// Session Keys
if (!defined('SESSION_USER_ID')) define('SESSION_USER_ID', 'user_id');
if (!defined('SESSION_USER_ROLE')) define('SESSION_USER_ROLE', 'user_role');
if (!defined('SESSION_USER_DATA')) define('SESSION_USER_DATA', 'user');
if (!defined('SESSION_FLASH')) define('SESSION_FLASH', 'flash_messages');
