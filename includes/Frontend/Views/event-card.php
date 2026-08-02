<?php

declare(strict_types=1);

/**
 * Event card template.
 *
 * @var \Dizzy\Events\Frontend\ViewModels\EventViewData $event
 */

defined('ABSPATH') || exit;

$datePresentation = $event->cardDatePresentation();
$visibleDates     = $datePresentation['visible'];
$remainingDates   = $datePresentation['remaining'];
$eventUrl         = esc_url(trim($event->url));
$imageUrl         = esc_url(trim($event->image));
$artist           = $event->artist !== null ? trim($event->artist) : '';
$venue            = $event->venue !== null ? trim($event->venue) : '';
$address          = $event->address !== null ? trim($event->address) : '';
$excerpt          = trim($event->excerpt);
$mapsUrl          = esc_url(
    $event->mapsUrl !== null
        ? trim($event->mapsUrl)
        : ''
);
$ticketUrl        = esc_url(
    $event->ticketUrl !== null
        ? trim($event->ticketUrl)
        : ''
);
$hasEventUrl      = $eventUrl !== '';
?>
<article class="dizzy-event-card">
    <?php if ($event->featured) : ?>
        <span class="dizzy-event-featured">
            <?php esc_html_e('Featured', 'dizzy-events-manager'); ?>
        </span>
    <?php endif; ?>

    <?php if ($imageUrl !== '') : ?>
        <?php if ($hasEventUrl) : ?>
            <a href="<?php echo $eventUrl; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>">
                <img
                    src="<?php echo $imageUrl; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>"
                    alt="<?php echo esc_attr($event->title); ?>"
                >
            </a>
        <?php else : ?>
            <img
                src="<?php echo $imageUrl; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>"
                alt="<?php echo esc_attr($event->title); ?>"
            >
        <?php endif; ?>
    <?php endif; ?>

    <h3 class="dizzy-event-title">
        <?php if ($hasEventUrl) : ?>
            <a href="<?php echo $eventUrl; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>">
                <?php echo esc_html($event->title); ?>
            </a>
        <?php else : ?>
            <?php echo esc_html($event->title); ?>
        <?php endif; ?>
    </h3>

    <?php if ($artist !== '') : ?>
        <p class="dizzy-event-artist">
            <?php echo esc_html($artist); ?>
        </p>
    <?php endif; ?>

    <?php if ($venue !== '') : ?>
        <p class="dizzy-event-venue">
            <?php echo esc_html($venue); ?>
        </p>
    <?php endif; ?>

    <?php if ($address !== '') : ?>
        <p class="dizzy-event-address">
            <?php if ($mapsUrl !== '') : ?>
                <a
                    href="<?php echo $mapsUrl; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <?php echo esc_html($address); ?>
                </a>
            <?php else : ?>
                <?php echo esc_html($address); ?>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if ($visibleDates !== []) : ?>
        <section class="dizzy-event-dates">
            <h4>
                <?php esc_html_e('Dates', 'dizzy-events-manager'); ?>
            </h4>

            <ul>
                <?php foreach ($visibleDates as $date) : ?>
                    <li>
                        <strong><?php echo esc_html($date->date); ?></strong>
                        <span><?php echo esc_html($date->time); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($remainingDates > 0) : ?>
                <p class="dizzy-event-more-dates">
                    <?php
                    $moreDatesLabel = sprintf(
                        /* translators: %d: number of additional event dates. */
                        _n(
                            '+%d more date',
                            '+%d more dates',
                            $remainingDates,
                            'dizzy-events-manager'
                        ),
                        $remainingDates
                    );
                    ?>
                    <?php if ($hasEventUrl) : ?>
                        <a href="<?php echo $eventUrl; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>">
                            <?php echo esc_html($moreDatesLabel); ?>
                        </a>
                    <?php else : ?>
                        <?php echo esc_html($moreDatesLabel); ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($event->ticketPrice !== null) : ?>
        <p class="dizzy-event-price">
            <?php
            echo esc_html(
                number_format_i18n($event->ticketPrice, 2)
            );
            ?> €
        </p>
    <?php endif; ?>

    <?php if ($excerpt !== '') : ?>
        <div class="dizzy-event-excerpt">
            <?php echo wp_kses_post($excerpt); ?>
        </div>
    <?php endif; ?>

    <?php if ($ticketUrl !== '') : ?>
        <a
            class="dizzy-event-ticket"
            href="<?php echo $ticketUrl; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>"
            target="_blank"
            rel="noopener noreferrer"
        >
            <?php esc_html_e('Buy Ticket', 'dizzy-events-manager'); ?>
        </a>
    <?php endif; ?>

    <?php if ($hasEventUrl) : ?>
        <a
            class="dizzy-event-link"
            href="<?php echo $eventUrl; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>"
        >
            <?php esc_html_e('Read more', 'dizzy-events-manager'); ?>
        </a>
    <?php endif; ?>
</article>
