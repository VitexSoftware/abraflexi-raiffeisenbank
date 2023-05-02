<?php

/**
 * RaiffeisenBank - Balance.
 *
 * @author     Vítězslav Dvořák <info@vitexsoftware.com>
 * @copyright  (C) 2023 Spoje.Net
 */

namespace AbraFlexi\RaiffeisenBank;

require_once( '../vendor/autoload.php');

const APP_NAME = 'RaiffeisenBankBalance';
/**
 * Get today's tramsactons list
 */
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'CERT_FILE', 'CERT_PASS', 'XIBMCLIENTID', 'ACCOUNT_NUMBER'], isset($argv[1]) ? $argv[1] : '../.env');
Transactor::checkCertificatePresence(\Ease\Functions::cfg('CERT_FILE'));
$apiInstance = new \VitexSoftware\Raiffeisenbank\PremiumAPI\GetAccountBalanceApi();
$xRequestId = time();
try {
    $result = $apiInstance->getBalance($xRequestId, \Ease\Functions::cfg('ACCOUNT_NUMBER'));
    echo json_encode($result, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo 'Exception when calling GetAccountBalanceApi->getBalance: ', $e->getMessage(), PHP_EOL;
}
