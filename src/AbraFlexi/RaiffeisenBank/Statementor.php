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
 * Description of Statementor.
 *
 * @author vitex
 */
class Statementor extends BankClient
{
    /**
     * Obtain Transactions from RB.
     *
     * @return array
     */
    public function getStatements()
    {
        $apiInstance = new \VitexSoftware\Raiffeisenbank\PremiumAPI\GetStatementListApi();
        $page = 0;
        $statements = [];
        $this->addStatusMessage(sprintf(_('Request statements from %s to %s'), $this->since->format(self::$dateFormat), $this->until->format(self::$dateFormat)), 'debug');

        try {
            $stop = true;

            do {
                $requestBody = new \VitexSoftware\Raiffeisenbank\Model\GetStatementsRequest([
                    'accountNumber' => $this->bank->getDataValue('buc'),
                    'page' => ++$page,
                    'size' => 60,
                    'currency' => $this->getCurrencyCode(),
                    'statementLine' => \Ease\Functions::cfg('STATEMENT_LINE', 'MAIN'),
                    'dateFrom' => $this->since->format(self::$dateFormat),
                    'dateTo' => $this->until->format(self::$dateFormat)]);

                $result = $apiInstance->getStatements($this->getxRequestId(), $requestBody, $page);

                if (empty($result)) {
                    $this->addStatusMessage(sprintf(_('No transactions from %s to %s'), $this->since->format(self::$dateFormat), $this->until->format(self::$dateFormat)));
                    $result['lastPage'] = true;
                }

                if (\array_key_exists('statements', $result)) {
                    $statements = array_merge($statements, $result['statements']);
                }

                if (\array_key_exists('last', $result) && $result['last'] === true) {
                    $stop = true;
                }

                if ($stop === false) {
                    sleep(1);
                }
            } while ($stop === false);
        } catch (\Exception $e) {
            echo 'Exception when calling GetTransactionListApi->getTransactionList: ', $e->getMessage(), \PHP_EOL;
        }

        return $statements;
    }

    public function import(): void
    {
        $statements = $this->getStatements();

        if ($statements) {
            $apiInstance = new \VitexSoftware\Raiffeisenbank\PremiumAPI\DownloadStatementApi();
            $success = 0;

            foreach ($statements as $statement) {
                $requestBody = new \VitexSoftware\Raiffeisenbank\Model\DownloadStatementRequest([
                    'accountNumber' => $this->bank->getDataValue('buc'),
                    'currency' => $this->getCurrencyCode(),
                    'statementId' => $statement->statementId,
                    'statementFormat' => 'xml']);
                $xmlStatementRaw = $apiInstance->downloadStatement($this->getxRequestId(), 'cs', $requestBody);
                $xmlStatement = $xmlStatementRaw->fread($xmlStatementRaw->getSize());
                $statementXML = new \SimpleXMLElement($xmlStatement);

                foreach ($statementXML->BkToCstmrStmt->Stmt->Ntry as $ntry) {
                    $this->dataReset();
                    $this->ntryToAbraFlexi($ntry);
                    $this->setDataValue('vypisCisDokl', $statementXML->BkToCstmrStmt->Stmt->Id);
                    $this->setDataValue('cisSouhrnne', $statementXML->BkToCstmrStmt->Stmt->LglSeqNb);
                    $success = $this->insertTransactionToAbraFlexi($success);
                }

                $this->addStatusMessage('Import done. '.$success.' of '.\count($statements).' imported');
            }
        }
    }

