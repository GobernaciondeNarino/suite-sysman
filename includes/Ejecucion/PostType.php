<?php
namespace SysmanSuite\Ejecucion;

if ( ! defined( 'ABSPATH' ) ) exit;

class PostType {

    public function register(): void {
        add_action( 'init', [ $this, 'register_post_type' ] );
    }

    public function register_post_type(): void {
        register_post_type( 'gn_ejecucion', [
            'labels' => [
                'name'               => __( 'Ejecución', 'sysman-suite' ),
                'singular_name'      => __( 'Seguimiento', 'sysman-suite' ),
                'add_new'            => __( 'Nuevo Seguimiento', 'sysman-suite' ),
                'add_new_item'       => __( 'Nuevo Seguimiento de Ejecución', 'sysman-suite' ),
                'edit_item'          => __( 'Editar Seguimiento', 'sysman-suite' ),
                'view_item'          => __( 'Ver Seguimiento', 'sysman-suite' ),
                'all_items'          => __( 'Todos los Seguimientos', 'sysman-suite' ),
                'search_items'       => __( 'Buscar Seguimientos', 'sysman-suite' ),
                'not_found'          => __( 'No se encontraron seguimientos', 'sysman-suite' ),
                'not_found_in_trash' => __( 'No hay seguimientos en la papelera', 'sysman-suite' ),
            ],
            'public'             => false,
            'show_ui'            => false,
            'show_in_menu'       => false,
            'show_in_rest'       => false,
            'supports'           => [ 'title' ],
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
        ] );
    }
}
