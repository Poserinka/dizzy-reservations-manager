# Reservation email templates

These files control the HTML emails sent by Dizzy Reservations Manager.

## Templates

- `reservation-confirmed.php`: sent after a new reservation is created.
- `reservation-status.php`: sent when an administrator changes a reservation status.

You may edit the HTML and inline CSS directly. Email clients work best with table layouts and inline CSS.

## Available variables

Both templates receive:

- `$site_name`
- `$site_url`
- `$reservation_id`
- `$name`
- `$email`
- `$phone`
- `$date` (DD/MM/YYYY)
- `$time`
- `$guests`
- `$message`
- `$status`

The status template also receives `$status_message`.

Escape dynamic output:

```php
<?php echo esc_html((string) $name); ?>
```

Allow message line breaks:

```php
<?php echo nl2br(esc_html((string) $message)); ?>
```

Plugin updates replace files inside the plugin directory. Keep a backup of customized templates before updating.