    /**
     * Parse "Ntry" element into \AbraFlexi\Banka data.
     *
     * @param SimpleXMLElement $ntry
     *
     * @return array
     */
    public function ntryToAbraFlexi($ntry)
    {
        $this->setDataValue('typDokl', \AbraFlexi\RO::code(\Ease\Functions::cfg('TYP_DOKLADU', 'STANDARD')));
        $this->setDataValue('bezPolozek', true);
        $this->setDataValue('stavUzivK', 'stavUziv.nactenoEl');
        $this->setDataValue('poznam', 'Import Job '.\Ease\Functions::cfg('JOB_ID', 'n/a'));

        if ((string) $ntry->CdtDbtInd === 'CRDT') {
            $this->setDataValue('rada', \AbraFlexi\RO::code('BANKA+'));
        } else {
            $this->setDataValue('rada', \AbraFlexi\RO::code('BANKA-'));
        }

        $moveTrans = ['DBIT' => 'typPohybu.vydej', 'CRDT' => 'typPohybu.prijem'];
        $this->setDataValue('typPohybuK', $moveTrans[(string) $ntry->CdtDbtInd]);
        $this->setDataValue('cisDosle', (string) $ntry->NtryRef);
        $this->setDataValue('datVyst', \AbraFlexi\RO::dateToFlexiDate(new \DateTime((string) $ntry->BookgDt->DtTm)));
        $this->setDataValue('sumOsv', abs((float) ((string) $ntry->Amt)));
        $this->setDataValue('banka', $this->bank);
        $this->setDataValue('mena', \AbraFlexi\RO::code((string) $ntry->Amt->attributes()->Ccy));

        if (property_exists($ntry, 'NtryDtls')) {
            if (property_exists($ntry->NtryDtls, 'TxDtls')) {
                $conSym = (string) $ntry->NtryDtls->TxDtls->Refs->InstrId;

                if ((int) $conSym) {
                    $conSym = sprintf('%04d', $conSym);
                    $this->ensureKSExists($conSym);
                    $this->setDataValue('konSym', \AbraFlexi\RO::code($conSym));
                }

                if (property_exists($ntry->NtryDtls->TxDtls->Refs, 'EndToEndId')) {
                    $this->setDataValue('varSym', (string) $ntry->NtryDtls->TxDtls->Refs->EndToEndId);
                }

                $transactionData['popis'] = (string) $ntry->NtryDtls->TxDtls->AddtlTxInf;

                if (property_exists($ntry->NtryDtls->TxDtls, 'RltdPties')) {
                    if (property_exists($ntry->NtryDtls->TxDtls->RltdPties, 'DbtrAcct')) {
                        $this->setDataValue('buc', (string) $ntry->NtryDtls->TxDtls->RltdPties->DbtrAcct->Id->Othr->Id);
                    }

                    $this->setDataValue('nazFirmy', (string) $ntry->NtryDtls->TxDtls->RltdPties->DbtrAcct->Nm);
                }

                if (property_exists($ntry->NtryDtls->TxDtls, 'RltdAgts')) {
                    if (property_exists($ntry->NtryDtls->TxDtls->RltdAgts->DbtrAgt, 'FinInstnId')) {
                        $this->setDataValue('smerKod', \AbraFlexi\RO::code((string) $ntry->NtryDtls->TxDtls->RltdAgts->DbtrAgt->FinInstnId->Othr->Id));
                    }
                }
            }
        }

        $this->setDataValue('source', $this->sourceString());

        return $transactionData;
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
            case 'yesterday':
                $this->since = (new \DateTime('yesterday'))->setTime(0, 0);
                $this->until = (new \DateTime('yesterday'))->setTime(23, 59);

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
            case 'previous_month':
                $this->since = new \DateTime('first day of -2 month');
                $this->until = new \DateTime('last day of -2 month');

                break;
            case 'two_months_ago':
                $this->since = new \DateTime('first day of -3 month');
                $this->until = new \DateTime('last day of -3 month');

                break;
            case 'this_year':
                $this->since = new \DateTime('first day of January '.date('Y'));
                $this->until = new \DateTime('last day of December'.date('Y'));

                break;
            case 'January':  // 1
            case 'February': // 2
            case 'March':    // 3
            case 'April':    // 4
            case 'May':      // 5
            case 'June':     // 6
            case 'July':     // 7
            case 'August':   // 8
            case 'September':// 9
            case 'October':  // 10
            case 'November': // 11
            case 'December': // 12
                $this->since = new \DateTime('first day of '.$scope.' '.date('Y'));
                $this->until = new \DateTime('last day of '.$scope.' '.date('Y'));

                break;
            case 'auto':
                $latestRecord = $this->getColumnsFromAbraFlexi(['id', 'lastUpdate'], ['limit' => 1, 'order' => 'lastUpdate@A', 'source' => $this->sourceString(), 'banka' => $this->bank]);

                if (\array_key_exists(0, $latestRecord) && \array_key_exists('lastUpdate', $latestRecord[0])) {
                    $this->since = $latestRecord[0]['lastUpdate'];
                } else {
                    $this->addStatusMessage('Previous record for "auto since" not found. Defaulting to today\'s 00:00', 'warning');
                    $this->since = (new \DateTime())->setTime(0, 0);
                }

                $this->until = new \DateTime(); // Now

                break;

            default:
                if (strstr($scope, '>')) {
                    [$begin, $end] = explode('>', $scope);
                    $this->since = new \DateTime($begin);
                    $this->until = new \DateTime($end);
                } else {
                    throw new \Exception('Unknown scope '.$scope);
                }

                break;
        }

        if ($scope !== 'auto' && $scope !== 'today' && $scope !== 'yesterday') {
            $this->since = $this->since->setTime(0, 0);
            $this->until = $this->until->setTime(0, 0);
        }
    }

    /**
     * Download PDF Statements.
     *
     * @return array<string> Files saved
     */
    public function download(string $saveTo): array
    {
        $downloaded = null;
        $statements = $this->getStatements();

        if ($statements) {
            $apiInstance = new \VitexSoftware\Raiffeisenbank\PremiumAPI\DownloadStatementApi();
            $success = 0;

            foreach ($statements as $statement) {
                $statementNumber = str_replace('/', '_', $statement->statementNumber).'_'.
                        $statement->accountNumber.'_'.
                        $statement->accountId.'_'.
                        $statement->currency.'_'.$statement->dateFrom;
                $statementFilename = $statementNumber.'.pdf';
                $requestBody = new \VitexSoftware\Raiffeisenbank\Model\DownloadStatementRequest([
                    'accountNumber' => $this->bank->getDataValue('buc'),
                    'currency' => $this->getCurrencyCode(),
                    'statementId' => $statement->statementId,
                    'statementFormat' => 'pdf']);
                $pdfStatementRaw = $apiInstance->downloadStatement($this->getxRequestId(), 'cs', $requestBody);
                sleep(1);

                if (file_put_contents($saveTo.'/'.$statementFilename, $pdfStatementRaw->fread($pdfStatementRaw->getSize()))) {
                    $this->addStatusMessage($saveTo.'/'.$statementFilename.' saved', 'success');
                    unset($pdfStatementRaw);
                    $downloaded[$statementNumber] = $saveTo.'/'.$statementFilename;
                    ++$success;
                }
            }

            $this->addStatusMessage('Download done. '.$success.' of '.\count($statements).' saved');
        }

        return $downloaded;
    }
}
