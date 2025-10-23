<?php
// Simple PDF generator for certificates
class SimplePDF {
    private $content = '';
    
    public function __construct() {
        $this->content = "%PDF-1.4\n";
    }
    
    public function addText($text, $x, $y, $size = 12) {
        // This is a very basic implementation
        // For production, use a proper library like TCPDF or FPDF
    }
    
    public function output($filename) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $this->content;
    }
}
