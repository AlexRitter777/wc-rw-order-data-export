<?php


class Wc_Rw_Order_Data_Export_Xml_Report {

    public function __construct(){

    // Adding to admin order list bulk dropdown a custom action 'xml_downloads'
    add_filter( 'bulk_actions-edit-shop_order', [$this, 'wc_rw_downloads_bulk_actions_edit_product'], 20, 1 );

    // Handle  the action from selected orders
    add_filter( 'handle_bulk_actions-edit-shop_order', [$this, 'wc_rw_downloads_handle_bulk_action_edit_shop_order'], 10, 3 );

    // Display results notice from bulk action on orders
    add_action( 'admin_notices', [$this, 'wc_rw_downloads_bulk_action_admin_notice_xml'], 11 );

    // Handle XML report generation
    add_action('admin_post_wc_rw_generate_xml_report', [$this, 'wc_rw_generate_xml_report']);

    }

    /**
     * Add custom action "Download XML" to the bulk actions dropdown
     *
     * @param array $actions
     * @return array
     */
    public function wc_rw_downloads_bulk_actions_edit_product( array $actions ) : array {

        $actions['xml_download'] = __( 'Download XML', 'wc-rw-order-data-export' );
        return $actions;

    }

    /**
     * Handle the custom bulk action for downloading XML
     *
     * @param string $redirect_to
     * @param string $action
     * @param array $post_ids
     * @return string
     * @throws Exception
     */
    public function wc_rw_downloads_handle_bulk_action_edit_shop_order(string $redirect_to, string $action, array $post_ids) : string
    {

        if ( $action !== 'xml_download' )
            return $redirect_to; // Exit if action is not "xml_download"

        $processed_ids = [];

        foreach ( $post_ids as $post_id ) {
            $processed_ids[] = $post_id;
        }

        $report_id = uniqid('wc_rw_ode_');

        $data = new Wc_Rw_Order_Data_Export_Xml_Data($report_id);
        $orders_data = $data->getXMLData($processed_ids);

        if(!$orders_data){
            return add_query_arg(
                array(
                    'report_id' => $report_id,
                    'success' => 0,
                    'count' => count($processed_ids),
                    'type' => 'xml'
                ),
                $redirect_to
            );
        }

        $xml = new Wc_Rw_Order_Data_Export_Xml_Creator($orders_data);
        $export = $xml->createXml();

        if($export) {

             set_transient( $report_id, $export, HOUR_IN_SECONDS );

             $arguments = array(
                 'report_id' => $report_id,
                 'success' => 1,
                 'count' => count($processed_ids),
                 'type' => 'xml'
             );

        } else {

            Wc_Rw_Order_Data_Export_Debug::wc_rw_order_data_export_error('Creating XML error in XML Creator');

            $arguments = array(
                'report_id' => $report_id,
                'success' => 0,
                'count' => count($processed_ids),
                'type' => 'xml'
            );

        }
        return add_query_arg($arguments,  $redirect_to);

    }

    /**
     * Display admin notice after bulk action
     */
    public function wc_rw_downloads_bulk_action_admin_notice_xml()
    {

        if(!isset($_GET['report_id']) && !isset($_GET['type']) && !isset($_GET['success']) && !isset($_GET['count']))  return;

        $report_id = $_GET['report_id'];
        $type = $_GET['type'];
        $success = $_GET['success'];
        $count = $_GET['count'];

        if (!Wc_Rw_Order_Data_Export_Validator::validate_report_id($report_id) ||
            !Wc_Rw_Order_Data_Export_Validator::validate_type($type) ||
            !Wc_Rw_Order_Data_Export_Validator::validate_success($success) ||
            !Wc_Rw_Order_Data_Export_Validator::validate_count($count)) {
            return;
        }

        // Check if type is correct
        if($_GET['type'] !== 'xml' ) return;

        if(($_GET['success'] == 1) && get_transient($report_id)){
            echo('<div id="message" class="updated fade wc-rw-ode-xml-message">
                <p>' . '<a class="wc-rw-ode-download-xml" href="' . esc_url(admin_url("admin-post.php?action=wc_rw_generate_xml_report&report_id=$report_id")) .' ">Download XML</a></p>
                <p>' . $count . ' orders were processed.</p>  
              </div>');

        } else {

            echo ('<div id="message" class="updated fade">
                    <p>XML report error! Please contact support!</p>
                    <p>'. get_transient('wc_rw_error_' . $report_id) .'</p>
              </div>');

        }

        delete_transient('wc_rw_error' . $report_id);

    }


    /**
     * Generate XML report and return as download
     */
    public function wc_rw_generate_xml_report(){

        if(!$_GET['report_id'] || !Wc_Rw_Order_Data_Export_Validator::validate_report_id($_GET['report_id'])) return;

        $report_id = $_GET['report_id'];

        $export = get_transient($report_id);

        if (!$export) return;

        delete_transient($report_id);

        header('Content-type: text/xml');
        header('Content-Disposition: attachment; filename=xml-report-' . date('Y-m-d') . '.xml');

        echo $export;

    }


}