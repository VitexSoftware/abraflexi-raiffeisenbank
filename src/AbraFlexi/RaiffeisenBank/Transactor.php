<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace AbraFlexi\RaiffeisenBank;

/**
 * Description of newPHPClass
 *
 * @author vitex
 */
class Transactor extends \AbraFlexi\Banka
{

    private $since;
    private $until;

    /**
     * 
     * @var \AbraFlexi\RO
     */
    private $bank;

    /**
     * 
     * @param type $init
     * @param type $options
     */
    public function __construct($bankAccount, $options = [])
    {
        parent::__construct(null, $options);
        $this->bank = $this->getBank($bankAccount);
    }

    public function getBank($accountNumber)
    {
        $banker = new \AbraFlexi\RO(null, ['evidence' => 'bankovni-ucet']);
        $candidat = $banker->getColumnsFromAbraFlexi('id', ['buc' => $accountNumber]);
        if (empty($candidat) || !array_key_exists('id', $candidat[0])) {
            throw new Exception('Bank account ' . $accountNumber . ' not found in AbraFlexi');
        } else {
            $banker->loadFromAbraFlexi($candidat[0]['id']);
        }
        return $banker;
    }

    /**
     * Prepare processing interval
     * 
     * @param string $scope 
     * 
     * @throws \Exception
     */
    function setScope($scope)
    {
        switch ($scope) {
            case 'current_month':
                $this->since = new \DateTime("first day of this month");
                $this->until = new \DateTime();
                break;
            case 'last_month':
                $this->since = new \DateTime("first day of last month");
                $this->until = new \DateTime("last day of last month");
                break;
            case 'last_two_months':
                $this->since = (new \DateTime("first day of last month"))->modify('-1 month');
                $this->until = (new \DateTime("last day of last month"));
                break;
            case 'previous_month':
                $this->since = new \DateTime("first day of -2 month");
                $this->until = new \DateTime("last day of -2 month");
                break;
            case 'two_months_ago':
                $this->since = new \DateTime("first day of -3 month");
                $this->until = new \DateTime("last day of -3 month");
                break;
            case 'this_year':
                $this->since = new \DateTime('first day of January ' . date('Y'));
                $this->until = new \DateTime("last day of December" . date('Y'));
                break;
            case 'January':  //1
            case 'February': //2
            case 'March':    //3
            case 'April':    //4
            case 'May':      //5
            case 'June':     //6
            case 'July':     //7
            case 'August':   //8
            case 'September'://9
            case 'October':  //10
            case 'November': //11
            case 'December': //12
                $this->since = new \DateTime('first day of ' . $scope . ' ' . date('Y'));
                $this->until = new \DateTime('last day of ' . $scope . ' ' . date('Y'));
                break;
            default:
                throw new \Exception('Unknown scope ' . $scope);
                break;
        }
        $this->since = $this->since->setTime(0, 0);
        $this->until = $this->until->setTime(0, 0);
    }

    /**
     * Obtain Transactions from RB
     * 
     * @return array
     */
    public function getTransactions()
    {
        $apiInstance = new \VitexSoftware\Raiffeisenbank\PremiumAPI\GetTransactionListApi();
        $page = 1;
        $transactions = [];
        try {
            do {
                $result = $apiInstance->getTransactionList($this->getxRequestId(), $this->bank->getDataValue('buc'), $this->getCurrencyCode(), $this->since->format('Y-m-d'), $this->until->format('Y-m-d'), $page);
                if (empty($result)) {
                    $this->addStatusMessage(sprintf(_('No transactions from %s to %s'), $this->since, $this->until));
                }
                $transactions = array_merge($transactions, $result['transactions']);
            } while ($result['lastPage'] === false);
        } catch (Exception $e) {
            echo 'Exception when calling GetTransactionListApi->getTransactionList: ', $e->getMessage(), PHP_EOL;
        }
        return $transactions;
    }

