<?php

use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Output\Destination;

class Wc_Rw_Order_Data_Export_Pdf_Invoices
{
    /**
     * @var bool Indicates if the tax settings are enabled or not.
     */
    private bool $taxState;

    /**
     * @var string|null The path to the PDF file to be deleted after email is sent.
     */
    private ?string $pdf_to_delete = null;

    /**
     * Constructor for initializing actions, filters, and meta fields for handling PDF invoices,
     * credit notes, and proformas for WooCommerce orders.
     *
     * @param bool $taxState Whether taxes are enabled in WooCommerce.
     */
    public function __construct(bool $taxState){

        // Store tax state setting
        $this->taxState = $taxState;

        // Add metabox on the WooCommerce order page for downloading invoice, credit note and proforma PDFs
        add_action( 'add_meta_boxes', [$this, 'wc_rw_add_custom_box']);

        // Display custom invoice date field in WooCommerce order edit page
        add_action( 'woocommerce_admin_order_data_after_order_details', [$this, 'wc_rw_show_custom_order_meta_field_invoice_date'], 10, 1);

        // Display custom credit note date field in WooCommerce order edit page
        add_action( 'woocommerce_admin_order_data_after_order_details', [$this, 'wc_rw_show_custom_order_meta_field_credit_note_date'], 10, 1);

        // Display custom proforma date field in WooCommerce order edit page
        add_action( 'woocommerce_admin_order_data_after_order_details', [$this, 'wc_rw_show_custom_order_meta_field_proforma_date'], 10, 1);

        // Display custom exchange rate field in WooCommerce order edit page
        add_action( 'woocommerce_admin_order_data_after_order_details', [$this, 'wc_rw_show_custom_order_meta_field_exchange_rate'], 10, 1);

        // Display custom shipping tracking field in WooCommerce order edit page
        add_action( 'woocommerce_admin_order_data_after_order_details', [$this, 'wc_rw_show_custom_order_meta_field_shipping_tracking'], 10, 1);


        // Save custom invoice date field when saving order details
        add_action( 'woocommerce_process_shop_order_meta', [$this, 'wc_rw_save_general_details_invoice_date'], 10, 1);

        // Save custom credit note date field when saving order details
        add_action( 'woocommerce_process_shop_order_meta', [$this, 'wc_rw_save_general_details_credit_note_date'], 10, 1);

        // Save custom proforma date field when saving order details
        add_action( 'woocommerce_process_shop_order_meta', [$this, 'wc_rw_save_general_details_proforma_date'], 10, 1);

        // Save custom exchange rate field when saving order details
        add_action( 'woocommerce_process_shop_order_meta', [$this, 'wc_rw_save_general_details_order_exchange_rate'], 10, 1);

        // Save shipping tracking field when saving order details
        add_action( 'woocommerce_process_shop_order_meta', [$this, 'wc_rw_save_general_details_shipping_tracking'], 10, 1);

        // Hide custom meta fields on the order edit page (invoice date)
        add_filter('is_protected_meta', [$this, 'wc_rw_hide_invoice_date_meta_field'], 10, 2);

        // Hide custom meta fields on the order edit page (credit note date)
        add_filter('is_protected_meta', [$this, 'wc_rw_hide_credit_note_date_meta_field'], 10, 2);

        // Hide custom meta fields on the order edit page (proforma date)
        add_filter('is_protected_meta', [$this, 'wc_rw_hide_proforma_date_meta_field'], 10, 2);

        // Hide custom meta fields on the order edit page (exchange rate)
        add_filter('is_protected_meta', [$this, 'wc_rw_hide_exchange_rate_meta_field'], 10, 2);

        // Hide custom meta fields on the order edit page (shipping tracking)
        add_filter('is_protected_meta', [$this, 'wc_rw_hide_shipping_tracking_meta_field'], 10, 2);

        // Generate PDF for invoice via admin-post
        add_action('admin_post_generate_pdf_invoice', [$this, 'wc_rw_generate_pdf_invoice']);

        // Generate PDF for credit note via admin-post
        add_action('admin_post_generate_pdf_credit_note', [$this, 'wc_rw_generate_pdf_credit_note']);

        // Generate PDF for proforma via admin-post
        add_action('admin_post_generate_pdf_proforma', [$this, 'wc_rw_generate_pdf_proforma']);

        // Add a custom product field for an "alternative product name" to be shown in invoices
        add_action('woocommerce_product_options_general_product_data', [$this, 'wc_rw_add_custom_product_alter_name_field']);

        // Save the custom product field for "alternative product name" in product meta
        add_action('woocommerce_process_product_meta', [$this, 'wc_rw_save_custom_product_alter_name_field']);

        // Attach PDF invoice to the "completed order" email
        add_filter( 'woocommerce_email_attachments', [$this, 'wc_rw_attach_pdf_to_order_completed_email'], 10, 3 );

        // Attach PDF credit note to the "order cancelled" or "order refunded" email
        add_filter( 'woocommerce_email_attachments', [$this, 'wc_rw_attach_pdf_to_order_cancelled_email'], 10, 3 );

        // Attach PDF proforma to the "order created" email for BACS (Bank transfer) payment method
        add_filter( 'woocommerce_email_attachments', [$this, 'wc_rw_attach_pdf_to_order_created_email'], 10, 3 );

        // Send cancellation email when order status changes from "completed" to "cancelled"
        //add_action( 'woocommerce_order_status_changed', [$this, 'wc_rw_send_cancellation_email_after_cancel_completed_order'], 9, 4 );

        // Delete temporary PDF file after email is successfully sent
        add_action('wp_mail_succeeded', [$this, 'wc_rw_delete_pdf_after_mail_sent'], 10, 1);

        // Add information about the invoice, credit note or proforma to the WooCommerce order email
        add_action('woocommerce_email_before_order_table', [$this, 'wc_rw_add_invoice_info_to_email'], 10, 4);


    }

