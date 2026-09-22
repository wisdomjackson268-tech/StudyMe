<?php

require_once dirname(__DIR__) . '/config/main.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

if (empty($_SESSION['logout_confirmed'])) {
    redirect('index.php');
}

$userName = $_SESSION['logout_user_name'] ?? '';

unset($_SESSION['logout_confirmed'], $_SESSION['logout_user_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out — StudyMe AI-Powered Learning</title>
    <meta name="description" content="You have been securely logged out of StudyMe.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary: #2563EB;
            --gold: #F59E0B;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #070B15 0%, #0F172A 60%, #1E293B 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        .logout-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 56px 48px;
            text-align: center;
            max-width: 480px;
            width: 100%;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            box-shadow: 0 32px 64px rgba(0,0,0,0.4);
        }
        .check-ring {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: rgba(16,185,129,0.15);
            border: 2px solid rgba(16,185,129,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 28px;
            animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
        }
        @keyframes popIn {
            0% { transform: scale(0.5); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .brand {
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            margin-bottom: 32px;
            opacity: 0.7;
        }
        .brand span { color: var(--gold); }
        h1 { font-size: 1.75rem; font-weight: 800; margin-bottom: 12px; }
        p { color: rgba(255,255,255,0.65); margin-bottom: 36px; line-height: 1.7; }
        .btn-login {
            background: linear-gradient(135deg, #2563EB, #3B82F6);
            color: #fff;
            border: 0;
            border-radius: 50px;
            padding: 14px 36px;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: transform .25s, box-shadow .25s;
            box-shadow: 0 8px 24px rgba(37,99,235,0.4);
        }
        .btn-login:hover { transform: translateY(-3px); box-shadow: 0 14px 32px rgba(37,99,235,0.5); color: #fff; }
        .btn-home {
            color: rgba(255,255,255,0.5);
            text-decoration: none;
            font-size: 0.9rem;
            margin-top: 16px;
            display: block;
            transition: color .2s;
        }
        .btn-home:hover { color: rgba(255,255,255,0.9); }
        .divider {
            border-color: rgba(255,255,255,0.1) !important;
            margin: 28px 0;
        }
        .security-note {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
    </style>
</head>
<body>
    <div class="logout-card">
        <div class="brand">Study<span>Me</span> &mdash; AI-Powered Learning</div>

        <div class="check-ring">
            <i class="bi bi-check-lg text-success" style="font-size: 2.5rem;"></i>
        </div>

        <h1>Logged Out Successfully</h1>
        <p>
            <?php if ($userName): ?>
                Goodbye, <strong><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></strong>!<br>
            <?php endif; ?>
            Your StudyMe session has been <strong>securely ended</strong>.<br>
            All authentication tokens have been cleared.
        </p>

        <a href="<?= rtrim(defined('APP_URL') ? APP_URL : 'http://localhost/StudyMe', '/') ?>/auth/login.php" class="btn-login" id="loginAgainBtn">
            <i class="bi bi-box-arrow-in-right"></i> Login Again
        </a>

        <a href="<?= rtrim(defined('APP_URL') ? APP_URL : 'http://localhost/StudyMe', '/') ?>/index.php" class="btn-home">
            <i class="bi bi-house-fill me-1"></i> Return to Homepage
        </a>

        <hr class="divider">

        <div class="security-note">
            <i class="bi bi-shield-lock-fill"></i>
            For your security, close this browser tab when done.
        </div>
    </div>

    <script src="<?= rtrim(defined('APP_URL') ? APP_URL : 'http://localhost/StudyMe', '/') ?>/assets/js/feedback.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof StudyMeFeedback !== 'undefined') {
                StudyMeFeedback.notification();
            }
        });
    </script>
</body>
</html>
