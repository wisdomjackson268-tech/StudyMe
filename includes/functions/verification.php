<?php
function generate_email_verification_code(int $userId, string $email, int $expiryMinutes = 15): string {
    $pdo = getDBConnection();
    $code = (string)random_int(100000, 999999);
    try {
        $stmt = $pdo->prepare("UPDATE email_verifications SET is_used = 2 WHERE email = ? AND is_used = 0");
        $stmt->execute([$email]);
    } catch (Throwable $e) {
    }
    $stmt = $pdo->prepare("
        INSERT INTO email_verifications (user_id, email, code, expires_at, created_at, is_used)
        VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW(), 0)
    ");
    $stmt->execute([$userId, $email, $code, $expiryMinutes]);
    return $code;
}

function check_resend_cooldown(string $email, int $cooldownSeconds = 60): array {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT created_at, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS elapsed
            FROM email_verifications
            WHERE email = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $last = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($last && isset($last['elapsed'])) {
            $elapsed = (int)$last['elapsed'];
            if ($elapsed < $cooldownSeconds) {
                return [
                    'allowed'           => false,
                    'seconds_remaining' => $cooldownSeconds - $elapsed
                ];
            }
        }
    } catch (Throwable $e) {
    }
    return [
        'allowed'           => true,
        'seconds_remaining' => 0
    ];
}

function send_verification_email(string $email, string $firstName, string $code): bool {
    $appName = defined('APP_NAME') ? APP_NAME : 'StudyMe';
    $appUrl  = defined('APP_URL') ? APP_URL : 'http://localhost/StudyMe';
    $directLink = rtrim($appUrl, '/') . '/auth/verify-email.php?code=' . urlencode($code) . '&email=' . urlencode($email);
    $subject = "Verify Your {$appName} Account — Code: {$code}";

    $body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your {$appName} Account</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f8fafc; padding: 40px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width: 540px; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0;">
                    <tr>
                        <td style="background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); padding: 32px 32px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 26px; font-weight: 800; letter-spacing: -0.5px;">{$appName}</h1>
                            <p style="margin: 6px 0 0 0; color: #bfdbfe; font-size: 14px; font-weight: 500;">AI-Powered Learning Platform</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 36px 32px;">
                            <h2 style="margin: 0 0 16px 0; font-size: 20px; font-weight: 700; color: #0f172a;">Welcome, {$firstName}!</h2>
                            <p style="margin: 0 0 24px 0; font-size: 15px; line-height: 1.6; color: #475569;">
                                Thank you for registering with <strong>{$appName}</strong>. To complete your account activation and access your courses and AI tutor, please use the 6-digit verification code below:
                            </p>
                            <div style="background: #f1f5f9; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 20px; text-align: center; margin: 0 0 28px 0;">
                                <span style="font-family: 'Courier New', Courier, monospace; font-size: 36px; font-weight: 800; letter-spacing: 8px; color: #1e40af; display: inline-block;">{$code}</span>
                                <div style="margin-top: 8px; font-size: 13px; color: #64748b;">Valid for 15 minutes</div>
                            </div>
                            <div style="text-align: center; margin: 0 0 28px 0;">
                                <a href="{$directLink}" style="display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 700; padding: 14px 32px; border-radius: 9999px; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);">
                                    Confirm Email Instantly &rarr;
                                </a>
                            </div>
                            <p style="margin: 0 0 12px 0; font-size: 13px; line-height: 1.5; color: #64748b;">
                                Or copy and paste this verification URL into your browser:<br>
                                <a href="{$directLink}" style="color: #2563eb; word-break: break-all;">{$directLink}</a>
                            </p>
                            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 28px 0;">
                            <p style="margin: 0; font-size: 12px; line-height: 1.5; color: #94a3b8;">
                                If you did not create a {$appName} account, you can safely ignore this message. Someone may have entered your email by mistake.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #f8fafc; padding: 20px 32px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 12px; color: #94a3b8;">
                            &copy; {$appName} — Empowering students with modern education &amp; AI tutoring.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

    return send_app_mail($email, $subject, $body);
}

