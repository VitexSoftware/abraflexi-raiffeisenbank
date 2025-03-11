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
    public string $statementLine = 'MAIN';
    private int $exitCode = 0;

    /**
     * Take care about Statements.
     *
     * @param string                $bankAccount
     * @param array<string, string> $options
     */
    public function __construct($bankAccount, $options = [])
    {
        parent::__construct($bankAccount, $options);
        $this->setupProperty($options, 'statementLine', 'STATEMENT_LINE');
    }

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
                    'statementLine' => $this->statementLine,
                    'dateFrom' => $this->since->format(self::$dateFormat),
                    'dateTo' => $this->until->format(self::$dateFormat)]);

                $result = $apiInstance->getStatements($this->getxRequestId(), $requestBody, $page);

                if (empty($result)) {
                    $this->addStatusMessage(sprintf(_('No Statements from %s to %s'), $this->since->format(self::$dateFormat), $this->until->format(self::$dateFormat)));
                    $result['lastPage'] = true;
                }

                if (\array_key_exists('statements', (array) $result)) {
                    $statements = array_merge($statements, $result['statements']);
                }

                if (\array_key_exists('last', (array) $result) && $result['last'] === true) {
                    $stop = true;
                }

                if ($stop === false) {
                    sleep(1);
                }
            } while ($stop === false);
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

            $this->addStatusMessage('Exception when calling GetStatementsRequest: '.$errorMessage, 'error', $apiInstance);

            $this->exitCode = (int) $errorCode;
        }

        return $statements;
    }

    public function getStatementLine(): string
    {
        return $this->statementLine;
    }

    public function import(): array
    {
        $imported = [];
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
                    $imported[$this->getRecordIdent()] = $this->getDataValue('buc').' '.$this->getDataValue('sumOsv').' '.$this->getDataValue('mena');
                }

                $this->addStatusMessage('Import done. '.$success.' of '.\count($statements).' imported');
            }
        }

        return $imported;
    }

    /**
     * Parse "Ntry" element into \AbraFlexi\Banka data.
     *
     * @see https://www.clearstream.com/resource/blob/3738792/fa564142233c781ec2c2126a54289561/swift-ug-cbpr-data.pdf
     * @see https://demo.flexibee.eu/c/demo/banka/properties
     *
     * @param \SimpleXMLElement $ntry
     *
     * @return array
     */
    public function ntryToAbraFlexi($ntry)
    {
        $this->setDataValue('typDokl', \AbraFlexi\Functions::code(\Ease\Shared::cfg('TYP_DOKLADU', 'STANDARD')));
        $this->setDataValue('bezPolozek', true);
        $this->setDataValue('stavUzivK', 'stavUziv.nactenoEl');
        $this->setDataValue('poznam', 'Import Job '.\Ease\Shared::cfg('MULTIFLEXI_JOB_ID', \Ease\Shared::cfg('JOB_ID', 'n/a')));

        if ((string) $ntry->CdtDbtInd === 'CRDT') {
            $this->setDataValue('rada', \AbraFlexi\Functions::code('BANKA+'));
        } else {
            $this->setDataValue('rada', \AbraFlexi\Functions::code('BANKA-'));
        }

        $moveTrans = ['DBIT' => 'typPohybu.vydej', 'CRDT' => 'typPohybu.prijem'];
        $this->setDataValue('typPohybuK', $moveTrans[(string) $ntry->CdtDbtInd]);
        $this->setDataValue('cisDosle', (string) $ntry->NtryRef);
        $this->setDataValue('datVyst', \AbraFlexi\RO::dateToFlexiDate(new \DateTime((string) $ntry->BookgDt->DtTm)));
        $this->setDataValue('sumOsv', abs((float) ((string) $ntry->Amt)));
        $this->setDataValue('banka', $this->bank);
        $this->setDataValue('mena', \AbraFlexi\Functions::code((string) $ntry->Amt->attributes()->Ccy));

        if (\property_exists($ntry, 'NtryDtls')) {
            if (\property_exists($ntry->NtryDtls, 'TxDtls')) {
                $conSym = (string) $ntry->NtryDtls->TxDtls->Refs->InstrId;

                if ((int) $conSym) {
                    $conSym = sprintf('%04d', $conSym);
                    $this->ensureKSExists($conSym);
                    $this->setDataValue('konSym', \AbraFlexi\Functions::code($conSym));
                }

                if (property_exists($ntry->NtryDtls->TxDtls->Refs, 'EndToEndId')) {
                    $this->setDataValue('varSym', (string) $ntry->NtryDtls->TxDtls->Refs->EndToEndId);
                }

                $this->setDataValue('popis', (string) $ntry->NtryDtls->TxDtls->AddtlTxInf);

                if (\property_exists($ntry->NtryDtls->TxDtls, 'RltdPties')) {
                    if (property_exists($ntry->NtryDtls->TxDtls->RltdPties, 'CdtrAcct')) {
                        $this->setDataValue('buc', (string) $ntry->NtryDtls->TxDtls->RltdPties->CdtrAcct->Id->Othr->Id);
                        $this->setDataValue('nazFirmy', (string) $ntry->NtryDtls->TxDtls->RltdPties->CdtrAcct->Nm);
                    }

                    if (\property_exists($ntry->NtryDtls->TxDtls->RltdPties, 'DbtrAcct')) {
                        $this->setDataValue('buc', (string) $ntry->NtryDtls->TxDtls->RltdPties->DbtrAcct->Id->Othr->Id);
                        $this->setDataValue('nazFirmy', (string) $ntry->NtryDtls->TxDtls->RltdPties->DbtrAcct->Nm);
                    }
                }

                if (\property_exists($ntry->NtryDtls->TxDtls, 'RltdAgts')) {
                    if (property_exists($ntry->NtryDtls->TxDtls->RltdAgts->DbtrAgt, 'FinInstnId')) {
                        $this->setDataValue('smerKod', \AbraFlexi\Functions::code((string) $ntry->NtryDtls->TxDtls->RltdAgts->DbtrAgt->FinInstnId->Othr->Id));
                    }
                }
            }
        }

        $this->setDataValue('source', $this->sourceString());

        return $this->getData();
    }

    /**
     * Download PDF Statements.
     *
     * @return array<string> Files saved
     */
    public function download(string $saveTo): array
    {
        $downloaded = [];
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

    public function getExitCode(): int
    {
        return $this->exitCode;
    }
}
