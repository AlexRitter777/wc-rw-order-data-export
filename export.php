<?php

/* Initialize Wordpress */
define( 'BASE_PATH', find_wordpress_base_path()."/" );
define( 'WP_USE_THEMES', false );
global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header;
require(BASE_PATH . 'wp-load.php');

function find_wordpress_base_path() {
    $dir = dirname(__FILE__);
    do {
        //it is possible to check for other files here
        if( file_exists($dir."/wp-config.php") ) {
            return $dir;
        }
    } while( $dir = realpath("$dir/..") );
    return null;
}

/* Restrict access to admin only */
if( ! current_user_can( 'administrator' )) return;

/* Now run your script here ... */

$orderId = $_GET['orderId'];

//$order = new WC_Order( $orderId ); // This way

$order =  wc_get_order($orderId);

$items = $order->get_items();

echo 'Test: ' . $order->get_total();
echo "<br/>";

echo 'Order number: ' . $orderId;
echo "<br/>";

$currentDate = Date('d.m.Y');
echo 'DatumVystaveni: ' . $currentDate;
echo "<br/>";

if ($order->get_payment_method() == 'cod'){
    $maturityDate = date('d.m.Y', strtotime($currentDate. '+ 14 days'));
} else {
    $maturityDate = $currentDate;
}
echo 'DatumSplatnosti: ' . $maturityDate;
echo "<br/>";

$varSymbol = date('Y') .'0'. $orderId;
echo 'VarSymbol: ' . $varSymbol;
echo "<br/>";

$invoiceNumber = 'PL' . $varSymbol;
echo 'EvidCisloDanDokl: ' . $invoiceNumber;
echo "<br/>";

echo "<h3>Odberatel</h3>";

$clientFirstName = $order->get_billing_first_name();
$clientLastName = $order->get_billing_last_name();
$clientName = $clientFirstName . ' ' . $clientLastName;
echo 'Nazev1r: ' . $clientName;
echo "<br/>";

$address1 = $order->get_billing_address_1();
$address2 = $order->get_billing_address_2();
echo 'Ulice1r: ' . $address1;
echo "<br/>";
echo 'Ulice2r: ' . $address2;
echo "<br/>";

$postcode = $order->get_billing_postcode();
echo 'PSC: ' . $postcode;
echo "<br/>";

$city = $order->get_billing_city();
echo 'Obec: ' . $city;
echo "<br/>";

$country = $order->get_billing_country();
echo 'Stat: ' . $country;
echo "<br/>";

$description = 'Prodej zboží ' . $invoiceNumber;
echo 'Popis: ' . $description;
echo "<br/>";


$vatRates = [

    '0' => 'NulovaOss',
    '8' => 'PolskoSnizena',
    '23' => 'PolskoZakladni',

];

$curr = $order->get_currency();

echo "<h3>Polozky</h3>";

echo 'Polozka   |   Mozstvi  |  Cena jdn. bez DPH   |   Cena celkem bez DPH   |   Sazba DPH %   |   DPH   |    Celkem vc. DPH   |    Mena';
echo '<br>';
echo '<br>';
global $totalItemsExlVat;
global $totalItemsVat;
global $vat23;
global $vat8;

foreach ($order->get_items() as $item_key => $item ){

    echo $item->get_name();
    echo "   |   ";
    echo $item->get_quantity();
    echo "   |   ";

    echo $singlePriceNet = round($item->get_total()/$item->get_quantity(), 2);
    echo "   |   ";

    echo $itemPriceExlVat =  round($item->get_total(), 2);
    echo "   |   ";


    $product = wc_get_product( $item->get_product_id() );
    $tax_rates = WC_Tax::get_rates( $product->get_tax_class() );
    $tax_rate = reset($tax_rates);
    $tax_rate_value = $tax_rate['rate'];
    echo $tax_rate_value . '%';
    echo "   |   ";


    echo $vat = round($item->get_total_tax(), 2);
    echo "   |   ";


    echo $itemPriceInclVat = $itemPriceExlVat + $vat;
    echo "   |   ";


    echo $curr;
    echo "   |   ";


    $totalItemsExlVat += $itemPriceExlVat;
    $totalItemsVat += $vat;

    if ($tax_rate_value == 8) {
        $vat8 = $vat8 + $vat;
    }else{
        $vat23 = $vat23 + $vat;
    }



    echo "<br/>";




}
echo "____________________________________________________________________________________________________________";
echo "<br/>";
echo 'Polozka   |    Cena bez DPH   |   Sazba DPH %   |   DPH   |    Celkem vc. DPH  ';
echo "<br/>";
echo "<br/>";