    /**
     * Adds a custom meta box to the WooCommerce order edit page.
     */
    public function wc_rw_add_custom_box(){

        add_meta_box(
            'wc_rw_order_data_export_box',  // Unique ID
            'PDF invoice',      // Box title
            [$this,'wc_rw_custom_box_template'],  // Callback function to display the content of the meta box
            'shop_order', // Post type
            'side' //Context, 'side' places the meta box in the right sidebar of the page
        );

    }

    /**
     * Renders the content for the custom meta box on the WooCommerce order edit page.
     */
    public function wc_rw_custom_box_template() {
        $order_id = $_GET['post'];
        echo '<div class="wc-rw-order-data-export-meta-box-wrapper">';
        echo '<a href="' . esc_url(admin_url("admin-post.php?action=generate_pdf_invoice&order_id=$order_id")) .' " class="add_note button">Invoice</a>';
        echo '<a href="' . esc_url(admin_url("admin-post.php?action=generate_pdf_credit_note&order_id=$order_id")) .' " class="add_note button">Credit Note</a>';
        echo '<a href="' . esc_url(admin_url("admin-post.php?action=generate_pdf_proforma&order_id=$order_id")) .' " class="add_note button">Proforma</a>';
        echo '</div>';
    }

    /**
     * Displays a custom meta field for the invoice date on the WooCommerce order edit page.
     *
     * @param WC_Order $order
     */
    public function wc_rw_show_custom_order_meta_field_invoice_date(WC_Order $order){

        $invoice_date = $order->get_meta( 'wc_wr_order_data_export_invoice_date' );
        if(!$invoice_date) $invoice_date = 'Not created!';

        woocommerce_wp_text_input( array(
            'id' => 'wc_rw_ode_invoice_date',
            'label' => 'Invoice date:',
            'wrapper_class' => 'form-field-wide wc-rw-data-export-invoice-date',
            'class' => 'date-picker wc-rw-data-export-invoice-input',
            'style' => 'width:100%;',
            'value' => $invoice_date,
            'description' => ''
        ));
    }

