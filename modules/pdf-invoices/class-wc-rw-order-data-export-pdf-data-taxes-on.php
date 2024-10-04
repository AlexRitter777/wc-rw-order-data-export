<?php

/**
 * Class responsible for extracting data from WooCommerce and calculating all necessary data
 * for creating invoices when taxes are enabled.
 */
class Wc_Rw_Order_Data_Export_Dpf_Data_Taxes_On extends Wc_Rw_Order_Data_Export_Data_Getter
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
                         'vat_rates'],
                         $error
                        )
        ) return false;

        // Check if exchange rates are up-to-date.
        if(!$this->checkRatesYear()) {
            $this->getError('year', $error);
            return false; // run always after lading exchanges_rates!
        }


        // Retrieve WooCommerce order object.
        $order = $this->returnOrder($orderId);

        if ($order) {

            // Check if the order includes VAT and VAT is enabled in WooCommerce
            if (!$order->get_total_tax()){
                $this->getError('vat_total', $error);
                return false;
            }

            // Retrieve common invoice data.
            if($result = $this->getCommonPdfData($orderId, $order, $error, $invoice_type)) {
                $data += $result;
            } else {
                return false;
            }

            // Get items data and validate VAT.
            if(!$data['itemsData'] = $this->getItemsData($order)){
                $this->getError('vat_item', $error);
                return false;
            };
            $data['itemsTotalValues'] = $this->getItemsTotalValues($order);


            // Retrieve fees and shipping data, validate VAT.
            if (is_null($data['feesData'] = $this->getFees($order))) {$this->getError('fee_vat', $error); return false;}
            if (is_null($data['shippingData'] = $this->getShipping($order))) {$this->getError('shipping_vat', $error); return false;}

            // Calculate total price including VAT.
            $data['totalPriceInclVat'] = (float)$data['itemsTotalValues']['totalIncVat'] + (isset($data['feesData']['totalIncVat']) ? (float)$data['feesData']['totalIncVat'] : 0) + (isset($data['shippingData']['totalIncVat']) ? (float)$data['shippingData']['totalIncVat'] : 0);

            // Retrieve VAT values.
            if(!$data['vatRatesValues'] = $this->getVatRatesValues($order)){
                $this->handleVatError();
                return false;
            }

            // Retrieve VAT values in base currency.
            if(!$data['vatRatesValuesBaseCurr'] = $this->getVatRatesValuesBaseCurr($order)){
                $this->handleVatError();
                return false;
            }

            // Retrieve VAT bases.
            if(!$data['vatBases'] = $this->getVatBases($order)){
                $this->handleVatError();
                return false;
            }

            return $data;
        }

        $this->getError('order', $error);
        return false;

    }


    /**
     * Centralized error handling for VAT-related errors.
     */
    private function handleVatError()
    {
        if ($_SESSION['int_err_code'] == 1) {
            $this->getError('vat_rates', 'General error');
        } elseif ($_SESSION['int_err_code'] == 2) {
            $this->getError('vat_item', 'General error');
        } elseif ($_SESSION['int_err_code'] == 3) {
            $this->getError('fee_or_shipping_vat', 'General error');
        }
    }


}