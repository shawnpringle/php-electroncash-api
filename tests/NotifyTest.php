<?php
require_once "../ec.php";
use PHPUnit\Framework\TestCase;

final class NotifyTest extends TestCase
{
    public $ec;
    public function setUp(): void
    {
        $this->ec = new ElectronCashRPC();
    }

    public function testNotifyBadAddress() {
        $response = $this->ec->notify('notanaddress', 'http://localhost');
        $decoded = json_decode($response, true);
        $this->assertNotEquals(null, $decoded, "notify: Did not return a JSON object: $response");
        $this->assertNotNull($decoded['error'], "notify: No Error returned from daemon: " . $response);
        $code = $decoded['error']['code'];
        $message = $decoded['error']['message'];
        $this->assertEquals(-32603, $code, "notify: Expected error code -5 for invalid address, got $code");
        $this->assertEquals($message, "Server error: File \"/opt/electron_cash-4.4.5/electroncash/address.py\", line 445, in from_string | electroncash.address.AddressError: invalid address: notanaddress (invalid characters in address: notanaddress)\n", "notify: Expected error message");
    }
}

