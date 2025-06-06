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
\define('APP_NAME', 'RBTransactions2AbraFlexi');
/**
 * Get today's transactions list.
 */

// Parse command line arguments
$options = getopt('s::e::o:', ['scope::', 'env::', 'output:']);
$exitcode = 0;
// Get the path to the .env file
$envfile = $options['env'] ?? '../.env';

$destination = \array_key_exists('output', $options) ? $options['output'] : \Ease\Shared::cfg('RESULT_FILE', 'php://stdout');

\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'CERT_FILE', 'CERT_PASS', 'XIBMCLIENTID', 'ACCOUNT_NUMBER'], $envfile);
Transactor::checkCertificatePresence(\Ease\Shared::cfg('CERT_FILE'));
$engine = new Transactor(\Ease\Shared::cfg('ACCOUNT_NUMBER'));
$scope = $options['scope'] ?? \Ease\Shared::cfg('IMPORT_SCOPE', 'last_month');
$engine->setScope($scope);

if (\Ease\Shared::cfg('APP_DEBUG', false)) {
    $engine->logBanner(\Ease\Shared::cfg('ACCOUNT_NUMBER').' '.$engine->getCurrencyCode(), 'Scope: '.$scope);
}

$report = [
    'source' => \Ease\Shared::appName().' v'.\Ease\Shared::appVersion(),
    'account' => \Ease\Shared::cfg('ACCOUNT_NUMBER'),
    'scope' => $scope,
    'until' => $engine->getUntil()->format('Y-m-d'),
    'since' => $engine->getSince()->format('Y-m-d'),
    'banka' => $engine->import(),
];

$written = file_put_contents($destination, json_encode($report, \Ease\Shared::cfg('DEBUG') ? \JSON_PRETTY_PRINT : 0));
$engine->addStatusMessage(sprintf(_('Saving result to %s'), $destination), $written ? 'success' : 'error');

exit($exitcode);
