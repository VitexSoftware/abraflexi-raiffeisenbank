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

use PHPUnit\Framework\TestCase;

class StatementorTest extends TestCase
{
    public function testGetStatements(): void
    {
        $bankAccount = '1234567890';
        $bankClient = new \AbraFlexi\RaiffeisenBank\Statementor($bankAccount);

        $statements = $bankClient->getStatements();

        $this->assertIsArray($statements);
    }

    public function testImport(): void
    {
        $bankAccount = '1234567890';
        $bankClient = new \AbraFlexi\RaiffeisenBank\Statementor($bankAccount);

        $bankClient->import();

        // Add assertions here
    }

    public function testNtryToAbraFlexi(): void
    {
        $bankAccount = '1234567890';
        $bankClient = new \AbraFlexi\RaiffeisenBank\Statementor($bankAccount);

        $ntry = new \SimpleXMLElement('<Ntry></Ntry>');
        // Set properties of $ntry

        $transactionData = $bankClient->ntryToAbraFlexi($ntry);

        $this->assertIsArray($transactionData);
        // Add more assertions here
    }

    public function testSetScope(): void
    {
        $bankAccount = '1234567890';
        $bankClient = new \AbraFlexi\RaiffeisenBank\Statementor($bankAccount);

        $bankClient->setScope('today');
        $this->assertInstanceOf(\DateTime::class, $bankClient->since);
        $this->assertInstanceOf(\DateTime::class, $bankClient->until);

        $bankClient->setScope('last_month');
        $this->assertInstanceOf(\DateTime::class, $bankClient->since);
        $this->assertInstanceOf(\DateTime::class, $bankClient->until);

        // Add more test cases for different scope values
    }

    public function testDownload(): void
    {
        $bankAccount = '1234567890';
        $bankClient = new \AbraFlexi\RaiffeisenBank\Statementor($bankAccount);

        $saveTo = '/path/to/save/directory';
        $bankClient->download($saveTo);

        // Add assertions here
    }
}
