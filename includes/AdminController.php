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
        $rows = $this->repository->all();
        $calendarRows = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'date' => (string) ($row['reservation_date'] ?? ''),
            'time' => substr((string) ($row['reservation_time'] ?? ''), 0, 5),
            'name' => (string) $row['name'],
            'people' => (int) $row['guests'],
            'status' => (string) $row['status'],
        ], $rows);
        ?>
        <div class="wrap dizzy-reservations-admin">
            <h1><?php esc_html_e('Reservations', 'dizzy-reservations-manager'); ?></h1>
            <?php $this->calendar($calendarRows); ?>
            <h2 id="dizzy-all-reservations"><?php esc_html_e('All Reservations', 'dizzy-reservations-manager'); ?></h2>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('Guest', 'dizzy-reservations-manager'); ?></th><th><?php esc_html_e('Date', 'dizzy-reservations-manager'); ?></th><th><?php esc_html_e('Time', 'dizzy-reservations-manager'); ?></th><th><?php esc_html_e('People', 'dizzy-reservations-manager'); ?></th><th><?php esc_html_e('Message', 'dizzy-reservations-manager'); ?></th><th><?php esc_html_e('Status', 'dizzy-reservations-manager'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row) : $id = (int) $row['id']; ?>
                    <tr id="dizzy-reservation-<?php echo esc_attr((string) $id); ?>">
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

    private function calendar(array $rows): void
    {
        ?>
        <style>
            .dizzy-calendar-toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:16px 0 10px}
            .dizzy-calendar-nav{display:flex;align-items:center;gap:8px}
            .dizzy-calendar-month{min-width:160px;text-align:center;font-size:16px;font-weight:600}
            .dizzy-calendar-layout{display:grid;grid-template-columns:minmax(520px,1.55fr) minmax(280px,.8fr);gap:16px;margin-bottom:28px}
            .dizzy-calendar-surface{background:#fff;border:1px solid #c3c4c7}
            .dizzy-calendar-weekdays,.dizzy-calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr))}
            .dizzy-calendar-weekdays div{padding:10px 6px;text-align:center;font-size:12px;font-weight:600;border-bottom:1px solid #dcdcde}
            .dizzy-calendar-day{position:relative;min-height:82px;padding:8px;border:0;border-right:1px solid #e2e4e7;border-bottom:1px solid #e2e4e7;background:#fff;color:#1d2327;text-align:left;cursor:pointer}
            .dizzy-calendar-day:nth-child(7n){border-right:0}
            .dizzy-calendar-day:hover{background:#f6f7f7}
            .dizzy-calendar-day.is-selected{background:#eef5ff;box-shadow:inset 0 0 0 2px #2271b1}
            .dizzy-calendar-day.is-other{color:#8c8f94;background:#fafafa}
            .dizzy-calendar-day-number{font-weight:600}
            .dizzy-calendar-count{display:block;width:max-content;max-width:100%;margin-top:20px;padding:3px 7px;border-radius:10px;background:#e7f3ff;font-size:11px;white-space:nowrap}
            .dizzy-calendar-count.is-busy{background:#fff0d5}
            .dizzy-calendar-detail-head{padding:15px 16px;border-bottom:1px solid #dcdcde}
            .dizzy-calendar-detail-head strong{display:block;font-size:15px}
            .dizzy-calendar-detail-head span{color:#646970}
            .dizzy-calendar-reservation{display:block;width:100%;padding:13px 16px;border:0;border-bottom:1px solid #e2e4e7;background:#fff;color:#1d2327;text-align:left;cursor:pointer}
            .dizzy-calendar-reservation:hover{background:#f6f7f7}
            .dizzy-calendar-reservation-top{display:flex;justify-content:space-between;gap:8px;font-weight:600}
            .dizzy-calendar-reservation-meta{margin-top:4px;color:#646970}
            .dizzy-calendar-status{padding:2px 7px;border-radius:10px;background:#fcf0f1;font-size:11px;font-weight:400}
            .dizzy-calendar-status.confirmed{background:#edfaef;color:#116329}
            .dizzy-calendar-status.waitlisted{background:#fff0d5;color:#674c00}
            .dizzy-calendar-status.cancelled{background:#f0f0f1;color:#50575e}
            .dizzy-calendar-empty{padding:30px 16px;text-align:center;color:#646970}
            @media(max-width:960px){.dizzy-calendar-layout{grid-template-columns:1fr}.dizzy-calendar-day{min-height:68px}.dizzy-calendar-count{margin-top:12px}}
            @media(max-width:600px){.dizzy-calendar-layout{display:block}.dizzy-calendar-surface+.dizzy-calendar-surface{margin-top:12px}.dizzy-calendar-day{min-height:54px;padding:5px}.dizzy-calendar-count{overflow:hidden;margin-top:6px;padding:2px 4px;text-indent:-9999px;width:8px;height:8px}.dizzy-calendar-toolbar{align-items:flex-start;flex-direction:column}}
        </style>
        <div class="dizzy-calendar-toolbar">
            <div class="dizzy-calendar-nav">
                <button type="button" class="button" id="dizzy-calendar-prev" aria-label="<?php esc_attr_e('Previous month', 'dizzy-reservations-manager'); ?>">‹</button>
                <div class="dizzy-calendar-month" id="dizzy-calendar-month"></div>
                <button type="button" class="button" id="dizzy-calendar-next" aria-label="<?php esc_attr_e('Next month', 'dizzy-reservations-manager'); ?>">›</button>
            </div>
            <button type="button" class="button" id="dizzy-calendar-today"><?php esc_html_e('Today', 'dizzy-reservations-manager'); ?></button>
        </div>
        <div class="dizzy-calendar-layout">
            <div class="dizzy-calendar-surface">
                <div class="dizzy-calendar-weekdays">
                    <?php foreach ([__('Mon', 'dizzy-reservations-manager'), __('Tue', 'dizzy-reservations-manager'), __('Wed', 'dizzy-reservations-manager'), __('Thu', 'dizzy-reservations-manager'), __('Fri', 'dizzy-reservations-manager'), __('Sat', 'dizzy-reservations-manager'), __('Sun', 'dizzy-reservations-manager')] as $day) : ?><div><?php echo esc_html($day); ?></div><?php endforeach; ?>
                </div>
                <div class="dizzy-calendar-grid" id="dizzy-calendar-grid"></div>
            </div>
            <aside class="dizzy-calendar-surface" aria-live="polite">
                <div class="dizzy-calendar-detail-head"><strong id="dizzy-calendar-selected-date"></strong><span id="dizzy-calendar-selected-count"></span></div>
                <div id="dizzy-calendar-list"></div>
            </aside>
        </div>
        <script>
        (() => {
            const rows = <?php echo wp_json_encode($rows); ?>;
            const grid = document.getElementById('dizzy-calendar-grid');
            if (!grid) return;
            const monthLabel = document.getElementById('dizzy-calendar-month');
            const selectedDate = document.getElementById('dizzy-calendar-selected-date');
            const selectedCount = document.getElementById('dizzy-calendar-selected-count');
            const list = document.getElementById('dizzy-calendar-list');
            const locale = <?php echo wp_json_encode(str_replace('_', '-', determine_locale())); ?>;
            const labels = {
                reservations: <?php echo wp_json_encode(__('reservations', 'dizzy-reservations-manager')); ?>,
                reservation: <?php echo wp_json_encode(__('reservation', 'dizzy-reservations-manager')); ?>,
                people: <?php echo wp_json_encode(__('people', 'dizzy-reservations-manager')); ?>,
                empty: <?php echo wp_json_encode(__('No reservations for this date.', 'dizzy-reservations-manager')); ?>,
                open: <?php echo wp_json_encode(__('Open reservation', 'dizzy-reservations-manager')); ?>
            };
            const grouped = {};
            rows.forEach(row => {
                if (!/^\d{4}-\d{2}-\d{2}$/.test(row.date)) return;
                (grouped[row.date] ||= []).push(row);
            });
            const now = new Date();
            let view = new Date(now.getFullYear(), now.getMonth(), 1);
            let selected = localKey(now);

            function localKey(date) {
                return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
            }

            function renderDetails() {
                const entries = grouped[selected] || [];
                const date = new Date(selected + 'T12:00:00');
                selectedDate.textContent = new Intl.DateTimeFormat(locale, {weekday:'long', day:'numeric', month:'long', year:'numeric'}).format(date);
                const guests = entries.reduce((total, row) => total + Number(row.people || 0), 0);
                selectedCount.textContent = entries.length + ' ' + (entries.length === 1 ? labels.reservation : labels.reservations) + ' · ' + guests + ' ' + labels.people;
                if (!entries.length) {
                    list.innerHTML = '<div class="dizzy-calendar-empty">' + labels.empty + '</div>';
                    return;
                }
                list.innerHTML = '';
                entries.sort((a,b) => a.time.localeCompare(b.time)).forEach(row => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'dizzy-calendar-reservation';
                    const top = document.createElement('span');
                    top.className = 'dizzy-calendar-reservation-top';
                    const title = document.createElement('span');
                    title.textContent = row.time + ' · ' + row.name;
                    const status = document.createElement('span');
                    status.className = 'dizzy-calendar-status ' + row.status;
                    status.textContent = row.status.charAt(0).toUpperCase() + row.status.slice(1);
                    top.append(title, status);
                    const meta = document.createElement('span');
                    meta.className = 'dizzy-calendar-reservation-meta';
                    meta.textContent = row.people + ' ' + labels.people + ' · ' + labels.open + ' ›';
                    button.append(top, meta);
                    button.addEventListener('click', () => document.getElementById('dizzy-reservation-' + row.id)?.scrollIntoView({behavior:'smooth', block:'center'}));
                    list.appendChild(button);
                });
            }

            function renderCalendar() {
                monthLabel.textContent = new Intl.DateTimeFormat(locale, {month:'long', year:'numeric'}).format(view);
                grid.innerHTML = '';
                const first = new Date(view.getFullYear(), view.getMonth(), 1);
                const offset = (first.getDay() + 6) % 7;
                const start = new Date(view.getFullYear(), view.getMonth(), 1 - offset);
                for (let index = 0; index < 42; index++) {
                    const date = new Date(start);
                    date.setDate(start.getDate() + index);
                    const key = localKey(date);
                    const entries = grouped[key] || [];
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'dizzy-calendar-day' + (date.getMonth() !== view.getMonth() ? ' is-other' : '') + (key === selected ? ' is-selected' : '');
                    button.setAttribute('aria-label', date.toDateString() + (entries.length ? ', ' + entries.length + ' ' + labels.reservations : ''));
                    const number = document.createElement('span');
                    number.className = 'dizzy-calendar-day-number';
                    number.textContent = String(date.getDate());
                    button.appendChild(number);
                    if (entries.length) {
                        const count = document.createElement('span');
                        count.className = 'dizzy-calendar-count' + (entries.length >= 4 ? ' is-busy' : '');
                        count.textContent = entries.length + ' ' + labels.reservations;
                        button.appendChild(count);
                    }
                    button.addEventListener('click', () => {
                        selected = key;
                        if (date.getMonth() !== view.getMonth()) view = new Date(date.getFullYear(), date.getMonth(), 1);
                        renderCalendar();
                        renderDetails();
                    });
                    grid.appendChild(button);
                }
            }

            document.getElementById('dizzy-calendar-prev').addEventListener('click', () => { view = new Date(view.getFullYear(), view.getMonth() - 1, 1); renderCalendar(); });
            document.getElementById('dizzy-calendar-next').addEventListener('click', () => { view = new Date(view.getFullYear(), view.getMonth() + 1, 1); renderCalendar(); });
            document.getElementById('dizzy-calendar-today').addEventListener('click', () => { const today = new Date(); view = new Date(today.getFullYear(), today.getMonth(), 1); selected = localKey(today); renderCalendar(); renderDetails(); });
            renderCalendar();
            renderDetails();
        })();
        </script>
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
