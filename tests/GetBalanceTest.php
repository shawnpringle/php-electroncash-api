<?php
require_once "../ec.php";
use PHPUnit\Framework\TestCase;

final class GetBalanceTest extends TestCase
{
    public $ec;
    public function setUp(): void
    {
        $this->ec = new ElectronCashRPC();
    }

    public function testGetBalance()
    {
        $response = $this->ec->getbalance();
        $decoded = json_decode($response, true);
        $this->assertNotNull($decoded);
        $this->assertArrayHasKey('result', $decoded);
        $this->assertArrayHasKey('confirmed', $decoded['result']);
        $this->assertGreaterThanOrEqual(0, $decoded['result']['confirmed']);    
    }
}