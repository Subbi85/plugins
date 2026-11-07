<?php
/**
 * Registrierung der Verwaltungsseiten.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', 'vkh_general_register_admin_menu' );

function vkh_general_register_admin_menu() {
    $capability = 'manage_options';
    $pages = array(
        'vkh-general'               => array( 'Übersicht', 'overview.php' ),
        'vkh-general-hunde'         => array( 'Unsere Hunde', 'dogs.php' ),
        'vkh-general-welpen'        => array( 'Unsere Welpen', 'puppies.php' ),
        'vkh-general-interessenten' => array( 'Interessenten', 'prospects.php' ),
    );

    // Eigenes SVG, damit die Pfote mit dem Farbschema des Backends harmoniert.
    $paw = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><g fill="black"><ellipse cx="3.5" cy="7.5" rx="2" ry="2.7" transform="rotate(-25 3.5 7.5)"/><ellipse cx="7.5" cy="4.5" rx="2" ry="2.7" transform="rotate(-10 7.5 4.5)"/><ellipse cx="12.5" cy="4.5" rx="2" ry="2.7" transform="rotate(10 12.5 4.5)"/><ellipse cx="16.5" cy="7.5" rx="2" ry="2.7" transform="rotate(25 16.5 7.5)"/><path d="M10 9c-2.2 0-3 2-4.8 3.8-1.7 1.7-1.2 4.7 1.3 4.7 1.4 0 2.2-.8 3.5-.8s2.1.8 3.5.8c2.5 0 3-3 1.3-4.7C13 11 12.2 9 10 9z"/></g></svg>';
    $render_page = static function ( $file ) use ( $capability ) {
        return static function () use ( $file, $capability ) {
            if ( ! current_user_can( $capability ) ) {
                wp_die( esc_html__( 'Du hast keine Berechtigung, diese Seite aufzurufen.', 'vkh-general' ) );
            }

            require __DIR__ . '/pages/' . $file;
        };
    };

    add_menu_page(
        'Übersicht',
        'Übersicht',
        $capability,
        'vkh-general',
        $render_page( 'overview.php' ),
        'data:image/svg+xml;base64,' . base64_encode( $paw ),
        26
    );

    foreach ( $pages as $slug => $page ) {
        add_submenu_page(
            'vkh-general',
            $page[0],
            $page[0],
            $capability,
            $slug,
            $render_page( $page[1] )
        );
    }
}