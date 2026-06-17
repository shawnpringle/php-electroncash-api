<?php
require_once "../ec.php";
use PHPUnit\Framework\TestCase;

class Regtest extends TestCase
{
    // Shared, single initialization for all TestCase instances
    protected static $ec;
    protected static $ch;
    private static $ownAddress;
    private static $instanceCount = 0;
    private static $electronCashDaemonProcess;
    private static $fulcrumDaemonProcess;
    private static $bitcoinDaemonProcess;
    private static $bitcoinRPCPassword;
    private static $bitcoinRPCPort;
    private static $bitcoinRPCUser;
    public static function setUpBeforeClass(): void
    {
        /** The regtest network contains of course only one bitcoin node, yet it is
         * surprisingly complicated.  Starting from the bitcoin nodes, there is a port
         * for connecting between various bitcoind instances across the internet.  As this
         * is regtest this is not applicable.  There is a port number for connecting
         * Fulcrum to it.  This port is 18444.  The -conf argument is ignored.  Do not use it.
         * 
         * Now, Fulcrum needs to have the port number to connect to bitcoind specified at the
         * command line with "-b 127.0.0.1:18444".  Fulcrum is not useful unless the 
         * electron-cash daemon connects to it.  When running in regtest mode, electron-cash
         * daemon tries to connect to its servers via port 51001.  This is specified for Fulcrum 
         * like this: "-t 127.0.0.1:51001".  
         * 
         * Electron-cash daemon will repsect its conf file found in "~/.electron-cash/regtest/config".
         * It will only sometimes try to connect to Fulcrum on 51001.  When asked about the balance
         * of a specific address it will do this.  I set things up so it listens on 7777.
         * 
         * Now the ElectronCashRPC object instance needs to connect to its daemon via the information
         * found in this file which is generally set to 7777 and for this test it is what I use.
         * 
         * For all of the interactions there are rpcuser and rpcpassword.  Fulcrum needs to specifiy 
         * the username and password according to the needs of BitcoinD.  Electron-Cash daemon needs to
         * do the same but for Fulcrum.  An electronCashRPC object has to the same for Electron-Cash daemon.
         * 
         * # Commands
         * 
         * bitcoind  --rpcallowip=::1 --rpcallowip=127.0.0.1  -rpcbind=127.0.0.1:18444 -regtest -txindex
         * Fulcrum -D ./fulcrum  --rpcuser leprechaun --rpcpassword password -b 127.0.0.1:18444 -d -t 127.0.0.1:51001
         * electron-cash --regtest daemon start
         * curl --data-binary '{"id":"curltext","method":"getaddressbalance","params":["qrxkqa9wkyc6zfvertvepwu5aj2ekxmha5krkxmy5j"]}' http://user:oBOXFDbWQ0cpV5oct4RHww==@127.0.0.1:7777
         * 
         */
        $home = getenv('HOME');
        $newLine = "\n";

        $bitcoinDConf = explode($newLine, file_get_contents($home.'/.bitcoin/bitcoin.conf'));
        foreach ($bitcoinDConf as $line)
        {
            $a = explode("=", $line);
            switch ($a[0]) {
                case "rpcpassword":
                    self::$bitcoinRPCPassword = $a[1];
                    break;
                case "rpcuser":
                    self::$bitcoinRPCUser = $a[1];
            }
        }
        self::$bitcoinRPCPort = 18444;
        
        $output = [];
        $return = 1;
        exec('bitcoin-cli -regtest -rpcuser=' . escapeshellarg(self::$bitcoinRPCUser)
            . ' -rpcpassword=' . escapeshellarg(self::$bitcoinRPCPassword)
            . ' -rpcport=' . escapeshellarg(self::$bitcoinRPCPort)
            . ' getblockcount 2>/dev/null', $output, $return);
        if ($return !== 0 || !isset($output[0]) || !is_numeric($output[0])) {
            exit("Please run:\nbitcoind  --rpcallowip=::1 --rpcallowip=127.0.0.1  -rpcbind=127.0.0.1:18444 -regtest -txindex > /dev/null 2>&1 &\n");
        }

        $fulcrumPIdFile = "fulcrum.pid";
        $start = time();
        if (!file_exists($fulcrumPIdFile)) {
            echo 'Please run:'."\n";
            exit("Fulcrum -D ./fulcrum  --rpcuser " . self::$bitcoinRPCUser . " --rpcpassword " . self::$bitcoinRPCPassword . " -b 127.0.0.1:" . self::$bitcoinRPCPort . "  -t 127.0.0.1:51001 --pidfile fulcrum.pid > /dev/null 2>&1 &\n");
        }

        // start electron-cash daemon in regtest mode for tests
        $output = [];
        exec('electron-cash --regtest daemon status 2> /dev/null', $output, $return);
        
        if ($output[0] == 'Daemon not running') {
            echo "Please run:\n";
            exit("electron-cash --regtest daemon start\n");
        }
        exec('electron-cash --regtest daemon load_wallet >/dev/null 2>&1');
        //echo ('*******************'."\n".$output[0]."\n");
        //self::$bitcoinDaemonProcess = exec('bitcoind --rpcallowip=::1 --rpcallowip=127.0.0.1 -rpcbind=127.0.0.1:18444 -regtest -txindex > /dev/null 2>&1 &');
        //self::$fulcrumDaemonProcess = exec('Fulcrum -D ./fulcrum --rpcuser leprechaun --rpcpassword password -b 127.0.0.1:18444 --pidfile fulcrum.pid > /dev/null 2>&1 &');
  

        self::$ec = new ElectronCashRPC(null, "$home/.electron-cash/regtest/config");
        //echo "Regtest: setUpBeforeClass: Started Electron Cash daemon and Bitcoin daemon\n";
        self::assertNotNull(self::$ec, "Failed to create ElectronCashRPC instance");
        $response = self::$ec->createnewaddress();
        $decoded = json_decode($response, true);
        self::assertNotNull($decoded, "Failed to decode JSON response from createnew"); 
        self::$ownAddress = $decoded['result'];


    }

