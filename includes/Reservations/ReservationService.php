<?php

declare(strict_types=1);

namespace Dizzy\Events\Reservations;

use Dizzy\Events\Enums\ReservationStatus;
use Dizzy\Events\Mail\Services\MailService;
use Dizzy\Events\Repositories\OccurrenceRepository;
use RuntimeException;

defined('ABSPATH') || exit;

final class ReservationService
{
    private const MAX_GUESTS = 100;

    public function __construct(
        private readonly ReservationRepository $repository,
        private readonly MailService $mailer,
        private readonly OccurrenceRepository $occurrences,
        private readonly TicketService $tickets,
    ) {
    }

    public function create(array $data): int
    {
        if (empty($data['event_id'])) {
            throw new RuntimeException('Event ID is required.');
        }

        $eventId = (int) $data['event_id'];
        $occurrenceId = (int) ($data['occurrence_id'] ?? 0);

        if ($occurrenceId < 0) {
            throw new RuntimeException('Invalid occurrence ID.');
        }

        if ($occurrenceId > 0 && ! $this->isBookableOccurrence($eventId, $occurrenceId)) {
            throw new RuntimeException('The selected event date is not available.');
        }

        if (trim((string) ($data['name'] ?? '')) === '') {
            throw new RuntimeException('Name is required.');
        }

        $email = (string) ($data['email'] ?? '');

        if ($email === '' || ! is_email($email)) {
            throw new RuntimeException('A valid email address is required.');
        }

        $guests = (int) ($data['guests'] ?? 0);

        if ($guests < 1 || $guests > self::MAX_GUESTS) {
            throw new RuntimeException(
                sprintf('Guest count must be between 1 and %d.', self::MAX_GUESTS)
            );
        }

        $data['event_id'] = $eventId;
        $data['occurrence_id'] = $occurrenceId;
        $data['guests'] = $guests;
        $data['status'] ??= ReservationStatus::Pending->value;

        $reservationId = $this->repository->save($data);
        $reservation = $this->repository->find($reservationId);
        $status = (string) ($reservation['status'] ?? ReservationStatus::Pending->value);

        if (! empty($data['email']) && is_string($data['email'])) {
            $waitlisted = $status === ReservationStatus::Waitlisted->value;
            $this->mailer->send(
                $data['email'],
                $waitlisted ? 'Added to the waiting list' : 'Reservation received',
                $waitlisted
                    ? 'The event is currently full. Your request has been added to the waiting list.'
                    : 'Your reservation request has been received and is awaiting approval.'
            );
        }

        return $reservationId;
    }

    private function isBookableOccurrence(int $eventId, int $occurrenceId): bool
    {
        $grouped = $this->occurrences->findUpcomingByEventIds([$eventId]);

        foreach ($grouped[$eventId] ?? [] as $occurrence) {
            if ($occurrence->id === $occurrenceId) {
                return true;
            }
        }

        return false;
    }

    public function confirm(int $reservationId): bool
    {
        return $this->changeStatus($reservationId, ReservationStatus::Confirmed);
    }

    public function cancel(int $reservationId): bool
    {
        return $this->changeStatus($reservationId, ReservationStatus::Cancelled);
    }

    public function changeStatus(int $reservationId, ReservationStatus $status): bool
    {
        $reservation = $this->repository->find($reservationId);

        if ($reservation === null) {
            return false;
        }

        $previous = (string) ($reservation['status'] ?? '');

        if ($previous === $status->value) {
            return true;
        }

        if (! $this->repository->updateStatus($reservationId, $status->value)) {
            return false;
        }

        $email = (string) ($reservation['email'] ?? '');

        if ($email !== '' && is_email($email)) {
            $ticketUrl = $status === ReservationStatus::Confirmed
                ? $this->tickets->ticketUrl($reservation)
                : '';
            [$subject, $message] = match ($status) {
                ReservationStatus::Confirmed => [
                    'Reservation confirmed',
                    sprintf(
                        'Your reservation has been confirmed.<br><br><a href="%1$s">Open your ticket</a><br><br><img src="%2$s" width="240" height="240" alt="QR ticket">',
                        esc_url($ticketUrl),
                        esc_url($this->tickets->qrImageUrl($ticketUrl))
                    ),
                ],
                ReservationStatus::Cancelled => [
                    'Reservation cancelled',
                    'Your reservation has been cancelled.',
                ],
                ReservationStatus::Waitlisted => [
                    'Added to the waiting list',
                    'Your reservation has been moved to the waiting list.',
                ],
                ReservationStatus::Pending => [
                    'Reservation awaiting approval',
                    'Your reservation is awaiting approval.',
                ],
            };

            $this->mailer->send($email, $subject, $message);
        }

        return true;
    }
}

