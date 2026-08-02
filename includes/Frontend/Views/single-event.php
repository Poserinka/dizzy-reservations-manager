<?php

declare(strict_types=1);

use Dizzy\Events\Frontend\ViewModels\SingleEventViewData;

defined('ABSPATH') || exit;

$data = get_query_var('dizzy_event_data');

if (! $data instanceof SingleEventViewData) {
    return;
}

$event   = $data->event;
$details = $data->details;

?>

<div class="dizzy-single-event">

<?php
get_template_part(
    'includes/Frontend/Views/partials/event-header',
    null,
    [
        'event'   => $event,
        'details' => $details,
    ]
);
?>

<?php
get_template_part(
    'includes/Frontend/Views/partials/event-meta',
    null,
    [
        'details' => $details,
    ]
);
?>

<?php
get_template_part(
    'includes/Frontend/Views/partials/event-content',
    null,
    [
        'event' => $event,
    ]
);
?>

<?php
get_template_part(
    'includes/Frontend/Views/partials/event-dates',
    null,
    [
        'upcomingOccurrences' => $data->upcomingOccurrences,
        'pastOccurrences'     => $data->pastOccurrences,
    ]
);
?>

<?php
get_template_part(
    'includes/Frontend/Views/partials/event-ticket',
    null,
    [
        'details' => $details,
    ]
);
?>

</div>
