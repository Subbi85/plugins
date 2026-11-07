<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Never log SQL, database messages, user input, paths or credentials. */
function vkh_general_log_error( $event ) {
    $reference = bin2hex( random_bytes( 6 ) );
    $entry = array(
        'event' => sanitize_key( $event ),
        'reference' => $reference,
        'time' => gmdate( 'c' ),
    );
    error_log( '[VKH General] ' . wp_json_encode( $entry ) );
    return $reference;
}