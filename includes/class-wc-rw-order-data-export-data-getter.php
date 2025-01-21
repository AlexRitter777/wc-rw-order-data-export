<?php



/**
 * Class Wc_Rw_Order_Data_Export_Data_Getter
 * Handles data extraction and preparation for WooCommerce RW Order Data Export.
 */
class Wc_Rw_Order_Data_Export_Data_Getter
{

    protected int $internal_error_code;

    protected array $countries;
    protected array $company_data;
    protected array $exchange_rates;
    protected array $vat_codes;
    protected array $prefixes;
    protected array $payment_methods;
    protected array $vat_rates;
    protected array $orderExchangeRates;
    protected array $errors;

    protected string $report_id; //uniq Id for XML or CSV reports


    public function __construct($report_id = '')
    {
        $this->report_id = $report_id;
    }



    /**
     * Loads properties from config files.
     *
     * @param string $property The property name (without extension).
     * @return bool
     */
    protected function getProperty(string $property) : bool
    {

        $property_path = plugin_dir_path( __DIR__ ) . "config/{$property}.php";
        if(!is_file($property_path)) return false;
        $propertyArray = require $property_path;
        if (!empty($propertyArray) && is_array($propertyArray)) {
            foreach ($propertyArray as $key => $value) {
                $this->{$property}[$key] = $value;
            }
            return true;
        }
        return false;
    }

    /**
     * Executes a method to load properties.
     *
     * @param string $property The property name.
     * @param string $error Error message to show on failure.
     * @return bool
     */
    protected function executePropertyMethod(string $property, string $error) : bool
    {
        if(!$this->getProperty($property)) {

            $propertyError = !empty($this->errors[$property]) ? $this->errors[$property] : $error;
            $this->logAndSetError($propertyError);
            return false;
        }
        return true;
    }

    /**
     * @param string $error
     */
    protected function logAndSetError(string $error){
        $report_id = $this->report_id;
        $error_id = 'wc_rw_error_' . $report_id;
        Wc_Rw_Order_Data_Export_Debug::wc_rw_order_data_export_error($error);
        set_transient( $error_id, $error, HOUR_IN_SECONDS );
    }