    /**
     * Displays a custom meta field for the credit note date on the WooCommerce order edit page.
     *
     * @param WC_Order $order
     */
    public function wc_rw_show_custom_order_meta_field_credit_note_date(WC_Order $order){

        $credit_note_date = $order->get_meta( 'wc_wr_order_data_export_credit_note_date' );
        if(!$credit_note_date) $credit_note_date = 'Not created!';

        woocommerce_wp_text_input( array(
            'id' => 'wc_rw_ode_credit_note_date',
            'label' => 'Credit note date:',
            'wrapper_class' => 'form-field-wide wc-rw-data-export-invoice-date',
            'class' => 'date-picker wc-rw-data-export-invoice-input',
            'style' => 'width:100%;',
            'value' => $credit_note_date,
            'description' => ''
        ));


    }

    /**
     * Displays a custom meta field for the proforma date on the WooCommerce order edit page.
     *
     * @param WC_Order $order
     */
    public function wc_rw_show_custom_order_meta_field_proforma_date(WC_Order $order){

        $proforma_date = $order->get_meta( 'wc_wr_order_data_export_proforma_date' );
        if(!$proforma_date) $proforma_date = 'Not created!';

        woocommerce_wp_text_input( array(
            'id' => 'wc_rw_ode_proforma_date',
            'label' => 'Proforma date:',
            'wrapper_class' => 'form-field-wide wc-rw-data-export-invoice-date',
            'class' => 'date-picker wc-rw-data-export-invoice-input',
            'style' => 'width:100%;',
            'value' => $proforma_date,
            'description' => ''
        ))  ;

    }


    /**
     * Displays a custom meta field for the exchange rate on the WooCommerce order edit page.
     *
     * @param WC_Order $order
     */
    public function wc_rw_show_custom_order_meta_field_exchange_rate(WC_Order $order): void
    {

        $exchange_rate = $order->get_meta( 'wc_wr_order_data_export_order_exchange_rate' );

        woocommerce_wp_text_input( array(
            'id' => 'wc_rw_ode_order_exchange_rate',
            'label' => 'Order exchange rate:',
            'wrapper_class' => 'form-field-wide wc-rw-data-export-invoice-date',
            'style' => 'width:100%;',
            'value' => $exchange_rate,
            'description' => '',
            'type' => 'number',
            'custom_attributes' => [
                'step' => '0.001',
                'min'  => '0.01',
                'max'  => '1000'
            ],
        )) ;

    }

    /**
     * Displays a custom meta field for the package shipping tracking on the WooCommerce order edit page.
     *
     * @param WC_Order $order
     */
    public function wc_rw_show_custom_order_meta_field_shipping_tracking(WC_Order $order): void
    {

        $shipping_tracking = $order->get_meta( 'wc_wr_order_data_export_shipping_tracking' );

        woocommerce_wp_text_input( array(
            'id' => 'wc_rw_ode_shipping_tracking',
            'label' => 'Shipping tracking:',
            'wrapper_class' => 'form-field-wide wc-rw-data-export-invoice-date',
            'style' => 'width:100%;',
            'value' => $shipping_tracking,
            'description' => '',
            'type' => 'text',

        )) ;

    }


    /**
     * Saves the custom invoice date field when updating the order in the WooCommerce admin.
     *
     * @param int $order_id
     */
    public function wc_rw_save_general_details_invoice_date(int $order_id){
        if(isset($_POST['wc_rw_ode_invoice_date']) && $_POST['wc_rw_ode_invoice_date'] !== 'Not created!') {
            $invoice_date = date("d.m.Y", strtotime(wc_clean($_POST['wc_rw_ode_invoice_date'])));
            update_post_meta($order_id, 'wc_wr_order_data_export_invoice_date', $invoice_date);
        }
        if(empty($_POST['wc_rw_ode_invoice_date'])) {
            delete_post_meta($order_id, 'wc_wr_order_data_export_invoice_date');
        }
    }

