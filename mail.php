<?php
/**
 * Gmail SMTP configuration for the Digital Banking/Bank Queue system.
 * Use a Google App Password, NOT the normal Gmail password.
 */

const MAIL_HOST = 'smtp.gmail.com';
const MAIL_PORT = 465; // Gmail implicit TLS (SSL)
const MAIL_USERNAME = 'shimelisdamesa8@gmail.com';
const MAIL_PASSWORD = 'choatjgosyhteupa';
const MAIL_FROM_EMAIL = 'shimelisdamesa8@gmail.com';
const MAIL_FROM_NAME = 'Berhan Bank';
const APP_URL = 'http://10.160.12.100/customer';

function send_smtp_mail(string $to, string $subject, string $html): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        mail_log('Invalid recipient email: ' . $to);
        return false;
    }

    // Environment variables take priority; the existing project configuration remains the fallback.
    $username = trim((string)getenv('MAIL_USERNAME')) ?: MAIL_USERNAME;
    $password = trim((string)getenv('MAIL_PASSWORD')) ?: MAIL_PASSWORD;
    $fromEmail = trim((string)getenv('MAIL_FROM_EMAIL')) ?: MAIL_FROM_EMAIL;
    $fromNameValue = trim((string)getenv('MAIL_FROM_NAME')) ?: MAIL_FROM_NAME;

    if (!filter_var($username, FILTER_VALIDATE_EMAIL) || $password === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        mail_log('SMTP is not configured. Set a valid Gmail address and Gmail App Password in config/mail.php or environment variables.');
        return false;
    }

    /*
     * Gmail supports:
     *   465 = implicit TLS
     *   587 = STARTTLS
     * Try 465 first, then 587. This makes the project work on more local
     * XAMPP/Windows/OpenSSL configurations without changing the workflow.
     */
    $attempts = [
        ['mode' => 'ssl', 'host' => 'ssl://' . MAIL_HOST . ':465'],
        ['mode' => 'starttls', 'host' => 'tcp://' . MAIL_HOST . ':587']
    ];

    foreach ($attempts as $attempt) {
        $socket = null;

        try {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                    'peer_name' => MAIL_HOST,
                    'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT
                ]
            ]);

            $socket = @stream_socket_client(
                $attempt['host'],
                $errno,
                $errstr,
                20,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$socket) {
                mail_log("SMTP {$attempt['mode']} connection failed: [$errno] $errstr");
                continue;
            }

            stream_set_timeout($socket, 20);

            $read = function() use ($socket): string {
                $data = '';
                while (($line = fgets($socket, 515)) !== false) {
                    $data .= $line;
                    if (strlen($line) >= 4 && $line[3] === ' ') {
                        break;
                    }
                }
                return $data;
            };

            $command = function(string $cmd) use ($socket, $read): array {
                if (fwrite($socket, $cmd . "\r\n") === false) {
                    return [0, ''];
                }
                $response = $read();
                return [(int)substr($response, 0, 3), $response];
            };

            $response = $read();
            if ((int)substr($response, 0, 3) !== 220) {
                throw new RuntimeException('SMTP greeting failed: ' . $response);
            }

            [$code, $response] = $command('EHLO localhost');
            if ($code !== 250) {
                throw new RuntimeException('EHLO failed: ' . $response);
            }

            if ($attempt['mode'] === 'starttls') {
                [$code, $response] = $command('STARTTLS');
                if ($code !== 220) {
                    throw new RuntimeException('STARTTLS failed: ' . $response);
                }

                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS TLS negotiation failed.');
                }

                [$code, $response] = $command('EHLO localhost');
                if ($code !== 250) {
                    throw new RuntimeException('EHLO after STARTTLS failed: ' . $response);
                }
            }

            [$code, $response] = $command('AUTH LOGIN');
            if ($code !== 334) {
                throw new RuntimeException('SMTP AUTH LOGIN failed: ' . $response);
            }

            [$code, $response] = $command(base64_encode($username));
            if ($code !== 334) {
                throw new RuntimeException('SMTP username rejected: ' . $response);
            }

            [$code, $response] = $command(base64_encode($password));
            if ($code !== 235) {
                throw new RuntimeException('SMTP App Password rejected: ' . $response);
            }

            [$code, $response] = $command('MAIL FROM:<' . $fromEmail . '>');
            if ($code !== 250) {
                throw new RuntimeException('MAIL FROM failed: ' . $response);
            }

            [$code, $response] = $command('RCPT TO:<' . $to . '>');
            if (!in_array($code, [250, 251], true)) {
                throw new RuntimeException('RCPT TO failed: ' . $response);
            }

            [$code, $response] = $command('DATA');
            if ($code !== 354) {
                throw new RuntimeException('DATA failed: ' . $response);
            }

            $safeSubject = function_exists('mb_encode_mimeheader')
                ? mb_encode_mimeheader($subject, 'UTF-8')
                : '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $fromName = function_exists('mb_encode_mimeheader')
                ? mb_encode_mimeheader($fromNameValue, 'UTF-8')
                : '=?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?=';

            $headers = "From: {$fromName} <" . $fromEmail . ">\r\n"
                . "To: <{$to}>\r\n"
                . "Subject: {$safeSubject}\r\n"
                . "MIME-Version: 1.0\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n";

            $body = preg_replace('/^\./m', '..', $html);
            if (fwrite($socket, $headers . $body . "\r\n.\r\n") === false) {
                throw new RuntimeException('Could not write email DATA.');
            }

            $response = $read();
            $code = (int)substr($response, 0, 3);
            if ($code !== 250) {
                throw new RuntimeException('Message rejected: ' . $response);
            }

            $command('QUIT');
            fclose($socket);
            return true;

        } catch (Throwable $e) {
            mail_log("SMTP {$attempt['mode']} attempt: " . $e->getMessage());
            if (is_resource($socket)) {
                @fwrite($socket, "QUIT\r\n");
                @fclose($socket);
            }
        }
    }

    return false;
}

function mail_log(string $message): void
{
    $file = __DIR__ . '/mail_error.log';
    @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function app_url(string $path = ''): string
{
    return rtrim((string)(getenv('APP_URL') ?: APP_URL), '/') . '/' . ltrim($path, '/');
}
