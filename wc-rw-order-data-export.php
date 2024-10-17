<?php
/**
 * Plugin Name: WooCommerce RW Order Data Export
 * Description: Creates and sends to customers PDF invoices, credit notes, and proformas. Generates XML reports for importing into Entry software. Produces CSV export reports for VAT EU declaration purposes.
 * Version: 2.1.0
 * Author: Alexej Bogačev (RAIN WOOLF s.r.o.)
 * Text Domain: wc-rw-order-data-export
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Prevent direct access
}


/**
 * Main class for the WooCommerce RW Order Data Export plugin.
 */
class Wc_Rw_Order_Data_Export
{

    const VERSION = '2.1.0';

    /**
     * Wc_Rw_Order_Data_Export constructor.
     * Initializes the plugin by registering hooks.
     */
    public function __construct()
    {
        $this->register_hooks();
    }


    /**
     * Registers all necessary hooks for the plugin.
     */
    private function register_hooks() : void
    {
        // Create the invoice folder on the plugin activation
        register_activation_hook(__FILE__, [$this,'wc_rw_create_invoice_directory']);

        // Load the text domain for translations
        add_action('plugins_loaded', [$this, 'load_textdomain']);

        // Initialize the plugin's main functionality
        add_action('plugins_loaded', [$this, 'initialize_plugin']);

        // Load scripts and styles common for entire plugin
        add_action('admin_enqueue_scripts', [$this, 'wc_rw_load_common_scripts_and_styles']);

    }


    /**
     * Initialize the plugin.
     */
    public function initialize_plugin()
    {
        $this->load_autoloader();
        new Wc_Rw_Order_Data_Export_Init();
    }



    /**
     * Load Composer autoloader.
     */
    private function load_autoloader()
    {
        if ( file_exists( WP_PLUGIN_DIR . '/wc-rw-order-data-export/vendor/autoload.php' ) ) {
            require_once WP_PLUGIN_DIR . '/wc-rw-order-data-export/vendor/autoload.php';
        }
    }



    /**
     * Load common admin scripts and styles.
     *
     * @param string $hook The current admin page hook.
     */
    public function wc_rw_load_common_scripts_and_styles($hook){

        // Only load scripts and styles on specific admin pages
        if ( 'post.php' != $hook && 'post-new.php' != $hook ) {
            return;
        }

        wp_enqueue_style(
            'wc-rw-order-data-export-common-style',
            plugins_url('/assets/css/style.css', __FILE__ ),
            array(),
            Wc_Rw_Order_Data_Export::VERSION
        );

    }


    /**
     * Load the plugin text domain for translations.
     */
    public function load_textdomain() {
        // Load the text domain from the /languages directory
        load_plugin_textdomain('wc-rw-order-data-export', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }


    /**
     * Create the invoices folder
     */
    public function wc_rw_create_invoice_directory() {
        $upload_dir = wp_upload_dir();
        $invoice_dir = $upload_dir['basedir'] . '/wc_rw_invoices';

        if ( ! file_exists( $invoice_dir ) ) {
            wp_mkdir_p( $invoice_dir );
        }
    }


}

/**
 * Initialize and return an instance of the main plugin class.
 *
 * @return Wc_Rw_Order_Data_Export
 */
function wc_rw_order_data_export(): Wc_Rw_Order_Data_Export
{
   return new Wc_Rw_Order_Data_Export();
}

// Start the plugin execution.
wc_rw_order_data_export();

