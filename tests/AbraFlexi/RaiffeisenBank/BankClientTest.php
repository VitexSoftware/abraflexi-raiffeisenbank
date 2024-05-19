<?php
use PHPUnit\Framework\TestCase;

class BankClientTest extends TestCase
{
    public function testGetBank()
    {
        $bankAccount = '1234567890';
        $bankClient = new \AbraFlexi\RaiffeisenBank\BankClient($bankAccount);
        $bank = $bankClient->getBank($bankAccount);
        
        $this->assertInstanceOf(\AbraFlexi\RO::class, $bank);
    }
    
    public function testSetScope()
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
    
    public function testGetxRequestId()
    {
        $bankAccount = '1234567890';
        $bankClient = new \AbraFlexi\RaiffeisenBank\BankClient($bankAccount);
        
        $xRequestId = $bankClient->getxRequestId();
        
        $this->assertIsString($xRequestId);
    }
    
    // Add more test methods for other public methods in BankClient class
}