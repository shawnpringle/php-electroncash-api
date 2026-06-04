# Electron Cash RPC client library for PHP.
PHP class for interacting with the Electron-Cash RPC

* This class provides methods to interact with the Electron Cash wallet via its JSON-RPC interface.
* It reads configuration from a specified file and allows you to perform actions such as creating new addresses, adding payment requests, and checking balances.
  
* Keep a copy of the Electron Cash config file at /var/www/etc/electron-cash/config.  Credentials will be read from there.:
 
 * Usage:
 ```
    $ec = new ElectronCashRPC();
    $balance = $ec->getbalance();
    $balance = $ec->getaddressbalance('qbchaddress');
    $ec->createnewaddress();
    $ec->notify('bchaddress', 'https://example.com/handlenotificatoin.php');
```

## Methods

Three methods are enough to have a working ecommerce site:  createnewaddress, getaddressbalance, and notify.

Below are concise, usage-focused docs for the three primary methods: createnewaddress, getaddressbalance, and notify.

### createnewaddress()
- Description: Requests the Electron Cash wallet to generate a new receiving address.

- Returns: (string) A JSON formatted string or it may set an error
  on the curl handle.
- Example:
```
        $ec = new ElectronCashRPC();
        // ensure there is an address available.
        $response = $ec->createnewaddress();

        if (curl_errno($ec->ch)) {
            echo "{\n";
            echo "  \"success\": false,\n";
            echo "  \"error\": \"" . curl_error($ec->ch) . "\",\n";
            echo "  \"rpc\": \"createnewaddress\"\n";
            echo "}\n";
            return;
        }

        //echo $response;
        $decoded = json_decode($response, true);

        $method = "createwalletaddress";
        if ($decoded == null) {
            error_log("create_invoice: $method: $response" );
            echo "{\n";
            echo "  \"success\": false,\n";
            echo "  \"rpc\": \"$method\",\n";
            echo "  \"error\": \"Returned response was `" . $response . "'\"\n";            
            echo "}\n";
            return;
        } else if ( $decoded['error'] ) {
            echo "{\n";
            echo "  \"success\": false,\n";
            echo "  \"rpc\": \"createnewaddress\",\n";
            //echo "  \"response\": " . json_encode($response)  . "\",\n";
            echo "  \"error\": \"" . json_encode($decoded['error']) . "\"\n";
            echo "}\n";
            return;
        }
```

### getaddressbalance(address)
- Description: Queries the address for its confirmed and unconfirmed balances.
- Parameters:
    - address (string): The address to check.  
- Returns: A JSON string which when decoded will be an associative array with keys 'confirmed', and 'unconfirmed' (numeric values in Bitcoincash ).
- Example:
```
$ec = new ElectronCashRPC();
$response = $ec->getaddressbalance($address);
// Will look like {"result": {"confirmed": "0.00001", "unconfirmed": "0"}, "id": "curltext", "error": null}
$decoded = json_decode($response, true);
```


### notify(address, url)
- Description: Requests that the daemon will send a POST message to the supplied URL whenever the balance for address changes.  
- Parameters:
    - address: Bitcoincash cash address
    - url    : Full URL of where to send the POST request.     - 
- Returns: to do
- Example:
```
$ec = new ElectronCashRPC();
$ec->notify('qbchaddress...', 'http://127.0.0.1/localonly/pay_listener.php');
```

Notes
- When the address balance changes the script will be called and it will be able to read standard input and thatwill look like `{"address": "qbchaddress...", "status": "8ea11ylonghexnumber..."}`
- One should prevent the whole internet from calling this script that will handle notifications.  


### getbalance
- Description: Queries the wallet for its confirmed and unconfirmed balances.
- Returns: A JSON string which when decoded will be an associative array with keys 'confirmed', and 'unconfirmed' (numeric values in Bitcoincash ).
- Example:
```
require_once "../ec.php";

$ec = new ElectronCashRPC();
$response = $ec->getbalance();
$decoded = json_decode($response, true);
if ($decoded == null) {
    echo "getbalance: Did not return a JSON object: $response" ;
    return;
} else if ( $decoded['error'] ) {
    echo "getbalance: Error returned from daemon: " . $decoded['error'] ;
    return;
} else if ( !isset($decoded['result']) ) {
    echo "getbalance: No result field in response: $response" ;
    return;
}
$result = $decoded['result'];
if (isset($result['confirmed'])) {
    $confirmed = $result['confirmed'];
    echo 'confirmed is ' . $confirmed;
}
if (isset($result['unconfirmed'])) {
    $unconfirmed = $result['unconfirmed'];
    echo 'unconfirmed is ' . $unconfirmed;
}
```
