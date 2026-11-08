<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class VKH_General_Dog_PDF extends tFPDF {
    public function __construct() {
        parent::__construct( 'P', 'mm', 'A4' );
        $this->SetMargins( 20, 25, 20 );
        $this->SetAutoPageBreak( true, 22 );
        $this->AddFont( 'DejaVu', '', 'DejaVuSans.ttf', true );
        $this->AddFont( 'DejaVu', 'B', 'DejaVuSans-Bold.ttf', true );
    }
    public function Header() {
        $this->SetY( 12 );
        $this->SetFont( 'DejaVu', '', 9 );
        $this->SetTextColor( 90, 105, 115 );
        $this->Cell( 0, 5, 'VKH  /  HUNDEDATEN', 0, 1 );
        $this->SetDrawColor( 215, 223, 228 );
        $this->Line( 20, 20, 190, 20 );
        $this->SetY( 27 );
    }
    public function Footer() {
        $this->SetY( -16 );
        $this->SetFont( 'DejaVu', '', 8 );
        $this->SetTextColor( 100, 110, 120 );
        $this->Cell( 140, 5, 'Erstellt am ' . wp_date( 'd.m.Y' ) );
        $this->Cell( 30, 5, 'Seite ' . $this->PageNo(), 0, 0, 'R' );
    }
    public function plain( $value ) {
        return preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', str_replace( array( "\r\n", "\r" ), "\n", (string) $value ) );
    }
    public function field( $label, $value, $link = '' ) {
        if ( $this->GetY() > 252 ) { $this->AddPage(); }
        $this->SetFont( 'DejaVu', 'B', 9 );
        $this->SetTextColor( 85, 100, 112 );
        $this->Cell( 0, 6, $label, 0, 1 );
        $this->SetFont( 'DejaVu', '', 11 );
        $this->SetTextColor( 30, 45, 55 );
        $text = '' === trim( (string) $value ) ? 'Nicht hinterlegt' : $this->plain( $value );
        if ( $link ) {
            $this->SetTextColor( 25, 95, 140 );
            $this->Write( 6, $text, $link );
            $this->Ln( 6 );
        } else { $this->MultiCell( 0, 6, $text ); }
        $this->Ln( 3 );
    }
}