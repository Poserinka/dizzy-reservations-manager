<?php

declare(strict_types=1);

namespace Dizzy\Reservations;

defined('ABSPATH') || exit;

final class AdminController
{
    private const MENU = 'dizzy-reservations';
    private const REPORTS = 'dizzy-reservations-reports';
    private const STATUSES = ['pending', 'confirmed', 'waitlisted', 'cancelled'];

    public function __construct(private ReservationRepository $repository, private ReservationService $service)
    {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_post_dizzy_reservation_status', [$this, 'status']);
        add_action('admin_post_dizzy_reservation_report_csv', [$this, 'exportCsv']);
    }

    public function menu(): void
    {
        add_menu_page(__('Reservations Manager', 'dizzy-reservations-manager'), __('Reservations', 'dizzy-reservations-manager'), 'manage_options', self::MENU, [$this, 'reservations'], 'dashicons-clipboard', 26);
        add_submenu_page(self::MENU, __('Reservations', 'dizzy-reservations-manager'), __('Reservations', 'dizzy-reservations-manager'), 'manage_options', self::MENU, [$this, 'reservations']);
        add_submenu_page(self::MENU, __('Reservation Reports', 'dizzy-reservations-manager'), __('Reports', 'dizzy-reservations-manager'), 'manage_options', self::REPORTS, [$this, 'reports']);
    }

    public function reservations(): void
    {
        $this->guard();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Reservations', 'dizzy-reservations-manager'); ?></h1>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('Guest', 'dizzy-reservations-manager'); ?></th><th><?php esc_html_e('Date', 'dizzy-reservations-manager'); ?></th><th><?php esc_html_e('Time', 'dizzy-reservations-manager'); ?></th><th><?php esc_html_e('People', 'dizzy-reservations-manager'); ?></th><th><?php esc_html_e('Message', 'dizzy-reservations-manager'); ?></th><th><?php esc_html_e('Status', 'dizzy-reservations-manager'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($this->repository->all() as $row) : $id = (int) $row['id']; ?>
                    <tr>
                        <td><strong><?php echo esc_html((string) $row['name']); ?></strong><br><?php echo esc_html((string) $row['email']); ?><br><?php echo esc_html((string) $row['phone']); ?></td>
                        <td><?php echo esc_html((string) $row['reservation_date']); ?></td>
                        <td><?php echo esc_html(substr((string) $row['reservation_time'], 0, 5)); ?></td>
                        <td><?php echo esc_html((string) $row['guests']); ?></td>
                        <td><?php echo nl2br(esc_html((string) $row['notes'])); ?></td>
                        <td><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="dizzy_reservation_status"><input type="hidden" name="reservation_id" value="<?php echo esc_attr((string) $id); ?>"><?php wp_nonce_field('dizzy_reservation_' . $id); ?><select name="status"><?php foreach (self::STATUSES as $status) : ?><option value="<?php echo esc_attr($status); ?>" <?php selected($row['status'], $status); ?>><?php echo esc_html(ucfirst($status)); ?></option><?php endforeach; ?></select> <button class="button"><?php esc_html_e('Save', 'dizzy-reservations-manager'); ?></button></form></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function reports(): void
    {
        $this->guard();
        $summary = $this->repository->reportSummary();
        $export = wp_nonce_url(admin_url('admin-post.php?action=dizzy_reservation_report_csv'), 'dizzy_reservation_report_csv');
        ?>
        <div class="wrap"><h1><?php esc_html_e('Reservation Reports', 'dizzy-reservations-manager'); ?></h1>
        <?php echo $this->cards([__('Reservations', 'dizzy-reservations-manager') => $summary['reservations'], __('Confirmed guests', 'dizzy-reservations-manager') => $summary['confirmed_guests'], __('Waitlisted', 'dizzy-reservations-manager') => $summary['waitlisted']]); ?>
        <p><a class="button" href="<?php echo esc_url($export); ?>"><?php esc_html_e('Export CSV', 'dizzy-reservations-manager'); ?></a></p>
        <table class="widefat striped"><thead><tr><th><?php esc_html_e('Date', 'dizzy-reservations-manager'); ?></th><th><?php esc_html_e('Time', 'dizzy-reservations-manager'); ?></th><th><?php esc_html_e('Reservations', 'dizzy-reservations-manager'); ?></th><th><?php esc_html_e('People', 'dizzy-reservations-manager'); ?></th></tr></thead><tbody>
        <?php foreach ($this->repository->reportRows() as $row) : ?><tr><td><?php echo esc_html((string) $row['reservation_date']); ?></td><td><?php echo esc_html(substr((string) $row['reservation_time'], 0, 5)); ?></td><td><?php echo esc_html((string) $row['reservations']); ?></td><td><?php echo esc_html((string) $row['guests']); ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php
    }

    public function status(): void
    {
        $this->guard();
        $id = absint($_POST['reservation_id'] ?? 0);
        check_admin_referer('dizzy_reservation_' . $id);
        $status = sanitize_key((string) ($_POST['status'] ?? ''));
        if (in_array($status, self::STATUSES, true)) $this->service->changeStatus($id, $status);
        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU));
        exit;
    }

    public function exportCsv(): void
    {
        $this->guard();
        check_admin_referer('dizzy_reservation_report_csv');
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=dizzy-reservation-report.csv');
        $output = fopen('php://output', 'wb');
        fputcsv($output, ['Date', 'Time', 'Reservations', 'People']);
        foreach ($this->repository->reportRows() as $row) fputcsv($output, [$row['reservation_date'], $row['reservation_time'], $row['reservations'], $row['guests']]);
        fclose($output);
        exit;
    }

    private function cards(array $items): string
    {
        $html = '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0">';
        foreach ($items as $label => $value) $html .= '<div style="background:#fff;border:1px solid #ccd0d4;padding:14px;min-width:150px"><strong style="font-size:22px;display:block">' . esc_html((string) $value) . '</strong>' . esc_html((string) $label) . '</div>';
        return $html . '</div>';
    }

    private function guard(): void
    {
        if (! current_user_can('manage_options')) wp_die(esc_html__('Unauthorized', 'dizzy-reservations-manager'));
    }
}
