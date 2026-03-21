<?php
/**
 * SYSMAN Suite - Uninstall
 * Removes all plugin data on uninstall.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom tables
$tables = [
    $wpdb->prefix . 'sysman_ejecucion_gastos',
    $wpdb->prefix . 'sysman_auxiliar_cuentas',
    $wpdb->prefix . 'sysman_plan_presupuestal',
];

foreach ( $tables as $table ) {
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

// Delete options
$options = [
    'sysman_api_compania',
    'sysman_api_anio',
    'sysman_api_mes',
    'sysman_import_frequency',
    'sysman_last_import',
    'sysman_update_notice_dismissed',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Delete transients
delete_transient( 'sysman_import_status' );
delete_transient( 'sysman_suite_update_info' );

// Delete chart CPT posts
$charts = get_posts( [
    'post_type'   => 'sysman_chart',
    'numberposts' => -1,
    'post_status' => 'any',
    'fields'      => 'ids',
] );

foreach ( $charts as $chart_id ) {
    wp_delete_post( $chart_id, true );
}

// Clear cron
wp_clear_scheduled_hook( 'sysman_scheduled_import' );
