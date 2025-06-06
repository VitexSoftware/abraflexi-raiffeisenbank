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
    public static array $moveTrans = [
        'DBIT' => 'typPohybu.vydej',
        'CRDT' => 'typPohybu.prijem',
    ];

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
     * @return array<VitexSoftware\Raiffeisenbank\Model\GetTransactionList200ResponseTransactionsInner>
     */
    public function getTransactions(): array
    {
        $apiInstance = new \VitexSoftware\Raiffeisenbank\PremiumAPI\GetTransactionListApi();
        $page = 1;
        $transactions = [];
        $this->addStatusMessage(sprintf(_('Request transactions from %s to %s'), $this->since->format(self::$dateTimeFormat), $this->until->format(self::$dateTimeFormat)), 'debug');

        try {
            do {
                $result = $apiInstance->getTransactionList($this->getxRequestId(), $this->bank->getDataValue('buc'), $this->getCurrencyCode(), $this->since->format(self::$dateTimeFormat), $this->until->format(self::$dateTimeFormat), $page);
                $transactions = $result->getTransactions();

                if (empty($transactions)) {
                    $this->addStatusMessage(sprintf(_('No transactions from %s to %s'), $this->since->format(self::$dateTimeFormat), $this->until->format(self::$dateTimeFormat)));
                    $lastPage = true;
                } else {
                    $lastPage = $result->getLastPage() ?? true; // Access lastPage using a method or property

                    foreach ($transactions as $transaction) {
                        if ($transaction instanceof \VitexSoftware\Raiffeisenbank\Model\GetTransactionList200ResponseTransactionsInner) {
                            $this->takeTransactionData($transaction); // Ensure correct type is passed
                        } else {
                            $this->addStatusMessage('Invalid transaction object type', 'error');
                        }
                    }

                    ++$page;
                }
            } while ($lastPage === false);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            preg_match('/cURL error ([0-9]+)/', $errorMessage, $matches);

            if (\array_key_exists(1, $matches)) {
                $errorCode = $matches[1];
            } elseif (preg_match('/\[([0-9]+)\]/', $errorMessage, $matches)) {
                $errorCode = $matches[1];
            } else {
                $errorCode = 2;
            }

            $this->addStatusMessage('Exception when calling GetTransactionListApi->getTransactionList: '.$errorMessage, 'error', $apiInstance);

            exit((int) $errorCode);
        }

        return (array) $transactions;
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

            if ($transaction->getCreditDebitIndication()) {
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
     */
    public function takeTransactionData(\VitexSoftware\Raiffeisenbank\Model\GetTransactionList200ResponseTransactionsInner $transaction): void
    {
        //        $this->setMyKey(\AbraFlexi\RO::code('RB' . $transactionData->entryReference));
        $this->setDataValue('bezPolozek', true);
        $this->setDataValue('typDokl', \AbraFlexi\Functions::code(\Ease\Shared::cfg('TYP_DOKLADU', 'STANDARD')));
        $this->setDataValue('typPohybuK', self::$moveTrans[$transaction->getCreditDebitIndication()]);
        $this->setDataValue('cisDosle', $transaction->getEntryReference());

        $entryDetails = $transaction->getEntryDetails();

        if ($entryDetails && $entryDetails->getTransactionDetails()) {
            $transactionDetails = $entryDetails->getTransactionDetails();
            $remittanceInformation = $transactionDetails->getRemittanceInformation();

            if ($remittanceInformation) {
                $this->setDataValue('popis', $remittanceInformation->getOriginatorMessage());

                $creditorReferenceInformation = $remittanceInformation->getCreditorReferenceInformation();

                if ($creditorReferenceInformation) {
                    $this->setDataValue('varSym', $creditorReferenceInformation->getVariable());

                    $constant = $creditorReferenceInformation->getConstant();

                    if ($constant) {
                        $conSym = sprintf('%04d', $constant);
                        $this->ensureKSExists($conSym);
                        $this->setDataValue('konSym', \AbraFlexi\RO::code($conSym));
                    }
                }
            }
        }

        $this->setDataValue('datVyst', $transaction->getBookingDate());
        $this->setDataValue('poznam', 'Import Job '.\Ease\Shared::cfg('MULTIFLEXI_JOB_ID', 'n/a'));
        $this->setDataValue('sumOsv', abs($transaction->getAmount()->getValue()));
        $this->setDataValue('stavUzivK', 'stavUziv.nactenoEl');

        $relatedParties = $transactionDetails->getRelatedParties();

        if ($relatedParties && $relatedParties->getCounterParty()) {
            $counterParty = $relatedParties->getCounterParty();
            $this->setDataValue('nazFirmy', $counterParty->getName());
            $this->setDataValue('buc', $counterParty->getAccount()->getAccountNumber());
            $this->setDataValue('smerKod', \AbraFlexi\Functions::code($counterParty->getOrganisationIdentification()->getBankCode()));
        }

        $this->setDataValue('banka', $this->bank);
        $this->setDataValue('mena', \AbraFlexi\Functions::code($transaction->getAmount()->getCurrency()));
        $this->setDataValue('source', $this->sourceString());

        if ((string) $transaction->getCreditDebitIndication() === 'CRDT') {
            $this->setDataValue('rada', \AbraFlexi\Functions::code('BANKA+'));
        } else {
            $this->setDataValue('rada', \AbraFlexi\Functions::code('BANKA-'));
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
                $this->until = (new \DateTime())->setTime(23, 59, 59, 999);

                break;
            case 'yesterday':
                $this->since = (new \DateTime('yesterday'))->setTime(0, 0);
                $this->until = (new \DateTime('yesterday'))->setTime(23, 59, 59, 999);

                break;
            case 'current_month':
                $this->since = new \DateTime('first day of this month');
                $this->until = new \DateTime();

                break;
            case 'last_month':
                $this->since = new \DateTime('first day of last month');
                $this->until = new \DateTime('last day of last month');

                break;
            case 'last_two_months':
                $this->since = (new \DateTime('first day of last month'))->modify('-1 month');
                $this->until = (new \DateTime('last day of last month'));

                break;
            case 'auto':
                $latestRecord = $this->getColumnsFromAbraFlexi(['id', 'lastUpdate'], ['limit' => 1, 'order' => 'lastUpdate@A', 'source' => $this->sourceString(), 'banka' => $this->bank]);

                if (\array_key_exists(0, $latestRecord) && \array_key_exists('lastUpdate', $latestRecord[0])) {
                    $lastUpdate = $latestRecord[0]['lastUpdate'];
                    $maxDate = (new \DateTime('89 days ago'))->setTime(0, 0);

                    if ($lastUpdate < $maxDate) {
                        $this->since = $maxDate;
                    } else {
                        $this->since = $lastUpdate;
                    }
                } else {
                    $this->since = (new \DateTime('89 days ago'))->setTime(23, 59, 59, 999);
                }

                $this->until = (new \DateTime('now'))->setTime(23, 59, 59, 999);

                break;

            default:
                if (strstr($scope, '>')) {
                    [$begin, $end] = explode('>', $scope);
                    $this->since = new \DateTime($begin);
                    $this->until = new \DateTime($end);
                } else {
                    if (preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/', $scope)) {
                        $this->since = new \DateTime($scope);
                        $this->until = (new \DateTime($scope))->setTime(23, 59, 59, 999);

                        break;
                    }

                    throw new \Exception('Unknown scope '.$scope);
                }

                break;
        }

        if ($scope !== 'auto' && $scope !== 'today' && $scope !== 'yesterday' && strstr($scope, '-')) {
            $this->since = $this->since->setTime(0, 0);
            $this->until = $this->until->setTime(23, 59, 59, 999);
        }
    }
}
