<?php

namespace includes;

class LionCsvCreator {

    protected $csvData;


    public function createCsv(array $orderData) : array
    {
        $this->makeCsvHeader($orderData);
        $this->makeCsv($orderData);
        return $this->csvData;


    }

    protected function makeCsvHeader(array $orderData) : void {

        $this->csvData[0][] = 'Номер фактуры';
        $this->csvData[0][] = 'Дата фактуры';
        $this->csvData[0][] = 'ФИО';
        $this->csvData[0][] = 'Адрес доставки';

        $firsOrderId = $orderData['ordersId'][0];
        ksort($orderData[$firsOrderId]['vatRatesValues']);
        foreach ($orderData[$firsOrderId]['vatRatesValues'] as $vatRate => $value){
            if($value !==0){

                $this->csvData[0][] = "Сумма без НДС, $vatRate%";
                $this->csvData[0][] = "НДС, $vatRate%";
            }

        }

    }

    protected function makeCsv(array $orderData) : void
    {

        $i = 1;
        foreach ($orderData['ordersId'] as $orderID){

            $this->csvData[$i][] = $orderData[$orderID]['invoiceNumber'];
            $this->csvData[$i][] = $orderData[$orderID]['invoiceDate'];
            $this->csvData[$i][] = $orderData[$orderID]['clientBillingName'];
            $this->csvData[$i][] = $orderData[$orderID]['clientBillingAddress1'] . "\n" . $orderData[$orderID]['clientBillingPostcode'] . " " . $orderData[$orderID]['clientBillingCity'] . "\n" . $orderData[$orderID]['clientBillingCountryFull'];

            ksort($orderData[$orderID]['vatRatesValues']);

            foreach ($orderData[$orderID]['vatRatesValues'] as $vatRate => $value){
                if($value !==0){

                    $this->csvData[$i][] = str_replace('.',',',(string)$orderData[$orderID]['vatBases'][$vatRate]);
                    $this->csvData[$i][] = str_replace('.',',',(string)$orderData[$orderID]['vatRatesValues'][$vatRate]);
                }

            }
            $i++;
        }

    }



}