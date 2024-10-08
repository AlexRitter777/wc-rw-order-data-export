<?php


/**
 * Main class for initializing the plugin's functionality.
 */
class Wc_Rw_Order_Data_Export_Init{

    /**
     * @var string $plugin_url URL path to the plugin directory.
     */
    private string $plugin_url;

    /**
     * Wc_Rw_Order_Data_Export_Init constructor.
     * Initializes modules and adds necessary hooks and filters.
     */
    public function __construct(){
        // Set plugin URL for enqueuing assets
        $this->plugin_url = plugin_dir_url(__FILE__) . '../';

        // Load the required modules based on WooCommerce settings
        $this->wc_rw_require_plugin_modules();

        // Add VAT total column to the WooCommerce admin order view
        $this->wc_rw_add_vat_column_to_admin_order();

        // Create a settings link in the plugin list
        add_filter( "plugin_action_links", [$this, 'wc_rw_create_settings_link'], 10, 2);

        // Initialize settings
        $this->wc_rw_init_settings();

    }


    /**
     * Adds a settings link to the plugins page.
     *
     * @param array $plugin_actions Existing plugin actions.
     * @param string $plugin_file The current plugin file.
     * @return array Modified plugin actions.
     */
    public function wc_rw_create_settings_link ($plugin_actions, $plugin_file){

        $new_actions = array();

        if ( plugin_basename( dirname( __DIR__ )) . '/wc-rw-order-data-export.php' === $plugin_file ) {
            $new_actions['cl_settings'] = sprintf(  '<a href="%s">'. __('Settings', 'wc-rw-order-data-export' ) . '</a>',  esc_url( admin_url( 'options-general.php?page=wc-rw-order-data-export' ) ) );
        }

        return array_merge( $new_actions, $plugin_actions );

    }

    /**
     * Initialize the settings for the plugin.
     */
    private function wc_rw_init_settings() {
        new Wc_Rw_Order_Data_Export_Settings();
    }


    /**
     * Checks if WooCommerce taxes are enabled.
     *
     * @return bool True if taxes are enabled, false otherwise.
     */
    private function wc_rw_check_woocommerce_taxes_state(): bool
    {
        return get_option('woocommerce_calc_taxes') === 'yes';
    }

    /**
     * Loads necessary modules based on WooCommerce tax settings.
     */
    private function wc_rw_require_plugin_modules(){

        $taxState = $this->wc_rw_check_woocommerce_taxes_state();


        if($taxState) {

            $this->wc_rw_start_pdf_invoices_module($taxState);

            $this->wc_rw_start_xml_export_module();

            $this->wc_rw_start_csv_export_module();

        } else {

            $this->wc_rw_start_pdf_invoices_module($taxState);

        }

    }

    /**
     * Starts the PDF Invoices module and enqueues necessary assets.
     *
     * @param bool $taxState True if taxes are enabled, false otherwise.
     */
    private function wc_rw_start_pdf_invoices_module($taxState){

        add_action('admin_enqueue_scripts', [$this, 'wc_rw_load_pdf_invoices_scripts_and_styles']);

        new Wc_Rw_Order_Data_Export_Pdf_Invoices($taxState);


    }


    /**
     * Enqueues scripts and styles for the PDF Invoices module.
     *
     * @param string $hook The current admin page hook.
     */
    public function wc_rw_load_pdf_invoices_scripts_and_styles($hook){

        // Only load scripts and styles on specific admin pages
        if ( 'post.php' != $hook && 'post-new.php' != $hook ) {
            return;
        }

        wp_enqueue_script(
            'wc-rw-order-data-export-pdf-invoices-script',
            $this->plugin_url . '/modules/pdf-invoices/assets/js/invoice.js',
            array('jquery'),
            Wc_Rw_Order_Data_Export::VERSION ,
            true
        );

         wp_enqueue_style(
            'wc-rw-order-data-export-pdf-invoices-style',
            $this->plugin_url . '/modules/pdf-invoices/assets/css/style.css',
            array(),
             Wc_Rw_Order_Data_Export::VERSION
        );

    }

    /**
     * Starts the XML Export module and enqueues necessary assets.
     */
    private function wc_rw_start_xml_export_module(){

        add_action('admin_enqueue_scripts', [$this, 'wc_rw_load_xml_export_scripts_and_styles']);

        new Wc_Rw_Order_Data_Export_Xml_Report();


    }

    /**
     * Enqueues scripts and styles for the XML Export module.
     */
    public function wc_rw_load_xml_export_scripts_and_styles(){
        $screen = get_current_screen();
        if ( isset( $screen->id ) && 'edit-shop_order' === $screen->id ) {
            wp_enqueue_script(
                'wc-rw-order-data-export-xml-report-script',
                $this->plugin_url . '/modules/xml-export/assets/js/xml-export.js',
                array('jquery'),
                Wc_Rw_Order_Data_Export::VERSION,
                true
            );
        }
    }


    /**
     * Starts the CSV Export module and enqueues necessary assets.
     */
    private function wc_rw_start_csv_export_module(){

        add_action('admin_enqueue_scripts', [$this, 'wc_rw_load_csv_export_scripts_and_styles']);

        new Wc_Rw_Order_Data_Export_Csv_Report();

    }

    /**
     * Enqueues scripts and styles for the CSV Export module.
     */
    public function wc_rw_load_csv_export_scripts_and_styles(){

        $screen = get_current_screen();

        if ( isset( $screen->id ) && 'edit-shop_order' === $screen->id ) {
            wp_enqueue_script(
                'wc-rw-order-data-export-csv-report-script',
                $this->plugin_url . '/modules/csv-export/assets/js/csv-export.js',
                array('jquery'),
                Wc_Rw_Order_Data_Export::VERSION,
                true
            );
        }
    }

    /**
     * Adds VAT total column to the WooCommerce admin order items table.
     */
    private function wc_rw_add_vat_column_to_admin_order() {
        //Header
        add_action( 'woocommerce_admin_order_item_headers', [$this, 'wc_rw_total_admin_order_item_headers'] );
        //Values
        add_action( 'woocommerce_admin_order_item_values', [$this, 'wc_rw_total_admin_order_item_values'], 9999, 3 );

    }

    /**
     * Adds the "Total inc. VAT" header to the WooCommerce admin order items table.
     *
     * @param WC_Order $order The WooCommerce order object.
     */
    public function wc_rw_total_admin_order_item_headers( $order ) {
        echo '<th class="total_incl_vat sortable" data-sort="int">Total inc. VAT</th>';
    }


    /**
     * Displays the total including VAT for each item in the WooCommerce admin order items table.
     *
     * @param WC_Product $product The WooCommerce product object.
     * @param WC_Order_Item $item The WooCommerce order item object.
     * @param int $item_id The item ID.
     */
    public function wc_rw_total_admin_order_item_values( $product, $item, $item_id )
    {
        // Calculate the total including VAT for products
        if ($product) {

            $totalExlVat = $item->get_total();
            $vat = $item->get_total_tax();
            $totalInclVat = round(($totalExlVat + $vat), 2);
            echo '<td class="" ><div class="view">' . $totalInclVat . ' ' . get_woocommerce_currency_symbol() . '</div></td>';
        }

        // Calculate the total including VAT for shipping or fees
        if (!$product) {
            $priceExlVat = $item->get_total();
            $vat = $item->get_total_tax();
            $priceInclVat = round(($priceExlVat + $vat), 2);
            echo '<td class="" ><div class="view">' . $priceInclVat . ' ' . get_woocommerce_currency_symbol() . '</div></td>';

        }

    }



}