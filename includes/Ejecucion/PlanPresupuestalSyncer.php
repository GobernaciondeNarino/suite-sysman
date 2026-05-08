<?php
namespace SysmanSuite\Ejecucion;

if ( ! defined( 'ABSPATH' ) ) exit;

class PlanPresupuestalSyncer {

    private SysmanClient $client;

    public function __construct( ?SysmanClient $client = null ) {
        $this->client = $client ?? SysmanClient::instance();
    }

    public function sync( string $compania, int $anio, int $mes ): array|\WP_Error {
        $data = $this->client->fetch( [
            'compania'   => $compania,
            'anio'       => $anio,
            'mes'        => $mes,
            'numinforme' => 4,
        ] );

        if ( is_wp_error( $data ) ) {
            return $data;
        }

        if ( empty( $data ) ) {
            return [ 'inserted' => 0 ];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sysman_plan_presupuestal';

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE compania = %s AND anio = %d AND mes = %d",
            $compania, $anio, $mes
        ) );

        $field_map = [
            'codigo'              => 'codigo',
            'nombre'              => 'nombre',
            'destino'             => 'destino',
            'naturaleza'          => 'naturaleza',
            'movimiento'          => 'movimiento',
            'tipovigencia'        => 'tipovigencia',
            'sector'              => 'sector',
            'programa'            => 'programa',
            'subPrograma'         => 'subprograma',
            'codigoProducto'      => 'codigoproducto',
            'codigoBPIN'          => 'codigobpin',
            'codigoCCPET'         => 'codigoccpet',
            'codigoCPCDANE'       => 'codigocpcdane',
            'codigoFuente'        => 'codigofuente',
            'codigoCCPETRegalias' => 'codigoccpetregalias',
            'politicaPublica'     => 'politicapublica',
            'detalleSectorial'    => 'detallesectorial',
            'tipoRecurso'         => 'tiporecurso',
            'codigoSIA'           => 'codigosia',
            'dependencia'         => 'dependencia',
            'nombreDependencia'   => 'nombredependencia',
            'codigoEquiv'         => 'codigoequiv',
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
                    $ph[] = '%s';
                    $vals[] = (string) ( $row[ $api_key ] ?? '' );
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

        delete_transient( "gn_sisman_pp_dependencias_{$anio}_{$mes}" );
        update_option( 'gn_sisman_last_sync_plan_presupuestal', current_time( 'mysql' ) );

        return [ 'inserted' => $inserted ];
    }
}
