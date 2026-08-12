<?php

declare(strict_types=1);

namespace Dizzy\Reservations;

use Throwable;

defined('ABSPATH') || exit;

final class FrontendController
{
    public function __construct(private ReservationService $service)
    {
    }

    public function register(): void
    {
        add_shortcode('dizzy_reservation_form', [$this, 'shortcode']);
        add_action('template_redirect', [$this, 'submit']);
    }

    public function shortcode(array $atts = []): string
    {
        ob_start();

        if (isset($_GET['reservation'])) {
            $result = sanitize_key(wp_unslash((string) $_GET['reservation']));
            echo '<div class="dizzy-reservation-message ' . esc_attr($result) . '">' . esc_html(
                $result === 'success'
                    ? __('Reservation received.', 'dizzy-reservations-manager')
                    : __('Reservation could not be completed. Please check all fields.', 'dizzy-reservations-manager')
            ) . '</div>';
        }
        ?>
        <style>
            .dizzy-reservation-form{--dr-border:#3d4143;display:grid;gap:27px;width:100%}
            .dizzy-reservation-field{margin:0}
            .dizzy-reservation-form label,.dizzy-reservation-legend{display:block;margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:1.6px;text-transform:uppercase}
            .dizzy-reservation-form input[type="text"],.dizzy-reservation-form input[type="email"],.dizzy-reservation-form input[type="tel"],.dizzy-reservation-form input[type="date"],.dizzy-reservation-form input[type="number"],.dizzy-reservation-form textarea{box-sizing:border-box;width:100%;min-height:49px;padding:12px 15px;border:1px solid var(--dr-border);border-radius:0;background:transparent;color:inherit;font:inherit}
            .dizzy-reservation-form textarea{min-height:160px;resize:vertical}
            .dizzy-reservation-times{display:flex;flex-wrap:wrap;gap:12px 24px}
            .dizzy-reservation-time{display:inline-flex!important;align-items:center;gap:7px;margin:0!important;letter-spacing:.4px!important;text-transform:none!important;cursor:pointer}
            .dizzy-reservation-time input{margin:0}
            .dizzy-reservation-submit{width:auto;padding:16px 37px;border:0;border-radius:0;background:#fff;color:#111;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;cursor:pointer}
            .dizzy-reservation-message{margin:0 0 22px;padding:14px;border:1px solid currentColor}
        </style>
        <form method="post" class="dizzy-reservation-form">
            <?php wp_nonce_field('dizzy_reservation_submit', 'dizzy_reservation_nonce'); ?>

            <p class="dizzy-reservation-field">
                <label for="dizzy-reservation-name"><?php esc_html_e('Your name', 'dizzy-reservations-manager'); ?>*</label>
                <input id="dizzy-reservation-name" type="text" name="name" autocomplete="name" required>
            </p>

            <p class="dizzy-reservation-field">
                <label for="dizzy-reservation-email"><?php esc_html_e('Your email', 'dizzy-reservations-manager'); ?>*</label>
                <input id="dizzy-reservation-email" type="email" name="email" autocomplete="email" required>
            </p>

            <p class="dizzy-reservation-field">
                <label for="dizzy-reservation-phone"><?php esc_html_e('Phone', 'dizzy-reservations-manager'); ?>*</label>
                <input id="dizzy-reservation-phone" type="tel" name="phone" autocomplete="tel" required>
            </p>

            <p class="dizzy-reservation-field">
                <label for="dizzy-reservation-date"><?php esc_html_e('Date', 'dizzy-reservations-manager'); ?>*</label>
                <input id="dizzy-reservation-date" type="date" name="reservation_date" min="<?php echo esc_attr(wp_date('Y-m-d')); ?>" required>
            </p>

            <div class="dizzy-reservation-field">
                <span class="dizzy-reservation-legend"><?php esc_html_e('Time', 'dizzy-reservations-manager'); ?>*</span>
                <div class="dizzy-reservation-times">
                    <?php foreach (ReservationService::TIMES as $time) : ?>
                        <label class="dizzy-reservation-time"><input type="radio" name="reservation_time" value="<?php echo esc_attr($time); ?>" required> <span><?php echo esc_html($time); ?></span></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <p class="dizzy-reservation-field">
                <label for="dizzy-reservation-guests"><?php esc_html_e('Number of people', 'dizzy-reservations-manager'); ?>*</label>
                <input id="dizzy-reservation-guests" type="number" name="guests" min="1" max="100" value="2" required>
            </p>

            <p class="dizzy-reservation-field">
                <label for="dizzy-reservation-message"><?php esc_html_e('Your message', 'dizzy-reservations-manager'); ?>*</label>
                <textarea id="dizzy-reservation-message" name="message" required></textarea>
            </p>

            <p class="dizzy-reservation-field">
                <button class="dizzy-reservation-submit" type="submit" name="dizzy_reservation_submit" value="1"><?php esc_html_e('Send', 'dizzy-reservations-manager'); ?></button>
            </p>
        </form>
        <?php
        return (string) ob_get_clean();
    }

    public function submit(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ! isset($_POST['dizzy_reservation_submit'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash((string) ($_POST['dizzy_reservation_nonce'] ?? '')));
        if (! wp_verify_nonce($nonce, 'dizzy_reservation_submit')) {
            return;
        }

        try {
            $this->service->create(wp_unslash($_POST));
            $result = 'success';
        } catch (Throwable $exception) {
            error_log('Dizzy reservation failed: ' . $exception->getMessage());
            $result = 'error';
        }

        wp_safe_redirect(add_query_arg('reservation', $result, wp_get_referer() ?: home_url('/')));
        exit;
    }
}
