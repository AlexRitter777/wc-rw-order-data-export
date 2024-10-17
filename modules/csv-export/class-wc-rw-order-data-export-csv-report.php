<?php


class Wc_Rw_Order_Data_Export_Csv_Report {


    public function __construct()
    {
        // Adding to admin order list bulk dropdown a custom action 'custom_downloads'
        add_filter( 'bulk_actions-edit-shop_order', [$this, 'wc_rw_downloads_bulk_actions_edit_product'], 20, 1 );

        // Handle  the action from selected orders
        add_filter( 'handle_bulk_actions-edit-shop_order', [$this, 'wc_rw_downloads_handle_bulk_action_edit_shop_order'], 10, 3 );

        // Display results notice from bulk action on orders
        add_action( 'admin_notices', [$this, 'wc_rw_downloads_bulk_action_admin_notice_csv'], 11 );

        // Handle CSV report generation
        add_action('admin_post_wc_rw_generate_csv_report', [$this, 'wc_rw_generate_csv_report']);


    }

    /**
     * Add custom action "Download CSV" to the bulk actions dropdown
     *
     * @param array $actions
     * @return array
     */
    public function wc_rw_downloads_bulk_actions_edit_product(array $actions ) : array{

        $actions['csv_download'] = __( 'Download CSV', 'wc-rw-order-data-export' );
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

        if ( $action !== 'csv_download' )
            return $redirect_to; // Exit if action is not "csv_download"

        $processed_ids = [];

        foreach ( $post_ids as $post_id ) {
            $processed_ids[] = $post_id;
        }

        $report_id = uniqid('wc_rw_ode_');

        $data = new Wc_Rw_Order_Data_Export_Csv_Data($report_id);
        $orders_data = $data->getCSVData($processed_ids);

        if(!$orders_data){
            return add_query_arg(
                array(
                    'report_id' => $report_id,
                    'success' => 0,
                    'count' => count($processed_ids),
                    'type' => 'csv'
                ),
                $redirect_to
            );
        }

        $csv = new Wc_Rw_Order_Data_Export_Csv_Creator();
        $export = $csv->createCsv($orders_data);


        if($export) {

            set_transient( $report_id, $export, HOUR_IN_SECONDS );

            $arguments = array(
                'report_id' => $report_id,
                'success' => 1,
                'count' => count($processed_ids),
                'type' => 'csv'
            );


        } else {
            Wc_Rw_Order_Data_Export_Debug::wc_rw_order_data_export_error('Creating CSV error in CSV Creator');
            $arguments = array(
                'report_id' => $report_id,
                'success' => 0,
                'count' => count($processed_ids),
                'type' => 'csv'
            );

        }

        return add_query_arg($arguments,  $redirect_to);

    }

    /**
     * Display admin notice after bulk action
     */
    public function wc_rw_downloads_bulk_action_admin_notice_csv()
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
        if($_GET['type'] !== 'csv' ) return;

        if($_GET['success'] == 1 && get_transient($report_id)){

            echo('<div id="message" class="updated fade wc-rw-ode-csv-message">
                <p>' . '<a class="wc-rw-ode-download-csv" href="' . esc_url(admin_url("admin-post.php?action=wc_rw_generate_csv_report&report_id=$report_id")) .' ">Download CSV</a></p>
                <p>' . $count . ' orders were processed.</p>  
              </div>');

        } else {

            echo ('<div id="message" class="updated fade">
                    <p>Error! Please try again later!</p>
                    <p>'. get_transient('wc_rw_error_' . $report_id) .'</p>
              </div>');

        }

        delete_transient('wc_rw_error' . $report_id);

    }

    /**
     * Generate CSV report and return as download
     */
    public function wc_rw_generate_csv_report(){

        if(!$_GET['report_id'] || !Wc_Rw_Order_Data_Export_Validator::validate_report_id($_GET['report_id'])) return;

        $report_id = $_GET['report_id'];

        $export = get_transient($report_id);

        if (!$export) return;

        //wc_rw_order_data_export_debug($export); //only for testing, headers below and "echo" should be comment

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="EU_VAT_report.csv"');
        echo "\xEF\xBB\xBF";

        $fp = fopen('php://output', 'wb');
        foreach ($export as $line) {
            fputcsv($fp, $line, ';');
        }
        fclose($fp);

    }




}