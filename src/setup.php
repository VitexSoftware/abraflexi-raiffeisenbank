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

require_once '../vendor/autoload.php';
/**
 * Get List of bank accounts and import it into AbraFlexi.
 */

// Parse command line arguments
$options = getopt('e::', ['env::']);

// Get the path to the .env file
$envfile = $options['env'] ?? '../.env';

\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'CERT_FILE', 'CERT_PASS', 'XIBMCLIENTID'], $envfile);
$apiInstance = new \VitexSoftware\Raiffeisenbank\PremiumAPI\GetAccountsApi();
$x_request_id = (string) time(); // string | Unique request id provided by consumer application for reference and auditing.

Transactor::checkCertificatePresence(\Ease\Shared::cfg('CERT_FILE'));

try {
    $result = $apiInstance->getAccounts($x_request_id);

    if ($result instanceof \VitexSoftware\Raiffeisenbank\Model\GetAccounts200Response && $result->getAccounts()) {
        $banker = new \AbraFlexi\RW(null, ['evidence' => 'bankovni-ucet']);

        if (\Ease\Shared::cfg('APP_DEBUG')) {
            $banker->logBanner($apiInstance->getConfig()->getUserAgent());
        }

        $currentAccounts = $banker->getColumnsFromAbraFlexi(['id', 'kod', 'nazev', 'iban', 'bic', 'nazBanky', 'poznam'], ['limit' => 0], 'iban');

        foreach ($result->getAccounts() as $account) {
            if (\array_key_exists($account->getIban(), $currentAccounts)) {
                $banker->addStatusMessage(sprintf('Account %s already exists in flexibee as %s', $account->getFriendlyName(), $currentAccounts[$account->getIban()]['kod']));
            } else {
                $banker->dataReset();
                $banker->setDataValue('kod', 'RB'.$account->getAccountId());
                $banker->setDataValue('nazev', $account->getAccountName());
                $banker->setDataValue('buc', $account->getAccountNumber());
                $banker->setDataValue('nazBanky', 'Raiffeisenbank');
                $banker->setDataValue('popis', $account->getFriendlyName());
                $banker->setDataValue('iban', $account->getIban());
                $banker->setDataValue('smerKod', \AbraFlexi\Code::ensure($account->getBankCode()));
                $banker->setDataValue('bic', $account->getBankBicCode());
                $saved = $banker->sync();
                $banker->addStatusMessage(
                    sprintf('Account %s registered in flexibee as %s', $account->getFriendlyName(), $banker->getRecordCode()),
                    $saved ? 'success' : 'error',
                );
            }
        }
    }
} catch (\Throwable $e) {
    echo 'Exception when calling GetAccountsApi->getAccounts: ', $e->getMessage(), \PHP_EOL;
}

$event = \Ease\Shared::cfg('ABRAFLEXI_EVENT', false);

if ($event) {
    $eventor = new \AbraFlexi\TypAktivity();
    $eventor->setDataValue('kod', \AbraFlexi\Code::ensure($event));
    $eventor->setDataValue('nazev', $event);
    $eventor->setDataValue('druhUdalK', 'druhUdal.udal');

    if ($eventor->recordExists($eventor->getData()) === false) {
        $result = $eventor->sync();
        $eventor->addStatusMessage(sprintf(_('Event Type %s %s created'), $event, $eventor->getRecordCode()), $result ? 'success' : 'error');
    }
}