function verify_email_code(string $email, string $code): array {
    $email = trim(strtolower($email));
    $code  = trim(preg_replace('/[^0-9]/', '', $code));

    if (empty($email) || empty($code) || strlen($code) !== 6) {
        return [
            'success' => false,
            'user_id' => null,
            'error'   => 'invalid_format',
            'message' => 'Please enter the complete 6-digit verification code.'
        ];
    }

    $pdo = getDBConnection();

    $stmt = $pdo->prepare("
        SELECT id, user_id, email, code, expires_at, is_used,
               CASE WHEN expires_at < NOW() THEN 1 ELSE 0 END AS is_expired
        FROM email_verifications
        WHERE email = ? AND code = ? AND is_used = 0
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$email, $code]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        return [
            'success' => false,
            'user_id' => null,
            'error'   => 'invalid_code',
            'message' => 'The verification code is invalid or has already been used. Please check your email or request a new code.'
        ];
    }

    if ((int)$record['is_expired'] === 1) {
        return [
            'success' => false,
            'user_id' => (int)$record['user_id'],
            'error'   => 'expired',
            'message' => 'This verification code has expired (codes expire after 15 minutes). Please click "Resend Code" to receive a new one.'
        ];
    }

    $userId = (int)$record['user_id'];

    try {
        $pdo->beginTransaction();

        $pdo->prepare("UPDATE email_verifications SET is_used = 1 WHERE id = ?")
            ->execute([$record['id']]);

        $pdo->prepare("UPDATE users SET email_verified_at = NOW(), status = IF(status='pending', 'active', status) WHERE id = ?")
            ->execute([$userId]);

        $pdo->commit();

        if (function_exists('log_user_activity')) {
            log_user_activity($userId, 'email_verified', 'User verified email address: ' . $email);
        }

        return [
            'success' => true,
            'user_id' => $userId,
            'error'   => null,
            'message' => 'Your email has been verified successfully! Welcome to StudyMe.'
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [
            'success' => false,
            'user_id' => null,
            'error'   => 'database_error',
            'message' => 'An error occurred while activating your account. Please try again.'
        ];
    }
}

function resend_email_verification_code(string $email): array {
    $email = trim(strtolower($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'error'   => 'invalid_email',
            'message' => 'Please provide a valid email address.',
            'seconds_remaining' => 0
        ];
    }

    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, first_name, email_verified_at, status FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return [
            'success' => true,
            'error'   => null,
            'message' => 'If an account exists for this email, a new verification code has been dispatched.',
            'seconds_remaining' => 60
        ];
    }

    if (!empty($user['email_verified_at'])) {
        return [
            'success' => true,
            'already_verified' => true,
            'error'   => null,
            'message' => 'This account is already verified. You can log in directly.',
            'seconds_remaining' => 0
        ];
    }

    $cooldown = check_resend_cooldown($email, 60);
    if (!$cooldown['allowed']) {
        return [
            'success' => false,
            'error'   => 'cooldown',
            'message' => "Please wait {$cooldown['seconds_remaining']} seconds before requesting another code.",
            'seconds_remaining' => $cooldown['seconds_remaining']
        ];
    }

    $code = generate_email_verification_code((int)$user['id'], $email, 15);
    $sent = send_verification_email($email, $user['first_name'], $code);

    if (!$sent) {
        return [
            'success' => false,
            'error' => 'mail_failed',
            'message' => 'The verification email could not be sent. Please check the mail configuration and try again.',
            'seconds_remaining' => 0
        ];
    }

    return [
        'success' => true,
        'error'   => null,
        'message' => "A new 6-digit verification code has been sent to {$email}.",
        'seconds_remaining' => 60
    ];
}
