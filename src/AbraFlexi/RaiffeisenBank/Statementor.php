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
        $transactions = [];
        $requestBody = new \VitexSoftware\Raiffeisenbank\Model\GetStatementsRequest(['accountNumber'=>$this->bank->getDataValue('buc'),'currency'=> $this->getCurrencyCode(), 'statementLine'=>'MT940', 'dateFrom'=>$this->since->format(self::$dateTimeFormat), 'dateTo'=> $this->until->format(self::$dateTimeFormat) ]);
       
        $this->addStatusMessage(sprintf(_('Request statements from %s to %s'), $this->since->format(self::$dateTimeFormat), $this->until->format(self::$dateTimeFormat)), 'debug');
        try {
            do {
                $result = $apiInstance->getStatements($this->getxRequestId(), $requestBody,  $page);
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
    
    public function import()
    {
        $statements = $this->getStatements();
        print_r($statements);
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
