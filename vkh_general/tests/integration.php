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
    $wpdb->delete( $p . 'hund', array( 'id' => $id ) );
    check( '0' === $wpdb->get_var( "SELECT COUNT(*) FROM `{$p}hund_championtitel`" ), 'Delete cascade failed' );
    $contents = file_get_contents( $log );
    check( str_contains( $contents, 'dog_save_failed' ) && ! str_contains( $contents, 'SECRET' ) && ! str_contains( $contents, 'INSERT INTO' ), 'Logging missing or sensitive data exposed' );
    echo "PASS: {$checks} integration assertions.\n";
} catch ( Throwable $error ) {
    fwrite( STDERR, 'FAIL: ' . $error->getMessage() . "\n" ); $exit_code = 1;
} finally {
    if ( $injection ) { remove_filter( 'query', $injection ); }
    foreach ( array( 'hund_championtitel', 'hund_jlpp_huu', 'hund', 'huefte', 'jlpp_huu', 'championtitel' ) as $table ) {
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