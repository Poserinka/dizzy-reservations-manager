<?php

declare(strict_types=1);

use Dizzy\Events\Models\EventDetails;

defined('ABSPATH') || exit;

$details = $args['details'] ?? null;

if (! $details instanceof EventDetails) {
    return;
}

$ticketUrl = $details->ticketUrl !== null
    ? esc_url(trim($details->ticketUrl))
    : '';
?>
<?php if ($details->ticketPrice !== null) : ?>
    <p class="dizzy-event-price">
        <strong>
            <?php esc_html_e('Price:', 'dizzy-events-manager'); ?>
        </strong>
        <?php
        echo esc_html(
            number_format_i18n($details->ticketPrice, 2)
        );
        ?> €
    </p>
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
