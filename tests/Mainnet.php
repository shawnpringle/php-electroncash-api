<?php
require_once "../ec.php";
use PHPUnit\Framework\TestCase;

class Mainnet extends TestCase
{
    // Shared, single initialization for all TestCase instances
    protected static $ec;
    protected static $ch;
    
    private static $instanceCount = 0;
    private static $electronCashDaemonProcess;
    private static $bitcoinDaemonProcess;
    public static function setUpBeforeClass(): void
    {
        $home = getenv('HOME') ?: (isset($_SERVER['HOME']) ? $_SERVER['HOME'] : null);
        // start electron-cash daemon in regtest mode for tests
        self::$electronCashDaemonProcess = exec('electron-cash daemon start > /dev/null 2>&1');
        //self::$bitcoinDaemonProcess = exec('bitcoind -conf=bitcoin.conf -daemon > /dev/null 2>&1');
        // give the daemon a moment to start

        self::$ec = new ElectronCashRPC(null, "$home/.electron-cash/config");
        echo "Mainnet: setUpBeforeClass: Started Electron Cash daemon and Bitcoin daemon\n";
        self::assertNotNull(self::$ec, "Failed to create ElectronCashRPC instance");
    }

    


    public static function tearDownAfterClass(): void
    {
            echo "Mainnet: Stopping Electron Cash daemon and Bitcoin daemon\n";
            // stop the daemon processes
            exec('electron-cash --regtest daemon stop > /dev/null 2>&1');
            //exec('bitcoin-cli -conf=bitcoin.conf stop > /dev/null 2>&1');
    }
}
