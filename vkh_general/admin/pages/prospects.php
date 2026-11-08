<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Keine Berechtigung.', '', array( 'response' => 403 ) ); }
$vkh_prospect_url = add_query_arg( 'page', 'vkh-general-interessenten', admin_url( 'admin.php' ) );
$vkh_prospect_page = isset( $_GET['paged'] ) && is_scalar( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$vkh_prospect_ready = VKH_GENERAL_DB_VERSION === get_option( 'vkh_general_db_version' );
$vkh_prospect_list = $vkh_prospect_ready ? vkh_general_list_prospects( $vkh_prospect_page ) : new WP_Error( 'schema', 'Die Datenbankaktualisierung muss zuerst erfolgreich abgeschlossen werden.' );
?>
<div class="wrap">
    <h1>Interessenten</h1>
    <p>Frontend-Formular einbinden: Widget „VKH: Interessenten-Anmeldung“ oder Shortcode <code>[vkh_interessenten]</code>. Online-Anmeldungen werden nach Bestätigung der E-Mail-Adresse als bestätigt angezeigt.</p>
    <?php if ( is_wp_error( $vkh_prospect_list ) ) : ?>
        <div class="notice notice-error inline"><p><?php echo esc_html( $vkh_prospect_list->get_error_message() ); ?></p></div>
    <?php else : ?>
        <?php require __DIR__ . '/prospect-form.php'; ?>
        <h2>Alle Interessenten <span style="font-weight:normal">(<?php echo esc_html( $vkh_prospect_list['total'] ); ?>)</span></h2>
        <div style="overflow-x:auto">
            <table class="wp-list-table widefat fixed striped">
                <caption class="screen-reader-text">Interessenten mit Kontaktdaten und Hundeerfahrung</caption>
                <thead><tr><th scope="col">Nachname</th><th scope="col">Vorname</th><th scope="col">Telefonnummer</th><th scope="col">E-Mail</th><th scope="col">Hundeerfahrung?</th><th scope="col">Interesse an</th><th scope="col">Angelegt am</th><th scope="col">Online-Anmeldung</th><th scope="col">Aktionen</th></tr></thead>
                <tbody>
                <?php if ( empty( $vkh_prospect_list['items'] ) ) : ?>
                    <tr><td colspan="9">Es sind noch keine Interessenten eingetragen.</td></tr>
                <?php else : ?>
                    <?php foreach ( $vkh_prospect_list['items'] as $vkh_prospect ) : ?>
                    <tr>
                        <td style="overflow-wrap:anywhere"><?php echo esc_html( $vkh_prospect['nachname'] ); ?></td>
                        <td style="overflow-wrap:anywhere"><?php echo esc_html( $vkh_prospect['vorname'] ); ?></td>
                        <td style="overflow-wrap:anywhere"><?php echo esc_html( $vkh_prospect['telefon'] ?: '—' ); ?></td>
                        <td style="overflow-wrap:anywhere"><a href="<?php echo esc_url( 'mailto:' . $vkh_prospect['email'] ); ?>"><?php echo esc_html( $vkh_prospect['email'] ); ?></a></td>
                        <td><?php echo $vkh_prospect['hundeerfahrung'] ? 'Ja' : 'Nein'; ?></td>
                        <td style="overflow-wrap:anywhere"><?php echo esc_html( ! empty( $vkh_prospect['welpe_name'] ) ? $vkh_prospect['welpe_name'] . ' (' . $vkh_prospect['wurf_bezeichnung'] . ')' : ( ! empty( $vkh_prospect['hund_name'] ) ? ( 'Safron' === $vkh_prospect['hund_name'] ? 'Safron (Zuchtrüde)' : 'Bisherige Zuordnung prüfen' ) : 'Noch offen' ) ); ?></td>
                        <td><?php echo esc_html( vkh_general_format_prospect_created( $vkh_prospect['angelegt_am'] ) ); ?></td>
                        <td><?php echo esc_html( vkh_general_signup_summary( $vkh_prospect ) ); ?></td>
                        <td><?php if ( ! $vkh_prospect_editing ) : ?><a class="button" href="<?php echo esc_url( add_query_arg( 'edit', absint( $vkh_prospect['id'] ), $vkh_prospect_url ) ); ?>" aria-label="<?php echo esc_attr( $vkh_prospect['vorname'] . ' ' . $vkh_prospect['nachname'] . ' bearbeiten' ); ?>">Bearbeiten</a><?php else : ?>—<?php endif; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ( $vkh_prospect_list['pages'] > 1 ) : ?>
            <nav class="tablenav" aria-label="Seiten der Interessentenliste">
                <?php if ( $vkh_prospect_list['page'] > 1 ) : ?><a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $vkh_prospect_list['page'] - 1, $vkh_prospect_url ) ); ?>">Vorherige Seite</a><?php endif; ?>
                <span><?php echo esc_html( sprintf( 'Seite %d von %d', $vkh_prospect_list['page'], $vkh_prospect_list['pages'] ) ); ?></span>
                <?php if ( $vkh_prospect_list['page'] < $vkh_prospect_list['pages'] ) : ?><a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $vkh_prospect_list['page'] + 1, $vkh_prospect_url ) ); ?>">Nächste Seite</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>