    /**
     * Load error messages from the configuration.
     */
    protected function getErrors() : void
    {
        $errors =  require plugin_dir_path( __DIR__ ) . 'config/errors.php';
        if (!empty($errors) && is_array($errors)) {

            foreach ($errors as $key => $value) {
                $this->errors[$key] = $value;
            }

        }

    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////



    /**
     * Executes the specified method for all items in the array.
     *
     * @param string $method_name Method name.
     * @param array $methods List of methods.
     * @param string $error Default error message.
     * @return bool
     */
    protected function executeAllPropertyMethods(string $method_name, array $methods, string $error = 'Common error. Please contact support.'): bool
    {

        if(empty($methods) || !is_array($methods)){
            Wc_Rw_Order_Data_Export_Debug::wc_rw_order_data_export_error('The provided data must be a non-empty array.');
            return false;
        }

        if(!method_exists($this, $method_name)){
            Wc_Rw_Order_Data_Export_Debug::wc_rw_order_data_export_error("Method '$method_name' does not exists.", true, 404);
            return false;
        }

        foreach ($methods as $method) {
            if(!$this->$method_name($method, $error)) return false;
        }

        return true;
    }


    /**
     * @param string $errorName
     * @param string $defaultError
     */
    protected function setErrorByName(string $errorName, string $defaultError){
        $report_id = $this->report_id;
        $error_id = 'wc_rw_error_' . $report_id;
        $error = !empty($this->errors[$errorName]) ? ($this->errors[$errorName]) : $defaultError;
        set_transient( $error_id, $error, HOUR_IN_SECONDS );
    }


    /**
     * Save error message and order ID to the DB.
     *
     * @param string $errorName
     * @param string $defaultError
     * @param string $orderID
     */
    public function setErrorAndOrderIDbyName(string $errorName, string $defaultError, string $orderID) : void
    {
        $report_id = $this->report_id;
        $error_id = 'wc_rw_error_' . $report_id;
        $error = !empty($this->errors[$errorName]) ? $this->errors[$errorName] : $defaultError;
        $error.= ' Номер заказа - ' . $orderID;
        set_transient( $error_id, $error, HOUR_IN_SECONDS );
    }


    /**
     * Get array property from class object
     *
     * @param string $name
     * @return array
     */

    public function getClassProperty(string $name) : array
    {
        return $this->{$name};
    }


    /**
     * Check if year exchange rates are set for the current year
     *
     * @return bool
     */
    public function checkRatesYear() : bool
    {

        if(isset($this->exchange_rates['YEAR']) && $this->exchange_rates['YEAR'] !== Date('Y')){
            return false;
        }
        return true;
    }


    /**
     * Return WoCommerce order object by order ID
     *
     * @param int $orderId
     * @return bool|WC_Order
     */
    public function returnOrder(int $orderId)
    {
        return wc_get_order($orderId);
    }


    /**
     * Returns current date in d.m.Y format
     *
     * @return string
     */
    public function getCurrentDate() : string
    {
        return Date('d.m.Y');
    }

    /**
     * Returns invoice date
     *
     * Checks if order(post) meta field invoice_date has a value.
     * If Yes - returns this value.
     * If No - records current date to this field and return current date value.
     *
     * @param WC_Order $order
     * @return string
     */
    public function getOrSetInvoiceDate(WC_Order $order) : string
    {
        $orderId = $order->get_id();
        if (!empty($invoiceDate = $order->get_meta('wc_wr_order_data_export_invoice_date'))){

            return $invoiceDate;
        }
        $invoiceDate = $this->getCurrentDate();

        update_post_meta( $orderId, 'wc_wr_order_data_export_invoice_date', $invoiceDate);

        return $invoiceDate;

    }

    /**
     * Returns credit note date
     *
     * Checks if order(post) meta field credit_note_date has a value.
     * If Yes - returns this value.
     * If No - records current date to this field and return current date value.
     *
     * @param WC_Order $order
     * @return string
     */
        protected function getOrSetCreditNoteDate(WC_Order $order) : string
    {
        $orderId = $order->get_id();
        if (!empty($creditNoteDate = $order->get_meta('wc_wr_order_data_export_credit_note_date'))){

            return $creditNoteDate;
        }
        $creditNoteDate = $this->getCurrentDate();
        update_post_meta( $orderId, 'wc_wr_order_data_export_credit_note_date', $creditNoteDate);
        return $creditNoteDate;

    }

    /**
     * Returns proforma date
     *
     * Checks if order(post) meta field proforma_date has a value.
     * If Yes - returns this value.
     * If No - records current date to this field and return current date value.
     *
     * @param WC_Order $order
     * @return string
     */
    private function getOrSetProformaDate(WC_Order $order) : string
    {
        $orderId = $order->get_id();
        if (!empty($proformaDate = $order->get_meta('wc_wr_order_data_export_proforma_date'))){

            return $proformaDate;
        }
        $proformaDate = $this->getCurrentDate();
        update_post_meta( $orderId, 'wc_wr_order_data_export_proforma_date', $proformaDate);
        return $proformaDate;

    }

    /**
     * Returns invoice date
     *
     * @param WC_Order $order
     * @return bool|string
     */
    public function getInvoiceDate(WC_Order $order)
    {

        if (!empty($invoiceDate = $order->get_meta('wc_wr_order_data_export_invoice_date'))){

            return $invoiceDate;
        }

        return false;

    }


    /**
     * Gets the maturity date of the invoice based on the payment method.
     *
     * For the "Cash on Delivery" (COD) payment method, the maturity date is set to today's date + 14 days.
     * For all other payment methods, the maturity date is set to today's date.
     *
     * @param object $order
     * @param string $currentDate
     * @return string
     */
    protected function getMaturityDate(object $order, string $currentDate) : string
    {
        if ($order->get_payment_method() == 'cod'){
            $maturityDate = date('d.m.Y', strtotime($currentDate. '+ 14 days'));
        } else {
            $maturityDate = $currentDate;
        }

        return $maturityDate;
    }

    /**
     * Gets the maturity date of the credit note
     *
     * For all cases, the maturity date of the credit note is set to today's date + 30 days.
     *
     * @param string $currentDate
     * @return string
     */
    protected function getCreditNoteMaturityDate(string $currentDate) : string
    {

        return date('d.m.Y', strtotime($currentDate. '+ 30 days'));

    }

    /**
     * Gets the maturity date of the proforma
     *
     * For all cases, the maturity date of the proforma is set to today's date + 5 days.
     *
     * @param string $currentDate
     * @return string
     */
    protected function getProformaMaturityDate(string $currentDate) : string
    {

        return date('d.m.Y', strtotime($currentDate. '+ 5 days'));

    }


    /**
     * Returns the variable symbol (var symbol) of the invoice.
     *
     * The variable symbol is generated using the current year and the WooCommerce order ID.
     *
     * @param int $orderId
     * @return string
     */
    protected function getVarSymbol(int $orderId) : string
    {
        $order = $this->returnOrder($orderId);
        $invoiceDate = $this->getInvoiceDate($order);
        if(!empty($invoiceDate)){
            $invoiceYear = date('Y', strtotime($invoiceDate));
        }else{
            $invoiceYear = date('Y');
        }
        return $invoiceYear .'0'. $orderId;
    }


    /**
     * Creates an invoice number using the country prefix, the current year, and the WooCommerce order ID.
     *
     * @param int $orderId
     * @param array $prefixes
     * @return bool|string
     */
    protected function getInvoiceNumber(int $orderId, array $prefixes)
    {
        $order = $this->returnOrder($orderId);
        $invoiceDate = $this->getInvoiceDate($order);
        if(!empty($invoiceDate)){
            $invoiceYear = date('Y', strtotime($invoiceDate));
        }else{
            $invoiceYear = date('Y');
        }

        $siteURL = $_SERVER['SERVER_NAME'];
        if (!empty($siteURL)) {
            foreach ($prefixes as $url => $prefix) {
                if ($siteURL == $url) {
                    return strtoupper($prefix) . $invoiceYear . '0' . $orderId;
                }
            }
        }
        return false;
    }


    /**
     * Creates the order number using the WooCommerce order ID and a country-specific prefix.
     *
     * @param int $orderId
     * @param array $prefixes
     * @return string
     */
    protected function getOrderNumber(int $orderId, array $prefixes) : string
    {
        $siteURL = $_SERVER['SERVER_NAME'];
        $orderPrefix = '';
        foreach ($prefixes as $url => $prefix) {
            if ($siteURL == $url) $orderPrefix = $prefix;
        }
        return $orderId . $orderPrefix;
    }


    /**
     * Retrieves the exchange rate for the current order's currency.
     *
     * The method compares the currency code of the current WooCommerce order with a user-defined configuration array
     * that maps currencies to exchange rates. If a matching currency is found, the corresponding exchange rate is returned.
     *
     * @param object $order
     * @param array $currencies
     * @return float|boolean
     */
    protected function getOrderRate(object $order, array $currencies)
    {
        $curr = $order->get_currency();
        foreach ($currencies as $code => $rate) {
            if ($code == $curr) {
                return $rate;
            }
        }

        return false;
    }

    /**
     * Retrieves or sets the exchange rate for the current order's currency.
     *
     * This method first checks if the exchange rate is already saved in the order metadata.
     * If not, it looks for the exchange rate in the provided configuration array based on the order's currency.
     * Returns the exchange rate if found, otherwise returns false.
     *
     * @param object $order
     * @param array $currencies
     * @return float|boolean
     */
    protected function getOrSetOrderRate(object $order, array $currencies)
    {
        if(!empty($exchange_rate = $order->get_meta( 'wc_wr_order_data_export_order_exchange_rate' ))){
            return $exchange_rate;
        }

        $curr = $order->get_currency();
        foreach ($currencies as $code => $rate) {
            if ($code == $curr) {
                return $rate;
            }
        }

        return false;
    }


    /**
     * Retrieves the full country name of the client based on the billing country code.
     *
     * @param WC_Order $order
     * @return array|bool
     */
    protected function getOrderClientCountry(WC_Order $order) {

        $currentCountryCode = $order->get_billing_country();

        foreach ($this->countries as $code => $value) {

            if ($code == $currentCountryCode) {

                return $value;

            }
        }

        return false;
    }


    /**
     * Retrieves the payment method name for the order.
     *
     * The method compares the payment method code from the WooCommerce order with a user-defined list of payment methods
     * and returns the corresponding name for the payment method.
     *
     * @param WC_Order $order
     * @param array $paymentMethods
     * @return bool|array
     */
    protected function getOrderPaymentMethod(WC_Order $order, array $paymentMethods)
    {
        $orderPaymentMethod  = $order->get_payment_method();
        foreach ($paymentMethods as $paymentMethod => $value) {
            if ($paymentMethod == $orderPaymentMethod) {
                return $value;

            }
        }
        return false;
    }


    /**
     * Retrieves the sum of values for each VAT rate applied to the order.
     *
     * @param WC_Order $order
     * @return array|bool
     */
    protected function getVatRatesValues(WC_Order $order)
    {

        $vatRatesValues = [];

        $standardVatRate = $this->getStandardVatRate();

        $itemsCounter = 0;
        $shippingAndFeeCounter = 0;

        //get every tax rate value from tax_rates
        foreach ($this->vat_rates as $key => $value){ //change property!!!

            $vatRatesValues[$value] = 0;

            //get every item from current order
            foreach ($order->get_items() as $item_key => $item ){
                //Check if product have a VAT
                if(!$item->get_total_tax()) {
                    $this->internal_error_code = 2;
                    return false;
                }
                //get product vat rate

                try {
                    $product = wc_get_product( $item->get_product_id() );
                    if(!$product){
                        throw new Exception("Error. Product was deleted.");
                    }
                    $tax_rates = WC_Tax::get_rates( $product->get_tax_class() );
                    $tax_rate = reset($tax_rates);
                    $tax_rate_value = $tax_rate['rate'];

                    if ($tax_rate_value == $value)  {
                        $vatRatesValues[$value] += round($item->get_total_tax(),2);
                        $itemsCounter++;
                    }
                }catch (Exception $e){
                    Wc_Rw_Order_Data_Export_Debug::wc_rw_order_data_export_error($e->getMessage());
                    wp_die(__('An error occurred: ', 'wc-rw-order-data-export') . $e->getMessage());
                }

            }
            /*
             * here is valid a rule, that shipping costs and fees
             * are always belong to standard VAT rate
             */
            if ($value == $standardVatRate) {
                if(!$shippingAndFeeVatRatesValues = $this->getShippingAndFeesVatValues($order)) {
                    $this->internal_error_code = 3;
                    return false;
                }
                $vatRatesValues[$value] += $shippingAndFeeVatRatesValues;
                $shippingAndFeeCounter++;
            }
        }
        /*
         * Checks if all VAT rates for items, shipping, and fees are present in the vat_rates configuration file.
         */
        if((count($order->get_items()) != $itemsCounter) || ($this->getShippingAndFeesVatValues($order) && !$shippingAndFeeCounter)) {
            $this->internal_error_code = 1;
            return false;
        }

        return $vatRatesValues;
    }

    /**
     * Retrieves the sum of values for each VAT rate in the base currency.
     *
     * This method calculates the VAT amounts for each VAT rate in the base currency by applying the order's exchange rate.
     * It compares the VAT total calculated from the difference between the price including VAT and the price excluding VAT
     * with the sum of VAT amounts from the VAT rate array.
     * If the two values do not match, the method adjusts the largest VAT rate amount to ensure accuracy.
     *
     * @param WC_Order $order
     * @return array|bool
     */
    protected function getVatRatesValuesBaseCurr(WC_Order $order)
    {

        $vatRatesValues = $this->getVatRatesValues($order);
        $vatRatesValuesBaseCurr = [];

        if($vatRatesValues) {

            foreach ($vatRatesValues as $rate => $value) {

                $vatRatesValuesBaseCurr[$rate] = round($value * $this->orderExchangeRates[$order->get_id()], 2);

            }

            $totalInclVatOrigin = $this->getTotalIncVatBaseCurr($order);
            $totalExclVatOrigin = $this->getTotalExlVatBaseCurr($order);
            $vatOrigin = $totalInclVatOrigin - $totalExclVatOrigin;
            $vat = array_sum($vatRatesValuesBaseCurr);

            if($vatOrigin !== $vat){

                $diff = round($vatOrigin - $vat, 2);
                //$diff = round($vat - $vatOrigin, 2);
                $key = array_keys($vatRatesValuesBaseCurr, max($vatRatesValuesBaseCurr));
                $vatRatesValuesBaseCurr[$key[0]] = $vatRatesValuesBaseCurr[$key[0]] + $diff;

            }

            return $vatRatesValuesBaseCurr;
        }

        return false;
    }

    /**
     * Retrieves the sum of VAT base values for each VAT rate.
     *
     * @param WC_Order $order
     * @return array|bool
     */
    protected function getVatBases(WC_Order $order) {

        $vatBases = [];

        $standardVatRate = $this->getStandardVatRate();
        $itemsCounter = 0;
        $shippingAndFeeCounter = 0;

        foreach ($this->vat_rates as $key => $value){

            $vatBases[$value] = 0;

            foreach ($order->get_items() as $item_key => $item ){
                if(!$item->get_total_tax()) {
                    $this->internal_error_code = 2;
                    return false;
                }
                try {
                    $product = wc_get_product( $item->get_product_id() );
                    if(!$product){
                        throw new Exception("Error. Product was deleted.");
                    }
                    $tax_rates = WC_Tax::get_rates( $product->get_tax_class() );
                    $tax_rate = reset($tax_rates);
                    $tax_rate_value = $tax_rate['rate'];

                    if ($tax_rate_value == $value)  {
                        $vatBases[$value] += round($item->get_total(),2);
                        $itemsCounter++;
                    }
                }catch (Exception $e){
                    Wc_Rw_Order_Data_Export_Debug::wc_rw_order_data_export_error($e->getMessage());
                    wp_die(__('An error occurred: ', 'wc-rw-order-data-export') . $e->getMessage());
                }
            }
            /*
             * here is valid a rule, that shipping costs and fees
             * are always belong to standard VAT rate
             */
            if ($value == $standardVatRate) {

                if(!$shippingAndFeesExlVat = $this->getShippingAndFeesExlVat($order)){
                    $this->internal_error_code = 3;
                    return false;
                }
                $vatBases[$value] += round($shippingAndFeesExlVat,2);
                $shippingAndFeeCounter++;
            }
        }


        // Checks if all VAT rates for items, shipping, and fees are present in the vat_rates configuration file.

        if((count($order->get_items()) != $itemsCounter) || ($this->getShippingAndFeesExlVat($order) && !$shippingAndFeeCounter)) {
            $this->internal_error_code = 1;
            return false;
        }

        return $vatBases;

    }


    /**
     * Retrieves the sum of VAT base values for each VAT rate in the base currency.
     *
     * It compares the total price without VAT, which is the sum of items,
     * shipping, and fees without VAT, with the total price excluding VAT calculated from the VAT base array.
     * If the two values do not match, the method adjusts the highest VAT base value to correct the difference.
     *
     * @param WC_Order $order
     * @return array|false
     */
    protected function getVatBasesBaseCurr(WC_Order $order){

        $vatBases = $this->getVatBases($order);
        $vatBasesBaseCurr = [];

        if($vatBases){

            foreach ($vatBases as $vatValue => $vatBaseValue){

                $vatBasesBaseCurr[$vatValue] = round($vatBaseValue * $this->orderExchangeRates[$order->get_id()], 2);

            }
            $exchangeRate = $this->orderExchangeRates[$order->get_id()];
            $totalExclVatOrigin = round($this->getItemsTotalValues($order)['totalExlVat'] * $exchangeRate, 2) + round($this->getShipping($order)['totalExlVat'] * $exchangeRate, 2) + round((isset($this->getFees($order)['totalExlVat']) ? $this->getFees($order)['totalExlVat'] : 0) * $exchangeRate, 2);
            $totalExlVat = array_sum($vatBasesBaseCurr);

            if($totalExlVat !== $totalExclVatOrigin){

                $diff = round($totalExclVatOrigin - $totalExlVat, 2);
                $key = array_keys($vatBasesBaseCurr, max($vatBasesBaseCurr));
                $vatBasesBaseCurr[$key[0]] = $vatBasesBaseCurr[$key[0]] + $diff;

            }
            return $vatBasesBaseCurr;
        }
        return false;
    }


    /**
     * Returns an array of data for each item in the order.
     *
     * The returned data includes the item name, quantity, tax rate, unit price excluding VAT,
     * total price excluding VAT, and total price including VAT for each item in the order.
     * If at least one product does not have VAT applied, the method returns false.
     *
     * @param WC_Order $order
     * @return array|bool
     */
    protected function getItemsData(WC_Order $order)
    {
        $itemData = [];
        $i = 0;
        foreach ($order->get_items() as $key => $item) {
            if(!$item->get_total_tax()) return false;
            $i++;
            $alterProductName = get_post_meta($item->get_product_id(),'_wc_rw_order_data_export_alter_product_name', true);
            $itemData[$i]['name'] = !empty($alterProductName) ? $alterProductName : $item->get_name();
            $itemData[$i]['quantity'] = $item->get_quantity();

            $product = wc_get_product( $item->get_product_id() );

            if(!$product){
                return false;
            }

            $tax_rates = WC_Tax::get_rates( $product->get_tax_class());
            $tax_rate = reset($tax_rates);
            $itemData[$i]['tax_rate'] = $tax_rate['rate'];

            $itemData[$i]['unitPriceExcVat'] = round($item->get_total()/$item->get_quantity(), 2);
            $itemData[$i]['allItemsPriceExlVat'] =  round($item->get_total(), 2);
            $vat = round($item->get_total_tax(), 2);
            $itemData[$i]['allItemsPriceIncVat'] = $itemData[$i]['allItemsPriceExlVat'] + $vat;

        }
        return $itemData;

    }

    /**
     * Checks if all items in the order have VAT applied.
     *
     * This method verifies that VAT is applied to every item in the WooCommerce order.
     * It returns false if at least one item does not have VAT, otherwise it returns true.
     *
     * @param WC_Order $order
     * @return bool
     */
    protected function checkItemsVat(WC_Order $order) : bool
    {
        foreach ($order->get_items() as $key => $item) {
            if (!$item->get_total_tax()) return false;
        }
        return true;

    }

    /**
     * Retrieves the total values of all item prices in the order, both including and excluding VAT.
     *
     * @param WC_Order $order
     * @return array
     */
    protected function getItemsTotalValues(WC_Order $order) : array
    {
        $totalValues['totalExlVat'] = 0;
        $totalValues['totalIncVat'] = 0;
        foreach ($order->get_items() as $key => $item) {
            $itemsPriceExlVat =  round($item->get_total(), 2);
            $vat = round($item->get_total_tax(), 2);
            $itemsPriceIncVat = $itemsPriceExlVat + $vat;
            $totalValues['totalExlVat'] += $itemsPriceExlVat;
            $totalValues['totalIncVat'] += $itemsPriceIncVat;
        }

        return $totalValues;
    }


    /**
     * Extracts fees data from WooCommerce if the client chose the Cash on Delivery (COD) payment method.
     *
     * This method assumes that the VAT rate for the COD fee is always equal to the standard VAT rate.
     * It retrieves the fee's name, total amount excluding VAT, total VAT, and total amount including VAT.
     *
     * - Returns null if the COD fee is entered without VAT.
     * - Returns 0 if there are no fee costs.
     *
     * @param WC_Order $order
     * @return array|int|null
     */
    protected function getFees(WC_Order $order)
    {
        $feesData = [];
        if($fees = $order->get_fees()){

            $fee = reset($fees);
            if(!$feesData['vatTotal'] = $fee->get_total_tax()) return null;
            $feesData['vatTotal'] = round($feesData['vatTotal'], 2);
            $alterCodName = get_option('wc_rw_order_data_export_cod_alter_name');
            $feesData['name'] = !empty($alterCodName) ? $alterCodName : $fee->get_name();
            $feesData['totalExlVat'] = $fee->get_total();
            $feesData['tax_rate'] = $this->getStandardVatRate();
            $feesData['totalIncVat'] = $feesData['totalExlVat'] + $feesData['vatTotal'];

            return $feesData;
        }
        return 0;
    }


    /**
     * Extracts shipping data from WooCommerce.
     *
     * This method assumes that the VAT rate for the shipping is always equal to the standard VAT rate.
     * It retrieves the fee's name, total amount excluding VAT, total VAT, and total amount including VAT.
     *
     * - Returns null if the Shipping is entered without VAT.
     * - Returns 0 if there are no Shipping costs.
     *
     *
     * @param WC_Order $order
     * @return array|int|null
     */
    protected function getShipping(WC_Order $order)
    {
        $shippingData = [];
        if($shippingData['totalExlVat'] = $order->get_shipping_total()) {

            if(!$shippingData['vatTotal'] = $order->get_shipping_tax()) return null;
            $alterShippingName = get_option('wc_rw_order_data_export_shipping_alter_name');
            $shippingData['name'] = !empty($alterShippingName) ? $alterShippingName : $order->get_shipping_method();
            $shippingData['tax_rate'] = $this->getStandardVatRate();
            $shippingData['totalIncVat'] = $shippingData['totalExlVat'] + $shippingData['vatTotal'];

            return $shippingData;

        }

        return 0;

    }

    /**
     * Returns the sum of VAT values for shipping and fees in the order.
     *
     * @param WC_Order $order
     * @return float
     */
    protected function getShippingAndFeesVatValues(WC_Order $order) : float
    {
        $fees = $this->getFees($order);
        if(is_null($fees)) return false; // Fees VAT is not set up
        if($fees){
            $feeVatTotal = $fees['vatTotal'];
        } else {
            $feeVatTotal = 0;
        }

        $shipping = $this->getShipping($order);
        if(is_null($shipping)) return false; // Shipping VAT is not set up
        if($shipping){
            $shippingVatTotal = $shipping['vatTotal'];
        } else {
            $shippingVatTotal = 0;
        }

        return round(($feeVatTotal + $shippingVatTotal), 2);

    }


    /**
     * Returns the total sum of shipping and fees excluding VAT.
     *
     * @param WC_Order $order
     * @return float
     */
    protected function getShippingAndFeesExlVat(WC_Order $order) : float
    {

        $fees = $this->getFees($order);
        if(is_null($fees)) return false; // Fees VAT is not set up
        if($fees){
            $feeExlVat = $fees['totalExlVat'];
        } else {
            $feeExlVat = 0;
        }

        $shipping = $this->getShipping($order);
        if(is_null($shipping)) return false; // Shipping VAT is not set up
        if($shipping){
            $shippingExlVat = $shipping['totalExlVat'];
        } else {
            $shippingExlVat = 0;
        }

        return round(($feeExlVat + $shippingExlVat), 2);

    }


    /**
     * Calculates the total shipping and fees costs in the base currency.
     *
     * @param WC_Order $order
     * @return bool|float
     */
    protected function getShippingAndFeesExlVatBaseCurr(WC_Order $order)
    {
        $exchangeRate = $this->orderExchangeRates[$order->get_id()];
        if($shippingAndFees = $this->getShippingAndFeesExlVat($order)){
            return round($shippingAndFees * $exchangeRate, 2);
        }
        return false;
    }


    /**
     * Calculates the total order price excluding VAT in the base currency.
     *
     *
     * @param WC_Order $order
     * @return float
     */
    protected function getTotalExlVatBaseCurr(WC_Order $order) : float
    {
        $exchangeRate = $this->orderExchangeRates[$order->get_id()];
        $shippingExlVat = $this->getShipping($order)['totalExlVat'] ?? 0;
        $feesExlVat = $this->getFees($order)['totalExlVat'] ?? 0;
        return round(($this->getItemsTotalValues($order)['totalExlVat'] * $exchangeRate),2) + round(($shippingExlVat + $feesExlVat) * $exchangeRate, 2);

    }

    /**
     * Calculates the total order price including VAT in the base currency.
     *
     * @param WC_Order $order
     * @return float
     */
    protected function getTotalIncVatBaseCurr(WC_Order $order) : float
    {
        $exchangeRate = $this->orderExchangeRates[$order->get_id()];
        $shippingIncVat = $this->getShipping($order)['totalIncVat'] ?? 0;
        $feesIncVat = $this->getFees($order)['totalIncVat'] ?? 0;
        return round(($this->getItemsTotalValues($order)['totalIncVat']  + $shippingIncVat + $feesIncVat) * $exchangeRate, 2);

    }


    /**
     * Finds VAT codes from the vat_codes.php configuration file based on the order's billing country.
     *
     * @param WC_Order $order
     * @param array $vatCodesAll
     * @return false|mixed
     */
    protected function getVatCodes(WC_Order $order, array $vatCodesAll)
    {
        $country = $order->get_billing_country();

        foreach ($vatCodesAll as $orderCountry => $rates) {
            if ($orderCountry == $country){
                return $rates;
            }
        }
        return false;

    }

    /**
     * Generates a unique import batch ID using numbers and capital letters.
     *
     * The format of the generated ID is XXXXXXXX-XXXX-XXXX-XXXXXXXXXXXX, where X represents a hexadecimal digit.
     *
     * @return string
     * @throws Exception
     */
    protected function generateBatchId() : string
    {
        return strtoupper(bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(6)));
    }


