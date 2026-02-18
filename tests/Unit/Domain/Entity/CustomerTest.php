<?php
declare(strict_types=1);
namespace App\Tests\Unit\Domain\Entity;
use App\Domain\Entity\Customer;
use PHPUnit\Framework\TestCase;
class CustomerTest extends TestCase {
    public function testCustomerCreation(): void {
        \ = new Customer();
        \->setSapCustomerId('TEST');
        \->setSalesOrg('101');
        \->assertEquals('TEST', \->getSapCustomerId());
    }
}
