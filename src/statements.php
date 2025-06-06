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

\define('APP_NAME', 'RBStatements2AbraFlexi');

require_once '../vendor/autoload.php';

/**
 * Get today's statements list.
 */
// Parse command line arguments

$options = getopt('o::e::d::s::', ['output::environment::destination::scope::']);
\Ease\Shared::init(
    ['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'CERT_FILE', 'CERT_PASS', 'XIBMCLIENTID', 'ACCOUNT_NUMBER'],
    \array_key_exists('environment', $options) ? $options['environment'] : (\array_key_exists('e', $options) ? $options['e'] : '../.env'),
);
$destination = \array_key_exists('output', $options) ? $options['output'] : (\array_key_exists('o', $options) ? $options['o'] : \Ease\Shared::cfg('RESULT_FILE', 'php://stdout'));

BankClient::checkCertificatePresence(\Ease\Shared::cfg('CERT_FILE'));

$engine = new Statementor(\Ease\Shared::cfg('ACCOUNT_NUMBER'));
$scope = $options['scope'] ?? \Ease\Shared::cfg('IMPORT_SCOPE', 'last_month');
$engine->setScope($scope);

if (\Ease\Shared::cfg('APP_DEBUG', false)) {
    $engine->logBanner(\Ease\Shared::cfg('ACCOUNT_NUMBER').' '.$engine->getCurrencyCode(), 'Scope: '.$scope);
}

$report = [
    'source' => \Ease\Shared::appName().' v'.\Ease\Shared::appVersion(),
    'account' => \Ease\Shared::cfg('ACCOUNT_NUMBER'),
    'line' => $engine->getStatementLine(),
    'scope' => $scope,
    'until' => $engine->getUntil()->format('Y-m-d'),
    'since' => $engine->getSince()->format('Y-m-d'),
    'banka' => $engine->import(),
    'exitcode' => $engine->getExitCode(),
];
$exitcode = $engine->getExitCode();

$written = file_put_contents($destination, json_encode($report, \Ease\Shared::cfg('DEBUG') ? \JSON_PRETTY_PRINT : 0));
$engine->addStatusMessage(sprintf(_('Saving result to %s'), $destination), $written ? 'success' : 'error');

exit($exitcode);
