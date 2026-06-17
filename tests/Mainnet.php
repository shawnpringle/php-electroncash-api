<?php
require_once "../ec.php";
use PHPUnit\Framework\TestCase;

class Mainnet extends TestCase
{
    // Shared, single initialization for all TestCase instances
    protected static $ec;
   
    public static function setUpBeforeClass(): void
    {
        $home = getenv('HOME') ?: (isset($_SERVER['HOME']) ? $_SERVER['HOME'] : null);
        self::$ec = new ElectronCashRPC();
        self::assertNotNull(self::$ec, "Failed to create ElectronCashRPC instance");
    }

    


    public static function tearDownAfterClass(): void
    {
    }
}
