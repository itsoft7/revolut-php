# revolut-php
One file. No dependencies for security reasons.

Revolut API has next bugs:

1. Scope is not supported. When I ask READ I also can WRITE and send payments. It's shame.

2. No possibility to revoke token. Again it's shame. You can ask for new token and not to save it, but it is a crutch.

3. No possibility to add account to counterparty. Delete and create again is bad solution due to old payments linked to old id of counterparty.
So we need add and delete accounts to counterparty.

4. It's really very bad that when I send payment via API no double authentication. I need second independed channel to confirm (sign) payments.
It can be SMS or ENUM.

Revolut API is very unsafe. 

[Revolut Business API Documentation](https://developer.revolut.com/docs/manage-accounts/#introduction-to-the-business-api).

## Installation

```bash
composer require itsoft/revolut
```

## Usage

```bash
cd your_secret_keys_dir/revolut
openssl genrsa -out privatekey.pem 1024
openssl req -new -x509 -key privatekey.pem -out publickey.cer -days 1825
more publickey.cer
```

Add publickey.cer content to [Revolut Business API settings page](https://business.revolut.com/settings/api) and point there your OAuth redirect URI.
Save ClientId and Enable API access to your account.

### Create insecured revolut.cfg.php
This config saves tokens in files. You can save them to database or other places, but for security reasons better not to save them, see the next section Secured revolut.cfg.php. 

```
<?php
$ROOT_PATH = '/www/your-crm...';

require_once $ROOT_PATH . 'vendor/autoload.php';
$path2token = $ROOT_PATH . 'token/revolut.txt';
$path2refresh_token = $ROOT_PATH . 'token/revolut_refresh_token.txt';


$a_token = json_decode(file_get_contents($path2token));
$r_token = json_decode(file_get_contents($path2refresh_token));

$params = [
	'apiUrl' => 'https://b2b.revolut.com/api/1.0', 
	'clientId' => 'aaasssss....',
	'privateKey' => file_get_contents('your_secret_keys_dir/revolut/privatekey.pem'),
	'redirectUri' => 'https://your_site.com/redirect_uri/', //OAuth redirect URI
	'accessToken' => $a_token->access_token,
	'accessTokenExpires' => $a_token->expires,
	'refreshToken' => $r_token->refresh_token,
	'refreshTokenExpires' => $r_token->expires,
	'saveAccessTokenCb' => function ($access_token, $expires) use ($path2token) {file_put_contents($path2token, json_encode(['access_token' => $access_token, 'expires' => $expires]));},
	'saveRefreshTokenCb' => function ($refresh_token, $expires) use ($path2refresh_token) {file_put_contents($path2refresh_token, json_encode(['refresh_token' => $refresh_token, 'expires' => $expires]));},
	'errorUrl' => "/error.php",
	'logError' => function ($error){mail('your_email@domin.com', 'Revolut API Error', $error);}
];


// for debug you can use
// $params['apiUrl'] =  'https://sandbox-b2b.revolut.com/api/1.0';

$revolut = new \ITSOFT\Revolut\Revolut($params);
```

### Secured revolut.cfg.php

```
<?php
$ROOT_PATH = '/www/your-crm...';

require_once $ROOT_PATH . 'vendor/autoload.php';
$params = [
	'apiUrl' => 'https://b2b.revolut.com/api/1.0', 
	'clientId' => 'aaasssss....',
	'privateKey' => file_get_contents('your_secret_keys_dir/revolut/privatekey.pem'),
	'redirectUri' => 'https://your_site.com/redirect_uri/', //OAuth redirect URI
	'accessToken' => '',
	'accessTokenExpires' => '',
	'refreshToken' => '',
	'refreshTokenExpires' => '',
	'saveAccessTokenCb' => function ($access_token, $expires){},
	'saveRefreshTokenCb' => function ($refresh_token, $expires){},
	'errorUrl' => "/error.php",
	'log_error' => function ($error){mail('your_email@domin.com', 'Revolut API Error', $error);}
];


// for debug you can use
// $params['apiUrl'] =  'https://sandbox-b2b.revolut.com/api/1.0';


$revolut = new \ITSOFT\Revolut\Revolut($params);
```

### Code for error.php
```
<?php

print "<h1>Error</h1>";

if (isset($_GET['msg'])) {
    print "<pre>";
    print_r($_GET['msg']);
    print "</pre>";
}
```

### Code for OAuth redirect URI (https://your_site.com/redirect_uri/)
```
require_once 'revolut.cfg.php';

if(isset($_GET['code'])) $revolut->exchangeCodeForAccessToken();
  
//print_r($revolut);
print "<pre><h2>accounts</h2>\n";
print_r($revolut->accounts());

print "<h2>counterparties</h2>\n";
print_r($revolut->counterparties());

print "<h2>getExchangeRate</h2>\n";
print_r($revolut->getExchangeRate(['from'=>'USD', 'to'=>'EUR']));
print_r($revolut->getExchangeRate(['from'=>'EUR', 'to'=>'USD']));

print "<h2>transactions</h2>\n";
print_r($revolut->transactions());
```

### Create payment and other methods

```
$revolut->createPayment($params); 
```
For $params see [tutorials-make-a-payment-create-payment](https://developer.revolut.com/docs/manage-accounts/#tutorials-tutorials-make-a-payment-create-payment) and [api-reference createPayment](https://developer.revolut.com/api-reference/business/#operation/createPayment).

For other methods see https://github.com/itsoft7/revolut-php/blob/master/src/Revolut.php
