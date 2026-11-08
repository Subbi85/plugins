<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

const VKH_GENERAL_DB_VERSION = '0.8.0';

/** Execute idempotent DDL. No options changed here, also usable in isolated tests. */
function vkh_general_install_schema() {
    global $wpdb;
    $previous = $wpdb->suppress_errors( true );
    try {
        $sql = file_get_contents( dirname( __DIR__ ) . '/database/schema.sql' );
        if ( false === $sql ) { throw new RuntimeException( 'schema_read' ); }
        $sql = str_replace( '`wp_posts`', '`' . str_replace( '`', '``', $wpdb->posts ) . '`', $sql );
        $sql = preg_replace( '/^--.*$/m', '', $sql );
        $sql = str_replace( '`wp_vkh_', '`' . str_replace( '`', '``', $wpdb->prefix ) . 'vkh_', $sql );
        $sql = str_replace( 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $wpdb->get_charset_collate(), $sql );
        foreach ( explode( ';', $sql ) as $statement ) {
            if ( '' !== trim( $statement ) && false === $wpdb->query( trim( $statement ) ) ) {
                throw new RuntimeException( 'schema_create' );
            }
        }
        $table = $wpdb->prefix . 'vkh_hund';
        $quoted_table = str_replace( '`', '``', $table );
        $posts = str_replace( '`', '``', $wpdb->posts );
        foreach ( array( 'foto_id', 'ahnentafel_id' ) as $column ) {
            $exists = $wpdb->get_var( $wpdb->prepare(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s', $table, $column
            ) );
            if ( $wpdb->last_error ) { throw new RuntimeException( 'schema_inspect' ); }
            if ( ! $exists && false === $wpdb->query( "ALTER TABLE `{$quoted_table}` ADD COLUMN `{$column}` BIGINT UNSIGNED DEFAULT NULL" ) ) {
                throw new RuntimeException( 'schema_column' );
            }
            // Repair partially applied upgrades too: a column alone is not sufficient.
            $foreign_key = $wpdb->get_var( $wpdb->prepare(
                'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s AND REFERENCED_TABLE_NAME = %s AND REFERENCED_COLUMN_NAME = %s',
                $table, $column, $wpdb->posts, 'ID'
            ) );
            if ( $wpdb->last_error ) { throw new RuntimeException( 'schema_inspect' ); }
            if ( ! $foreign_key && false === $wpdb->query(
                "ALTER TABLE `{$quoted_table}` ADD FOREIGN KEY (`{$column}`) REFERENCES `{$posts}` (`ID`) ON DELETE SET NULL ON UPDATE CASCADE"
            ) ) { throw new RuntimeException( 'schema_foreign_key' ); }
        }
        $prospects = $wpdb->prefix . 'vkh_interessent';
        $created_column = $wpdb->get_var( $wpdb->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
            $prospects, 'angelegt_am'
        ) );
        if ( $wpdb->last_error ) { throw new RuntimeException( 'schema_inspect' ); }
        // Historical creation times are unknown: retain NULL rather than invent a date.
        $quoted_prospects = str_replace( '`', '``', $prospects );
        if ( ! $created_column && false === $wpdb->query( "ALTER TABLE `{$quoted_prospects}` ADD COLUMN `angelegt_am` DATETIME DEFAULT NULL COMMENT 'UTC - NULL for legacy records'" ) ) {
            throw new RuntimeException( 'schema_prospect_created' );
        }
        $email_index = $wpdb->get_var( $wpdb->prepare(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND INDEX_NAME=%s',
            $prospects, 'idx_email'
        ) );
        if ( $wpdb->last_error ) { throw new RuntimeException( 'schema_inspect' ); }
        if ( ! $email_index && false === $wpdb->query( "ALTER TABLE `{$quoted_prospects}` ADD INDEX `idx_email` (`email`(191))" ) ) {
            throw new RuntimeException( 'schema_prospect_email_index' );
        }
        $signup_table = $wpdb->prefix . 'vkh_anmeldung';
        $quoted_signup = str_replace( '`', '``', $signup_table );
        foreach ( array(
            'doi_token_hash' => 'CHAR(64) DEFAULT NULL',
            'doi_gueltig_bis' => 'DATETIME DEFAULT NULL',
            'doi_mail_versuch_am' => 'DATETIME DEFAULT NULL',
            'doi_mail_versendet_am' => 'DATETIME DEFAULT NULL',
            'doi_mail_status' => "VARCHAR(20) NOT NULL DEFAULT 'ausstehend'",
        ) as $column => $definition ) {
            $exists = $wpdb->get_var( $wpdb->prepare( 'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME=%s', $signup_table, $column ) );
            if ( $wpdb->last_error ) { throw new RuntimeException( 'schema_inspect' ); }
            if ( ! $exists && false === $wpdb->query( "ALTER TABLE `{$quoted_signup}` ADD COLUMN `{$column}` {$definition}" ) ) { throw new RuntimeException( 'schema_doi' ); }
        }
        $puppy_column = $wpdb->get_var( $wpdb->prepare( 'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME=%s', $prospects, 'welpe_id' ) );
        if ( $wpdb->last_error ) { throw new RuntimeException( 'schema_inspect' ); }
        if ( ! $puppy_column && false === $wpdb->query( "ALTER TABLE `{$quoted_prospects}` ADD COLUMN `welpe_id` BIGINT UNSIGNED DEFAULT NULL" ) ) { throw new RuntimeException( 'schema_assignment' ); }
        $puppy_table = $wpdb->prefix . 'vkh_welpe';
        $assignment_fk = $wpdb->get_var( $wpdb->prepare( 'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME=%s AND REFERENCED_TABLE_NAME=%s', $prospects, 'welpe_id', $puppy_table ) );
        if ( $wpdb->last_error ) { throw new RuntimeException( 'schema_inspect' ); }
        if ( ! $assignment_fk && false === $wpdb->query( "ALTER TABLE `{$quoted_prospects}` ADD FOREIGN KEY (`welpe_id`) REFERENCES `" . str_replace( '`', '``', $puppy_table ) . "` (`id`) ON DELETE SET NULL ON UPDATE CASCADE" ) ) { throw new RuntimeException( 'schema_assignment_fk' ); }
        return true;
    } catch ( Throwable $error ) {
        $reference = vkh_general_log_error( 'schema_install_failed' );
        return new WP_Error( 'schema_failed', 'Die Datenbankaktualisierung ist fehlgeschlagen.', array( 'reference' => $reference ) );
    } finally {
        $wpdb->suppress_errors( $previous );
    }
}

