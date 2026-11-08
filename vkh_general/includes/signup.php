<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

const VKH_SIGNUP_CONSENT_VERSION = '1.1';
function vkh_general_signup_consents() {
    return array(
        'weitere_infos' => 'Ich möchte weitere Informationen zu Hunden und Würfen per E-Mail erhalten. Ich kann meine Einwilligung jederzeit widerrufen.',
    );
}

function vkh_general_signup_origin_ok( $url ) {
    $origin = wp_parse_url( $url ); $site = wp_parse_url( home_url( '/' ) );
    if ( ! is_array( $origin ) || ! is_array( $site ) || isset( $origin['user'] ) || isset( $origin['pass'] ) ) { return false; }
    $port = static fn( $parts ) => $parts['port'] ?? ( 'https' === ( $parts['scheme'] ?? '' ) ? 443 : 80 );
    return in_array( $origin['scheme'] ?? '', array( 'https', 'http' ), true )
        && strtolower( $origin['host'] ?? '' ) === strtolower( $site['host'] ?? '' )
        && ( $origin['scheme'] ?? '' ) === ( $site['scheme'] ?? '' ) && $port( $origin ) === $port( $site );
}

function vkh_general_signup_ip() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}

/** HMAC binds each short-lived form token to its issuing IP, without storing an IP. */
function vkh_general_signup_token( $ip, $issued = null ) {
    $message = ( $issued ?? time() ) . '.' . bin2hex( random_bytes( 16 ) );
    return $message . '.' . hash_hmac( 'sha256', $message . '|' . $ip, wp_salt( 'nonce' ) );
}
function vkh_general_signup_token_ok( $token, $ip ) {
    if ( ! preg_match( '/\A([0-9]{10})\.([a-f0-9]{32})\.([a-f0-9]{64})\z/', $token, $matches ) ) { return false; }
    $age = time() - (int) $matches[1];
    return $age >= 2 && $age <= 3600 && hash_equals( hash_hmac( 'sha256', $matches[1] . '.' . $matches[2] . '|' . $ip, wp_salt( 'nonce' ) ), $matches[3] );
}

function vkh_general_signup_transient_key( $type, $identity = '' ) {
    global $wpdb;
    return 'vkh_signup_' . hash_hmac( 'sha256', $wpdb->prefix . '|' . $type . '|' . ( 'token' === $type ? '' : gmdate( 'Y-m-d-H' ) ) . '|' . $identity, wp_salt( 'nonce' ) );
}

/** Counter updates are serialized by the signup advisory lock. Keys expire in one hour. */
function vkh_general_signup_rate( $ip, $email ) {
    $keys = array( vkh_general_signup_transient_key( 'ip', $ip ) => 5, vkh_general_signup_transient_key( 'global' ) => 100 );
    foreach ( $keys as $key => $limit ) { if ( (int) get_transient( $key ) >= $limit ) { return false; } }
    foreach ( $keys as $key => $limit ) { if ( ! set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS ) ) { throw new RuntimeException( 'rate_storage' ); } }
    $email_key = vkh_general_signup_transient_key( 'email', strtolower( $email ) );
    if ( get_transient( $email_key ) ) { return 'duplicate'; }
    return true;
}

