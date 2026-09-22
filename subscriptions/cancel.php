<?php

require_once dirname(__DIR__) . '/config/main.php';

set_flash('info', 'To adjust course enrollments or update billing, please manage your account history or contact support.');
redirect('payments/history.php');
