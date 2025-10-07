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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

#[CoversClass(\AbraFlexi\RaiffeisenBank\Transactor::class)]
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

        // Each transaction should be an instance of the expected class
        if (!empty($transactions)) {
            $this->assertInstanceOf(
                \VitexSoftware\Raiffeisenbank\Model\GetTransactionList200ResponseTransactionsInner::class,
                $transactions[0],
            );
        }
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
        // Create mock objects for the new API structure
        $amount = $this->createMock(\VitexSoftware\Raiffeisenbank\Model\GetTransactionList200ResponseTransactionsInnerAmount::class);
        $amount->method('getValue')->willReturn(6776);
        $amount->method('getCurrency')->willReturn('CZK');

        $counterParty = $this->createMock(\VitexSoftware\Raiffeisenbank\Model\GetTransactionList200ResponseTransactionsInnerEntryDetailsTransactionDetailsRelatedPartiesCounterParty::class);
        $counterParty->method('getName')->willReturn('Customer s.r.o.');

        $account = $this->createMock(\VitexSoftware\Raiffeisenbank\Model\GetTransactionList200ResponseTransactionsInnerEntryDetailsTransactionDetailsRelatedPartiesCounterPartyAccount::class);
        $account->method('getAccountNumber')->willReturn('6260979339');
        $counterParty->method('getAccount')->willReturn($account);

        $orgId = $this->createMock(\VitexSoftware\Raiffeisenbank\Model\GetTransactionList200ResponseTransactionsInnerEntryDetailsTransactionDetailsRelatedPartiesCounterPartyOrganisationIdentification::class);
        $orgId->method('getBankCode')->willReturn('0800');
        $counterParty->method('getOrganisationIdentification')->willReturn($orgId);

        $relatedParties = $this->createMock(\VitexSoftware\Raiffeisenbank\Model\GetTransactionList200ResponseTransactionsInnerEntryDetailsTransactionDetailsRelatedParties::class);
        $relatedParties->method('getCounterParty')->willReturn($counterParty);

        $creditorRef = $this->createMock(\VitexSoftware\Raiffeisenbank\Model\GetTransactionList200ResponseTransactionsInnerEntryDetailsTransactionDetailsRemittanceInformationCreditorReferenceInformation::class);
        $creditorRef->method('getVariable')->willReturn('197712024');
        $creditorRef->method('getConstant')->willReturn('0');

        $remittanceInfo = $this->createMock(\VitexSoftware\Raiffeisenbank\Model\GetTransactionList200ResponseTransactionsInnerEntryDetailsTransactionDetailsRemittanceInformation::class);
        $remittanceInfo->method('getOriginatorMessage')->willReturn('2024/09 IT');
        $remittanceInfo->method('getCreditorReferenceInformation')->willReturn($creditorRef);

        $transactionDetails = $this->createMock(\VitexSoftware\Raiffeisenbank\Model\GetTransactionList200ResponseTransactionsInnerEntryDetailsTransactionDetails::class);
        $transactionDetails->method('getRelatedParties')->willReturn($relatedParties);
        $transactionDetails->method('getRemittanceInformation')->willReturn($remittanceInfo);

        $entryDetails = $this->createMock(\VitexSoftware\Raiffeisenbank\Model\GetTransactionList200ResponseTransactionsInnerEntryDetails::class);
        $entryDetails->method('getTransactionDetails')->willReturn($transactionDetails);

        $transaction = $this->createMock(\VitexSoftware\Raiffeisenbank\Model\GetTransactionList200ResponseTransactionsInner::class);
        $transaction->method('getEntryReference')->willReturn('6828747987');
        $transaction->method('getAmount')->willReturn($amount);
        $transaction->method('getCreditDebitIndication')->willReturn('CRDT');
        $transaction->method('getBookingDate')->willReturn(new \DateTime('2024-10-10T14:05:48.000+02:00'));
        $transaction->method('getEntryDetails')->willReturn($entryDetails);

        $this->transactor->takeTransactionData($transaction);

        $this->assertIsArray($this->transactor->getData());
    }
}