    /**
     * Saves the custom credit note date field when updating the order in the WooCommerce admin.
     *
     * @param int $order_id
     */
    public function wc_rw_save_general_details_credit_note_date(int $order_id){
        if(isset($_POST['wc_rw_ode_credit_note_date']) && $_POST['wc_rw_ode_credit_note_date'] !== 'Not created!') {
            $credit_note_date = date("d.m.Y", strtotime(wc_clean( $_POST['wc_rw_ode_credit_note_date'])));
            update_post_meta( $order_id, 'wc_wr_order_data_export_credit_note_date', $credit_note_date ) ;
        }

        if(empty($_POST['wc_rw_ode_credit_note_date'])) {
            delete_post_meta($order_id, 'wc_wr_order_data_export_credit_note_date');
        }
    }

    /**
     * Saves the custom credit note date field when updating the order in the WooCommerce admin.
     *
     * @param int $order_id
     */
    public function wc_rw_save_general_details_proforma_date(int $order_id){
        if(isset($_POST['wc_rw_ode_proforma_date']) && $_POST['wc_rw_ode_proforma_date'] !== 'Not created!') {
            $proforma_date = date("d.m.Y", strtotime(wc_clean( $_POST['wc_rw_ode_proforma_date'])));
            update_post_meta( $order_id, 'wc_wr_order_data_export_proforma_date', $proforma_date ) ;
        }

        if(empty($_POST['wc_rw_ode_proforma_date'])) {
            delete_post_meta($order_id, 'wc_wr_order_data_export_proforma_date');
        }
    }

    /**
     * Saves the custom exchange rate field when updating the order in the WooCommerce admin.
     *
     * @param int $order_id
     */
    public function wc_rw_save_general_details_order_exchange_rate(int $order_id){
        if(!empty($_POST['wc_rw_ode_order_exchange_rate'])) {
            $exchange_rate = $_POST['wc_rw_ode_order_exchange_rate'];
            update_post_meta( $order_id, 'wc_wr_order_data_export_order_exchange_rate', $exchange_rate ) ;
        } else {
            delete_post_meta($order_id, 'wc_wr_order_data_export_order_exchange_rate');
        }
    }

    /**
     * Saves the shipping tracking field when updating the order in the WooCommerce admin.
     *
     * @param int $order_id
     */
    public function wc_rw_save_general_details_shipping_tracking(int $order_id){
        if(!empty($_POST['wc_rw_ode_shipping_tracking'])) {
            $shipping_tracking = $_POST['wc_rw_ode_shipping_tracking'];
            update_post_meta( $order_id, 'wc_wr_order_data_export_shipping_tracking', $shipping_tracking ) ;
        } else {
            delete_post_meta($order_id, 'wc_wr_order_data_export_shipping_tracking');
        }
    }


    /**
     * Hides the custom invoice date meta field on the WooCommerce order page.
     *
     * @param bool $protected
     * @param string $meta_key
     * @return bool
     */
    public function wc_rw_hide_invoice_date_meta_field(bool $protected, string $meta_key) : bool
    {

        if( in_array($meta_key, array('wc_wr_order_data_export_invoice_date'))){
            return true;
        }
        return $protected;

    }

    /**
     * Hides the custom credit note date meta field on the WooCommerce order page.
     *
     * @param bool $protected
     * @param string $meta_key
     * @return bool
     */
    public function wc_rw_hide_credit_note_date_meta_field(bool $protected, string $meta_key) : bool
    {

        if( in_array($meta_key, array('wc_wr_order_data_export_credit_note_date'))){
            return true;
        }
        return $protected;

    }


