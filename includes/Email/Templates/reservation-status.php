<?php
/**
 * Reservation status-change email.
 *
 * Available variables:
 * $site_name, $site_url, $reservation_id, $name, $email, $phone,
 * $date, $time, $guests, $message, $status and $status_message.
 *
 * This file may be edited as HTML. Keep dynamic values escaped as shown below.
 */
defined('ABSPATH') || exit;
?>
<!doctype html>
<html>
<head>
    <meta charset="<?php echo esc_attr(get_bloginfo('charset')); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html((string) $status_message); ?></title>
</head>
<body style="margin:0;padding:0;background:#0b0b0b;color:#ffffff;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#0b0b0b;">
    <tr>
        <td align="center" style="padding:32px 16px;">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background:#191919;">
                <tr>
                    <td style="padding:34px 38px;border-bottom:1px solid #333333;">
                        <div style="font-size:13px;letter-spacing:2px;text-transform:uppercase;color:#bdbdbd;"><?php echo esc_html((string) $site_name); ?></div>
                        <h1 style="margin:12px 0 0;color:#ffffff;font-size:28px;line-height:1.25;"><?php echo esc_html((string) $status_message); ?></h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:34px 38px;color:#ffffff;">
                        <p style="margin:0 0 24px;font-size:16px;line-height:1.65;"><?php echo esc_html(sprintf(__('Hello %s, the status of your reservation has changed.', 'dizzy-reservations-manager'), (string) $name)); ?></p>
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">
                            <tr><td style="padding:11px 0;border-bottom:1px solid #333333;color:#a7a7a7;"><?php esc_html_e('Status', 'dizzy-reservations-manager'); ?></td><td align="right" style="padding:11px 0;border-bottom:1px solid #333333;color:#ffffff;text-transform:capitalize;"><?php echo esc_html((string) $status); ?></td></tr>
                            <tr><td style="padding:11px 0;border-bottom:1px solid #333333;color:#a7a7a7;"><?php esc_html_e('Date', 'dizzy-reservations-manager'); ?></td><td align="right" style="padding:11px 0;border-bottom:1px solid #333333;color:#ffffff;"><?php echo esc_html((string) $date); ?></td></tr>
                            <tr><td style="padding:11px 0;border-bottom:1px solid #333333;color:#a7a7a7;"><?php esc_html_e('Time', 'dizzy-reservations-manager'); ?></td><td align="right" style="padding:11px 0;border-bottom:1px solid #333333;color:#ffffff;"><?php echo esc_html((string) $time); ?></td></tr>
                            <tr><td style="padding:11px 0;border-bottom:1px solid #333333;color:#a7a7a7;"><?php esc_html_e('Number of people', 'dizzy-reservations-manager'); ?></td><td align="right" style="padding:11px 0;border-bottom:1px solid #333333;color:#ffffff;"><?php echo esc_html((string) $guests); ?></td></tr>
                        </table>
                        <p style="margin:26px 0 0;color:#8f8f8f;font-size:12px;"><?php echo esc_html(sprintf(__('Reservation number: %d', 'dizzy-reservations-manager'), (int) $reservation_id)); ?></p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
