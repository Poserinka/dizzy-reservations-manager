<?php

declare(strict_types=1);

defined('ABSPATH') || exit;


$event =
    $args['event'] ?? null;



if (
    ! $event
) {
    return;
}

?>

<div class="dizzy-event-content">


<?php

echo wp_kses_post(

    apply_filters(

        'the_content',

        $event->content

    )

);

?>


</div>