    /**
     * Hides the custom proforma date meta field on the WooCommerce order page.
     *
     * @param bool $protected
     * @param string $meta_key
     * @return bool
     */
    public function wc_rw_hide_proforma_date_meta_field(bool $protected, string $meta_key) : bool
    {

        if( in_array($meta_key, array('wc_wr_order_data_export_proforma_date'))){
            return true;
        }
        return $protected;

    }

    /**
     * Hides the custom exchange rate meta field on the WooCommerce order page.
     *
     * @param bool $protected
     * @param string $meta_key
     * @return bool
     */
    public function wc_rw_hide_exchange_rate_meta_field(bool $protected, string $meta_key) : bool
    {

        if( in_array($meta_key, array('wc_wr_order_data_export_order_exchange_rate'))){
            return true;
        }
        return $protected;

    }

    /**
     * Hides the shipping tracking meta field on the WooCommerce order page.
     *
     * @param bool $protected
     * @param string $meta_key
     * @return bool
     */
    public function wc_rw_hide_shipping_tracking_meta_field(bool $protected, string $meta_key) : bool
    {

        if( in_array($meta_key, array('wc_wr_order_data_export_shipping_tracking'))){
            return true;
        }
        return $protected;

    }



    /**
     * Handles the generation of a PDF invoice for a WooCommerce order.
     */
    public function wc_rw_generate_pdf_invoice() {

        if(isset($_GET['order_id'])) {

            $order_id = $_GET['order_id'];

            $this->wc_rw_generate_invoice('invoice', $order_id, false);

        } else {
            unset($_SESSION['error']);
            require_once WP_PLUGIN_DIR . "/wc-rw-order-data-export/modules/pdf-invoices/invoices_templates/invoice-error.php";
            exit();
        }
    }

    /**
     * Handles the generation of a PDF credit note for a WooCommerce order.
     */
    public function wc_rw_generate_pdf_credit_note() {

        if(isset($_GET['order_id'])) {

            $order_id = $_GET['order_id'];

            $this->wc_rw_generate_invoice('creditnote', $order_id, false);

        } else {
            unset($_SESSION['error']);
            require_once WP_PLUGIN_DIR . "/wc-rw-order-data-export/modules/pdf-invoices/invoices_templates/invoice-error.php";
            exit();
        }
    }

    /**
     * Handles the generation of a PDF proforma for a WooCommerce order.
     */
    public function wc_rw_generate_pdf_proforma() {

        if(isset($_GET['order_id'])) {

            $order_id = $_GET['order_id'];

            $this->wc_rw_generate_invoice('proforma', $order_id, false);

        } else {
            unset($_SESSION['error']);
            require_once WP_PLUGIN_DIR . "/wc-rw-order-data-export/modules/pdf-invoices/invoices_templates/invoice-error.php";
            die();
        }
    }


