<?php
if ( PHP_SAPI !== 'cli' || ! defined( 'VKH_INTEGRATION_CASES' ) ) { exit; }
function doi_link_data( $mail ) {
    preg_match( '~https?://[^\s]+~', $mail['message'], $matches );
    parse_str( wp_parse_url( $matches[0], PHP_URL_QUERY ), $args );
    return $args;
}
$first_link = doi_link_data( $signup_mails[0] );
$doi_id = (int) $first_link['id']; $first_token = $first_link['token'];
$stored = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$p}anmeldung` WHERE id=%d", $doi_id ), ARRAY_A );
check( hash( 'sha256', $first_token ) === $stored['doi_token_hash'] && ! str_contains( json_encode( $stored ), $first_token ), 'Store only a hash, never the raw confirmation token' );
check( abs( strtotime( $stored['doi_gueltig_bis'] . ' UTC' ) - time() - 48 * HOUR_IN_SECONDS ) < 30, 'Confirmation expiry must be 48 hours' );
check( in_array( 'From: ' . get_option( 'admin_email' ), $signup_mails[0]['headers'], true ), 'Sender must use WordPress administration address' );
check( ! str_contains( $signup_mails[0]['subject'], "DROP TABLE" ), 'Untrusted name must not enter mail headers' );
check( vkh_general_confirmation_record( $doi_id, $first_token ) && null === $stored['bestaetigt_am'], 'Reading the link must not confirm the request' );
check( false === vkh_general_confirm_signup( $doi_id, str_repeat( 'f', 64 ) ), 'Forged confirmation token accepted' );
check( false === vkh_general_confirm_signup( $doi_id, array( 'bad' ) ), 'Array token accepted' );
check( false === vkh_general_confirm_signup( 0, $first_token ), 'Missing confirmation ID accepted' );
$wpdb->update( $p . 'anmeldung', array( 'doi_gueltig_bis' => gmdate( 'Y-m-d H:i:s', time() - 1 ) ), array( 'id' => $doi_id ) );
check( false === vkh_general_confirm_signup( $doi_id, $first_token ), 'Expired confirmation token accepted' );
check( is_wp_error( vkh_general_send_confirmation( $doi_id, 60 ) ), 'Resend cooldown not enforced' );
$wpdb->update( $p . 'anmeldung', array( 'doi_mail_versuch_am' => null ), array( 'id' => $doi_id ) );
check( true === vkh_general_send_confirmation( $doi_id, 60 ), 'Resend failed' );
$new_link = doi_link_data( $signup_mails[ count( $signup_mails ) - 1 ] );
check( $new_link['token'] !== $first_token && null === vkh_general_confirmation_record( $doi_id, $first_token ), 'Resend must rotate and invalidate the old token' );
check( true === vkh_general_confirm_signup( $doi_id, $new_link['token'] ), 'Valid confirmation failed' );
$confirmed = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$p}anmeldung` WHERE id=%d", $doi_id ), ARRAY_A );
check( null !== $confirmed['bestaetigt_am'] && null === $confirmed['doi_token_hash'] && null === $confirmed['doi_gueltig_bis'], 'Confirmation must set server date and consume the token' );
check( false === vkh_general_confirm_signup( $doi_id, $new_link['token'] ), 'Consumed token was accepted twice' );
check( str_contains( vkh_general_signup_summary( vkh_general_get_prospect( $confirmed['interessent_id'] ) ), '(bestätigt)' ), 'Backend must show confirmed state' );
$before_mail = $signup_mail_count;
check( true === vkh_general_send_confirmation( $doi_id, 60 ) && $before_mail === $signup_mail_count, 'Confirmed request must not receive another confirmation mail' );
// An actual transport failure stays visible without confirming or deleting the request.
$failure_id = (int) $existing_person['anmeldung_id'];
$wpdb->update( $p . 'anmeldung', array( 'doi_mail_versuch_am' => null ), array( 'id' => $failure_id ) );
$fail_mail = static fn() => false;
add_filter( 'pre_wp_mail', $fail_mail, 20 );
try { $failed_mail = vkh_general_send_confirmation( $failure_id, 60 ); }
finally { remove_filter( 'pre_wp_mail', $fail_mail, 20 ); }
check( is_wp_error( $failed_mail ), 'Mail transport failure must return WP_Error' );
$failed_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$p}anmeldung` WHERE id=%d", $failure_id ), ARRAY_A );
check( 'fehler' === $failed_row['doi_mail_status'] && null === $failed_row['bestaetigt_am'], 'Mail failure must stay pending and visible' );
check( (bool) get_option( 'vkh_doi_mail_error' ), 'Mail failure must trigger the administrator notice' );
$wpdb->update( $p . 'anmeldung', array( 'doi_mail_versuch_am' => null ), array( 'id' => $failure_id ) );
check( true === vkh_general_send_confirmation( $failure_id, 60 ) && ! get_option( 'vkh_doi_mail_error' ), 'Successful retry must clear the mail error' );
check( ! str_contains( file_get_contents( $log ), $first_token ) && ! str_contains( file_get_contents( $log ), $new_link['token'] ), 'Logs must not contain confirmation tokens' );