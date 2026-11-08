<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Tokens are random, single-use and only their SHA-256 hashes are stored. */
function vkh_general_send_confirmation( $id, $cooldown = 3600 ) {
    global $wpdb;
    $table = str_replace( '`', '``', $wpdb->prefix ) . 'vkh_anmeldung';
    $previous = $wpdb->suppress_errors( true ); $locked = false;
    $lock = 'vkh_doi_' . md5( DB_NAME . ':' . $wpdb->prefix . ':' . (int) $id );
    try {
        $locked = '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) );
        if ( ! $locked ) { return new WP_Error( 'busy', 'Bitte später erneut versuchen.' ); }
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id=%d", $id ), ARRAY_A );
        if ( $wpdb->last_error ) { throw new RuntimeException(); }
        if ( ! $row ) { return new WP_Error( 'missing', 'Anmeldung nicht gefunden.' ); }
        if ( $row['bestaetigt_am'] ) { return true; }
        $last = $row['doi_mail_versuch_am'] ? strtotime( $row['doi_mail_versuch_am'] . ' UTC' ) : 0;
        if ( $last && time() - $last < max( 60, (int) $cooldown ) ) { return new WP_Error( 'rate', 'Bitte vor einem erneuten Versand etwas warten.' ); }
        $from = get_option( 'admin_email' );
        if ( ! is_email( $from ) || ! is_email( $row['email'] ) ) { throw new RuntimeException(); }
        $token = bin2hex( random_bytes( 32 ) );
        $hash = hash( 'sha256', $token );
        $now = gmdate( 'Y-m-d H:i:s' );
        $result = $wpdb->update( $wpdb->prefix . 'vkh_anmeldung', array(
            'doi_token_hash' => $hash, 'doi_gueltig_bis' => gmdate( 'Y-m-d H:i:s', time() + 48 * HOUR_IN_SECONDS ),
            'doi_mail_versuch_am' => $now, 'doi_mail_status' => 'ausstehend',
        ), array( 'id' => (int) $id ), array( '%s', '%s', '%s', '%s' ), array( '%d' ) );
        if ( false === $result ) { throw new RuntimeException(); }
        $url = add_query_arg( array( 'action' => 'vkh_confirm_signup', 'id' => (int) $id, 'token' => $token ), admin_url( 'admin-post.php' ) );
        $site = sanitize_text_field( html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' ) );
        // Fixed, plain-text message: never echo submitted names into mail headers.
        $subject = 'Bitte bestätige deine Anfrage - ' . $site;
        $message = "Hallo,\n\nbitte bestätige deine Anfrage nach weiteren Informationen zu unseren Hunden und Würfen. Öffne dazu diesen Link und bestätige dort die Anmeldung:\n\n" . $url
            . "\n\nDer Link ist 48 Stunden gültig und kann nur einmal verwendet werden.\n\nDeine Einwilligung:\n" . $row['einwilligung_text']
            . "\n\nFalls du diese Anfrage nicht gestellt hast, ignoriere diese E-Mail. Ohne Bestätigung wird die Anfrage nicht freigegeben.\n\n" . $site;
        $sent = @wp_mail( $row['email'], $subject, $message, array( 'Content-Type: text/plain; charset=UTF-8', 'From: ' . $from ) );
        $data = array( 'doi_mail_status' => $sent ? 'uebergeben' : 'fehler' );
        if ( $sent ) { $data['doi_mail_versendet_am'] = $now; }
        if ( false === $wpdb->update( $wpdb->prefix . 'vkh_anmeldung', $data, array( 'id' => (int) $id, 'doi_token_hash' => $hash ) ) ) { throw new RuntimeException(); }
        if ( ! $sent ) { throw new RuntimeException(); }
        delete_option( 'vkh_doi_mail_error' );
        return true;
    } catch ( Throwable $error ) {
        $wpdb->update( $wpdb->prefix . 'vkh_anmeldung', array( 'doi_mail_status' => 'fehler' ), array( 'id' => (int) $id ) );
        $reference = vkh_general_log_error( 'confirmation_mail_failed' );
        update_option( 'vkh_doi_mail_error', array( 'reference' => $reference, 'time' => time() ), false );
        // Do not log addresses, mail bodies, tokens or URLs.
        return new WP_Error( 'mail', 'Die Bestätigungsmail konnte nicht versendet werden. Fehlerreferenz: ' . $reference );
    } finally {
        if ( $locked ) { $wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
        $wpdb->suppress_errors( $previous );
    }
}

function vkh_general_confirmation_record( $id, $token ) {
    global $wpdb;
    if ( ! $id || ! is_string( $token ) || ! preg_match( '/\A[a-f0-9]{64}\z/', $token ) ) { return null; }
    $table = str_replace( '`', '``', $wpdb->prefix ) . 'vkh_anmeldung';
    $previous = $wpdb->suppress_errors( true );
    try {
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT id, einwilligung_text FROM `{$table}` WHERE id=%d AND doi_token_hash=%s AND doi_gueltig_bis>%s AND bestaetigt_am IS NULL", $id, hash( 'sha256', $token ), gmdate( 'Y-m-d H:i:s' ) ), ARRAY_A );
        if ( $wpdb->last_error ) { throw new RuntimeException(); }
        return $row;
    } catch ( Throwable $error ) {
        vkh_general_log_error( 'confirmation_read_failed' );
        return new WP_Error( 'database', 'Die Bestätigung ist vorübergehend nicht verfügbar.' );
    } finally { $wpdb->suppress_errors( $previous ); }
}

function vkh_general_confirm_signup( $id, $token ) {
    global $wpdb;
    if ( ! $id || ! is_string( $token ) || ! preg_match( '/\A[a-f0-9]{64}\z/', $token ) ) { return false; }
    $table = str_replace( '`', '``', $wpdb->prefix ) . 'vkh_anmeldung';
    $previous = $wpdb->suppress_errors( true );
    try {
        $result = $wpdb->query( $wpdb->prepare(
            "UPDATE `{$table}` SET bestaetigt_am=%s, doi_token_hash=NULL, doi_gueltig_bis=NULL, doi_mail_status='bestaetigt' WHERE id=%d AND doi_token_hash=%s AND doi_gueltig_bis>%s AND bestaetigt_am IS NULL",
            gmdate( 'Y-m-d H:i:s' ), $id, hash( 'sha256', $token ), gmdate( 'Y-m-d H:i:s' )
        ) );
        if ( false === $result ) { throw new RuntimeException(); }
        return 1 === $result;
    } catch ( Throwable $error ) {
        vkh_general_log_error( 'confirmation_save_failed' );
        return new WP_Error( 'database', 'Die Bestätigung konnte nicht gespeichert werden. Bitte später erneut versuchen.' );
    } finally { $wpdb->suppress_errors( $previous ); }
}

/** A GET only displays the confirmation page, so mail scanners cannot opt users in. */
function vkh_general_confirmation_page() {
    $input = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ? wp_unslash( $_POST ) : wp_unslash( $_GET );
    $id = isset( $input['id'] ) && is_scalar( $input['id'] ) ? absint( $input['id'] ) : 0;
    $token = isset( $input['token'] ) && is_string( $input['token'] ) ? $input['token'] : '';
    $record = vkh_general_confirmation_record( $id, $token );
    $success = false; $error = '';
    if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
        $nonce = isset( $input['_wpnonce'] ) && is_string( $input['_wpnonce'] ) ? $input['_wpnonce'] : '';
        if ( ! vkh_general_signup_origin_ok( $_SERVER['HTTP_ORIGIN'] ?? ( $_SERVER['HTTP_REFERER'] ?? '' ) ) || ! wp_verify_nonce( $nonce, 'vkh_doi_' . $id ) ) {
            $error = 'Die Anfrage konnte nicht geprüft werden. Bitte öffne den Link aus deiner E-Mail erneut.';
        } elseif ( $record && ! is_wp_error( $record ) ) {
            $result = vkh_general_confirm_signup( $id, $token );
            $success = true === $result;
            if ( is_wp_error( $result ) ) { $error = $result->get_error_message(); }
        }
    }
    if ( is_wp_error( $record ) ) { $error = $record->get_error_message(); }
    nocache_headers();
    header( 'Content-Type: text/html; charset=UTF-8' );
    header( 'Referrer-Policy: no-referrer' );
    header( 'X-Robots-Tag: noindex, nofollow' );
    header( "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'" );
    ?>
    <!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Anfrage bestätigen</title>
    <style>body{font:17px/1.6 system-ui,sans-serif;background:#f4f6f7;color:#253541;margin:0;padding:24px}main{max-width:620px;margin:5vh auto;background:#fff;padding:28px;border:1px solid #d7dfe3;border-radius:10px}button{font:inherit;background:#244f60;color:white;border:0;border-radius:5px;padding:12px 18px;cursor:pointer}a{color:#244f60}button:focus-visible,a:focus-visible{outline:3px solid #347b9a;outline-offset:3px}</style><main>
    <?php if ( $success ) : ?><h1>Vielen Dank!</h1><p>Deine E-Mail-Adresse und deine Anfrage sind jetzt bestätigt.</p>
    <?php elseif ( $error ) : ?><h1>Bestätigung nicht möglich</h1><p><?php echo esc_html( $error ); ?></p>
    <?php elseif ( ! $record ) : ?><h1>Link nicht mehr gültig</h1><p>Dieser Link ist ungültig, abgelaufen oder bereits verwendet. Du kannst auf unserer Website eine neue Bestätigungs-Mail anfordern, indem du dich erneut einträgst.</p>
    <?php else : ?><h1>Anfrage bestätigen</h1><p>Bitte bestätige deine Einwilligung:</p><p style="white-space:pre-wrap"><?php echo esc_html( $record['einwilligung_text'] ); ?></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="vkh_confirm_signup"><input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>"><?php wp_nonce_field( 'vkh_doi_' . $id, '_wpnonce', false ); ?><button type="submit">Anfrage verbindlich bestätigen</button></form>
    <?php endif; ?><p><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Zur Website</a></p></main></html>
    <?php
    exit;
}
add_action( 'admin_post_nopriv_vkh_confirm_signup', 'vkh_general_confirmation_page' );
add_action( 'admin_post_vkh_confirm_signup', 'vkh_general_confirmation_page' );

add_action( 'admin_post_vkh_resend_confirmation', 'vkh_general_resend_confirmation' );
function vkh_general_resend_confirmation() {
    if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { wp_die( 'Nur POST erlaubt.', '', array( 'response' => 405 ) ); }
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Keine Berechtigung.', '', array( 'response' => 403 ) ); }
    $id = isset( $_POST['signup_id'] ) && is_scalar( $_POST['signup_id'] ) ? absint( $_POST['signup_id'] ) : 0;
    check_admin_referer( 'vkh_resend_doi_' . $id );
    $result = vkh_general_send_confirmation( $id, 60 );
    $status = is_wp_error( $result ) ? ( 'rate' === $result->get_error_code() ? 'wait' : 'failed' ) : 'sent';
    wp_safe_redirect( add_query_arg( array( 'page' => 'vkh-general-interessenten', 'doi' => $status ), admin_url( 'admin.php' ) ) );
    exit;
}

add_action( 'admin_notices', static function() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $failure = get_option( 'vkh_doi_mail_error' );
    if ( $failure ) { echo '<div class="notice notice-error"><p>VKH: Der Versand von Bestätigungs-Mails ist fehlgeschlagen. Bitte den WordPress-/SMTP-Mailversand einrichten oder prüfen. Die Anmeldungen bleiben unbestätigt gespeichert.</p></div>'; }
    if ( 'vkh-general-interessenten' !== ( $_GET['page'] ?? '' ) ) { return; }
    $messages = array( 'sent' => 'Die Bestätigungsmail wurde an den Maildienst übergeben. Dies bestätigt noch nicht die Zustellung.', 'failed' => 'Die Bestätigungsmail konnte nicht versendet werden. Bitte die Mailkonfiguration prüfen.', 'wait' => 'Bitte mindestens eine Minute zwischen Versandversuchen warten.' );
    $status = isset( $_GET['doi'] ) && is_string( $_GET['doi'] ) ? $_GET['doi'] : '';
    if ( isset( $messages[ $status ] ) ) { echo '<div class="notice notice-info"><p>' . esc_html( $messages[ $status ] ) . '</p></div>'; }
} );