    /**
     * Returns the current date and time.
     *
     * @return string
     */
    protected function getNow() : string
    {
        return date('d.m.Y h:m:s');
    }


    /**
     * Returns the standard VAT rate from WooCommerce.
     *
     * @return int
     */
    protected function getStandardVatRate() : int {
        $standardVatRates = WC_Tax::get_rates_for_tax_class('standard');
        $standardVatRateObj = reset($standardVatRates);
        return (int)($standardVatRateObj->tax_rate);
    }

    /**
     * Returns the document date and maturity date based on the document type.
     *
     * @param WC_Order $order
     * @param string $invoice_type
     * @return array
     */
    protected function getInvoiceDates(WC_Order $order, string $invoice_type = 'invoice'): array
    {
        $data =[];

        if($invoice_type === 'invoice'){
            $data['invoiceDate'] = $this->getOrSetInvoiceDate($order);
            $data['maturityDate'] = $this->getMaturityDate($order, $data['invoiceDate']);
        } elseif ($invoice_type === 'creditnote'){
            $data['creditNoteDate'] = $this->getOrSetCreditNoteDate($order);
            $data['maturityDate'] = $this->getCreditNoteMaturityDate($data['creditNoteDate']);
        } elseif ($invoice_type === 'proforma') {
            $data['proformaDate'] = $this->getOrSetProformaDate($order);
            $data['maturityDate'] = $this->getProformaMaturityDate($data['proformaDate']);
        }
        return $data;
    }


