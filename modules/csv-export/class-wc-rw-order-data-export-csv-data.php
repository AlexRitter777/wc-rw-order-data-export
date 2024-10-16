<?php


class Wc_Rw_Order_Data_Export_Csv_Data extends Wc_Rw_Order_Data_Export_Data_Getter
{

    private string $report_id;


    public function __construct($report_id)
    {
        $this->report_id = $report_id;
    }


    public function getCSVData($orders){

        $count = 0; //invoices counter
        $ordersData = [];
        $total = 0;
        $ordersID = [];
        $error = 'Ошибка общего характера! Обратитесь к разработчикам!';
        $this->getErrors(); //should be executed first !!!

        //Load all config data
        if (!$this->executePropertyMethod('vat_rates', $error)) return false;
        if (!$this->executePropertyMethod('countries', $error)) return false;
        if (!$this->executePropertyMethod('prefixes', $error)) return false;

        foreach ($orders as $orderId) {

            $data = [];

            //Order object
            $order = $this->returnOrder($orderId);

            if ($order){

                //Chek if VAT is turn on in WooCommerce
                if (!$order->get_total_tax()){
                    $this->getErrorAndOrderID('vat_total', $error, $orderId);
                    return false;
                }

                //Order details
                $data['orderId'] = $orderId;
                if(!$data['invoiceDate'] = $this->getInvoiceDate($order)){
                    $this->getErrorAndOrderID('invoice_date', $error, $orderId);
                    return false;
                };

                //Invoice number
                if (!$data['invoiceNumber'] = $this->getInvoiceNumber($orderId, $this->prefixes)) {
                    $this->getErrorAndOrderID('prefixes', $error, $orderId);
                    return false;
                }

                //Client
                $data['clientBillingName'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                $data['clientBillingAddress1'] = $order->get_billing_address_1();
                $data['clientBillingAddress2'] = $order->get_billing_address_2();
                $data['clientBillingPostcode'] = $order->get_billing_postcode();
                $data['clientBillingCity'] = $order->get_billing_city();
                $data['clientBillingCountry'] =  $order->get_billing_country();


                //Client Billing Country
                if (!$data['clientBillingCountryFull'] = $this->getOrderClientCountry($order)) {
                    $this->getError('countries', $error);
                    return false;
                }

                if(!$data['vatRatesValues'] = $this->getVatRatesValues($order)){

                    if ($_SESSION['int_err_code'] == 1) {
                        $this->getError('vat_rates', $error);
                    } elseif ($_SESSION['int_err_code'] == 2) {
                        $this->getError('vat_item', $error);
                    } elseif ($_SESSION['int_err_code'] == 3) {
                        $this->getError('fee_or_shipping_vat', $error);
                    }

                    return false;
                }

                if(!$data['vatBases'] = $this->getVatBases($order)){

                    if ($_SESSION['int_err_code'] == 1) {
                        $this->getError('vat_rates', $error);
                    } elseif ($_SESSION['int_err_code'] == 2) {
                        $this->getError('vat_item', $error);
                    } elseif ($_SESSION['int_err_code'] == 3) {
                        $this->getError('fee_or_shipping_vat', $error);
                    }

                }

            } else {
                $this->getErrorAndOrderID('order', $error, $orderId);
                return false;
            }

            $ordersData[$orderId] = $data;
            $ordersID[] = $orderId;
        }


        $ordersData['userVatRates'] = $this->vat_rates;
        $ordersData['ordersId'] = $ordersID;

        return $ordersData;

    }





}