<?php

namespace includes;

use FluidXml\FluidXml;

class EntryXmlCreator {

    public $data;

    public function __construct($data){

        $this->data = $data;

    }

    public function createXml(){

        if(!$this->data) return null;

        $book =  new FluidXml(null, ['root' => 'DavkyEntry']);

        $book->setAttribute([
            'okamzik' => $this->data['now'],
            'nazevSW' => 'Entry',
            'verzeSW' => '2016.04',
            'VerzeFormatu' => '01.01',
            'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
        ]);


        $book->addChild('Komentar', 'Faktury vydané - počet: ' . $this->data['count'] . '; celková suma: ' . $this->data['total'] );

        $book->addChild('IdDavky', $this->data['batchId']);

        $fakturyVydane = $book->addChild('FaturyVydane', true);

        foreach ($this->data['ordersId'] as $orderId) {

            $faV = $fakturyVydane->addChild('FakturaVydana', true);

            $hlavicka = $faV->addChild('Hlavicka', true);

            $hlavicka->addChild([
                'Doklad' => $this->data[$orderId]['orderId'],
                //'Datum' => $this->data[$orderId]['currentDate'],
                'Datum' => $this->data[$orderId]['invoiceDate'],
                //'DatumVystaveni' => $this->data[$orderId]['currentDate'],
                'DatumVystaveni' => $this->data[$orderId]['invoiceDate'],
                'DatumSplatnosti' => $this->data[$orderId]['maturityDate'],
                'VarSymbol' => $this->data[$orderId]['varSymbol'],
                'EvidCisloDanDokl' => $this->data[$orderId]['invoiceNumber'],
                //'DPPD' => $this->data[$orderId]['currentDate'],
                'DPPD' => $this->data[$orderId]['invoiceDate'],
                'Dodavatel' => [
                    'Adresa' => [
                        'Nazev1r' => $this->data['companyData']['companyName'],
                        'Nazev2r' => null,
                        'Ulice1r' => $this->data['companyData']['companyStreet'],
                        'Ulice2r' => null,
                        'Obec' => $this->data['companyData']['companyCity'],
                        'PSC' => $this->data['companyData']['companyZIP'],
                        'Stat' => $this->data['companyData']['companyCountry'],

                    ],
                    'ICO' => $this->data['companyData']['ICO'],
                    'DIC' => $this->data['companyData']['DIC'],
                    'Vystavil' => $this->data['companyData']['accountant'],
                    'Schválil' => $this->data['companyData']['director']
                ],
                'Odberatel' => [
                    'Adresa' => [
                        'Nazev1r' => $this->data[$orderId]['clientBillingName'],
                        'Nazev2r' => null,
                        'Ulice1r' => $this->data[$orderId]['clientBillingAddress1'],
                        'Ulice2r' => !empty($this->data[$orderId]['clientBillingAddress2']) ? $this->data[$orderId]['clientBillingAddress2'] : null,
                        'Obec' => $this->data[$orderId]['clientBillingCity'],
                        'PSC' => $this->data[$orderId]['clientBillingPostcode'],
                        'Stat' => $this->data[$orderId]['clientBillingCountry'],

                    ],
                    'ICO' => null,
                    'DIC' => null,
                ],
                'ZpusobPlatby' => null,
                'ZpusobDopravy' => null,
                'Popis' => 'Prodej zboží ' . $this->data[$orderId]['invoiceNumber'],
            ]);

            $ucetniRecapitulace = $faV->addChild('UcetniRekapitulace', true);

            $ucetniRecapitulace->addChild([
                'Uctovani' => [
                    'Predkontace' => 'ODB-EXP',
                    'UcetOdberatelsky' => '311200',
                ],
            ]);

            $sazbyDPH = $ucetniRecapitulace->addChild('SazbyDph', true);

            $sazbyDPH->addChild('sazba', true)
                ->setAttribute([
                    'kod' => 'NulovaOss',
                    'hodnota' => '0',
                    'urceni' => 'nulova',
                ]);


            foreach ($this->data[$orderId]['vatCodes'] as $vatCode => $value) {

                $sazbyDPH->addChild('sazba', true)
                    ->setAttribute([
                        'kod' => $vatCode,
                        'hodnota' => $value,
                        'urceni' => 'jina',
                    ]);
            }


            $ucetniRecapitulace->addChild([
                'Mena' => [
                    'Kod' => $this->data[$orderId]['currency'],
                    'Kurz' => [
                        '@koeficient' => $this->data[$orderId]['exchangeRate'],
                        '@mnozstvi' => '1',
                    ],
                    'CelkemBezDPH' => $this->data[$orderId]['totalPriceExlVat'],
                    'CelkemVcetneDPH' => $this->data[$orderId]['totalPriceInclVat'],
                ],

            ]);

            $zakladniMena = $ucetniRecapitulace->addChild('ZaklMena', true);

            $zakladniMena->addChild([
                'CelkemBezDPH' =>  $this->data[$orderId]['totalPriceExlVatBaseCurr'],
                'CelkemVcetneDPH' => $this->data[$orderId]['totalPriceInclVatBaseCurr'],

            ]);

            $ucetniRadky = $zakladniMena->addChild('UcetniRadkyDokladu', true);



            $ucetniRadky->addChild([

                'Radek' => [
                    'Typ' => 'Zaklad',
                    'Sazba' => 'NulovaOss',
                    'Castka' => $this->data[$orderId]['totalItemsPriceExlVatBaseCurr'],
                    'Popis' => 'prodej zbozi ' . $this->data[$orderId]['invoiceNumber'],
                    'Uctovani' => [
                        'Predkontace' => null,
                        'Ucet' => '604001',
                        'KodDph' => null,
                    ],

                ]

            ]);

            if(!empty($this->data[$orderId]['totalSippingAndFeesBaseCurr'])) {

                $ucetniRadky->addChild([

                    'Radek' => [
                        'Typ' => 'Zaklad',
                        'Sazba' => 'NulovaOss',
                        'Castka' => $this->data[$orderId]['totalSippingAndFeesBaseCurr'],
                        'Popis' => 'prodej zbozi ' . $this->data[$orderId]['invoiceNumber'] . '-preprava',
                        'Uctovani' => [
                            'Predkontace' => null,
                            'Ucet' => '602001',
                            'KodDph' => null,
                        ],

                    ]


                ]);
            }

            foreach ($this->data[$orderId]['vatRatesValuesBaseCurr'] as $rate => $value) {

                if($value != 0) {
                    $ucetniRadky->addChild([

                        'Radek' => [
                            'Typ' => 'Dan',
                            'Sazba' => array_search($rate, $this->data[$orderId]['vatCodes']),
                            'Castka' => $value,
                            'ZakladDph' => $this->data[$orderId]['vatBasesBaseCur'][$rate],
                            'Popis' => 'Prodej zboží na dálku - ' . $rate . '%',
                            'Uctovani' => [
                                'Predkontace' => null,
                                'Ucet' => '344100',
                                'KodDph' => array_search($rate, $this->data['userVatRates']),
                            ],

                        ]

                    ]);

                    $ucetniRadky->addChild([

                        'Radek' => [
                            'Typ' => 'Dan',
                            'Sazba' => 'NulovaOss',
                            'Castka' => '0',
                            'ZakladDph' => $this->data[$orderId]['vatBasesBaseCur'][$rate],
                            'Popis' => 'Prodej zboží na dálku OSS-0% generovaný',
                            'Uctovani' => [
                                'Predkontace' => null,
                                'Ucet' => '343001',
                                'KodDph' => 'S0',
                            ],

                        ]

                    ]);
                }
            }

        }


        return $book->xml();




    }







}