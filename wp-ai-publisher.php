<?php
/**
 * Plugin Name: WP AI Publisher
 * Plugin URI:  https://github.com/
 * Description: Automação inteligente de criação e agendamento de posts com texto e imagens gerados por IA.
 * Version:     1.0.0
 * Author:      Jônathas Quintanilha (Garré)
 * License:     GPL-2.0-or-later
 * Text Domain: wp-ai-publisher
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

defined( 'ABSPATH' ) || exit;

// ── Constantes ───────────────────────────────────────────────────────────────
define( 'WPAIP_VERSION',   '1.0.0' );
define( 'WPAIP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPAIP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPAIP_SLUG',      'wp-ai-publisher' );

// ── Autoload ──────────────────────────────────────────────────────────────────
spl_autoload_register( function ( string $class ): void {
    $prefix = 'WPAIP\\';
    if ( ! str_starts_with( $class, $prefix ) ) {
        return;
    }
    $relative = str_replace( [ $prefix, '\\' ], [ '', DIRECTORY_SEPARATOR ], $class );
    $file     = WPAIP_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . 'class-' . strtolower( $relative ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

// ── Includes manuais (não PSR-4) ──────────────────────────────────────────────
require_once WPAIP_PLUGIN_DIR . 'includes/class-security.php';
require_once WPAIP_PLUGIN_DIR . 'includes/class-settings.php';
require_once WPAIP_PLUGIN_DIR . 'includes/class-asaas.php';
require_once WPAIP_PLUGIN_DIR . 'includes/class-paywall.php';
require_once WPAIP_PLUGIN_DIR . 'includes/class-llm.php';
require_once WPAIP_PLUGIN_DIR . 'includes/class-image.php';
require_once WPAIP_PLUGIN_DIR . 'includes/class-media.php';
require_once WPAIP_PLUGIN_DIR . 'includes/class-metabox.php';
require_once WPAIP_PLUGIN_DIR . 'includes/class-cron.php';

// ── Hooks de ciclo de vida ────────────────────────────────────────────────────
register_activation_hook( __FILE__,   [ 'WPAIP_Cron',     'on_activate'   ] );
register_deactivation_hook( __FILE__, [ 'WPAIP_Cron',     'on_deactivate' ] );
register_uninstall_hook( __FILE__,    'wpaip_uninstall' );

function wpaip_uninstall(): void {
    if ( file_exists( WPAIP_PLUGIN_DIR . 'uninstall.php' ) ) {
        require_once WPAIP_PLUGIN_DIR . 'uninstall.php';
    }
}

// ── Boot ──────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function (): void {
    WPAIP_Settings::init();
    WPAIP_Paywall::init();
    WPAIP_Metabox::init();
    WPAIP_Cron::init();
} );
