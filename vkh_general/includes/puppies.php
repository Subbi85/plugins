<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function vkh_general_puppy_statuses() {
    return array( 'nein' => 'Nein', 'vorgemerkt' => 'Vorgemerkt', 'verkauft' => 'Verkauft' );
}
function vkh_general_puppy_sexes() {
    return array( 'ruede' => 'Rüde', 'huendin' => 'Hündin' );
}

function vkh_general_validate_litter( $input ) {
    if ( ! is_array( $input ) || ! is_string( $input['bezeichnung'] ?? null ) || ! is_string( $input['wurfdatum'] ?? '' ) ) {
        return new WP_Error( 'input', 'Ungültige Formulardaten.' );
    }
    $id = $input['id'] ?? '0';
    if ( ! is_scalar( $id ) || ! ctype_digit( (string) $id ) || strlen( (string) $id ) > 18 ) { return new WP_Error( 'id', 'Ungültiger Wurf.' ); }
    $name = sanitize_text_field( $input['bezeichnung'] );
    if ( '' === $name || mb_strlen( $name ) > 30 ) { return new WP_Error( 'name', 'Bitte eine Wurfbezeichnung mit maximal 30 Zeichen eingeben.' ); }
    $date = $input['wurfdatum'] ?? '';
    if ( '' !== $date ) {
        $parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
        if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $date ) { return new WP_Error( 'date', 'Bitte ein gültiges Wurfdatum eingeben.' ); }
    }
    $rows = $input['welpen'] ?? null;
    if ( ! is_array( $rows ) || count( $rows ) < 1 || count( $rows ) > 20 ) { return new WP_Error( 'count', 'Bitte zwischen einem und 20 Welpen eintragen.' ); }
    $puppies = array();
    foreach ( $rows as $row ) {
        if ( ! is_array( $row ) ) { return new WP_Error( 'input', 'Ungültige Welpendaten.' ); }
        foreach ( array( 'name', 'geschlecht', 'verkaufsstatus' ) as $field ) {
            if ( ! isset( $row[ $field ] ) || ! is_string( $row[ $field ] ) ) { return new WP_Error( 'input', 'Bitte für jeden Welpen Name, Geschlecht und Status angeben.' ); }
        }
        $puppy_name = sanitize_text_field( $row['name'] );
        if ( '' === $puppy_name || mb_strlen( $puppy_name ) > 100 ) { return new WP_Error( 'puppy_name', 'Bitte für jeden Welpen einen Namen mit maximal 100 Zeichen eingeben.' ); }
        if ( ! isset( vkh_general_puppy_sexes()[ $row['geschlecht'] ] ) || ! isset( vkh_general_puppy_statuses()[ $row['verkaufsstatus'] ] ) ) {
            return new WP_Error( 'choice', 'Bitte ein gültiges Geschlecht und einen gültigen Verkaufsstatus wählen.' );
        }
        $puppy_id = $row['id'] ?? '0';
        if ( ! is_scalar( $puppy_id ) || ! ctype_digit( (string) $puppy_id ) || strlen( (string) $puppy_id ) > 18 ) { return new WP_Error( 'id', 'Ungültiger Welpe.' ); }
        $puppies[] = array( 'id' => (int) $puppy_id, 'name' => $puppy_name, 'geschlecht' => $row['geschlecht'], 'verkaufsstatus' => $row['verkaufsstatus'] );
    }
    return array( 'id' => (int) $id, 'bezeichnung' => $name, 'wurfdatum' => $date ?: null, 'welpen' => $puppies );
}

