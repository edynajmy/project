<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

function sendEmail($toEmail, $toName, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        // Debug OFF
        $mail->SMTPDebug = 0;

        // SMTP Gmail
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;

        // Tukar kepada Gmail sender kau
        $mail->Username   = 'zameelakmal21@gmail.com';

        // Tukar kepada Google App Password, bukan password Gmail biasa
        // Buang semua space dalam app password
        $mail->Password   = 'hochexehfdlmniee';

        // Since port 587 works on your laptop
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Extra settings for localhost/XAMPP
        $mail->SMTPAutoTLS = true;
        $mail->Timeout = 8;
        $mail->SMTPKeepAlive = false;

        // Sender and receiver
        $mail->setFrom('emailkau@gmail.com', 'TraceIoT System');
        $mail->addAddress($toEmail, $toName);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true;

    } catch (Exception $e) {
        echo "<script>alert('Email Error: " . addslashes($mail->ErrorInfo) . "');</script>";
        return false;
    }
}
?>

