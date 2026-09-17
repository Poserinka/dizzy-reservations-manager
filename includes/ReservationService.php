<?php

declare(strict_types=1);

namespace Dizzy\Reservations;

use DateTimeImmutable;
use RuntimeException;

defined('ABSPATH') || exit;

final class ReservationService
{
    public const TIMES = ['16:00','16:30','17:00','17:30','18:00','18:30','19:00','19:30','20:00','20:30','21:00'];

    public function __construct(
        private ReservationRepository $repository,
        private Mailer $mailer,
        private TableRepository $tables,
        private EventGateway $events
    ) {
    }

    public function create(array $data): int
    {
        $name = sanitize_text_field((string) ($data['name'] ?? ''));
        $email = sanitize_email((string) ($data['email'] ?? ''));
        $phone = sanitize_text_field((string) ($data['phone'] ?? ''));
        $date = sanitize_text_field((string) ($data['reservation_date'] ?? ''));
        $time = sanitize_text_field((string) ($data['reservation_time'] ?? ''));
        $guests = absint($data['guests'] ?? 0);
        $message = sanitize_textarea_field((string) ($data['message'] ?? ''));
        $tableId = absint($data['table_id'] ?? 0);
        $tableSession = sanitize_key((string) ($data['table_session'] ?? ''));
        $requestedType = sanitize_key((string) ($data['reservation_type'] ?? ''));
        $requestedTicket = sanitize_key((string) ($data['ticket_status'] ?? ''));

        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
        $today = new DateTimeImmutable('today', wp_timezone());

        if (
            $name === ''
            || ! is_email($email)
            || $phone === ''
            || $parsedDate === false
            || $parsedDate < $today
            || ! in_array($time, self::TIMES, true)
            || $guests < 1
            || $guests > 100
            || $message === ''
        ) {
            throw new RuntimeException('Invalid reservation details.');
        }

        $plan = $this->plan($date, $time, $guests, $requestedType, $requestedTicket);

        $tablesEnabled = $this->tables->hasActiveTables();
        if ($tablesEnabled && ($tableId < 1 || $tableSession === '')) {
            throw new RuntimeException('Please select an available table.');
        }

        $table = $tableId > 0 ? $this->tables->find($tableId) : null;
        if ($tablesEnabled && ($table === null || ! $this->tables->validateAndLock($tableId, $date, $time, $guests, $tableSession, $plan['duration_minutes']))) {
            throw new RuntimeException('The selected table is no longer available.');
        }

        try {
            $id = $this->repository->create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'reservation_date' => $date,
                'reservation_time' => $time,
                'guests' => $guests,
                'table_id' => $tableId,
                'duration_minutes' => $plan['duration_minutes'],
                'event_id' => $plan['event_id'],
                'occurrence_id' => $plan['occurrence_id'],
                'reservation_type' => $plan['reservation_type'],
                'ticket_status' => $plan['ticket_status'],
                'ticket_quantity' => $plan['ticket_quantity'],
                'ticket_price' => $plan['ticket_price'],
                'event_title' => $plan['event_title'],
                'concert_start' => $plan['concert_start'],
                'concert_end' => $plan['concert_end'],
                'ticket_url' => $plan['ticket_url'],
                'message' => $message,
                'status' => 'confirmed',
                'experience' => $plan,
            ]);
            $this->tables->release($tableSession);
        } finally {
            if ($tablesEnabled && $tableId > 0) $this->tables->unlock($tableId);
        }

        $this->mailer->sendTemplate(
            $email,
            __('Reservation confirmed', 'dizzy-reservations-manager'),
            'reservation-confirmed',
            [
                'reservation_id' => $id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'date' => $parsedDate->format('d/m/Y'),
                'time' => $time,
                'guests' => $guests,
                'table' => (string) ($table['code'] ?? ''),
                'message' => $message,
                'status' => 'confirmed',
            ]
        );

        do_action('dizzy_reservation_created', [
            'reservation_id' => $id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'date' => $parsedDate->format('d/m/Y'),
            'time' => $time,
            'guests' => $guests,
            'table' => (string) ($table['code'] ?? ''),
            'message' => $message,
            'status' => 'confirmed',
            'experience' => $plan,
        ]);

        return $id;
    }

    /**
     * Return authoritative concert and duration information for a selected slot.
     */
    public function plan(string $date, string $time, int $guests, string $requestedType = '', string $requestedTicket = ''): array
    {
        $concert = $this->events->ticketedConcertOn($date);
        if ($concert === null) {
            return [
                'has_concert' => false, 'reservation_type' => 'standard', 'ticket_status' => 'none',
                'ticket_quantity' => 0, 'ticket_price' => 0.0, 'duration_minutes' => 120,
                'event_id' => 0, 'occurrence_id' => 0, 'event_title' => '', 'concert_start' => null,
                'concert_end' => null, 'concert_time' => '', 'dinner_cutoff' => '', 'ticket_url' => '',
            ];
        }

        $type = in_array($requestedType, ['dinner_only', 'dinner_concert'], true) ? $requestedType : 'dinner_concert';
        $ticketStatus = $type === 'dinner_concert'
            ? (in_array($requestedTicket, ['already_purchased', 'buy'], true) ? $requestedTicket : 'buy')
            : 'none';
        if ($ticketStatus === 'buy' && (string) $concert['ticket_url'] === '') {
            throw new RuntimeException('Online ticket sales are not available for this concert. Choose “I already have concert tickets” or contact Dizzy.');
        }
        $start = new DateTimeImmutable($date . ' ' . $time . ':00', wp_timezone());
        $concertStart = new DateTimeImmutable((string) $concert['start_datetime'], wp_timezone());
        $concertEnd = ! empty($concert['end_datetime'])
            ? new DateTimeImmutable((string) $concert['end_datetime'], wp_timezone())
            : $concertStart->modify('+3 hours');
        $cutoff = $concertStart->modify('-1 hour');
        $end = $type === 'dinner_only' ? $cutoff : $concertEnd;

        if ($start >= $end) {
            throw new RuntimeException($type === 'dinner_only'
                ? sprintf('Dinner-only reservations must start before %s on concert nights.', $cutoff->format('H:i'))
                : 'Please choose a reservation time before the concert ends.');
        }

        return [
            'has_concert' => true,
            'reservation_type' => $type,
            'ticket_status' => $ticketStatus,
            'ticket_quantity' => $type === 'dinner_concert' ? $guests : 0,
            'ticket_price' => (float) $concert['price'],
            'duration_minutes' => max(1, (int) round(($end->getTimestamp() - $start->getTimestamp()) / 60)),
            'event_id' => (int) $concert['event_id'],
            'occurrence_id' => (int) $concert['id'],
            'event_title' => (string) $concert['post_title'],
            'concert_start' => (string) $concert['start_datetime'],
            'concert_end' => $concertEnd->format('Y-m-d H:i:s'),
            'concert_time' => $concertStart->format('H:i'),
            'dinner_cutoff' => $cutoff->format('H:i'),
            'ticket_url' => (string) $concert['ticket_url'],
        ];
    }

    public function changeStatus(int $id, string $status): bool
    {
        $row = $this->repository->find($id);

        if ($row === null || ! $this->repository->updateStatus($id, $status)) {
            return false;
        }

        if (is_email((string) $row['email'])) {
            $statusMessage = match ($status) {
                'confirmed' => __('Reservation confirmed', 'dizzy-reservations-manager'),
                'cancelled' => __('Reservation cancelled', 'dizzy-reservations-manager'),
                'waitlisted' => __('Reservation waitlisted', 'dizzy-reservations-manager'),
                default => __('Reservation pending', 'dizzy-reservations-manager'),
            };

            $date = (string) ($row['reservation_date'] ?? '');
            $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());

            $this->mailer->sendTemplate(
                (string) $row['email'],
                $statusMessage,
                'reservation-status',
                [
                    'reservation_id' => $id,
                    'name' => (string) $row['name'],
                    'email' => (string) $row['email'],
                    'phone' => (string) ($row['phone'] ?? ''),
                    'date' => $parsedDate instanceof DateTimeImmutable ? $parsedDate->format('d/m/Y') : $date,
                    'time' => substr((string) ($row['reservation_time'] ?? ''), 0, 5),
                    'guests' => (int) $row['guests'],
                    'message' => (string) ($row['notes'] ?? ''),
                    'status' => $status,
                    'status_message' => $statusMessage,
                    'experience' => $this->experienceFromRow($row),
                ]
            );
        }

        return true;
    }

    private function experienceFromRow(array $row): array
    {
        return [
            'has_concert' => (int) ($row['event_id'] ?? 0) > 0,
            'reservation_type' => (string) ($row['reservation_type'] ?? 'standard'),
            'ticket_status' => (string) ($row['ticket_status'] ?? 'none'),
            'ticket_quantity' => (int) ($row['ticket_quantity'] ?? 0),
            'ticket_price' => (float) ($row['ticket_price'] ?? 0),
            'event_title' => (string) ($row['event_title'] ?? ''),
            'concert_time' => ! empty($row['concert_start']) ? substr((string) $row['concert_start'], 11, 5) : '',
            'dinner_cutoff' => (string) ($row['reservation_type'] ?? '') === 'dinner_only'
                ? (new DateTimeImmutable((string) $row['concert_start'], wp_timezone()))->modify('-1 hour')->format('H:i') : '',
            'ticket_url' => (string) ($row['ticket_url'] ?? ''),
        ];
    }
}
