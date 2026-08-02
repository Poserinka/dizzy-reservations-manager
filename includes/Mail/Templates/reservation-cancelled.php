<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

?>
<h2><?php echo esc_html($title ?? 'Reservation cancelled'); ?></h2>
<p><?php echo esc_html($message ?? 'Your reservation has been cancelled.'); ?></p>
