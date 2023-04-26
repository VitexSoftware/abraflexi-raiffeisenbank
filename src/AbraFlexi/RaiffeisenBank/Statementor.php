<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace AbraFlexi\RaiffeisenBank;

/**
 * Description of Statementor
 *
 * @author vitex
 */
class Statementor extends BankClient
{

    /**
     * Obtain Transactions from RB
     * 
     * @return array
     */
    public function getStatements()
    {
        $apiInstance = new \VitexSoftware\Raiffeisenbank\PremiumAPI\GetStatementListApi();
        $page = 1;
        $statements = [];
        $requestBody = new \VitexSoftware\Raiffeisenbank\Model\GetStatementsRequest(['accountNumber' => $this->bank->getDataValue('buc'), 'currency' => $this->getCurrencyCode(), 'statementLine' => 'MAIN', 'dateFrom' => $this->since->format(self::$dateFormat), 'dateTo' => $this->until->format(self::$dateFormat)]);
        $this->addStatusMessage(sprintf(_('Request statements from %s to %s'), $this->since->format(self::$dateFormat), $this->until->format(self::$dateFormat)), 'debug');
        try {
            do {
                $result = $apiInstance->getStatements($this->getxRequestId(), $requestBody, $page);
                if (empty($result)) {
                    $this->addStatusMessage(sprintf(_('No transactions from %s to %s'), $this->since->format(self::$dateFormat), $this->until->format(self::$dateFormat)));
                    $result['lastPage'] = true;
                }
                if (array_key_exists('statements', $result)) {
                    $statements = array_merge($statements, $result['statements']);
                }
            } while ($result['last'] === false);
        } catch (Exception $e) {
            echo 'Exception when calling GetTransactionListApi->getTransactionList: ', $e->getMessage(), PHP_EOL;
        }
        return $statements;
    }

    public function import()
    {
        $statements = $this->getStatements();
        if ($statements) {
            $apiInstance = new \VitexSoftware\Raiffeisenbank\PremiumAPI\DownloadStatementApi();
            $success = 0;
            foreach ($statements as $statement) {
                $requestBody = new \VitexSoftware\Raiffeisenbank\Model\DownloadStatementRequest(['accountNumber' => $this->bank->getDataValue('buc'), 'currency' => $this->getCurrencyCode(), 'statementId' => $statement->statementId, 'statementFormat' => 'xml']);
                $xmlStatementRaw = $apiInstance->downloadStatement($this->getxRequestId(), 'cs', $requestBody);
                $statementXML = new \SimpleXMLElement($xmlStatementRaw);
                foreach ($statementXML->BkToCstmrStmt->Stmt->Ntry as $ntry) {
                    $this->dataReset();
                    $this->ntryToAbraFlexi($ntry);
                    $this->setDataValue('vypisCisDokl', $statementXML->BkToCstmrStmt->Stmt->Id);
                    $this->setDataValue('cisSouhrnne', $statementXML->BkToCstmrStmt->Stmt->LglSeqNb);
                    if ($this->checkForTransactionPresence() === false) {
                        try {
                            $this->addStatusMessage('New entry ' . $this->getRecordIdent() . ' ' . $this->getDataValue('nazFirmy') . ': ' . $this->getDataValue('popis') . ' ' . $this->getDataValue('sumOsv') . ' ' . \AbraFlexi\RO::uncode($this->getDataValue('mena')), $this->sync() ? 'success' : 'error');
                            $success++;
                        } catch (\AbraFlexi\Exception $exc) {
                            
                        }
                    } else {
                        $this->addStatusMessage('Record with remoteNumber ' . $this->getDataValue('cisDosle') . ' already present in AbraFlexi', 'warning');
                    }
                }
                $this->addStatusMessage('Import done. ' . $success . ' of ' . count($statements) . ' imported');
            }
        }
    }

    /**
     * Parse Ntry element into \AbraFlexi\Banka data
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
        $this->setDataValue('poznam', 'Import Job ' . \Ease\Functions::cfg('JOB_ID', 'n/a'));
        if (trim($ntry->CdtDbtInd) == 'CRDT') {
            $this->setDataValue('rada', \AbraFlexi\RO::code('BANKA+'));
        } else {
            $this->setDataValue('rada', \AbraFlexi\RO::code('BANKA-'));
        }

        $moveTrans = ['DBIT' => 'typPohybu.vydej', 'CRDT' => 'typPohybu.prijem'];
        $this->setDataValue('typPohybuK', $moveTrans[trim($ntry->CdtDbtInd)]);
        $this->setDataValue('cisDosle', strval($ntry->NtryRef));
        $this->setDataValue('datVyst', \AbraFlexi\RO::dateToFlexiDate(new \DateTime($ntry->BookgDt->DtTm)));
        $this->setDataValue('sumOsv', abs($ntry->Amt));
        $this->setDataValue('banka', $this->bank);
        $this->setDataValue('mena', \AbraFlexi\RO::code($ntry->Amt->attributes()->Ccy));
        if (property_exists($ntry, 'NtryDtls')) {

            if (property_exists($ntry->NtryDtls, 'TxDtls')) {

                $conSym = $ntry->NtryDtls->TxDtls->Refs->InstrId;
                if (intval($conSym)) {
                    $conSym = sprintf('%04d', $conSym);
                    $this->ensureKSExists($conSym);
                    $this->setDataValue('konSym', \AbraFlexi\RO::code($conSym));
                }

                if (property_exists($ntry->NtryDtls->TxDtls->Refs, 'EndToEndId')) {
                    $this->setDataValue('varSym', $ntry->NtryDtls->TxDtls->Refs->EndToEndId);
                }
                $transactionData['popis'] = $ntry->NtryDtls->TxDtls->AddtlTxInf;
                if (property_exists($ntry->NtryDtls->TxDtls, 'RltdPties')) {
                    if (property_exists($ntry->NtryDtls->TxDtls->RltdPties, 'DbtrAcct')) {
                        $this->setDataValue('buc', $ntry->NtryDtls->TxDtls->RltdPties->DbtrAcct->Id->Othr->Id);
                    }
                    $this->setDataValue('nazFirmy', $ntry->NtryDtls->TxDtls->RltdPties->DbtrAcct->Nm);
                }

                if (property_exists($ntry->NtryDtls->TxDtls, 'RltdAgts')) {

                    if (property_exists($ntry->NtryDtls->TxDtls->RltdAgts->DbtrAgt, 'FinInstnId')) {
                        $this->setDataValue('smerKod', \AbraFlexi\RO::code($ntry->NtryDtls->TxDtls->RltdAgts->DbtrAgt->FinInstnId->Othr->Id));
                    }
                }
            }
        }

        $this->setDataValue('source', $this->sourceString());
        return $transactionData;
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
}
