<?php
namespace SysmanSuite\Ejecucion;

if ( ! defined( 'ABSPATH' ) ) exit;

class MovimientosSyncer {

    private SysmanClient $client;

    public function __construct( ?SysmanClient $client = null ) {
        $this->client = $client ?? SysmanClient::instance();
    }

    public function sync( string $compania, int $anio, int $mes, string $tipoCpte ): array|\WP_Error {
        $data = $this->client->fetch( [
            'compania'   => $compania,
            'anio'       => $anio,
            'mes'        => $mes,
            'numinforme' => 2,
            'tipo_cpte'  => $tipoCpte,
        ] );

        if ( is_wp_error( $data ) ) {
            return $data;
        }

        if ( empty( $data ) ) {
            return [ 'inserted' => 0, 'tipo' => $tipoCpte ];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sysman_auxiliar_cuentas';

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE compania = %s AND anio = %d AND mes = %d AND tipocpte = %s",
            $compania, $anio, $mes, $tipoCpte
        ) );

        $field_map = [
            'numero'              => 'numero',
            'nombrepred'          => 'nombrepred',
            'idprede'             => 'idprede',
            'nombreplan'          => 'nombreplan',
            'rubro'               => 'rubro',
            'fecha'               => 'fecha',
            'tipocpte'            => 'tipocpte',
            'tercero'             => 'tercero',
            'nombretercero'       => 'nombretercero',
            'descripcion'         => 'descripcion',
            'nrodocumento'        => 'nrodocumento',
            'valordebito'         => 'valordebito',
            'valorcredito'        => 'valorcredito',
            'debitoafectado'      => 'debitoafectado',
            'creditoafectado'     => 'creditoafectado',
            'modificaciondebito'  => 'modificaciondebito',
            'modificacioncredito' => 'modificacioncredito',
            'saldoporejecutaresp' => 'saldoporejecutaresp',
            'tipocpteafect'       => 'tipocpteafect',
            'cmpteafectado'       => 'cmpteafectado',
        ];

        $numeric_cols = [
            'valordebito', 'valorcredito', 'debitoafectado', 'creditoafectado',
            'modificaciondebito', 'modificacioncredito', 'saldoporejecutaresp',
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
            $result = $wpdb->query( $wpdb->prepare( $sql, $values ) );
            if ( false === $result ) {
                return new \WP_Error( 'sysman_db', $wpdb->last_error ?: 'Error al insertar en auxiliar_cuentas' );
            }
            $inserted += count( $batch );
        }

        update_option( "gn_sisman_last_sync_auxiliar_{$tipoCpte}", current_time( 'mysql' ) );

        return [ 'inserted' => $inserted, 'tipo' => $tipoCpte ];
    }
}
