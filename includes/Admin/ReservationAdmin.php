<?php

declare(strict_types=1);

namespace Dizzy\Events\Admin;

use Dizzy\Events\Reservations\ReservationRepository;
use Dizzy\Events\Reservations\ReservationService;
use Dizzy\Events\Enums\ReservationStatus;
use Throwable;

defined('ABSPATH') || exit;

final class ReservationAdmin
{
    public function __construct(
        private readonly ReservationRepository $repository,
        private readonly ReservationService $service
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_post_dizzy_update_reservation_status', [$this, 'updateStatus']);
        add_action('admin_post_dizzy_delete_reservation', [$this, 'deleteReservation']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=dizzy_event',
            'Reservations',
            'Reservations',
            'manage_options',
            'dizzy-reservations',
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $reservations = $this->repository->all();

        echo '<div class="wrap"><h1>Reservations</h1>';
        $error = isset($_GET['reservation_error'])
            ? sanitize_key(wp_unslash((string) $_GET['reservation_error']))
            : '';

        if ($error === 'capacity') {
            echo '<div class="notice notice-error"><p>' . esc_html__('This reservation cannot be approved because the occurrence capacity would be exceeded.', 'dizzy-events-manager') . '</p></div>';
        } elseif ($error === 'save') {
            echo '<div class="notice notice-error"><p>' . esc_html__('The reservation status could not be updated.', 'dizzy-events-manager') . '</p></div>';
        }
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>Name</th><th>Email</th><th>Event</th><th>Guests</th><th>Status</th><th>Actions</th></tr></thead><tbody>';

        foreach ($reservations as $reservation) {
            $id = (int) ($reservation['id'] ?? 0);
            $status = (string) ($reservation['status'] ?? 'pending');

            echo '<tr>';
            echo '<td>' . esc_html((string) ($reservation['name'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($reservation['email'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($reservation['event_id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($reservation['guests'] ?? '')) . '</td>';
            echo '<td>' . esc_html($status) . '</td>';
            echo '<td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">';
            echo '<input type="hidden" name="action" value="dizzy_update_reservation_status">';
            echo '<input type="hidden" name="reservation_id" value="' . esc_attr((string) $id) . '">';
            wp_nonce_field('dizzy_update_reservation_status_' . $id);
            echo '<select name="status">';
            foreach (['pending' => 'Pending', 'waitlisted' => 'Waitlisted', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled'] as $value => $label) {
                echo '<option value="' . esc_attr($value) . '" ' . selected($status, $value, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
            echo '<button class="button">Update</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    public function updateStatus(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $id = isset($_POST['reservation_id']) ? absint($_POST['reservation_id']) : 0;

        check_admin_referer('dizzy_update_reservation_status_' . $id);

        $status = isset($_POST['status']) && is_string($_POST['status'])
            ? sanitize_key(wp_unslash($_POST['status']))
            : 'pending';

        $newStatus = ReservationStatus::tryFrom($status);

        if ($newStatus !== null) {
            try {
                if (! $this->service->changeStatus($id, $newStatus)) {
                    wp_safe_redirect(add_query_arg('reservation_error', 'capacity', admin_url('edit.php?post_type=dizzy_event&page=dizzy-reservations')));
                    exit;
                }
            } catch (Throwable $exception) {
                error_log('Dizzy Events reservation status update failed: ' . $exception->getMessage());
                wp_safe_redirect(add_query_arg('reservation_error', 'save', admin_url('edit.php?post_type=dizzy_event&page=dizzy-reservations')));
                exit;
            }
        }

        wp_safe_redirect(admin_url('edit.php?post_type=dizzy_event&page=dizzy-reservations'));
        exit;
    }

    public function deleteReservation(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $id = isset($_POST['reservation_id']) ? absint($_POST['reservation_id']) : 0;

        check_admin_referer('dizzy_delete_reservation_' . $id);

        $this->repository->delete($id);

        wp_safe_redirect(admin_url('edit.php?post_type=dizzy_event&page=dizzy-reservations'));
        exit;
    }
}

