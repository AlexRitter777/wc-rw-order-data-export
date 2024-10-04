<?php


class Wc_Rw_Order_Data_Export_Csv_Report {


    public function __construct()
    {
        // Adding to admin order list bulk dropdown a custom action 'custom_downloads'
        add_filter( 'bulk_actions-edit-shop_order', [$this, 'wc_rw_downloads_bulk_actions_edit_product'], 20, 1 );

        // Make the action from selected orders
        add_filter( 'handle_bulk_actions-edit-shop_order', [$this, 'wc_rw_downloads_handle_bulk_action_edit_shop_order'], 10, 3 );

        // The results notice from bulk action on orders
        add_action( 'admin_notices', [$this, 'wc_rw_downloads_bulk_action_admin_notice_csv'], 11 );

        add_action('admin_post_wc_rw_generate_csv_report', [$this, 'wc_rw_generate_csv_report']);


    }


    public function wc_rw_downloads_bulk_actions_edit_product( $actions ){

        $actions['csv_download'] = __( 'Download CSV', 'wc-rw-order-data-export' );
        return $actions;

    }


    public function wc_rw_downloads_handle_bulk_action_edit_shop_order($redirect_to, $action, $post_ids){

        if ( $action !== 'csv_download' )
            return $redirect_to; // Exit

        $processed_ids = [];

        foreach ( $post_ids as $post_id ) {

            $processed_ids[] = $post_id;
        }

        $data = new Wc_Rw_Order_Data_Export_Csv_Data();
        $orders_data = $data->getCSVData($processed_ids);

        $session_id = uniqid('wc_rw_ode_', true);

        $redirect_to = add_query_arg(
            'session_id', $session_id,
            $redirect_to
        );



        if(!$orders_data){
            $_SESSION[$session_id]['success'] = false;
            $_SESSION['wc_rw_order_data_export']['error'] = 'Orders data retrieving for CSV report failed! ';
            Wc_Rw_Order_Data_Export_Debug::wc_rw_order_data_export_error('Orders data retrieving for CSV report failed! ');
            return $redirect_to;
        }

        $csv = new Wc_Rw_Order_Data_Export_Csv_Creator();
        $export = $csv->createCsv($orders_data);


        if($export) {

            $_SESSION[$session_id]['success'] = true;
            $_SESSION[$session_id]['export_type'] = 'csv';
            $_SESSION[$session_id]['data'] = $export; //comment on testing
            //$_SESSION[$session_id]['data'] = $ordersData; //uncomment on testing
            $_SESSION[$session_id]['count'] = count($processed_ids);

        } else {
            Wc_Rw_Order_Data_Export_Debug::wc_rw_order_data_export_error('Error with CSV generating!');
            $_SESSION[$session_id]['success'] = false;
            $_SESSION[$session_id]['error'] = 'Ошибка при генерировании CSV! Обратитесь к разработчикам!';
        }

        return $redirect_to;


    }

    public function wc_rw_downloads_bulk_action_admin_notice_csv()
    {

        if(!isset($_GET['session_id']))  return;

        $session_id = $_GET['session_id'];

        if(!isset($_SESSION[$session_id]) || $_SESSION[$session_id]['export_type'] !== 'csv' ) return;

        if(($_SESSION[$session_id]['success'])){

            echo('<div id="message" class="updated fade wc-rw-ode-csv-message">
                <p>' . '<a class="wc-rw-ode-download-csv" href="' . esc_url(admin_url("admin-post.php?action=wc_rw_generate_csv_report&session_id=$session_id")) .' ">Download CSV</a></p>
                <p>' . $_SESSION[$session_id]['count'] . ' orders were processed.</p>  
              </div>');

        } else {

            echo ('<div id="message" class="updated fade">
                    <p>Error! Please try again later!</p>
                    <p>'. $_SESSION['wc_rw_order_data_export']['error'] .'</p>
              </div>');

        }

        unset($_SESSION['wc_rw_order_data_export']['error']);
        unset($_SESSION[$session_id]['exported']);

    }


    public function wc_rw_generate_csv_report(){

        if(!$_GET['session_id']) return;

        $session_id = $_GET['session_id'];

        if (!isset($_SESSION[$session_id]['data'])) return;

        $export = $_SESSION[$session_id]['data'];
        unset($_SESSION[$session_id]);

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