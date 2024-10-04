<?php


/**
 * Class Wc_Rw_Order_Data_Export_Settings
 * Manages the settings for the WooCommerce RW Order Data Export plugin.
 */
class Wc_Rw_Order_Data_Export_Settings {

    public function __construct()
    {
        add_action( 'admin_menu', [$this, 'wc_rw_register_settings_page'] );
        add_action( 'admin_init', [$this, 'wc_rw_register_settings'] );
    }

    /**
     * Registers a settings page for the plugin without adding it to the admin menu.
     */
    public function wc_rw_register_settings_page() : void {
        add_options_page(
            __('Wc Rw Order Data Export Settings', 'wc-rw-order-data-export'), // Settings page title
            '', // don't add to menu
            'manage_options', // permission
            'wc-rw-order-data-export', // settings page URL
            [$this, 'wc_rw_render_settings_page']
        );
    }

    /**
     * Registers all settings fields for the plugin.
     * It registers settings for altering shipping and COD names and company data (bank details).
     */
    public function wc_rw_register_settings() : void {
        register_setting( 'wc_rw_order_data_export_settings', 'wc_rw_order_data_export_shipping_alter_name' );
        register_setting( 'wc_rw_order_data_export_settings', 'wc_rw_order_data_export_cod_alter_name' );

        $this->wc_rw_register_company_data_settings();

        add_settings_section(
            'wc_rw_order_data_export_section_invoice_settings',
            __('Invoice Settings', 'wc-rw-order-data-export'),
            '',
            'wc-rw-order-data-export'
        );

        add_settings_field(
            'wc_rw_order_data_export_shipping_alter_name',
            __('Shipping alternative name:', 'wc-rw-order-data-export'),
            [$this, 'wc_rw_render_settings_field'],
            'wc-rw-order-data-export',
            'wc_rw_order_data_export_section_invoice_settings',
            array('input_name' => 'shipping_alter_name')

        );
        add_settings_field(
            'wc_rw_order_data_export_cod_alter_name',
            __('Cash on delivery alternative name:', 'wc-rw-order-data-export'),
            [$this, 'wc_rw_render_settings_field'],
            'wc-rw-order-data-export',
            'wc_rw_order_data_export_section_invoice_settings',
            array('input_name' => 'cod_alter_name')

        );
    }

    /**
     * Registers company data (bank details) settings.
     */
    private function wc_rw_register_company_data_settings() : void {

        register_setting( 'wc_rw_order_data_export_settings', 'wc_rw_order_data_export_bank_iban' );
        register_setting( 'wc_rw_order_data_export_settings', 'wc_rw_order_data_export_bank_swift' );
        register_setting( 'wc_rw_order_data_export_settings', 'wc_rw_order_data_export_bank_name' );
        register_setting( 'wc_rw_order_data_export_settings', 'wc_rw_order_data_export_bank_street' );
        register_setting( 'wc_rw_order_data_export_settings', 'wc_rw_order_data_export_bank_city' );
        register_setting( 'wc_rw_order_data_export_settings', 'wc_rw_order_data_export_bank_zip' );
        register_setting( 'wc_rw_order_data_export_settings', 'wc_rw_order_data_export_bank_country' );

        add_settings_section(
            'wc_rw_order_data_export_section_company_data',
            __('Company data', 'wc-rw-order-data-export'),
            '',
            'wc-rw-order-data-export'
        );

        add_settings_field(
            'wc_rw_order_data_export_bank_iban',
            __('IBAN:', 'wc-rw-order-data-export'),
            [$this, 'wc_rw_render_settings_field'],
            'wc-rw-order-data-export',
            'wc_rw_order_data_export_section_company_data',
            array('input_name' => 'bank_iban')

        );

        add_settings_field(
            'wc_rw_order_data_export_bank_swift',
            __('SWIFT/BIC:', 'wc-rw-order-data-export'),
            [$this, 'wc_rw_render_settings_field'],
            'wc-rw-order-data-export',
            'wc_rw_order_data_export_section_company_data',
            array('input_name' => 'bank_swift')

        );

        add_settings_field(
            'wc_rw_order_data_export_bank_name',
            __('Bank name:', 'wc-rw-order-data-export'),
            [$this, 'wc_rw_render_settings_field'],
            'wc-rw-order-data-export',
            'wc_rw_order_data_export_section_company_data',
            array('input_name' => 'bank_name')

        );

        add_settings_field(
            'wc_rw_order_data_export_bank_street',
            __('Bank street:', 'wc-rw-order-data-export'),
            [$this, 'wc_rw_render_settings_field'],
            'wc-rw-order-data-export',
            'wc_rw_order_data_export_section_company_data',
            array('input_name' => 'bank_street')

        );

        add_settings_field(
            'wc_rw_order_data_export_bank_city',
            __('Bank city:', 'wc-rw-order-data-export'),
            [$this, 'wc_rw_render_settings_field'],
            'wc-rw-order-data-export',
            'wc_rw_order_data_export_section_company_data',
            array('input_name' => 'bank_city')

        );

        add_settings_field(
            'wc_rw_order_data_export_bank_zip',
            __('ZIP:', 'wc-rw-order-data-export'),
            [$this, 'wc_rw_render_settings_field'],
            'wc-rw-order-data-export',
            'wc_rw_order_data_export_section_company_data',
            array('input_name' => 'bank_zip')

        );

        add_settings_field(
            'wc_rw_order_data_export_bank_country',
            __('Bank country:', 'wc-rw-order-data-export'),
            [$this, 'wc_rw_render_settings_field'],
            'wc-rw-order-data-export',
            'wc_rw_order_data_export_section_company_data',
            array('input_name' => 'bank_country')

        );

    }



    /**
     * Renders the settings page for the plugin.
     */
    public function wc_rw_render_settings_page() : void {
        ?>
        <div class="wrap">
            <h1><?php _e('WooCommerce RW Order Data Export Settings', 'wc-rw-order-data-export'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wc_rw_order_data_export_settings' );
                do_settings_sections( 'wc-rw-order-data-export' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }


    /**
     * Renders the settings field input for the settings page.
     *
     * @param array $args Arguments containing the input name.
     */
    public function wc_rw_render_settings_field(array $args) : void {
        $input_name = $args['input_name'] ?? '';
        $value = get_option( 'wc_rw_order_data_export_' .  $input_name, '' );
        echo '<input type="text" name="wc_rw_order_data_export_'. $input_name .'" value="' . esc_attr( $value ) . '" />';
    }


}