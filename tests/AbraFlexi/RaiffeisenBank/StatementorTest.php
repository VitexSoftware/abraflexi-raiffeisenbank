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
use PHPUnit\Framework\TestCase;

#[CoversClass(\AbraFlexi\RaiffeisenBank\Statementor::class)]
class StatementorTest extends TestCase
{
    protected \AbraFlexi\RaiffeisenBank\Statementor $statementor;
    private string $bankAccount;

    protected function setUp(): void
    {
        $this->bankAccount = \Ease\Functions::cfg('ACCOUNT_NUMBER');
        $this->statementor = new \AbraFlexi\RaiffeisenBank\Statementor($this->bankAccount);
    }

    public function testSetScope(): void
    {
        $this->statementor->setScope('today');
        $this->assertInstanceOf(\DateTime::class, $this->statementor->getSince());
        $this->assertInstanceOf(\DateTime::class, $this->statementor->getUntil());

        $this->statementor->setScope('last_month');
        $this->assertInstanceOf(\DateTime::class, $this->statementor->getSince());
        $this->assertInstanceOf(\DateTime::class, $this->statementor->getUntil());

        // Add more test cases for different scope values
    }

    /**
     * @depends testSetScope
     */
    #[Depends('testSetScope')]
    public function testGetBank(): void
    {
        $bank = $this->statementor->getBank($this->bankAccount);

        $this->assertInstanceOf(\AbraFlexi\BankovniUcet::class, $bank);
    }

    public function testGetxRequestId(): void
    {
        $xRequestId = $this->statementor->getxRequestId();

        $this->assertIsString($xRequestId);
    }

    /**
     * @depends testSetScope
     */
    #[Depends('testSetScope')]
    public function testGetStatements(): void
    {
        $this->statementor->setScope('auto');
        $statements = $this->statementor->getStatements();

        $this->assertIsArray($statements);
    }

    /**
     * @depends testSetScope
     */
    #[Depends('testSetScope')]
    public function testImport(): void
    {
        $this->statementor->setScope('last_month');
        $imported = $this->statementor->import();

        $this->assertIsArray($imported);
    }

    public function testNtryToAbraFlexi(): void
    {
        $ntry = new \SimpleXMLElement(<<<'EOD'
<Ntry>
                <NtryRef>6727959302</NtryRef>
                <Amt Ccy="CZK">41805.55</Amt>
                <CdtDbtInd>DBIT</CdtDbtInd>
                <Sts>BOOK</Sts>
                <BookgDt>
                    <DtTm>2024-09-10T00:56:17</DtTm>
                </BookgDt>
                <ValDt>
                    <DtTm>2024-09-10T00:00:00</DtTm>
                </ValDt>
                <BkTxCd>
                    <Prtry>
                        <Cd>10000104000</Cd>
                    </Prtry>
                </BkTxCd>
                <NtryDtls>
                    <TxDtls>
                        <Refs>
                            <MsgId>1</MsgId>
                            <AcctSvcrRef>6727959302</AcctSvcrRef>
                        </Refs>
                        <RltdPties>
                            <CdtrAcct>
                                <Id>
                                    <Othr>
                                        <Id>6430089575</Id>
                                    </Othr>
                                </Id>
                                <Nm>Customer</Nm>
                            </CdtrAcct>
                        </RltdPties>
                        <RltdAgts>
                            <CdtrAgt>
                                <FinInstnId>
                                    <Othr>
                                        <Id>5500</Id>
                                    </Othr>
                                </FinInstnId>
                            </CdtrAgt>
                        </RltdAgts>
                        <AddtlTxInf>770871 INKASO 4861/23/021/SME/PRP</AddtlTxInf>
                    </TxDtls>
                </NtryDtls>
            </Ntry>

EOD);

        $transactionData = $this->statementor->ntryToAbraFlexi($ntry);

        $this->assertIsArray($transactionData);
        // Add more assertions here
    }

    /**
     * @depends testSetScope
     */
    #[Depends('testSetScope')]
    public function testDownload(): void
    {
        $this->statementor->setScope('auto');
        $saveTo = sys_get_temp_dir();
        $downloaded = $this->statementor->download($saveTo);

        $this->assertIsArray($downloaded);
    }
}
