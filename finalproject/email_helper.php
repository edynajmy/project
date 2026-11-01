<?php
// email_helper.php
// Requires PHPMailer. Install in your project folder with:
// composer require phpmailer/phpmailer

function sendReporterEmail(string $toEmail, string $toName, string $subject, string $htmlBody): bool
{
    $autoloadCandidates = [
        __DIR__ . '/vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
    ];

    $autoload = null;
    foreach ($autoloadCandidates as $candidate) {
        if (is_file($candidate)) {
            $autoload = $candidate;
            break;
        }
    }

    if ($autoload === null) {
        error_log('Email not sent: PHPMailer vendor/autoload.php not found. Run composer require phpmailer/phpmailer.');
        return false;
    }

    require_once $autoload;
    $config = require __DIR__ . '/email_config.php';

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        $mail->Port       = (int)$config['port'];

        if (($config['encryption'] ?? '') === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

        return $mail->send();
    } catch (Throwable $e) {
        error_log('Reporter email error: ' . $e->getMessage());
        return false;
    }
}

function emailEscape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function buildReporterEmail(string $name, string $title, string $code, string $category, string $location, string $date, string $status, string $type, string $extraLabel = '', string $extraValue = ''): string
{
    $safeName = emailEscape($name !== '' ? $name : 'Reporter');
    $safeTitle = emailEscape($title);
    $safeCode = emailEscape($code);
    $safeCategory = emailEscape($category);
    $safeLocation = emailEscape($location);
    $safeDate = emailEscape($date);
    $safeStatus = emailEscape($status);
    $safeExtra = $extraLabel !== '' ? '<tr><td style="padding:8px 0;font-weight:600;">' . emailEscape($extraLabel) . '</td><td style="padding:8px 0;">' . emailEscape($extraValue) . '</td></tr>' : '';

    $intro = $type === 'Lost'
        ? 'Your lost item report has been successfully submitted to the Resort Lost &amp; Found Management System.'
        : 'Thank you for reporting a found item to the Resort Lost &amp; Found Management System.';

    $instruction = $type === 'Lost'
        ? 'You can log in to the system to track the status of your lost item report.'
        : 'Please hand the found item to resort staff for check-in so it can be processed and stored.';

    return '<!doctype html><html><body style="margin:0;background:#f4f6f9;font-family:Arial,sans-serif;color:#212529;">'
        . '<div style="max-width:620px;margin:30px auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e9ecef;">'
        . '<div style="background:#212529;color:#ffffff;padding:20px 24px;font-size:20px;font-weight:700;">Resort Lost &amp; Found Management System</div>'
        . '<div style="padding:24px;">'
        . '<p>Dear ' . $safeName . ',</p>'
        . '<p>' . $intro . '</p>'
        . '<div style="background:#f8f9fa;border-radius:8px;padding:16px;margin:20px 0;">'
        . '<table style="width:100%;border-collapse:collapse;">'
        . '<tr><td style="padding:8px 0;font-weight:600;">' . ($type === 'Lost' ? 'Reference Code' : 'Tracking Code') . '</td><td style="padding:8px 0;"><strong>' . $safeCode . '</strong></td></tr>'
        . '<tr><td style="padding:8px 0;font-weight:600;">Item</td><td style="padding:8px 0;">' . $safeTitle . '</td></tr>'
        . '<tr><td style="padding:8px 0;font-weight:600;">Category</td><td style="padding:8px 0;">' . $safeCategory . '</td></tr>'
        . '<tr><td style="padding:8px 0;font-weight:600;">Location</td><td style="padding:8px 0;">' . $safeLocation . '</td></tr>'
        . '<tr><td style="padding:8px 0;font-weight:600;">Date</td><td style="padding:8px 0;">' . $safeDate . '</td></tr>'
        . $safeExtra
        . '<tr><td style="padding:8px 0;font-weight:600;">Status</td><td style="padding:8px 0;">' . $safeStatus . '</td></tr>'
        . '</table></div>'
        . '<p>' . $instruction . '</p>'
        . '<p>Thank you.</p>'
        . '<p style="color:#6c757d;font-size:12px;margin-top:28px;">This is an automated email. Please do not reply directly to this message.</p>'
        . '</div></div></body></html>';
}


function buildClaimEmail(string $name, string $itemTitle, string $itemCode, string $category, string $locationFound, string $status, string $message, string $pickupLocation = ''): string
{
    $safeName = emailEscape($name !== '' ? $name : 'Reporter');
    $safeTitle = emailEscape($itemTitle);
    $safeCode = emailEscape($itemCode);
    $safeCategory = emailEscape($category);
    $safeLocationFound = emailEscape($locationFound);
    $safeStatus = emailEscape($status);
    $safeMessage = nl2br(emailEscape($message));
    $safePickupLocation = $pickupLocation !== ''
        ? '<tr><td style="padding:8px 0;font-weight:600;">Pickup Location</td><td style="padding:8px 0;">' . nl2br(emailEscape($pickupLocation)) . '</td></tr>'
        : '';

    return '<!doctype html><html><body style="margin:0;background:#f4f6f9;font-family:Arial,sans-serif;color:#212529;">'
        . '<div style="max-width:620px;margin:30px auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e9ecef;">'
        . '<div style="background:#212529;color:#ffffff;padding:20px 24px;font-size:20px;font-weight:700;">Resort Lost &amp; Found Management System</div>'
        . '<div style="padding:24px;">'
        . '<p>Dear ' . $safeName . ',</p>'
        . '<p>' . $safeMessage . '</p>'
        . '<div style="background:#f8f9fa;border-radius:8px;padding:16px;margin:20px 0;">'
        . '<table style="width:100%;border-collapse:collapse;">'
        . '<tr><td style="padding:8px 0;font-weight:600;">Item Code</td><td style="padding:8px 0;"><strong>' . $safeCode . '</strong></td></tr>'
        . '<tr><td style="padding:8px 0;font-weight:600;">Item</td><td style="padding:8px 0;">' . $safeTitle . '</td></tr>'
        . '<tr><td style="padding:8px 0;font-weight:600;">Category</td><td style="padding:8px 0;">' . $safeCategory . '</td></tr>'
        . '<tr><td style="padding:8px 0;font-weight:600;">Found At</td><td style="padding:8px 0;">' . $safeLocationFound . '</td></tr>'
        . $safePickupLocation
        . '<tr><td style="padding:8px 0;font-weight:600;">Claim Status</td><td style="padding:8px 0;"><strong>' . $safeStatus . '</strong></td></tr>'
        . '</table></div>'
        . '<p>If you have any questions, please contact the resort Lost &amp; Found staff.</p>'
        . '<p>Thank you.</p>'
        . '<p style="color:#6c757d;font-size:12px;margin-top:28px;">This is an automated email. Please do not reply directly to this message.</p>'
        . '</div></div></body></html>';
}
