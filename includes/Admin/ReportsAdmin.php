<?php

declare(strict_types=1);

namespace Dizzy\Events\Admin;

use Dizzy\Events\Core\Config;
use Dizzy\Events\Reports\ReportRepository;

defined('ABSPATH') || exit;

final class ReportsAdmin
{
    public function __construct(private readonly ReportRepository $reports)
    {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_post_dizzy_export_reports', [$this, 'exportCsv']);
    }

    public function registerMenu(): void
    {
        add_submenu_page('edit.php?post_type=' . Config::POST_TYPE_EVENT, __('Reports', 'dizzy-events-manager'), __('Reports', 'dizzy-events-manager'), 'manage_options', 'dizzy-reports', [$this, 'render']);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        [$eventId, $from, $to] = $this->filters();
        $summary = $this->reports->summary($eventId, $from, $to);
        $rows = $this->reports->occurrences($eventId, $from, $to);
        $events = get_posts(['post_type' => Config::POST_TYPE_EVENT, 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        $exportUrl = wp_nonce_url(add_query_arg(['action' => 'dizzy_export_reports', 'event_id' => $eventId, 'date_from' => $from, 'date_to' => $to], admin_url('admin-post.php')), 'dizzy_export_reports');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Event Reports', 'dizzy-events-manager'); ?></h1>
            <form method="get" style="display:flex; gap:12px; align-items:end; flex-wrap:wrap; margin:16px 0;">
                <input type="hidden" name="post_type" value="<?php echo esc_attr(Config::POST_TYPE_EVENT); ?>">
                <input type="hidden" name="page" value="dizzy-reports">
                <label><?php esc_html_e('Event', 'dizzy-events-manager'); ?><br><select name="event_id"><option value="0"><?php esc_html_e('All events', 'dizzy-events-manager'); ?></option><?php foreach ($events as $event) : ?><option value="<?php echo esc_attr((string) $event->ID); ?>" <?php selected($eventId, $event->ID); ?>><?php echo esc_html($event->post_title); ?></option><?php endforeach; ?></select></label>
                <label><?php esc_html_e('From', 'dizzy-events-manager'); ?><br><input type="date" name="date_from" value="<?php echo esc_attr($from); ?>"></label>
                <label><?php esc_html_e('To', 'dizzy-events-manager'); ?><br><input type="date" name="date_to" value="<?php echo esc_attr($to); ?>"></label>
                <button class="button button-primary"><?php esc_html_e('Filter', 'dizzy-events-manager'); ?></button>
                <a class="button" href="<?php echo esc_url($exportUrl); ?>"><?php esc_html_e('Export CSV', 'dizzy-events-manager'); ?></a>
            </form>

            <div style="display:flex; gap:12px; flex-wrap:wrap; margin:16px 0;">
                <?php foreach ([
                    __('Reservations', 'dizzy-events-manager') => $summary['reservations'],
                    __('Confirmed guests', 'dizzy-events-manager') => $summary['confirmed_guests'],
                    __('Attended guests', 'dizzy-events-manager') => $summary['attended_guests'],
                    __('No-show guests', 'dizzy-events-manager') => $summary['no_show_guests'],
                    __('Waitlisted', 'dizzy-events-manager') => $summary['waitlisted'],
                ] as $label => $value) : ?><div style="background:#fff;border:1px solid #ccd0d4;padding:14px;min-width:150px;"><strong style="font-size:22px;display:block;"><?php echo esc_html((string) $value); ?></strong><?php echo esc_html($label); ?></div><?php endforeach; ?>
            </div>

            <h2><?php esc_html_e('Reservation Status Report', 'dizzy-events-manager'); ?></h2>
            <table class="widefat striped" style="max-width:700px;"><thead><tr><th><?php esc_html_e('Pending', 'dizzy-events-manager'); ?></th><th><?php esc_html_e('Waitlisted', 'dizzy-events-manager'); ?></th><th><?php esc_html_e('Confirmed', 'dizzy-events-manager'); ?></th><th><?php esc_html_e('Cancelled', 'dizzy-events-manager'); ?></th></tr></thead><tbody><tr><td><?php echo esc_html((string) $summary['pending']); ?></td><td><?php echo esc_html((string) $summary['waitlisted']); ?></td><td><?php echo esc_html((string) $summary['confirmed']); ?></td><td><?php echo esc_html((string) $summary['cancelled']); ?></td></tr></tbody></table>

            <h2><?php esc_html_e('Attendance & Capacity Analysis', 'dizzy-events-manager'); ?></h2>
            <table class="widefat striped"><thead><tr><th><?php esc_html_e('Event', 'dizzy-events-manager'); ?></th><th><?php esc_html_e('Date', 'dizzy-events-manager'); ?></th><th><?php esc_html_e('Capacity', 'dizzy-events-manager'); ?></th><th><?php esc_html_e('Confirmed', 'dizzy-events-manager'); ?></th><th><?php esc_html_e('Attended', 'dizzy-events-manager'); ?></th><th><?php esc_html_e('No-show', 'dizzy-events-manager'); ?></th><th><?php esc_html_e('Waitlisted', 'dizzy-events-manager'); ?></th><th><?php esc_html_e('Utilization', 'dizzy-events-manager'); ?></th></tr></thead><tbody>
            <?php foreach ($rows as $row) : $capacity = (int) ($row['capacity'] ?? 0); $confirmed = (int) ($row['confirmed_guests'] ?? 0); ?>
                <tr><td><?php echo esc_html((string) ($row['event_title'] ?? '')); ?></td><td><?php echo esc_html((string) ($row['start_datetime'] ?? '')); ?></td><td><?php echo esc_html($capacity > 0 ? (string) $capacity : __('Unlimited', 'dizzy-events-manager')); ?></td><td><?php echo esc_html((string) $confirmed); ?></td><td><?php echo esc_html((string) ($row['attended_guests'] ?? 0)); ?></td><td><?php echo esc_html((string) max(0, $confirmed - (int) ($row['attended_guests'] ?? 0))); ?></td><td><?php echo esc_html((string) ($row['waitlisted_guests'] ?? 0)); ?></td><td><?php echo esc_html($capacity > 0 ? number_format_i18n(($confirmed / $capacity) * 100, 1) . '%' : '-'); ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
        <?php
    }

    public function exportCsv(): never
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'dizzy-events-manager'));
        }
        check_admin_referer('dizzy_export_reports');
        [$eventId, $from, $to] = $this->filters();
        $rows = $this->reports->occurrences($eventId, $from, $to);
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="dizzy-event-report-' . gmdate('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'wb');
        fputcsv($output, ['Event', 'Date', 'Capacity', 'Confirmed guests', 'Attended guests', 'No-show guests', 'Waitlisted guests', 'Utilization %']);
        foreach ($rows as $row) {
            $capacity = (int) ($row['capacity'] ?? 0);
            $confirmed = (int) ($row['confirmed_guests'] ?? 0);
            $attended = (int) ($row['attended_guests'] ?? 0);
            fputcsv($output, [$this->csvCell((string) ($row['event_title'] ?? '')), (string) ($row['start_datetime'] ?? ''), $capacity > 0 ? $capacity : 'Unlimited', $confirmed, $attended, max(0, $confirmed - $attended), (int) ($row['waitlisted_guests'] ?? 0), $capacity > 0 ? round(($confirmed / $capacity) * 100, 1) : '']);
        }
        fclose($output);
        exit;
    }

    /** @return array{0:int, 1:string, 2:string} */
    private function filters(): array
    {
        $eventId = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
        $from = isset($_GET['date_from']) ? $this->date(wp_unslash((string) $_GET['date_from'])) : '';
        $to = isset($_GET['date_to']) ? $this->date(wp_unslash((string) $_GET['date_to'])) : '';
        return [$eventId, $from, $to];
    }

    private function date(string $value): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) !== 1) {
            return '';
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]) ? $value : '';
    }

    private function csvCell(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'" . $value : $value;
    }
}

