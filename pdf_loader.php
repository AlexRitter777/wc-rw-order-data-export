<?php

use includes\DataGetter;
use Mpdf\Mpdf;


/* Initialize Wordpress by wp-load.php */
define( 'BASE_PATH', find_wordpress_base_path()."/" );
define( 'WP_USE_THEMES', false );
//global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header;
require(BASE_PATH . 'wp-load.php');

/* Restrict access to admin only */
if(!current_user_can( 'administrator' )) die('Access denied!'); //move to another file later, not need in class!


/**
 * Looking for the WP root directory
 *
 * @return string|null
 */
function find_wordpress_base_path() {
    $dir = dirname(__FILE__);
    do {
        //it is possible to check for other files here
        if( file_exists($dir."/wp-config.php") ) {
            return $dir;

        }
    } while( $dir = realpath("$dir/..") );
    return null;
}

//Require composer autoloader
$dir = dirname(__FILE__);
require_once $dir . '/vendor/autoload.php';
require_once $dir . '/config/debug.php';

//Run DataGetter and write PDF invoice
if(isset($_GET['orderId'])) {

    /*$order = wc_get_order($_GET['orderId']);
    debug($order->get_payment_method());
    die();*/

    $orderId = $_GET['orderId'];
    $invoice = new DataGetter();
    $invoiceData = $invoice->getPDFData($orderId);

    //debug($invoiceData);die();

    if(!$invoiceData) {
        require_once "invoices_templates/invoice-error.php";
        die();
    }

    $invoiceSettings = $invoice->getClassProperty('invoice_settings');
    $invoiceLanguage = $invoiceSettings['language'];
    extract($invoiceData);

    ob_start();
    $invoiceTemplate = "invoices_templates/invoice-{$invoiceLanguage}.php";
    if(is_file($invoiceTemplate)) {
        require_once $invoiceTemplate;
        $content = ob_get_clean();

        $mPdf = new Mpdf();
        $stylesheet = file_get_contents("{$dir}/assets/css/invoice.css");
        $mPdf->WriteHTML($stylesheet, 1);
        $mPdf->WriteHTML($content, 2);

        $mPdf->Output("{$invoiceNumber}.pdf", 'D');
    } else {
        $_SESSION['error'] = "Invoice template is not found!";
        require_once "invoices_templates/invoice-error.php";
        die();
    }
} else {
    unset($_SESSION['error']);
    require_once "invoices_templates/invoice-error.php";
    die();

}





