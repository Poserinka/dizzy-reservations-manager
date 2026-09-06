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
        add_action('admin_head', [$this, 'hideAdminNotices']);
    }

    public function hideAdminNotices(): void
    {
        $page = sanitize_key((string) ($_GET['page'] ?? ''));
        if (! in_array($page, [self::MENU, self::REPORTS], true)) {
            return;
        }
        echo '<style>
            #wpbody-content > .notice,
            #wpbody-content > .update-nag,
            #wpbody-content > .updated,
            #wpbody-content > .error,
            #wpbody-content > div[class*="notice"],
            #wpbody-content .wrap > .notice,
            #wpbody-content .wrap > .update-nag,
            .notice.notice-info,
            .notice.notice-warning,
            .notice.notice-error,
            .notice.notice-success,
            .mc4wp-is-dismissible {display:none!important}
        </style>';
    }

    public function menu(): void
    {
        add_menu_page(__('Reservations Manager', 'dizzy-reservations-manager'), __('Reservations', 'dizzy-reservations-manager'), ControllerRole::RESERVATIONS_CAP, self::MENU, [$this, 'reservations'], 'dashicons-clipboard', 26);
        add_submenu_page(self::MENU, __('Reservations', 'dizzy-reservations-manager'), __('Reservations', 'dizzy-reservations-manager'), ControllerRole::RESERVATIONS_CAP, self::MENU, [$this, 'reservations']);
        add_submenu_page(self::MENU, __('Reservation Reports', 'dizzy-reservations-manager'), __('Reports', 'dizzy-reservations-manager'), 'manage_options', self::REPORTS, [$this, 'reports']);
    }

    public function reservations(): void
    {
        $this->guard(ControllerRole::RESERVATIONS_CAP);
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
            <div class="dizzy-reservations-workspace">
                <section class="dizzy-reservations-list">
                    <div class="dizzy-list-heading">
                        <div class="dizzy-list-summary">
                            <h2 id="dizzy-list-title"><?php esc_html_e('All Reservations', 'dizzy-reservations-manager'); ?></h2>
                            <span><?php esc_html_e('Total reservations:', 'dizzy-reservations-manager'); ?> <strong id="dizzy-total-reservations"><?php echo esc_html((string) count($rows)); ?></strong></span>
                            <span><?php esc_html_e('Total Guests:', 'dizzy-reservations-manager'); ?> <strong id="dizzy-total-guests"><?php echo esc_html((string) array_sum(array_map(static fn (array $row): int => (int) $row['guests'], $rows))); ?></strong></span>
                        </div>
                        <button type="button" class="button-link" id="dizzy-show-all"><?php esc_html_e('Show all', 'dizzy-reservations-manager'); ?></button>
                    </div>
                    <div class="dizzy-reservations-table-wrap">
                        <table class="widefat striped">
                            <thead><tr><th><?php esc_html_e('Guest', 'dizzy-reservations-manager'); ?></th><th><?php esc_html_e('Date', 'dizzy-reservations-manager'); ?></th><th><?php esc_html_e('Time', 'dizzy-reservations-manager'); ?></th><th><?php esc_html_e('People', 'dizzy-reservations-manager'); ?></th><th><?php esc_html_e('Table', 'dizzy-reservations-manager'); ?></th><th><?php esc_html_e('Message', 'dizzy-reservations-manager'); ?></th><th><?php esc_html_e('Status', 'dizzy-reservations-manager'); ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($rows as $row) : $id = (int) $row['id']; ?>
                                <tr id="dizzy-reservation-<?php echo esc_attr((string) $id); ?>" data-reservation-date="<?php echo esc_attr((string) $row['reservation_date']); ?>">
                                    <td><strong><?php echo esc_html((string) $row['name']); ?></strong><br><?php echo esc_html((string) $row['email']); ?><br><?php echo esc_html((string) $row['phone']); ?></td>
                                    <td><?php echo esc_html($this->formatDate((string) $row['reservation_date'])); ?></td>
                                    <td><?php echo esc_html(substr((string) $row['reservation_time'], 0, 5)); ?></td>
                                    <td><?php echo esc_html((string) $row['guests']); ?></td>
                                    <td><strong><?php echo esc_html((string) ($row['table_code'] ?: '—')); ?></strong><?php if (! empty($row['table_label']) && $row['table_label'] !== $row['table_code']) : ?><br><small><?php echo esc_html((string) $row['table_label']); ?></small><?php endif; ?></td>
                                    <td><?php echo nl2br(esc_html((string) $row['notes'])); ?></td>
                                    <td><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="dizzy_reservation_status"><input type="hidden" name="reservation_id" value="<?php echo esc_attr((string) $id); ?>"><?php wp_nonce_field('dizzy_reservation_' . $id); ?><select name="status"><?php foreach (self::STATUSES as $status) : ?><option value="<?php echo esc_attr($status); ?>" <?php selected($row['status'], $status); ?>><?php echo esc_html(ucfirst($status)); ?></option><?php endforeach; ?></select> <button class="button"><?php esc_html_e('Save', 'dizzy-reservations-manager'); ?></button></form></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="dizzy-no-reservations" hidden><td colspan="7"><?php esc_html_e('No reservations for this date.', 'dizzy-reservations-manager'); ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <aside class="dizzy-reservations-calendar-column">
                    <?php $this->calendar($calendarRows); ?>
                </aside>
            </div>
        </div>
        <?php
    }

    private function calendar(array $rows): void
    {
        ?>
        <style>
            .dizzy-reservations-workspace{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(460px,.9fr);gap:24px;align-items:start;margin-top:14px}
            .dizzy-reservations-list,.dizzy-calendar-surface{background:#fff;border:1px solid #c3c4c7}
            .dizzy-list-heading{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:1px solid #c3c4c7}
            .dizzy-list-heading h2{margin:0;font-size:14px}.dizzy-list-summary{display:flex;align-items:center;gap:18px;flex-wrap:wrap}.dizzy-list-summary>span{color:#50575e}.dizzy-list-summary strong{color:#1d2327}
            .dizzy-reservations-table-wrap{overflow-x:auto}
            .dizzy-reservations-table-wrap .widefat{border:0}.dizzy-reservations-table-wrap .widefat tbody td{vertical-align:middle}.dizzy-reservations-table-wrap .widefat tbody form{display:flex;align-items:center;gap:5px}
            .dizzy-reservations-table-wrap tr.is-calendar-hidden{display:none}
            .dizzy-calendar-toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}
            .dizzy-calendar-nav{display:flex;align-items:center;gap:8px}
            .dizzy-calendar-month{min-width:150px;text-align:center;font-size:15px;font-weight:600;text-transform:capitalize}
            .dizzy-calendar-weekdays,.dizzy-calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr))}
            .dizzy-calendar-weekdays div{padding:10px 4px;text-align:center;font-size:12px;font-weight:600;border-bottom:1px solid #dcdcde}
            .dizzy-calendar-day{position:relative;min-height:72px;padding:7px;border:0;border-right:1px solid #e2e4e7;border-bottom:1px solid #e2e4e7;background:#fff;color:#1d2327;text-align:left;cursor:pointer}
            .dizzy-calendar-day:nth-child(7n){border-right:0}
            .dizzy-calendar-day:hover{background:#f6f7f7}
            .dizzy-calendar-day.is-selected{background:#eef5ff;box-shadow:inset 0 0 0 2px #2271b1}
            .dizzy-calendar-day.is-other{color:#8c8f94;background:#fafafa}
            .dizzy-calendar-day-number{font-weight:600}
            .dizzy-calendar-count{display:block;width:max-content;max-width:100%;margin-top:15px;padding:3px 6px;border-radius:10px;background:#e7f3ff;font-size:10px;white-space:nowrap}
            .dizzy-calendar-count.is-busy{background:#fff0d5}
            @media(max-width:1300px){.dizzy-reservations-workspace{grid-template-columns:minmax(0,1.35fr) minmax(400px,.9fr)}.dizzy-calendar-day{min-height:64px}}
            @media(max-width:1050px){.dizzy-reservations-workspace{display:flex;flex-direction:column-reverse}.dizzy-reservations-list,.dizzy-reservations-calendar-column{width:100%}}
            @media(max-width:600px){.dizzy-calendar-day{min-height:52px;padding:4px}.dizzy-calendar-count{overflow:hidden;margin-top:5px;padding:0;width:8px;height:8px;text-indent:-9999px}.dizzy-calendar-toolbar{align-items:flex-start;flex-direction:column}}
        </style>
        <div class="dizzy-calendar-toolbar">
            <div class="dizzy-calendar-nav">
                <button type="button" class="button" id="dizzy-calendar-prev" aria-label="<?php esc_attr_e('Previous month', 'dizzy-reservations-manager'); ?>">‹</button>
                <div class="dizzy-calendar-month" id="dizzy-calendar-month"></div>
                <button type="button" class="button" id="dizzy-calendar-next" aria-label="<?php esc_attr_e('Next month', 'dizzy-reservations-manager'); ?>">›</button>
            </div>
            <button type="button" class="button" id="dizzy-calendar-today"><?php esc_html_e('Today', 'dizzy-reservations-manager'); ?></button>
        </div>
        <div class="dizzy-calendar-surface">
            <div class="dizzy-calendar-weekdays">
                <?php foreach ([__('Mon', 'dizzy-reservations-manager'), __('Tue', 'dizzy-reservations-manager'), __('Wed', 'dizzy-reservations-manager'), __('Thu', 'dizzy-reservations-manager'), __('Fri', 'dizzy-reservations-manager'), __('Sat', 'dizzy-reservations-manager'), __('Sun', 'dizzy-reservations-manager')] as $day) : ?><div><?php echo esc_html($day); ?></div><?php endforeach; ?>
            </div>
            <div class="dizzy-calendar-grid" id="dizzy-calendar-grid"></div>
        </div>
        <script>
        (() => {
            const rows = <?php echo wp_json_encode($rows); ?>;
            const grid = document.getElementById('dizzy-calendar-grid');
            if (!grid) return;
            const monthLabel = document.getElementById('dizzy-calendar-month');
            const listTitle = document.getElementById('dizzy-list-title');
            const showAll = document.getElementById('dizzy-show-all');
            const totalReservations = document.getElementById('dizzy-total-reservations');
            const totalGuests = document.getElementById('dizzy-total-guests');
            const tableRows = Array.from(document.querySelectorAll('[data-reservation-date]'));
            const emptyRow = document.getElementById('dizzy-no-reservations');
            const locale = <?php echo wp_json_encode(str_replace('_', '-', determine_locale())); ?>;
            const allLabel = <?php echo wp_json_encode(__('All Reservations', 'dizzy-reservations-manager')); ?>;
            const reservationsLabel = <?php echo wp_json_encode(__('reservations', 'dizzy-reservations-manager')); ?>;
            const grouped = {};
            rows.forEach(row => {
                if (/^\d{4}-\d{2}-\d{2}$/.test(row.date)) (grouped[row.date] ||= []).push(row);
            });
            const now = new Date();
            let view = new Date(now.getFullYear(), now.getMonth(), 1);
            let selected = '';

            function localKey(date) {
                return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
            }

            function filterList() {
                let visible = 0;
                tableRows.forEach(row => {
                    const show = !selected || row.dataset.reservationDate === selected;
                    row.classList.toggle('is-calendar-hidden', !show);
                    if (show) visible++;
                });
                emptyRow.hidden = !selected || visible > 0;
                const filtered = selected ? rows.filter(row => row.date === selected) : rows;
                totalReservations.textContent = String(filtered.length);
                totalGuests.textContent = String(filtered.reduce((sum, row) => sum + Number(row.people || 0), 0));
                showAll.hidden = !selected;
                listTitle.textContent = selected
                    ? new Intl.DateTimeFormat(locale, {weekday:'long', day:'numeric', month:'long', year:'numeric'}).format(new Date(selected + 'T12:00:00'))
                    : allLabel;
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
                    button.setAttribute('aria-label', date.toDateString() + (entries.length ? ', ' + entries.length + ' ' + reservationsLabel : ''));
                    const number = document.createElement('span');
                    number.className = 'dizzy-calendar-day-number';
                    number.textContent = String(date.getDate());
                    button.appendChild(number);
                    if (entries.length) {
                        const count = document.createElement('span');
                        count.className = 'dizzy-calendar-count' + (entries.length >= 4 ? ' is-busy' : '');
                        count.textContent = entries.length + ' ' + reservationsLabel;
                        button.appendChild(count);
                    }
                    button.addEventListener('click', () => {
                        selected = key;
                        if (date.getMonth() !== view.getMonth()) view = new Date(date.getFullYear(), date.getMonth(), 1);
                        renderCalendar();
                        filterList();
                    });
                    grid.appendChild(button);
                }
            }

            showAll.addEventListener('click', () => { selected = ''; renderCalendar(); filterList(); });
            document.getElementById('dizzy-calendar-prev').addEventListener('click', () => { view = new Date(view.getFullYear(), view.getMonth() - 1, 1); renderCalendar(); });
            document.getElementById('dizzy-calendar-next').addEventListener('click', () => { view = new Date(view.getFullYear(), view.getMonth() + 1, 1); renderCalendar(); });
            document.getElementById('dizzy-calendar-today').addEventListener('click', () => { const today = new Date(); view = new Date(today.getFullYear(), today.getMonth(), 1); selected = localKey(today); renderCalendar(); filterList(); });
            renderCalendar();
            filterList();
        })();
        </script>
        <?php
    }

    public function reports(): void
    {
        $this->guard();
        $summary = $this->repository->reportSummary();
        $rows = $this->repository->reportRows();
        $calendarRows = array_map(static fn (array $row): array => [
            'date' => (string) ($row['reservation_date'] ?? ''),
            'reservations' => (int) ($row['reservations'] ?? 0),
            'confirmed_guests' => (int) ($row['confirmed_guests'] ?? 0),
            'waitlisted' => (int) ($row['waitlisted'] ?? 0),
        ], $rows);
        $export = wp_nonce_url(admin_url('admin-post.php?action=dizzy_reservation_report_csv'), 'dizzy_reservation_report_csv');
        ?>
        <div class="wrap dizzy-reservation-reports-admin">
            <h1><?php esc_html_e('Reservation Reports', 'dizzy-reservations-manager'); ?></h1>
            <div class="dizzy-reservation-reports-workspace">
                <section class="dizzy-reservation-report-list">
                    <div class="dizzy-reservation-report-heading">
                        <div>
                            <h2 id="dizzy-reservation-report-title"><?php esc_html_e('All Reservation Reports', 'dizzy-reservations-manager'); ?></h2>
                            <div class="dizzy-reservation-report-cards">
                                <div><strong id="dizzy-reservation-report-total"><?php echo esc_html((string) $summary['reservations']); ?></strong><?php esc_html_e('Reservations', 'dizzy-reservations-manager'); ?></div>
                                <div><strong id="dizzy-reservation-report-confirmed"><?php echo esc_html((string) $summary['confirmed_guests']); ?></strong><?php esc_html_e('Confirmed guests', 'dizzy-reservations-manager'); ?></div>
                                <div><strong id="dizzy-reservation-report-waitlisted"><?php echo esc_html((string) $summary['waitlisted']); ?></strong><?php esc_html_e('Waitlisted', 'dizzy-reservations-manager'); ?></div>
                            </div>
                        </div>
                        <button type="button" class="button-link" id="dizzy-reservation-report-show-all"><?php esc_html_e('Show all', 'dizzy-reservations-manager'); ?></button>
                    </div>
                    <div class="dizzy-reservation-report-actions"><a class="button" href="<?php echo esc_url($export); ?>"><?php esc_html_e('Export CSV', 'dizzy-reservations-manager'); ?></a></div>
                    <div class="dizzy-reservation-report-table-wrap">
                        <table class="widefat striped">
                            <thead><tr><th><?php esc_html_e('Date', 'dizzy-reservations-manager'); ?></th><th><?php esc_html_e('Time', 'dizzy-reservations-manager'); ?></th><th><?php esc_html_e('Reservations', 'dizzy-reservations-manager'); ?></th><th><?php esc_html_e('People', 'dizzy-reservations-manager'); ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($rows as $row) : ?>
                                <tr data-reservation-report-date="<?php echo esc_attr((string) $row['reservation_date']); ?>">
                                    <td><?php echo esc_html($this->formatDate((string) $row['reservation_date'])); ?></td>
                                    <td><?php echo esc_html(substr((string) $row['reservation_time'], 0, 5)); ?></td>
                                    <td><?php echo esc_html((string) $row['reservations']); ?></td>
                                    <td><?php echo esc_html((string) $row['guests']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="dizzy-reservation-report-empty" hidden><td colspan="4"><?php esc_html_e('No reservation reports for this date.', 'dizzy-reservations-manager'); ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <aside class="dizzy-reservation-report-calendar-column"><?php $this->reportsCalendar($calendarRows); ?></aside>
            </div>
        </div>
        <?php
    }

    private function reportsCalendar(array $rows): void
    {
        ?>
        <style>
            .dizzy-reservation-reports-workspace{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(460px,.9fr);gap:24px;align-items:start;margin-top:14px}
            .dizzy-reservation-report-list,.dizzy-report-calendar-surface{background:#fff;border:1px solid #c3c4c7}
            .dizzy-reservation-report-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:12px;border-bottom:1px solid #c3c4c7}
            .dizzy-reservation-report-heading h2{margin:0 0 12px;font-size:14px}.dizzy-reservation-report-cards{display:flex;gap:12px;flex-wrap:wrap}
            .dizzy-reservation-report-cards>div{background:#fff;border:1px solid #ccd0d4;padding:12px 14px;min-width:150px}.dizzy-reservation-report-cards strong{display:block;font-size:22px;line-height:1.1}
            .dizzy-reservation-report-actions{padding:10px 12px}.dizzy-reservation-report-table-wrap{overflow-x:auto}.dizzy-reservation-report-table-wrap .widefat{border:0}.dizzy-reservation-report-table-wrap tr.is-calendar-hidden{display:none}
            .dizzy-report-calendar-toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}.dizzy-report-calendar-nav{display:flex;align-items:center;gap:8px}
            .dizzy-report-calendar-month{min-width:150px;text-align:center;font-size:15px;font-weight:600;text-transform:capitalize}
            .dizzy-report-calendar-weekdays,.dizzy-report-calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr))}
            .dizzy-report-calendar-weekdays div{padding:10px 4px;text-align:center;font-size:12px;font-weight:600;border-bottom:1px solid #dcdcde}
            .dizzy-report-calendar-day{position:relative;min-height:72px;padding:7px;border:0;border-right:1px solid #e2e4e7;border-bottom:1px solid #e2e4e7;background:#fff;color:#1d2327;text-align:left;cursor:pointer}
            .dizzy-report-calendar-day:nth-child(7n){border-right:0}.dizzy-report-calendar-day:hover{background:#f6f7f7}.dizzy-report-calendar-day.is-selected{background:#eef5ff;box-shadow:inset 0 0 0 2px #2271b1}
            .dizzy-report-calendar-day.is-other{color:#8c8f94;background:#fafafa}.dizzy-report-calendar-day-number{font-weight:600}
            .dizzy-report-calendar-count{display:block;width:max-content;max-width:100%;margin-top:15px;padding:3px 6px;border-radius:10px;background:#e7f3ff;font-size:10px;white-space:nowrap}.dizzy-report-calendar-count.is-busy{background:#fff0d5}
            @media(max-width:1300px){.dizzy-reservation-reports-workspace{grid-template-columns:minmax(0,1.35fr) minmax(400px,.9fr)}.dizzy-report-calendar-day{min-height:64px}}
            @media(max-width:1050px){.dizzy-reservation-reports-workspace{display:flex;flex-direction:column-reverse}.dizzy-reservation-report-list,.dizzy-reservation-report-calendar-column{width:100%}}
            @media(max-width:600px){.dizzy-report-calendar-day{min-height:52px;padding:4px}.dizzy-report-calendar-count{overflow:hidden;margin-top:5px;padding:0;width:8px;height:8px;text-indent:-9999px}.dizzy-report-calendar-toolbar{align-items:flex-start;flex-direction:column}}
        </style>
        <div class="dizzy-report-calendar-toolbar">
            <div class="dizzy-report-calendar-nav">
                <button type="button" class="button" id="dizzy-report-calendar-prev" aria-label="<?php esc_attr_e('Previous month', 'dizzy-reservations-manager'); ?>">‹</button>
                <div class="dizzy-report-calendar-month" id="dizzy-report-calendar-month"></div>
                <button type="button" class="button" id="dizzy-report-calendar-next" aria-label="<?php esc_attr_e('Next month', 'dizzy-reservations-manager'); ?>">›</button>
            </div>
            <button type="button" class="button" id="dizzy-report-calendar-today"><?php esc_html_e('Today', 'dizzy-reservations-manager'); ?></button>
        </div>
        <div class="dizzy-report-calendar-surface">
            <div class="dizzy-report-calendar-weekdays"><?php foreach ([__('Mon', 'dizzy-reservations-manager'), __('Tue', 'dizzy-reservations-manager'), __('Wed', 'dizzy-reservations-manager'), __('Thu', 'dizzy-reservations-manager'), __('Fri', 'dizzy-reservations-manager'), __('Sat', 'dizzy-reservations-manager'), __('Sun', 'dizzy-reservations-manager')] as $day) : ?><div><?php echo esc_html($day); ?></div><?php endforeach; ?></div>
            <div class="dizzy-report-calendar-grid" id="dizzy-report-calendar-grid"></div>
        </div>
        <script>
        (() => {
            const rows = <?php echo wp_json_encode($rows); ?>;
            const grid = document.getElementById('dizzy-report-calendar-grid');
            if (!grid) return;
            const monthLabel = document.getElementById('dizzy-report-calendar-month');
            const listTitle = document.getElementById('dizzy-reservation-report-title');
            const showAll = document.getElementById('dizzy-reservation-report-show-all');
            const totalEl = document.getElementById('dizzy-reservation-report-total');
            const confirmedEl = document.getElementById('dizzy-reservation-report-confirmed');
            const waitlistedEl = document.getElementById('dizzy-reservation-report-waitlisted');
            const tableRows = Array.from(document.querySelectorAll('[data-reservation-report-date]'));
            const emptyRow = document.getElementById('dizzy-reservation-report-empty');
            const locale = <?php echo wp_json_encode(str_replace('_', '-', determine_locale())); ?>;
            const allLabel = <?php echo wp_json_encode(__('All Reservation Reports', 'dizzy-reservations-manager')); ?>;
            const reservationsLabel = <?php echo wp_json_encode(__('reservations', 'dizzy-reservations-manager')); ?>;
            const grouped = {};
            rows.forEach(row => {
                if (/^\d{4}-\d{2}-\d{2}$/.test(row.date)) {
                    grouped[row.date] ||= {reservations:0, confirmed_guests:0, waitlisted:0};
                    grouped[row.date].reservations += Number(row.reservations || 0);
                    grouped[row.date].confirmed_guests += Number(row.confirmed_guests || 0);
                    grouped[row.date].waitlisted += Number(row.waitlisted || 0);
                }
            });
            const now = new Date();
            let view = new Date(now.getFullYear(), now.getMonth(), 1);
            let selected = '';

            function localKey(date) {
                return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
            }

            function filterReport() {
                let visible = 0;
                tableRows.forEach(row => {
                    const show = !selected || row.dataset.reservationReportDate === selected;
                    row.classList.toggle('is-calendar-hidden', !show);
                    if (show) visible++;
                });
                emptyRow.hidden = !selected || visible > 0;
                const filtered = selected ? rows.filter(row => row.date === selected) : rows;
                totalEl.textContent = String(filtered.reduce((sum, row) => sum + Number(row.reservations || 0), 0));
                confirmedEl.textContent = String(filtered.reduce((sum, row) => sum + Number(row.confirmed_guests || 0), 0));
                waitlistedEl.textContent = String(filtered.reduce((sum, row) => sum + Number(row.waitlisted || 0), 0));
                showAll.hidden = !selected;
                listTitle.textContent = selected ? new Intl.DateTimeFormat(locale, {weekday:'long',day:'numeric',month:'long',year:'numeric'}).format(new Date(selected + 'T12:00:00')) : allLabel;
            }

            function renderCalendar() {
                monthLabel.textContent = new Intl.DateTimeFormat(locale, {month:'long',year:'numeric'}).format(view);
                grid.innerHTML = '';
                const first = new Date(view.getFullYear(), view.getMonth(), 1);
                const offset = (first.getDay() + 6) % 7;
                const start = new Date(view.getFullYear(), view.getMonth(), 1 - offset);
                for (let index = 0; index < 42; index++) {
                    const date = new Date(start); date.setDate(start.getDate() + index);
                    const key = localKey(date); const totals = grouped[key] || {reservations:0,confirmed_guests:0,waitlisted:0};
                    const button = document.createElement('button'); button.type = 'button';
                    button.className = 'dizzy-report-calendar-day' + (date.getMonth() !== view.getMonth() ? ' is-other' : '') + (key === selected ? ' is-selected' : '');
                    button.setAttribute('aria-label', date.toDateString() + (totals.reservations ? ', ' + totals.reservations + ' ' + reservationsLabel : ''));
                    const number = document.createElement('span'); number.className = 'dizzy-report-calendar-day-number'; number.textContent = String(date.getDate()); button.appendChild(number);
                    if (totals.reservations) {
                        const count = document.createElement('span'); count.className = 'dizzy-report-calendar-count' + (totals.reservations >= 4 ? ' is-busy' : '');
                        count.textContent = totals.reservations + ' ' + reservationsLabel; button.appendChild(count);
                    }
                    button.addEventListener('click', () => { selected = key; if (date.getMonth() !== view.getMonth()) view = new Date(date.getFullYear(), date.getMonth(), 1); renderCalendar(); filterReport(); });
                    grid.appendChild(button);
                }
            }

            showAll.addEventListener('click', () => { selected = ''; renderCalendar(); filterReport(); });
            document.getElementById('dizzy-report-calendar-prev').addEventListener('click', () => { view = new Date(view.getFullYear(), view.getMonth() - 1, 1); renderCalendar(); });
            document.getElementById('dizzy-report-calendar-next').addEventListener('click', () => { view = new Date(view.getFullYear(), view.getMonth() + 1, 1); renderCalendar(); });
            document.getElementById('dizzy-report-calendar-today').addEventListener('click', () => { const today = new Date(); view = new Date(today.getFullYear(), today.getMonth(), 1); selected = localKey(today); renderCalendar(); filterReport(); });
            renderCalendar(); filterReport();
        })();
        </script>
        <?php
    }

    public function status(): void
    {
        $this->guard(ControllerRole::RESERVATIONS_CAP);
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

    private function formatDate(string $date): string
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
        return $parsed instanceof \DateTimeImmutable ? $parsed->format('d/m/Y') : $date;
    }

    private function cards(array $items): string
    {
        $html = '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0">';
        foreach ($items as $label => $value) $html .= '<div style="background:#fff;border:1px solid #ccd0d4;padding:14px;min-width:150px"><strong style="font-size:22px;display:block">' . esc_html((string) $value) . '</strong>' . esc_html((string) $label) . '</div>';
        return $html . '</div>';
    }

    private function guard(string $capability = 'manage_options'): void
    {
        if (! current_user_can($capability)) wp_die(esc_html__('Unauthorized', 'dizzy-reservations-manager'));
    }
}
