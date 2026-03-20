<?php
/**
 * SISMAN Suite - Uninstall
 * Removes all plugin data on uninstall.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom tables
$tables = [
    $wpdb->prefix . 'sisman_ejecucion_gastos',
    $wpdb->prefix . 'sisman_auxiliar_cuentas',
    $wpdb->prefix . 'sisman_plan_presupuestal',
];

foreach ( $tables as $table ) {
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

// Delete options
$options = [
    'sisman_api_compania',
    'sisman_api_anio',
    'sisman_api_mes',
    'sisman_import_frequency',
    'sisman_last_import',
    'sisman_update_notice_dismissed',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Delete transients
delete_transient( 'sisman_import_status' );
delete_transient( 'sisman_suite_update_info' );

// Delete chart CPT posts
$charts = get_posts( [
    'post_type'   => 'sisman_chart',
    'numberposts' => -1,
    'post_status' => 'any',
    'fields'      => 'ids',
] );

foreach ( $charts as $chart_id ) {
    wp_delete_post( $chart_id, true );
}

// Clear cron
wp_clear_scheduled_hook( 'sisman_scheduled_import' );
