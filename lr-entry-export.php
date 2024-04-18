<?php
/**
Plugin Name: LION RITTER - PDF, XML, CSV Export
Description: Makes XML files for import to Entry software. Crates PDF invoices. Makes CSV export for VAT EU report.
Version: 1.3
Author: Alexej Bogačev
*/

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Invalid request!' );
}


use includes\DataGetter;
use includes\EntryXmlCreator;
use includes\LionCsvCreator;

$dir = dirname(__FILE__);
require_once $dir . '/vendor/autoload.php';


/*
 * $_SESSION
 */
function wpse16119876_init_session() {
    if ( ! session_id() ) {
        session_start();
    }
}
// Start session on init hook.
add_action( 'init', 'wpse16119876_init_session' );


/*
 * Invoice creating and PDF download
 */

//Create MetaBox
function wporg_add_custom_box() {

        add_meta_box(
            'wporg_box_id',               // Unique ID
            'PDF invoice download',      // Box title
            'wporg_custom_box_html',  // Content callback, must be of type callable
            'shop_order'               // Post type
        );

}
//Show MetaBox like a button
add_action( 'add_meta_boxes', 'wporg_add_custom_box' );
function wporg_custom_box_html( $post ) {

    $order = new WC_Order($post->ID);
    $order_id = trim(str_replace('#', '', $order->get_order_number()));

    echo '<a href="/wp-content/plugins/lr-entry-export/pdf_loader.php?orderId=' .  $order_id . '"  class="add_note button">Download invoice</a>';

}



//Add empty custom field "InvoiceDate" to order when order is creating
add_action( 'woocommerce_checkout_create_order', 'add_custom_field_on_placed_order', 10, 2 );
function add_custom_field_on_placed_order( $order, $data ){

        $order->update_meta_data('invoiceDate', '');

}


//Show InvoiceDate custom field value in order-edit page
add_action( 'woocommerce_admin_order_data_after_order_details', 'show_custom_order_meta_field' );

function show_custom_order_meta_field( $order ){

    $invoiceDate = $order->get_meta( 'invoiceDate' );
    if(!$invoiceDate) $invoiceDate = 'Not created!';

    echo "
            <div class='edit_address'> ".

                    woocommerce_wp_text_input( array(
                        'id' => 'invoicedate',
                        'label' => 'Invoice date:',
                        'wrapper_class' => 'form-field-wide',
                        'class' => 'date-picker',
                        'style' => 'width:100%',
                        'value' => $invoiceDate,
                        'description' => ''
                    )) .

            "</div>";

}

add_action( 'woocommerce_process_shop_order_meta', 'misha_save_general_details' );

//Update order meta field invoiceDate
function misha_save_general_details( $order_id ){

    $invoiceDate = date("d.m.Y", strtotime(wc_clean( $_POST[ 'invoicedate' ])));

    update_post_meta( $order_id, 'invoiceDate', $invoiceDate ) ;
    // wc_clean() and wc_sanitize_textarea() are WooCommerce sanitization functions

}


/*
 * Create XML file for export/import
 */

// Adding to admin order list bulk dropdown a custom action 'custom_downloads'
add_filter( 'bulk_actions-edit-shop_order', 'downloads_bulk_actions_edit_product', 20, 1 );
function downloads_bulk_actions_edit_product( $actions ) {
    $actions['xml_download'] = __( 'Download XML', 'woocommerce' );
    return $actions;
}

// Make the action from selected orders
add_filter( 'handle_bulk_actions-edit-shop_order', 'downloads_handle_bulk_action_edit_shop_order', 10, 3 );
function downloads_handle_bulk_action_edit_shop_order( $redirect_to, $action, $post_ids ) {
    if ( $action !== 'xml_download' )
        return $redirect_to; // Exit

    $processed_ids = array();

    foreach ( $post_ids as $post_id ) {

        $processed_ids[] = $post_id;
    }

    $data = new DataGetter();
    $ordersData = $data->getXMLData($processed_ids);

    if(!$ordersData){
        $_SESSION['lr_entry_export']['success'] = false;
        $_SESSION['lr_entry_export']['exported'] = true;
        return $redirect_to;
    }

    $xml = new EntryXmlCreator($ordersData);
    $export = $xml->createXml();

    if($export) {

        $_SESSION['lr_entry_export']['success'] = true;
        $_SESSION['lr_entry_export']['export_type'] = 'xml';
        $_SESSION['lr_entry_export']['data'] = $export; //comment on testing
        //$_SESSION['lr_entry_export']['data'] = $ordersData; //uncomment on testing
        $_SESSION['lr_entry_export']['count'] = count($processed_ids);

    } else {

        $_SESSION['lr_entry_export']['success'] = false;
        $_SESSION['error'] = 'Ошибка при генерировании XML! Обратитесь к разработчикам!';
    }

    $_SESSION['lr_entry_export']['exported'] = true;


    return $redirect_to;

}



