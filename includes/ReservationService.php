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
            'status' => 'pending',
        ]);

        $this->mailer->send(
            $email,
            'Reservation received',
            sprintf(
                'Your reservation request for %s at %s has been received and is awaiting approval.',
                wp_date(get_option('date_format'), $parsedDate->getTimestamp(), wp_timezone()),
                $time
            )
        );

        return $id;
    }

    public function changeStatus(int $id, string $status): bool
    {
        $row = $this->repository->find($id);

        if ($row === null || ! $this->repository->updateStatus($id, $status)) {
            return false;
        }

        if (is_email((string) $row['email'])) {
            $message = match ($status) {
                'confirmed' => 'Your reservation is confirmed.',
                'cancelled' => 'Your reservation is cancelled.',
                'waitlisted' => 'Your reservation is on the waiting list.',
                default => 'Your reservation is awaiting approval.',
            };
            $this->mailer->send((string) $row['email'], 'Reservation ' . ucfirst($status), $message);
        }

        return true;
    }
}
