<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Data access remains reusable for a later authenticated API. */
function vkh_general_get_prospect( $id ) {
    global $wpdb;
    $table = str_replace( '`', '``', $wpdb->prefix ) . 'vkh_interessent';
    $previous = $wpdb->suppress_errors( true );
    try {
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT i.*, a.id AS anmeldung_id, a.doi_mail_status AS anmeldung_mail_status, a.newsletter AS anmeldung_newsletter, a.weitere_infos AS anmeldung_infos, a.email AS anmeldung_email, a.eingegangen_am AS anmeldung_am, a.einwilligung_text AS anmeldung_text, a.bestaetigt_am AS anmeldung_bestaetigt FROM `{$table}` i LEFT JOIN `{$wpdb->prefix}vkh_anmeldung` a ON a.interessent_id=i.id WHERE i.id = %d", $id ), ARRAY_A );
        if ( $wpdb->last_error ) { throw new RuntimeException(); }
        return $row;
    } catch ( Throwable $error ) {
        $reference = vkh_general_log_error( 'prospect_read_failed' );
        return new WP_Error( 'database', 'Der Interessent konnte nicht geladen werden. Fehlerreferenz: ' . $reference );
    } finally { $wpdb->suppress_errors( $previous ); }
}

function vkh_general_list_prospects( $page = 1 ) {
    global $wpdb;
    $p = str_replace( '`', '``', $wpdb->prefix ) . 'vkh_';
    $previous = $wpdb->suppress_errors( true );
    try {
        $total = $wpdb->get_var( "SELECT COUNT(*) FROM `{$p}interessent`" );
        if ( $wpdb->last_error ) { throw new RuntimeException(); }
        $pages = max( 1, (int) ceil( (int) $total / 25 ) );
        $page = min( $pages, max( 1, absint( $page ) ) );
        $items = $wpdb->get_results( $wpdb->prepare(
            "SELECT i.*, h.name AS hund_name, w.name AS welpe_name, r.bezeichnung AS wurf_bezeichnung, a.id AS anmeldung_id, a.doi_mail_status AS anmeldung_mail_status, a.newsletter AS anmeldung_newsletter, a.weitere_infos AS anmeldung_infos, a.email AS anmeldung_email, a.eingegangen_am AS anmeldung_am, a.einwilligung_text AS anmeldung_text, a.bestaetigt_am AS anmeldung_bestaetigt FROM `{$p}interessent` i LEFT JOIN `{$p}hund` h ON h.id = i.hund_id LEFT JOIN `{$p}welpe` w ON w.id=i.welpe_id LEFT JOIN `{$p}wurf` r ON r.id=w.wurf_id LEFT JOIN `{$p}anmeldung` a ON a.interessent_id=i.id ORDER BY i.nachname, i.vorname, i.id LIMIT %d OFFSET %d", 25, ( $page - 1 ) * 25
        ), ARRAY_A );
        if ( $wpdb->last_error ) { throw new RuntimeException(); }
        $dogs = $wpdb->get_results( $wpdb->prepare( "SELECT id, name FROM `{$p}hund` WHERE name=%s ORDER BY id LIMIT 1", 'Safron' ), ARRAY_A );
        if ( $wpdb->last_error ) { throw new RuntimeException(); }
        $puppies = $wpdb->get_results( "SELECT w.id, w.name, r.bezeichnung AS wurf FROM `{$p}welpe` w INNER JOIN `{$p}wurf` r ON r.id=w.wurf_id ORDER BY r.bezeichnung, w.name, w.id", ARRAY_A );
        if ( $wpdb->last_error ) { throw new RuntimeException(); }
        return array( 'items' => $items, 'dogs' => $dogs, 'puppies' => $puppies, 'total' => (int) $total, 'page' => $page, 'pages' => $pages );
    } catch ( Throwable $error ) {
        $reference = vkh_general_log_error( 'prospect_list_failed' );
        return new WP_Error( 'database', 'Die Interessenten konnten nicht geladen werden. Fehlerreferenz: ' . $reference );
    } finally { $wpdb->suppress_errors( $previous ); }
}