    /**
     * Generates a PDF invoice, credit note, or proforma for a WooCommerce order.
     *
     * @param string $invoice_type
     * @param int $order_id
     * @param bool $save_invoice
     * @throws MpdfException
     */
    private function wc_rw_generate_invoice(string $invoice_type, int $order_id, bool $save_invoice)
    {

        if($this->taxState){
            $invoice = new \Wc_Rw_Order_Data_Export_Dpf_Data_Taxes_On();
        } else {
            $invoice = new \Wc_Rw_Order_Data_Export_Dpf_Data_Taxes_Off();
        }

        $invoice_data = $invoice->getPDFData($order_id, $invoice_type);

        //wc_rw_order_data_export_debug($invoice_data);die();

        if(!$invoice_data) {
            error_log("Ошибка генерации данных для PDF-инвойса. Order ID: $order_id, Invoice Type: $invoice_type");
            require_once WP_PLUGIN_DIR . "/wc-rw-order-data-export/modules/pdf-invoices/invoices_templates/invoice-error.php";
            die();
        }

        extract($invoice_data);

        ob_start();
        $invoice_template = WP_PLUGIN_DIR ."/wc-rw-order-data-export/modules/pdf-invoices/invoices_templates/$invoice_type.php";
        if(is_file($invoice_template)) {

            require_once $invoice_template;
            $content = ob_get_clean();
            $mPdf = new Mpdf();
            $stylesheet = file_get_contents(WP_PLUGIN_DIR . "/wc-rw-order-data-export/modules/pdf-invoices/assets/css/invoice.css");
            $mPdf->WriteHTML($stylesheet, 1);
            $mPdf->WriteHTML($content, 2);

            $document_number = ${$invoice_type . 'Number'};

            if($save_invoice) {
                $upload_dir = wp_upload_dir();
                $pdf_path = $upload_dir['basedir'] . '/wc_rw_invoices/' . $document_number  . '.pdf';
                $mPdf->Output( $pdf_path, Destination::FILE );
                return $pdf_path;
            }

            $mPdf->Output("{$document_number}.pdf", 'D');
        } else {
            ob_end_clean();
            error_log("Invoice template is not found: $invoice_template");
            $_SESSION['error'] = "Invoice template is not found!";
            require_once WP_PLUGIN_DIR . "/wc-rw-order-data-export/modules/pdf-invoices/invoices_templates/invoice-error.php";
            die();
        }

    }



    /**
     * Attaches a PDF invoice to the "Completed Order" WooCommerce email.
     *
     * @param array $attachments
     * @param string $email_id
     * @param WC_Order $order
     * @return array
     * @throws MpdfException
     */
    public function wc_rw_attach_pdf_to_order_completed_email( array $attachments, string $email_id, object $order ) : array
    {

        $invoice_date = $order->get_meta( 'wc_wr_order_data_export_invoice_date' );

        if ( 'customer_completed_order' === $email_id && is_a( $order, 'WC_Order' ) && $invoice_date ) {
            // make and save PDF
            $pdf_path = $this->wc_rw_generate_invoice('invoice', $order->get_id(), true);

            // attach file
            if ( file_exists( $pdf_path ) ) {
                $attachments[] = $pdf_path;

                $this->pdf_to_delete = $pdf_path;
            }

        }

        return $attachments;
    }


    /**
     * Attaches a PDF credit note to the "Cancelled Order" WooCommerce email.
     *
     * @param array $attachments
     * @param string $email_id
     * @param WC_Order $order
     * @return array
     * @throws MpdfException
     */
    public function wc_rw_attach_pdf_to_order_cancelled_email(array $attachments, string $email_id, object $order ) : array
    {

        $credit_note_date = $order->get_meta( 'wc_wr_order_data_export_credit_note_date' );

        if ( (/*'cancelled_order' === $email_id ||*/ 'customer_refunded_order' === $email_id) && is_a( $order, 'WC_Order' ) && $credit_note_date ) {
            // make and save PDF
            $pdf_path = $this->wc_rw_generate_invoice('creditnote', $order->get_id(), true);

            // attach file
            if ( file_exists( $pdf_path ) ) {
                $attachments[] = $pdf_path;

                $this->pdf_to_delete = $pdf_path;
            }

        }

        return $attachments;
    }


    /**
     * Attaches a PDF proforma to the "New Order" WooCommerce email, when payment method is "Bank transfer".
     *
     * @param array $attachments
     * @param string $email_id
     * @param WC_Order $order
     * @return array
     * @throws MpdfException
     */
    public function wc_rw_attach_pdf_to_order_created_email(array $attachments, string $email_id, object $order ) : array
    {

        $paymentMethod = $order->get_payment_method();

        if ( 'customer_on_hold_order' === $email_id && 'bacs' === $paymentMethod && is_a( $order, 'WC_Order' )) {
            // make and save PDF
            $pdf_path = $this->wc_rw_generate_invoice('proforma', $order->get_id(), true);

            // attach file
            if ( file_exists( $pdf_path ) ) {
                $attachments[] = $pdf_path;

                $this->pdf_to_delete = $pdf_path;
            }

        }

        return $attachments;
    }


