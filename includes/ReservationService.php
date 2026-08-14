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
        private Mailer $mailer
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

        $id = $this->repository->create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'reservation_date' => $date,
            'reservation_time' => $time,
            'guests' => $guests,
            'message' => $message,
            'status' => 'confirmed',
        ]);

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
            'message' => $message,
            'status' => 'confirmed',
        ]);

        return $id;
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
                ]
            );
        }

        return true;
    }
}
