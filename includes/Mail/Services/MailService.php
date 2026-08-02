<?php

declare(strict_types=1);

namespace Dizzy\Events\Mail\Services;

defined('ABSPATH') || exit;

final class MailService
{
    public function send(string $to, string $subject, string $message, array $headers = []): bool
    {
        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        return wp_mail(
            $to,
            $subject,
            wpautop($message),
            $headers
        );
    }

    public function sendTemplate(string $to, string $subject, string $template, array $data = []): bool
    {
        return $this->send(
            $to,
            $subject,
            $this->renderTemplate($template, $data)
        );
    }

    private function renderTemplate(string $template, array $data): string
    {
        $path = dirname(__DIR__) . '/Templates/' . $template . '.php';

        if (! file_exists($path)) {
            return '';
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $path;

        return (string) ob_get_clean();
    }
}
