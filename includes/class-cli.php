<?php
namespace SysmanSuite;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WP-CLI commands for SYSMAN Suite.
 *
 * ## EXAMPLES
 *
 *     wp sysman import --anio=2024 --mes=1
 *     wp sysman stats
 *     wp sysman truncate --yes
 */
class Cli {

    /**
     * Import data from SYSMAN API.
     *
     * ## OPTIONS
     *
     * [--compania=<compania>]
     * : Company code. Default: 001
     *
     * [--anio=<anio>]
     * : Year to import. Default: current year
     *
     * [--mes=<mes>]
     * : Month to import. Default: current month
     *
     * [--report=<report>]
     * : Report type: all, ejecucion, auxiliar, plan. Default: all
     *
     * [--tipo_cpte=<tipo_cpte>]
     * : Document type for auxiliar report. Default: RES
     *
     * ## EXAMPLES
     *
     *     wp sysman import --anio=2024 --mes=6
     *     wp sysman import --report=ejecucion --anio=2024 --mes=1
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Named arguments.
     */
    public function import( array $args, array $assoc_args ): void {
        $compania  = $assoc_args['compania'] ?? '001';
        $anio      = (int) ( $assoc_args['anio'] ?? date( 'Y' ) );
        $mes       = (int) ( $assoc_args['mes'] ?? date( 'n' ) );
        $report    = $assoc_args['report'] ?? 'all';
        $tipo_cpte = $assoc_args['tipo_cpte'] ?? 'RES';

        $plugin   = \Sysman_Suite::instance();
        $importer = $plugin->importer;

        \WP_CLI::log( "Importando datos SYSMAN: Compañía={$compania}, Año={$anio}, Mes={$mes}, Informe={$report}" );

        $start = microtime( true );

        switch ( $report ) {
            case 'ejecucion':
                $result = $importer->import_ejecucion( $compania, $anio, $mes );
                $this->print_result( 'Ejecución Presupuestal', $result );
                break;
            case 'auxiliar':
                $result = $importer->import_auxiliar( $compania, $anio, $mes, $tipo_cpte );
                $this->print_result( 'Auxiliar Presupuestal', $result );
                break;
            case 'plan':
                $result = $importer->import_plan( $compania, $anio, $mes );
                $this->print_result( 'Plan Presupuestal', $result );
                break;
            default:
                $results = $importer->import_all( $compania, $anio, $mes );
                foreach ( $results as $key => $result ) {
                    $this->print_result( ucfirst( $key ), $result );
                }
                break;
        }

        $elapsed = round( microtime( true ) - $start, 2 );
        \WP_CLI::success( "Importación completada en {$elapsed}s." );
    }

    /**
     * Show database statistics.
     *
     * ## EXAMPLES
     *
     *     wp sysman stats
     */
    public function stats(): void {
        $plugin = \Sysman_Suite::instance();
        $stats  = $plugin->database->get_stats();

        $rows = [];
        foreach ( $stats as $table => $info ) {
            $rows[] = [
                'Tabla'      => $info['label'],
                'Nombre BD'  => $table,
                'Registros'  => number_format( $info['count'] ),
            ];
        }

        \WP_CLI\Utils\format_items( 'table', $rows, [ 'Tabla', 'Nombre BD', 'Registros' ] );
    }

    /**
     * Truncate all plugin tables.
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Skip confirmation prompt.
     *
     * ## EXAMPLES
     *
     *     wp sysman truncate --yes
     */
    public function truncate( array $args, array $assoc_args ): void {
        \WP_CLI::confirm( '¿Está seguro de eliminar todos los datos del plugin?', $assoc_args );

        $plugin = \Sysman_Suite::instance();
        $plugin->database->drop_tables();
        $plugin->database->create_tables();

        \WP_CLI::success( 'Todas las tablas han sido vaciadas y recreadas.' );
    }

    private function print_result( string $label, array $result ): void {
        if ( $result['success'] ) {
            \WP_CLI::log( "  {$label}: {$result['imported']}/{$result['total']} registros importados." );
        } else {
            \WP_CLI::warning( "  {$label}: Error - " . ( $result['error'] ?? 'desconocido' ) );
        }
    }
}
