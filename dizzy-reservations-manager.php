<?php

/**
 * Plugin Name: Dizzy Events Manager
 * Plugin URI: https://github.com/Poserinka/dizzy-events-manager
 * Description: Advanced event management system for Dizzy Rotterdam.
 * Version: 1.3.0
 * Author: Poserinka Design
 * Author URI: https://poserinka.com
 * Text Domain: dizzy-events-manager
 * Requires PHP: 8.2
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define(
    'DIZZY_EVENTS_VERSION',
    '1.3.0'
);

define(
    'DIZZY_EVENTS_PATH',
    plugin_dir_path(__FILE__)
);

define(
    'DIZZY_EVENTS_URL',
    plugin_dir_url(__FILE__)
);

$autoload = DIZZY_EVENTS_PATH . 'vendor/autoload.php';

if (file_exists($autoload)) {
    require_once $autoload;
} else {
    require_once DIZZY_EVENTS_PATH . 'includes/Core/Autoloader.php';
    \Dizzy\Events\Core\Autoloader::register();
}

register_activation_hook(
    __FILE__,
    static function (): void {
        \Dizzy\Events\Database\Migrations::run();
    }
);

add_action(
    'init',
    static function (): void {
        \Dizzy\Events\Database\Migrations::run();

        $application = new \Dizzy\Events\Core\Application();
        $application->boot();

        $GLOBALS['dizzy_events_application'] = $application;
    },
    0
);
