<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Public data only. Missing dates fall back to the creation day; ties use the ID. */
function vkh_general_latest_litter() {
    global $wpdb;
    if ( VKH_GENERAL_DB_VERSION !== get_option( 'vkh_general_db_version' ) ) { return null; }
    $p = str_replace( '`', '``', $wpdb->prefix ) . 'vkh_';
    $previous = $wpdb->suppress_errors( true );
    try {
        $litter = $wpdb->get_row( "SELECT id, bezeichnung FROM `{$p}wurf` ORDER BY COALESCE(wurfdatum, DATE(angelegt_am)) DESC, angelegt_am DESC, id DESC LIMIT 1", ARRAY_A );
        if ( $wpdb->last_error ) { throw new RuntimeException(); }
        if ( ! $litter ) { return null; }
        $puppies = $wpdb->get_results( $wpdb->prepare( "SELECT name, geschlecht, verkaufsstatus FROM `{$p}welpe` WHERE wurf_id=%d ORDER BY name, id", $litter['id'] ), ARRAY_A );
        if ( $wpdb->last_error ) { throw new RuntimeException(); }
        if ( ! $puppies ) { return null; }
        return array( 'bezeichnung' => $litter['bezeichnung'], 'welpen' => $puppies );
    } catch ( Throwable $error ) {
        vkh_general_log_error( 'latest_litter_failed' );
        return null;
    } finally { $wpdb->suppress_errors( $previous ); }
}

function vkh_general_render_latest_litter( $title_before = '<h2>', $title_after = '</h2>' ) {
    $litter = vkh_general_latest_litter();
    if ( ! $litter ) { return ''; }
    $available = count( array_filter( $litter['welpen'], static fn( $puppy ) => 'nein' === $puppy['verkaufsstatus'] ) ) > 0;
    $labels = vkh_general_puppy_statuses();
    ob_start();
    ?>
    <section class="vkh-latest-litter" aria-label="<?php echo esc_attr( 'Welpen aus dem ' . $litter['bezeichnung'] ); ?>">
        <?php if ( $available ) { echo $title_before . esc_html( 'Wir haben aktuell Welpen' ) . $title_after; } ?>
        <p class="vkh-latest-litter-name"><?php echo esc_html( $litter['bezeichnung'] ); ?></p>
        <table><caption class="vkh-sr-only"><?php echo esc_html( 'Welpen und Verkaufsstatus: ' . $litter['bezeichnung'] ); ?></caption><thead><tr><th scope="col">Name</th><th scope="col">Status</th></tr></thead><tbody>
            <?php foreach ( $litter['welpen'] as $puppy ) : ?>
                <tr><td><?php echo esc_html( $puppy['name'] ); ?> <span class="vkh-public-sex" aria-hidden="true"><?php echo 'ruede' === $puppy['geschlecht'] ? '♂' : '♀'; ?></span><span class="vkh-sr-only"> <?php echo esc_html( vkh_general_puppy_sexes()[ $puppy['geschlecht'] ] ?? 'Geschlecht unbekannt' ); ?></span></td><td><span class="vkh-public-status"><span class="vkh-public-dot vkh-public-dot-<?php echo esc_attr( $puppy['verkaufsstatus'] ); ?>" aria-hidden="true"></span><span class="vkh-sr-only"><?php echo esc_html( $labels[ $puppy['verkaufsstatus'] ] ?? 'Unbekannt' ); ?></span></span></td></tr>
            <?php endforeach; ?>
        </tbody></table>
    </section>
    <?php
    return ob_get_clean();
}

add_action( 'widgets_init', static function() {
    require_once __DIR__ . '/latest-litter-widget.php';
    register_widget( 'VKH_General_Latest_Litter_Widget' );
} );

add_action( 'init', static function() {
    $root = dirname( __DIR__ );
    wp_register_style( 'vkh-latest-litter', plugins_url( 'public/latest-litter.css', $root . '/index.php' ), array(), filemtime( $root . '/public/latest-litter.css' ) );
    wp_register_script( 'vkh-latest-litter-editor', plugins_url( 'blocks/latest-litter/editor.js', $root . '/index.php' ), array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-server-side-render' ), filemtime( $root . '/blocks/latest-litter/editor.js' ), true );
    register_block_type( $root . '/blocks/latest-litter', array( 'render_callback' => static function() {
        $html = vkh_general_render_latest_litter();
        return $html ? '<div ' . get_block_wrapper_attributes() . '>' . $html . '</div>' : '';
    } ) );
} );
add_action( 'wp_enqueue_scripts', static function() { wp_enqueue_style( 'vkh-latest-litter' ); } );