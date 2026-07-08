<?php
/**
 * Executado ao desinstalar o plugin via WP Admin.
 * Remove todas as opções e dados do banco.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove opções principais
delete_option( 'wpaip_settings'          );
delete_option( 'wpaip_cron_schedules'    );
delete_option( 'wpaip_cron_logs'         );

// Remove post meta de posts gerados pelo plugin
delete_post_meta_by_key( '_wpaip_generated' );
delete_post_meta_by_key( '_wpaip_schedule_id' );

// Remove eventos WP-Cron agendados pelo plugin
$schedules = get_option( 'wpaip_cron_schedules', [] );
foreach ( (array) $schedules as $s ) {
    $hook      = 'wpaip_run_schedule_' . ( $s['id'] ?? '' );
    $timestamp = wp_next_scheduled( $hook );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, $hook );
    }
}