    static function electronCashCLI($message, $output = null, $return = null ) {
        exec('electron-cash --regtest ' . $message, $output, $return);
    }

    static function bitcoinCLI($message, $output = null, $return = null) {
        exec('bitcoin-cli  -regtest -rpcuser=' . escapeshellarg(self::$bitcoinRPCUser)
                . ' -rpcpassword=' . escapeshellarg(self::$bitcoinRPCPassword)
                . ' -rpcport=' . escapeshellarg(self::$bitcoinRPCPort)." ". $message, $output, $return);
    }

    // Sleeps for a long enough timme for the recent action of what happened in bitcoind
    // to effect the state of the electroncash daemon.
    static function sleep() {
        // Even 1 second is too short
        // Three works.
        usleep(1900000);
    }



    function __constructor() {
        self::$instanceCount++;
    }

    function __destruct() {
        self::$instanceCount--;
        if (self::$instanceCount < 0) {
            //echo "Regtest: Warning: instance count is negative: " . self::$instanceCount . "\n";
        } else if (self::$instanceCount == 0) {
            //echo "Regtest: All test instances destroyed, instance count is zero\n";
        } else {
            //echo "Regtest: Instance destroyed, remaining count: " . self::$instanceCount . "\n";
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
        //$this->assertArrayHasKey('unconfirmed', $decoded['result'], "Result has no unconfirmed balance");
    }

    public function testNotifyBadAddress() {
        //$stdnull = fopen("/dev/null", "w");
        //curl_setopt(self::$ec->ch, CURLOPT_STDERR, $stdnull);
        $response = self::$ec->notify('notanaddress', 'http://localhost');
        $decoded = json_decode($response, true);
        //curl_setopt(self::$ec->ch, CURLOPT_STDERR, fopen("php://stderr", "w"));
        //fclose($stdnull);
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

    public function testAddRequest3() {
        $response = self::$ec->addrequest(0.001, "Test Request", 500);
        $decoded = json_decode($response, true);
        //echo "$response\n";
        $this->assertNotEquals(null, $decoded, "addrequest: Did not return a JSON object: $response");
        $this->assertNotNull($decoded['result'], "addrequest: Error value returned for addrequest: " . $response);
        $result = $decoded['result'];
        $amount = $result['amount'];
        $this->assertEquals(100000, $amount);
        $this->assertEquals("Test Request", $result['memo']);
        $this->assertEquals(500, $result['exp']);

        $address = $result['address'];
        
        $response = self::$ec->getaddressbalance($address);
        $decoded = json_decode($response, true);
        $this->assertNotEquals(null, $decoded, "getaddressbalance: Did not return a JSON object: $response");
        // Fix me.  Regtest network is complicated with three servers that I don't know how to configure.  
        // Infrequent mainnet tests are how testing is done unfortunately.
        $this->assertNotNull($decoded['result'], "getaddressbalance: Error value returned for getaddressbalance: " . $response);
        $balance = $decoded['result'];
        $this->assertEquals(0, $balance['confirmed'], "Balance should be zero for new request");
        
        $this->assertEquals(array_key_exists('unconfirmed', $balance) && $balance['unconfirmed']!=0, false, "Balance should be null for new request");

        self::bitcoinCLI('generate 1');
        self::sleep();
        // send coin
        self::bitcoinCLI('sendtoaddress ' . $address . ' 0.001');
        self::sleep();

        $response = self::$ec->getaddressbalance($address);
        $decoded = json_decode($response, true);
        if ($decoded['error']) {
            sleep(1);
            $response = self::$ec->gethostbyaddressbalance($address);
            $decoded = json_decode($response, true);
        }
        $this->assertNotEquals(null, $decoded, "getaddressbalance: Did not return a JSON object: $response");
        $this->assertNotNull($decoded['result'], "getaddressbalance: Error value returned for getaddressbalance: " . $response);
        $balance = $decoded['result'];
        $this->assertEquals(0, $balance['confirmed'], "Confirmed balance should be 0 even after payment.  Instead balance is u:".$balance['unconfirmed']." and  c:".$balance['confirmed']);
        $this->assertEquals(0.001, $balance['unconfirmed'], "Unconfirmed Balance should be 0.001 after payment is sent.  It is ". $balance['unconfirmed']);

        self::bitcoinCLI('generate 1');
        self::sleep();

        $response = self::$ec->getaddressbalance($address);
        $decoded = json_decode($response, true);
        $this->assertNotEquals(null, $decoded, "getaddressbalance: Did not return a JSON object: $response");
        $this->assertNotNull($decoded['result'], "getaddressbalance: Error value returned for getaddressbalance: " . $response);
        $balance = $decoded['result'];
        $this->assertEquals(0.001, $balance['confirmed'], "Confirmed balance should be 0.001 after payment");
        $this->assertEquals(false, array_key_exists('unconfirmed', $balance) and $balance['unconfirmed'] > 0, "There should be no unconfirmed balance after mining.");
    }



    public function testAddRequestAll() {
        $response = self::$ec->addrequest(0.001, "Test Request", 500, 1, 'https://example.com/payment', 'https://example.com/index', true, 'e38d7f8e85da78943b3d7766e94c5560522ad67758402ae8f31765412b746292');
        $decoded = json_decode($response, true);
        //echo "$response\n";
        $this->assertNotEquals(null, $decoded, "addrequest: Did not return a JSON object: $response");
        $this->assertNotNull($decoded['result'], "addrequest: Error value returned for addrequest: " . $response);
        $result = $decoded['result'];
        //echo json_encode($result) . "\n";
        $address = $result['address'];
        $this->assertEquals("z", substr($address,0,1), "token address");
        // NB: URI starts with bchreg:q rather than 'bchreg:z'. Whether that is wrong or not is out of 
        // the scope of a wrapper.  We could decode the addresses and make sure they are for the same key.        
        $this->assertEquals("bchreg:", substr($result['URI'],0,7), "URI prefix");
        $amount = $result['amount'];
        $id = $result['id'];
        $this->assertNotNull($id);
        $this->assertEquals(100000, $amount);
        $this->assertEquals("Test Request", $result['memo'], "memo set");
        $this->assertEquals("https://example.com/payment/" . $id, $result['payment_url'], "Payment URL Test");
        $this->assertEquals("https://example.com/index/" . $id, $result['index_url'], "Index URL Test");        
        $this->assertEquals('e38d7f8e85da78943b3d7766e94c5560522ad67758402ae8f31765412b746292', $result['category_id'], 'category id');
        $this->assertEquals('Pending', $result['status'], 'Status');
        $this->assertEquals(500, $result['exp'], 'Expiration delay');
        $this->assertEquals(1, $result['tokenreq'], 'Token required');
        $this->assertEquals($result['tx_hashes'], [], 'Tx hashes should be an empty hash');
        $this->assertEquals('0.001', $result['amount (BCH)'], 'Amount in BCH should be 0.001');

        $response = self::$ec->getaddressbalance($address);
        $decoded = json_decode($response, true);
        $this->assertNotEquals(null, $decoded, "getaddressbalance: Did not return a JSON object: $response");
        $this->assertNotNull($decoded['result'], "getaddressbalance: Error value returned for getaddressbalance: " . $response);
        $balance = $decoded['result'];
        $this->assertEquals(0, $balance['confirmed'], "Balance should be zero for new request");
        $this->assertEquals(false, array_key_exists('unconfirmed', $balance) && $balance['unconfirmed']>0, "Balance should be null for new request");

        self::bitcoinCLI('generate 1');
        self::sleep();
        self::bitcoinCLI('sendtoaddress ' . $address . ' 0.001');
        // this takes longer than other calls...
        sleep(5);

        $response = self::$ec->getaddressbalance($address);
        $decoded = json_decode($response, true);
        $this->assertNotEquals(null, $decoded, "getaddressbalance: Did not return a JSON object: $response");
        $this->assertNotNull($decoded['result'], "getaddressbalance: Error value returned for getaddressbalance: " . $response);
        $balance = $decoded['result'];
        $this->assertEquals(0, $balance['confirmed'], "Confirmed balance should be 0 before confirmation payment");
        $this->assertEquals(0.001, $balance['unconfirmed'], "Unconfirmed balance should be 0.001 after payment is sent.  It is ". $balance['unconfirmed']);

        self::bitcoinCLI('generate 1');
        self::sleep();

        $response = self::$ec->getaddressbalance($address);
        $decoded = json_decode($response, true);
        $this->assertNotEquals(null, $decoded, "getaddressbalance: Did not return a JSON object: $response");
        $this->assertNotNull($decoded['result'], "getaddressbalance: Error value returned for getaddressbalance: " . $response);
        $balance = $decoded['result'];
        $this->assertEquals(0.001, $balance['confirmed'], "Balance should be 100000 after payment");
        $this->assertEquals(0, $balance['unconfirmed'], "Balance should be zero unconfirmed after payment is confirmed");

    }



    public static function tearDownAfterClass(): void
    {
        //echo "Leaving the Daemons up\n";
    }
}