/** Save one person and preserve the record if a database operation fails. */
function vkh_general_save_prospect( $input ) {
    global $wpdb;
    $validated = vkh_general_validate_prospect( $input );
    if ( is_wp_error( $validated ) ) { return $validated; }
    $id = $validated['id'];
    $data = $validated['data'];
    $dog_id = $data['hund_id'];
    $puppy_id = $data['welpe_id'];
    $p = str_replace( '`', '``', $wpdb->prefix ) . 'vkh_';
    $previous = $wpdb->suppress_errors( true );
    $transaction = false;
    try {
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) { throw new RuntimeException(); }
        $transaction = true;
        if ( $dog_id ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$p}hund` WHERE name=%s ORDER BY id LIMIT 1", 'Safron' ) );
            if ( $wpdb->last_error ) { throw new RuntimeException(); }
            if ( ! $exists || (int) $exists !== $dog_id ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'dog', 'Als Zuchtrüde kann ausschließlich Safron zugeordnet werden.' ); }
        }
        if ( $puppy_id ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT w.id FROM `{$p}welpe` w INNER JOIN `{$p}wurf` r ON r.id=w.wurf_id WHERE w.id=%d", $puppy_id ) );
            if ( $wpdb->last_error ) { throw new RuntimeException(); }
            if ( ! $exists ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'puppy', 'Der ausgewählte Welpe gehört zu keinem vorhandenen Wurf.' ); }
        }
        if ( $id ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$p}interessent` WHERE id = %d FOR UPDATE", $id ) );
            if ( $wpdb->last_error ) { throw new RuntimeException(); }
            if ( ! $exists ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'missing', 'Dieser Interessent wurde nicht gefunden.' ); }
            $result = $wpdb->update( $wpdb->prefix . 'vkh_interessent', $data, array( 'id' => $id ), array( '%s', '%s', '%s', '%s', '%d', '%d', '%d' ), array( '%d' ) );
        } else {
            // Ignore any client-supplied date; set once in UTC on creation only.
            $data['angelegt_am'] = current_time( 'mysql', true );
            $result = $wpdb->insert( $wpdb->prefix . 'vkh_interessent', $data, array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s' ) );
            $id = (int) $wpdb->insert_id;
        }
        if ( false === $result || false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException(); }
        return $id;
    } catch ( Throwable $error ) {
        $reference = vkh_general_log_error( 'prospect_save_failed' );
        if ( $transaction ) { $wpdb->query( 'ROLLBACK' ); }
        return new WP_Error( 'database', 'Der Interessent konnte nicht gespeichert werden. Fehlerreferenz: ' . $reference );
    } finally { $wpdb->suppress_errors( $previous ); }
}

