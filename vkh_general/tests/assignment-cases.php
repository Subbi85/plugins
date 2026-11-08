<?php
if ( PHP_SAPI !== 'cli' || ! defined( 'VKH_INTEGRATION_CASES' ) ) { exit; }
$wpdb->insert( $p . 'hund', array( 'name' => 'Safron' ) ); $safron_id = (int) $wpdb->insert_id;
$wpdb->insert( $p . 'hund', array( 'name' => 'Other adult dog' ) ); $other_id = (int) $wpdb->insert_id;
$wurf_id = vkh_general_save_litter( array( 'bezeichnung' => 'Assignment litter', 'wurfdatum' => '', 'welpen' => array( array( 'name' => 'Assignable puppy', 'geschlecht' => 'ruede', 'verkaufsstatus' => 'verkauft' ) ) ) );
$puppy_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$p}welpe` WHERE wurf_id=%d", $wurf_id ) );
$person = array( 'vorname' => 'Assignment', 'nachname' => 'Fixture', 'email' => 'assignment@example.com', 'zuordnung' => 'welpe:' . $puppy_id );
$person_id = vkh_general_save_prospect( $person );
check( ! is_wp_error( $person_id ), 'Assign litter puppy failed' );
try {
    $row = vkh_general_get_prospect( $person_id );
    check( (int) $row['welpe_id'] === $puppy_id && null === $row['hund_id'], 'Puppy assignment must clear adult dog assignment' );
    $list = vkh_general_list_prospects();
    check( count( $list['dogs'] ) === 1 && (int) $list['dogs'][0]['id'] === $safron_id, 'Only Safron must be an adult dog choice' );
    check( count( $list['puppies'] ) === 1 && 'Assignment litter' === $list['puppies'][0]['wurf'], 'Puppy choices must include their litter' );
    $visible_person = array_values( array_filter( $list['items'], static fn( $item ) => (int) $item['id'] === $person_id ) );
    check( $visible_person && 'Assignable puppy' === $visible_person[0]['welpe_name'], 'List must resolve assigned puppy name' );
    $_GET = array( 'edit' => $person_id );
    ob_start(); include $root . '/admin/pages/prospects.php'; $html = ob_get_clean();
    check( str_contains( $html, 'Safron (Zuchtrüde)' ) && str_contains( $html, 'Assignable puppy (Assignment litter)' ) && ! str_contains( $html, 'Other adult dog' ), 'Assignment selector shows disallowed or missing choices' );
    check( preg_match( '~value="welpe:' . $puppy_id . '"\s+selected=~', $html ), 'Existing puppy assignment must be selected' );
    $person['id'] = $person_id; $person['zuordnung'] = 'hund:' . $safron_id;
    check( $person_id === vkh_general_save_prospect( $person ), 'Assign Safron failed' );
    $row = vkh_general_get_prospect( $person_id );
    check( (int) $row['hund_id'] === $safron_id && null === $row['welpe_id'], 'Safron assignment must clear puppy assignment' );
    foreach ( array( 'hund:' . $other_id, 'hund:999999999', 'welpe:999999999', 'evil:' . $puppy_id, 'welpe:1 OR 1=1' ) as $bad ) {
        check( is_wp_error( vkh_general_save_prospect( array_merge( $person, array( 'zuordnung' => $bad ) ) ) ), 'Disallowed assignment accepted' );
    }
    check( is_wp_error( vkh_general_save_prospect( array_merge( $person, array( 'zuordnung' => array( 'bad' ) ) ) ) ), 'Array assignment accepted' );
    $both = $person; unset( $both['zuordnung'] ); $both['hund_id'] = $safron_id; $both['welpe_id'] = $puppy_id;
    check( is_wp_error( vkh_general_save_prospect( $both ) ), 'Two simultaneous assignments accepted' );
    $other = $both; unset( $other['welpe_id'] ); $other['hund_id'] = $other_id;
    check( is_wp_error( vkh_general_save_prospect( $other ) ), 'Legacy adult-dog field bypassed Safron restriction' );
    $person['zuordnung'] = '';
    check( $person_id === vkh_general_save_prospect( $person ), 'Clearing assignment failed' );
    $row = vkh_general_get_prospect( $person_id );
    check( null === $row['hund_id'] && null === $row['welpe_id'], 'Clearing must reset both foreign keys' );
    $person['zuordnung'] = 'welpe:' . $puppy_id; vkh_general_save_prospect( $person );
    check( 'verkauft' === $wpdb->get_var( $wpdb->prepare( "SELECT verkaufsstatus FROM `{$p}welpe` WHERE id=%d", $puppy_id ) ), 'Assignment must not change puppy sales status' );
    check( false === $wpdb->update( $p . 'interessent', array( 'welpe_id' => 999999999 ), array( 'id' => $person_id ) ), 'Puppy assignment FK not enforced' );
    $wpdb->delete( $p . 'wurf', array( 'id' => $wurf_id ) );
    check( null === vkh_general_get_prospect( $person_id )['welpe_id'], 'Deleting litter/puppy must retain contact and SET NULL' );
    $person['zuordnung'] = 'hund:' . $safron_id; vkh_general_save_prospect( $person );
    $wpdb->delete( $p . 'hund', array( 'id' => $safron_id ) );
    check( null === vkh_general_get_prospect( $person_id )['hund_id'], 'Deleting Safron must retain contact and SET NULL' );
} finally {
    $wpdb->delete( $p . 'interessent', array( 'id' => $person_id ) );
    $wpdb->delete( $p . 'wurf', array( 'id' => $wurf_id ) );
    $wpdb->delete( $p . 'hund', array( 'id' => $safron_id ) );
    $wpdb->delete( $p . 'hund', array( 'id' => $other_id ) );
}