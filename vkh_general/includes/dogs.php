<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Validate and atomically save exactly one dog, including its relations. */
function vkh_general_save_dog( $input ) {
    global $wpdb;
    $previous = $wpdb->suppress_errors( true );
    try {
        return vkh_general_save_dog_data( $input );
    } catch ( Throwable $error ) {
        $reference = vkh_general_log_error( 'dog_save_failed' );
        return new WP_Error( 'database', 'Speichern fehlgeschlagen. Fehlerreferenz: ' . $reference );
    } finally {
        $wpdb->suppress_errors( $previous );
    }
}

function vkh_general_save_dog_data( $input ) {
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
        if ( $wpdb->last_error ) { throw new RuntimeException(); }
        return new WP_Error( 'missing', 'Dieser Hund wurde nicht gefunden.' );
    }
    $media = array();
    foreach ( array( 'foto_id', 'ahnentafel_id' ) as $field ) {
        $media[ $field ] = absint( $input[ $field ] ?? 0 ) ?: null;
        if ( ! $media[ $field ] ) { continue; }
        $attachment = get_post( $media[ $field ] );
        $allowed = $attachment && 'attachment' === $attachment->post_type && 'trash' !== $attachment->post_status;
        $mime = $attachment ? $attachment->post_mime_type : '';
        $allowed = $allowed && ( wp_attachment_is_image( $media[ $field ] ) || ( 'ahnentafel_id' === $field && 'application/pdf' === $mime ) );
        if ( ! $allowed || ! current_user_can( 'read_post', $media[ $field ] ) ) {
            return new WP_Error( 'media', 'Bitte ein gültiges Foto bzw. eine Ahnentafel als Bild oder PDF aus der Mediathek auswählen.' );
        }
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
                if ( $wpdb->last_error ) { throw new RuntimeException(); }
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
                if ( $wpdb->last_error ) { throw new RuntimeException(); }
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
            'foto_id' => $media['foto_id'],
            'ahnentafel_id' => $media['ahnentafel_id'],
            'name' => $name,
            'geburtsdatum' => $birth ?: null,
            'huefte_id' => $groups['huefte']['ids'][0] ?? null,
            'bemerkungen' => sanitize_textarea_field( $input['bemerkungen'] ?? '' ),
            'verstorben' => empty( $input['verstorben'] ) ? 0 : 1,
        );
        if ( $id ) {
            // Lock/check the row so a concurrent deletion cannot create a false success.
            if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$p}hund` WHERE id = %d FOR UPDATE", $id ) ) ) { throw new RuntimeException(); }
            $result = $wpdb->update( $p . 'hund', $data, array( 'id' => $id ), array( '%d', '%d', '%s', '%s', '%d', '%s', '%d' ), array( '%d' ) );
        } else {
            $result = $wpdb->insert( $p . 'hund', $data, array( '%d', '%d', '%s', '%s', '%d', '%s', '%d' ) );
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
        $reference = vkh_general_log_error( 'dog_save_failed' );
        $wpdb->query( 'ROLLBACK' );
        return new WP_Error( 'database', 'Speichern fehlgeschlagen. Bitte erneut versuchen. Fehlerreferenz: ' . $reference );
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
    foreach ( array( 'foto_id', 'ahnentafel_id', 'dog_id', 'name', 'geburtsdatum', 'huefte_id', 'bemerkungen', 'verstorben', 'new_championtitel', 'new_jlpp_huu', 'new_huefte' ) as $key ) {
        if ( isset( $input[ $key ] ) && ! is_scalar( $input[ $key ] ) ) { wp_die( 'Ungültige Formulardaten.' ); }
    }
    foreach ( array( 'championtitel', 'jlpp_huu' ) as $key ) {
        if ( isset( $input[ $key ] ) && ( ! is_array( $input[ $key ] ) || count( array_filter( $input[ $key ], 'is_scalar' ) ) !== count( $input[ $key ] ) ) ) { wp_die( 'Ungültige Formulardaten.' ); }
    }
    if ( VKH_GENERAL_DB_VERSION !== get_option( 'vkh_general_db_version' ) ) {
        $GLOBALS['vkh_dog_form_error'] = 'Speichern ist erst nach erfolgreicher Datenbankaktualisierung möglich.';
        $GLOBALS['vkh_dog_form_input'] = $input;
        return;
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
add_action( 'admin_enqueue_scripts', 'vkh_general_enqueue_dog_media' );
function vkh_general_enqueue_dog_media() {
    if ( 'vkh-general-hunde' !== ( $_GET['page'] ?? '' ) ) { return; }
    wp_enqueue_media();
    $file = dirname( __DIR__ ) . '/admin/dog-media.js';
    wp_enqueue_script( 'vkh-dog-media', plugins_url( 'admin/dog-media.js', dirname( __DIR__ ) . '/index.php' ), array( 'media-views' ), filemtime( $file ), true );
}