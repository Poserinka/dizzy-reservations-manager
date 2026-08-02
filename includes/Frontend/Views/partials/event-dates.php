<?php

declare(strict_types=1);

use Dizzy\Events\Frontend\ViewModels\OccurrenceViewData;

defined('ABSPATH') || exit;

$upcomingOccurrences = $args['upcomingOccurrences'] ?? [];
$pastOccurrences     = $args['pastOccurrences'] ?? [];

$renderOccurrences = static function (array $occurrences): void {
    foreach ($occurrences as $occurrence) {
        if (! $occurrence instanceof OccurrenceViewData) {
            continue;
        }
        ?>
        <li>
            <?php echo esc_html($occurrence->date . ' - ' . $occurrence->time); ?>
        </li>
        <?php
    }
};
?>

<?php if ($upcomingOccurrences !== []) : ?>
    <section class="dizzy-event-dates">
        <h2>
            <?php esc_html_e('Upcoming Dates', 'dizzy-events-manager'); ?>
        </h2>

        <ul>
            <?php $renderOccurrences($upcomingOccurrences); ?>
        </ul>
    </section>
<?php endif; ?>

<?php if ($pastOccurrences !== []) : ?>
    <section class="dizzy-event-past-dates">
        <h2>
            <?php esc_html_e('Past Dates', 'dizzy-events-manager'); ?>
        </h2>

        <ul>
            <?php $renderOccurrences($pastOccurrences); ?>
        </ul>
    </section>
<?php endif;
