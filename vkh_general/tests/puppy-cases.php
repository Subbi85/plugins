<?php
if ( PHP_SAPI !== 'cli' || ! defined( 'VKH_INTEGRATION_CASES' ) ) { exit; }
require_once $root . '/includes/puppies.php';
$input = array( 'bezeichnung' => "F-Wurf O'Balu", 'wurfdatum' => '2026-11-08', 'welpen' => array(
    array( 'name' => "Fiete'; DROP TABLE welpe;--", 'geschlecht' => 'ruede', 'verkaufsstatus' => 'nein' ),
    array( 'name' => 'Flora <script>alert(1)</script>', 'geschlecht' => 'huendin', 'verkaufsstatus' => 'vorgemerkt' ),
    array( 'name' => 'Frieda', 'geschlecht' => 'huendin', 'verkaufsstatus' => 'verkauft' ),
) );
$litter_id = vkh_general_save_litter( $input );
check( ! is_wp_error( $litter_id ), 'Create litter failed' );
$litters = vkh_general_list_litters();
check( ! is_wp_error( $litters ) && count( $litters ) === 1 && count( $litters[0]['welpen'] ) === 3, 'Litter grouping failed' );
check( "F-Wurf O'Balu" === $litters[0]['bezeichnung'] && '2026-11-08' === $litters[0]['wurfdatum'], 'Litter fields not stored correctly' );
check( is_wp_error( vkh_general_save_litter( $input ) ), 'Duplicate litter name accepted' );
$puppy = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$p}welpe` WHERE wurf_id=%d AND geschlecht=%s", $litter_id, 'ruede' ), ARRAY_A );
check( $puppy && $input['welpen'][0]['name'] === $puppy['name'], 'SQL-like name must remain plain text' );
foreach ( array( 'vorgemerkt', 'verkauft', 'nein' ) as $status ) {
    check( $litter_id === vkh_general_update_puppy_status( (int) $puppy['id'], $status ), 'Status update failed' );
    check( $status === $wpdb->get_var( $wpdb->prepare( "SELECT verkaufsstatus FROM `{$p}welpe` WHERE id=%d", $puppy['id'] ) ), 'Status update not persisted' );
}
check( is_wp_error( vkh_general_update_puppy_status( (int) $puppy['id'], 'evil' ) ), 'Invalid puppy status accepted' );
check( is_wp_error( vkh_general_update_puppy_status( 999999999, 'verkauft' ) ), 'Missing puppy accepted' );
$bad = $input; $bad['bezeichnung'] = 'Invalid'; $bad['welpen'][1]['geschlecht'] = 'invalid';
check( is_wp_error( vkh_general_save_litter( $bad ) ), 'Invalid sex accepted' );
$bad['welpen'][1]['geschlecht'] = 'huendin'; $bad['welpen'][1]['verkaufsstatus'] = array( 'bad' );
check( is_wp_error( vkh_general_save_litter( $bad ) ), 'Array status accepted' );
$bad = $input; $bad['bezeichnung'] = 'SECRET rollback litter';
$injection = static function( $query ) use ( $p ) { return str_starts_with( $query, 'INSERT INTO `' . $p . 'welpe`' ) ? 'VKH_TEST_INVALID_SQL' : $query; };
add_filter( 'query', $injection );
check( is_wp_error( vkh_general_save_litter( $bad ) ), 'Injected puppy failure accepted' );
remove_filter( 'query', $injection ); $injection = null;
check( '1' === $wpdb->get_var( "SELECT COUNT(*) FROM `{$p}wurf`" ) && '3' === $wpdb->get_var( "SELECT COUNT(*) FROM `{$p}welpe`" ), 'Failed save must roll back the litter and all puppies' );
check( false === $wpdb->insert( $p . 'welpe', array( 'wurf_id' => 999999999, 'name' => 'Orphan', 'geschlecht' => 'ruede', 'verkaufsstatus' => 'nein' ) ), 'Puppy foreign key not enforced' );
$_GET = array();
ob_start(); include $root . '/admin/pages/puppies.php'; $html = ob_get_clean();
check( str_contains( $html, 'Vorgemerkt' ) && str_contains( $html, 'vkh-dot-verkauft' ) && ! str_contains( $html, '<script>alert(1)</script>' ), 'Puppy overview/status labels not safe or missing' );
$_GET = array( 'new' => 1 );
ob_start(); include $root . '/admin/pages/puppies.php'; $html = ob_get_clean();
check( str_contains( $html, 'data-vkh-litter-form' ) && str_contains( $html, '_wpnonce' ) && str_contains( $html, 'data-vkh-step="2"' ), 'Wizard/nonce missing' );
check( substr_count( $html, 'data-vkh-puppy-name' ) === 4, 'Expected three initial puppy fields plus one row template' );
$edit = $litters[0];
$original_created = $edit['angelegt_am'];
$original_ids = array_map( 'intval', array_column( $edit['welpen'], 'id' ) );
$edit['bezeichnung'] = 'F-Wurf bearbeitet'; $edit['wurfdatum'] = '2026-11-07';
$edit['welpen'][0]['name'] = 'Neu benannt'; $edit['welpen'][0]['geschlecht'] = 'huendin'; $edit['welpen'][0]['verkaufsstatus'] = 'verkauft';
check( $litter_id === vkh_general_save_litter( $edit ), 'Edit litter failed' );
$after_edit = vkh_general_list_litters()[0];
check( 'F-Wurf bearbeitet' === $after_edit['bezeichnung'] && '2026-11-07' === $after_edit['wurfdatum'], 'Litter edits not persisted' );
check( $original_created === $after_edit['angelegt_am'], 'Editing changed litter creation timestamp' );
$after_ids = array_map( 'intval', array_column( $after_edit['welpen'], 'id' ) ); sort( $after_ids ); sort( $original_ids );
check( $original_ids === $after_ids, 'Editing must preserve puppy identities' );
$updated_puppy = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$p}welpe` WHERE id=%d", $edit['welpen'][0]['id'] ), ARRAY_A );
check( 'Neu benannt' === $updated_puppy['name'] && 'huendin' === $updated_puppy['geschlecht'] && 'verkauft' === $updated_puppy['verkaufsstatus'], 'Puppy edits not persisted' );
$_GET = array( 'edit' => $litter_id );
ob_start(); include $root . '/admin/pages/puppies.php'; $edit_html = ob_get_clean();
check( str_contains( $edit_html, 'F-Wurf bearbeitet' ) && str_contains( $edit_html, 'Neu benannt' ) && str_contains( $edit_html, 'value="2026-11-07"' ), 'Edit form not prefilled' );
$edit['welpen'][] = array( 'name' => 'Felix', 'geschlecht' => 'ruede', 'verkaufsstatus' => 'nein' );
check( $litter_id === vkh_general_save_litter( $edit ) && '4' === $wpdb->get_var( "SELECT COUNT(*) FROM `{$p}welpe`" ), 'Adding puppy while editing failed' );
$edit = vkh_general_list_litters()[0];
$missing = $edit; array_pop( $missing['welpen'] );
check( is_wp_error( vkh_general_save_litter( $missing ) ), 'Omitted existing puppy must not be silently deleted' );
$foreign = $edit; $foreign['welpen'][0]['id'] = '999999999';
check( is_wp_error( vkh_general_save_litter( $foreign ) ), 'Foreign puppy ID accepted' );
$duplicate_id = $edit; $duplicate_id['welpen'][1]['id'] = $duplicate_id['welpen'][0]['id'];
check( is_wp_error( vkh_general_save_litter( $duplicate_id ) ), 'Repeated puppy ID accepted' );
$missing_litter = $edit; $missing_litter['id'] = '999999999';
check( is_wp_error( vkh_general_save_litter( $missing_litter ) ), 'Missing litter accepted for edit' );
$other_input = $input; $other_input['bezeichnung'] = 'Other litter';
$other_id = vkh_general_save_litter( $other_input );
$bad_name = $edit; $bad_name['bezeichnung'] = 'Other litter';
check( is_wp_error( vkh_general_save_litter( $bad_name ) ), 'Edit allowed duplicate litter name' );
$other_puppy_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$p}welpe` WHERE wurf_id=%d LIMIT 1", $other_id ) );
$foreign['welpen'][0]['id'] = $other_puppy_id;
check( is_wp_error( vkh_general_save_litter( $foreign ) ), 'Editing puppy owned by another litter accepted' );
$wpdb->delete( $p . 'wurf', array( 'id' => $other_id ) );
$injection = static function( $query ) use ( $p ) { return str_starts_with( $query, 'UPDATE `' . $p . 'welpe`' ) ? 'VKH_TEST_INVALID_SQL' : $query; };
add_filter( 'query', $injection );
$broken_edit = $edit; $broken_edit['bezeichnung'] = 'Rollback edit';
check( is_wp_error( vkh_general_save_litter( $broken_edit ) ), 'Injected edit error accepted' );
remove_filter( 'query', $injection ); $injection = null;
check( 'F-Wurf bearbeitet' === vkh_general_list_litters()[0]['bezeichnung'] && '4' === $wpdb->get_var( "SELECT COUNT(*) FROM `{$p}welpe`" ), 'Failed edit did not roll back completely' );
$wpdb->delete( $p . 'wurf', array( 'id' => $litter_id ) );
check( '0' === $wpdb->get_var( "SELECT COUNT(*) FROM `{$p}welpe`" ), 'Litter deletion must cascade to puppies' );
check( str_contains( file_get_contents( $log ), 'litter_save_failed' ) && ! str_contains( file_get_contents( $log ), 'SECRET rollback litter' ), 'Litter logging missing or exposes input' );