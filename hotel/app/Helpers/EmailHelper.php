<?php
namespace App\Helpers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Simple wrapper around PHPMailer for sending transactional emails.
 * Uses environment variables (via vlucas/phpdotenv) for configuration.
 */
class EmailHelper
{
    private static ?PHPMailer $mailer = null;

    private static function init(): void
    {
        if (self::$mailer !== null) {
            return;
        }
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = getenv('MAIL_HOST');
            $mail->SMTPAuth   = true;
            $mail->Username   = getenv('MAIL_USERNAME');
            $mail->Password   = getenv('MAIL_PASSWORD');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int)getenv('MAIL_PORT');

            // Sender info
            $mail->setFrom(getenv('MAIL_FROM'), getenv('MAIL_FROM_NAME'));
            $mail->isHTML(true);
            self::$mailer = $mail;
        } catch (Exception $e) {
            // In production you would log this rather than die.
            die('Email configuration error: ' . $e->getMessage());
        }
    }

    /**
     * Send an email.
     * @param string $to      Recipient email address
     * @param string $subject Subject line
     * @param string $htmlBody HTML body (plain‑text fallback is optional)
     * @return bool           True on success, false on failure
     */
    public static function send(string $to, string $subject, string $htmlBody): bool
    {
        self::init();
        try {
            $mail = clone self::$mailer; // clone to avoid overriding previous recipients
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);
            return $mail->send();
        } catch (Exception $e) {
            // Log error in real app
            return false;
        }
    }
}
?>
