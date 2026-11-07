<?php
/**
 * Plugin Name: VKH General
 * Description: Zentrale Verwaltung mit Übersicht, unseren Hunden, unseren Welpen und Interessenten.
 * Version: 0.2.1
 * Text Domain: vkh-general
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/admin/menu.php';
require_once __DIR__ . '/includes/logging.php';
require_once __DIR__ . '/includes/database.php';

register_activation_hook( __FILE__, 'vkh_general_activate' );

require_once __DIR__ . '/includes/dogs.php';
