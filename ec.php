<?php
/*** 
 * Electron Cash RPC client for PHP.
 * 
 * This class provides methods to interact with the Electron Cash wallet via its JSON-RPC interface.
 * It reads configuration from a specified file and allows you to perform actions such as creating new addresses,
 * adding payment requests, and checking balances.
 * 
 * Keep a copy of the Electron Cash config file at /var/www/etc/electron-cash/config.  Credentials
 * will be read from there.:
 * 
 * Usage:
 *   $ec = new ElectronCashRPC();
 *   $balance = $ec->getbalance();
 *   echo "Balance: " . $balance;
 * 
 ***/

final class ElectronCashRPC
{
    /** The cURL handle for the RPC connection. */
    public $ch;   
    private $message_id;
    private $chowned;

    public function __construct($ch = null, $configFile = '/var/www/etc/electron-cash/config') {
        $home = getenv('HOME') ?: (isset($_SERVER['HOME']) ? $_SERVER['HOME'] : null);

        if (!is_readable($configFile)) {
            throw new Exception("Cannot read Electron Cash config: $configFile (home is $home)");
        }    
        $configJson = file_get_contents($configFile);
        $config = json_decode($configJson, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($config)) {
            throw new Exception ("Invalid JSON in Electron Cash config");
        }

        $rpc_port = isset($config['rpcport']) ? (int)$config['rpcport'] : null;
        $rpc_user = isset($config['rpcuser']) ? $config['rpcuser'] : null;
        $rpc_password = isset($config['rpcpassword']) ? $config['rpcpassword'] : null;

        $config['rpcport'] = $rpc_port;
        $config['rpcuser'] = $rpc_user;
        $config['rpcpassword'] = $rpc_password;

        $headers = [
            'Content-Type: application/json'
        ];

        $url = "http://localhost:$rpc_port";
        if ($ch == null) {
            $this->ch = curl_init($url);
            $this->chowned = true;
        } else {
            $this->ch = $ch;
            $this->chowned = false;
        }

        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->ch, CURLOPT_USERPWD, $rpc_user . ":" . $rpc_password);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);

        $this->message_id = random_int(0, 400000);
        return $this;
    }

    function __destruct() {
        if ($this->ch && $this->chowned) {
            curl_close($this->ch);
        }
    }

    function getbalance() {
        $this->message_id++;
        $data = [
            'id' => $this->message_id,
            'method' => 'getbalance',
            'params' => []
        ];
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($data));
        return curl_exec($this->ch);
    }

    /**
     * Get the balance of a specific BCH address.
     *
     * First parameter is the bitcoincash address
     * The second parameter is optional, which defaults may change in the future.  
     * They can be one of tokens_only, include_tokens, or exclude_tokens. 
     * 
     * Return is an 'array' of the form { 'confirmed': number } or 
     * { 'confirmed': number, 'unconfirmed': number }.  It may be the second
     * form even with the unconfirmed part being equal to zero.
     * 
     */
    function getaddressbalance($bch_address, $token_filter=false) {
        $this->message_id++;
        $params = [ $bch_address ];
        if ($token_filter) {
          // should be one of tokens_only, include_tokens, or exclude_tokens
          $params.push($token_filter);
        }
        $data = [
            'id' => $this->message_id,
            'method' => 'getaddressbalance',
            'params' => $params
        ];
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($data));
        return curl_exec($this->ch);
    }

    /**
     * Create a new BCH cash address.
     */
    function createnewaddress() {    
        $this->message_id++;
        $data = [
            'id' => $this->message_id,
            'method' => 'createnewaddress',
            'params' => []
        ];
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($data));
        return curl_exec($this->ch);
    }


    /**
     * Add a new payment request.
     *
     * The first parameter is the amount in Bitcoincash.
     * The second parameter is the memo
     * Third, the timeout in seconds
     * Fourth, can be any non-zero to indicate that it should create a new address if we don't have any unused addresses left.
     * Fifth, is the payment_url that the wallet will put an id on the end of like 
     * 'https://example.com/invoice' (note no trailing slash) and the URL returned will be 'https://example.com/invoice/' + some id.
     * Sixth, the index_url base like 'https://example.com/index' (not no trailing slash) and returned will have a slash and then some id.
     * Seventh is the token_request boolean; either 0 or 1.
     * Eigth is the catogory_id.  I think this maybe the token hash.
     */
    function addrequest($amount, $memo='', $timeout=0, $force=0, $payment_url=0, $index_url=0, $token_request=0, $category_id=0){
        $params = [
            $amount,
            $memo,
            $timeout,
            $force,
            $payment_url,
            $index_url,
            $token_request,
            $category_id
        ];

        while ($params[count($params) - 1] === 0) {
            array_pop($params);
        }

        $this->message_id++;  
        $data = [
            'id' => $this->message_id,
            'method' => 'addrequest',
            'params' => $params
        ];

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($data));
        return $response = curl_exec($this->ch);
    }

    /**
     * Sets up the daemon to call a specified URL when a payment is 
     * received to the given BCH address.   
     * This is used to trigger the port scan when payment is received.
     */
    function notify($bch_address, $notify_url) {    
        $this->message_id++;
        $data = [
            'id' => $this->message_id,
            'method' => 'notify',
            'params' => [
                $bch_address,
                $notify_url
            ]
        ];

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($data));

        return curl_exec($this->ch);
    }
}