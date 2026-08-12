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
        add_action('wp_ajax_dizzy_reservation_submit', [$this, 'ajaxSubmit']);
        add_action('wp_ajax_nopriv_dizzy_reservation_submit', [$this, 'ajaxSubmit']);
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
            .dizzy-reservation-submit:disabled{cursor:wait;opacity:.65}
            .dizzy-reservation-overlay{align-items:center;background:rgba(0,0,0,.82);display:none;inset:0;justify-content:center;padding:18px;position:fixed;z-index:999999}
            .dizzy-reservation-overlay.is-open{display:flex}
            .dizzy-reservation-modal{background:#191919;border:0;box-shadow:0 24px 70px rgba(0,0,0,.55);box-sizing:border-box;color:#fff;max-width:720px;padding:38px;position:relative;text-align:left;width:100%}
            .dizzy-reservation-modal h2,.dizzy-reservation-modal p{color:inherit}
            .dizzy-reservation-modal h2{margin:0 0 10px}
            .dizzy-reservation-modal p{line-height:1.6;margin:0}
            .dizzy-reservation-close,.dizzy-reservation-close:hover,.dizzy-reservation-close:focus,.dizzy-reservation-close:focus-visible{-webkit-appearance:none!important;appearance:none!important;background:transparent!important;border:0!important;border-radius:0!important;box-shadow:none!important;color:#fff!important;font-size:28px!important;line-height:1!important;outline:0!important;padding:8px!important;position:absolute;right:1px;top:1px}
            .dizzy-reservation-result{background:#1d1d1d;padding:24px}
            .dizzy-reservation-modal.is-success .dizzy-reservation-result{border-left:4px solid #46b450}
            .dizzy-reservation-modal.is-error .dizzy-reservation-result{border-left:4px solid #dc3232}
            @media(max-width:600px){.dizzy-reservation-modal{padding:38px 18px 24px}}
        </style>
        <div class="dizzy-reservation-shell">
        <form method="post" class="dizzy-reservation-form">
            <?php wp_nonce_field('dizzy_reservation_submit', 'dizzy_reservation_nonce'); ?>
            <input type="hidden" name="dizzy_reservation_submit" value="1">

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
                <button class="dizzy-reservation-submit" type="submit"><?php esc_html_e('Send', 'dizzy-reservations-manager'); ?></button>
            </p>
        </form>
        <div class="dizzy-reservation-overlay" role="dialog" aria-modal="true" aria-labelledby="dizzy-reservation-result-heading" aria-hidden="true">
            <div class="dizzy-reservation-modal">
                <button type="button" class="dizzy-reservation-close" aria-label="<?php esc_attr_e('Close', 'dizzy-reservations-manager'); ?>">&times;</button>
                <div class="dizzy-reservation-result" role="alert"></div>
            </div>
        </div>
        </div>
        <script>
        (() => {
            const shell = document.currentScript.previousElementSibling;
            if (!shell || !shell.classList.contains('dizzy-reservation-shell')) return;
            const form = shell.querySelector('.dizzy-reservation-form');
            const overlay = shell.querySelector('.dizzy-reservation-overlay');
            const modal = shell.querySelector('.dizzy-reservation-modal');
            const result = shell.querySelector('.dizzy-reservation-result');
            const close = shell.querySelector('.dizzy-reservation-close');
            const submit = form.querySelector('.dizzy-reservation-submit');
            const ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            const sendLabel = <?php echo wp_json_encode(__('Send', 'dizzy-reservations-manager')); ?>;
            const sendingLabel = <?php echo wp_json_encode(__('Sending…', 'dizzy-reservations-manager')); ?>;
            const genericError = <?php echo wp_json_encode(__('Reservation could not be completed. Please check all fields and try again.', 'dizzy-reservations-manager')); ?>;

            const open = (message, success) => {
                result.innerHTML = '';
                const heading = document.createElement('h2');
                heading.id = 'dizzy-reservation-result-heading';
                heading.textContent = success
                    ? <?php echo wp_json_encode(__('Reservation confirmed', 'dizzy-reservations-manager')); ?>
                    : <?php echo wp_json_encode(__('Reservation failed', 'dizzy-reservations-manager')); ?>;
                const text = document.createElement('p');
                text.textContent = message;
                result.append(heading, text);
                modal.classList.toggle('is-success', success);
                modal.classList.toggle('is-error', !success);
                overlay.classList.add('is-open');
                overlay.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
                close.focus();
            };

            const shut = () => {
                overlay.classList.remove('is-open');
                overlay.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            };

            form.addEventListener('submit', async event => {
                event.preventDefault();
                if (!form.reportValidity()) return;
                submit.disabled = true;
                submit.textContent = sendingLabel;
                const body = new FormData(form);
                body.set('action', 'dizzy_reservation_submit');
                try {
                    const response = await fetch(ajaxUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body
                    });
                    const json = await response.json();
                    if (!response.ok || !json.success) {
                        throw new Error(json.data?.message || genericError);
                    }
                    form.reset();
                    const guests = form.querySelector('[name="guests"]');
                    if (guests) guests.value = '2';
                    open(json.data.message, true);
                } catch (error) {
                    open(error instanceof Error ? error.message : genericError, false);
                } finally {
                    submit.disabled = false;
                    submit.textContent = sendLabel;
                }
            });

            close.addEventListener('click', shut);
            overlay.addEventListener('click', event => { if (event.target === overlay) shut(); });
            document.addEventListener('keydown', event => { if (event.key === 'Escape' && overlay.classList.contains('is-open')) shut(); });
        })();
        </script>
        <?php
        return (string) ob_get_clean();
    }

    public function ajaxSubmit(): void
    {
        $nonce = sanitize_text_field(wp_unslash((string) ($_POST['dizzy_reservation_nonce'] ?? '')));

        if (! wp_verify_nonce($nonce, 'dizzy_reservation_submit')) {
            wp_send_json_error([
                'message' => __('Your session expired. Refresh the page and try again.', 'dizzy-reservations-manager'),
            ], 403);
        }

        try {
            $this->service->create(wp_unslash($_POST));
            wp_send_json_success([
                'message' => __('Your reservation is confirmed. A confirmation email has been sent.', 'dizzy-reservations-manager'),
            ]);
        } catch (Throwable $exception) {
            error_log('Dizzy AJAX reservation failed: ' . $exception->getMessage());
            wp_send_json_error([
                'message' => __('Reservation could not be completed. Please check all fields and try again.', 'dizzy-reservations-manager'),
            ], 400);
        }
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
