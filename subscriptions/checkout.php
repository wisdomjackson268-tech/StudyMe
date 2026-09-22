<?php

require_once dirname(__DIR__) . '/config/main.php';

$planId = (int)($_GET['plan_id'] ?? 0);
if ($planId > 0) {
    redirect('payments/checkout.php?plan_id=' . $planId);
} else {
    redirect('pricing.php');
}
