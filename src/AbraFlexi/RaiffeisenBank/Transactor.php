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
     * DateTime Formating eg. 2021-08-01T10:00:00.0Z
     * @var string
     */
    public static $dateTimeFormat = 'Y-m-d\\TH:i:s.0\\Z';

    /**
     * 
     * @var \AbraFlexi\RO
     */
    private $bank;
    private $constantor;
    private $constSymbols;

    /**
     * Transaction Handler
     * 
     * @param null $init
     * @param array $options
     */
    public function __construct($bankAccount, $options = [])
    {
        parent::__construct(null, $options);
        $this->bank = $this->getBank($bankAccount);
        $this->constantor = new \AbraFlexi\RW(null, ['evidence' => 'konst-symbol']);
        $this->constSymbols = $this->constantor->getColumnsFromAbraFlexi(['kod'], ['limit' => 0], 'kod');
    }

    /**
     * Try to check certificate readibilty
     * 
     * @param string $certFile path to certificate
     */
    public static function checkCertificatePresence($certFile)
    {
        if ((file_exists($certFile) === false) || (is_readable($certFile) === false)) {
            fwrite(STDERR, 'Cannot read specified certificate file: ' . $certFile . PHP_EOL);
            exit;
        }
    }

    /**
     * Gives you AbraFlexi Bank 
     * 
     * @param STRING $accountNumber
     * 
     * @return \AbraFlexi\RO
     * 
     * @throws Exception
     */
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
            case 'today':
                $this->since = (new \DateTime())->setTime(0, 0);
                $this->until = (new \DateTime())->setTime(23, 59);
                break;
            case 'yesterday':
                $this->since = (new \DateTime('yesterday'))->setTime(0, 0);
                $this->until = (new \DateTime('yesterday'))->setTime(23, 59);
                break;
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
            case 'auto':
                $latestRecord = $this->getColumnsFromAbraFlexi(['id', 'lastUpdate'], ['limit' => 1, 'order' => 'lastUpdate@A', 'source' => $this->sourceString(), 'banka' => $this->bank]);
                if (array_key_exists(0, $latestRecord) && array_key_exists('lastUpdate', $latestRecord[0])) {
                    $this->since = $latestRecord[0]['lastUpdate'];
                } else {
                    $this->addStatusMessage('Previous record for "auto since" not found. Defaulting to today\'s 00:00', 'warning');
                    $this->since = (new \DateTime())->setTime(0, 0);
                }
                $this->until = new \DateTime(); //Now
                break;
            default:
                throw new \Exception('Unknown scope ' . $scope);
                break;
        }
        if ($scope != 'auto' && $scope != 'today' && $scope != 'yesterday') {
            $this->since = $this->since->setTime(0, 0);
            $this->until = $this->until->setTime(0, 0);
        }
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
        $this->addStatusMessage(sprintf(_('Request transactions from %s to %s'), $this->since->format(self::$dateTimeFormat), $this->until->format(self::$dateTimeFormat)), 'debug');
        try {
            do {
                $result = $apiInstance->getTransactionList($this->getxRequestId(), $this->bank->getDataValue('buc'), $this->getCurrencyCode(), $this->since->format(self::$dateTimeFormat), $this->until->format(self::$dateTimeFormat), $page);
                if (empty($result)) {
                    $this->addStatusMessage(sprintf(_('No transactions from %s to %s'), $this->since->format(self::$dateTimeFormat), $this->until->format(self::$dateTimeFormat)));
                    $result['lastPage'] = true;
                }
                if (array_key_exists('transactions', $result)) {
                    $transactions = array_merge($transactions, $result['transactions']);
                }
            } while ($result['lastPage'] === false);
        } catch (Exception $e) {
            echo 'Exception when calling GetTransactionListApi->getTransactionList: ', $e->getMessage(), PHP_EOL;
        }
        return $transactions;
    }

    /**
     * Import process itself
     */
    public function import()
    {
        $allMoves = $this->getColumnsFromAbraFlexi('id', ['limit' => 0, 'banka' => $this->bank]);
        $allTransactions = $this->getTransactions();
        $success = 0;
        foreach ($allTransactions as $transaction) {
            $this->dataReset();
            $this->takeTransactionData($transaction);
            if ($this->checkForTransactionPresence() === false) {
                try {
                    $this->addStatusMessage('New entry ' . $this->getRecordIdent() . ' ' . $this->getDataValue('nazFirmy') . ': ' . $this->getDataValue('popis') . ' ' . $this->getDataValue('zklOsv') . ' ' . \AbraFlexi\RO::uncode($this->getDataValue('mena')), $this->sync() ? 'success' : 'error');
                    $success++;
                } catch (\AbraFlexi\Exception $exc) {
                    
                }
            } else {
                $this->addStatusMessage('Record with remoteNumber ' . $this->getDataValue('cisDosle') . ' already present in AbraFlexi', 'warning');
            }
        }
        $this->addStatusMessage('Import done. ' . $success . ' of ' . count($allTransactions) . ' imported');
    }

    /**
     * Use Transaction data for Bank record
     * 
     * @param array $transactionData
     */
    public function takeTransactionData($transactionData)
    {
//        $this->setMyKey(\AbraFlexi\RO::code('RB' . $transactionData->entryReference));
        $this->setDataValue('bezPolozek', true);
        $this->setDataValue('typDokl', \AbraFlexi\RO::code(\Ease\Functions::cfg('TYP_DOKLADU', 'STANDARD')));
        $moveTrans = [
            'DBIT' => 'typPohybu.vydej',
            'CRDT' => 'typPohybu.prijem'
        ];
        $this->setDataValue('typPohybuK', $moveTrans[$transactionData->creditDebitIndication]);
        $this->setDataValue('cisDosle', $transactionData->entryReference);
        if (property_exists($transactionData->entryDetails->transactionDetails->remittanceInformation, 'creditorReferenceInformation')) {
            if (property_exists($transactionData->entryDetails->transactionDetails->remittanceInformation->creditorReferenceInformation, 'variable')) {
                $this->setDataValue('varSym', $transactionData->entryDetails->transactionDetails->remittanceInformation->creditorReferenceInformation->variable);
            }
            if (property_exists($transactionData->entryDetails->transactionDetails->remittanceInformation->creditorReferenceInformation, 'constant')) {
                $conSym = $transactionData->entryDetails->transactionDetails->remittanceInformation->creditorReferenceInformation->constant;
                if (intval($conSym)) {
                    $conSym = sprintf('%04d', $conSym);
                    if (!array_key_exists($conSym, $this->constSymbols)) {
                        $this->constantor->insertToAbraFlexi(['kod' => $conSym, 'poznam' => 'Created by Raiffeisen Bank importer', 'nazev' => '?!?!? ' . $conSym]);
                        $this->constantor->addStatusMessage('New constant ' . $conSym . ' created in flexibee', 'warning');
                        $this->constSymbols[$conSym] = $conSym;
                    }
                    $this->setDataValue('konSym', \AbraFlexi\RO::code($conSym));
                }
            }
        }


        $this->setDataValue('datVyst', $transactionData->bookingDate);
        //$this->setDataValue('duzpPuv', $transactionData->valueDate);
        if (property_exists($transactionData->entryDetails->transactionDetails->remittanceInformation, 'originatorMessage')) {
            $this->setDataValue('popis', $transactionData->entryDetails->transactionDetails->remittanceInformation->originatorMessage);
        }

        $this->setDataValue('poznam', 'Import Job ' . \Ease\Functions::cfg('JOB_ID', 'n/a'));
        $this->setDataValue('sumOsv', abs($transactionData->amount->value));
        //$this->setDataValue('sumCelkem', abs($transactionData->amount->value));
        $this->setDataValue('stavUzivK', 'stavUziv.nactenoEl');
        if (property_exists($transactionData->entryDetails->transactionDetails->relatedParties, 'counterParty')) {
            if (property_exists($transactionData->entryDetails->transactionDetails->relatedParties->counterParty, 'name')) {
                $this->setDataValue('nazFirmy', $transactionData->entryDetails->transactionDetails->relatedParties->counterParty->name);
            }
            $this->setDataValue('buc', $transactionData->entryDetails->transactionDetails->relatedParties->counterParty->account->accountNumber);
            $this->setDataValue('smerKod', \AbraFlexi\RO::code($transactionData->entryDetails->transactionDetails->relatedParties->counterParty->organisationIdentification->bankCode));
        }

        $this->setDataValue('banka', $this->bank);
        $this->setDataValue('mena', \AbraFlexi\RO::code($transactionData->amount->currency));
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
//        $this->setDataValue('smerKod', $transactionData);
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

        $this->setDataValue('source', $this->sourceString());
//        echo $this->getJsonizedData() . "\n";
    }

    /**
     * Source Identifier
     * 
     * @return string
     */
    public function sourceString()
    {
        return substr(__FILE__ . '@' . gethostname(), -50);
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

    public function getCurrencyCode()
    {
        return empty($this->bank->getDataValue('mena')->value) ? 'CZK' : \AbraFlexi\RO::uncode($this->bank->getDataValue('mena'));
    }

    /**
     * Is Record with current remoteNumber already present in AbraFlexi ?
     * 
     * @return bool
     */
    public function checkForTransactionPresence()
    {
        return !empty($this->getColumnsFromAbraFlexi('id', ['cisDosle' => $this->getDataValue('cisDosle')]));
    }
}
