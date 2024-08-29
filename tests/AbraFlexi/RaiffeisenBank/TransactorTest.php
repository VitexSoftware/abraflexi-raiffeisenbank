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

class TransactorTest extends TestCase
{
    public function testGetTransactions(): void
    {
        $bankAccount = '1234567890';
        $transactor = new \AbraFlexi\RaiffeisenBank\Transactor($bankAccount);

        $transactions = $transactor->getTransactions();

        $this->assertIsArray($transactions);
    }

    public function testImport(): void
    {
        $bankAccount = '1234567890';
        $transactor = new \AbraFlexi\RaiffeisenBank\Transactor($bankAccount);

        $transactor->import();

        // Add assertions to check the import process
    }

    public function testTakeTransactionData(): void
    {
        $bankAccount = '1234567890';
        $transactor = new \AbraFlexi\RaiffeisenBank\Transactor($bankAccount);
        $transactionData = [
            // Provide sample transaction data here
        ];

        $transactor->takeTransactionData($transactionData);

        // Add assertions to check the transaction data
    }

    public function testSetScope(): void
    {
        $bankAccount = '1234567890';
        $transactor = new \AbraFlexi\RaiffeisenBank\Transactor($bankAccount);

        $transactor->setScope('today');
        $this->assertInstanceOf(\DateTime::class, $transactor->since);
        $this->assertInstanceOf(\DateTime::class, $transactor->until);

        $transactor->setScope('yesterday');
        $this->assertInstanceOf(\DateTime::class, $transactor->since);
        $this->assertInstanceOf(\DateTime::class, $transactor->until);

        $transactor->setScope('auto');
        $this->assertInstanceOf(\DateTime::class, $transactor->since);
        $this->assertInstanceOf(\DateTime::class, $transactor->until);

        // Add more test cases for different scope values
    }
}
