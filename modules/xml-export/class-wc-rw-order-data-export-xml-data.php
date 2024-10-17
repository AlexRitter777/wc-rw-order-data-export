<?php


class Wc_Rw_Order_Data_Export_Xml_Data extends Wc_Rw_Order_Data_Export_Data_Getter
{

    /**
     * Extracts all necessary data from WoCommerce and calculate all necessary additional data for xml export creating
     * Save all data to array
     *
     * @param array $orders
     * @return array|false
     * @throws Exception
     */
    public function getXMLData(array $orders)
    {
        $count = 0; //invoices counter
        $ordersData = [];
        $total = 0;
        $ordersID = [];
        $error = 'Ошибка общего характера! Обратитесь к разработчикам!';
        $this->getErrors(); //should be executed first !!!

        //Load all config data
        if (!$this->executePropertyMethod('vat_rates', $error)) return false;
        if (!$this->executePropertyMethod('exchange_rates', $error)) return false;
        if (!$this->executePropertyMethod('vat_codes', $error)) return false;
        if (!$this->executePropertyMethod('company_data', $error)) return false;
        if (!$this->executePropertyMethod('prefixes', $error)) return false;

        //Chek if using actual exchange rates
        if(!$this->checkRatesYear()) {
            $this->setErrorByName('year', $error);
            return false; // run always after loading exchanges_rates!
        }

        foreach ($orders as $orderId) {

            $data = [];

            //Order object
            $order = $this->returnOrder($orderId);

            if ($order){

                //Chek if VAT is turn on in WooCommerce
                if (!$order->get_total_tax()){
                    $this->setErrorAndOrderIDbyName('vat_total', $error, $orderId);
                    return false;
                }

                //Order exchange rate
                if (!$this->orderExchangeRates[$orderId] = $this->getOrderRate($order, $this->exchange_rates)){
                    $this->setErrorAndOrderIDbyName('exchange_rates', $error, $orderId);
                    return false;
                }

                //Order details
                $data['orderId'] = $orderId;
                if(!$data['invoiceDate'] = $this->getInvoiceDate($order)){
                    $this->setErrorAndOrderIDbyName('invoice_date', $error, $orderId);
                    return false;
                };
                $data['maturityDate'] = $this->getMaturityDate($order, $data['invoiceDate']);
                $data['varSymbol'] = $this->getVarSymbol($orderId);

                //Invoice number
                if (!$data['invoiceNumber'] = $this->getInvoiceNumber($orderId, $this->prefixes)) {
                    $this->setErrorAndOrderIDbyName('prefixes', $error, $orderId);
                    return false;
                }

                //Currency and exchange rate
                $data['currency'] = $order->get_currency();
                $data['exchangeRate'] = $this->orderExchangeRates[$orderId];

                //Client
                $data['clientBillingName'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                $data['clientBillingAddress1'] = $order->get_billing_address_1();
                $data['clientBillingAddress2'] = $order->get_billing_address_2();
                $data['clientBillingPostcode'] = $order->get_billing_postcode();
                $data['clientBillingCity'] = $order->get_billing_city();
                $data['clientBillingCountry'] =  $order->get_billing_country();


                //XML Entry
                $data['description'] = 'Prodej zboží ' . $data['invoiceNumber'];

                //Order VAT codes
                if (!$data['vatCodes'] = $this->getVatCodes($order, $this->vat_codes)){
                    $this->setErrorAndOrderIDbyName('vat_codes', $error, $orderId);
                    return false;
                };

                //Items
                if(!$this->checkItemsVat($order)) {
                    $this->setErrorAndOrderIDbyName('vat_item', $error, $orderId);
                    return false;
                }
                $data['itemsTotalValues'] = $this->getItemsTotalValues($order);

                //Fees and Shipping
                if (is_null($data['feesData'] = $this->getFees($order))) {$this->setErrorAndOrderIDbyName('fee_vat', $error, $orderId); return false;}
                if (is_null($data['shippingData'] = $this->getShipping($order))) {$this->setErrorAndOrderIDbyName('shipping_vat', $error, $orderId); return false;}


                //Total values
                $data['totalPriceInclVat'] = (float)$data['itemsTotalValues']['totalIncVat'] + (isset($data['feesData']['totalIncVat']) ? (float)$data['feesData']['totalIncVat'] : 0) + (float)$data['shippingData']['totalIncVat'];

                $data['totalPriceExlVat'] = (float)$data['itemsTotalValues']['totalExlVat'] + (isset($data['feesData']['totalIncVat']) ? (float)$data['feesData']['totalIncVat'] : 0) + (float)$data['shippingData']['totalExlVat'];

                $data['totalPriceInclVatBaseCurr'] = round($data['totalPriceInclVat'] * $data['exchangeRate'], 2);

                $data['totalItemsPriceExlVatBaseCurr'] = round($data['itemsTotalValues']['totalExlVat'] * $data['exchangeRate'], 2);

                if(!$data['totalSippingAndFeesBaseCurr'] = $this->getShippingAndFeesExlVatBaseCurr($order)){
                    $this->setErrorAndOrderIDbyName('fee_or_shipping_vat', $error, $orderId);
                    return false;
                };

                $data['totalPriceExlVatBaseCurr'] = $data['totalItemsPriceExlVatBaseCurr'] + $data['totalSippingAndFeesBaseCurr'];

                if(!$data['vatRatesValuesBaseCurr'] = $this->getVatRatesValuesBaseCurr($order)){

                    if ($this->internal_error_code == 1) {
                        $this->setErrorAndOrderIDbyName('vat_rates', $error, $orderId);
                    } elseif ($this->internal_error_code == 2) {
                        $this->setErrorAndOrderIDbyName('vat_item', $error, $orderId);
                    } elseif ($this->internal_error_code == 3) {
                        $this->setErrorAndOrderIDbyName('fee_or_shipping_vat', $error, $orderId);
                    }

                    return false;

                }

                if(!$data['vatBasesBaseCur'] = $this->getVatBasesBaseCurr($order)){

                    if ($this->internal_error_code == 1) {
                        $this->setErrorAndOrderIDbyName('vat_rates', $error, $orderId);
                    } elseif ($this->internal_error_code == 2) {
                        $this->setErrorAndOrderIDbyName('vat_item', $error, $orderId);
                    } elseif ($this->internal_error_code == 3) {
                        $this->setErrorAndOrderIDbyName('fee_or_shipping_vat', $error, $orderId);
                    }

                    return false;

                }


            } else {
                $this->setErrorAndOrderIDbyName('order', $error, $orderId);
                return false;
            }

            $ordersData[$orderId] = $data;
            $count++;
            $total += $data['totalPriceExlVatBaseCurr'];
            $ordersID[] = $orderId;
        }

        $ordersData['companyData'] = $this->company_data;
        $ordersData['userVatRates'] = $this->vat_rates;
        $ordersData['now'] = $this->getNow();
        $ordersData['count'] = $count;
        $ordersData['batchId'] = $this->generateBatchId();
        $ordersData['total'] = $total ;
        $ordersData['ordersId'] = $ordersID;
        $ordersData['exchangeRates'] = $this->exchange_rates;
        $ordersData['orderExchangeRates'] = $this->orderExchangeRates;

        return $ordersData;

    }


}