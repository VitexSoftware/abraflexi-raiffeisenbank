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

\define('APP_NAME', 'Importer');

require_once '../vendor/autoload.php';

/**
 * Get today's statements list.
 */

// Parse command line arguments
$options = getopt('s::e::', ['scope::', 'env::']);

// Get the path to the .env file
$envfile = $options['env'] ?? '../.env';

\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'CERT_FILE', 'CERT_PASS', 'XIBMCLIENTID', 'ACCOUNT_NUMBER'], $envfile);
BankClient::checkCertificatePresence(\Ease\Shared::cfg('CERT_FILE'));
$engine = new Statementor(\Ease\Shared::cfg('ACCOUNT_NUMBER'));
$scope = $options['scope'] ?? \Ease\Shared::cfg('IMPORT_SCOPE', 'last_month');
$engine->setScope($scope);
$engine->import();