    /**
     * Deletes the generated PDF file after the email is successfully sent.
     *
     * @param array $mail_data
     */
    public function wc_rw_delete_pdf_after_mail_sent(array $mail_data ) {

        if ( isset( $this->pdf_to_delete ) && file_exists( $this->pdf_to_delete ) ) {
            unlink( $this->pdf_to_delete );
            unset( $this->pdf_to_delete );
        }
    }

    /**
     * Adds a custom message with invoice, credit note, or proforma information to the WooCommerce email.
     *
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     * @param WC_Email $email
     */
    public function wc_rw_add_invoice_info_to_email(WC_Order $order, bool $sent_to_admin, bool $plain_text, WC_Email $email){

        if($order->get_meta('wc_wr_order_data_export_invoice_date') && $email->id === 'customer_completed_order'){

            echo '
                
                <div style="margin: 10px 0; border: 1px solid #e0e0e0; border-radius: 3px; background: #fafafa; padding: 5px"> ' . __('Attached to this email, you\'ll find the invoice for your order.', 'wc-rw-order-data-export') . '</div>
            
            ';

        }elseif ($order->get_meta('wc_wr_order_data_export_credit_note_date') && ('cancelled_order' === $email->id || 'customer_refunded_order' === $email->id)) {

            echo '
                
                <div style="margin: 10px 0; border: 1px solid #e0e0e0; border-radius: 3px; background: #fafafa; padding: 5px"> ' . __('Attached to this email, you\'ll find the credit note for your cancelled order.', 'wc-rw-order-data-export') . '</div>
            
            ';
        }elseif ($order->get_payment_method() === 'bacs' && 'customer_on_hold_order' === $email->id) {

            echo '
                
                <div style="margin: 10px 0; border: 1px solid #e0e0e0; border-radius: 3px; background: #fafafa; padding: 5px"> ' . __('Attached, you will find the proforma invoice with the details for making a bank transfer.', 'wc-rw-order-data-export') . '</div>
            
            ';

        }

    }

    /**
     * Sends a cancellation email if an order's status changes from "completed" to "cancelled".
     *
     * @param int $order_id
     * @param string $old_status
     * @param string $new_status
     * @param WC_Order $order
     */
    /*public function wc_rw_send_cancellation_email_after_cancel_completed_order(int $order_id, string $old_status, string $new_status, WC_Order $order){

        if ( 'cancelled' === $new_status && 'completed' === $old_status ) {

            $mailer = WC()->mailer();
            $email = $mailer->get_emails()['WC_Email_Cancelled_Order'];

            if ( $email ) {
                $email->trigger( $order_id );
            }
        }

    }*/


    /**
     * Add custom product fields.
     */
    public function wc_rw_add_custom_product_alter_name_field() {

            echo '<div class="options_group">';

            woocommerce_wp_text_input(
                array(
                    'id' => '_wc_rw_order_data_export_alter_product_name',
                    'label' => __('Альтернативное название', 'woocommerce'),
                    'placeholder' => 'Название для инвойса',
                    'desc_tip' => 'true',
                    'description' => __('Введите название товара, которое будет отобрадаться в инвойсе.', 'woocommerce')
                )
            );

            echo '</div>';
    }


    /**
     * Save custom product fields.
     *
     * @param int $post_id
     */
    public function wc_rw_save_custom_product_alter_name_field($post_id) {

        $custom_field_value_alter_name = !empty( $_POST['_wc_rw_order_data_export_alter_product_name'] ) ? sanitize_text_field( $_POST['_wc_rw_order_data_export_alter_product_name'] ) : '';

        update_post_meta( $post_id, '_wc_rw_order_data_export_alter_product_name', $custom_field_value_alter_name );


    }



}