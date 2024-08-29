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

class BankClientTest extends TestCase
{
    public function testGetBank(): void
    {
        $bankAccount = '1234567890';
        $bankClient = new \AbraFlexi\RaiffeisenBank\BankClient($bankAccount);
        $bank = $bankClient->getBank($bankAccount);

        $this->assertInstanceOf(\AbraFlexi\RO::class, $bank);
    }

    public function testSetScope(): void
    {
        $bankAccount = '1234567890';
        $bankClient = new \AbraFlexi\RaiffeisenBank\BankClient($bankAccount);

        $bankClient->setScope('today');
        $this->assertInstanceOf(\DateTime::class, $bankClient->since);
        $this->assertInstanceOf(\DateTime::class, $bankClient->until);

        $bankClient->setScope('last_month');
        $this->assertInstanceOf(\DateTime::class, $bankClient->since);
        $this->assertInstanceOf(\DateTime::class, $bankClient->until);

        // Add more test cases for different scope values
    }

    public function testGetxRequestId(): void
    {
        $bankAccount = '1234567890';
        $bankClient = new \AbraFlexi\RaiffeisenBank\BankClient($bankAccount);

        $xRequestId = $bankClient->getxRequestId();

        $this->assertIsString($xRequestId);
    }

    // Add more test methods for other public methods in BankClient class
}
