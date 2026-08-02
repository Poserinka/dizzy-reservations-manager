<?php

declare(strict_types=1);

namespace Dizzy\Events\Reservations;

use Dizzy\Events\Repositories\OccurrenceRepository;

defined('ABSPATH') || exit;

final class TicketService
{
    public function __construct(
        private readonly ReservationRepository $reservations,
        private readonly OccurrenceRepository $occurrences
    ) {
    }

    public function register(): void
    {
        add_action('template_redirect', [$this, 'renderTicket']);
    }

    /** @param array<string, mixed> $reservation */
    public function ticketUrl(array $reservation): string
    {
        $id = (int) ($reservation['id'] ?? 0);

        return add_query_arg(
            [
                'dizzy_ticket' => '1',
                'reservation' => $id,
                'signature' => $this->signature($reservation),
            ],
            home_url('/')
        );
    }

    public function qrImageUrl(string $ticketUrl): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' . rawurlencode($ticketUrl);
    }

    public function renderTicket(): void
    {
        if (! isset($_GET['dizzy_ticket']) || sanitize_key(wp_unslash((string) $_GET['dizzy_ticket'])) !== '1') {
            return;
        }

        $id = isset($_GET['reservation']) ? absint($_GET['reservation']) : 0;
        $provided = isset($_GET['signature'])
            ? sanitize_text_field(wp_unslash((string) $_GET['signature']))
            : '';
        $reservation = $this->reservations->find($id);

        if (
            $reservation === null
            || (string) ($reservation['status'] ?? '') !== 'confirmed'
            || $provided === ''
            || ! hash_equals($this->signature($reservation), $provided)
        ) {
            status_header(404);
            wp_die(esc_html__('This ticket is invalid or no longer active.', 'dizzy-events-manager'));
        }

        $eventId = (int) ($reservation['event_id'] ?? 0);
        $occurrenceId = (int) ($reservation['occurrence_id'] ?? 0);
        $date = '';
        $checkInResult = '';

        $checkInNonce = isset($_GET['checkin_nonce'])
            ? sanitize_text_field(wp_unslash((string) $_GET['checkin_nonce']))
            : '';

        if (
            is_user_logged_in()
            && current_user_can('manage_options')
            && wp_verify_nonce($checkInNonce, 'dizzy_qr_checkin')
        ) {
            $checkInResult = $this->reservations->checkIn($id, get_current_user_id());
            $reservation = $this->reservations->find($id) ?? $reservation;
        }

        foreach ($this->occurrences->findByEventId($eventId) as $occurrence) {
            if ($occurrence->id === $occurrenceId) {
                $date = wp_date('d F Y H:i', $occurrence->startDateTime->getTimestamp(), $occurrence->startDateTime->getTimezone());
                break;
            }
        }

        status_header(200);
        nocache_headers();
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php esc_html_e('Valid Reservation Ticket', 'dizzy-events-manager'); ?></title>
        </head>
        <body style="font-family: sans-serif; max-width: 640px; margin: 48px auto; padding: 24px;">
            <h1><?php esc_html_e('Valid Reservation Ticket', 'dizzy-events-manager'); ?></h1>
            <p><strong><?php echo esc_html(get_the_title($eventId)); ?></strong></p>
            <?php if ($date !== '') : ?><p><?php echo esc_html($date); ?></p><?php endif; ?>
            <p><?php echo esc_html((string) ($reservation['name'] ?? '')); ?></p>
            <p><?php echo esc_html(sprintf(__('Guests: %d', 'dizzy-events-manager'), (int) ($reservation['guests'] ?? 1))); ?></p>
            <p style="color: #167c3a;"><strong><?php esc_html_e('Confirmed', 'dizzy-events-manager'); ?></strong></p>
            <?php if ($checkInResult === 'checked_in') : ?>
                <p style="padding: 16px; background: #dff3e5; color: #116b32;"><strong><?php esc_html_e('Check-in completed.', 'dizzy-events-manager'); ?></strong></p>
            <?php elseif ($checkInResult === 'already_checked_in') : ?>
                <p style="padding: 16px; background: #fff3cd; color: #7a5d00;"><strong><?php esc_html_e('This ticket was already checked in.', 'dizzy-events-manager'); ?></strong></p>
            <?php endif; ?>
            <?php if (! empty($reservation['checked_in_at'])) : ?>
                <p><?php echo esc_html(sprintf(__('Checked in: %s UTC', 'dizzy-events-manager'), (string) $reservation['checked_in_at'])); ?></p>
            <?php endif; ?>
        </body>
        </html>
        <?php
        exit;
    }

    /** @param array<string, mixed> $reservation */
    private function signature(array $reservation): string
    {
        $payload = implode('|', [
            (string) ($reservation['id'] ?? ''),
            (string) ($reservation['email'] ?? ''),
            (string) ($reservation['created_at'] ?? ''),
        ]);

        return hash_hmac('sha256', $payload, wp_salt('auth'));
    }
}

