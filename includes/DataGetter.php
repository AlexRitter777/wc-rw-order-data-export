<?php

namespace includes;

use Cassandra\Date;
use FontLib\Table\Type\os2;
use WC_Countries;
use WC_Tax;


class DataGetter
{

    public $data;
    protected $countries;
    protected $company_data;
    protected $exchange_rates;
    protected $vat_codes;
    protected $invoice_settings;
    protected $prefixes;
    protected $payment_methods;
    protected $vat_rates;
    protected $orderExchangeRates;
    protected $errors;


    /**
     * Load properties from config files and write them into protected properties of this class
     *
     * @param string $property
     * @return bool
     */
    public function getProperty(string $property) : bool
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
     * Check if properties were loaded by method getProperty()
     *
     * @param string $property
     * @param string $error
     * @return bool
     */
    public function executePropertyMethod(string $property, string $error) : bool
    {
        if(!$this->getProperty($property)) {
            $_SESSION['error'] = !empty($this->errors[$property]) ? $this->errors[$property] : $error;
            return false;
        }
        return true;
    }


    /**
     * Load error list from config file errors.php to class property
     */
    public function getErrors() : void
    {
        $errors =  require plugin_dir_path( __DIR__ ) . 'config/errors.php';
        if (!empty($errors) && is_array($errors)) {

            foreach ($errors as $key => $value) {
                $this->errors[$key] = $value;
            }

        }

    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Extracts all necessary data from WoCommerce and calculate all necessary additional data for xml export creating
     * Save all data to array
     *
     * @param $orders
     * @return array|false
     * @throws \Exception
     */
    public function getXMLData($orders){

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
            $this->getError('year', $error);
            return false; // run always after loading exchanges_rates!
        }

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

                //Order exchange rate
                if (!$this->orderExchangeRates[$orderId] = $this->getOrderRate($order, $this->exchange_rates)){
                    $this->getErrorAndOrderID('exchange_rates', $error, $orderId);
                    return false;
                }

                //Order details
                $data['orderId'] = $orderId;
                if(!$data['invoiceDate'] = $this->getInvoiceDate($order)){
                    $this->getErrorAndOrderID('invoice_date', $error, $orderId);
                    return false;
                };
                $data['maturityDate'] = $this->getMaturityDate($order, $data['invoiceDate']);
                $data['varSymbol'] = $this->getVarSymbol($orderId);

                //Invoice number
                if (!$data['invoiceNumber'] = $this->getInvoiceNumber($orderId, $this->prefixes)) {
                    $this->getErrorAndOrderID('prefixes', $error, $orderId);
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
                    $this->getErrorAndOrderID('vat_codes', $error, $orderId);
                    return false;
                };

                //Items
                if(!$this->checkItemsVat($order)) {
                    $this->getErrorAndOrderID('vat_item', $error, $orderId);
                    return false;
                }
                $data['itemsTotalValues'] = $this->getItemsTotalValues($order);

                //Fees and Shipping
                if (is_null($data['feesData'] = $this->getFees($order))) {$this->getErrorAndOrderID('fee_vat', $error, $orderId); return false;}
                if (is_null($data['shippingData'] = $this->getShipping($order))) {$this->getErrorAndOrderID('shipping_vat', $error, $orderId); return false;}


                //Total values
                $data['totalPriceInclVat'] = (float)$data['itemsTotalValues']['totalIncVat'] + (float)$data['feesData']['totalIncVat'] + (float)$data['shippingData']['totalIncVat'];

                $data['totalPriceExlVat'] = (float)$data['itemsTotalValues']['totalExlVat'] + (float)$data['feesData']['totalExlVat'] + (float)$data['shippingData']['totalExlVat'];

                $data['totalPriceInclVatBaseCurr'] = round($data['totalPriceInclVat'] * $data['exchangeRate'], 2);

                $data['totalItemsPriceExlVatBaseCurr'] = round($data['itemsTotalValues']['totalExlVat'] * $data['exchangeRate'], 2);

                if(!$data['totalSippingAndFeesBaseCurr'] = $this->getShippingAndFeesExlVatBaseCurr($order)){
                    $this->getErrorAndOrderID('fee_or_shipping_vat', $error, $orderId);
                    return false;
                };

                $data['totalPriceExlVatBaseCurr'] = $data['totalItemsPriceExlVatBaseCurr'] + $data['totalSippingAndFeesBaseCurr'];

                if(!$data['vatRatesValuesBaseCurr'] = $this->getVatRatesValuesBaseCurr($order)){

                    if ($_SESSION['int_err_code'] == 1) {
                        $this->getErrorAndOrderID('vat_rates', $error, $orderId);
                    } elseif ($_SESSION['int_err_code'] == 2) {
                        $this->getErrorAndOrderID('vat_item', $error, $orderId);
                    } elseif ($_SESSION['int_err_code'] == 3) {
                        $this->getErrorAndOrderID('fee_or_shipping_vat', $error, $orderId);
                    }

                    return false;

                }

                if(!$data['vatBasesBaseCur'] = $this->getVatBasesBaseCurr($order)){

                    if ($_SESSION['int_err_code'] == 1) {
                        $this->getErrorAndOrderID('vat_rates', $error, $orderId);
                    } elseif ($_SESSION['int_err_code'] == 2) {
                        $this->getErrorAndOrderID('vat_item', $error, $orderId);
                    } elseif ($_SESSION['int_err_code'] == 3) {
                        $this->getErrorAndOrderID('fee_or_shipping_vat', $error, $orderId);
                    }

                    return false;

                }


            } else {
                $this->getErrorAndOrderID('order', $error, $orderId);
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
        if (!$this->executePropertyMethod('invoice_settings', $error)) return false;
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
                if(!$this->getOrderClientCountry($order)){
                    $this->getError('countries', $error);
                    return false;
                }
                if(!$data['clientBillingCountryFull'] = $this->getOrderClientCountry($order)[$this->invoice_settings['language']]) {
                    $this->getError('invoice_settings', $error);
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


    /**
     * Extracts all necessary data from WoCommerce and calculate all necessary additional data for invoice creating
     * Save all data to array
     *
     * @param int $orderId
     * @return array|false
     */
        public function getPDFData(int $orderId)
    {
        $data = [];
        $error = 'Ошибка общего характера! Обратитесь к разработчикам!';
        $this->getErrors(); //should be executed first !!!

        //Load all config data
        if (!$this->executePropertyMethod('exchange_rates', $error)) return false;
        if (!$this->executePropertyMethod('countries', $error)) return false;
        if (!$this->executePropertyMethod('company_data', $error)) return false;
        if (!$this->executePropertyMethod('invoice_settings', $error)) return false;
        if (!$this->executePropertyMethod('prefixes', $error)) return false;
        if (!$this->executePropertyMethod('payment_methods', $error)) return false;
        if (!$this->executePropertyMethod('vat_rates', $error)) return false;


        //Chek if using actual exchange rates
        if(!$this->checkRatesYear()) {
            $this->getError('year', $error);
            return false; // run always after lading exchanges_rates!
        }

        //Chek if exists language setting in config
        if(!isset($this->invoice_settings['language']) && empty($this->invoice_settings['language'])) {
            $this->getError('invoice_settings', $error);
            return false;
        }

        //Order object
        $order = $this->returnOrder($orderId);

        if ($order) {

            //Chek if VAT is turn on in WooCommerce
            if (!$order->get_total_tax()){
                $this->getError('vat_total', $error);
                return false;
            }

            //Order exchange rate
            if (!$this->orderExchangeRates[$orderId] = $this->getOrderRate($order, $this->exchange_rates)){
                $this->getError('exchange_rates', $error);
                return false;
            }

            //Order details
            $data['orderId'] = $orderId;
            $data['invoiceDate'] = $this->getOrSetInvoiceDate($order);
            $data['maturityDate'] = $this->getMaturityDate($order, $data['invoiceDate']);
            $data['varSymbol'] = $this->getVarSymbol($orderId);
            $data['orderNumber'] = $this->getOrderNumber($orderId, $this->prefixes);

            //Invoice number
            if (!$data['invoiceNumber'] = $this->getInvoiceNumber($orderId, $this->prefixes)) {
                $this->getError('prefixes', $error);
                return false;
            }

            //Currency and exchange rate
            $data['currency'] = $order->get_currency();
            $data['exchangeRate'] = $this->orderExchangeRates[$orderId];


            //Payment method
            if (!$data['paymentMethod'] = $this->getOrderPaymentMethod($order, $this->payment_methods)[$this->invoice_settings['language']]) {
                $this->getError('payment_methods', $error);
                return false;
            }

            //Seller
            foreach ($this->company_data as $key => $value){
                $data[$key] = $value;
            }

            //Client
            $data['clientBillingName'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $data['clientBillingAddress1'] = $order->get_billing_address_1();
            $data['clientBillingAddress2'] = $order->get_billing_address_2();
            $data['clientBillingPostcode'] = $order->get_billing_postcode();
            $data['clientBillingCity'] = $order->get_billing_city();


            //Client Billing Country
            if(!$this->getOrderClientCountry($order)){
                $this->getError('countries', $error);
                 return false;
            }
            if(!$data['clientBillingCountryFull'] = $this->getOrderClientCountry($order)[$this->invoice_settings['language']]) {
                $this->getError('invoice_settings', $error);
                return false;
            }

            //Items
            if(!$data['itemsData'] = $this->getItemsData($order)){
                $this->getError('vat_item', $error);
                return false;
            };
            $data['itemsTotalValues'] = $this->getItemsTotalValues($order);


            //Fees and Shipping
            if (is_null($data['feesData'] = $this->getFees($order))) {$this->getError('fee_vat', $error); return false;}
            if (is_null($data['shippingData'] = $this->getShipping($order))) {$this->getError('shipping_vat', $error); return false;}

            //Total
            $data['totalPriceInclVat'] = (float)$data['itemsTotalValues']['totalIncVat'] + (float)$data['feesData']['totalIncVat'] + (float)$data['shippingData']['totalIncVat'];

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

            if(!$data['vatRatesValuesBaseCurr'] = $this->getVatRatesValuesBaseCurr($order)){

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

            return $data;
        }

        $this->getError('order', $error);
        return false;

    }

    /**
     * Save to SESSION error text or default error text
     *
     * @param string $errorName
     * @param string $defaultError
     */
    public function getError(string $errorName, string $defaultError) : void
    {
        $_SESSION['error'] = !empty($this->errors[$errorName]) ? ($this->errors[$errorName]) : $defaultError;

    }

    /**
     * Save to SESSION error text or default error text and orderID
     *
     * @param string $errorName
     * @param string $defaultError
     * @param string $orderID
     */
    public function getErrorAndOrderID(string $errorName, string $defaultError, string $orderID) : void
    {
        $error = !empty($this->errors[$errorName]) ? ($this->errors[$errorName]) : $defaultError;
        $error.= ' Номер заказа - ' . $orderID;
        $_SESSION['error'] = $error;

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
     * Check if year exchange rates are set for current year
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
     * @return bool|\WC_Order
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
     * Checks if order(post) meta field invoiceDate has a value.
     * If Yes - returns this value.
     * If No - records current date to this field and return current date value.
     *
     * @param object $order
     * @return string
     */
    public function getOrSetInvoiceDate(object $order) : string
    {
        $orderId = $order->get_id();
        if (!empty($invoiceDate = $order->get_meta('invoiceDate'))){

            return $invoiceDate;
        }
        $invoiceDate = $this->getCurrentDate();
        update_post_meta( $orderId, 'invoiceDate', $invoiceDate);
        return $invoiceDate;

    }

    public function getInvoiceDate(object $order) : string
    {

        if (!empty($invoiceDate = $order->get_meta('invoiceDate'))){

            return $invoiceDate;
        }

        return false;

    }





    /**
     * Gets maturity date of invoice according payment method
     *
     * for COD payment method maturity date = today + 14 days
     * for all others methods maturity date = today
     *
     * @param object $order
     * @param string $currentDate
     * @return string
     */
    public function getMaturityDate(object $order, string $currentDate) : string
    {
        if ($order->get_payment_method() == 'cod'){
            $maturityDate = date('d.m.Y', strtotime($currentDate. '+ 14 days'));
        } else {
            $maturityDate = $currentDate;
        }

        return $maturityDate;
    }


    /**
     * Returns var symbol of invoice
     *
     * Generates var symbol from current YEAR and order number
     *
     * @param int $orderId
     * @return string
     */
    public function getVarSymbol(int $orderId) : string
    {
        return date('Y') .'0'. $orderId;
    }


    /**
     * Create invoice number from country prefix, current year adn order ID
     *
     * @param int $orderId
     * @param array $prefixes
     * @return false|string
     */
    public function getInvoiceNumber(int $orderId, array $prefixes)
    {
        $siteURL = $_SERVER['SERVER_NAME'];
        if (!empty($siteURL)) {
            foreach ($prefixes as $url => $prefix) {
                if ($siteURL == $url) {
                    return strtoupper($prefix) . date('Y') . '0' . $orderId;
                }
            }
        }
        return false;
    }

    /**
     * Creates order number from order ID and prefix
     *
     * @param int $orderId
     * @param array $prefixes
     * @return string
     */
    public function getOrderNumber(int $orderId, array $prefixes) : string
    {
        $siteURL = $_SERVER['SERVER_NAME'];
        $orderPrefix = '';
        foreach ($prefixes as $url => $prefix) {
            if ($siteURL == $url) $orderPrefix = $prefix;
        }
        return $orderId . $orderPrefix;
    }


    /**
     * Get current order exchange rate
     *
     * Method compare currency code of current order from WooCommerce
     * with user config array Currency => Exchange rate
     *
     * @param object $order
     * @param array $currencies
     * @return float|boolean
     */
    public function getOrderRate(object $order, array $currencies)
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
     * Gets client country name in different languages
     *
     * Compares current order client country code from WooCommerce with config array countries values. Gets array with countries names in different languages corresponds this code
     *
     * @param object $order
     * @return array|bool
     */
    public function getOrderClientCountry(object $order) {

        $currentCountryCode = $order->get_billing_country();

        foreach ($this->countries as $code => $values) {

            if ($code == $currentCountryCode) {

                if(is_array($values)) return $values;

            }
        }

        return false;

    }


    /**
     * Gets payment method name in different languages
     *
     * Compares current order payment method short name from WooCommerce with
     * config array payment_methods values. Gets array with payment method names
     * in different languages corresponds this short name
     *
     * @param object $order
     * @param array $paymentMethods
     * @return false|array
     */
    public function getOrderPaymentMethod(object $order, array $paymentMethods)
    {
        $orderPaymentMethod  = $order->get_payment_method();
        foreach ($paymentMethods as $paymentMethod => $values) {
            if ($paymentMethod == $orderPaymentMethod) {
                return $values;

            }
        }
        return false;

    }


    /**
     * Gets values sums of every VAT rate
     *
     * @param object $order
     * @return array|bool
     */
    public function getVatRatesValues(object $order)
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
                if(!$item->get_total_tax()) {$_SESSION['int_err_code'] = 2; return false;}
                //get product vat rate
                $product = wc_get_product( $item->get_product_id() );
                $tax_rates = WC_Tax::get_rates( $product->get_tax_class() );
                $tax_rate = reset($tax_rates);
                $tax_rate_value = $tax_rate['rate'];

                if ($tax_rate_value == $value)  {

                    $vatRatesValues[$value] += round($item->get_total_tax(),2);
                    $itemsCounter++;

                }

            }
            /*
             * here is valid a rule, that shipping costs and fees
             * are always belong to standard VAT rate
             */
            if ($value == $standardVatRate) {
                if(!$shippingAndFeeVatRatesValues = $this->getShippingAndFeesVatValues($order)) {
                    $_SESSION['int_err_code'] = 3;
                    return false;
                }
                $vatRatesValues[$value] += $shippingAndFeeVatRatesValues;
                $shippingAndFeeCounter++;
            }

        }
        /*
         * Chek if all VAT rates of items, shipping and fees are also in vat_rates config file
         */
        if((count($order->get_items()) != $itemsCounter) || ($this->getShippingAndFeesVatValues($order) && !$shippingAndFeeCounter)) {
            $_SESSION['int_err_code'] = 1;
            return false;
        }

        return $vatRatesValues;

    }

    /**
     * Gets values sums of every VAT rate in base currency
     *
     * Compares VAT sum got by difference between Price inc VAT and Price exl. VAT and
     * VAT sum got by this method like VAT array sum.
     * In case is values are not equals method corrects maximum array value
     *
     * @param object $order
     * @return array|false
     */
    public function getVatRatesValuesBaseCurr(object $order)
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
     * Gets VAT bases values sums of every VAT rate
     *
     * @param object $order
     * @return array|bool
     */
    public function getVatBases($order) {

        $vatBases = [];

        $standardVatRate = $this->getStandardVatRate();
        $itemsCounter = 0;
        $shippingAndFeeCounter = 0;

        foreach ($this->vat_rates as $key => $value){

            $vatBases[$value] = 0;

            foreach ($order->get_items() as $item_key => $item ){
                if(!$item->get_total_tax()) {$_SESSION['int_err_code'] = 2; return false;}
                $product = wc_get_product( $item->get_product_id() );
                $tax_rates = WC_Tax::get_rates( $product->get_tax_class() );
                $tax_rate = reset($tax_rates);
                $tax_rate_value = $tax_rate['rate'];

                if ($tax_rate_value == $value)  {

                    $vatBases[$value] += round($item->get_total(),2);
                    $itemsCounter++;

                }

            }
            /*
             * here is valid a rule, that shipping costs and fees
             * are always belong to standard VAT rate
             */
            if ($value == $standardVatRate) {

                if(!$shippingAndFeesExlVat = $this->getShippingAndFeesExlVat($order)){
                    $_SESSION['int_err_code'] = 3;
                    return false;
                }
                $vatBases[$value] += round($shippingAndFeesExlVat,2);
                $shippingAndFeeCounter++;

            }

        }

        /*
         * Chek if all VAT rates of items, shipping and fees are also in vat_rates config file
         */
        if((count($order->get_items()) != $itemsCounter) || ($this->getShippingAndFeesExlVat($order) && !$shippingAndFeeCounter)) {
            $_SESSION['int_err_code'] = 1;
            return false;
        }

        return $vatBases;

    }


    /**
     * Gets values sums of VAT bases for every VAT rate in base currency
     *
     * Compares total price without VAT, got by sum of items total values without VAT, shipping and fees total values without VAT with total price exl. VAT,  got by this method like VAT bases array sum.
     * In case is values are not equals method corrects maximal array value
     *
     * @param object $order
     * @return array|false
     */
    public function getVatBasesBaseCurr($order){

        $vatBases = $this->getVatBases($order);
        $vatBasesBaseCurr = [];

        if($vatBases){

            foreach ($vatBases as $vatValue => $vatBaseValue){

                $vatBasesBaseCurr[$vatValue] = round($vatBaseValue * $this->orderExchangeRates[$order->get_id()], 2);

            }
            $exchangeRate = $this->orderExchangeRates[$order->get_id()];
            $totalExclVatOrigin = round($this->getItemsTotalValues($order)['totalExlVat'] * $exchangeRate, 2) + round($this->getShipping($order)['totalExlVat'] * $exchangeRate, 2) + round($this->getFees($order)['totalExlVat'] * $exchangeRate, 2);
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
     * Returns array of items data
     *
     * Name, qty, tax_rate, unit price exl. VAT, total price exl. VAT, total price incl. VAT
     * Returns false if at least one product don't have VAT
     *
     * @param object $order
     * @return array|false
     */
    public function getItemsData(object $order)
    {
        $itemData = [];
        $i = 0;
        foreach ($order->get_items() as $key => $item) {
            if(!$item->get_total_tax()) return false;
            $i++;
            $itemData[$i]['name'] = $item->get_name();
            $itemData[$i]['quantity'] = $item->get_quantity();

            $product = wc_get_product( $item->get_product_id() );
            $tax_rates = WC_Tax::get_rates( $product->get_tax_class() );
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
     * Check if all items have VAT. Return false if at least one item has not VAT.
     *
     * @param object $order
     * @return bool
     */
    public function checkItemsVat(object $order) : bool
    {
        foreach ($order->get_items() as $key => $item) {
            if (!$item->get_total_tax()) return false;
        }
        return true;

    }

    /**
     * Gets total values of all items prices including and excluding VAT
     *
     * @param object $order
     * @return array
     */
    public function getItemsTotalValues(object $order) : array
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
     * Extracts fees data from WoCommerce in case if client chose COD payment method.
     *
     * Gets standard VAT rate value from WoCommerce. Here we are working with assertion,
     * that the Fee (COD payment) Vat rate is always equal to the standard VAT rate
     * Fee data: name, total exl. VAT, total VAT and total incl. VAT
     * Returns null in case if COD price is entered without VAT
     * Returns 0 if there are not any fee costs
     *
     * @param object $order
     * @return array|int|null
     */
    public function getFees(object $order)
    {
        $feesData = [];
        if($fees = $order->get_fees()){

            $fee = reset($fees);
            if(!$feesData['vatTotal'] = $fee->get_total_tax()) return null;
            $feesData['vatTotal'] = round($feesData['vatTotal'], 2);
            $feesData['name'] = $fee->get_name();
            $feesData['totalExlVat'] = $fee->get_total();
            $feesData['tax_rate'] = $this->getStandardVatRate();
            $feesData['totalIncVat'] = $feesData['totalExlVat'] + $feesData['vatTotal'];

            return $feesData;
        }
        return 0;
    }


    /**
     * Extracts shipping data from WoCommerce.
     *
     * Gets standard VAT rate value from WoCommerce. Here we are working with assertion,
     * that the Shipping Vat rate is always equal to the standard VAT rate
     * Shipping data: name, total exl. VAT, total VAT and total incl. VAT
     * Returns null in case if Shipping costs not allowed VAT
     * Returns 0 if there are not any shipping costs
     *
     * @param object $order
     * @return array|int|null
     */
    public function getShipping(object $order)
    {
        $shippingData = [];
        if($shippingData['totalExlVat'] = $order->get_shipping_total()) {

            if(!$shippingData['vatTotal'] = $order->get_shipping_tax()) return null;
            $shippingData['name'] = $order->get_shipping_method();
            $shippingData['tax_rate'] = $this->getStandardVatRate();
            $shippingData['totalIncVat'] = $shippingData['totalExlVat'] + $shippingData['vatTotal'];

            return $shippingData;

        }
        return 0;

    }

    /**
     * Returns shipping and fees VAT values sum
     *
     * @param object $order
     * @return float
     */
    public function getShippingAndFeesVatValues(object $order) : float
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
     * Returns shipping and fees sum excluding VAT
     *
     * @param object $order
     * @return float
     */
    public function getShippingAndFeesExlVat(object $order) : float
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
     * Calculates shipping and fees costs in a base currency
     *
     * @param object $order
     * @return false|float
     */
    public function getShippingAndFeesExlVatBaseCurr(object $order)
    {
        $exchangeRate = $this->orderExchangeRates[$order->get_id()];
        if($shippingAndFees = $this->getShippingAndFeesExlVat($order)){
            return round($shippingAndFees * $exchangeRate, 2);
        }
        return false;
    }


    /**
     * Calculates total order price excluding VAT in base currency
     *
     *
     * @param object $order
     * @return float
     */
    public function getTotalExlVatBaseCurr(object $order) : float
    {
        $exchangeRate = $this->orderExchangeRates[$order->get_id()];
        return round(($this->getItemsTotalValues($order)['totalExlVat'] * $exchangeRate),2) + round(($this->getShipping($order)['totalExlVat'] + $this->getFees($order)['totalExlVat']) * $exchangeRate, 2);

    }

    /**
     * Calculates total order price including VAT in base currency
     *
     *
     * @param object $order
     * @return float
     */
    public function getTotalIncVatBaseCurr(object $order) : float
    {
        $exchangeRate = $this->orderExchangeRates[$order->get_id()];
        return round(($this->getItemsTotalValues($order)['totalIncVat']  + $this->getShipping($order)['totalIncVat'] + $this->getFees($order)['totalIncVat']) * $exchangeRate, 2);

    }


    /**
     * Find VAT codes form vat_codes.php for Entry Import according actual order country
     *
     * @param object $order
     * @param array $vatCodesAll
     * @return false|mixed
     */
    public function getVatCodes(object $order, array $vatCodesAll)
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
     * Generates import batch ID from numbers and capital letters
     *
     * XXXXXXXX-XXXX-XXXX-XXXXXXXXXXXX
     *
     * @return string
     * @throws \Exception
     */
    public function generateBatchId() : string
    {

        return strtoupper(bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(6)));

    }


    /**
     * Returns actual date and time
     *
     * @return string
     */
    public function getNow() : string
    {
        return date('d.m.Y h:m:s');
    }


    /**
     * Return standard VAT rate from WoCommerce
     *
     * @return int
     */
    public function getStandardVatRate() : int {
        $standardVatRates = WC_Tax::get_rates_for_tax_class('standard');
        $standardVatRateObj = reset($standardVatRates);
        return (int)($standardVatRateObj->tax_rate);
    }


}