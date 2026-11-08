<?php
/** Run without WordPress or a database: php tests/unit.php */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', __DIR__ );
define( 'DB_NAME', 'unit_tests' );
class WP_Error {
    public function __construct( public $code, public $message, public $data = null ) {}
    public function get_error_data() { return $this->data; }
    public function get_error_message() { return $this->message; }
}
class TestStop extends RuntimeException {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function add_action( ...$args ) {}
function add_shortcode( ...$args ) {}
function get_option( $key ) { return $GLOBALS['options'][ $key ] ?? false; }
function update_option( $key, $value, ...$args ) { $GLOBALS['options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); }
function wp_cache_delete( ...$args ) {}
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_]/', '', $value ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function current_user_can( ...$args ) { return $GLOBALS['allowed'] ?? false; }
function check_admin_referer( ...$args ) { if ( empty( $GLOBALS['nonce_ok'] ) ) { throw new TestStop( 'nonce' ); } }
function wp_die( ...$args ) { throw new TestStop( 'permission' ); }
function wp_verify_nonce( $nonce, $action ) { return $nonce === 'valid:' . $action; }
function wp_unslash( $value ) { return $value; }
class TestDB {
    public $prefix = 'test_';
    public $posts = 'test_posts';
    public $last_error = '';
    public $suppressed = false;
    public $fail_ddl = false;
    public $insert_id = 0;
    public $busy = false;
    public $missing_fk = false;
    public $queries = array();
    public function suppress_errors( $flag ) { $old = $this->suppressed; $this->suppressed = $flag; return $old; }
    public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4'; }
    public function prepare( $sql, ...$args ) { return $sql; }
    public function get_var( $sql ) {
        $this->queries[] = $sql; $this->last_error = '';
        if ( str_contains( $sql, 'GET_LOCK' ) ) { return $this->busy ? '0' : '1'; }
        if ( str_contains( $sql, 'KEY_COLUMN_USAGE' ) ) { return $this->missing_fk ? null : 'foreign_key'; }
        if ( str_contains( $sql, 'information_schema' ) ) { return 'foto_id'; }
        return null;
    }
    public function query( $sql ) {
        $this->queries[] = $sql; $this->last_error = '';
        if ( $this->fail_ddl && str_starts_with( $sql, 'CREATE' ) ) { $this->last_error = 'SECRET password SQL user input'; return false; }
        return 1;
    }
    public function insert( ...$args ) { $this->last_error = 'SECRET password SQL user input'; return false; }
}
require dirname( __DIR__ ) . '/includes/logging.php';
require dirname( __DIR__ ) . '/includes/database.php';
require dirname( __DIR__ ) . '/includes/dogs.php';
require dirname( __DIR__ ) . '/includes/dog-pdf.php';
require dirname( __DIR__ ) . '/includes/prospects.php';
require dirname( __DIR__ ) . '/includes/confirmation.php';
require dirname( __DIR__ ) . '/includes/puppies.php';
$checks = 0;
function check( $condition, $message ) { global $checks; if ( ! $condition ) { throw new RuntimeException( $message ); } ++$checks; }
$log = tempnam( sys_get_temp_dir(), 'vkh-unit-' );
$old_log = ini_set( 'error_log', $log );
try {
    $wpdb = new TestDB(); $options = array();
    $wpdb->fail_ddl = true;
    $result = vkh_general_run_migrations();
    check( is_wp_error( $result ), 'Migration failure must return WP_Error' );
    check( ! get_option( 'vkh_general_db_version' ), 'Failed update must not advance version' );
    check( is_array( get_option( 'vkh_general_db_error' ) ), 'Failure must persist' );
    check( count( array_filter( $wpdb->queries, fn( $q ) => str_contains( $q, 'RELEASE_LOCK' ) ) ) === 1, 'Lock must be released on failure' );
    check( ! $wpdb->suppressed, 'Error suppression must be restored' );
    $before = count( $wpdb->queries );
    vkh_general_run_migrations();
    check( count( $wpdb->queries ) === $before, 'Failed update must not repeat automatically' );
    $wpdb->fail_ddl = false;
    check( true === vkh_general_run_migrations( true ), 'Manual retry must succeed' );
    check( get_option( 'vkh_general_db_version' ) === VKH_GENERAL_DB_VERSION && ! get_option( 'vkh_general_db_error' ), 'Success must advance version and clear failure' );
    $before = count( $wpdb->queries ); vkh_general_run_migrations();
    check( count( $wpdb->queries ) === $before, 'Current schema must skip DDL' );
    $options = array(); $wpdb = new TestDB(); $wpdb->busy = true;
    check( is_wp_error( vkh_general_run_migrations() ) && ! get_option( 'vkh_general_db_error' ), 'Busy lock is not a failed migration' );
    check( ! count( array_filter( $wpdb->queries, fn( $q ) => str_starts_with( $q, 'CREATE' ) ) ), 'Busy lock must prevent DDL' );
    $wpdb = new TestDB(); $wpdb->missing_fk = true;
    check( true === vkh_general_install_schema(), 'Partial schema repair must succeed' );
    check( count( array_filter( $wpdb->queries, fn( $q ) => str_contains( $q, 'ADD FOREIGN KEY' ) ) ) === 3, 'Missing foreign keys must be repaired' );
    $wpdb = new TestDB();
    $result = vkh_general_save_dog( array( 'name' => 'SECRET dog name' ) );
    check( is_wp_error( $result ), 'Save failure must return WP_Error' );
    check( in_array( 'ROLLBACK', $wpdb->queries, true ), 'Save failure must roll back' );
    check( ! $wpdb->suppressed, 'Save must restore error suppression' );
    $contents = file_get_contents( $log );
    check( str_contains( $contents, 'schema_install_failed' ) && str_contains( $contents, 'dog_save_failed' ), 'Migration and save failures must be logged' );
    check( ! str_contains( $contents, 'SECRET' ) && ! str_contains( $contents, 'password' ) && ! str_contains( $contents, 'CREATE TABLE' ), 'Log must not expose sensitive data or SQL' );
    $_SERVER['REQUEST_METHOD'] = 'POST'; $_GET = array( 'page' => 'vkh-general-hunde' ); $_POST = array( 'vkh_save_dog' => 1 );
    try { vkh_general_handle_dog_form(); throw new RuntimeException( 'Unauthorized save accepted' ); }
    catch ( TestStop $error ) { check( 'permission' === $error->getMessage(), 'Save must check permissions' ); }
    $allowed = true;
    try { vkh_general_handle_dog_form(); throw new RuntimeException( 'Invalid nonce accepted' ); }
    catch ( TestStop $error ) { check( 'nonce' === $error->getMessage(), 'Save must check nonce' ); }
    $allowed = false;
    try { vkh_general_retry_database(); throw new RuntimeException( 'Unauthorized retry accepted' ); }
    catch ( TestStop $error ) { check( 'permission' === $error->getMessage(), 'Retry must check permissions' ); }
    $allowed = true;
    try { vkh_general_retry_database(); throw new RuntimeException( 'Retry with invalid nonce accepted' ); }
    catch ( TestStop $error ) { check( 'nonce' === $error->getMessage(), 'Retry must check nonce' ); }
    $allowed = false;
    check( is_wp_error( vkh_general_export_access( 1, 'valid:vkh_export_dog_1' ) ), 'PDF export must check permission' );
    $allowed = true;
    check( is_wp_error( vkh_general_export_access( 1, 'invalid' ) ), 'PDF export must check nonce' );
    check( is_wp_error( vkh_general_export_access( 2, 'valid:vkh_export_dog_1' ) ), 'PDF nonce must be tied to the selected dog' );
    check( is_wp_error( vkh_general_export_access( 0, 'valid:vkh_export_dog_0' ) ), 'PDF must reject missing dog ID' );
    check( true === vkh_general_export_access( 1, 'valid:vkh_export_dog_1' ), 'Authorized PDF request rejected' );
    $_GET = array( 'page' => 'vkh-general-interessenten' ); $_POST = array( 'vkh_save_prospect' => 1 );
    $allowed = false;
    try { vkh_general_handle_prospect_form(); throw new RuntimeException( 'Unauthorized prospect save accepted' ); }
    catch ( TestStop $error ) { check( 'permission' === $error->getMessage(), 'Prospect save must check permissions' ); }
    $allowed = true;
    try { vkh_general_handle_prospect_form(); throw new RuntimeException( 'Prospect save with invalid nonce accepted' ); }
    catch ( TestStop $error ) { check( 'nonce' === $error->getMessage(), 'Prospect save must check nonce' ); }
    check( is_wp_error( vkh_general_save_prospect( array( 'vorname' => array( 'bad' ) ) ) ), 'Prospect arrays must be rejected' );
    $allowed = false;
    try { vkh_general_resend_confirmation(); throw new RuntimeException( 'Unauthorized confirmation resend accepted' ); }
    catch ( TestStop $error ) { check( 'permission' === $error->getMessage(), 'Confirmation resend must check permissions' ); }
    $allowed = true;
    try { vkh_general_resend_confirmation(); throw new RuntimeException( 'Confirmation resend with invalid nonce accepted' ); }
    catch ( TestStop $error ) { check( 'nonce' === $error->getMessage(), 'Confirmation resend must check nonce' ); }
    $litter = array( 'bezeichnung' => 'F-Wurf', 'wurfdatum' => '', 'welpen' => array( array( 'name' => 'Fiete', 'geschlecht' => 'ruede', 'verkaufsstatus' => 'nein' ) ) );
    check( ! is_wp_error( vkh_general_validate_litter( $litter ) ), 'Valid litter rejected' );
    foreach ( array( array( 'bezeichnung' => '' ), array( 'bezeichnung' => array() ), array( 'wurfdatum' => '2026-02-30' ), array( 'welpen' => array() ), array( 'welpen' => array_fill( 0, 21, $litter['welpen'][0] ) ) ) as $invalid ) {
        check( is_wp_error( vkh_general_validate_litter( array_merge( $litter, $invalid ) ) ), 'Invalid litter accepted' );
    }
    $wpdb = new TestDB();
    check( is_wp_error( vkh_general_save_litter( $litter ) ) && in_array( 'ROLLBACK', $wpdb->queries, true ), 'Failed litter save must roll back' );
    $_SERVER['REQUEST_METHOD'] = 'POST'; $_GET = array( 'page' => 'vkh-general-welpen' ); $_POST = array( 'vkh_save_litter' => 1 );
    $allowed = false;
    try { vkh_general_handle_litter_form(); throw new RuntimeException( 'Unauthorized litter save accepted' ); }
    catch ( TestStop $error ) { check( 'permission' === $error->getMessage(), 'Litter save must check capability' ); }
    $allowed = true;
    try { vkh_general_handle_litter_form(); throw new RuntimeException( 'Litter save with invalid nonce accepted' ); }
    catch ( TestStop $error ) { check( 'nonce' === $error->getMessage(), 'Litter save must check nonce' ); }
    $allowed = false;
    try { vkh_general_puppy_status_request(); throw new RuntimeException( 'Unauthorized puppy status accepted' ); }
    catch ( TestStop $error ) { check( 'permission' === $error->getMessage(), 'Status update must check capability' ); }
    $allowed = true;
    try { vkh_general_puppy_status_request(); throw new RuntimeException( 'Puppy status with invalid nonce accepted' ); }
    catch ( TestStop $error ) { check( 'nonce' === $error->getMessage(), 'Status update must check nonce' ); }
    echo "PASS: {$checks} unit assertions.\n";
} finally {
    ini_set( 'error_log', $old_log ); unlink( $log );
}