add_action( 'admin_init', 'vkh_general_handle_prospect_form' );
function vkh_general_handle_prospect_form() {
    if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || 'vkh-general-interessenten' !== ( $_GET['page'] ?? '' ) || ! isset( $_POST['vkh_save_prospect'] ) ) { return; }
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Keine Berechtigung.', '', array( 'response' => 403 ) ); }
    check_admin_referer( 'vkh_save_prospect' );
    $input = wp_unslash( $_POST );
    $result = VKH_GENERAL_DB_VERSION === get_option( 'vkh_general_db_version' )
        ? vkh_general_save_prospect( $input )
        : new WP_Error( 'schema', 'Speichern ist erst nach erfolgreicher Datenbankaktualisierung möglich.' );
    if ( is_wp_error( $result ) ) {
        // Preserve only known scalar fields. Never reflect arrays or arbitrary POST keys.
        $fields = array( 'id', 'vorname', 'nachname', 'telefon', 'email', 'hundeerfahrung', 'hund_id', 'welpe_id', 'zuordnung' );
        $GLOBALS['vkh_prospect_form_input'] = array();
        foreach ( $fields as $key ) {
            $GLOBALS['vkh_prospect_form_input'][ $key ] = isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? $input[ $key ] : '';
        }
        $GLOBALS['vkh_prospect_form_error'] = $result->get_error_message();
        return;
    }
    wp_safe_redirect( add_query_arg( array( 'page' => 'vkh-general-interessenten', 'saved' => 1 ), admin_url( 'admin.php' ) ) );
    exit;
}
/** Stored UTC, displayed in the timezone configured in WordPress. */
function vkh_general_format_prospect_created( $value ) {
    if ( ! $value ) { return 'Nicht erfasst'; }
    $date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new DateTimeZone( 'UTC' ) );
    if ( ! $date || $date->format( 'Y-m-d H:i:s' ) !== $value ) { return 'Nicht erfasst'; }
    return wp_date( 'd.m.Y H:i', $date->getTimestamp() );
}
/** Shared validation for backend and public registration, without database writes. */
function vkh_general_validate_prospect( $input ) {
    $keys = array( 'id', 'vorname', 'nachname', 'telefon', 'email', 'hundeerfahrung', 'hund_id', 'welpe_id', 'zuordnung' );
    foreach ( $keys as $key ) {
        if ( isset( $input[ $key ] ) && ! is_scalar( $input[ $key ] ) ) { return new WP_Error( 'input', 'Ungültige Formulardaten.' ); }
    }
    foreach ( array( 'id', 'hund_id', 'welpe_id' ) as $key ) {
        if ( ! empty( $input[ $key ] ) && ( ! ctype_digit( (string) $input[ $key ] ) || strlen( (string) $input[ $key ] ) > 18 ) ) {
            return new WP_Error( 'input', 'Ungültige Zuordnung.' );
        }
    }
    $id = absint( $input['id'] ?? 0 );
    $dog_id = absint( $input['hund_id'] ?? 0 ) ?: null;
    $puppy_id = absint( $input['welpe_id'] ?? 0 ) ?: null;
    if ( isset( $input['zuordnung'] ) ) {
        if ( $dog_id || $puppy_id ) { return new WP_Error( 'assignment', 'Bitte genau eine Zuordnung über die Auswahl treffen.' ); }
        if ( '' !== $input['zuordnung'] ) {
            if ( ! preg_match( '/\A(hund|welpe):([1-9][0-9]{0,17})\z/', (string) $input['zuordnung'], $match ) ) { return new WP_Error( 'assignment', 'Ungültige Hundezuordnung.' ); }
            if ( 'hund' === $match[1] ) { $dog_id = (int) $match[2]; } else { $puppy_id = (int) $match[2]; }
        }
    }
    if ( $dog_id && $puppy_id ) { return new WP_Error( 'assignment', 'Bitte nur einen Hund zuordnen.' ); }
    $data = array();
    foreach ( array( 'vorname', 'nachname' ) as $key ) {
        $data[ $key ] = sanitize_text_field( $input[ $key ] ?? '' );
        if ( '' === $data[ $key ] || mb_strlen( $data[ $key ] ) > 100 ) {
            return new WP_Error( 'name', 'Bitte Vorname und Nachname mit jeweils maximal 100 Zeichen eingeben.' );
        }
    }
    $data['telefon'] = sanitize_text_field( $input['telefon'] ?? '' );
    if ( '' !== $data['telefon'] && ! preg_match( '/\A[0-9+(). \/-]{3,50}\z/', $data['telefon'] ) ) {
        return new WP_Error( 'phone', 'Bitte eine gültige Telefonnummer mit maximal 50 Zeichen eingeben.' );
    }
    $data['email'] = trim( (string) ( $input['email'] ?? '' ) );
    if ( mb_strlen( $data['email'] ) > 254 || ! is_email( $data['email'] ) ) {
        return new WP_Error( 'email', 'Bitte eine gültige E-Mail-Adresse eingeben.' );
    }
    $experience = (string) ( $input['hundeerfahrung'] ?? '0' );
    if ( ! in_array( $experience, array( '', '0', '1' ), true ) ) { return new WP_Error( 'experience', 'Ungültige Angabe zur Hundeerfahrung.' ); }
    $data['hundeerfahrung'] = '1' === $experience ? 1 : 0;
    $data['hund_id'] = $dog_id;
    $data['welpe_id'] = $puppy_id;
    return array( 'id' => $id, 'data' => $data );
}
