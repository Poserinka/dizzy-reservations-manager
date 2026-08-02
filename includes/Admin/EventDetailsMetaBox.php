<?php

declare(strict_types=1);

namespace Dizzy\Events\Admin;

use Dizzy\Events\Core\Config;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Event details administration box.
 *
 * @package Dizzy\Events\Admin
 */
final class EventDetailsMetaBox
{
    /**
     * Register hooks.
     */
    public function register(): void
    {
        add_action(
            'add_meta_boxes_' . Config::POST_TYPE_EVENT,
            [
                $this,
                'addMetaBox',
            ]
        );

        add_action(
            'save_post_' . Config::POST_TYPE_EVENT,
            [
                $this,
                'save',
            ]
        );
    }

    /**
     * Add meta box.
     */
    public function addMetaBox(): void
    {
        add_meta_box(
            'dizzy_event_additional_details',
            esc_html__('Additional Event Details', 'dizzy-events-manager'),
            [
                $this,
                'render',
            ],
            Config::POST_TYPE_EVENT,
            'side',
            'default'
        );
    }

    /**
     * Render fields.
     */
    public function render(WP_Post $post): void
    {
        wp_nonce_field(
            'dizzy_event_details_save',
            'dizzy_event_details_nonce'
        );

        $fields = [
            'ticket_url'   => '',
            'ticket_price' => '',
        ];

        foreach ($fields as $field => $defaultValue) {
            $value = get_post_meta(
                $post->ID,
                '_dizzy_' . $field,
                true
            );

            if ($value === '') {
                $value = $defaultValue;
            }

            $inputType = match ($field) {
                'maps_url', 'ticket_url' => 'url',
                'ticket_price'           => 'number',
                default                  => 'text',
            };
            ?>
            <p>
                <label for="dizzy-<?php echo esc_attr($field); ?>">
                    <?php
                    echo esc_html(
                        ucfirst(
                            str_replace('_', ' ', $field)
                        )
                    );
                    ?>
                </label>

                <input
                    id="dizzy-<?php echo esc_attr($field); ?>"
                    type="<?php echo esc_attr($inputType); ?>"
                    class="widefat"
                    name="dizzy_<?php echo esc_attr($field); ?>"
                    value="<?php echo esc_attr((string) $value); ?>"
                    <?php if ($field === 'ticket_price') : ?>
                        min="0"
                        step="0.01"
                        inputmode="decimal"
                    <?php endif; ?>
                >

                <?php if ($field === 'ticket_price') : ?>
                    <span class="description">
                        <?php esc_html_e('Price in euros.', 'dizzy-events-manager'); ?>
                    </span>
                <?php endif; ?>
            </p>
            <?php
        }

        $featured = get_post_meta(
            $post->ID,
            '_dizzy_featured',
            true
        );
        ?>
        <p>
            <label>
                <input
                    type="checkbox"
                    name="dizzy_featured"
                    value="1"
                    <?php checked($featured, '1'); ?>
                >

                <?php
                esc_html_e(
                    'Featured Event',
                    'dizzy-events-manager'
                );
                ?>
            </label>
        </p>
        <?php
    }

    /**
     * Save fields.
     */
    public function save(int $postId): void
    {
        if (! $this->canSave($postId)) {
            return;
        }

        $fields = [
            'ticket_url',
            'ticket_price',
        ];

        foreach ($fields as $field) {
            $key = 'dizzy_' . $field;

            if (! isset($_POST[$key]) || ! is_string($_POST[$key])) {
                continue;
            }

            $value = wp_unslash($_POST[$key]);

            if (in_array($field, ['maps_url', 'ticket_url'], true)) {
                $value = esc_url_raw($value);
            } elseif ($field === 'ticket_price') {
                $value = $this->sanitizeTicketPrice($value);
            } else {
                $value = sanitize_text_field($value);
            }

            update_post_meta(
                $postId,
                '_dizzy_' . $field,
                $value
            );
        }

        update_post_meta(
            $postId,
            '_dizzy_featured',
            isset($_POST['dizzy_featured']) ? '1' : '0'
        );
    }

    /**
     * Normalize a submitted ticket price.
     */
    private function sanitizeTicketPrice(string $value): string
    {
        $value = trim(str_replace(',', '.', $value));

        if ($value === '' || ! is_numeric($value)) {
            return '';
        }

        $price = max(0.0, (float) $value);

        return number_format($price, 2, '.', '');
    }

    /**
     * Determine whether event details can be saved.
     */
    private function canSave(int $postId): bool
    {
        if (
            ! isset($_POST['dizzy_event_details_nonce'])
            || ! is_string($_POST['dizzy_event_details_nonce'])
        ) {
            return false;
        }

        $nonce = sanitize_text_field(
            wp_unslash($_POST['dizzy_event_details_nonce'])
        );

        if (! wp_verify_nonce($nonce, 'dizzy_event_details_save')) {
            return false;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }

        if (wp_is_post_revision($postId) !== false) {
            return false;
        }

        if (wp_is_post_autosave($postId) !== false) {
            return false;
        }

        return current_user_can('edit_post', $postId);
    }
}