/** All-or-nothing: a litter is never stored without its submitted puppies. */
function vkh_general_save_litter( $input, $example = false ) {
    global $wpdb;
    $data = vkh_general_validate_litter( $input );
    if ( is_wp_error( $data ) ) { return $data; }
    $id = $data['id'];
    $p = str_replace( '`', '``', $wpdb->prefix ) . 'vkh_';
    $previous = $wpdb->suppress_errors( true ); $transaction = false;
    try {
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$p}wurf` WHERE bezeichnung=%s AND id<>%d", $data['bezeichnung'], $id ) );
        if ( $wpdb->last_error ) { throw new RuntimeException(); }
        if ( $exists ) { return new WP_Error( 'duplicate', 'Diese Wurfbezeichnung ist bereits vergeben.' ); }
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) { throw new RuntimeException(); }
        $transaction = true;
        $owned = array();
        if ( $id ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$p}wurf` WHERE id=%d FOR UPDATE", $id ) );
            if ( $wpdb->last_error ) { throw new RuntimeException(); }
            if ( ! $exists ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'missing', 'Dieser Wurf wurde nicht gefunden.' ); }
            $owned = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$p}welpe` WHERE wurf_id=%d FOR UPDATE", $id ) ) );
            if ( $wpdb->last_error ) { throw new RuntimeException(); }
        }
        $posted = array();
        foreach ( $data['welpen'] as $puppy ) {
            if ( ! $puppy['id'] ) { continue; }
            if ( ! in_array( $puppy['id'], $owned, true ) || in_array( $puppy['id'], $posted, true ) ) {
                $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'ownership', 'Eine Welpenzuordnung ist ungültig. Bitte den Wurf erneut öffnen.' );
            }
            $posted[] = $puppy['id'];
        }
        // Never delete omitted records; reject stale or manipulated forms instead.
        if ( array_diff( $owned, $posted ) ) {
            $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'stale', 'Vorhandene Welpen fehlen im Formular. Bitte den Wurf erneut öffnen.' );
        }
        if ( $id ) {
            $result = $wpdb->update( $wpdb->prefix . 'vkh_wurf', array( 'bezeichnung' => $data['bezeichnung'], 'wurfdatum' => $data['wurfdatum'] ), array( 'id' => $id ), array( '%s', '%s' ), array( '%d' ) );
        } else {
            $result = $wpdb->insert( $wpdb->prefix . 'vkh_wurf', array( 'bezeichnung' => $data['bezeichnung'], 'wurfdatum' => $data['wurfdatum'], 'angelegt_am' => current_time( 'mysql', true ), 'beispiel' => $example ? 1 : 0 ), array( '%s', '%s', '%s', '%d' ) );
            $id = (int) $wpdb->insert_id;
        }
        if ( false === $result ) { throw new RuntimeException(); }
        foreach ( $data['welpen'] as $puppy ) {
            $puppy_id = $puppy['id']; unset( $puppy['id'] );
            if ( $puppy_id ) {
                $result = $wpdb->update( $wpdb->prefix . 'vkh_welpe', $puppy, array( 'id' => $puppy_id, 'wurf_id' => $id ), array( '%s', '%s', '%s' ), array( '%d', '%d' ) );
            } else {
                $puppy['wurf_id'] = $id;
                $result = $wpdb->insert( $wpdb->prefix . 'vkh_welpe', $puppy, array( '%s', '%s', '%s', '%d' ) );
            }
            if ( false === $result ) { throw new RuntimeException(); }
        }
        if ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException(); }
        return $id;
    } catch ( Throwable $error ) {
        if ( $transaction ) { $wpdb->query( 'ROLLBACK' ); }
        $reference = vkh_general_log_error( 'litter_save_failed' );
        return new WP_Error( 'database', 'Wurf und Welpen konnten nicht gespeichert werden. Fehlerreferenz: ' . $reference );
    } finally { $wpdb->suppress_errors( $previous ); }
}
function vkh_general_list_litters() {
    global $wpdb;
    $p = str_replace( '`', '``', $wpdb->prefix ) . 'vkh_';
    $previous = $wpdb->suppress_errors( true );
    try {
        $litters = $wpdb->get_results( "SELECT * FROM `{$p}wurf` ORDER BY bezeichnung, id", ARRAY_A );
        if ( $wpdb->last_error ) { throw new RuntimeException(); }
        $puppies = $wpdb->get_results( "SELECT * FROM `{$p}welpe` ORDER BY name, id", ARRAY_A );
        if ( $wpdb->last_error ) { throw new RuntimeException(); }
        $groups = array();
        foreach ( $puppies as $puppy ) { $groups[ $puppy['wurf_id'] ][] = $puppy; }
        foreach ( $litters as &$litter ) { $litter['welpen'] = $groups[ $litter['id'] ] ?? array(); }
        unset( $litter );
        return $litters;
    } catch ( Throwable $error ) {
        $reference = vkh_general_log_error( 'litter_list_failed' );
        return new WP_Error( 'database', 'Die Würfe konnten nicht geladen werden. Fehlerreferenz: ' . $reference );
    } finally { $wpdb->suppress_errors( $previous ); }
}

