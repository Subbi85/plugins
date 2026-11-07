<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$vkh_prefix = str_replace( '`', '``', $wpdb->prefix ) . 'vkh_';
$vkh_previous_suppression = $wpdb->suppress_errors( true );
$vkh_dogs = $wpdb->get_results(
    "SELECT hund.*, huefte.befund AS huefte,
        (SELECT GROUP_CONCAT(DISTINCT titel.titel ORDER BY titel.titel SEPARATOR ', ')
         FROM `{$vkh_prefix}hund_championtitel` AS zuordnung
         INNER JOIN `{$vkh_prefix}championtitel` AS titel ON titel.id = zuordnung.championtitel_id
         WHERE zuordnung.hund_id = hund.id) AS titel,
        (SELECT GROUP_CONCAT(DISTINCT befund.jlpp_huu ORDER BY befund.jlpp_huu SEPARATOR ', ')
         FROM `{$vkh_prefix}hund_jlpp_huu` AS zuordnung
         INNER JOIN `{$vkh_prefix}jlpp_huu` AS befund ON befund.id = zuordnung.jlpp_huu_id
         WHERE zuordnung.hund_id = hund.id) AS jlpp_huu
     FROM `{$vkh_prefix}hund` AS hund
     LEFT JOIN `{$vkh_prefix}huefte` AS huefte ON huefte.id = hund.huefte_id
     ORDER BY hund.name ASC, hund.id ASC"
);
$vkh_query_failed = '' !== $wpdb->last_error;
if ( $vkh_query_failed ) { vkh_general_log_error( 'dog_list_failed' ); }
$vkh_query_failed = $vkh_query_failed || VKH_GENERAL_DB_VERSION !== get_option( 'vkh_general_db_version' );
$wpdb->suppress_errors( $vkh_previous_suppression );
$vkh_is_demo = ! $vkh_query_failed && empty( $vkh_dogs );

