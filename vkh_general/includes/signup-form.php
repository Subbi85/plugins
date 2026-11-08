<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_enqueue_scripts', static function() {
    $root = dirname( __DIR__ );
    wp_enqueue_style( 'vkh-signup', plugins_url( 'public/signup.css', $root . '/index.php' ), array(), filemtime( $root . '/public/signup.css' ) );
    wp_enqueue_script( 'vkh-signup', plugins_url( 'public/signup.js', $root . '/index.php' ), array(), filemtime( $root . '/public/signup.js' ), true );
} );

function vkh_general_signup_form( $attributes = array() ) {
    static $count = 0; ++$count;
    $id = wp_unique_id( 'vkh-signup-' );
    $privacy_url = get_privacy_policy_url();
    $messages = array(
        'success' => 'Danke! Deine Anfrage wurde aufgenommen. Falls deine Adresse noch nicht bestätigt ist, erhältst du eine Bestätigungs-Mail. Bitte prüfe auch deinen Spam-Ordner und bestätige die Anfrage über den Link.',
        'invalid' => 'Bitte prüfe deine Angaben und sende das Formular erneut ab.',
        'expired' => 'Bitte lade das Formular neu und fülle es noch einmal aus.',
        'consent' => 'Bitte bestätige die Einwilligung für weitere Informationen.',
        'rate' => 'Zu viele Anfragen. Bitte versuche es später erneut.',
        'unavailable' => 'Die Anmeldung ist vorübergehend nicht verfügbar. Bitte versuche es später erneut.',
    );
    $status = isset( $_GET['vkh_signup'] ) && is_string( $_GET['vkh_signup'] ) ? $_GET['vkh_signup'] : '';
    $ready = VKH_GENERAL_DB_VERSION === get_option( 'vkh_general_db_version' );
    $return = home_url( '/' . ltrim( $GLOBALS['wp']->request ?? '', '/' ) );
    ob_start();
    ?>
    <section id="<?php echo esc_attr( 1 === $count ? 'vkh-anmeldung' : $id ); ?>" class="vkh-signup" aria-labelledby="<?php echo esc_attr( $id ); ?>-title">
        <h2 id="<?php echo esc_attr( $id ); ?>-title">Weitere Infos zu unseren Hunden</h2>
        <p>Du möchtest Neues über unsere Hunde und Würfe erfahren? Hinterlasse uns deine Kontaktdaten.</p>
        <?php if ( isset( $messages[ $status ] ) ) : ?><p class="vkh-signup-message" role="status"><?php echo esc_html( $messages[ $status ] ); ?></p><?php endif; ?>
        <?php if ( get_option( 'vkh_doi_mail_error' ) ) : ?><p class="vkh-signup-message" role="status">Der E-Mail-Versand ist derzeit gestört. Gespeicherte Anfragen bleiben erhalten. Bitte versuche es später erneut oder kontaktiere uns.</p><?php endif; ?>
        <?php if ( ! $ready ) : ?><p role="status">Die Anmeldung ist vorübergehend nicht verfügbar.</p><?php else : ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-vkh-signup data-token-url="<?php echo esc_url( add_query_arg( 'action', 'vkh_signup_token', admin_url( 'admin-ajax.php' ) ) ); ?>">
            <input type="hidden" name="action" value="vkh_public_signup">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'vkh_public_signup' ) ); ?>">
            <input type="hidden" name="vkh_token" value="<?php echo esc_attr( vkh_general_signup_token( vkh_general_signup_ip() ) ); ?>">
            <input type="hidden" name="vkh_return" value="<?php echo esc_url( $return ); ?>">
            <div class="vkh-signup-trap" aria-hidden="true"><label for="<?php echo esc_attr( $id ); ?>-website">Dieses Feld bitte leer lassen</label><input id="<?php echo esc_attr( $id ); ?>-website" name="website" type="text" tabindex="-1" autocomplete="off" maxlength="100"></div>
            <div class="vkh-signup-names">
                <label for="<?php echo esc_attr( $id ); ?>-first">Vorname *<input id="<?php echo esc_attr( $id ); ?>-first" name="vorname" type="text" autocomplete="given-name" maxlength="100" required></label>
                <label for="<?php echo esc_attr( $id ); ?>-last">Nachname *<input id="<?php echo esc_attr( $id ); ?>-last" name="nachname" type="text" autocomplete="family-name" maxlength="100" required></label>
            </div>
            <label for="<?php echo esc_attr( $id ); ?>-email">E-Mail *<input id="<?php echo esc_attr( $id ); ?>-email" name="email" type="email" autocomplete="email" maxlength="254" required></label>
            <label for="<?php echo esc_attr( $id ); ?>-phone">Telefonnummer (optional)<input id="<?php echo esc_attr( $id ); ?>-phone" name="telefon" type="tel" autocomplete="tel" maxlength="50"></label>
            <label class="vkh-signup-check"><input name="hundeerfahrung" type="checkbox" value="1"> Ich habe bereits Hundeerfahrung.</label>
            <fieldset><legend>Einwilligung *</legend>
                <?php foreach ( vkh_general_signup_consents() as $key => $text ) : ?>
                    <label class="vkh-signup-check"><input name="<?php echo esc_attr( $key ); ?>" type="checkbox" value="1" data-vkh-consent required> <?php echo esc_html( $text ); ?></label>
                <?php endforeach; ?>
            </fieldset>
            <p class="vkh-signup-privacy">Wir speichern deine Angaben zur Bearbeitung deiner Anfrage. Du erhältst zunächst eine Bestätigungs-Mail. Erst nach deiner Bestätigung wird die Anfrage freigegeben.<?php if ( $privacy_url ) : ?> <a href="<?php echo esc_url( $privacy_url ); ?>">Datenschutzhinweise lesen</a>.<?php endif; ?></p>
            <button type="submit">Anfrage eintragen</button>
        </form>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}

add_action( 'admin_notices', static function() {
    if ( current_user_can( 'manage_options' ) && ! get_privacy_policy_url() && 'vkh-general-interessenten' === ( $_GET['page'] ?? '' ) ) {
        echo '<div class="notice notice-warning"><p>Bitte hinterlege vor der öffentlichen Nutzung des Anmeldeformulars unter Einstellungen → Datenschutz eine veröffentlichte Datenschutzseite mit den Angaben zu dieser Verarbeitung.</p></div>';
    }
} );