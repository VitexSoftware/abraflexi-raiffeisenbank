<?php

use PHPUnit\Framework\TestCase;

class StatementorTest extends TestCase {

    public function testGetStatements() {
        $bankAccount = '1234567890';
        $bankClient = new \AbraFlexi\RaiffeisenBank\Statementor($bankAccount);

        $statements = $bankClient->getStatements();

        $this->assertIsArray($statements);
    }

    public function testImport() {
        $bankAccount = '1234567890';
        $bankClient = new \AbraFlexi\RaiffeisenBank\Statementor($bankAccount);

        $bankClient->import();

        // Add assertions here
    }

    public function testNtryToAbraFlexi() {
        $bankAccount = '1234567890';
        $bankClient = new \AbraFlexi\RaiffeisenBank\Statementor($bankAccount);

        $ntry = new \SimpleXMLElement('<Ntry></Ntry>');
        // Set properties of $ntry

        $transactionData = $bankClient->ntryToAbraFlexi($ntry);

        $this->assertIsArray($transactionData);
        // Add more assertions here
    }

    public function testSetScope() {
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

    public function testDownload() {
        $bankAccount = '1234567890';
        $bankClient = new \AbraFlexi\RaiffeisenBank\Statementor($bankAccount);

        $saveTo = '/path/to/save/directory';
        $bankClient->download($saveTo);

        // Add assertions here
    }
}