/*
 * Create CSV file for VAT EU Report
 */

// Adding to admin order list bulk dropdown a custom action 'custom_downloads'
add_filter( 'bulk_actions-edit-shop_order', 'downloads_bulk_actions_csv_report', 20, 1 );
function downloads_bulk_actions_csv_report( $actions ) {
    $actions['csv_download'] = __( 'Download CSV', 'woocommerce' );
    return $actions;
}

// Make the action from selected orders
add_filter( 'handle_bulk_actions-edit-shop_order', 'downloads_handle_bulk_action_csv_report_download', 10, 3 );
function downloads_handle_bulk_action_csv_report_download( $redirect_to, $action, $post_ids ) {
    if ( $action !== 'csv_download' )
        return $redirect_to; // Exit


    $processed_ids = array();

    foreach ( $post_ids as $post_id ) {

        $processed_ids[] = $post_id;
    }

    $data = new DataGetter();
    $ordersData = $data->getCSVData($processed_ids);

    if(!$ordersData){
        $_SESSION['lr_entry_export']['success'] = false;
        $_SESSION['lr_entry_export']['exported'] = true;
        return $redirect_to;
    }

    $csv = new LionCsvCreator();
    $csvData = $csv->createCsv($ordersData);


    if($csvData) {

        $_SESSION['lr_entry_export']['success'] = true;
        $_SESSION['lr_entry_export']['export_type'] = 'csv';
        $_SESSION['lr_entry_export']['data'] = $csvData; //comment on testing
        //$_SESSION['lr_entry_export']['data'] = $ordersData; //uncomment on testing
        $_SESSION['lr_entry_export']['count'] = count($processed_ids);

    } else {

        $_SESSION['lr_entry_export']['success'] = false;
        $_SESSION['error'] = 'Ошибка при генерировании CSV! Обратитесь к разработчикам!';
    }

    $_SESSION['lr_entry_export']['exported'] = true;


    return $redirect_to;

}

// The results notice from bulk action on orders
add_action( 'admin_notices', 'downloads_bulk_action_admin_notice' );
function downloads_bulk_action_admin_notice()
{

    if(!isset($_SESSION['lr_entry_export']['exported'])) return;

    if(($_SESSION['lr_entry_export']['success'])){

        $exportType = $_SESSION['lr_entry_export']['export_type'];

        echo('<div id="message" class="updated fade">
                <p>' . '<a href="' . $dir .'/wp-content/plugins/lr-entry-export/' . $exportType . '_loader.php">Download '. strtoupper($exportType) . '</a></p>
                <p>' . $_SESSION['lr_entry_export']['count'] . ' orders were processed.</p>  
              </div>');

    } else {

        echo ('<div id="message" class="updated fade">
                    <p>Error! Please try again later!</p>
                    <p>'. $_SESSION['error'] .'</p>
              </div>');

    }

    unset($_SESSION['lr_entry_export']['exported']);

}


//Add additional column with totalPriceInclVat into total order table
//Header
add_action( 'woocommerce_admin_order_item_headers', 'total_admin_order_item_headers' );

function total_admin_order_item_headers( $order ) {
    echo '<th class="total_incl_vat sortable" data-sort="int">Total inc. VAT</th>';
}

//Values
add_action( 'woocommerce_admin_order_item_values', 'total_admin_order_item_values', 9999, 3 );

function total_admin_order_item_values( $product, $item, $item_id ) {
    //Items
    if ( $product ) {

        $totalExlVat = $item->get_total();
        $vat = $item->get_total_tax();
        $totalInclVat = round(($totalExlVat + $vat), 2);
        echo '<td class="" width="1%"><div class="view">' . $totalInclVat . ' ' . get_woocommerce_currency_symbol() . '</div></td>';
    }

    //Shipping or fees
    if (!$product) {
        $priceExlVat = $item->get_total();
        $vat = $item->get_total_tax();
        $priceInclVat = round(($priceExlVat + $vat),2);

        echo '<td class="" width="1%"><div class="view">' . $priceInclVat . ' ' . get_woocommerce_currency_symbol() . '</div></td>';

    }





}