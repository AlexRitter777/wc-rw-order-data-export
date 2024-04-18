<!--Invoice template - Polish | version 1.0 | -->
<!doctype html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" type="text/css" href="/wp-content/plugins/lr-entry-export/assets/css/invoice.css">

</head>
<body>
<div class="content">
    <table class="invoice-title" border="0">
        <tr>
            <td class="col-1"></td>
            <td class="col-2">
                <h1 class="main-title">
                    <span>Faktura</span>
                    <span class="text-gray"><?= $invoiceNumber; ?></span>
                </h1>
                <p class="main-subtitle text-gray">rachunek vat</p>
            </td>
        </tr>
    </table>

    <table class="customer-supplier" border="0">
        <tr>
            <td class="col-1"><h2 class="upc text-gray fs13">sprzedawca</h2></td>
            <td class="col-2"><h2 class="upc text-gray fs13">nabywca</h2></td>
        </tr>
    </table>

    <table class="customer-supplier-data" border="0">
        <tr>
            <td class="col-1">
                <h3 class="upc fs13"><?=$companyName; ?></h3>
                <span><?=$companyStreet;?></span><br>
                <span><?=$companyZIP . ' ' . $companyCity;?></span><br>
                <span><?=$companyCountryFull['eng'];?></span>
            </td>
            <td class="col-2">
                <h3 class="upc fs13"><?=$clientBillingName;?></h3>
                <span><?=$clientBillingAddress1;?></span><br>
                <span><?=!empty($clientBillingAddress2) ? $clientBillingAddress2 : '';?></span>
                <span><?=$clientBillingPostcode . ' ' . $clientBillingCity;?></span><br>
                <span><?=!empty($clientBillingCountryFull) ? $clientBillingCountryFull :'';?></span>

            </td>
        </tr>
    </table>
    <table class="supplier-registration-data" border="0">
        <tr>
            <td class="col-1">
                <span class="text-gray">REGON</span><br>
                <span class="upc text-gray">nip</span>
            </td>
            <td class="col-2">
                <span><?=$ICO;?></span><br>
                <span><?=$DIC;?></span>
            </td>
            <td class="col-3"></td>
            <td class="col-4"></td>
        </tr>
    </table>
    <table class="order-data" border="0">
        <tr>
            <td class="col-1">
                <span class="text-gray">Nr zamówienia </span><br>
                <span class="text-gray">Reference</span><br>
                <span class="text-gray">Tytuł płatności</span><br>
                <span class="text-gray">Forma płatności</span>
            </td>
            <td class="col-2">
                <span><?= $orderNumber;?></span><br>
                <span><?= $varSymbol ;?></span><br>
                <span><?= $paymentMethod; ?></span><br>
                <span><?= $currency; ?></span>
            </td>
            <td class="col-3"></td>
            <td class="col-4">
                <span class="text-gray">Data wystawienia </span><br>
                <span class="text-gray">Termin płatności</span><br>
                <span class="text-gray">Data dostawy usługi</span><br>
                <span>&#160</span>
            </td>
            <td class="col-5">
                <span><?= $invoiceDate; ?></span><br>
                <span><?= $maturityDate; ?></span><br>
                <span><?= $invoiceDate; ?></span><br>
                <span>&#160</span>
            </td>
        </tr>
    </table>

    <table class="items" border="0">
        <tr>
            <th class="col-1"></th>
            <th class="col-2"></th>
            <th class="col-3"></th>
            <th class="col-4 upc text-gray">vat</th>
            <th class="col-5 upc text-gray">cena netto</th>
            <th class="col-6 upc text-gray">wartość netto</th>
            <th class="col-7 upc text-gray">wartość brutto</th>
        </tr>
    </table>
    <?php foreach ($itemsData as $key => $item): ?>
        <table class="item" border="0">
            <tr>
                <td class="col-1"><?=$item['quantity'] ?></td>
                <td class="col-2">psc</td>
                <td class="col-3"><?=$item['name'];?></td>
                <td class="col-4"><?=$item['tax_rate'];?> %</td>
                <td class="col-5"><?=$item['unitPriceExcVat'];?></td>
                <td class="col-6"><?=$item['allItemsPriceExlVat'];?></td>
                <td class="col-7"><?=$item['allItemsPriceIncVat'];?></td>
            </tr>
        </table>
    <?php endforeach;?>

    <?php if(!empty($feesData)):?>
        <table class="fees" border="0">
            <tr>
                <td class="col-1"></td>
                <td class="col-2"></td>
                <td class="col-3"><?=$feesData['name'];?></td>
                <td class="col-4"><?=$feesData['tax_rate'];?> %</td>
                <td class="col-5"></td>
                <td class="col-6"><?=$feesData['totalExlVat'];?></td>
                <td class="col-7"><?=$feesData['totalIncVat'];?></td>
            </tr>
        </table>
    <?php endif;?>

    <?php if(!empty($shippingData)):?>
        <table class="fees" border="0">
            <tr>
                <td class="col-1"></td>
                <td class="col-2"></td>
                <td class="col-3"><?=$shippingData['name'];?></td>
                <td class="col-4"><?=$shippingData['tax_rate'];?> %</td>
                <td class="col-5"></td>
                <td class="col-6"><?=$shippingData['totalExlVat'];?></td>
                <td class="col-7"><?=$shippingData['totalIncVat'];?></td>
            </tr>
        </table>
    <?php endif;?>

    <div class="line" border="0"><span></span></div>

    <table class="vat-total" border="0">
        <tr>
            <td class="col-1"></td>
            <td class="col-2 text-gray upc">stawka vat</td>
            <td class="col-3 text-gray upc">podstawa</td>
            <td class="col-4 text-gray upc">vat</td>
            <td class="col-5 text-gray upc">vat miejscowość czk</td>
        </tr>
    </table>

    <?php foreach ($vatRatesValues as $rate => $value): ?>
        <?php if($value != 0):?>
            <table class="vat-total" border="0">
                <tr>
                    <td class="col-1"></td>
                    <td class="col-2"><?=$rate;?> %</td>
                    <td class="col-3"><?=$vatBases[$rate];?></td>
                    <td class="col-4"><?=$value;?></td>
                    <td class="col-5"><?=$vatRatesValuesBaseCurr[$rate];?></td>
                </tr>
            </table>
        <?php endif;?>
    <?php endforeach;?>
    <div class="invoice-total-wrapper">
        <div class="invoice-total-right">
            <table class="invoice-total" border="0">
                <tr>
                    <td class="col-1">1 <?=$currency;?> = <?=$exchangeRate;?> CZK</td>
                    <td class="col-2 text-gray"><?=$totalPriceInclVat . ' ' . $currency;?></td>
                </tr>
            </table>
        </div>
    </div>
</div>
</body>
</html>

