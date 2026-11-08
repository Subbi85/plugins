<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function vkh_general_export_access( $id, $nonce ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return new WP_Error( 'forbidden', 'Keine Berechtigung zum Exportieren.' );
    }
    if ( ! $id || ! wp_verify_nonce( $nonce, 'vkh_export_dog_' . $id ) ) {
        return new WP_Error( 'forbidden', 'Der Export-Link ist ungültig oder abgelaufen. Bitte die Hundeübersicht neu laden.' );
    }
    return true;
}

function vkh_general_pdf_dog_data( $id ) {
    global $wpdb;
    $p = str_replace( '`', '``', $wpdb->prefix ) . 'vkh_';
    $previous = $wpdb->suppress_errors( true );
    try {
        $dog = $wpdb->get_row( $wpdb->prepare( "SELECT h.*, b.befund AS huefte FROM `{$p}hund` h LEFT JOIN `{$p}huefte` b ON b.id=h.huefte_id WHERE h.id=%d", $id ) );
        if ( $wpdb->last_error ) { throw new RuntimeException(); }
        if ( ! $dog ) { return new WP_Error( 'missing', 'Dieser Hund wurde nicht gefunden.' ); }
        foreach ( array( 'championtitel' => 'titel', 'jlpp_huu' => 'jlpp_huu' ) as $table => $column ) {
            $values = $wpdb->get_col( $wpdb->prepare(
                "SELECT b.`{$column}` FROM `{$p}{$table}` b INNER JOIN `{$p}hund_{$table}` r ON r.`{$table}_id`=b.id WHERE r.hund_id=%d ORDER BY b.`{$column}`", $id
            ) );
            if ( $wpdb->last_error ) { throw new RuntimeException(); }
            $dog->$column = implode( ', ', $values );
        }
        return $dog;
    } catch ( Throwable $error ) {
        $reference = vkh_general_log_error( 'dog_pdf_data_failed' );
        return new WP_Error( 'database', 'Die Hundedaten konnten nicht geladen werden. Fehlerreferenz: ' . $reference );
    } finally {
        $wpdb->suppress_errors( $previous );
    }
}

/** Only raster files inside the real uploads directory may enter the PDF parser. */
function vkh_general_pdf_photo( $id ) {
    if ( ! $id || 'attachment' !== get_post_type( $id ) || 'trash' === get_post_status( $id ) || ! current_user_can( 'read_post', $id ) ) { return false; }
    $uploads = wp_get_upload_dir();
    $base = realpath( $uploads['basedir'] );
    $file = get_attached_file( $id, true );
    $path = $file ? realpath( $file ) : false;
    if ( ! $base || ! $path || ! str_starts_with( wp_normalize_path( $path ), trailingslashit( wp_normalize_path( $base ) ) ) ) { return false; }
    if ( filesize( $path ) > 15 * 1024 * 1024 ) { return false; }
    $size = @getimagesize( $path );
    if ( ! $size || $size[0] * $size[1] > 30000000 || ! in_array( $size[2], array( IMAGETYPE_JPEG, IMAGETYPE_PNG ), true ) ) { return false; }
    return array( 'path' => $path, 'width' => $size[0], 'height' => $size[1], 'type' => IMAGETYPE_JPEG === $size[2] ? 'JPG' : 'PNG' );
}

