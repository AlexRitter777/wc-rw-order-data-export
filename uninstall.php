<?php

// Exit if accessed directly
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options if they were saved
delete_option('wc_rw_order_data_export_shipping_alter_name');
delete_option('wc_rw_order_data_export_cod_alter_name');
delete_option('wc_rw_order_data_export_bank_iban');
delete_option('wc_rw_order_data_export_bank_swift');
delete_option('wc_rw_order_data_export_bank_name');
delete_option('wc_rw_order_data_export_bank_street');
delete_option('wc_rw_order_data_export_bank_city');
delete_option('wc_rw_order_data_export_bank_zip');
delete_option('wc_rw_order_data_export_bank_country');

// Delete order meta (Order-specific metadata)
delete_post_meta_by_key('wc_wr_order_data_export_invoice_date');
delete_post_meta_by_key('wc_wr_order_data_export_credit_note_date');
delete_post_meta_by_key('wc_wr_order_data_export_proforma_date');

// Delete product meta (Product-specific metadata)
delete_post_meta_by_key('_wc_rw_order_data_export_alter_product_name');

// Remove the temporary directory with PDF files
$upload_dir = wp_upload_dir();
$invoice_dir = $upload_dir['basedir'] . '/wc_rw_invoices';

if (file_exists($invoice_dir)) {
    // Recursive function to delete the directory
    function wc_rw_order_data_export_delete_directory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            $item_path = $dir . '/' . $item;
            if (is_dir($item_path)) {
                wc_rw_order_data_export_delete_directory($item_path);
            } else {
                unlink($item_path);
            }
        }
        rmdir($dir);
    }

    wc_rw_order_data_export_delete_directory($invoice_dir);
}
