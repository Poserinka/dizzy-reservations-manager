<?php

declare(strict_types=1);

namespace Dizzy\Events\Admin;

use Dizzy\Events\Core\Config;

defined('ABSPATH') || exit;

final class PosterSettings
{
    private const GROUP = 'dizzy-events';

    public function register(): void
    {
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_menu', [$this, 'registerMenu']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . Config::POST_TYPE_EVENT,
            esc_html__('Poster Settings', 'dizzy-events-manager'),
            esc_html__('Poster Settings', 'dizzy-events-manager'),
            'manage_options',
            'dizzy-poster-settings',
            [$this, 'renderPage']
        );
    }

    public function registerSettings(): void
    {
        register_setting(self::GROUP, 'dizzy_events_openai_api_key', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting(self::GROUP, 'dizzy_events_watermark_image_id', ['type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0]);
        register_setting(self::GROUP, 'dizzy_events_watermark_alignment', ['type' => 'string', 'sanitize_callback' => [$this, 'sanitizeAlignment'], 'default' => 'top_center']);
        register_setting(self::GROUP, 'dizzy_events_watermark_offset_x', ['type' => 'number', 'sanitize_callback' => [$this, 'sanitizeOffset'], 'default' => 0]);
        register_setting(self::GROUP, 'dizzy_events_watermark_offset_y', ['type' => 'number', 'sanitize_callback' => [$this, 'sanitizeOffset'], 'default' => 0]);
        register_setting(self::GROUP, 'dizzy_events_watermark_offset_unit', ['type' => 'string', 'sanitize_callback' => [$this, 'sanitizeUnit'], 'default' => 'percentages']);
        register_setting(self::GROUP, 'dizzy_events_watermark_size_mode', ['type' => 'string', 'sanitize_callback' => [$this, 'sanitizeSizeMode'], 'default' => 'scaled']);
        register_setting(self::GROUP, 'dizzy_events_watermark_custom_width', ['type' => 'integer', 'sanitize_callback' => [$this, 'sanitizeWidth'], 'default' => 400]);
        register_setting(self::GROUP, 'dizzy_events_watermark_scale', ['type' => 'integer', 'sanitize_callback' => [$this, 'sanitizePercent'], 'default' => 35]);
        register_setting(self::GROUP, 'dizzy_events_watermark_opacity', ['type' => 'integer', 'sanitize_callback' => [$this, 'sanitizePercent'], 'default' => 85]);
        register_setting(self::GROUP, 'dizzy_events_watermark_social', ['type' => 'boolean', 'sanitize_callback' => [$this, 'sanitizeCheckbox'], 'default' => true]);
        register_setting(self::GROUP, 'dizzy_events_watermark_print', ['type' => 'boolean', 'sanitize_callback' => [$this, 'sanitizeCheckbox'], 'default' => false]);
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        wp_enqueue_media();

        $imageId = (int) get_option('dizzy_events_watermark_image_id', 0);
        $imageUrl = $imageId > 0 ? (string) wp_get_attachment_image_url($imageId, 'full') : '';
        $alignment = (string) get_option('dizzy_events_watermark_alignment', 'top_center');
        $unit = (string) get_option('dizzy_events_watermark_offset_unit', 'percentages');
        $sizeMode = (string) get_option('dizzy_events_watermark_size_mode', 'scaled');
        $positions = ['top_left', 'top_center', 'top_right', 'middle_left', 'middle_center', 'middle_right', 'bottom_left', 'bottom_center', 'bottom_right'];
        ?>
        <div class="wrap dizzy-watermark-settings">
            <h1><?php esc_html_e('Poster Settings', 'dizzy-events-manager'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
                <?php settings_fields(self::GROUP); ?>
                <h2><?php esc_html_e('AI Poster Settings', 'dizzy-events-manager'); ?></h2>
                <table class="form-table"><tr><th><label for="dizzy-openai-key"><?php esc_html_e('OpenAI API Key', 'dizzy-events-manager'); ?></label></th><td><input id="dizzy-openai-key" type="password" class="regular-text" name="dizzy_events_openai_api_key" value="<?php echo esc_attr((string) get_option('dizzy_events_openai_api_key', '')); ?>"></td></tr></table>

                <h2><?php esc_html_e('Logo / Watermark', 'dizzy-events-manager'); ?></h2>
                <p><?php esc_html_e('These settings are applied proportionally to all social poster sizes.', 'dizzy-events-manager'); ?></p>
                <table class="form-table">
                    <tr><th><?php esc_html_e('Apply watermark', 'dizzy-events-manager'); ?></th><td>
                        <label><input type="checkbox" name="dizzy_events_watermark_social" value="1" <?php checked((bool) get_option('dizzy_events_watermark_social', true)); ?>> <?php esc_html_e('Social media posters', 'dizzy-events-manager'); ?></label><br>
                        <label><input type="checkbox" name="dizzy_events_watermark_print" value="1" <?php checked((bool) get_option('dizzy_events_watermark_print', false)); ?>> <?php esc_html_e('A4 print posters', 'dizzy-events-manager'); ?></label>
                    </td></tr>
                    <tr><th><?php esc_html_e('Watermark image', 'dizzy-events-manager'); ?></th><td>
                        <input id="dizzy-watermark-id" type="hidden" name="dizzy_events_watermark_image_id" value="<?php echo esc_attr((string) $imageId); ?>">
                        <button id="dizzy-select-watermark" type="button" class="button"><?php esc_html_e('Select image', 'dizzy-events-manager'); ?></button>
                        <button id="dizzy-remove-watermark" type="button" class="button"><?php esc_html_e('Remove image', 'dizzy-events-manager'); ?></button>
                        <p class="description"><?php esc_html_e('Use a transparent PNG or WebP. The WordPress site logo is used when no image is selected.', 'dizzy-events-manager'); ?></p>
                    </td></tr>
                    <tr><th><?php esc_html_e('Watermark alignment', 'dizzy-events-manager'); ?></th><td><div id="dizzy-alignment-grid" class="dizzy-alignment-grid">
                        <?php foreach ($positions as $position) : ?><label class="<?php echo $alignment === $position ? 'is-selected' : ''; ?>"><input type="radio" name="dizzy_events_watermark_alignment" value="<?php echo esc_attr($position); ?>" <?php checked($alignment, $position); ?>><span></span></label><?php endforeach; ?>
                    </div></td></tr>
                    <tr><th><?php esc_html_e('Watermark offset', 'dizzy-events-manager'); ?></th><td>
                        <label>x: <input class="small-text" type="number" step="0.1" name="dizzy_events_watermark_offset_x" value="<?php echo esc_attr((string) get_option('dizzy_events_watermark_offset_x', 0)); ?>"></label>
                        <label>y: <input class="small-text" type="number" step="0.1" name="dizzy_events_watermark_offset_y" value="<?php echo esc_attr((string) get_option('dizzy_events_watermark_offset_y', 0)); ?>"></label>
                    </td></tr>
                    <tr><th><?php esc_html_e('Offset unit', 'dizzy-events-manager'); ?></th><td>
                        <label><input type="radio" name="dizzy_events_watermark_offset_unit" value="pixels" <?php checked($unit, 'pixels'); ?>> <?php esc_html_e('pixels', 'dizzy-events-manager'); ?></label>
                        <label><input type="radio" name="dizzy_events_watermark_offset_unit" value="percentages" <?php checked($unit, 'percentages'); ?>> <?php esc_html_e('percentages', 'dizzy-events-manager'); ?></label>
                    </td></tr>
                    <tr><th><?php esc_html_e('Watermark preview', 'dizzy-events-manager'); ?></th><td><div id="dizzy-watermark-preview"><img<?php echo $imageUrl !== '' ? ' src="' . esc_url($imageUrl) . '"' : ''; ?> alt=""></div></td></tr>
                    <tr><th><?php esc_html_e('Watermark size', 'dizzy-events-manager'); ?></th><td>
                        <?php foreach (['original' => __('Original', 'dizzy-events-manager'), 'custom' => __('Custom', 'dizzy-events-manager'), 'scaled' => __('Scaled', 'dizzy-events-manager')] as $value => $label) : ?><label><input type="radio" name="dizzy_events_watermark_size_mode" value="<?php echo esc_attr($value); ?>" <?php checked($sizeMode, $value); ?>> <?php echo esc_html($label); ?></label> <?php endforeach; ?>
                        <p><label><?php esc_html_e('Custom width', 'dizzy-events-manager'); ?> <input class="small-text" type="number" min="1" max="4000" name="dizzy_events_watermark_custom_width" value="<?php echo esc_attr((string) get_option('dizzy_events_watermark_custom_width', 400)); ?>"> px</label></p>
                        <p><label><?php esc_html_e('Scaled width', 'dizzy-events-manager'); ?> <input type="range" min="1" max="100" name="dizzy_events_watermark_scale" value="<?php echo esc_attr((string) get_option('dizzy_events_watermark_scale', 35)); ?>"> <output></output>%</label></p>
                    </td></tr>
                    <tr><th><?php esc_html_e('Watermark opacity', 'dizzy-events-manager'); ?></th><td><label><input type="range" min="0" max="100" name="dizzy_events_watermark_opacity" value="<?php echo esc_attr((string) get_option('dizzy_events_watermark_opacity', 85)); ?>"> <output></output>%</label></td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <style>
            .dizzy-alignment-grid{display:grid;grid-template-columns:repeat(3,40px);width:120px;border:1px solid #ccd0d4}.dizzy-alignment-grid label{height:40px;border:1px solid #e2e4e7;display:grid;place-items:center;cursor:pointer}.dizzy-alignment-grid input{display:none}.dizzy-alignment-grid span{width:9px;height:9px;border-radius:50%;background:#c8c9cc}.dizzy-alignment-grid .is-selected{background:#3858e9}.dizzy-alignment-grid .is-selected span{background:#fff}#dizzy-watermark-preview{position:relative;width:min(600px,100%);aspect-ratio:3/2;overflow:hidden;border:1px solid #ccd0d4;background-color:#fff;background-image:linear-gradient(45deg,#e7e7e7 25%,transparent 25%),linear-gradient(-45deg,#e7e7e7 25%,transparent 25%),linear-gradient(45deg,transparent 75%,#e7e7e7 75%),linear-gradient(-45deg,transparent 75%,#e7e7e7 75%);background-size:24px 24px;background-position:0 0,0 12px,12px -12px,-12px 0}#dizzy-watermark-preview img{position:absolute;max-width:none;display:none}.dizzy-watermark-settings label{margin-right:14px}
        </style>
        <script>
        (()=>{const form=document.querySelector('.dizzy-watermark-settings form'),preview=document.querySelector('#dizzy-watermark-preview img'),id=document.querySelector('#dizzy-watermark-id');if(!form)return;let frame;const val=n=>form.querySelector(`[name="${n}"]:checked`)?.value||form.querySelector(`[name="${n}"]`)?.value||'';const update=()=>{document.querySelectorAll('#dizzy-alignment-grid label').forEach(l=>l.classList.toggle('is-selected',l.querySelector('input').checked));form.querySelectorAll('input[type=range]').forEach(r=>r.parentElement.querySelector('output').textContent=r.value);if(!preview.src){preview.style.display='none';return}preview.style.display='block';const mode=val('dizzy_events_watermark_size_mode'),scale=Number(val('dizzy_events_watermark_scale')),custom=Number(val('dizzy_events_watermark_custom_width'));preview.style.width=mode==='scaled'?scale+'%':mode==='custom'?Math.min(100,custom/6)+'%':'auto';preview.style.height='auto';preview.style.opacity=Number(val('dizzy_events_watermark_opacity'))/100;const p=val('dizzy_events_watermark_alignment').split('_'),unit=val('dizzy_events_watermark_offset_unit')==='percentages'?'%':'px',x=Number(val('dizzy_events_watermark_offset_x'))+unit,y=Number(val('dizzy_events_watermark_offset_y'))+unit;preview.style.left=p[1]==='left'?x:p[1]==='right'?'auto':'calc(50% + '+x+')';preview.style.right=p[1]==='right'?x:'auto';preview.style.top=p[0]==='top'?y:p[0]==='bottom'?'auto':'calc(50% + '+y+')';preview.style.bottom=p[0]==='bottom'?y:'auto';preview.style.transform=(p[1]==='center'?'translateX(-50%) ':'')+(p[0]==='middle'?'translateY(-50%)':'')};form.addEventListener('input',update);document.querySelector('#dizzy-select-watermark').addEventListener('click',()=>{frame=wp.media({title:'Select watermark',multiple:false,library:{type:'image'}});frame.on('select',()=>{const a=frame.state().get('selection').first().toJSON();id.value=a.id;preview.src=a.url;update()});frame.open()});document.querySelector('#dizzy-remove-watermark').addEventListener('click',()=>{id.value='0';preview.removeAttribute('src');update()});update()})();
        </script>
        <?php
    }

    public function sanitizeAlignment(mixed $value): string
    {
        $allowed = ['top_left', 'top_center', 'top_right', 'middle_left', 'middle_center', 'middle_right', 'bottom_left', 'bottom_center', 'bottom_right'];
        return in_array($value, $allowed, true) ? (string) $value : 'top_center';
    }

    public function sanitizeUnit(mixed $value): string { return $value === 'pixels' ? 'pixels' : 'percentages'; }
    public function sanitizeSizeMode(mixed $value): string { return in_array($value, ['original', 'custom', 'scaled'], true) ? (string) $value : 'scaled'; }
    public function sanitizeOffset(mixed $value): float { return max(-10000, min(10000, (float) $value)); }
    public function sanitizeWidth(mixed $value): int { return max(1, min(4000, (int) $value)); }
    public function sanitizePercent(mixed $value): int { return max(0, min(100, (int) $value)); }
    public function sanitizeCheckbox(mixed $value): bool { return (bool) $value; }
}
