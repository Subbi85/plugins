<?php
/** Integration tests use randomly prefixed plugin tables and temporary media records. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
if ( '--allow-test-writes' !== ( $argv[1] ?? '' ) ) {
    fwrite( STDERR, "Use --allow-test-writes on a local/test installation.\n" ); exit( 1 );
}
$load = $argv[2] ?? dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_file( $load ) ) { fwrite( STDERR, "Usage: php tests/integration.php --allow-test-writes /path/to/wordpress/wp-load.php\n" ); exit( 1 ); }
if ( ! defined( 'DISABLE_WP_CRON' ) ) { define( 'DISABLE_WP_CRON', true ); }
require $load;
$root = dirname( __DIR__ );
require_once $root . '/includes/logging.php';
require_once $root . '/includes/database.php';
require_once $root . '/includes/dogs.php';
require_once $root . '/includes/dog-pdf.php';
require_once $root . '/includes/prospects.php';
$checks = 0;
function check( $condition, $message ) { global $checks; if ( ! $condition ) { throw new RuntimeException( $message ); } ++$checks; }
$original_prefix = $wpdb->prefix;
$original_user = get_current_user_id();
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
check( ! empty( $admins ), 'A local administrator is required for media validation' );
wp_set_current_user( $admins[0] );
$wpdb->prefix = $original_prefix . 'vkh_test_' . bin2hex( random_bytes( 6 ) ) . '_';
$p = $wpdb->prefix . 'vkh_';
$attachments = array();
$log = tempnam( sys_get_temp_dir(), 'vkh-integration-' );
$old_log = ini_set( 'error_log', $log );
$old_suppression = $wpdb->suppress_errors( true );
$version_filter = static fn() => VKH_GENERAL_DB_VERSION;
add_filter( 'pre_option_vkh_general_db_version', $version_filter );
$injection = null;
$exit_code = 0;
try {
    check( true === vkh_general_install_schema(), 'Initial schema installation failed' );
    check( true === vkh_general_install_schema(), 'Repeated installation failed' );
    $wpdb->insert( $p . 'hund', array( 'name' => 'Migration fixture' ) );
    $fixture = (int) $wpdb->insert_id;
    $fk = $wpdb->get_var( $wpdb->prepare( 'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME=%s AND REFERENCED_TABLE_NAME IS NOT NULL', $p . 'hund', 'foto_id' ) );
    check( (bool) $fk, 'Photo foreign key missing' );
    check( false !== $wpdb->query( "ALTER TABLE `{$p}hund` DROP FOREIGN KEY `{$fk}`, DROP COLUMN foto_id" ), 'Cannot simulate an old schema' );
    check( true === vkh_general_install_schema(), 'Upgrade of existing schema failed' );
    check( 'Migration fixture' === $wpdb->get_var( "SELECT name FROM `{$p}hund` WHERE id={$fixture}" ), 'Upgrade lost existing data' );
    // Also repair a partially completed upgrade with a column but no FK.
    $fk = $wpdb->get_var( $wpdb->prepare( 'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME=%s AND REFERENCED_TABLE_NAME IS NOT NULL', $p . 'hund', 'foto_id' ) );
    $wpdb->query( "ALTER TABLE `{$p}hund` DROP FOREIGN KEY `{$fk}`" );
    check( true === vkh_general_install_schema(), 'Partial migration repair failed' );
    $wpdb->delete( $p . 'hund', array( 'id' => $fixture ) );
    foreach ( array( 'image/jpeg', 'application/pdf' ) as $mime ) {
        $attachment = wp_insert_attachment( array( 'post_title' => 'VKH isolated test ' . bin2hex( random_bytes( 8 ) ), 'post_mime_type' => $mime, 'post_status' => 'inherit' ), false, 0, true );
        check( ! is_wp_error( $attachment ), 'Test attachment creation failed' );
        $attachments[] = $attachment;
        update_post_meta( $attachment, '_wp_attached_file', 'vkh-test-' . bin2hex( random_bytes( 16 ) ) . ( 'image/jpeg' === $mime ? '.jpg' : '.pdf' ) );
    }
    $data = array( 'name' => 'Balu <script>alert(1)</script>', 'geburtsdatum' => '2022-05-14', 'new_championtitel' => "Titel A\nTitel B", 'new_jlpp_huu' => "JLPP frei\nHUU frei", 'new_huefte' => 'HD A', 'foto_id' => $attachments[0], 'ahnentafel_id' => $attachments[1], 'verstorben' => 1 );
    $id = vkh_general_save_dog( $data );
    check( ! is_wp_error( $id ), 'Create dog with media failed' );
    check( '2' === $wpdb->get_var( "SELECT COUNT(*) FROM `{$p}hund_championtitel` WHERE hund_id={$id}" ), 'Multiple titles missing' );
    check( '2' === $wpdb->get_var( "SELECT COUNT(*) FROM `{$p}hund_jlpp_huu` WHERE hund_id={$id}" ), 'Multiple health findings missing' );
    $export_dog = vkh_general_pdf_dog_data( $id );
    check( ! is_wp_error( $export_dog ) && str_contains( $export_dog->titel, 'Titel B' ), 'PDF must include all titles' );
    $bytes = vkh_general_build_dog_pdf( $export_dog );
    check( is_string( $bytes ) && str_starts_with( $bytes, '%PDF-' ), 'PDF generation failed' );
    $export_dog->bemerkungen = str_repeat( "Hüfte und Größe: längere Bemerkung.\n", 150 );
    $bytes = vkh_general_build_dog_pdf( $export_dog );
    check( is_string( $bytes ) && preg_match_all( '~/Type /Page\b~', $bytes ) > 1, 'Long PDF must have multiple pages' );
    check( is_wp_error( vkh_general_pdf_dog_data( 999999999 ) ), 'Missing dog must not be exported' );
    $row = $wpdb->get_row( "SELECT * FROM `{$p}hund` WHERE id={$id}" );
    check( (int) $row->foto_id === $attachments[0] && (int) $row->ahnentafel_id === $attachments[1], 'Media IDs not stored' );
    $_GET = array( 'edit' => $id );
    ob_start(); include $root . '/admin/pages/dogs.php'; $html = ob_get_clean();
    check( substr_count( $html, '<form ' ) === 1 && str_contains( $html, '_wpnonce' ), 'Exactly one protected form expected' );
    check( ! str_contains( $html, '<script>alert(1)</script>' ) && ! str_contains( $html, '<dt>ID</dt>' ), 'Unsafe output or visible dog ID' );
    check( is_wp_error( vkh_general_save_dog( array( 'name' => 'Invalid', 'geburtsdatum' => '2023-02-30' ) ) ), 'Invalid date accepted' );
    check( is_wp_error( vkh_general_save_dog( array( 'name' => '' ) ) ), 'Empty name accepted' );
    check( is_wp_error( vkh_general_save_dog( array( 'name' => 'Invalid', 'championtitel' => array( 999999999 ) ) ) ), 'Invalid relation accepted' );
    check( is_wp_error( vkh_general_save_dog( array( 'name' => 'Invalid', 'foto_id' => $attachments[1] ) ) ), 'PDF accepted as photo' );
    check( false === $wpdb->query( "INSERT INTO `{$p}hund_jlpp_huu` (hund_id,jlpp_huu_id) VALUES(999999999,1)" ), 'Orphan relation accepted' );
    $before = $wpdb->get_var( "SELECT COUNT(*) FROM `{$p}hund`" );
    $injection = static function ( $query ) use ( $p ) {
        return str_starts_with( $query, 'INSERT INTO `' . $p . 'hund_championtitel`' ) ? 'VKH_TEST_INVALID_SQL' : $query;
    };
    add_filter( 'query', $injection );
    $failed = vkh_general_save_dog( array( 'name' => 'SECRET rollback fixture', 'new_championtitel' => 'SECRET unique title' ) );
    remove_filter( 'query', $injection ); $injection = null;
    check( is_wp_error( $failed ), 'Injected SQL failure must fail the save' );
    check( $before === $wpdb->get_var( "SELECT COUNT(*) FROM `{$p}hund`" ), 'Failed save did not roll back dog' );
    check( '0' === $wpdb->get_var( "SELECT COUNT(*) FROM `{$p}championtitel` WHERE titel='SECRET unique title'" ), 'Failed save did not roll back lookup' );
    wp_delete_attachment( $attachments[0], true );
    check( null === $wpdb->get_var( "SELECT foto_id FROM `{$p}hund` WHERE id={$id}" ), 'Media FK must SET NULL on deletion' );
    $title_id = $wpdb->get_var( "SELECT championtitel_id FROM `{$p}hund_championtitel` WHERE hund_id={$id} LIMIT 1" );
    $result = vkh_general_save_dog( array( 'dog_id' => $id, 'name' => "O'Balu", 'championtitel' => array( $title_id ) ) );
    check( $id === $result, 'Update failed' );
    $row = $wpdb->get_row( "SELECT * FROM `{$p}hund` WHERE id={$id}" );
    check( $row->name === "O'Balu" && null === $row->ahnentafel_id && '0' === $row->verstorben, 'Update/reset fields failed' );
    check( '1' === $wpdb->get_var( "SELECT COUNT(*) FROM `{$p}hund_championtitel` WHERE hund_id={$id}" ), 'Relation replacement failed' );
    $wpdb->insert( $p . 'interessent', array( 'vorname' => 'Legacy', 'nachname' => 'Fixture', 'email' => 'legacy@example.com' ) );
    $legacy_id = (int) $wpdb->insert_id;
    check( false !== $wpdb->query( "ALTER TABLE `{$p}interessent` DROP COLUMN `angelegt_am`" ), 'Cannot simulate legacy prospect schema' );
    check( true === vkh_general_install_schema(), 'Prospect creation-date migration failed' );
    $legacy = vkh_general_get_prospect( $legacy_id );
    check( $legacy && null === $legacy['angelegt_am'] && 'Legacy' === $legacy['vorname'], 'Legacy migration must retain data without inventing a creation date' );
    check( $legacy_id === vkh_general_save_prospect( array( 'id' => $legacy_id, 'vorname' => 'Legacy', 'nachname' => 'Updated', 'email' => 'legacy@example.com' ) ), 'Legacy update failed' );
    check( null === vkh_general_get_prospect( $legacy_id )['angelegt_am'], 'Editing legacy data must not invent a creation date' );
    $wpdb->delete( $p . 'interessent', array( 'id' => $legacy_id ) );
    $wpdb->update( $p . 'hund', array( 'name' => 'Safron' ), array( 'id' => $id ) );
    $prospect_data = array( 'angelegt_am' => '1900-01-01 00:00:00', 'vorname' => 'Max <script>alert(1)</script>', 'nachname' => "O'Hundefreund", 'telefon' => '+49 (0) 123 456-789', 'email' => 'max@example.com', 'hundeerfahrung' => '1', 'hund_id' => $id );
    $prospect_id = vkh_general_save_prospect( $prospect_data );
    check( ! is_wp_error( $prospect_id ), 'Create prospect failed' );
    $prospect_row = vkh_general_get_prospect( $prospect_id );
    check( $prospect_row['nachname'] === "O'Hundefreund" && $prospect_row['email'] === 'max@example.com' && '1' === $prospect_row['hundeerfahrung'], 'Prospect fields not stored' );
    check( (int) $prospect_row['hund_id'] === $id, 'Prospect dog reference not stored' );
    $created_at = $prospect_row['angelegt_am'];
    check( is_string( $created_at ) && abs( strtotime( $created_at . ' UTC' ) - time() ) < 30, 'Creation date must be generated in UTC, ignoring POST data' );
    check( 'Nicht erfasst' === vkh_general_format_prospect_created( null ), 'Unknown creation date must be explicit' );
    $tz_filter = static fn() => 'Europe/Berlin';
    add_filter( 'pre_option_timezone_string', $tz_filter );
    try {
        check( '08.11.2026 13:00' === vkh_general_format_prospect_created( '2026-11-08 12:00:00' ), 'Creation date winter timezone conversion failed' );
        check( '08.07.2026 14:00' === vkh_general_format_prospect_created( '2026-07-08 12:00:00' ), 'Creation date summer timezone conversion failed' );
    } finally { remove_filter( 'pre_option_timezone_string', $tz_filter ); }
    $_GET = array();
    ob_start(); include $root . '/admin/pages/prospects.php'; $prospect_html = ob_get_clean();
    check( str_contains( $prospect_html, 'max@example.com' ) && str_contains( $prospect_html, 'Bearbeiten' ), 'Prospect table missing contact/actions' );
    check( ! str_contains( $prospect_html, '<script>alert(1)</script>' ), 'Unsafe prospect HTML' );
    $_GET = array( 'edit' => $prospect_id );
    ob_start(); include $root . '/admin/pages/prospects.php'; $prospect_html = ob_get_clean();
    check( substr_count( $prospect_html, '<form ' ) === 1 && str_contains( $prospect_html, '_wpnonce' ), 'One protected prospect form expected' );
    check( str_contains( $prospect_html, 'max@example.com' ) && str_contains( $prospect_html, 'checked=' ), 'Prospect form not prefilled' );
    foreach ( array(
        array( 'email' => 'invalid' ), array( 'vorname' => '' ), array( 'telefon' => 'call me' ),
        array( 'hundeerfahrung' => '2' ), array( 'hund_id' => '999999999' ), array( 'id' => '999999999' ),
        array( 'email' => array( 'bad' ) ), array( 'nachname' => str_repeat( 'a', 101 ) )
    ) as $invalid ) {
        check( is_wp_error( vkh_general_save_prospect( array_merge( $prospect_data, $invalid ) ) ), 'Invalid prospect input accepted' );
    }
    $prospect_update = array( 'angelegt_am' => '2099-01-01 00:00:00', 'id' => $prospect_id, 'vorname' => 'Max', 'nachname' => 'Hundefreund', 'telefon' => '', 'email' => 'neu@example.com', 'hund_id' => $id );
    check( $prospect_id === vkh_general_save_prospect( $prospect_update ), 'Edit prospect failed' );
    $prospect_row = vkh_general_get_prospect( $prospect_id );
    check( '0' === $prospect_row['hundeerfahrung'] && '' === $prospect_row['telefon'] && 'neu@example.com' === $prospect_row['email'], 'Prospect update/reset failed' );
    check( $created_at === $prospect_row['angelegt_am'], 'Creation date must remain unchanged when editing' );
    $injection = static function( $query ) use ( $p ) { return str_starts_with( $query, 'UPDATE `' . $p . 'interessent`' ) ? 'VKH_TEST_INVALID_SQL' : $query; };
    add_filter( 'query', $injection );
    check( is_wp_error( vkh_general_save_prospect( array_merge( $prospect_update, array( 'vorname' => 'SECRET changed' ) ) ) ), 'Injected prospect failure accepted' );
    remove_filter( 'query', $injection ); $injection = null;
    check( 'Max' === vkh_general_get_prospect( $prospect_id )['vorname'], 'Prospect failure must retain old record' );
    check( false === $wpdb->insert( $p . 'interessent', array( 'vorname' => 'Invalid', 'nachname' => 'FK', 'email' => 'fk@example.com', 'hund_id' => 999999999 ) ), 'Prospect FK not enforced' );
    for ( $i = 0; $i < 26; ++$i ) {
        check( false !== $wpdb->insert( $p . 'interessent', array( 'vorname' => 'Paging', 'nachname' => 'Test' . $i, 'email' => 'paging@example.com' ) ), 'Paging fixture insert failed' );
    }
    $page_one = vkh_general_list_prospects( 1 );
    $page_last = vkh_general_list_prospects( 999 );
    check( 25 === count( $page_one['items'] ) && 27 === $page_one['total'] && 2 === $page_one['pages'], 'Prospect pagination failed' );
    check( 2 === count( $page_last['items'] ) && 2 === $page_last['page'], 'Prospect last page clamp failed' );
    $wpdb->delete( $p . 'hund', array( 'id' => $id ) );
    check( null === vkh_general_get_prospect( $prospect_id )['hund_id'], 'Deleting dog must retain prospect and SET NULL' );
    check( str_contains( file_get_contents( $log ), 'prospect_save_failed' ), 'Prospect failure not logged' );
    check( '0' === $wpdb->get_var( "SELECT COUNT(*) FROM `{$p}hund_championtitel`" ), 'Delete cascade failed' );
    $contents = file_get_contents( $log );
    check( str_contains( $contents, 'dog_save_failed' ) && ! str_contains( $contents, 'SECRET' ) && ! str_contains( $contents, 'INSERT INTO' ), 'Logging missing or sensitive data exposed' );
    define( 'VKH_INTEGRATION_CASES', true );
    require __DIR__ . '/signup-cases.php';
    require __DIR__ . '/puppy-cases.php';
    require __DIR__ . '/latest-litter-cases.php';
    require __DIR__ . '/assignment-cases.php';
    echo "PASS: {$checks} integration assertions.\n";
} catch ( Throwable $error ) {
    fwrite( STDERR, 'FAIL: ' . $error->getMessage() . "\n" ); $exit_code = 1;
} finally {
    if ( $injection ) { remove_filter( 'query', $injection ); }
    foreach ( array( 'anmeldung', 'interessent', 'welpe', 'wurf', 'hund_championtitel', 'hund_jlpp_huu', 'hund', 'huefte', 'jlpp_huu', 'championtitel' ) as $table ) {
        if ( false === $wpdb->query( 'DROP TABLE IF EXISTS `' . $p . $table . '`' ) ) { $exit_code = 1; fwrite( STDERR, 'Cleanup failed for isolated test table.' . "\n" ); }
    }
    foreach ( $attachments as $attachment ) { wp_delete_attachment( $attachment, true ); }
    $wpdb->prefix = $original_prefix;
    $wpdb->suppress_errors( $old_suppression );
    remove_filter( 'pre_option_vkh_general_db_version', $version_filter );
    wp_set_current_user( $original_user );
    ini_set( 'error_log', $old_log ); unlink( $log );
    echo "Isolated test tables and media records removed.\n";
}
exit( $exit_code );