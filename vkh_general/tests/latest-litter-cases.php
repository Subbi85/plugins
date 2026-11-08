<?php
if ( PHP_SAPI !== 'cli' || ! defined( 'VKH_INTEGRATION_CASES' ) ) { exit; }
require_once $root . '/includes/latest-litter.php';
check( '' === vkh_general_render_latest_litter(), 'No litter must render an empty widget' );
check( WP_Block_Type_Registry::get_instance()->is_registered( 'vkh/latest-litter' ), 'Native latest-litter block not registered' );
check( isset( $GLOBALS['wp_widget_factory']->widgets['VKH_General_Latest_Litter_Widget'] ), 'Classic latest-litter widget not registered' );
function latest_fixture( $name, $date, $status ) {
    return vkh_general_save_litter( array( 'bezeichnung' => $name, 'wurfdatum' => $date, 'welpen' => array( array( 'name' => $name . ' Welpe', 'geschlecht' => 'huendin', 'verkaufsstatus' => $status ) ) ) );
}
$old_litter = latest_fixture( 'Old litter', '2025-01-01', 'nein' );
$new_litter = latest_fixture( 'New litter', '2026-01-01', 'nein' );
$last_insert = latest_fixture( 'Late historic entry', '2020-01-01', 'nein' );
$latest = vkh_general_latest_litter();
check( 'New litter' === $latest['bezeichnung'], 'Latest must use litter date, not insertion or name order' );
check( array_keys( $latest['welpen'][0] ) === array( 'name', 'geschlecht', 'verkaufsstatus' ), 'Widget must select only public puppy fields' );
$user_before = get_current_user_id(); wp_set_current_user( 0 );
try {
    $html = vkh_general_render_latest_litter();
    check( str_contains( $html, 'Wir haben aktuell Welpen' ) && str_contains( $html, 'New litter Welpe' ), 'Available latest litter must show headline and names for guests' );
    check( ! str_contains( $html, 'Old litter Welpe' ), 'Widget must not show puppies from older litters' );
    $puppy_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$p}welpe` WHERE wurf_id=%d", $new_litter ) );
    check( str_contains( $html, 'aria-hidden="true">♀</span>' ) && str_contains( $html, 'Hündin</span>' ), 'Female symbol and accessible description missing' );
    $wpdb->update( $p . 'welpe', array( 'geschlecht' => 'ruede' ), array( 'id' => $puppy_id ) );
    $male_html = vkh_general_render_latest_litter();
    check( str_contains( $male_html, 'aria-hidden="true">♂</span>' ) && str_contains( $male_html, 'Rüde</span>' ), 'Male symbol and accessible description missing' );
    vkh_general_update_puppy_status( (int) $puppy_id, 'vorgemerkt' );
    $html = vkh_general_render_latest_litter();
    check( ! str_contains( $html, 'Wir haben aktuell Welpen' ) && str_contains( $html, 'Vorgemerkt' ), 'Reserved alone must not trigger headline; list must remain' );
    check( str_contains( $html, '<span class="vkh-sr-only">Vorgemerkt</span>' ), 'Status text must be screen-reader-only' );
    vkh_general_update_puppy_status( (int) $puppy_id, 'verkauft' );
    $html = vkh_general_render_latest_litter();
    check( ! str_contains( $html, 'Wir haben aktuell Welpen' ) && str_contains( $html, 'Verkauft' ), 'Sold latest litter must keep its list without availability headline' );
    check( str_contains( $html, 'vkh-public-dot-verkauft' ), 'Sold status mark missing' );
    $wpdb->update( $p . 'welpe', array( 'name' => '<script>alert(1)</script>' ), array( 'id' => $puppy_id ) );
    $html = vkh_general_render_latest_litter();
    check( ! str_contains( $html, '<script>alert(1)</script>' ) && str_contains( $html, '&lt;script&gt;' ), 'Stored widget output must be escaped' );
    $block_html = do_blocks( '<!-- wp:vkh/latest-litter /-->' );
    check( str_contains( $block_html, 'vkh-latest-litter' ), 'Native block must render without a shortcode' );
    ob_start(); the_widget( 'VKH_General_Latest_Litter_Widget' ); $classic_html = ob_get_clean();
    check( str_contains( $classic_html, 'vkh-latest-litter' ), 'Classic widget must render' );
    $unknown = latest_fixture( 'Unknown birth date', '', 'nein' );
    $wpdb->update( $p . 'wurf', array( 'angelegt_am' => '2026-02-01 10:00:00' ), array( 'id' => $unknown ) );
    check( 'Unknown birth date' === vkh_general_latest_litter()['bezeichnung'], 'Missing birth date must fall back to creation date' );
    $wpdb->update( $p . 'wurf', array( 'angelegt_am' => '2019-02-01 10:00:00' ), array( 'id' => $unknown ) );
    check( 'New litter' === vkh_general_latest_litter()['bezeichnung'], 'Old unknown-date litter must not displace a newer birth date' );
    $injection = static function( $query ) use ( $p ) { return str_starts_with( $query, 'SELECT id, bezeichnung FROM `' . $p . 'wurf`' ) ? 'VKH_TEST_INVALID_SQL' : $query; };
    add_filter( 'query', $injection );
    check( '' === vkh_general_render_latest_litter(), 'Database error must not leak into the public widget' );
    remove_filter( 'query', $injection ); $injection = null;
    check( str_contains( file_get_contents( $log ), 'latest_litter_failed' ), 'Widget database failure must be logged safely' );
} finally {
    if ( $injection ) { remove_filter( 'query', $injection ); $injection = null; }
    wp_set_current_user( $user_before );
    foreach ( array( $old_litter, $new_litter, $last_insert, $unknown ?? 0 ) as $id ) { if ( $id ) { $wpdb->delete( $p . 'wurf', array( 'id' => $id ) ); } }
}