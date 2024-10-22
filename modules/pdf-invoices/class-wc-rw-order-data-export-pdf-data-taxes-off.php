<?php

/**
 * Class responsible for extracting data from WooCommerce and calculating all necessary data
 * for creating invoices when taxes are disabled.
 */
class Wc_Rw_Order_Data_Export_Dpf_Data_Taxes_Off extends Wc_Rw_Order_Data_Export_Data_Getter
{


    /**
     * Retrieves all necessary data from WooCommerce and prepares data array for invoice generation.
     *
     * @param int $orderId
     * @param string $invoice_type
     * @return array|false
     */

    public function getPDFData(int $orderId, string $invoice_type)
    {

        $data = [];
        $error = 'Ошибка общего характера! Обратитесь к разработчикам!';
        $this->getErrors(); //should be executed first !!!

        // Load configuration properties (e.g., exchange rates, company data).
        if(!$this->executeAllPropertyMethods(
            'executePropertyMethod',
                ['exchange_rates',
                'countries',
                'company_data',
                'prefixes',
                'payment_methods',
                ],
            $error
        )
        ) return false;

        // Retrieve WooCommerce order object.
        if (!$this->checkRatesYear()) {
            $this->setErrorByName('year', $error);
            return false; // run always after lading exchanges_rates!
        }


        //Order object
        $order = $this->returnOrder($orderId);

        if ($order) {

            // Check if the order without VAT and VAT is disabled in WooCommerce
            if ($order->get_total_tax()) {
                $this->setErrorByName('vat_total_off', $error);
                return false;
            }

            // Retrieve common invoice data.
            if($result = $this->getCommonPdfData($orderId, $order, $error, $invoice_type)) {
                $data += $result;
            } else {
                return false;
            }

            // Get items data and validate VAT.
            $data['itemsData'] = $this->getItemsData($order);
            $data['itemsTotalValues'] = $this->getItemsTotalValues($order);

            // Retrieve fees and shipping data, validate VAT.
            $data['feesData'] = $this->getFees($order);
            $data['shippingData'] = $this->getShipping($order);

            // Calculate total price including VAT.
            $data['totalPriceInclVat'] = (float)$data['itemsTotalValues']['totalIncVat'] + (isset($data['feesData']['totalIncVat']) ? (float)$data['feesData']['totalIncVat'] : 0) + (isset($data['shippingData']['totalIncVat']) ? (float)$data['shippingData']['totalIncVat'] : 0);


            return $data;
        }

        $this->setErrorByName('order', $error);
        return false;

    }


    public function getItemsData(WC_Order $order): array
    {
        $itemData = [];
        $i = 0;
        foreach ($order->get_items() as $key => $item) {
            $i++;
            $alterProductName = get_post_meta($item->get_product_id(),'_wc_rw_order_data_export_alter_product_name', true);
            $itemData[$i]['name'] = !empty($alterProductName) ? $alterProductName : $item->get_name();
            $itemData[$i]['quantity'] = $item->get_quantity();
            $itemData[$i]['unitPriceExcVat'] = round($item->get_total()/$item->get_quantity(), 2);
            $itemData[$i]['allItemsPriceExlVat'] =  round($item->get_total(), 2);
            $itemData[$i]['allItemsPriceIncVat'] = $itemData[$i]['allItemsPriceExlVat'];
            $itemData[$i]['tax_rate'] = 0;
        }
        return $itemData;

    }

    public function getItemsTotalValues(WC_Order $order) : array
    {
        $totalValues['totalExlVat'] = 0;
        $totalValues['totalIncVat'] = 0;
        foreach ($order->get_items() as $key => $item) {
            $itemsPriceExlVat =  round($item->get_total(), 2);
            $totalValues['totalExlVat'] += $itemsPriceExlVat;
        }
        $totalValues['totalIncVat'] = $totalValues['totalExlVat'];
        return $totalValues;

    }

    public function getFees(WC_Order $order)
    {
        $feesData = [];
        if($fees = $order->get_fees()){

            $fee = reset($fees);
            $alterCodName = get_option('wc_rw_order_data_export_cod_alter_name');
            $feesData['name'] = !empty($alterCodName) ? $alterCodName : $fee->get_name();
            $feesData['totalExlVat'] = $fee->get_total();
            $feesData['totalIncVat'] = $feesData['totalExlVat'];
            $feesData['tax_rate'] = 0;
            return $feesData;
        }
        return 0;
    }

    public function getShipping(WC_Order $order)
    {
        $shippingData = [];
        if($shippingData['totalExlVat'] = $order->get_shipping_total()) {

            $alterShippingName = get_option('wc_rw_order_data_export_shipping_alter_name');
            $shippingData['name'] = !empty($alterShippingName) ? $alterShippingName : $order->get_shipping_method();
            $shippingData['totalIncVat'] = $shippingData['totalExlVat'];
            $shippingData['tax_rate'] = 0;
            return $shippingData;

        }

        return 0;

    }



}