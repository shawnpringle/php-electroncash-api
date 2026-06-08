<?php
require_once "../ec.php";
use PHPUnit\Framework\TestCase;

class ECTestCase extends TestCase
{
    // Shared, single initialization for all TestCase instances
    protected static $ec;
    protected static $ch;
    private static $ownAddress;
    private static $instanceCount = 0;
    private static $electronCashDaemonProcess;
    private static $bitcoinDaemonProcess;
    public static function setUpBeforeClass(): void
    {
        $home = getenv('HOME') ?: (isset($_SERVER['HOME']) ? $_SERVER['HOME'] : null);
        // start electron-cash daemon in regtest mode for tests
        self::$electronCashDaemonProcess = exec('electron-cash --regtest daemon start > /dev/null 2>&1');
        self::$bitcoinDaemonProcess = exec('bitcoind -conf=bitcoin.conf -daemon > /dev/null 2>&1');
        exec('electron-cash --regtest daemon load_wallet > /dev/null 2>&1');
        // give the daemon a moment to start
        sleep(5);

        self::$ec = new ElectronCashRPC(null, "$home/.electron-cash/regtest/config");
        echo "ECTestCase: setUpBeforeClass: Started Electron Cash daemon and Bitcoin daemon\n";
        self::assertNotNull(self::$ec, "Failed to create ElectronCashRPC instance");
        $response = self::$ec->createnewaddress();
        $decoded = json_decode($response, true);
        self::assertNotNull($decoded, "Failed to decode JSON response from createnew"); 
        self::$ownAddress = $decoded['result'];

    }

    function __constructor() {
        self::$instanceCount++;
    }

    function __destruct() {
        self::$instanceCount--;
        if (self::$instanceCount < 0) {
            //echo "ECTestCase: Warning: instance count is negative: " . self::$instanceCount . "\n";
        } else if (self::$instanceCount == 0) {
            echo "ECTestCase: All test instances destroyed, instance count is zero\n";
        } else {
            echo "ECTestCase: Instance destroyed, remaining count: " . self::$instanceCount . "\n";
        }
    }

    public function testGetBalance()
    {
        
        $this->assertNotNull(self::$ec, "ElectronCashRPC instance is not initialized");
        $response = self::$ec->getbalance();
        //echo $response;
        $decoded = json_decode($response, true);
        $this->assertNotNull($decoded);
        $this->assertArrayHasKey('result', $decoded);
        $this->assertArrayHasKey('confirmed', $decoded['result'], "Result has no confirmed balance");
        $this->assertGreaterThanOrEqual(0, $decoded['result']['confirmed']);    
    }

    public function testNotifyBadAddress() {
        $response = self::$ec->notify('notanaddress', 'http://localhost');
        $decoded = json_decode($response, true);
        $this->assertNotEquals(null, $decoded, "notify: Did not return a JSON object: $response");
        $this->assertNotNull($decoded['error'], "notify: No Error returned from daemon: " . $response);
        $code = $decoded['error']['code'];
        $message = $decoded['error']['message'];
        $this->assertEquals(-32603, $code, "notify: Expected error code -5 for invalid address, got $code");
        $this->assertEquals($message, "Server error: File \"/opt/electron_cash-4.4.5/electroncash/address.py\", line 445, in from_string | electroncash.address.AddressError: invalid address: notanaddress (invalid characters in address: notanaddress)\n", "notify: Expected error message");
    }

    public function testNotifySetup() {

        $response = self::$ec->notify(self::$ownAddress, 'http://127.0.0.1:7778');
$decoded = json_decode($response, true);
        $this->assertNotEquals(null, $decoded, "notify: Did not return a JSON object: $response");
        $decoded = json_decode($response, true);
        $this->assertNotNull($decoded, "notify: Did not return a JSON object");
        $this->assertArrayHasKey('result', $decoded, "notify: No result key in response");
        $this->assertEquals(true, $decoded['result'], "notify: Expected result true for valid address");        
    }

    public function testAddRequest() {
        $response = self::$ec->addrequest(0.001, "Test Request", 500);
        $decoded = json_decode($response, true);
        //echo "$response\n";
        $this->assertNotEquals(null, $decoded, "addrequest: Did not return a JSON object: $response");
        $this->assertNotNull($decoded['result'], "addrequest: Error value returned for addrequest: " . $response);
        $result = $decoded['result'];
        $amount = $result['amount'];
        $this->assertEquals(100000, $amount);
        $this->assertEquals("Test Request", $result['memo']);
    }

    public static function tearDownAfterClass(): void
    {
            echo "ECTestCase: Stopping Electron Cash daemon and Bitcoin daemon\n";
            // stop the daemon processes
            exec('electron-cash --regtest daemon stop > /dev/null 2>&1');
            exec('bitcoin-cli -conf=bitcoin.conf stop > /dev/null 2>&1');
    }
}