function vkh_general_update_puppy_status( $id, $status ) {
    global $wpdb;
    if ( ! $id || ! is_string( $status ) || ! isset( vkh_general_puppy_statuses()[ $status ] ) ) { return new WP_Error( 'input', 'Ungültiger Verkaufsstatus.' ); }
    $p = str_replace( '`', '``', $wpdb->prefix ) . 'vkh_';
    $previous = $wpdb->suppress_errors( true );
    try {
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT id, wurf_id FROM `{$p}welpe` WHERE id=%d", $id ), ARRAY_A );
        if ( $wpdb->last_error ) { throw new RuntimeException(); }
        if ( ! $row ) { return new WP_Error( 'missing', 'Dieser Welpe wurde nicht gefunden.' ); }
        if ( false === $wpdb->update( $wpdb->prefix . 'vkh_welpe', array( 'verkaufsstatus' => $status ), array( 'id' => $id ), array( '%s' ), array( '%d' ) ) ) { throw new RuntimeException(); }
        return (int) $row['wurf_id'];
    } catch ( Throwable $error ) {
        $reference = vkh_general_log_error( 'puppy_status_failed' );
        return new WP_Error( 'database', 'Der Status konnte nicht gespeichert werden. Fehlerreferenz: ' . $reference );
    } finally { $wpdb->suppress_errors( $previous ); }
}

add_action( 'admin_init', 'vkh_general_handle_litter_form' );
function vkh_general_handle_litter_form() {
    if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || 'vkh-general-welpen' !== ( $_GET['page'] ?? '' ) || ! isset( $_POST['vkh_save_litter'] ) ) { return; }
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Keine Berechtigung.', '', array( 'response' => 403 ) ); }
    check_admin_referer( 'vkh_save_litter' );
    $input = wp_unslash( $_POST );
    $result = VKH_GENERAL_DB_VERSION === get_option( 'vkh_general_db_version' )
        ? vkh_general_save_litter( $input ) : new WP_Error( 'schema', 'Bitte zuerst die Datenbankaktualisierung abschließen.' );
    if ( is_wp_error( $result ) ) {
        $GLOBALS['vkh_litter_error'] = $result->get_error_message();
        $GLOBALS['vkh_litter_input'] = $input;
        return;
    }
    wp_safe_redirect( add_query_arg( array( 'page' => 'vkh-general-welpen', 'saved' => 1, 'open' => $result ), admin_url( 'admin.php' ) ) );
    exit;
}

/** Both HTTP entry points independently enforce capability and a per-puppy nonce. */
function vkh_general_puppy_status_request() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Keine Berechtigung.', '', array( 'response' => 403 ) ); }
    if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { wp_die( 'Nur POST erlaubt.', '', array( 'response' => 405 ) ); }
    $id = isset( $_POST['puppy_id'] ) && is_scalar( $_POST['puppy_id'] ) ? absint( $_POST['puppy_id'] ) : 0;
    check_admin_referer( 'vkh_puppy_status_' . $id );
    $status = isset( $_POST['verkaufsstatus'] ) && is_string( $_POST['verkaufsstatus'] ) ? wp_unslash( $_POST['verkaufsstatus'] ) : '';
    if ( VKH_GENERAL_DB_VERSION !== get_option( 'vkh_general_db_version' ) ) { return new WP_Error( 'schema', 'Bitte zuerst die Datenbankaktualisierung abschließen.' ); }
    return vkh_general_update_puppy_status( $id, $status );
}
add_action( 'wp_ajax_vkh_puppy_status', static function() {
    $result = vkh_general_puppy_status_request();
    if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 ); }
    wp_send_json_success();
} );
add_action( 'admin_post_vkh_puppy_status', static function() {
    $result = vkh_general_puppy_status_request();
    $args = array( 'page' => 'vkh-general-welpen', 'status' => is_wp_error( $result ) ? 'failed' : 'saved' );
    if ( ! is_wp_error( $result ) ) { $args['open'] = $result; }
    wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
    exit;
} );

add_action( 'admin_enqueue_scripts', static function() {
    if ( 'vkh-general-welpen' !== ( $_GET['page'] ?? '' ) ) { return; }
    $root = dirname( __DIR__ );
    wp_enqueue_style( 'vkh-puppies', plugins_url( 'admin/puppies.css', $root . '/index.php' ), array(), filemtime( $root . '/admin/puppies.css' ) );
    wp_enqueue_script( 'vkh-puppies', plugins_url( 'admin/puppies.js', $root . '/index.php' ), array(), filemtime( $root . '/admin/puppies.js' ), true );
} );