    /**
     * Returns common data for invoices, whether they include VAT or not.
     *
     * @param string $orderId
     * @param WC_Order $order
     * @param string $error
     * @param string $invoice_type
     * @return array|bool
     */
    protected function getCommonPdfData (string $orderId, WC_Order $order, string $error, string $invoice_type = 'invoice')
    {

        // Order exchange rate
        if (!$this->orderExchangeRates[$orderId] = $this->getOrSetOrderRate($order, $this->exchange_rates)) {
            $this->setErrorByName('exchange_rates', $error);
            return false;
        }

        //Order details
        $data['orderId'] = $orderId;

        $data +=  $this->getInvoiceDates($order, $invoice_type);

        $data['varSymbol'] = $this->getVarSymbol($orderId);
        $data['orderNumber'] = $this->getOrderNumber($orderId, $this->prefixes);


        // Invoice number
        if (!$data['invoiceNumber'] = $this->getInvoiceNumber($orderId, $this->prefixes)) {
            $this->setErrorByName('prefixes', $error);
            return false;
        }

        // Credit note number
        if (!$data['creditnoteNumber'] = $this->getCreditNoteNumber($orderId, $this->prefixes)) {
            $this->setErrorByName('prefixes', $error);
            return false;
        }

        // Proforma number
        if (!$data['proformaNumber'] = $this->getProformaNumber($orderId, $this->prefixes)) {
            $this->setErrorByName('prefixes', $error);
            return false;
        }


        // Currency and exchange rate
        $data['currency'] = $order->get_currency();
        $data['exchangeRate'] = $this->orderExchangeRates[$orderId];


        // Payment method
        if (!$data['paymentMethod'] = $this->getOrderPaymentMethod($order, $this->payment_methods)) {
            $this->setErrorByName('payment_methods', $error);
            return false;
        }

        //Seller
        $data += $this->getCompanyData($this->company_data);

        //Client
        $data['clientBillingName'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $data['clientBillingAddress1'] = $order->get_billing_address_1();
        $data['clientBillingAddress2'] = $order->get_billing_address_2();
        $data['clientBillingPostcode'] = $order->get_billing_postcode();
        $data['clientBillingCity'] = $order->get_billing_city();


        //Client Billing Country
        if (!$data['clientBillingCountryFull'] = $this->getOrderClientCountry($order)) {
            $this->setErrorByName('countries', $error);
            return false;
        }

        return $data;

    }

    /**
     * Creates a credit note number using the country prefix and WooCommerce order ID.
     *
     * @param int $orderId
     * @param array $prefixes
     * @return bool|string
     */
    protected function getCreditNoteNumber(int $orderId, array $prefixes)
    {
        $siteURL = $_SERVER['SERVER_NAME'];
        if (!empty($siteURL)) {
            foreach ($prefixes as $url => $prefix) {
                if ($siteURL == $url) {
                    return 'OD000' . $orderId . strtoupper($prefix);
                }
            }
        }
        return false;

    }

    /**
     * Creates a proforma number using the country prefix and WooCommerce order ID.
     *
     * @param int $orderId
     * @param array $prefixes
     * @return bool|string
     */
    protected function getProformaNumber(int $orderId, array $prefixes)
    {
        $siteURL = $_SERVER['SERVER_NAME'];
        if (!empty($siteURL)) {
            foreach ($prefixes as $url => $prefix) {
                if ($siteURL == $url) {
                    return 'PI000' . $orderId . strtoupper($prefix);
                }
            }
        }
        return false;

    }


    /**
     * Retrieves company data from the configuration and bank details from the options table.
     *
     * This method first retrieves company information from the configuration file.
     * It then attempts to retrieve bank details from the WordPress options table.
     * If the bank details are not set in the database, it uses the default values from the configuration file.
     *
     *
     * @param array $companyData
     * @return array
     */

    protected function getCompanyData(array $companyData): array
    {
        $result = [];
        //Company common information
        $result['companyName'] = $companyData['companyName'];
        $result['companyStreet'] = $companyData['companyStreet'];
        $result['companyCity'] = $companyData['companyCity'];
        $result['companyZIP'] = $companyData['companyZIP'];
        $result['companyCountry'] = $companyData['companyCountry'];
        $result['companyCountryFull'] = $companyData['companyCountryFull'];
        $result['ICO'] = $companyData['ICO'];
        $result['DIC'] = $companyData['DIC'];
        $result['accountant'] = $companyData['accountant'];
        $result['director'] = $companyData['director'];

        //Bank details
        $result['bankIBAN'] = !empty(get_option('wc_rw_order_data_export_bank_iban')) ? get_option('wc_rw_order_data_export_bank_iban') : $companyData['bankIBAN'];
        $result['bankSWIFT'] = !empty(get_option('wc_rw_order_data_export_bank_swift')) ? get_option('wc_rw_order_data_export_bank_swift') : $companyData['bankSWIFT'];
        $result['bankName'] = !empty(get_option('wc_rw_order_data_export_bank_name')) ? get_option('wc_rw_order_data_export_bank_name') : $companyData['bankName'];
        $result['bankStreet'] = !empty(get_option('wc_rw_order_data_export_bank_street')) ? get_option('wc_rw_order_data_export_bank_street') : $companyData['bankStreet'];
        $result['bankCity'] = !empty(get_option('wc_rw_order_data_export_bank_city')) ? get_option('wc_rw_order_data_export_bank_city') : $companyData['bankCity'];
        $result['bankZIP'] = !empty(get_option('wc_rw_order_data_export_bank_zip')) ? get_option('wc_rw_order_data_export_bank_zip') : $companyData['bankZIP'];
        $result['bankCountry'] = !empty(get_option('wc_rw_order_data_export_bank_country')) ? get_option('wc_rw_order_data_export_bank_country') : $companyData['bankCountry'];

        return $result;


    }


}