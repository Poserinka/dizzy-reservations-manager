<?php

declare(strict_types=1);

namespace Dizzy\Events\Admin;

use Dizzy\Events\Reservations\ReservationRepository;

defined('ABSPATH') || exit;

final class CheckinAdmin
{
    public function __construct(private readonly ReservationRepository $reservations)
    {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_post_dizzy_manual_checkin', [$this, 'manualCheckIn']);
        add_action('admin_post_dizzy_undo_checkin', [$this, 'undoCheckIn']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=dizzy_event',
            __('Check-in', 'dizzy-events-manager'),
            __('Check-in', 'dizzy-events-manager'),
            'manage_options',
            'dizzy-checkin',
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $totals = $this->reservations->attendanceTotals();
        $reservations = $this->reservations->all();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Check-in & Attendance', 'dizzy-events-manager'); ?></h1>
            <div style="display:flex; gap:16px; flex-wrap:wrap; margin:16px 0;">
                <?php foreach ([
                    __('Confirmed reservations', 'dizzy-events-manager') => $totals['confirmed_reservations'],
                    __('Expected guests', 'dizzy-events-manager') => $totals['confirmed_guests'],
                    __('Checked-in reservations', 'dizzy-events-manager') => $totals['checked_in_reservations'],
                    __('Guests attended', 'dizzy-events-manager') => $totals['checked_in_guests'],
                ] as $label => $value) : ?>
                    <div data-stat-card style="background:#fff; border:1px solid #ccd0d4; padding:16px; min-width:170px;">
                        <strong data-stat-value style="font-size:24px; display:block;"><?php echo esc_html((string) $value); ?></strong>
                        <?php echo esc_html($label); ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <h2><?php esc_html_e('QR Scanner', 'dizzy-events-manager'); ?></h2>
            <p><?php esc_html_e('Allow camera access and point it at a Dizzy reservation QR code.', 'dizzy-events-manager'); ?></p>
            <video id="dizzy-qr-video" style="width:100%; max-width:480px; background:#111;" playsinline></video>
            <p id="dizzy-qr-message" class="description"></p>
            <p><input id="dizzy-qr-url" type="url" class="regular-text" placeholder="<?php esc_attr_e('Paste ticket URL', 'dizzy-events-manager'); ?>"> <button id="dizzy-open-ticket" class="button"><?php esc_html_e('Open ticket', 'dizzy-events-manager'); ?></button></p>

            <h2><?php esc_html_e('Reservations', 'dizzy-events-manager'); ?></h2>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('Name', 'dizzy-events-manager'); ?></th><th><?php esc_html_e('Guests', 'dizzy-events-manager'); ?></th><th><?php esc_html_e('Status', 'dizzy-events-manager'); ?></th><th><?php esc_html_e('Checked in', 'dizzy-events-manager'); ?></th><th><?php esc_html_e('Action', 'dizzy-events-manager'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($reservations as $reservation) : $id = (int) ($reservation['id'] ?? 0); ?>
                    <tr>
                        <td><?php echo esc_html((string) ($reservation['name'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($reservation['guests'] ?? 1)); ?></td>
                        <td><?php echo esc_html((string) ($reservation['status'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($reservation['checked_in_at'] ?? '-')); ?></td>
                        <td>
                            <?php if ((string) ($reservation['status'] ?? '') === 'confirmed') : ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <input type="hidden" name="action" value="<?php echo ! empty($reservation['checked_in_at']) ? 'dizzy_undo_checkin' : 'dizzy_manual_checkin'; ?>">
                                    <input type="hidden" name="reservation_id" value="<?php echo esc_attr((string) $id); ?>">
                                    <?php wp_nonce_field('dizzy_checkin_' . $id); ?>
                                    <button class="button"><?php echo esc_html(! empty($reservation['checked_in_at']) ? __('Undo', 'dizzy-events-manager') : __('Check in', 'dizzy-events-manager')); ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>
        (() => {
            const video = document.getElementById('dizzy-qr-video');
            const message = document.getElementById('dizzy-qr-message');
            const input = document.getElementById('dizzy-qr-url');
            const openTicket = (value) => {
                try {
                    const url = new URL(value, window.location.origin);
                    if (url.origin !== window.location.origin || url.searchParams.get('dizzy_ticket') !== '1') {
                        throw new Error('invalid');
                    }
                    url.searchParams.set('checkin_nonce', <?php echo wp_json_encode(wp_create_nonce('dizzy_qr_checkin')); ?>);
                    window.location.assign(url.href);
                    return true;
                } catch (error) {
                    message.textContent = <?php echo wp_json_encode(__('This is not a valid Dizzy ticket URL.', 'dizzy-events-manager')); ?>;
                    return false;
                }
            };
            document.getElementById('dizzy-open-ticket').addEventListener('click', (event) => {
                event.preventDefault();
                if (input.value) openTicket(input.value);
            });
            setInterval(async () => {
                try {
                    const html = await fetch(window.location.href, {credentials:'same-origin'}).then(response => response.text());
                    const next = new DOMParser().parseFromString(html, 'text/html');
                    const values = next.querySelectorAll('[data-stat-value]');
                    document.querySelectorAll('[data-stat-value]').forEach((element, index) => {
                        if (values[index]) element.textContent = values[index].textContent;
                    });
                } catch (error) {}
            }, 10000);
            if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
                message.textContent = <?php echo wp_json_encode(__('Camera QR scanning is not supported by this browser. Paste the ticket URL instead.', 'dizzy-events-manager')); ?>;
                return;
            }
            const detector = new BarcodeDetector({formats:['qr_code']});
            navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}}).then((stream) => {
                video.srcObject = stream;
                video.play();
                const scan = async () => {
                    try {
                        const codes = await detector.detect(video);
                        if (codes[0]?.rawValue) {
                            if (openTicket(codes[0].rawValue)) {
                                stream.getTracks().forEach(track => track.stop());
                                return;
                            }
                        }
                    } catch (error) {}
                    requestAnimationFrame(scan);
                };
                scan();
            }).catch(() => { message.textContent = <?php echo wp_json_encode(__('Camera access was denied. Paste the ticket URL instead.', 'dizzy-events-manager')); ?>; });
        })();
        </script>
        <?php
    }

    public function manualCheckIn(): void
    {
        $id = $this->authorizedReservationId();
        $this->reservations->checkIn($id, get_current_user_id());
        $this->redirect();
    }

    public function undoCheckIn(): void
    {
        $id = $this->authorizedReservationId();
        $this->reservations->undoCheckIn($id);
        $this->redirect();
    }

    private function authorizedReservationId(): int
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'dizzy-events-manager'));
        }
        $id = isset($_POST['reservation_id']) ? absint($_POST['reservation_id']) : 0;
        check_admin_referer('dizzy_checkin_' . $id);
        return $id;
    }

    private function redirect(): never
    {
        wp_safe_redirect(admin_url('edit.php?post_type=dizzy_event&page=dizzy-checkin'));
        exit;
    }
}

