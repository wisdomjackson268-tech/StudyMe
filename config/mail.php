<?php
/**
 * StudyMe AI Platform — Mail Configuration Helper
 */

function send_app_mail($to, $subject, $body, $headers = []) {
    $from = defined('APP_NAME') ? APP_NAME . ' <support@studyme.ng>' : 'support@studyme.ng';
    $defaultHeaders = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: ' . $from,
        'Reply-To: ' . $from,
        'X-Mailer: PHP/' . phpversion()
    ];
    $finalHeaders = array_merge($defaultHeaders, $headers);
    return @mail($to, $subject, $body, implode("\r\n", $finalHeaders));
}
