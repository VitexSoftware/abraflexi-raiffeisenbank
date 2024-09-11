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
 * Description of ApiClient.
 *
 * @author vitex
 */
abstract class BankClient extends \AbraFlexi\Banka
{
    /**
     * DateTime Formating eg. 2021-08-01T10:00:00.0Z.
     */
    public static string $dateTimeFormat = 'Y-m-d\\TH:i:s.0\\Z';

    /**
     * DateTime Formating eg. 2021-08-01T10:00:00.0Z.
     */
    public static string $dateFormat = 'Y-m-d';
    protected \AbraFlexi\KonstSymbol $constantor;
    protected ?array $constSymbols;
    protected \DateTime $since;
    protected \DateTime $until;
    protected \AbraFlexi\BankovniUcet $bank;

    /**
     * Transaction Handler.
     *
     * @param string $bankAccount Account Number
     * @param array  $options
     */
    public function __construct($bankAccount, $options = [])
    {
        parent::__construct(null, $options);
        $this->bank = $this->getBank($bankAccount);
        $this->constantor = new \AbraFlexi\KonstSymbol();
        $this->constSymbols = $this->constantor->getColumnsFromAbraFlexi(['kod'], ['limit' => 0], 'kod');
    }

    /**
     * Source Identifier.
     *
     * @return string
     */
    public function sourceString()
    {
        return substr(__FILE__.'@'.gethostname(), -50);
    }

    /**
     * Try to check certificate readibilty.
     *
     * @param string $certFile path to certificate
     */
    public static function checkCertificatePresence($certFile): void
    {
        if ((file_exists($certFile) === false) || (is_readable($certFile) === false)) {
            fwrite(\STDERR, 'Cannot read specified certificate file: '.$certFile.\PHP_EOL);

            exit(1);
        }
    }

    /**
     * Gives you AbraFlexi Bank.
     *
     * @param string $accountNumber
     *
     * @throws Exception
     */
    public function getBank($accountNumber): \AbraFlexi\BankovniUcet
    {
        $banker = new \AbraFlexi\BankovniUcet();
        $candidat = $banker->getColumnsFromAbraFlexi('id', ['buc' => $accountNumber]);

        if (empty($candidat) || !\array_key_exists('id', $candidat[0])) {
            throw new \Exception('Bank account '.$accountNumber.' not found in AbraFlexi');
        }

        $banker->loadFromAbraFlexi($candidat[0]['id']);

        return $banker;
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
                throw new \Exception('Unknown scope '.$scope);

                break;
        }

        if ($scope !== 'auto' && $scope !== 'today' && $scope !== 'yesterday') {
            $this->since = $this->since->setTime(0, 0);
            $this->until = $this->until->setTime(0, 0);
        }
    }

    /**
     * Request Identifier.
     *
     * @return string
     */
    public function getxRequestId()
    {
        return $this->bank->getDataValue('buc').time();
    }

    /**
     * Obtain Current Currency.
     *
     * @return string
     */
    public function getCurrencyCode()
    {
        return empty((string) $this->bank->getDataValue('mena')) ? 'CZK' : \AbraFlexi\Functions::uncode((string) $this->bank->getDataValue('mena'));
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

    /**
     * @param string $conSym
     */
    public function ensureKSExists($conSym): void
    {
        if (!\array_key_exists($conSym, $this->constSymbols)) {
            $this->constantor->insertToAbraFlexi(['kod' => $conSym, 'poznam' => 'Created by Raiffeisen Bank importer', 'nazev' => '?!?!? '.$conSym]);
            $this->constantor->addStatusMessage('New constant '.$conSym.' created in flexibee', 'warning');
            $this->constSymbols[$conSym] = $conSym;
        }
    }

    /**
     * @param int $success
     *
     * @return int
     */
    public function insertTransactionToAbraFlexi($success)
    {
        if ($this->checkForTransactionPresence() === false) {
            try {
                $this->setDataValue('stredisko', ''); // HOTFIX For [{"message":"Pole 'St\u0159edisko' obsahuje neplatnou polo\u017eku \u010d\u00edseln\u00edku. [B+0001\/2024]","for":"stredisko","path":"banka[temporary-id=null].stredisko","code":"INVALID"}]
                $result = $this->sync();
            } catch (\AbraFlexi\Exception $exc) {
                exit(1);
            }

            $this->addStatusMessage('New entry '.$this->getRecordIdent().' '.$this->getDataValue('nazFirmy').': '.$this->getDataValue('popis').' '.$this->getDataValue('sumOsv').' '.\AbraFlexi\Functions::uncode((string) $this->getDataValue('mena')), $result ? 'success' : 'error');
            ++$success;
        } else {
            $this->addStatusMessage('Record with remoteNumber '.$this->getDataValue('cisDosle').' already present in AbraFlexi', 'warning');
        }

        return $success;
    }
}
