<?php

/**
 * @author vitex
 */
require_once( '../vendor/autoload.php');
/**
 * Get List of bank accounts and import it into AbraFlexi
 */
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'CERT_FILE', 'CERT_PASS', 'XIBMCLIENTID'], isset($argv[1]) ? $argv[1] : '../.env');
$apiInstance = new \VitexSoftware\Raiffeisenbank\PremiumAPI\GetAccountsApi();
$x_request_id = time(); // string | Unique request id provided by consumer application for reference and auditing.

Transactor::checkCertificatePresence(\Ease\Functions::cfg('CERT_FILE'));
