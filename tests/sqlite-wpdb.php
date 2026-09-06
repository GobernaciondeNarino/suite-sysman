<?php
/**
 * $wpdb mínimo sobre SQLite en memoria, para probar las consultas reales del
 * módulo Presupuesto sin levantar MySQL ni WordPress.
 *
 * Solo implementa lo que usan los repositorios: prepare() sustituyendo %s/%d/%f
 * y los cuatro getters. No pretende ser compatible con wpdb.
 */
class Sysman_Sqlite_Wpdb {

    public string $prefix = 'wp_';
    public string $last_error = '';
    public PDO $pdo;

    public function __construct() {
        $this->pdo = new PDO( 'sqlite::memory:' );
        $this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
    }

    public function prepare( $consulta, ...$args ) {
        $valores = ( 1 === count( $args ) && is_array( $args[0] ) ) ? $args[0] : $args;

        foreach ( $valores as $valor ) {
            $literal = is_int( $valor ) || is_float( $valor )
                ? (string) $valor
                : $this->pdo->quote( (string) $valor );
            // El reemplazo va de uno en uno para respetar el orden de los args.
            $consulta = preg_replace( '/%[dfs]/', str_replace( '$', '\\$', $literal ), $consulta, 1 );
        }

        return $consulta;
    }

    public function get_results( $consulta, $modo = null ) {
        return $this->pdo->query( $consulta )->fetchAll( PDO::FETCH_ASSOC ) ?: [];
    }

    public function get_row( $consulta, $modo = null ) {
        return $this->pdo->query( $consulta )->fetch( PDO::FETCH_ASSOC ) ?: null;
    }

    public function get_var( $consulta ) {
        $fila = $this->pdo->query( $consulta )->fetch( PDO::FETCH_NUM );
        return $fila ? $fila[0] : null;
    }

    public function get_col( $consulta, $columna = 0 ) {
        return $this->pdo->query( $consulta )->fetchAll( PDO::FETCH_COLUMN, $columna ) ?: [];
    }

    public function query( $consulta ) {
        return $this->pdo->query( $consulta )->rowCount();
    }
}
