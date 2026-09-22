<?php

if (!function_exists('send_app_mail')) {
    function send_app_mail($to, $subject, $body, $headers = []): bool {
        $fromEmail = defined('STUDYME_EMAIL_PRIMARY') ? STUDYME_EMAIL_PRIMARY : 'studyme910@gmail.com';
        $appName = defined('APP_NAME') ? APP_NAME : 'StudyMe';
        $from = $appName . ' <' . $fromEmail . '>';
        
        $defaultHeaders = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: ' . $from,
            'Reply-To: ' . $from,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        $finalHeaders = array_merge($defaultHeaders, $headers);
        $headerString = implode("\r\n", $finalHeaders);

        $smtpHost = function_exists('env') ? trim((string)env('SMTP_HOST', '')) : '';
        $smtpUser = function_exists('env') ? trim((string)env('SMTP_USER', $fromEmail)) : $fromEmail;
        $smtpPass = function_exists('env') ? (string)env('SMTP_PASS', '') : '';
        $smtpPort = (int)(function_exists('env') ? env('SMTP_PORT', 587) : 587);
        $smtpEncryption = strtolower(trim((string)(function_exists('env') ? env('SMTP_ENCRYPTION', 'tls') : 'tls')));
        $sent = false;
        $transport = 'native mail';
        $mailError = '';

        if ($smtpHost !== '' && $smtpUser !== '' && $smtpPass !== '') {
            $transport = 'SMTP';
            $socketAddress = ($smtpEncryption === 'ssl' ? 'ssl://' : '') . $smtpHost . ':' . $smtpPort;
            $socket = @stream_socket_client($socketAddress, $errorNumber, $errorMessage, 15);

            if ($socket !== false) {
                stream_set_timeout($socket, 15);
                $readResponse = static function ($socket): string {
                    $response = '';
                    while (($line = fgets($socket, 515)) !== false) {
                        $response .= $line;
                        if (strlen($line) < 4 || $line[3] !== '-') {
                            break;
                        }
                    }
                    return $response;
                };
                $sendCommand = static function ($socket, string $command) use ($readResponse): string {
                    fwrite($socket, $command . "\r\n");
                    return $readResponse($socket);
                };
                $isSuccess = static function (string $response, array $codes): bool {
                    return in_array((int)substr($response, 0, 3), $codes, true);
                };

                $greeting = $readResponse($socket);
                $ehlo = $sendCommand($socket, 'EHLO localhost');
                $tlsReady = $smtpEncryption !== 'tls' || $isSuccess($sendCommand($socket, 'STARTTLS'), [220]);
                if ($smtpEncryption === 'tls' && $tlsReady) {
                    $tlsReady = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) === true;
                    if ($tlsReady) {
                        $ehlo = $sendCommand($socket, 'EHLO localhost');
                    }
                }

                if ($greeting !== '' && $isSuccess($ehlo, [250]) && $tlsReady) {
                    $auth = $sendCommand($socket, 'AUTH LOGIN');
                    $authUser = $sendCommand($socket, base64_encode($smtpUser));
                    $authPass = $sendCommand($socket, base64_encode($smtpPass));
                    $mailFrom = $sendCommand($socket, 'MAIL FROM:<' . $fromEmail . '>');
                    $recipient = $sendCommand($socket, 'RCPT TO:<' . $to . '>');
                    $data = $sendCommand($socket, 'DATA');
                    $message = 'From: ' . $from . "\r\n"
                             . 'To: ' . $to . "\r\n"
                             . 'Subject: ' . $subject . "\r\n"
                             . $headerString . "\r\n\r\n"
                             . $body . "\r\n.";
                    $queued = $isSuccess($auth, [334])
                        && $isSuccess($authUser, [334])
                        && $isSuccess($authPass, [235])
                        && $isSuccess($mailFrom, [250])
                        && $isSuccess($recipient, [250, 251])
                        && $isSuccess($data, [354]);
                    if ($queued) {
                        fwrite($socket, $message . "\r\n");
                        $sent = $isSuccess($readResponse($socket), [250]);
                    }
                    $sendCommand($socket, 'QUIT');
                }
                fclose($socket);
            } else {
                $mailError = $errorMessage;
            }
        } else {
            $mailError = 'SMTP is not configured; using the server mail transport.';
            $sent = @mail($to, $subject, $body, $headerString);
        }

        try {
            $logDir = defined('STORAGE_PATH') ? STORAGE_PATH . '/logs' : dirname(__DIR__) . '/storage/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }
            $logFile = $logDir . '/mail.log';
            $logEntry = "[" . date('Y-m-d H:i:s') . "] TO: {$to} | SUBJECT: {$subject}\n"
                      . "HEADERS: {$headerString}\n"
                      . "BODY_PREVIEW: " . substr(strip_tags($body), 0, 200) . "...\n"
                      . "TRANSPORT: {$transport}\n"
                      . "STATUS: " . ($sent ? "SUCCESS" : "FAILED") . "\n"
                      . ($mailError !== '' ? "ERROR: {$mailError}\n" : '')
                      . str_repeat('-', 70) . "\n";
            @file_put_contents($logFile, $logEntry, FILE_APPEND);
        } catch (Throwable $e) {
        }

        return $sent;
    }
}
