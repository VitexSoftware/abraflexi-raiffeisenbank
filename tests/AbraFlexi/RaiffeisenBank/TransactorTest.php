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
    protected \AbraFlexi\RaiffeisenBank\Transactor $transactor;
    private string $bankAccount;

    protected function setUp(): void
    {
        $this->bankAccount = \Ease\Shared::cfg('ACCOUNT_NUMBER');
        $this->transactor = new \AbraFlexi\RaiffeisenBank\Transactor($this->bankAccount);
    }

    public function testSetScope(): void
    {
        $this->transactor->setScope('today');
        $this->assertInstanceOf(\DateTime::class, $this->transactor->getSince());
        $this->assertInstanceOf(\DateTime::class, $this->transactor->getUntil());

        $this->transactor->setScope('yesterday');
        $this->assertInstanceOf(\DateTime::class, $this->transactor->getSince());
        $this->assertInstanceOf(\DateTime::class, $this->transactor->getUntil());

        $this->transactor->setScope('auto');
        $this->assertInstanceOf(\DateTime::class, $this->transactor->getSince());
        $this->assertInstanceOf(\DateTime::class, $this->transactor->getUntil());

        // Add more test cases for different scope values
    }

    public function testGetBank(): void
    {
        $bank = $this->transactor->getBank($this->bankAccount);

        $this->assertInstanceOf(\AbraFlexi\BankovniUcet::class, $bank);
    }

    public function testGetxRequestId(): void
    {
        $xRequestId = $this->transactor->getxRequestId();

        $this->assertIsString($xRequestId);
    }

    /**
     * @depends testSetScope
     */
    #[Depends('testSetScope')]
    public function testGetTransactions(): void
    {
        $this->transactor->setScope('yesterday');
        $transactions = $this->transactor->getTransactions();

        $this->assertIsArray($transactions);
    }

    /**
     * @depends testSetScope
     */
    #[Depends('testSetScope')]
    public function testImport(): void
    {
        $this->transactor->setScope('yesterday');
        $imported = $this->transactor->import();

        $this->assertIsArray($imported);
    }

    public function testTakeTransactionData(): void
    {
        $transactionData = json_decode(<<<'EOD'
        {
            "entryReference": "6828747987",
            "amount": {
                "value": 6776,
                "currency": "CZK"
            },
            "creditDebitIndication": "CRDT",
            "bookingDate": "2024-10-10T14:05:48.000+02:00",
            "valueDate": "2024-10-10T14:05:47.000+02:00",
            "bankTransactionCode": {
                "code": "10000107000"
            },
            "entryDetails": {
                "transactionDetails": {
                    "references": {},
                    "relatedParties": {
                        "counterParty": {
                            "name": "Customer s.r.o.",
                            "organisationIdentification": {
                                "bankCode": "0800"
                            },
                            "account": {
                                "accountNumber": "6260979339"
                            }
                        }
                    },
                    "remittanceInformation": {
                        "creditorReferenceInformation": {
                            "variable": "197712024",
                            "constant": "0"
                        },
                        "originatorMessage": "2024/09  IT"
                    }
                }
            }
        }
EOD);

        $this->transactor->takeTransactionData($transactionData);

        $this->assertIsArray($this->transactor->getData());
    }
}
