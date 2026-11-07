<?php
/**
 * Initial schema installation. Use explicit DDL to preserve foreign keys.
 * Existing tables and data are retained; future changes need migrations.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function vkh_general_activate() {
    global $wpdb;

    $sql = file_get_contents( dirname( __DIR__ ) . '/database/schema.sql' );
    if ( false === $sql ) {
        wp_die( esc_html__( 'Die VKH-Datenbankstruktur konnte nicht gelesen werden.', 'vkh-general' ) );
    }

    $sql = preg_replace( '/^--.*$/m', '', $sql );
    $sql = str_replace( '`wp_vkh_', '`' . str_replace( '`', '``', $wpdb->prefix ) . 'vkh_', $sql );
    $sql = str_replace(
        'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        $wpdb->get_charset_collate(),
        $sql
    );

    foreach ( explode( ';', $sql ) as $statement ) {
        $statement = trim( $statement );
        if ( '' === $statement ) {
            continue;
        }

        if ( false === $wpdb->query( $statement ) ) {
            wp_die(
                esc_html__( 'Die VKH-Tabellen konnten nicht vollständig angelegt werden. Bitte prüfe die Datenbankberechtigungen und die InnoDB-Unterstützung und aktiviere das Plugin erneut.', 'vkh-general' )
            );
        }
    }
}