    public function import()
    {
        $allMoves = $this->getColumnsFromAbraFlexi('id', ['limit' => 0, 'banka' => $this->bank]);
        $allTransactions = $this->getTransactions();
        foreach ($allTransactions as $transaction) {
            $this->dataReset();
            $this->takeTransactionData($transaction);
            $this->addStatusMessage('New entry ' . $this->getRecordIdent(), $this->sync() ? 'success' : 'error');
        }
    }

    public function takeTransactionData($transactionData)
    {
        print_r($transactionData);
//stdClass Object
//(
//    [entryReference] => 5316910993
//    [amount] => stdClass Object
//        (
//            [value] => -249
//            [currency] => CZK
//        )
//
//    [creditDebitIndication] => DBIT
//    [bookingDate] => 2023-03-31T23:59:59.000+02:00
//    [valueDate] => 2023-03-31T23:59:59.000+02:00
//    [bankTransactionCode] => stdClass Object
//        (
//            [code] => 40000605000
//        )
//
//    [entryDetails] => stdClass Object
//        (
//            [transactionDetails] => stdClass Object
//                (
//                    [references] => stdClass Object
//                        (
//                        )
//
//                    [relatedParties] => stdClass Object
//                        (
//                        )
//
//                    [remittanceInformation] => stdClass Object
//                        (
//                            [unstructured] => Souhrnná položka k účtu 630804003.
//                            [creditorReferenceInformation] => stdClass Object
//                                (
//                                    [constant] => 898
//                                )
//
//                            [originatorMessage] => Souhrnná položka k účtu 630804003.
//                        )
//
//                )
//
//        )
//
//)

        $this->setMyKey(\AbraFlexi\RO::code('RB' . $transactionData->entryReference));
        $this->setDataValue('typDokl', \AbraFlexi\RO::code('STANDARD')); // TODO: Configure somehow

        $moveTrans = [
            'DBIT' => 'typPohybu.vydej',
            'CRDT' => 'typPohybu.prijem'
        ];
        $this->setDataValue('typPohybuK', $moveTrans[$transactionData->creditDebitIndication]);
        $this->setDataValue('cisDosle', $transactionData->entryReference);
        if (property_exists($transactionData->entryDetails->transactionDetails->remittanceInformation->creditorReferenceInformation, 'variable')) {
            $this->setDataValue('varSym', $transactionData->entryDetails->transactionDetails->remittanceInformation->creditorReferenceInformation->variable);
        }
        $this->setDataValue('datVyst', $transactionData->bookingDate);
        $this->setDataValue('duzpPuv', $transactionData->valueDate);
        $this->setDataValue('popis', $transactionData->entryDetails->transactionDetails->remittanceInformation->originatorMessage);
//    "poznam": {
//        "showToUser": "true",
//        "propertyName": "poznam",
//        "dbName": "Poznam",
//        "name": "Pozn\u00e1mka",
//        "title": "Pozn\u00e1mka",
//        "type": "string",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "false",
//        "isUpperCase": "false",
//        "isLowerCase": "false",
//        "links": null
//    },
        $this->setDataValue('sumZklCelkem', $transactionData->amount->value);
        $this->setDataValue('stavUzivK', 'stavUziv.nactenoEl');
        if (property_exists($transactionData->entryDetails->transactionDetails->relatedParties, 'counterParty')) {
            $this->setDataValue('nazFirmy', $transactionData->entryDetails->transactionDetails->relatedParties->counterParty->name);
            $this->setDataValue('buc', $transactionData->entryDetails->transactionDetails->relatedParties->counterParty->account->accountNumber);
        }
//    "iban": {
//        "showToUser": "true",
//        "propertyName": "iban",
//        "dbName": "Iban",
//        "name": "IBAN",
//        "title": "IBAN",
//        "type": "string",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "maxLength": "255",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "false",
//        "isUpperCase": "false",
//        "isLowerCase": "false",
//        "links": null
//    },
//    "bic": {
//        "showToUser": "true",
//        "propertyName": "bic",
//        "dbName": "Bic",
//        "name": "BIC",
//        "title": "BIC",
//        "type": "string",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "maxLength": "255",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "false",
//        "isUpperCase": "false",
//        "isLowerCase": "false",
//        "links": null
//    },
//    "specSym": {
//        "showToUser": "true",
//        "propertyName": "specSym",
//        "dbName": "SpecSym",
//        "name": "Specifick\u00fd symbol",
//        "title": "Specifick\u00fd symbol",
//        "type": "string",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "maxLength": "255",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "false",
//        "isUpperCase": "false",
//        "isLowerCase": "false",
//        "links": null
//    },
//    "datUcto": {
//        "showToUser": "true",
//        "propertyName": "datUcto",
//        "dbName": "DatUcto",
//        "name": "Datum za\u00fa\u010dt.",
//        "title": "Datum za\u00fa\u010dtov\u00e1n\u00ed",
//        "type": "date",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "true",
//        "isUpperCase": "false",
//        "isLowerCase": "false",
//        "links": null
//    },
//    "storno": {
//        "showToUser": "true",
//        "propertyName": "storno",
//        "dbName": "Storno",
//        "name": "Storno",
//        "title": "Storno",
//        "type": "logic",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "isWritable": "false",
//        "isOverWritable": "false",
//        "hasBusinessLogic": "false",
//        "isUpperCase": "false",
//        "isLowerCase": "false",
//        "links": null
//    },
//    "stitky": {
//        "showToUser": "true",
//        "propertyName": "stitky",
//        "name": "\u0160t\u00edtky",
//        "title": "\u0160t\u00edtky",
//        "type": "string",
//        "isVisible": "true",
//        "isSortable": "false",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "false",
//        "isUpperCase": "false",
//        "isLowerCase": "false",
//        "links": null
//    },
//    "typDokl": {
//        "showToUser": "true",
//        "propertyName": "typDokl",
//        "dbName": "IdTypDokl",
//        "name": "Typ dokladu",
//        "title": "Typ dokladu",
//        "type": "relation",
//        "fkName": "Typy bankovn\u00edch doklad\u016f 13001",
//        "fkEvidencePath": "typ-banka",
//        "fkEvidenceType": "BANKA_TYP",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "true",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "true",
//        "isUpperCase": "false",
//        "isLowerCase": "false",
//        "url": "http:\/\/demo.flexibee.eu\/c\/demo\/typ-banka",
//        "links": null
//    },

        $this->setDataValue('banka', $this->bank);
        $this->setDataValue('mena', \AbraFlexi\RO::code($transactionData->amount->currency));
        if (property_exists($transactionData->entryDetails->transactionDetails->remittanceInformation->creditorReferenceInformation, 'constant')) {
//            $this->setDataValue('konSym', \AbraFlexi\RO::code($transactionData->entryDetails->transactionDetails->remittanceInformation->creditorReferenceInformation->constant));
        }
//    "firma": {
//        "showToUser": "true",
//        "propertyName": "firma",
//        "dbName": "IdFirmy",
//        "name": "Zkratka firmy",
//        "title": "Zkratka firmy",
//        "type": "relation",
//        "fkName": "Adresy firem 4021",
//        "fkEvidencePath": "adresar",
//        "fkEvidenceType": "ADRESAR",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "true",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "maxLength": "20",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "true",
//        "isUpperCase": "true",
//        "isLowerCase": "false",
//        "url": "http:\/\/demo.flexibee.eu\/c\/demo\/adresar",
//        "links": null
//    },
//    "stat": {
//        "showToUser": "true",
//        "propertyName": "stat",
//        "dbName": "IdStatu",
//        "name": "St\u00e1t",
//        "title": "St\u00e1t",
//        "type": "relation",
//        "fkName": "St\u00e1ty 20004",
//        "fkEvidencePath": "stat",
//        "fkEvidenceType": "STAT",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "maxLength": "3",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "true",
//        "isUpperCase": "true",
//        "isLowerCase": "false",
//        "url": "http:\/\/demo.flexibee.eu\/c\/demo\/stat",
//        "links": null
//    },
//    "banSpojDod": {
//        "showToUser": "true",
//        "propertyName": "banSpojDod",
//        "dbName": "IdBanSpojDod",
//        "name": "\u00da\u010det firmy",
//        "title": "\u00da\u010det firmy",
//        "type": "relation",
//        "fkName": "Bankovn\u00ed spojen\u00ed 4005",
//        "fkEvidencePath": "adresar-bankovni-ucet",
//        "fkEvidenceType": "ADR_BANKOVNI_UCET",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "true",
//        "isUpperCase": "false",
//        "isLowerCase": "false",
//        "url": "http:\/\/demo.flexibee.eu\/c\/demo\/adresar-bankovni-ucet",
//        "links": null
//    },
//    "typUcOp": {
//        "showToUser": "true",
//        "propertyName": "typUcOp",
//        "dbName": "IdTypUcOp",
//        "name": "P\u0159edpis za\u00fa\u010dtov\u00e1n\u00ed",
//        "title": "P\u0159edpis za\u00fa\u010dtov\u00e1n\u00ed",
//        "type": "relation",
//        "fkName": "P\u0159edpisy za\u00fa\u010dtov\u00e1n\u00ed - bankovn\u00ed doklady 20135",
//        "fkEvidencePath": "predpis-zauctovani",
//        "fkEvidenceType": "PREDPIS_ZAUCTOVANI",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "true",
//        "isUpperCase": "false",
//        "isLowerCase": "false",
//        "url": "http:\/\/demo.flexibee.eu\/c\/demo\/predpis-zauctovani",
//        "links": null
//    },
//    "dphZaklUcet": {
//        "showToUser": "true",
//        "propertyName": "dphZaklUcet",
//        "dbName": "IdDphZaklUcet",
//        "name": "\u00da\u010det DPH z\u00e1kl.",
//        "title": "DPH z\u00e1kladn\u00ed",
//        "type": "relation",
//        "fkName": "\u00da\u010dtov\u00fd rozvrh 2001",
//        "fkEvidencePath": "ucet",
//        "fkEvidenceType": "UCET",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "maxLength": "6",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "true",
//        "isUpperCase": "true",
//        "isLowerCase": "false",
//        "url": "http:\/\/demo.flexibee.eu\/c\/demo\/ucet",
//        "links": null
//    },
//    "dphSnizUcet": {
//        "showToUser": "true",
//        "propertyName": "dphSnizUcet",
//        "dbName": "IdDphSnizUcet",
//        "name": "\u00da\u010det DPH sn\u00ed\u017e.",
//        "title": "DPH sn\u00ed\u017een\u00e1",
//        "type": "relation",
//        "fkName": "\u00da\u010dtov\u00fd rozvrh 2001",
//        "fkEvidencePath": "ucet",
//        "fkEvidenceType": "UCET",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "maxLength": "6",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "true",
//        "isUpperCase": "true",
//        "isLowerCase": "false",
//        "url": "http:\/\/demo.flexibee.eu\/c\/demo\/ucet",
//        "links": null
//    },
//    "dphSniz2Ucet": {
//        "showToUser": "true",
//        "propertyName": "dphSniz2Ucet",
//        "dbName": "IdDphSniz2Ucet",
//        "name": "\u00da\u010det DPH 2. sn\u00ed\u017e.",
//        "title": "DPH 2. sn\u00ed\u017een\u00e1",
//        "type": "relation",
//        "fkName": "\u00da\u010dtov\u00fd rozvrh 2001",
//        "fkEvidencePath": "ucet",
//        "fkEvidenceType": "UCET",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "maxLength": "6",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "true",
//        "isUpperCase": "true",
//        "isLowerCase": "false",
//        "url": "http:\/\/demo.flexibee.eu\/c\/demo\/ucet",
//        "links": null
//    },
//    "smerKod": {
//        "showToUser": "true",
//        "propertyName": "smerKod",
//        "dbName": "IdSmerKod",
//        "name": "K\u00f3d banky",
//        "title": "K\u00f3d banky",
//        "type": "relation",
//        "fkName": "Pen\u011b\u017en\u00ed \u00fastavy 20010",
//        "fkEvidencePath": "penezni-ustav",
//        "fkEvidenceType": "PENEZNI_USTAV",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "maxLength": "20",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "false",
//        "isUpperCase": "true",
//        "isLowerCase": "false",
//        "url": "http:\/\/demo.flexibee.eu\/c\/demo\/penezni-ustav",
//        "links": null
//    },
//    "statDph": {
//        "showToUser": "true",
//        "propertyName": "statDph",
//        "dbName": "IdStatDph",
//        "name": "St\u00e1t DPH",
//        "title": "St\u00e1t DPH",
//        "type": "relation",
//        "fkName": "St\u00e1ty 20004",
//        "fkEvidencePath": "stat",
//        "fkEvidenceType": "STAT",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "maxLength": "3",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "true",
//        "isUpperCase": "true",
//        "isLowerCase": "false",
//        "url": "http:\/\/demo.flexibee.eu\/c\/demo\/stat",
//        "links": null
//    },
//    "clenDph": {
//        "showToUser": "true",
//        "propertyName": "clenDph",
//        "dbName": "IdClenDph",
//        "name": "\u0158\u00e1dky DPH",
//        "title": "\u0158\u00e1dky DPH",
//        "type": "relation",
//        "fkName": "\u0158\u00e1dky p\u0159izn\u00e1n\u00ed DPH 20025",
//        "fkEvidencePath": "cleneni-dph",
//        "fkEvidenceType": "CLENENI_DPH",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "true",
//        "isUpperCase": "false",
//        "isLowerCase": "false",
//        "url": "http:\/\/demo.flexibee.eu\/c\/demo\/cleneni-dph",
//        "links": null
//    },
//    "stredisko": {
//        "showToUser": "true",
//        "propertyName": "stredisko",
//        "dbName": "IdStred",
//        "name": "St\u0159edisko",
//        "title": "St\u0159edisko",
//        "type": "relation",
//        "fkName": "St\u0159ediska 20001",
//        "fkEvidencePath": "stredisko",
//        "fkEvidenceType": "STREDISKO",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "maxLength": "20",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "true",
//        "isUpperCase": "true",
//        "isLowerCase": "false",
//        "url": "http:\/\/demo.flexibee.eu\/c\/demo\/stredisko",
//        "links": null
//    },
//    "cinnost": {
//        "showToUser": "true",
//        "propertyName": "cinnost",
//        "dbName": "IdCinnost",
//        "name": "\u010cinnost",
//        "title": "\u010cinnost",
//        "type": "relation",
//        "fkName": "\u010cinnost 20013",
//        "fkEvidencePath": "cinnost",
//        "fkEvidenceType": "CINNOST",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "true",
//        "isUpperCase": "false",
//        "isLowerCase": "false",
//        "url": "http:\/\/demo.flexibee.eu\/c\/demo\/cinnost",
//        "links": null
//    },
//    "zakazka": {
//        "showToUser": "true",
//        "propertyName": "zakazka",
//        "dbName": "IdZakazky",
//        "name": "Zak\u00e1zka",
//        "title": "Zak\u00e1zka",
//        "type": "relation",
//        "fkName": "Zak\u00e1zky 20002",
//        "fkEvidencePath": "zakazka",
//        "fkEvidenceType": "ZAKAZKA",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "maxLength": "30",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "true",
//        "isUpperCase": "false",
//        "isLowerCase": "false",
//        "url": "http:\/\/demo.flexibee.eu\/c\/demo\/zakazka",
//        "links": null
//    },
//    "uzivatel": {
//        "showToUser": "true",
//        "propertyName": "uzivatel",
//        "dbName": "IdUziv",
//        "name": "U\u017eivatel",
//        "title": "U\u017eivatel",
//        "type": "relation",
//        "fkName": "Osoby a u\u017eivatel\u00e9 20024",
//        "fkEvidencePath": "uzivatel",
//        "fkEvidenceType": "UZIVATELE",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "maxLength": "254",
//        "isWritable": "false",
//        "isOverWritable": "false",
//        "hasBusinessLogic": "false",
//        "isUpperCase": "false",
//        "isLowerCase": "true",
//        "url": "http:\/\/demo.flexibee.eu\/c\/demo\/uzivatel",
//        "links": null
//    },
//    "zodpOsoba": {
//        "showToUser": "true",
//        "propertyName": "zodpOsoba",
//        "dbName": "IdZodpOsoba",
//        "name": "Zodpov\u011bdn\u00e1 osoba",
//        "title": "Zodpov\u011bdn\u00e1 osoba",
//        "type": "relation",
//        "fkName": "Osoby a u\u017eivatel\u00e9 20024",
//        "fkEvidencePath": "uzivatel",
//        "fkEvidenceType": "UZIVATELE",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "maxLength": "254",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "false",
//        "isUpperCase": "false",
//        "isLowerCase": "true",
//        "url": "http:\/\/demo.flexibee.eu\/c\/demo\/uzivatel",
//        "links": null
//    },
//    "kontaktOsoba": {
//        "showToUser": "true",
//        "propertyName": "kontaktOsoba",
//        "dbName": "IdKontaktOsoba",
//        "name": "Kontaktn\u00ed osoba",
//        "title": "Kontaktn\u00ed osoba",
//        "type": "relation",
//        "fkName": "Kontakty 4003",
//        "fkEvidencePath": "kontakt",
//        "fkEvidenceType": "ADR_KONTAKT",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "true",
//        "isUpperCase": "false",
//        "isLowerCase": "false",
//        "url": "http:\/\/demo.flexibee.eu\/c\/demo\/kontakt",
//        "links": null
//    },
//    "kontaktJmeno": {
//        "showToUser": "true",
//        "propertyName": "kontaktJmeno",
//        "dbName": "KontaktJmeno",
//        "name": "Kontaktn\u00ed jm\u00e9no",
//        "title": "Jm\u00e9no",
//        "type": "string",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "maxLength": "255",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "false",
//        "isUpperCase": "false",
//        "isLowerCase": "false",
//        "gdprType": "OSOBNI",
//        "links": null
//    },
//    "kontaktEmail": {
//        "showToUser": "true",
//        "propertyName": "kontaktEmail",
//        "dbName": "KontaktEmail",
//        "name": "Kontaktn\u00ed email",
//        "title": "Email",
//        "type": "string",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "maxLength": "255",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "false",
//        "isUpperCase": "false",
//        "isLowerCase": "false",
//        "gdprType": "OSOBNI",
//        "links": null
//    },
//    "kontaktTel": {
//        "showToUser": "true",
//        "propertyName": "kontaktTel",
//        "dbName": "KontaktTel",
//        "name": "Kontaktn\u00ed telefon",
//        "title": "Telefon",
//        "type": "string",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "maxLength": "255",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "false",
//        "isUpperCase": "false",
//        "isLowerCase": "false",
//        "gdprType": "OSOBNI",
//        "links": null
//    },
//    "rada": {
//        "showToUser": "true",
//        "propertyName": "rada",
//        "dbName": "IdRady",
//        "name": "\u010c\u00eds. \u0159ada",
//        "title": "\u010c\u00eds. \u0159ada",
//        "type": "relation",
//        "fkName": "Dokladov\u00e9 \u0159ady - bankovn\u00ed doklady 13005",
//        "fkEvidencePath": "rada-banka",
//        "fkEvidenceType": "BANKA_RADA",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "false",
//        "isUpperCase": "false",
//        "isLowerCase": "false",
//        "url": "http:\/\/demo.flexibee.eu\/c\/demo\/rada-banka",
//        "links": null
//    },
//    "uuid": {
//        "showToUser": "true",
//        "propertyName": "uuid",
//        "dbName": "Uuid",
//        "name": "Uuid",
//        "title": "Univerz\u00e1ln\u00ed unik\u00e1tn\u00ed identifik\u00e1tor",
//        "type": "string",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "maxLength": "50",
//        "isWritable": "false",
//        "isOverWritable": "false",
//        "hasBusinessLogic": "false",
//        "isUpperCase": "false",
//        "isLowerCase": "false",
//        "links": null
//    },
//    "source": {
//        "showToUser": "true",
//        "propertyName": "source",
//        "dbName": "Source",
//        "name": "Zdroj",
//        "title": "Zdroj",
//        "type": "string",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "maxLength": "50",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "false",
//        "isUpperCase": "false",
//        "isLowerCase": "false",
//        "links": null
//    },
//    "clenKonVykDph": {
//        "showToUser": "true",
//        "propertyName": "clenKonVykDph",
//        "dbName": "IdClenKonVykDph",
//        "name": "\u0158\u00e1dek kontroln\u00edho hl\u00e1\u0161en\u00ed DPH",
//        "title": "\u0158\u00e1dek kontroln\u00edho hl\u00e1\u0161en\u00ed DPH",
//        "type": "relation",
//        "fkName": "\u0158\u00e1dky kontroln\u00edho hl\u00e1\u0161en\u00ed DPH 20042",
//        "fkEvidencePath": "cleneni-kontrolni-hlaseni",
//        "fkEvidenceType": "CLEN_KON_VYK_DPH",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "true",
//        "isUpperCase": "false",
//        "isLowerCase": "false",
//        "url": "http:\/\/demo.flexibee.eu\/c\/demo\/cleneni-kontrolni-hlaseni",
//        "links": null
//    },
//    "jakUhrK": {
//        "showToUser": "true",
//        "propertyName": "jakUhrK",
//        "dbName": "JakUhrK",
//        "name": "Jak uhrazeno",
//        "title": "Jak uhrazeno",
//        "type": "select",
//        "isVisible": "true",
//        "isSortable": "true",
//        "isHighlight": "false",
//        "inId": "false",
//        "inSummary": "false",
//        "inDetail": "true",
//        "inExpensive": "false",
//        "mandatory": "false",
//        "maxLength": "50",
//        "isWritable": "true",
//        "isOverWritable": "true",
//        "hasBusinessLogic": "false",
//        "isUpperCase": "false",
//        "isLowerCase": "false",
//        "links": null,
//        "values": {
//            "value": [{
//                    "@key": "jakUhrazeno.rucne1",
//                    "$": "Ru\u010dn\u011b - 1 doklad"
//                }, {
//                    "@key": "jakUhrazeno.rucneN",
//                    "$": "Ru\u010dn\u011b - v\u00edce doklad\u016f"
//                }, {
//                    "@key": "jakUhrazeno.rucneCast",
//                    "$": "Ru\u010dn\u011b - \u010d\u00e1ste\u010dn\u00e1 \u00fahrada"
//                }, {
//                    "@key": "jakUhrazeno.autoVs",
//                    "$": "Automaticky dle var. sym."
//                }, {
//                    "@key": "jakUhrazeno.autoVsRuzneMeny",
//                    "$": "Automaticky dle var. sym. (r\u016fzn\u00e9 m\u011bny)"
//                }, {
//                    "@key": "jakUhrazeno.autoBezVs",
//                    "$": "Automaticky dle \u010d\u00e1stky"
//                }, {
//                    "@key": "jakUhrazeno.autoBuc",
//                    "$": "Automaticky dle bank. \u00fa\u010dtu"
//                }, {
//                    "@key": "jakUhrazeno.autoZak",
//                    "$": "Automaticky dle z\u00e1kaznick\u00e9ho \u010d\u00edsla"
//                }, {
//                    "@key": "jakUhrazeno.autoKod",
//                    "$": "Automaticky dle int. \u010d\u00eds. dokladu"
//                }]
//        }
//    },
    }

    /**
     * Request Identifier
     * 
     * @return string
     */
    public function getxRequestId()
    {
        return $this->bank->getDataValue('buc') . time();
    }

    public
            function getCurrencyCode()
    {
        return empty($this->bank->getDataValue('mena')->value) ? 'CZK' : \AbraFlexi\RO::uncode($this->bank->getDataValue('mena'));
    }
}
