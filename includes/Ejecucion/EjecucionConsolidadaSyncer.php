<?php
namespace SysmanSuite\Ejecucion;

if ( ! defined( 'ABSPATH' ) ) exit;

class EjecucionConsolidadaSyncer {

    private SysmanClient $client;

    public function __construct( ?SysmanClient $client = null ) {
        $this->client = $client ?? SysmanClient::instance();
    }

    public function sync( string $compania, int $anio, int $mes ): array|\WP_Error {
        $data = $this->client->fetch( [
            'compania'   => $compania,
            'anio'       => $anio,
            'mes'        => $mes,
            'numinforme' => 1,
        ] );

        if ( is_wp_error( $data ) ) {
            return $data;
        }

        if ( empty( $data ) ) {
            return [ 'inserted' => 0 ];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sysman_ejecucion_gastos';

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE compania = %s AND anio = %d AND mes = %d",
            $compania, $anio, $mes
        ) );

        $field_map = [
            'codigocuenta'            => 'codigocuenta',
            'nombrerubro'             => 'nombrerubro',
            'movimiento'              => 'movimiento',
            'destino'                 => 'destino',
            'bpid'                    => 'bpid',
            'apropiacioninicial'      => 'apropiacioninicial',
            'adicion'                 => 'adicion',
            'reduccion'               => 'reduccion',
            'credito'                 => 'credito',
            'contracredito'           => 'contracredito',
            'aplazamiento'            => 'aplazamiento',
            'desplazaminento'         => 'desplazaminento',
            'apropiacionvigente'      => 'apropiacionvigente',
            'disponibilidades'        => 'disponibilidades',
            'saldodisponible'         => 'saldodisponible',
            'compromisos'             => 'compromisos',
            'disponibilidadesabiertas' => 'disponibilidadesabiertas',
            'obligacion'              => 'obligacion',
            'pagos'                   => 'pagos',
            'obligacionesporpagar'    => 'obligacionesporpagar',
        ];

        $numeric_cols = [
            'apropiacioninicial', 'adicion', 'reduccion', 'credito', 'contracredito',
            'aplazamiento', 'desplazaminento', 'apropiacionvigente', 'disponibilidades',
            'saldodisponible', 'compromisos', 'disponibilidadesabiertas', 'obligacion',
            'pagos', 'obligacionesporpagar',
        ];

        $columns = array_merge(
            [ 'compania', 'anio', 'mes' ],
            array_values( $field_map ),
            [ 'synced_at' ]
        );
        $col_list = implode( ', ', $columns );
        $now = current_time( 'mysql' );
        $inserted = 0;

        foreach ( array_chunk( $data, 500 ) as $batch ) {
            $placeholders = [];
            $values = [];

            foreach ( $batch as $row ) {
                $ph = [ '%s', '%d', '%d' ];
                $vals = [ $compania, $anio, $mes ];

                foreach ( $field_map as $api_key => $db_col ) {
                    if ( in_array( $db_col, $numeric_cols, true ) ) {
                        $ph[] = '%f';
                        $vals[] = (float) ( $row[ $api_key ] ?? 0 );
                    } else {
                        $ph[] = '%s';
                        $vals[] = (string) ( $row[ $api_key ] ?? '' );
                    }
                }

                $ph[] = '%s';
                $vals[] = $now;

                $placeholders[] = '(' . implode( ',', $ph ) . ')';
                $values = array_merge( $values, $vals );
            }

            $sql = "INSERT INTO {$table} ({$col_list}) VALUES " . implode( ',', $placeholders );
            $wpdb->query( $wpdb->prepare( $sql, $values ) );
            $inserted += count( $batch );
        }

        update_option( 'gn_sisman_last_sync_ejecucion_gastos', current_time( 'mysql' ) );

        return [ 'inserted' => $inserted ];
    }
}
