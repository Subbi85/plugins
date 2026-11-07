<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Validate and atomically save exactly one dog, including its relations. */
function vkh_general_save_dog( $input ) {
    global $wpdb;
    $p = $wpdb->prefix . 'vkh_';
    $id = absint( $input['dog_id'] ?? 0 );
    $name = sanitize_text_field( $input['name'] ?? '' );
    $birth = sanitize_text_field( $input['geburtsdatum'] ?? '' );
    if ( '' === $name || mb_strlen( $name ) > 255 ) {
        return new WP_Error( 'name', 'Bitte einen Namen mit maximal 255 Zeichen eingeben.' );
    }
    $date = $birth ? DateTimeImmutable::createFromFormat( '!Y-m-d', $birth ) : false;
    if ( $birth && ( ! $date || $date->format( 'Y-m-d' ) !== $birth ) ) {
        return new WP_Error( 'date', 'Bitte ein gültiges Geburtsdatum eingeben.' );
    }
    if ( $id && ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$p}hund` WHERE id = %d", $id ) ) ) {
        return new WP_Error( 'missing', 'Dieser Hund wurde nicht gefunden.' );
    }
    $groups = array(
        'championtitel' => array( 'column' => 'titel', 'ids' => array(), 'new' => array() ),
        'jlpp_huu' => array( 'column' => 'jlpp_huu', 'ids' => array(), 'new' => array() ),
        'huefte' => array( 'column' => 'befund', 'ids' => array(), 'new' => array() ),
    );
    foreach ( $groups as $table => &$group ) {
        $raw_ids = 'huefte' === $table ? array( $input['huefte_id'] ?? 0 ) : (array) ( $input[ $table ] ?? array() );
        $group['ids'] = array_values( array_unique( array_filter( array_map( 'absint', $raw_ids ) ) ) );
        foreach ( $group['ids'] as $lookup_id ) {
            if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$p}{$table}` WHERE id = %d", $lookup_id ) ) ) {
                return new WP_Error( 'lookup', 'Eine ausgewählte Zuordnung existiert nicht mehr. Bitte Seite neu laden.' );
            }
        }
        $lines = preg_split( '/\R/u', (string) ( $input[ 'new_' . $table ] ?? '' ) );
        foreach ( $lines as $line ) {
            $line = sanitize_text_field( $line );
            if ( mb_strlen( $line ) > 255 ) { return new WP_Error( 'length', 'Neue Titel und Befunde dürfen maximal 255 Zeichen pro Zeile enthalten.' ); }
            if ( '' !== $line ) { $group['new'][] = $line; }
        }
        if ( 'huefte' === $table && count( $group['new'] ) > 1 ) {
            return new WP_Error( 'hip', 'Bitte nur einen neuen Hüftbefund eingeben.' );
        }
    }
    unset( $group );
    $previous = $wpdb->suppress_errors( true );
    try {
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) { throw new RuntimeException(); }
        foreach ( $groups as $table => &$group ) {
            foreach ( array_unique( $group['new'] ) as $value ) {
                $column = $group['column'];
                $lookup_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$p}{$table}` WHERE `{$column}` = %s LIMIT 1", $value ) );
                if ( ! $lookup_id ) {
                    if ( false === $wpdb->insert( $p . $table, array( $column => $value ), array( '%s' ) ) ) { throw new RuntimeException(); }
                    $lookup_id = $wpdb->insert_id;
                }
                if ( 'huefte' === $table ) { $group['ids'] = array(); }
                $group['ids'][] = (int) $lookup_id;
            }
        }
        unset( $group );
        $data = array(
            'name' => $name,
            'geburtsdatum' => $birth ?: null,
            'huefte_id' => $groups['huefte']['ids'][0] ?? null,
            'bemerkungen' => sanitize_textarea_field( $input['bemerkungen'] ?? '' ),
            'verstorben' => empty( $input['verstorben'] ) ? 0 : 1,
        );
        if ( $id ) {
            // Lock/check the row so a concurrent deletion cannot create a false success.
            if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$p}hund` WHERE id = %d FOR UPDATE", $id ) ) ) { throw new RuntimeException(); }
            $result = $wpdb->update( $p . 'hund', $data, array( 'id' => $id ), array( '%s', '%s', '%d', '%s', '%d' ), array( '%d' ) );
        } else {
            $result = $wpdb->insert( $p . 'hund', $data, array( '%s', '%s', '%d', '%s', '%d' ) );
            $id = (int) $wpdb->insert_id;
        }
        if ( false === $result ) { throw new RuntimeException(); }
        foreach ( array( 'championtitel', 'jlpp_huu' ) as $table ) {
            if ( false === $wpdb->delete( $p . 'hund_' . $table, array( 'hund_id' => $id ), array( '%d' ) ) ) { throw new RuntimeException(); }
            foreach ( array_unique( $groups[ $table ]['ids'] ) as $lookup_id ) {
                if ( false === $wpdb->insert( $p . 'hund_' . $table, array( 'hund_id' => $id, $table . '_id' => $lookup_id ), array( '%d', '%d' ) ) ) { throw new RuntimeException(); }
            }
        }
        if ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException(); }
        return $id;
    } catch ( Throwable $error ) {
        $wpdb->query( 'ROLLBACK' );
        return new WP_Error( 'database', 'Speichern fehlgeschlagen. Bitte die Datenbankverbindung prüfen und erneut versuchen.' );
    } finally {
        $wpdb->suppress_errors( $previous );
    }
}

add_action( 'admin_init', 'vkh_general_handle_dog_form' );
function vkh_general_handle_dog_form() {
    if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || 'vkh-general-hunde' !== ( $_GET['page'] ?? '' ) || ! isset( $_POST['vkh_save_dog'] ) ) { return; }
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Keine Berechtigung.' ); }
    check_admin_referer( 'vkh_save_dog' );
    $input = wp_unslash( $_POST );
    // Reject malformed scalar/array fields before processing.
    foreach ( array( 'dog_id', 'name', 'geburtsdatum', 'huefte_id', 'bemerkungen', 'verstorben', 'new_championtitel', 'new_jlpp_huu', 'new_huefte' ) as $key ) {
        if ( isset( $input[ $key ] ) && ! is_scalar( $input[ $key ] ) ) { wp_die( 'Ungültige Formulardaten.' ); }
    }
    foreach ( array( 'championtitel', 'jlpp_huu' ) as $key ) {
        if ( isset( $input[ $key ] ) && ( ! is_array( $input[ $key ] ) || count( array_filter( $input[ $key ], 'is_scalar' ) ) !== count( $input[ $key ] ) ) ) { wp_die( 'Ungültige Formulardaten.' ); }
    }
    $result = vkh_general_save_dog( $input );
    if ( is_wp_error( $result ) ) {
        $GLOBALS['vkh_dog_form_error'] = $result->get_error_message();
        $GLOBALS['vkh_dog_form_input'] = $input;
        return;
    }
    wp_safe_redirect( add_query_arg( array( 'page' => 'vkh-general-hunde', 'saved' => $result ), admin_url( 'admin.php' ) ) );
    exit;
}