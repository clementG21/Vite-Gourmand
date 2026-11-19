<?php
declare(strict_types=1);

// Envoi de mail simple. Pour la production, remplacer par PHPMailer + SMTP.
function send_mail(string $to, string $subject, string $body, ?string $replyTo = null): bool {
    // Anti injection d'en-têtes : on retire tout retour à la ligne
    $to      = str_replace(["\r", "\n"], '', $to);
    $subject = str_replace(["\r", "\n"], ' ', $subject);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $from = config()['mail_from'];
    $headers = "From: Vite & Gourmand <$from>\r\nContent-Type: text/plain; charset=UTF-8";
    if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers .= "\r\nReply-To: " . str_replace(["\r", "\n"], '', $replyTo);
    }
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}
