<?php

namespace AbraFlexi\RaiffeisenBank;

require_once( '../vendor/autoload.php');

if (isset($argv[1])) {
    $envFile = $argv[1];
} else {
    $envFile = '../.env';
}

/**
 * Get List of bank accounts and import it into AbraFlexi
 */
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'CERT_FILE', 'CERT_PASS', 'XIBMCLIENTID'], $envFile);
$apiInstance = new \VitexSoftware\Raiffeisenbank\PremiumAPI\GetAccountsApi();
$x_request_id = time(); // string | Unique request id provided by consumer application for reference and auditing.

try {
    $result = $apiInstance->getAccounts($x_request_id);
    if (array_key_exists('accounts', $result)) {

        $banker = new \AbraFlexi\RW(null, ['evidence' => 'bankovni-ucet']);
        if (\Ease\Functions::cfg('APP_DEBUG')) {
            $banker->logBanner($apiInstance->getConfig()->getUserAgent());
        }
        $currentAccounts = $banker->getColumnsFromAbraFlexi(['id', 'kod', 'nazev', 'iban', 'bic', 'nazBanky', 'poznam'], ['limit' => 0], 'iban');
        foreach ($result['accounts'] as $account) {

            if (array_key_exists($account->iban, $currentAccounts)) {
                $banker->addStatusMessage(sprintf('Account %s already exists in flexibee as %s', $account->friendlyName, $currentAccounts[$account->iban]['kod']));
            } else {
                $banker->dataReset();
                $banker->setDataValue('kod', 'RB' . $account->accountId);
                $banker->setDataValue('nazev', $account->accountName);
                $banker->setDataValue('buc', $account->accountNumber);
                $banker->setDataValue('nazBanky', 'Raiffeisenbank');
                $banker->setDataValue('popis', $account->friendlyName);
                $banker->setDataValue('iban', $account->iban);
                $banker->setDataValue('smerKod', \AbraFlexi\RO::code($account->bankCode));
                $banker->setDataValue('bic', $account->bankBicCode);
                $saved = $banker->sync();
                $banker->addStatusMessage(
                        sprintf('Account %s registered in flexibee as %s', $account->friendlyName, $banker->getRecordCode()),
                        ($saved ? 'success' : 'error')
                );
            }
        }
    }
} catch (Exception $e) {
    echo 'Exception when calling GetAccountsApi->getAccounts: ', $e->getMessage(), PHP_EOL;
}

