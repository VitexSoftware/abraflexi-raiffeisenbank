<?php

namespace AbraFlexi\RaiffeisenBank;

require_once( '../vendor/autoload.php');

/**
 * Get today's tramsactons list
 */
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'CERT_FILE', 'CERT_PASS', 'XIBMCLIENTID', 'ACCOUNT_NUMBER'], isset($argv[1]) ? $argv[1] : '../.env');
BankClient::checkCertificatePresence(\Ease\Functions::cfg('CERT_FILE'));
$engine = new Statementor(\Ease\Functions::cfg('ACCOUNT_NUMBER'));
$engine->setScope(\Ease\Functions::cfg('STATEMENT_IMPORT_SCOPE', 'last_month'));
$engine->import();
