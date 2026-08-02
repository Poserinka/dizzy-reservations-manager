<?php

declare(strict_types=1);

namespace Dizzy\Events\Frontend;

use Dizzy\Events\Core\Config;
use Dizzy\Events\Reservations\ReservationService;
use Throwable;


defined('ABSPATH') || exit;

final class ReservationController
{
    public function __construct(
        private readonly ReservationService $service
    ) {
    }

    public function register(): void
    {
        add_shortcode(
            'dizzy_reservation_form',
            [$this, 'render']
        );

        if (doing_action('init') || did_action('init')) {
            $this->handle();
        } else {
            add_action('init', [$this, 'handle']);
        }
    }

    public function render(): string
    {
        $message = '';

        $reservationStatus = isset($_GET['reservation']) && is_string($_GET['reservation'])
            ? sanitize_key(wp_unslash($_GET['reservation']))
            : '';

        if ($reservationStatus === 'success') {
            $message = 'Reservation request received.';
        } elseif ($reservationStatus === 'error') {
            $message = 'The reservation request could not be processed.';
        }

        $occurrenceId = isset($_GET['occurrence_id']) ? absint($_GET['occurrence_id']) : 0;

        ob_start();
        ?>
        <?php if ($message !== '') : ?>
            <div class="dizzy-reservation-message">
                <?php echo esc_html($message); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="dizzy-reservation-form">
            <?php wp_nonce_field('dizzy_reservation_submit', 'dizzy_reservation_nonce'); ?>

            <input type="hidden" name="event_id" value="<?php echo esc_attr((string) get_the_ID()); ?>">
            <input type="hidden" name="occurrence_id" value="<?php echo esc_attr((string) $occurrenceId); ?>">

            <input type="text" name="name" required placeholder="Name">
            <input type="email" name="email" required placeholder="Email">
            <input type="number" name="guests" min="1" required placeholder="Guests">

            <button type="submit" name="dizzy_reservation_submit">
                Reserve
            </button>
        </form>
        <?php

        return (string) ob_get_clean();
    }

    public function handle(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ! isset($_POST['dizzy_reservation_submit'])) {
            return;
        }

        if (
            ! isset($_POST['dizzy_reservation_nonce'])
            || ! is_string($_POST['dizzy_reservation_nonce'])
            || ! wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['dizzy_reservation_nonce'])),
                'dizzy_reservation_submit'
            )
        ) {
            return;
        }

        $eventId = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        $event = get_post($eventId);

        if (! $event || $event->post_type !== Config::POST_TYPE_EVENT || $event->post_status !== 'publish') {
            return;
        }

        $redirectUrl = get_permalink($eventId) ?: home_url('/');

        try {
            $this->service->create([
                'event_id' => $eventId,
                'occurrence_id' => absint($_POST['occurrence_id'] ?? 0),
                'name' => isset($_POST['name']) && is_string($_POST['name'])
                    ? sanitize_text_field(wp_unslash($_POST['name']))
                    : '',
                'email' => isset($_POST['email']) && is_string($_POST['email'])
                    ? sanitize_email(wp_unslash($_POST['email']))
                    : '',
                'guests' => absint($_POST['guests'] ?? 1),
            ]);
        } catch (Throwable) {
            wp_safe_redirect(add_query_arg('reservation', 'error', $redirectUrl));
            exit;
        }

        wp_safe_redirect(add_query_arg('reservation', 'success', $redirectUrl));
        exit;
    }
}
