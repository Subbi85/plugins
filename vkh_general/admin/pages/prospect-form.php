<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$vkh_prospect_editing = isset( $_GET['new'] ) || isset( $_GET['edit'] ) || isset( $GLOBALS['vkh_prospect_form_input'] );
$vkh_prospect_error = $GLOBALS['vkh_prospect_form_error'] ?? '';
$vkh_prospect_state = array( 'id' => 0, 'vorname' => '', 'nachname' => '', 'telefon' => '', 'email' => '', 'hundeerfahrung' => 0, 'hund_id' => '', 'welpe_id' => '' );
$vkh_prospect_can_edit = true;
if ( isset( $GLOBALS['vkh_prospect_form_input'] ) ) {
    $vkh_prospect_state = array_merge( $vkh_prospect_state, $GLOBALS['vkh_prospect_form_input'] );
    if ( ! empty( $vkh_prospect_state['id'] ) ) {
        $vkh_stored_prospect = vkh_general_get_prospect( absint( $vkh_prospect_state['id'] ) );
        if ( $vkh_stored_prospect && ! is_wp_error( $vkh_stored_prospect ) ) {
            $vkh_prospect_state['angelegt_am'] = $vkh_stored_prospect['angelegt_am'];
            foreach ( array( 'anmeldung_am', 'anmeldung_email', 'anmeldung_text', 'anmeldung_newsletter', 'anmeldung_infos', 'anmeldung_bestaetigt', 'anmeldung_id', 'anmeldung_mail_status' ) as $key ) {
                $vkh_prospect_state[ $key ] = $vkh_stored_prospect[ $key ];
            }
        }
    }
} elseif ( isset( $_GET['edit'] ) ) {
    $vkh_prospect_id = is_scalar( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
    $vkh_prospect_row = vkh_general_get_prospect( $vkh_prospect_id );
    if ( ! $vkh_prospect_row || is_wp_error( $vkh_prospect_row ) ) {
        $vkh_prospect_error = is_wp_error( $vkh_prospect_row ) ? $vkh_prospect_row->get_error_message() : 'Dieser Interessent wurde nicht gefunden.';
        $vkh_prospect_can_edit = false;
    } else { $vkh_prospect_state = array_merge( $vkh_prospect_state, $vkh_prospect_row ); }
}
?>
<?php if ( isset( $_GET['saved'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Der Interessent wurde gespeichert.</p></div><?php endif; ?>
<?php if ( $vkh_prospect_error ) : ?><div class="notice notice-error inline"><p><?php echo esc_html( $vkh_prospect_error ); ?></p></div><?php endif; ?>
<?php if ( ! $vkh_prospect_editing ) : ?>
    <p><a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'new', 1, $vkh_prospect_url ) ); ?>">Interessent hinzufügen</a></p>
<?php elseif ( $vkh_prospect_can_edit ) : ?>
    <div style="max-width:900px;padding:20px;background:#fff;border:1px solid #c3c4c7;margin:20px 0">
        <h2><?php echo empty( $vkh_prospect_state['id'] ) ? 'Interessent hinzufügen' : 'Interessent bearbeiten'; ?></h2>
        <form method="post" action="<?php echo esc_url( empty( $vkh_prospect_state['id'] ) ? add_query_arg( 'new', 1, $vkh_prospect_url ) : add_query_arg( 'edit', absint( $vkh_prospect_state['id'] ), $vkh_prospect_url ) ); ?>">
            <?php wp_nonce_field( 'vkh_save_prospect' ); ?>
            <input type="hidden" name="id" value="<?php echo esc_attr( $vkh_prospect_state['id'] ); ?>">
            <?php if ( ! empty( $vkh_prospect_state['anmeldung_am'] ) ) : ?>
                <details style="padding:12px;border:1px solid #dcdcde;margin:12px 0"><summary>Online-Anmeldung und Einwilligung ansehen</summary>
                    <p><strong><?php echo esc_html( vkh_general_signup_summary( $vkh_prospect_state ) ); ?></strong></p>
                    <p>Eingegangen: <?php echo esc_html( vkh_general_format_prospect_created( $vkh_prospect_state['anmeldung_am'] ) ); ?><br>E-Mail der Anfrage: <?php echo esc_html( $vkh_prospect_state['anmeldung_email'] ); ?></p>
                    <p style="white-space:pre-wrap"><?php echo esc_html( $vkh_prospect_state['anmeldung_text'] ); ?></p>
                                        <p><?php echo empty( $vkh_prospect_state['anmeldung_bestaetigt'] ) ? 'Die E-Mail-Adresse ist noch unbestätigt.' : 'Bestätigt am: ' . esc_html( vkh_general_format_prospect_created( $vkh_prospect_state['anmeldung_bestaetigt'] ) ); ?></p>
                    <p>Versandstatus: <?php $vkh_mail_labels = array( 'ausstehend' => 'Noch nicht an den Maildienst übergeben', 'uebergeben' => 'An den Maildienst übergeben (Zustellung nicht geprüft)', 'fehler' => 'Versand fehlgeschlagen', 'bestaetigt' => 'E-Mail-Adresse bestätigt' ); echo esc_html( $vkh_mail_labels[ $vkh_prospect_state['anmeldung_mail_status'] ?? '' ] ?? 'Unbekannt' ); ?></p>
                    <p>Kontaktänderungen verändern diese Einwilligungsaufzeichnung nicht.</p>
                </details>
            <?php endif; ?>
            <table class="form-table" role="presentation"><tbody>
                <?php if ( ! empty( $vkh_prospect_state['id'] ) ) : ?>
                <tr><th>Angelegt am</th><td><?php echo esc_html( vkh_general_format_prospect_created( $vkh_prospect_state['angelegt_am'] ?? null ) ); ?><p class="description">Wird automatisch erfasst und beim Bearbeiten nicht verändert.</p></td></tr>
                <?php endif; ?>
                <tr><th><label for="vkh-prospect-vorname">Vorname *</label></th><td><input class="regular-text" id="vkh-prospect-vorname" type="text" name="vorname" maxlength="100" autocomplete="given-name" required value="<?php echo esc_attr( $vkh_prospect_state['vorname'] ); ?>"></td></tr>
                <tr><th><label for="vkh-prospect-nachname">Nachname *</label></th><td><input class="regular-text" id="vkh-prospect-nachname" type="text" name="nachname" maxlength="100" autocomplete="family-name" required value="<?php echo esc_attr( $vkh_prospect_state['nachname'] ); ?>"></td></tr>
                <tr><th><label for="vkh-prospect-telefon">Telefonnummer</label></th><td><input class="regular-text" id="vkh-prospect-telefon" type="tel" name="telefon" maxlength="50" autocomplete="tel" value="<?php echo esc_attr( $vkh_prospect_state['telefon'] ); ?>"></td></tr>
                <tr><th><label for="vkh-prospect-email">E-Mail *</label></th><td><input class="regular-text" id="vkh-prospect-email" type="email" name="email" maxlength="254" autocomplete="email" required value="<?php echo esc_attr( $vkh_prospect_state['email'] ); ?>"></td></tr>
                <tr><th>Hundeerfahrung?</th><td><label for="vkh-prospect-experience"><input id="vkh-prospect-experience" type="checkbox" name="hundeerfahrung" value="1" <?php checked( '1' === (string) $vkh_prospect_state['hundeerfahrung'] ); ?>> Ja, Hundeerfahrung vorhanden</label></td></tr>
                <?php
                $vkh_assignment = array_key_exists( 'zuordnung', $vkh_prospect_state ) ? $vkh_prospect_state['zuordnung'] : ( ! empty( $vkh_prospect_state['welpe_id'] ) ? 'welpe:' . $vkh_prospect_state['welpe_id'] : ( ! empty( $vkh_prospect_state['hund_id'] ) ? 'hund:' . $vkh_prospect_state['hund_id'] : '' ) );
                $vkh_puppy_groups = array();
                foreach ( $vkh_prospect_list['puppies'] as $option ) { $vkh_puppy_groups[ $option['wurf'] ][] = $option; }
                ?>
                <tr><th><label for="vkh-prospect-dog">Interesse an</label></th><td><select id="vkh-prospect-dog" name="zuordnung"><option value="">Noch offen</option>
                    <?php if ( $vkh_prospect_list['dogs'] ) : ?><optgroup label="Zuchtrüde">
                        <?php foreach ( $vkh_prospect_list['dogs'] as $vkh_option ) : ?><option value="<?php echo esc_attr( 'hund:' . $vkh_option['id'] ); ?>" <?php selected( $vkh_assignment, 'hund:' . $vkh_option['id'] ); ?>>Safron (Zuchtrüde)</option><?php endforeach; ?>
                    </optgroup><?php endif; ?>
                    <?php foreach ( $vkh_puppy_groups as $vkh_group_name => $vkh_options ) : ?><optgroup label="<?php echo esc_attr( $vkh_group_name ); ?>">
                        <?php foreach ( $vkh_options as $vkh_option ) : ?><option value="<?php echo esc_attr( 'welpe:' . $vkh_option['id'] ); ?>" <?php selected( $vkh_assignment, 'welpe:' . $vkh_option['id'] ); ?>><?php echo esc_html( $vkh_option['name'] . ' (' . $vkh_group_name . ')' ); ?></option><?php endforeach; ?>
                    </optgroup><?php endforeach; ?>
                </select><p class="description">Wähle einen Welpen aus einem Wurf oder Safron als Zuchtrüden. Die Zuordnung ändert den Verkaufsstatus nicht.</p></td></tr>
            </tbody></table>
            <p><button class="button button-primary" type="submit" name="vkh_save_prospect" value="1">Interessent speichern</button> <a class="button" href="<?php echo esc_url( $vkh_prospect_url ); ?>">Abbrechen</a></p>
        </form>
        <?php if ( ! empty( $vkh_prospect_state['anmeldung_id'] ) && empty( $vkh_prospect_state['anmeldung_bestaetigt'] ) ) : ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="vkh_resend_confirmation"><input type="hidden" name="signup_id" value="<?php echo esc_attr( $vkh_prospect_state['anmeldung_id'] ); ?>">
            <?php wp_nonce_field( 'vkh_resend_doi_' . $vkh_prospect_state['anmeldung_id'] ); ?>
            <p><button type="submit" class="button">Bestätigungsmail senden / erneut senden</button></p>
        </form>
        <?php endif; ?>
    </div>
<?php else : ?><p><a class="button" href="<?php echo esc_url( $vkh_prospect_url ); ?>">Zurück zu den Interessenten</a></p><?php endif; ?>