/** User data is plain UTF-8 text; never HTML or a resource URL for the renderer. */
function vkh_general_build_dog_pdf( $dog ) {
    try {
        require_once dirname( __DIR__ ) . '/vendor/autoload.php';
        require_once __DIR__ . '/pdf-document.php';
        $pdf = new VKH_General_Dog_PDF();
        $pdf->SetTitle( 'Hundedaten - ' . $dog->name, true );
        $pdf->SetAuthor( 'VKH General' );
        $pdf->AddPage();
        $pdf->SetFont( 'DejaVu', 'B', 21 );
        $pdf->SetTextColor( 25, 45, 62 );
        $pdf->MultiCell( 0, 10, $pdf->plain( $dog->name ) );
        $pdf->Ln( 4 );
        $photo = vkh_general_pdf_photo( $dog->foto_id ?? 0 );
        if ( $photo ) {
            $scale = min( 90 / $photo['width'], 65 / $photo['height'] );
            $height = $photo['height'] * $scale;
            $pdf->Image( $photo['path'], 20, $pdf->GetY(), $photo['width'] * $scale, $height, $photo['type'] );
            $pdf->SetY( $pdf->GetY() + $height + 7 );
        } elseif ( ! empty( $dog->foto_id ) ) {
            $pdf->field( 'Foto', 'Das hinterlegte Foto konnte nicht eingebettet werden (lokales JPEG oder PNG erforderlich).' );
        }
        $birth = 'Nicht hinterlegt';
        if ( ! empty( $dog->geburtsdatum ) ) {
            $date = DateTimeImmutable::createFromFormat( '!Y-m-d', $dog->geburtsdatum );
            if ( $date && $date->format( 'Y-m-d' ) === $dog->geburtsdatum ) { $birth = $date->format( 'd.m.Y' ); }
        }
        $pdf->field( 'Geburtsdatum', $birth );
        $pdf->field( 'Championtitel', $dog->titel ?? '' );
        $pdf->field( 'JLPP / HUU', $dog->jlpp_huu ?? '' );
        $pdf->field( 'Hüfte', $dog->huefte ?? '' );
        $pdf->field( 'Verstorben', empty( $dog->verstorben ) ? 'Nein' : 'Ja' );
        $pdf->field( 'Bemerkungen', $dog->bemerkungen ?? '' );
        $pedigree = absint( $dog->ahnentafel_id ?? 0 );
        $url = $pedigree && 'attachment' === get_post_type( $pedigree ) && 'trash' !== get_post_status( $pedigree ) && current_user_can( 'read_post', $pedigree ) ? wp_get_attachment_url( $pedigree ) : false;
        $url = $url ? esc_url_raw( $url, array( 'https', 'http' ) ) : '';
        $pdf->field( 'Ahnentafel', $url ? 'Hinterlegte Ahnentafel öffnen' : 'Nicht hinterlegt', $url );
        return $pdf->Output( 'S' );
    } catch ( Throwable $error ) {
        $reference = vkh_general_log_error( 'dog_pdf_render_failed' );
        return new WP_Error( 'pdf', 'Der PDF-Export ist fehlgeschlagen. Fehlerreferenz: ' . $reference );
    }
}

add_action( 'admin_post_vkh_export_dog_pdf', 'vkh_general_export_dog_pdf' );
function vkh_general_export_dog_pdf() {
    $id = isset( $_GET['dog_id'] ) && is_scalar( $_GET['dog_id'] ) ? absint( $_GET['dog_id'] ) : 0;
    $nonce = isset( $_GET['_wpnonce'] ) && is_scalar( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    $access = vkh_general_export_access( $id, $nonce );
    if ( is_wp_error( $access ) ) { wp_die( esc_html( $access->get_error_message() ), '', array( 'response' => 403 ) ); }
    $dog = vkh_general_pdf_dog_data( $id );
    if ( is_wp_error( $dog ) ) { wp_die( esc_html( $dog->get_error_message() ), '', array( 'response' => 'missing' === $dog->get_error_code() ? 404 : 500 ) ); }
    $bytes = vkh_general_build_dog_pdf( $dog );
    if ( is_wp_error( $bytes ) ) { wp_die( esc_html( $bytes->get_error_message() ), '', array( 'response' => 500 ) ); }
    if ( headers_sent() ) { wp_die( 'Der Download konnte nicht gestartet werden. Bitte erneut versuchen.' ); }
    nocache_headers();
    header( 'Content-Type: application/pdf' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( 'hund-' . sanitize_title( $dog->name ) . '.pdf' ) . '"' );
    header( 'Content-Length: ' . strlen( $bytes ) );
    echo $bytes;
    exit;
}