if ( $vkh_is_demo ) {
    $vkh_dogs = array(
        (object) array(
            'id'           => null,
            'name'         => 'Balu vom Beispielhof',
            'geburtsdatum' => '2022-05-14',
            'titel'        => 'Deutscher Jugendchampion (Beispiel)',
            'jlpp_huu'     => 'JLPP: frei, HUU: frei (Beispiel)',
            'huefte'       => 'HD A (Beispiel)',
            'bemerkungen'  => 'Dies ist ein Dummy-Hund zur Vorschau. Die Daten sind nicht in der Datenbank gespeichert.',
            'verstorben'   => 0,
        ),
    );
}
?>
<style>
    .vkh-dogs { max-width: 1000px; margin-top: 20px; }
    .vkh-dogs details { margin-bottom: 12px; border: 1px solid #c3c4c7; border-radius: 4px; background: #fff; }
    .vkh-dogs summary { padding: 16px 20px; cursor: pointer; font-size: 15px; font-weight: 600; }
    .vkh-dogs summary:hover { background: #f6f7f7; }
    .vkh-dogs summary:focus-visible { outline: 2px solid #2271b1; outline-offset: 2px; }
    .vkh-dogs details[open] summary { border-bottom: 1px solid #dcdcde; }
    .vkh-dogs .vkh-dog-info { display: grid; grid-template-columns: minmax(120px, 180px) minmax(0, 1fr); gap: 12px 20px; margin: 0; padding: 20px; }
    .vkh-dogs dt { font-weight: 600; }
    .vkh-dogs dd { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; }
    .vkh-dogs .vkh-demo-label { margin-left: 8px; color: #646970; font-size: 12px; font-weight: 400; }
    @media (max-width: 600px) {
        .vkh-dogs .vkh-dog-info { grid-template-columns: 1fr; gap: 6px; }
        .vkh-dogs dd { margin-bottom: 10px; }
    }
</style>
<div class="wrap">
    <h1><?php esc_html_e( 'Unsere Hunde', 'vkh-general' ); ?></h1>
    <?php if ( ! $vkh_query_failed ) { require __DIR__ . '/dog-form.php'; } ?>
    <?php if ( $vkh_query_failed ) : ?>
        <div class="notice notice-error inline"><p><?php esc_html_e( 'Die Hunde konnten nicht geladen werden. Bitte prüfe die Datenbankverbindung und ob die Plugin-Tabellen durch erneutes Aktivieren angelegt wurden.', 'vkh-general' ); ?></p></div>
    <?php else : ?>
        <?php if ( $vkh_is_demo ) : ?>
            <p><?php esc_html_e( 'Es sind noch keine Hunde angelegt. Hier siehst du einen Dummy-Eintrag zur Vorschau.', 'vkh-general' ); ?></p>
        <?php endif; ?>
        <div class="vkh-dogs">
            <?php foreach ( $vkh_dogs as $vkh_dog ) : ?>
                <?php
                $vkh_birthdate = '—';
                if ( ! empty( $vkh_dog->geburtsdatum ) && '0000-00-00' !== $vkh_dog->geburtsdatum ) {
                    $vkh_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $vkh_dog->geburtsdatum );
                    if ( $vkh_date && $vkh_date->format( 'Y-m-d' ) === $vkh_dog->geburtsdatum ) {
                        $vkh_birthdate = $vkh_date->format( 'd.m.Y' );
                    }
                }
                $vkh_fields = array(
                    'Name'         => $vkh_dog->name,
                    'Geburtsdatum' => $vkh_birthdate,
                    'Titel'        => $vkh_dog->titel,
                    'JLPP / HUU'   => $vkh_dog->jlpp_huu,
                    'Hüfte'        => $vkh_dog->huefte,
                    'Bemerkungen'  => $vkh_dog->bemerkungen,
                    'Verstorben'   => $vkh_dog->verstorben ? 'Ja' : 'Nein',
                );
                ?>
                <details name="vkh-dog">
                    <summary><?php echo esc_html( $vkh_dog->name ); ?><?php if ( $vkh_is_demo ) : ?><span class="vkh-demo-label"><?php esc_html_e( 'Dummy', 'vkh-general' ); ?></span><?php endif; ?></summary>
                    <div style="padding:16px 20px 0">
                        <?php if ( ! empty( $vkh_dog->foto_id ) && 'trash' !== get_post_status( $vkh_dog->foto_id ) ) : ?>
                            <?php echo wp_get_attachment_image( $vkh_dog->foto_id, 'medium', false, array( 'style' => 'max-width:100%;height:auto;border-radius:4px' ) ); ?>
                        <?php endif; ?>
                        <?php if ( ! empty( $vkh_dog->ahnentafel_id ) && 'trash' !== get_post_status( $vkh_dog->ahnentafel_id ) && wp_get_attachment_url( $vkh_dog->ahnentafel_id ) ) : ?>
                            <p><a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( wp_get_attachment_url( $vkh_dog->ahnentafel_id ) ); ?>">Ahnentafel öffnen</a></p>
                        <?php endif; ?>
                    </div>
                    <dl class="vkh-dog-info">
                        <?php foreach ( $vkh_fields as $vkh_label => $vkh_value ) : ?>
                            <dt><?php echo esc_html( $vkh_label ); ?></dt>
                            <dd><?php echo esc_html( null !== $vkh_value && '' !== (string) $vkh_value ? (string) $vkh_value : '—' ); ?></dd>
                        <?php endforeach; ?>
                    </dl>
                    <?php if ( ! $vkh_is_demo && ! $vkh_editing ) : ?>
                        <p style="padding:0 20px 16px;margin:0"><a class="button" href="<?php echo esc_url( add_query_arg( 'edit', absint( $vkh_dog->id ), $vkh_base_url ) ); ?>">Hund bearbeiten</a></p>
                    <?php endif; ?>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