/** Public route is insert-only: no public caller can edit a prospect or set confirmation. */
function vkh_general_collect_signup( $post, $context ) {
    global $wpdb;
    if ( 'POST' !== ( $context['method'] ?? '' ) || ! vkh_general_signup_origin_ok( $context['origin'] ?? '' ) ) {
        return new WP_Error( 'invalid', 'Bitte das Formular direkt auf unserer Website absenden.' );
    }
    $ip = $context['ip'] ?? '';
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) || strlen( wp_json_encode( $post ) ) > 8192 || (int) ( $context['length'] ?? 0 ) > 8192 ) {
        return new WP_Error( 'invalid', 'Ungültige Anfrage.' );
    }
    $allowed = array( 'action', '_wpnonce', '_wp_http_referer', 'vkh_token', 'vkh_return', 'website', 'vorname', 'nachname', 'telefon', 'email', 'hundeerfahrung', 'newsletter', 'weitere_infos' );
    foreach ( $post as $key => $value ) {
        if ( ! in_array( $key, $allowed, true ) || ! is_string( $value ) || strlen( $value ) > 2000 ) { return new WP_Error( 'invalid', 'Ungültige Formulardaten.' ); }
    }
    if ( ! wp_verify_nonce( $post['_wpnonce'] ?? '', 'vkh_public_signup' ) || ! vkh_general_signup_token_ok( $post['vkh_token'] ?? '', $ip ) ) {
        return new WP_Error( 'expired', 'Das Formular ist abgelaufen oder wurde zu schnell abgesendet. Bitte neu laden und erneut versuchen.' );
    }
    if ( ! empty( $post['website'] ) ) { return true; }
    if ( ! empty( $post['newsletter'] ) ) { return new WP_Error( 'invalid', 'Bitte das aktuelle Formular für weitere Informationen verwenden.' ); }
    $consents = vkh_general_signup_consents(); $selected = array();
    foreach ( $consents as $key => $text ) {
        $value = $post[ $key ] ?? '0';
        if ( ! in_array( $value, array( '', '0', '1' ), true ) ) { return new WP_Error( 'invalid', 'Ungültige Einwilligung.' ); }
        if ( '1' === $value ) { $selected[ $key ] = $text; }
    }
    if ( ! $selected ) { return new WP_Error( 'consent', 'Bitte die Einwilligung für weitere Informationen bestätigen.' ); }
    $validated = vkh_general_validate_prospect( array(
        'vorname' => $post['vorname'] ?? '', 'nachname' => $post['nachname'] ?? '', 'email' => $post['email'] ?? '',
        'telefon' => $post['telefon'] ?? '', 'hundeerfahrung' => $post['hundeerfahrung'] ?? '0',
    ) );
    if ( is_wp_error( $validated ) ) { return new WP_Error( 'invalid', 'Bitte Namen, E-Mail-Adresse und gegebenenfalls Telefonnummer prüfen.' ); }
    if ( VKH_GENERAL_DB_VERSION !== get_option( 'vkh_general_db_version' ) ) { return new WP_Error( 'unavailable', 'Die Anmeldung ist vorübergehend nicht verfügbar.' ); }
    $previous = $wpdb->suppress_errors( true ); $locked = false; $transaction = false;
    $lock = 'vkh_signup_' . md5( DB_NAME . ':' . $wpdb->prefix );
    $p = str_replace( '`', '``', $wpdb->prefix ) . 'vkh_';
    try {
        $locked = '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) );
        if ( $wpdb->last_error ) { throw new RuntimeException(); }
        if ( ! $locked ) { return new WP_Error( 'rate', 'Bitte versuche es später erneut.' ); }
        $email = $validated['data']['email'];
        $rate = vkh_general_signup_rate( $ip, $email );
        if ( false === $rate ) { return new WP_Error( 'rate', 'Zu viele Versuche. Bitte versuche es später erneut.' ); }
        // Identical response for existing and new email addresses prevents enumeration.
        if ( 'duplicate' === $rate ) { return true; }
        $used_key = vkh_general_signup_transient_key( 'token', $post['vkh_token'] );
        if ( get_transient( $used_key ) ) { return new WP_Error( 'expired', 'Bitte das Formular neu laden.' ); }
        $email_hash = hash( 'sha256', strtolower( $email ) );
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$p}anmeldung` WHERE email_hash=%s", $email_hash ) );
        if ( $wpdb->last_error ) { throw new RuntimeException(); }
        if ( $existing ) {
            $wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); $locked = false;
            vkh_general_send_confirmation( (int) $existing );
            return true;
        }
        $person = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$p}interessent` WHERE email=%s ORDER BY id LIMIT 1", $email ) );
        if ( $wpdb->last_error ) { throw new RuntimeException(); }
        if ( $person ) {
            $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$p}anmeldung` WHERE interessent_id=%d", $person ) );
            if ( $wpdb->last_error ) { throw new RuntimeException(); }
            if ( $existing ) {
            $wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); $locked = false;
            vkh_general_send_confirmation( (int) $existing );
            return true;
        }
        }
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) { throw new RuntimeException(); }
        $transaction = true;
        if ( ! $person ) {
            $data = $validated['data']; $data['angelegt_am'] = current_time( 'mysql', true );
            if ( false === $wpdb->insert( $wpdb->prefix . 'vkh_interessent', $data, array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s' ) ) ) { throw new RuntimeException(); }
            $person = (int) $wpdb->insert_id;
        }
        $row = array(
            'interessent_id' => $person, 'email' => $email, 'email_hash' => $email_hash,
            'newsletter' => isset( $selected['newsletter'] ) ? 1 : 0, 'weitere_infos' => isset( $selected['weitere_infos'] ) ? 1 : 0,
            'eingegangen_am' => current_time( 'mysql', true ), 'einwilligung_version' => VKH_SIGNUP_CONSENT_VERSION,
            'einwilligung_text' => implode( "\n", $selected ), 'bestaetigt_am' => null,
        );
        if ( false === $wpdb->insert( $wpdb->prefix . 'vkh_anmeldung', $row, array( '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' ) ) ) { throw new RuntimeException(); }
        $signup_id = (int) $wpdb->insert_id;
        if ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException(); }
        $transaction = false;
        set_transient( vkh_general_signup_transient_key( 'email', strtolower( $email ) ), 1, HOUR_IN_SECONDS );
        set_transient( $used_key, 1, HOUR_IN_SECONDS );
        $wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); $locked = false;
        vkh_general_send_confirmation( $signup_id );
        return true;
    } catch ( Throwable $error ) {
        if ( $transaction ) { $wpdb->query( 'ROLLBACK' ); }
        vkh_general_log_error( 'public_signup_failed' );
        return new WP_Error( 'unavailable', 'Die Anmeldung konnte nicht gespeichert werden. Bitte später erneut versuchen.' );
    } finally {
        if ( $locked ) { $wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
        $wpdb->suppress_errors( $previous );
    }
}

function vkh_general_public_signup_handler() {
    $result = vkh_general_collect_signup( wp_unslash( $_POST ), array(
        'method' => $_SERVER['REQUEST_METHOD'] ?? '', 'ip' => vkh_general_signup_ip(),
        'origin' => $_SERVER['HTTP_ORIGIN'] ?? ( $_SERVER['HTTP_REFERER'] ?? '' ), 'length' => $_SERVER['CONTENT_LENGTH'] ?? 0,
    ) );
    $status = is_wp_error( $result ) ? $result->get_error_code() : 'success';
    if ( ! in_array( $status, array( 'success', 'invalid', 'expired', 'consent', 'rate', 'unavailable' ), true ) ) { $status = 'invalid'; }
    $return = isset( $_POST['vkh_return'] ) && is_string( $_POST['vkh_return'] ) ? esc_url_raw( wp_unslash( $_POST['vkh_return'] ) ) : home_url( '/' );
    if ( ! vkh_general_signup_origin_ok( $return ) ) { $return = home_url( '/' ); }
    $return = remove_query_arg( array( 'vkh_signup', 'vkh_token', '_wpnonce' ), $return );
    nocache_headers();
    wp_safe_redirect( add_query_arg( 'vkh_signup', $status, $return ) . '#vkh-anmeldung', 303 );
    exit;
}
add_action( 'admin_post_nopriv_vkh_public_signup', 'vkh_general_public_signup_handler' );
add_action( 'admin_post_vkh_public_signup', 'vkh_general_public_signup_handler' );

function vkh_general_public_signup_token() {
    nocache_headers();
    wp_send_json_success( array( 'token' => vkh_general_signup_token( vkh_general_signup_ip() ), 'nonce' => wp_create_nonce( 'vkh_public_signup' ) ) );
}
add_action( 'wp_ajax_nopriv_vkh_signup_token', 'vkh_general_public_signup_token' );
add_action( 'wp_ajax_vkh_signup_token', 'vkh_general_public_signup_token' );

require_once __DIR__ . '/signup-form.php';
add_shortcode( 'vkh_interessenten', 'vkh_general_signup_form' );
add_action( 'widgets_init', static function() {
    require_once __DIR__ . '/signup-widget.php';
    register_widget( 'VKH_General_Signup_Widget' );
} );
function vkh_general_signup_summary( $row ) {
    if ( empty( $row['anmeldung_am'] ) ) { return 'Keine Online-Anmeldung'; }
    $types = array();
    if ( ! empty( $row['anmeldung_newsletter'] ) ) { $types[] = 'Newsletter'; }
    if ( ! empty( $row['anmeldung_infos'] ) ) { $types[] = 'Weitere Infos'; }
    return implode( ' / ', $types ) . ( empty( $row['anmeldung_bestaetigt'] ) ? ' (unbestätigt)' : ' (bestätigt)' );
}