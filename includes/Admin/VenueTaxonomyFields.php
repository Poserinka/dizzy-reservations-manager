<?php

declare(strict_types=1);

namespace Dizzy\Events\Admin;

use Dizzy\Events\Core\Config;
use WP_Term;

defined('ABSPATH') || exit;

final class VenueTaxonomyFields
{
    public function register(): void
    {
        add_action(Config::TAX_VENUE . '_add_form_fields', [$this, 'renderAddFields']);
        add_action(Config::TAX_VENUE . '_edit_form_fields', [$this, 'renderEditFields']);
        add_action('created_' . Config::TAX_VENUE, [$this, 'save']);
        add_action('edited_' . Config::TAX_VENUE, [$this, 'save']);
    }

    public function renderAddFields(): void
    {
        $this->renderField('Address', 'dizzy_venue_address', 'text', '');
        $this->renderField('Maps URL', 'dizzy_venue_maps_url', 'url', '');
    }

    public function renderEditFields(WP_Term $term): void
    {
        $this->renderField('Address', 'dizzy_venue_address', 'text', (string) get_term_meta($term->term_id, '_dizzy_address', true), true);
        $this->renderField('Maps URL', 'dizzy_venue_maps_url', 'url', (string) get_term_meta($term->term_id, '_dizzy_maps_url', true), true);
    }

    public function save(int $termId): void
    {
        if (! current_user_can('manage_categories')) {
            return;
        }

        if (isset($_POST['dizzy_venue_address']) && is_string($_POST['dizzy_venue_address'])) {
            update_term_meta($termId, '_dizzy_address', sanitize_text_field(wp_unslash($_POST['dizzy_venue_address'])));
        }

        if (isset($_POST['dizzy_venue_maps_url']) && is_string($_POST['dizzy_venue_maps_url'])) {
            update_term_meta($termId, '_dizzy_maps_url', esc_url_raw(wp_unslash($_POST['dizzy_venue_maps_url'])));
        }
    }

    private function renderField(string $label, string $name, string $type, string $value, bool $tableRow = false): void
    {
        $tag = $tableRow ? 'tr' : 'div';
        $class = $tableRow ? 'form-field' : 'form-field term-' . $name . '-wrap';

        echo '<' . esc_attr($tag) . ' class="' . esc_attr($class) . '">';
        echo $tableRow ? '<th scope="row">' : '';
        echo '<label for="' . esc_attr($name) . '">' . esc_html($label) . '</label>';
        echo $tableRow ? '</th><td>' : '';
        echo '<input type="' . esc_attr($type) . '" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
        echo $tableRow ? '</td>' : '';
        echo '</' . esc_attr($tag) . '>';
    }
}
