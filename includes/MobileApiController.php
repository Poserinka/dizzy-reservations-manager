<?php

declare(strict_types=1);

namespace Dizzy\Reservations;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

final class MobileApiController
{
    private const NAMESPACE = 'dizzy-controller/v1';

    public function __construct(
        private ReservationRepository $repository,
        private ReservationService $service
    ) {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes(): void
    {
        register_rest_route(self::NAMESPACE, '/session', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'session'],
            'permission_callback' => [$this, 'canManage'],
        ]);
        register_rest_route(self::NAMESPACE, '/reservations', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'reservations'],
            'permission_callback' => [$this, 'canManage'],
        ]);
        register_rest_route(self::NAMESPACE, '/reservations/(?P<id>\d+)/status', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [$this, 'status'],
            'permission_callback' => [$this, 'canManage'],
            'args' => [
                'id' => ['required' => true, 'sanitize_callback' => 'absint'],
                'status' => ['required' => true, 'sanitize_callback' => 'sanitize_key'],
            ],
        ]);
    }

    public function canManage(): bool
    {
        return current_user_can(ControllerRole::RESERVATIONS_CAP);
    }

    public function session(): WP_REST_Response
    {
        $user = wp_get_current_user();
        return new WP_REST_Response([
            'id' => $user->ID,
            'name' => $user->display_name,
            'capabilities' => [
                'reservations' => current_user_can(ControllerRole::RESERVATIONS_CAP),
                'tickets' => current_user_can(ControllerRole::TICKETS_CAP),
            ],
        ]);
    }

    public function reservations(WP_REST_Request $request): WP_REST_Response
    {
        $date = sanitize_text_field((string) $request->get_param('date'));
        $date = $date !== '' ? $date : current_time('Y-m-d');
        $rows = array_values(array_filter(
            $this->repository->all(),
            static fn (array $row): bool => $date === '' || (string) ($row['reservation_date'] ?? '') === $date
        ));

        return new WP_REST_Response(array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'email' => (string) $row['email'],
            'phone' => (string) ($row['phone'] ?? ''),
            'date' => (string) ($row['reservation_date'] ?? ''),
            'time' => substr((string) ($row['reservation_time'] ?? ''), 0, 5),
            'guests' => (int) $row['guests'],
            'message' => (string) ($row['notes'] ?? ''),
            'status' => (string) $row['status'],
        ], $rows));
    }

    public function status(WP_REST_Request $request): WP_REST_Response
    {
        $id = absint($request['id']);
        $status = sanitize_key((string) $request->get_param('status'));

        if (! in_array($status, ['pending', 'confirmed', 'waitlisted', 'cancelled'], true)) {
            return new WP_REST_Response(['message' => 'Invalid reservation status.'], 400);
        }

        if (! $this->service->changeStatus($id, $status)) {
            return new WP_REST_Response(['message' => 'Reservation could not be updated.'], 409);
        }

        return new WP_REST_Response(['ok' => true, 'id' => $id, 'status' => $status]);
    }
}
