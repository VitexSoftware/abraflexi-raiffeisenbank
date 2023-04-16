<?php

namespace AbraFlexi\RaiffeisenBank;

require_once( '../vendor/autoload.php');


if(isset($argv[1])){
    $envFile = $argv[1];
} else {
    $envFile = '../.env';
}

/**
 * Get today's tramsactons list
 */
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'CERT_FILE', 'CERT_PASS', 'XIBMCLIENTID', 'ACCOUNT_NUMBER'], $envFile);
BankClient::checkCertificatePresence(\Ease\Functions::cfg('CERT_FILE'));

$engine = new Statementor(\Ease\Functions::cfg('ACCOUNT_NUMBER'));
$engine->setScope(\Ease\Functions::cfg('IMPORT_SCOPE','yesterday'));
$engine->import();

