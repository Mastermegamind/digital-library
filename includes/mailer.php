<?php
// includes/mailer.php
require_once __DIR__ . '/config.php';

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

function mailer_is_configured(): bool {
    global $MAILER_DSN, $MAIL_FROM_ADDRESS;
    return !empty($MAILER_DSN) && !empty($MAIL_FROM_ADDRESS);
}

function send_app_mail(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool {
    global $MAILER_DSN, $MAIL_FROM_ADDRESS, $MAIL_FROM_NAME;

    if (empty($MAILER_DSN) || empty($MAIL_FROM_ADDRESS)) {
        log_warning('Mailer not configured', ['to' => $to, 'subject' => $subject]);
        return false;
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoload)) {
        log_warning('Mailer autoload missing', ['path' => $autoload]);
        return false;
    }

    require_once $autoload;

    try {
        $transport = Transport::fromDsn($MAILER_DSN);
        $mailer = new Mailer($transport);
        $email = (new Email())
            ->from(sprintf('%s <%s>', $MAIL_FROM_NAME ?: 'E-Library', $MAIL_FROM_ADDRESS))
            ->to($to)
            ->subject($subject)
            ->html($htmlBody);

        if ($textBody !== null && $textBody !== '') {
            $email->text($textBody);
        }

        $mailer->send($email);
        log_info('Email sent', ['to' => $to, 'subject' => $subject]);
        return true;
    } catch (Throwable $e) {
        log_error('Email send failed', ['message' => $e->getMessage()]);
        return false;
    }
}