$fees = $order->get_fees();
$fee = reset($fees);
echo $feeName = $fee->get_name();
echo "   |   ";
echo $feeValueExlVat = $fee->get_total();
echo "   |   ";

$standardVatRates = WC_Tax::get_rates_for_tax_class('standard');
$standardVatRateObj = reset($standardVatRates);
$feeVatRate = $standardVatRate =  (int)($standardVatRateObj->tax_rate);

echo $feeVatRate . '%';
echo "   |   ";

echo $feeVatTotal = $fee->get_total_tax();
echo "   |   ";
echo $feeValueInclVat = $feeValueExlVat + $feeVatTotal;
echo "   |   ";
echo "(dobirka)";

echo "<br/>";


///Shipping

echo 'Shipping costs  ';
echo "   |   ";
echo $shippingExlVat = $order->get_shipping_total();
echo "   |   ";
$shippingVatRate = $standardVatRate;
echo $shippingVatRate . "%";
echo "   |   ";
echo $shippingVat = $order->get_shipping_tax();
echo "   |   ";
echo $shippingInclVat = $shippingExlVat + $shippingVat;
echo "   |   ";
echo $order->get_shipping_method();

///Total

echo "<h3>Total</h3>";
$totalExlVat = $totalItemsExlVat + $feeValueExlVat + $shippingExlVat;
echo 'Total bez DPH  ' . $totalExlVat . ' ' . $curr;
echo "<br/>";
$totalVat = $totalItemsVat + $feeVatTotal + $shippingVat;
echo 'Total VAT ' . $totalVat . ' ' . $curr;
echo "<br/>";
echo 'Total VAT, CZK ' . round($totalVat * 24.115, 2) . ' ' . $curr;
echo "<br/>";

$totalInclVat = (float)$totalExlVat + (float)$totalVat;
echo 'Total vcetne DPH ' . $totalInclVat . ' ' . $curr;
echo "<br/>";
echo 'Total Vat 8% ' . $vat8;
echo "<br/>";
echo 'Total Vat 8%, CZK ' . round($vat8 * 24.115, 2);
echo "<br/>";
echo 'Total Vat 23% ' . $totalVat23 = (float)$vat23 + (float)$feeVatTotal + (float)$shippingVat;
echo "<br/>";
echo 'Total Vat 23%, CZK ' .  round($totalVat23 * 24.115, 2);

///XML

echo "<h3>Other data for XML</h3>";

echo "Mena " . $curr;
echo "<br/>";

echo "Kurz " . $kurz = 24.115;
echo "<br/>";

echo "CelkemBezDPH " . $totalExlVat;
echo "<br/>";

echo "CelkemVcetneDPH " . $totalInclVat;
echo "<br/>";

echo 'ZaklMena';
echo "<br/>";

echo 'CelkemBezDPH ' . round($totalExlVat * $kurz, 2);
echo "<br/>";

echo 'CelkemVcetneDPH ' . round($totalInclVat * $kurz, 2);
echo "<br/>";

echo 'UcetniRadkyDokladu';
echo "<br/>";

echo 'Castka ' . round($totalItemsExlVat * $kurz, 2);
echo "<br/>";

echo 'Popis ' . "prodej zbozi $invoiceNumber";
echo "<br/>";

echo 'Castka ' . (round($shippingExlVat * $kurz, 2) + round($feeValueExlVat * $kurz, 2));
echo "<br/>";

echo 'Popis ' . "prodej zbozi $invoiceNumber-preprava";
echo "<br/>";



///////////////////////////////////////////////////////////////////////
/*$product = wc_get_product( 4316 );
$tax_rates = WC_Tax::get_rates( $product->get_tax_class() );
debug($tax_rates);echo "<br/>";*/






///////////////////////////////////////////////////////////////////////////
$fees = $order->get_fees();
//debug($fees);

//debug($order);

$order->get_items();
//debug($items);

function debug($arr) {
    echo '<pre>' . print_r($arr, true) . '</pre>';
}