/** Non-blocking advisory lock prevents concurrent DDL; retry only on explicit request. */
function vkh_general_run_migrations( $retry = false ) {
    global $wpdb;
    if ( VKH_GENERAL_DB_VERSION === get_option( 'vkh_general_db_version' ) ) { return true; }
    $failure = get_option( 'vkh_general_db_error' );
    if ( ! $retry && is_array( $failure ) && VKH_GENERAL_DB_VERSION === ( $failure['target'] ?? '' ) ) {
        return new WP_Error( 'schema_paused', 'Die Datenbankaktualisierung wartet auf einen erneuten Versuch.' );
    }
    $previous = $wpdb->suppress_errors( true );
    $lock = 'vkh_' . md5( DB_NAME . ':' . $wpdb->prefix );
    $locked = false;
    try {
        $locked = '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) );
        if ( ! $locked ) {
            if ( $wpdb->last_error ) { throw new RuntimeException( 'schema_lock' ); }
            return new WP_Error( 'schema_busy', 'Die Datenbankaktualisierung läuft bereits.' );
        }
        // A concurrent request may already have completed the upgrade.
        wp_cache_delete( 'alloptions', 'options' );
        wp_cache_delete( 'vkh_general_db_version', 'options' );
        if ( VKH_GENERAL_DB_VERSION === get_option( 'vkh_general_db_version' ) ) { return true; }
        wp_cache_delete( 'vkh_general_db_error', 'options' );
        $failure = get_option( 'vkh_general_db_error' );
        if ( ! $retry && is_array( $failure ) && VKH_GENERAL_DB_VERSION === ( $failure['target'] ?? '' ) ) {
            return new WP_Error( 'schema_paused', 'Die Datenbankaktualisierung wartet auf einen erneuten Versuch.' );
        }
        $result = vkh_general_install_schema();
        if ( is_wp_error( $result ) ) {
            $details = $result->get_error_data();
            update_option( 'vkh_general_db_error', array( 'target' => VKH_GENERAL_DB_VERSION, 'reference' => $details['reference'], 'time' => time() ), false );
            return $result;
        }
        update_option( 'vkh_general_db_version', VKH_GENERAL_DB_VERSION, false );
        if ( VKH_GENERAL_DB_VERSION !== get_option( 'vkh_general_db_version' ) ) { throw new RuntimeException( 'schema_version' ); }
        delete_option( 'vkh_general_db_error' );
        return true;
    } catch ( Throwable $error ) {
        $reference = vkh_general_log_error( 'schema_migration_failed' );
        update_option( 'vkh_general_db_error', array( 'target' => VKH_GENERAL_DB_VERSION, 'reference' => $reference, 'time' => time() ), false );
        return new WP_Error( 'schema_failed', 'Die Datenbankaktualisierung ist fehlgeschlagen.' );
    } finally {
        if ( $locked ) { $wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
        $wpdb->suppress_errors( $previous );
    }
}

function vkh_general_activate() {
    // Activation remains successful: the rest of WordPress stays accessible on failure.
    vkh_general_run_migrations( true );
}

add_action( 'admin_init', 'vkh_general_maybe_upgrade_schema', 5 );
function vkh_general_maybe_upgrade_schema() {
    if ( current_user_can( 'manage_options' ) ) { vkh_general_run_migrations(); }
}

add_action( 'admin_notices', 'vkh_general_database_notice' );
function vkh_general_database_notice() {
    if ( ! current_user_can( 'manage_options' ) || VKH_GENERAL_DB_VERSION === get_option( 'vkh_general_db_version' ) ) { return; }
    $failure = get_option( 'vkh_general_db_error' );
    echo '<div class="notice notice-warning"><p>' . esc_html( 'VKH General: Die Datenbank ist noch nicht bereit. Das übrige WordPress-Backend bleibt nutzbar. Bitte Datenbankberechtigungen und InnoDB prüfen.' ) . '</p>';
    if ( is_array( $failure ) && ! empty( $failure['reference'] ) ) {
        echo '<p>' . esc_html( 'Fehlerreferenz: ' . $failure['reference'] ) . '</p>';
    }
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    wp_nonce_field( 'vkh_retry_database' );
    echo '<input type="hidden" name="action" value="vkh_retry_database"><p><button class="button" type="submit">Datenbankaktualisierung erneut versuchen</button></p></form></div>';
}

add_action( 'admin_post_vkh_retry_database', 'vkh_general_retry_database' );
function vkh_general_retry_database() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Keine Berechtigung.', '', array( 'response' => 403 ) ); }
    check_admin_referer( 'vkh_retry_database' );
    vkh_general_run_migrations( true );
    wp_safe_redirect( admin_url( 'admin.php?page=vkh-general' ) );
    exit;
}