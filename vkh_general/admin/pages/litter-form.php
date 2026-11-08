<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$vkh_last_letter = 64;
foreach ( $vkh_litters as $litter ) { if ( preg_match( '/\A([A-Z])-Wurf\z/i', $litter['bezeichnung'], $match ) ) { $vkh_last_letter = max( $vkh_last_letter, ord( strtoupper( $match[1] ) ) ); } }
$vkh_litter_state = array( 'id' => 0, 'bezeichnung' => $vkh_last_letter < 90 ? chr( $vkh_last_letter + 1 ) . '-Wurf' : 'Neuer Wurf', 'wurfdatum' => '', 'welpen' => array_fill( 0, 3, array( 'name' => '', 'geschlecht' => '', 'verkaufsstatus' => 'nein' ) ) );
$vkh_edit_id = isset( $_GET['edit'] ) && is_scalar( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
if ( isset( $GLOBALS['vkh_litter_input']['id'] ) && is_scalar( $GLOBALS['vkh_litter_input']['id'] ) && ctype_digit( (string) $GLOBALS['vkh_litter_input']['id'] ) ) {
    $vkh_edit_id = absint( $GLOBALS['vkh_litter_input']['id'] );
}
if ( isset( $_GET['edit'] ) || $vkh_edit_id ) {
    $vkh_current_litter = null;
    foreach ( $vkh_litters as $candidate ) { if ( (int) $candidate['id'] === $vkh_edit_id ) { $vkh_current_litter = $candidate; break; } }
    if ( ! $vkh_current_litter ) {
        echo '<div class="notice notice-error inline"><p>Dieser Wurf wurde nicht gefunden.</p></div><p><a class="button" href="' . esc_url( $vkh_litter_url ) . '">Zurück zu den Würfen</a></p>';
        return;
    }
    $vkh_litter_state = array( 'id' => $vkh_edit_id, 'bezeichnung' => $vkh_current_litter['bezeichnung'], 'wurfdatum' => $vkh_current_litter['wurfdatum'] ?? '', 'welpen' => $vkh_current_litter['welpen'] );
}
if ( isset( $GLOBALS['vkh_litter_input'] ) ) {
    $raw = $GLOBALS['vkh_litter_input'];
    foreach ( array( 'bezeichnung', 'wurfdatum' ) as $field ) { $vkh_litter_state[ $field ] = isset( $raw[ $field ] ) && is_string( $raw[ $field ] ) ? $raw[ $field ] : ''; }
    $vkh_litter_state['welpen'] = array();
    foreach ( array_slice( is_array( $raw['welpen'] ?? null ) ? $raw['welpen'] : array(), 0, 20 ) as $row ) {
        $safe = array();
        foreach ( array( 'id', 'name', 'geschlecht', 'verkaufsstatus' ) as $field ) { $safe[ $field ] = is_array( $row ) && is_string( $row[ $field ] ?? null ) ? $row[ $field ] : ''; }
        $safe['id'] = ctype_digit( (string) $safe['id'] ) ? (int) $safe['id'] : 0;
        $vkh_litter_state['welpen'][] = $safe;
    }
    if ( ! $vkh_litter_state['welpen'] ) { $vkh_litter_state['welpen'][] = array( 'name' => '', 'geschlecht' => '', 'verkaufsstatus' => 'nein' ); }
}
$vkh_render_puppy_row = static function( $index, $row ) {
    ?>
    <tr><td><input type="hidden" name="welpen[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $row['id'] ?? 0 ); ?>" data-vkh-puppy-id><input type="text" name="welpen[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $row['name'] ); ?>" maxlength="100" placeholder="Name des Welpen" aria-label="Name des Welpen" required data-vkh-puppy-name></td>
        <td><select name="welpen[<?php echo esc_attr( $index ); ?>][geschlecht]" aria-label="Geschlecht des Welpen" required data-vkh-puppy-sex><option value="">Bitte wählen</option><?php foreach ( vkh_general_puppy_sexes() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $row['geschlecht'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td>
        <td><select name="welpen[<?php echo esc_attr( $index ); ?>][verkaufsstatus]" aria-label="Verkaufsstatus des Welpen" data-vkh-puppy-status><?php foreach ( vkh_general_puppy_statuses() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $row['verkaufsstatus'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td>
        <td><button type="button" class="button" data-vkh-remove hidden>Entfernen</button></td>
    </tr>
    <?php
};
?>
<div class="vkh-litter-editor">
    <h2><?php echo $vkh_litter_state['id'] ? 'Wurf bearbeiten' : 'Neuen Wurf anlegen'; ?></h2>
    <ol class="vkh-litter-steps" aria-label="Formularschritte"><li data-vkh-step-label="0">1 · Wurf</li><li data-vkh-step-label="1">2 · Welpen</li><li data-vkh-step-label="2">3 · Prüfen</li></ol>
    <form method="post" action="<?php echo esc_url( add_query_arg( $vkh_litter_state['id'] ? 'edit' : 'new', $vkh_litter_state['id'] ?: 1, $vkh_litter_url ) ); ?>" data-vkh-litter-form>
        <?php wp_nonce_field( 'vkh_save_litter' ); ?>
        <input type="hidden" name="vkh_save_litter" value="1"><input type="hidden" name="id" value="<?php echo esc_attr( $vkh_litter_state['id'] ); ?>">
        <section data-vkh-step="0"><h2>Angaben zum Wurf</h2><div class="vkh-litter-fields">
            <label>Wurfbezeichnung *<input type="text" name="bezeichnung" value="<?php echo esc_attr( $vkh_litter_state['bezeichnung'] ); ?>" maxlength="30" required></label>
            <label>Wurfdatum (optional)<input type="date" name="wurfdatum" value="<?php echo esc_attr( $vkh_litter_state['wurfdatum'] ); ?>"></label>
        </div></section>
        <section data-vkh-step="1"><h2>Welpen eintragen</h2><p>Name, Geschlecht und Verkaufsstatus pro Welpe.</p>
            <div class="vkh-table-wrap"><table class="widefat"><thead><tr><th scope="col">Name *</th><th scope="col">Geschlecht *</th><th scope="col">Verkauft?</th><th scope="col">Aktion</th></tr></thead><tbody data-vkh-puppy-rows>
                <?php foreach ( $vkh_litter_state['welpen'] as $index => $row ) { $vkh_render_puppy_row( $index, $row ); } ?>
            </tbody></table></div>
            <button type="button" class="button" data-vkh-add hidden>Weiteren Welpen hinzufügen</button>
        </section>
        <section data-vkh-step="2" hidden><h2>Angaben prüfen</h2><p data-vkh-review-title></p><div class="vkh-table-wrap" data-vkh-review></div></section>
        <p class="vkh-litter-error" role="alert" data-vkh-litter-error <?php echo empty( $GLOBALS['vkh_litter_error'] ) ? 'hidden' : ''; ?>><?php echo esc_html( $GLOBALS['vkh_litter_error'] ?? '' ); ?></p>
        <div class="vkh-litter-actions"><a class="button" href="<?php echo esc_url( $vkh_litter_url ); ?>">Abbrechen</a><span class="vkh-spacer"></span><button type="button" class="button" data-vkh-back hidden>Zurück</button><button type="button" class="button button-primary" data-vkh-next hidden>Weiter zu den Welpen</button><button type="submit" class="button button-primary" data-vkh-submit>Wurf &amp; Welpen speichern</button></div>
    </form>
    <template data-vkh-row-template><table><tbody><?php $vkh_render_puppy_row( 0, array( 'name' => '', 'geschlecht' => '', 'verkaufsstatus' => 'nein' ) ); ?></tbody></table></template>
</div>