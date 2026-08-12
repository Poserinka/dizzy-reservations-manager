<?php

declare(strict_types=1);

namespace Dizzy\Reservations;

use RuntimeException;

defined('ABSPATH') || exit;

final class Mailer
{
    public function send(string $email, string $subject, string $message): bool
    {
        return wp_mail($email, $subject, wp_kses_post($message), ['Content-Type: text/html; charset=UTF-8']);
    }

    /**
     * Render an editable PHP/HTML email template.
     *
     * Templates live in includes/Email/Templates and receive the values in $data
     * both as individual variables and through the $data array.
     */
    public function sendTemplate(string $email, string $subject, string $template, array $data): bool
    {
        if (! preg_match('/^[a-z0-9-]+$/', $template)) {
            throw new RuntimeException('Invalid email template name.');
        }

        $path = DIZZY_RESERVATIONS_PATH . 'includes/Email/Templates/' . $template . '.php';

        if (! is_file($path)) {
            throw new RuntimeException('Reservation email template was not found: ' . $template);
        }

        $data = array_merge([
            'site_name' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
            'site_url' => home_url('/'),
        ], $data);

        extract($data, EXTR_SKIP);
        ob_start();
        include $path;
        $html = (string) ob_get_clean();

        return wp_mail(
            $email,
            $subject,
            $html,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }
}
