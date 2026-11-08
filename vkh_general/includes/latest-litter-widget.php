<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class VKH_General_Latest_Litter_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct( 'vkh_latest_litter', 'VKH: Aktueller Wurf', array( 'description' => 'Welpen des neuesten Wurfs mit Ampelstatus. Überschrift nur bei verfügbaren Welpen.' ) );
    }
    public function widget( $args, $instance ) {
        $html = vkh_general_render_latest_litter( $args['before_title'] ?? '<h2>', $args['after_title'] ?? '</h2>' );
        if ( ! $html ) { return; }
        echo ( $args['before_widget'] ?? '' ) . $html . ( $args['after_widget'] ?? '' );
    }
    public function form( $instance ) {
        echo '<p>Zeigt automatisch den neuesten Wurf nach Wurfdatum, ersatzweise Anlagedatum. Die Überschrift erscheint nur, wenn mindestens ein Welpe den Status „Nein“ hat.</p>';
    }
}