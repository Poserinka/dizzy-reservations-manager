<?php
/** @var array<string,mixed> $experience */
defined('ABSPATH') || exit;

if (empty($experience['has_concert'])) {
    return;
}

$isDinnerOnly = ($experience['reservation_type'] ?? '') === 'dinner_only';
$ticketStatus = (string) ($experience['ticket_status'] ?? 'none');
$ticketUrl = (string) ($experience['ticket_url'] ?? '');
?>
<tr>
 <td align="center" style="padding:14px 18px;Margin:10px 0;background:#fff6d8;border-left:4px solid #ffb900;color:#333333;font-family:arial,'helvetica neue',helvetica,sans-serif">
  <h3 style="Margin:0 0 8px;font-size:20px;line-height:28px;color:#333333"><?php echo esc_html($isDinnerOnly ? __('Dinner only', 'dizzy-reservations-manager') : __('Dinner & Live Music', 'dizzy-reservations-manager')); ?></h3>
  <?php if ($isDinnerOnly) : ?>
   <p style="Margin:0;line-height:22px;font-size:14px"><strong><?php echo esc_html(sprintf(__('Dinner reservation — %1$s–%2$s', 'dizzy-reservations-manager'), (string) $time, (string) ($experience['dinner_cutoff'] ?? ''))); ?></strong><br><?php echo esc_html(sprintf(__('A ticketed concert starts at %s. This reservation does not include concert admission. If you would like to stay for the concert, please book Dinner + Concert.', 'dizzy-reservations-manager'), (string) ($experience['concert_time'] ?? ''))); ?></p>
  <?php else : ?>
   <p style="Margin:0;line-height:22px;font-size:14px"><strong><?php esc_html_e('Your table is guaranteed for the entire concert.', 'dizzy-reservations-manager'); ?></strong><br><?php echo esc_html((string) ($experience['event_title'] ?? '')); ?> — <?php echo esc_html((string) ($experience['concert_time'] ?? '')); ?><br><?php echo esc_html($ticketStatus === 'already_purchased' ? __('Concert tickets: already purchased.', 'dizzy-reservations-manager') : sprintf(__('Concert tickets requested: %1$d × €%2$s per person. Tickets are not valid until payment is completed.', 'dizzy-reservations-manager'), (int) ($experience['ticket_quantity'] ?? 0), number_format_i18n((float) ($experience['ticket_price'] ?? 0), 2))); ?></p>
   <?php if ($ticketStatus === 'buy' && $ticketUrl !== '') : ?><p style="Margin:12px 0 0"><a href="<?php echo esc_url($ticketUrl); ?>" style="display:inline-block;padding:10px 18px;background:#ffb900;color:#111;text-decoration:none;font-weight:bold"><?php esc_html_e('Complete concert ticket payment', 'dizzy-reservations-manager'); ?></a></p><?php endif; ?>
  <?php endif; ?>
 </td>
</tr>
