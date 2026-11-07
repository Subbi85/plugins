<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$vkh_base_url = add_query_arg( 'page', 'vkh-general-hunde', admin_url( 'admin.php' ) );
$vkh_editing = isset( $_GET['new'] ) || isset( $_GET['edit'] ) || isset( $GLOBALS['vkh_dog_form_input'] );
?>
<?php if ( isset( $_GET['saved'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p>Der Hund wurde gespeichert.</p></div>
<?php endif; ?>
<?php if ( ! $vkh_editing ) : ?>
    <p><a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'new', '1', $vkh_base_url ) ); ?>">Hund hinzufügen</a></p>
<?php else : ?>
    <?php
    $vkh_form = array( 'dog_id' => 0, 'name' => '', 'geburtsdatum' => '', 'huefte_id' => '', 'bemerkungen' => '', 'verstorben' => 0, 'championtitel' => array(), 'jlpp_huu' => array() );
    $vkh_form_error = $GLOBALS['vkh_dog_form_error'] ?? '';
    $vkh_edit_id = isset( $_GET['edit'] ) && is_scalar( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
    if ( isset( $GLOBALS['vkh_dog_form_input'] ) ) {
        $vkh_form = array_merge( $vkh_form, $GLOBALS['vkh_dog_form_input'] );
    } elseif ( isset( $_GET['edit'] ) ) {
        $vkh_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$vkh_prefix}hund` WHERE id = %d", $vkh_edit_id ), ARRAY_A );
        if ( ! $vkh_row ) { $vkh_form_error = 'Dieser Hund wurde nicht gefunden.'; }
        else {
            $vkh_form = array_merge( $vkh_form, $vkh_row, array( 'dog_id' => $vkh_edit_id ) );
            foreach ( array( 'championtitel', 'jlpp_huu' ) as $vkh_relation ) {
                $vkh_form[ $vkh_relation ] = $wpdb->get_col( $wpdb->prepare( "SELECT `{$vkh_relation}_id` FROM `{$vkh_prefix}hund_{$vkh_relation}` WHERE hund_id = %d", $vkh_edit_id ) );
            }
        }
    }
    $vkh_can_edit = ! ( isset( $_GET['edit'] ) && empty( $vkh_form['dog_id'] ) );
    ?>
    <?php if ( $vkh_form_error ) : ?><div class="notice notice-error inline"><p><?php echo esc_html( $vkh_form_error ); ?></p></div><?php endif; ?>
    <?php if ( $vkh_can_edit ) : ?>
    <div class="vkh-dog-editor" style="max-width:960px;background:#fff;border:1px solid #c3c4c7;padding:20px;margin:20px 0;">
        <h2><?php echo $vkh_form['dog_id'] ? 'Hund bearbeiten' : 'Hund hinzufügen'; ?></h2>
        <form method="post" action="<?php echo esc_url( $vkh_form['dog_id'] ? add_query_arg( 'edit', absint( $vkh_form['dog_id'] ), $vkh_base_url ) : add_query_arg( 'new', '1', $vkh_base_url ) ); ?>">
            <?php wp_nonce_field( 'vkh_save_dog' ); ?>
            <input type="hidden" name="dog_id" value="<?php echo esc_attr( $vkh_form['dog_id'] ); ?>">
            <table class="form-table" role="presentation"><tbody>
                <tr><th><label for="vkh-name">Name *</label></th><td><input class="regular-text" id="vkh-name" name="name" type="text" maxlength="255" required value="<?php echo esc_attr( $vkh_form['name'] ); ?>"></td></tr>
                <tr><th><label for="vkh-birth">Geburtsdatum</label></th><td><input id="vkh-birth" name="geburtsdatum" type="date" value="<?php echo esc_attr( $vkh_form['geburtsdatum'] ?? '' ); ?>"></td></tr>
                <?php foreach ( array( 'championtitel' => array( 'Titel', 'titel' ), 'jlpp_huu' => array( 'JLPP / HUU', 'jlpp_huu' ) ) as $vkh_relation => $vkh_config ) : ?>
                    <?php $vkh_options = $wpdb->get_results( "SELECT id, `{$vkh_config[1]}` AS label FROM `{$vkh_prefix}{$vkh_relation}` ORDER BY `{$vkh_config[1]}`" ); ?>
                    <tr><th><?php echo esc_html( $vkh_config[0] ); ?></th><td>
                        <fieldset><legend class="screen-reader-text"><?php echo esc_html( $vkh_config[0] ); ?></legend>
                        <?php foreach ( $vkh_options as $vkh_option ) : ?>
                            <label style="display:block;margin-bottom:8px"><input type="checkbox" name="<?php echo esc_attr( $vkh_relation ); ?>[]" value="<?php echo esc_attr( $vkh_option->id ); ?>" <?php checked( in_array( (int) $vkh_option->id, array_map( 'intval', (array) $vkh_form[ $vkh_relation ] ), true ) ); ?>> <?php echo esc_html( $vkh_option->label ); ?></label>
                        <?php endforeach; ?>
                        </fieldset>
                        <label for="vkh-new-<?php echo esc_attr( $vkh_relation ); ?>">Neue Einträge (optional, einer pro Zeile)</label><br>
                        <textarea class="large-text" rows="2" id="vkh-new-<?php echo esc_attr( $vkh_relation ); ?>" name="new_<?php echo esc_attr( $vkh_relation ); ?>"><?php echo esc_textarea( $vkh_form[ 'new_' . $vkh_relation ] ?? '' ); ?></textarea>
                        <p class="description">Mehrere Zuordnungen sind möglich. Neue Einträge werden beim Speichern angelegt und zugeordnet.</p>
                    </td></tr>
                <?php endforeach; ?>
                <tr><th><label for="vkh-hip">Hüfte</label></th><td>
                    <select id="vkh-hip" name="huefte_id"><option value="">Kein Befund</option>
                    <?php foreach ( $wpdb->get_results( "SELECT id, befund FROM `{$vkh_prefix}huefte` ORDER BY befund" ) as $vkh_option ) : ?>
                        <option value="<?php echo esc_attr( $vkh_option->id ); ?>" <?php selected( $vkh_form['huefte_id'], $vkh_option->id ); ?>><?php echo esc_html( $vkh_option->befund ); ?></option>
                    <?php endforeach; ?></select>
                    <p><label for="vkh-new-hip">Oder neuer Befund:</label> <input id="vkh-new-hip" type="text" name="new_huefte" maxlength="255" value="<?php echo esc_attr( $vkh_form['new_huefte'] ?? '' ); ?>"></p>
                    <p class="description">Ein neuer Befund ersetzt die obige Auswahl für diesen Hund.</p>
                </td></tr>
                <tr><th><label for="vkh-notes">Bemerkungen</label></th><td><textarea class="large-text" id="vkh-notes" name="bemerkungen" rows="4"><?php echo esc_textarea( $vkh_form['bemerkungen'] ); ?></textarea></td></tr>
                <tr><th>Verstorben</th><td><label><input type="checkbox" name="verstorben" value="1" <?php checked( ! empty( $vkh_form['verstorben'] ) ); ?>> Ja</label></td></tr>
            </tbody></table>
            <p><button class="button button-primary" type="submit" name="vkh_save_dog" value="1">Hund speichern</button> <a class="button" href="<?php echo esc_url( $vkh_base_url ); ?>">Abbrechen</a></p>
        </form>
    </div>
    <?php else : ?><p><a class="button" href="<?php echo esc_url( $vkh_base_url ); ?>">Zurück zu den Hunden</a></p><?php endif; ?>
<?php endif; ?>