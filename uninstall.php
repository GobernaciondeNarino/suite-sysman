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
    $wpdb->prefix . 'sysman_personal_nomina',
    $wpdb->prefix . 'sysman_ejecucion_ingresos',
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
    'sysman_db_version',
    'sysman_api_base_url',
    'sysman_github_repo',
    'sysman_d3_cdn_url',
    'sysman_d3plus_cdn_url',
    'gn_sisman_schema_version',
    'gn_sisman_last_sync_ejecucion_module',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Delete transients
delete_transient( 'sysman_import_status' );
delete_transient( 'sysman_suite_update_info' );

// Delete cached module transients (gn_sisman_*)
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_gn\\_sisman\\_%' "
    . "OR option_name LIKE '\\_transient\\_timeout\\_gn\\_sisman\\_%'"
);

// Delete plugin CPT posts (charts and seguimientos)
foreach ( [ 'sysman_chart', 'gn_ejecucion' ] as $post_type ) {
    $ids = get_posts( [
        'post_type'   => $post_type,
        'numberposts' => -1,
        'post_status' => 'any',
        'fields'      => 'ids',
    ] );

    foreach ( $ids as $post_id ) {
        wp_delete_post( $post_id, true );
    }
}

// Remove log directory in uploads
$upload  = wp_upload_dir( null, false );
$log_dir = trailingslashit( $upload['basedir'] ) . 'sysman-suite';
if ( is_dir( $log_dir ) ) {
    foreach ( (array) glob( $log_dir . '/*' ) as $file ) {
        if ( is_file( $file ) ) {
            @unlink( $file );
        }
    }
    @unlink( $log_dir . '/.htaccess' );
    @rmdir( $log_dir );
}

// Clear cron
wp_clear_scheduled_hook( 'sysman_scheduled_import' );
