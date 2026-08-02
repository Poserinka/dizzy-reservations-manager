<?php

declare(strict_types=1);

namespace Dizzy\Events\Admin;

use Dizzy\Events\Core\Config;
use WP_Post;

defined('ABSPATH') || exit;

final class EventStatusMetaBox
{
    private const NONCE_ACTION = 'dizzy_event_status_save';

    private const NONCE_NAME = 'dizzy_event_status_nonce';

    /** @var array<string, string> */
    private array $statuses;

    public function __construct()
    {
        $this->statuses = [
            'publish' => __('Published', 'dizzy-events-manager'),
            'draft' => __('Draft', 'dizzy-events-manager'),
            'pending' => __('Pending Review', 'dizzy-events-manager'),
            'future' => __('Scheduled', 'dizzy-events-manager'),
            'private' => __('Private', 'dizzy-events-manager'),
            'cancelled' => __('Cancelled', 'dizzy-events-manager'),
            'archived' => __('Archived', 'dizzy-events-manager'),
        ];
    }

    public function register(): void
    {
        add_action('add_meta_boxes_' . Config::POST_TYPE_EVENT, [$this, 'addMetaBox']);
        add_filter('wp_insert_post_data', [$this, 'filterStatus'], 20, 2);
    }

    public function addMetaBox(): void
    {
        add_meta_box(
            'dizzy-event-status',
            __('Event Status', 'dizzy-events-manager'),
            [$this, 'render'],
            Config::POST_TYPE_EVENT,
            'side',
            'high'
        );
    }

    public function render(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        ?>
        <p>
            <label for="dizzy_event_status"><?php esc_html_e('Status', 'dizzy-events-manager'); ?></label>
        </p>
        <select id="dizzy_event_status" name="dizzy_event_status" class="widefat">
            <?php foreach ($this->statuses as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($post->post_status, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e('Cancelled and archived events remain available in admin but are hidden from public event and reservation flows.', 'dizzy-events-manager'); ?>
        </p>
        <?php
    }

    /**
     * Apply the selected status during the original post save operation.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $postarr
     *
     * @return array<string, mixed>
     */
    public function filterStatus(array $data, array $postarr): array
    {
        if (($data['post_type'] ?? '') !== Config::POST_TYPE_EVENT) {
            return $data;
        }

        $nonce = isset($_POST[self::NONCE_NAME])
            ? sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_NAME]))
            : '';

        $postId = absint($postarr['ID'] ?? 0);
        $canEdit = $postId > 0
            ? current_user_can('edit_post', $postId)
            : current_user_can('edit_posts');

        if (
            $nonce === ''
            || ! wp_verify_nonce($nonce, self::NONCE_ACTION)
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || ! $canEdit
        ) {
            return $data;
        }

        $status = isset($_POST['dizzy_event_status'])
            ? sanitize_key(wp_unslash((string) $_POST['dizzy_event_status']))
            : '';

        if (! isset($this->statuses[$status])) {
            return $data;
        }

        $data['post_status'] = $status;

        return $data;
    }
}

