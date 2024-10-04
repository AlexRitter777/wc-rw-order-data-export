<?php


class Wc_Rw_Order_Data_Export_Xml_Report {

    public function __construct(){

    // Adding to admin order list bulk dropdown a custom action 'custom_downloads'
    add_filter( 'bulk_actions-edit-shop_order', [$this, 'wc_rw_downloads_bulk_actions_edit_product'], 20, 1 );

    // Make the action from selected orders
    add_filter( 'handle_bulk_actions-edit-shop_order', [$this, 'wc_rw_downloads_handle_bulk_action_edit_shop_order'], 10, 3 );

    // The results notice from bulk action on orders
    add_action( 'admin_notices', [$this, 'wc_rw_downloads_bulk_action_admin_notice_xml'], 11 );

    add_action('admin_post_wc_rw_generate_xml_report', [$this, 'wc_rw_generate_xml_report']);

    }

    public function wc_rw_downloads_bulk_actions_edit_product( $actions ){

        $actions['xml_download'] = __( 'Download XML', 'wc-rw-order-data-export' );
        return $actions;

    }

    public function wc_rw_downloads_handle_bulk_action_edit_shop_order($redirect_to, $action, $post_ids){

        if ( $action !== 'xml_download' )
            return $redirect_to; // Exit

        $processed_ids = [];

        foreach ( $post_ids as $post_id ) {

            $processed_ids[] = $post_id;
        }

        $data = new Wc_Rw_Order_Data_Export_Xml_Data();
        $orders_data = $data->getXMLData($processed_ids);

        $session_id = uniqid('wc_rw_ode_', true);

        $redirect_to = add_query_arg(
            'session_id', $session_id,
            $redirect_to
        );

        if(!$orders_data){
            $_SESSION[$session_id]['success'] = false;
            return $redirect_to;
        }

        $xml = new Wc_Rw_Order_Data_Export_Xml_Creator($orders_data);
        $export = $xml->createXml();

        if($export) {

            $_SESSION[$session_id]['success'] = true;
            $_SESSION[$session_id]['export_type'] = 'xml';
            $_SESSION[$session_id]['data'] = $export; //comment on testing
            //$_SESSION[$session_id]['data'] = $ordersData; //uncomment on testing
            $_SESSION[$session_id]['count'] = count($processed_ids);

        } else {

            $_SESSION[$session_id]['success'] = false;
            $_SESSION[$session_id]['error'] = 'Ошибка при генерировании XML! Обратитесь к разработчикам!';
        }

        return $redirect_to;


    }


    public function wc_rw_downloads_bulk_action_admin_notice_xml()
    {

        if(!isset($_GET['session_id']))  return;

        $session_id = $_GET['session_id'];

        if(!isset($_SESSION[$session_id]) || $_SESSION[$session_id]['export_type'] !== 'xml' ) return;

        if(($_SESSION[$session_id]['success'])){
            echo('<div id="message" class="updated fade wc-rw-ode-xml-message">
                <p>' . '<a class="wc-rw-ode-download-xml" href="' . esc_url(admin_url("admin-post.php?action=wc_rw_generate_xml_report&session_id=$session_id")) .' ">Download XML</a></p>
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

    public function wc_rw_generate_xml_report(){

        if(!$_GET['session_id']) return;

        $session_id = $_GET['session_id'];

        if (!isset($_SESSION[$session_id]['data'])) return;

        $export = $_SESSION[$session_id]['data'];
        //wc_rw_order_data_export_debug($export); //only for testing, headers below and "echo" should be comment

        unset($_SESSION[$session_id]);

        header('Content-type: text/xml');
        header('Content-Disposition: attachment; filename="text.xml"');
        echo $export;

    }


}