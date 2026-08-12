<?php
/**
 * Plugin Name: Dizzy Reservations Manager
 * Plugin URI: https://github.com/Poserinka/dizzy-reservations-manager
 * Description: Independent date and time reservations, guest management, messages and reservation reports.
 * Version: 3.4.0
 * Author: Poserinka Design
 * Text Domain: dizzy-reservations-manager
 * Requires PHP: 8.2
 * Update URI: https://github.com/Poserinka/dizzy-reservations-manager
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('DIZZY_RESERVATIONS_VERSION', '3.4.0');
define('DIZZY_RESERVATIONS_PATH', plugin_dir_path(__FILE__));

require_once DIZZY_RESERVATIONS_PATH . 'includes/Autoloader.php';
\Dizzy\Reservations\Autoloader::register();

(new \Dizzy\Reservations\GitHubUpdater(
    __FILE__,
    'dizzy-reservations-manager',
    'Poserinka/dizzy-reservations-manager',
    DIZZY_RESERVATIONS_VERSION
))->register();

register_activation_hook(__FILE__, [\Dizzy\Reservations\Database\Migrations::class, 'run']);

add_action('init', static function (): void {
    \Dizzy\Reservations\Database\Migrations::run();
    (new \Dizzy\Reservations\Plugin())->boot();
}, 20);
