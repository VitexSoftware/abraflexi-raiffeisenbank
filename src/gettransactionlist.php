<?php

namespace AbraFlexi\RaiffeisenBank;

require_once( '../vendor/autoload.php');
/**
 * Get today's tramsactons list
 */
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'CERT_FILE', 'CERT_PASS', 'XIBMCLIENTID', 'ACCOUNT_NUMBER'], '../.env');
$engine = new Transactor(\Ease\Functions::cfg('ACCOUNT_NUMBER'));
$engine->setScope('last_month');
$engine->import();
