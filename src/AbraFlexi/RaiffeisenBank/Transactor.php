<?php

declare(strict_types=1);

/**
 * This file is part of the AbraFlexi-RaiffeisenBank package
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AbraFlexi\RaiffeisenBank;

/**
 * Handle bank transactions.
 *
 * @author vitex
 */
class Transactor extends BankClient
{
    /**
     * Transaction Handler.
     *
     * @param string $bankAccount
     * @param array  $options
     */
    public function __construct($bankAccount, $options = [])
    {
        parent::__construct($bankAccount, $options);
    }

    /**
     * Obtain Transactions from RB.
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

                if (\array_key_exists('transactions', $result)) {
                    $transactions = array_merge($transactions, $result['transactions']);
                }
            } while ($result['lastPage'] === false);
        } catch (Exception $e) {
            echo 'Exception when calling GetTransactionListApi->getTransactionList: ', $e->getMessage(), \PHP_EOL;
        }

        return $transactions;
    }

    /**
     * Import process itself.
     */
    public function import(): array
    {
        $payments = [];
        //        $allMoves = $this->getColumnsFromAbraFlexi('id', ['limit' => 0, 'banka' => $this->bank]);
        $allTransactions = $this->getTransactions();
        $success = 0;

        foreach ($allTransactions as $transaction) {
            $this->dataReset();

            if (property_exists($transaction, 'creditDebitIndication')) {
                $this->takeTransactionData($transaction);
                $success = $this->insertTransactionToAbraFlexi($success);
                $payments[] = $this->getRecordIdent();
            } else {
                $this->addStatusMessage('Skipping transaction without creditDebitIndication', 'warning');
            }
        }

        $this->addStatusMessage('Import done. '.$success.' of '.\count($allTransactions).' imported');
        return $payments;
    }

    /**
     * Use Transaction data for Bank record.
     *
     * @param array $transactionData
     */
    public function takeTransactionData($transactionData): void
    {
        //        $this->setMyKey(\AbraFlexi\RO::code('RB' . $transactionData->entryReference));
        $this->setDataValue('bezPolozek', true);
        $this->setDataValue('typDokl', \AbraFlexi\RO::code(\Ease\Functions::cfg('TYP_DOKLADU', 'STANDARD')));
        $moveTrans = [
            'DBIT' => 'typPohybu.vydej',
            'CRDT' => 'typPohybu.prijem',
        ];
        $this->setDataValue('typPohybuK', $moveTrans[$transactionData->creditDebitIndication]);
        $this->setDataValue('cisDosle', $transactionData->entryReference);

        if (property_exists($transactionData->entryDetails, 'transactionDetails')) {
            if (property_exists($transactionData->entryDetails->transactionDetails, 'remittanceInformation') && property_exists($transactionData->entryDetails->transactionDetails->remittanceInformation, 'creditorReferenceInformation')) {
                if (property_exists($transactionData->entryDetails->transactionDetails->remittanceInformation->creditorReferenceInformation, 'variable')) {
                    $this->setDataValue('varSym', $transactionData->entryDetails->transactionDetails->remittanceInformation->creditorReferenceInformation->variable);
                }

                if (property_exists($transactionData->entryDetails->transactionDetails->remittanceInformation->creditorReferenceInformation, 'constant')) {
                    $conSym = $transactionData->entryDetails->transactionDetails->remittanceInformation->creditorReferenceInformation->constant;

                    if ((int) $conSym) {
                        $conSym = sprintf('%04d', $conSym);
                        $this->ensureKSExists($conSym);
                        $this->setDataValue('konSym', \AbraFlexi\RO::code($conSym));
                    }
                }
            }
        }

        $this->setDataValue('datVyst', $transactionData->bookingDate);

        // $this->setDataValue('duzpPuv', $transactionData->valueDate);
        if (property_exists($transactionData->entryDetails->transactionDetails->remittanceInformation, 'originatorMessage')) {
            $this->setDataValue('popis', $transactionData->entryDetails->transactionDetails->remittanceInformation->originatorMessage);
        }

        $this->setDataValue('poznam', 'Import Job '.\Ease\Functions::cfg('JOB_ID', 'n/a'));
        $this->setDataValue('sumOsv', abs($transactionData->amount->value));
        // $this->setDataValue('sumCelkem', abs($transactionData->amount->value));
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

        if ((string) $transactionData->creditDebitIndication === 'CRDT') {
            $this->setDataValue('rada', \AbraFlexi\RO::code('BANKA+'));
        } else {
            $this->setDataValue('rada', \AbraFlexi\RO::code('BANKA-'));
        }
    }

    /**
     * Prepare processing interval.
     *
     * @param string $scope
     *
     * @throws \Exception
     */
    public function setScope($scope): void
    {
        switch ($scope) {
            case 'today':
                $this->since = (new \DateTime())->setTime(0, 0);
                $this->until = (new \DateTime())->setTime(23, 59);

                break;
            case 'yesterday':
                $this->since = (new \DateTime('yesterday'))->setTime(0, 0);
                $this->until = (new \DateTime('yesterday'))->setTime(23, 59, 59, 999);

                break;
            case 'auto':
                $latestRecord = $this->getColumnsFromAbraFlexi(['id', 'lastUpdate'], ['limit' => 1, 'order' => 'lastUpdate@A', 'source' => $this->sourceString(), 'banka' => $this->bank]);

                if (\array_key_exists(0, $latestRecord) && \array_key_exists('lastUpdate', $latestRecord[0])) {
                    $this->since = $latestRecord[0]['lastUpdate'];
                } else {
                    $this->since = (new \DateTime('89 days ago'))->setTime(0, 0);
                }

                $this->until = (new \DateTime('now'))->setTime(0, 0);

                break;

            default:
                if (strstr($scope, '>')) {
                    [$begin, $end] = explode('>', $scope);
                    $this->since = new \DateTime($begin);
                    $this->until = new \DateTime($end);
                } else {
                    if (preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/', $scope)) {
                        $this->since = new \DateTime($scope);
                        $this->until = (new \DateTime($scope))->setTime(23, 59);

                        break;
                    }

                    throw new \Exception('Unknown scope '.$scope);
                }

                break;
        }

        if ($scope !== 'auto' && $scope !== 'today' && $scope !== 'yesterday' && strstr($scope, '-')) {
            $this->since = $this->since->setTime(0, 0);
            $this->until = $this->until->setTime(23, 59);
        }
    }
}
