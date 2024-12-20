<!--Credit Note template - English | version 1.0 | -->
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" type="text/css" href="/wp-content/plugins/wc-rw-order-data-export/modules/pdf-invoices/assets/css/invoice.css">

</head>
<body>
<div class="content">
    <table class="invoice-title" border="0">
        <tr>
            <td class="col-1"></td>
            <td class="col-2">
                <h1 class="main-title">
                    <span><?= __('Credit Note', 'wc-rw-order-data-export'); ?></span>
                    <span class="text-gray"><?= $creditnoteNumber; ?></span>
                </h1>
                <p class="main-subtitle text-gray"><?= __('to invoice', 'wc-rw-order-data-export') . ' ' . $invoiceNumber; ?></p>
            </td>
        </tr>
    </table>

    <table class="customer-supplier" border="0">
        <tr>
            <td class="col-1"><h2 class="upc text-gray fs13"><?= __('Supplier', 'wc-rw-order-data-export'); ?></h2></td>
            <td class="col-2"><h2 class="upc text-gray fs13"><?= __('Customer', 'wc-rw-order-data-export'); ?></h2></td>
        </tr>
    </table>

    <table class="customer-supplier-data" border="0">
        <tr>
            <td class="col-1">
                <h3 class="upc fs13"><?=$companyName; ?></h3>
                <span><?=$companyStreet;?></span><br>
                <span><?=$companyZIP . ' ' . $companyCity;?></span><br>
                <span><?=$companyCountryFull;?></span>
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
                <span class="text-gray"><?= __('Reg.No', 'wc-rw-order-data-export'); ?></span><br>
                <span class="upc text-gray"><?= __('vat id', 'wc-rw-order-data-export'); ?></span>
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
                <span class="text-gray"><?= __('Order number', 'wc-rw-order-data-export');?></span><br>
                <span class="text-gray"><?= __('Reference', 'wc-rw-order-data-export');?></span><br>
                <span class="text-gray"><?= __('Payment method', 'wc-rw-order-data-export');?></span><br>
                <span class="text-gray"><?= __('Invoice currency', 'wc-rw-order-data-export');?></span>
            </td>
            <td class="col-2">
                <span><?= $orderNumber;?></span><br>
                <span><?= $varSymbol ;?></span><br>
                <span><?= $paymentMethod; ?></span><br>
                <span><?= $currency; ?></span>
            </td>
            <td class="col-3"></td>
            <td class="col-4">
                <span class="text-gray"><?= __('Issued on', 'wc-rw-order-data-export'); ?></span><br>
                <span class="text-gray"><?= __('Due on', 'wc-rw-order-data-export'); ?></span><br>
                <span class="text-gray"><?= __('Taxable fulfillment due', 'wc-rw-order-data-export'); ?></span><br>
                <span>&#160</span>
            </td>
            <td class="col-5">
                <span><?= $creditNoteDate; ?></span><br>
                <span><?= $maturityDate; ?></span><br>
                <span><?= $creditNoteDate; ?></span><br>
                <span>&#160</span>
            </td>
        </tr>
    </table>

    <table class="items" border="0">
        <tr>
            <th class="col-1"></th>
            <th class="col-2"></th>
            <th class="col-3"></th>
            <th class="col-4 upc text-gray"><?= __('vat', 'wc-rw-order-data-export');?></th>
            <th class="col-5 upc text-gray"><?= __('unit price', 'wc-rw-order-data-export');?></th>
            <th class="col-6 upc text-gray"><?= __('total w/o vat', 'wc-rw-order-data-export');?></th>
            <th class="col-7 upc text-gray"><?= __('total incl. vat', 'wc-rw-order-data-export');?></th>
        </tr>
    </table>
    <?php foreach ($itemsData as $key => $item): ?>
        <table class="item" border="0">
            <tr>
                <td class="col-1"><?=$item['quantity'] ?></td>
                <td class="col-2"><?= __('psc', 'wc-rw-order-data-export');?></td>
                <td class="col-3"><?=$item['name'];?></td>
                <td class="col-4"><?=$item['tax_rate'];?> %</td>
                <td class="col-5"><?=-1 * $item['unitPriceExcVat'];?></td>
                <td class="col-6"><?=-1 * $item['allItemsPriceExlVat'];?></td>
                <td class="col-7"><?=-1 * $item['allItemsPriceIncVat'];?></td>
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
                <td class="col-6"><?=-1 * $feesData['totalExlVat'];?></td>
                <td class="col-7"><?=-1 * $feesData['totalIncVat'];?></td>
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
                <td class="col-6"><?=-1 * $shippingData['totalExlVat'];?></td>
                <td class="col-7"><?=-1 * $shippingData['totalIncVat'];?></td>
            </tr>
        </table>
    <?php endif;?>

    <div class="line" border="0"><span></span></div>

    <?php if(isset($vatRatesValues)) :?>

        <table class="vat-total" border="0">
            <tr>
                <td class="col-1"></td>
                <td class="col-2 text-gray upc"><?= __('vat rate', 'wc-rw-order-data-export');?></td>
                <td class="col-3 text-gray upc"><?= __('vat base', 'wc-rw-order-data-export');?></td>
                <td class="col-4 text-gray upc"><?= __('vat', 'wc-rw-order-data-export');?></td>
                <td class="col-5 text-gray upc"><?= __('vat in czk', 'wc-rw-order-data-export');?></td>
            </tr>
        </table>



        <?php foreach ($vatRatesValues as $rate => $value): ?>
            <?php if($value != 0):?>
                <table class="vat-total" border="0">
                    <tr>
                        <td class="col-1"></td>
                        <td class="col-2"><?=$rate;?> %</td>
                        <td class="col-3"><?=-1 * $vatBases[$rate];?></td>
                        <td class="col-4"><?=-1 * $value;?></td>
                        <td class="col-5"><?=-1 * $vatRatesValuesBaseCurr[$rate];?></td>
                    </tr>
                </table>
            <?php endif;?>
        <?php endforeach;?>

    <?php else: ?>

        <table class="vat-total" border="0">
            <tr>
                <td class="col-1"></td>
                <td class="col-2 text-gray upc"><?= __('vat rate', 'wc-rw-order-data-export');?></td>
                <td class="col-3 text-gray upc"><?= __('vat base', 'wc-rw-order-data-export');?></td>
                <td class="col-4 text-gray upc"><?= __('vat', 'wc-rw-order-data-export');?></td>
            </tr>
        </table>


        <table class="vat-total" border="0">
            <tr>
                <td class="col-1"></td>
                <td class="col-2">0 %</td>
                <td class="col-3"><?=-1 * $totalPriceInclVat;?></td>
                <td class="col-4">0</td>
            </tr>
        </table>

    <?php endif; ?>
    <div class="invoice-total-wrapper">
        <div class="invoice-total-right">
            <table class="invoice-total" border="0">
                <tr>
                    <td class="col-1">1 <?=$currency;?> = <?=$exchangeRate;?> CZK</td>
                    <td class="col-2 text-gray"><?=-1 * $totalPriceInclVat . ' ' . $currency;?></td>
                </tr>
            </table>
        </div>
    </div>
</div>
</body>
</html>
