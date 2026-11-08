<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class VKH_General_Signup_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct( 'vkh_signup', 'VKH: Interessenten-Anmeldung', array( 'description' => 'Formular für Informationsanfragen zu Hunden und Würfen.' ) );
    }
    public function widget( $args, $instance ) {
        echo $args['before_widget'];
        echo vkh_general_signup_form();
        echo $args['after_widget'];
    }
    public function form( $instance ) {
        echo '<p>Zeigt das Anmeldeformular für Interessenten an. Alternativ kannst du den Shortcode <code>[vkh_interessenten]</code> verwenden.</p>';
    }
}