<?php
if ( PHP_SAPI !== 'cli' || ! defined( 'VKH_INTEGRATION_CASES' ) ) { exit; }
require_once $root . '/includes/signup.php';
$signup_keys = array();
$signup_user = get_current_user_id();
$signup_mail_count = 0; $signup_mails = array();
$original_doi_error = get_option( 'vkh_doi_mail_error' );
$signup_mail_filter = static function( $return, $atts ) use ( &$signup_mail_count, &$signup_mails ) { ++$signup_mail_count; $signup_mails[] = $atts; return true; };
add_filter( 'pre_wp_mail', $signup_mail_filter, 10, 2 );
wp_set_current_user( 0 );
function signup_fixture( $email, $ip = '203.0.113.10' ) {
    global $signup_keys;
    $post = array( 'action' => 'vkh_public_signup', '_wpnonce' => wp_create_nonce( 'vkh_public_signup' ), 'vkh_token' => vkh_general_signup_token( $ip, time() - 5 ), 'vkh_return' => home_url( '/' ),
        'vorname' => "Max'; DROP TABLE hund;--", 'nachname' => 'Newsletterfreund', 'email' => $email, 'telefon' => '0000 000000', 'newsletter' => '0', 'weitere_infos' => '1', 'hundeerfahrung' => '1', 'website' => '' );
    foreach ( array( 'ip' => $ip, 'email' => strtolower( $email ), 'global' => '', 'token' => $post['vkh_token'] ) as $type => $identity ) { $signup_keys[] = vkh_general_signup_transient_key( $type, $identity ); }
    return array( $post, array( 'ip' => $ip, 'method' => 'POST', 'origin' => home_url( '/' ), 'length' => 1000 ) );
}
try {
    $start_count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$p}interessent`" );
    list( $post, $ctx ) = signup_fixture( 'public-test@example.com' );
    check( is_wp_error( vkh_general_collect_signup( $post, array_merge( $ctx, array( 'origin' => 'https://evil.example/' ) ) ) ), 'Cross-site signup accepted' );
    check( is_wp_error( vkh_general_collect_signup( $post, array_merge( $ctx, array( 'origin' => '' ) ) ) ), 'Missing origin accepted' );
    check( is_wp_error( vkh_general_collect_signup( $post, array_merge( $ctx, array( 'method' => 'GET' ) ) ) ), 'Signup must be POST only' );
    check( is_wp_error( vkh_general_collect_signup( $post, array_merge( $ctx, array( 'length' => 99999 ) ) ) ), 'Oversized request accepted' );
    foreach ( array(
        array( '_wpnonce' => 'invalid' ), array( 'vkh_token' => 'forged' ), array( 'vkh_token' => vkh_general_signup_token( $ctx['ip'], time() - 4000 ) ),
        array( 'vkh_token' => vkh_general_signup_token( '203.0.113.99', time() - 5 ) ), array( 'email' => array( 'invalid' ) ),
        array( 'id' => '1' ), array( 'bestaetigt_am' => '2026-01-01' ), array( 'newsletter' => '2' ), array( 'newsletter' => '1' ), array( 'newsletter' => '0', 'weitere_infos' => '0' ),
        array( 'email' => "x@example.com\r\nBcc:evil@example.com" ), array( 'telefon' => str_repeat( '1', 51 ) )
    ) as $bad ) { check( is_wp_error( vkh_general_collect_signup( array_merge( $post, $bad ), $ctx ) ), 'Malicious/invalid public input accepted' ); }
    check( true === vkh_general_collect_signup( array_merge( $post, array( 'website' => 'spam.example' ) ), $ctx ), 'Honeypot response must be generic' );
    check( $start_count === $wpdb->get_var( "SELECT COUNT(*) FROM `{$p}interessent`" ), 'Rejected requests wrote personal data' );
    check( true === vkh_general_collect_signup( $post, $ctx ), 'Public signup failed' );
    $signup = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$p}anmeldung` WHERE email=%s", $post['email'] ), ARRAY_A );
    check( $signup && '0' === $signup['newsletter'] && '1' === $signup['weitere_infos'] && null === $signup['bestaetigt_am'], 'Signup must remain unconfirmed with selected purpose only' );
    check( $signup['einwilligung_text'] === vkh_general_signup_consents()['weitere_infos'] && VKH_SIGNUP_CONSENT_VERSION === $signup['einwilligung_version'], 'Consent snapshot missing' );
    check( abs( strtotime( $signup['eingegangen_am'] . ' UTC' ) - time() ) < 30, 'Consent timestamp must be server-generated UTC' );
    $person = vkh_general_get_prospect( $signup['interessent_id'] );
    check( $person['vorname'] === $post['vorname'], 'SQL-like name was not stored as plain data' );
    check( str_contains( vkh_general_signup_summary( $person ), 'unbestätigt' ), 'Backend must identify pending signup' );
    list( $duplicate, $duplicate_ctx ) = signup_fixture( $post['email'], '203.0.113.11' );
    $duplicate['vorname'] = 'ATTACKER';
    check( true === vkh_general_collect_signup( $duplicate, $duplicate_ctx ), 'Duplicate response must be indistinguishable' );
    check( $post['vorname'] === vkh_general_get_prospect( $signup['interessent_id'] )['vorname'], 'Public duplicate overwrote prospect' );
    $replay = $post; $replay['email'] = 'replay-other@example.com';
    check( is_wp_error( vkh_general_collect_signup( $replay, $ctx ) ), 'A successful form token must not register another email' );
    $existing_id = vkh_general_save_prospect( array( 'vorname' => 'Internal', 'nachname' => 'Contact', 'email' => 'internal@example.com', 'telefon' => '123456' ) );
    list( $existing_post, $existing_ctx ) = signup_fixture( 'internal@example.com', '203.0.113.12' );
    check( true === vkh_general_collect_signup( $existing_post, $existing_ctx ), 'Signup for existing backend contact failed' );
    $existing_person = vkh_general_get_prospect( $existing_id );
    check( 'Internal' === $existing_person['vorname'] && '123456' === $existing_person['telefon'] && null === $existing_person['anmeldung_bestaetigt'], 'Existing backend data changed or consent confirmed' );
    for ( $n = 0; $n < 6; ++$n ) {
        list( $limited, $limited_ctx ) = signup_fixture( 'limit-' . $n . '@example.com', '203.0.113.20' );
        $result = vkh_general_collect_signup( $limited, $limited_ctx );
        check( $n < 5 ? true === $result : is_wp_error( $result ), 'Per-IP submission limit failed' );
    }
    list( $broken, $broken_ctx ) = signup_fixture( 'rollback-public@example.com', '203.0.113.30' );
    $before_failed = $wpdb->get_var( "SELECT COUNT(*) FROM `{$p}interessent`" );
    $injection = static function( $query ) use ( $p ) { return str_starts_with( $query, 'INSERT INTO `' . $p . 'anmeldung`' ) ? 'VKH_TEST_INVALID_SQL' : $query; };
    add_filter( 'query', $injection );
    check( is_wp_error( vkh_general_collect_signup( $broken, $broken_ctx ) ), 'Failed public insert must return an error' );
    remove_filter( 'query', $injection ); $injection = null;
    check( $before_failed === $wpdb->get_var( "SELECT COUNT(*) FROM `{$p}interessent`" ), 'Failed signup left an orphan person' );
    check( str_contains( file_get_contents( $log ), 'public_signup_failed' ) && ! str_contains( file_get_contents( $log ), 'rollback-public@example.com' ), 'Public failure logging missing or contains email' );
    $_GET = array();
    $form_html = do_shortcode( '[vkh_interessenten]' );
    check( str_contains( $form_html, 'vkh_public_signup' ) && str_contains( $form_html, 'data-vkh-consent' ), 'Shortcode form missing' );
    check( ! str_contains( $form_html, ' checked' ), 'Consent must not be preselected' );
    check( ! str_contains( $form_html, 'name="newsletter"' ) && substr_count( $form_html, 'data-vkh-consent' ) === 1, 'Only further-information consent must be displayed' );
    check( ! str_contains( $form_html, $post['email'] ), 'Public form exposes registered email' );
    check( $signup_mail_count > 0, 'Double opt-in must hand confirmation mails to the mocked transport' );
    require __DIR__ . '/confirmation-cases.php';
    // Consent is deleted with its associated person, not orphaned.
    $wpdb->delete( $p . 'interessent', array( 'id' => $signup['interessent_id'] ) );
    check( '0' === $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$p}anmeldung` WHERE interessent_id=%d", $signup['interessent_id'] ) ), 'Signup FK deletion cascade failed' );
} finally {
    if ( $injection ) { remove_filter( 'query', $injection ); $injection = null; }
    foreach ( array_unique( $signup_keys ) as $key ) { delete_transient( $key ); }
    remove_filter( 'pre_wp_mail', $signup_mail_filter, 10 );
    if ( false === $original_doi_error ) { delete_option( 'vkh_doi_mail_error' ); } else { update_option( 'vkh_doi_mail_error', $original_doi_error, false ); }
    wp_set_current_user( $signup_user );
}