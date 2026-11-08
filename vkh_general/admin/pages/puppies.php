<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Keine Berechtigung.', '', array( 'response' => 403 ) ); }
$vkh_litter_url = add_query_arg( 'page', 'vkh-general-welpen', admin_url( 'admin.php' ) );
$vkh_litters = VKH_GENERAL_DB_VERSION === get_option( 'vkh_general_db_version' ) ? vkh_general_list_litters() : new WP_Error( 'schema', 'Bitte zuerst die Datenbankaktualisierung abschließen.' );
$vkh_litter_editing = isset( $_GET['edit'] ) || isset( $_GET['new'] ) || isset( $GLOBALS['vkh_litter_input'] );
$vkh_litter_open = isset( $_GET['open'] ) && is_scalar( $_GET['open'] ) ? absint( $_GET['open'] ) : 0;
?>
<div class="wrap vkh-puppies">
    <div class="vkh-puppies-heading"><h1>Unsere Welpen</h1><?php if ( ! $vkh_litter_editing && ! is_wp_error( $vkh_litters ) ) : ?><a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'new', 1, $vkh_litter_url ) ); ?>">Neuen Wurf anlegen</a><?php endif; ?></div>
    <?php if ( is_wp_error( $vkh_litters ) ) : ?><div class="notice notice-error inline"><p><?php echo esc_html( $vkh_litters->get_error_message() ); ?></p></div>
    <?php elseif ( $vkh_litter_editing ) : ?>
        <?php require __DIR__ . '/litter-form.php'; ?>
    <?php else : ?>
        <?php if ( isset( $_GET['saved'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Wurf und Welpen wurden gespeichert.</p></div><?php endif; ?>
        <?php if ( isset( $_GET['status'] ) && 'saved' === $_GET['status'] ) : ?><div class="notice notice-success is-dismissible"><p>Der Verkaufsstatus wurde gespeichert.</p></div><?php endif; ?>
        <?php if ( isset( $_GET['status'] ) && 'failed' === $_GET['status'] ) : ?><div class="notice notice-error inline"><p>Der Verkaufsstatus konnte nicht gespeichert werden. Bitte erneut versuchen.</p></div><?php endif; ?>
        <p class="description"><?php echo esc_html( count( $vkh_litters ) . ' Würfe · ' . array_sum( array_map( static fn( $litter ) => count( $litter['welpen'] ), $vkh_litters ) ) . ' Welpen' ); ?></p>
        <div data-vkh-status-message role="status" aria-live="polite"></div>
        <?php if ( ! $vkh_litters ) : ?><p>Noch keine Würfe vorhanden. Über „Neuen Wurf anlegen“ kannst du den ersten Wurf erfassen.</p><?php endif; ?>
        <?php foreach ( $vkh_litters as $vkh_index => $vkh_litter ) : ?>
            <?php $vkh_available = count( array_filter( $vkh_litter['welpen'], static fn( $puppy ) => 'nein' === $puppy['verkaufsstatus'] ) ); ?>
            <details class="vkh-litter" <?php echo ( $vkh_litter_open ? (int) $vkh_litter['id'] === $vkh_litter_open : 0 === $vkh_index ) ? 'open' : ''; ?>>
                <summary><strong><?php echo esc_html( $vkh_litter['bezeichnung'] ); ?></strong> <span class="vkh-litter-meta"><?php echo esc_html( count( $vkh_litter['welpen'] ) . ' Welpen · ' ); ?><span data-vkh-available><?php echo esc_html( $vkh_available ); ?></span> verfügbar<?php if ( $vkh_litter['beispiel'] ) : ?> · Beispiel<?php endif; ?></span></summary>
                <?php if ( $vkh_litter['wurfdatum'] ) : ?><p class="vkh-litter-date">Wurfdatum: <?php echo esc_html( implode( '.', array_reverse( explode( '-', $vkh_litter['wurfdatum'] ) ) ) ); ?></p><?php endif; ?>
                <p class="vkh-litter-date"><a class="button" href="<?php echo esc_url( add_query_arg( 'edit', absint( $vkh_litter['id'] ), $vkh_litter_url ) ); ?>">Wurf bearbeiten</a></p>
                <div class="vkh-table-wrap"><table class="widefat striped"><caption class="screen-reader-text"><?php echo esc_html( 'Welpen im ' . $vkh_litter['bezeichnung'] ); ?></caption>
                    <thead><tr><th scope="col">Name</th><th scope="col">Geschlecht</th><th scope="col">Verkauft?</th></tr></thead><tbody>
                    <?php foreach ( $vkh_litter['welpen'] as $vkh_puppy ) : ?>
                        <tr><td><?php echo esc_html( $vkh_puppy['name'] ); ?></td><td><?php echo esc_html( vkh_general_puppy_sexes()[ $vkh_puppy['geschlecht'] ] ); ?></td><td>
                            <form class="vkh-status-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-vkh-status-form>
                                <input type="hidden" name="action" value="vkh_puppy_status"><input type="hidden" name="puppy_id" value="<?php echo esc_attr( $vkh_puppy['id'] ); ?>">
                                <?php wp_nonce_field( 'vkh_puppy_status_' . $vkh_puppy['id'] ); ?>
                                <span class="vkh-dot vkh-dot-<?php echo esc_attr( $vkh_puppy['verkaufsstatus'] ); ?>" aria-hidden="true"></span>
                                <label class="screen-reader-text" for="vkh-status-<?php echo esc_attr( $vkh_puppy['id'] ); ?>"><?php echo esc_html( 'Verkaufsstatus von ' . $vkh_puppy['name'] ); ?></label>
                                <select id="vkh-status-<?php echo esc_attr( $vkh_puppy['id'] ); ?>" name="verkaufsstatus" data-previous="<?php echo esc_attr( $vkh_puppy['verkaufsstatus'] ); ?>">
                                    <?php foreach ( vkh_general_puppy_statuses() as $vkh_value => $vkh_label ) : ?><option value="<?php echo esc_attr( $vkh_value ); ?>" <?php selected( $vkh_puppy['verkaufsstatus'], $vkh_value ); ?>><?php echo esc_html( $vkh_label ); ?></option><?php endforeach; ?>
                                </select><button type="submit" class="button" data-vkh-save-status>Speichern</button>
                            </form>
                        </td></tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
            </details>
        <?php endforeach; ?>
    <?php endif; ?>
</div>