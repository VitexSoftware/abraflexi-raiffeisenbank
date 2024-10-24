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
\define('APP_NAME', 'Importer');
/**
 * Get today's transactions list.
 */
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'CERT_FILE', 'CERT_PASS', 'XIBMCLIENTID', 'ACCOUNT_NUMBER'], $argv[1] ?? '../.env');
Transactor::checkCertificatePresence(\Ease\Functions::cfg('CERT_FILE'));
$engine = new Transactor(\Ease\Functions::cfg('ACCOUNT_NUMBER'));
$engine->setScope(\Ease\Functions::cfg('IMPORT_SCOPE', 'yesterday'));
$engine->import();
